# [SPEC] El LIBRO del pedido — cada gestión con su línea (+/−), un total y un saldo que se liquida en el parque

> Estado: ✅ **diseño aprobado por el owner** (2026-09-01) · 🟦 **T1 EN EL ÁRBOL** (§6.1) · sigue la
> T2 · Última actualización: 2026-09-01 · Decisiones: `DECISIONES #305` (la decisión de PRODUCTO)
> y `#306` (la T1) · Sustituye, cuando se ejecute entera, al modelo de DOS EJES de
> `specs/desglose-dinero-cliente.md` §10 (`DECISIONES #127`), que pasa a HISTÓRICO.
>
> **Base medida**: commit `78265ec` (main, 2026-09-01), BD local con 22 pedidos pagados/cancelados,
> `ENTORNOS`: **0 pedidos en producción**.

## 0. En una frase

El desglose deja de ser un **balance neteado** (dos ejes, cinco canales, cuatro mecanismos de frase)
y pasa a ser un **libro de movimientos**: cada gestión escribe **un hecho con su importe y su
signo**, el total es la suma, y el saldo entre lo que vale y lo que se ha pagado **se liquida en el
parque** — positivo se paga, negativo se devuelve. **Nada se cobra ni se devuelve online después
de la reserva** salvo que un operador lo haga a mano desde el panel (`#244` sigue en pie).

## 1. Contexto y problema — medido, no afirmado

### 1.1 Lo que el owner decidió (2026-09-01)

| | Decisión `[DECIDIDO owner]` |
|---|---|
| D1 | **Cliente y operador ven lo MISMO**: cada acción como una línea «+» o «−» con su fecha, un **Total**, y el saldo: «a pagar en el parque» o «a devolver en el parque». *«Es una suma / resta sencilla de varias líneas.»* |
| D2 | **No se hacen cobros online post-reserva ni reembolsos automáticos.** *«El total es lo que haces cuando llegas a las instalaciones: o pagas lo que falta o te pagan a ti lo que te falta.»* (Rectifica su primer planteamiento, que pedía las dos cosas; es coherente con `#244` y con la fase 2 de `specs/cumple-mixto.md` §20.2.) |
| D3 | **Sin regímenes**: un pedido con señal y uno pagado entero siguen **la misma regla**; la señal solo hace que el saldo nazca positivo. |
| D4 | **El descuento de fiesta mixta es una línea «−» como las demás y entra en el saldo** (*«no quiero dejar deuda»*): cae el tope de cobertura de la T4 (`#296`) y con él la línea aparte «a tu favor». |
| D5 | **El reembolso manual del panel se queda** (Redsys REST o registro manual, decisión del operador) y sale como línea «Devuelto»: es el único canal para un pedido **cancelado**, donde no hay visita. |
| D6 | *«Profesional, sin ambigüedades, sin deuda»*: el modelo viejo **se retira**, no convive. |

### 1.2 Por qué el balance «exige razonar»: medido sobre pedidos reales de la BD local

**`T4-PRB01`** (pack con señal; cuatro gestiones: 4→3→2→9 invitados, y un descuento mixto de
−4,00 € que después se retiró). Lo que ve hoy el cliente:

    +7 Cumpleaños Jump               105,00 €      ← etiqueta del ÚLTIMO cambio, importe del NETO
    Resto de la señal                 20,00 €      ← 50 − 15 − 15: las dos bajadas están AQUÍ dentro
    Valor del pedido                 135,00 €
    «Al reservar se facturaron 10,00 €. El pedido cambió después y ahora vale 125,00 € más.»
    «Te quedan 125,00 € por pagar en recepción al llegar.»

La reserva pasó de 4 a 9 invitados (+5) y la pantalla dice «+7». Las dos bajadas de −15,00 € no
tienen línea. La T5 (`#298`, adenda 4) tuvo que poner «Ver historial» **junto al dinero**: es la
muleta que confiesa que el balance no explica.

**`T5-PRB01`** (100 % online, 4→2): la bajada de −30,00 € **no existe como hecho con importe en
ninguna tabla de dinero**. `OrderItemEditor::creditReduction()` escribe un ajuste de **0 €**
(`Order::recordReductionMarker()`) y el «Pendiente de devolución 30,00 €» se **deriva** en lectura:
`retenido (60) − pagadoOnline (30)`, donde `pagadoOnline` sale de `Order::itemOriginalOnlineCents()`,
que reconstruye el «online original» con `TicketType::depositCents()` **del catálogo de hoy**.

▶ Ésa es la raíz del **fantasma de la señal** (`DEUDA.md`, ficha del 2026-09-01): en `T4-PRB01` la
card por reserva dice `pendienteDevolución = 20,00 €` y el pedido dice `0` — medido en vivo:
`itemOriginalOnlineCents = 3000` porque el producto declara HOY señal fija de 30,00 € cuando se
cobraron 10,00. *Que un importe se reconstruya desde la configuración viva es la clase de defecto
que el SELLO (`#288`, `PAY-19`) cerró para los tramos: registrar el hecho, no re-derivarlo.*

**El peso de derivar en vez de leer**: `Order` (2.363 líneas) + `OrderFinancialSummary` (413) +
`ReservationFinancials` (154) + `OrderLedger` (433) ≈ **3.400 líneas** para contestar «¿cuánto vale
y cuánto se debe?», y **cuatro** mecanismos de FRASE (`OrderLedger::noteFor()` con 7 casos
ordenados, `invoicedNoteFor()`, `inFavourHintFor()`, `hasCash()`) que existen porque los números
neteados no explican. En un libro, cada línea es su propia explicación.

### 1.3 La cronología YA está en la BD — en tres sitios que nadie lee juntos

| Hecho | Dónde vive hoy | ¿Es un hecho con importe? |
|---|---|---|
| Nacimiento del pedido | `orders.total` (`OrderCreator` guarda el **subtotal completo**) + filas de `order_items` | ✓ |
| Reparto señal / parque de cada línea al nacer | `order_adjustments.deposit_remainder` (positivo, `reason = deposit_remainder`, contexto nulo) | ✓ |
| Subida por edición (cantidad, producto, fecha, complemento) | `order_adjustments.extra_due` (+Δ, contexto `changes`), atada a SU línea | ✓ |
| Bajada por edición **cubierta** por puerta | créditos negativos `extra_due` / `deposit_remainder` en cascada (`creditReduction`) | ✓ solo si la puerta cubre TODO |
| Bajada **parcialmente** cubierta | los créditos… y el RESTO no se persiste (`pending_refund` solo se devuelve al correo) | ✗ |
| Bajada **no cubierta** (pedido 100 % online) | marcador `extra_due` de **0 €** | ✗ (el importe se reconstruye) |
| Cancelación de línea / pedido | la fila conserva `quantity × unit_price` y gana `cancelled_at` | ✓ (derivable sin configuración) |
| Suplemento / descuento de fiesta mixta | línea hija + ajuste gemelo `extra_due` ±, **reconciliados en el sitio** (`forceFill`) | ✓ el importe vigente; la historia solo en `audit_logs` |
| Compensación (cortesía) | **derivada al leer**: `compensado() = max(0, devuelto − sinRespaldo)` | ✗ |
| Cobro online / en taquilla | `payments` (`paid`, `paid_at`, `provider`) | ✓ |
| Devolución | `payment_refunds` (importe, `status`, `mode`, `intent`, fechas) | ✓ |
| Liquidación en el parque | **implícita**: franja pasada ∧ pedido cobrado (`itemGateResolved`) — `[DECIDIDO owner]` D9 de la T5 («Liquidado», nadie registra el cobro) | regla, no fila (se conserva) |

Y **`Order.total` es lo facturado**, no «lo cobrado online»: el docblock de `Order::applyExtraDue()`
lo afirma al revés y `OrderCreator` lo desmiente (`'total' => $subtotal`). Se corrige en la tanda.

### 1.4 El prototipo: el modelo del libro cruzado contra `OrderLedger` en los 22 pedidos

Antes de escribir una línea de diseño se implementaron **las fórmulas de §4.1 en un guion de
lectura** (fuera del repo) y se compararon con el modelo de hoy en **total, saldo, cobro y
nacimiento**:

    19 de 22 pedidos: IDÉNTICOS en las cuatro cifras
     3 de 22: DATOS SUCIOS, y los dos modelos los marcan
       R-IBX8B1 · R-D3AN8Q  → `paid_at` puesto con un `Payment` que NO está pagado (siembra)
       T4-PRB01             → pedido FABRICADO por una sonda con `Order.total` = la señal (10,00)
                              en vez del subtotal (60,00); con 60,00 cierra al céntimo

Y en una primera pasada **desde las tablas de dinero solas** el libro cerró en 16 de 22: los 6 que
no cerraban eran **(a)** ediciones anteriores a `#150` (sin marcador; solo constan en
`audit_logs.price_diff_cents`), **(b)** la compensación derivada, **(c)** `T4-PRB01`. Ninguno es
un defecto del modelo: son los tres hechos que faltan (§4.2) y un dato fabricado.

## 2. Objetivo

1. El cliente y el operador leen **la misma lista**: una línea por gestión con signo y fecha, el
   Total, lo pagado y el saldo con su sentido. Sin frases que reemplacen números.
2. **Ningún importe del desglose se reconstruye desde configuración**: todo sale de hechos con
   fecha (filas de `order_items`, movimientos, pagos, reembolsos). El fantasma de la señal muere
   por construcción.
3. Las identidades del dinero siguen **evaluándose en ejecución** (`is_consistent`) y **se hacen
   más estrictas** (§4.4): nacimiento, caja y libro.
4. El modelo viejo **se retira entero** (clases, campos del contrato, claves de idioma, guardas),
   no convive.

**Fuera de alcance** (por decisión, §1.1): cobro online post-reserva · reembolso automático · el
AFORO de fiestas mixtas (`[owner]`) · el registro explícito del cobro en puerta
(`OrderAdjustment::TYPE_COLLECTED_IN_PERSON`, declarado y sin uso — **se retira** como código
muerto, no se construye: D9 de la T5 decidió que la liquidación es implícita).

## 3. Opciones consideradas

| | Opción | Por qué no / por qué sí |
|---|---|---|
| A | **Un diario «encima»**: tabla nueva `order_movements` escrita en paralelo a los ajustes de hoy; el libro la lee, los cubos de puerta siguen mandando la liquidación | Dos escrituras por gestión = **dos verdades** (la clase de divergencia de la que nació `OrderLedger`). Deja el modelo viejo vivo debajo: **deuda** |
| B | **Reinterpretar las filas de golpe**: cambiar en una tanda escritura y lectura de los cubos de puerta | Toca `Order`, el editor, la reconciliación mixta y las 9 superficies **sin oráculo** que diga si el resultado es el mismo. En el corazón del `CRITICAL_RE`, inaceptable |
| **C** | **Hechos primero, libro después, retirada al final** — tres tandas, cada una en verde y sin deuda propia: (T1) cada gestión deja su importe como hecho, sin cambiar lo que se pinta; (T2) el libro se compone desde los hechos **con el modelo viejo de ORÁCULO** (guarda puente: 15 escenarios + corpus); (T3) las superficies pintan el libro, cae el tope de la T4 y se retira el modelo viejo | ✅ **Elegida.** El puente de la T2 es lo que §1.4 ya hizo a mano: 19/22 idénticos |

## 4. Diseño elegido

### 4.1 Vocabulario y aritmética (por línea → por reserva → por pedido)

Por **línea** `i` (`order_items`; principal o complemento; una línea de crédito mixto tiene
`fila < 0` por `is_credit`):

    fila(i)        = chargedSubtotalCents()                       (unidades cobradas × precio; con signo)
    vivo(i)        = fila(i) si la línea y el pedido no están cancelados, si no 0
    Δ(i)           = Σ movimientos de EDICIÓN atribuidos a i        (§4.2: kind edit · mixed)
    nac(i)         = fila(i) − Δ(i)                                 (con qué nació; 0 si la creó una edición)
    resto(i)       = Σ filas de reparto de señal al nacer          (deposit_split; 0 sin señal)
    online_nac(i)  = nac(i) − resto(i)   si el pedido se cobró, si no 0   («sin cobro no hay cobro»)
    dev(i)         = reembolsos con éxito atribuidos a i            (los totales, a prorrata — se conserva)
    cortesía(i)    = Σ movimientos kind = courtesy atribuidos a i   (≤ 0)

Por **reserva** `r` (principal + sus complementos):

    Total(r)       = Σ vivo(i) + Σ cortesía(i)
    Pagado(r)      = Σ online_nac(i) − Σ dev(i)
    Liquidado(r)   = max(0, Total(r) − Pagado(r))   si resuelta(r) (franja pasada ∧ pedido cobrado), si no 0
    Saldo(r)       = Total(r) − Pagado(r) − Liquidado(r)

Por **pedido**:

    Total     = Σ Total(r)          Cobrado = Σ payments paid        Devuelto = Σ refunds succeeded
    Liquidado = Σ Liquidado(r)      Saldo   = Σ Saldo(r)  ( = Total − (Cobrado − Devuelto) − Liquidado por I2 )

**Identidades, evaluadas en ejecución** (sustituyen a `PAY-16`/`PAY-17`, §5):

    I1 · nacimiento   Order.total       == Σ_i nac(i)
    I2 · caja         Cobrado           == Σ_i online_nac(i)        (y 0 si el pedido no se cobró)
    I3 · libro        Total             == Σ movimientos de valor  (nacimiento + Σ Δ − cancelaciones + cortesías)
    I4 · columna      Order.refund_amount_cents == Σ refunds succeeded   (la de hoy, `#127`)
    cuadra = I1 ∧ I2 ∧ I3 ∧ I4   →   si no: «en revisión» (la conducta de #132, intacta)

▶ **Verificado en §1.4**: I1 caza `T4-PRB01` (fabricado) e I2 caza `R-IBX8B1`/`R-D3AN8Q` (pagados
sin pago). El modelo de hoy también los marca; el nuevo los marca **diciendo cuál identidad**.

⚠️ **Por qué `nac(i)` es un hecho y no una reconstrucción**: `fila(i)` es la fila; `Δ(i)` son filas
con importe; ninguna de las dos consulta `TicketType`. Hoy `itemOriginalOnlineCents()` llega al
mismo número **pasando por `depositCents()` del catálogo vivo** — es exactamente lo que se retira.

⚠️ **Por qué el saldo es por RESERVA y no solo por pedido**: un pedido con dos reservas en días
distintos se liquida en **dos visitas**. El cliente ve el saldo del pedido; la puerta, la hoja de
sala y la sub-tarjeta ven el de SU reserva. Las dos cifras salen de la misma suma.

### 4.2 Los hechos: qué escribe cada gestión (una fila por gestión y por línea afectada)

Las filas de `order_adjustments` pasan a ser **hechos**, y **`type` es el ÚNICO discriminador**
(▶ derivación de la T1, §6.1: el primer borrador esbozaba una columna `kind` junto a `type`; dos
columnas para una sola clasificación son ambigüedad, y `type` con cuatro valores cerrados basta).
Tipos:

| `type` | Quién la escribe | Importe | Contexto |
|---|---|---|---|
| `deposit_split` (hasta la T1, `deposit_remainder` de nacimiento) | `OrderCreator` (principal y, en la Opción A del origen, cada complemento de pago de un pack con señal) | ≥ 0 | `null` |
| `edit` | `OrderItemEditor` vía `Order::recordEdit` (cantidad · producto · fecha con re-tarifa `PAY-18` · complemento añadido/subido · re-escala per-invitado) | **el delta ENTERO** con signo, por línea afectada | `changes` (las claves de hoy) |
| `mixed` | `MixedPartySurcharge` (suplemento o descuento) | el vigente, con signo, **reconciliado en el sitio** (línea viva) | `mixed_party` (el de hoy) |
| `courtesy` | `Order::executePartialRefund()` / `executeFullRefund()` | ≤ 0 | `{refund_id}` |

- **La bajada escribe UNA fila con el delta entero** (kind `edit`, negativa). Muere la cascada de
  créditos de `creditReduction()` (`applyGateCredit`, `applyDepositRemainderCredit`) y el marcador
  de 0 €: la liquidación ya no se **asigna** a cubos, se **deriva** del saldo (§4.1).
- **Cancelar no escribe fila**: `cancelled_at` + la fila que conserva su importe **son** el hecho.
  El libro compone la línea «Cancelado: X» desde ahí.
- **La línea mixta es una línea VIVA**, no un apunte por guardado: la T4 la reconcilia (idempotente,
  N guardados → una línea) y esa doctrina no cambia; su historia sigue en «Ver historial»
  (`mixed_party_surcharge_synced` lleva `old_cents`/`new_cents`). Decisión derivada (§4.9).
- **Cortesía**: al registrar un reembolso, `cortesía = max(0, importe − debido_antes)`, donde
  `debido_antes = max(0, −Saldo)` del ámbito del reembolso (la reserva si va atada a línea; el
  pedido si es total), **en la misma transacción** que la fila del reembolso. Con `intent =
  paid_in_person` **no** hay cortesía: ese reembolso re-canaliza (el saldo pasa a «a pagar en el
  parque», que es lo que significa). Con `compensation`, `value_returned` o sin intención, el
  exceso sobre lo debido es cortesía. ▶ Es la regla que `compensado()` aplica hoy **al leer**,
  escrita **al ocurrir**; y resuelve `L4` (`#133`, aparcado): «Compensación devuelta» deja de ser
  un canal opaco y es una línea «−» con fecha.
- **El tope de la T4 cae** (D4): `MixedPartySurcharge::applyCredit()` escribe el crédito
  **derivado entero** (`min(derivado, cobertura)` → `derivado`); `gateCoverageCents()` e
  `inFavourCents()` se retiran. Un pedido 100 % online con −8,00 € de descuento queda con
  `Saldo = −8` → «A devolver en el parque 8,00 €». La cota de `specs/cumple-mixto.md` §20.3 (el
  crédito nunca deja una reserva en negativo) sigue siendo cierta y se conserva como guarda.

### 4.3 El libro: `Booking\Services\OrderBook` (futuro)

Value object **inmutable**, compuesto UNA vez por pedido (`forOrder`) o por reserva
(`forReservation`), como hoy `OrderLedger`. Publica:

| Campo | Qué es |
|---|---|
| `movements[]` | las líneas de VALOR, **cronológicas**: `booking` (+`Order.total`, `created_at`) · `edit` · `cancel` · `mixed` · `courtesy`. Cada una: `kind`, `label` (compuesta por el dominio), `amount_cents` con signo, `occurred_at` (ISO) y `occurred_label` (`d/m/Y`), `reservation_id` (`null` en el nacimiento y en una cortesía de pedido) |
| `settlements[]` | las líneas de DINERO: `payment` (+, `paid_at`, método `web`/`desk`) · `refund` (−, `status` succeeded/pending/failed, `mode` rest/manual, fecha) · `gate` (liquidado en el parque, fecha = fin de la franja) |
| `total_cents` | `Total` |
| `paid_cents` | `Cobrado − Devuelto + Liquidado` (solo reembolsos con éxito) |
| `balance` | `{cents (con signo), kind}` — §4.4 |
| `is_consistent` · `note` | como hoy; `note` queda para **tres** casos: `under_review`, `expired`, `pending_payment` |
| `has_deposit` | como hoy (hecho de dominio que la app móvil consume) |

**Etiquetas** (dominio, `tickets.journal.*`, en los idiomas que exige
`ClientMoneyLabelsAreTranslatedTest`), **neutras de voz** para que el mismo diccionario sirva al
cliente y al panel (D1): «Reserva realizada» · «Cantidad: 4 → 2» (la forma de la guarda O de la T5)
· «Cambio de fecha a …» / «Cambio a X» / «+3 Calcetines» (las de `breakdownLabel()`, que se muda a
un compositor `MovementLabel` (futuro)) · «Precio del día: 40,00 → 30,00» (re-tarifa sola) ·
«Cancelado: X · N invitados» · las dos de fiesta mixta de hoy · «Compensación» · «Pagado online» /
«Pagado en recepción» · «Devuelto a la tarjeta» / «Devuelto en el parque (registrado)» con estado
«en curso» / «fallida» · «Liquidado en el parque». En un pedido con **más de una reserva** cada
línea de valor lleva delante el nombre de su reserva.

**Por reserva**: los mismos campos acotados a sus líneas; `booking` se sustituye por `nac(r)`
(«Reserva realizada» de esa reserva), y `payment`/`refund` viajan atribuidos (`online_nac`, `dev`).

### 4.4 Las reglas del saldo (`balance.kind`) — cerradas

| Situación | `kind` | Importe | Rótulo |
|---|---|---|---|
| Pedido cobrado, `Saldo > 0` | `pay_at_park` | `Saldo` | «A pagar en el parque» |
| Pedido cobrado, `Saldo < 0`, **hay una reserva viva sin finalizar** | `refund_at_park` | `−Saldo` | «A devolver en el parque» |
| Pedido cobrado, `Saldo < 0`, **no habrá visita** (pedido cancelado, o todas las reservas finalizadas/canceladas) | `refund_pending` | `−Saldo` | «Pendiente de devolución» (el operador decide el canal: D5) |
| Pedido cobrado, `Saldo = 0` | `settled` | 0 | (sin línea) |
| Pedido **sin cobrar** y aún pagable (`canBeRetried()` / pendiente) | `pay_online` | `onlineDueCents()` | «Pendiente de pagar por web» (+ «el resto, X, en el parque» si hay señal) |
| Pedido **caducado** sin cobro | `expired` | 0 | `note = expired` (la frase de hoy) |
| `cuadra = false` | `under_review` | 0 | `note = under_review`; el cliente ve Total y Cobrado, **no** el libro (la asimetría de `#132`) |

⚠️ Un reembolso **en curso** o **fallido** sale como línea de `settlements` con su estado y **no
entra en `paid_cents`**: el saldo sigue diciendo «a devolver X» mientras el dinero no ha vuelto, y
`PAY-09` (la capacidad cuenta los pendientes) impide devolverlo dos veces. Visible para los dos.

### 4.5 El contrato (`openapi/v1.yaml` → `Ledger`, **primero**, como manda `specs/api-v1.md`)

    Ledger:
      total_cents · paid_cents
      balance: { cents: int (signed), kind: pay_at_park|refund_at_park|refund_pending|settled|pay_online|expired|under_review }
      movements:   [ { kind, label, amount_cents, occurred_at, occurred_label, reservation_id } ]
      settlements: [ { kind, label, amount_cents, occurred_at, occurred_label, status, method } ]
      has_deposit · is_consistent · note

**Se retiran** `value`, `cash`, `invoiced_cents`, `invoiced_hint`, `gate_lines`, `in_favour_hint`
(`LedgerValue` y `LedgerCash` desaparecen del contrato). Es un cambio incompatible de `v1` y es
asumible: el único consumidor es el cajón (la app móvil es Fase 6 y no existe). `OrderResource`,
`OrderItemResource` (por reserva) y `GateReservation` lo consumen; `LedgerResource` transcribe.

### 4.6 Las superficies — las NUEVE pintan el MISMO libro

| # | Superficie | Qué pinta |
|---|---|---|
| 1 | Panel · «Totales del pedido» (`order-totals.blade.php`) | el libro del pedido: movimientos, Total, liquidaciones, saldo. Además, **solo aquí**: el aviso rojo de `cuadra = false` y el botón «Ver historial» (eventos que no son dinero) |
| 2 | Panel · sub-tarjeta por reserva (`reservation-financials.blade.php`) | el libro de la reserva + su saldo |
| 3 | Panel · modal del calendario | reusa 2 |
| 4 | Panel · columna «Total» de la lista | `total_cents` (= `totalFinalNeto()` de hoy) |
| 5 | Taquilla (`ManualOrderFulfiller`, `onlineDueCents()`) | **sin cambio**: es el nacimiento |
| 6 | Hoja PDF «con precios» (`reservation-slip.blade.php`) | el libro de la reserva + su saldo. La hoja OPERATIVA sigue **sin un euro** (guarda P de la T5) |
| 7 | Correos (`OrderConfirmation`, `OrderItemModified`, `OrderItemRefunded`, `OrderRefunded`, `MixedPartySurchargeChanged`) | la(s) línea(s) de la gestión + Total + saldo, compuestos desde el libro **al enviar** (se reenvían). El texto `reduction_pending_refund` pasa a decir «se te devolverán en el parque» — ya no es una promesa vaga: es D2 |
| 8 | Cliente (`OrderResource` → `orders.js::financialsOf` → `PurchaseCard.vue`) | el libro del pedido |
| 9 | Puerta (`GateReservationsReader` → `reservation.blade.php`) | saldo de la reserva («A cobrar» / «A devolver» / «Nada pendiente») + las líneas mixtas como explicación; **muere** la línea «a tu favor» |

Más `resources/js/sidebar/outcome.js` (desenlace del pago), que lee el ledger y se re-apunta.

### 4.7 Lo que MUERE (la lista es el compromiso de «sin deuda»)

**Dominio**: `OrderLedger` (con `hasCash`, `hasBreakdown`, `noteFor` salvo 3 casos, `invoicedNoteFor`,
`inFavourHintFor`) · `OrderFinancialSummary` (`compensado`, `pagadoOnline`, `pendienteOnline`,
`cobradoPuerta`, `retenidoOnline`, `pendienteDevolucion`, `totalWithChanges`, `netOnline`,
`effectiveRefunded`) · `ReservationFinancials` · en `Order`: `itemOriginalOnlineCents`,
`itemChargedAtGateAsDeposit`, `itemPendingRefundCents`, `itemCollectedCents`, `itemExtraDueCents`,
`itemDepositRemainderCents`, `pendingAtGateLines`, `gateLineLabel`, `reservationGateLines`,
`gateBreakdownLines`, `depositRemainderPendingByProduct`, `reservationCompensatedCents`,
`applyExtraDue`, `applyGateCredit`, `applyDepositRemainderCredit`, `recordReductionMarker`,
`onlineBackingProductsCents`, `lastRefundIntent` (la intención solo la leen los correos) · en
`OrderItemEditor`: `creditReduction` · en `MixedPartySurcharge`: `gateCoverageCents`,
`inFavourCents`, el tope · `OrderAdjustment::TYPE_COLLECTED_IN_PERSON` (sin uso) · el docblock
falso de `applyExtraDue` sobre `Order.total`.
**Se conservan** (son hechos o reglas): `itemRefundedCents` + `unattributedRefundShareFor` (la
prorrata de un reembolso total: determinista y sin configuración), `itemGateResolved` (la regla de
liquidación implícita, D9), `refundableCapacityCents` (`PAY-09`), `isVoidedLeftoverItem`
(re-escrito sobre `online_nac`/`dev`), `chargeMethod`/`chargedAtLabel`.
**Contrato / cliente**: `LedgerValue`, `LedgerCash`, `gate_lines`, `in_favour_hint`, `invoiced_*`;
`financialsOf` y `PurchaseCard.vue` se reescriben; las claves `tickets.ledger.*`,
`tickets.ledger_in_favour`, la mayor parte de `tickets.ledger_note.*`,
`admin.orders.order_financial.*` y `admin.orders.item_financial.*` se retiran (quedan las tres
notas).
**Guardas**: las que aseveran el modelo viejo se **re-apuntan o se retiran** (§6): `ItemPriceChangeReconstructionTest`
(la reconstrucción ya no existe: sus 14 casos pasan a aseverar `nac`/`online_nac` como hechos),
`OrderGateCreditTest`, `ReservationFinancialsTest`, `OrderTotalsBreakdownTest`,
`MeOrdersFinancialsTest`, `orders.test.js` (52), `SidebarAccountParityTest` (`invoiced_hint`), los
tres casos del tope en `OrderFinancialInvariantsTest`, `LedgerSingleSourceTest` (patrones nuevos).
**Doc**: `specs/desglose-dinero-cliente.md` pasa a HISTÓRICO con cabecera que apunta aquí; las
fichas de `DEUDA.md` del fantasma de la señal y de `L4` se cierran; `INVARIANTES` §5.

### 4.8 Los datos que ya existen (0 en producción)

Una **migración de hechos** (futuro), idempotente, que hace converger cualquier base sin paso manual:
1. añade `kind` y renombra el nacimiento a `deposit_split`;
2. por cada gestión de edición ya escrita (grupo = misma línea, mismo `context.changes`, mismo
   segundo de `created_at`, mismo `applied_by`): **una** fila `edit` con el delta calculado **desde
   su propio contexto** (`nueva_cantidad × nuevo_precio − antigua × antiguo`, ambos en el
   contexto desde `#150`) y se borran los créditos de cascada y el marcador que la componían;
3. las ediciones **anteriores a `#150`** (solo en el corpus local/staging; `audit_logs`
   `orders.item_edited` lleva `price_diff_cents` y `addon_upcharge_cents`) generan su fila desde
   el rastro — es el único uso de `audit_logs` como fuente, y solo para datos que no volverán;
4. cortesías históricas: una fila por reembolso con exceso, con la regla de §4.2 evaluada sobre el
   estado anterior a ese reembolso;
5. lo que siga sin cerrar (I1–I4) queda **en revisión**, que es su estado real: `T4-PRB01`
   (fabricado), `R-IBX8B1`/`R-D3AN8Q` (siembra sin pago) y `R-L6UTIA` (`#131`).

### 4.9 Decisiones derivadas (tomadas aquí; el owner puede vetar cualquiera)

- Orden **cronológico ascendente**; fechas `d/m/Y` (la hora vive en el historial).
- **Cada línea afectada por una gestión, su propia línea** (una re-escala per-invitado sale como
  «Menú por invitado: 4 → 2» debajo de «Cantidad: 4 → 2»): fiel y sin netear.
- **El nacimiento es UNA línea** (`Order.total`); la lista de reservas de encima sigue como hoy,
  con cantidades e importes ACTUALES.
- **Etiquetas neutras de voz** (sin «tu»/«el cliente»): un diccionario para los dos lectores.
  Las tres notas que sobreviven mantienen su voz de hoy.
- El «Ver historial» del panel (T5, adenda 4) **se queda**: cuenta lo que no es dinero.
- `has_deposit` se sigue publicando (hecho de dominio con consumidor futuro).

## 5. Impacto en invariantes

| ID | Cambio |
|---|---|
| `PAY-16` | **Se reescribe**: «EL LIBRO DEL PEDIDO CIERRA» — I1 nacimiento · I3 libro, evaluadas en ejecución; los «cinco canales» desaparecen |
| `PAY-17` | **Se reescribe**: «EL SALDO SE LIQUIDA EN EL PARQUE» — I2 caja; `Saldo = Total − Pagado − Liquidado`; el sentido lo da el signo; **nada se cobra ni se devuelve online post-reserva sin un operador** (D2/D5); un reembolso en vuelo no baja el saldo |
| `PAY-19` | Intacta en su regla; su frase «cargo/crédito de puerta» pasa a «movimiento» |
| `PAY-09` · `PAY-10` · `PAY-18` | Intactas (`PAY-10`: la Opción A del origen se conserva como `deposit_split` del complemento) |
| `CRITICAL_RE` | Toca `OrderCreator`, `OrderItemEditor`, `MixedPartySurcharge` → cada tanda empuja con `VERIFY_CONC=1` tras los verificadores de `INVARIANTES` §6 (`purchase:verify-oversell`, `redsys:verify-concurrency`, `mixed-party:verify-concurrency` en sus DOS escenarios) |

Los cambios de `PAY-16`/`PAY-17` son del owner (`CONVENCIONES` §9): se registran en `DECISIONES
#305` y entran en `INVARIANTES.md` en la T3, cuando el modelo viejo deja de existir.

## 6. Plan por tandas, con sus guardas y sus mutaciones

Cada tanda deja el árbol verde, la doc al día (`ESTADO`, tracker, esta spec §7) y **ninguna deuda
propia**. Ningún dinero se mueve de forma distinta hasta la T3 (donde cambia UNA cosa: el tope de
la T4).

### T1 · Los hechos (1 sesión)
- `kind` en `order_adjustments`; `deposit_split`; **cada bajada escribe su delta entero** (kind
  `edit`; muere la cascada y el marcador); la **cortesía** se escribe al reembolsar; la migración de
  hechos (§4.8). Lo que se pinta **no cambia**: el modelo viejo sigue leyendo las mismas cifras.
- ⚠️ La cascada de créditos alimentaba los cubos que el modelo viejo lee (`itemExtraDueCents`,
  `itemDepositRemainderCents`): en T1 el modelo viejo pasa a derivar esos cubos desde el nuevo
  hecho (`Δ` cubierto = `min(Δ, cobertura)`), con la **guarda puente** de la T2 adelantada a los 15
  escenarios de `OrderFinancialInvariantsTest`: mismas cifras antes y después, escenario a escenario.

| Guarda | Mutación que la pone en rojo |
|---|---|
| A · una bajada 100 % online deja UNA fila `edit` con −Δ (no un 0 €) | volver a escribir el marcador |
| B · una bajada parcialmente cubierta deja el delta ENTERO | escribir solo la parte cubierta |
| C · I1 (`Order.total == Σ nac`) en los 15 escenarios + `Order.total` fabricado → `cuadra = false` | quitar `Δ` de `nac` |
| D · la cortesía se escribe en la MISMA transacción que el reembolso, con la regla de §4.2 (los tres intentos, con y sin exceso) | escribirla en post-commit / contar `paid_in_person` |
| E · la migración es idempotente (correr dos veces = una) y reconstruye `T5-PRB01` y `R-DWFRDP` al céntimo | — (se corre sobre la copia del corpus) |
| F · puente: los 15 escenarios dan las MISMAS cifras que en `78265ec` | cualquier cambio de lectura |

### 6.1 · ✅ T1 EJECUTADA (2026-09-01, `DECISIONES #306`)

**En el árbol.** `OrderAdjustment` con los cuatro tipos (`type` único; `TYPE_EXTRA_DUE`,
`TYPE_DEPOSIT_REMAINDER` y el `TYPE_COLLECTED_IN_PERSON` sin uso, retirados) ·
`Order::recordEdit(item, Δ con signo)` como ÚNICA escritura de una gestión (audit
`orders.extra_due_applied` para Δ>0 y **`orders.value_reduction_applied`** para Δ<0; caen
`applyExtraDue`, `applyGateCredit`, `applyDepositRemainderCredit`, `recordReductionMarker`) ·
`OrderItemEditor::creditReduction` escribe UNA fila con el delta entero y calcula el reparto para el
correo con los cubos frescos · la **cortesía** (`Order::recordCourtesyForRefund`) en la 2.ª
transacción de los dos reembolsos, con la regla de §4.2 (`paid_in_person` no la genera; el total se
reparte por resto mayor sobre lo que cada línea recibió por encima de lo debido) ·
**`Booking\Services\GateBuckets`**: el replay TEMPORAL de la cascada en lectura, del que ahora salen
`itemExtraDueCents`, `itemDepositRemainderCents`, `itemOriginalOnlineCents` (= `onlineAtBirth`, un
hecho), `OrderFinancialSummary`, `pendingAtGateLines`, la cobertura del tope de la T4 y la hoja PDF
— **muere en la T3** · `Order::birthValueCents()` y la identidad **I1 en `OrderLedger::cierra`** ·
`OrderCreator` escribe `deposit_split` · la migración
`2026_09_01_000100_order_adjustments_become_movements` (autocontenida, idempotente, seis pasos de
§4.8) · retirados `LegacyGateAdjustmentReconciliation` y `LegacyAddonAdjustmentRepair` con sus
tests, y sus dos migraciones de 2026-06-06 neutralizadas · doc: `MODELO-DATOS` §`order_adjustments`,
`GLOSARIO`, `DEPOSITO.md` (tabla de equivalencias), `INVARIANTES` `PAY-10`, la ficha del fantasma
en `DEUDA.md` RETIRADA.

**Medido.**
- **Foto puente**: `tests/Fixtures/ledger-bridge.json`, tomada en `78265ec` con el modelo viejo,
  **idéntica en los 15 escenarios** tras el cambio — ni un céntimo ni una etiqueta movidos.
- **BD local migrada**: 36 filas (29 `deposit_remainder` + 7 `extra_due`) → 43 en cuatro tipos (26
  `deposit_split` · 13 `edit` · 2 `mixed` · 2 `courtesy`), cero tipos viejos. `T5-PRB01`: el
  marcador de 0 € es `edit −3000`, ledger idéntico. `R-DWFRDP`: idéntico. `R-MOTEHE` y `R-P4NA2I`:
  cortesía escrita y fechada (−2000 / −3000) y la edición pre-`#150` recuperada del rastro;
  `compensado()` idéntico. **`T4-PRB01`: la card pasa de «pendiente 20,00 · online 30,00» a
  0,00 / 10,00** (el fantasma, cerrado) y su total fabricado queda «en revisión» por I1.
- Suite **3711** en verde (24.200 aserciones) · Pint · docs-check · guardas nuevas:
  `EditMovementTest` (A · B · C + la lectura de la cascada, 8 casos), `CourtesyMovementTest` (D,
  6 casos con la guarda puente `Σ courtesy == compensado()`), `OrderRecordEditTest` (7),
  `OrderAdjustmentsBecomeMovementsMigrationTest` (E, 8 casos incluida la idempotencia).

**Lo que la ejecución enseñó.**
- ⚠️⚠️ **Tres fixtures eran ILEGALES y solo se vio al poner la identidad de nacimiento**: el
  guardián ponía `Order.total` = la parte ONLINE (un pack de 120,00 con señal nacía con
  `total = 3000`, y el desglose decía «vale 90,00 € más» sobre un pedido sin tocar); la paridad
  del cajón tenía un pack cancelado «pagado» que el cobro (6.800) no incluía; y una bajada 3→1
  estaba escrita como −12,00 (la mitad que la cascada guardaba). Se **legalizaron** (la lección
  de la T6 de mixtos), no se excepcionó I1 — y el fixture legal de la paridad destapó que ese
  cliente tenía 60,00 € pendientes de devolver que nadie enseñaba.
- ⚠️ La migración **no puede atajar por «tabla vacía»**: un pedido 100 % online editado antes de
  `#150` tiene cero ajustes y su hecho solo está en el rastro (paso 6). Lo cazó su propio test.
- ⚠️ La columna `kind` de la spec **no hizo falta**: un discriminador basta y dos son ambigüedad.
- ⚠️ `payments.payable_type` es un alias de morph (`order`): la migración acepta el alias y los
  dos FQCN históricos para no depender de la configuración.

### T2 · El libro en el dominio (1 sesión)
- `OrderBook` (+ `Movement`, `Settlement`, `Balance`), `MovementLabel`, las claves
  `tickets.journal.*` en todos los idiomas exigidos, I1–I4 en ejecución. **Sin tocar superficies.**
- **La guarda puente**, ampliada: sobre los 15 escenarios (salvo los 3 del tope, que la T3
  invierte) y sobre el corpus local por HTTP, `OrderBook.total == valor − compensado`,
  `OrderBook.balance == pendientePuerta − pendienteDevolución`, `Σ online_nac == cobradoOnline`.
  Es §1.4 convertido en test, y se **borra** en la T3 con el oráculo.

| Guarda | Mutación |
|---|---|
| G · I3: quitar un `kind` del compositor → `cuadra = false` | omitir las cancelaciones |
| H · por reserva y por pedido: `Σ Saldo(r) == Saldo` en los 15 escenarios | atribuir un reembolso total a una sola reserva |
| I · las etiquetas existen en todos los idiomas **sin respaldo** (`Lang::has(…, false)`, la lección de `#134`) | borrar una clave en `fr` |
| J · `balance.kind` en los siete casos de §4.4, con `refund_pending` en un cancelado y `refund_at_park` en uno vivo | invertir la condición de visita |
| K · un reembolso `pending`/`failed` sale en `settlements` y NO en `paid_cents` | sumarlo |

### T3 · Las superficies, el tope y la retirada (2 sesiones)
- Contrato primero (§4.5); las nueve superficies (§4.6) + `outcome.js`; correos; el tope de la T4
  cae (D4) y `mixed-party:verify-concurrency` se re-corre en sus dos escenarios; **se retira** todo
  §4.7; `INVARIANTES` (§5); `DEUDA` (dos fichas cerradas); esta spec a ✅ con §7; el guion headless
  de `VERIFICACION-E2E-CAJON.md` gana el apartado del libro (pedido con señal, pedido 100 % online
  con bajada, pedido cancelado, mixto con descuento) **antes** del ojo del owner.

| Guarda | Mutación |
|---|---|
| L · `LedgerSingleSourceTest`: ninguna superficie resta canales ni cita `OrderLedger`/`financialSummary`; los patrones nuevos se auto-verifican | reintroducir `total −` en un blade |
| M · paridad cliente↔panel: la MISMA lista de líneas (etiquetas e importes) en `order-totals` y en `GET /me/orders` para los 15 escenarios | cambiar una etiqueta en un solo sitio |
| N · el descuento mixto en un pedido 100 % online → línea «−» y `refund_at_park`; en uno con señal → baja «a pagar en el parque» | restaurar el tope |
| O · `PurchaseCard.vue` nombra `movements` y `settlements` (la guarda al MARCADO de `#130`) | `v-for` sobre `[]` |
| P · los correos componen desde el libro al ENVIAR (reenvío tras una edición dice el saldo nuevo) | leer `Order.total` |
| Q · puerta: `refund_at_park` se pinta como «A devolver» y no como «nada pendiente» | — |
| R · la hoja operativa sigue sin un euro (P de la T5, intacta) | — |

**Cómo se demuestra** (DoD): suite en paralelo · Pint · docs-check · los tres verificadores ·
la receta de `specs/desglose-dinero-cliente.md` §4.quater re-corrida sobre el corpus (25 acciones →
25/25 `cuadra` y 25/25 «lo que se pinta suma») · el guion headless · y el **ojo del owner** sobre
`T5-PRB01`, `T4-PRB01` (que verá «en revisión», y es correcto), `R-MOTEHE` y `R-BEEL3E`.

## 7. Revisión y decisión

- 2026-09-01 · **Agente**: análisis empírico (§1.2–§1.4), prototipo de lectura y este diseño. Dos
  preguntas al owner —el descuento mixto en el saldo · el reembolso manual se queda— contestadas
  con *«no quiero dejar deuda, profesional sin ambigüedades»* → D4 y D5.
- 2026-09-01 · **Owner**: *«Perfecto, validado, procede con T1»* → diseño ✅.
- 2026-09-01 · **T1 ejecutada** (§6.1, `DECISIONES #306`). Sigue la T2.
- Entradas: `DECISIONES #305` (la decisión de producto) · `#306` (la T1).
