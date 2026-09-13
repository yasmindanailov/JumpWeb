<?php

namespace App\Filament\Resources\LandingServices\Schemas;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Models\LandingService;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Formulario de alta/edición de un servicio de la landing (#256). Identidad (slug/anchor, imagen,
 * orden, toggles, pack vinculado) + textos i18n (es/en/fr): título, palabra de acento, etiqueta de
 * zona, subtítulo del nav, descripción y «specs» (condiciones rápidas, repeater). La limpieza i18n,
 * la normalización de imagen y los defaults viven en `InteractsWithLandingServiceForm`.
 *
 * La imagen es una RUTA relativa a `public/` (coherente con atracciones/zonas; la subida de
 * ficheros llegará con la galería). Los PACKS son OPCIONALES (`#588`, varios): cada uno comprable sale
 * con su tabla y su «Reservar»; sin packs, CTA «Pedir información».
 */
class LandingServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.landing_services.section_identity'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('slug')
                            ->label(__('admin.landing_services.field_slug'))
                            ->helperText(__('admin.landing_services.field_slug_hint'))
                            ->required()
                            ->maxLength(60)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            // Inmutable al editar: es el ANCHOR (/servicios#slug) y la clave de
                            // updateOrCreate del seeder; renombrarlo rompería enlaces y duplicaría.
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                        TextInput::make('position')
                            ->label(__('admin.landing_services.field_position'))
                            ->helperText(__('admin.landing_services.field_position_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('image')
                            ->label(__('admin.landing_services.field_image'))
                            ->helperText(__('admin.landing_services.field_image_hint'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        // Los packs que se venden desde este servicio (`#588`: varios; una excursión son
                        // dos). Si alguno no es realmente comprable se avisa: no saldría su tabla
                        // (coherencia #226). ⚠️ Un pack está en UN servicio como mucho: la regla lo dice
                        // antes de que lo diga el índice único con un error de base de datos.
                        Select::make('products')
                            ->label(__('admin.landing_services.field_pack'))
                            ->helperText(fn ($state): string => self::packWarning(array_map('intval', (array) $state))
                                ?? __('admin.landing_services.field_pack_hint'))
                            ->relationship(
                                'products',
                                'id',
                                fn (Builder $query): Builder => $query->where('ticket_types.type', TicketType::TYPE_PACK),
                            )
                            ->getOptionLabelFromRecordUsing(fn (TicketType $pack): string => (string) $pack->tr('name'))
                            ->multiple()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->rules([
                                fn (?LandingService $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $ids = array_map('intval', (array) $value);
                                    $taken = $ids !== [] && TicketType::query()->whereKey($ids)
                                        ->whereHas('landingServices', fn (Builder $q): Builder => $record === null
                                            ? $q
                                            : $q->whereKeyNot($record->getKey()))
                                        ->exists();
                                    if ($taken) {
                                        $fail(__('admin.landing_services.pack_taken'));
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label(__('admin.landing_services.field_is_active'))
                            ->helperText(__('admin.landing_services.field_is_active_hint'))
                            ->default(true),
                        // ⚠️ Aquí vivía el interruptor «Sale en el menú» (`show_in_nav`), y se retira
                        // con su consumidor (`#521`, `[DECIDIDO owner]`): los destinos del menú son
                        // el inventario de páginas y un servicio ya no es un destino suelto. Un
                        // control que no gobierna nada engaña a quien lo toca. La COLUMNA se queda
                        // en la base —con el valor que tuviera— por si algún día vuelve a hacer falta.
                    ]),

                Tabs::make('translations')->tabs([
                    self::translatableTab('es', __('admin.landing_services.lang.es')),
                    self::translatableTab('en', __('admin.landing_services.lang.en')),
                    self::translatableTab('fr', __('admin.landing_services.lang.fr')),
                ]),
            ]);
    }

    /**
     * Aviso si ALGUNO de los packs elegidos NO será comprable en la landing (sin precio, no vendible,
     * inactivo, sin zona o zona desactivada): no saldría su tabla. Delega en la fuente única
     * `TicketType::isSellablePackForLanding()` (la misma que decide el render). null = todo coherente.
     *
     * @param  list<int>  $packIds
     */
    private static function packWarning(array $packIds): ?string
    {
        if ($packIds === []) {
            return null;
        }

        $noVendible = TicketType::with('zone')->whereKey($packIds)->get()
            ->contains(fn (TicketType $pack): bool => ! $pack->isSellablePackForLanding());

        return $noVendible ? __('admin.landing_services.pack_not_purchasable_warning') : null;
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("title.{$locale}")
                ->label(__('admin.landing_services.field_title'))
                ->required($locale === 'es')
                ->maxLength(120),
            TextInput::make("accent_word.{$locale}")
                ->label(__('admin.landing_services.field_accent_word'))
                ->helperText($locale === 'es' ? __('admin.landing_services.field_accent_word_hint') : null)
                ->maxLength(40),
            TextInput::make("zone_label.{$locale}")
                ->label(__('admin.landing_services.field_zone_label'))
                ->helperText($locale === 'es' ? __('admin.landing_services.field_zone_label_hint') : null)
                ->maxLength(60),
            // ⚠️ Aquí vivía «Subtítulo en el menú» (`nav_subtitle`), y se va con el interruptor por el
            // mismo motivo (`#521`): lo pintaba el menú viejo debajo del servicio, y el menú ya no
            // ofrece servicios sueltos. La columna y lo que tuviera escrito se conservan.
            Textarea::make("body.{$locale}")
                ->label(__('admin.landing_services.field_body'))
                ->rows(4)
                ->maxLength(2000),
            Repeater::make("specs.{$locale}")
                ->label(__('admin.landing_services.field_specs'))
                ->helperText($locale === 'es' ? __('admin.landing_services.field_specs_hint') : null)
                ->schema([
                    TextInput::make('label')
                        ->label(__('admin.landing_services.field_spec_label'))
                        ->maxLength(60),
                    TextInput::make('value')
                        ->label(__('admin.landing_services.field_spec_value'))
                        ->maxLength(120),
                ])
                ->columns(2)
                ->maxItems(6)
                ->reorderable()
                ->addActionLabel(__('admin.landing_services.add_spec'))
                ->defaultItems(0)
                ->collapsible(),
        ]);
    }
}
