# [SPEC] El DESGLOSE de dinero que ve el cliente — auditoría y marco

> Estado: **🟦 en revisión** (auditoría CERRADA, diseño pendiente de decisiones) ·
> Última actualización: 2026-08-24 · Decisión asociada: la siguiente entrada libre de
> `docs/DECISIONES.md`, al aprobarse.
>
> ⚠️⚠️ **Esta spec NO se implementa hasta tener el marco entero** (encargo del owner, 2026-08-24):
> es la superficie más crítica del producto y se toca una vez, con todo decidido.
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
owner**, no una que el código pueda contestar (§6.5).

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
canónica (`php artisan redsys:verify-sandbox`, citada en `PAY-08`) y queda pendiente (§7.5).

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

⚠️ `DEMO-0001` está `paid` **sin ninguna fila `Payment`**, así que su `grossPaidOnline` es **0**. Es
sembrado, no un caso real, y cualquier decisión sobre «lo realmente cobrado online» hay que medirla
sobre `DEMO-LEDGER`, que sí tiene su `Payment` de 85,00 €. Si se publica el bruto, este pedido
sembrado enseñará 0,00 € — hay que arreglar la semilla o el siguiente agente leerá un fallo donde no
lo hay.
