<?php

namespace App\Filament\Resources\Slots\Schemas;

use App\Domain\Booking\Models\Slot;
use App\Filament\Resources\Slots\SlotResource;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Fase 7.7 iter.3 — Edición de una franja: contexto de solo lectura (zona/fecha/hora/ocupación
 * viva) + venta online (toggle) + excepción de aforo online (solo en zonas de entradas; en la
 * de cumpleaños el aforo va por cupo y no se edita aquí). La validación (aforo ≥ ocupación, ≤
 * aforo total) y la marca de "ajustado a mano" viven en `InteractsWithSlotForm`.
 */
class SlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.slots.section_context'))
                    ->columns(2)
                    ->schema([
                        Placeholder::make('zone_label')
                            ->label(__('admin.slots.col_zone'))
                            ->content(fn (Slot $record): string => (string) ($record->zone?->tr('name') ?? '—')),
                        Placeholder::make('date_label')
                            ->label(__('admin.slots.col_date'))
                            ->content(fn (Slot $record): string => $record->date->format('d/m/Y')),
                        Placeholder::make('time_label')
                            ->label(__('admin.slots.col_time'))
                            ->content(fn (Slot $record): string => substr((string) $record->start_time, 0, 5).' – '.substr((string) $record->end_time, 0, 5)),
                        Placeholder::make('occupancy_label')
                            ->label(__('admin.slots.occupancy'))
                            ->content(fn (Slot $record): string => SlotResource::isPackZone($record)
                                ? __('admin.slots.occupancy_guests', ['n' => SlotResource::liveOccupancy($record)])
                                : __('admin.slots.occupancy_seats', ['n' => SlotResource::liveOccupancy($record), 'cap' => $record->online_capacity])),
                    ]),

                Section::make(__('admin.slots.section_sale'))
                    ->description(__('admin.slots.section_sale_hint'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('online_sales_open')
                            ->label(__('admin.slots.field_sale'))
                            ->helperText(__('admin.slots.field_sale_hint'))
                            ->default(true)
                            ->columnSpanFull(),

                        TextInput::make('online_capacity')
                            ->label(__('admin.slots.field_online_capacity'))
                            ->helperText(__('admin.slots.field_online_capacity_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->visible(fn (Slot $record): bool => ! SlotResource::isPackZone($record)),

                        Placeholder::make('pack_aforo_note')
                            ->label(__('admin.slots.field_online_capacity'))
                            ->content(__('admin.slots.pack_aforo_note'))
                            ->visible(fn (Slot $record): bool => SlotResource::isPackZone($record)),
                    ]),
            ]);
    }
}
