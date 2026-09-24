<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PermissionCatalog;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Analytics\CategoryChart;
use App\Filament\Widgets\Analytics\CustomersBreakdownWidget;
use App\Filament\Widgets\Analytics\CustomersSeriesChart;
use App\Filament\Widgets\Analytics\DevicesChart;
use App\Filament\Widgets\Analytics\ExperimentsWidget;
use App\Filament\Widgets\Analytics\FunnelChart;
use App\Filament\Widgets\Analytics\FunnelWidget;
use App\Filament\Widgets\Analytics\GateHoursChart;
use App\Filament\Widgets\Analytics\GateWidget;
use App\Filament\Widgets\Analytics\MoneyBreakdownWidget;
use App\Filament\Widgets\Analytics\MoneyChannelsChart;
use App\Filament\Widgets\Analytics\MoneyCustomersWidget;
use App\Filament\Widgets\Analytics\MoneyOverviewWidget;
use App\Filament\Widgets\Analytics\MoneyProductsChart;
use App\Filament\Widgets\Analytics\MoneySeriesChart;
use App\Filament\Widgets\Analytics\PagesWidget;
use App\Filament\Widgets\Analytics\RegistrationMethodsChart;
use App\Filament\Widgets\Analytics\RegistrationsWidget;
use App\Filament\Widgets\Analytics\SegmentsWidget;
use App\Filament\Widgets\Analytics\SourcesChart;
use App\Filament\Widgets\Analytics\SourcesWidget;
use App\Filament\Widgets\Analytics\TrafficHoursChart;
use App\Filament\Widgets\Analytics\TrafficSeriesChart;
use App\Filament\Widgets\Analytics\TrafficWidget;
use App\Filament\Widgets\DashboardStatsWidget;
use App\Filament\Widgets\ReservationsWidget;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La página «Analítica» y su puerta** (`specs/analitica.md` §4.5, T2a; `#735`): quién entra, qué widgets
 * son suyos y que «Hoy» no los hereda. Esconder no es autorizar: cada widget vuelve a preguntar el permiso.
 */
class AnalyticsPageTest extends TestCase
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
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->value('id')]);

        return $user;
    }

    // ─── La puerta ──────────────────────────────────────────────────────────────────────────────

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(AnalyticsPage::getUrl())->assertRedirect('/admin/login');
    }

    public function test_customer_and_staff_get_403(): void
    {
        $this->actingAs($this->withRole('customer'))->get(AnalyticsPage::getUrl())->assertForbidden();
        $this->actingAs($this->withRole('staff'))->get(AnalyticsPage::getUrl())->assertForbidden();
    }

    /**
     * Caso aparte a propósito, y PRIMERA petición del caso: tras una petición que renderiza Livewire en el
     * mismo test, `redirect()` devuelve el `Redirector` de Livewire y el middleware de la puerta explota con un
     * `TypeError` que en HTTP real no existe (cada petición es una aplicación nueva). Medido el 24-09.
     */
    public function test_puerta_lands_at_the_gate(): void
    {
        $this->actingAs($this->withRole('puerta'))->get(AnalyticsPage::getUrl())->assertRedirect(route('admin.puerta.validar'));
    }

    public function test_admin_opens_the_page_with_its_filters_and_its_three_tabs(): void
    {
        $this->actingAs($this->withRole('admin'))
            ->get(AnalyticsPage::getUrl())
            ->assertOk()
            ->assertSee('/admin/analitica', escape: false)
            ->assertSeeText('Analítica')
            ->assertSee(__('admin.analytics.period.this_month'))
            ->assertSee(__('admin.analytics.period.last_90'))
            ->assertSee(__('admin.analytics.period.this_year'))
            ->assertSee(__('admin.analytics.period.custom'))
            ->assertSee(__('admin.analytics.compare.year_ago'))
            ->assertSeeText(__('admin.analytics.tabs.money'))
            ->assertSeeText(__('admin.analytics.tabs.customers'))
            ->assertSeeText(__('admin.analytics.tabs.traffic'))
            ->assertSee('role="tablist"', escape: false);
    }

    /** El permiso existe sembrado, es de gestión (no del staff) y el CSV tiene el suyo. */
    public function test_the_permissions_are_seeded_and_catalogued_and_not_given_to_staff(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => AnalyticsPage::PERMISSION]);
        $this->assertDatabaseHas('permissions', ['name' => AnalyticsPage::PERMISSION_EXPORT]);
        $this->assertContains(AnalyticsPage::PERMISSION, PermissionCatalog::GROUPS['gestion']);
        $this->assertContains(AnalyticsPage::PERMISSION_EXPORT, PermissionCatalog::GROUPS['gestion']);

        $staff = $this->withRole('staff');
        $this->assertFalse($staff->hasPermission(AnalyticsPage::PERMISSION));
        $this->assertFalse($staff->hasPermission(AnalyticsPage::PERMISSION_EXPORT));
        $this->assertTrue($this->withRole('admin')->hasPermission(AnalyticsPage::PERMISSION));
    }

    /** Un empleado al que se le CONCEDE el permiso entra: es el permiso el que decide, no el rol. */
    public function test_a_staff_member_granted_the_permission_can_open_it(): void
    {
        $staff = $this->withRole('staff');
        $staff->roles->first()->permissions()->attach(Permission::where('name', AnalyticsPage::PERMISSION)->value('id'));

        $this->actingAs($staff->fresh())->get(AnalyticsPage::getUrl())->assertOk();
    }

    // ─── Los widgets ────────────────────────────────────────────────────────────────────────────

    /**
     * Los widgets del cuadro, pestaña a pestaña y en su orden de lectura (tarjetas → gráficos → tablas): el dinero
     * (T2a), los registros y la puerta (T2b) y la conversión (T2c), con los gráficos de categorías de la T2f.
     */
    private const WIDGETS = [
        MoneyOverviewWidget::class,
        MoneySeriesChart::class,
        MoneyProductsChart::class,
        MoneyChannelsChart::class,
        MoneyCustomersWidget::class,
        MoneyBreakdownWidget::class,
        RegistrationsWidget::class,
        GateWidget::class,
        CustomersSeriesChart::class,
        GateHoursChart::class,
        RegistrationMethodsChart::class,
        // T4b: los segmentos, antes de la tabla plegada que cierra «Clientes».
        SegmentsWidget::class,
        CustomersBreakdownWidget::class,
        TrafficWidget::class,
        FunnelChart::class,
        SourcesChart::class,
        TrafficSeriesChart::class,
        TrafficHoursChart::class,
        DevicesChart::class,
        // T5b: los experimentos, antes de las tablas plegadas que cierran «Conversión».
        ExperimentsWidget::class,
        FunnelWidget::class,
        SourcesWidget::class,
        PagesWidget::class,
    ];

    public function test_each_widget_asks_the_permission_again(): void
    {
        foreach (self::WIDGETS as $widget) {
            $this->actingAs($this->withRole('staff'));
            $this->assertFalse($widget::canView(), "{$widget} se abre sin permiso");

            $this->actingAs($this->withRole('admin'));
            $this->assertTrue($widget::canView(), "{$widget} no se abre con permiso");
        }
    }

    /** Dos cuadros en un panel: cada uno declara los suyos, y «Hoy» no hereda los del dinero. */
    public function test_hoy_keeps_its_two_widgets_and_analitica_has_its_own(): void
    {
        $this->assertSame([DashboardStatsWidget::class, ReservationsWidget::class], (new Dashboard)->getWidgets());

        $analytics = (new AnalyticsPage)->getWidgets();
        $this->assertSame(self::WIDGETS, $analytics);
        $this->assertEmpty(array_intersect($analytics, (new Dashboard)->getWidgets()));

        // T2f: tres pestañas, cada widget en una sola, y las tablas plegadas al final de cada una.
        $this->assertSame(['money', 'customers', 'traffic'], array_keys(AnalyticsPage::TABS));
        $this->assertSame($analytics, array_unique($analytics), 'ningún widget en dos pestañas');
        $this->assertSame(MoneyBreakdownWidget::class, array_last(AnalyticsPage::TABS['money']));
        $this->assertSame(CustomersBreakdownWidget::class, array_last(AnalyticsPage::TABS['customers']));
        $this->assertSame(PagesWidget::class, array_last(AnalyticsPage::TABS['traffic']));
    }

    /**
     * T2f: los gráficos de categorías leen de los informes. Sin datos devuelven VACÍO (el estado vacío de Filament,
     * no unos ejes en blanco); con una sesión móvil de Google, el embudo lleva el % de las visitas en el rótulo, las
     * fuentes se suman por fuente y el anillo de dispositivos tiene su porción.
     */
    public function test_the_category_charts_read_their_reports_and_are_empty_without_data(): void
    {
        $this->actingAs($this->withRole('admin'));
        $filters = ['period' => ReportPeriod::Last30->value];
        $data = static function (CategoryChart $chart) use ($filters): array {
            $chart->pageFilters = $filters;

            return (new \ReflectionMethod($chart, 'getData'))->invoke($chart);
        };

        foreach ([new MoneyProductsChart, new MoneyChannelsChart, new RegistrationMethodsChart, new FunnelChart, new SourcesChart, new DevicesChart] as $chart) {
            $this->assertSame([], $data($chart), $chart::class.' sin datos');
        }
        $this->assertSame(__('admin.analytics.money.empty'), (string) (new FunnelChart)->getEmptyStateHeading());

        AnalyticsSession::query()->create([
            'visitor_id' => Visitor::mint(), 'started_at' => now()->subDay(), 'last_seen_at' => now()->subDay(),
            'utm_source' => 'google', 'utm_medium' => 'cpc', 'device' => 'mobile', 'is_bot' => false, 'is_internal' => false,
        ]);
        Cache::flush();

        $funnel = $data(new FunnelChart);
        $this->assertSame(__('admin.analytics.traffic.step_short.visits').' · 100,0'."\u{00A0}%", $funnel['labels'][0]);
        $this->assertSame(__('admin.analytics.traffic.step_short.pay_started').' · 0,0'."\u{00A0}%", $funnel['labels'][5]);
        $this->assertSame([1, 0, 0, 0, 0, 0], $funnel['datasets'][0]['data']);
        $this->assertSame(MoneySeriesChart::COLORS['collected'], $funnel['datasets'][0]['backgroundColor']);

        $sources = $data(new SourcesChart);
        $this->assertSame(['google'], $sources['labels']);
        $this->assertSame([1], $sources['datasets'][0]['data']);

        $devices = new DevicesChart;
        $this->assertSame([__('admin.analytics.traffic.device.mobile')], $data($devices)['labels']);
        $this->assertSame([CategoryChart::PALETTE[0]], $data($devices)['datasets'][0]['backgroundColor']);
        $this->assertSame('doughnut', (new \ReflectionMethod($devices, 'getType'))->invoke($devices));
        $this->assertSame('bar', (new \ReflectionMethod($funnel = new FunnelChart, 'getType'))->invoke($funnel));
    }

    public function test_the_gate_hours_chart_has_the_24_park_hours_and_the_customers_breakdown_its_tables(): void
    {
        $this->actingAs($this->withRole('admin'));

        $chart = new GateHoursChart;
        $chart->pageFilters = ['period' => ReportPeriod::ThisMonth->value];
        $data = (new \ReflectionMethod(GateHoursChart::class, 'getData'))->invoke($chart);
        $this->assertCount(24, $data['labels']);
        $this->assertSame('00 h', $data['labels'][0]);
        $this->assertSame(array_fill(0, 24, 0), $data['datasets'][0]['data']);

        Livewire::test(CustomersBreakdownWidget::class, ['pageFilters' => ['period' => ReportPeriod::Last30->value]])
            ->assertOk()
            ->assertSee(__('admin.analytics.customers.by_method'))
            ->assertSee(__('admin.analytics.customers.method.unknown'))
            ->assertSee(__('admin.analytics.money.by_day'));
    }

    /** T2c: el embudo, las fuentes y las páginas renderizan vacíos sin romperse, y una campaña con fórmula sale como texto. */
    public function test_the_traffic_widgets_render_and_escape_what_an_advertiser_typed(): void
    {
        $this->actingAs($this->withRole('admin'));

        Livewire::test(FunnelWidget::class, ['pageFilters' => ['period' => ReportPeriod::Last30->value]])
            ->assertOk()
            ->assertSee(__('admin.analytics.traffic.step.pay_started'))
            ->assertSee(__('admin.analytics.traffic.left_at.cart'));

        Livewire::test(PagesWidget::class, ['pageFilters' => ['period' => ReportPeriod::Last30->value]])
            ->assertOk()
            ->assertSee(__('admin.analytics.traffic.entries'))
            ->assertSee(__('admin.analytics.traffic.rejected_reason.pii'));

        // Una campaña llamada `=1+1` y otra con una etiqueta: texto, nunca HTML ni fórmula viva.
        AnalyticsSession::query()->create([
            'visitor_id' => Visitor::mint(), 'started_at' => now()->subDay(), 'last_seen_at' => now()->subDay(),
            'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => '<img src=x onerror=alert(1)>', 'is_bot' => false, 'is_internal' => false,
        ]);
        AnalyticsSession::query()->create([
            'visitor_id' => Visitor::mint(), 'started_at' => now()->subDay(), 'last_seen_at' => now()->subDay(),
            'utm_source' => 'meta', 'utm_medium' => 'paid_social', 'utm_campaign' => '=1+1', 'is_bot' => false, 'is_internal' => false,
        ]);
        Cache::flush();

        Livewire::test(SourcesWidget::class, ['pageFilters' => ['period' => ReportPeriod::Last30->value]])
            ->assertOk()
            ->assertSee('=1+1')
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', escape: false)
            ->assertDontSee('<img src=x onerror=alert(1)>', escape: false);

        $chart = new TrafficHoursChart;
        $chart->pageFilters = ['period' => ReportPeriod::Last30->value];
        $this->assertCount(24, (new \ReflectionMethod(TrafficHoursChart::class, 'getData'))->invoke($chart)['labels']);
    }

    public function test_the_overview_renders_six_money_tiles_with_the_default_period(): void
    {
        $this->actingAs($this->withRole('admin'));

        $widget = new MoneyOverviewWidget;
        $method = new \ReflectionMethod(MoneyOverviewWidget::class, 'getStats');
        /** @var array<int, Stat> $stats */
        $stats = $method->invoke($widget);

        $this->assertCount(6, $stats);
        $this->assertSame(__('admin.analytics.money.collected'), (string) $stats[0]->getLabel());
        $this->assertSame('0,00 €', (string) $stats[0]->getValue());
        $this->assertSame(__('admin.analytics.delta.none_previous'), (string) $stats[0]->getDescription());
        $this->assertSame(ReportPeriod::ThisMonth, ReportPeriod::fromValue(null));
    }

    public function test_the_breakdown_widget_renders_its_six_tables(): void
    {
        $this->actingAs($this->withRole('admin'));

        Livewire::test(MoneyBreakdownWidget::class, ['pageFilters' => ['period' => ReportPeriod::Last30->value]])
            ->assertOk()
            ->assertSee(__('admin.analytics.money.by_day'))
            ->assertSee(__('admin.analytics.money.by_channel'))
            ->assertSee(__('admin.analytics.money.by_method'))
            ->assertSee(__('admin.analytics.money.by_product'))
            ->assertSee(__('admin.analytics.money.deposit'))
            ->assertSee(__('admin.analytics.money.lost'))
            ->assertSee(__('admin.analytics.money.empty'));
    }

    public function test_the_chart_speaks_euros_by_day_for_a_month(): void
    {
        $this->actingAs($this->withRole('admin'));

        $chart = new MoneySeriesChart;
        $chart->pageFilters = ['period' => ReportPeriod::ThisMonth->value];
        $data = (new \ReflectionMethod(MoneySeriesChart::class, 'getData'))->invoke($chart);

        $this->assertCount(30, $data['labels']);
        $this->assertSame('01/06', $data['labels'][0]);
        $this->assertCount(3, $data['datasets']);
        $this->assertSame(__('admin.analytics.money.collected'), $data['datasets'][0]['label']);
        $this->assertSame(array_fill(0, 30, 0.0), $data['datasets'][0]['data']);
        $this->assertStringContainsString(__('admin.analytics.money.granularity.day'), (string) $chart->getHeading());
    }
}
