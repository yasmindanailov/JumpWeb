<?php

namespace App\Filament\Resources\Faqs\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Fase 7.9 (iter. 1) — Formulario de alta/edición de una FAQ (compartido por crear/editar).
 * Clasificación (orden, activa) + pregunta/respuesta i18n (es/en/fr). La limpieza i18n y los
 * defaults viven en `InteractsWithFaqForm`.
 */
class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.faqs.section_classification'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('position')
                            ->label(__('admin.faqs.field_position'))
                            ->helperText(__('admin.faqs.field_position_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_active')
                            ->label(__('admin.faqs.field_is_active'))
                            ->helperText(__('admin.faqs.field_is_active_hint'))
                            ->default(true),
                    ]),

                Tabs::make('translations')->tabs([
                    self::translatableTab('es', __('admin.faqs.lang.es')),
                    self::translatableTab('en', __('admin.faqs.lang.en')),
                    self::translatableTab('fr', __('admin.faqs.lang.fr')),
                ]),
            ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("question.{$locale}")
                ->label(__('admin.faqs.field_question'))
                ->required($locale === 'es')
                ->maxLength(255),
            Textarea::make("answer.{$locale}")
                ->label(__('admin.faqs.field_answer'))
                ->required($locale === 'es')
                ->rows(4)
                ->maxLength(2000),
        ]);
    }
}
