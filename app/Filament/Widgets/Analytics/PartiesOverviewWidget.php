<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * **La fiesta, en nueve cifras** (`specs/analitica-fiesta.md` §4.3, T2): las fiestas del periodo, lo vendido después
 * de reservar y lo cobrado en el parque, las reservas con extras y su media, los formularios completados y cuántos
 * dentro del plazo, las respuestas «sí» de la invitación y los justificantes firmados. Cada una con su variación
 * frente al periodo de comparación, salvo el plazo, que es un porcentaje del propio periodo.
 */
class PartiesOverviewWidget extends StatsOverviewWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.parties.heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.analytics.parties.note');
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $report = $this->parties();
        /** @var array<string, mixed> $m */
        $m = $report['money'];
        /** @var array<string, int> $f */
        $f = $report['forms'];
        /** @var array<string, int> $i */
        $i = $report['invitations'];
        /** @var array<string, int> $a */
        $a = $report['authorizations'];
        /** @var array<string, int> $p */
        $p = $report['previous'];
        $parties = (int) $report['parties'];

        // El dinero de la fiesta se sostiene en sus FIESTAS (y la media de extras, en las que los compraron); el plazo es
        // una TASA de los completados (T0b, `#755`). El periodo comparado no trae el plazo: va sin cambio, como antes.
        return [
            $this->metric(Metric::count('parties.parties', __('admin.analytics.parties.parties'), $parties, $p['parties'], Polarity::UpIsGood, self::how('parties.parties'))),
            $this->metric(Metric::money('parties.sold_after', __('admin.analytics.parties.sold_after'), (int) $m['sold_after_booking'], $p['sold_after_booking'], $parties, $p['parties'], Polarity::UpIsGood, self::how('parties.sold_after'), squares: (int) $m['sold_after_sq'], previousSquares: $p['sold_after_sq'])),
            $this->metric(Metric::money('parties.collected_in_park', __('admin.analytics.parties.collected_in_park'), (int) $m['collected_in_park'], $p['collected_in_park'], $parties, $p['parties'], Polarity::UpIsGood, self::how('parties.collected_in_park'), squares: (int) $m['collected_in_park_sq'], previousSquares: $p['collected_in_park_sq'])),
            $this->metric(Metric::count('parties.with_extras', __('admin.analytics.parties.with_extras'), (int) $m['with_extras'], $p['with_extras'], Polarity::UpIsGood, self::how('parties.with_extras'), display: self::countOf((int) $m['with_extras'], $parties))),
            $this->metric(Metric::money('parties.avg_extras', __('admin.analytics.parties.avg_extras'), (int) $m['avg_extras'], $p['avg_extras'], (int) $m['with_extras'], $p['with_extras'], Polarity::UpIsGood, self::how('parties.avg_extras'), squares: (int) $m['extras_sq'], previousSquares: $p['extras_sq'], mean: true)),
            $this->metric(Metric::count('parties.completed', __('admin.analytics.parties.completed'), $f['completed'], $p['completed'], Polarity::UpIsGood, self::how('parties.completed'), display: self::countOf($f['completed'], $parties))),
            $this->metric(Metric::rate('parties.on_time', __('admin.analytics.parties.on_time'), $f['on_time'], $f['completed'], null, null, Polarity::UpIsGood, self::how('parties.on_time', ['hours' => $f['cutoff_hours']]))),
            $this->metric(Metric::count('parties.replies_yes', __('admin.analytics.parties.replies_yes'), $i['replies_yes'], $p['replies_yes'], Polarity::UpIsGood, self::how('parties.replies_yes'))),
            $this->metric(Metric::count('parties.signatures', __('admin.analytics.parties.signatures'), $a['signatures'], $p['signatures'], Polarity::UpIsGood, self::how('parties.signatures'))),
        ];
    }

    /** «12 · 80,0 %»: el recuento y lo que supone de las fiestas del periodo. */
    private static function countOf(int $count, int $parties): string
    {
        return $count.' · '.self::percent($parties > 0 ? (int) round($count / $parties * 10000) : 0);
    }
}
