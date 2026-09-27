<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\CsvExport;
use App\Filament\Analytics\Metric;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Widgets\Analytics\GateWidget;
use App\Filament\Widgets\Analytics\MoneyCustomersWidget;
use App\Filament\Widgets\Analytics\MoneyOverviewWidget;
use App\Filament\Widgets\Analytics\PartiesOverviewWidget;
use App\Filament\Widgets\Analytics\RegistrationsWidget;
use App\Filament\Widgets\Analytics\SurveysOverviewWidget;
use App\Filament\Widgets\Analytics\TrafficWidget;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **El censo del cuadro: nada de lo medido se pierde** (`specs/analitica-para-decidir.md` §4.1.bis, `[DECIDIDO owner]`
 * 27-09: «no quitaremos contenido, ¿no?»). Las 44 cifras de las tarjetas del 27-09, TECLEADAS A MANO (`#734`: con
 * `__()` dentro, vaciar una clave pasaría igual), tienen que seguir en el resumen del CSV de su informe, que es lo que
 * sobrevive a cualquier reorganización de la pantalla (T3). Y cada tarjeta lleva su «¿Cómo se calcula?» en los dos
 * idiomas del panel.
 */
class AnalyticsCensusTest extends TestCase
{
    use RefreshDatabase;

    /** Las 44 cifras del 27-09, por informe del CSV. «Cobrado» y «Cobro medio» dejaron de decir «online» (T0b). */
    private const CENSUS = [
        CsvExport::REPORT_MONEY => [
            'Cobrado', 'Devuelto', 'Ingresos netos', 'Vendido', 'Pedidos cobrados', 'Valor medio del pedido',
            'Compradores', 'Nuevos', 'Recurrentes', 'Valor medio por cliente', 'Valor de vida medio', 'Gestiones posteriores',
        ],
        CsvExport::REPORT_CUSTOMERS => [
            'Cuentas nuevas', 'Con el correo verificado', 'Que han comprado alguna vez', 'Búsquedas en la puerta', 'Tecleadas',
            'Escaneos de carné', 'Encontradas', 'Clientes distintos buscados', 'Fichas abiertas', 'Visitas acreditadas',
            'Clientes con visita acreditada',
        ],
        CsvExport::REPORT_FUNNEL => [
            'Visitas', 'Compras por la web o la app', 'Conversión', 'Cobrado en esas compras', 'Sesiones identificadas',
            'Fuera del recuento',
        ],
        CsvExport::REPORT_PARTIES => [
            'Fiestas', 'Vendido después de reservar', 'Cobrado en el parque', 'Reservas con extras', 'Extras por reserva (media)',
            'Formularios completados', 'Dentro del plazo', 'Respuestas «sí» a la invitación', 'Justificantes firmados',
        ],
        CsvExport::REPORT_SURVEYS => [
            'Contestadas', 'Tasa en la puerta', 'Tasa por correo', 'Correos mandados', 'No preguntadas', 'Nota media',
        ],
    ];

    /** Los widgets de tarjetas del cuadro (T0b: todos pasan por `Metric`). */
    private const TILE_WIDGETS = [
        MoneyOverviewWidget::class, MoneyCustomersWidget::class, RegistrationsWidget::class, GateWidget::class,
        TrafficWidget::class, PartiesOverviewWidget::class, SurveysOverviewWidget::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        app()->setLocale('es');
    }

    public function test_the_44_figures_of_27_09_are_still_in_the_csv_of_their_report(): void
    {
        $this->assertSame(44, array_sum(array_map('count', self::CENSUS)), 'el censo es el del 27-09: 44 tarjetas');

        foreach (self::CENSUS as $report => $labels) {
            $firstCells = array_map(static fn (array $row): string => (string) ($row[0] ?? ''), (new CsvExport)->build($report, ReportPeriod::ThisMonth->window())['rows']);

            foreach ($labels as $label) {
                $this->assertContains($label, $firstCells, "«{$label}» ya no está en el CSV de «{$report}»: nada de lo medido se quita (§4.1.bis)");
            }
        }
    }

    public function test_every_tile_is_a_metric_with_its_definition_in_both_panel_languages(): void
    {
        $this->actingAs($this->admin());
        $keys = [];

        foreach (self::TILE_WIDGETS as $class) {
            /** @var list<Stat> $stats */
            $stats = (new \ReflectionMethod($class, 'getStats'))->invoke(new $class);

            foreach ($stats as $stat) {
                $this->assertSame('filament.widgets.analytics.metric', $stat->getView(), "una tarjeta de {$class} no pasa por la anatomía");
                /** @var Metric $metric */
                $metric = $stat->getViewData()['metric'];
                $keys[] = $metric->key;

                foreach (['es', 'zh_CN'] as $locale) {
                    $how = trans('admin.analytics.how.'.$metric->key, [], $locale);
                    $this->assertNotSame('admin.analytics.how.'.$metric->key, $how, "«{$metric->key}» no tiene «¿Cómo se calcula?» en {$locale}");
                    $this->assertNotSame('', trim((string) $how));
                }
            }
        }

        $this->assertCount(44, $keys);
        $this->assertCount(44, array_unique($keys), 'cada tarjeta, una clave');
    }

    /** La prueba de un recuento compara RITMOS: marzo (31 días) contra febrero (28) no pesan lo mismo. */
    public function test_the_widget_passes_the_length_of_each_window_to_the_count_test(): void
    {
        $widget = new TrafficWidget;
        $march = Window::ofDays(CarbonImmutable::parse('2026-03-01', 'Europe/Madrid'), CarbonImmutable::parse('2026-03-31', 'Europe/Madrid'), 'Europe/Madrid', Window::UNIT_MONTH);
        (new \ReflectionProperty(TrafficWidget::class, 'forcedWindow'))->setValue($widget, $march);
        (new \ReflectionProperty(TrafficWidget::class, 'forcedComparison'))->setValue($widget, Comparison::Previous);

        $share = (new \ReflectionMethod(TrafficWidget::class, 'windowShare'))->invoke($widget);

        // 31 días de marzo contra 28 de febrero, y el 29 de marzo tuvo 23 horas (cambio de hora): 743 h contra 672 h.
        $this->assertEqualsWithDelta(743 / (743 + 672), $share, 1e-9);
    }

    /**
     * Ningún widget del cuadro se vuelve a pedir solo (T0b, medido el 27-09): con el sondeo de Filament (`'5s'`) el
     * cuadro quieto hacía una petición cada 5 s y la que estaba en vuelo al tocar el filtro se abortaba en la consola.
     */
    public function test_no_widget_of_the_dashboard_polls(): void
    {
        foreach (array_merge(...array_values(AnalyticsPage::TABS)) as $class) {
            if (! method_exists($class, 'getPollingInterval')) {
                continue;
            }

            $this->assertNull((new \ReflectionMethod($class, 'getPollingInterval'))->invoke(new $class), "{$class} vuelve a sondear");
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $user;
    }
}
