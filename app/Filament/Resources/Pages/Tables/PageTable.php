<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Domain\Content\Models\Page;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Route;

/**
 * Fase 7.9 (iter. 2) — Tabla de páginas legales: identificador, título (idioma activo) y estado.
 * Fila clicable → edición. Acción «Ver en la web» (si la ruta existe). Sin crear/borrar.
 */
class PageTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->label(__('admin.pages.col_slug'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('title')
                    ->label(__('admin.pages.col_title'))
                    ->getStateUsing(fn (Page $record): string => (string) ($record->tr('title') ?? '—')),

                TextColumn::make('is_active')
                    ->label(__('admin.pages.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.pages.active_yes')
                        : __('admin.pages.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->defaultSort('slug')
            ->recordUrl(fn (Page $record): string => PageResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.pages.col_active')),
            ])
            ->recordActions([
                Action::make('viewOnWeb')
                    ->label(__('admin.pages.actions.view'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Page $record): ?string => Route::has('legal.'.$record->slug) ? route('legal.'.$record->slug) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Page $record): bool => Route::has('legal.'.$record->slug)),
            ])
            ->toolbarActions([]);
    }
}
