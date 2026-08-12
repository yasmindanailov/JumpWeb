<?php

namespace App\Filament\Resources\SlotTemplates\Tables;

use App\Filament\Resources\SlotTemplates\SlotTemplateResource;
use App\Models\SlotTemplate;
use App\Models\Zone;
use App\Support\Duration;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.7 iter.3 — Tabla de plantillas: zona, día de la semana, hora, duración, aforos y
 * estado. Fila clicable → edición. Ordenada por zona y día.
 */
class SlotTemplateTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('zone')
                    ->label(__('admin.slot_templates.col_zone'))
                    ->getStateUsing(fn (SlotTemplate $record): string => (string) ($record->zone?->tr('name') ?? '—')),

                TextColumn::make('weekday')
                    ->label(__('admin.slot_templates.col_weekday'))
                    ->getStateUsing(fn (SlotTemplate $record): string => __('admin.rate_types.weekdays.'.$record->weekday)),

                TextColumn::make('start_time')
                    ->label(__('admin.slot_templates.col_time'))
                    ->getStateUsing(fn (SlotTemplate $record): string => substr((string) $record->start_time, 0, 5)
                        .' – '.Carbon::parse((string) $record->start_time)->addMinutes($record->duration_min)->format('H:i')),

                TextColumn::make('duration_min')
                    ->label(__('admin.slot_templates.col_duration'))
                    ->getStateUsing(fn (SlotTemplate $record): string => Duration::formatHumane($record->duration_min)),

                TextColumn::make('capacity')
                    ->label(__('admin.slot_templates.col_capacity')),

                TextColumn::make('online_capacity')
                    ->label(__('admin.slot_templates.col_online_capacity')),

                TextColumn::make('is_active')
                    ->label(__('admin.slot_templates.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.slot_templates.active_yes')
                        : __('admin.slot_templates.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('zone_id')->orderBy('weekday')->orderBy('start_time'))
            ->recordUrl(fn (SlotTemplate $record): string => SlotTemplateResource::getUrl('edit', ['record' => $record]))
            ->filters([
                SelectFilter::make('zone_id')
                    ->label(__('admin.slot_templates.col_zone'))
                    ->options(fn (): array => Zone::orderBy('position')->get()
                        ->mapWithKeys(fn (Zone $zone): array => [$zone->id => (string) $zone->tr('name')])
                        ->all()),

                SelectFilter::make('weekday')
                    ->label(__('admin.slot_templates.col_weekday'))
                    ->options([
                        1 => __('admin.rate_types.weekdays.1'),
                        2 => __('admin.rate_types.weekdays.2'),
                        3 => __('admin.rate_types.weekdays.3'),
                        4 => __('admin.rate_types.weekdays.4'),
                        5 => __('admin.rate_types.weekdays.5'),
                        6 => __('admin.rate_types.weekdays.6'),
                        0 => __('admin.rate_types.weekdays.0'),
                    ]),

                TernaryFilter::make('is_active')
                    ->label(__('admin.slot_templates.col_active')),
            ])
            ->toolbarActions([]);
    }
}
