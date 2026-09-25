<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * **Las encuestas, en seis cifras** (`specs/encuestas.md` §4.4, T4): contestadas, la tasa interna (en la puerta,
 * sobre las visitas acreditadas) y la externa (por correo, sobre las mandadas), mandadas, declinadas y la media de
 * la primera escala. Las cuatro de recuento con su variación frente al periodo de comparación.
 */
class SurveysOverviewWidget extends StatsOverviewWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.surveys.heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.analytics.surveys.note');
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $report = $this->surveys();
        /** @var array<string, int> $t */
        $t = $report['totals'];
        /** @var array<string, int> $p */
        $p = $report['previous'];
        /** @var array{survey: string, question: string, mean: float, n: int}|null $scale */
        $scale = $report['scale'];

        return [
            $this->countStat(__('admin.analytics.surveys.answered'), $t['answered'], $p['answered']),
            Stat::make(__('admin.analytics.surveys.internal_rate'), self::percent($t['internal_rate_bp']))
                ->description(__('admin.analytics.surveys.internal_rate_hint', ['answered' => $t['answered_internal'], 'offered' => $t['offered'], 'visits' => $t['visits']]))
                ->color('gray'),
            Stat::make(__('admin.analytics.surveys.external_rate'), self::percent($t['external_rate_bp']))
                ->description(__('admin.analytics.surveys.external_rate_hint', ['answered' => $t['answered_external'], 'sent' => $t['sent']]))
                ->color('gray'),
            $this->countStat(__('admin.analytics.surveys.sent'), $t['sent'], $p['sent']),
            $this->countStat(__('admin.analytics.surveys.declined'), $t['declined'], $p['declined'], upIsGood: false),
            Stat::make(
                __('admin.analytics.surveys.scale_mean'),
                $scale === null ? __('admin.analytics.parties.none') : number_format($scale['mean'], 1, ',', '.').' / 5',
            )
                ->description($scale === null ? __('admin.analytics.surveys.scale_mean_none') : __('admin.analytics.surveys.scale_mean_hint', ['question' => $scale['question'], 'n' => $scale['n']]))
                ->color('gray'),
        ];
    }
}
