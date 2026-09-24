<?php

namespace App\Filament\Resources\Experiments\Schemas;

use App\Domain\Platform\Models\Experiment;
use App\Domain\Platform\Services\Analytics\Experiments;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * **El formulario de un experimento** (`docs/specs/analitica.md` §4.4, T5b): la clave, el nombre, si está activo y
 * su ventana; y las variantes con su peso, en orden.
 *
 * ⚠️ **Con el experimento ACTIVO la clave y las variantes van BLOQUEADAS**: la asignación es `hash(clave | sujeto)`
 * repartido por pesos acumulados en el orden guardado, así que tocar cualquiera de las tres cosas REBARAJA a todos
 * los visitantes y mezcla lo medido antes con lo de después. Para cambiarlas se apaga (y lo honesto es crear otro).
 * Un campo deshabilitado no viaja al guardar: el valor de la BD se queda tal cual.
 */
class ExperimentForm
{
    public static function configure(Schema $schema): Schema
    {
        $locked = static fn (?Experiment $record): bool => $record !== null && $record->active;

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.experiments.section_experiment'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('key')
                            ->label(__('admin.experiments.field_key'))
                            ->helperText(__('admin.experiments.field_key_hint'))
                            ->required()
                            ->maxLength(48)
                            ->regex(Experiments::KEY_RE)
                            ->unique(ignoreRecord: true)
                            ->disabled($locked),
                        TextInput::make('name')
                            ->label(__('admin.experiments.field_name'))
                            ->required()
                            ->maxLength(120),
                        Toggle::make('active')
                            ->label(__('admin.experiments.field_active'))
                            ->helperText(__('admin.experiments.field_active_hint'))
                            ->default(false)
                            ->columnSpanFull(),
                        DateTimePicker::make('started_at')
                            ->label(__('admin.experiments.field_started_at'))
                            ->helperText(__('admin.experiments.field_window_hint'))
                            ->seconds(false)
                            ->native(false),
                        DateTimePicker::make('ended_at')
                            ->label(__('admin.experiments.field_ended_at'))
                            ->helperText(__('admin.experiments.field_window_hint'))
                            ->seconds(false)
                            ->native(false)
                            ->after('started_at'),
                    ]),

                Section::make(__('admin.experiments.section_variants'))
                    ->description(__('admin.experiments.variants_hint'))
                    ->schema([
                        Repeater::make('variants')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('key')
                                    ->label(__('admin.experiments.field_variant_key'))
                                    ->required()
                                    ->maxLength(32)
                                    ->regex(Experiments::VARIANT_RE)
                                    ->distinct(),
                                TextInput::make('weight')
                                    ->label(__('admin.experiments.field_variant_weight'))
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(1000)
                                    ->default(1),
                            ])
                            ->columns(2)
                            ->defaultItems(2)
                            ->minItems(2)
                            ->maxItems(8)
                            ->reorderable()
                            ->addActionLabel(__('admin.experiments.actions.add_variant'))
                            ->disabled($locked),
                    ]),
            ]);
    }
}
