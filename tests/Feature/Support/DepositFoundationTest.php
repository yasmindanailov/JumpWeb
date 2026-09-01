<?php

namespace Tests\Feature\Support;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * #225 — Cimientos de la señal/depósito (iteración 1, riesgo cero).
 *
 * Verifica las piezas SIN cablear el cobro todavía:
 *  - Tipo de ajuste `deposit_remainder`.
 *  - `itemDepositRemainderCents` (espejo de `itemExtraDueCents`).
 *  - `itemCollectedCents` redefinido = valor − extra_due − deposit_remainder (LA PALANCA):
 *    legacy idéntico (sin filas deposit_remainder), y deposit-aware cuando las hay.
 *  - `onlineDueCents` = Σ collected de no cancelados (fuente única del importe online).
 *  - Columna muerta `prices.deposit_cents` eliminada.
 *
 * Como iter. 1 NO crea filas `deposit_remainder` en ningún flujo, simulamos esa fila a mano
 * para PROBAR que la palanca corrige en cascada (techo de reembolso) antes de iter. 2.
 */
class DepositFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $type;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_deposit_remainder_type_constant_exists(): void
    {
        $this->assertSame('deposit_split', OrderAdjustment::TYPE_DEPOSIT_SPLIT);
    }

    public function test_deposit_cents_with_zero_value_charges_full_total_not_zero(): void
    {
        // Auditoría Fase 1 (M1): un `fixed`/`percent` con `deposit_value=0` NO es una señal de 0 €
        // (eso haría `onlineDueCents()=0` → `DS_MERCHANT_AMOUNT='0'` → Redsys rechaza el cobro). Se
        // comporta como `none`: cobra el TOTAL online. Coherente con `hasDeposit()`, que ya trata el
        // valor 0 como "sin señal", y con lo que se anuncia al cliente.
        foreach ([TicketType::DEPOSIT_FIXED, TicketType::DEPOSIT_PERCENT] as $type) {
            $t = new TicketType;
            $t->deposit_type = $type;
            $t->deposit_value = 0;

            $this->assertFalse($t->hasDeposit(), "tipo $type con valor 0 NO es señal");
            $this->assertSame(10000, $t->depositCents(10000), "tipo $type con valor 0 cobra el total, no 0");
        }

        // Señal REAL: fixed 30 € sobre 100 € → 30 €; percent 30 % → 30 €.
        $fixed = new TicketType;
        $fixed->deposit_type = TicketType::DEPOSIT_FIXED;
        $fixed->deposit_value = 3000;
        $this->assertSame(3000, $fixed->depositCents(10000));

        $percent = new TicketType;
        $percent->deposit_type = TicketType::DEPOSIT_PERCENT;
        $percent->deposit_value = 30;
        $this->assertSame(3000, $percent->depositCents(10000));
    }

    public function test_prices_deposit_cents_column_is_dropped(): void
    {
        $this->assertTrue(Schema::hasColumn('prices', 'amount_cents'));
        $this->assertFalse(
            Schema::hasColumn('prices', 'deposit_cents'),
            'La columna muerta prices.deposit_cents debe estar eliminada (#225).',
        );
    }

    public function test_no_deposit_remainder_means_collected_equals_charged_subtotal(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachItem($order, unitPrice: 6000, quantity: 3); // 180,00 €
        $order->load(['items', 'adjustments']);

        $this->assertSame(0, $order->itemDepositRemainderCents($item));
        $this->assertSame(18000, $item->chargedSubtotalCents());
        // Legacy: collected == charged (sin extra_due ni deposit_remainder).
        $this->assertSame(18000, $order->itemCollectedCents($item));
    }

    public function test_extra_due_only_keeps_legacy_collected_formula_unchanged(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItem($order, unitPrice: 6000, quantity: 3); // 180,00 €

        // Edición que sube precio (cobro en puerta): NO mueve deposit_remainder.
        $order->recordEdit($item, 6000, $by, 'cantidad 3 → 4');
        $order->load(['items', 'adjustments']);

        $this->assertSame(0, $order->itemDepositRemainderCents($item));
        $this->assertSame(6000, $order->itemExtraDueCents($item));
        // No-regresión: collected = charged − extra_due (idéntico al histórico).
        $this->assertSame(12000, $order->itemCollectedCents($item));
    }

    public function test_deposit_remainder_reduces_collected_and_refund_ceiling(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItem($order, unitPrice: 6000, quantity: 3); // valor 180,00 €

        // Simula lo que hará OrderCreator en iter. 2: señal 30 € → resto 150 € a puerta.
        OrderAdjustment::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
            'amount_cents' => 15000,
            'currency' => 'EUR',
            'applied_by' => $by->id,
        ]);
        $order->load(['items', 'adjustments', 'payments.refunds']);

        $this->assertSame(15000, $order->itemDepositRemainderCents($item));
        // LA PALANCA: cobrado online = valor − resto-señal = la señal (30,00 €).
        $this->assertSame(3000, $order->itemCollectedCents($item));
        // En cascada, el techo de reembolso por línea baja a la señal (no al valor).
        $this->assertSame(3000, $order->itemRefundableRemainderCents($item));
    }

    public function test_deposit_remainder_coexists_with_extra_due(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItem($order, unitPrice: 6000, quantity: 3); // valor 180,00 €

        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
            'amount_cents' => 15000, 'currency' => 'EUR', 'applied_by' => $by->id,
        ]);
        // Subir cantidad tras pagar: +30 € a puerta (extra_due), señal congelada.
        $order->recordEdit($item, 6000, $by, '+1');
        $item->forceFill(['quantity' => 4])->save(); // valor pasa a 240,00 €
        $order->load(['items', 'adjustments']);

        $this->assertSame(15000, $order->itemDepositRemainderCents($item));
        $this->assertSame(6000, $order->itemExtraDueCents($item));
        // collected = 240 − 60 (extra) − 150 (resto-señal) = 30 (la señal, CONGELADA).
        $this->assertSame(3000, $order->itemCollectedCents($item));
    }

    public function test_online_due_cents_sums_collected_and_excludes_cancelled(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $entrada = $this->attachItem($order, unitPrice: 4000, quantity: 1); // 40,00 € (sin señal)
        $pack = $this->attachItem($order, unitPrice: 6000, quantity: 3);    // 180,00 €
        $order->load(['items', 'adjustments']);

        // Sin señal aún: onlineDue = Σ valor = 40 + 180 = 220.
        $this->assertSame(22000, $order->onlineDueCents());

        // Señal del pack (resto 150) → onlineDue baja a 40 + 30 = 70.
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $pack->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
            'amount_cents' => 15000, 'currency' => 'EUR', 'applied_by' => $by->id,
        ]);
        $order->load(['items', 'adjustments']);
        $this->assertSame(7000, $order->onlineDueCents());

        // Cancelar la entrada → excluida del importe online (queda solo la señal del pack).
        $entrada->forceFill(['cancelled_at' => now()])->save();
        $order->load(['items', 'adjustments']);
        $this->assertSame(3000, $order->onlineDueCents());
    }

    public function test_online_due_equals_total_for_fresh_order_without_deposit(): void
    {
        $order = $this->makePaidOrder();
        $this->attachItem($order, unitPrice: 4000, quantity: 1);
        $this->attachItem($order, unitPrice: 6000, quantity: 3);
        $order->load(['items', 'adjustments']);

        // Pedido fresco sin señal: el importe online == Σ valor de los productos.
        $sumCharged = $order->items->sum(fn (OrderItem $i) => $i->chargedSubtotalCents());
        $this->assertSame($sumCharged, $order->onlineDueCents());
        $this->assertSame(22000, $order->onlineDueCents());
    }

    // ─── helpers ──────────────────────────────────────────────────────────

    private function ensureSetup(): void
    {
        if (isset($this->type)) {
            return;
        }
        RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0,
        ]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->type = TicketType::create([
            'name' => ['es' => 'Producto'], 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function makePaidOrder(): Order
    {
        $code = 'JJ-DEP'.str_pad((string) ($this->counter++), 3, '0', STR_PAD_LEFT);

        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => $code,
            'status' => Order::STATUS_PAID,
            'subtotal' => 0, 'tax' => 0, 'total' => 0, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function attachItem(Order $order, int $unitPrice, int $quantity): OrderItem
    {
        $this->ensureSetup();
        $h = str_pad((string) ($this->counter++ % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 100, 'online_capacity' => 100,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->type->id,
            'slot_id' => $slot->id,
            'quantity' => $quantity, 'seats' => $quantity, 'unit_price' => $unitPrice,
        ]);
    }
}
