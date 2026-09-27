<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\PartiesMetrics;

/**
 * **La fiesta, arriba** (T3a de `analitica-para-decidir.md` §4.1.bis, `#759`; las cifras, T2 de `analitica-fiesta.md`): las
 * fiestas del periodo (la principal), lo vendido después de reservar, lo cobrado en el parque, los formularios completados,
 * las respuestas «sí» y los justificantes firmados. Las reservas con extras, su media y el plazo, plegados
 * ({@see PartiesMoreWidget}).
 */
class PartiesOverviewWidget extends MetricsWidget
{
    public const KEYS = ['parties.parties', 'parties.sold_after', 'parties.collected_in_park', 'parties.completed', 'parties.replies_yes', 'parties.signatures'];

    public const PRINCIPAL = 'parties.parties';

    protected static ?int $sort = 10;

    protected int|array|null $columns = 4;

    protected function metrics(): array
    {
        return PartiesMetrics::for($this->window(), $this->comparison());
    }
}
