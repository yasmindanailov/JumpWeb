<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\SurveysMetrics;

/**
 * **La satisfacción, arriba** (T3a de `analitica-para-decidir.md` §4.1.bis, `#759`; las cifras, T4 de `encuestas.md` y
 * `#754`): la nota media (la principal) y la tasa de respuesta, las dos tasas en una. Lo demás, plegado
 * ({@see SurveysMoreWidget}). La nota de Google, las notas bajas y si volvieron, arriba, con la T8.
 */
class SurveysOverviewWidget extends MetricsWidget
{
    public const KEYS = ['surveys.scale_mean', 'surveys.response_rate'];

    public const PRINCIPAL = 'surveys.scale_mean';

    protected static ?int $sort = 10;

    protected function metrics(): array
    {
        return SurveysMetrics::for($this->window(), $this->comparison());
    }
}
