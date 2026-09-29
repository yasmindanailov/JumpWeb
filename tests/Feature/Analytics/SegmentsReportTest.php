<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PermissionCatalog;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Filament\Analytics\AudienceReport;
use App\Filament\Analytics\SegmentsReport;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Widgets\Analytics\SegmentsWidget;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Los segmentos** (`specs/analitica.md` §4.6, T4b): cada regla con su caso y su CONTROL —quien casi entra y no
 * entra—, siempre desde los PEDIDOS y el libro, nunca desde la fecha de nacimiento de un menor; el equipo y las
 * cuentas anonimizadas no cuentan; y el widget solo pinta RECUENTOS, con 1–4 dicho «menos de 5» (TP·3b, `#793`).
 */
class SegmentsReportTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $jump;

    private TicketType $pack;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00'));
        Cache::flush();

        $zone = Zone::create(['slug' => 'z-'.Str::lower(Str::random(5)), 'name' => ['es' => 'Zona']]);
        $this->slot = Slot::create(['zone_id' => $zone->id, 'date' => now()->addDays(7)->toDateString(), 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'online_capacity' => 50]);
        $this->jump = TicketType::create(['name' => ['es' => 'Salto libre'], 'zone_id' => $zone->id, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]);
        $this->pack = TicketType::create(['name' => ['es' => 'Cumpleaños'], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2]);
    }

    private function customer(bool $optIn = false, array $attributes = []): User
    {
        return User::factory()->create(['marketing_opt_in' => $optIn] + $attributes);
    }

    private function order(User $user, string $paidAt, ?TicketType $type = null, string $status = Order::STATUS_PAID): Order
    {
        $order = Order::create(['user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => $status, 'paid_at' => Carbon::parse($paidAt), 'total' => 3000, 'expires_at' => now()->addMinutes(30)]);
        OrderItem::create(['order_id' => $order->id, 'ticket_type_id' => ($type ?? $this->jump)->id, 'slot_id' => $this->slot->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 3000]);

        return $order;
    }

    private function contact(User $user): void
    {
        AnalyticsEvent::create(['event_id' => Visitor::mint(), 'session_id' => null, 'visitor_id' => Visitor::mint(), 'user_id' => $user->id, 'name' => 'contact_received', 'occurred_at' => now(), 'received_at' => now()]);
    }

    private function guest(string $email, Order $order): void
    {
        DB::table('guardian_authorizations')->insert([
            'order_item_id' => $order->items()->value('id'),
            'minor_name' => 'Peque', 'minor_surname' => 'Invitado', 'minor_key' => Str::random(12), 'minor_born_on' => '2018-05-05',
            'guardian_name' => 'Madre', 'guardian_surname' => 'Invitada', 'guardian_relationship' => 'mother',
            'guardian_email' => $email, 'guardian_phone' => null, 'created_at' => now(),
        ]);
    }

    public function test_bought_once_and_never_came_back_needs_one_collected_order_older_than_a_season(): void
    {
        $in = $this->customer(optIn: true);
        $this->order($in, '2026-03-01 10:00:00');
        $recent = $this->customer();
        $this->order($recent, '2026-09-01 10:00:00');                     // volvió hace poco: no
        $twice = $this->customer();
        $this->order($twice, '2026-01-01 10:00:00');
        $this->order($twice, '2026-02-01 10:00:00');                      // dos compras: no
        $pending = $this->customer();
        $this->order($pending, '2026-01-01 10:00:00', status: Order::STATUS_PENDING);   // sin cobrar: no
        $refunded = $this->customer();
        $this->order($refunded, '2026-02-01 10:00:00', status: Order::STATUS_REFUNDED); // cobrado y devuelto: sí

        $counts = (new SegmentsReport)->compute();

        $this->assertSame(['size' => 2, 'opt_in' => 1], $counts[SegmentsReport::ONCE_NEVER_BACK], 'el opt-in es solo el de quien lo dio');
    }

    public function test_a_party_a_year_ago_is_the_last_pack_between_ten_and_twelve_months_ago_from_the_orders(): void
    {
        $in = $this->customer(optIn: true);
        $this->order($in, '2025-10-20 10:00:00', $this->pack);             // hace 11 meses: sí
        $again = $this->customer();
        $this->order($again, '2025-10-20 10:00:00', $this->pack);
        $this->order($again, '2026-08-01 10:00:00', $this->pack);          // volvió a celebrar: no
        $noPack = $this->customer();
        $this->order($noPack, '2025-10-20 10:00:00');                      // no era una fiesta: no
        $tooOld = $this->customer();
        $this->order($tooOld, '2025-08-01 10:00:00', $this->pack);         // hace más de 12 meses: no
        $tooRecent = $this->customer();
        $this->order($tooRecent, '2026-02-01 10:00:00', $this->pack);      // hace 7 meses: no

        $counts = (new SegmentsReport)->compute();

        $this->assertSame(['size' => 1, 'opt_in' => 1], $counts[SegmentsReport::PARTY_YEAR_AGO]);
    }

    public function test_a_guest_who_never_bought_is_a_guardian_email_without_a_collected_order_and_is_only_counted(): void
    {
        $host = $this->customer();
        $party = $this->order($host, '2026-06-01 10:00:00', $this->pack);
        $this->guest('madre@example.test', $party);
        $this->guest('MADRE@example.test', $party);                        // el mismo correo, otra fiesta: uno
        $this->guest('padre@example.test', $party);
        $buyer = $this->customer(optIn: true, attributes: ['email' => 'padre@example.test']);
        $this->order($buyer, '2026-07-01 10:00:00');                       // el padre compró después: no

        $counts = (new SegmentsReport)->compute();

        $this->assertSame(['size' => 1, 'opt_in' => 0], $counts[SegmentsReport::GUEST_NO_PURCHASE], 'sin cuenta no hay opt-in');
    }

    /**
     * T3 de la fiesta: vino invitado (su correo firmó un justificante de menor invitado) y DESPUÉS compró con su
     * cuenta. El correo se compara en minúsculas; quien firmó siendo ya cliente no cuenta; quien firmó y no compró
     * es el otro segmento. Son cuentas: su opt-in cuenta.
     */
    public function test_a_guest_who_later_bought_is_a_guardian_email_that_became_an_account_with_a_collected_order(): void
    {
        $host = $this->customer();
        $party = $this->order($host, '2026-05-10 10:00:00', $this->pack);

        $converted = $this->customer(optIn: true, attributes: ['email' => 'Madre@Example.test', 'name' => 'Madre Convertida']);
        $this->guest('madre@example.test', $party);                        // firmó el 24-09 (now)…
        $this->order($converted, '2026-09-30 10:00:00');                   // …y compró después
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));

        $already = $this->customer(attributes: ['email' => 'cliente@example.test']);
        $this->order($already, '2026-03-01 10:00:00');                      // ya era cliente cuando firmó
        $this->guest('cliente@example.test', $party);

        $neverBought = 'sinpedido@example.test';
        $this->guest($neverBought, $party);

        Cache::flush();
        $counts = SegmentsReport::counts();

        $this->assertSame(['size' => 1, 'opt_in' => 1], $counts[SegmentsReport::GUEST_BECAME_CUSTOMER]);
        $this->assertSame(['size' => 1, 'opt_in' => 0], $counts[SegmentsReport::GUEST_NO_PURCHASE], 'el que nunca compró sigue en su segmento');
    }

    public function test_a_contact_without_an_order_is_an_identified_contact_fact_and_no_collected_order(): void
    {
        $in = $this->customer(optIn: true);
        $this->contact($in);
        $bought = $this->customer();
        $this->contact($bought);
        $this->order($bought, '2026-08-01 10:00:00');                      // escribió y compró: no
        $silent = $this->customer();                                       // ni escribió ni compró: no

        $counts = (new SegmentsReport)->compute();

        $this->assertSame(['size' => 1, 'opt_in' => 1], $counts[SegmentsReport::CONTACT_NO_ORDER]);
    }

    /** El equipo y una cuenta anonimizada no son clientes de ningún segmento, aunque sus pedidos digan que sí. */
    public function test_the_team_and_an_anonymized_account_never_count(): void
    {
        $staff = $this->customer(optIn: true);
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $this->order($staff, '2026-03-01 10:00:00');
        $anon = $this->customer(optIn: true, attributes: ['email' => 'deleted_9@'.User::ANONYMIZED_EMAIL_DOMAIN]);
        $this->order($anon, '2026-03-01 10:00:00');

        $counts = (new SegmentsReport)->compute();

        $this->assertSame(['size' => 0, 'opt_in' => 0], $counts[SegmentsReport::ONCE_NEVER_BACK]);
    }

    /**
     * TP·3b (`#793`): el widget solo pinta RECUENTOS y una cifra de 1 a 4 se dice «menos de 5» (`RGPD-07`); el 0 y
     * el 5 se escriben tal cual. CONTROL: seis que compraron una vez, dos con opt-in.
     */
    public function test_the_widget_masks_a_count_between_one_and_four_and_names_nobody(): void
    {
        $people = collect(range(1, 6))->map(fn (int $i): User => $this->customer(optIn: $i <= 2));
        $people->each(fn (User $user) => $this->order($user, '2026-03-01 10:00:00'));
        foreach (range(1, 5) as $i) {
            $this->contact($this->customer());
        }
        Cache::flush();

        $rows = collect((new SegmentsWidget)->tablesFor(ReportPeriod::Custom->window('2026-06-01', '2026-06-30'))[0]['rows'])
            ->keyBy(0);
        $fewer = __('admin.analytics.surveys.fewer_than_min', ['min' => AudienceReport::MIN_CELL]);

        $this->assertSame(['6', $fewer], array_slice($rows[__('admin.analytics.segments.name.once_never_back')], 1), '6 tal cual; 2 con opt-in → «menos de 5»');
        $this->assertSame(['5', '0'], array_slice($rows[__('admin.analytics.segments.name.contact_no_order')], 1), 'el 5 y el 0, tal cual');
        $this->assertSame(['0', '0'], array_slice($rows[__('admin.analytics.segments.name.party_year_ago')], 1));
        $this->assertStringNotContainsString('@', json_encode($rows->all(), JSON_THROW_ON_ERROR), 'ni un correo');
    }

    /**
     * TP·3b (`#793`): «Exportar segmento» se retiró con su permiso. Ni el catálogo ni el seeder lo traen, su descarga ya no
     * existe, y la migración borra la fila de una instalación que ya lo tenía sembrado, con su pivote (cae en cascada).
     */
    public function test_nothing_exports_people_and_the_old_permission_is_gone(): void
    {
        $this->assertNotContains('analytics.export', PermissionCatalog::all());
        $this->assertDatabaseMissing('permissions', ['name' => 'analytics.export']);

        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole = Role::where('name', 'admin')->value('id')]);
        $this->actingAs($admin)->get('/admin/analitica/segmentos/csv?segment=once_never_back')->assertNotFound();

        $old = DB::table('permissions')->insertGetId(['name' => 'analytics.export', 'label' => 'Exportar segmentos', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('permission_role')->insert(['permission_id' => $old, 'role_id' => $adminRole]);
        (require base_path('database/migrations/2026_09_29_110000_drop_analytics_export_permission.php'))->up();

        $this->assertDatabaseMissing('permissions', ['name' => 'analytics.export']);
        $this->assertDatabaseMissing('permission_role', ['permission_id' => $old]);
        $this->assertDatabaseHas('permissions', ['name' => AnalyticsPage::PERMISSION]);
    }

    /** El widget de la pestaña «Clientes» pinta los cuatro segmentos con sus dos cifras. */
    public function test_the_widget_lists_the_five_segments_with_their_counts(): void
    {
        $in = $this->customer(optIn: true);
        $this->order($in, '2026-03-01 10:00:00');
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        Livewire::actingAs($admin)->test(SegmentsWidget::class)
            ->assertSee(__('admin.analytics.segments.heading'))
            ->assertSee(__('admin.analytics.segments.name.once_never_back'))
            ->assertSee(__('admin.analytics.segments.name.party_year_ago'))
            ->assertSee(__('admin.analytics.segments.name.guest_no_purchase'))
            ->assertSee(__('admin.analytics.segments.name.guest_became_customer'))
            ->assertSee(__('admin.analytics.segments.name.contact_no_order'));
    }
}
