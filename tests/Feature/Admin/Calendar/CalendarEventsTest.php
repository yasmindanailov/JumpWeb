<?php

namespace Tests\Feature\Admin\Calendar;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\Duration;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 7.4 — feed JSON del calendario unificado (`admin.calendario.eventos`).
 *
 * Cubre: autorización (`calendar.view`), el mapeo de un order_item a evento de
 * FullCalendar, las reglas de inclusión (principal + pagado + activo + en rango),
 * el filtro entradas/packs, el color por zona y el estado operativo.
 */
class CalendarEventsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin/calendario/eventos';

    private Zone $jump;

    private TicketType $entry;

    private TicketType $pack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'color' => '#FF5B22']);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1 hora'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->jump->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->jump->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado']],
                ['key' => 'age', 'type' => 'number', 'required' => false, 'label' => ['es' => 'Edad']],
            ],
        ]);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function customer(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    private function slotOn(string $date, Zone $zone, string $start = '10:00:00', string $end = '11:00:00'): Slot
    {
        return Slot::create([
            'zone_id' => $zone->id, 'date' => $date,
            'start_time' => $start, 'end_time' => $end,
            'capacity' => 20, 'online_capacity' => 20,
        ]);
    }

    private function paidOrder(string $code = 'JJ-CAL001'): Order
    {
        return Order::create([
            'user_id' => $this->customer()->id, 'code' => $code,
            'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'tax' => 210, 'total' => 1210,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
    }

    private function item(Order $order, TicketType $type, Slot $slot, array $overrides = []): OrderItem
    {
        return OrderItem::create(array_merge([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ], $overrides));
    }

    /** @return array{0:string,1:string} rango [start,end] que abarca $date */
    private function rangeAround(string $date): array
    {
        $d = Carbon::parse($date);

        return [$d->copy()->subDay()->toDateString(), $d->copy()->addDay()->toDateString()];
    }

    // ─── Autorización ─────────────────────────────────────────────────────

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_customer_is_forbidden(): void
    {
        $this->actingAs($this->customer())->getJson(self::URL)->assertForbidden();
    }

    public function test_staff_without_calendar_view_is_forbidden(): void
    {
        $staff = $this->staff();
        $perm = Permission::where('name', 'calendar.view')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        $this->actingAs($staff)->getJson(self::URL)->assertForbidden();
    }

    public function test_staff_with_calendar_view_can_read(): void
    {
        $this->actingAs($this->staff())->getJson(self::URL)->assertOk();
    }

    // ─── Mapeo y reglas de inclusión ──────────────────────────────────────

    public function test_paid_entry_item_maps_to_event(): void
    {
        $date = Carbon::today()->addDays(3)->toDateString();
        $slot = $this->slotOn($date, $this->jump, '10:00:00', '11:00:00');
        $entryItem = $this->item($this->paidOrder(), $this->entry, $slot);
        [$start, $end] = $this->rangeAround($date);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', (string) $entryItem->id)
            ->assertJsonPath('0.title', 'Entrada 1 hora')
            ->assertJsonPath('0.start', $date.'T10:00:00')
            // duración 60 min → fin 11:00.
            ->assertJsonPath('0.end', $date.'T11:00:00')
            // El color de la zona va en extendedProps (línea lateral), no como
            // fondo del evento (el fondo lo decide el estado operativo).
            ->assertJsonPath('0.extendedProps.zoneColor', '#FF5B22')
            ->assertJsonPath('0.extendedProps.orderCode', 'JJ-CAL001');
    }

    public function test_addons_are_excluded(): void
    {
        $date = Carbon::today()->addDays(3)->toDateString();
        $slot = $this->slotOn($date, $this->jump);
        $order = $this->paidOrder();
        $principal = $this->item($order, $this->entry, $slot);

        // Complemento: cuelga del principal, sin franja propia.
        $addonType = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 0, 'position' => 9,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $principal->id,
            'ticket_type_id' => $addonType->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
        ]);

        [$start, $end] = $this->rangeAround($date);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonCount(1) // solo el principal
            ->assertJsonPath('0.id', (string) $principal->id);
    }

    public function test_cancelled_items_are_excluded(): void
    {
        $date = Carbon::today()->addDays(3)->toDateString();
        $slot = $this->slotOn($date, $this->jump);
        $this->item($this->paidOrder(), $this->entry, $slot, ['cancelled_at' => now()]);
        [$start, $end] = $this->rangeAround($date);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_items_from_non_paid_orders_are_excluded(): void
    {
        $date = Carbon::today()->addDays(3)->toDateString();
        $slot = $this->slotOn($date, $this->jump);
        $pending = Order::create([
            'user_id' => $this->customer()->id, 'code' => 'JJ-PEND01',
            'status' => Order::STATUS_PENDING, 'subtotal' => 1000, 'tax' => 210, 'total' => 1210,
            'currency' => 'EUR',
        ]);
        $this->item($pending, $this->entry, $slot);
        [$start, $end] = $this->rangeAround($date);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_range_scoping_excludes_items_outside_window(): void
    {
        $inside = Carbon::today()->addDays(3)->toDateString();
        $outside = Carbon::today()->addDays(40)->toDateString();
        $order = $this->paidOrder();
        $this->item($order, $this->entry, $this->slotOn($inside, $this->jump));
        $this->item($order, $this->entry, $this->slotOn($outside, $this->jump), ['unit_price' => 1000]);

        [$start, $end] = $this->rangeAround($inside);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.start', $inside.'T10:00:00');
    }

    public function test_type_filter_returns_only_requested_type(): void
    {
        $date = Carbon::today()->addDays(3)->toDateString();
        $order = $this->paidOrder();
        $this->item($order, $this->entry, $this->slotOn($date, $this->jump, '10:00:00', '11:00:00'));
        $this->item($order, $this->pack, $this->slotOn($date, $this->jump, '17:00:00', '19:00:00'), ['unit_price' => 12000, 'event_data' => ['celebrant' => 'Lucía']]);

        [$start, $end] = $this->rangeAround($date);
        $base = self::URL."?start={$start}&end={$end}";

        $this->actingAs($this->staff())->getJson($base.'&type=entry')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Entrada 1 hora');

        $this->actingAs($this->staff())->getJson($base.'&type=pack')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Cumpleaños Jump');

        // Sin filtro (o all) → ambos.
        $this->actingAs($this->staff())->getJson($base)
            ->assertOk()->assertJsonCount(2);
    }

    public function test_title_is_product_name_without_celebrant(): void
    {
        // El homenajeado NO va en el título (vive en el modal); el título es el
        // nombre del producto a secas.
        $date = Carbon::today()->addDays(3)->toDateString();
        $slot = $this->slotOn($date, $this->jump, '17:00:00', '19:00:00');
        $this->item($this->paidOrder(), $this->pack, $slot, ['unit_price' => 12000, 'event_data' => ['celebrant' => 'Mateo', 'age' => '8']]);
        [$start, $end] = $this->rangeAround($date);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonPath('0.title', 'Cumpleaños Jump');
    }

    public function test_event_includes_customer_first_name_only(): void
    {
        $date = Carbon::today()->addDays(3)->toDateString();
        $slot = $this->slotOn($date, $this->jump, '10:00:00', '11:00:00');

        $customer = User::factory()->create(['name' => 'Ana Pérez García']);
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);
        $order = Order::create([
            'user_id' => $customer->id, 'code' => 'JJ-CALNAME',
            'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'tax' => 210, 'total' => 1210,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $this->item($order, $this->entry, $slot);
        [$start, $end] = $this->rangeAround($date);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonPath('0.extendedProps.customerFirstName', 'Ana');
    }

    public function test_feed_is_served_with_no_store(): void
    {
        // `no-store` (auditoría Fase 1, Sistema 5): el feed emite nombres de pila + códigos de pedido
        // y es un controlador que devuelve `response()->json()` (no Livewire) → Symfony solo pone
        // `no-cache, private`; debe llevar el alias explícito como sus hermanas con PII (slip/resumen).
        $date = Carbon::today()->addDays(3)->toDateString();
        [$start, $end] = $this->rangeAround($date);

        $response = $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}");

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_zone_color_with_neutral_fallback(): void
    {
        $kids = Zone::create(['slug' => 'kids', 'name' => ['es' => 'KIDS'], 'color' => '#C6FF3A']);
        $noColor = Zone::create(['slug' => 'plain', 'name' => ['es' => 'PLANA'], 'color' => null]);

        $kidsType = TicketType::create([
            'name' => ['es' => 'Kids'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $kids->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 3,
        ]);
        $plainType = TicketType::create([
            'name' => ['es' => 'Plana'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $noColor->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 4,
        ]);

        $date = Carbon::today()->addDays(3)->toDateString();
        $order = $this->paidOrder();
        $this->item($order, $kidsType, $this->slotOn($date, $kids, '10:00:00', '11:00:00'));
        $this->item($order, $plainType, $this->slotOn($date, $noColor, '12:00:00', '13:00:00'));
        [$start, $end] = $this->rangeAround($date);

        $response = $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()->assertJsonCount(2);

        $colors = collect($response->json())->pluck('extendedProps.zoneColor')->all();
        $this->assertContains('#C6FF3A', $colors);   // kids
        $this->assertContains('#9CA3AF', $colors);   // fallback neutro
    }

    public function test_operative_status_classnames(): void
    {
        // El estado operativo del feed deriva de si la franja ya pasó: activo
        // (futuro) → fc-event--active; finalizado (pasado) → fc-event--finished.
        $order = $this->paidOrder();
        $activeItem = $this->item($order, $this->entry, $this->slotOn(Carbon::today()->addDays(3)->toDateString(), $this->jump, '10:00:00', '11:00:00'));
        $finishedItem = $this->item($order, $this->entry, $this->slotOn(Carbon::today()->subDays(3)->toDateString(), $this->jump, '12:00:00', '13:00:00'));

        $start = Carbon::today()->subDays(7)->toDateString();
        $end = Carbon::today()->addDays(7)->toDateString();

        $events = collect(
            $this->actingAs($this->staff())
                ->getJson(self::URL."?start={$start}&end={$end}")
                ->assertOk()->json()
        )->keyBy('id');

        $this->assertSame('active', $events[(string) $activeItem->id]['extendedProps']['operativeStatus']);
        $this->assertContains('fc-event--active', $events[(string) $activeItem->id]['classNames']);
        $this->assertSame('finished', $events[(string) $finishedItem->id]['extendedProps']['operativeStatus']);
        $this->assertContains('fc-event--finished', $events[(string) $finishedItem->id]['classNames']);
    }

    public function test_past_slot_item_is_finished(): void
    {
        $date = Carbon::today()->subDays(2)->toDateString();
        $slot = $this->slotOn($date, $this->jump, '10:00:00', '11:00:00');
        $this->item($this->paidOrder(), $this->entry, $slot);
        [$start, $end] = $this->rangeAround($date);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonPath('0.extendedProps.operativeStatus', 'finished')
            ->assertJsonPath('0.classNames.0', 'fc-event--finished');
    }

    // ─── Meta del card (punto 3: invitados / cantidad + duración) ─────────

    public function test_entry_meta_shows_quantity_and_duration(): void
    {
        $date = Carbon::today()->addDays(3)->toDateString();
        $slot = $this->slotOn($date, $this->jump, '10:00:00', '11:00:00');
        $this->item($this->paidOrder(), $this->entry, $slot, ['quantity' => 4]);
        [$start, $end] = $this->rangeAround($date);

        // "4 entradas · 1h" (locale-agnóstico: se construye igual que el feed).
        $expected = '4 '.__('admin.orders.item_detail.unit_entries').' · '.Duration::formatHumane(60);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonPath('0.extendedProps.meta', $expected);
    }

    public function test_pack_meta_shows_guest_count(): void
    {
        $date = Carbon::today()->addDays(3)->toDateString();
        $slot = $this->slotOn($date, $this->jump, '17:00:00', '19:00:00');
        $this->item($this->paidOrder(), $this->pack, $slot, [
            'quantity' => 8, 'seats' => 8, 'unit_price' => 12000, 'event_data' => ['celebrant' => 'Lucía'],
        ]);
        [$start, $end] = $this->rangeAround($date);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonPath('0.extendedProps.meta', __('tickets.guests_count', ['count' => 8]));
    }

    public function test_end_falls_back_to_slot_end_when_duration_is_null(): void
    {
        $unlimited = TicketType::create([
            'name' => ['es' => 'Entrada libre'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->jump->id,
            'duration_min' => null, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 5,
        ]);
        $date = Carbon::today()->addDays(3)->toDateString();
        $slot = $this->slotOn($date, $this->jump, '10:00:00', '14:30:00');
        $this->item($this->paidOrder(), $unlimited, $slot);
        [$start, $end] = $this->rangeAround($date);

        $this->actingAs($this->staff())
            ->getJson(self::URL."?start={$start}&end={$end}")
            ->assertOk()
            ->assertJsonPath('0.end', $date.'T14:30:00');
    }
}
