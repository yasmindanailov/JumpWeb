<?php

namespace App\Filament\Resources\Offers\Tables;

use App\Filament\Resources\Offers\OfferResource;
use App\Models\Offer;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Tabla de ofertas: miniatura + título (idioma activo) + estado. Fila clicable → edición.
 * Ordenada por posición (reordenable arrastrando = orden del carrusel del widget).
 */
class OfferTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label(__('admin.offers.col_image'))
                    ->disk(Offer::IMAGE_DISK)
                    ->height(46)
                    ->square(),

                TextColumn::make('title')
                    ->label(__('admin.offers.col_title'))
                    ->getStateUsing(fn (Offer $record): string => (string) ($record->tr('title') ?? '—'))
                    ->wrap(),

                TextColumn::make('is_active')
                    ->label(__('admin.offers.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.offers.active_yes')
                        : __('admin.offers.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordUrl(fn (Offer $record): string => OfferResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.offers.col_active')),
            ])
            ->toolbarActions([]);
    }
}
