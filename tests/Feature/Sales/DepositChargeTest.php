<?php

namespace Tests\Feature\Sales;

use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductAddon;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Support\ManualOrderFulfiller;
use App\Support\OrderCreator;
use App\Support\Redsys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #225 iter. 2 — Cableado del cobro de la señal/depósito.
 *
 * `OrderCreator` registra el «resto de la señal» (`deposit_remainder`) por línea y los
 * importes de pago (compra, reintento, pedido manual, ida Redsys) leen `onlineDueCents()`
 * en vez de `Order.total`. Verifica el split online/puerta DESDE LA CREACIÓN, la Opción A
 * (complementos de un pack con señal → 100% a puerta), el canario (ida == Payment.amount) y
 * la no-regresión (sin señal → online == total).
 *
 * Nota: la lógica de señal de `OrderCreator` es DATA-DRIVEN (vía `TicketType::depositCents`),
 * no ramifica por tipo de producto. Se prueba con productos `entry` con señal configurada — el
 * "solo packs" (D1) se enforce en el catálogo (CreateCatalog), no aquí. Exactamente el mismo
 * código path que un pack.
 */
class DepositChargeTest extends TestCase
{
    use RefreshDatabase;

    private OrderCreator $creator;

    private User $user;

    private Zone $zone;

    private TicketType $dep;   // 180,00 € · señal fija 30,00 €

    private TicketType $full;  // 40,00 € · sin señal

    private string $date;

    private int $normalRateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = app(OrderCreator::class);
        $this->user = User::factory()->create();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->normalRateId = (int) RateType::where('key', 'normal')->value('id');

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00', '12:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 50, 'online_capacity' => 50,
            ]);
        }

        $this->dep = $this->makeProduct('Con señal', 18000, TicketType::DEPOSIT_FIXED, 3000);
        $this->full = $this->makeProduct('Sin señal', 4000, TicketType::DEPOSIT_NONE, 0);
    }

    public function test_deposit_line_records_remainder_and_online_due_is_the_deposit(): void
    {
        $order = $this->creator->createPendingOrder($this->user, [$this->line($this->dep, '10:00:00', 1)]);
        $order->load(['items', 'adjustments']);

        // El eje VALOR no se toca: total = valor completo.
        $this->assertSame(18000, (int) $order->total);

        // Resto-señal registrado en el principal: 180 − 30 = 150.
        $remainders = $order->adjustments->where('type', OrderAdjustment::TYPE_DEPOSIT_REMAINDER);
        $this->assertCount(1, $remainders);
        $this->assertSame(15000, (int) $remainders->first()->amount_cents);

        // Importe ONLINE = la señal (no el total).
        $this->assertSame(3000, $order->onlineDueCents());
        $principal = $order->items->firstWhere('parent_item_id', null);
        $this->assertSame(3000, $order->itemCollectedCents($principal));
    }

    public function test_mixed_cart_online_due_is_deposit_plus_full_line(): void
    {
        // E1 del plan: pack con señal (180/30) + entrada al total (40).
        $order = $this->creator->createPendingOrder($this->user, [
            $this->line($this->dep, '10:00:00', 1),
            $this->line($this->full, '11:00:00', 1),
        ]);
        $order->load(['items', 'adjustments']);

        $this->assertSame(22000, (int) $order->total);             // valor final
        $this->assertSame(7000, $order->onlineDueCents());         // 30 (señal) + 40 (entrada full)

        // Solo el producto con señal genera resto; la entrada NO.
        $remainders = $order->adjustments->where('type', OrderAdjustment::TYPE_DEPOSIT_REMAINDER);
        $this->assertCount(1, $remainders);
        $this->assertSame(15000, (int) $remainders->first()->amount_cents);
    }

    public function test_percent_deposit_online_due_is_self_consistent_across_lines(): void
    {
        // 30 % de 999 = round(299,7) = 300 por línea; dos líneas → 600 (suma por-línea).
        $pct = $this->makeProduct('Porcentual', 999, TicketType::DEPOSIT_PERCENT, 30);

        $order = $this->creator->createPendingOrder($this->user, [
            $this->line($pct, '10:00:00', 1),
            $this->line($pct, '11:00:00', 1),
        ]);
        $order->load(['items', 'adjustments']);

        // onlineDue == Σ itemCollectedCents (sin descuadre global-vs-línea: el pago y la ida
        // usan la MISMA suma por-línea, por eso el canario nunca diverge).
        $sumCollected = $order->items->sum(fn (OrderItem $i) => $order->itemCollectedCents($i));
        $this->assertSame($sumCollected, $order->onlineDueCents());
        $this->assertSame(600, $order->onlineDueCents());
        $this->assertSame(1998, (int) $order->total);
    }

    public function test_legacy_no_deposit_online_due_equals_total(): void
    {
        $order = $this->creator->createPendingOrder($this->user, [
            $this->line($this->full, '10:00:00', 2),
        ]);
        $order->load(['items', 'adjustments']);

        // No-regresión: sin señal NO hay filas deposit_remainder y online == total.
        $this->assertCount(0, $order->adjustments->where('type', OrderAdjustment::TYPE_DEPOSIT_REMAINDER));
        $this->assertSame(8000, (int) $order->total);
        $this->assertSame(8000, $order->onlineDueCents());
    }

    public function test_addons_of_a_deposit_product_are_fully_charged_at_the_park(): void
    {
        // Opción A (D1/D7): los complementos de un producto con señal van 100% a puerta.
        $socks = $this->attachAddon($this->dep, 'Calcetines', 2000);

        $order = $this->creator->createPendingOrder($this->user, [
            $this->line($this->dep, '10:00:00', 1) + ['addons' => [['ticket_type_id' => $socks->id, 'qty' => 1]]],
        ]);
        $order->load(['items', 'adjustments']);

        $addonItem = $order->items->firstWhere('parent_item_id', '!=', null);
        $this->assertNotNull($addonItem);
        $this->assertSame(2000, $addonItem->chargedSubtotalCents());

        // El complemento tiene resto-señal = su valor íntegro → cobrado online 0.
        $this->assertSame(2000, $order->itemDepositRemainderCents($addonItem));
        $this->assertSame(0, $order->itemCollectedCents($addonItem));

        // Online del pedido = SOLO la señal del principal (ni el resto del pack ni el complemento).
        $this->assertSame(3000, $order->onlineDueCents());
        $this->assertSame(20000, (int) $order->total); // 180 + 20 (valor completo)
    }

    public function test_addons_of_a_non_deposit_product_are_charged_online(): void
    {
        // Contraste: una entrada SIN señal cobra su complemento online (no a puerta).
        $socks = $this->attachAddon($this->full, 'Calcetines', 2000);

        $order = $this->creator->createPendingOrder($this->user, [
            $this->line($this->full, '10:00:00', 1) + ['addons' => [['ticket_type_id' => $socks->id, 'qty' => 1]]],
        ]);
        $order->load(['items', 'adjustments']);

        $this->assertCount(0, $order->adjustments->where('type', OrderAdjustment::TYPE_DEPOSIT_REMAINDER));
        $this->assertSame(6000, $order->onlineDueCents()); // 40 + 20, todo online
        $this->assertSame(6000, (int) $order->total);
    }

    public function test_manual_order_charges_only_the_deposit(): void
    {
        Notification::fake();

        $order = app(ManualOrderFulfiller::class)->fulfill(
            $this->user,
            [$this->line($this->dep, '10:00:00', 1)],
            ManualOrderFulfiller::METHOD_CASH,
        );
        $order->load(['items', 'adjustments', 'payments']);

        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(18000, (int) $order->total);  // valor completo

        $payment = $order->payments->firstWhere('status', Payment::STATUS_PAID);
        $this->assertNotNull($payment);
        $this->assertSame('cash', $payment->provider);
        $this->assertSame(3000, (int) $payment->amount); // SOLO la señal (D3)
        $this->assertSame(3000, $order->onlineDueCents());
        // El resto queda a cobrar en el parque (resto-señal registrado).
        $this->assertSame(15000, (int) $order->adjustments
            ->where('type', OrderAdjustment::TYPE_DEPOSIT_REMAINDER)->sum('amount_cents'));
    }

    public function test_redsys_ida_amount_equals_payment_amount_not_order_total(): void
    {
        // Canario blindado: la ida envía Payment.amount (la señal), NO Order.total.
        $order = Order::create([
            'user_id' => $this->user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PENDING,
            'subtotal' => 18000, 'tax' => 0, 'total' => 18000, 'currency' => 'EUR',
        ]);
        $payment = Payment::create([
            'payable_type' => Order::class, 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 3000, 'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING, 'gateway_order' => '1234567890',
        ]);

        $form = (new Redsys)->buildPaymentFormData($order, $payment, 'es');
        $data = (new Redsys)->decodeMerchantParameters($form['params']);

        $this->assertSame('3000', $data['DS_MERCHANT_AMOUNT']); // = Payment.amount (señal), NO 18000
    }

    // ─── helpers ──────────────────────────────────────────────────────────

    private function makeProduct(string $name, int $priceCents, string $depositType, int $depositValue): TicketType
    {
        $type = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'position' => 1, 'deposit_type' => $depositType, 'deposit_value' => $depositValue,
        ]);
        $type->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $priceCents]);

        return $type;
    }

    private function attachAddon(TicketType $product, string $name, int $priceCents): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 90,
        ]);
        $addon->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $priceCents]);

        DB::table('product_addons')->insert([
            'product_id' => $product->id, 'addon_id' => $addon->id, 'position' => 0,
            'is_included' => false, 'included_quantity' => 1, 'is_mandatory' => false,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true, 'choice_group' => null,
        ]);

        return $addon;
    }

    /** @return array{ticket_type_id:int, date:string, time:string, qty:int} */
    private function line(TicketType $type, string $time, int $qty): array
    {
        return ['ticket_type_id' => $type->id, 'date' => $this->date, 'time' => $time, 'qty' => $qty];
    }
}
