<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Payments\Services\Redsys;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **`#146` D4 + D3 (+D2 y el SEXTO sitio) — la reconstrucción sobrevive a un cambio de PRECIO**
 * (`DECISIONES #150`).
 *
 * ⚠️⚠️ El tronco: `PAY-18` hizo que mover la fecha RE-TARIFIQUE, y la reconstrucción de lo cobrado
 * original (`itemOriginalOnlineCents`) solo sabía hacerlo por CANTIDAD — multiplicando además por
 * el `unit_price` ACTUAL, que la re-tarificación ya había sobrescrito. Medido en `#149` con los
 * números del owner: pagó 40,00 → movida a día de 30,00 → cancelada; se le debían 40,00, el tope
 * por línea decía 30,00 y **10,00 quedaban ATRAPADOS sin ninguna vía de panel** (el reembolso del
 * pedido se bloquea sobre cancelados). El marcador de reducción (D3) tampoco se disparaba sin
 * `quantity_change`: «(ninguna fila de ajuste)».
 *
 * El arreglo: el cambio de precio unitario viaja como cambio ESTRUCTURADO en el contexto del
 * ajuste (`unit_price_change`), el marcador se dispara con cualquier cambio reconstruible, y la
 * reconstrucción calcula `cantidad_original × precio_original` — cada término del PRIMER cambio de
 * su clase. Cubre los CUATRO caminos que `#146` exige: bajar cantidad · bajar precio · las dos ·
 * cancelar tras una bajada.
 */
class ItemPriceChangeReconstructionTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private string $saturday = '2026-06-06';   // tarifa fin de semana: 20,00/ud

    private string $monday = '2026-06-08';     // tarifa normal: 12,00/ud

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Notification::fake();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true]);
        RateType::create(['key' => 'weekend', 'label' => ['es' => 'Fin de semana'],
            'weekdays' => [0, 6], 'priority' => 10, 'is_active' => true]);

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entry->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'),
            'amount_cents' => 1200,
        ]);
        $this->entry->prices()->create([
            'rate_type_id' => RateType::where('key', 'weekend')->value('id'),
            'amount_cents' => 2000,
        ]);

        foreach ([$this->saturday, $this->monday] as $d) {
            foreach (range(9, 14) as $h) {
                Slot::create([
                    'zone_id' => $this->zone->id, 'date' => $d,
                    'start_time' => sprintf('%02d:00:00', $h),
                    'end_time' => sprintf('%02d:00:00', $h + 1),
                    'capacity' => 60, 'online_capacity' => 60,
                    'online_sales_open' => true, 'status' => Slot::STATUS_OPEN,
                ]);
            }
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * CAMINO «bajar precio» (D3+D4): mover la fecha a un día más barato en un pedido pagado
     * íntegro online DEJA MARCADOR con la causa completa, y el tope de reembolso de la línea
     * sigue siendo lo que se cobró de verdad.
     */
    public function test_a_price_drop_by_date_leaves_a_marker_and_keeps_the_refund_cap(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 2);   // 2 × 20,00 = 40,00 cobrados

        $this->moveTo($order, $item, $this->monday);                     // re-tarifica a 2 × 12,00

        // D3: el marcador EXISTE y porta la causa entera.
        $marker = $this->fresh($order)->adjustments()
            ->where('order_item_id', $item->id)
            ->where('reason', 'reduction_marker')
            ->first();
        $this->assertNotNull($marker, 'la bajada por fecha tiene que dejar su marcador (D3)');
        $this->assertSame(0, (int) $marker->amount_cents);
        $changes = $marker->context['changes'] ?? [];
        $this->assertArrayHasKey('slot_change', $changes);
        $this->assertSame(
            ['old' => 2000, 'new' => 1200],
            $changes['unit_price_change'] ?? null,
            'el cambio de precio viaja estructurado, con el precio ORIGINAL en `old`',
        );

        // D4: la reconstrucción dice lo que se COBRÓ, no lo que vale ahora.
        $fresh = $this->fresh($order);
        $this->assertSame(4000, $fresh->itemOriginalOnlineCents($item->fresh()), 'se cobraron 40,00 online');
        $this->assertSame(4000, $fresh->itemRefundableRemainderCents($item->fresh()), 'y ese es el tope de la línea');
        $this->assertSame(
            1600, $fresh->itemPendingRefundCents($item->fresh()),
            'el sobre-cobro de la línea AFLORA: 40,00 cobrados − 24,00 de valor vivo',
        );
        $this->assertSame(1600, $fresh->financialSummary()->pendienteDevolucion());
    }

    /** CAMINO «bajar cantidad» (regresión): la conducta de siempre no se mueve. */
    public function test_a_quantity_only_drop_keeps_the_original_behaviour(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 2);   // 40,00 cobrados

        $this->editQty($order, $item, 1);                                // 2 → 1, mismo día

        $marker = $this->fresh($order)->adjustments()
            ->where('order_item_id', $item->id)->where('reason', 'reduction_marker')->first();
        $this->assertNotNull($marker);
        $changes = $marker->context['changes'] ?? [];
        $this->assertArrayHasKey('quantity_change', $changes);
        $this->assertArrayNotHasKey('unit_price_change', $changes, 'sin re-tarificación no hay cambio de precio');

        $fresh = $this->fresh($order);
        $this->assertSame(4000, $fresh->itemOriginalOnlineCents($item->fresh()));
        $this->assertSame(2000, $fresh->itemPendingRefundCents($item->fresh()));
    }

    /** CAMINO «las dos a la vez»: bajar cantidad Y moverse a un día más barato en UNA edición. */
    public function test_quantity_and_price_drop_combined_reconstructs_both_terms(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 2);   // 40,00 cobrados

        $this->moveTo($order, $item, $this->monday, qty: 1);             // 1 × 12,00 = 12,00 de valor

        $fresh = $this->fresh($order);
        $this->assertSame(1200, $fresh->financialSummary()->totalFinalNeto());
        $this->assertSame(
            4000, $fresh->itemOriginalOnlineCents($item->fresh()),
            'cantidad original (2) × precio original (20,00) — ni el qty nuevo ni el precio nuevo',
        );
        $this->assertSame(2800, $fresh->itemPendingRefundCents($item->fresh()));
        $this->assertSame(2800, $fresh->financialSummary()->pendienteDevolucion());
    }

    /**
     * CADENA de ediciones: bajar el precio (sáb→lun) y luego volver a subirlo (lun→sáb). El precio
     * ORIGINAL es el `old` del PRIMER cambio (20,00), no el del último (12,00) — la mutación
     * «coger el último» produce 24,00 y este caso la mata.
     */
    public function test_a_chain_of_edits_reconstructs_from_the_earliest_change(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 2);   // 40,00 cobrados

        $this->moveTo($order, $item, $this->monday);                     // 20,00 → 12,00 (marcador)
        // ⚠️ Reloj adelantado a propósito: con el reloj congelado las dos ediciones comparten
        // `created_at` y «el primero» degenera al orden de iteración — la mutación «coger el
        // último» salía VERDE (medido en `#150`). Con marcas distintas, muerde.
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:05:00'));
        $this->moveTo($order, $item, $this->saturday);                   // 12,00 → 20,00 (extra_due)

        $fresh = $this->fresh($order);
        $this->assertSame(
            4000, $fresh->itemOriginalOnlineCents($item->fresh()),
            'el original es el PRIMER `old` de su clase: 2 × 20,00',
        );
    }

    /**
     * CAMINO «cancelar tras una bajada» — EL CALLEJÓN de `#146`/`#149`, cerrado. Es el escenario
     * EXACTO que midió D4: bajada por fecha → **cancelar la RESERVA** (el pedido sigue `paid`) →
     * «Reembolsar» la línea. Antes el tope decía 24,00 de 40,00 y los 16,00 restantes no salían
     * por esa vía; ahora sale TODO lo cobrado.
     */
    public function test_cancel_item_after_a_price_drop_frees_the_whole_payment(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 2);   // 40,00 cobrados
        $payment = $order->payments()->firstOrFail();

        $this->moveTo($order, $item, $this->monday);                     // valor 24,00 · deuda 16,00

        // ⚠️ Sin `Http::fake()` pelado aquí: un catch-all registrado ANTES se come la respuesta
        // FIRMADA del fake específico de abajo y el REST sale «failed» (trampa medida en `#150`).
        Livewire::actingAs($this->staff(['orders.view', 'orders.cancel_item']))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancelItem', data: [], arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();
        $fresh = $this->fresh($order);
        $this->assertSame(4000, $fresh->financialSummary()->pendienteDevolucion(), 'cancelada la reserva: se debe TODO lo cobrado');
        $this->assertSame(
            4000, $fresh->itemRefundableRemainderCents($item->fresh()),
            'y el tope de la línea ALCANZA — antes se quedaba en el precio re-tarificado',
        );

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(PaymentRefund::REDSYS_REFUND_SUCCESS_CODE, $payment->gateway_order),
                200,
            ),
        ]);
        Livewire::actingAs($this->staff(['orders.view', 'orders.refund_item']))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'intent' => PaymentRefund::INTENT_COMPENSATION,
                    'items_to_refund' => [$item->id],
                ],
                arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->firstOrFail();
        $this->assertSame(PaymentRefund::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame(4000, (int) $refund->amount_cents, 'sale TODO el dinero del cliente, no una parte');
        $this->assertSame(
            0, $this->fresh($order)->financialSummary()->pendienteDevolucion(),
            'y no queda NADA atrapado — el callejón está cerrado',
        );
    }

    /**
     * ⚠️⚠️ **Un pedido CANCELADO con deuda SE PUEDE reembolsar POR LÍNEA** (`DECISIONES #152`,
     * owner). Hasta `#152` no tenía NINGUNA vía de panel —el total bloqueado
     * (`already_cancelled`) y la línea también (`order_not_paid`)— y el cliente leía «tenemos
     * pendiente devolverte X €» para siempre. Cancelar cancela el PRODUCTO; el dinero se sigue
     * debiendo y esta es la vía. El TOTAL sigue bloqueado a propósito: devolvería
     * `payment.amount` entero sin preguntar, y la atribución por línea es la que explica.
     */
    public function test_a_cancelled_order_with_debt_is_refundable_line_by_line(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 2);   // 40,00 cobrados
        $payment = $order->payments()->firstOrFail();

        $this->moveTo($order, $item, $this->monday);                     // valor 24,00 · deuda 16,00

        Livewire::actingAs($this->staff(['orders.view', 'orders.cancel']))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancel');
        $fresh = $this->fresh($order);
        $this->assertSame(Order::STATUS_CANCELLED, $fresh->status);
        $this->assertSame(4000, $fresh->financialSummary()->pendienteDevolucion());

        // La LÍNEA se abre; el TOTAL sigue vetado (devolvería el pago entero sin preguntar).
        $this->assertNull($fresh->refundItemBlockedReason($item->fresh()));
        $this->assertSame('already_cancelled', $fresh->refundBlockedReason());

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(PaymentRefund::REDSYS_REFUND_SUCCESS_CODE, $payment->gateway_order),
                200,
            ),
        ]);
        Livewire::actingAs($this->staff(['orders.view', 'orders.refund_item']))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'intent' => PaymentRefund::INTENT_COMPENSATION,
                    'items_to_refund' => [$item->id],
                ],
                arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->firstOrFail();
        $this->assertSame(PaymentRefund::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame(4000, (int) $refund->amount_cents, 'sale TODO el dinero del cliente');
        $this->assertSame(0, $this->fresh($order)->financialSummary()->pendienteDevolucion(),
            'y el «pendiente de devolverte» del cliente queda a CERO');
    }

    /** Y el candado que la apertura NO afloja: un cancelado SIN deuda sigue sin ofrecer nada. */
    public function test_a_cancelled_order_with_nothing_left_stays_blocked(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 2);
        $payment = $order->payments()->firstOrFail();

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(PaymentRefund::REDSYS_REFUND_SUCCESS_CODE, $payment->gateway_order),
                200,
            ),
        ]);
        // Reembolso TOTAL con cancelación en una acción: no queda deuda.
        Livewire::actingAs($this->staff(['orders.view', 'orders.refund', 'orders.cancel']))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: ['mode' => PaymentRefund::MODE_REST, 'also_cancel' => true]);

        $fresh = $this->fresh($order);
        $this->assertSame(Order::STATUS_CANCELLED, $fresh->status);
        $this->assertSame(0, $fresh->financialSummary()->pendienteDevolucion());
        $this->assertSame('already_fully_refunded', $fresh->refundItemBlockedReason($item->fresh()));
    }

    /**
     * EL SEXTO SITIO (`#149`, «+2 Pack»): una subida por CAMBIO DE FECHA cuyo diff es múltiplo del
     * precio unitario ya NO se narra como cantidad — la etiqueta dice que fue la fecha. La leía el
     * CLIENTE en su desglose.
     */
    public function test_a_date_move_gate_charge_is_not_labelled_as_a_quantity_change(): void
    {
        [$order, $item] = $this->paidOrderOn($this->monday, qty: 5);     // 5 × 12,00 = 60,00

        $this->moveTo($order, $item, $this->saturday);                   // +5 × 8,00 = 40,00 (múltiplo de 20,00)

        $lines = $this->fresh($order)->gateBreakdownLines();
        $this->assertCount(1, $lines);
        $this->assertSame(4000, (int) $lines[0]['amount']);
        $adj = $this->fresh($order)->adjustments()->where('amount_cents', '>', 0)->latest('id')->firstOrFail();
        $this->assertSame(
            __('tickets.gate_change_line_slot', [
                'when' => $adj->context['changes']['slot_change']['new'],
            ]),
            $lines[0]['label'],
            'era «+2 producto» — cantidad INVENTADA por divisibilidad — y la leía el cliente',
        );
    }

    /** Control del sexto sitio: una subida REAL de cantidad conserva su «+N producto». */
    public function test_a_real_quantity_increase_still_labels_as_quantity(): void
    {
        [$order, $item] = $this->paidOrderOn($this->monday, qty: 1);

        $this->editQty($order, $item, 3);                                // +2 × 12,00 en puerta

        $lines = $this->fresh($order)->gateBreakdownLines();
        $this->assertCount(1, $lines);
        $this->assertSame('+2 Jump 1h', $lines[0]['label']);
    }

    /** D2: el toast de una bajada por FECHA ya no dice «unidades canceladas». */
    public function test_the_toast_tells_the_truth_for_a_price_drop(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 2);

        $this->moveTo($order, $item, $this->monday)
            ->assertNotified(__('admin.orders.manage_item.success_edited_reduced_price'));
    }

    /** D2 control: una bajada de CANTIDAD conserva su texto («unidades canceladas» es verdad ahí). */
    public function test_the_toast_keeps_the_quantity_wording_for_a_quantity_drop(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 2);

        $this->editQty($order, $item, 1)
            ->assertNotified(__('admin.orders.manage_item.success_edited_reduced'));
    }

    // ── helpers (fixture del retariff: `ItemDateChangeRetariffTest`) ────────────────────────────

    /** @return array{0: Order, 1: OrderItem} */
    private function paidOrderOn(string $date, int $qty): array
    {
        $unit = $date === $this->monday ? 1200 : 2000;
        $total = $unit * $qty;

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-REC'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => $total, 'total' => $total,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) ($this->counter + 600000), 10, '0', STR_PAD_LEFT),
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->entry->id,
            'slot_id' => Slot::where('zone_id', $this->zone->id)->where('date', $date)
                ->where('start_time', '10:00:00')->firstOrFail()->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);

        return [$this->fresh($order), $item->fresh('slot', 'ticketType')];
    }

    /**
     * Mueve la reserva de día por el CALENDARIO, que es como lo hace el operador (§22.3).
     * ⚠️ Los datos del form van por `setActionData`: el primer parámetro de `callMountedAction`
     * son ARGUMENTS, no datos — pasarlos ahí los ignora en silencio (trampa medida en `#150`:
     * el override de cantidad no llegaba y el caso combinado probaba otra cosa).
     */
    private function moveTo(Order $order, OrderItem $item, string $date, ?int $qty = null): Testable
    {
        return Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('manageItem', arguments: ['item' => $item->id])
            ->set('calendarSelectedDate', $date)
            ->set('calendarSelectedTime', '10:00:00')
            ->setActionData($this->editData($item->fresh('slot'), $qty === null ? [] : ['quantity' => $qty]))
            ->callMountedAction()
            ->assertHasNoActionErrors();
    }

    /** Edita SOLO la cantidad (sin tocar el día): conserva la tarifa histórica (`PAY-18`, límite). */
    private function editQty(Order $order, OrderItem $item, int $qty): Testable
    {
        return Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item->fresh('slot'), ['quantity' => $qty]),
                arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();
    }

    private function fresh(Order $order): Order
    {
        return Order::with(['items.children.ticketType', 'items.ticketType', 'items.slot',
            'payments.refunds', 'adjustments'])->findOrFail($order->id);
    }

    private function staff(array $permissions = ['orders.view', 'orders.edit_item']): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id'),
        );

        return $u;
    }

    /** @return array<string,mixed> */
    private function editData(OrderItem $item, array $overrides = []): array
    {
        $slot = $item->slot;

        return array_merge([
            'optimistic_token' => (string) ($item->updated_at?->getTimestamp() ?? ''),
            'product_id' => (int) $item->ticket_type_id,
            'quantity' => (int) $item->quantity,
            'slot_date' => $slot?->date?->toDateString() ?? '',
            'slot_time' => $slot?->start_time ?? '',
        ], $overrides);
    }

    private function fakeRedsysRefundResponse(string $dsResponse, string $gatewayOrder): array
    {
        $redsys = app(Redsys::class);
        $cfg = $redsys->config();
        $params = $redsys->createMerchantParameters([
            'Ds_Order' => $gatewayOrder,
            'Ds_Response' => $dsResponse,
            'Ds_MerchantCode' => $cfg['merchant_code'],
            'Ds_Terminal' => $cfg['terminal'],
        ]);

        return [
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $redsys->createMerchantSignature($cfg['secret_key'], $params, $gatewayOrder),
        ];
    }
}
