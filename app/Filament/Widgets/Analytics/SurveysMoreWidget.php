<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\SurveysMetrics;

/**
 * **Más de las encuestas, plegado** (T3a, `#759`; §4.1.bis): contestadas, cada tasa por separado —en la puerta y por
 * correo—, los correos mandados y «No preguntadas». Lleva la nota del informe (anónimas, el mínimo de cinco, sobre qué se
 * calcula cada tasa), que antes iba bajo el título de la pestaña.
 */
class SurveysMoreWidget extends MetricsWidget
{
    public const KEYS = ['surveys.answered', 'surveys.internal_rate', 'surveys.external_rate', 'surveys.sent', 'surveys.declined'];

    public const FOLDED = true;

    protected static ?int $sort = 12;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.surveys.more_heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.analytics.surveys.note');
    }

    protected function metrics(): array
    {
        return SurveysMetrics::for($this->window(), $this->comparison());
    }
}
