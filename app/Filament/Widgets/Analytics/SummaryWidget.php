<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Metrics\MarketingMetrics;
use App\Filament\Analytics\Metrics\MoneyMetrics;
use App\Filament\Analytics\Metrics\OccupancyMetrics;
use App\Filament\Analytics\Metrics\SurveysMetrics;
use App\Filament\Pages\AnalyticsPage;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * **«Resumen»: las cifras clave, cada una con su pestaña** (T3a de `analitica-para-decidir.md` §4.5 y §4.13, `#759`). Son
 * LAS MISMAS de su pestaña —misma clave, misma definición, el mismo catálogo—: aquí no se compone ninguna. Cada una lleva
 * un enlace a la pestaña donde se explica.
 *
 * Siete de las ocho de §4.5: la cartera de los próximos 30 días llega con la T4. El veredicto y la frase (T3b), «lo que ha
 * cambiado» y los objetivos (T3c) y «Explícamelo con IA» (T3d), después.
 */
class SummaryWidget extends MetricsWidget
{
    public const MAX_TOP = 8;

    public const KEYS = ['money.net', 'occupancy.entries', 'occupancy.visitors', 'traffic.conversion', 'money.avg_order', 'money.returning', 'surveys.scale_mean'];

    /** Adónde lleva cada cifra: la pestaña donde está arriba. @var array<string, string> */
    public const TABS = [
        'money.net' => 'money',
        'occupancy.entries' => 'occupancy',
        'occupancy.visitors' => 'customers',
        'traffic.conversion' => 'marketing',
        'money.avg_order' => 'money',
        'money.returning' => 'customers',
        'surveys.scale_mean' => 'satisfaction',
    ];

    protected static ?int $sort = 1;

    protected int|array|null $columns = 4;

    protected function metrics(): array
    {
        $window = $this->window();
        $comparison = $this->comparison();

        return MoneyMetrics::for($window, $comparison)
            + OccupancyMetrics::for($window, $comparison)
            + MarketingMetrics::for($window, $comparison)
            + SurveysMetrics::for($window, $comparison);
    }

    protected function tile(Metric $metric): Stat
    {
        $tab = self::TABS[$metric->key];

        return $this->metric($metric, [
            'url' => AnalyticsPage::getUrl([AnalyticsPage::TAB_QUERY_KEY => $tab]),
            'label' => __('admin.analytics.summary.see', ['tab' => __('admin.analytics.tabs.'.$tab)]),
        ]);
    }
}
