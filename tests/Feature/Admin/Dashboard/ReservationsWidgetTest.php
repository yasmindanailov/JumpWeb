<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Filament\Widgets\ReservationsWidget;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.4 iter2 — `ReservationsWidget`: tabla «Reservas» con el filtro de
 * periodo compartido. Reloj fijado en miércoles 2026-06-10 09:00 UTC (semana
 * 08–14, mes 01–30). Cubre gate, ventana por periodo, exclusiones, orden,
 * columna de estado, etiqueta de invitados, enlace al pedido y zona sin color.
 */
class ReservationsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private Zone $jump;

    private TicketType $entry;

    private TicketType $pack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'UTC', 'group' => 'general']);
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00')); // miércoles

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

    private function slotOn(string $date, string $start, string $end, ?Zone $zone = null): Slot
    {
        return Slot::create([
            'zone_id' => ($zone ?? $this->jump)->id, 'date' => $date,
            'start_time' => $start, 'end_time' => $end,
            'capacity' => 50, 'online_capacity' => 50,
        ]);
    }

    private function paidOrder(string $code): Order
    {
        return Order::create([
            'user_id' => $this->customer()->id, 'code' => $code,
            'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'tax' => 210, 'total' => 1210,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
    }

    private function item(Order $order, TicketType $type, ?Slot $slot, array $overrides = []): OrderItem
    {
        return OrderItem::create(array_merge([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $type->id, 'slot_id' => $slot?->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ], $overrides));
    }

    /** @return array<string, OrderItem> reservas repartidas por periodos. */
    private function spread(): array
    {
        $order = $this->paidOrder('JJ-RES01');

        $set = [
            'todayPast' => $this->item($order, $this->entry, $this->slotOn('2026-06-10', '07:00:00', '08:00:00')),
            'todayMid' => $this->item($order, $this->entry, $this->slotOn('2026-06-10', '11:00:00', '12:00:00')),
            'todayLate' => $this->item($order, $this->entry, $this->slotOn('2026-06-10', '17:00:00', '18:00:00')),
            'weekFuture' => $this->item($order, $this->entry, $this->slotOn('2026-06-12', '10:00:00', '11:00:00')),
            'weekPast' => $this->item($order, $this->entry, $this->slotOn('2026-06-08', '10:00:00', '11:00:00')),
            'monthOnly' => $this->item($order, $this->entry, $this->slotOn('2026-06-20', '10:00:00', '11:00:00')),
            'nextMonth' => $this->item($order, $this->entry, $this->slotOn('2026-07-05', '10:00:00', '11:00:00')),
            'cancelled' => $this->item($order, $this->entry, $this->slotOn('2026-06-10', '12:00:00', '13:00:00'), ['cancelled_at' => now()]),
        ];

        $pending = Order::create([
            'user_id' => $this->customer()->id, 'code' => 'JJ-RESPEND',
            'status' => Order::STATUS_PENDING, 'subtotal' => 1, 'tax' => 0, 'total' => 1, 'currency' => 'EUR',
        ]);
        $set['nonPaid'] = $this->item($pending, $this->entry, $this->slotOn('2026-06-10', '19:00:00', '20:00:00'));

        // Addon: principal + complemento sin franja.
        $set['addon'] = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $set['todayMid']->id,
            'ticket_type_id' => $this->pack->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
        ]);

        return $set;
    }

    /** Ids resueltos por la query del widget para un periodo (vía reflexión). */
    private function idsFor(?string $period): Collection
    {
        $widget = new ReservationsWidget;
        if ($period !== null) {
            $widget->pageFilters = ['period' => $period];
        }
        $method = new \ReflectionMethod(ReservationsWidget::class, 'reservationsQuery');
        $method->setAccessible(true);

        return $method->invoke($widget)->get()->pluck('id');
    }

    // ─── Gate ────────────────────────────────────────────────────────────

    public function test_customer_cannot_view(): void
    {
        $this->actingAs($this->customer());
        $this->assertFalse(ReservationsWidget::canView());
    }

    public function test_staff_without_calendar_view_cannot_view(): void
    {
        $staff = $this->staff();
        $perm = Permission::where('name', 'calendar.view')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        $this->actingAs($staff);
        $this->assertFalse(ReservationsWidget::canView());
    }

    public function test_staff_with_permission_can_view(): void
    {
        $this->actingAs($this->staff());
        $this->assertTrue(ReservationsWidget::canView());
    }

    // ─── Default (HOY): día completo, exclusiones ────────────────────────

    public function test_default_today_shows_full_day_and_excludes_others(): void
    {
        $s = $this->spread();

        Livewire::actingAs($this->staff())
            ->test(ReservationsWidget::class)
            ->assertCanSeeTableRecords([$s['todayPast'], $s['todayMid'], $s['todayLate']])
            ->assertCanNotSeeTableRecords([
                $s['weekFuture'], $s['weekPast'], $s['monthOnly'], $s['nextMonth'],
                $s['cancelled'], $s['nonPaid'], $s['addon'],
            ]);
    }

    public function test_default_today_orders_by_time(): void
    {
        $s = $this->spread();

        Livewire::actingAs($this->staff())
            ->test(ReservationsWidget::class)
            ->assertCanSeeTableRecords([$s['todayPast'], $s['todayMid'], $s['todayLate']], inOrder: true);
    }

    // ─── Periodo: semana y mes (vía la query del widget) ─────────────────

    public function test_week_period_includes_this_week_only(): void
    {
        $s = $this->spread();
        $ids = $this->idsFor('week');

        foreach (['todayPast', 'todayMid', 'todayLate', 'weekFuture', 'weekPast'] as $key) {
            $this->assertContains($s[$key]->id, $ids, "se esperaba ver {$key} en la semana");
        }
        foreach (['monthOnly', 'nextMonth', 'cancelled', 'nonPaid', 'addon'] as $key) {
            $this->assertNotContains($s[$key]->id, $ids, "no se esperaba {$key} en la semana");
        }
    }

    public function test_month_period_includes_this_month_only(): void
    {
        $s = $this->spread();
        $ids = $this->idsFor('month');

        foreach (['todayPast', 'todayMid', 'todayLate', 'weekFuture', 'weekPast', 'monthOnly'] as $key) {
            $this->assertContains($s[$key]->id, $ids, "se esperaba ver {$key} en el mes");
        }
        foreach (['nextMonth', 'cancelled', 'nonPaid', 'addon'] as $key) {
            $this->assertNotContains($s[$key]->id, $ids, "no se esperaba {$key} en el mes");
        }
    }

    // ─── Columnas + interacción ──────────────────────────────────────────

    public function test_status_and_guest_label_render(): void
    {
        $order = $this->paidOrder('JJ-RES02');
        // Pack futuro con 8 invitados.
        $this->item($order, $this->pack, $this->slotOn('2026-06-10', '17:00:00', '19:00:00'), ['quantity' => 8, 'seats' => 8]);
        // Entrada futura.
        $this->item($order, $this->entry, $this->slotOn('2026-06-10', '16:00:00', '17:00:00'));

        Livewire::actingAs($this->staff())
            ->test(ReservationsWidget::class)
            ->assertSee(__('admin.dashboard.status.active'))
            ->assertSee(__('tickets.guests_count', ['count' => 8]));
    }

    public function test_guest_form_status_column_renders_for_packs_with_a_form(): void
    {
        // #227 punto 3: la tabla del escritorio muestra el estado del post-form (#217) de los packs
        // que lo piden — ✓ Enviado / ! Pendiente, como en el calendario.
        $formPack = TicketType::create([
            'name' => ['es' => 'Cumpleaños con form'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->jump->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 5,
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ],
        ]);
        $order = $this->paidOrder('JJ-FORM01');
        $item = $this->item($order, $formPack, $this->slotOn('2026-06-10', '17:00:00', '19:00:00'), ['quantity' => 2, 'seats' => 2]);

        // Sin guest_data → «Pendiente».
        Livewire::actingAs($this->staff())
            ->test(ReservationsWidget::class)
            ->assertSee(__('admin.dashboard.form_status.pending'))
            ->assertDontSee(__('admin.dashboard.form_status.ok'));

        // Datos de los 2 invitados → «Enviado».
        $item->update(['guest_data' => [['name' => 'Ana'], ['name' => 'Leo']]]);
        Livewire::actingAs($this->staff())
            ->test(ReservationsWidget::class)
            ->assertSee(__('admin.dashboard.form_status.ok'))
            ->assertDontSee(__('admin.dashboard.form_status.pending'));
    }

    public function test_entry_reservation_has_no_guest_form_status(): void
    {
        // Una entrada (sin post-form) no muestra ni Enviado ni Pendiente en la columna Formulario.
        $order = $this->paidOrder('JJ-ENTRY1');
        $item = $this->item($order, $this->entry, $this->slotOn('2026-06-10', '17:00:00', '18:00:00'));

        Livewire::actingAs($this->staff())
            ->test(ReservationsWidget::class)
            ->assertCanSeeTableRecords([$item])
            ->assertDontSee(__('admin.dashboard.form_status.pending'))
            ->assertDontSee(__('admin.dashboard.form_status.ok'));
    }

    public function test_row_links_to_order_view(): void
    {
        $order = $this->paidOrder('JJ-RES03');
        $this->item($order, $this->entry, $this->slotOn('2026-06-10', '17:00:00', '18:00:00'));

        Livewire::actingAs($this->staff())
            ->test(ReservationsWidget::class)
            ->assertSee('admin/orders/JJ-RES03');
    }

    public function test_zone_without_color_does_not_break(): void
    {
        $plainZone = Zone::create(['slug' => 'plain', 'name' => ['es' => 'PLANA'], 'color' => null]);
        $plainType = TicketType::create([
            'name' => ['es' => 'Plana'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $plainZone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 3,
        ]);
        $order = $this->paidOrder('JJ-RES04');
        $item = $this->item($order, $plainType, $this->slotOn('2026-06-10', '17:00:00', '18:00:00', $plainZone));

        Livewire::actingAs($this->staff())
            ->test(ReservationsWidget::class)
            ->assertCanSeeTableRecords([$item]);
    }

    public function test_product_icon_renders_as_svg_tinted_with_zone_color(): void
    {
        // Regresión (refinamiento clienta 2026-06-13): el icono de tipo se pinta como SVG NATIVO
        // tintado con el color exacto de la zona, sustituyendo al ColorColumn. El intento previo vía
        // `TextColumn->html()` fallaba en silencio: el saneador de Filament (`allowSafeElements()`)
        // elimina el `<svg>` → icono invisible. Esta aserción comprueba que el SVG llega al HTML.
        $order = $this->paidOrder('JJ-ICON1');
        $this->item($order, $this->entry, $this->slotOn('2026-06-10', '17:00:00', '18:00:00'));

        $html = Livewire::actingAs($this->staff())
            ->test(ReservationsWidget::class)
            ->html();

        $this->assertStringContainsString('<svg', $html, 'El <svg> del icono de tipo no aparece en el HTML');
        $this->assertStringContainsString('#FF5B22', $html, 'El color exacto de la zona jump no se aplicó al icono');
    }

    public function test_empty_state_when_no_reservations(): void
    {
        Livewire::actingAs($this->staff())
            ->test(ReservationsWidget::class)
            ->assertSee(__('admin.dashboard.reservations.empty'));
    }
}
