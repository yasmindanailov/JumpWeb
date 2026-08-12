<?php

namespace App\Filament\Resources\RateTypes\Tables;

use App\Filament\Resources\RateTypes\RateTypeResource;
use App\Models\RateType;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Fase 7.8 — Tabla de tarifas. Una fila por tarifa con su etiqueta, los días que la
 * activan, su prioridad, cuántos precios dependen de ella (informa de por qué no se podrá
 * borrar) y su estado. Fila clicable → edición. Ordenada por prioridad descendente, igual
 * que las resuelve `RateResolver` (la de mayor prioridad gana cuando coinciden en un día).
 */
class RateTypeTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label(__('admin.rate_types.col_label'))
                    ->getStateUsing(fn (RateType $record): string => (string) ($record->tr('label') ?? '—'))
                    ->description(fn (RateType $record): string => $record->key)
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('label->es', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%")),

                TextColumn::make('weekdays')
                    ->label(__('admin.rate_types.col_weekdays'))
                    ->getStateUsing(fn (RateType $record): string => self::weekdaysLabel($record)),

                TextColumn::make('priority')
                    ->label(__('admin.rate_types.col_priority'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                IconColumn::make('is_special')
                    ->label(__('admin.rate_types.col_special'))
                    ->boolean(),

                TextColumn::make('prices_count')
                    ->label(__('admin.rate_types.col_prices_count'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'primary' : 'gray')
                    ->tooltip(__('admin.rate_types.prices_count_hint')),

                TextColumn::make('is_active')
                    ->label(__('admin.rate_types.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.rate_types.active_yes')
                        : __('admin.rate_types.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('priority', 'desc')
            ->recordUrl(fn (RateType $record): string => RateTypeResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.rate_types.col_active')),
            ])
            ->toolbarActions([
                // Sin acciones masivas: el borrado es por tarifa, con guardas (defensa en profundidad).
            ]);
    }

    /** Días que activan la tarifa, en nombres cortos y orden europeo; "—" si es fallback. */
    private static function weekdaysLabel(RateType $record): string
    {
        $days = is_array($record->weekdays) ? $record->weekdays : [];
        if ($days === []) {
            return '—';
        }

        // Orden de lectura europeo (lunes..domingo) independientemente del guardado.
        // Comparación ESTRICTA igual que `RateResolver` (los weekdays son enteros): si un
        // dato fuera-de-banda guardara strings, la tabla deja de pintar el día en lugar de
        // divergir en silencio de lo que realmente aplica el resolver.
        $order = [1, 2, 3, 4, 5, 6, 0];
        $labels = [];
        foreach ($order as $day) {
            if (in_array($day, $days, true)) {
                $labels[] = __('admin.rate_types.weekdays_short.'.$day);
            }
        }

        return implode(', ', $labels);
    }
}
