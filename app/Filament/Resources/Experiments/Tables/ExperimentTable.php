<?php

namespace App\Filament\Resources\Experiments\Tables;

use App\Domain\Platform\Models\Experiment;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Experiments\ExperimentResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * La lista de experimentos (T5b): clave, nombre, variantes con su peso, el ESTADO real (vivo · apagado ·
 * programado · acabado, que es lo que decide si asigna) y la ventana. Fila clicable → edición.
 */
class ExperimentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.experiments.col_name'))
                    ->searchable()
                    ->wrap(),

                TextColumn::make('key')
                    ->label(__('admin.experiments.col_key'))
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('variants')
                    ->label(__('admin.experiments.col_variants'))
                    ->getStateUsing(static fn (Experiment $record): string => self::variantsSummary($record))
                    ->wrap(),

                TextColumn::make('active')
                    ->label(__('admin.experiments.col_state'))
                    ->badge()
                    ->getStateUsing(static fn (Experiment $record): string => self::state($record))
                    ->formatStateUsing(static fn (string $state): string => __('admin.experiments.state.'.$state))
                    ->color(static fn (string $state): string => match ($state) {
                        'running' => 'success',
                        'scheduled' => 'info',
                        'finished' => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('started_at')
                    ->label(__('admin.experiments.col_window'))
                    ->getStateUsing(static fn (Experiment $record): string => self::window($record)),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Experiment $record): string => ExperimentResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('active')->label(__('admin.experiments.field_active')),
            ])
            ->toolbarActions([]);
    }

    /** `cajon 1 · isla 1`, en el orden del reparto; lo que no vale como variante no se enseña. */
    public static function variantsSummary(Experiment $record): string
    {
        $parts = [];

        foreach ($record->weightedVariants() as $key => $weight) {
            $parts[] = $key.' '.$weight;
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    /** El estado que decide si asigna: `running` · `inactive` · `scheduled` · `finished`. */
    public static function state(Experiment $record): string
    {
        if ($record->isRunning()) {
            return 'running';
        }

        if (! $record->active) {
            return 'inactive';
        }

        if ($record->started_at !== null && $record->started_at->gt(now())) {
            return 'scheduled';
        }

        return 'finished';
    }

    private static function window(Experiment $record): string
    {
        $from = $record->started_at === null ? '—' : DisplayTime::format($record->started_at, 'd/m/Y H:i');
        $to = $record->ended_at === null ? '—' : DisplayTime::format($record->ended_at, 'd/m/Y H:i');

        return $from.' → '.$to;
    }
}
