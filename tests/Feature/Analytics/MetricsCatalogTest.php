<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Setting;
use App\Filament\Analytics\FunnelReport;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Metrics\CustomersMetrics;
use App\Filament\Analytics\Metrics\MarketingMetrics;
use App\Filament\Analytics\Metrics\MoneyMetrics;
use App\Filament\Analytics\Metrics\OccupancyMetrics;
use App\Filament\Analytics\Metrics\PartiesMetrics;
use App\Filament\Analytics\Metrics\SurveysMetrics;
use App\Filament\Analytics\MoneyReport;
use App\Filament\Analytics\OccupancyReport;
use App\Filament\Analytics\Polarity;
use App\Filament\Analytics\SurveysReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **El catálogo de cifras** (T3a de `specs/analitica-para-decidir.md` §4.13, `#759`): cada informe compone sus cifras UNA
 * vez, y las pestañas y «Resumen» eligen de aquí. Las tres cifras nuevas se prueban con la forma REAL de su informe (sobre
 * una base vacía) y los números puestos a mano; los rótulos, tecleados (`#734`).
 */
class MetricsCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        app()->setLocale('es');
    }

    /** «Pendiente de cobrar en el parque» es la fila de «La señal»: lo pendiente, no lo liquidado; sin cambio y sin signo. */
    public function test_pending_in_park_is_the_pending_row_of_the_deposit(): void
    {
        $report = MoneyReport::for(ReportPeriod::ThisMonth->window(), Comparison::Previous);
        $report['deposit'] = ['orders' => 3, 'pending' => 12500, 'settled' => 4000];

        $m = MoneyMetrics::from($report)['money.pending_in_park'];

        $this->assertSame('Pendiente de cobrar en el parque', $m->label);
        $this->assertSame('125 €', $m->displayValue());
        $this->assertSame('De 3 pedidos con señal', $m->detail);
        $this->assertSame(Polarity::Neutral, $m->polarity);
        $this->assertNull($m->previous, 'el informe no la trae del periodo comparado: sin cambio');
    }

    /** «Visitantes»: las plazas, con las reservas como base y sus cuadrados para la prueba (plazas en lotes). */
    public function test_visitors_are_units_in_batches_based_on_their_bookings(): void
    {
        $report = OccupancyReport::for(ReportPeriod::ThisMonth->window(), Comparison::Previous);
        $report['visitors'] = ['seats' => 45, 'lines' => 3, 'seats_sq' => 20 * 20 + 20 * 20 + 5 * 5];
        $report['previous'] = ['visitors' => 30, 'visitor_lines' => 2, 'visitors_sq' => 15 * 15 * 2] + $report['previous'];

        $m = OccupancyMetrics::from($report)['occupancy.visitors'];

        $this->assertSame('Visitantes', $m->label);
        $this->assertSame('45', $m->displayValue());
        $this->assertSame([3, 2], [$m->base, $m->previousBase], 'la base son las reservas, no las plazas');
        $this->assertSame([825, 450], [$m->squares, $m->previousSquares]);
        $this->assertSame('En 3 reservas', $m->detail);
    }

    /** «Tasa de respuesta»: lo contestado entre lo pedido, en la puerta y por correo SUMADOS; en puntos. */
    public function test_the_response_rate_adds_the_door_and_the_mail(): void
    {
        $report = SurveysReport::for(ReportPeriod::ThisMonth->window(), Comparison::Previous);
        $report['totals'] = ['answered_internal' => 3, 'offered' => 10, 'answered_external' => 2, 'sent' => 15] + $report['totals'];
        $report['previous'] = ['answered_internal' => 1, 'offered' => 10, 'answered_external' => 0, 'sent' => 10] + $report['previous'];

        $m = SurveysMetrics::from($report)['surveys.response_rate'];

        $this->assertSame('Tasa de respuesta', $m->label);
        $this->assertSame("20,0\u{00A0}%", $m->displayValue(), '5 de 25');
        $this->assertSame([5, 25, 1, 20], [$m->hits, $m->base, $m->previousHits, $m->previousBase]);
        $this->assertSame('5 contestadas de 25 pedidas (en la puerta y por correo)', $m->detail);
        $this->assertSame(Metric::UNIT_RATE, $m->unit);
    }

    /** La conversión lleva en su detalle lo que se plegó: las compras y lo cobrado. */
    public function test_the_conversion_tells_the_purchases_and_what_was_collected(): void
    {
        $report = FunnelReport::for(ReportPeriod::ThisMonth->window(), Comparison::Previous);
        $report['purchases'] = ['orders' => 2, 'revenue' => 5000] + $report['purchases'];

        $this->assertSame('2 compras por la web o la app · 50,00 € cobrados', MarketingMetrics::from($report)['traffic.conversion']->detail);

        $report['purchases'] = ['orders' => 0, 'revenue' => 0] + $report['purchases'];
        $this->assertSame('Ninguna compra por la web o la app', MarketingMetrics::from($report)['traffic.conversion']->detail);
    }

    /** Cada cifra del catálogo tiene clave única, y su «¿Cómo se calcula?» en los dos idiomas del panel. */
    public function test_every_figure_of_the_catalog_has_its_definition_in_both_languages(): void
    {
        $window = ReportPeriod::ThisMonth->window();
        $keys = [];

        foreach ([MoneyMetrics::class, OccupancyMetrics::class, CustomersMetrics::class, MarketingMetrics::class, PartiesMetrics::class, SurveysMetrics::class] as $set) {
            foreach ($set::for($window, Comparison::Previous) as $key => $metric) {
                $this->assertSame($key, $metric->key);
                $keys[] = $key;
                foreach (['es', 'zh_CN'] as $locale) {
                    $this->assertNotSame('admin.analytics.how.'.$key, trans('admin.analytics.how.'.$key, [], $locale), "«{$key}» sin «¿Cómo se calcula?» en {$locale}");
                }
            }
        }

        $this->assertCount(58, $keys, 'las 58 cifras del cuadro (44 del 27-09 + 5 de la T0c + 6 de la T2 + 3 de la T3a)');
        $this->assertSame($keys, array_values(array_unique($keys)), 'una clave, una cifra');
    }
}
