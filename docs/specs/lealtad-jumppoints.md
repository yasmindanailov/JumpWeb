# [SPEC] JumpPoints — puntos de fidelización y vales

> Estado: diseño 🟦 en revisión (pendiente de revisión adversarial por otro agente) ·
> Última actualización: 2026-08-24 ·
> Verificado contra código: 2026-08-24 (ModuleBoundariesTest, OrderLedger, AuditLog, CRITICAL_RE del pre-push) ·
> Decisión asociada: `DECISIONES #142` ·
> Se invalida si: el canje deja de ser solo en puerta, o el vale pasa a llevar importe.

Subsistema **D** de la visión de Fase 6, y el **último por dependencia**: necesita el carné de
`identidad-qr-puerta.md` para canjear.

---

## 1. Contexto y problema

La app móvil existe para **fidelizar**, y hoy no hay ningún mecanismo de fidelización: medido el
2026-08-24, **cero** artefactos de puntos, vales o recompensas en todo el árbol.

El problema real no es construir un contador. Es construirlo **sin abrir un agujero de ingresos**, que
es exactamente el tipo de fallo que este proyecto acaba de cerrar dos veces seguidas (`DECISIONES #127`:
mover la fecha de una reserva no re-tarificaba, así que comprar el día barato y pedir el cambio al
sábado salía gratis).

## 2. Objetivo

Un programa de puntos configurable y desactivable que **no toque el núcleo de dinero** y que pueda
explicar cada punto de su saldo.

**Criterios de éxito medibles:**
- El saldo **se deriva del ledger**; no existe columna de saldo — mutación obligatoria.
- Ninguna fila del ledger se actualiza ni se borra.
- Dos canjes simultáneos del mismo saldo producen **un** vale, verificado con `pcntl_fork` sobre MySQL
  real, no con la suite.
- Un vale usado **no se puede volver a usar**.
- El programa se puede **desactivar** sin borrar saldos.

**FUERA de alcance:**
- **Descuentos sobre el precio** (porcentaje sobre producto, sobre total o sobre complementos). Es un
  proyecto de dinero: entra en `PAY-12`, en `OrderCreator`, en los dos ejes del ledger y en la pregunta
  de si un reembolso devuelve el precio con descuento o sin él — y «hasta gastarse N veces» es **otra
  carrera**. `DECISIONES #142` lo aplaza a propósito.
- **Referidos**: caben en este ledger sin cambiar el modelo (§4.3), pero se especifican aparte.
- Pagar con puntos en el checkout. Si algún día se quiere, deja de ser este spec.

## 3. Opciones consideradas

**A · Contador de saldo en `users`.** DESCARTADA. Un contador deriva, y cuando deriva **no hay forma de
reconstruirlo**. Y hay un aviso vivo dentro de este mismo repo: `slots.seats_taken` es una
columna-contador **sin ningún escritor**, que `DEUDA.md` describe como *«trampa para agentes: parece la
verdad del aforo y no lo es»*.

**B · Ledger append-only, saldo derivado (ELEGIDA).** Permite contestar *«¿por qué tengo 340 puntos?»*,
que es la pregunta que el cliente **va a hacer**.

**C · Vale con importe en euros.** DESCARTADA por decisión del owner: un vale con valor monetario es un
**pasivo contable** con implicaciones fiscales, y arrastraría el subsistema entero de vuelta al núcleo
de dinero. **Recompensa en especie**: el vale no tiene valor monetario, no entra en el desglose
financiero y no toca `LedgerSingleSourceTest`.

**D · Canje como descuento en el checkout.** DESCARTADA (owner). **Es la decisión que hace barato todo
lo demás**: canjeando en puerta, el vale nunca se ata a una reserva, así que la pregunta «¿qué pasa con
un vale canjeado sobre una reserva que se cancela?» **deja de existir**.

## 4. Diseño elegido

### 4.1 El ledger

Append-only, una fila por evento:

`user_id · kind (earned|reversed|redeemed|adjusted|expired) · points (con signo) · available_from ·
source_type · source_id · reason · created_by · created_at`

- **No se actualiza, no se borra.** Una corrección es **una fila nueva**.
- **El saldo es la suma**, y el **saldo gastable** es la suma de las filas cuyo `available_from` ya
  llegó.
- **Sin caché en el primer corte.** Son cientos de filas por titular a lo largo de años, con índice. Si
  se mide que hace falta, se cachea **con su invalidación y con un test de que cuadra con el ledger** —
  o no se cachea. `PERF-07`: no optimizar sin medir.

### 4.2 Los puntos se abren DESPUÉS de la visita

⚠️ **El caso que rompe cualquier sistema de puntos ingenuo:**

> Compra → gana 100 puntos → **los canjea** por un vale → pide el reembolso.

El saldo se va a −100 y el vale ya está emitido. Las tres salidas obvias son malas: permitir saldo
negativo, bloquear el reembolso (absurdo, es dinero real), o comerse la pérdida en silencio.

**La salida elegida elimina el problema por construcción**: los puntos se **ganan al pagar** pero se
hacen **gastables después de la visita**.

- La regla se explica sola: *«los puntos de tu reserva se te abonan después de venir»*. Y dice la
  verdad sobre lo que el negocio premia: **premia visitar, no transaccionar**.
- Un reembolso **anterior** a la visita anula puntos que **nunca fueron gastables**.
- ⚠️ Y —esto importa— **no necesita ninguna tarea programada**: cada apunte nace con su fecha de
  disponibilidad y el saldo gastable se deriva. El cron sigue muerto en staging (`#115`) y esto no
  depende de él.

**Dos bordes que hay que resolver:**

1. **Las fuentes sin visita.** Si algún día se dan puntos por reseñar o por referir, esos apuntes no
   cuelgan de ninguna reserva. El modelo ya lo aguanta —`available_from` es **por apunte**, no una
   regla global—: esas fuentes traen la suya. Solo hay que decirlo.
2. ⚠️ **Una reserva puede CAMBIAR de fecha**, y no es hipotético: `PAY-18` hace que mover la fecha
   re-tarifique. Si la reserva se mueve a un mes vista, los puntos no pueden seguir abriéndose en la
   fecha vieja.
   ▶ **Salida preferida**: los puntos de una reserva vencen **mirando la reserva** (un join en la
   lectura). El ledger sigue append-only, no hay nada que actualizar y no hay deriva posible.
   ▶ Alternativa válida: escribir un apunte de reversión y otro de re-ganancia al mover la fecha — más
   rastro, a cambio de más filas y de acoplar el movimiento de fecha a este módulo. Lo decide la
   revisión.

**Reembolsos posteriores a la visita**: caso raro y acotado, se resuelve con un apunte negativo. Si el
saldo quedara negativo, **ajuste con motivo y aviso al operador** — nunca un bloqueo.

### 4.3 Ganar puntos: dos decisiones explícitas

- ⚠️ **Sobre qué base.** Con el modelo de señal, «lo pagado online» y «lo que vale la reserva» son
  números muy distintos, y son precisamente los ejes que `DECISIONES #127` hizo canónicos. Como los
  puntos se abren **después de la visita**, para entonces el cobro en puerta ya está registrado: la
  base es **`pagadoOnline + pagadoPuerta`** leído de **`Booking\Services\OrderLedger`**, y **nunca
  recompuesto** (`LedgerSingleSourceTest`).
- **El redondeo.** Puntos por euro con decimales obliga a decidir: truncar, redondear o acumular
  fracciones. **Truncar** es lo estándar y lo que no genera reclamaciones. Hay que escribirlo, no
  dejarlo al azar del tipo de dato.
- **Desactivable.** Un interruptor maestro. ⚠️ Al desactivar, **los saldos se quedan** —son una
  obligación ya contraída—: lo que se apaga es ganar, y opcionalmente canjear.
- **Configurable desde el panel**: tasa, activación y catálogo de recompensas. Nada de negocio quemado
  en código.

### 4.4 Los vales

`user_id · reward_id · points_spent · issued_at · expires_at · used_at · used_by · cancelled_at`

- **No necesita código propio**: se enseña con el carné del titular y el empleado lo marca usado. Una
  pieza menos.
- ⚠️ **La caducidad no es opcional.** Un vale sin fecha es un pasivo perpetuo del negocio.
- **Marcar usado tiene que ser idempotente** — dos clics no consumen dos veces. Mismo patrón que
  `TicketIssuer` (`PAY-03`).
- **Catálogo de recompensas configurable** (coste en puntos + descripción), en especie.
- ⚠️ **Un refresco cuesta dinero.** No es contable, pero sí operativo: el panel debe poder ver
  **cuántos vales hay emitidos sin usar**, porque eso es stock que alguien repone.

### 4.5 ⚠️ «Fuera del núcleo de dinero» NO exime de la disciplina de concurrencia

Es lo que más fácil se pierde de este spec. Los puntos quedan fuera del dinero —y es cierto para el
ledger y para el checkout— pero **la carrera es idéntica**:

> Dos canjes simultáneos del mismo saldo → **dos vales por el precio de uno.**

Es un `AFORO-01` en miniatura y se resuelve igual: **operación atómica condicionada**, como el CAS de
`PAY-11` (`orders:expire` solo notifica si afectó a 1 fila).

**Y arrastra dos consecuencias que hay que aceptar por adelantado:**

1. ⚠️ **La suite no puede verlo.** `SUITE-04`: SQLite en memoria **no reproduce los locks de InnoDB**.
   Necesita su propio verificador con `pcntl_fork` sobre MySQL real, hermano de
   `redsys:verify-concurrency` y `purchase:verify-oversell`.
2. ⚠️ **El fichero del canje entra en el `CRITICAL_RE` del `pre-push`**, y por tanto tocarlo exige
   `VERIFY_CONC=1`. `CriticalPathGateTest` vigila que esa lista siga cubriendo lo que debe.

▶ Dicho de una vez: **no es dinero, pero se protege como si lo fuera.**

### 4.6 La caducidad de puntos: la única pieza que depende del cron

Los puntos caducan **por inactividad** (decisión del owner), configurable por instalación.

⚠️ **Podría derivarse, y NO debe.** Si la caducidad no deja un apunte en el ledger, la pregunta *«¿por
qué tengo 0 puntos?»* se queda sin respuesta — y ésa es justamente la propiedad por la que se eligió un
ledger. La caducidad es **un apunte real** (`kind = expired`), escrito por una tarea.

Consecuencias en cadena:

- ❗ **Depende del scheduler**, que es el bloqueo vivo de `#115`: el crontab está bien y `schedule:run`
  funciona a mano, pero **no hay demonio cron en staging**. Se construye y se prueba en local; **no se
  verifica de punta a punta** hasta que el owner lo desbloquee.
- **Hay que avisar antes de caducar.** Un saldo que desaparece sin aviso genera reclamaciones. Y ese
  aviso es una notificación nueva → **obligatoriamente `ShouldQueue`** (`PAY-14`), lo que la mete en la
  misma cola que tampoco corre sin cron.

▶ **Eso ordena el corte**: el resto de JumpPoints funciona sin cron; **la caducidad y su aviso van los
últimos** y llegan con el cron resuelto.

### 4.7 Dónde vive: un módulo nuevo

**`Loyalty`, como sexto módulo.** Los puntos no son identidad ni son reserva, y meterlos en `Identity`
dejaría cinco ficheros que no son identidad dentro de Identity.

El grafo lo hace barato: **una línea en `ModuleBoundariesTest::ALLOWED`** declarando
`['Platform', 'Booking\Contracts']`, más `User` por la exención de kernel compartido. Y
`test_every_module_declares_its_allowed_arrows` **obliga** a declararlo, que es exactamente lo que se
quiere. La máquina para esto se montó en Fase 2; usarla.

### 4.8 Superficies

- **Cliente** (cajón): saldo, historial, catálogo de recompensas, canjear, mis vales.
- **Puerta**: vales activos + saldo, con la acción de marcar usado (`identidad-qr-puerta.md` §4.6).
- **Panel**: configuración, catálogo, ajuste manual con motivo, vales emitidos sin usar.
- **API**: familia nueva bajo el grupo autenticado. ⚠️ **`openapi/v1.yaml` PRIMERO**, que manda sobre
  el código (`DECISIONES #21`).

⚠️ **Aviso de coste**: ésta es **la adición más cara al cajón** de los cuatro subsistemas —son varias
pantallas—. El techo vivo es `SidebarBundleBudgetTest::SIDEBAR_CHUNK_MAX_KB` y la holgura al diseñar
esto era **inferior a 1 KiB**. Hay que subirlo **con su medición y su párrafo**, que es el ritual del
proyecto; conviene saberlo ahora y no con el gate en rojo. ⚠️ La cifra no se copia aquí: vive en su test.

## 5. Impacto en invariantes

| ID | Impacto |
|---|---|
| **PAY-11** | Se CITA como patrón: el canje es un CAS atómico, como la caducidad de pedidos |
| **PAY-14** | Se APLICA: el aviso de caducidad es `ShouldQueue` |
| **PAY-03** | Se CITA como patrón: marcar un vale usado es idempotente, como `TicketIssuer` |
| **SUITE-04** | Se AMPLÍA: nace un tercer verificador de concurrencia, y el fichero del canje entra en el `CRITICAL_RE` del `pre-push` |
| **RGPD-01** | Se AMPLÍA: `anonymize()` tiene que decidir qué hace con el ledger de puntos y los vales de un titular suprimido |
| — | **Ninguna se relaja.** El vale en especie mantiene el subsistema fuera de `INVARIANTES` §1 |

## 6. Plan de verificación empírica

**Guardas ejecutables:**
1. **El saldo se deriva del ledger** — no existe columna de saldo.
2. **Ninguna fila del ledger se actualiza ni se borra.**
3. **El canje es atómico** — verificado con `pcntl_fork` sobre **MySQL real**, no con la suite.
4. **Un vale usado no se puede volver a usar.**
5. **Los puntos no disponibles no se pueden canjear.**
6. **La base de cálculo sale de `OrderLedger`** y no se recompone.

**Comprobación empírica:**
- Ejercitar la secuencia **comprar → ganar → intentar canjear antes de la visita → reembolsar**, y
  comprobar que el saldo gastable **nunca fue positivo**.
- Mover la fecha de una reserva y comprobar que la disponibilidad de sus puntos **se mueve con ella**.
- ⚠️ La caducidad, **con el reloj congelado** (`SUITE-03`), y su aviso en Mailpit.
- Medir el chunk del cajón antes y después.

## 7. Revisión y decisión

Diseñado en sesión de arquitectura con el owner el 2026-08-24 (`DECISIONES #142`).

❗ **PENDIENTE de revisión adversarial por otro agente** (`CONVENCIONES` §5). ⚠️ Lo que más merece un
revisor hostil aquí es §4.2: si el modelo de disponibilidad tiene un hueco, es un **agujero de
ingresos**, que es la familia de fallo que este proyecto acaba de pagar dos veces.
