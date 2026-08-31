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
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sub-fase 7.2d (decisión #151) — Action Filament `viewOrderHistory` que
 * abre el modal con el audit log agregado del Order (entradas a nivel Order
 * + entradas de cualquier OrderItem del mismo Order, paginadas vía
 * `<x-filament::pagination>` y `WithPagination` trait).
 *
 * Cubre: query agregada con OR (Order + Items), no-leak cross-Order,
 * paginación nativa Filament, render del CTA en card Detalles, permiso
 * heredado de `orders.view`.
 */
class OrderAuditModalTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump1h;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create([
            'key' => RateType::KEY_NORMAL,
            'label' => ['es' => 'Normal'],
            'weekdays' => null,
            'priority' => 0,
        ]);
        $this->zone = Zone::create([
            'slug' => 'jump',
            'name' => ['es' => 'JUMP'],
            'accent' => 'jump',
            'color' => '#FF5B22',
            'position' => 1,
        ]);

        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump 1h'],
            'zone_id' => $this->zone->id,
            'duration_min' => 60,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 1,
        ]);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private int $slotCounter = 0;

    private function makeSlot(): Slot
    {
        $h = str_pad((string) ($this->slotCounter++ % 23), 2, '0', STR_PAD_LEFT);

        return Slot::create([
            'zone_id' => $this->zone->id,
            'date' => '2099-01-01',
            'start_time' => "{$h}:00:00",
            'end_time' => "{$h}:59:00",
            'capacity' => 10,
            'online_capacity' => 5,
        ]);
    }

    /**
     * @return array{0:Order,1:OrderItem}
     */
    private function makeOrderWithItem(): array
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-A'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'EUR',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $this->jump1h->id,
            'slot_id' => $this->makeSlot()->id,
            'quantity' => 1,
            'seats' => 1,
            'unit_price' => 1000,
        ]);

        return [$order, $item];
    }

    // ─── CTA en card Detalles ─────────────────────────────────────────────

    public function test_view_renders_audit_cta_with_mount_action(): void
    {
        [$order] = $this->makeOrderWithItem();

        $response = $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code);

        $response->assertOk();
        $response->assertSee('mountAction', false);
        $response->assertSee('viewOrderHistory', false);
        $response->assertSee('Ver historial completo', false);
    }

    // ─── Permiso del action ───────────────────────────────────────────────

    public function test_view_order_history_action_visible_for_staff_with_orders_view(): void
    {
        [$order] = $this->makeOrderWithItem();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('viewOrderHistory');
    }

    public function test_view_order_history_action_hidden_when_no_orders_view_permission(): void
    {
        [$order] = $this->makeOrderWithItem();

        // Usuario sin rol `staff`/`admin` no llega al panel — testeamos al
        // staff con el permiso `orders.view` revocado de su rol.
        $staff = $this->staff();
        $perm = Permission::where('name', 'orders.view')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        // Sin orders.view el panel rechaza la ruta — verificamos forbidden GET.
        $this->actingAs($staff)
            ->get('/admin/orders/'.$order->code)
            ->assertForbidden();
    }

    // ─── Query agregada (Order + Items) ───────────────────────────────────

    public function test_paginator_includes_both_order_level_and_item_level_entries(): void
    {
        [$order, $item] = $this->makeOrderWithItem();

        AuditLogger::log(
            action: 'orders.cancelled',
            target: $order,
            payload: ['order_code' => $order->code, 'previous_status' => 'paid'],
        );
        AuditLogger::log(
            action: 'order_items.event_data_updated',
            target: $item,
            payload: ['order_code' => $order->code, 'ticket_type_id' => $item->ticket_type_id],
        );

        $component = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code]);

        // Forzamos la computed property accediendo a su renderer.
        $paginator = $component->instance()->getOrderAuditPaginatorProperty();
        $this->assertSame(2, $paginator->total());

        $actions = $paginator->getCollection()->pluck('action')->all();
        $this->assertContains('orders.cancelled', $actions);
        $this->assertContains('order_items.event_data_updated', $actions);
    }

    public function test_paginator_does_not_leak_entries_from_other_orders(): void
    {
        [$orderA, $itemA] = $this->makeOrderWithItem();
        [$orderB, $itemB] = $this->makeOrderWithItem();

        AuditLogger::log(action: 'orders.cancelled', target: $orderB, payload: []);
        AuditLogger::log(action: 'order_items.event_data_updated', target: $itemB, payload: []);

        $component = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $orderA->code]);

        $paginator = $component->instance()->getOrderAuditPaginatorProperty();
        $this->assertSame(0, $paginator->total(), 'No debe leakear entradas del Order B en el A.');
    }

    public function test_paginator_does_not_leak_orderitem_entries_from_unrelated_items(): void
    {
        [$orderA] = $this->makeOrderWithItem();
        [, $itemFromB] = $this->makeOrderWithItem();

        // Entrada de un OrderItem que NO pertenece al Order A.
        AuditLogger::log(action: 'order_items.event_data_updated', target: $itemFromB, payload: []);

        $component = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $orderA->code]);

        $paginator = $component->instance()->getOrderAuditPaginatorProperty();
        $this->assertSame(0, $paginator->total());
    }

    // ─── Paginación nativa Filament ───────────────────────────────────────

    public function test_paginator_respects_per_page_property(): void
    {
        [$order, $item] = $this->makeOrderWithItem();

        // 13 entradas de OrderItem para que la paginación sea no-trivial: 13 / 10 = 2 páginas.
        for ($i = 0; $i < 13; $i++) {
            AuditLogger::log(
                action: 'order_items.event_data_updated',
                target: $item,
                payload: ['order_code' => $order->code, 'ticket_type_id' => $item->ticket_type_id, 'i' => $i],
            );
        }

        $component = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code]);

        // Default: 10 por página (T5 adenda 5, `[DECIDIDO owner]` — REVISA el 5 de #151bis: cada
        // gestión escribe 2–3 entradas y una sesión de ediciones no cabía en una página; el owner
        // leyó el historial como incompleto teniendo un «Siguiente» delante).
        $paginator = $component->instance()->getOrderAuditPaginatorProperty();
        $this->assertSame(10, $paginator->perPage());
        $this->assertSame(13, $paginator->total());
        $this->assertSame(2, $paginator->lastPage());

        // Cambio de page size via property pública (el 5 compacto sigue en el selector).
        $component->set('auditPerPage', 5);
        $paginator = $component->instance()->getOrderAuditPaginatorProperty();
        $this->assertSame(5, $paginator->perPage());
        $this->assertSame(3, $paginator->lastPage());
    }

    public function test_paginator_orders_entries_by_created_at_desc(): void
    {
        [$order, $item] = $this->makeOrderWithItem();

        $first = AuditLogger::log(action: 'order_items.event_data_updated', target: $item, payload: ['n' => 1]);
        $second = AuditLogger::log(action: 'order_items.event_data_blocked', target: $item, payload: ['n' => 2]);
        $third = AuditLogger::log(action: 'orders.cancelled', target: $order, payload: ['n' => 3]);

        $component = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code]);

        $paginator = $component->instance()->getOrderAuditPaginatorProperty();
        $ordered = $paginator->getCollection()->pluck('id')->all();

        // Tiebreaker es created_at desc, id desc → los IDs deben venir en orden
        // decreciente (el último log creado primero).
        $this->assertSame($third->id, $ordered[0]);
        $this->assertSame($second->id, $ordered[1]);
        $this->assertSame($first->id, $ordered[2]);
    }

    // ─── Estados del action montado ───────────────────────────────────────

    public function test_mount_action_marks_view_order_history_as_mounted(): void
    {
        [$order] = $this->makeOrderWithItem();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('viewOrderHistory')
            ->assertActionMounted('viewOrderHistory');
    }

    public function test_paginator_is_empty_when_no_audit_entries(): void
    {
        [$order] = $this->makeOrderWithItem();

        $component = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code]);

        $paginator = $component->instance()->getOrderAuditPaginatorProperty();

        $this->assertSame(0, $paginator->total());
        $this->assertTrue($paginator->isEmpty());
    }

    public function test_paginator_loads_related_item_with_ticket_type_for_orderitem_entries(): void
    {
        // Decisión #151bis: las entradas de OrderItem incluyen el nombre del
        // producto y el slot en el modal. Verificamos que el render encuentra
        // el ticket_type via `$order->items->firstWhere('id', $entry->target_id)`.
        [$order, $item] = $this->makeOrderWithItem();

        AuditLogger::log(
            action: 'order_items.event_data_updated',
            target: $item,
            payload: ['order_code' => $order->code, 'ticket_type_id' => $item->ticket_type_id],
        );

        $component = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code]);

        $paginator = $component->instance()->getOrderAuditPaginatorProperty();
        $entry = $paginator->getCollection()->first();

        $this->assertSame($item->id, $entry->target_id);
        $this->assertSame((new OrderItem)->getMorphClass(), $entry->target_type);

        // El blade busca el item en el record con `$order->items->firstWhere('id', target_id)`.
        // Verificamos que el lookup resuelve.
        $order->loadMissing(['items.ticketType.zone', 'items.slot']);
        $relatedItem = $order->items->firstWhere('id', $entry->target_id);

        $this->assertNotNull($relatedItem);
        $this->assertSame('Jump 1h', $relatedItem->ticketType->tr('name'));
    }
}
