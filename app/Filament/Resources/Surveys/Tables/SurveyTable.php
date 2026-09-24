<?php

namespace App\Filament\Resources\Surveys\Tables;

use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Surveys\SurveyResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * **La lista de encuestas** (`docs/specs/encuestas.md` §4.4, T1): nombre, clave, clase, estado real (viva ·
 * programada · terminada · apagada), respuestas recibidas y ventana.
 */
class SurveyTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn ($query) => $query->withCount('responses'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.surveys.col_name'))
                    ->getStateUsing(static fn (Survey $record): string => $record->displayName())
                    ->wrap(),
                TextColumn::make('key')
                    ->label(__('admin.surveys.col_key'))
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('kind')
                    ->label(__('admin.surveys.col_kind'))
                    ->badge()
                    ->formatStateUsing(static fn (string $state): string => __('admin.surveys.kind.'.$state))
                    ->color(static fn (string $state): string => $state === Survey::KIND_INTERNAL ? 'info' : 'primary'),
                TextColumn::make('active')
                    ->label(__('admin.surveys.col_state'))
                    ->badge()
                    ->getStateUsing(static fn (Survey $record): string => self::state($record))
                    ->formatStateUsing(static fn (string $state): string => __('admin.surveys.state.'.$state))
                    ->color(static fn (string $state): string => match ($state) {
                        'running' => 'success',
                        'scheduled' => 'info',
                        'finished' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('responses_count')
                    ->label(__('admin.surveys.col_responses'))
                    ->numeric(),
                TextColumn::make('starts_at')
                    ->label(__('admin.surveys.col_window'))
                    ->getStateUsing(static fn (Survey $record): string => self::window($record)),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Survey $record): string => SurveyResource::getUrl('edit', ['record' => $record]))
            ->filters([
                SelectFilter::make('kind')
                    ->label(__('admin.surveys.col_kind'))
                    ->options([
                        Survey::KIND_INTERNAL => __('admin.surveys.kind.internal'),
                        Survey::KIND_EXTERNAL => __('admin.surveys.kind.external'),
                    ]),
                TernaryFilter::make('active')->label(__('admin.surveys.field_active')),
            ])
            ->toolbarActions([]);
    }

    public static function state(Survey $record): string
    {
        if ($record->isRunning()) {
            return 'running';
        }
        if (! $record->active) {
            return 'inactive';
        }

        return $record->starts_at !== null && $record->starts_at->isFuture() ? 'scheduled' : 'finished';
    }

    public static function window(Survey $record): string
    {
        $from = $record->starts_at === null ? '—' : DisplayTime::format($record->starts_at, 'd/m/Y H:i');
        $to = $record->ends_at === null ? '—' : DisplayTime::format($record->ends_at, 'd/m/Y H:i');

        return $from.' → '.$to;
    }
}
