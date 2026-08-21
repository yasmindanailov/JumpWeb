# Sistema: señal/depósito (cobro parcial online; resto en el local)

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Sistema IMPLEMENTADO** (núcleo completo + superficies + armonización cancelar/reembolsar).
> Referencia, no plan. ⚠️ Los `ruta:línea` son anclas del momento de escritura: pueden haber
> derivado. Ver también `MODELO-DATOS.md`, `FLUJOS.md` y el doc de integración Redsys en
> `docs/sistemas/`.

---

## 0. Resumen

Un producto puede cobrar **online solo una señal** (depósito) en vez del total; el resto se
cobra **presencialmente en el local** (fuera de Redsys). Aplica solo a **packs de
cumpleaños/eventos** *(vocabulario del sector origen; su generalización se decide en
`00-REFACTOR.md` Fase 1/2)*. El cambio fue de mínima superficie porque el sistema YA separaba
«valor del producto» de «dinero cobrado online», y los reembolsos YA tenían modo `manual`
(no-Redsys). Clave: **no tocar el eje VALOR** (`Order.total`, `productsValue`, «total final»,
«pendiente de devolución») — invariante de no-regresión — y cambiar **solo el SPLIT
online/puerta** de ese valor.

**Hallazgo que lo habilita:** `Payment.amount` es la **única fuente del dinero realmente
cobrado**; `amountCollectedCents`/`onlineBackingProductsCents`/`refundableCapacityCents`/el
canario `amount_mismatch` ya leen `Payment.amount` (no `Order.total`). Al pasar
`Payment.amount` a ser la señal, esos cálculos quedan correctos sin tocarlos.

Junto al depósito se armonizó el principio **cancelar ≠ reembolsar** (independientes y
manuales) en TODO el sistema, también en pedidos sin señal (D7–D9 abajo).

---

## 1. Decisiones de diseño (cerradas en el origen)

| # | Decisión | Resuelto |
|---|---|---|
| D1 | **Alcance** | Solo **packs**. Entradas pagan 100 %. Carros mixtos soportados (split por línea). |
| D2 | **Mecanismo Redsys** | Autorización normal `'0'` por el importe de la señal. Resto presencial, fuera de Redsys. **NO** preautorización. |
| D3 | **Pedido manual** | También cobra solo la señal si el producto lo configura; resto «a cobrar en el local». |
| D4 | **Reembolso** | Independiente de cancelar y decisión **manual** del empleado. Techo de reembolso por línea = **lo cobrado al reservar (la señal)**, no el valor. Cancelar puede quedarse el depósito si los T&C lo permiten. |
| D5 | **Editar al alza** (subir cantidad / producto más caro / añadir complemento) | Todo el incremento a cobrar en el local; la señal pagada se **congela**. Sin 2.º cargo online (evita fricción PSD2/SCA). |
| D6 | **Tipo de señal** | `fixed` o `percent` (`ticket_types.deposit_type/deposit_value`). Recomendado `fixed` (evita redondeo por línea). Editable en el catálogo. |
| D7 | **Reembolso solo Redsys-ejecutable + «manual» record-only** | El reembolso REST solo se ejecuta cuando el pago tiene `gateway_order`. En pagos en caja/sin gateway se oculta el modo REST y queda «reembolso manual» = **badge + log** de constancia (el abono físico ocurre fuera del sistema). Corrige incoherencia latente: antes «Reembolsar» se ofrecía en pedidos de caja pero el REST abortaba (`Redsys::executeRefund` exige `gateway_order`). |
| D8 | **Bajar cantidad/complemento = SOLO cancelar** | Reducir cantidad solo cancela las unidades retiradas (soft, sin tocar Redsys → nunca falla, tampoco en manuales). **NO auto-reembolsa** (antes sí). El sobre-cobro online aflora como «pendiente de devolución»; el reembolso es decisión separada y manual. **Cambio de comportamiento legacy intencional**: aplica también a pedidos de pago completo (uniforme). |
| D9 | **Cancelar ≠ reembolsar tras un reembolso completo** | Un pedido reembolsado por completo **sigue permitiendo cancelar** productos individuales. El banner `order_fully_refunded` solo informa de que no queda nada que reembolsar. |

**Pendiente heredado:** la cláusula de condiciones sobre el reembolso de la señal
(`App\Domain\Content\Services\LegalContent`) quedó en el origen con marcador `[PENDING: … refund of the
birthday deposit]` (+ marcador en el seeder de landing). Verificar si sigue en la base; en
JumpWeb el contenido legal es por-instalación.

---

## 2. Mecanismo Redsys

**Autorización normal (`Ds_Merchant_TransactionType = '0'`) por el importe de la señal.** El
resto (`valor − señal`) se cobra presencialmente y vive fuera de Redsys.

- La ida usa siempre `'0'` (`Redsys::buildPaymentFormData`, clave `DS_MERCHANT_TRANSACTIONTYPE`);
  la devolución usa REST `'3'` (`Redsys::executeRefund`). No hay preautorización
  `'1'`/confirmación `'2'` en el repo.
- **Por qué NO preautorización:** según la doc oficial de Redsys caduca en 7 días (extensible
  a 30 con permiso bancario) y exige alta; las reservas de eventos se hacen con semanas de
  antelación → caducaría. Además el resto se cobra presencialmente, no online.
- **Devoluciones:** REST `'3'`. Redsys enforce server-side que la suma de devoluciones no
  supere el importe autorizado original → siendo la señal el autorizado, **el techo de
  reembolso pasa a ser la señal automáticamente** (refuerza D4).
- **Diferido:** cobrar el resto online exigiría preauth `'1'` + confirmación `'2'` (alta
  bancaria + caducidad) → fuera de alcance.

---

## 3. El modelo de cálculo (dos ejes: VALOR y CAJA)

> **Los cálculos de producto y de pedido son independientes.** Eje VALOR = lo que valen los
> productos; eje CAJA = lo cobrado online. Sin señal coinciden; la señal los separa.

### 3.1 Los dos niveles

**Nivel PEDIDO — `App\Domain\Booking\Services\OrderFinancialSummary`** (fuente única del bloque «Totales del
pedido»):

| Campo | Fórmula |
|---|---|
| `totalOriginal` | `Order.total` (valor al crear) |
| `totalRefunded` | Σ `payment_refunds.succeeded` |
| `extraDue` | Σ `order_adjustments(extra_due).amount_cents` de items no cancelados (SOLO ediciones) |
| `extraDueResolved` | subset de `extraDue` cuyos items finalizaron (cobro en puerta implícito) |
| `productsValue` | Σ `chargedSubtotalCents` de items no cancelados (valor ACTUAL) |
| `pendingAtGate()` | `max(0, (extraDue + depositRemainder) − (extraDueResolved + depositRemainderResolved))` |
| `totalWithChanges()` | `totalOriginal + extraDue − effectiveRefunded` (extraDue = SOLO ediciones) |
| `totalFinalNeto()` | `productsValue` |
| `pendienteDevolucion()` | fórmula **DE CAJA**: `max(0, (Σ pagos pagados − reembolso efectivo) − onlineBacking)` — ver §5.9 |

**Nivel PRODUCTO/RESERVA — `App\Domain\Booking\Services\ReservationFinancials`** (principal + complementos):

| Campo | Fórmula |
|---|---|
| `valor` | Σ `chargedSubtotalCents` (no cancelados) |
| `pagadoOnline` | Σ `itemCollectedCents` (no cancelados) |
| `aCobrarPuerta` / `cobradoPuerta` | `itemExtraDueCents + itemDepositRemainderCents` neto (pendiente / resuelto si finalizó) |
| `devuelto` | Σ `itemRefundedCents` |
| `pendienteReembolso` | Σ `itemPendingRefundCents` |
| **Reconcilia** | `valor = pagadoOnline + aCobrarPuerta + cobradoPuerta` |

**Helpers por-línea — `App\Domain\Booking\Models\Order` (y `OrderItem`):**

| Helper | Fórmula |
|---|---|
| `OrderItem::chargedSubtotalCents` | `(quantity − free_quantity) × unit_price` |
| `itemExtraDueCents` | Σ `extra_due` (neto, con créditos) atados al item |
| `itemDepositRemainderCents` | Σ `deposit_remainder` (neto) atados al item |
| **`itemCollectedCents`** | **`max(0, chargedSubtotal − itemExtraDueCents − itemDepositRemainderCents)`** — LA PALANCA |
| `itemRefundedCents` | Σ `payment_refunds.succeeded` con `order_item_id` |
| `itemOriginalOnlineCents` | **deposit-aware**: online original = `depositCents(origQty × unit_price)` (`none`→valor; `fixed`→señal; `percent`→`round(% × valor_original)`) |
| `itemRefundableRemainderCents` | `max(0, itemOriginalOnlineCents − itemRefundedCents)` (techo ensanchado, §5.8) |
| `refundableCapacityCents` (agregado) | `max(0, Payment.amount − (Σ refunds `succeeded` **+ `pending`**))` — ⚠️ **NO es `totalRefunded`**: reserva ADEMÁS los refunds EN VUELO. Un `pending` —parcial o TOTAL (`order_item_id` NULL)— inmoviliza su importe para que dos reembolsos concurrentes no puedan devolver más de lo cobrado (auditoría Fase 1, **M5**). Un `failed` no reserva nada. Código: `Order::refundableCapacityCents()`. |
| `amountCollectedCents` | `max(0, Σ payments.paid − Σ refunds)` |
| `onlineBackingProductsCents` | `max(0, netHeld − pendienteDevolucion)`, `netHeld = paid − refunds` |

### 3.2 El supuesto que la señal rompía (histórico, ya resuelto)

Sin señal: `Order.total == Payment.amount == cobrado online == Σ valor` (un solo número). Por
eso `itemCollectedCents` derivaba «cobrado online» como `valor − extra_due` (asumía valor base
= todo cobrado online). Con señal, `Order.total (valor) > Payment.amount (señal)` desde la
creación; la parte `valor_base − señal` tampoco se cobró online pero no era `extra_due` → sin
la pieza `deposit_remainder`, `itemCollectedCents` sobreestimaría y arrastraría el techo de
reembolso por línea, `pagadoOnline` y «pendiente de devolución».

**Lo que nunca se rompió** (anclado a `Payment.amount`): `amountCollectedCents`,
`onlineBackingProductsCents`, `refundableCapacityCents`, canario `amount_mismatch`
(`RedsysReturnHandler`, chequeo `Ds_Amount` vs importe firmado).

---

## 4. Principio de diseño

1. **El eje VALOR NO se toca.** `Order.total`, `productsValue`, `extraDue` (delta de
   ediciones), `totalWithChanges`, `totalFinalNeto` siguen midiendo valor. Preserva
   facturación, cards, PDF, «total final».
2. **Solo cambia el SPLIT online/puerta.** Sin señal: online = `valor − extra_due`, puerta =
   `extra_due`. Con señal: online = `señal`, puerta = `extra_due + (valor_base − señal)`.
3. **El dinero real está en `Payment.amount`.** Fuente única `Order::onlineDueCents()`
   alimenta `Payment.amount`, el `DS_MERCHANT_AMOUNT` de la ida y el reintento → el canario
   `amount_mismatch` no puede dispararse por la señal.
4. **Legacy idéntico.** Para `deposit_type=none`, `señal = valor` → el split da exactamente
   los mismos números (§6). Ningún pedido histórico cambia por el MODELADO de la señal.

---

## 5. Diseño técnico (piezas)

### 5.1 El «resto de la señal» = ajuste separado

`valor_base − señal` (a cobrar en puerta, conocido desde la creación) se modela como fila
`order_adjustments` de tipo propio **`OrderAdjustment::TYPE_DEPOSIT_REMAINDER =
'deposit_remainder'`**, atada al `order_item_id` (cancelar la línea lo anula).

**Por qué un tipo separado y NO `extra_due`:**
- `Order::pendingAtGateLines()`/`gateLineLabel()` reconstruyen «+N producto» dividiendo el
  importe por `unit_price` → meter el resto-señal por `extra_due` produciría etiquetas falsas.
- `applyGateCredit` asume que un crédito de edición netea contra `extra_due` del item;
  mezclarlos haría que un crédito de edición consumiera el resto-señal.
- `extraDue` (término de `totalWithChanges`) debe ser SOLO ediciones: el resto-señal ya está
  dentro de `Order.total`/`productsValue` (es parte del valor base) → sumarlo doble-contaría.
- Se descartó reusar el enum reservado `TYPE_COLLECTED_IN_PERSON` («ya cobrado» ≠ «a cobrar»).

### 5.2 `Order::onlineDueCents()`

`onlineDueCents() = Σ_{líneas no canceladas} itemCollectedCents(línea)`. Al crear equivale a
`Σ depositCents(chargedSubtotal_línea)`. Lo usan: `OrderCreator` (Payment.amount), `Redsys`
ida, reintento, pedido manual. Espejo para el sidecart: `Purchase::cartDepositCents()`.

### 5.3 `OrderCreator::createPendingOrder`

Por línea: `señal_línea = type->depositCents(chargedSubtotal_línea)` (`none` →
chargedSubtotal); `resto_línea = chargedSubtotal − señal_línea`; si `resto_línea > 0` crea el
`OrderAdjustment(deposit_remainder, item, resto_línea)`. `Order.total = subtotal` (sin
cambios). Los complementos de un pack con señal van **100 % a puerta** (Opción A, D1).
**Redondeo (percent):** `depositCents` redondea POR LÍNEA → test de que `Σ señal_línea ==
onlineDueCents` exacto en multi-línea percent.

### 5.4 La palanca: `itemCollectedCents` redefinido

```
itemCollectedCents(item) = max(0, chargedSubtotal − itemExtraDueCents − itemDepositRemainderCents)
```
Todo lo que cuelga de él se corrige automáticamente: `itemRefundableRemainderCents`,
`isVoidedLeftoverItem`, `itemPendingRefundCents`, `itemOriginalOnlineCents`,
`ReservationFinancials.pagadoOnline`. Legacy (`deposit_remainder=0`) → idéntico.

### 5.5 `OrderFinancialSummary`

Eje valor intacto. `pendingAtGate()` y su `resolved` incluyen `deposit_remainder` además de
`extra_due` (params `depositRemainder`/`depositRemainderResolved`). «Pagado online» del bloque
pedido = `onlineBackingProductsCents`. El bloque «Valor final = Pagado online + A cobrar en
local + Pagado en local» reconcilia (§7).

### 5.6 `ReservationFinancials`

`pagadoOnline` ya es deposit-aware vía §5.4; `aCobrarPuerta`/`cobradoPuerta` suman
`itemExtraDueCents + itemDepositRemainderCents` (`gateNeto`). Reconciliación intacta.

### 5.7 Inicio de pago — fuente única `Payment.amount = onlineDueCents()`

- ✅ **Fuente ÚNICA real (corregido 2026-08-19)**: `Payments\Services\PaymentInitiator::start()`
  crea el `Payment` con `'amount' => $order->onlineDueCents()`. Es el **único** `Payment::create` del
  camino de compra, y se alcanza por el puerto `Booking\Contracts\PaymentInitiation`.
  ⚠️ Antes esta lista decía `Purchase.php` (2 puntos) y `RetryPaymentController.php`: **ninguno de los
  dos crea ya el `Payment`** — pasan por el orquestador desde Fase 3.
- `Redsys::buildPaymentFormData` — `DS_MERCHANT_AMOUNT => (string) $payment->amount` (usa el
  amount del `$payment` recibido = ancla única → blinda el canario). **Crítico de seguridad.**
- `app/Domain/Booking/Services/ManualOrderFulfiller.php` — `amount = onlineDueCents()` (D3); los
  `deposit_remainder` los crea `OrderCreator`; status `PAID` (semántica: la parte upfront está
  cobrada; el resto pendiente en puerta).

### 5.8 Bajar cantidad = SOLO cancelar (D8)

Antes, la bajada creditaba el cargo de puerta y **auto-reembolsaba** online vía
`executePartialRefund` REST — acoplaba cancelar+reembolsar y en pedidos manuales fallaba
siempre (sin `gateway_order`). Ahora:

```
$reduction   = -$diff;
$pendingGate = itemExtraDueCents + itemDepositRemainderCents;
$gateCredit  = max(0, min($reduction, $pendingGate));  // créditos firmados: extra_due primero, luego deposit_remainder
// SIN executePartialRefund. El remanente NO se reembolsa: queda como «pendiente de devolución».
```

- Pack con señal fija + bajar invitados → normalmente depósito ≤ nuevo valor → 0 pendiente
  (solo baja lo de puerta). Solo si el valor cae por debajo del depósito hay sobre-cobro (E4).
- Pago completo (legacy) + bajar → el valor retirado queda como «pendiente de devolución»,
  NO auto-reembolsado. Pieza auxiliar: `Order::recordReductionMarker` (ajuste de 0 € que porta
  el `quantity_change` en bajadas de pago-íntegro-online, para reconstruir el pendiente).
  Crédito del resto-señal: `Order::applyDepositRemainderCredit()`.
- **Feedback al operador obligatorio** (ya no hay reembolso automático):
  aviso previo `price_diff_reduce` (neutro/ámbar: «se cancelarán las unidades…, no se
  reembolsa automáticamente») en vez del verde `price_diff_refund`; toast posterior
  `success_edited_reduced` (+ «quedan X € pendientes de devolución» si aplica). i18n es+zh_CN.
  `price_diff_refund`/`success_edited_refunded` quedan SOLO para reembolsos reales.

**Techo de reembolso por-producto ENSANCHADO** para poder devolver el sobre-cobro de una
reducción: `refundableItemCents = max(0, itemOriginalOnlineCents − itemRefundedCents)` (base
online ORIGINAL, no collected actual). `refundItemBlockedReason` y
`hasAnyRefundableItemOrChild` (`app/Domain/Payments/Concerns/GuardsItemRefunds.php`) usan la nueva
base. Legacy sin reducción: `itemOriginalOnlineCents == itemCollectedCents` → techo idéntico.

### 5.9 «Pendiente de devolución» de nivel pedido = fórmula DE CAJA

Bug corregido en el origen: el bloque del pedido mostraba un «pendiente de devolución»
fantasma en un pack cobrado solo por señal (usaba `totalWithChanges − productsValue`,
tratando `Order.total` como cobrado online), con riesgo de reembolsar de más. Fix:
`pendienteDevolucion = max(0, (Σ pagos pagados − reembolso efectivo) − Σ
itemCollectedCents(vivos))`. Correcta para señal, bajada, cancelación y cambio a producto más
barato. Auto-corrige `onlineBackingProductsCents` (columna «Pagado» de la lista) y el caption
«pagó X por web» (= pagos reales).

### 5.10 Gating de reembolso por canal (D7)

`Order::isRedsysRefundable()` = pago con `gateway_order`. Los selectores de modo (reembolso de
pedido y de producto en `ViewOrder`) **ocultan `MODE_REST`** en pagos de caja → solo
`MODE_MANUAL` (record-only: `refunded_at`/badge + log `orders.refunded`, sin tocar Redsys) +
coacción server-side en ambos handlers. `refundBlockedReason()` no cambia (sigue por estado);
el gating de modo vive en UI + handler.

### 5.11 Banner «reembolsado por completo» (D9)

Un full-refund deja el Order en `paid` → **cancelar productos SIGUE disponible** (cancelar ≠
reembolsar); solo `refundItem` queda bloqueado (`already_fully_refunded`). Solo un pedido
`status=CANCELLED` entero suprime el cancelar individual. Texto i18n
`order_fully_refunded` reescrito en ese sentido.

### 5.12 Datos

- Sin backfill: pedidos legacy no tienen filas `deposit_remainder` →
  `itemDepositRemainderCents=0` → cálculos idénticos.
- Columna muerta `prices.deposit_cents` **eliminada** (migración
  `2026_06_09_000001_drop_deposit_cents_from_prices.php`). Fuente única de la señal:
  `ticket_types.deposit_type/deposit_value` (`TicketType::depositCents()`, `hasDeposit()`,
  `depositLabel()`).

---

## 6. Prueba de no-regresión legacy

Para `deposit_type=none`: `depositCents(v)=v` → `resto_línea=0` → no se crea
`deposit_remainder` → `itemDepositRemainderCents=0`:
- `itemCollectedCents = max(0, chargedSubtotal − extraDue − 0)` — idéntico al pre-señal.
- `pendingAtGate = (extraDue + 0) − (resolved + 0)` — idéntico.
- `Payment.amount = onlineDueCents = Σ valor` — idéntico a `Order.total`.

→ **Invariante:** ningún pedido sin señal cambia un céntimo por el MODELADO del depósito.
Test obligatorio (existe).

> **Salvedad:** D8/D9 SÍ cambian comportamiento a propósito en TODOS los pedidos (bajada ya no
> auto-reembolsa; full-refund no bloquea cancelar). Son ortogonales al depósito y llevan sus
> propios tests. No confundir con la no-regresión del modelado.

---

## 7. Ejemplos numéricos (reconcilian a los dos niveles)

> Ejemplo del sector origen: pack valor 180 €, señal fija 30 €; entrada 40 € (sin señal).

**E1 — Pedido mixto recién pagado.** `Order.total=220`; `deposit_remainder(pack)=150`;
`onlineDueCents = 30 + 40 = 70` → `Payment.amount=70` → ida Redsys 70 → canario 70 ✓.
Pedido: online 70 · puerta 150 · valor 220 → **70+150+0=220** ✓. Pack: `itemCollectedCents =
180−0−150 = 30`; **30+150=180** ✓. Entrada: **40=40** ✓.

**E2 — Subir cantidad (+30 valor) tras pagar (D5).** `extra_due=30`; `deposit_remainder=150`
congelado; `chargedSubtotal=210`; `itemCollectedCents = 210−30−150 = 30` (señal congelada).
Puerta = 180. **30+180=210** ✓.

**E3 — Bajar cantidad (−30 valor) — D8.** `gateCredit=min(30,150)=30` → remainder 150→120;
sin reembolso. `itemCollectedCents = 150−0−120 = 30` (depósito intacto). **30+120=150** ✓.
0 pendiente de devolución (30 ≤ 150).

**E4 — Bajar por debajo de la señal (valor → 20, reduce 160) — D8.**
`gateCredit=min(160,150)=150` → remainder 0; remanente 10 aflora como **«pendiente de
devolución» = 10**. `itemCollectedCents = 20`; `itemOriginalOnlineCents = depositCents(180) =
30`; `itemPendingRefundCents = max(0, 30−0−20) = 10` ✓. Reembolso aparte si procede (§5.10);
techo `refundableItemCents = 30`.

**E5 — Cancelar el pack (sin reembolsar — D4).** Soft-cancel excluye el item de
`productsValue`, `extraDue` y `deposit_remainder`. «Pendiente de devolución» del pack =
`itemCollectedCents = 30` (cobrado online sin producto detrás). El empleado puede reembolsar
o quedarse el depósito según T&C.

**E6 — Reembolso por línea (techo = señal).** El modal ofrece `30`, NO 180. Techo agregado
`refundableCapacityCents = 70`; Redsys capa server-side además. ✓

---

## 8. Matriz de coherencia (operación por operación)

| Operación | Comportamiento con señal | Mecanismo |
|---|---|---|
| Reembolso TOTAL (`executeFullRefund`) | Devuelve la señal cobrada (`Payment.amount`); REST si Redsys, manual record-only si caja | Gating D7 (§5.10) |
| Reembolso parcial libre (`executePartialRefund`) | Tope agregado = señal | `refundableCapacityCents` + gating de modo |
| Reembolso por ITEM | ≤ online original de la línea (deposit-aware) | Palanca §5.4 + techo §5.8 |
| Reembolso por COMPLEMENTO | Como item; addon de puerta → collected 0; addon de checkout → su señal | Palanca §5.4 |
| Subir cantidad / producto más caro | Incremento → puerta; señal congelada | D5 (sin cambio) |
| Bajar cantidad / producto más barato | SOLO cancela; sobre-cobro → «pendiente de devolución»; refund manual aparte | §5.8 (D8) |
| Añadir complemento | A cobrar en puerta | D5 |
| Quitar complemento (q=0) | Soft-cancel; su cargo se anula; si estaba en la señal, su collected queda refundable | cancel-only heredado + palanca |
| Cambio de menú (gratis↔pago) | Net-cero | compatible |
| Cancelar item | Soft-cancel; anula su `deposit_remainder`; refund aparte | D4 |
| Cancelar item tras reembolso completo | Cancelar SIGUE disponible | §5.11 (D9) |
| Cancelar pedido | Devolución de la señal = decisión del empleado | D4 (política, no código) |
| Pedido manual | Cobra solo la señal; resto en el local; reembolso solo manual record-only | §5.7 + §5.10 |
| Reintento de pago | Reintenta la señal | `onlineDueCents` en `RetryPaymentController` |
| Caducidad (`orders:expire`) | Igual (no usa importe) | sin cambio |

---

## 9. Invariantes a proteger (con tests)

1. **No-regresión legacy** (§6): pedido `deposit_type=none` no cambia un céntimo
   (subir/bajar/cancelar/reembolsar incluidos).
2. **`Σ señal_línea == Payment.amount == DS_MERCHANT_AMOUNT == canario`** (fuente única
   `onlineDueCents`). Caso percent multi-línea (redondeo por línea).
3. **Techo de reembolso por línea = cobrado online de esa línea**, no el valor.
4. **Reconciliación a los dos niveles** (§7) en todos los estados.
5. **`Order.total` inmutable** (factura, cards, PDF, total final).
6. **Canario `amount_mismatch` intacto** (`RedsysReturnHandler`) — NO relajar.
7. Bajada de pack con señal fija ⇒ 0 reembolso online (E3); bajada por debajo de la señal ⇒
   sobre-cobro exacto como pendiente (E4).
8. **Invariante de desglose:** `Σ reservationGateLines == aCobrarPuerta` (probado).

---

## 10. Superficies (anuncio y desglose de la señal)

- **Catálogo:** «Señal :amount para reservar» (`TicketType::depositLabel` — valor CONFIGURADO,
  no `depositCents`, que con precio por-unidad caparía; `fixed`→€, `percent`→%).
- **Paso de cantidad:** «Señal :deposit ahora · :rest en el local»
  (calculado en el COMPONENTE — ver gotcha Blade abajo). ⚠️ **El nombre `Purchase::stepDepositHint`
  es FANTASMA** (corregido 2026-08-19): 0 ocurrencias en el código; nunca se portó del origen.
- **Cesta y pago:** nota por-producto en cada card; el total del pago es «**Total a pagar
  ahora**» + «En el local». El agregado online NO se etiqueta «señal» (en cestas mixtas
  pack+entrada sería falso): agregado neutro «Pagado online», y la señal real se nombra
  por-producto (`deposit_card_note`, tense-neutral). `confirmationSummary` expone
  `has_deposit`/`deposit`/`gate_remainder` por línea.
- **Panel** (`order-totals`, card `reservation-financials`, modal calendario), **«Mis
  pedidos»**, **PDF** (`ReservationSlip`) y **emails** (`OrderConfirmation`,
  `OrderProcessedAfterExpiration`) muestran «Señal pagada / Pendiente en el local».
- **Desglose ↳ de «A cobrar en el local»**: `Order::reservationGateLines($principal)` (reúne
  `pendingAtGateLines($itemIds)` + línea «Resto de la señal»). La línea «Resto de la señal»
  nombra su producto y se desglosa POR PRODUCTO
  (`Order::depositRemainderPendingByProduct()`, Σ == `depositRemainder −
  depositRemainderResolved`). El desglose va **oculto por defecto** tras un toggle «Ver
  desglose» (Alpine `x-show`; las líneas siguen server-rendered en el DOM → `assertSee`
  funciona) en panel y cliente.
- **Predicado canónico «este pedido lleva señal»**: `depositRemainder > 0` (evita falsos
  positivos en pedidos legacy con línea cancelada).
- **Fix N+1 heredado:** `Order::itemFinishedInPractice()` resuelve el parent desde la
  relación `items` ya cargada (elimina N+1 en `reservationGateLines` y order-totals; test de
  0 consultas).

**Gotchas:**
- ⚠️ **Blade:** `purchase.blade.php` es enorme; `@php`/`@php(...)` anidados rompen el
  compilado (dangling `endif` / PCRE «regex too large») → calcular en el componente, Blade fino.
- El email de pago-tras-caducidad debe usar el importe cobrado (la señal), no el total (bug
  corregido en el origen).

---

## 11. Ficheros load-bearing (mapa rápido)

`app/Domain/Booking/Services/OrderCreator.php` (creación + `deposit_remainder`) · `app/Domain/Payments/Services/Redsys.php`
(`:302` ida, `:354-358` guard `gateway_order`, `:376` REST refund) ·
`app/Domain/Booking/Models/TicketType.php` (`depositCents`/`hasDeposit`/`depositLabel`) ·
`app/Domain/Booking/Models/Order.php` (`itemCollectedCents` — palanca; `itemExtraDueCents`;
`itemDepositRemainderCents`; `onlineDueCents`; `applyDepositRemainderCredit`;
`recordReductionMarker`; `isRedsysRefundable`; `itemOriginalOnlineCents` deposit-aware;
`itemRefundableRemainderCents`; `pendingAtGateLines`/`gateLineLabel`;
`reservationGateLines`; `depositRemainderPendingByProduct`; `itemFinishedInPractice`) ·
`app/Domain/Booking/Services/OrderFinancialSummary.php` · `app/Domain/Booking/Services/ReservationFinancials.php` ·
`app/Filament/Resources/Orders/Pages/ViewOrder.php` (rama de bajada; acciones Reembolsar +
selector de modo) · `app/Domain/Payments/Concerns/GuardsItemRefunds.php` ·
`app/Domain/Booking/Services/ManualOrderFulfiller.php` · `app/Http/Controllers/Payments/RetryPaymentController.php` ·
`app/Domain/Booking/Services/CartPricer.php` (el desglose de la señal por línea; ⚠️ aquí vivía
`Livewire\Tickets\Purchase::cartDepositCents()`, retirado en 4.7·2b·3 — hoy el cajón lo pide por
`POST /api/v1/orders/quote`) ·
`app/Domain/Booking/Models/OrderAdjustment.php` (tipos) · `app/Domain/Payments/Services/RedsysReturnHandler.php` (canario, NO
tocar) · `resources/views/filament/orders/items-list.blade.php` (banner D9) ·
`app/Domain/Content/Services/LegalContent.php` (cláusula de reembolso de señal — marcador `[PENDING]`
heredado).

## 12. Tests

`DepositFoundationTest` · `DepositChargeTest` (señal, manual, mixto, percent, canario ida) ·
`DepositSurfacesTest` (sin pendiente fantasma / sobre-cobro E4) · `DepositRefundCoherenceTest`
(gating D7, techo, cancelar tras full-refund) · `ManageItemQuantityProductTest` (reescrita a
cancel-only + pendiente) · `OrderGateCreditTest` · `OrderApplyExtraDueTest` ·
`RefundItemActionTest` · `OrderAdminActionsTest` · casos en `ReservationFinancialsTest`
(incl. `test_no_phantom_pending_refund_when_increasing_quantity_of_a_deposit_pack`, N+1 0
consultas) y `OrderFinancialSummaryTest`.
