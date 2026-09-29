<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\CustomerVisit;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\CsvExport;
use App\Filament\Analytics\Metric;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Widgets\Analytics\BookedMoreWidget;
use App\Filament\Widgets\Analytics\CustomersMoreWidget;
use App\Filament\Widgets\Analytics\CustomersOverviewWidget;
use App\Filament\Widgets\Analytics\DataQualityWidget;
use App\Filament\Widgets\Analytics\GateWidget;
use App\Filament\Widgets\Analytics\MoneyMoreWidget;
use App\Filament\Widgets\Analytics\MoneyOverviewWidget;
use App\Filament\Widgets\Analytics\OccupancyOverviewWidget;
use App\Filament\Widgets\Analytics\PartiesMoreWidget;
use App\Filament\Widgets\Analytics\PartiesOverviewWidget;
use App\Filament\Widgets\Analytics\RegistrationsWidget;
use App\Filament\Widgets\Analytics\SurveysMoreWidget;
use App\Filament\Widgets\Analytics\SurveysOverviewWidget;
use App\Filament\Widgets\Analytics\TrafficMoreWidget;
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

    /**
     * Las 44 cifras del 27-09, por informe del CSV. Renombradas A PROPÓSITO, con su nombre de entonces:
     *  - T0b: «Cobrado online» → «Cobrado» (sumaba también el mostrador).
     *  - T3a (`#759`, glosario de §4.11): «Nuevos» → «Compradores nuevos» y «Recurrentes» → «Compradores recurrentes» (van a
     *    «Clientes», sin el título «Clientes que compran» que les daba sentido); «Visitas» → «Visitas a la web» (junto a
     *    «Visitantes» del parque); «Sesiones identificadas» → «Visitas identificadas» («sesión» es jerga).
     */
    private const CENSUS = [
        CsvExport::REPORT_MONEY => [
            'Cobrado', 'Devuelto', 'Ingresos netos', 'Vendido', 'Pedidos cobrados', 'Valor medio del pedido',
            'Compradores', 'Compradores nuevos', 'Compradores recurrentes', 'Valor medio por cliente', 'Valor de vida medio', 'Gestiones posteriores',
        ],
        CsvExport::REPORT_CUSTOMERS => [
            'Cuentas nuevas', 'Con el correo verificado', 'Que han comprado alguna vez', 'Búsquedas en la puerta', 'Tecleadas',
            'Escaneos de carné', 'Encontradas', 'Clientes distintos buscados', 'Fichas abiertas', 'Visitas acreditadas',
            'Clientes con visita acreditada',
        ],
        CsvExport::REPORT_FUNNEL => [
            'Visitas a la web', 'Compras por la web o la app', 'Conversión', 'Cobrado en esas compras', 'Visitas identificadas',
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

    /**
     * Las cifras que llegan DESPUÉS del 27-09, por informe, con su tanda: también tecleadas a mano y también en su CSV.
     * La T2 (`#758`): las seis de «Ocupación». La T3a (`#759`): «Visitantes» (en la ocupación y en los clientes), «Pendiente
     * de cobrar en el parque» (la fila de «La señal») y «Tasa de respuesta».
     */
    private const CENSUS_SINCE = [
        CsvExport::REPORT_OCCUPANCY => [
            'Ocupación de las entradas', 'Fiestas por franja', 'Franjas llenas', 'Ingreso por plaza-hora', 'Anticipación',
            'Demanda sin hueco', 'Visitantes',
            // La T4 (§4.8.quater): la cartera —sus seis cifras— y su tabla por semana.
            'Vendido para los próximos 7 días', 'Vendido para los próximos 30 días', 'Vendido para los próximos 90 días',
            'Plazas vendidas para los próximos 7 días', 'Plazas vendidas para los próximos 30 días', 'Plazas vendidas para los próximos 90 días',
            'Lo ya vendido, por semana que viene',
        ],
        // TP·2 (`#792`): «Quién viene», las siete tablas (los repartos, sin celdas de 1 a 4).
        CsvExport::REPORT_CUSTOMERS => [
            'Visitantes', 'Compradores nuevos', 'Compradores recurrentes', 'Valor de vida medio',
            'La edad de quien reserva', 'Cuántos hijos ha declarado', 'La edad de sus hijos el día de la visita', 'Quién los declara',
            'Con quién viene', 'La edad de quien cumple', 'La edad de los invitados',
        ],
        CsvExport::REPORT_MONEY => ['Pendiente de cobrar en el parque'],
        CsvExport::REPORT_SURVEYS => ['Tasa de respuesta'],
        // T3a: el gráfico de las horas es tabla (antes su dato no estaba en ningún CSV), y los rechazados salen de «Calidad
        // del dato»: siguen en el CSV del marketing.
        CsvExport::REPORT_FUNNEL => ['Visitas por hora del parque', '00 h', 'Total rechazados'],
    ];

    /**
     * Los widgets de tarjetas del cuadro (T0b: todos pasan por `Metric`; T3a: todos eligen del catálogo). «Resumen» no está:
     * reusa las de su pestaña ({@see AnalyticsTabsTest}).
     */
    private const TILE_WIDGETS = [
        MoneyOverviewWidget::class, MoneyMoreWidget::class,
        OccupancyOverviewWidget::class, BookedMoreWidget::class,
        CustomersOverviewWidget::class, CustomersMoreWidget::class, RegistrationsWidget::class, GateWidget::class,
        TrafficWidget::class, TrafficMoreWidget::class, DataQualityWidget::class,
        PartiesOverviewWidget::class, PartiesMoreWidget::class,
        SurveysOverviewWidget::class, SurveysMoreWidget::class,
    ];

    /**
     * Las 44 del 27-09 y las que se añaden después, cada una con su tanda (T0c, `#756`: cinco —ya habían venido, primera
     * vez, dos o más días, cada cuánto vuelven y repiten por la web—; cómo se acreditó la visita va como detalle · T2,
     * `#758`: las seis de la ocupación · T3a, `#759`: visitantes, pendiente de cobrar en el parque y tasa de respuesta · T4: las
     * seis de la cartera, euros y plazas a 7, 30 y 90 días).
     */
    private const TILES = 44 + 5 + 6 + 3 + 6;

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

        foreach ([self::CENSUS, self::CENSUS_SINCE] as $census) {
            foreach ($census as $report => $labels) {
                $firstCells = array_map(static fn (array $row): string => (string) ($row[0] ?? ''), (new CsvExport)->build($report, ReportPeriod::ThisMonth->window())['rows']);

                foreach ($labels as $label) {
                    $this->assertContains($label, $firstCells, "«{$label}» ya no está en el CSV de «{$report}»: nada de lo medido se quita (§4.1.bis)");
                }
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

        $this->assertCount(self::TILES, $keys);
        $this->assertCount(self::TILES, array_unique($keys), 'cada tarjeta, una clave');
    }

    /** «Visitas acreditadas» dice cómo se acreditó cada una (`#756`): por carné, por búsqueda, y las de antes sin origen. */
    public function test_the_visits_tile_says_how_each_visit_was_registered(): void
    {
        $this->actingAs($this->admin());
        $visits = app(GateVisits::class);
        // Asimétrico a propósito (2 · 1 · 1): con cifras iguales, cambiar carné por búsqueda no se vería.
        $visits->register(User::factory()->create(), null, Carbon::parse('2026-06-03'), CustomerVisit::SOURCE_CARD);
        $visits->register(User::factory()->create(), null, Carbon::parse('2026-06-03'), CustomerVisit::SOURCE_CARD);
        $visits->register(User::factory()->create(), null, Carbon::parse('2026-06-04'), CustomerVisit::SOURCE_LOOKUP);
        $visits->register(User::factory()->create(), null, Carbon::parse('2026-06-05'));

        /** @var list<Stat> $stats */
        $stats = (new \ReflectionMethod(GateWidget::class, 'getStats'))->invoke(new GateWidget);
        $tile = collect($stats)->first(fn (Stat $s): bool => $s->getViewData()['metric']->key === 'customers.visits');

        $this->assertSame('2 por carné · 1 por búsqueda · 1 de antes, sin origen', $tile->getViewData()['metric']->detail);
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
