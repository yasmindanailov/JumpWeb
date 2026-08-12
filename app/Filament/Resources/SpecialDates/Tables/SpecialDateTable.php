<?php

namespace App\Filament\Resources\SpecialDates\Tables;

use App\Domain\Booking\Models\SpecialDate;
use App\Filament\Resources\SpecialDates\SpecialDateResource;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.7 — Tabla de fechas especiales. Una fila por excepción del calendario: la fecha,
 * su nota interna, si está cerrada o abierta (con su horario especial si lo tiene) y la
 * tarifa que aplica ese día. Fila clicable → edición. Filtros por estado y "próximas".
 */
class SpecialDateTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('admin.special_dates.col_date'))
                    ->date('D, d M Y')
                    ->sortable(),

                TextColumn::make('note')
                    ->label(__('admin.special_dates.col_note'))
                    ->getStateUsing(fn (SpecialDate $record): string => (string) ($record->tr('note') ?? '—'))
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('is_closed')
                    ->label(__('admin.special_dates.col_state'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('admin.special_dates.closed')
                        : __('admin.special_dates.open'))
                    ->color(fn (bool $state): string => $state ? 'danger' : 'success'),

                TextColumn::make('window')
                    ->label(__('admin.special_dates.col_window'))
                    ->getStateUsing(fn (SpecialDate $record): string => self::windowLabel($record)),

                TextColumn::make('rateType.label')
                    ->label(__('admin.special_dates.col_rate'))
                    ->getStateUsing(fn (SpecialDate $record): string => $record->is_closed
                        ? '—'
                        : (string) ($record->rateType?->tr('label') ?? __('admin.special_dates.rate_by_weekday'))),
            ])
            ->defaultSort('date', 'desc')
            ->recordUrl(fn (SpecialDate $record): string => SpecialDateResource::getUrl('edit', ['record' => $record]))
            ->filters([
                TernaryFilter::make('is_closed')
                    ->label(__('admin.special_dates.col_state'))
                    ->placeholder(__('admin.special_dates.filter_all'))
                    ->trueLabel(__('admin.special_dates.closed'))
                    ->falseLabel(__('admin.special_dates.open')),

                Filter::make('upcoming')
                    ->label(__('admin.special_dates.filter_upcoming'))
                    ->query(fn (Builder $query): Builder => $query->whereDate('date', '>=', Carbon::today()->toDateString())),
            ])
            ->toolbarActions([]);
    }

    /** Horario del día: "12:00–16:00" si tiene ventana propia; "Semanal" si hereda; "—" si cerrado. */
    private static function windowLabel(SpecialDate $record): string
    {
        if ($record->is_closed) {
            return '—';
        }

        if ($record->open_time === null && $record->close_time === null) {
            return __('admin.special_dates.window_weekly');
        }

        // Ventana parcial: el extremo no definido se hereda del horario semanal (OperatingSchedule
        // hace `open ?? weekly`), así que se rotula "semanal" en lugar de un placeholder opaco.
        $weekly = __('admin.special_dates.window_weekly_short');
        $open = $record->open_time !== null ? substr((string) $record->open_time, 0, 5) : $weekly;
        $close = $record->close_time !== null ? substr((string) $record->close_time, 0, 5) : $weekly;

        return "{$open}–{$close}";
    }
}
