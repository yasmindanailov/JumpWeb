<?php

namespace App\Filament\Resources\ParkRules\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Fase 7.9 (iter. 1) — Formulario de alta/edición de una norma (compartido por crear/editar).
 * Clasificación (orden, activa) + nombre/descripción i18n (es/en/fr). La limpieza i18n y los
 * defaults viven en `InteractsWithParkRuleForm`.
 */
class ParkRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.park_rules.section_classification'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('position')
                            ->label(__('admin.park_rules.field_position'))
                            ->helperText(__('admin.park_rules.field_position_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_active')
                            ->label(__('admin.park_rules.field_is_active'))
                            ->helperText(__('admin.park_rules.field_is_active_hint'))
                            ->default(true),
                    ]),

                Tabs::make('translations')->tabs([
                    self::translatableTab('es', __('admin.park_rules.lang.es')),
                    self::translatableTab('en', __('admin.park_rules.lang.en')),
                    self::translatableTab('fr', __('admin.park_rules.lang.fr')),
                ]),
            ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("name.{$locale}")
                ->label(__('admin.park_rules.field_name'))
                ->required($locale === 'es')
                ->maxLength(160),
            Textarea::make("description.{$locale}")
                ->label(__('admin.park_rules.field_description'))
                ->rows(3)
                ->maxLength(2000),
        ]);
    }
}
