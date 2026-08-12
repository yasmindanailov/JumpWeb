<?php

namespace App\Filament\Resources\Zones\Tables;

use App\Filament\Resources\Zones\ZoneResource;
use App\Models\Zone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Fase 7.9 (adelanto) — Tabla de zonas: nombre, slug, cupo por zona (o "global"), estado.
 * Fila clicable → edición. Ordenada por posición. Reordenable arrastrando.
 */
class ZoneTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.zones.col_name'))
                    ->getStateUsing(fn (Zone $record): string => (string) ($record->tr('name') ?? '—')),

                TextColumn::make('slug')
                    ->label(__('admin.zones.col_slug'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('max_guests_per_slot')
                    ->label(__('admin.zones.col_cupo'))
                    ->getStateUsing(fn (Zone $record): string => $record->max_guests_per_slot !== null
                        ? (string) $record->max_guests_per_slot
                        : __('admin.zones.cupo_global')),

                TextColumn::make('is_active')
                    ->label(__('admin.zones.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.zones.active_yes')
                        : __('admin.zones.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordUrl(fn (Zone $record): string => ZoneResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.zones.col_active')),
            ])
            ->toolbarActions([]);
    }
}
