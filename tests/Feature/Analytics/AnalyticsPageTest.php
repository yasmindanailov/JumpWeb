<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PermissionCatalog;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Analytics\MoneyBreakdownWidget;
use App\Filament\Widgets\Analytics\MoneyCustomersWidget;
use App\Filament\Widgets\Analytics\MoneyOverviewWidget;
use App\Filament\Widgets\Analytics\MoneySeriesChart;
use App\Filament\Widgets\DashboardStatsWidget;
use App\Filament\Widgets\ReservationsWidget;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_admin_opens_the_page_with_its_period_filter(): void
    {
        $this->actingAs($this->withRole('admin'))
            ->get(AnalyticsPage::getUrl())
            ->assertOk()
            ->assertSee('/admin/analitica', escape: false)
            ->assertSeeText('Analítica')
            ->assertSee(__('admin.analytics.period.this_month'))
            ->assertSee(__('admin.analytics.period.last_90'));
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

    public function test_each_widget_asks_the_permission_again(): void
    {
        foreach ([MoneyOverviewWidget::class, MoneySeriesChart::class, MoneyCustomersWidget::class, MoneyBreakdownWidget::class] as $widget) {
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
        $this->assertSame([MoneyOverviewWidget::class, MoneySeriesChart::class, MoneyCustomersWidget::class, MoneyBreakdownWidget::class], $analytics);
        $this->assertEmpty(array_intersect($analytics, (new Dashboard)->getWidgets()));
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
        $this->assertSame(__('admin.analytics.delta.no_previous'), (string) $stats[0]->getDescription());
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
