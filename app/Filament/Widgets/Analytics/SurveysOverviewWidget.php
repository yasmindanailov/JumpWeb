<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
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

        // Las dos tasas, con sus dos números del periodo y del comparado: en puntos y con sus intervalos (T0b, `#755`).
        return [
            $this->metric(Metric::count('surveys.answered', __('admin.analytics.surveys.answered'), $t['answered'], $p['answered'], Polarity::UpIsGood, self::how('surveys.answered'))),
            $this->metric(Metric::rate(
                'surveys.internal_rate', __('admin.analytics.surveys.internal_rate'), $t['answered_internal'], $t['offered'], $p['answered_internal'], $p['offered'], Polarity::UpIsGood, self::how('surveys.internal_rate'),
                detail: __('admin.analytics.surveys.internal_rate_hint', ['answered' => $t['answered_internal'], 'offered' => $t['offered'], 'visits' => $t['visits']]),
            )),
            $this->metric(Metric::rate(
                'surveys.external_rate', __('admin.analytics.surveys.external_rate'), $t['answered_external'], $t['sent'], $p['answered_external'], $p['sent'], Polarity::UpIsGood, self::how('surveys.external_rate'),
                detail: __('admin.analytics.surveys.external_rate_hint', ['answered' => $t['answered_external'], 'sent' => $t['sent']]),
            )),
            $this->metric(Metric::count('surveys.sent', __('admin.analytics.surveys.sent'), $t['sent'], $p['sent'], Polarity::Neutral, self::how('surveys.sent'))),
            $this->metric(Metric::count('surveys.declined', __('admin.analytics.surveys.declined'), $t['declined'], $p['declined'], Polarity::DownIsGood, self::how('surveys.declined'))),
            $this->metric(Metric::text(
                'surveys.scale_mean',
                __('admin.analytics.surveys.scale_mean'),
                $scale === null ? __('admin.analytics.parties.none') : number_format($scale['mean'], 1, ',', '.').' / 5',
                self::how('surveys.scale_mean'),
                detail: $scale === null ? __('admin.analytics.surveys.scale_mean_none') : __('admin.analytics.surveys.scale_mean_hint', ['question' => $scale['question'], 'n' => $scale['n']]),
            )),
        ];
    }
}
