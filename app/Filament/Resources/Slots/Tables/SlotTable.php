<?php

namespace App\Filament\Resources\Slots\Tables;

use App\Filament\Resources\Slots\SlotResource;
use App\Models\Slot;
use App\Models\Zone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.7 iter.3 — Tabla de franjas: zona, fecha, hora, aforo online, estado de venta y si
 * el aforo está ajustado a mano. Fila clicable → edición. Por defecto, solo las próximas
 * (de hoy en adelante) y ordenadas por fecha+hora.
 */
class SlotTable
{
    public static function configure(Table $table): Table
    {
        // Calculado una vez por render (sin caché estática que filtre entre tests).
        $packZoneIds = SlotResource::packZoneIds();

        return $table
            ->columns([
                TextColumn::make('zone')
                    ->label(__('admin.slots.col_zone'))
                    ->getStateUsing(fn (Slot $record): string => (string) ($record->zone?->tr('name') ?? '—'))
                    ->searchable(false),

                TextColumn::make('date')
                    ->label(__('admin.slots.col_date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label(__('admin.slots.col_time'))
                    ->getStateUsing(fn (Slot $record): string => substr((string) $record->start_time, 0, 5).' – '.substr((string) $record->end_time, 0, 5))
                    ->sortable(),

                TextColumn::make('online_capacity')
                    ->label(__('admin.slots.col_online_capacity'))
                    ->getStateUsing(fn (Slot $record): string => in_array((int) $record->zone_id, $packZoneIds, true)
                        ? __('admin.slots.by_cupo')
                        : (string) $record->online_capacity),

                TextColumn::make('online_sales_open')
                    ->label(__('admin.slots.col_sale'))
                    ->badge()
                    ->getStateUsing(fn (Slot $record): bool => $record->online_sales_open && $record->status !== Slot::STATUS_CLOSED)
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.slots.sale_open')
                        : __('admin.slots.sale_closed'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('capacity_overridden')
                    ->label(__('admin.slots.col_overridden'))
                    ->badge()
                    ->getStateUsing(fn (Slot $record): bool => (bool) $record->capacity_overridden)
                    ->formatStateUsing(fn (bool $state): string => $state ? __('admin.slots.overridden_yes') : '—')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('date')->orderBy('start_time'))
            ->recordUrl(fn (Slot $record): string => SlotResource::getUrl('edit', ['record' => $record]))
            ->filters([
                SelectFilter::make('zone_id')
                    ->label(__('admin.slots.col_zone'))
                    ->options(fn (): array => Zone::orderBy('position')->get()
                        ->mapWithKeys(fn (Zone $zone): array => [$zone->id => (string) $zone->tr('name')])
                        ->all()),

                TernaryFilter::make('online_sales_open')
                    ->label(__('admin.slots.col_sale')),

                Filter::make('upcoming')
                    ->label(__('admin.slots.filter_upcoming'))
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereDate('date', '>=', now()->toDateString())),
            ])
            ->toolbarActions([]);
    }
}
