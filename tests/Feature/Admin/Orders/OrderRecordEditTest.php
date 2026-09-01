<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **`Order::recordEdit` — el hecho de una gestión** (T1 del libro, `specs/desglose-libro.md` §4.2).
 *
 * Nació como `OrderApplyExtraDueTest` (sub-fase 7.2e del origen): entonces solo existía la
 * SUBIDA, y una bajada se escribía en cascada por otros dos métodos. Desde la T1 los dos sentidos
 * son la misma escritura con el signo del delta, y aquí se verifica el contrato del hecho:
 *  - fila `order_adjustments` de tipo `edit` con importe (con signo), contexto, autor y moneda;
 *  - audit `orders.extra_due_applied` (subida) / `orders.value_reduction_applied` (bajada);
 *  - las gestiones ACUMULAN (histórico inmutable, no upsert);
 *  - bloqueos: delta 0 → InvalidArgumentException; item ajeno → DomainException.
 * La LECTURA de esos hechos (los cubos de puerta, el nacimiento) vive en `EditMovementTest`.
 */
class OrderRecordEditTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_a_raise_creates_an_edit_row_with_correct_fields(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $adjustment = $order->recordEdit(
            item: $item,
            deltaCents: 1200,
            by: $by,
            reason: 'cantidad 3 → 5',
            context: ['quantity_change' => ['old' => 3, 'new' => 5]],
        );

        $this->assertInstanceOf(OrderAdjustment::class, $adjustment);
        $this->assertSame($order->id, (int) $adjustment->order_id);
        $this->assertSame($item->id, (int) $adjustment->order_item_id);
        $this->assertSame(OrderAdjustment::TYPE_EDIT, $adjustment->type);
        $this->assertSame(1200, (int) $adjustment->amount_cents);
        $this->assertSame('EUR', $adjustment->currency);
        $this->assertSame('cantidad 3 → 5', $adjustment->reason);
        $this->assertSame(['quantity_change' => ['old' => 3, 'new' => 5]], $adjustment->context);
        $this->assertSame($by->id, (int) $adjustment->applied_by);
    }

    public function test_a_raise_writes_the_charge_audit_entry(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $adjustment = $order->recordEdit($item, 800, $by, 'addon nuevo');

        $log = AuditLog::where('action', 'orders.extra_due_applied')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($order->code, $log->payload['order_code']);
        $this->assertSame($item->id, $log->payload['order_item_id']);
        $this->assertSame(800, $log->payload['amount_cents']);
        $this->assertSame('addon nuevo', $log->payload['reason']);
        $this->assertSame($adjustment->id, $log->payload['adjustment_id']);
        $this->assertNull(AuditLog::where('action', 'orders.value_reduction_applied')->first());
    }

    /**
     * Una BAJADA es el mismo hecho con el signo cambiado: una fila `edit` NEGATIVA con su delta
     * entero, y su propia entrada de historial. (Hasta la T1 un delta negativo se rechazaba aquí y
     * la bajada se repartía en créditos por otros dos métodos.)
     */
    public function test_a_reduction_is_the_same_fact_with_a_negative_delta_and_its_own_audit_entry(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $adjustment = $order->recordEdit($item, -400, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 1]]]);

        $this->assertSame(OrderAdjustment::TYPE_EDIT, $adjustment->type);
        $this->assertSame(-400, (int) $adjustment->amount_cents, 'se persiste CON signo, sin negar ni marcar');

        $log = AuditLog::where('action', 'orders.value_reduction_applied')->latest()->first();
        $this->assertNotNull($log, 'la bajada tiene su propia acción de historial');
        $this->assertSame(-400, $log->payload['amount_cents']);
        $this->assertSame($adjustment->id, $log->payload['adjustment_id']);
        $this->assertNull(AuditLog::where('action', 'orders.extra_due_applied')->first(), 'y no la del cargo');
    }

    public function test_multiple_edits_accumulate_as_immutable_history(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $order->recordEdit($item, 500, $by, 'cambio 1');
        $order->recordEdit($item, 700, $by, 'cambio 2');
        $order->recordEdit($item, -300, $by, 'cambio 3');

        $this->assertSame(3, $order->adjustments()->count(), 'tres gestiones, tres hechos: nada se sobrescribe');
        $this->assertSame(900, (int) $order->adjustments()->sum('amount_cents'));
    }

    public function test_a_zero_delta_is_rejected(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $this->expectException(\InvalidArgumentException::class);
        $order->recordEdit($item, 0, $by);
    }

    public function test_an_item_from_another_order_is_rejected(): void
    {
        $by = User::factory()->create();
        $orderA = $this->makePaidOrder('JJ-OWN0001');
        $orderB = $this->makePaidOrder('JJ-OWN0002');
        $itemOfB = $this->attachActiveItem($orderB);

        $this->expectException(\DomainException::class);
        // Anti-IDOR: el orquestador rechaza items que no pertenecen al Order.
        $orderA->recordEdit($itemOfB, 500, $by);
    }

    public function test_an_empty_context_is_persisted_as_null(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $adjustment = $order->recordEdit($item, 200, $by, 'sin contexto', []);

        // context vacío se persiste como null (la columna admite null, evita
        // serializaciones JSON "[]" inútiles que confundirían lecturas).
        $this->assertNull($adjustment->context);
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

    private function makePaidOrder(string $code = 'JJ-ED0001'): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => $code,
            'status' => Order::STATUS_PAID,
            'subtotal' => 2000, 'tax' => 0, 'total' => 2000, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function attachActiveItem(Order $order): OrderItem
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
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
    }
}
