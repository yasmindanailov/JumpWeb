<?php

namespace App\Filament\Resources\BarImages\Schemas;

use App\Domain\Content\Models\BarImage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Formulario de una imagen del bar (`DECISIONES #536`).
 *
 * ❗❗ **EL TEXTO ALTERNATIVO ES OBLIGATORIO, y aquí no es burocracia.** La carta se publica como
 * IMAGEN (`[DECIDIDO owner]`), así que el `alt` es **lo único** que va a encontrar un lector de
 * pantalla, un buscador o alguien con las imágenes desactivadas: sin él, la página no dice nada de
 * lo que hay en la carta. Se exige en español, como el título de una oferta; los otros dos idiomas
 * son opcionales y caen al español si faltan.
 */
class BarImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.bar_images.section_image'))
                    ->description(__('admin.bar_images.section_image_hint'))
                    ->columns(2)
                    ->schema([
                        Select::make('kind')
                            ->label(__('admin.bar_images.field_kind'))
                            ->helperText(__('admin.bar_images.field_kind_hint'))
                            ->options([
                                BarImage::KIND_MENU => __('admin.bar_images.kind.menu'),
                                BarImage::KIND_VENUE => __('admin.bar_images.kind.venue'),
                            ])
                            ->default(BarImage::KIND_MENU)
                            ->required()
                            ->native(false),

                        TextInput::make('position')
                            ->label(__('admin.bar_images.field_position'))
                            ->helperText(__('admin.bar_images.field_position_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        Toggle::make('is_active')
                            ->label(__('admin.bar_images.field_is_active'))
                            ->helperText(__('admin.bar_images.field_is_active_hint'))
                            ->default(true),

                        /*
                         * ⚠️ **3 MB y no más.** Una carta escaneada a 300 ppp se va a 8–10 MB y la
                         * descarga **el visitante**, en el móvil y en la puerta del parque. El editor
                         * de imagen va activado a propósito: es donde se recorta el margen blanco del
                         * escáner, que es la mitad del peso.
                         */
                        FileUpload::make('image')
                            ->label(__('admin.bar_images.field_image'))
                            ->helperText(__('admin.bar_images.field_image_hint'))
                            ->disk(BarImage::IMAGE_DISK)
                            ->directory('bar')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->maxSize(3072)
                            ->acceptedFileTypes(['image/webp', 'image/jpeg', 'image/png'])
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('admin.bar_images.section_alt'))
                    ->description(__('admin.bar_images.section_alt_hint'))
                    ->schema([
                        Tabs::make('translations')->tabs([
                            self::translatableTab('es', __('admin.bar_images.lang.es')),
                            self::translatableTab('en', __('admin.bar_images.lang.en')),
                            self::translatableTab('fr', __('admin.bar_images.lang.fr')),
                        ]),
                    ]),
            ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("alt.{$locale}")
                ->label(__('admin.bar_images.field_alt'))
                ->helperText($locale === 'es' ? __('admin.bar_images.field_alt_hint') : null)
                ->required($locale === 'es')
                ->maxLength(200),
        ]);
    }
}
