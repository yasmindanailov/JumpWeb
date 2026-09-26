<?php

namespace App\Filament\Resources\Catalog\Schemas;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ProductIcon;
use App\Filament\Resources\Catalog\CatalogResource;
use Filament\Forms\Components\FileUpload;
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
                self::occupancySection(),
                self::packSection(),
                self::priceSection(),
                self::priceTiersSection(),
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

                    // ⚠️ El icono que marca el producto en la cesta, el resumen y «Mis pedidos»
                    // (`DECISIONES #140`). Lo decidía un booleano —tarta si es pack, entrada si no—,
                    // así que la tirolina, la tarta y los calcetines eran los tres «un ticket».
                    // ⚠️ Lista CURADA, no subida de ficheros (owner, `specs/landing-white-label.md`
                    // §4.6): un SVG subido es código ejecutable y rompería la paridad de dibujos
                    // entre el cajón y la landing, que hoy es comprobable byte a byte.
                    // Vacío ⇒ el que le toca por su tipo, que es el aspecto actual.
                    Select::make('icon')
                        ->label(__('admin.catalog.field_icon'))
                        ->options(array_combine(
                            ProductIcon::CHOICES,
                            array_map(fn (string $k): string => __('admin.catalog.icon_option.'.$k), ProductIcon::CHOICES),
                        ))
                        ->native(false)
                        ->placeholder(__('admin.catalog.icon_placeholder'))
                        ->helperText(__('admin.catalog.icon_hint')),
                ]),

                // **LA FOTO de la ficha** (`DECISIONES #632` P1, T6 del menú de hechos). La sirve
                // `GET /catalog/products` como URL absoluta, y es lo que pinta una tarjeta de
                // catálogo quien vende SIN landing — la app de F6, o la landing de la instancia.
                //
                // ⚠️ **Esto sí es una subida, y el `icon` de arriba sigue sin serlo**: no se
                // contradicen. Aquél es un DIBUJO de interfaz y un SVG subido sería código
                // ejecutable; ésta es una FOTOGRAFÍA, y los tipos aceptados no incluyen SVG.
                //
                // ⚠️⚠️ Va al disco `uploads` (`public/uploads`, gitignorado y excluido del
                // `rsync --delete` del despliegue), que es el hueco de la instalación: así la
                // clienta cambia la foto sin commitear nada al repo del producto. El fichero
                // antiguo lo borra `TicketType::booted()` — `FileUpload` no lo hace solo.
                //
                // Opcional a propósito: una instalación sin fotos es válida y la API se calla el
                // campo (la receta del menú: lo que no se rellenó no viaja).
                FileUpload::make('image')
                    ->label(__('admin.catalog.field_image'))
                    ->helperText(__('admin.catalog.field_image_hint'))
                    ->disk(TicketType::IMAGE_DISK)
                    ->directory('productos')
                    ->visibility('public')
                    ->image()
                    ->imageEditor()
                    ->maxSize(3072)
                    ->acceptedFileTypes(['image/webp', 'image/jpeg', 'image/png'])
                    ->columnSpanFull(),
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

            // La etiqueta destacada ("Top"…) la pintan la ENTRADA y el PACK: desde `#585` sale donde
            // salga el producto —carril de tarifas, `/precios`, tarjetas de cumpleaños, `/cumpleanos`,
            // `/servicios` y el cajón—. El complemento no la consume → oculta para addon.
            TextInput::make("badge.{$locale}")
                ->label(__('admin.catalog.field_badge'))
                ->maxLength(60)
                ->visible(fn (Get $get): bool => $get('type') !== TicketType::TYPE_ADDON)
                ->helperText(__('admin.catalog.badge_hint')),

            Textarea::make("features_{$locale}")
                ->label(__('admin.catalog.field_features'))
                ->rows(4)
                ->helperText(__('admin.catalog.features_hint')),

            // LA MERIENDA DE LA INVITACIÓN por grupos (F1b de `fiesta-sistema-nuevo.md`): tres listas i18n que la
            // invitación pinta con su icono («Para beber», «Para comer», «Y para terminar»). Solo tienen sentido en un
            // complemento que un pack enseña en la invitación; vacías, el complemento sale por su nombre y sus ventajas.
            Textarea::make("menu_drink_{$locale}")
                ->label(__('admin.catalog.field_menu_drink'))
                ->rows(2)
                ->visible(fn (Get $get): bool => $get('type') === TicketType::TYPE_ADDON)
                ->helperText(__('admin.catalog.menu_groups_hint')),
            Textarea::make("menu_food_{$locale}")
                ->label(__('admin.catalog.field_menu_food'))
                ->rows(2)
                ->visible(fn (Get $get): bool => $get('type') === TicketType::TYPE_ADDON),
            Textarea::make("menu_sweet_{$locale}")
                ->label(__('admin.catalog.field_menu_sweet'))
                ->rows(2)
                ->visible(fn (Get $get): bool => $get('type') === TicketType::TYPE_ADDON),

            // EL AVISO DEL COMPLEMENTO EN MI CUENTA (`#775`): lo que la reserva del cliente dice de él, con `:n` por la
            // cantidad («Tenéis :n pares de calcetines comprados; os los damos en la puerta.»). Dónde se recoge un
            // complemento es de cada instalación, no del producto; vacío, la reserva lo nombra con su cantidad.
            TextInput::make("reservation_note.{$locale}")
                ->label(__('admin.catalog.field_reservation_note'))
                ->maxLength(200)
                ->visible(fn (Get $get): bool => $get('type') === TicketType::TYPE_ADDON)
                ->helperText(__('admin.catalog.reservation_note_hint')),

            // LOS REGALOS (`#589`) viven en PROMOCIONES desde `#770`: aquí solo se ENSEÑAN, para que quien edita
            // la ficha sepa qué se anuncia con ella y dónde cambiarlo. Una sola vez, en la pestaña del español.
            Placeholder::make('gifts_managed_in_promotions')
                ->label(__('admin.catalog.field_gifts'))
                ->content(fn (?TicketType $record): string => $record !== null && $record->giftLines() !== []
                    ? implode(' · ', $record->giftLines())
                    : __('admin.catalog.gifts_none'))
                ->helperText(__('admin.catalog.gifts_hint'))
                ->visible($locale === 'es'),
        ]);
    }

    /**
     * La HORA EXTRA (`specs/hora-extra.md` §4.9): la sección que hace ENCENDIBLE el interruptor.
     * Hasta `#410` era imposible: `CatalogForm` escondía `duration_min` a los complementos y
     * `normalizeByType()` la borraba al guardar — el panel destruía el dato del que depende el
     * diseño. Es una sección PROPIA (solo complementos) y no un destape de la operativa, porque un
     * complemento no usa ventanas horarias ni antelaciones: solo QUE ocupa y CUÁNTO.
     *
     * ⚠️⚠️ Con ventas hechas, ni el interruptor se apaga ni la duración se mueve: `occupancyMap`
     * lee la duración del PRODUCTO para cada línea vendida, así que cambiarla re-interpreta el
     * aforo de lo YA vendido (apagarla lo dejaría en «hasta el cierre»). Es el borde 9 de §4.6
     * aplicado al complemento, con el mismo candado que la zona (`zone_locked_sold`).
     * Encenderlo con ventas NEUTRAS previas sí se puede: esas hijas tienen `slot_id` nulo y el
     * aforo no las cuenta — solo cambia lo que se venda a partir de ahora.
     */
    private static function occupancySection(): Section
    {
        $lockedAsOccupant = fn (?TicketType $record): bool => $record !== null
            && $record->occupies_after_parent === true
            && CatalogResource::hasSales($record);

        // Su hermano (`specs/hora-extra.md` §10.3.1): con ventas hechas el interruptor tampoco se
        // apaga, y el motivo es OTRO —los minutos ya están materializados en cada línea, así que el
        // aforo de lo vendido no se re-interpreta—: lo que se rompe es la EDICIÓN. El editor
        // reconoce a sus hijas por este interruptor, así que apagarlo haría que el siguiente
        // guardado del padre las diera por inexistentes y **acortara la fiesta sin que nadie lo pida**.
        $lockedAsExtender = fn (?TicketType $record): bool => $record !== null
            && $record->extends_parent_stay === true
            && CatalogResource::hasSales($record);

        return Section::make(__('admin.catalog.section_occupancy'))
            ->description(__('admin.catalog.section_occupancy_hint'))
            ->visible(fn (Get $get): bool => $get('type') === TicketType::TYPE_ADDON)
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    Toggle::make('occupies_after_parent')
                        ->label(__('admin.catalog.field_occupies_after_parent'))
                        ->default(false)
                        ->live()
                        ->disabled($lockedAsOccupant)
                        ->dehydrated(fn (?TicketType $record): bool => ! $lockedAsOccupant($record))
                        ->helperText(fn (?TicketType $record): string => $lockedAsOccupant($record)
                            ? __('admin.catalog.occupancy_locked_sold')
                            : __('admin.catalog.occupies_after_parent_hint')),

                    // La hora extra de un PACK (§10): el hermano EXCLUYENTE del de arriba. Los dos
                    // «prolongan la estancia», pero el ocupante se vende por PERSONA y éste por
                    // BLOQUE DE TIEMPO — por eso son dos productos y no uno con dos modos. Cada uno
                    // se esconde cuando el otro está puesto: la combinación no existe y el dominio
                    // la rechaza, así que ofrecerla en pantalla solo produciría un error al guardar.
                    Toggle::make('extends_parent_stay')
                        ->label(__('admin.catalog.field_extends_parent_stay'))
                        ->default(false)
                        ->live()
                        ->visible(fn (Get $get): bool => ! (bool) $get('occupies_after_parent'))
                        ->disabled($lockedAsExtender)
                        ->dehydrated(fn (?TicketType $record): bool => ! $lockedAsExtender($record))
                        ->helperText(fn (?TicketType $record): string => $lockedAsExtender($record)
                            ? __('admin.catalog.stay_extension_locked_sold')
                            : __('admin.catalog.extends_parent_stay_hint')),
                ]),

                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    // El MISMO `duration_min` de la operativa (una columna, dos puertas excluyentes
                    // por tipo): aquí es «cuánto ocupa» —o «cuánto alarga»— y solo existe si uno de
                    // los dos interruptores está puesto.
                    TextInput::make('duration_min')
                        ->label(fn (Get $get): string => (bool) $get('extends_parent_stay')
                            ? __('admin.catalog.field_extends_duration_min')
                            : __('admin.catalog.field_occupies_duration_min'))
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn (Get $get): bool => (bool) $get('occupies_after_parent') || (bool) $get('extends_parent_stay'))
                        ->required(fn (Get $get): bool => (bool) $get('occupies_after_parent') || (bool) $get('extends_parent_stay'))
                        ->disabled(fn (?TicketType $record): bool => $lockedAsOccupant($record) || $lockedAsExtender($record))
                        ->dehydrated(fn (?TicketType $record): bool => ! $lockedAsOccupant($record) && ! $lockedAsExtender($record))
                        ->helperText(fn (?TicketType $record, Get $get): string => match (true) {
                            $lockedAsOccupant($record) => __('admin.catalog.occupancy_locked_sold'),
                            $lockedAsExtender($record) => __('admin.catalog.stay_extension_locked_sold'),
                            (bool) $get('extends_parent_stay') => __('admin.catalog.extends_duration_min_hint'),
                            default => __('admin.catalog.occupies_duration_min_hint'),
                        }),
                ]),
            ]);
    }

    private static function operationalSection(): Section
    {
        return Section::make(__('admin.catalog.section_operational'))
            ->description(__('admin.catalog.section_operational_hint'))
            ->visible(fn (Get $get): bool => $get('type') !== TicketType::TYPE_ADDON)
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    // ⚠️ Con ventas hechas la duración NO se mueve (borde 9 de `specs/hora-extra.md`
                    // §4.6): `occupancyMap` la lee del PRODUCTO para cada línea vendida, así que
                    // alargarla mete el tramo del padre encima de su propia hora extra (la misma
                    // persona contada dos veces) y en general re-interpreta el aforo de lo ya
                    // vendido. El mismo candado que la zona, que existe por el mismo motivo.
                    TextInput::make('duration_min')
                        ->label(__('admin.catalog.field_duration_min'))
                        ->numeric()
                        ->minValue(1)
                        ->disabled(fn (?TicketType $record): bool => $record !== null && CatalogResource::hasSales($record))
                        ->dehydrated(fn (?TicketType $record): bool => $record === null || ! CatalogResource::hasSales($record))
                        ->helperText(fn (?TicketType $record): string => ($record !== null && CatalogResource::hasSales($record))
                            ? __('admin.catalog.duration_locked_sold')
                            : __('admin.catalog.duration_min_hint')),

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

                    // EL PLAZO DE CAMBIO Y CANCELACIÓN (`#699`): lo que la página, la isla y el pago DICEN. No
                    // bloquea nada —los cambios los hace el personal—, así que no hay candado por ventas.
                    TextInput::make('cancellation_cutoff_hours')
                        ->label(__('admin.catalog.field_cancellation_cutoff_hours'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(8760)
                        ->suffix('h')
                        ->helperText(__('admin.catalog.cancellation_cutoff_hint')),
                ]),

                // LA EDAD DE UNA ENTRADA (`#761`): la que se DICE («de 4 a 7 años»). Son las MISMAS columnas que la
                // sección del pack (`guest_age_min`/`max`, publicadas en el catálogo desde `#676`), que en un pack
                // además deciden el tramo de su familia; aquí solo aparecen en las entradas, y en un pack solo
                // aparecen allí: nunca se ven las dos a la vez. Hasta `#761` una entrada no podía declararla.
                Grid::make(['default' => 1, 'sm' => 2])
                    ->visible(fn (Get $get): bool => $get('type') === TicketType::TYPE_ENTRY)
                    ->schema([
                        TextInput::make('guest_age_min')
                            ->label(__('admin.catalog.field_guest_age_min'))
                            ->numeric()
                            ->minValue(TicketType::GUEST_AGE_MIN)
                            ->maxValue(TicketType::GUEST_AGE_MAX)
                            ->helperText(__('admin.catalog.entry_age_min_hint')),
                        TextInput::make('guest_age_max')
                            ->label(__('admin.catalog.field_guest_age_max'))
                            ->numeric()
                            ->minValue(TicketType::GUEST_AGE_MIN)
                            ->maxValue(TicketType::GUEST_AGE_MAX)
                            ->gte('guest_age_min')
                            ->helperText(__('admin.catalog.entry_age_max_hint')),
                    ]),

                // EL JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2,
                // `[DECIDIDO owner, 2026-09-01]`). Vive AQUÍ y no en la sección del pack a propósito:
                // el caso que originó la feature —el amigo del hijo— son ENTRADAS sueltas, y la
                // sección del pack solo se ve en packs (§1.5: «no puede apoyarse en nada que solo
                // tengan los packs»).
                Select::make('guardian_authorization')
                    ->label(__('admin.catalog.field_guardian_authorization'))
                    ->helperText(__('admin.catalog.guardian_authorization_hint'))
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->default(TicketType::GUARDIAN_NONE)
                    // Derivadas de la constante y no escritas a mano: es la lección de
                    // `fieldTypeOptions()` de aquí al lado, donde dos listas copiadas divergieron.
                    ->options(collect(TicketType::GUARDIAN_MODES)
                        ->mapWithKeys(fn (string $mode): array => [$mode => __('admin.catalog.guardian_modes.'.$mode)])
                        ->all()),

                // LA INVITACIÓN DIGITAL (`specs/celebracion-e-invitacion.md` §4.8 D15, `#575`), junto a
                // su hermano: los dos contestan a «¿qué papeles pide este producto?».
                //
                // ⚠️⚠️ **La autoridad es el guard de `TicketType::saving()`, no este campo.** Exige un
                // PACK, con columna de nombre en sus datos por invitado, y justificante distinto de
                // «obligatorio» — y lo exige también a los seeders y a un `update()` a mano, que es por
                // donde entraría la combinación imposible sin que nadie lo viera. Aquí solo se dice, y
                // guardar una combinación prohibida devuelve el mensaje del dominio.
                Toggle::make('guest_invitation')
                    ->label(__('admin.catalog.field_guest_invitation'))
                    ->helperText(__('admin.catalog.guest_invitation_hint'))
                    ->default(false),
            ]);
    }

    /**
     * Opciones del `Select` de tipo de campo de un esquema data-driven. Se DERIVAN de
     * `TicketType::FIELD_TYPES` en vez de escribirse: los dos repetidores (datos del evento y datos
     * por niño) tenían la lista copiada, así que un tipo nuevo salía en uno y no en el otro.
     *
     * ⚠️ **La EDAD solo se ofrece en los datos por niño.** Es un dato POR INVITADO del que sale un
     * cobro; en `event_fields` sería una sola edad para toda la fiesta, que no significa nada y
     * confundiría al operador con una opción que el veredicto nunca mira.
     *
     * @return array<string,string>
     */
    private static function fieldTypeOptions(bool $withAge = false): array
    {
        $options = [];
        foreach (TicketType::fieldTypesFor(perGuest: $withAge) as $type) {
            $options[$type] = __('admin.catalog.event_field_types.'.$type);
        }

        return $options;
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

                    // SI SE DEVUELVE LA SEÑAL al cancelar en plazo (`#775`): Mi cuenta lo DICE al pedir un cambio («…y te
                    // devolvemos la señal») solo con esto encendido. No devuelve nada: lo hace el personal. Solo con señal.
                    Toggle::make('deposit_refundable_in_time')
                        ->label(__('admin.catalog.field_deposit_refundable_in_time'))
                        ->visible(fn (Get $get): bool => ($get('deposit_type') ?? TicketType::DEPOSIT_NONE) !== TicketType::DEPOSIT_NONE)
                        ->helperText(__('admin.catalog.deposit_refundable_in_time_hint')),

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

                // ─── Familia y tramo de edad (`specs/cumple-mixto.md` §9) ────────────────────
                // Lo que conecta dos packs que son el MISMO servicio en dos regímenes (KIDS/JUMP).
                // Hasta el 2026-08-29 no había forma de decirlo: eran dos filas sin relación. Con
                // esto, el sistema sabe a qué producto le toca cada invitado por su edad y puede
                // proponer el suplemento de una fiesta MIXTA. Sin familia, apagado.
                Grid::make(['default' => 1, 'sm' => 3])->schema([
                    TextInput::make('guest_age_family')
                        ->label(__('admin.catalog.field_guest_age_family'))
                        ->maxLength(40)
                        // Misma forma que una clave técnica: se compara por igualdad exacta contra
                        // la de sus hermanos, así que no puede admitir acentos ni espacios.
                        ->rule('regex:/^[a-z0-9_\-]*$/')
                        ->helperText(__('admin.catalog.guest_age_family_hint')),

                    TextInput::make('guest_age_min')
                        ->label(__('admin.catalog.field_guest_age_min'))
                        ->numeric()
                        ->minValue(TicketType::GUEST_AGE_MIN)
                        ->maxValue(TicketType::GUEST_AGE_MAX)
                        ->helperText(__('admin.catalog.guest_age_min_hint')),

                    TextInput::make('guest_age_max')
                        ->label(__('admin.catalog.field_guest_age_max'))
                        ->numeric()
                        ->minValue(TicketType::GUEST_AGE_MIN)
                        ->maxValue(TicketType::GUEST_AGE_MAX)
                        // El tope no puede quedar por debajo del suelo. Se re-valida en servidor
                        // (`InteractsWithCatalogForm::normalizeGuestAgeFields`), que es donde
                        // además se comprueba que el tramo no pise al de un hermano.
                        ->gte('guest_age_min')
                        ->helperText(__('admin.catalog.guest_age_max_hint')),
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
                                ->default(TicketType::FIELD_TYPE_TEXT)
                                ->options(self::fieldTypeOptions()),

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
                                ->default(TicketType::FIELD_TYPE_TEXT)
                                // El único esquema que ofrece EDAD: es un dato POR NIÑO, y de él
                                // deriva el veredicto de fiesta mixta (`specs/cumple-mixto.md` §9).
                                ->options(self::fieldTypeOptions(withAge: true)),

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
     * `#324` — los TRAMOS de precio por cantidad (`docs/specs/precio-por-tramo.md`).
     *
     * «Desde N unidades, cada una cuesta X.» El tramo llega hasta que empieza el siguiente: **no hay
     * máximo**, y por eso no puede haber huecos ni solapes. El precio es UNIFORME
     * (`[DECIDIDO owner]`): 70 personas a 13 € son 910 €, no 30×15 + 40×13.
     *
     * ⚠️ **La sección se OCULTA en un producto con familia de edades**, porque el dominio lo prohíbe
     * (el sello congelaría un precio que el tramo movería después). El form no re-implementa la
     * regla: enseña el aviso y deja que el guardián del modelo sea la autoridad — el mismo reparto
     * que `#299` con los solapes de edad.
     */
    private static function priceTiersSection(): Section
    {
        return Section::make(__('admin.catalog.section_price_tiers'))
            ->description(__('admin.catalog.price_tiers_hint'))
            ->schema([
                Placeholder::make('price_tiers_blocked')
                    ->hiddenLabel()
                    ->content(__('admin.catalog.price_tiers_blocked'))
                    ->visible(fn (Get $get): bool => trim((string) $get('guest_age_family')) !== ''),

                Repeater::make('priceTiers')
                    ->relationship()
                    ->hiddenLabel()
                    ->visible(fn (Get $get): bool => trim((string) $get('guest_age_family')) === '')
                    ->addActionLabel(__('admin.catalog.price_tier_add'))
                    ->defaultItems(0)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            Select::make('rate_type_id')
                                ->label(__('admin.catalog.price_tier_rate'))
                                ->options(fn (): array => RateType::where('is_active', true)
                                    ->orderBy('priority')
                                    ->get()
                                    ->mapWithKeys(fn (RateType $r): array => [$r->id => (string) ($r->tr('label') ?? $r->key)])
                                    ->all())
                                ->required(),
                            TextInput::make('min_qty')
                                ->label(__('admin.catalog.price_tier_min_qty'))
                                ->helperText(__('admin.catalog.price_tier_min_qty_hint'))
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                            TextInput::make('amount_cents')
                                ->label(__('admin.catalog.price_tier_amount'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(99999.99)
                                ->step('0.01')
                                ->prefix('€')
                                ->required()
                                // La columna es CÉNTIMOS (única fuente de verdad, como `prices`);
                                // el operador teclea euros. La conversión vive aquí y no en el
                                // modelo para que la BD no tenga dos formatos según quién escriba.
                                ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state / 100, 2, '.', ''))
                                ->dehydrateStateUsing(fn (?string $state): int => (int) round(((float) $state) * 100)),
                        ]),
                    ]),
            ]);
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
