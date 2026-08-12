<?php

namespace App\Filament\Resources\Catalog\Schemas;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Filament\Resources\Catalog\CatalogResource;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Fase 7.6 — Formulario de ALTA y edición de un producto del catálogo (compartido por
 * `CreateCatalog` y `EditCatalog`).
 *
 * Organizado en bloques:
 *  - **Clasificación y estado**: tipo (editable solo al CREAR; en edición es de solo
 *    lectura — cambiar de tipo corrompe el aforo de productos vendidos), zona (bloqueada
 *    si el producto tiene ventas), activo/vendible/destacado.
 *  - **Textos** (i18n es/en/fr en pestañas): nombre, descripción, etc. + ventajas
 *    (una por línea).
 *  - **Operativa** (no complementos): duración, plazas, ventanas horarias.
 *  - **Pack** (solo packs): mín/máx invitados, señal, montaje/limpieza y el editor
 *    del esquema de **datos del evento** (`event_fields`).
 *  - **Precio** (editable, #189): un campo € por tarifa activa.
 *
 * Las transformaciones de i18n con listas (ventajas), la integridad del pack, el upsert
 * de precios y la auditoría del guardado viven en el trait `InteractsWithCatalogForm`
 * (compartido por las dos páginas), no aquí.
 */
class CatalogForm
{
    public static function configure(Schema $schema): Schema
    {
        // Una sola columna: el form de Filament por defecto usa 2 columnas y las
        // secciones de alturas dispares dejarían huecos verticales (CSS-grid estira
        // las filas — el mismo problema que la decisión #137). Apiladas a ancho
        // completo se leen limpias y sin huecos.
        return $schema
            ->columns(1)
            ->components([
                self::classificationSection(),
                self::translatableTabs(),
                self::operationalSection(),
                self::packSection(),
                self::priceSection(),
            ]);
    }

    private static function classificationSection(): Section
    {
        return Section::make(__('admin.catalog.section_classification'))
            ->description(__('admin.catalog.section_classification_hint'))
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    // El `type` gobierna qué secciones se ven (`live`). En CREACIÓN es un Select
                    // editable; en EDICIÓN es de SOLO LECTURA: cambiar de tipo movería el producto
                    // entre pools de aforo (entradas vs packs) y corrompería los pedidos históricos
                    // → se deshabilita y no se re-persiste (`dehydrated` solo en creación; además
                    // EditCatalog hace `unset($data['type'])` como defensa). El ORDEN de aparición
                    // se gestiona arrastrando en el listado del catálogo, no como campo aquí.
                    Select::make('type')
                        ->label(__('admin.catalog.col_type'))
                        ->options([
                            TicketType::TYPE_ENTRY => __('admin.catalog.types.entry'),
                            TicketType::TYPE_PACK => __('admin.catalog.types.pack'),
                            TicketType::TYPE_ADDON => __('admin.catalog.types.addon'),
                        ])
                        ->required()
                        ->native(false)
                        ->live()
                        ->default(TicketType::TYPE_ENTRY)
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated(fn (string $operation): bool => $operation === 'create')
                        ->helperText(fn (string $operation): string => $operation === 'create'
                            ? __('admin.catalog.type_create_hint')
                            : __('admin.catalog.type_locked_hint')),

                    Select::make('zone_id')
                        ->label(__('admin.catalog.col_zone'))
                        ->options(fn (): array => Zone::orderBy('position')->get()
                            ->mapWithKeys(fn (Zone $zone): array => [$zone->id => (string) $zone->tr('name')])
                            ->all())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('type') !== TicketType::TYPE_ADDON)
                        ->required(fn (Get $get): bool => $get('type') !== TicketType::TYPE_ADDON)
                        ->disabled(fn (?TicketType $record): bool => $record !== null && CatalogResource::hasSales($record))
                        ->helperText(fn (?TicketType $record): ?string => ($record !== null && CatalogResource::hasSales($record))
                            ? __('admin.catalog.zone_locked_sold')
                            : __('admin.catalog.zone_hint')),
                ]),

                Grid::make(['default' => 1, 'sm' => 3])->schema([
                    // Defaults EXPLÍCITOS para el alta (= defaults de la BD): un Toggle de Filament
                    // se dehidrata como `false` si no se toca, y al crear eso SOBREESCRIBIRÍA el
                    // default de la columna → un producto nuevo nacería oculto. Por eso fijamos
                    // is_active=true (nace visible), is_sellable=false y featured=false (borrador
                    // seguro: no se vende hasta marcarlo + ponerle precio). En edición el valor real
                    // del registro rehidrata y estos defaults no aplican.
                    Toggle::make('is_active')
                        ->label(__('admin.catalog.field_is_active'))
                        ->default(true)
                        ->helperText(__('admin.catalog.is_active_hint')),

                    Toggle::make('is_sellable')
                        ->label(__('admin.catalog.field_is_sellable'))
                        ->default(false)
                        ->helperText(__('admin.catalog.is_sellable_hint')),

                    // "Destacado" solo resalta la tarjeta de ENTRADA en la landing (clase
                    // price--feat); el pack y el complemento no lo consumen → solo para entry.
                    Toggle::make('featured')
                        ->label(__('admin.catalog.field_featured'))
                        ->default(false)
                        ->visible(fn (Get $get): bool => $get('type') === TicketType::TYPE_ENTRY)
                        ->helperText(__('admin.catalog.featured_hint')),
                ]),
            ]);
    }

    private static function translatableTabs(): Tabs
    {
        return Tabs::make('translations')->tabs([
            self::translatableTab('es', __('admin.catalog.lang.es')),
            self::translatableTab('en', __('admin.catalog.lang.en')),
            self::translatableTab('fr', __('admin.catalog.lang.fr')),
        ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("name.{$locale}")
                ->label(__('admin.catalog.field_name'))
                // El español es obligatorio (es la base del fallback de `tr()`); en/fr opcionales.
                ->required($locale === 'es')
                ->maxLength(255),

            // Unidad de precio ("por persona"…): la pintan la tarjeta de entrada y la de
            // pack; el COMPLEMENTO no la usa (auditoría de consumo por tipo) → se oculta
            // para addon (conserva su valor en BD si lo tuviera).
            TextInput::make("period_label.{$locale}")
                ->label(__('admin.catalog.field_period_label'))
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('type') !== TicketType::TYPE_ADDON)
                ->helperText(__('admin.catalog.period_label_hint')),

            // La descripción larga solo se pinta en la tarjeta de pack; el complemento usa
            // su lista de "ventajas", no la descripción → se oculta para addon.
            Textarea::make("description.{$locale}")
                ->label(__('admin.catalog.field_description'))
                ->rows(3)
                ->maxLength(2000)
                ->visible(fn (Get $get): bool => $get('type') !== TicketType::TYPE_ADDON),

            // La etiqueta destacada ("Top"…) solo la pinta la tarjeta de ENTRADA; ni el pack
            // ni el complemento la usan (auditoría de consumo por tipo) → solo para entry.
            TextInput::make("badge.{$locale}")
                ->label(__('admin.catalog.field_badge'))
                ->maxLength(60)
                ->visible(fn (Get $get): bool => $get('type') === TicketType::TYPE_ENTRY)
                ->helperText(__('admin.catalog.badge_hint')),

            Textarea::make("features_{$locale}")
                ->label(__('admin.catalog.field_features'))
                ->rows(4)
                ->helperText(__('admin.catalog.features_hint')),
        ]);
    }

    private static function operationalSection(): Section
    {
        return Section::make(__('admin.catalog.section_operational'))
            ->description(__('admin.catalog.section_operational_hint'))
            ->visible(fn (Get $get): bool => $get('type') !== TicketType::TYPE_ADDON)
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('duration_min')
                        ->label(__('admin.catalog.field_duration_min'))
                        ->numeric()
                        ->minValue(1)
                        ->helperText(__('admin.catalog.duration_min_hint')),

                    // Solo ENTRADAS (auditoría Fase 1 · L2): un PACK es «1 niño = 1 plaza» (el cupo
                    // lo gobiernan min/max_qty + los topes por franja); con `seats_per_unit>1` el
                    // checkout validaría el cupo en UNIDADES pero lo consumiría en PLAZAS → sobreventa.
                    // Para packs se fuerza a 1 en el guardado (regla 12). Los addons no consumen aforo.
                    TextInput::make('seats_per_unit')
                        ->label(__('admin.catalog.field_seats_per_unit'))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->visible(fn (Get $get): bool => $get('type') === TicketType::TYPE_ENTRY)
                        ->helperText(__('admin.catalog.seats_per_unit_hint')),

                    TextInput::make('available_after_open_min')
                        ->label(__('admin.catalog.field_available_after_open_min'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix(__('admin.catalog.minutes'))
                        ->helperText(__('admin.catalog.available_after_open_hint')),

                    TextInput::make('available_before_close_min')
                        ->label(__('admin.catalog.field_available_before_close_min'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix(__('admin.catalog.minutes'))
                        ->helperText(__('admin.catalog.available_before_close_hint')),

                    // ANTELACIÓN MÍNIMA de reserva (auditoría Fase 1): con cuánta antelación respecto
                    // a HOY se puede reservar (≠ ventana intra-día de arriba). 0 = sin restricción.
                    TextInput::make('min_advance_value')
                        ->label(__('admin.catalog.field_min_advance'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText(__('admin.catalog.min_advance_hint')),

                    Select::make('min_advance_unit')
                        ->label(__('admin.catalog.field_min_advance_unit'))
                        ->native(false)
                        ->default(TicketType::UNIT_DAYS)
                        ->options([
                            TicketType::UNIT_DAYS => __('admin.catalog.min_advance_units.days'),
                            TicketType::UNIT_HOURS => __('admin.catalog.min_advance_units.hours'),
                        ]),
                ]),
            ]);
    }

    private static function packSection(): Section
    {
        return Section::make(__('admin.catalog.section_pack'))
            ->description(__('admin.catalog.section_pack_hint'))
            ->visible(fn (Get $get): bool => $get('type') === TicketType::TYPE_PACK)
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('min_qty')
                        ->label(__('admin.catalog.field_min_qty'))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->helperText(__('admin.catalog.min_qty_hint')),

                    TextInput::make('max_qty')
                        ->label(__('admin.catalog.field_max_qty'))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        // El máximo no puede ser menor que el mínimo. Se usa el helper
                        // NATIVO de Filament `->gte()` (resuelve el statePath del form);
                        // `->rule('gte:min_qty')` NO funciona bajo el prefijo `data.` y
                        // bloquearía cualquier guardado del pack. Re-validado en EditCatalog.
                        ->gte('min_qty')
                        ->helperText(__('admin.catalog.max_qty_hint')),

                    Select::make('deposit_type')
                        ->label(__('admin.catalog.field_deposit_type'))
                        ->native(false)
                        ->live()
                        // Default = sin señal (= default de la columna NOT NULL); si no se fija,
                        // al crear un pack llegaría null y rompería el insert.
                        ->default(TicketType::DEPOSIT_NONE)
                        ->options([
                            TicketType::DEPOSIT_NONE => __('admin.catalog.deposit_types.none'),
                            TicketType::DEPOSIT_PERCENT => __('admin.catalog.deposit_types.percent'),
                            TicketType::DEPOSIT_FIXED => __('admin.catalog.deposit_types.fixed'),
                        ]),

                    TextInput::make('deposit_value')
                        ->label(__('admin.catalog.field_deposit_value'))
                        ->numeric()
                        // Auditoría Fase 1 (M1): si hay señal (tipo ≠ none) el valor debe ser ≥ 1 y
                        // obligatorio. Un `fixed`/`percent` con valor 0 cobraría 0 € online → Redsys
                        // rechaza y el checkout queda roto. Con `none` el campo es libre (se ignora).
                        ->required(fn (Get $get): bool => $get('deposit_type') !== TicketType::DEPOSIT_NONE)
                        ->minValue(fn (Get $get): int => $get('deposit_type') === TicketType::DEPOSIT_NONE ? 0 : 1)
                        // Si la señal es un porcentaje, el valor es 1–100 (en fijo son céntimos, sin tope).
                        ->maxValue(fn (Get $get): ?int => $get('deposit_type') === TicketType::DEPOSIT_PERCENT ? 100 : null)
                        ->helperText(fn (Get $get): string => match ($get('deposit_type')) {
                            TicketType::DEPOSIT_PERCENT => __('admin.catalog.deposit_value_percent'),
                            TicketType::DEPOSIT_FIXED => __('admin.catalog.deposit_value_fixed'),
                            default => __('admin.catalog.deposit_value_none'),
                        }),

                    TextInput::make('prep_before_min')
                        ->label(__('admin.catalog.field_prep_before_min'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix(__('admin.catalog.minutes'))
                        ->helperText(__('admin.catalog.prep_before_hint')),

                    TextInput::make('prep_after_min')
                        ->label(__('admin.catalog.field_prep_after_min'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix(__('admin.catalog.minutes'))
                        ->helperText(__('admin.catalog.prep_after_hint')),
                ]),

                // Editor del esquema de "datos del evento" del pack (#86). La estructura del
                // Repeater coincide 1:1 con la columna JSON `event_fields`
                // (`[{key,type,required,label:{es,en,fr}}]`).
                Repeater::make('event_fields')
                    ->label(__('admin.catalog.field_event_fields'))
                    ->helperText(__('admin.catalog.event_fields_hint'))
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            TextInput::make('key')
                                ->label(__('admin.catalog.event_field_key'))
                                ->required()
                                ->maxLength(40)
                                // Clave técnica: minúsculas, números, guion/guion bajo.
                                ->rule('regex:/^[a-z0-9_\-]+$/')
                                ->helperText(__('admin.catalog.event_field_key_hint')),

                            Select::make('type')
                                ->label(__('admin.catalog.event_field_type'))
                                ->native(false)
                                ->required()
                                ->default('text')
                                ->options([
                                    'text' => __('admin.catalog.event_field_types.text'),
                                    'number' => __('admin.catalog.event_field_types.number'),
                                    'textarea' => __('admin.catalog.event_field_types.textarea'),
                                ]),

                            Toggle::make('required')
                                ->label(__('admin.catalog.event_field_required'))
                                ->default(false),
                        ]),

                        // Fase de captura (#217): `booking` = se pide al RESERVAR; `postform` = se pide
                        // en el formulario POSTERIOR a la reserva (p. ej. nº aproximado de adultos,
                        // observaciones). Solo en event_fields (los guest_fields van siempre al post-form).
                        Select::make('stage')
                            ->label(__('admin.catalog.event_field_stage'))
                            ->native(false)
                            ->default(TicketType::EVENT_STAGE_BOOKING)
                            ->options([
                                TicketType::EVENT_STAGE_BOOKING => __('admin.catalog.event_field_stages.booking'),
                                TicketType::EVENT_STAGE_POSTFORM => __('admin.catalog.event_field_stages.postform'),
                            ])
                            ->helperText(__('admin.catalog.event_field_stage_hint')),

                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            TextInput::make('label.es')
                                ->label(__('admin.catalog.event_field_label').' (ES)')
                                ->required()
                                ->maxLength(120),
                            TextInput::make('label.en')
                                ->label(__('admin.catalog.event_field_label').' (EN)')
                                ->maxLength(120),
                            TextInput::make('label.fr')
                                ->label(__('admin.catalog.event_field_label').' (FR)')
                                ->maxLength(120),
                        ]),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['key'] ?? null)
                    ->addActionLabel(__('admin.catalog.event_field_add'))
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(0),

                // Editor del esquema de "datos por niño" del pack (post-form, #217). Estructura 1:1
                // con la columna JSON `guest_fields` (mismo formato que `event_fields`); cada RESERVA
                // guarda en `order_items.guest_data` una lista con un objeto por invitado. La clienta
                // ajusta aquí qué columnas se piden de cada niño (por defecto: nombre/alergia/
                // observaciones/menú especial). El formulario que el cliente rellena llega en iter. 2.
                Repeater::make('guest_fields')
                    ->label(__('admin.catalog.field_guest_fields'))
                    ->helperText(__('admin.catalog.guest_fields_hint'))
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            TextInput::make('key')
                                ->label(__('admin.catalog.event_field_key'))
                                ->required()
                                ->maxLength(40)
                                ->rule('regex:/^[a-z0-9_\-]+$/')
                                ->helperText(__('admin.catalog.event_field_key_hint')),

                            Select::make('type')
                                ->label(__('admin.catalog.event_field_type'))
                                ->native(false)
                                ->required()
                                ->default('text')
                                ->options([
                                    'text' => __('admin.catalog.event_field_types.text'),
                                    'number' => __('admin.catalog.event_field_types.number'),
                                    'textarea' => __('admin.catalog.event_field_types.textarea'),
                                ]),

                            Toggle::make('required')
                                ->label(__('admin.catalog.event_field_required'))
                                ->default(false),
                        ]),

                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            TextInput::make('label.es')
                                ->label(__('admin.catalog.event_field_label').' (ES)')
                                ->required()
                                ->maxLength(120),
                            TextInput::make('label.en')
                                ->label(__('admin.catalog.event_field_label').' (EN)')
                                ->maxLength(120),
                            TextInput::make('label.fr')
                                ->label(__('admin.catalog.event_field_label').' (FR)')
                                ->maxLength(120),
                        ]),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['key'] ?? null)
                    ->addActionLabel(__('admin.catalog.guest_field_add'))
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(0),
            ]);
    }

    private static function priceSection(): Section
    {
        // Un campo de precio (en euros) por cada tarifa ACTIVA. El schema es un closure para
        // que la matriz se ajuste si cambian las tarifas. El fill (en edición) y el upsert
        // (euros↔céntimos) viven en el trait/páginas; la columna `prices` (céntimos) sigue
        // siendo la única fuente de verdad.
        return Section::make(__('admin.catalog.section_price'))
            ->description(__('admin.catalog.price_section_hint'))
            ->schema(fn (): array => self::priceFields());
    }

    /**
     * Un `TextInput` (€) por tarifa activa, nombrado `price_rate_{id}`. Vacío = sin precio para
     * esa tarifa (el producto no es vendible los días en que esa tarifa aplica). Si no hay
     * ninguna tarifa activa, un aviso (en vez de una sección vacía sin explicación).
     *
     * @return array<int, TextInput|Placeholder>
     */
    private static function priceFields(): array
    {
        $rates = RateType::where('is_active', true)->orderBy('priority')->get();

        if ($rates->isEmpty()) {
            return [
                Placeholder::make('price_no_rates')
                    ->hiddenLabel()
                    ->content(__('admin.catalog.price_no_rates')),
            ];
        }

        return $rates
            ->map(fn (RateType $rate): TextInput => TextInput::make("price_rate_{$rate->id}")
                ->label((string) ($rate->tr('label') ?? $rate->key))
                ->helperText($rate->is_special
                    ? __('admin.catalog.price_rate_special_hint')
                    : __('admin.catalog.price_rate_normal_hint'))
                ->numeric()
                ->minValue(0)
                // Tope de negocio: evita desbordar `amount_cents` (unsignedInteger, máx ~42,9 M €).
                ->maxValue(99999.99)
                ->step('0.01')
                ->prefix('€'))
            ->all();
    }
}
