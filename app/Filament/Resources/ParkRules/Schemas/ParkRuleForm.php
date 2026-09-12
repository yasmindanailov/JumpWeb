<?php

namespace App\Filament\Resources\ParkRules\Schemas;

use App\Domain\Content\Models\VenueRule;
use Filament\Forms\Components\Select;
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
                        /*
                         * EL MOMENTO (`DECISIONES #533`): es lo que agrupa la página, porque es el
                         * único orden que el visitante puede usar.
                         * ⚠️⚠️ **Se puede dejar VACÍO a propósito, y no es un descuido del
                         * formulario**: sin momento la norma se publica igual, al final y sin
                         * grupo. Si fuera obligatorio, una norma creada con prisa desaparecería de
                         * la web sin fallar y sin avisar.
                         */
                        Select::make('moment')
                            ->label(__('admin.park_rules.field_moment'))
                            ->helperText(__('admin.park_rules.field_moment_hint'))
                            ->options(fn (): array => collect(VenueRule::MOMENTS)
                                ->mapWithKeys(fn (string $m): array => [$m => __('admin.park_rules.moments.'.$m)])
                                ->all())
                            ->placeholder(__('admin.park_rules.field_moment_none'))
                            ->native(false),
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
            /*
             * EL PORQUÉ (`DECISIONES #533`): *«una norma con motivo se cumple y una norma sola se
             * discute en la puerta»*.
             * ⚠️ **Opcional a propósito**: no toda norma tiene motivo que contar —la de la zona Kids
             * no lleva— y la página lo pinta **solo si está**. Un campo obligatorio obligaría a
             * inventarse una frase.
             */
            Textarea::make("reason.{$locale}")
                ->label(__('admin.park_rules.field_reason'))
                ->helperText(__('admin.park_rules.field_reason_hint'))
                ->rows(2)
                ->maxLength(500),
        ]);
    }
}
