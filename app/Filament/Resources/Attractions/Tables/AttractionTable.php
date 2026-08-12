<?php

namespace App\Filament\Resources\Attractions\Tables;

use App\Domain\Content\Models\Attraction;
use App\Filament\Resources\Attractions\AttractionResource;
use App\Models\Zone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Fase 7.9 (iter. 1) — Tabla de atracciones: zona, nombre (idioma activo), badge, estado.
 * Fila clicable → edición. Ordenada por posición. Reordenable arrastrando.
 *
 * El orden por posición es GLOBAL (no por zona): coincide con cómo la landing pinta las
 * atracciones de cada zona (`Zone::attractions` ordenadas por `position`), donde el orden
 * relativo dentro de una zona es lo que importa.
 */
class AttractionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('zone')
                    ->label(__('admin.attractions.col_zone'))
                    ->getStateUsing(fn (Attraction $record): string => (string) ($record->zone?->tr('name') ?? '—'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label(__('admin.attractions.col_name'))
                    ->getStateUsing(fn (Attraction $record): string => (string) ($record->tr('name') ?? '—'))
                    ->wrap(),

                TextColumn::make('badge')
                    ->label(__('admin.attractions.col_badge'))
                    ->getStateUsing(fn (Attraction $record): string => (string) ($record->tr('badge') ?? '—')),

                TextColumn::make('complement')
                    ->label(__('admin.attractions.col_complement'))
                    ->getStateUsing(fn (Attraction $record): string => (string) ($record->ticketType?->tr('name') ?? '—')),

                TextColumn::make('is_special')
                    ->label(__('admin.attractions.col_special'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.attractions.special_yes')
                        : __('admin.attractions.special_no'))
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),

                TextColumn::make('is_active')
                    ->label(__('admin.attractions.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.attractions.active_yes')
                        : __('admin.attractions.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordUrl(fn (Attraction $record): string => AttractionResource::getUrl('edit', ['record' => $record]))
            ->filters([
                SelectFilter::make('zone_id')
                    ->label(__('admin.attractions.col_zone'))
                    ->options(fn (): array => Zone::orderBy('position')->get()
                        ->mapWithKeys(fn (Zone $zone): array => [$zone->id => (string) $zone->tr('name')])
                        ->all()),

                TernaryFilter::make('is_active')
                    ->label(__('admin.attractions.col_active')),
            ])
            ->toolbarActions([]);
    }
}
