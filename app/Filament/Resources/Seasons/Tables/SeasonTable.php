<?php

namespace App\Filament\Resources\Seasons\Tables;

use App\Filament\Resources\Seasons\SeasonResource;
use App\Models\Season;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Fase 7.7 — Tabla de temporadas: nombre, rango de fechas, horario y estado. Fila clicable
 * → edición. Ordenada por fecha de inicio.
 */
class SeasonTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.seasons.col_name'))
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label(__('admin.seasons.col_range'))
                    ->getStateUsing(fn (Season $record): string => $record->start_date?->format('d/m/Y').' – '.$record->end_date?->format('d/m/Y')),

                TextColumn::make('open_time')
                    ->label(__('admin.seasons.col_hours'))
                    ->getStateUsing(fn (Season $record): string => substr((string) $record->open_time, 0, 5).' – '.substr((string) $record->close_time, 0, 5)),

                TextColumn::make('is_active')
                    ->label(__('admin.seasons.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.seasons.active_yes')
                        : __('admin.seasons.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('start_date', 'desc')
            ->recordUrl(fn (Season $record): string => SeasonResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.seasons.col_active')),
            ])
            ->toolbarActions([]);
    }
}
