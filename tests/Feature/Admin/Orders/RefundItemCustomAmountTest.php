<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Payments\Services\Redsys;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\OrderItemRefunded;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **`#146` D5 — «Reembolsar» deja elegir el IMPORTE** (`DECISIONES #149`).
 *
 * ⚠️⚠️ Antes de esto, el botón devolvía SIEMPRE el remanente entero de la línea. Medido
 * ejecutándolo con los números del owner (2026-08-25): una entrada de 40,00 € movida a un día de
 * 30,00 € deja 10,00 € «pendiente de devolución» — y «Reembolsar» devolvía **30,00 €**, dejando una
 * reserva viva de 30,00 pagada con 10,00: **20,00 € regalados**, que el ledger registraba
 * (honestamente) como `compensado`. El dominio siempre supo devolver un importe arbitrario
 * (`executePartialRefund`); lo que faltaba era PREGUNTARLO.
 *
 * La elección exige UNA línea (la atribución por línea es lo que el eje de caja `PAY-17` explota
 * para explicar el desglose) y no relaja ningún tope: el remanente del item y la capacidad del
 * pedido se re-validan bajo lock (`PAY-09`), pase lo que pase en el form.
 */
class RefundItemCustomAmountTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private string $saturday = '2026-06-06';   // tarifa fin de semana: 40,00

    private string $monday = '2026-06-08';     // tarifa normal: 30,00

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
            'amount_cents' => 3000,
        ]);
        $this->entry->prices()->create([
            'rate_type_id' => RateType::where('key', 'weekend')->value('id'),
            'amount_cents' => 4000,
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
     * EL ESCENARIO DEL OWNER, de punta a punta: entrada de 40,00 € pagada online, el operador la
     * mueve por el CALENDARIO a un día de 30,00 € (re-tarifica, `PAY-18`) y devuelve EXACTAMENTE
     * los 10,00 € que se deben. El ledger queda limpio: nada pendiente, nada compensado.
     */
    public function test_refunds_exactly_the_price_difference_after_a_date_move(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday);   // 40,00 cobrados

        $this->moveTo($order, $item, $this->monday);             // re-tarifica a 30,00
        $this->assertSame(1000, OrderBook::forOrder($this->fresh($order))->owedToCustomerCents(), 'fixture: la bajada deja 10,00 a devolver');

        $payment = $order->payments()->firstOrFail();
        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(PaymentRefund::REDSYS_REFUND_SUCCESS_CODE, $payment->gateway_order),
                200,
            ),
        ]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'intent' => PaymentRefund::INTENT_COMPENSATION,
                    'items_to_refund' => [$item->id],
                    'amount_mode' => 'custom',
                    'custom_amount' => '10.00',
                ],
                arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        // A Redsys viajan 10,00 — no los 30,00 del remanente de la línea.
        Http::assertSent(function ($request): bool {
            $decoded = app(Redsys::class)->decodeMerchantParameters($request->data()['Ds_MerchantParameters']);

            return ($decoded['DS_MERCHANT_AMOUNT'] ?? null) === '1000';
        });

        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->firstOrFail();
        $this->assertSame(PaymentRefund::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame(1000, (int) $refund->amount_cents, 'se devuelve el importe ELEGIDO');
        $this->assertFalse($item->fresh()->isCancelled(), 'reembolsar no cancela (decisión #157)');

        // El libro queda LIMPIO: se devolvió la deuda exacta, ni pendiente ni regalo.
        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertTrue($book->isConsistent);
        $this->assertSame(3000, $book->totalCents);
        $this->assertSame(3000, $book->paidCents, '40,00 cobrados − 10,00 devueltos');
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind, 'la deuda quedó saldada');
        $this->assertSame(0, OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->count(),
            'y NO se regaló nada — el defecto D5 era exactamente esto');

        Notification::assertSentTo(
            $order->user,
            OrderItemRefunded::class,
            fn (OrderItemRefunded $n): bool => $n->refundedAmountCents === 1000,
        );
    }

    /** El importe elegido no puede superar el remanente de la línea: se bloquea con audit y sin tocar nada. */
    public function test_custom_amount_above_the_line_remainder_is_blocked(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday);   // remanente de línea: 40,00
        Http::fake();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'intent' => PaymentRefund::INTENT_COMPENSATION,
                    'items_to_refund' => [$item->id],
                    'amount_mode' => 'custom',
                    'custom_amount' => '50.00',
                ],
                arguments: ['item' => $item->id]);

        Http::assertNothingSent();
        $this->assertSame(0, PaymentRefund::count(), 'ni fila pending: el bloqueo es ANTES de materializar nada');
        $this->assertTrue(
            AuditLog::where('action', 'orders.item_refund_blocked')
                ->where('payload', 'like', '%exceeds_item_refundable%')->exists(),
            'el bloqueo deja su audit, como el resto de capas',
        );
    }

    /** Con VARIAS líneas marcadas no hay importe elegido: sería ambiguo a cuál se atribuye. */
    public function test_custom_amount_with_multiple_lines_is_blocked(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday);
        $addon = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $this->entry->id, 'slot_id' => $item->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 500,
        ]);
        Http::fake();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'intent' => PaymentRefund::INTENT_COMPENSATION,
                    'items_to_refund' => [$item->id, $addon->id],
                    'amount_mode' => 'custom',
                    'custom_amount' => '10.00',
                ],
                arguments: ['item' => $item->id]);

        Http::assertNothingSent();
        $this->assertSame(0, PaymentRefund::count());
        $this->assertTrue(
            AuditLog::where('action', 'orders.item_refund_blocked')
                ->where('payload', 'like', '%custom_amount_requires_single_item%')->exists(),
        );
    }

    /**
     * Un importe a cero no es una devolución. Lo corta la VALIDACIÓN del form (min 0,01) — capa
     * anterior al handler — y por si el form fallara, el mismo caso vive en el dominio
     * ({@see test_domain_batch_rejects_a_non_positive_override}).
     */
    public function test_zero_custom_amount_is_blocked(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday);
        Http::fake();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'intent' => PaymentRefund::INTENT_COMPENSATION,
                    'items_to_refund' => [$item->id],
                    'amount_mode' => 'custom',
                    'custom_amount' => '0',
                ],
                arguments: ['item' => $item->id])
            ->assertHasActionErrors(['custom_amount']);

        Http::assertNothingSent();
        $this->assertSame(0, PaymentRefund::count());
    }

    /** La defensa del importe no-positivo también vive en el dominio, por si el form fallara. */
    public function test_domain_batch_rejects_a_non_positive_override(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday);

        $result = $this->fresh($order)->executePartialRefundBatch(
            itemIds: [$item->id],
            by: $this->staff(),
            mode: PaymentRefund::MODE_MANUAL,
            alsoCancelItems: false,
            intent: PaymentRefund::INTENT_COMPENSATION,
            amountCentsOverride: 0,
        );

        $this->assertSame([], $result['succeeded']);
        $this->assertSame('invalid_custom_amount', $result['failed'][0]['reason']);
        $this->assertSame(0, PaymentRefund::count());
    }

    /**
     * El camino de SIEMPRE no cambia: sin elegir importe (payload viejo incluido — sin
     * `amount_mode`), se devuelve el remanente completo de la línea.
     */
    public function test_without_choosing_an_amount_the_full_remainder_is_refunded(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday);
        $payment = $order->payments()->firstOrFail();
        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(PaymentRefund::REDSYS_REFUND_SUCCESS_CODE, $payment->gateway_order),
                200,
            ),
        ]);

        Livewire::actingAs($this->staff())
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
        $this->assertSame(4000, (int) $refund->amount_cents, 'el default sigue siendo el remanente entero');
    }

    /** La guarda vive TAMBIÉN en el dominio: el batch rechaza un override con varias líneas. */
    public function test_domain_batch_rejects_an_override_with_multiple_items(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday);
        $addon = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $this->entry->id, 'slot_id' => $item->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 500,
        ]);

        $result = $this->fresh($order)->executePartialRefundBatch(
            itemIds: [$item->id, $addon->id],
            by: $this->staff(),
            mode: PaymentRefund::MODE_MANUAL,
            alsoCancelItems: false,
            intent: PaymentRefund::INTENT_COMPENSATION,
            amountCentsOverride: 1000,
        );

        $this->assertSame([], $result['succeeded']);
        $this->assertSame('custom_amount_requires_single_item', $result['failed'][0]['reason']);
        $this->assertSame(0, PaymentRefund::count());
    }

    /**
     * Y el TOPE POR LÍNEA vive bajo lock en el dominio (`PAY-09`): aunque el form y el handler
     * fallaran, un override por encima del remanente de la línea muere en `executePartialRefund`.
     * ⚠️ El pedido lleva una SEGUNDA línea a propósito: sin ella, exceder la línea excede también
     * la capacidad agregada y dispara la otra guarda — que es exactamente el agujero que el tope
     * por línea existe para cerrar (devolver una línea respaldada por el dinero de OTRA).
     */
    public function test_domain_lock_rejects_an_override_exceeding_the_item_remainder(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday);   // línea: 40,00
        OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->entry->id, 'slot_id' => $item->slot_id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 500,
        ]);
        $order->payments()->update(['amount' => 4500]);          // capacidad agregada: 45,00
        $order->update(['subtotal' => 4500, 'total' => 4500]);

        $result = $this->fresh($order)->executePartialRefundBatch(
            itemIds: [$item->id],
            by: $this->staff(),
            mode: PaymentRefund::MODE_MANUAL,
            alsoCancelItems: false,
            intent: PaymentRefund::INTENT_COMPENSATION,
            amountCentsOverride: 4200,                           // > línea (40,00) · < capacidad (45,00)
        );

        $this->assertSame([], $result['succeeded']);
        $this->assertSame('exceeds_item_refundable', $result['failed'][0]['reason']);
        $this->assertSame(0, PaymentRefund::where('status', PaymentRefund::STATUS_SUCCEEDED)->count());
    }

    // ── helpers (fixture del retariff: `ItemDateChangeRetariffTest`) ────────────────────────────

    /** @return array{0: Order, 1: OrderItem} */
    private function paidOrderOn(string $date): array
    {
        $unit = 4000;   // tarifa de fin de semana (fixture: se compra en sábado)

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-D5X'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => $unit, 'total' => $unit,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $unit, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) ($this->counter + 500000), 10, '0', STR_PAD_LEFT),
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->entry->id,
            'slot_id' => Slot::where('zone_id', $this->zone->id)->where('date', $date)
                ->where('start_time', '10:00:00')->firstOrFail()->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => $unit,
        ]);

        return [$this->fresh($order), $item->fresh('slot', 'ticketType')];
    }

    /** Mueve la reserva de día por el CALENDARIO, que es como lo hace el operador (§22.3). */
    private function moveTo(Order $order, OrderItem $item, string $date): void
    {
        $slot = $item->fresh('slot')->slot;
        Livewire::actingAs($this->staff(['orders.view', 'orders.edit_item']))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('manageItem', arguments: ['item' => $item->id])
            ->set('calendarSelectedDate', $date)
            ->set('calendarSelectedTime', '10:00:00')
            ->callMountedAction([
                'optimistic_token' => (string) ($item->fresh()->updated_at?->getTimestamp() ?? ''),
                'product_id' => (int) $item->ticket_type_id,
                'quantity' => (int) $item->quantity,
                'slot_date' => $slot?->date?->toDateString() ?? '',
                'slot_time' => $slot?->start_time ?? '',
            ])
            ->assertHasNoActionErrors();
    }

    private function fresh(Order $order): Order
    {
        return Order::with(['items.children.ticketType', 'items.ticketType', 'items.slot',
            'payments.refunds', 'adjustments'])->findOrFail($order->id);
    }

    private function staff(array $permissions = ['orders.view', 'orders.refund_item']): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id'),
        );

        return $u;
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
