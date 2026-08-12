<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Domain\Platform\Models\Setting;
use App\Filament\Widgets\DashboardStatsWidget;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 7.4 iter2 — `DashboardStatsWidget`: «Reservas» + «Ocupación», con el
 * filtro de periodo compartido (hoy / esta semana / este mes). Reloj fijado en
 * miércoles 2026-06-10 09:00 UTC (semana 08–14, mes 01–30) para deterministas.
 * (Antes el primer stat era «Sin preparar»; al retirar el sistema "preparado" se
 * reconvirtió a «Reservas» = total de principales pagados del periodo, #202.)
 */
class DashboardStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private Zone $jump;

    private TicketType $entry;

    private TicketType $addonType;

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
        $this->addonType = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 0, 'position' => 9,
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

    private function slotOn(string $date, string $start, string $end): Slot
    {
        return Slot::create([
            'zone_id' => $this->jump->id, 'date' => $date,
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

    private function item(Order $order, ?Slot $slot, int $seats, array $overrides = []): OrderItem
    {
        return OrderItem::create(array_merge([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->entry->id, 'slot_id' => $slot?->id,
            'quantity' => max(1, $seats), 'seats' => $seats, 'unit_price' => 1000,
        ], $overrides));
    }

    /**
     * @return array<int, Stat>
     */
    private function statsFor(?string $period): array
    {
        $widget = new DashboardStatsWidget;
        if ($period !== null) {
            $widget->pageFilters = ['period' => $period];
        }
        $method = new \ReflectionMethod(DashboardStatsWidget::class, 'getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    private function reservations(array $stats): string
    {
        return (string) $stats[0]->getValue();
    }

    private function occupancy(int $seats): string
    {
        return __('admin.dashboard.stats.occupancy_value', ['count' => $seats]);
    }

    // ─── Gate ────────────────────────────────────────────────────────────

    public function test_customer_cannot_view(): void
    {
        $this->actingAs($this->customer());
        $this->assertFalse(DashboardStatsWidget::canView());
    }

    public function test_staff_without_calendar_view_cannot_view(): void
    {
        $staff = $this->staff();
        $perm = Permission::where('name', 'calendar.view')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        $this->actingAs($staff);
        $this->assertFalse(DashboardStatsWidget::canView());
    }

    public function test_staff_with_permission_can_view(): void
    {
        $this->actingAs($this->staff());
        $this->assertTrue(DashboardStatsWidget::canView());
    }

    // ─── Periodo: conteo y suma ──────────────────────────────────────────

    public function test_counts_and_sums_per_period(): void
    {
        $order = $this->paidOrder('JJ-DST01');

        // Reservas = principales pagados, con franja y no cancelados, cuya franja
        // cae en el periodo (NO se filtra por futuro ni por estado: es el total).
        // A: hoy futuro, 4.
        $this->item($order, $this->slotOn('2026-06-10', '14:00:00', '15:00:00'), 4);
        // B: viernes (esta semana, futuro), 5.
        $this->item($order, $this->slotOn('2026-06-12', '10:00:00', '11:00:00'), 5);
        // C: este mes (no esta semana), 6.
        $this->item($order, $this->slotOn('2026-06-20', '10:00:00', '11:00:00'), 6);
        // D: lunes (esta semana, PASADO), 7.
        $this->item($order, $this->slotOn('2026-06-08', '10:00:00', '11:00:00'), 7);
        // E: hoy ya pasado (07–08 < 09), 3.
        $this->item($order, $this->slotOn('2026-06-10', '07:00:00', '08:00:00'), 3);
        // F: hoy futuro, 2.
        $this->item($order, $this->slotOn('2026-06-10', '16:00:00', '17:00:00'), 2);
        // G: hoy, principal con seats 0 (cuenta como reserva, no suma plazas).
        $principal = $this->item($order, $this->slotOn('2026-06-10', '12:00:00', '13:00:00'), 0);

        // Exclusiones (NO cuentan ni como reserva ni en ocupación):
        OrderItem::create([ // addon (parent + sin franja)
            'order_id' => $order->id, 'parent_item_id' => $principal->id,
            'ticket_type_id' => $this->addonType->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
        ]);
        $this->item($order, $this->slotOn('2026-06-10', '13:00:00', '14:00:00'), 9, ['cancelled_at' => now()]); // cancelado
        $nonPaid = Order::create([
            'user_id' => $this->customer()->id, 'code' => 'JJ-DSTPEND',
            'status' => Order::STATUS_PENDING, 'subtotal' => 1, 'tax' => 0, 'total' => 1, 'currency' => 'EUR',
        ]);
        $this->item($nonPaid, $this->slotOn('2026-06-10', '20:00:00', '21:00:00'), 8); // pedido no pagado
        $this->item($order, $this->slotOn('2026-07-05', '10:00:00', '11:00:00'), 10); // mes siguiente

        // HOY: reservas = A + E + F + G = 4; ocupación = 4+3+2+0 = 9.
        $today = $this->statsFor('today');
        $this->assertSame('4', $this->reservations($today));
        $this->assertSame($this->occupancy(9), $today[1]->getValue());

        // SEMANA: reservas = hoy(4) + D + B = 6; ocupación = 9 + B5 + D7 = 21.
        $week = $this->statsFor('week');
        $this->assertSame('6', $this->reservations($week));
        $this->assertSame($this->occupancy(21), $week[1]->getValue());

        // MES: reservas = semana(6) + C = 7; ocupación = 21 + C6 = 27.
        $month = $this->statsFor('month');
        $this->assertSame('7', $this->reservations($month));
        $this->assertSame($this->occupancy(27), $month[1]->getValue());
    }

    public function test_default_period_is_today(): void
    {
        $order = $this->paidOrder('JJ-DST02');
        $this->item($order, $this->slotOn('2026-06-10', '14:00:00', '15:00:00'), 4);
        $this->item($order, $this->slotOn('2026-06-12', '10:00:00', '11:00:00'), 5); // esta semana, no hoy

        $default = $this->statsFor(null); // sin filtro → HOY
        $this->assertSame('1', $this->reservations($default));
        $this->assertSame($this->occupancy(4), $default[1]->getValue());
    }

    public function test_empty_is_zero(): void
    {
        $stats = $this->statsFor('today');
        $this->assertSame('0', $this->reservations($stats));
        $this->assertSame($this->occupancy(0), $stats[1]->getValue());
    }
}
