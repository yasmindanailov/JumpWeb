<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Domain\Content\Models\Testimonial;
use App\Filament\Resources\Testimonials\TestimonialResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** Tabla de opiniones propias (`#490`). Molde de `FaqTable`: reordenable, fila clicable. */
class TestimonialTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author')->label(__('admin.testimonials.col_author')),

                TextColumn::make('text')
                    ->label(__('admin.testimonials.col_text'))
                    ->getStateUsing(fn (Testimonial $record): string => (string) ($record->tr('text') ?? '—'))
                    ->limit(70)
                    ->wrap(),

                TextColumn::make('rating')
                    ->label(__('admin.testimonials.col_rating'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : str_repeat('★', $state)),

                // De dónde es y en qué páginas sale (`#771`): lo que el parque decide al elegir.
                TextColumn::make('origin')
                    ->label(__('admin.testimonials.col_origin'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('admin.testimonials.origins.'.$state))
                    ->color(fn (string $state): string => $state === Testimonial::ORIGIN_GOOGLE ? 'info' : 'gray'),

                TextColumn::make('tags')
                    ->label(__('admin.testimonials.col_tags'))
                    ->badge()
                    ->placeholder(__('admin.testimonials.tags_none')),

                // Lo que de verdad pasa con ella: una copiada que Google enseñaba TRADUCIDA no se publica aunque esté
                // activa (`#874`), y la columna lo dice en vez de prometer un «Activa» que no sale en ninguna página.
                TextColumn::make('is_active')
                    ->label(__('admin.testimonials.col_active'))
                    ->badge()
                    ->getStateUsing(fn (Testimonial $record): string => $record->translated ? 'translated' : ($record->is_active ? 'yes' : 'no'))
                    ->formatStateUsing(fn (string $state): string => __('admin.testimonials.active_'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'yes' => 'success',
                        'translated' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordUrl(fn (Testimonial $record): string => TestimonialResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin.testimonials.col_active')),
                SelectFilter::make('origin')
                    ->label(__('admin.testimonials.col_origin'))
                    ->options([
                        Testimonial::ORIGIN_GOOGLE => __('admin.testimonials.origins.google'),
                        Testimonial::ORIGIN_OWN => __('admin.testimonials.origins.own'),
                    ]),
            ])
            ->toolbarActions([]);
    }
}
