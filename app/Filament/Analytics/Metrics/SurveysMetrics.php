<?php

namespace App\Filament\Analytics\Metrics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
use App\Filament\Analytics\SurveysReport;

/**
 * **Las cifras de la satisfacción** (`SurveysReport`; T4 de `encuestas.md` §4.4 y `#754`, anónimas): contestadas, la tasa
 * en la puerta (sobre lo ofrecido) y por correo (sobre lo mandado), mandadas, «No preguntadas» y la nota media —que no se
 * enseña con menos de cinco respuestas—. Y desde la T3a (`#759`) la TASA DE RESPUESTA: las dos tasas en una.
 */
final class SurveysMetrics extends MetricSet
{
    protected static function report(Window $window, Comparison $comparison): array
    {
        return SurveysReport::for($window, $comparison);
    }

    public static function from(array $report): array
    {
        /** @var array<string, int> $t */
        $t = $report['totals'];
        /** @var array<string, int> $p */
        $p = $report['previous'];
        /** @var array{survey: string, question: string, mean: ?float, n: int, suppressed: bool}|null $scale */
        $scale = $report['scale'];
        // `#754`: una media de menos de cinco respuestas no se enseña (con el registro de la puerta diría quién puntuó).
        $scaleValue = match (true) {
            $scale === null => __('admin.analytics.parties.none'),
            $scale['suppressed'] || $scale['mean'] === null => __('admin.analytics.surveys.fewer_than_min', ['min' => SurveysReport::MIN_CELL]),
            default => number_format($scale['mean'], 1, ',', '.').' / 5',
        };

        // Las tasas, con sus dos números del periodo y del comparado: en puntos y con sus intervalos (T0b, `#755`).
        return self::keyed([
            Metric::count('surveys.answered', __('admin.analytics.surveys.answered'), $t['answered'], $p['answered'], Polarity::UpIsGood, self::how('surveys.answered')),
            Metric::rate(
                'surveys.internal_rate', __('admin.analytics.surveys.internal_rate'), $t['answered_internal'], $t['offered'], $p['answered_internal'], $p['offered'], Polarity::UpIsGood, self::how('surveys.internal_rate'),
                detail: __('admin.analytics.surveys.internal_rate_hint', ['answered' => $t['answered_internal'], 'offered' => $t['offered'], 'visits' => $t['visits']]),
            ),
            Metric::rate(
                'surveys.external_rate', __('admin.analytics.surveys.external_rate'), $t['answered_external'], $t['sent'], $p['answered_external'], $p['sent'], Polarity::UpIsGood, self::how('surveys.external_rate'),
                detail: __('admin.analytics.surveys.external_rate_hint', ['answered' => $t['answered_external'], 'sent' => $t['sent']]),
            ),
            // T3a (`#759`, §4.1.bis): las dos tasas unidas —lo contestado entre lo pedido, en la puerta y por correo—; cada
            // una por separado sigue, plegada.
            Metric::rate(
                'surveys.response_rate', __('admin.analytics.surveys.response_rate'),
                $t['answered_internal'] + $t['answered_external'], $t['offered'] + $t['sent'],
                $p['answered_internal'] + $p['answered_external'], $p['offered'] + $p['sent'],
                Polarity::UpIsGood, self::how('surveys.response_rate'),
                detail: __('admin.analytics.surveys.response_rate_hint', ['answered' => $t['answered_internal'] + $t['answered_external'], 'asked' => $t['offered'] + $t['sent']]),
            ),
            Metric::count('surveys.sent', __('admin.analytics.surveys.sent'), $t['sent'], $p['sent'], Polarity::Neutral, self::how('surveys.sent')),
            Metric::count('surveys.declined', __('admin.analytics.surveys.declined'), $t['declined'], $p['declined'], Polarity::DownIsGood, self::how('surveys.declined')),
            Metric::text(
                'surveys.scale_mean',
                __('admin.analytics.surveys.scale_mean'),
                $scaleValue,
                self::how('surveys.scale_mean'),
                detail: $scale === null ? __('admin.analytics.surveys.scale_mean_none') : __('admin.analytics.surveys.scale_mean_hint', ['question' => $scale['question'], 'n' => $scale['n']]),
            ),
        ]);
    }
}
