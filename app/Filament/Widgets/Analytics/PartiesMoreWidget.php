<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\PartiesMetrics;

/**
 * **Más de la fiesta, plegado** (T3a, `#759`; §4.1.bis): las reservas con extras, lo que suman por reserva y cuántas listas
 * se completaron dentro del plazo. Lleva la nota del informe (qué es una fiesta aquí y de dónde sale su dinero), que antes
 * iba bajo el título de la pestaña.
 */
class PartiesMoreWidget extends MetricsWidget
{
    public const KEYS = ['parties.with_extras', 'parties.avg_extras', 'parties.on_time'];

    public const FOLDED = true;

    protected static ?int $sort = 14;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.parties.more_heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.analytics.parties.note');
    }

    protected function metrics(): array
    {
        return PartiesMetrics::for($this->window(), $this->comparison());
    }
}
