<?php

namespace Tests\Feature\Support;

use App\Domain\Identity\Models\User;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\LegacyGateAdjustmentReconciliation;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reparación de datos legacy: netea los cargos de puerta `extra_due` fantasma
 * (subir+bajar dejaba filas append-only sin acreditar). Verifica:
 *  - caso qty-only EXACTO (reproduce JJ-WIMWJW item Jump): reconstruye el
 *    baseline online desde `quantity_change.old` → crédito exacto;
 *  - item sano (neto ≤ cargado) NO se toca;
 *  - cambio de producto → cota conservadora (neto = cargado, ambiguo);
 *  - idempotente (2.ª ejecución = no-op);
 *  - no-op en BD limpia.
 */
class LegacyGateAdjustmentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private TicketType $otherType;

    private User $actor;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0,
        ]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->jumpType = TicketType::create([
            'name' => ['es' => 'Jump · Ilimitada'], 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->otherType = TicketType::create([
            'name' => ['es' => 'Jump 2h'], 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 2,
        ]);
        $this->actor = User::factory()->create();
    }

    public function test_qty_only_phantom_is_credited_exactly(): void
    {
        // Reproduce JJ-WIMWJW item#101: qty original 2 (pagado online), subió y
        // bajó dejando 3 cargos +22,90 y la qty final en 1.
        $order = $this->makeOrder(total: 4580);
        $item = $this->makeItem($order, qty: 1, unit: 2290);
        $this->legacyCharge($order, $item, 2290, oldQty: 2, newQty: 3, minutesAgo: 30);
        $this->legacyCharge($order, $item, 2290, oldQty: 1, newQty: 2, minutesAgo: 20);
        $this->legacyCharge($order, $item, 2290, oldQty: 1, newQty: 2, minutesAgo: 10);

        $this->assertSame(6870, $order->fresh()->load('adjustments')->itemExtraDueCents($item)); // roto

        $result = LegacyGateAdjustmentReconciliation::run();

        $this->assertSame(['credited' => 1, 'exact' => 1, 'ambiguous' => 0], $result);

        $order->refresh()->load('adjustments');
        $this->assertSame(0, $order->itemExtraDueCents($item)); // neteado a 0 (baseline 2×2290 ≥ cargado)
        $credit = $order->adjustments->firstWhere('reason', 'legacy_gate_reconciliation');
        $this->assertNotNull($credit);
        $this->assertSame(-6870, (int) $credit->amount_cents);
        $this->assertFalse($credit->context['legacy_gate_reconciliation']['ambiguous']);
    }

    public function test_healthy_item_is_left_untouched(): void
    {
        // Neto (7200 = +4 invitados) ≤ cargado (5 × 1800 = 9000) → sano, no se toca.
        $order = $this->makeOrder(total: 9000);
        $item = $this->makeItem($order, qty: 5, unit: 1800);
        $this->legacyCharge($order, $item, 7200, oldQty: 1, newQty: 5, minutesAgo: 10);

        $result = LegacyGateAdjustmentReconciliation::run();

        $this->assertSame(0, $result['credited']);
        $this->assertNull($order->fresh()->load('adjustments')->adjustments->firstWhere('reason', 'legacy_gate_reconciliation'));
        $this->assertSame(7200, $order->itemExtraDueCents($item));
    }

    public function test_product_change_history_uses_conservative_cap(): void
    {
        // Hubo cambio de producto → baseline online ambiguo → cota = cargado.
        $order = $this->makeOrder(total: 1200);
        $item = $this->makeItem($order, qty: 1, unit: 2000); // cargado 2000
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_EXTRA_DUE, 'amount_cents' => 5000, // > cargado → roto
            'currency' => 'EUR', 'reason' => 'item_edit',
            'context' => ['changes' => ['product_change' => ['old' => 'Jump 1h', 'new' => 'Jump 2h']]],
            'applied_by' => $this->actor->id,
        ]);

        $result = LegacyGateAdjustmentReconciliation::run();

        $this->assertSame(['credited' => 1, 'exact' => 0, 'ambiguous' => 1], $result);
        $order->refresh()->load('adjustments');
        $this->assertSame(2000, $order->itemExtraDueCents($item)); // capado al cargado
        $credit = $order->adjustments->firstWhere('reason', 'legacy_gate_reconciliation');
        $this->assertSame(-3000, (int) $credit->amount_cents);     // 5000 − 2000
        $this->assertTrue($credit->context['legacy_gate_reconciliation']['ambiguous']);
    }

    public function test_is_idempotent(): void
    {
        $order = $this->makeOrder(total: 4580);
        $item = $this->makeItem($order, qty: 1, unit: 2290);
        $this->legacyCharge($order, $item, 2290, oldQty: 2, newQty: 3, minutesAgo: 30);
        $this->legacyCharge($order, $item, 2290, oldQty: 1, newQty: 2, minutesAgo: 20);
        $this->legacyCharge($order, $item, 2290, oldQty: 1, newQty: 2, minutesAgo: 10);

        $first = LegacyGateAdjustmentReconciliation::run();
        $second = LegacyGateAdjustmentReconciliation::run();

        $this->assertSame(1, $first['credited']);
        $this->assertSame(0, $second['credited']); // ya saneado → no-op
        $this->assertSame(1, $order->fresh()->adjustments()->where('reason', 'legacy_gate_reconciliation')->count());
    }

    public function test_is_noop_on_clean_database(): void
    {
        $this->assertSame(['credited' => 0, 'exact' => 0, 'ambiguous' => 0], LegacyGateAdjustmentReconciliation::run());
    }

    private function makeOrder(int $total): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-LR'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function makeItem(Order $order, int $qty, int $unit): OrderItem
    {
        $h = str_pad((string) ($this->counter % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 50, 'online_capacity' => 50,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id, 'slot_id' => $slot->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);
    }

    private function legacyCharge(Order $order, OrderItem $item, int $amount, int $oldQty, int $newQty, int $minutesAgo): void
    {
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_EXTRA_DUE, 'amount_cents' => $amount,
            'currency' => 'EUR', 'reason' => 'item_edit',
            'context' => ['changes' => ['quantity_change' => ['old' => $oldQty, 'new' => $newQty]]],
            'applied_by' => $this->actor->id,
            'created_at' => now()->subMinutes($minutesAgo),
            'updated_at' => now()->subMinutes($minutesAgo),
        ]);
    }
}
