<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderFinancialSummary;
use App\Domain\Booking\Services\ReservationFinancials;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * INVARIANTES financieros cruzados (red de seguridad para futuros refactors).
 *
 * La auditoría de organización del código marcó como hallazgo ALTO que la regla
 * "pendiente de devolución / valor neto del pedido" se calcula en TRES sitios que
 * DEBEN coincidir y hoy solo coinciden "por disciplina + tests", no por construcción:
 *   - {@see Order::itemPendingRefundCents()} (por ítem),
 *   - {@see ReservationFinancials} (por reserva = card de producto, #200),
 *   - {@see OrderFinancialSummary} (agregado del pedido).
 *
 * Este test PINEA explícitamente que las tres fuentes reconcilian, en escenarios
 * variados (sin actividad, cargo de puerta pendiente/cobrado, cancelación, reembolso
 * parcial, combo). No prueba un cómputo nuevo: ANCLA la coincidencia que un cambio
 * futuro en cualquiera de las tres implementaciones rompería en silencio.
 *
 * Invariantes verificados sobre cada pedido:
 *   1. Por reserva:  valor == pagadoOnline + aCobrarPuerta + cobradoPuerta.
 *   2. Σ cards valor          == OrderFinancialSummary::totalFinalNeto().
 *   3. Σ cards aCobrarPuerta   == OrderFinancialSummary::pendingAtGate().
 *   4. Σ cards pendienteReembolso == OrderFinancialSummary::pendienteDevolucion().
 *   5. Σ Order::itemPendingRefundCents() (todos los ítems) == pendienteDevolucion().
 */
class OrderFinancialInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private int $counter = 0;

    private int $paymentCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_plain_paid_order_with_addon_reconciles(): void
    {
        $order = $this->makePaidOrder();
        $principal = $this->attachActiveItem($order, unitPrice: 1000);
        $this->attachAddon($order, $principal, unitPrice: 300);
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'sin actividad (principal + complemento)');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(1300, $summary->totalFinalNeto());
        $this->assertSame(0, $summary->pendienteDevolucion());
    }

    public function test_active_item_with_pending_gate_charge_reconciles(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        // El producto vale 1500 hoy (subió en gestión); de eso 500 se cobra en puerta.
        $item = $this->attachActiveItem($order, unitPrice: 1500);
        $order->applyExtraDue($item, 500, $by);
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'cargo de puerta PENDIENTE (ítem activo)');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(500, $summary->pendingAtGate());
        $this->assertSame(0, $summary->pendienteDevolucion());
    }

    public function test_finished_item_with_collected_gate_charge_reconciles(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItemWithPastSlot($order);   // qty1 × 1000, slot pasado → finalizado
        $order->applyExtraDue($item, 400, $by);
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'cargo de puerta COBRADO (ítem finalizado)');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(0, $summary->pendingAtGate());   // finalizado → ya cobrado en puerta
        $this->assertSame(0, $summary->pendienteDevolucion());
    }

    public function test_cancelled_collected_item_surfaces_pending_refund_and_reconciles(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1000);
        $this->syncTotalToOnline($order);   // total = 1000 (lo pagado online)
        $item->markCancelled($by);          // cancelado, sin reembolsar todavía

        $this->assertReconciles($order, 'cancelación con reembolso pendiente');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(0, $summary->totalFinalNeto());        // ya no hay producto
        $this->assertSame(1000, $summary->pendienteDevolucion()); // todo lo cobrado, por devolver
    }

    public function test_partial_per_item_refund_reconciles(): void
    {
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1000);
        $this->syncTotalToOnline($order);
        $this->attachSucceededRefund($payment, 400, $item->id);   // reembolso parcial de cortesía

        $this->assertReconciles($order, 'reembolso parcial por ítem (producto activo)');

        $order = $this->freshOrder($order);
        $summary = $order->financialSummary();
        $this->assertSame(1000, $summary->totalFinalNeto());      // el producto sigue ahí
        $this->assertSame(0, $summary->pendienteDevolucion());    // el reembolso ya salió ("Devuelto")
        $this->assertSame(400, (int) $order->reservationFinancialsByPrincipal()[0]['rf']->devuelto);
    }

    public function test_combo_active_with_addon_plus_cancelled_principal_reconciles(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);

        $p1 = $this->attachActiveItem($order, unitPrice: 1000);
        $this->attachAddon($order, $p1, unitPrice: 300);
        $p2 = $this->attachActiveItem($order, unitPrice: 800);
        $this->syncTotalToOnline($order);   // total = 1000 + 300 + 800 = 2100
        $p2->markCancelled($by);            // se cancela el segundo principal (sin reembolsar)

        $this->assertReconciles($order, 'combo: activo+complemento y principal cancelado');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(1300, $summary->totalFinalNeto());       // solo P1 + su complemento
        $this->assertSame(800, $summary->pendienteDevolucion());   // P2 cancelado, por devolver
    }

    // ─── Los escenarios que la TERCERA auditoría destapó ───────────────────
    //
    // ⚠️ Los seis de arriba pasaban ya antes de `DECISIONES #127`: su hueco no estaba en la
    // aserción, estaba en el FIXTURE. Estos cinco son los que ejercitan los defectos que se
    // arreglaron, y sin ellos las guardas nuevas no verían nada.

    public function test_cancelled_order_has_no_live_value_and_owes_the_money_back(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $this->attachActiveItem($order, unitPrice: 1000);
        $this->syncTotalToOnline($order);

        // El camino del panel: cancelar el PEDIDO. Antes de #127 dejaba las líneas VIVAS.
        $order = $this->freshOrder($order);
        $order->update(['status' => Order::STATUS_CANCELLED]);
        $order->cancelLiveItems($by);

        $this->assertReconciles($order, 'pedido CANCELADO sin reembolsar');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(0, $summary->totalFinalNeto(), 'un pedido cancelado no tiene valor vivo');
        $this->assertSame(1000, $summary->pendienteDevolucion(), 'y lo cobrado aflora como pendiente de devolver');
    }

    public function test_an_order_never_collected_claims_no_money_even_after_its_slot_passes(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachItemWithPastSlot($order);          // franja PASADA
        $order->applyExtraDue($item, 400, User::factory()->create());
        $this->syncTotalToOnline($order);
        // Nadie llegó a pagarlo: ni `paid_at` ni pago cobrado. Es el checkout abandonado cuya
        // franja pasa — el caso que destapó STAGING.
        $this->freshOrder($order)->update(['status' => Order::STATUS_PENDING, 'paid_at' => null]);
        $order->payments()->delete();

        $this->assertReconciles($order, 'pedido NUNCA cobrado con la franja ya pasada');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(0, $summary->cobradoPuerta(), 'sin cobro no se cobró nada en el parque');
        $this->assertSame(0, $summary->pagadoOnline(), 'ni se pagó nada por web');
        $this->assertGreaterThan(0, $summary->pendienteOnline(), 'lo cobrable sigue PENDIENTE, no pagado');
    }

    public function test_a_full_refund_is_attributed_to_its_reservation(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order, unitPrice: 1000);
        $this->syncTotalToOnline($order);
        $payment = $this->freshOrder($order)->payments->firstWhere('status', Payment::STATUS_PAID);
        // Un reembolso TOTAL se escribe SIN atar a ninguna línea: es la operación sobre el `Payment`.
        $this->attachSucceededRefund($payment, 1000, null);

        $this->assertReconciles($order, 'reembolso TOTAL, sin atar a línea');

        $order = $this->freshOrder($order);
        $this->assertSame(
            1000, (int) $order->reservationFinancialsByPrincipal()[0]['rf']->devuelto,
            'la reserva tiene que ver el reembolso total, no un 0,00 €',
        );
        $this->assertSame(1000, $order->itemRefundedCents($order->items->firstWhere('id', $item->id)));
    }

    public function test_a_deposit_pack_whose_slot_passed_collects_the_rest_at_the_gate(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachItemWithPastSlot($order);          // 1000, franja pasada
        $this->attachDepositRemainder($order, $item, 700);      // señal 300, resto 700 en puerta
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'pack con señal cuya franja YA PASÓ');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(700, $summary->cobradoPuerta(), 'el resto de la señal se cobró en el parque');
        $this->assertSame(0, $summary->pendingAtGate());
        $this->assertSame(300, $summary->pagadoOnline());
    }

    public function test_a_pending_order_owes_its_money_online_not_paid_it(): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-IVP'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR',
        ]);
        $this->attachActiveItem($order, unitPrice: 1000);

        $this->assertReconciles($order, 'pedido PENDIENTE, todavía sin cobrar');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(0, $summary->pagadoOnline());
        $this->assertSame(1000, $summary->pendienteOnline(), 'es lo que FALTA por pagar, no lo pagado');
        $this->assertSame(0, $summary->retenidoOnline(), 'el parque no retiene nada suyo');
    }

    // ─── T4 · el −X € del descuento de fiesta mixta (`specs/cumple-mixto.md` §20 y §24) ─────
    //
    // Los tres casos A/B/C del diseño entran aquí ANTES del reconciliador (§16.8/§20.8), con la
    // línea de crédito construida A MANO: lo que estos escenarios prueban es la CONTABILIDAD del
    // espejo —una línea `is_credit` con su `extra_due` gemelo negativo— contra las dos identidades
    // y los cinco canales, no el mecanismo que la escribe.

    public function test_mixed_party_credit_with_deposit_reconciles(): void
    {
        // CASO A (§20.3): fiesta 8 × 15,00 € = 120,00 · señal 30,00 online · resto 90,00 en
        // puerta · descuento 8,00 (2 invitados de un pack 4,00 € más barato). La puerta absorbe:
        // el cliente pagará 82,00 en el parque.
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order, quantity: 8, unitPrice: 1500);
        $this->attachDepositRemainder($order, $item, 9000);
        $this->attachCreditLine($order, $item, 800, guests: 2, unitCents: 400);
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'T4·A crédito con señal (la puerta absorbe)');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(11200, $summary->totalFinalNeto(), 'el valor baja con el descuento');
        $this->assertSame(8200, $summary->pendingAtGate(), '90,00 − 8,00: en la puerta le pedirán 82,00');
        $this->assertSame(3000, $summary->pagadoOnline(), 'la señal no se toca');
        $this->assertSame(0, $summary->pendienteDevolucion(), 'un descuento de puerta no es deuda bancaria');
    }

    public function test_mixed_party_credit_fully_online_writes_nothing(): void
    {
        // CASO B (§20.2 fase 2): pagado 100 % online → cobertura de puerta CERO → el descuento NO
        // se escribe (el tope de §20.1); el exceso se ENSEÑA como «a tu favor» y se liquida en el
        // parque (§20.5). Este escenario documenta la decisión: no hay línea que construir, y por
        // eso los totales quedan INTACTOS — escribir aquí el crédito dejaría la puerta de la
        // reserva en negativo y el cinturón `max(0,…)` mordería, que este guardián prohíbe.
        // (La mutación de quitar el tope muerde en el caso B del reconciliador,
        // `MixedPartySurchargeTest`.)
        $order = $this->makePaidOrder();
        $this->attachActiveItem($order, quantity: 8, unitPrice: 1500);
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'T4·B pagado 100 % online (el tope no deja escribir)');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(12000, $summary->totalFinalNeto(), 'sin cobertura, los totales no se mueven');
        $this->assertSame(12000, $summary->pagadoOnline());
        $this->assertSame(0, $summary->pendingAtGate());
    }

    public function test_mixed_party_credit_with_partial_coverage_reconciles(): void
    {
        // CASO C (§19.4): señal grande (117,00 online, 3,00 en puerta) → el tope deja escribir
        // SOLO 3,00 de los 8,00 derivados; los 5,00 restantes son el «a tu favor» (no escrito).
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order, quantity: 8, unitPrice: 1500);
        $this->attachDepositRemainder($order, $item, 300);
        $this->attachCreditLine($order, $item, 300, guests: 2, unitCents: 400);
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'T4·C cobertura parcial (3,00 de 8,00)');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(11700, $summary->totalFinalNeto());
        $this->assertSame(0, $summary->pendingAtGate(), 'la puerta queda exactamente en cero, no en negativo');
        $this->assertSame(11700, $summary->pagadoOnline());
    }

    public function test_mixed_party_credit_resolves_with_the_finished_reservation(): void
    {
        // Y al FINALIZAR la fiesta, el crédito se resuelve con sus cubos: «cobrado en puerta»
        // dice 82,00 —lo que de verdad se le cobró—, no 90,00.
        $order = $this->makePaidOrder();
        $item = $this->attachItemWithPastSlot($order);
        $item->forceFill(['quantity' => 8, 'seats' => 8, 'unit_price' => 1500])->save();
        $this->attachDepositRemainder($order, $item, 9000);
        $this->attachCreditLine($order, $item, 800, guests: 2, unitCents: 400);
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'T4 crédito con la reserva FINALIZADA');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(8200, $summary->cobradoPuerta(), 'se cobró 82,00 en el parque, no 90,00');
        $this->assertSame(0, $summary->pendingAtGate());
    }

    // ─── Aserción de reconciliación ────────────────────────────────────────

    private function assertReconciles(Order $order, string $label): void
    {
        $order = $this->freshOrder($order);
        $summary = $order->financialSummary();
        $cards = $order->reservationFinancialsByPrincipal();

        $sumValor = $sumOnline = $sumPendOnline = $sumPend = $sumCobrado = 0;
        $sumDev = $sumPendReemb = $sumComp = 0;
        foreach ($cards as $card) {
            $rf = $card['rf'];

            // ── PAY-16 · EJE VALOR por reserva: los CINCO canales cierran el valor.
            $this->assertSame(
                $rf->pagadoOnline + $rf->pendienteOnline + $rf->aCobrarPuerta + $rf->cobradoPuerta + $rf->compensado,
                $rf->valor,
                "$label · PAY-16 reserva: pagadoOnline+pendienteOnline+aCobrarPuerta+cobradoPuerta+compensado == valor",
            );
            // ⚠️ Los dos canales web son EXCLUYENTES: el mismo importe está en uno o en otro.
            $this->assertTrue(
                $rf->pagadoOnline === 0 || $rf->pendienteOnline === 0,
                "$label · reserva: «pagado por web» y «pendiente de pagar por web» no pueden convivir",
            );
            // ⚠️ Y ningún canal es negativo. Los `max(0, …)` del dominio existen como cinturón; esto
            // asevera que NO están tapando nada — un clamp que muerde es un defecto escondido.
            foreach (['pagadoOnline', 'pendienteOnline', 'aCobrarPuerta', 'cobradoPuerta', 'compensado'] as $canal) {
                $this->assertGreaterThanOrEqual(0, $rf->{$canal}, "$label · reserva: «{$canal}» negativo");
            }

            $sumValor += $rf->valor;
            $sumOnline += $rf->pagadoOnline;
            $sumPendOnline += $rf->pendienteOnline;
            $sumPend += $rf->aCobrarPuerta;
            $sumCobrado += $rf->cobradoPuerta;
            $sumDev += $rf->devuelto;
            $sumPendReemb += $rf->pendienteReembolso;
            $sumComp += $rf->compensado;
        }

        $this->assertSame($summary->totalFinalNeto(), $sumValor, "$label · Σ valor cards == Total final");
        $this->assertSame($summary->pendingAtGate(), $sumPend, "$label · Σ aCobrarPuerta cards == pendingAtGate");
        $this->assertSame($summary->pendienteDevolucion(), $sumPendReemb, "$label · Σ pendienteReembolso cards == pendienteDevolucion");

        // ── Los CUATRO cruces que faltaban (`specs/desglose-dinero-cliente.md` §4.5, §9).
        $this->assertSame(
            $summary->cobradoPuerta(), $sumCobrado,
            "$label · B3 · Σ cobradoPuerta cards == extraDueResolved + depositRemainderResolved",
        );
        $this->assertSame(
            $summary->effectiveRefunded(), $sumDev,
            "$label · B5 · Σ devuelto cards == reembolso efectivo del pedido",
        );
        $this->assertSame(
            $summary->compensado(), $sumComp,
            "$label · Σ compensado cards == compensación del pedido",
        );
        $this->assertSame($summary->pagadoOnline(), $sumOnline, "$label · Σ pagadoOnline cards == pagadoOnline del pedido");
        $this->assertSame($summary->pendienteOnline(), $sumPendOnline, "$label · Σ pendienteOnline cards == pendienteOnline del pedido");
        // C · las DOS derivaciones del importe online coinciden — hasta hoy solo por álgebra.
        $this->assertSame(
            $summary->totalFinalNeto() - $summary->pendingAtGate() - $summary->cobradoPuerta() - $summary->compensado(),
            $summary->pagadoOnline() + $summary->pendienteOnline(),
            "$label · C · las dos fórmulas del importe online coinciden",
        );
        // D · el desglose ↳ de puerta suma su titular.
        $this->assertSame(
            $summary->pendingAtGate(),
            (int) array_sum(array_column($order->gateBreakdownLines(), 'amount')),
            "$label · D · Σ líneas del desglose de puerta == pendingAtGate",
        );

        // ── PAY-16 · EJE VALOR agregado.
        $this->assertSame(
            $summary->pagadoOnline() + $summary->pendienteOnline() + $summary->pendingAtGate()
                + $summary->cobradoPuerta() + $summary->compensado(),
            $summary->totalFinalNeto(),
            "$label · PAY-16 pedido: los cinco canales cierran el valor final",
        );

        // ── PAY-17 · EJE CAJA: todo euro retenido respalda producto o se debe devolver.
        $this->assertSame(
            $summary->pagadoOnline() + $summary->pendienteDevolucion(),
            $summary->retenidoOnline(),
            "$label · PAY-17: retenido == pagadoOnline + pendienteDevolucion",
        );
        $this->assertGreaterThanOrEqual(0, $summary->retenidoOnline(), "$label · PAY-17: retenido negativo (se devolvió más de lo cobrado)");

        // Hallazgo ALTO del audit: la TERCERA fuente (por ítem, directa) también coincide.
        $directPending = (int) $order->items->sum(fn (OrderItem $i) => $order->itemPendingRefundCents($i));
        $this->assertSame($summary->pendienteDevolucion(), $directPending, "$label · Σ itemPendingRefundCents == pendienteDevolucion");

        // ⚠️ La columna agregada del reembolso es SIEMPRE Σ de las filas. Es la guarda de construcción
        // que sustituye a la «legacy-safety»: mientras se cumpla, el `max()` de `effectiveRefunded()`
        // es código muerto (`DECISIONES #127`: JumpWeb solo instala limpio).
        $this->assertFalse(
            $summary->refundColumnDivergesFromRows(),
            "$label · la columna `refund_amount_cents` diverge de Σ payment_refunds",
        );
    }

    // ─── Fixtures (patrón de OrderPerItemHelpersTest) ──────────────────────

    private function freshOrder(Order $order): Order
    {
        return Order::with(['items.children', 'items.slot', 'items.ticketType', 'payments.refunds', 'adjustments'])
            ->findOrFail($order->id);
    }

    /** Ajusta `Order.total` a lo realmente cobrado online (Σ itemCollectedCents): invariante de alta. */
    private function syncTotalToOnline(Order $order): void
    {
        $order->refresh()->load(['items', 'adjustments']);
        $online = (int) $order->items->sum(fn (OrderItem $i) => $order->itemCollectedCents($i));
        $order->update(['subtotal' => $online, 'total' => $online]);
        // #225: «pendiente de devolución» se ancla al dinero REALMENTE cobrado por web (no a
        // `Order.total` como proxy). El pago se crea ANTES de añadir items en estos fixtures, así
        // que aquí lo sincronizamos a lo cobrado online tras montarlos — como en un pedido real.
        //
        // ⚠️⚠️ **Y si no había pago, se crea**: un pedido marcado `paid` SIN ninguna fila `Payment`
        // no lo produce ningún cobro real —ni el web ni el de taquilla— y hace que el eje de caja
        // (`PAY-17`) compare contra un cobro de 0,00 €. Es el mismo defecto de datos que la auditoría
        // encontró en los pedidos sembrados a mano (`specs/desglose-dinero-cliente.md` §9.7): un
        // fixture irreal inventa defectos tan bien como los oculta.
        if ($order->payments()->where('status', Payment::STATUS_PAID)->doesntExist()) {
            $this->attachPaidPayment($order);
        }
        $order->payments()->where('status', Payment::STATUS_PAID)->update(['amount' => $online]);
    }

    private function ensureTicketTypeSetup(): void
    {
        if (isset($this->jumpType)) {
            return;
        }
        RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0,
        ]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->jumpType = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function makePaidOrder(int $total = 1000): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-IV'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function attachPaidPayment(Order $order): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'gateway_order' => str_pad((string) (++$this->paymentCounter + 100000), 10, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * ⚠️ Crea la fila **y actualiza la columna agregada**, que es lo que hace el flujo real: los dos
     * únicos escritores (`executeFullRefund`, `executePartialRefund`) derivan
     * `Order.refund_amount_cents` de `totalRefundedCents()` en la misma transacción. Un fixture que
     * escriba solo la fila fabrica una divergencia que ningún reembolso puede producir.
     */
    private function attachSucceededRefund(Payment $payment, int $amount, ?int $itemId = null): PaymentRefund
    {
        $row = PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $itemId,
            'amount_cents' => $amount,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => User::factory()->create()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $order = Order::with('payments.refunds')->findOrFail($payment->payable_id);
        $order->forceFill([
            'refunded_at' => now(),
            'refund_amount_cents' => $order->totalRefundedCents(),
        ])->save();

        return $row;
    }

    private function attachActiveItem(Order $order, int $quantity = 1, int $unitPrice = 1000): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $h = str_pad((string) ($this->counter++ % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 10, 'online_capacity' => 5,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $slot->id,
            'quantity' => $quantity, 'seats' => $quantity, 'unit_price' => $unitPrice,
        ]);
    }

    private function attachItemWithPastSlot(Order $order): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => '2000-01-01',
            'start_time' => '10:00:00', 'end_time' => '10:59:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
    }

    /**
     * La LÍNEA DE CRÉDITO del descuento de fiesta mixta (T4, `specs/cumple-mixto.md` §24.3), tal
     * como la escribe el reconciliador: una línea hija `is_credit` (su subtotal RESTA vía
     * `chargedSubtotalCents`) con su `extra_due` gemelo NEGATIVO del mismo importe — el patrón
     * exacto del cargo, con el signo cambiado. ⚠️ El `context` lleva la marca `mixed_party` con
     * `credit: true` y JAMÁS `changes.*`: con un `quantity_change` dentro,
     * `itemOriginalOnlineCents` «reconstruiría» un original y el eje de caja inventaría un
     * pendiente de devolución (la trampa medida de §16.5.bis).
     */
    private function attachCreditLine(Order $order, OrderItem $principal, int $writtenCents, int $guests, int $unitCents): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $credit = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $principal->id,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => null,
            'quantity' => 1, 'free_quantity' => 0, 'unit_price' => $writtenCents,
            'is_credit' => true,
            'seats' => 0,
        ]);
        OrderAdjustment::create([
            'order_id' => $order->id,
            'order_item_id' => $credit->id,
            'type' => OrderAdjustment::TYPE_EXTRA_DUE,
            'amount_cents' => -$writtenCents,
            'currency' => 'EUR',
            'applied_by' => User::factory()->create()->id,
            'reason' => 'mixed_party_credit',
            'context' => ['mixed_party' => [
                'credit' => true,
                'guests' => $guests,
                'targets' => [['name' => 'Kids', 'count' => $guests, 'unit_cents' => $unitCents]],
                'derived_cents' => $guests * $unitCents,
            ]],
        ]);

        return $credit;
    }

    /** Resto de la SEÑAL (#225): la parte del valor que no se cobra online y se paga en el parque. */
    private function attachDepositRemainder(Order $order, OrderItem $item, int $cents): void
    {
        OrderAdjustment::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => $cents,
            'currency' => 'EUR',
            'applied_by' => User::factory()->create()->id,
        ]);
    }

    private function attachAddon(Order $order, OrderItem $parent, int $unitPrice = 300): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $parent->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => $unitPrice,
        ]);
    }
}
