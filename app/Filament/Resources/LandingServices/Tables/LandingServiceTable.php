<?php

namespace App\Filament\Resources\LandingServices\Tables;

use App\Filament\Resources\LandingServices\LandingServiceResource;
use App\Models\LandingService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Tabla de servicios de la landing: título (idioma activo), pack vinculado, estado y nav.
 * Fila clicable → edición. Ordenada por posición (reordenable arrastrando).
 */
class LandingServiceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.landing_services.col_title'))
                    ->getStateUsing(fn (LandingService $record): string => (string) ($record->tr('title') ?? '—'))
                    ->wrap(),

                TextColumn::make('slug')
                    ->label(__('admin.landing_services.col_slug'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('pack')
                    ->label(__('admin.landing_services.col_pack'))
                    ->getStateUsing(fn (LandingService $record): string => $record->ticketType
                        ? (string) ($record->ticketType->tr('name') ?? '—')
                        : __('admin.landing_services.contact_only')),

                TextColumn::make('is_active')
                    ->label(__('admin.landing_services.col_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.landing_services.active_yes')
                        : __('admin.landing_services.active_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('show_in_nav')
                    ->label(__('admin.landing_services.col_nav'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.landing_services.nav_yes')
                        : __('admin.landing_services.nav_no'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordUrl(fn (LandingService $record): string => LandingServiceResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.landing_services.col_active')),
                TernaryFilter::make('show_in_nav')
                    ->label(__('admin.landing_services.col_nav')),
            ])
            ->toolbarActions([]);
    }
}
