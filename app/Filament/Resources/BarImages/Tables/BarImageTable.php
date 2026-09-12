<?php

namespace App\Filament\Resources\BarImages\Tables;

use App\Domain\Content\Models\BarImage;
use App\Filament\Resources\BarImages\BarImageResource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Tabla de imágenes del bar: miniatura + tipo + texto alternativo + estado (`#536`).
 *
 * ⚠️ **El texto alternativo es una COLUMNA y no un detalle escondido en la edición**: es lo único
 * que un lector de pantalla va a leer de una carta que es una imagen, así que tiene que verse de un
 * vistazo que está puesto y que dice algo.
 *
 * Ordenada por posición y reordenable arrastrando — que es el orden en que la página publica las
 * caras de la carta, y el que decide **qué foto del local manda** cuando hay más de una activa.
 */
class BarImageTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label(__('admin.bar_images.col_image'))
                    ->disk(BarImage::IMAGE_DISK)
                    ->height(46),

                TextColumn::make('kind')
                    ->label(__('admin.bar_images.col_kind'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('admin.bar_images.kind.'.$state))
                    ->color(fn (string $state): string => $state === BarImage::KIND_VENUE ? 'gray' : 'primary'),

                TextColumn::make('alt')
                    ->label(__('admin.bar_images.col_alt'))
                    ->getStateUsing(fn (BarImage $record): string => (string) ($record->tr('alt') ?? '—'))
                    ->wrap(),

                TextColumn::make('is_active')
                    ->label(__('admin.bar_images.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.bar_images.active_yes')
                        : __('admin.bar_images.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordUrl(fn (BarImage $record): string => BarImageResource::getUrl('edit', ['record' => $record]))
            ->filters([
                SelectFilter::make('kind')
                    ->label(__('admin.bar_images.col_kind'))
                    ->options([
                        BarImage::KIND_MENU => __('admin.bar_images.kind.menu'),
                        BarImage::KIND_VENUE => __('admin.bar_images.kind.venue'),
                    ]),
                TernaryFilter::make('is_active')
                    ->label(__('admin.bar_images.col_active')),
            ])
            ->toolbarActions([]);
    }
}
