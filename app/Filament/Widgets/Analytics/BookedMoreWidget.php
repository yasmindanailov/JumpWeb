<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\BookedMetrics;

/**
 * **Lo ya vendido: 7, 30 y 90 días, plegado en «Ocupación»** (T4 de `analitica-para-decidir.md` §4.8.quater): las seis cifras
 * de la cartera —euros y plazas a cada horizonte—, cada una con lo vendido a estas alturas en su detalle. «Resumen» lleva la de
 * 30 días; el gráfico por semana va encima. No depende del periodo del filtro: mira hacia delante.
 */
class BookedMoreWidget extends MetricsWidget
{
    public const KEYS = BookedMetrics::KEYS;

    public const FOLDED = true;

    protected static ?int $sort = 13;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.booked.more_heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.analytics.booked.more_note');
    }

    protected function metrics(): array
    {
        return BookedMetrics::for();
    }
}
