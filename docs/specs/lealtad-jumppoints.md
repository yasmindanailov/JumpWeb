# [SPEC] JumpPoints — puntos de fidelización y vales

> Estado: diseño 🟦 **REVISADO** (revisión adversarial hecha — **§8**) · pendiente del ✅ del owner ·
> ❗❗ **§8 REESCRIBE el encuadre de §4.2 y hay que leerlo ANTES**: no hay una regla global de
> disponibilidad, hay **una por FUENTE** (`[DECIDIDO owner]`). La visita se acredita en la pantalla de
> puerta; la compra conserva el retardo. **§8.2: abrir los puntos de compra al instante reabre el
> agujero de ingresos desde un campo del panel.**
>
> Estado anterior: diseño 🟦 en revisión ·
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

✅ **REVISADA el 2026-08-25** por un segundo agente (`CONVENCIONES` §5) — **§8**. La revisión encontró
que **§4.2 descansaba sobre un hecho que el sistema no podía observar** (nadie sabe si un cliente
vino) y el owner resolvió el modelo: **puntos por FUENTE, configurables** — visita acreditada en
puerta + compra pagada. ❗ **§8.2 es lo que no se puede perder**: la fuente «compra» reabre el agujero
de ingresos si se abre al instante.

⚠️ Lo que más merece un
revisor hostil aquí es §4.2: si el modelo de disponibilidad tiene un hueco, es un **agujero de
ingresos**, que es la familia de fallo que este proyecto acaba de pagar dos veces.

---

## 8. Revisión adversarial — 2026-08-25

> Segundo agente, `CONVENCIONES` §5. La spec pedía revisor hostil sobre **§4.2**, «porque si el modelo
> de disponibilidad tiene un hueco es un agujero de ingresos». Lo tenía, y no donde se buscaba.

### 8.0 Lo que aguantó

Re-medido el 2026-08-25: **cero artefactos de puntos, vales o recompensas** en `app/`,
`database/migrations`, `resources/js` y `routes/` — sigue siendo construcción desde cero ·
`slots.seats_taken` es exactamente el contador muerto que §3·A usa de escarmiento (la migración lo
llama «cache de display (verdad = pedidos)» y `SlotResource` avisa de que **está muerta**) · y §4.7 es
correcta: `ModuleBoundariesTest::ALLOWED` es un mapa declarativo y
`test_every_module_declares_its_allowed_arrows` **obliga** a registrar el módulo nuevo, así que
`Loyalty` cuesta **una línea**.

### 8.1 ❗ El hallazgo: «después de la visita» no era un hecho observable

§4.2 hace descansar **toda** la defensa del agujero *comprar → ganar → canjear → reembolsar* en un
instante: el de la visita. Medido el 2026-08-25: **el sistema no sabe si alguien vino.**

- `tickets` **tiene** las columnas del ciclo (`status` con `purchased|prepared|redeemed|void`,
  `prepared_at`/`prepared_by`, `redeemed_at`/`redeemed_by`)…
- …y **nadie las escribe**: cero escritores en `app/`, `resources/` y `routes/`. Lo confirma la spec
  hermana: *«no hay puerta que canjee, porque el ciclo de canje se retiró»*
  (`identidad-qr-puerta.md` §1), que además declara el canje digital **fuera de su alcance**.

▶ El único sustituto disponible era «ya pasó la hora de la franja», que **es otro hecho**: un
*no-show* cobraría puntos, justo lo contrario de lo que §4.3 dice que el programa premia.

#### ✅ [DECIDIDO owner, 2026-08-25] — los puntos tienen FUENTES, y son configurables

> «El cliente recibirá JumpPoints de manera diferente, será configurable. Un caso es: el cliente viene
> al parque, enseña su QR, al enseñar su QR el empleado abre su página de identificación en el panel
> de admin de empleado, entonces ahí se cuentan puntos. Cuando el cliente compra cualquier producto y
> el pago es satisfactorio, ahí se apuntan “x” puntos.»

Eso **reencuadra §4.2**: no hay una regla global de disponibilidad, hay **una regla por fuente**.

| Fuente | Cuándo nace el apunte | Cuándo es gastable | Riesgo de reversión |
|---|---|---|---|
| **Visita** | Cuando el empleado **acredita** la visita desde la pantalla de puerta | **Ya** — la visita es un hecho consumado | **Ninguno.** No se puede deshacer haber venido |
| **Compra** | Al confirmarse el pago | ⚠️ **NO inmediatamente** — ver §8.2 | Reembolso |

▶ **La buena noticia**: el modelo del ledger **ya lo aguanta sin cambios**. §4.2 ya dice que
`available_from` es **por apunte, no una regla global**, y ya previó «las fuentes sin visita». Lo que
cambia no es la tabla: es que la regla de apertura deja de ser una y pasa a ser una por fuente.
▶ **Y la visita ya no es invisible**: la acredita la pantalla de puerta. Eso está escrito en
`identidad-qr-puerta.md` §8.3, con la guarda que exige — **acto explícito e idempotente por cliente y
día, nunca un efecto secundario de abrir la ficha**, porque la ficha se abre varias veces por cliente
y también tecleando un correo.
❗ **Consecuencia de orden**: JumpPoints pasa a **depender de la pantalla de puerta** para su fuente
principal. El tracker ya ponía **A antes que D**; ahora es una dependencia dura, no una preferencia.

### 8.2 ⚠️ Y la fuente «compra» REABRE el agujero que §4.2 cerraba

Con puntos al confirmarse el pago y gastables al instante, la secuencia vuelve entera:

> compra → gana → **canjea** → pide el reembolso → el vale ya está emitido y el saldo se va a negativo.

**No es hipotético en este repo**: `#146` acaba de medir un reembolso que devolvió **36,00 € donde se
debían 4,00**. El dinero vuelve; el vale en especie ya salió por la puerta.

▶ **La salida no exige inventar nada** — es el mismo dispositivo de §4.2, aplicado solo a esta fuente:
el apunte de compra nace con `available_from` **posterior al momento en que el dinero deja de poder
volver**. Con el modelo de dos fuentes, lo natural es la **fecha de la franja** (y §4.2·2 ya resolvió
que se mira **la reserva**, con un join en la lectura, para que mover la fecha mueva la apertura sin
tocar el ledger).

❗❗ **Y de aquí sale una regla de producto que hay que escribir antes de construir el panel**: lo
configurable es **cuántos** puntos da cada fuente, **no cuándo se abren**. Un ajuste que permita
«compra → disponible ya» reabre el agujero desde un formulario, sin que nadie lo revise y sin que nada
falle. Si el owner quiere esa opción, es una decisión con precio medido — no un campo más.

### 8.3 Consecuencias en cadena de las dos fuentes (que la spec debe absorber)

1. **§4.3 «sobre qué base» se parte en dos.** `pagadoOnline + pagadoPuerta` desde `OrderLedger` sigue
   siendo la base de la fuente **compra**. La fuente **visita** no tiene base monetaria: es una
   cantidad fija por visita acreditada. Escribirlo evita que alguien intente derivar la segunda del
   ledger, que no tiene de dónde.
2. **Los reembolsos solo revierten los puntos de COMPRA.** Los de visita no se tocan: vino. Esto sale
   gratis del modelo y cierra el caso «reembolso posterior a la visita» de §4.2 mucho mejor que el
   ajuste manual que allí se proponía — el ajuste queda para el residuo, no para el caso normal.
3. **El aviso de §4.4 se refuerza**: si los puntos de visita son fáciles de ganar, el catálogo de
   recompensas es stock que alguien repone. «Cuántos vales hay emitidos sin usar» deja de ser un
   informe cómodo y pasa a ser operativo.

### 8.4 Precisión: «lo mata por construcción» está sobrevendido

§4.2 dice que la salida elegida «elimina el problema **por construcción**». Con una sola fuente
cerraba la ventana **anterior** a la visita; la **posterior** la resolvía «un ajuste con motivo y
aviso al operador», que es una excepción operativa, no una construcción. Con dos fuentes la frase es
más cierta que antes (§8.3·2), pero conviene decirla con su alcance: **cierra la ventana de la fuente
que puede revertirse, y solo mientras la apertura no sea configurable** (§8.2).

⚠️ La fila de enrutado de `CLAUDE.md` repetía la versión sin matizar. Corregida.

### 8.5 Veredicto

**El ledger, el vale en especie, el canje en puerta y la disciplina de concurrencia de §4.5 son
correctos y no se tocan.** Lo que la revisión cambia es §4.2: deja de ser una regla global y pasa a
ser **una regla por fuente**, con la fuente «visita» apoyada en la pantalla de puerta y la fuente
«compra» conservando el retardo que impide el agujero de ingresos. **§8.2 es lo que no se puede
perder**: es la línea entre un programa de fidelización y un grifo.

---

## 9.bis ❗ La VISITA ya no se puede registrar: su botón se retiró y vuelve AQUÍ

`[DECIDIDO owner, 2026-08-28]` (`DECISIONES #234`): la tarjeta de «Visita» de la pantalla de puerta
**se retiró hasta que exista JumpPoints**, que es lo único que da sentido a acreditar una visita.
Cuando se diseñe la ejecución de este subsistema hay que decidir **dónde y cómo vuelve**.

⚠️ **Consecuencia y es de DATOS, no de pantalla**: era el ÚNICO sitio desde el que se registraba una
visita, así que **`customer_visits` deja de crecer desde el 2026-08-28**. Cuando esto arranque, el
histórico anterior a la fecha en que se reponga el botón **no existirá**, y eso afecta a §8.1: el
hecho observable que justificaba la dependencia `A → D` sigue construido, pero sin escritor.

**La maquinaria está entera y probada** —`ValidarRegistro::registerVisit()` (idempotente por día,
con el operador y auditoría), `CustomerVisit`, `GateProfileData::visitRegisteredToday` y
`GateVisitsTest`—: lo único que falta es su botón.

▶ **Tres preguntas que el diseño de este subsistema tiene que contestar**, y que la retirada deja
planteadas en vez de resueltas:
1. ¿Acreditar la visita es un acto del empleado (un botón) o se deriva de algo que ya ocurre
   —canjear una entrada, por ejemplo—? `identidad-qr-puerta.md` §8.3 ya avisa de que **no puede
   colgar de «se abrió la ficha»**: la ficha se abre varias veces por cliente.
2. Si vuelve como tarjeta en la puerta, **hay que revisar el reparto de columnas del kiosco**: hoy
   cuadra con CUATRO tarjetas repartidas en dos pilas (`panel-navegacion.md` §8).
3. ¿Se recupera de algún modo el histórico perdido, o los puntos empiezan a contar el día que se
   reponga? Es una decisión de producto, no técnica.

---

## 9. Resumen de UNA página para el ✅ del owner (2026-08-28, carril A; `DECISIONES #212`·3)

> El owner pidió repasar la spec antes de aprobarla («quiero repasarla antes»). Esto es lo que dice,
> sin añadir nada: cada línea remite a su sección. **No se diseña la ejecución hasta su ✅.**

**Qué es (§2, §4.1, §4.4).** Un programa de puntos («JumpPoints») con **ledger append-only** —una
fila por evento, nunca se actualiza ni se borra; el saldo es la suma— y **vales en especie**
(catálogo configurable: coste en puntos + descripción) que se canjean **en la puerta**, enseñando el
carné: el empleado los marca usados (idempotente, como las entradas). Sin dinero de por medio: el
subsistema queda **fuera del núcleo de pagos** (§5), pero **se protege como si fuera dinero** (§4.5).

**Cómo se ganan (§8, `[DECIDIDO owner]` 2026-08-25).** No hay una regla global: hay **una por
FUENTE**, y lo configurable es **cuántos** puntos da cada fuente, **no cuándo se abren**:
- **Visita** acreditada en la pantalla de puerta («Registrar visita», el hecho que ya existe:
  `customer_visits`, `identidad-qr-puerta.md` §9.4) → puntos **disponibles al instante**.
- **Compra** pagada → puntos que se abren **después de la visita** (§4.2), sobre lo pagado online +
  en puerta según el ledger de dinero (§4.3), **truncando** los decimales. ❗ **§8.2: si se abrieran al
  pagar, se reabre el agujero** «compra → canjea → reembolsa» que §4.2 cierra por construcción.
- Un reembolso anterior a la visita anula puntos que **nunca fueron gastables**; posterior, un apunte
  negativo y, si el saldo quedara en rojo, ajuste con motivo y aviso al operador — nunca un bloqueo.
- Si una reserva **cambia de fecha**, sus puntos se abren mirando la reserva (§4.2·2), no la fecha vieja.

**Cómo caducan (§4.6).** Por **inactividad**, configurable en meses, con **aviso previo** por correo.
Es la ÚNICA pieza que depende del **cron**, que sigue sin demonio en staging (`#115`): se construye y
se prueba en local **la última**, y no se verifica de punta a punta hasta que el cron se desbloquee.

**Dónde se ve (§4.8).** Cajón: saldo, historial, catálogo, canjear, mis vales — ⚠️ **la adición más
cara al cajón** de la Fase 6 (varias pantallas; el techo del chunk se sube por feature, como hoy).
Puerta: vales activos + saldo, marcar usado. Panel: interruptor, tasas por fuente, catálogo, ajuste
manual con motivo, **vales emitidos sin usar** (es stock que alguien repone). API primero
(`openapi/v1.yaml` manda).

**Lo que cuesta de verdad (§4.5, §5).** El canje es una carrera idéntica al aforo: dos canjes a la vez
= dos vales por el precio de uno. Se resuelve con una operación atómica condicionada, **su fichero
entra en el `CRITICAL_RE` del `pre-push`** y necesita **su propio verificador con `pcntl_fork` sobre
MySQL real** (la suite en SQLite no puede verlo, `SUITE-04`). Y `anonymize()` tiene que decidir qué
hace con el ledger y los vales de un titular suprimido (`RGPD-01`).

**Lo que te toca decidir para el ✅** (con la propuesta del agente delante):
1. **El modelo de §8 tal cual** — dos fuentes, la compra abre tras la visita, cuántos puntos por
   fuente es un ajuste del panel. *Propuesta: ✅.*
2. **Las tasas iniciales** (ajustes, no código): puntos por **visita** y puntos por **euro** de
   compra. *Propuesta: 10 por visita, 1 por euro; se cambian desde el panel.*
3. **La caducidad**: meses de inactividad y con cuánta antelación se avisa. *Propuesta: 12 meses,
   aviso 30 días antes.*
4. **El catálogo inicial** de recompensas (nombre + coste): tres o cuatro entradas bastan para
   arrancar; se editan desde el panel.
5. **Al anonimizar** un titular: *propuesta: el ledger se conserva SIN titular (sumas históricas del
   parque) y sus vales sin usar se cancelan* — o se borra todo con la cuenta.
6. **El orden de corte** (§4.6 lo impone en parte): ledger + fuentes → cajón → puerta → panel →
   caducidad al final. *Propuesta: ese.*
