<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Domain\Content\Models\Testimonial;
use App\Filament\Resources\Testimonials\TestimonialResource;
use Filament\Tables\Columns\TextColumn;
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

                TextColumn::make('is_active')
                    ->label(__('admin.testimonials.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.testimonials.active_yes')
                        : __('admin.testimonials.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordUrl(fn (Testimonial $record): string => TestimonialResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin.testimonials.col_active')),
            ])
            ->toolbarActions([]);
    }
}
