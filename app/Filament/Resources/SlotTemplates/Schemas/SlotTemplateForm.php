<?php

namespace App\Filament\Resources\SlotTemplates\Schemas;

use App\Domain\Booking\Models\Zone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Fase 7.7 iter.3 — Formulario de alta/edición de una plantilla de franja (compartido por
 * crear y editar). Las invariantes (online ≤ total, unicidad zona/día/hora) viven en el trait
 * `InteractsWithSlotTemplateForm`. El día de la semana se muestra L→D pero se guarda con el
 * convenio de Carbon (0=domingo..6=sábado), igual que el generador.
 */
class SlotTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.slot_templates.section_when'))
                    ->description(__('admin.slot_templates.section_when_hint'))
                    ->columns(2)
                    ->schema([
                        Select::make('zone_id')
                            ->label(__('admin.slot_templates.field_zone'))
                            ->options(fn (): array => Zone::orderBy('position')->get()
                                ->mapWithKeys(fn (Zone $zone): array => [$zone->id => (string) $zone->tr('name')])
                                ->all())
                            ->required()
                            ->native(false),
                        Select::make('weekday')
                            ->label(__('admin.slot_templates.field_weekday'))
                            ->options([
                                1 => __('admin.rate_types.weekdays.1'),
                                2 => __('admin.rate_types.weekdays.2'),
                                3 => __('admin.rate_types.weekdays.3'),
                                4 => __('admin.rate_types.weekdays.4'),
                                5 => __('admin.rate_types.weekdays.5'),
                                6 => __('admin.rate_types.weekdays.6'),
                                0 => __('admin.rate_types.weekdays.0'),
                            ])
                            ->required()
                            ->native(false),
                        TimePicker::make('start_time')
                            ->label(__('admin.slot_templates.field_start_time'))
                            ->required()
                            ->seconds(false)
                            ->native(false),
                        TextInput::make('duration_min')
                            ->label(__('admin.slot_templates.field_duration'))
                            ->helperText(__('admin.slot_templates.field_duration_hint'))
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ]),

                Section::make(__('admin.slot_templates.section_capacity'))
                    ->description(__('admin.slot_templates.section_capacity_hint'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('capacity')
                            ->label(__('admin.slot_templates.field_capacity'))
                            ->helperText(__('admin.slot_templates.field_capacity_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('online_capacity')
                            ->label(__('admin.slot_templates.field_online_capacity'))
                            ->helperText(__('admin.slot_templates.field_online_capacity_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label(__('admin.slot_templates.field_is_active'))
                            ->helperText(__('admin.slot_templates.field_is_active_hint'))
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
