<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Filament\Analytics\CsvExport;
use App\Filament\Pages\AnalyticsPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **El CSV de «Analítica»** (`specs/analitica.md` §4.5 y §6, T2d; `#735`): quién lo descarga, qué lleva, cómo se
 * sanea y que deja rastro sin PII.
 */
class AnalyticsExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        Cache::flush();
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->value('id')]);

        return $user;
    }

    private function url(string $report = 'money', string $period = 'this_month'): string
    {
        return route('admin.analitica.csv', ['report' => $report, 'period' => $period]);
    }

    // ─── La puerta ──────────────────────────────────────────────────────────────────────────────

    public function test_the_permission_is_checked_in_the_controller_not_only_in_the_button(): void
    {
        // Es una ruta `auth` del enrutador, no una página de Filament: el invitado va al login de la web.
        $this->get($this->url())->assertRedirect('/login');
        $this->actingAs($this->withRole('customer'))->get($this->url())->assertForbidden();
        $this->actingAs($this->withRole('staff'))->get($this->url())->assertForbidden();

        // Con `reports.view` se ve el cuadro, pero el CSV pide el SUYO.
        $viewer = $this->withRole('staff');
        $viewer->roles->first()->permissions()->attach(Permission::where('name', AnalyticsPage::PERMISSION)->value('id'));
        $this->actingAs($viewer->fresh())->get($this->url())->assertForbidden();
    }

    public function test_an_unknown_report_is_a_404_and_a_bad_period_falls_back(): void
    {
        $admin = $this->withRole('admin');

        $this->actingAs($admin)->get($this->url('inventado'))->assertNotFound();
        $this->actingAs($admin)->get($this->url('money', 'bogus'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="analitica-money-2026-06-01-2026-06-30.csv"');
    }

    // ─── El fichero ─────────────────────────────────────────────────────────────────────────────

    public function test_the_admin_downloads_a_csv_with_bom_semicolons_the_summary_and_the_tables(): void
    {
        $response = $this->actingAs($this->withRole('admin'))->get($this->url('money', 'last_30'))->assertOk();

        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment; filename="analitica-money-', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'UTF-8 con BOM: la hoja de cálculo en español lo abre bien');
        $this->assertStringContainsString("\r\n", $csv);
        // ⚠️ `fputcsv` entrecomilla toda celda con un espacio: se aserta el rótulo, no el rótulo pegado al separador.
        $this->assertStringContainsString(__('admin.analytics.export.summary'), $csv);
        $this->assertStringContainsString('"'.__('admin.analytics.money.collected').'";', $csv);
        $this->assertStringContainsString(__('admin.analytics.money.by_channel'), $csv);
        $this->assertStringContainsString(__('admin.analytics.money.by_day'), $csv);
        $this->assertStringContainsString(__('admin.analytics.money.col.day').';', $csv);
    }

    public function test_the_three_reports_download_and_each_carries_its_own_tables(): void
    {
        $admin = $this->withRole('admin');

        $customers = $this->actingAs($admin)->get($this->url('customers'))->assertOk()->getContent();
        $this->assertStringContainsString(__('admin.analytics.customers.by_method'), $customers);
        $this->assertStringContainsString('"'.__('admin.analytics.customers.lookups').'";', $customers);

        $funnel = $this->actingAs($admin)->get($this->url('funnel'))->assertOk()->getContent();
        $this->assertStringContainsString(__('admin.analytics.traffic.funnel'), $funnel);
        $this->assertStringContainsString(__('admin.analytics.traffic.first_touch'), $funnel);
        $this->assertStringContainsString(__('admin.analytics.traffic.entries'), $funnel);
        $this->assertStringContainsString('"'.__('admin.analytics.traffic.step.pay_started').'";', $funnel);
    }

    /** Una campaña que empiece por un signo de fórmula no se ejecuta al abrir el fichero: lleva el apóstrofo. */
    public function test_a_campaign_that_looks_like_a_formula_is_escaped(): void
    {
        AnalyticsSession::query()->create([
            'visitor_id' => Visitor::mint(), 'started_at' => '2026-06-05 10:00:00', 'last_seen_at' => '2026-06-05 10:05:00',
            'utm_source' => 'meta', 'utm_medium' => 'paid_social', 'utm_campaign' => '=1+1', 'is_bot' => false, 'is_internal' => false,
        ]);
        AnalyticsSession::query()->create([
            'visitor_id' => Visitor::mint(), 'started_at' => '2026-06-06 10:00:00', 'last_seen_at' => '2026-06-06 10:05:00',
            'utm_source' => '@remoto', 'utm_medium' => '-menos', 'utm_campaign' => '+HYPERLINK()', 'is_bot' => false, 'is_internal' => false,
        ]);

        $csv = $this->actingAs($this->withRole('admin'))->get($this->url('funnel'))->assertOk()->getContent();

        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString("'@remoto;'-menos;'+HYPERLINK()", $csv);
        $this->assertStringNotContainsString(';=1+1;', $csv);

        // Y la regla, sola: los seis prefijos y nada más.
        foreach (['=', '+', '-', '@', "\t", "\r"] as $prefix) {
            $this->assertSame("'".$prefix.'x', CsvExport::cell($prefix.'x'));
        }
        $this->assertSame('12,00 €', CsvExport::cell('12,00 €'));
        $this->assertSame('', CsvExport::cell(''));
    }

    // ─── El rastro ──────────────────────────────────────────────────────────────────────────────

    public function test_every_download_is_audited_with_the_report_the_period_and_the_row_count_and_no_pii(): void
    {
        $admin = $this->withRole('admin');

        $this->actingAs($admin)->get($this->url('customers', 'last_week'))->assertOk();

        $audit = AuditLog::query()->where('action', 'reports.exported')->sole();
        $this->assertSame($admin->id, (int) $audit->user_id);
        $this->assertNull($audit->target_id);
        $this->assertSame('customers', $audit->payload['report']);
        $this->assertSame('last_week', $audit->payload['period']);
        $this->assertSame('previous', $audit->payload['compare']);
        $this->assertGreaterThan(10, $audit->payload['rows']);
        $this->assertSame(['report', 'period', 'from', 'to', 'compare', 'rows'], array_keys($audit->payload));
    }

    /** Dos fechas a medida y la comparación con el año pasado viajan en la URL y salen en el fichero. */
    public function test_a_custom_range_and_the_year_ago_comparison_reach_the_file(): void
    {
        $csv = $this->actingAs($this->withRole('admin'))
            ->get(route('admin.analitica.csv', ['report' => 'money', 'period' => 'custom', 'from' => '2026-04-01', 'to' => '2026-06-15', 'compare' => 'year_ago']))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="analitica-money-2026-04-01-2026-06-15.csv"')
            ->getContent();

        $this->assertStringContainsString(__('admin.analytics.export.compare_line', ['comparison' => __('admin.analytics.compare.year_ago')]), $csv);
        $this->assertStringContainsString(__('admin.analytics.money.by_week'), $csv, '76 días se agrupan por semana');
    }

    // ─── El botón ───────────────────────────────────────────────────────────────────────────────

    public function test_the_button_shows_only_with_the_export_permission(): void
    {
        $this->actingAs($this->withRole('admin'))
            ->get(AnalyticsPage::getUrl())
            ->assertOk()
            ->assertSee(__('admin.analytics.export.button'));

        $viewer = $this->withRole('staff');
        $viewer->roles->first()->permissions()->attach(Permission::where('name', AnalyticsPage::PERMISSION)->value('id'));
        $this->actingAs($viewer->fresh())
            ->get(AnalyticsPage::getUrl())
            ->assertOk()
            ->assertDontSee(__('admin.analytics.export.button'));
    }

    public function test_the_default_period_of_the_file_is_this_month(): void
    {
        $built = (new CsvExport)->build(CsvExport::REPORT_MONEY, ReportPeriod::fromValue(null)->window());

        $this->assertSame('analitica-money-2026-06-01-2026-06-30.csv', $built['filename']);
        $this->assertSame([__('admin.analytics.export.title', ['report' => __('admin.analytics.export.report.money')])], $built['rows'][0]);
    }
}
