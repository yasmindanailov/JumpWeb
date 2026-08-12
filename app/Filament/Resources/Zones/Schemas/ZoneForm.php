<?php

namespace App\Filament\Resources\Zones\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Fase 7.9 (adelanto) — Formulario de alta/edición de una zona (compartido por crear y
 * editar). Identidad + textos i18n (es/en/fr) + aforo de packs POR ZONA (override del
 * ajuste global). La limpieza i18n, la normalización del cupo y los defaults viven en
 * `InteractsWithZoneForm`.
 */
class ZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.zones.section_identity'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('slug')
                            ->label(__('admin.zones.field_slug'))
                            ->helperText(__('admin.zones.field_slug_hint'))
                            ->required()
                            ->maxLength(40)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            // Inmutable al editar: el slug es la clave por la que los seeders
                            // hacen updateOrCreate; renombrarlo crearía una zona duplicada en el
                            // siguiente seed. Se fija al crear y ya no se toca.
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                        TextInput::make('accent')
                            ->label(__('admin.zones.field_accent'))
                            ->helperText(__('admin.zones.field_accent_hint'))
                            ->maxLength(40)
                            // El acento viaja al DOM dentro de directivas Alpine (`goToRides('…')`,
                            // `:class`). Aunque Blade escapa, el navegador decodifica la entidad
                            // antes de que Alpine evalúe el JS → un carácter raro (comilla) rompería
                            // la expresión. Se restringe a [a-z0-9-_] (como el slug) → defensa en
                            // origen contra manipulación directa de BD (revisión adversarial #230).
                            ->alphaDash()
                            ->default('jump'),
                        ColorPicker::make('color')
                            ->label(__('admin.zones.field_color'))
                            ->helperText(__('admin.zones.field_color_hint'))
                            // Hex #RRGGBB o vacío: el color de zona viaja al DOM de la landing
                            // (acento de su sección) y al panel; se valida en origen, coherente
                            // con el color de marca (#7.10). `ThemeSettings` sanea además al render.
                            ->regex('/^$|^#[0-9a-fA-F]{6}$/'),
                        TextInput::make('position')
                            ->label(__('admin.zones.field_position'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('area_sqm')
                            ->label(__('admin.zones.field_area_sqm'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('rides_count')
                            ->label(__('admin.zones.field_rides_count'))
                            ->numeric()
                            ->minValue(0),
                        // Foto de zona (feature 2026-06-11): ruta relativa a `public/`, igual que la
                        // de una atracción. La landing pinta la card con la foto integrada; vacío →
                        // card sin foto (fallback). La subida de ficheros llegará con la galería.
                        TextInput::make('image')
                            ->label(__('admin.zones.field_image'))
                            ->helperText(__('admin.zones.field_image_hint'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label(__('admin.zones.field_is_active'))
                            ->helperText(__('admin.zones.field_is_active_hint'))
                            ->default(true),
                        Toggle::make('show_in_landing')
                            ->label(__('admin.zones.field_show_in_landing'))
                            ->helperText(__('admin.zones.field_show_in_landing_hint'))
                            ->default(true),
                    ]),

                Tabs::make('translations')->tabs([
                    self::translatableTab('es', __('admin.zones.lang.es')),
                    self::translatableTab('en', __('admin.zones.lang.en')),
                    self::translatableTab('fr', __('admin.zones.lang.fr')),
                ]),

                Section::make(__('admin.zones.section_cupo'))
                    ->description(__('admin.zones.section_cupo_hint'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_per_slot')
                            ->label(__('admin.zones.field_max_per_slot'))
                            ->helperText(__('admin.zones.field_cupo_hint'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('max_guests_per_slot')
                            ->label(__('admin.zones.field_max_guests_per_slot'))
                            ->helperText(__('admin.zones.field_cupo_hint'))
                            ->numeric()
                            ->minValue(0),
                        Select::make('prep_blocks_cupo')
                            ->label(__('admin.zones.field_prep_blocks_cupo'))
                            ->helperText(__('admin.zones.field_prep_blocks_cupo_hint'))
                            ->options([
                                '' => __('admin.zones.cupo_use_global'),
                                '1' => __('admin.zones.yes'),
                                '0' => __('admin.zones.no'),
                            ])
                            ->default('')
                            ->selectablePlaceholder(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("name.{$locale}")
                ->label(__('admin.zones.field_name'))
                ->required($locale === 'es')
                ->maxLength(120),
            TextInput::make("subtitle.{$locale}")
                ->label(__('admin.zones.field_subtitle'))
                ->maxLength(255),
            Textarea::make("description.{$locale}")
                ->label(__('admin.zones.field_description'))
                ->rows(3)
                ->maxLength(2000),
            TextInput::make("age_label.{$locale}")
                ->label(__('admin.zones.field_age_label'))
                ->maxLength(60),
            TextInput::make("age_range.{$locale}")
                ->label(__('admin.zones.field_age_range'))
                ->maxLength(60),
        ]);
    }
}
