<?php

namespace App\Filament\Widgets\Analytics;

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

        return [
            $this->countStat(__('admin.analytics.parties.parties'), $parties, $p['parties']),
            $this->moneyStat(__('admin.analytics.parties.sold_after'), (int) $m['sold_after_booking'], $p['sold_after_booking']),
            $this->moneyStat(__('admin.analytics.parties.collected_in_park'), (int) $m['collected_in_park'], $p['collected_in_park']),
            $this->countStat(__('admin.analytics.parties.with_extras'), (int) $m['with_extras'], $p['with_extras'])
                ->value(self::countOf((int) $m['with_extras'], $parties)),
            $this->moneyStat(__('admin.analytics.parties.avg_extras'), (int) $m['avg_extras'], $p['avg_extras']),
            $this->countStat(__('admin.analytics.parties.completed'), $f['completed'], $p['completed'])
                ->value(self::countOf($f['completed'], $parties)),
            Stat::make(__('admin.analytics.parties.on_time'), self::percent($f['on_time_bp']))
                ->description(__('admin.analytics.parties.on_time_hint', ['hours' => $f['cutoff_hours']]))
                ->color('gray'),
            $this->countStat(__('admin.analytics.parties.replies_yes'), $i['replies_yes'], $p['replies_yes']),
            $this->countStat(__('admin.analytics.parties.signatures'), $a['signatures'], $p['signatures']),
        ];
    }

    /** «12 · 80,0 %»: el recuento y lo que supone de las fiestas del periodo. */
    private static function countOf(int $count, int $parties): string
    {
        return $count.' · '.self::percent($parties > 0 ? (int) round($count / $parties * 10000) : 0);
    }
}
