<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Services\Surveys\QuestionSchema;
use App\Filament\Analytics\BucketLabel;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **Las encuestas, al detalle** (`specs/encuestas.md` §4.4, T4): por día o semana de respuesta, por encuesta, y por
 * pregunta el reparto de cada una; y los últimos textos libres, que van SOLO en la pestaña: un texto libre puede
 * llevar un nombre, así que el CSV (que llama a `tablesFor()`) no los lleva.
 */
class SurveysBreakdownWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 14;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.tables';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $report = $this->surveys();
        /** @var list<array<string, mixed>> $surveys */
        $surveys = $report['surveys'];

        $tables = [
            $this->series($report['series'], (string) $report['window']['granularity']),
            $this->bySurvey($surveys),
        ];
        foreach ($surveys as $survey) {
            $tables[] = $this->questions($survey);
        }
        // Los textos libres, solo en la pestaña: el CSV fuerza la ventana y por ahí se reconoce.
        if ($this->forcedWindow === null) {
            foreach ($surveys as $survey) {
                $texts = $this->texts($survey);
                if ($texts !== null) {
                    $tables[] = $texts;
                }
            }
        }

        return [
            'heading' => __('admin.analytics.surveys.breakdown_heading'),
            'description' => __('admin.analytics.surveys.breakdown_note'),
            'tables' => $tables,
        ];
    }

    /**
     * @param  list<array{key: string, answered: int, declined: int, sent: int}>  $series
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function series(array $series, string $granularity): array
    {
        $rows = array_map(static fn (array $r): array => [
            BucketLabel::long($r['key'], $granularity),
            (string) $r['answered'],
            (string) $r['declined'],
            (string) $r['sent'],
        ], $series);
        $sum = static fn (string $field): int => (int) array_sum(array_column($series, $field));
        $rows[] = [__('admin.analytics.money.col.total'), (string) $sum('answered'), (string) $sum('declined'), (string) $sum('sent')];

        return [
            'heading' => BucketLabel::heading($granularity),
            'columns' => [
                BucketLabel::column($granularity),
                __('admin.analytics.surveys.col.answered'),
                __('admin.analytics.surveys.col.declined'),
                __('admin.analytics.surveys.col.sent'),
            ],
            'rows' => $rows,
            'wide' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $surveys
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function bySurvey(array $surveys): array
    {
        return [
            'heading' => __('admin.analytics.surveys.by_survey'),
            'columns' => [
                __('admin.analytics.surveys.col.survey'),
                __('admin.analytics.surveys.col.kind'),
                __('admin.analytics.surveys.col.state'),
                __('admin.analytics.surveys.col.sent'),
                __('admin.analytics.surveys.col.answered_internal'),
                __('admin.analytics.surveys.col.answered_external'),
                __('admin.analytics.surveys.col.declined'),
            ],
            'rows' => array_map(static fn (array $s): array => [
                (string) $s['name'],
                __('admin.analytics.surveys.kind.'.$s['kind']),
                $s['live'] ? __('admin.analytics.surveys.live') : __('admin.analytics.surveys.off'),
                (string) $s['sent'],
                (string) $s['answered_internal'],
                (string) $s['answered_external'],
                (string) $s['declined'],
            ], $surveys),
            'wide' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $survey
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function questions(array $survey): array
    {
        $rows = [];
        /** @var list<array<string, mixed>> $questions */
        $questions = $survey['questions'];
        foreach ($questions as $q) {
            if ($q['type'] === QuestionSchema::TYPE_TEXT) {
                $rows[] = [(string) $q['label'], __('admin.analytics.surveys.texts_count', ['n' => $q['n']]), (string) $q['n']];

                continue;
            }
            $n = (int) $q['n'];
            /** @var list<array{key: string, label: string, n: int}> $distribution */
            $distribution = $q['distribution'];
            foreach ($distribution as $option) {
                $rows[] = [
                    (string) $q['label'].($q['type'] === QuestionSchema::TYPE_SCALE && $q['mean'] !== null ? ' · '.__('admin.analytics.surveys.mean', ['mean' => number_format((float) $q['mean'], 1, ',', '.')]) : ''),
                    $option['label'],
                    $option['n'].' · '.self::percent($n > 0 ? (int) round($option['n'] / $n * 10000) : 0),
                ];
            }
        }

        return [
            'heading' => __('admin.analytics.surveys.by_question', ['survey' => $survey['name']]),
            'columns' => [__('admin.analytics.surveys.col.question'), __('admin.analytics.surveys.col.option'), __('admin.analytics.surveys.col.answers')],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $survey
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}|null
     */
    private function texts(array $survey): ?array
    {
        $rows = [];
        /** @var list<array<string, mixed>> $questions */
        $questions = $survey['questions'];
        foreach ($questions as $q) {
            /** @var list<string> $texts */
            $texts = $q['texts'];
            foreach ($texts as $text) {
                $rows[] = [(string) $q['label'], $text];
            }
        }

        if ($rows === []) {
            return null;
        }

        return [
            'heading' => __('admin.analytics.surveys.texts_heading', ['survey' => $survey['name']]),
            'columns' => [__('admin.analytics.surveys.col.question'), __('admin.analytics.surveys.col.text')],
            'rows' => $rows,
        ];
    }
}
