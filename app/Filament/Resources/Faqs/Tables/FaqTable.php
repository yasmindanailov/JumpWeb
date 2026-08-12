<?php

namespace App\Filament\Resources\Faqs\Tables;

use App\Domain\Content\Models\Faq;
use App\Filament\Resources\Faqs\FaqResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Fase 7.9 (iter. 1) — Tabla de FAQ: pregunta (idioma activo), estado. Fila clicable →
 * edición. Ordenada por posición. Reordenable arrastrando.
 */
class FaqTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label(__('admin.faqs.col_question'))
                    ->getStateUsing(fn (Faq $record): string => (string) ($record->tr('question') ?? '—'))
                    ->wrap(),

                TextColumn::make('is_active')
                    ->label(__('admin.faqs.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.faqs.active_yes')
                        : __('admin.faqs.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordUrl(fn (Faq $record): string => FaqResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.faqs.col_active')),
            ])
            ->toolbarActions([]);
    }
}
