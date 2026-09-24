<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Analytics\SegmentsReport;
use App\Filament\Pages\AnalyticsPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La exportación de un segmento** (`specs/analitica.md` §4.6, T4b): permiso propio `analytics.export` (el admin
 * lo tiene; el staff no, salvo que el rol lo lleve), solo las personas con opt-in de comunicaciones, celdas
 * saneadas, rastro con el segmento y el recuento —sin PII— y `no-store`.
 */
class SegmentsExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00'));
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->value('id')]);

        return $user;
    }

    /** Un cliente que compró UNA vez hace más de una temporada: del segmento «compró y no volvió». */
    private function onceNeverBack(bool $optIn, string $name = 'Ada Lovelace'): User
    {
        $user = User::factory()->create(['name' => $name, 'marketing_opt_in' => $optIn, 'phone' => '600111222']);
        Order::create(['user_id' => $user->id, 'code' => 'JJ-'.strtoupper(substr(md5($name), 0, 6)), 'status' => Order::STATUS_PAID, 'paid_at' => now()->subDays(200), 'total' => 3000, 'expires_at' => now()->addMinutes(30)]);

        return $user;
    }

    private function url(string $segment = SegmentsReport::ONCE_NEVER_BACK): string
    {
        return route('admin.analitica.segmentos.csv', ['segment' => $segment]);
    }

    public function test_the_permission_is_its_own_and_the_staff_does_not_hold_it_by_default(): void
    {
        $this->actingAs($this->withRole('staff'))->get($this->url())->assertForbidden();
        $this->actingAs($this->withRole('customer'))->get($this->url())->assertForbidden();

        $this->assertTrue($this->withRole('admin')->hasPermission(AnalyticsPage::PERMISSION_SEGMENTS_EXPORT));
        $this->actingAs($this->withRole('admin'))->get($this->url())->assertOk();

        // Concedido al rol staff, surte efecto; y es DISTINTO del CSV de agregados.
        Role::where('name', 'staff')->firstOrFail()->permissions()->attach(Permission::where('name', 'analytics.export')->value('id'));
        $staff = $this->withRole('staff');
        $this->assertFalse($staff->hasPermission(AnalyticsPage::PERMISSION_EXPORT), 'precondición: el staff no exporta agregados');
        $this->actingAs($staff)->get($this->url())->assertOk();
    }

    public function test_an_unknown_segment_is_a_404(): void
    {
        $this->actingAs($this->withRole('admin'))->get(route('admin.analitica.segmentos.csv', ['segment' => 'todos']))->assertNotFound();
        $this->actingAs($this->withRole('admin'))->get(route('admin.analitica.segmentos.csv'))->assertNotFound();
    }

    public function test_the_csv_carries_only_the_people_with_opt_in_sanitized_and_leaves_a_trace_without_pii(): void
    {
        $this->onceNeverBack(optIn: true, name: '=Ada Lovelace');
        $this->onceNeverBack(optIn: false, name: 'Grace Hopper');

        $response = $this->actingAs($this->withRole('admin'))->get($this->url());

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'), 'una lista de personas no se guarda en ninguna caché');
        $this->assertStringContainsString('attachment; filename="segmento-once_never_back-20260924.csv"', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->getContent();
        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', (string) $csv))));
        $this->assertCount(2, $lines, 'la cabecera y UNA persona: la que dio el opt-in');
        $this->assertStringContainsString('Ada Lovelace', $lines[1]);
        $this->assertStringNotContainsString('Grace', (string) $csv, 'sin opt-in no se exporta');
        $this->assertStringContainsString('600111222', $lines[1]);
        $this->assertStringNotContainsString(';=Ada', (string) $csv, 'una celda que empieza por «=» no es una fórmula');

        $trace = AuditLog::query()->where('action', 'segments.exported')->sole();
        $this->assertSame('once_never_back', $trace->payload['segment']);
        $this->assertSame(1, $trace->payload['rows']);
        $this->assertStringNotContainsString('Lovelace', json_encode($trace->payload), 'el rastro no lleva PII');
    }

    public function test_the_guests_segment_exports_nobody_because_nobody_gave_an_opt_in(): void
    {
        $response = $this->actingAs($this->withRole('admin'))->get($this->url(SegmentsReport::GUEST_NO_PURCHASE))->assertOk();

        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', (string) $response->getContent()))));
        $this->assertCount(1, $lines, 'solo la cabecera');
    }

    /** T3 de la fiesta: quien vino invitado y luego compró tiene cuenta, y con opt-in se exporta como cualquier cliente. */
    public function test_the_guests_who_became_customers_export_with_their_opt_in(): void
    {
        $host = User::factory()->create();
        $party = Order::create(['user_id' => $host->id, 'code' => 'JJ-FIESTA', 'status' => Order::STATUS_PAID, 'paid_at' => now()->subDays(120), 'total' => 9000, 'expires_at' => now()->addMinutes(30)]);
        $zone = Zone::create(['slug' => 'z-exp', 'name' => ['es' => 'Z']]);
        $pack = TicketType::create(['name' => ['es' => 'Cumple'], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]);
        $reservation = $party->items()->create(['ticket_type_id' => $pack->id, 'quantity' => 6, 'seats' => 6, 'unit_price' => 1500]);
        DB::table('guardian_authorizations')->insert([
            'order_item_id' => $reservation->id, 'minor_name' => 'Peque', 'minor_surname' => 'Invitado', 'minor_key' => 'peque-invitado', 'minor_born_on' => '2018-05-05',
            'guardian_name' => 'Marie', 'guardian_surname' => 'Curie', 'guardian_relationship' => 'mother', 'guardian_email' => 'marie@example.test', 'guardian_phone' => null, 'created_at' => now()->subDays(100),
        ]);
        $converted = User::factory()->create(['name' => 'Marie Curie', 'email' => 'MARIE@example.test', 'marketing_opt_in' => true, 'phone' => '600333444']);
        Order::create(['user_id' => $converted->id, 'code' => 'JJ-MARIE', 'status' => Order::STATUS_PAID, 'paid_at' => now()->subDays(10), 'total' => 3000, 'expires_at' => now()->addMinutes(30)]);

        $response = $this->actingAs($this->withRole('admin'))->get($this->url(SegmentsReport::GUEST_BECAME_CUSTOMER))->assertOk();

        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', (string) $response->getContent()))));
        $this->assertCount(2, $lines, 'la cabecera y la persona que vino invitada y luego compró');
        $this->assertStringContainsString('Marie Curie', $lines[1]);
        $this->assertStringContainsString('600333444', $lines[1]);
    }

    /** El botón vive en la página de «Analítica» y solo lo ve quien tiene el permiso. */
    public function test_the_page_offers_the_export_only_with_the_permission(): void
    {
        Livewire::actingAs($this->withRole('admin'))->test(AnalyticsPage::class)
            ->assertActionVisible('exportSegment')
            ->assertSee(__('admin.analytics.segments.export.button'));

        $staffRole = Role::where('name', 'staff')->firstOrFail();
        $staffRole->permissions()->attach(Permission::where('name', 'reports.view')->value('id'));
        Livewire::actingAs($this->withRole('staff'))->test(AnalyticsPage::class)->assertActionHidden('exportSegment');
    }
}
