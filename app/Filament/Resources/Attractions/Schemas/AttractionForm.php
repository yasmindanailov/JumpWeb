<?php

namespace App\Filament\Resources\Attractions\Schemas;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Fase 7.9 (iter. 1) — Formulario de alta/edición de una atracción (compartido por crear/
 * editar). Identidad (zona, imagen, orden, activa, destacada) + textos i18n
 * (es/en/fr). La limpieza i18n, la normalización de la ruta de imagen y los defaults viven
 * en `InteractsWithAttractionForm`.
 *
 * La imagen es por ahora una RUTA relativa a `public/` (p. ej. `images/attractions/x.jpg`),
 * coherente con el dato sembrado; la subida de ficheros desde el panel llegará con la galería.
 *
 * ⚠️ Atracciones de pago (`#228`): quedaba `is_special` (resalte visual). El `ticket_type_id`
 * —el complemento vendible vinculado, que daba precio y CTA en la landing— se retiró en `#668`
 * con la pieza entera (`#632`·P3: **0 de 23** lo usaban).
 */
class AttractionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.attractions.section_identity'))
                    ->columns(2)
                    ->schema([
                        Select::make('zone_id')
                            ->label(__('admin.attractions.field_zone'))
                            ->helperText(__('admin.attractions.field_zone_hint'))
                            // Se marca la zona que NO se muestra en la landing: una atracción en
                            // una zona oculta (p. ej. cumpleaños) no aparece en la web, así que
                            // el operador lo ve antes de elegirla (coherencia añadir/quitar).
                            ->options(fn (): array => Zone::orderBy('position')->get()
                                ->mapWithKeys(fn (Zone $zone): array => [
                                    $zone->id => (string) $zone->tr('name')
                                        .($zone->show_in_landing ? '' : ' '.__('admin.attractions.zone_hidden_suffix')),
                                ])
                                ->all())
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->live(),
                        TextInput::make('position')
                            ->label(__('admin.attractions.field_position'))
                            ->helperText(__('admin.attractions.field_position_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('image')
                            ->label(__('admin.attractions.field_image'))
                            ->helperText(__('admin.attractions.field_image_hint'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        /*
                         * EL VÍDEO (el owner, 25-09: el «play» de las atracciones). Con él, la web pone el triángulo de
                         * «play» y el visor lo reproduce; sin él, la foto va sin play (`ClipTile.jsx`: un triángulo sobre
                         * una foto promete un vídeo que no existe). Un plano VERTICAL y corto (4–6 s del brief).
                         * ⚠️ 12 MB: el tope de la subida temporal de Livewire, que es quien la recibe; subirlo es tocar
                         * su configuración Y el servidor, así que el formulario lo dice en vez de fallar después.
                         */
                        FileUpload::make('video')
                            ->label(__('admin.attractions.field_video'))
                            ->helperText(__('admin.attractions.field_video_hint'))
                            ->disk(Attraction::VIDEO_DISK)
                            ->directory('atracciones')
                            ->visibility('public')
                            ->maxSize(12288)
                            ->acceptedFileTypes(['video/mp4', 'video/webm'])
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label(__('admin.attractions.field_is_active'))
                            ->helperText(__('admin.attractions.field_is_active_hint'))
                            ->default(true),
                        Toggle::make('is_special')
                            ->label(__('admin.attractions.field_is_special'))
                            ->helperText(__('admin.attractions.field_is_special_hint'))
                            ->default(false),
                        // ⚠️ Aquí estaba el selector de COMPLEMENTO vinculado (`#228`) y se retira en
                        // `#668` (F5 · T3). `#632`·P3, con la medida delante: las atracciones son
                        // presentación y **0 de 23** tenían complemento. Lo que se vende, se vende
                        // desde el catálogo; lo que restringe, vive en Normas.
                    ]),

                Tabs::make('translations')->tabs([
                    self::translatableTab('es', __('admin.attractions.lang.es')),
                    self::translatableTab('en', __('admin.attractions.lang.en')),
                    self::translatableTab('fr', __('admin.attractions.lang.fr')),
                ]),
            ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("name.{$locale}")
                ->label(__('admin.attractions.field_name'))
                ->required($locale === 'es')
                ->maxLength(120),
            Textarea::make("description.{$locale}")
                ->label(__('admin.attractions.field_description'))
                ->rows(3)
                ->maxLength(2000),
            TextInput::make("age.{$locale}")
                ->label(__('admin.attractions.field_age'))
                ->maxLength(60),
            TextInput::make("badge.{$locale}")
                ->label(__('admin.attractions.field_badge'))
                ->helperText($locale === 'es' ? __('admin.attractions.field_badge_hint') : null)
                ->maxLength(30),
        ]);
    }
}
