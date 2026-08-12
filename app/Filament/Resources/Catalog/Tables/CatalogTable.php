<?php

namespace App\Filament\Resources\Catalog\Tables;

use App\Filament\Resources\Catalog\CatalogResource;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\Duration;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.6 — Tabla del catálogo. Una fila por producto (entrada/pack/complemento),
 * con su tipo, zona, duración, precio de referencia (SOLO LECTURA) y estado de
 * visibilidad/venta de un vistazo. Fila clicable → edición. Filtros por tipo, zona
 * y estado (activo / vendible).
 */
class CatalogTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.catalog.col_name'))
                    // `name` es JSON i18n → se muestra/busca/ordena por la clave es.
                    ->getStateUsing(fn (TicketType $record): string => (string) ($record->tr('name') ?? '—'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('name->es', 'like', "%{$search}%"))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('name->es', $direction))
                    ->limit(40),

                TextColumn::make('type')
                    ->label(__('admin.catalog.col_type'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => __('admin.catalog.types.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        TicketType::TYPE_PACK => 'warning',
                        TicketType::TYPE_ADDON => 'gray',
                        default => 'primary',
                    }),

                TextColumn::make('zone')
                    ->label(__('admin.catalog.col_zone'))
                    ->getStateUsing(fn (TicketType $record): string => $record->zone?->tr('name') ?? '—')
                    ->placeholder('—'),

                TextColumn::make('duration_min')
                    ->label(__('admin.catalog.col_duration'))
                    ->getStateUsing(fn (TicketType $record): string => self::durationLabel($record)),

                TextColumn::make('price')
                    ->label(__('admin.catalog.col_price'))
                    ->getStateUsing(fn (TicketType $record): string => self::priceLabel($record))
                    ->tooltip(__('admin.catalog.price_readonly_hint')),

                IconColumn::make('featured')
                    ->label(__('admin.catalog.col_featured'))
                    ->boolean(),

                TextColumn::make('is_active')
                    ->label(__('admin.catalog.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('admin.catalog.active_yes') : __('admin.catalog.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('is_sellable')
                    ->label(__('admin.catalog.col_sellable'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('admin.catalog.sellable_yes') : __('admin.catalog.sellable_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('position')
                    ->label(__('admin.catalog.col_position'))
                    ->sortable(),
            ])
            ->defaultSort('position')
            // El ORDEN de aparición (web/sidebar) se ajusta arrastrando las filas; actualiza
            // la columna `position` (reemplaza al antiguo campo numérico de la ficha de edición).
            ->reorderable('position')
            ->recordUrl(fn (TicketType $record): string => CatalogResource::getUrl('edit', ['record' => $record]))
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.catalog.col_type'))
                    ->options([
                        TicketType::TYPE_ENTRY => __('admin.catalog.types.'.TicketType::TYPE_ENTRY),
                        TicketType::TYPE_PACK => __('admin.catalog.types.'.TicketType::TYPE_PACK),
                        TicketType::TYPE_ADDON => __('admin.catalog.types.'.TicketType::TYPE_ADDON),
                    ]),

                SelectFilter::make('zone_id')
                    ->label(__('admin.catalog.col_zone'))
                    ->options(fn (): array => Zone::orderBy('position')->get()
                        ->mapWithKeys(fn (Zone $zone): array => [$zone->id => (string) $zone->tr('name')])
                        ->all()),

                TernaryFilter::make('is_active')
                    ->label(__('admin.catalog.col_active')),

                TernaryFilter::make('is_sellable')
                    ->label(__('admin.catalog.col_sellable')),
            ])
            ->toolbarActions([
                // Sin acciones masivas: el borrado/desactivación es por producto (defensa en profundidad).
            ]);
    }

    /** Duración legible: "1h 30min" / "Ilimitada" (entradas) o "—" (complementos sin duración). */
    private static function durationLabel(TicketType $record): string
    {
        if ($record->isAddon()) {
            return '—';
        }

        if ($record->isUnlimited()) {
            return __('admin.catalog.duration_unlimited');
        }

        return Duration::formatHumane((int) $record->duration_min);
    }

    /**
     * Precio de referencia (SOLO LECTURA, se edita en 7.8): el de la tarifa normal, o
     * "desde X €" si varía por día. "—" si el producto aún no tiene ningún precio.
     */
    private static function priceLabel(TicketType $record): string
    {
        if ($record->prices->isEmpty()) {
            return '—';
        }

        $euros = number_format($record->displayPriceCents() / 100, 2, ',', '.').' €';

        return $record->priceVaries()
            ? __('admin.catalog.price_from', ['amount' => $euros])
            : $euros;
    }
}
