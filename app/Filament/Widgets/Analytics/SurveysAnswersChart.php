<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Services\Surveys\QuestionSchema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Lo que contestan, como barras** (`specs/encuestas.md` §4.4, T4): el reparto de la primera pregunta con opciones
 * —o escala, o sí/no— de la primera encuesta de la pestaña (la interna viva, si la hay), con el nombre de la
 * encuesta y la pregunta en el título. Sin respuestas no hay barras. Su vista de tabla es «Por pregunta».
 */
class SurveysAnswersChart extends CategoryChart
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string|Htmlable|null
    {
        $first = $this->firstQuestion();

        return $first === null
            ? __('admin.analytics.surveys.answers_chart')
            : __('admin.analytics.surveys.answers_chart_of', ['survey' => $first['survey'], 'question' => $first['label']]);
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string}>}|null */
    protected function categories(): ?array
    {
        $first = $this->firstQuestion();
        if ($first === null) {
            return null;
        }

        return [
            'labels' => array_map(static fn (array $row): string => $row['label'], $first['distribution']),
            'datasets' => [[
                'label' => __('admin.analytics.surveys.col.answers'),
                'data' => array_map(static fn (array $row): int => $row['n'], $first['distribution']),
                'color' => MoneySeriesChart::COLORS['collected'],
            ]],
        ];
    }

    /** @return array{survey: string, label: string, distribution: list<array{key: string, label: string, n: int}>}|null */
    private function firstQuestion(): ?array
    {
        /** @var list<array<string, mixed>> $surveys */
        $surveys = $this->surveys()['surveys'];
        foreach ($surveys as $survey) {
            /** @var list<array<string, mixed>> $questions */
            $questions = $survey['questions'];
            foreach ($questions as $q) {
                if ($q['type'] !== QuestionSchema::TYPE_TEXT && (int) $q['n'] > 0) {
                    return ['survey' => (string) $survey['name'], 'label' => (string) $q['label'], 'distribution' => $q['distribution']];
                }
            }
        }

        return null;
    }
}
