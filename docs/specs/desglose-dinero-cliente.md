# [SPEC] El DESGLOSE de dinero que ve el cliente — auditoría y marco

> Estado: **🟦 en revisión** (auditoría CERRADA · **marco COMPLETO y DECIDIDO**, §10) ·
> Última actualización: 2026-08-24 · Decisiones asociadas: `DECISIONES #127` y **`#128`**.
>
> ✅ **TANDAS A y B EJECUTADAS** (§15, §16) y ✅ **los tres defectos de LECTURA, también** (§17.1 →
> **§18**): el ancla de caja se ve siempre que haya habido un cobro —con su método y su fecha—, la
> cantidad va con su sustantivo y la nota de la reserva dejó de llamar «señal» a lo que no lo es.
> ▶ **Lo que queda es la TANDA C** (§14.4), que es pantalla y no toca dinero.
>
> ✅ **EL MARCO YA ESTÁ ENTERO** (§10, decidido el 2026-08-24), que es la condición que el owner puso
> para tocar esto. **Empieza por §9 y §10**: la tercera auditoría encontró **cuatro defectos de
> DOMINIO** que las dos anteriores no llegaron a construir, y el plan de tres tandas del tracker está
> **mal dimensionado** — la tanda 1 no es «riesgo cero». El plan revisado es §11.
>
> ⚠️⚠️ **Esta spec NO se implementa a trozos** (encargo del owner, 2026-08-24): es la superficie más
> crítica del producto y se toca una vez, con todo decidido.
>
> ⚠️ **Antes de tocar nada**: `docs/INVARIANTES.md` §1 (PAY) y `docs/sistemas/DEPOSITO.md`.

## 1. Qué se auditó, y por qué

El owner preguntó si el desglose que ve el cliente **es cierto**, y si el desglose del PANEL y el del
cliente son **dos fuentes de verdad**. Las dos preguntas se contestaron midiendo: contra el dominio y
contra datos reales, no leyendo la pantalla.

## 2. Veredicto 1 — el desglose que se enseña hoy es ARITMÉTICAMENTE cierto

`DEMO-0001` (pack con señal, sin incidencias), medido:

    Subtotal              98,00      = Order.total
    Pagado online         68,00      = 98 − 30
    A cobrar en el parque +30,00     = ajuste `deposit_remainder`; el ↳ suma 30,00
    Total                 98,00      = 68 + 30  ✓

Caso mixto **realista** (resto de señal 30,00 + subida de precio por edición 12,00): `110,00 = 68,00 +
42,00`, con el `↳` sumando `12,00 + 30,00 = 42,00`. ✓

⚠️ **Y una corrección de método que conviene dejar escrita.** La primera simulación del caso mixto creó
un `extra_due` **sin subir el precio del ítem**, y eso pintaba fallos aparatosos —«Total 98 debiendo
42», «Pagado online» cayendo a 56— que **no existen**: el flujo real
(`ViewOrder::…applyExtraDue`) sube el precio *y después* registra el extra. Un fixture que no reproduce
el flujo real inventa defectos tan bien como los oculta.

## 3. Veredicto 2 — NO hay dos fuentes de verdad, y está guardado

Medido: **una sola aritmética**, con su identidad declarada y aseverada.

| Fuente | Qué es | Dimensiones |
|---|---|---|
| `Booking\Services\ReservationFinancials` | El desglose de UNA reserva. Su docblock se declara «fuente ÚNICA del bloque de totales del producto para TODAS las superficies» | `valor`, `pagadoOnline`, `aCobrarPuerta`, `cobradoPuerta`, `devuelto`, `pendienteReembolso` |
| `Booking\Services\OrderFinancialSummary` | El agregado del pedido | 11 campos + 7 derivados |

**Identidad declarada**: `valor = pagadoOnline + aCobrarPuerta + cobradoPuerta`.

**Medido hoy sobre datos reales**, y reconcilia en las SEIS dimensiones a los dos niveles:

    DEMO-LEDGER (una reserva cancelada)
      Cumpleaños Jump  valor 9100 = online 4300 + aCobrar    0 + cobrado 4800   CUADRA
      Jump · 1 hora    valor    0 = online    0 + aCobrar    0 + cobrado    0   CUADRA  (pdteReemb 2300)
      Jump · 1 hora    valor 1900 = online 1900 + aCobrar    0 + cobrado    0   CUADRA
      Σ reservas: valor 11000 · online 6200 · aCobrar 0 · cobrado 4800 · pdteReemb 2300
      El pedido:  valor 11000 · online 6200 · aCobrar 0 · cobrado 4800 · pdteReemb 2300

**Y hay guardas.** `Admin\Orders\OrderFinancialInvariantsTest` existe precisamente para esto —nació de
una auditoría que marcó como hallazgo ALTO que la regla vivía en TRES sitios «coincidiendo por
disciplina + tests, no por construcción»— y pinea **cinco invariantes** sobre **seis escenarios**:

1. por reserva, `valor == pagadoOnline + aCobrarPuerta + cobradoPuerta`;
2. `Σ cards valor == totalFinalNeto()`;
3. `Σ cards aCobrarPuerta == pendingAtGate()`;
4. `Σ cards pendienteReembolso == pendienteDevolucion()`;
5. `Σ itemPendingRefundCents() == pendienteDevolucion()`.

Más `ReservationFinancialsTest` (15 casos) y `OrderFinancialSummaryTest` (21).

▶ **Conclusión: el riesgo de divergencia que el owner temía no está en la aritmética.** Está en la
PROYECCIÓN — qué dimensiones publica la API y con qué rótulo.

## 4. Veredicto 3 — lo que SÍ está mal, medido

### 4.1 El cliente ve una proyección EMPOBRECIDA de la fuente única

`ReservationFinancials` calcula **seis** dimensiones. `OrderItemResource` publica **dos**
(`paid_online_cents`, `gate_remainder_cents`). No viajan `cobradoPuerta`, `devuelto`,
`pendienteReembolso` ni `valor`. En el pedido, `OrderResource` no publica `grossPaidOnline` ni
`extraDueResolved + depositRemainderResolved`.

**El panel las lee todas** (`order-totals.blade.php` computa `$pagadoPuerta`, `$brutoOnline`, y usa
`reservationFinancialsByPrincipal()` para desglosar cada dimensión por reserva).

### 4.2 ⚠️⚠️ Al cliente le falta la línea que hace CUADRAR el bloque

El panel tiene `order_financial.pagado_puerta` = «Pagado en el parque». **El cliente no la tiene.** Y
es exactamente la que falta para que su columna sume:

    Lo que ve el cliente hoy          Con la línea que falta
    Subtotal              133,00      Subtotal              133,00
    Pagado online          62,00      Pagado online          62,00
    (nada)                            Pagado en el parque     48,00   ← 62 + 48 = 110 ✓
    Pdte. devolución      −23,00      Pdte. devolución      −23,00
    Total                 110,00      Total                 110,00

**48,00 € que el cliente entregó en recepción no aparecen en ninguna parte de su propio historial.**

### 4.3 ⚠️ Y mezcla DOS bases en la misma columna

«Subtotal» es `Order.total` (**133,00** = valor original), mientras que todas las demás líneas
descomponen `productsValue` (**110,00** = valor actual). Son bases distintas apiladas sin decirlo, y es
lo que hace el bloque ilegible tras una cancelación. **El panel no tiene ese problema porque solo
enseña `valor_final`**; el «Subtotal» es del cliente y no tiene hermano en el panel.

### 4.4 ⚠️ «Señal 68,00 €» es la palabra equivocada en la tarjeta de la reserva

`tickets.deposit_card_note` = «Señal :deposit · :rest en el parque», alimentada con
`paid_online_cents`. Para `DEMO-0001` la señal del pack son **60,00 €**; los otros 8,00 € son los
calcetines, cobrados íntegros. El número es «pagado online de esta reserva», no una señal.

### 4.5 ⚠️⚠️ El MISMO importe se deriva con DOS FÓRMULAS distintas, y nada las cruza

| Superficie | Fórmula de «Pagado online» |
|---|---|
| Panel | `valorFinal − aCobrar − pagadoPuerta` (clamp en el AGREGADO) |
| API / cliente | `Order::onlineDueCents()` = `Σ max(0, charged − extraDue − depositRemainder)` (clamp POR ÍTEM) |

Son algebraicamente iguales en el caso normal —medido: las dos dan **6.200** en `DEMO-LEDGER`— y
**difieren en cuanto un ítem tenga un `extra_due` mayor que su propio valor**: el clamp por ítem lo
corta a 0, el del agregado lo resta entero.

▶ **Y no hay cruce.** De los cinco invariantes de `OrderFinancialInvariantsTest`, ninguno asevera
`Σ cards cobradoPuerta == extraDueResolved + depositRemainderResolved`, ni compara las dos fórmulas del
importe online, ni toca `grossPaidOnline`. **Ésa es la divergencia real que hay hoy**: dos derivaciones
del mismo número, que coinciden por álgebra y no por construcción.

### 4.6 Por qué nada de esto cayó en rojo

`OrderFinancialSummaryTest` verifica **cada cifra aislada**; `OrderFinancialInvariantsTest` cruza
**cuatro** de las seis dimensiones. Ninguno asevera **lo que la pantalla del cliente enseña**: que las
líneas visibles sumen. Y en `INVARIANTES.md`, `PAY-01`…`PAY-15` cubren cobrar, reembolsar y la puerta
de la señal — **el desglose que lee el cliente no tiene invariante**.

⚠️ Todo lo de §4 es **heredado**, no lo introdujo `#126`: verificado en git que la página retirada
`/mi-cuenta/pedidos` pintaba estas mismas líneas con las mismas fuentes
(`@php($senalOnline = $order->onlineDueCents())`).

## 4.bis LA AUDITORÍA SISTEMÁTICA — 19 escenarios × 9 identidades

Encargo del owner (2026-08-24): «*revisa todo el desglose con diferentes casos, de manera rigurosa y
empírica*». Se montó una sonda —**instrumento de medida, no guarda**: imprime la matriz completa en vez
de parar en el primer fallo— y se recorrieron los 19 escenarios que el dominio sabe distinguir.

**Las nueve identidades evaluadas:**

| | Identidad | ¿Guardada hoy? |
|---|---|---|
| **A** | por reserva: `valor = pagadoOnline + aCobrarPuerta + cobradoPuerta` | sí (`ReservationFinancialsTest`, `OrderFinancialInvariantsTest` inv. 1) |
| **B1** | `Σ cards valor == totalFinalNeto()` | sí (inv. 2) |
| **B2** | `Σ cards aCobrarPuerta == pendingAtGate()` | sí (inv. 3) |
| **B3** | `Σ cards cobradoPuerta == extraDueResolved + depositRemainderResolved` | **NO** |
| **B4** | `Σ cards pendienteReembolso == pendienteDevolucion()` | sí (inv. 4) |
| **B5** | `Σ cards devuelto == effectiveRefunded()` | **NO** — y el motivo importa: `assertReconciles()` **acumula `$sumDev` y nunca lo asevera**. Variable muerta = hueco sin nombre |
| **C** | las DOS fórmulas del importe online coinciden | **NO** |
| **D** | `Σ gateBreakdownLines == pendingAtGate()` | parcial |
| **E** | **LEY DE CAJA**: `valor = brutoOnline + cobradoPuerta + aCobrarPuerta − devuelto − pdteDevolución` | **NO** |

**Escenarios**: plano · plano+complemento · señal pendiente · señal cobrada · señal + complemento
íntegro (PAY-10) · subida pendiente · subida cobrada · señal+subida (los dos buckets) · cancelado sin
reembolsar · cancelado + reembolso hecho · reembolso parcial sobre ítem vivo · **reembolso legacy** ·
señal cuyo principal se cancela · complemento cancelado con principal vivo · `free_quantity` · sin
franja · pedido pendiente sin pago · mixto con reembolso · mixto sin reembolsar.

### Resultado

    A reserva     se cumple en los 19
    B1 valor      se cumple en los 19
    B2 aCobrar    se cumple en los 19
    B3 cobrado    se cumple en los 19
    B4 pdteRee    FALLA en 1: reembolso LEGACY
    B5 devuelto   FALLA en 1: reembolso LEGACY
    C 2fórmulas   se cumple en los 19
    D ↳ suma      se cumple en los 19
    E ley caja    FALLA en 2: reembolso parcial sobre ítem vivo · pedido pendiente sin pago

▶ **El cálculo es ROBUSTO.** Seis de las nueve identidades se cumplen en los 19 escenarios, incluidas
las tres que hoy **nadie guarda** (B3, C) — o sea que los huecos existen pero no hay daño pasando por
ellos.

### 4.bis.1 El ÚNICO defecto: el reembolso LEGACY

Un reembolso anotado **solo** en `Order.refund_amount_cents`, sin fila `PaymentRefund` (los pre-#142,
según el docblock de `effectiveRefunded()`). Medido:

    tarjeta A   valor=1000  online=1000  devuelto=   0  pdteReemb=   0
    tarjeta B   valor=   0  online=   0  devuelto=   0  pdteReemb=1000   ← el ítem cancelado
    Σ TARJETAS                          devuelto=   0  pdteReemb=1000
    EL PEDIDO                           devuelto=1000  pdteReemb=   0
    Σ itemPendingRefundCents = 1000

**Las tres fuentes discrepan.** El PEDIDO sabe que el reembolso salió —`effectiveRefunded()` es
legacy-safe: `max(filas, columna)`— y dice «nada pendiente». Las TARJETAS y el helper por ítem **solo
leen filas `PaymentRefund`**, así que dicen «10,00 € pendientes de devolver» de dinero **ya devuelto**.

▶ **Causa raíz**: la legacy-safety existe a nivel de PEDIDO y no a nivel de ÍTEM — y no puede
existir limpiamente, porque la columna agregada **no tiene atribución por ítem**.
▶ **Efecto en el cliente**: su pantalla anunciaría «Pendiente de devolución» de algo ya devuelto.
▶ **Y es exactamente el escenario que los seis casos de `OrderFinancialInvariantsTest` no incluyen**,
aunque sus invariantes 4 y 5 lo cazarían. El hueco no está en la aserción: está en el fixture.

⚠️ **¿Es alcanzable en JumpWeb?** Medido en la BD de desarrollo: **0 pedidos con reembolso** de
ningún tipo, así que aquí no se puede saber. Y **JumpWeb es el PRODUCTO**: una instalación limpia no
tiene datos pre-#142. La rama legacy podría ser código heredado inalcanzable. **Es una pregunta para el
owner**, no una que el código pueda contestar.
▶ **[RESUELTO 2026-08-24, §9.1]** El código SÍ contesta la mitad: la rama legacy es **inalcanzable**
(ningún escritor la produce), y el owner decidió que **JumpWeb solo instala limpio** → es código muerto
y se retira en la tanda A.

### 4.bis.2 La LEY DE CAJA y sus dos excepciones legítimas

`valor = brutoOnline + cobradoPuerta + aCobrarPuerta − devuelto − pendienteDevolución`

Se cumple en **17 de 19**. Los dos que no, **no son defectos**:

- **pedido pendiente sin pago** — no ha entrado nada todavía; la ley solo aplica a pedidos cobrados;
- **reembolso parcial sobre un ítem VIVO** — dinero devuelto **sin reducir el producto** (una
  compensación comercial). La ley supone que todo reembolso responde a una bajada de valor, y ésta no.

▶ **Ésa es la invariante que falta en `INVARIANTES.md`**, con sus dos excepciones escritas. Hoy no
existe ninguna que cubra el desglose que lee el cliente.

### 4.bis.3 Dos correcciones de MÉTODO que conviene dejar escritas

⚠️ **Dos de las nueve identidades estaban mal especificadas en el primer intento y acusaban al código
de fallos que no existen.** La versión inicial restaba «pendiente de devolución» de la descomposición
por canal, y eso dio **7 falsos fallos**: `valor = online + aCobrar + cobrado` ya cierra por sí sola, y
`devuelto`/`pendienteReembolso` son **otro eje** —dinero que ya no respalda producto—, no deducciones
del valor.

⚠️ **Y un fixture que sobrepaga inventa defectos igual de bien que los oculta.** El escenario mixto
pagó 9.000 online cuando lo cobrable eran 7.800, y ese desfase de 1.200 apareció como un descuadre de
«pendiente de devolución» **que no existe**. `OrderFinancialInvariantsTest` tiene un
`syncTotalToOnline()` precisamente para eso. Es la tercera vez en esta auditoría que un fixture irreal
produce un falso positivo (la primera fue el `extra_due` sin subir el precio, §2).
▶ **Regla para quien escriba la guarda**: el pago se sincroniza a `Σ itemCollectedCents` **después** de
montar las líneas, como en un pedido real.

## 4.ter LA SEGUNDA AUDITORÍA — 11 pedidos REALES por los FLUJOS REALES

La primera auditoría (§4.bis) construía las filas a mano. Encargo del owner (2026-08-24): hacerlo con
**pedidos de prueba de verdad**. Se hizo en **local** y con los flujos reales:

| Paso | Cómo |
|---|---|
| Crear | `OrderCreator::createPendingOrder()` con carrito real |
| Cobrar | `RedsysReturnHandler::process()` con una vuelta **FIRMADA** (`Ds_Response=0000`) |
| Editar | subir el precio del ítem + `Order::applyExtraDue()`, como hace el panel |
| Cancelar | `OrderItem::markCancelled()` y el 🗑️ (`executePartialRefund(alsoCancelItem: true)`) |
| Reembolsar | `Order::executePartialRefund()` en **`MODE_REST`** y en **`MODE_MANUAL`** |

⚠️ **Por qué LOCAL y no staging** (el owner preguntó, con razón, que el sandbox existe para probar): la
matriz necesita **muchos estados, reloj determinista y MySQL** —staging es MariaDB, y `ENTORNOS.md` dice
que «verificado en staging NO equivale a verificado en MySQL»—, se repite muchas veces, y staging **no
lleva `#123`–`#126`**. `RefundGateway` se dobla, que es la costura que la arquitectura ofrece.
▶ **Pero eso deja un hueco REAL y hay que decirlo**: que `Redsys::executeRefund()` hable de verdad con
la pasarela y su respuesta se parsee bien **local no lo prueba**. Eso es sandbox, tiene herramienta
canónica (`php artisan redsys:verify-sandbox`, citada en `PAY-08`) y queda pendiente.
▶ ⚠️ **[MEDIDO 2026-08-24, §9.5] No es «que nadie lo haya corrido»: NO HAY POR DÓNDE.** `sis-t.redsys.es:443`
da timeout de TCP desde los TRES entornos. Es una IP que el banco tiene que autorizar.

### Los once casos, y las cuatro validaciones que costó llegar a ellos

⚠️ **`OrderCreator` está bien defendido, y montar los casos lo demostró**: rechazó cuatro intentos
seguidos con cuatro errores distintos —`unavailable_line` porque la franja existía pero no estaba
OFRECIDA; `unavailable_line` otra vez porque `slotKey()` compara la hora CRUDA y «10:00» no casa con
«10:00:00»; `pack_guests_range_line` por la `qty` fuera del rango del pack; `event_required_line` por
los datos del evento—. **Un guion que no pase por esas cuatro puertas no está probando el flujo real.**

    C1  entrada simple pagada online          C7  reembolso PARCIAL sobre ítem VIVO
    C2  pack con señal + complemento          C8  pack con señal cuyo principal se cancela
    C3  señal + subida por edición            C9  complemento cancelado, principal vivo
    C4  cancelada + reembolso REST            C10 pedido PENDIENTE sin pagar
    C5  cancelada + reembolso MANUAL          X   franja PASADA (resto de señal ya cobrado)
    C6  cancelada SIN reembolsar

### Resultado del DOMINIO

    A reserva · B1 valor · B2 aCobrar · B3 cobrado · B4 pdteRee · B5 devuelto · C 2fórmulas · D ↳suma
                                    se cumplen en los ONCE
    E ley caja        dos excepciones, las dos legítimas:
                      · reembolso de cortesía sobre un ítem VIVO (dinero devuelto sin bajar el valor)
                      · pedido sin pagar (todavía no ha entrado nada)

▶ **El cálculo es robusto y está bien defendido.** Y `C4` frente a `C5` cierra otra pregunta: el MODO
del reembolso (REST o manual) **no se filtra** al desglose del cliente — las dos salidas son idénticas.

### Resultado de la PROYECCIÓN — «Pagado online» COINCIDE en las dos superficies

Medido pedido a pedido: **el panel y el cliente enseñan el mismo número bajo el mismo rótulo en los
once**. La divergencia que se temía **no existe**.

### 4.ter.1 ⚠️⚠️ Y el descuadre solo aparece CUANDO LA FRANJA YA PASÓ

**En los diez pedidos con franja futura, la columna del cliente CUADRA.** Y no por suerte:
`cobradoPuerta` vale 0 en todos, así que la línea que falta no tiene nada que enseñar.

El único que lo destapa es el que tiene la franja pasada:

    CLIENTE                          PANEL
      Subtotal            133,00       Valor final del pedido   110,00
      Pagado online        62,00       Pagado online             62,00
      (falta)                          Pagado en el parque       48,00   ← el cliente no la tiene
      Pdte. devolución    −23,00       Pendiente de devolución   23,00
      Total               110,00       (caption) pagó por web    85,00
      visibles 62,00 ≠ total 110,00 → NO CUADRA, faltan 48,00

▶ **Eso reencuadra el defecto: no es que el bloque esté mal, es que se rompe justo cuando el cliente lo
mira.** Antes de la reserva cuadra; después de disfrutarla —que es cuando uno entra a repasar lo que
pagó— deja de cuadrar. Es la peor ventana posible y explica por qué no se había visto.

### 4.ter.2 Los otros tres, medidos sobre pedidos reales

| # | Caso | Qué enseña | Por qué es falso o ilegible |
|---|---|---|---|
| 1 | **C10** pedido sin pagar | «Pagado online 11,90 €» | **No se ha cobrado nada** (`grossPaidOnline` = 0). `online_amount_cents` es «importe a COBRAR» —la fuente de `DS_MERCHANT_AMOUNT`— leído en pasado. Y es el camino de recuperación que `openapi/v1.yaml` preserva a propósito |
| 2 | **C8** pack cancelado | «Subtotal 120,00 · Pdte. devolución −30,00 · **Total 0,00**» | 120 − 30 ≠ 0. El «Subtotal» es `Order.total`, otra base que ninguna otra línea usa |
| 3 | **C7** reembolso de cortesía | «Subtotal 19,80 · Devuelto −4,00 · **Total 19,80**» | El cliente pagó **15,80 € netos**. «Total» es el VALOR del producto (el «Valor final» del panel), y la palabra invita a leerlo como «lo que pagué» |

### 4.ter.3 Los pedidos quedan en local para inspeccionarlos

`R-HXASTE` · `R-UPFQAB` · `R-XCACFO` · `R-BEMOOI` · `R-HXRM74` · `R-SOS1IG` · `R-HM44LF` · `R-BBA4P2` ·
`R-HQCWUA` · `R-POIMYG`, todos del titular `cliente.demo@jumpweb.test`, más el sembrado `DEMO-LEDGER`
(franja pasada). Comprobado que **se pueden borrar** (`Order::delete()` no lo bloquea ninguna FK), así
que la tanda es desechable.
⚠️ Retienen aforo de franjas reales y llevan `event_data` **ficticio** marcado como tal.

### 4.quater CÓMO REPRODUCIR LA AUDITORÍA (la receta, porque el guion NO está en el repo)

Los dos guiones se escribieron **fuera del repo a propósito** —son instrumento de medida, no guarda: una
auditoría imprime la matriz completa, una guarda para en el primer fallo— y por tanto **no sobreviven a
la sesión**. La receta sí, y es corta:

1. **Un fichero PHP y `tinker`**: `docker compose exec -u sail laravel.test php artisan tinker
   --execute='require "/tmp/auditoria.php";'`. Así corre con Laravel arrancado y contra el MySQL local.
2. **`Notification::fake()`** al empezar: la auditoría no manda correos.
3. **Doblar `RefundGateway`** con un `RefundResult::succeeded('0900', [...])` — ⚠️ el segundo argumento es
   un **array**, no una cadena.
4. **Crear** con `OrderCreator::createPendingOrder($user, $cart, null)`. El carrito es
   `[['ticket_type_id', 'date', 'time', 'qty', 'event_data', 'addons' => [['ticket_type_id','qty']]]]`.
5. **Cobrar** recorriendo la vuelta real: `PaymentInitiation::open($order)` para crear el `Payment`
   pending, y `RedsysReturnHandler::process()` con `Ds_Response=0000` firmado con
   `Redsys::createMerchantSignature($redsys->config()['secret_key'], $params, $gatewayOrder)`.
6. **Reembolsar** con `Order::executePartialRefund($item, $cents, $operador, MODE_REST|MODE_MANUAL,
   $alsoCancelItem)`. Cancelar sin reembolso: `OrderItem::markCancelled($operador)`.

⚠️⚠️ **LAS CUATRO PUERTAS que `OrderCreator` pone, y que hay que pasar para estar probando el flujo
real.** Rechazó cuatro intentos seguidos con cuatro errores distintos:

| Error | Causa |
|---|---|
| `unavailable_line` | la franja EXISTE pero no está **ofrecida**. Pídela a `AvailabilityOffer::dates()`/`times()`, no a la tabla `slots` |
| `unavailable_line` (otra vez) | `OrderCreator::slotKey()` compara la hora **CRUDA**: «10:00» **no** casa con «10:00:00». Pasa `HH:MM:SS` |
| `pack_guests_range_line` | un pack tiene rango `min_qty`..`max_qty`. Léelo del producto |
| `event_required_line` | un pack con `event_fields` requeridos exige `event_data`. Usa valores **obviamente ficticios**: es PII de un menor (art. 9) |

⚠️ **Y las cuatro reglas de método que esta auditoría pagó** (§2, §4.bis.3, §4.ter): el pago se
sincroniza a `Σ itemCollectedCents` **después** de montar las líneas · un `extra_due` va **con** la
subida de precio · `unit_price` es **por unidad** · y `valor = online + aCobrar + cobrado` cierra sola:
`devuelto`/`pendienteReembolso` son **otro eje**, no deducciones del valor.

▶ Los pedidos de la auditoría quedaron vivos en local (§4.ter.3) y **se pueden borrar**.

## 5. Decisiones ya tomadas por el owner (2026-08-23/24)

1. **Contar la caja entera** en el desglose del cliente — con el matiz de §6.1, que la auditoría
   destapó DESPUÉS de tomar esta decisión.
2. **«Ver pedido» abre la pantalla de pedidos EN ese pedido, desplegado.**
3. **«Mis pedidos» entra en el índice de Mi cuenta Y se llega desde la reserva.**
4. **La pantalla de «Mis reservas» se queda como está**; lo que se arregla es su nota por reserva
   (§4.4) y el desglose se muda a la pantalla de pedidos.

## 6. Lo que la auditoría deja PENDIENTE de decidir

### 6.1 ⚠️⚠️ «Pagado online»: la decisión 1 hay que matizarla

La opción elegida proponía enseñar **85,00 €** («el real»). Medido después: **el panel enseña 62,00 €
bajo ese mismo rótulo**, porque su bloque es una *descomposición del valor final por canal de cobro*
(`valorFinal = pagadoOnline + aCobrar + pagadoPuerta`), no un extracto de caja. Los 85,00 € solo
aparecen en el panel dentro del caption de devolución.

▶ **Poner 85 en el cliente CREARÍA la divergencia que se quiere evitar**: mismo rótulo, dos números.
Tres caminos, y hay que elegir uno:

| Camino | Qué implica |
|---|---|
| **(a) Añadir solo lo que falta** | El cliente gana «Pagado en el parque» (§4.2) y el caption con el bruto que el panel ya tiene. Cuadra (62 + 48 = 110), **no cambia ni un número del panel** y no toca aritmética. El más barato y el de menor riesgo. |
| (b) Cambiar las DOS superficies al bruto | Más fiel a la caja, pero toca la pantalla más crítica del operador y reescribe su bloque. |
| (c) Dejarlo como está y solo re-rotular | No cuadra igual: sin la línea de puerta, la columna sigue sin sumar. |

### 6.2 La base del «Subtotal» (§4.3)

¿Se retira, o se rotula como «Valor original» distinguiéndolo de «Total»? El panel no lo tiene.

### 6.3 El rótulo de la nota por reserva (§4.4)

`«Señal 68,00 € · 30,00 € en el parque»` → propuesta: `«Pagado online 68,00 € · 30,00 € en el parque»`.
Decisión de producto: es texto que lee el cliente.

### 6.4 Vocabulario: dos voces, ¿una lista de conceptos?

El panel habla del cliente en tercera persona («El cliente pagó de más…») y el cliente en segunda
(«Pagaste de más…»). **Eso es correcto y no se toca.** Lo que hay que decidir es si los CONCEPTOS son
una lista única: hoy el panel dice «Falta por cobrar» y el cliente «A cobrar en el parque» para lo
mismo, y el panel dice «Valor final del pedido» donde el cliente dice «Total».

## 7. Las guardas que este trabajo tiene que dejar (no negociables)

1. **Cerrar los cruces que faltan** en `OrderFinancialInvariantsTest`: `Σ cards cobradoPuerta` contra
   el agregado, y **las dos fórmulas del importe online** una contra la otra (§4.5).
2. **Una reconciliación sobre los campos PUBLICADOS**: lo que la API publica —y por tanto lo que la
   pantalla puede pintar— tiene que sumar, recorrida sobre los seis escenarios que el dominio ya
   sabe distinguir (señal, cancelación, reembolso hecho, reembolso pendiente, subida por edición,
   bajada por edición). Es lo que hoy no existe (§4.6).
3. **Verificación por mutación de las dos**: una guarda de dinero que no se ha visto morder no cuenta.
4. **Invariante nueva en `INVARIANTES.md`**: el desglose que lee el cliente reconcilia. Hoy no hay
   ninguna que lo cubra.

## 8. Nota sobre los datos de desarrollo

> ⚠️⚠️ **CORREGIDO en §9.7: la premisa de abajo («arreglar la semilla») es FALSA.** Medido el
> 2026-08-24: **ningún seeder del repo crea pedidos**. Los `DEMO-*` son datos escritos a mano en
> sesiones anteriores, y una instalación limpia no tiene ninguno.

⚠️ `DEMO-0001` está `paid` **sin ninguna fila `Payment`**, así que su `grossPaidOnline` es **0**. Es
sembrado, no un caso real, y cualquier decisión sobre «lo realmente cobrado online» hay que medirla
sobre `DEMO-LEDGER`, que sí tiene su `Payment` de 85,00 €. Si se publica el bruto, este pedido
sembrado enseñará 0,00 € — hay que arreglar la semilla o el siguiente agente leerá un fallo donde no
lo hay.

---

## 9. LA TERCERA AUDITORÍA — 58 pedidos en MySQL + 6 en MariaDB, y la proyección por HTTP

Encargo del owner (2026-08-24, segunda vuelta): auditar **todo** el desglose antes de decidir nada,
en local **y en staging**, y con los objetivos de producto por delante —«*que el cliente entienda la
situación económica de su pedido, ya sea reembolso o lo que sea, que haya trazabilidad y explicación
ante cualquier situación y esté informado de ello; y que lo tenga claro y lo entienda fácilmente*».

**Corpus**: 58 pedidos en local (36 preexistentes + **22 creados por los flujos reales**) y 6 en
staging (**MariaDB**). La proyección se midió **por HTTP** contra `GET /api/v1/me/orders` con token
Bearer propio, no leyendo el Resource en memoria.

### 9.1 Lo que la auditoría anterior acertó, y sigue en pie

- **NO hay dos fuentes de verdad**: panel y cliente enseñan el mismo «Pagado online» en **64 de 64**.
- Las identidades `A`, `B1`–`B4`, `B6`, `C`, `D` se cumplen en **todos** los pedidos de los dos motores.
- La rama **legacy** de reembolso es **inalcanzable por código**: los dos únicos escritores de
  `Order.refund_amount_cents` —`executeFullRefund()` y `executePartialRefund()`— la derivan de las
  filas, y no hay ningún escritor en `app/Filament` ni en `app/Http`. Con la decisión del owner
  («JumpWeb solo instala limpio», 2026-08-24) es **código muerto**: se retira, no se guarda con una
  excepción que debilite el invariante.

### 9.2 ⚠️⚠️ CUATRO defectos de DOMINIO que la auditoría anterior no llegó a construir

Ninguno es legacy, ninguno es de fixture: los cuatro los produce el flujo ordinario.

| | Defecto | Medido en | Causa raíz |
|---|---|---|---|
| **D1** | Un pedido **cancelado sin reembolsar** dice que su valor sigue vivo y **no anuncia nada pendiente de devolver**. El parque retiene el dinero y el cliente lee «Total 19,80 €» y ni una palabra | `R-RPEW08` (local) | Cancelar el pedido **no cancela sus líneas** — ni por `ViewOrder` (la acción del panel) ni por `executeFullRefund`. `productsValue` sigue sumándolas |
| **D2** | Un pedido **reembolsado ENTERO** sigue diciendo que su total es el precio completo | `R-3FLLUY` | La misma: `alsoCancel` cancela el PEDIDO, no las LÍNEAS |
| **D3** | Un pedido **que nunca se pagó** declara dinero cobrado: «Pagado online 30,00 € + Pagado en el parque 90,00 €» con **0,00 € realmente cobrados** | `R-VYXKRD` (staging), reproducido en local como `R-OUNAHW`/`R-ZBXYOX` con el `orders:expire` real | Las dos cestas de puerta se resuelven **solo con que la franja haya pasado**, sin mirar si el pedido llegó a cobrarse. ⚠️ La comprobación **ya existe en el código**, tres líneas más arriba, en `ReservationFinancials::showsDepositNote()` |
| **D4** | Un **reembolso total** no se atribuye a ninguna reserva: la tarjeta dice «devuelto 0,00 €» con 19,80 € devueltos. ⚠️ **Y se ve HOY** (corregido el 2026-08-24: la primera lectura dijo que era latente): lo consumen la **sub-tarjeta por reserva del panel** y la **hoja PDF**; solo el bloque de totales del pedido lo esquiva a mano | `R-3FLLUY` | `executeFullRefund` escribe `order_item_id => null`, e `itemRefundedCents()` solo casa por id |

⚠️⚠️ **D1 es el más grave y ninguna identidad lo caza, ni siquiera la ley de caja** —el dominio cree
que el producto sigue vivo, así que la caja cuadra—. Y su alcance operativo es peor que el contable:
`cancellationBlockedReason()` **permite** cancelar un pedido ya cobrado (tiene su propio texto de
confirmación para ese caso), y después `refundBlockedReason()` **bloquea el reembolso sobre pedidos
cancelados** («eso es operativa banco directa … y queda fuera del panel»). Es decir: un clic deja el
dinero retenido, sin camino de vuelta en el panel y **sin avisar al cliente**.

▶ **Consecuencia para el plan**: la tanda 1 **NO es «riesgo cero»**. Sus invariantes nacen en ROJO
sobre estos cuatro casos. Primero se arregla el dominio.

### 9.3 La proyección: no es UN defecto, son CUATRO

Medido por HTTP sobre 50 pedidos: **30 se leen sin ambigüedad, 20 no**.

| | Qué pasa | Alcance |
|---|---|---|
| **P1** | Falta «Pagado en el parque»: la API publica 2 de las 3 patas del valor | 6 de 50 — **el 100 %** de los que tienen algo cobrado en puerta |
| **P2** | «Devuelto» y «Pdte. de devolución» se pintan **como restas dentro de la columna del valor**, y no restan del valor: son otro eje | 8 de 50 |
| **P3** | «Subtotal» (`Order.total`) y «Total» (`productsValue`) son **bases distintas** apiladas sin decirlo | 11 de 50 |
| **P4** | **«Pagado online» se OCULTA** si el producto no lleva señal (`account/orders.js::financialsOf`). La API sí lo publica; lo esconde la interfaz | 7 de 50 |

⚠️ **P4 no estaba registrado en ningún documento.** Y P1 se había medido sobre UN pedido; sobre siete
falla en los siete.

### 9.4 Tres correcciones de MÉTODO más, que costaron tres falsos veredictos

⚠️ **La excepción de la ley de caja hay que escribirla ENTERA, y se escribió mal dos veces.**
«El pedido no está pagado» excusaba `R-3FLLUY`; «no entró nada por web» excusaba `R-VYXKRD`. La
condición correcta es **«no ha movido dinero»**: ni entró por web **ni el dominio afirma haber
cobrado en puerta.

⚠️ **`R-L6UTIA` parecía un defecto y NO lo es**: tiene un `extra_due` de 12,00 € sobre una subida de
valor de 84,00 €, que el flujo real no puede producir —`ViewOrder` registra el `extra_due` por el
**diff entero**—. Es un artefacto de un guion de auditoría anterior. Es la trampa de §2 **al revés**.

⚠️ **`dates()`/`times()` de `AvailabilityOffer` devuelven VALUE OBJECTS** (`OfferedDate`/`OfferedTime`),
no arrays, y `OfferedTime::$time` ya viene en `H:i:s`. Y `slots` tiene ÚNICO `(zone_id, date,
start_time)`: un guion que cree franjas pasadas debe reusarlas o variar la hora.

### 9.5 ✅ El sandbox de Redsys: VERIFICADO — y el bloqueo que se anunció era un ERROR DE MEDIDA

⚠️⚠️ **Esta sección afirmó primero que el sandbox era inalcanzable desde los tres entornos. Era
FALSO, y la causa es instructiva**: se probó el puerto **443**, y Redsys sirve su entorno de pruebas
en el **25443** — que es exactamente el que el código usa (`Redsys::REST_URL_TEST`). Medir contra un
puerto que el código no usa produce un diagnóstico de infraestructura donde no lo hay.
▶ **Medido de nuevo el 2026-08-24**: TCP 25443 **abierto** desde el host, desde el contenedor y desde
staging, y el endpoint REST responde `HTTP 200` en 0,29 s.

**Y la verificación se hizo, con las credenciales de sandbox del owner** (comercio `263100000`,
**terminal 45**):

    php artisan redsys:verify-sandbox --merchant=263100000 --terminal=45
      → Ds_Response = SIS0054   ✅ firma HMAC_SHA512_V2 ACEPTADA; denegación esperada
                                   sobre una operación inexistente
    … --bad-key   (control)
      → Ds_Response = SIS0042   la firma es RECHAZADA → el control discrimina

▶ **`PAY-08` queda verificado en su parte de integración**: conectividad, credenciales del comercio,
firma aceptada por el banco, formato de la petición y parseo de la respuesta. Lo que **sigue
pendiente** es un `Ds_Response=0900` REAL, que exige una autorización previa en el sandbox (un pago de
prueba con tarjeta) y después `--gateway-order=`. Eso es navegador sobre staging, no CLI.

⚠️⚠️ **Y el propio verificador tiene un defecto: SU CONTROL NEGATIVO SALE EN VERDE.**
`VerifyRedsysSandbox::report()` clasifica **cualquier** `gateway_denied` como «✅ INTEGRACIÓN VÁLIDA»,
así que `--bad-key` —que existe para demostrar que la comprobación puede fallar— devuelve `SUCCESS` y
además imprime un texto que se contradice: «una firma errónea daría SIS0042, no esto», mostrando
SIS0042. **Una guarda cuyo control negativo no falla no es una guarda.** Se arregla en la tanda A:
`SIS0042` (y la familia de errores de firma) tiene que salir en ROJO.

### 9.6 Qué aportó staging, y qué no

- **NO hacía falta desplegar**: el código financiero de staging es **idéntico** a `main` (diff vacío
  en los 7 ficheros que calculan y proyectan el dinero), pese a ir 5 commits por detrás.
- **Sí aportó el caso que local no tenía**: `R-VYXKRD`, el pedido caducado que destapó `D3`.
- **Segundo motor**: las identidades se cumplen igual en **MariaDB**. La aritmética es entera y no
  depende del motor — lo que sí depende son los locks, y eso sigue siendo `SUITE-04`.

### 9.7 Corrección a §8: no hay ninguna semilla que arreglar

⚠️ **§8 parte de una premisa falsa.** Medido: **ningún seeder del repo crea pedidos**
(`grep Order::create database/seeders/` → nada). Los `DEMO-*` son datos escritos a mano en sesiones
anteriores. Una instalación limpia **no tiene pedidos**, así que no hay semilla que corregir: hay
datos de desarrollo sucios que conviene borrar. Su valor real es otro: son un **control negativo**
—18 pedidos `paid` sin ninguna fila `Payment`— y el modelo de §10 los marca como imposibles.

---

## 10. [DECIDIDO 2026-08-24] EL MODELO — dos ejes cerrados, y cada euro con su línea

> Decisión tomada por el agente con los objetivos de producto del owner por delante (§9), y
> **verificada empíricamente sobre los 58 pedidos antes de escribir una línea de producción**.
> Decisión asociada: `DECISIONES #127`.

**Principio rector**: el desglose **no se deriva en ninguna superficie**. El dominio publica un
desglose **cerrado** y las superficies solo pintan. Y **cada euro que se ha movido tiene su línea, en
su eje** — que es lo que convierte un número en una explicación.

### 10.1 Las dos identidades

    EJE VALOR   valor = pagadoWeb + pendienteWeb + pagadoParque + pendienteParque + compensado
    EJE CAJA    cobradoWeb − devuelto = pagadoWeb + pendienteDevolución

▶ **Medido: las dos cierran en 58 de 58 pedidos reales.** Y el único estado que el modelo declara
imposible —`pendienteDevolución < 0`, o sea «el parque retiene menos de lo que dice haber cobrado»—
dispara **exactamente** sobre los 19 pedidos de datos sucios (los 18 `DEMO-*` sin `Payment` y
`R-L6UTIA`) y sobre **ninguno** de los 22 creados por flujos reales. Un invariante que solo se queja
de lo que ya está roto.

### 10.2 Lo que cambia respecto del modelo de hoy

1. **El eje del valor tiene CINCO canales, no tres.** Aparecen dos:
   - **`pendienteWeb`** — lo que falta por cobrar ONLINE. Es lo que hoy se publica como
     `online_amount_cents` y la pantalla lee **en pasado** («Pagado online 11,90 €» en un pedido que
     nadie ha pagado, §4.ter.2). Separar cobrado de pendiente mata ese defecto de raíz.
   - **`compensado`** — dinero devuelto **sin quitar producto** (la cortesía de §4.bis.2). No es un
     canal de cobro y no es una bajada de valor: es su propio término, y por eso la ley de caja tenía
     una «excepción» que en realidad era un término que faltaba.
2. **El eje de la caja se ancla en lo REALMENTE cobrado** (`Σ payments pagados`), que es lo único que
   el cliente puede cotejar con su extracto bancario. Hoy ese número solo existe en un caption del panel.
3. **Un pedido cancelado no tiene valor vivo.** Los dos caminos de cancelación cancelan sus reservas.
4. **Sin cobro no hay cobro**: las cestas de puerta solo se resuelven si `paid_at !== null`.
   ⚠️ Medido: `paid_at` lo escriben **exactamente dos sitios** —`RedsysReturnHandler` (web) y
   `ManualOrderFulfiller` (taquilla)—, así que cubre los dos canales de cobro reales.
5. **Todo reembolso se atribuye a una reserva**, también el total.
6. **«Subtotal» sale de la columna.** `Order.total` pasa a línea de **trazabilidad** («Importe al
   reservar»), y solo cuando difiere del valor de hoy. Resuelve §6.2.
7. **«Pagado por web» nunca se oculta.** Resuelve P4.
8. **Cada estado lleva su FRASE.** Un número no explica; la frase sí, y es el objetivo del owner:
   «tu reserva se canceló el … · tenemos pendiente devolverte …», «esta reserva caducó sin
   completarse el pago: no se te ha cobrado nada». La compone el DOMINIO, como ya hace con las
   etiquetas de `pending_at_gate_lines` (`DECISIONES #120(j)`).

### 10.3 La columna resultante, medida sobre pedidos reales

    PACK CON SEÑAL, FRANJA PASADA (R-ECNPQR)        PEDIDO CANCELADO SIN REEMBOLSAR (R-RPEW08)
      HOY                                             HOY
        Total                    120,00                 Total                        19,80
        Pagado online         +   30,00               PROPUESTO
      PROPUESTO                                         — QUÉ VALE TU RESERVA —
        — QUÉ VALE TU RESERVA —                         Valor de tu reserva           0,00
        Pagado por web            30,00                 — TU DINERO —
        Pagado en el parque       90,00                 Cobrado por web              19,80
        Valor de tu reserva      120,00  ✓ cierra       Pendiente de devolverte      19,80

    CANCELACIÓN PARCIAL PENDIENTE DE DEVOLVER (DEMO-LEDGER) — el caso insignia de §4.ter.1
      HOY                                             PROPUESTO
        Subtotal                 133,00                 — QUÉ VALE TU RESERVA —
        Pagado online         +   62,00                 Pagado por web               62,00
        Pdte. devolución      −   23,00                 Pagado en el parque          48,00
        Total                    110,00                 Valor de tu reserva         110,00  ✓ cierra
        visibles 39,00 ≠ 110,00                         — TU DINERO —
                                                        Cobrado por web              85,00
                                                        Pendiente de devolverte      23,00

### 10.4 Vocabulario — UNA lista de conceptos, DOS voces (resuelve §6.4)

El panel habla en tercera persona y el cliente en segunda; **eso no se toca**. Lo que se unifica es el
CONCEPTO: mismo concepto, mismo sitio, mismo signo.

| Concepto | Panel | Cliente |
|---|---|---|
| valor actual | Valor final del pedido | Valor de tu reserva |
| canal web COBRADO | Pagado online | Pagado por web |
| canal web PENDIENTE | Pendiente de cobro online *(nuevo)* | Pendiente de pagar por web |
| puerta COBRADA | Pagado en el parque | Pagado en el parque |
| puerta PENDIENTE | Falta por cobrar | Pendiente de pagar en el parque |
| compensación | Compensación *(nuevo)* | Compensación |
| ya devuelto | Devuelto | Ya devuelto |
| por devolver | Pendiente de devolución | Pendiente de devolverte |
| ancla de caja | Cobrado por web | Cobrado por web |

### 10.5 ⚠️ El panel VA a cambiar de números, y es lo correcto

La tanda 2 prometía «sin tocar un número del panel». **Esa promesa es imposible y era incorrecta**:
los números del panel mienten en los mismos cuatro casos. Medido sobre el corpus local:

- **valor** y **pendiente de devolución** cambian en **3** pedidos creados por flujos reales
  (`R-3FLLUY`, `R-LTZWAE`, `R-RPEW08`) — los tres, de un número falso a uno cierto;
- el **reparto por canal** cambia en **todos los no cobrados** (pasa de «pagado» a «pendiente»);
- los **19 de datos sucios** pasan a marcarse como imposibles, que es el objetivo.

## 11. El plan revisado — TRES tandas, correctamente dimensionadas

Sustituye a las tres del tracker, que estaban bien orientadas y mal dimensionadas.

- **Tanda A · el DOMINIO y sus guardas.**
  Los cuatro arreglos de §9.2 y los dos ejes de §10.1 como
  invariante, en el mismo paso: un arreglo de dinero y su guarda no se separan. Cierra además los
  cruces que faltaban (`B3`, `B5`, `C`, `D`) y retira la legacy-safety como código muerto.
  **Verificación por mutación de cada aserción nueva.** `PAY-16` (eje valor) y `PAY-17` (eje caja).
  ⚠️ **No es «riesgo cero»**: cambia conducta, y es justo lo que hay que cambiar.
- **Tanda B · PROYECCIÓN.** Publicar los cinco canales + el ancla de caja + la frase de estado, por
  reserva y agregados; partir la columna en los dos bloques; retirar «Subtotal»; dejar de ocultar
  «Pagado por web». Contrato en `openapi/v1.yaml` **primero**. Aquí sí es escribible la guarda
  «lo publicado tiene que sumar» (§7·2), que hoy sería un test rojo por definición.
- **Tanda C · PANTALLA.** «Mis pedidos» como zona propia y el «Ver pedido» de cada reserva llevando a
  ella (§5). ⚠️ Medido: hoy existen `ORDERS` y `ORDERS_HISTORY`; añadir una zona es una línea en `ZONES`.

⚠️ **Y una consecuencia de secuencia**: publicar `devuelto` por reserva (tanda B) está **BLOQUEADO**
hasta que `D4` esté arreglado, o la tarjeta de un pedido reembolsado entero dirá «devuelto 0,00 €».

---

## 12. LA MATRIZ DE GESTIÓN DEL PANEL — 23 acciones reales medidas contra el desglose

Encargo del owner (2026-08-24): «*lista TODA la gestión que puede accionar un admin desde el panel
hacia una reserva y hacia un pedido … y ve cómo afecta al desglose, con pruebas empíricas*».

**Cómo se midió**: **conduciendo las acciones REALES del panel** (Livewire sobre
`Filament\…\Pages\ViewOrder`), no replicando su lógica. Cada acción arranca de un pedido limpio, se
ejecuta, y se fotografía el desglose antes/después en tres vistas: el DOMINIO (6 dimensiones), lo que
**publica la API** y lo que la pantalla pinta, y el modelo propuesto de §10.
⚠️ La sonda lee además `audit_logs` tras cada acción: **las acciones del panel tienen capas de guarda
que devuelven EN SILENCIO**, y sin eso un bloqueo se lee como «la acción no cambió nada».

### 12.1 El inventario COMPLETO

| Nivel | Acción | ¿Toca el dinero? |
|---|---|---|
| **PEDIDO** | `cancel` — Cancelar pedido | **sí** |
| | `refund` — Reembolsar el pedido (modo REST/manual · toggle «también cancelar») | **sí** |
| | `resendEmail` — Reenviar un email | no |
| | `viewOrderHistory` — Historial de cambios | no |
| | *(página aparte)* `CreateManualOrderPage` — pedido de taquilla (efectivo/datáfono) | **sí** |
| **RESERVA** | `cancelItem` — Cancelar la reserva (cascada a sus complementos) | **sí** |
| | `refundItem` — Reembolsar la reserva (selector de líneas · REST/manual) | **sí** |
| | `manageItem` → pestaña **Reserva**: cambiar **fecha y franja** | no (salvo cambio de tarifa) |
| | `manageItem` → pestaña **Editar producto**: cambiar **producto**, cambiar **cantidad/invitados**, editar **datos del evento**, **añadir** complemento, **cambiar la cantidad** de un complemento, **quitarlo** (cantidad 0) | **sí** |
| | `copyGuestFormLink` — Copiar el enlace del post-form | no |

⚠️ **Dos reglas del panel que no están escritas en ninguna parte y conviene saber:**
- **Un COMPLEMENTO no se puede cancelar ni reembolsar suelto** (`item_is_addon`): el 🗑️ de una línea
  solo existe para el principal. Los complementos se gestionan **solo** desde el modal, poniendo su
  cantidad a 0. Medido: las dos acciones se bloquean con audit propio.
- **Cancelar la reserva CASCADEA a sus complementos** (`#157`), y cancelar el PEDIDO **no cascadea a
  nada** (§12.3).

### 12.2 Resultado: 18 de 23 acciones dejan el desglose del cliente ILEGIBLE

    23 acciones medidas · 0 fallaron al ejecutarse
     2 bloqueadas por una guarda legítima  (cancelar/reembolsar un complemento suelto)
     2 sin efecto financiero, correctamente (cambiar la franja de una entrada y de un pack)
     3 rompen la identidad B5               (los TRES reembolsos a nivel de pedido)
    18 dejan la columna del cliente ilegible
     0 rompen las DOS identidades del modelo propuesto (§10.1)

▶ **Ése es el dato que ordena el trabajo**: no es que el desglose falle en un caso raro — **casi
cualquier gestión ordinaria del operador lo rompe**, y el modelo de §10 aguanta las 23.

**Los tres patrones, y en cuántas acciones aparecen:**

| Patrón | Acciones |
|---|---|
| **«Subtotal ≠ Total»** — `Order.total` es lo FACTURADO e inmutable, y se pinta como primera línea de una columna que acaba en otro número | **10 de 10 ediciones**. Sin excepción |
| **eje-caja restando dentro del valor** — «Devuelto» y «Pdte. de devolución» se pintan como restas de una columna de la que no restan | 9 |
| **«Pagado online» OCULTO** — en productos sin señal la línea no se pinta, aunque la API la publique | 9 |

### 12.3 ⚠️⚠️ La prueba más limpia del defecto D1: la MISMA operación, a dos niveles

    R1 · Cancelar la RESERVA   valor 24,00 → 0,00   ·  pendiente de devolución 0,00 → 24,00   ✅
    P1 · Cancelar el PEDIDO    valor 24,00 → 24,00  ·  pendiente de devolución 0,00 →  0,00   ❌

**A nivel de línea el dominio lo hace bien; a nivel de pedido no hace nada.** El cliente, tras P1, lee
«Total 24,00 €» sobre un pedido cancelado cuyo dinero el parque retiene. No hace falta inventar la
conducta correcta: **ya existe, un nivel más abajo**. El arreglo es que cancelar el pedido cancele sus
reservas, que es lo que de hecho ocurre.

### 12.4 ⚠️⚠️ `refundItem` NO cancela la línea — y su propio docblock dice que sí

El docblock de la acción afirma: «*Cada item marcado → `executePartialRefund` con
`alsoCancelItem=true` (devolver = el operador entiende que ese item ya no se entrega)*». El código
llama `executePartialRefundBatch(alsoCancelItems: **false**)`. **Código y documentación se
contradicen en dinero.**

Medido, y el caso del pack es el que duele:

    S5 · Reembolsar (🗑️) la reserva de un pack con señal — se devuelven los 30,00 € de señal
      CLIENTE HOY  Subtotal 120,00 | Pagado online +30,00 | A cobrar parque +90,00
                   | Devuelto −30,00 | Total 120,00
      DOMINIO      pagadoOnline sigue diciendo 30,00 tras devolver esos mismos 30,00

▶ **La reserva sigue viva, la señal se devolvió, y las dos superficies siguen contando esos 30,00 €
como pagados.** El parque atendería una fiesta cuya señal salió, y nadie lo ve. Con el modelo de §10
se lee correcto: `Pdte. parque 90,00 | Compensado 30,00 | = VALOR 120,00 ‖ cobrado web 30,00 ·
devuelto 30,00`.

⚠️ **Y hay una decisión de producto detrás**: «reembolsar sin cancelar» cubre DOS situaciones de
negocio distintas —una **compensación** de cortesía y el **canje en persona** (el cliente pagará en
taquilla)— y el sistema **no distingue cuál es**, así que ni el ledger ni la puerta saben si el
cliente sigue debiendo el dinero. El modelo lo hace visible con un canal propio y una frase honesta
(«te devolvimos X € y tu reserva sigue en pie»); **saber cuál de las dos es** exige que el operador lo
diga, y eso es decisión del owner.

### 12.5 Tres cosas más que la matriz enseñó, para quien la repita

⚠️ **El `slot_time` del payload se IGNORA.** `mountUsing` rellena las propiedades Livewire del
calendario y `executeManageItemSave` les da PRIORIDAD, así que el fallback que su docblock ofrece
«para tests y automatización» **nunca se alcanza**. Hay que montar la acción y fijar
`calendarSelectedDate`/`calendarSelectedTime`.

⚠️ **Las franjas del fixture deben ser CONTIGUAS y abiertas a venta online** (`online_sales_open`,
`status`, y `end_time` encadenando con la siguiente). Si no, `SlotAvailability` devuelve 0 y **toda**
edición se bloquea con `insufficient_capacity_at_save` — un falso «el panel no deja editar».

⚠️ **`Order.total` de un pedido con señal es el VALOR COMPLETO, no la señal.** Medido contra los
pedidos que crea `OrderCreator` de verdad. Ponerlo a la señal en un fixture fabrica un
«Subtotal ≠ Total» que no existe — le pasó a la primera pasada de esta misma sonda.

### 12.6 La sonda, y por qué no está en el repo

Vive fuera (instrumento de medida, no guarda: imprime la matriz entera en vez de parar en el primer
fallo). La receta, que sí sobrevive: un test de `tests/Feature/Admin/Orders/` con `RefreshDatabase`,
reloj congelado, `Notification::fake()`, un staff con los permisos `orders.view`, `orders.edit_item`,
`orders.edit_event_data`, `orders.cancel`, `orders.refund`, **`orders.cancel_item`** y
**`orders.refund_item`** (los dos últimos son los que faltaban y hacían invisible media matriz), y
`Livewire::actingAs($staff)->test(ViewOrder::class, ['record' => $order->code])->callAction(…)`.
Los payloads: `cancelItem` → `['item_id', 'optimistic_token']` · `refundItem` →
`['mode', 'items_to_refund' => [ids]]` · `manageItem` → `['optimistic_token', 'product_id',
'quantity', 'slot_date', 'slot_time', 'addon_edits' => [['child_id','quantity']],
'addon_adds' => [['ticket_type_id','quantity']]]` (+ `event_data` **solo** en packs).

---

## 13. LA TARIFA AL CAMBIAR DE FECHA — medido, y no es lo que parece

Pregunta del owner (2026-08-24): «*si un producto tiene un precio el sábado y se cambia la fecha a
lunes, el precio debería cambiar. ¿No está reflejado? ¿Es un hueco?*».

**No es un olvido: es una REGLA**, y está escrita —solo— en un docblock de `ViewOrder`:

> «Producto SIN cambio → conserva el `unit_price` **HISTÓRICO** del item (extiende la reserva a la
> tarifa que pagó el cliente, sin sorpresas).»

⚠️ **Pero no tiene decisión numerada ni una línea en `docs/`.** Vive en ese docblock y en dos
aserciones de test (`// tarifa histórica conservada`). Es una regla que mueve dinero **en las dos
direcciones** y no está en el registro de decisiones.

### 13.1 Medido, con dos tarifas reales y las acciones reales del panel

Producto a **12,00 € laborable / 20,00 € fin de semana**, moviendo la fecha en las dos direcciones:

| Movimiento | `unit_price` | Catálogo del día destino | Efecto |
|---|---|---|---|
| **Sábado → Lunes** | 20,00 → **20,00** | 12,00 | **+16,00 € a favor del PARQUE** (el cliente paga de más por 2 uds) |
| **Lunes → Sábado** | 12,00 → **12,00** | 20,00 | **−16,00 € a favor del CLIENTE** (el parque deja de ingresar) |

▶ **Y el desglose del cliente es IDÉNTICO antes y después**: ni una línea lo menciona.

**Control de la sonda** (para que no sea una lectura de código): cambiar de **PRODUCTO** sí re-tarifica
al catálogo del día destino (25,00 € el sábado). Así que el instrumento mide, y la asimetría es real.

### 13.2 ⚠️⚠️ Lo grave no es la regla — es que el panel AFIRMA que no hay diferencia

`priceDiffPreview` y el guardado comparten el mismo cómputo puro (`computeEditPricing`), «*garantizando
que lo que ve el operador y lo que se cobra coinciden*». Medido invocándolo:

    Sábado → Lunes : actual 40,00 € → nuevo 40,00 € · le muestra «SIN CAMBIO DE PRECIO» (0,00 €)
                     cuando la diferencia real de catálogo son −16,00 €
    Lunes → Sábado : actual 24,00 € → nuevo 24,00 € · le muestra «SIN CAMBIO DE PRECIO» (0,00 €)
                     cuando la diferencia real de catálogo son +16,00 €

**No es silencio: es una afirmación falsa.** El operador mueve una reserva de lunes a sábado leyendo
«sin cambio de precio», y el parque deja de ingresar 16,00 € sin que nadie lo decida.

### 13.3 Y hay una INCONSISTENCIA dentro del mismo formulario

En el mismo modal, con la misma fecha destino:

- cambiar el **PRODUCTO** → se re-tarifica al catálogo de ese día;
- cambiar solo la **FECHA** → se conserva la tarifa pagada.

Un cliente que mueve su reserva de lunes a sábado paga tarifa de lunes; si además cambia de producto,
paga tarifa de sábado. **Dos reglas distintas en el mismo formulario**, y ninguna de las dos se le
explica a nadie.

### 13.4 [DECIDIDO 2026-08-24, owner] La regla CAMBIA: mover la fecha RE-TARIFICA

⚠️⚠️ **Esta sección propuso conservar la regla —«política defendible»— y el owner la REVOCÓ**, con un
argumento que la cierra:

> «*Si el cliente quiere elegir un día cuando sabe que es más caro, se le cobra. El cliente desde un
> principio elige un día y sabe el precio del día. Imagínate a todos los clientes comprando un día que
> es más barato y luego llamando para cambiar el día a sábado porque no se les cobra nada. Esto es
> inviable.*»

**No es una política, es un arbitraje abierto**: comprar el día barato y pedir el cambio al caro sale
gratis, y el descuento es exactamente la diferencia de tarifa. «Respetar la tarifa pagada» solo sería
defendible si el precio no dependiera del día — y aquí depende por diseño.

▶ **La regla nueva, cerrada en sus TRES direcciones** (`DECISIONES #127(d)`):

1. el precio es el de **HOY del día destino** — «pagas el precio del día que elijas»;
2. si **sube**, la diferencia **se cobra en el parque**, como cualquier otra subida (subir cantidad,
   añadir complemento, cambiar de producto). **No toca la pasarela**: el segundo cobro online se
   descartó a propósito por su fricción (PSD2/SCA). Cero mecanismos nuevos;
3. si **baja**, **se le abona** y aflora como «pendiente de devolución», como cualquier bajada de
   valor. Simétrico. ▶ Se evaluó el «arbitraje espejo» y **no existe**: comprar sábado y moverse a
   lunes deja una reserva de lunes a precio de lunes, que es lo que se habría comprado directamente.
   El único coste real es **operativo** (el reembolso lo ejecuta un operador, como toda bajada).

⚠️ **EL LÍMITE de la regla, y hay que escribirlo para que no se desborde**: se re-tarifica **SOLO
cuando cambia la FECHA**. Una edición que no mueve el día (subir cantidad, añadir un complemento)
**conserva la tarifa histórica del ítem**, como hoy. Aplicar el catálogo actual a *toda* edición sería
un cambio mucho mayor que nadie ha pedido.

⚠️ **EFECTO LATERAL ACEPTADO, escrito para que nadie lo redescubra como un fallo**: mover de un sábado
a OTRO sábado tras una subida de precios **cobra la subida**, y un descuento previo **se pierde** al
cambiar de fecha. Se descartó a sabiendas la alternativa —cobrar solo la diferencia entre el tipo de
día de origen y el de destino, que habría respetado precio y descuento— por ser más difícil de
explicar al cliente y de vigilar con una guarda.

**Y con esto se acaba la asimetría de §13.3**: cambiar el producto y cambiar la fecha pasan a
re-tarificar por la misma regla, en el mismo formulario.

**Lo que la regla NO resuelve por sí sola, y va con ella:**

1. **Que el operador vea la verdad.** Hoy el previo **afirma** «sin cambio de precio»; con la regla
   nueva enseñará la diferencia real, porque comparte el mismo cómputo (§13.2).
2. **Que el cliente tenga trazabilidad**: el cambio aparecerá como cargo o abono en su desglose, con
   la línea «Importe al reservar» de §10 como referencia de lo que se facturó.

---

## 14. EL PLAN DE EJECUCIÓN — detallado, para que no haya ambigüedad después

> Encargo del owner (2026-08-24): «*esto es muy crítico … meticuloso, detallista, profesional … y no
> quiero divergencias ni complejidades luego en torno a ese desglose, tanto en el panel de admin como
> en la página del cliente*».

### 14.0 El contrato de trabajo

1. **Una sola aritmética, un solo vocabulario, ocho superficies.** Ninguna superficie deriva nada: el
   dominio publica el desglose cerrado y todas pintan lo mismo con la voz que les toca.
2. **Un arreglo de dinero y su guarda no se separan**, y toda guarda nueva se **verifica por mutación**.
3. **El contrato (`openapi/v1.yaml`) va ANTES que el código** en todo lo que publique la API.
4. **Nada se marca ✅ sin las cuatro condiciones del DoD** (`CONVENCIONES §3.bis`).

### 14.1 ⚠️⚠️ LAS OCHO SUPERFICIES que enseñan este dinero

Medido el 2026-08-24. **«Sin divergencias» significa que las ocho se mueven juntas.** Una que se quede
atrás es la divergencia que este trabajo existe para no crear.

| # | Superficie | Qué enseña hoy |
|---|---|---|
| 1 | **Panel · bloque «Totales del pedido»** (`order-totals.blade.php`) | Las 7 cifras del agregado, con detalle ↳ por reserva |
| 2 | **Panel · sub-tarjeta por reserva** (`reservation-financials.blade.php`) | Las **6** dimensiones de `ReservationFinancials` |
| 3 | **Panel · modal del calendario** (`CalendarPage` + `item-detail.blade.php`) | Reusa la sub-tarjeta (2) |
| 4 | **Panel · columna «Total» de la lista** (`OrdersTable`) | `totalFinalNeto()` |
| 5 | **Panel · taquilla** (`CreateManualOrderPage` + `ManualOrderFulfiller`) | `onlineDueCents()` como importe a cobrar |
| 6 | **Hoja PDF de la reserva** (`reservation-slip.blade.php`) | **5** dimensiones, incluida `devuelto` |
| 7 | **Emails** (`OrderConfirmation`, `OrderProcessedAfterExpiration`) | `onlineDueCents`, `pendingAtGate`, `depositRemainder`, `total` |
| 8 | **Cliente** (`OrderResource`/`OrderItemResource` → `account/orders.js` → `ReservationCard.vue`) | 2 de las 6 dimensiones |

### 14.2 TANDA A — que el dominio diga la verdad (y sus guardas)

⚠️ **Cambia importes.** Es lo que hay que cambiar: hoy son falsos en los casos de abajo.

| | Qué | Dónde | Verificación |
|---|---|---|---|
| **A1** | **Mover la FECHA re-tarifica** al precio de HOY del día destino (§13.4, `#127(d)`). Sube → cargo de puerta; baja → abono. ⚠️ **Solo al cambiar la FECHA**: el resto de ediciones conservan la tarifa histórica. ▶ **Reusa la maquinaria que ya existe**: un cambio de fecha con tarifa distinta entra por `executeItemEdit` —que ya sabe cobrar y abonar— en vez de por `executeItemSlotChange`, que hoy no roza el precio; y `computeEditPricing` resuelve por fecha en ese caso. **El previo del operador se arregla solo**: comparte ese mismo cómputo, que es justo por lo que hoy *afirma* «sin cambio de precio» | el despacho de `ViewOrder::executeManageItemSave` · `computeEditPricing` | Las dos direcciones × entrada y pack · el **control** (cambiar de producto sigue re-tarificando) · y el **límite** (subir cantidad sin mover la fecha NO re-tarifica) |
| **A2** | **Un pedido cancelado no tiene valor vivo**: los DOS caminos de cancelación cancelan sus reservas | `Order::executeFullRefund`, la acción `cancel` de `ViewOrder` | Mutación: sin el arreglo, `PAY-17` cae |
| **A3** | **Sin cobro no hay cobro**: las cestas de puerta solo se resuelven si `paid_at !== null` (los dos escritores reales: `RedsysReturnHandler` y `ManualOrderFulfiller`) | `OrderFinancialSummary::fromOrder`, `ReservationFinancials::make` | Pedido pendiente y caducado con franja pasada |
| **A4** | **Todo reembolso se atribuye a una reserva**, también el total | `Order::executeFullRefund` | `B5` cae sin el arreglo |
| **A5** | **El MOTIVO** de reembolsar-sin-cancelar y cancelar-sin-reembolsar (migración aditiva y nullable en `payment_refunds`; `order_adjustments` ya tiene `reason`) | migración + los dos modales + audit | Que el motivo viaje al audit y al dominio |
| **A6** | **Las dos identidades como INVARIANTE** (`PAY-16` eje valor · `PAY-17` eje caja, las de §10.1), más los cruces `B3`, `B5`, `C` y `D` sobre todos los escenarios. Van al documento de invariantes | `OrderFinancialInvariantsTest` | **Mutación de cada aserción nueva** |
| **A7** | **Retirar la legacy-safety** (código muerto: `JumpWeb solo instala limpio`) y sustituirla por la guarda de construcción «la columna es siempre Σ filas» | `OrderFinancialSummary::effectiveRefunded` y sus lectores | La guarda nueva se pone roja si alguien escribe la columna a mano |
| **A8** | **Dos arreglos de HERRAMIENTA**: el docblock de `refundItem` (afirma `alsoCancelItem=true`, el código pasa `false` — **la conducta es deliberada**, el texto no) y el control negativo de `redsys:verify-sandbox`, que **sale en verde** | `ViewOrder`, `VerifyRedsysSandbox::report` | `--bad-key` debe salir ROJO |

### 14.3 TANDA B — la proyección, en las OCHO superficies

| | Qué |
|---|---|
| **B0** | **`openapi/v1.yaml` primero**: los cinco canales del valor, el ancla de caja, el motivo y la frase de estado — por reserva y agregados |
| **B1** | El dominio compone la **frase de estado** (como ya compone las etiquetas de `pending_at_gate_lines`) |
| **B2** | **Cliente**: la columna en los DOS bloques de §10.3 · «Subtotal» sale de la columna y pasa a «Importe al reservar» · «Pagado por web» deja de ocultarse |
| **B3** | **Panel**: el mismo modelo, los mismos conceptos, el mismo orden y el mismo signo — **tercera persona**, que no se toca (§10.4) |
| **B4** | Las otras seis superficies alineadas: sub-tarjeta, calendario, lista, taquilla, **PDF** y **emails** |
| **B5** | La guarda **«lo publicado tiene que sumar»** (§7·2), que hoy sería un test rojo por definición |

### 14.4 TANDA C — «Mis pedidos» como pantalla propia

Zona nueva (`ZONES` + rótulo + componente) y el «Ver pedido» de cada reserva llevando a ella (§5).

### 14.5 Cómo se verifica que quedó bien

1. **Mutación** de cada guarda nueva de dinero.
2. **La matriz del panel (§12) se vuelve a correr entera**: las 23 acciones deben dejar el desglose
   legible en las 23, y las dos identidades cerradas.
3. **Los 58 pedidos reales** de local vuelven a pasar por la sonda de §10.
4. **Navegador** sobre el cajón y el panel, y el guion de `VERIFICACION-E2E-CAJON.md`.
5. **Auditoría final** con todas las pruebas y acciones (encargo del owner) antes de borrar los
   pedidos de auditoría.

### 14.6 Lo que NO se toca

- **Reembolsar y cancelar siguen siendo independientes** (`DECISIONES #127(c)`, owner).
- **`Order.total` sigue siendo inmutable**: es lo facturado. Cambia dónde se enseña, no lo que vale.
- **Las voces**: el panel habla en tercera persona y el cliente en segunda.
- **`FUNNEL_TRANSITIONS`, el grafo del embudo y el presupuesto del cajón**: nada de esto es del embudo.

---

## 15. TANDA A · EJECUTADA — lo que quedó hecho, y lo que midió

> 2026-08-24 · `DECISIONES #127`, `#127(c)`, `#127(d)` · dos commits.
> **Estado: ✅ el dominio dice la verdad y tiene guardas.** Falta la PROYECCIÓN (tanda B).

### 15.1 Los seis arreglos

| | Qué | Cómo se verificó |
|---|---|---|
| **A1** | **Mover la fecha RE-TARIFICA** al precio de hoy del día destino. Sube → cargo de puerta; baja → abono. Con su LÍMITE: solo al cambiar la fecha | `ItemDateChangeRetariffTest` (6 casos: las dos direcciones, el mismo tipo de día, el límite, el control del cambio de producto y el previo del operador). **Mutación 3/3**, incluida la que pierde el límite |
| **A2** | **Un pedido cancelado no tiene valor vivo.** Los dos caminos cancelan sus reservas, **y además la regla es cierta por construcción** —una segunda capa de lectura la aplica también sobre filas anteriores a la cascada— | Dos guardas que conducen las ACCIONES REALES del panel. **Mutación 3/3** |
| **A3** | **Sin cobro no hay cobro**: las cestas de puerta solo se resuelven si `paid_at !== null`, en los **tres** sitios que deben decir lo mismo | **Mutación 3/3.** ⚠️ La identidad `D` cazó el tercero, que se había quedado atrás |
| **A4** | **Todo reembolso se atribuye a su reserva**, también el total, a prorrata de lo aportado online y por **resto mayor** (Σ exacta, sin fuga de céntimos) | **Mutación 1/1** + un caso que asevera la PROPIEDAD (importe impar sobre tres líneas), no una foto |
| **A5** | **El MOTIVO del reembolso** (migración aditiva y nullable), preguntado **solo cuando hace falta**: si además se cancela, es evidente y se registra solo | Tres guardas: se registra · no se pregunta lo obvio · un valor inventado no se guarda |
| **A8** | El **docblock** de `refundItem` (afirmaba lo contrario del código) y el **control negativo** de `redsys:verify-sandbox`, que salía en VERDE | **Contra el sandbox REAL** con las credenciales del owner: clave buena → `SIS0054`, salida 0; clave mala → `SIS0042`, salida 1 |

### 15.2 Las guardas: 5 invariantes → 13, y 6 escenarios → 11

`PAY-16` (eje valor, cinco canales) · `PAY-17` (eje caja) · `PAY-18` (la tarifa al mover la fecha).
Más los cuatro cruces que faltaban (`B3`, `B5`, `C`, `D`), la **exclusión mutua** de los dos canales
web, la **no-negatividad** de cada canal —que asevera que los `max(0,…)` del dominio no están tapando
nada— y la **guarda de construcción** «la columna de reembolso es siempre Σ filas», que sustituye a la
legacy-safety sin retirarle el cinturón.

⚠️ **Los cinco escenarios nuevos son el trabajo de verdad.** Los seis viejos pasaban ya antes de los
arreglos: su hueco no estaba en la aserción, estaba en el FIXTURE.

### 15.3 Verificado sobre los 58 pedidos REALES (MySQL), no solo sobre fixtures

    PAY-16 · eje VALOR por reserva : cierra en los 58  ✓
    ningún canal negativo          : en los 58        ✓
    las DOS identidades            : las cumplen 38 de 58

▶ **Y los 20 que no las cumplen son EXACTAMENTE los datos escritos a mano**, clasificados por causa:

| Causa | Cuántos | ¿Lo produce algún flujo? |
|---|---|---|
| pedido `paid` **sin ninguna fila `Payment`** | 18 (`DEMO-*`) | **No.** Ningún seeder crea pedidos (§9.7); son datos de sesiones antiguas |
| columna de reembolso **escrita a mano** | 1 (`R-LTZWAE`) | **No.** Los dos escritores la derivan de las filas |
| dice 114,00 € cobrados online con un pago de 30,00 € | 1 (`R-L6UTIA`) | **No.** Es el artefacto de §9.4: una subida de 84,00 € con 12,00 € registrados |

▶ **Todo pedido creado por un flujo real cumple las dos identidades.** Un invariante que solo se
queja de lo que ya estaba roto es exactamente lo que se buscaba.

### 15.4 La matriz del panel, re-corrida entera

    23 acciones · 0 fallaron · 0 rompen ninguna identidad   (antes: 3 rompían B5)
    19 siguen dejando la columna del cliente ilegible       → eso es la TANDA B

⚠️ **Y el contador de columnas ilegibles subió de 18 a 19, que es una BUENA noticia**: `P1` (cancelar
el pedido) antes «se leía bien» porque **mentía en silencio** —decía «Total 19,80 €» y nada más—.
Ahora dice la verdad («pendiente de devolverte 19,80 €») y lo que falla es la maquetación, que es
justo lo que la tanda B arregla.

### 15.5 Tres cosas medidas que conviene no volver a descubrir

⚠️ **Una guarda sobre el helper suelto NO ve el cableado.** La mutación de A2 **no mordió** al
principio porque el escenario llamaba a `cancelLiveItems()` directamente en vez de conducir las
acciones del panel. Es la trampa nº1 de `CONVENCIONES §3.quater` —comprobar DÓNDE cayó la mutación— y
costó dos guardas nuevas.

⚠️ **`compensado` NO se puede definir por línea.** Se intentó, y rompía el caso que `#225` fijó: la
versión por-línea sobre-reporta cuando la pérdida de valor no deja huella en el ítem (un cambio a
producto más barato deja un `unit_price` nuevo, así que «cantidad_original × unit_price» miente). Va
anclado a CAJA a nivel de pedido y se REPARTE por reserva — una sola fórmula, un solo número.

⚠️ **Tres fixtures irreales corregidos, y los tres inventaban o tapaban defectos**: un pedido `paid`
sin `paid_at`, otro `paid` sin ninguna fila `Payment`, y un reembolso que escribía la fila sin
actualizar la columna agregada. Es la cuarta vez que esta spec anota lo mismo: **un fixture que no
reproduce el flujo real inventa defectos tan bien como los oculta.**

---

## 16. TANDA B · EJECUTADA — el desglose ya es LEGIBLE

> 2026-08-24 · dos commits · `DECISIONES #127`.
> **Estado: ✅ el dominio dice la verdad (tanda A) y ahora se entiende.** Falta la tanda C (pantalla).

### 16.1 El defecto de fondo era ESTRUCTURAL, no de rótulos

Las ocho superficies componían **cada una su desglose** a partir de campos sueltos. Por eso
divergían. La tanda B lo cambia por construcción:

- **`Booking\Services\OrderLedger`** compone el desglose —del pedido y de la reserva— y las ocho
  superficies **pintan**: no derivan, no deciden, no rotulan un importe con otro nombre.
- **El contrato lo publica en DOS EJES** (`Ledger` / `LedgerValue` / `LedgerCash`), y los seis campos
  sueltos de la raíz se van dentro. Un número, un sitio.
- **`LedgerSingleSourceTest`** prohíbe el MECANISMO: una superficie que vuelva a restar canales a
  mano cae **aunque su resultado sea correcto hoy** — porque «correcto hoy» fue exactamente el estado
  del que salió todo esto.

⚠️ **`online_amount_cents` se queda FUERA del ledger a propósito**: es «cuánto se te cobrará si pagas
ahora», lo que consume el reintento. Leerlo como «lo pagado» es lo que hacía que un pedido sin pagar
anunciara «Pagado online 11,90 €».

### 16.2 Los cuatro defectos de proyección, cerrados

| | Estaba | Ahora |
|---|---|---|
| **P1** | Faltaba «Pagado en el parque»: la columna no sumaba en el **100 %** de los pedidos con algo cobrado en puerta | Es un canal publicado. El eje del valor **cierra en 50 de 50** pedidos, medido por HTTP real |
| **P2** | «Devuelto» y «Pdte. de devolución» se pintaban como **restas dentro de la columna del valor**, de la que no restan | Bloque propio. **La separación es la corrección**, no un retoque visual |
| **P3** | «Subtotal» (lo facturado) y «Total» (el valor) apilados sin decirlo | «Importe al reservar» sale de la columna, va al pie con su explicación y **solo si difiere** |
| **P4** | «Pagado online» **se ocultaba** sin señal: 7 de 50 pedidos en los que el cliente no veía cuánto había pagado | Se enseña siempre, y partido en cobrado/pendiente |

### 16.3 Y la FRASE, que es lo que un número no puede decir

La compone el dominio porque decidir qué caso es —se le debe dinero, se canceló, caducó sin cobro, se
le devolvió y conserva la reserva— **es regla, no presentación**. ⚠️ **El orden de los casos es parte
de la regla**: lo que se le debe al cliente va SIEMPRE primero. Y `null` es un estado real: una frase
de relleno enseña a ignorar las que sí importan.

    Cancelar el pedido      → «Tu reserva se canceló el … Tenemos pendiente devolverte 24,00 €.»
    Bajar la cantidad       → «Tenemos pendiente devolverte 12,00 €.»
    Reembolsar sin cancelar → «Te devolvimos 30,00 € y conservas tu reserva: no tienes que pagar nada más.»
    Caducar sin pagar       → «Esta reserva caducó sin completarse el pago. No se te ha cobrado nada.»

⚠️ La última frase la elige el **motivo** que la tanda A registra: sin él solo se podría decir «te
devolvimos X €», que no responde a lo único que el cliente necesita saber — **si sigue debiendo**.

### 16.4 LA MEDIDA QUE CIERRA LA TANDA

La matriz de §12, re-corrida entera con el criterio del modelo nuevo:

    23 acciones · 0 fallaron · 0 rompen ninguna identidad
    dejan la columna ILEGIBLE:  0 de 23      (antes: 18 de 23)

Y el ejemplo que más dolía, `P1 · cancelar el pedido`:

    ANTES    Total 19,80 €          ← y nada más. El parque retenía el dinero y el cliente no lo sabía.
    AHORA    Valor del pedido 0,00 €
             Tu dinero · cobrado por web 24,00 € · pendiente de devolverte 24,00 €
             «Tu reserva se canceló el 01/06/2026. Tenemos pendiente devolverte 24,00 €.»

### 16.5 Dos cosas que la ejecución enseñó

⚠️ **Los CORREOS tenían el mismo problema y por otra razón: se REENVÍAN.** `onlineDueCents()` es «lo
que se cobraría al pagar ahora» y `Order.total` es lo facturado; los dos envejecen mal en un reenvío
hecho meses después. Es el mismo razonamiento que ya había llevado lo pendiente en puerta a
`pendingAtGate()`, aplicado a los otros dos importes del correo.

⚠️ **Dos guardas de arquitectura cazaron cosas que yo no vi**: la de fronteras de módulos, que
`Booking` no puede nombrar un modelo de `Payments` —la intención del reembolso llega ahora como
cadena desde `Order`—, y la de estilo, las cinco clases nuevas sin una sola regla CSS. Ninguna de las
dos habría fallado en la suite ni se habría visto en el navegador hasta mucho después.

---

## 17. ⚠️ `R-L6UTIA` — el pedido que parece un fallo del desglose y es un DATO ROTO

**Si alguien te enseña esta pantalla y te pregunta «¿esto está bien?», la respuesta está aquí.**

    Cumpleaños Jump · 8×216,00 €
    Señal 114,00 € · 102,00 € en el parque
    ...
    Pagado por web                    114,00 €
    Pendiente de pagar en el parque   102,00 €
      ↳ Cumpleaños Jump                12,00 €
      ↳ Resto de la señal              90,00 €
    Valor del pedido                  216,00 €
    Importe al reservar               132,00 €

**El desglose está bien; el DATO no.** Medido: el pago real de ese pedido fueron **30,00 €**, no
114,00 €. Es uno de los **21 pedidos de auditoría** de la BD local, montado por un guion anterior que
subió el precio del ítem **84,00 €** y solo registró **12,00 €** de `extra_due` — algo que el flujo
real del panel **no puede producir**, porque `ViewOrder` registra el diff ENTERO (§9.4).

▶ **La aritmética cierra (114 + 102 = 216) porque cierra sobre una mentira que está en la base de
datos**, no en el cálculo. Y la guarda lo dice: `R-L6UTIA` es uno de los **20 pedidos que incumplen
`PAY-17`** (§15.3), y el único de su clase.

⚠️ **Regla general, que vale para cualquier pedido raro que aparezca**: cuando el desglose enseñe algo
que no cuadra con la realidad, **comprueba primero si el dato es real** —`grossPaidOnline` contra
`pagadoOnline`— antes de buscar el fallo en el código. Es la trampa que este trabajo pagó CUATRO veces
con fixtures irreales (§16.5).

### 17.1 Pero al mirarlo en pantalla salieron TRES defectos de lectura que sí son nuestros

Independientes del dato roto. ✅ **Los tres EJECUTADOS el 2026-08-24** (`DECISIONES #128`); lo que
quedó hecho y lo que midió, en **§18**.

| | Qué se ve | Por qué está mal |
|---|---|---|
| **L1** | El **ancla de caja no aparece** | El bloque «Tu dinero» solo se pinta si hay devoluciones, así que **en un pedido normal el cliente nunca ve cuánto salió de su banco**. ⚠️ Es lo único que puede cotejar con su extracto — y aquí habría delatado el problema al instante: «cobrado por web 30,00 €» junto a «pagado por web 114,00 €». **Es un fallo de diseño, no del dato**: el ancla debería verse siempre que haya habido un cobro |
| **L2** | `8×216,00 €` | Se lee como «8 unidades a 216 € cada una» = 1.728 €. Son **8 invitados y 216 € en total**. La línea de la reserva no distingue cantidad de importe |
| **L3** | «Señal 114,00 €» | Ni es la señal (fueron 30,00 €) ni la palabra es correcta: es «lo pagado por web de esta reserva» (§4.4). **El rótulo sigue pendiente de decisión del owner** (§6.3), por eso no se tocó en la tanda B |

▶ **Los tres son de PANTALLA, no de dinero.** `L1` es el que más valor tiene: convierte el desglose en
algo que el cliente puede **verificar**, en vez de solo leer.

---

## 18. `L1`·`L2`·`L3` · EJECUTADOS — el desglose pasa de LEGIBLE a VERIFICABLE

> [DECIDIDO 2026-08-24] · `DECISIONES #128`. Owner: «*nada de chapuzas … transparentes con el cliente
> sobre su dinero y la gestión de la reserva, y que lo entienda de manera sencilla; la gestión de una
> reserva es muy flexible y cada movimiento mueve ese desglose*».

### 18.1 `L1` no era un fallo de diseño: eran DOS, y el segundo es el que importa

1. **Al predicado le faltaba un término.** `OrderLedger::hasCash()` miraba devoluciones y no el cobro.
2. **Y nadie llamaba al predicado.** Existía en el dominio y **ninguna superficie lo usaba**: el
   cliente re-derivaba la condición en JavaScript y el panel en su blade. Es exactamente la forma de
   divergencia que `OrderLedger` existe para cerrar, viva dentro de la clase que la cierra.

**Medido sobre los 58 pedidos, antes:**

    panel enseña el ancla de caja  : 28 de 38 pedidos SANOS
    cliente enseña el eje de caja  :  9 de 38
    DIVERGEN (panel sí, cliente no): 19   ← la mitad del corpus

**Después:** la condición se publica (`ledger.cash.has_cash`) y las dos superficies preguntan al mismo
sitio. **37 de 58 pedidos enseñan el eje de caja** —exactamente los 37 que han movido dinero— y el
cambio de condición **no altera el panel en ninguno de los 58** (verificado antes de tocarlo).

### 18.2 Hacer visible el ancla obligó a arreglar algo LATENTE: el método de cobro

⚠️⚠️ `grossPaidOnline` suma **todos** los pagos cobrados sin mirar el `provider`, y eso es correcto —el
eje de caja mide dinero movido, no medios—. Pero el rótulo decía «Cobrado por **web**», así que un
pedido cobrado en **taquilla** (`ManualOrderFulfiller`: efectivo o datáfono) le habría dicho al cliente
que pagó por internet un dinero que entregó en mano. **El panel ya distinguía el método** desde
`P1/P10`; el cliente no. Con el ancla escondida la divergencia era inocua; **con el ancla visible pasa
a ser una afirmación falsa en pantalla**, y en la superficie que este trabajo existe para hacer honesta.

▶ Se publica **`charged_method` como ENUM y no como rótulo** (`web` | `desk` | `null`): el panel habla
en tercera persona y el cliente en segunda (§10.4), así que la voz la pone cada superficie. Y el
método manda en **los dos ejes**: rotula el canal del VALOR («Pagado por web» / «Pagado en recepción»)
y el ancla de CAJA.
▶ Y con él **`charged_at_label`**: «cobrado 30,00 €» no se busca en un extracto bancario; «30,00 € el
24/08/2026» sí. **Sin fecha el ancla no es conciliable**, que era el objetivo entero.
▶ Y el panel deja de derivarlo: tenía `provider === 'redsys'` como literal suelto. Ahora es
`Payment::PROVIDER_REDSYS` y la regla vive en `Order::chargeMethod()`.

### 18.3 Lo que se ve ahora — el MISMO pedido, medido por el camino real

La composición REAL del cliente alimentada con la respuesta REAL del servidor para `R-L6UTIA`:

    Cumpleaños Jump
      Mar. 25 ago. · 11:00–13:00 · 8 invitados · 216,00 €        ← L2 (era «8×216,00 €»)
      Pagado por web 114,00 € · 102,00 € en el parque            ← L3 (era «Señal 114,00 €»)

    ── Qué vale este pedido ──
      Pagado por web                          114,00 €
      Pendiente de pagar en el parque         102,00 €
        ↳ Cumpleaños Jump                      12,00 €
        ↳ Resto de la señal de Cumpleaños Jump  90,00 €
      Valor del pedido                        216,00 €

    ── Tu dinero ──                                             ← L1: ANTES NO SE PINTABA
      Cobrado por web · 24/08/2026             30,00 €
      (Es el dinero que ya te hemos cobrado por este pedido.
       Puedes cotejarlo con tu extracto bancario.)

▶ **Y ahí está el valor de `L1` en una línea**: «Cobrado por web 30,00 €» queda justo debajo de
«Pagado por web 114,00 €». El dato roto de §17 **ya no hace falta auditarlo**: se ve.

### 18.4 Las guardas, y sus SIETE mutaciones

Ninguna guarda de dinero entra sin mutación (§14.0·2). Las siete matan a la suya:

| Mutación | Qué cae |
|---|---|
| `hasCash()` vuelve a la regla vieja | 2 de API + la paridad extremo a extremo |
| `chargeMethod()` devuelve siempre `web` | el caso de taquilla (API y cliente) |
| `chargedAtLabel()` devuelve `null` | el ancla de API + la paridad |
| `displayQuantityLabel()` devuelve el número pelado | `L2` en API y en la paridad |
| la nota vuelve a `deposit_card_note` | 4 casos de `L3` en el cliente |
| el cliente re-deriva `has_cash` | el caso «no se re-deriva» |
| el cliente nombra una clave pluralizada | `SidebarTextParityTest` |

⚠️ **La red que más pesa es `SidebarAccountParityTest`**, porque no dobla nada: la entrada la produce
el servidor y la composición la hace el módulo real en Node. Un campo mal entendido no puede salir
verde en las dos mitades a la vez.

### 18.5 Cuatro cosas que la ejecución enseñó

⚠️⚠️ **El fixture de la paridad tenía la forma exacta de los 18 `DEMO-*` sucios**: pedido `paid` **sin
ninguna fila `Payment`**, que el flujo real no puede producir y que `PAY-17` marca como imposible. Con
esa forma el ancla vale 0 y el caso **no podía ejercitarla**. Es la **quinta** vez que este trabajo
tropieza con lo mismo (§16.5, §9.4): un fixture irreal oculta defectos tan bien como los inventa.

⚠️⚠️ **`SidebarTextParityTest` cazó un defecto real en la clave nueva** —`entries_count` se escribió
con la sintaxis de RANGOS de Laravel (`{1}…|[2,*]…`), que el cajón **no resuelve** y habría pintado con
las llaves dentro—. Y su segunda mitad (solo `cart_items` puede llevar barra) obligó a algo mejor que
una excepción: **la exención se DEMUESTRA** comprobando sobre las fuentes del cliente que la clave no
se nombra ahí. Una lista blanca que nadie verifica es una promesa, no una guarda.

⚠️⚠️ **Y una que NO se ve en el código, solo al RENDERIZAR: el eje de caja heredaba el color de
REEMBOLSO.** Las tres filas usan `.orders__refund-amount`, que pinta en ámbar. Mientras el bloque solo
aparecía habiendo devoluciones eso era coherente; **en cuanto el ancla se enseña en todo pedido
cobrado, un cargo corriente se lee en ámbar como si algo se hubiera devuelto** — el mismo error de
fondo que la tanda B quitó del eje del valor, reaparecido por herencia de estilo. El ancla va marcada
y se pinta neutra. ▶ **La lección**: hacer visible algo que estaba oculto **hereda decisiones tomadas
para el caso oculto**, y ninguna de ellas está en el diff.

⚠️ **El techo del bundle cedió: `214,5 → 215,5 KiB`** (medido `213,57 → 214,58`, **+1,01**). Su propio
comentario dice que **no cede por «una pantalla más», cede por corrección medida**, y estos tres
salieron de mirar un pedido real. Ni un importe ni una condición se calculan en el cliente:
recomponerlos habría costado menos bytes y una divergencia.

⚠️ **`L3` NO se resolvió cambiando el rótulo compartido, sino separando la clave.** En la CESTA los dos
importes **sí** son la señal y su resto, así que «Señal» es correcto allí y `deposit_card_note` se
queda como está. Cambiar la clave común habría arreglado una pantalla y roto otra — y el diff habría
parecido más limpio.

### 18.6 Lo que queda pendiente de decisión del owner

Tres cadenas, **una palabra cada una y un solo sitio donde se cambian**. Se decidieron aquí porque el
owner delegó explícitamente el rótulo que §6.3 llevaba parado desde `#127`:

| Clave | Dice hoy |
|---|---|
| `tickets.reservation_paid_note` | «Pagado por web :paid · :rest en el parque» |
| `tickets.reservation_paid_note_desk` | «Ya pagado :paid · :rest en el parque» |
| `tickets.ledger.cash_caption` | «Es el dinero que ya te hemos cobrado por este pedido. Puedes cotejarlo con tu extracto bancario.» |
