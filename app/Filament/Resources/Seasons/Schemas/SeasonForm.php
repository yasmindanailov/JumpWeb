<?php

namespace App\Filament\Resources\Seasons\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Fase 7.7 — Formulario de alta/edición de una temporada (compartido por crear y editar).
 * La validación cruzada (fin≥inicio, cierre>apertura) vive en el trait
 * `InteractsWithSeasonForm`, no aquí.
 */
class SeasonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.seasons.section_period'))
                    ->description(__('admin.seasons.section_period_hint'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.seasons.field_name'))
                            ->helperText(__('admin.seasons.field_name_hint'))
                            ->required()
                            ->maxLength(80)
                            ->columnSpanFull(),
                        DatePicker::make('start_date')
                            ->label(__('admin.seasons.field_start_date'))
                            ->required()
                            ->native(false),
                        DatePicker::make('end_date')
                            ->label(__('admin.seasons.field_end_date'))
                            ->required()
                            ->native(false)
                            ->after('start_date'),
                    ]),

                Section::make(__('admin.seasons.section_hours'))
                    ->description(__('admin.seasons.section_hours_hint'))
                    ->columns(2)
                    ->schema([
                        TimePicker::make('open_time')
                            ->label(__('admin.seasons.field_open_time'))
                            ->required()
                            ->seconds(false)
                            ->native(false),
                        TimePicker::make('close_time')
                            ->label(__('admin.seasons.field_close_time'))
                            ->required()
                            ->seconds(false)
                            ->native(false)
                            ->after('open_time'),
                        Toggle::make('is_active')
                            ->label(__('admin.seasons.field_is_active'))
                            ->helperText(__('admin.seasons.field_is_active_hint'))
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
