<?php

namespace Tests\Feature\Admin\Calendar;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\DailyReservationsSummary;
use App\Domain\Booking\Services\ReservationSlip;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Pages\CalendarPage;
use App\Filament\Pages\Dashboard;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Decisión #184 — Resumen del día (PDF A4 horizontal) imprimible desde el
 * Calendario y el Escritorio.
 *
 * Cubre el presenter (qué entra/sale, filtro de tipo, totales, orden por hora),
 * la ruta segura (acceso/permiso `calendar.view`, locale ES forzado, audit,
 * ausencia de PII de cobro) y la acción de cabecera en ambas páginas.
 */
class DailySummaryTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2099-06-20';

    private Zone $zoneJump;

    private TicketType $entry;

    private TicketType $pack;

    private TicketType $addon;

    private int $slotCounter = 0;

    private int $ownerCounter = 0;

    private int $paymentCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);

        $this->zoneJump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Zona Jump'], 'color' => '#FF5B22']);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1 hora'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zoneJump->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zoneJump->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado']],
                ['key' => 'age', 'type' => 'number', 'required' => false, 'label' => ['es' => 'Edad']],
            ],
        ]);
        $this->addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'zone_id' => null, 'duration_min' => null,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 3,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function staff(): User
    {
        return $this->userWithRole('staff');
    }

    private function makeSlot(string $date = self::DAY, string $start = '12:00:00', string $end = '13:00:00'): Slot
    {
        // Unicidad (zone_id, date, start_time): desplazar la hora por contador
        // cuando se pide la misma de base no es necesario porque los tests pasan
        // horas explícitas; el contador solo evita choques con el default.
        $this->slotCounter++;

        return Slot::create([
            'zone_id' => $this->zoneJump->id, 'date' => $date,
            'start_time' => $start, 'end_time' => $end,
            'capacity' => 50, 'online_capacity' => 50,
        ]);
    }

    private function makeOrder(string $status = Order::STATUS_PAID): Order
    {
        $owner = User::factory()->create([
            'name' => 'Ana Pérez', 'phone' => '612345678',
            'email' => 'ana'.(++$this->ownerCounter).'@example.test',
        ]);

        $attrs = [
            'user_id' => $owner->id,
            'code' => 'JJ-T'.bin2hex(random_bytes(2)),
            'status' => $status,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
        ];
        if ($status === Order::STATUS_PAID) {
            $attrs['paid_at'] = now();
        }

        return Order::create($attrs);
    }

    private function makeItem(Order $order, TicketType $type, ?Slot $slot, array $attrs = []): OrderItem
    {
        return OrderItem::create(array_merge([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $type->id, 'slot_id' => $slot?->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ], $attrs));
    }

    // ─── Presenter: qué entra / qué sale ──────────────────────────────────────

    public function test_includes_only_paid_principal_items_of_the_day(): void
    {
        $orderA = $this->makeOrder();
        $packItem = $this->makeItem($orderA, $this->pack, $this->makeSlot(self::DAY, '16:00:00', '18:00:00'), [
            'quantity' => 8, 'seats' => 8, 'event_data' => ['celebrant' => 'Lucía'],
        ]);
        $entryItem = $this->makeItem($this->makeOrder(), $this->entry, $this->makeSlot(self::DAY, '10:00:00', '11:00:00'), [
            'quantity' => 3, 'seats' => 3,
        ]);

        // Ruido que NO debe aparecer:
        OrderItem::create([ // addon (tiene parent) del pack
            'order_id' => $orderA->id, 'parent_item_id' => $packItem->id,
            'ticket_type_id' => $this->addon->id, 'slot_id' => null, 'quantity' => 8, 'seats' => 0, 'unit_price' => 200,
        ]);
        $this->makeItem($this->makeOrder(), $this->pack, $this->makeSlot(self::DAY, '12:00:00', '14:00:00'))
            ->markCancelled($this->staff()); // cancelado
        $this->makeItem($this->makeOrder(Order::STATUS_PENDING), $this->entry, $this->makeSlot(self::DAY, '09:00:00', '10:00:00')); // no pagado
        $this->makeItem($this->makeOrder(), $this->entry, $this->makeSlot('2099-06-21', '10:00:00', '11:00:00')); // otro día

        $summary = DailyReservationsSummary::for(self::DAY);

        $this->assertSame(2, $summary->count());
        $ids = $summary->items->pluck('id')->all();
        $this->assertContains($packItem->id, $ids);
        $this->assertContains($entryItem->id, $ids);
    }

    public function test_type_filter(): void
    {
        $this->makeItem($this->makeOrder(), $this->pack, $this->makeSlot(self::DAY, '16:00:00', '18:00:00'), ['quantity' => 8, 'seats' => 8]);
        $this->makeItem($this->makeOrder(), $this->entry, $this->makeSlot(self::DAY, '10:00:00', '11:00:00'), ['quantity' => 3, 'seats' => 3]);

        $this->assertSame(2, DailyReservationsSummary::for(self::DAY, 'all')->count());
        $this->assertSame(1, DailyReservationsSummary::for(self::DAY, 'pack')->count());
        $this->assertSame(1, DailyReservationsSummary::for(self::DAY, 'entry')->count());
        // Tipo inválido → todas (no destructivo).
        $this->assertSame(2, DailyReservationsSummary::for(self::DAY, 'bogus')->count());
    }

    public function test_totals(): void
    {
        $this->makeItem($this->makeOrder(), $this->pack, $this->makeSlot(self::DAY, '16:00:00', '18:00:00'), ['quantity' => 8, 'seats' => 8]);
        $this->makeItem($this->makeOrder(), $this->pack, $this->makeSlot(self::DAY, '19:00:00', '21:00:00'), ['quantity' => 16, 'seats' => 16]);
        $this->makeItem($this->makeOrder(), $this->entry, $this->makeSlot(self::DAY, '10:00:00', '11:00:00'), ['quantity' => 3, 'seats' => 3]);

        $summary = DailyReservationsSummary::for(self::DAY);

        $this->assertSame(3, $summary->count());
        $this->assertSame(24, $summary->guestsTotal());
        $this->assertSame(3, $summary->entriesTotal());
        $this->assertFalse($summary->isEmpty());
    }

    public function test_rows_sorted_by_start_time(): void
    {
        $this->makeItem($this->makeOrder(), $this->pack, $this->makeSlot(self::DAY, '18:00:00', '20:00:00'), ['quantity' => 8, 'seats' => 8]);
        $this->makeItem($this->makeOrder(), $this->entry, $this->makeSlot(self::DAY, '09:00:00', '10:00:00'), ['quantity' => 2, 'seats' => 2]);
        $this->makeItem($this->makeOrder(), $this->pack, $this->makeSlot(self::DAY, '13:00:00', '15:00:00'), ['quantity' => 8, 'seats' => 8]);

        $rows = DailyReservationsSummary::for(self::DAY)->rows();

        // La ventana mostrada es entrada → entrada + duración (entrada 60' / packs 120'),
        // ordenada por hora de entrada.
        $this->assertSame(['09:00–10:00', '13:00–15:00', '18:00–20:00'], array_column($rows, 'time'));
    }

    public function test_row_content_and_celebrant(): void
    {
        App::setLocale('es');
        $this->makeItem($this->makeOrder(), $this->pack, $this->makeSlot(self::DAY, '16:00:00', '18:00:00'), [
            'quantity' => 8, 'seats' => 8, 'event_data' => ['celebrant' => 'Lucía', 'age' => '7'],
        ]);
        $this->makeItem($this->makeOrder(), $this->entry, $this->makeSlot(self::DAY, '10:00:00', '11:00:00'), ['quantity' => 3, 'seats' => 3]);

        $rows = DailyReservationsSummary::for(self::DAY)->rows();

        // rows[0] = entrada (10:00), rows[1] = pack (16:00).
        $this->assertSame('Entrada', $rows[0]['typeLabel']);
        $this->assertSame('Entrada 1 hora', $rows[0]['product']);
        $this->assertSame('3 entradas', $rows[0]['quantityLabel']);
        $this->assertNull($rows[0]['celebrant']);

        $this->assertSame('Cumpleaños', $rows[1]['typeLabel']);
        $this->assertSame('Cumpleaños Jump', $rows[1]['product']);
        $this->assertSame('8 invitados', $rows[1]['quantityLabel']);
        $this->assertSame('Lucía', $rows[1]['celebrant']);
        $this->assertSame('Ana Pérez', $rows[1]['customer']);
        $this->assertSame('612345678', $rows[1]['phone']);
        $this->assertSame('#FF5B22', $rows[1]['zoneColor']);
    }

    // ─── Ruta segura ──────────────────────────────────────────────────────────

    private function url(array $params = []): string
    {
        return route('admin.calendario.resumen-dia', array_merge(['date' => self::DAY, 'type' => 'all'], $params));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->url())->assertRedirect(route('login'));
    }

    public function test_customer_gets_403(): void
    {
        $this->actingAs($this->userWithRole('customer'))->get($this->url())->assertForbidden();
    }

    public function test_staff_without_calendar_view_gets_403(): void
    {
        $staff = $this->staff();
        $perm = Permission::where('name', 'calendar.view')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        $this->actingAs($staff)->get($this->url())->assertForbidden();
    }

    public function test_staff_can_download_pdf(): void
    {
        $this->makeItem($this->makeOrder(), $this->pack, $this->makeSlot(self::DAY, '16:00:00', '18:00:00'), ['quantity' => 8, 'seats' => 8]);

        $response = $this->actingAs($this->staff())->get($this->url());

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        // L1 (auditoría Fase 1): el resumen lista clientes/teléfonos/cumpleañeros (PII) → no-store.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_admin_can_download_pdf(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get($this->url())->assertOk();
    }

    public function test_invalid_date_defaults_to_today(): void
    {
        $this->actingAs($this->staff())->get($this->url(['date' => 'no-soy-fecha']))->assertOk();
    }

    public function test_impossible_dates_fall_back_to_today_without_error(): void
    {
        $staff = $this->staff();
        $today = now(DisplayTime::timezone())->toDateString();

        // Fechas con formato válido pero IMPOSIBLES: mes 13 (Carbon lanzaría → 500)
        // y 30-feb (Carbon haría rollover al día equivocado). Deben caer a HOY sin
        // error y registrar HOY en el audit (no la fecha imposible ni el rollover).
        foreach (['2099-13-45', '2099-13-01', '2099-02-30', '2099-00-00'] as $bad) {
            $this->actingAs($staff)->get($this->url(['date' => $bad]))->assertOk();
        }

        $log = AuditLog::where('action', 'calendar.day_summary_printed')->latest()->first();
        $this->assertSame($today, $log->payload['date']);
    }

    public function test_row_zone_color_falls_back_when_zone_is_null(): void
    {
        $noZone = TicketType::create([
            'name' => ['es' => 'Entrada sin zona'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => null, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 9,
        ]);
        $this->makeItem($this->makeOrder(), $noZone, $this->makeSlot(self::DAY, '10:00:00', '11:00:00'), ['quantity' => 2, 'seats' => 2]);

        $rows = DailyReservationsSummary::for(self::DAY)->rows();

        $this->assertSame(ReservationSlip::ZONE_COLOR_FALLBACK, $rows[0]['zoneColor']);
    }

    public function test_celebrant_is_null_when_pack_has_no_or_empty_event_data(): void
    {
        // Pack sin event_data (null) y pack con el campo del homenajeado vacío:
        // ambos → celebrant null (la vista lo degrada a "—", sin romper).
        $this->makeItem($this->makeOrder(), $this->pack, $this->makeSlot(self::DAY, '16:00:00', '18:00:00'), ['quantity' => 8, 'seats' => 8]);
        $this->makeItem($this->makeOrder(), $this->pack, $this->makeSlot(self::DAY, '18:00:00', '20:00:00'), ['quantity' => 8, 'seats' => 8, 'event_data' => ['celebrant' => '']]);

        $rows = DailyReservationsSummary::for(self::DAY)->rows();

        $this->assertNull($rows[0]['celebrant']);
        $this->assertNull($rows[1]['celebrant']);
    }

    public function test_locale_is_forced_to_spanish(): void
    {
        $staff = $this->staff();
        $staff->forceFill(['panel_locale' => 'zh_CN'])->save();
        App::setLocale('zh_CN');

        $this->actingAs($staff)->get($this->url())->assertOk();

        $this->assertSame('es', App::getLocale());
    }

    public function test_audit_log_is_written(): void
    {
        $staff = $this->staff();
        $this->makeItem($this->makeOrder(), $this->entry, $this->makeSlot(self::DAY, '10:00:00', '11:00:00'), ['quantity' => 3, 'seats' => 3]);

        $this->actingAs($staff)->get($this->url(['type' => 'entry']))->assertOk();

        $log = AuditLog::where('action', 'calendar.day_summary_printed')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame(self::DAY, $log->payload['date']);
        $this->assertSame('entry', $log->payload['type']);
        $this->assertSame(1, $log->payload['count']);
    }

    public function test_view_renders_in_spanish_without_payment_pii(): void
    {
        App::setLocale('es');
        $order = $this->makeOrder();
        $this->makeItem($order, $this->pack, $this->makeSlot(self::DAY, '16:00:00', '18:00:00'), [
            'quantity' => 8, 'seats' => 8, 'event_data' => ['celebrant' => 'Lucía'],
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'auth_code' => 'AUTHSECRET77',
            'gateway_order' => str_pad((string) (++$this->paymentCounter + 100000), 10, '0', STR_PAD_LEFT),
        ]);

        $html = view('pdf.daily-summary', [
            'summary' => DailyReservationsSummary::for(self::DAY),
        ])->render();

        $this->assertStringContainsString('Resumen del día', $html);
        $this->assertStringContainsString('Cumpleaños Jump', $html);
        $this->assertStringContainsString('Lucía', $html);
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('8 invitados', $html);
        // Ninguna clave i18n cruda + nada de la dimensión de cobro.
        $this->assertStringNotContainsString('admin.calendar.', $html);
        $this->assertStringNotContainsString('AUTHSECRET77', $html);
        $this->assertStringNotContainsString('0000100001', $html);
    }

    // ─── Acción de cabecera en ambas páginas ──────────────────────────────────

    public function test_calendar_page_shows_print_action(): void
    {
        Livewire::actingAs($this->staff())
            ->test(CalendarPage::class)
            ->assertActionVisible('printDaySummary');
    }

    public function test_dashboard_shows_print_action(): void
    {
        Livewire::actingAs($this->staff())
            ->test(Dashboard::class)
            ->assertActionVisible('printDaySummary');
    }

    public function test_print_action_hidden_without_calendar_view(): void
    {
        $staff = $this->staff();
        $perm = Permission::where('name', 'calendar.view')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        // El Escritorio es accesible aunque falte `calendar.view`; el botón se oculta.
        Livewire::actingAs($staff)
            ->test(Dashboard::class)
            ->assertActionHidden('printDaySummary');
    }

    public function test_print_action_opens_summary_in_new_tab(): void
    {
        // La acción NO redirige (misma pestaña): emite `open-url-new-tab` con la URL
        // del resumen, que el listener global del panel abre en pestaña nueva.
        Livewire::actingAs($this->staff())
            ->test(CalendarPage::class)
            ->callAction('printDaySummary', ['date' => self::DAY, 'type' => 'pack'])
            ->assertNoRedirect()
            ->assertDispatched('open-url-new-tab', url: route('admin.calendario.resumen-dia', ['date' => self::DAY, 'type' => 'pack']));
    }

    public function test_open_url_listener_does_not_double_open_the_tab(): void
    {
        // Regresión (2026-06-15): `window.open(url, '_blank', 'noopener')` devuelve SIEMPRE null
        // (por especificación), así que el fallback de «popup bloqueado» se disparaba también en el
        // caso normal → la URL se abría DOS veces (pestaña nueva + misma pestaña). El listener debe
        // abrir SIN el feature `noopener` y anular `opener` a mano; el fallback a la misma pestaña
        // solo cuando el popup fue realmente bloqueado.
        $js = view('filament.admin.open-url-listener')->render();

        $this->assertStringNotContainsString("window.open(url, '_blank', 'noopener')", $js); // patrón buggy fuera
        $this->assertStringContainsString("window.open(url, '_blank')", $js);                // apertura correcta
        $this->assertStringContainsString('opener = null', $js);                             // seguridad equivalente a noopener
    }
}
