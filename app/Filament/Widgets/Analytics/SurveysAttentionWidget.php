<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **«Por atender»** (`specs/encuestas.md` §4.4, T4; `[DECIDIDO owner]` §7·1): las respuestas de los últimos 30 días con
 * una escala ≤ 2 —el día, el canal, la encuesta, la nota, el texto y el enlace a la ficha del cliente—. Es el
 * valor de atar la respuesta a la persona: una mala visita se puede llamar y arreglar; un agregado no.
 *
 * ⚠️ **La persona Y su texto salen SOLO con `customers.insights`** (el permiso de la 360; `#742`): sin él, la fila va
 * sin nadie y sin lo que escribió, porque un texto libre es la persona hablando y puede llevar un nombre. No depende
 * del periodo del filtro: es lo que hay que atender HOY.
 */
class SurveysAttentionWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    public const PERMISSION_PERSON = 'customers.insights';

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.attention';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $showPerson = auth()->user()?->hasPermission(self::PERMISSION_PERSON) ?? false;
        /** @var list<array{response_id: int, on: string, channel: string, survey: string, score: int, text: ?string, user_id: ?int, user_name: ?string}> $attention */
        $attention = $this->surveys()['attention'];

        $rows = [];
        foreach ($attention as $row) {
            $rows[] = [
                'on' => DisplayTime::format($row['on'], 'd/m/Y'),
                'channel' => __('admin.analytics.surveys.channel.'.$row['channel']),
                'survey' => $row['survey'],
                'score' => $row['score'].' / 5',
                'text' => $showPerson ? (string) ($row['text'] ?? '') : '',
                'person' => $showPerson && $row['user_id'] !== null ? (string) ($row['user_name'] ?? '#'.$row['user_id']) : null,
                'url' => $showPerson && $row['user_id'] !== null ? UserResource::getUrl('view', ['record' => $row['user_id']]) : null,
            ];
        }

        return [
            'heading' => __('admin.analytics.surveys.attention_heading'),
            'description' => $showPerson ? __('admin.analytics.surveys.attention_note') : __('admin.analytics.surveys.attention_note_no_person'),
            'columns' => [
                __('admin.analytics.surveys.col.day'),
                __('admin.analytics.surveys.col.channel'),
                __('admin.analytics.surveys.col.survey'),
                __('admin.analytics.surveys.col.score'),
                __('admin.analytics.surveys.col.text'),
                __('admin.analytics.surveys.col.customer'),
            ],
            'rows' => $rows,
        ];
    }
}
