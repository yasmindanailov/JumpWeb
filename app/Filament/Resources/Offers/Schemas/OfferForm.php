<?php

namespace App\Filament\Resources\Offers\Schemas;

use App\Domain\Content\Models\Offer;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Formulario de alta/edición de una oferta (#270): clasificación (orden, activa) + IMAGEN subida
 * (FileUpload REAL al disco `uploads` → public/uploads/ofertas) + título i18n (es/en/fr). La limpieza
 * i18n y los defaults NOT NULL viven en `InteractsWithOfferForm`. La imagen es obligatoria: el modal
 * del widget muestra título + imagen, así que una oferta sin imagen no tendría sentido.
 */
class OfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.offers.section_classification'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('position')
                            ->label(__('admin.offers.field_position'))
                            ->helperText(__('admin.offers.field_position_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_active')
                            ->label(__('admin.offers.field_is_active'))
                            ->helperText(__('admin.offers.field_is_active_hint'))
                            ->default(true),
                        FileUpload::make('image')
                            ->label(__('admin.offers.field_image'))
                            ->helperText(__('admin.offers.field_image_hint'))
                            ->disk(Offer::IMAGE_DISK)
                            ->directory('ofertas')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/webp', 'image/jpeg', 'image/png'])
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Tabs::make('translations')->tabs([
                    self::translatableTab('es', __('admin.offers.lang.es')),
                    self::translatableTab('en', __('admin.offers.lang.en')),
                    self::translatableTab('fr', __('admin.offers.lang.fr')),
                ]),
            ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("title.{$locale}")
                ->label(__('admin.offers.field_title'))
                ->required($locale === 'es')
                ->maxLength(120),
        ]);
    }
}
