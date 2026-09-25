<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Domain\Booking\Models\Promotion;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * El formulario de una promoción (`docs/specs/promociones.md` §4.2), compartido por crear y editar: QUÉ es (clase y
 * texto en tres idiomas), DÓNDE sale (el objetivo) y CUÁNDO (sus fechas). La limpieza y el objetivo, que no es una
 * columna, viven en `InteractsWithPromotionForm`.
 */
class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.promotions.section_what'))
                    ->columns(2)
                    ->schema([
                        Select::make('kind')
                            ->label(__('admin.promotions.field_kind'))
                            ->helperText(__('admin.promotions.field_kind_hint'))
                            ->options(fn (): array => collect(Promotion::KINDS)
                                ->mapWithKeys(fn (string $k): array => [$k => __('admin.promotions.kinds.'.$k)])
                                ->all())
                            ->default(Promotion::KIND_OFFER)
                            ->required()
                            ->live()
                            ->native(false),
                        Toggle::make('is_active')
                            ->label(__('admin.promotions.field_is_active'))
                            ->helperText(__('admin.promotions.field_is_active_hint'))
                            ->default(true),
                    ]),

                Section::make(__('admin.promotions.section_where'))
                    ->columns(2)
                    ->schema([
                        /*
                         * El OBJETIVO no es una columna: se deriva de las dos claves (`Promotion::target()`), y el
                         * formulario lo pide primero para enseñar solo el selector que toca. Guardar pone a `null` la
                         * clave que no aplica (`InteractsWithPromotionForm`): cambiar de «zona» a «instalación» no
                         * deja la zona de antes escondida en la fila.
                         */
                        Select::make('target')
                            ->label(__('admin.promotions.field_target'))
                            ->helperText(__('admin.promotions.field_target_hint'))
                            ->options(fn (): array => collect(Promotion::TARGETS)
                                ->mapWithKeys(fn (string $t): array => [$t => __('admin.promotions.targets.'.$t)])
                                ->all())
                            ->default(Promotion::TARGET_INSTALLATION)
                            ->required()
                            ->live()
                            ->native(false),
                        Select::make('zone_id')
                            ->label(__('admin.promotions.field_zone'))
                            ->options(fn (): array => Zone::query()->orderBy('position')->get()
                                ->mapWithKeys(fn (Zone $z): array => [$z->id => (string) $z->tr('name')])
                                ->all())
                            ->visible(fn (Get $get): bool => $get('target') === Promotion::TARGET_ZONE)
                            ->required(fn (Get $get): bool => $get('target') === Promotion::TARGET_ZONE)
                            ->native(false),
                        Select::make('ticket_type_id')
                            ->label(__('admin.promotions.field_product'))
                            ->options(fn (): array => TicketType::query()->orderBy('position')->get()
                                ->mapWithKeys(fn (TicketType $p): array => [$p->id => (string) $p->tr('name')])
                                ->all())
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('target') === Promotion::TARGET_PRODUCT)
                            ->required(fn (Get $get): bool => $get('target') === Promotion::TARGET_PRODUCT)
                            ->native(false),
                    ]),

                Section::make(__('admin.promotions.section_when'))
                    ->columns(3)
                    ->schema([
                        DatePicker::make('starts_on')
                            ->label(__('admin.promotions.field_starts_on'))
                            ->helperText(__('admin.promotions.field_starts_on_hint'))
                            ->native(false),
                        /*
                         * ⚠️ OBLIGATORIA en una oferta (el modelo también lo exige): una oferta sin fin no es una
                         * oferta, y es la fecha que la web escribe al lado —«Hasta el 30 de septiembre»— y la que da a
                         * la isla sus tres últimos días.
                         */
                        DatePicker::make('ends_on')
                            ->label(__('admin.promotions.field_ends_on'))
                            ->helperText(fn (Get $get): string => __($get('kind') === Promotion::KIND_OFFER
                                ? 'admin.promotions.field_ends_on_hint_offer'
                                : 'admin.promotions.field_ends_on_hint_gift'))
                            ->required(fn (Get $get): bool => $get('kind') === Promotion::KIND_OFFER)
                            ->afterOrEqual('starts_on')
                            ->native(false),
                        TextInput::make('position')
                            ->label(__('admin.promotions.field_position'))
                            ->helperText(__('admin.promotions.field_position_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),

                Tabs::make('translations')->tabs([
                    self::translatableTab('es', __('admin.promotions.lang.es')),
                    self::translatableTab('en', __('admin.promotions.lang.en')),
                    self::translatableTab('fr', __('admin.promotions.lang.fr')),
                ]),
            ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            Textarea::make("text.{$locale}")
                ->label(__('admin.promotions.field_text'))
                ->helperText(fn (Get $get): string => __($get('kind') === Promotion::KIND_OFFER
                    ? 'admin.promotions.field_text_hint_offer'
                    : 'admin.promotions.field_text_hint_gift'))
                ->required($locale === 'es')
                ->rows(2)
                ->maxLength(160),
        ]);
    }
}
