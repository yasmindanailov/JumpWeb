<?php

namespace App\Filament\Resources\RateTypes\Schemas;

use App\Domain\Booking\Models\RateType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Fase 7.8 — Formulario de alta y edición de una tarifa (compartido por `CreateRateType`
 * y `EditRateType`).
 *
 * Bloques:
 *  - **Identificación**: la `key` (identificador técnico, **editable solo al crear**;
 *    inmutable al editar porque RateResolver y la web la usan como referencia) y la
 *    etiqueta legible i18n (es/en/fr).
 *  - **Cuándo aplica**: los días de la semana que activan la tarifa y su prioridad
 *    (si varias coinciden un día, gana la de mayor prioridad).
 *  - **Estado**: marca de "especial" (informativa) y activa/inactiva.
 *
 * La normalización (etiqueta i18n, `weekdays` a enteros, prioridad) vive en el trait
 * `InteractsWithRateTypeForm` compartido por las dos páginas, no aquí.
 */
class RateTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::identitySection(),
                self::applicabilitySection(),
                self::statusSection(),
            ]);
    }

    private static function identitySection(): Section
    {
        return Section::make(__('admin.rate_types.section_identity'))
            ->description(__('admin.rate_types.section_identity_hint'))
            ->schema([
                TextInput::make('key')
                    ->label(__('admin.rate_types.field_key'))
                    ->helperText(fn (?RateType $record): string => $record === null
                        ? __('admin.rate_types.key_create_hint')
                        : __('admin.rate_types.key_locked_hint'))
                    ->required()
                    ->maxLength(50)
                    // Identificador técnico: minúsculas, números y guion bajo.
                    ->regex('/^[a-z][a-z0-9_]*$/')
                    ->unique(ignoreRecord: true)
                    // Inmutable al editar: cambiar la `key` de `normal` rompería el RateResolver
                    // y la web; deshidratada para que NO entre en el payload de guardado.
                    ->disabled(fn (?RateType $record): bool => $record !== null)
                    ->dehydrated(fn (?RateType $record): bool => $record === null),

                self::labelTabs(),
            ]);
    }

    private static function labelTabs(): Tabs
    {
        return Tabs::make('label_translations')->tabs([
            self::labelTab('es', __('admin.rate_types.lang.es'), true),
            self::labelTab('en', __('admin.rate_types.lang.en'), false),
            self::labelTab('fr', __('admin.rate_types.lang.fr'), false),
        ]);
    }

    private static function labelTab(string $locale, string $label, bool $required): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("label.{$locale}")
                ->label(__('admin.rate_types.field_label'))
                ->required($required)
                ->maxLength(80),
        ]);
    }

    private static function applicabilitySection(): Section
    {
        return Section::make(__('admin.rate_types.section_applicability'))
            ->description(__('admin.rate_types.section_applicability_hint'))
            ->schema([
                CheckboxList::make('weekdays')
                    ->label(__('admin.rate_types.field_weekdays'))
                    ->helperText(__('admin.rate_types.weekdays_hint'))
                    // Valores = Carbon dayOfWeek (0=domingo..6=sábado); orden de lectura
                    // europeo (lunes primero). El trait los normaliza a enteros al guardar.
                    ->options([
                        1 => __('admin.rate_types.weekdays.1'),
                        2 => __('admin.rate_types.weekdays.2'),
                        3 => __('admin.rate_types.weekdays.3'),
                        4 => __('admin.rate_types.weekdays.4'),
                        5 => __('admin.rate_types.weekdays.5'),
                        6 => __('admin.rate_types.weekdays.6'),
                        0 => __('admin.rate_types.weekdays.0'),
                    ])
                    ->columns(4)
                    ->default([]),

                TextInput::make('priority')
                    ->label(__('admin.rate_types.field_priority'))
                    ->helperText(__('admin.rate_types.priority_hint'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1000)
                    ->default(0)
                    ->required(),
            ]);
    }

    private static function statusSection(): Section
    {
        return Section::make(__('admin.rate_types.section_status'))
            ->schema([
                Toggle::make('is_special')
                    ->label(__('admin.rate_types.field_is_special'))
                    ->helperText(__('admin.rate_types.is_special_hint'))
                    ->default(false),

                Toggle::make('is_active')
                    ->label(__('admin.rate_types.field_is_active'))
                    ->helperText(__('admin.rate_types.is_active_hint'))
                    ->default(true),
            ]);
    }
}
