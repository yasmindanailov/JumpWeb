<?php

namespace App\Filament\Resources\ParkRules\Tables;

use App\Domain\Content\Models\VenueRule;
use App\Filament\Resources\ParkRules\ParkRuleResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Fase 7.9 (iter. 1) — Tabla de normas: nombre (idioma activo), estado. Fila clicable →
 * edición. Ordenada por posición. Reordenable arrastrando.
 */
class ParkRuleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.park_rules.col_name'))
                    ->getStateUsing(fn (VenueRule $record): string => (string) ($record->tr('name') ?? '—'))
                    ->wrap(),

                TextColumn::make('is_active')
                    ->label(__('admin.park_rules.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.park_rules.active_yes')
                        : __('admin.park_rules.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordUrl(fn (VenueRule $record): string => ParkRuleResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.park_rules.col_active')),
            ])
            ->toolbarActions([]);
    }
}
