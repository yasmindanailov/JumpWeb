<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Domain\Booking\Models\Promotion;
use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * La tabla de promociones: qué dice, su clase, a qué va, cuándo y en qué ESTADO está hoy —vigente, programada, acabada o
 * apagada—, con el mismo orden que la web apila (la que acaba antes primero). Fila clicable → edición.
 *
 * ⚠️ La columna de IDIOMAS no es decorativa: una oferta sin traducir NO sale en ese idioma (spec §0), así que desde el
 * listado tiene que verse cuál se queda sin inglés o sin francés.
 */
class PromotionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('text')
                    ->label(__('admin.promotions.col_text'))
                    ->getStateUsing(fn (Promotion $record): string => (string) ($record->tr('text') ?? '—'))
                    ->wrap(),

                TextColumn::make('kind')
                    ->label(__('admin.promotions.col_kind'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('admin.promotions.kinds.'.$state))
                    ->color(fn (string $state): string => $state === Promotion::KIND_OFFER ? 'warning' : 'info'),

                TextColumn::make('target')
                    ->label(__('admin.promotions.col_target'))
                    ->getStateUsing(fn (Promotion $record): string => match ($record->target()) {
                        Promotion::TARGET_ZONE => __('admin.promotions.target_zone', ['name' => (string) $record->zone?->tr('name')]),
                        Promotion::TARGET_PRODUCT => __('admin.promotions.target_product', ['name' => (string) $record->product?->tr('name')]),
                        default => __('admin.promotions.targets.installation'),
                    }),

                TextColumn::make('dates')
                    ->label(__('admin.promotions.col_dates'))
                    ->getStateUsing(fn (Promotion $record): string => match (true) {
                        $record->starts_on !== null && $record->ends_on !== null => __('admin.promotions.dates_between', ['from' => $record->starts_on->format('d/m/Y'), 'to' => $record->ends_on->format('d/m/Y')]),
                        $record->ends_on !== null => __('admin.promotions.dates_until', ['to' => $record->ends_on->format('d/m/Y')]),
                        $record->starts_on !== null => __('admin.promotions.dates_from', ['from' => $record->starts_on->format('d/m/Y')]),
                        default => __('admin.promotions.dates_always'),
                    }),

                TextColumn::make('state')
                    ->label(__('admin.promotions.col_state'))
                    ->badge()
                    ->getStateUsing(fn (Promotion $record): string => $record->state())
                    ->formatStateUsing(fn (string $state): string => __('admin.promotions.states.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'current' => 'success',
                        'scheduled' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('languages')
                    ->label(__('admin.promotions.col_languages'))
                    ->getStateUsing(fn (Promotion $record): string => ($faltan = array_values(array_filter(['en', 'fr'], fn (string $l): bool => $record->textIn($l) === null))) === []
                        ? __('admin.promotions.languages_all')
                        : __('admin.promotions.languages_missing', ['langs' => strtoupper(implode(', ', $faltan))]))
                    ->color(fn (Promotion $record): string => $record->textIn('en') !== null && $record->textIn('fr') !== null ? 'gray' : 'warning'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['zone', 'product'])->scopes('stacked'))
            ->recordUrl(fn (Promotion $record): string => PromotionResource::getUrl('edit', ['record' => $record]))
            ->filters([
                SelectFilter::make('kind')
                    ->label(__('admin.promotions.col_kind'))
                    ->options(fn (): array => collect(Promotion::KINDS)
                        ->mapWithKeys(fn (string $k): array => [$k => __('admin.promotions.kinds.'.$k)])
                        ->all()),
            ])
            ->emptyStateHeading(__('admin.promotions.empty_heading'))
            ->emptyStateDescription(__('admin.promotions.empty_description'))
            ->toolbarActions([]);
    }
}
