# [SPEC] El LIBRO del pedido — cada gestión con su línea (+/−), un total y un saldo que se liquida en el parque

> Estado: ✅ **diseño aprobado por el owner** (2026-09-01) · 🟦 **T1 EN EL ÁRBOL** (§6.1) · 🟦 **T2 EN
> EL ÁRBOL** (§6.2: el libro en el dominio, sin superficies) · sigue la T3 · Última actualización:
> 2026-09-01 · Decisiones: `DECISIONES #305` (la decisión de PRODUCTO), `#306` (la T1) y `#308`
> (la T2) · Sustituye, cuando se ejecute entera, al modelo de DOS EJES de
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

    Total(r)       = Σ_{líneas VIVAS} ( fila(i) + cortesía(i) )     (la cortesía de una línea cancelada se extingue con ella)
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
- ⚠️ **Lo que la T1 dejó dicho para esta tanda** (§6.1):
  - `nac(i)` y `online_nac(i)` **ya existen** como hechos en `GateBuckets::birthValue()` /
    `onlineAtBirth()`: el libro los reutiliza, no los reimplementa (dos fórmulas del mismo número
    es la divergencia de la que nació `OrderLedger`).
  - **El puente diverge A PROPÓSITO con `intent = paid_in_person`**: el modelo viejo cuenta ese
    reembolso como `compensado` (no distingue la intención) y el libro **no escribe cortesía** (§4.2:
    re-canaliza el dinero → «a pagar en el parque»). En ese caso el puente compara `Total` con
    `valor` (no con `valor − compensado`) y `Saldo` con `pendientePuerta − pendienteDevolución +
    compensado`. `CourtesyMovementTest::test_paid_in_person_writes_no_courtesy` lo fija.
  - **`Total(r)` suma solo líneas VIVAS**, cortesía incluida (§4.1): la cortesía de una línea que
    después se cancela se extingue con ella, o un pedido cancelado tras una cortesía daría un total
    negativo. La línea `cancel` del libro vale `−(fila + cortesía)` de la línea en ese momento.
  - **Los fixtures obedecen `I1`** (`Order.total == Σ nac`): uno que no lo haga es un mundo que no
    existe y se LEGALIZA (los tres de la T1, en §6.1), nunca se excepciona la identidad.
  - La foto puente `tests/Fixtures/ledger-bridge.json` sigue vigilando el modelo viejo hasta la
    T3; la T2 NO la regenera (se regenera solo con una decisión escrita aquí).

| Guarda | Mutación |
|---|---|
| G · I3: quitar un `kind` del compositor → `cuadra = false` | omitir las cancelaciones |
| H · por reserva y por pedido: `Σ Saldo(r) == Saldo` en los 15 escenarios | atribuir un reembolso total a una sola reserva |
| I · las etiquetas existen en todos los idiomas **sin respaldo** (`Lang::has(…, false)`, la lección de `#134`) | borrar una clave en `fr` |
| J · `balance.kind` en los siete casos de §4.4, con `refund_pending` en un cancelado y `refund_at_park` en uno vivo | invertir la condición de visita |
| K · un reembolso `pending`/`failed` sale en `settlements` y NO en `paid_cents` | sumarlo |

### 6.2 · ✅ T2 EJECUTADA (2026-09-01, `DECISIONES #308`)

**En el árbol.** `Booking\Services\OrderBook` (+ `Movement`, `Settlement`, `Balance`,
`MovementLabel`): value object inmutable, compuesto UNA vez por pedido (`forOrder`) o por reserva
(`forReservation`), **lectura pura** sobre las relaciones cargadas (`items.slot`, `items.ticketType`,
`adjustments`, `payments.refunds`), sin catálogo, sin consultas y **sin nombrar `Payments\Models`**
—`Order`, que sí está en la costura, traduce pagos y reembolsos al vocabulario de `Settlement`
(`Order::collectedPaymentFacts()` · `Order::refundFacts()`; `unattributedRefundShareFor` pasa a
pública para que la reserva atribuya con la MISMA prorrata las devoluciones sin línea, también las
en curso)— · las **17 claves `tickets.journal.*`** en es/en/fr y en zh_CN (el panel) · **I1–I4 en
ejecución** (`is_consistent`), con I2 ampliada: un pedido `paid` sin `paid_at` no cuadra · las
guardas **G · H · I · J · K**: `OrderBookTest` (22 casos), `ClientMoneyLabelsAreTranslatedTest`
(la lista CERRADA de etiquetas, cruzada con `lang/es`, sin respaldo, distinta por idioma; y la
línea `gate` bajo la regla D9) y **la guarda PUENTE** en `OrderFinancialInvariantsTest`
(`assertBookBridge`: los 15 escenarios × pedido + reserva — `Total == valor − compensado`,
`Saldo == pendientePuerta + pendienteOnline − pendienteDevolución`, `Pagado == cobrado − devuelto +
cobradoPuerta`, `Liquidado == cobradoPuerta`, la clase del saldo, I3 y H; con la excepción de
`paid_in_person` de §6·T2) · **ninguna superficie ni el contrato se tocan**: `OrderLedger` sigue
siendo la pantalla hasta la T3.

**Medido.**
- **Puente idéntico en los 15 escenarios**, pedido y reserva — **incluidos los 3 del tope**: en la T2
  el tope sigue mandando la ESCRITURA de la línea de crédito, así que el libro lee lo mismo que el
  modelo viejo; la T3 lo invierte y borra el puente. La foto de `78265ec` no se movió.
- **Corpus local, 40 pedidos** (19 pagados · 3 cancelados · 18 caducados; los 22 de §1.4 más los
  caducados): **37/40 idénticos** en total, saldo, pagado, liquidado, `has_deposit`, I3, por reserva y H.
  Los 3 restantes: `R-IBX8B1`/`R-D3AN8Q` **en revisión en los DOS modelos** (I2: `paid_at` con un
  pago no pagado; sin identidad de caja no hay saldo que comparar) y `R-REM7YW` ↓. Distribución:
  18 `expired` · 14 `settled` · 3 `pay_at_park` · 1 `refund_at_park` · 1 `refund_pending` · 3
  `under_review`. (Guion en el scratchpad de la sesión, como el prototipo de §1.4; «por HTTP» es la
  guarda M de la T3, cuando el libro tenga endpoint.)
- Suite **3741** en verde (24.673 aserciones, 1 skipped a propósito) · Pint · docs-check ·
  **7 mutaciones muerden** (omitir la cancelación del compositor → I3 · contar un reembolso en curso
  como devuelto → K · invertir la condición de visita → J · fechar la línea mixta con `created_at` ·
  borrar `journal.gate` en `fr` → I · atribuir un reembolso total a UNA línea → H · medir lo debido
  antes de la cancelación → el defecto de la T1, abajo), control 51/51 · `audit-clock` sobre los
  tres tests con calendario.
- ⚠️ Sin `VERIFY_CONC`: la T2 **no toca ningún fichero del `CRITICAL_RE`** (`OrderCreator`,
  `OrderItemEditor`, `MixedPartySurcharge` intactos); lo que cambió en `Order` es lectura y el ORDEN
  de dos pasos dentro de una transacción que ya iba bajo `lockForUpdate`. Los tres verificadores no
  ejercitan reembolsos, así que no medirían este cambio.

**Lo que la ejecución enseñó.**
- ⚠️⚠️ **La T1 escribía una CORTESÍA FALSA con «también cancelar»** — y es el flujo REAL del panel
  («Reembolsar» + «también cancelar», intent `value_returned`, `ViewOrder`). `executeFullRefund`
  medía «lo debido antes» ANTES de aplicar la cancelación que viaja con el reembolso: con la línea
  todavía viva nada se debía, y los 40,00 € salían ENTEROS como cortesía; el libro contaba
  «Compensación −40,00 · Cancelado 0,00» sobre un pedido cancelado (las cifras cerraban: Total 0,
  saldo 0 — la historia no). Lo cazó `OrderBookTest` al primer intento; `CourtesyMovementTest` no
  tenía el caso y `Σ courtesy == compensado()` tampoco lo cruzaba porque ningún fixture cancelaba.
  Corregido en los dos reembolsos (la cancelación se aplica ANTES de medir lo debido: es parte del
  hecho) + 2 casos con el puente. *Una guarda que compone la HISTORIA ve lo que una que solo suma no ve.*
- ⚠️ **Dos fixtures más resultaron ILEGALES** (los 4.º y 5.º de este carril): `attachSucceededRefund`
  de `OrderFinancialInvariantsTest` creaba la fila del reembolso SIN su cortesía. Pasan por el flujo
  real en modo manual (`executePartialRefund` / `executeFullRefund`); la foto puente no se movió
  porque el modelo viejo no lee `courtesy`.
- ⚠️ **`has_deposit` por RESERVA era CATÁLOGO en el modelo viejo** (`ticketType->hasDeposit()`, la
  configuración viva — justo la derivación que este carril retira) y por eso los cuatro escenarios
  con señal salieron rojos en el puente por reserva: el puente lo cruza solo a nivel de pedido, donde
  el viejo mira el resto de la señal. El libro lo define como HECHO (D-T2·1).
- ⚠️ **`R-REM7YW`: el modelo viejo se contradice a sí mismo** — bajada de precio 40→30 (100 % online)
  + reembolso de 30 + cancelación: el PEDIDO dice «pendiente de devolver 10,00» y su única RESERVA
  dice 0 (`itemPendingRefundCents` de una línea cancelada olvida la bajada anterior: mira lo cobrado
  ACTUAL, no lo original). El libro dice −10,00 en los dos niveles (H). Divergencia a favor del
  libro; el defecto muere con el modelo viejo en la T3 y no se arregla en él.
- ⚠️ **El arnés de mutación nació CIEGO**: filtraba `^\s*Tests:` y la línea del resumen lleva el
  color ANSI DELANTE —siete «vacíos» con las restauraciones en verde—. *Quitar el color antes de
  filtrar*: la undécima variante del instrumento como primer sospechoso.
- ⚠️ **`audit-clock` cazó dos aserciones en UTC**: comparaban `now()->format('d/m/Y')` con
  `occurred_label`, que va en la ZONA del parque — a las 23:59:30 UTC («fin de mes», «fin de año»,
  «medianoche UTC») en Madrid ya es mañana y el test se ponía rojo sin que nadie tocara nada. La
  expectativa correcta es `DisplayTime::format(now(), 'd/m/Y')`. El producto estaba bien; el
  test esperaba en la zona equivocada.

**Decisiones derivadas** (tomadas aquí; el owner puede vetar cualquiera):
- **D-T2·1** `has_deposit` = alguna línea VIVA nació con `deposit_split > 0`. Un hecho: no cambia
  cuando una bajada absorbe el resto entero, ni depende del catálogo.
- **D-T2·2** `balance.cents` va CON SIGNO (contrato §4.5); en `pay_online` es lo que falta POR WEB y
  `rest_at_park_cents` publica el resto — ninguna superficie lo deriva restando.
- **D-T2·3** La línea `mixed` se fecha con su ÚLTIMO importe (`updated_at`): es una línea viva y eso
  es «lo que hoy vale y desde cuándo»; su historia sigue en «Ver historial».
- **D-T2·4** I2 incluye «un pedido `paid` tiene cobro» (`status = paid ∧ paid_at = null` → en
  revisión): caja que no cierra, aunque las sumas den 0 = 0.
- **D-T2·5** Las etiquetas viajan también en zh_CN: D1 exige UN diccionario para cliente y panel,
  y el panel se sirve en chino (precedente: `gate_mixed_party_line`).
- **D-T2·6** El aviso `ledger.no_cuadra` sigue en `OrderLedger` mientras convivan (dos avisos por
  pedido serían ruido); se muda al libro en la T3.
- **D-T2·7** La frase de fiesta mixta la sigue componiendo `OrderAdjustment::breakdownLabel()`
  (`MovementLabel::mixed` delega): una copia sería el duplicado del que nació el ledger. Se muda en la T3.
- **D-T2·8** En un pedido con varias reservas cada línea de VALOR lleva delante el nombre de su
  reserva (`journal.with_reservation`); el nacimiento del pedido, no.
- **D-T2·9** Orden: cronológico ascendente; en el mismo instante, nacimiento → hechos por `id` →
  cancelación (un reembolso «con también cancelar» escribe la cortesía y la cancelación en el mismo
  segundo, y la cancelación se lee después de lo que se lleva).

**Lo que la T3 hereda de aquí** (además de §4.7): mover el aviso `no_cuadra` y la rama mixta de
`breakdownLabel()` al libro · borrar `assertBookBridge` con el oráculo · la prorrata de
`unattributedRefundShareFor` pesa por `itemCollectedCents`, que pasa por `GateBuckets` (muere):
en la T3 pesa por `online_nac` · `OrderBook::forReservation` compone el pedido entero para
evaluar la consistencia (como `OrderLedger::forReservation` con `financialSummary()`): una lista
de N reservas son N composiciones — si una superficie lo nota, `forOrder` una vez y repartir.

### T3 · Las superficies, el tope y la retirada (2 sesiones)
- Contrato primero (§4.5); las nueve superficies (§4.6) + `outcome.js`; correos; el tope de la T4
  cae (D4) y `mixed-party:verify-concurrency` se re-corre en sus dos escenarios; **se retira** todo
  §4.7; `INVARIANTES` (§5); `DEUDA` (dos fichas cerradas); esta spec a ✅ con §7; el guion headless
  de `VERIFICACION-E2E-CAJON.md` gana el apartado del libro (pedido con señal, pedido 100 % online
  con bajada, pedido cancelado, mixto con descuento) **antes** del ojo del owner.

### 6.3 · Diseño fino de la T3 (2026-09-01) — cuatro sub-tandas, el árbol verde en cada una

**Medido antes de diseñar** (`git grep` sobre el árbol en `9c7dad7`): el modelo viejo tiene **40
consumidores** fuera de sus propias clases —9 superficies de §4.6 más `ReservationSlipController`,
`OrdersRelationManager`, `GuestFormController` + `reservation/guests.blade`, `item-summary-flat`,
`GateProfile` (Identity), `outcome.js`— y **25 tests** que lo citan. Ninguno de los dos números cabe
en una sesión sin dejar el árbol rojo a medias, así que la T3 se parte por **superficie**, no por
capa, y cada corte deja verde: mientras un consumidor no se re-apunta, sigue leyendo el modelo viejo,
que convive **solo hasta la T3·4**.

| Sub-tanda | Qué se re-apunta al libro | Lo que cae con ella |
|---|---|---|
| **T3·1 · contrato + API + cajón** | `openapi/v1.yaml` → `Ledger` de §4.5 (**primero**, y `ApiContractTest` exige `required` completo + `additionalProperties: false` en cada esquema nuevo) · `LedgerResource` transcribe `OrderBook` · `OrderResource` (`forOrder`) · `OrderItemResource` (`forReservation`; `shows_deposit_note` = pedido cobrado ∧ `has_deposit` ∧ `balance.kind = pay_at_park`) · `orders.js::financialsOf` + `PurchaseCard.vue` · `outcome.js` (`park_cents` = el saldo `pay_at_park` del pedido; por línea, `deposit_cents`/`gate_remainder_cents` del libro de SU reserva) · claves del cliente `tickets.journal.*` (títulos y rótulos del saldo) | `LedgerValue`, `LedgerCash`, `OrderGateLine`, `gate_lines`, `invoiced_*`, `in_favour_hint` del contrato; `financialsOf` de dos ejes; `orders.test.js` (bloque financiero), `outcome.test.js`, `MeOrdersFinancialsTest`, `SidebarAccountParityTest` (3 casos), `OrderSummaryFieldsTest`, `MeReservationsPageTest`, `LedgerSingleSourceTest` (los seis canales → los campos del libro; guarda O al marcado nuevo) |
| **T3·2 · panel + hoja + puerta** | `order-totals.blade` (movimientos · Total · liquidaciones · saldo · aviso `!is_consistent` · «Ver historial») · `reservation-financials.blade` (el libro de la reserva) · `items-list.blade` · `item-detail.blade` + `CalendarPage` · `OrdersTable` (Total = `total_cents` · Pagado = `paid_cents`) · `OrdersRelationManager` · `ViewOrder` (`pendienteDevolucion()` ×2 → `balance` de clase reembolso) · `ReservationSlip` + `reservation-slip.blade` (líneas de producto como hoy + el libro + UNA caja de saldo) · `GateReservation`/`GateReservationsReader`/`GateProfile`/`reservation.blade` («A cobrar X» · «A devolver X» · «Nada pendiente») · claves `admin.orders.book.*` (es · zh_CN) | `OrderTotalsBreakdownTest`, `ItemsListSubCardTest`, `GateProfileTest`, `MixedPartyParkSurfacesTest` (lo de dinero), `admin.orders.order_financial.*`/`item_financial.*` salvo `heading`/`total`/`principal`/`addons`/`no_cuadra_*` |
| **T3·3 · correos + post-form + el tope** | `OrderConfirmation` · `OrderItemModified` (pierde sus tres céntimos: el libro los dice) · `OrderItemRefunded` · `OrderRefunded` · `MixedPartySurchargeChanged`: **todos** pintan el libro del pedido al ENVIAR (movimientos + Total + pagos + saldo) · `reduction_pending_refund` → «se te devolverán en el parque» (D2) · `GuestFormController`/`guests.blade` sin «a tu favor» · **`MixedPartySurcharge::applyCredit` escribe el crédito DERIVADO entero** (D4): mueren `gateCoverageCents` e `inFavourCents`; `mixed-party:verify-concurrency` en sus DOS escenarios y `VERIFY_CONC=1` | `OrderItemEditor::creditReduction` (su reparto para el correo), `emails.*` del reparto, `guestform.mixed_in_favour`; los 3 casos del tope en `OrderFinancialInvariantsTest` se INVIERTEN (guarda N) y los de `MixedPartySurchargeTest` |
| **T3·4 · la retirada** | `Order` pierde §4.7 y gana `LineFacts` (`nac`/`online_nac`/reparto/cortesía por línea, SIN cascada: sustituye a `GateBuckets`); `itemRefundableRemainderCents`, `isVoidedLeftoverItem` y la prorrata de `unattributedRefundShareFor` pasan a `online_nac`; `onlineDueCents` = Σ líneas vivas (fila − reparto); el aviso `ledger.no_cuadra` y la rama mixta de `breakdownLabel()` se mudan al libro; `INVARIANTES` `PAY-16`/`PAY-17` reescritas y `PAY-19` sin tope; `DEUDA` (L4 cerrada); `desglose-dinero-cliente.md` a HISTÓRICO; guion headless (`VERIFICACION-E2E-CAJON.md`, apartado del libro) y la receta de las 25 acciones sobre el corpus; el ojo del owner | `OrderLedger` · `OrderFinancialSummary` · `ReservationFinancials` · `GateBuckets` · `OrderAdjustment::breakdownLabel` · `tickets.ledger.*` (salvo las tres notas) · `assertBookBridge` + `ledger-bridge.json` · `ReservationFinancialsTest`, `ItemPriceChangeReconstructionTest` (→ hechos), `OrderGateCreditTest`… |

**Decisiones derivadas de la T3** (tomadas aquí; el owner puede vetar cualquiera):
- **D-T3·1** El libro se enseña ENTERO en cuanto la tarjeta/el bloque está abierto: sin un segundo
  «ver más» sobre los movimientos (D1: «es una suma / resta sencilla de varias líneas»; una lista
  plegada obliga a razonar otra vez). El botón «Ver historial» del panel se queda al lado (T5·4).
- **D-T3·2** Cada línea de valor lleva su signo delante (`+60,00 €` · `−30,00 €`), también el
  nacimiento; los pagos en positivo, las devoluciones en negativo; el Total y lo Pagado sin signo.
- **D-T3·3** Con `settled` no se pinta línea de saldo (spec §4.4); con `pay_online` la línea dice
  lo que falta por web y, si hay señal, «+ X en el parque» (`rest_at_park_cents`).
- **D-T3·4** Con `is_consistent = false` el CLIENTE ve el Total, los cobros (`settlements` de clase
  `payment`) y la frase «en revisión»; el panel ve el libro entero y el aviso rojo (la asimetría
  de `#132`). La API publica movimientos y liquidaciones igual: son hechos; decidir qué se pinta
  es de quien pinta.
- **D-T3·5** Los CORREOS de dinero pintan el libro ENTERO del pedido al enviar. La spec dice «la(s)
  línea(s) de la gestión + Total + saldo»; al REENVIAR desde el panel «la gestión» ya no existe, y
  el libro entero es un superconjunto siempre cierto. Un correo de gestión pasa a ser el estado
  de la cuenta ese día.
- **D-T3·6** La lista de pedidos conserva DOS columnas de dinero: Total (`total_cents`) y Pagado
  (`paid_cents`, que incluye lo liquidado en el parque): `Total − Pagado` es el saldo. No se añade
  una columna «Saldo» (no está en la spec; el detalle está a un clic).
- **D-T3·7** La hoja «con precios» conserva las líneas de producto (cantidad × unitario: eso no es
  dinero movido, es qué se compró) y sustituye el reparto viejo + las dos cajas por el libro de la
  reserva y UNA caja de saldo.
- **D-T3·8** La puerta enseña el saldo de la RESERVA con su clase: `pay_at_park` en alerta (como
  hoy «pendiente de cobrar»), `refund_at_park`/`refund_pending` en alerta también (es dinero que
  el empleado tiene que devolver), `settled` «nada pendiente»; y las líneas mixtas siguen como
  explicación (§4.6·9).
- **D-T3·9** `LineFacts` sustituye a `GateBuckets` en la T3·4 y no antes: mientras el modelo viejo
  pinte, el replay de la cascada sigue siendo lo que alimenta sus cubos.

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

### 6.3.1 · ✅ T3·1 EJECUTADA (2026-09-01, `DECISIONES #310`) — contrato + API + cajón

**Lo que entró** (la fila 1 de la tabla de §6.3, entera):
- `openapi/v1.yaml`: `Ledger` de §4.5 (+ `LedgerBalance` · `LedgerMovement` · `LedgerSettlement`),
  con `required` completo y `additionalProperties: false`; las descripciones de `Order.ledger` y
  `OrderItem.ledger` reescritas. Mueren `LedgerValue`, `LedgerCash`, `OrderGateLine`, `gate_lines`,
  `invoiced_*`, `in_favour_hint`. Incompatible sin versión: 0 LIVE, un consumidor, sin app.
- `LedgerResource` transcribe `OrderBook`; `OrderResource` (`forOrder`) y `OrderItemResource`
  (`forReservation`; `shows_deposit_note` = cobrado ∧ `has_deposit` ∧ `pay_at_park`).
- `orders.js::financialsOf` + `PurchaseCard.vue`: movimientos (signo + fecha), Total,
  liquidaciones (las pendientes al 70 %), Pagado, saldo por clase (`orders__balance--{kind}`, color
  por rol: `--warn` / `--refund`) y la nota; con `is_consistent = false`, D-T3·4. `outcome.js`:
  `deposit_cents`/`gate_remainder_cents` por línea y `total_cents`/`park_cents` por pedido, del libro.
- `tickets.journal.*` gana los títulos y los rótulos del saldo (es · en · fr · zh_CN).
- `OrderBook`: `ledger.no_cuadra` (`warning`, por pedido, con las cifras de I1–I4) y **una línea
  fantasma cancelada a 0 € no genera movimiento** (una línea de 0 no dice nada; D-T3·10).
- Tests re-apuntados: `MeOrdersFinancialsTest` (reescrito: API == dominio campo a campo, suma en
  cada escenario, la clase la decide el servidor, etiquetas por `Accept-Language`, `desk`, reembolso
  pendiente listado y no contado, `has_deposit` hecho, no-cuadra + log, sano, sustantivo de la
  cantidad, los datos del formulario fuera), `orders.test.js`, `outcome.test.js`,
  `SidebarAccountParityTest` (3), `OrderSummaryFieldsTest` (+2), `LedgerSingleSourceTest` (los
  campos del libro; guarda O al marcado nuevo), `OrdersTest`, `MeOrdersTest`, `SidebarDomContractTest`,
  `ClientMoneyLabelsAreTranslatedTest`.

**Decisiones derivadas de la ejecución** (además de D-T3·1…9):
- **D-T3·10** Una línea cancelada cuyo valor (fila + cortesía) es 0 no genera movimiento de
  cancelación. Es la traducción al libro de `Order::isVoidedLeftoverItem` (el complemento a 0 € que
  deja un cambio de menú): ninguna otra superficie la nombra y el libro tampoco.
- **D-T3·11** `ledger.no_cuadra` se registra UNA vez por pedido (en `forOrder`), no por reserva:
  `forReservation` compone el mismo libro y registrarlo ahí lo duplicaría por línea.
- **D-T3·12** El color del saldo y de los movimientos negativos es por ROL (`--warn` lo que se
  paga, `--refund` lo que se devuelve), nunca por literal: sale con la paleta de cada cliente.
- **D-T3·13** `balanceCentsOf` de `outcome.js` es privada: se prueba por la conducta de
  `confirmationLine`/`buildConfirmation`, no exportando «para los tests».

**Mutaciones** (sobre árbol commiteado, restaurando con `git checkout` — la regla de `#181`):

| # | Mutación | Resultado |
|---|---|---|
| M1 | `LedgerResource`: `paid_cents` publica `totalCents` | **4 rojos** (`MeOrdersFinancialsTest` ×3, `SidebarAccountParityTest`) |
| M2 | `OrderItemResource`: `shows_deposit_note` sin la clase del saldo | ⚠️ **VERDE** → dos casos nuevos en `OrderSummaryFieldsTest`; ahora **1 rojo** (el del resto liquidado) |
| M3 | `OrderBook`: omitir TODA cancelación | **12 rojos + 1 error** |
| M4 | `OrderBook`: `warning` → `debug` | **1 error** (`…inconsistent_and_logged`) |
| M5 | `orders.js`: la puerta de `is_consistent` nunca se abre | **1 rojo** (42/1; control 43/0) |
| M6 | `outcome.js`: el saldo sin mirar su clase | **1 rojo** (29/1; control 30/0) |

⚠️ **M2 es la lección de la tanda**: la tercera condición de la nota no la vigilaba nadie porque
ningún caso tenía un pack con señal en su estado NORMAL después de la fiesta (resto liquidado en
puerta → `settled`). El caso hermano (reserva cancelada → `refund_pending`) **no discrimina M2** y lo
dice en su docblock: `has_deposit` del libro cuenta solo repartos de líneas VIVAS (T2), así que la
nota cae por la segunda condición.

**Cuatro fixtures ILEGALES, legalizados** (no se excepcionó ninguna identidad): tres pedidos
«pagados» sin `Payment` —`OrderSummaryFieldsTest` ×2 y `SidebarDomContractTest::setUpConfirmedOrder`;
con el modelo viejo nadie cruzaba cobro con estado, con I2 responden «en revisión» y saldo 0— y
`MeOrdersTest::makeOrder`, sin líneas y facturando 1.000 (el libro publica lo que VALE: 0). El cobro
se registra como lo hace `RedsysReturnHandler`: `Payment` pagado por `onlineDueCents()`.

**Dos cosas que la paridad enseñó**: con DOS reservas cada línea de valor lleva delante el nombre de
la suya (§4.3; el nacimiento no), y dos cancelaciones del MISMO segundo las ordena el desempate del
libro (`rank`, `seq`) — la guarda localiza por etiqueta, no por posición.

**Trampas de instrumento**: el bundle SSR rancio (`assertBundleIsNotStale` puso en rojo los once
casos de `SidebarDomContractTest` al tocar `outcome.js` sin `build:ssr` — funcionó) · el YAML con
una descripción con `: ` sin comillas · el reporter de `node --test` resume con `ℹ pass/fail` y una
mutación grep-eada con `# pass` salió MUDA (pareció verde hasta repetirla).

**Lo que la T3·2 hereda**: el panel, la hoja y la puerta siguen leyendo `OrderLedger` /
`OrderFinancialSummary` / `ReservationFinancials` (fila 2 de §6.3); las claves `admin.orders.book.*`
no existen aún; `OrderTotalsBreakdownTest`, `ItemsListSubCardTest`, `GateProfileTest` y
`MixedPartyParkSurfacesTest` (lo de dinero) se re-apuntan allí. El puente de
`OrderFinancialInvariantsTest` sigue verde y sigue siendo el oráculo hasta la T3·4.

### 6.3.2 · Diseño fino de la T3·2 (2026-09-01) — panel + hoja + puerta pintan el libro

**Medido antes de diseñar** (sobre `cf29ca4`): el panel tenía **tres** compositores distintos del
mismo dinero —`order-totals` (`OrderLedger::forOrder` + `OrderFinancialSummary` +
`reservationFinancialsByPrincipal` + `pendingAtGateLines` + `depositRemainderPendingByProduct`, 352
líneas), `reservation-financials` (`ReservationFinancials` + `reservationGateLines`, 109) y la hoja
(`ReservationSlip::financials/pendingAtGate*/pendingRefundCents/refundedCents`, 130 líneas de
blade)—, más la puerta (`OrderLedger::forReservation`), las dos tablas (`totalFinalNeto` /
`onlineBackingProductsCents` en una, `totalWithChangesCents` / `amountCollectedCents` en la otra:
**dos parejas de fórmulas para las mismas dos columnas**) y `ViewOrder` (`pendienteDevolucion` ×2).
Siete lectores, cinco fórmulas. Con el libro son **un pintor** y **un value object**.

**El diseño.**
- **Un solo PINTOR** para el panel: `reservation-financials.blade.php` pasa a recibir un
  `OrderBook` (del pedido o de la reserva) y transcribe movimientos (signo + fecha) → Total → pagos
  y devoluciones (con su estado si no son efectivas) → Pagado → el saldo con su clase (rótulo
  `admin.orders.book.balance_<kind>`, color por rol). `order-totals` lo incluye con
  `forOrder` y añade solo lo que va alrededor (el aviso `!is_consistent`, el atajo al historial);
  `items-list` y el modal del calendario lo incluyen con `forReservation`. **Cero importes se
  derivan en un blade** (`LedgerSingleSourceTest`, que gana los siete lectores en su lista).
- **Las etiquetas de las líneas son las del cliente** (`tickets.journal.*`): el panel no reescribe
  ninguna. Es lo que hace la guarda M (paridad cliente↔panel) trivial de escribir y difícil de
  romper: la MISMA lista de (etiqueta, importe) en `order-totals` y en `GET /me/orders`.
- **El libro gana tres preguntas** que antes contestaba cada superficie a su manera:
  `hasHistoryToExplain()` (el atajo «Ver historial»: una línea que no sea el nacimiento, o una
  devolución), `owedToCustomerCents()` (lo que se le debe: el saldo en clase de devolución, en
  positivo — el importe sugerido al reembolsar y el del aviso del pedido cancelado) y
  `paymentMethod()` (el de la primera liquidación de clase «cobro»; la puerta lo dice).
- **Las dos tablas** (`OrdersTable`, `OrdersRelationManager`): Total = `totalCents`, Pagado =
  `paidCents` (D-T3·6). Eager-load de lo que el libro lee (`items.slot`, `items.ticketType`,
  `adjustments`, `payments.refunds`) para que componerlo por fila no dispare consultas.
- **La hoja «con precios»** (D-T3·7): conserva las líneas de producto (cantidad × unitario: qué se
  compró) y sustituye la tabla de canales + las dos cajas por el libro de la reserva (movimientos
  · Total · pagos y devoluciones · Pagado) y **UNA caja de saldo** por clase. `ReservationSlip`
  gana `book()` y pierde `financials()`, `refundedCents()`, `pendingRefundCents()`,
  `pendingAtGateCents()`, `hasPendingAtGate()`, `pendingAtGateBreakdown()` y `grandTotalCents()`
  del render (el Total es el del libro: con una cortesía, la suma de las líneas ya no es lo que
  vale). La hoja OPERATIVA sigue sin un euro (guarda R, intacta: todo va dentro de `$showPrices`).
- **La puerta** (D-T3·8): `GateReservation` cambia `paidOnlineCents`/`pendingGateCents` por
  `paidCents` + `balanceKind` + `balanceCents` (con signo), y la fila de `GateProfile` por
  `paid_cents` + `balance_kind` + `balance_cents`. La tarjeta pinta por clase: `pay_at_park` →
  alerta «Pendiente de cobrar en puerta: X» (`data-gate-pending`, como hoy); `refund_at_park` /
  `refund_pending` → alerta «Pendiente de devolver en puerta: X» (`data-gate-refund`);
  `settled` → «Nada pendiente de cobrar»; `under_review` → alerta «Dinero en revisión» (nunca
  «nada pendiente» sobre un libro que no cuadra). **Las líneas mixtas siguen como explicación**
  (§4.6·9) y **el «a tu favor» se queda hasta la T3·3**: mientras el tope exista, el exceso no
  está en el libro y retirarlo aquí dejaría al operador sin el importe que liquida en mano.
- **Lo que NO se toca**: correos, post-form, `MixedPartySurcharge` (T3·3); el modelo viejo y sus
  tests de servicio (`OrderFinancialSummaryTest`, `ReservationFinancialsTest`, …) siguen hasta la
  T3·4 como oráculo; `Order::reservationGateLines` / `pendingAtGateLines` /
  `depositRemainderPendingByProduct` se quedan sin consumidor de superficie y mueren en la T3·4.

**Decisiones derivadas** (además de D-T3·1…13; el owner puede vetar cualquiera):
- **D-T3·14** El panel pinta el libro ENTERO también en el caso simple (nacimiento + cobro): muere
  el «caso SIMPLE» de `order-totals` (solo «Total» + cómo se pagó). Un bloque con dos formas
  según el pedido era una tercera fuente de divergencia; y lo que ve el operador es lo que ve el
  cliente (guarda M).
- **D-T3·15** El importe sugerido al reembolsar y el del aviso del pedido cancelado son
  `owedToCustomerCents()`: el saldo del libro cuando es de devolución. Con `settled` o «a pagar»
  el sugerido es 0 (el operador teclea lo que devuelve) — nada se re-deriva de canales.
- **D-T3·16** El atajo «Ver historial» lo condiciona el libro (`hasHistoryToExplain()`), en el
  bloque del pedido y en la tarjeta de la reserva con la MISMA regla.
- **D-T3·17** Las claves `admin.orders.order_financial.*` e `item_financial.*` se retiran salvo
  `heading`, `no_cuadra_*`, `principal`, `addons` y `total`; `slip.pending_at_gate`,
  `slip.pending_refund(_caption)` y `slip.deposit_remainder_line` mueren con las cajas;
  `item_financial.deposit_remainder_line` y `deposit_for_product` se quedan hasta la T3·4 (los
  lee `Order::reservationGateLines`, aún vivo para el oráculo).
- **D-T3·18** La puerta llama `paid_cents` a lo pagado (no `paid_online_cents`): el libro cuenta
  también un cobro en mostrador y una liquidación ya hecha; el nombre viejo afirmaba un canal.

**Guardas y mutaciones previstas** (se ejecutan en §6.3.3):

| Guarda | Mutación |
|---|---|
| M · `BookSurfacesParityTest`: `order-totals` imprime EXACTAMENTE la lista (etiqueta, importe) del libro que publica `GET /me/orders`; la tarjeta de la reserva, la hoja con precios y la fila de la puerta, el de SU reserva | el pintor imprime `kind` en vez de `label` · la hoja lee `grandTotalCents()` |
| L · `LedgerSingleSourceTest`: los siete lectores del panel/hoja/puerta no citan `OrderLedger` / `financialSummary(` / `ReservationFinancials`; la lista de los que AÚN pueden (`OrderConfirmation`) solo encoge | reintroducir `financialSummary()` en un blade |
| N · el saldo por clase en la puerta (Q): `refund_at_park` se pinta como «a devolver» y nunca como «nada pendiente»; `under_review` es alerta | quitar la rama de devolución |
| O · el atajo al historial: aparece con una edición o una devolución; no en el caso simple | invertir `hasHistoryToExplain()` |
| P · el importe sugerido al reembolsar es lo debido (D-T3·15) | devolver `abs(cents)` con cualquier clase |
| R · la hoja operativa sin un euro (`MixedPartyParkSurfacesTest`, intacta) | — |
| Presupuesto · `GateProfileTest` (≤ 28) y `MixedPartyParkSurfacesTest` (constante con las filas) | — |

### 6.3.3 · ✅ T3·2 EJECUTADA (2026-09-01, `DECISIONES #311`) — panel + hoja + puerta

**Lo que entró**: exactamente el diseño de §6.3.2. Un pintor (`reservation-financials.blade.php`,
`book` + `struck`) para el bloque del pedido, la tarjeta de cada reserva y el modal del calendario;
`order-totals` reducido a lo que va alrededor; `items-list` y `CalendarPage` con `forReservation`;
`OrdersTable` y `OrdersRelationManager` con Total/Pagado del libro; `ViewOrder` con
`owedToCustomerCents()`; `ReservationSlip::book()` y la hoja con precios (líneas + libro + UNA caja
de saldo, con `.settled-box` para «nada pendiente»); `GateReservation`/`GateReservationsReader`/
`GateProfile`/`GateProfileData` con `paidCents` + `balanceKind` + `balanceCents` y la tarjeta por
clase (`data-gate-pending` · `data-gate-refund` · `data-gate-under-review`); claves
`admin.orders.book.*` y `puerta.validar.profile.refund_at_gate`/`under_review` (es · zh_CN); las
claves viejas retiradas (D-T3·17). `OrderBook` gana `hasHistoryToExplain()`, `owedToCustomerCents()`
y `paymentMethod()`.

**Guardas ejecutadas y sus mutaciones** (sobre árbol commiteado, restaurando con `git checkout`):

| # | Guarda | Mutación | Resultado |
|---|---|---|---|
| M | `BookSurfacesParityTest` (nuevo): bloque del pedido, tarjeta de cada reserva, hoja con precios y fila de la puerta = la lista de la API | el pintor imprime `kind` en vez de `label` | **5 rojos** |
| M | ídem, la hoja | la hoja imprime `kind` | **1 rojo** |
| L | `LedgerSingleSourceTest::test_no_surface_reads_the_old_model` (nuevo; `STILL_ON_THE_OLD_MODEL` solo encoge) | `financialSummary()` de vuelta en `order-totals` | **1 rojo** |
| N/Q | `GateProfileTest` (+2): «a devolver» con alerta, nunca «nada pendiente»; «en revisión» es alerta | la tarjeta pierde la rama de devolución | **1 rojo** |
| O | `OrderTotalsBreakdownTest::…history_shortcut…` | `hasHistoryToExplain()` invertido | **2 rojos** |
| P | `BookSurfacesParityTest::…fixture…` (`owedToCustomerCents() === 0` con «a pagar») + `ItemsListSubCardTest` (banner con deuda) | `owedToCustomerCents()` devuelve `abs` con cualquier clase | **1 rojo** |
| R | la hoja operativa sin un euro (`MixedPartyParkSurfacesTest`, intacta) | — | verde |
| Presupuesto | `GateProfileTest` ≤ 28 · `MixedPartyParkSurfacesTest` constante | — | verde |

**Lo que se aprendió.**
- ⚠️⚠️ **Diez ficheros de tests tenían fixtures que el libro rechaza** (`OrderTotalsBreakdownTest`,
  `ItemsListSubCardTest`, `ReservationSlipTest`, `DepositSurfacesTest`, `GateProfileTest`,
  `ManageItemQuantityProductTest`, `OrderInfolistEnrichedTest`, `Polish7e1bis5Test`,
  `OrdersPolish179Test`, `ReservationFinancialsTest`): «pagados» sin `Payment`, totales que no eran
  la suma de las líneas al nacer, ajustes de puerta sin cambio de valor, devoluciones que eran dos
  columnas. Con los dos ejes pasaban —nadie cruzaba cobro con estado ni ajuste con valor—; con
  I1/I2/I4 responden «en revisión». Se LEGALIZARON todos; ninguna identidad se excepcionó. El
  patrón que se repite: *el fixture añade un ajuste de 2400 «porque sí» y el test asevera que se
  pinta 24,00* — el libro obliga a que el ajuste explique un cambio de valor real.
- **El ejemplo de la clienta, contado como libro, es la historia verdadera**: 16 invitados
  (288,00), baja a 8 (−144,00), sube a 12 (+72,00); vale 216,00, pagó 288,00, se le devuelven
  72,00 en el parque. El fixture viejo (item a 12 con un +72 y «144 pendientes de devolución») no
  cuadraba con nada: era la aritmética de canales, no lo que pasó.
- **`tickets.journal.refund_pending` ya dice «en curso»**: un sufijo de estado en el pintor lo
  duplicaba; fuera, y con él dos claves que nacieron muertas (`book.status_*`).
- **Tema del panel**: `opacity-70` no estaba compilado (0 apariciones). Las utilidades de un blade
  nuevo no existen hasta `npm run build`; el `pre-push` construye antes de la suite.
- `has_deposit` por reserva cuenta solo líneas VIVAS (T2): el caso «cancelada con señal» de la
  T3·1 lo dejó escrito, y aquí la puerta lo hereda sin sorpresa.

**Lo que la T3·3 hereda**: los correos (`OrderConfirmation`, `OrderItemModified`, `OrderItemRefunded`,
`OrderRefunded`, `MixedPartySurchargeChanged`) y el post-form siguen en el modelo viejo —
`STILL_ON_THE_OLD_MODEL` tiene UNA entrada—; el «a tu favor» sigue en `items-list`, la hoja y la
puerta hasta que `MixedPartySurcharge::applyCredit` escriba el crédito entero (D4) y muera
`inFavourCents`; `mixed-party:verify-concurrency` en sus dos escenarios con `VERIFY_CONC=1`.

### 6.3.4 · Diseño fino de la T3·3 (2026-09-01) — correos + post-form + el tope

**Medido antes de diseñar** (sobre `9ceff33`): los cinco correos de dinero componen cada uno lo suyo
—`OrderConfirmation` con `OrderFinancialSummary` + `OrderLedger` (tres claves: «Total pagado» /
«Señal pagada online» / «Pendiente de pago en el parque»), `OrderItemModified` con **tres céntimos
que le pasa el editor** (`extraDueCents`, `pendingRefundCents`, `gateCreditedCents`, calculados por
`OrderItemEditor::creditReduction` con `GateBuckets`), `OrderItemRefunded`/`OrderRefunded` con el
importe y el canal, `MixedPartySurchargeChanged` con el neto viejo/nuevo—; y **el tope** de la T4
vive en `MixedPartySurcharge::applyCredit` (`min(derivado, gateCoverageCents())`), con el exceso
(`inFavourCents()`) enseñado en **seis** sitios (`items-list`, la hoja ×2, la puerta, el post-form
×2) y publicado por `OrderLedger::inFavourHint`. Tres claves de correo por idioma y cinco del «a tu
favor» en cuatro ficheros de idioma.

**El diseño.**
- **Cae el tope (D4, `[DECIDIDO owner]` §1.1)**: `applyCredit` escribe el crédito DERIVADO entero.
  Con el libro no existe «un canal que quede en negativo» —la razón de ser del tope (§20.1 de
  `cumple-mixto.md`)—: lo que la puerta no absorbe es saldo «a devolver en el parque» (spec §4.4),
  y el circuito de §20.5 (el operador lo da en mano; si quiere constancia, reembolso manual) es el
  MISMO que el de cualquier saldo negativo. Mueren `gateCoverageCents()`, `inFavourCents()`, la
  línea «a tu favor» en las seis superficies, `OrderLedger::inFavourHint` (siempre `null` hasta que
  la clase se retire en la T3·4) y las cinco claves. La asimetría del silencio del crédito
  (§24.3·7) **se conserva**: sin veredicto que gobierne no se mueve.
- **Los correos pintan el libro AL ENVIAR (D-T3·5)**: un bloque compartido,
  `Booking\Services\EmailBookBlock::forOrder()` → `emails.partials.book` (tabla con estilos en línea,
  como la tarjeta de producto: los clientes de correo no respetan CSS externo), con el título
  `tickets.journal.email_title`, los movimientos con signo y fecha, el Total, los pagos y
  devoluciones, lo Pagado, el saldo con su clase (`tickets.journal.balance_*`, que gana
  `settled`/`expired`/`under_review`) y la nota si la hay. Cada correo conserva su narrativa (qué
  cambió, cuánto se devolvió y por qué canal, la voz del suplemento) y añade el bloque: un correo
  de gestión pasa a ser el estado de la cuenta ese día, y al REENVIARLO desde el panel dice el
  saldo nuevo. `OrderConfirmation` pierde sus tres líneas de canal; `OrderItemModified` pierde sus
  tres céntimos y `creditReduction` se reduce a `recordEdit(−Δ)` (la única cosa que hacía además
  del hecho era el reparto para este correo).
- **D2** («se te devolverán en el parque»): ya no es una frase — es la línea de saldo «A devolver
  en el parque» del bloque. `reduction_pending_refund`, `reduction_gate_credit` y `extra_due`
  mueren; `mixed_party_surcharge.where_discounted` y `guestform.mixed_discount_total` pasan a decir
  las DOS salidas (se descuenta de lo que pagarás; si ya estaba todo pagado, se te devuelve en el
  parque).
- **Post-form**: sin `inFavourCents`; con dinero escrito manda lo escrito (el descuento es una línea
  con su frase); sin dinero escrito, el veredicto (`mixed_savings_pending` / `mixed_no_difference`).
- **El modelo viejo (oráculo hasta la T3·4) deja de cerrar para el crédito sin cobertura**: su eje
  de puerta se clava en cero (`max(0,…)`) y `PAY-16`/`PAY-17` ya no se aseveran en ese caso. Los
  tres casos del tope de `OrderFinancialInvariantsTest` se INVIERTEN (guarda N) y salen del puente
  (`ledger-bridge.json` pierde `T4·B` y `T4·C` a propósito); `MixedPartySurchargeTest` cambia
  `assertChannelsClose` por las identidades del libro en los casos del crédito; `INVARIANTES`
  `PAY-16`/`PAY-17`/`PAY-19` llevan la nota hasta que la T3·4 las reescriba.
- **`MixedPartySurcharge` está en el `CRITICAL_RE`**: `mixed-party:verify-concurrency` en sus DOS
  escenarios sobre MySQL (el de `credit` sigue esperando UNA línea de −7,00 €: el lock no cambia),
  más `redsys:verify-concurrency` y `purchase:verify-oversell`, y el push con `VERIFY_CONC=1`.

**Decisiones derivadas** (el owner puede vetar cualquiera):
- **D-T3·19** El bloque del libro va en TODOS los correos de dinero, también en los de devolución
  (su importe y canal siguen en su línea): un correo con un importe suelto obliga a razonar; con
  el libro, el importe está en su sitio.
- **D-T3·20** `EmailBookBlock` es DEFENSIVO como `EmailProductCard`: si el libro no se puede
  componer, el correo sale sin el bloque y el fallo se reporta (`report()`), en vez de dejar al
  cliente sin correo por una tarea en cola que revienta.
- **D-T3·21** `OrderItemModified` no lleva NINGÚN importe: el bloque los dice todos, y con signo.
- **D-T3·22** `MixedPartySurchargeChanged` conserva su narrativa (la voz del suplemento o del
  descuento, el viejo/nuevo) y añade el bloque del PEDIDO: el neto del suplemento es una línea más.

**Guardas y mutaciones previstas** (se ejecutan en §6.3.5):

| Guarda | Mutación |
|---|---|
| P · `EmailBookBlockTest`: los cinco correos llevan el bloque; al REENVIAR tras una edición dice el saldo NUEVO; `OrderItemModified` no lleva importes sueltos | leer `Order.total` en el bloque · devolver `''` |
| N · el crédito se escribe ENTERO sin cobertura y el libro dice «a devolver en el parque» (`MixedPartySurchargeTest`, `OrderFinancialInvariantsTest` B/C invertidos) | restaurar el `min()` |
| S · ningún sitio dice «a tu favor» (lang, blades, DTO) | — (lo cubre el compilador y `LangKeysTest`) |
| L · `STILL_ON_THE_OLD_MODEL` vacía y borrada; `EmailBookBlock.php` en `SURFACES` | `financialSummary()` en un correo |

### 6.3.5 · ✅ T3·3 EJECUTADA (2026-09-01, `DECISIONES #312`) — correos + post-form + el tope

**Lo que entró**: exactamente §6.3.4. `MixedPartySurcharge::applyCredit` sin tope (mueren
`gateCoverageCents()` e `inFavourCents()`); `OrderLedger::inFavourHint` a `null`; el «a tu favor»
fuera de `items-list`, la hoja, la puerta (DTO, reader, fila, tarjeta) y el post-form (controlador
y vista), con sus claves (`guestform.mixed_in_favour`, `tickets.ledger_in_favour`,
`admin.orders.mixed_party.in_favour`, `admin.orders.slip.mixed_party_in_favour_fact`,
`puerta.validar.profile.mixed_party_in_favour`); `EmailBookBlock` + `emails.partials.book`; los
cinco correos con el bloque; `OrderItemModified` con solo `changes`; `OrderItemEditor` sin
`creditReduction` (la bajada es `recordEdit(−Δ)`; las dos cifras del OUTCOME siguen para la
notificación del operador); `tickets.journal.{email_title, balance_settled, balance_expired,
balance_under_review}` en cuatro idiomas; fuera `emails.order_confirmation.{total, deposit_paid,
pending_at_park}` y `emails.order_item_modified.{extra_due, reduction_pending_refund,
reduction_gate_credit}`; `where_discounted` y `mixed_discount_total` con las dos salidas;
`INVARIANTES` `PAY-16`/`PAY-17`/`PAY-19` con la nota; `cumple-mixto.md` §20 con la corrección.

**Guardas ejecutadas y sus mutaciones** (sobre árbol commiteado, restaurando con `git checkout`):

| # | Guarda | Mutación | Resultado |
|---|---|---|---|
| N | `MixedPartySurchargeTest`: el crédito se escribe ENTERO sin cobertura y el libro lo debe en el parque (+ B/C invertidos en `OrderFinancialInvariantsTest`) | vuelve un tope al crédito | **8 rojos** |
| P | `EmailBookBlockTest` (nuevo): los cinco correos llevan el bloque; el reenvío tras una edición dice el saldo nuevo; sin céntimos sueltos; defensivo | el bloque devuelve vacío | **5 rojos** |
| P | ídem | el bloque imprime lo Pagado como Total | ⚠️ **VERDE** → acotada a la fila del Total; **1 rojo** |
| P | ídem | el correo de modificación recupera un céntimo | **1 rojo** |
| P | ídem | un correo pierde el bloque | **1 rojo** |
| L | `LedgerSingleSourceTest` (sin lista de excepciones; correos y bloque en `SURFACES`) | `financialSummary()` de vuelta en un correo | **1 rojo** |
| Concurrencia | `mixed-party:verify-concurrency` charge/credit (12) · `redsys:verify-concurrency` (16) · `purchase:verify-oversell` (16), sobre MySQL | — | ✓ los cuatro |

**Lo que se aprendió.**
- ⚠️⚠️ **La guarda del Total pasó en verde con el Total mutado**: buscaba «30,00 €» en el cuerpo entero
  y la tarjeta de producto del mismo correo imprime esa cifra. *Acota al ELEMENTO* (`<tr
  data-book-total>`), la quinta vez en este carril.
- **`jumpParty` facturaba «total = la parte online»** —el fixture del modelo viejo, que el libro
  rechaza (I1)—: legalizado como `syncTotalToOnline` hacía ya en el otro fichero. Los cuatro tests
  que aseveraban el «a tu favor» pasan a aseverar el descuento ESCRITO y el saldo del libro.
- **Un corte por índice de `"    }\n"` casa dentro de una llave más sangrada** y deja dos `}`: la
  suite en paralelo lo enseña como un fatal del `ExceptionHandler` sin línea; `php -l` lo dice.
- El lock del reconciliador **no dependía del tope**: los dos escenarios del verificador dan la
  misma línea única con 12 guardados simultáneos.

**Lo que la T3·4 hereda**: §4.7 entero (`OrderLedger` con `inFavourHint` a `null`,
`OrderFinancialSummary`, `ReservationFinancials`, `GateBuckets` → `LineFacts`, la rama mixta de
`breakdownLabel()`, `tickets.ledger.*` salvo las tres notas, `assertBookBridge` + `ledger-bridge.json`
—ya sin `T4·B`/`T4·C`—, `ReservationFinancialsTest`, `OrderFinancialSummaryTest`, `OrderGateCreditTest`,
`ItemPriceChangeReconstructionTest` → hechos); `INVARIANTES` `PAY-16`/`PAY-17` reescritas y `PAY-19`
sin la nota; `DEUDA` L4; `desglose-dinero-cliente.md` a HISTÓRICO; el guion headless
(`VERIFICACION-E2E-CAJON.md`) y la receta de las 25 acciones sobre el corpus; el ojo del owner.

## 7. Revisión y decisión

- 2026-09-01 · **Agente**: análisis empírico (§1.2–§1.4), prototipo de lectura y este diseño. Dos
  preguntas al owner —el descuento mixto en el saldo · el reembolso manual se queda— contestadas
  con *«no quiero dejar deuda, profesional sin ambigüedades»* → D4 y D5.
- 2026-09-01 · **Owner**: *«Perfecto, validado, procede con T1»* → diseño ✅.
- 2026-09-01 · **T1 ejecutada** (§6.1, `DECISIONES #306`). Sigue la T2.
- 2026-09-01 · **T2 ejecutada** (§6.2, `DECISIONES #308`): el libro en el dominio, puente idéntico
  en los 15 escenarios y 37/40 en el corpus; de paso cayó una cortesía falsa de la T1 («también
  cancelar»). Sigue la T3. (⚠️ Es `#308` y no `#307`: el carril de la landing numeró `#307` el mismo
  día — la colisión de `CONVENCIONES` §10, otra vez.)
- Entradas: `DECISIONES #305` (la decisión de producto) · `#306` (la T1) · `#308` (la T2).
