<?php

namespace App\Filament\Resources\WristbandColors\Tables;

use App\Domain\Booking\Models\WristbandColor;
use App\Domain\Booking\Services\WristbandWheel;
use App\Domain\Platform\Models\Setting;
use App\Filament\Resources\WristbandColors\WristbandColorResource;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * **La lista de colores** (la P2, D10): en el ORDEN de la rueda —se arrastra—, con su muestra, sus dos frases, si va en
 * la rueda y en cuántos productos va fijo. Arriba, la rueda tal como la verá la Puerta: así se comprueba sin abrirla.
 */
class WristbandColorTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn ($query) => $query->withCount('ticketTypes'))
            ->description(static fn (): string => self::wheelSummary())
            ->columns([
                ColorColumn::make('hex')
                    ->label(__('admin.wristbands.col_color')),
                TextColumn::make('name_one')
                    ->label(__('admin.wristbands.field_name_one')),
                TextColumn::make('name_other')
                    ->label(__('admin.wristbands.field_name_other')),
                TextColumn::make('in_wheel')
                    ->label(__('admin.wristbands.col_in_wheel'))
                    ->badge()
                    ->formatStateUsing(static fn (bool $state): string => __('admin.wristbands.'.($state ? 'in_wheel_yes' : 'in_wheel_no')))
                    ->color(static fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('ticket_types_count')
                    ->label(__('admin.wristbands.col_fixed_on'))
                    ->numeric(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordUrl(static fn (WristbandColor $record): string => WristbandColorResource::getUrl('edit', ['record' => $record]))
            ->toolbarActions([]);
    }

    /**
     * «La rueda: 11:00 pulsera naranja · 11:30 pulsera lila · …» —una vuelta, desde la hora del primero—, o por qué no hay
     * rueda. Lee lo MISMO que la Puerta ({@see WristbandWheel}).
     */
    public static function wheelSummary(): string
    {
        $start = WristbandWheel::minutesOf((string) Setting::value(WristbandWheel::KEY_START, ''));
        $colors = WristbandColor::query()->where('in_wheel', true)->orderBy('position')->orderBy('id')->get();
        if ($start === null || $colors->isEmpty()) {
            return __('admin.wristbands.wheel_off');
        }

        $step = WristbandWheel::step();
        $turns = $colors->values()->map(static function (WristbandColor $color, int $i) use ($start, $step): string {
            $minutes = ($start + $i * $step) % (24 * 60);

            return sprintf('%02d:%02d %s', intdiv($minutes, 60), $minutes % 60, $color->name_one);
        });

        return __('admin.wristbands.wheel_on', ['step' => $step, 'turns' => $turns->implode(' · ')]);
    }
}
