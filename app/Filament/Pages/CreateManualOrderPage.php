<?php

namespace App\Filament\Pages;

use App\Domain\Platform\Services\DisplayTime;
use App\Exceptions\ReservationException;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\TicketType;
use App\Models\User;
use App\Support\AddonResolver;
use App\Support\CustomerRegistrar;
use App\Support\ManualOrderFulfiller;
use App\Support\PackAvailability;
use App\Support\PaymentSettings;
use App\Support\RateResolver;
use App\Support\SlotAvailability;
use App\Support\SlotOffer;
use BackedEnum;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fase 7.3 — Pedido manual desde back-office (decisión #120, iteración 1: efectivo/datáfono).
 *
 * El operador identifica al cliente (o lo invita a registrarse) y recorre el MISMO flujo que la
 * compra pública (producto → fecha → franja → cantidad → complementos → carrito), cobrando al
 * momento en efectivo o datáfono. Reutiliza el backend validado:
 *  - `OrderCreator` (vía `ManualOrderFulfiller`) re-valida y bloquea aforo en servidor.
 *  - `RateResolver`/`SlotAvailability`/`PackAvailability` pueblan selectores y precios.
 *
 * Stepper PROPIO (no el Wizard de Filament): así la navegación (Anterior/Siguiente/Cobrar) vive
 * bajo el "Resumen del pedido" y "Siguiente" se bloquea sin cliente (paso 1) o con carrito vacío
 * (paso 2) — control que el Wizard nativo no da. Cada paso es un `Group` gateado por `$step`; el
 * estado de los pasos ocultos persiste en `$this->data` (array Livewire), por eso las acciones
 * leen de `$this->data` y no de `getState()` (que solo devolvería el paso visible).
 *
 * Aquí aterriza también el cross-zona/cross-tipo que 7.2e.3/4 remitían a 7.3.
 */
class CreateManualOrderPage extends Page
{
    public const STEP_CUSTOMER = 1;

    public const STEP_PRODUCTS = 2;

    public const STEP_PAYMENT = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?int $navigationSort = 15;   // Plan B · L1: entre Calendario (10) y Pedidos (20)

    protected static ?string $slug = 'crear-pedido';

    protected string $view = 'filament.pages.create-manual-order';

    /** Estado del formulario (cliente, selección de producto en curso, método de pago). */
    public ?array $data = [];

    /** Líneas confirmadas del pedido (ya en forma de cesta de `OrderCreator` + datos de display). */
    public array $cart = [];

    /**
     * Estado de la selección de COMPLEMENTOS de la línea en curso (misma lógica que la web vía
     * `AddonResolver`): cantidades de los de cantidad libre (addonId→qty) y elección de cada grupo
     * excluyente (grupo→addonId). Los incluidos/obligatorios/default del grupo se pre-cargan al
     * elegir el producto.
     *
     * @var array<int,int>
     */
    public array $selAddonQty = [];

    /** @var array<string,int> */
    public array $selAddonGroup = [];

    /** Paso actual del asistente (1 Cliente · 2 Productos · 3 Pago). */
    public int $step = self::STEP_CUSTOMER;

    /**
     * Alta de cliente SIN email a la espera de la decisión de duplicados (#263; decisión clienta:
     * «avisar y dejar elegir»). Cuando el teléfono ya existe, NO creamos: guardamos aquí los datos y
     * mostramos las coincidencias para que el operador reutilice o cree uno nuevo. `null` = sin alta
     * pendiente.
     *
     * @var array{name:string, phone:string}|null
     */
    public ?array $pendingNoEmailCustomer = null;

    /**
     * Coincidencias por teléfono [id => "Nombre · contacto"] que pinta el panel de elección.
     *
     * @var array<int, string>
     */
    public array $phoneMatchOptions = [];

    /** Memo por petición de los complementos del producto en curso (evita N consultas por render). */
    private ?Collection $addonsMemo = null;

    private ?int $addonsMemoFor = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('orders.create_manual') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Plan B · L1: «Crear pedido» es tarea de mostrador frecuente → AL SIDEBAR (grupo
        // Operativa). El botón del topbar se conserva como atajo redundante. `canAccess()`
        // (permiso `orders.create_manual`) sigue protegiendo ruta y visibilidad.
        return auth()->user()?->hasPermission('orders.create_manual') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.orders.create_manual.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.orders.nav_group');
    }

    public function getTitle(): string
    {
        return __('admin.orders.create_manual.title');
    }

    public function mount(): void
    {
        $this->form->fill(['payment_method' => ManualOrderFulfiller::METHOD_CASH]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->customerStep(),
                $this->productsStep(),
                $this->paymentStep(),
            ]);
    }

    // ─── Navegación del stepper ───────────────────────────────────────────

    /** ¿Puede avanzarse desde el paso actual? (gobierna el `:disabled` de "Siguiente"). */
    public function canAdvance(): bool
    {
        return match ($this->step) {
            self::STEP_CUSTOMER => filled($this->data['customer_id'] ?? null),
            self::STEP_PRODUCTS => $this->cart !== [],
            default => false,
        };
    }

    public function next(): void
    {
        if ($this->canAdvance()) {
            $this->step = min(self::STEP_PAYMENT, $this->step + 1);
        }
    }

    public function back(): void
    {
        $this->step = max(self::STEP_CUSTOMER, $this->step - 1);
    }

    /**
     * Selecciona un cliente en el flujo (tras un alta directa #181). Vacía el carrito,
     * igual que el `afterStateUpdated` del Select: nunca cobrar a B las líneas de A.
     */
    private function selectCustomer(int $id): void
    {
        $this->data['customer_id'] = $id;
        $this->cart = [];
        $this->clearPhoneMatch();
    }

    // ─── Paso 1: cliente ──────────────────────────────────────────────────

    private function customerStep(): Group
    {
        return Group::make()
            ->visible(fn (): bool => $this->step === self::STEP_CUSTOMER)
            ->schema([
                Section::make(__('admin.orders.create_manual.step_customer'))
                    ->schema([
                        Select::make('customer_id')
                            ->label(__('admin.orders.create_manual.customer'))
                            ->placeholder(__('admin.orders.create_manual.customer_search_placeholder'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->customerSearchResults($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->customerLabel((int) $value))
                            ->helperText(__('admin.orders.create_manual.customer_help'))
                            ->live()
                            // Cambiar de cliente vacía el carrito (nunca cobrar a B las líneas de A) y
                            // descarta cualquier aviso de duplicado pendiente.
                            ->afterStateUpdated(function (): void {
                                $this->cart = [];
                                $this->clearPhoneMatch();
                            })
                            ->required(),

                        SchemaActions::make([
                            Action::make('registerCustomer')
                                ->label(__('admin.orders.create_manual.register_cta'))
                                ->icon(Heroicon::OutlinedUserPlus)
                                ->color('gray')
                                ->modalHeading(__('admin.orders.create_manual.register_heading'))
                                ->modalDescription(__('admin.orders.create_manual.register_description'))
                                ->modalSubmitActionLabel(__('admin.orders.create_manual.register_submit'))
                                ->schema([
                                    TextInput::make('name')
                                        ->label(__('admin.orders.create_manual.register_name'))
                                        ->required()
                                        ->maxLength(255),
                                    // Email OPCIONAL (#263): la clienta da de alta reservas de su
                                    // agenda de las que solo tiene el teléfono. Sin email, la cuenta
                                    // vive solo en el panel (no recibe correos ni inicia sesión) y el
                                    // enlace del post-form se entrega con el icono «enlace» del producto.
                                    TextInput::make('email')
                                        ->label(__('admin.orders.create_manual.register_email'))
                                        ->helperText(__('admin.orders.create_manual.register_email_optional'))
                                        ->email()
                                        ->maxLength(255),
                                    // Teléfono OBLIGATORIO: es el identificador del cliente cuando no
                                    // hay email (y la clave de deduplicación / rate-limit).
                                    TextInput::make('phone')
                                        ->label(__('admin.orders.create_manual.register_phone'))
                                        ->tel()
                                        ->required()
                                        ->maxLength(30),
                                    Checkbox::make('privacy_informed')
                                        ->label(__('admin.orders.create_manual.register_privacy'))
                                        ->accepted()
                                        ->required(),
                                ])
                                ->action(fn (array $data) => $this->registerCustomerFromData($data)),
                        ]),
                    ]),
            ]);
    }

    /** Memo por petición del mapa de horas ofrecibles (`SlotOffer`); clave "producto|fecha". */
    private ?array $timeMapCache = null;

    private ?string $timeMapKey = null;

    /** Memo por petición de las fechas ofrecibles del producto (las consumen minDate/maxDate/disabledDates). */
    private ?array $offerableDatesCache = null;

    private ?int $offerableDatesFor = null;

    // ─── Paso 2: productos ────────────────────────────────────────────────

    private function productsStep(): Group
    {
        return Group::make()
            ->visible(fn (): bool => $this->step === self::STEP_PRODUCTS)
            ->schema([
                Section::make(__('admin.orders.create_manual.add_product'))
                    ->schema([
                        Select::make('sel_product_id')
                            ->label(__('admin.orders.create_manual.product'))
                            ->options(fn (): array => $this->productOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Get $get, callable $set): void {
                                $set('sel_time', null);
                                $set('sel_qty', $this->defaultQtyFor((int) $get('sel_product_id')));
                                $set('event_data', []);
                                // Pre-carga los complementos incluidos/obligatorios y el default de
                                // cada grupo del producto elegido (igual que la web).
                                $this->initManualAddonDefaults();
                            }),

                        DatePicker::make('sel_date')
                            ->label(__('admin.orders.create_manual.date'))
                            ->native(false)
                            ->closeOnDateSelection()           // cierra el popover al elegir día
                            // Fuente ÚNICA compartida con la web (SlotOffer): solo se habilitan los
                            // días con franja ofrecible del producto, y el máximo es la última franja
                            // REAL (no hoy+horizonte aritmético, que ofrecía días sin franjas). "Hoy"
                            // en la zona operativa del parque (auditoría Fase 1).
                            // Empieza en la PRIMERA fecha ofrecible → la ventana de antelación y los
                            // días sin franja del principio quedan bloqueados (gris), no clicables-sin-horas.
                            ->minDate(fn (): Carbon => $this->minOfferableDate() ?? DisplayTime::today())
                            ->maxDate(fn (): Carbon => $this->maxOfferableDate()
                                ?? DisplayTime::today()->addMonths(PaymentSettings::purchaseHorizonMonths()))
                            ->disabledDates(fn (): array => $this->disabledOfferDates())
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('sel_time', null)),

                        Select::make('sel_time')
                            ->label(__('admin.orders.create_manual.time'))
                            ->options(fn (): array => $this->timeOptions())
                            // Entradas llenas: se muestran DESHABILITADAS (no se ocultan), igual que la web.
                            ->disableOptionWhen(fn (string $value): bool => ! ($this->timeMap()[$value]['sellable'] ?? false))
                            ->helperText(fn (): string => $this->timeFieldHelp())
                            ->live(),

                        TextInput::make('sel_qty')
                            ->label(fn (): string => $this->isPackSelected()
                                ? __('admin.orders.create_manual.guests')
                                : __('admin.orders.create_manual.quantity'))
                            ->numeric()
                            // Min/máx aplicados en el campo (no solo al validar): packs respetan
                            // su rango [min_qty, max_qty]; entradas mínimo 1, sin tope.
                            ->minValue(fn (): int => $this->selectedMinQty())
                            ->maxValue(fn (): ?int => $this->selectedMaxQty())
                            ->default(1)
                            // Reactivo: al cambiar el nº de invitados, el widget de complementos
                            // recalcula los `per_guest` (uno por invitado) y su importe.
                            ->live(onBlur: true),

                        // Datos del evento del pack (reactivo: solo hay UNA selección en curso).
                        Group::make()
                            ->schema(fn (): array => $this->selectionEventDataFields())
                            ->visible(fn (): bool => $this->selectionEventDataFields() !== []),

                        // Complementos del producto seleccionado: MISMA lógica/condiciones que la web
                        // (incluido/obligatorio/por-invitado/grupo de elección) vía el view-model
                        // compartido `AddonResolver::viewModel`. La data se pasa por `viewData` (el
                        // `$this` del partial sería el View, no la página — lección #161).
                        ViewComponent::make('filament.pages.partials.manual-order-addons')
                            ->viewData(fn (): array => ['model' => $this->manualAddonViewModel()])
                            ->visible(fn (): bool => $this->selectedProductAddons()->isNotEmpty()),

                        // "Añadir al carrito" alineado a la derecha.
                        SchemaActions::make([
                            Action::make('addLine')
                                ->label(__('admin.orders.create_manual.add_to_cart'))
                                ->icon(Heroicon::OutlinedPlus)
                                ->action('addLineToCart'),
                        ])->alignment(Alignment::End),
                    ]),
            ]);
    }

    // ─── Paso 3: pago ─────────────────────────────────────────────────────

    private function paymentStep(): Group
    {
        return Group::make()
            ->visible(fn (): bool => $this->step === self::STEP_PAYMENT)
            ->schema([
                Section::make(__('admin.orders.create_manual.step_payment'))
                    ->schema([
                        Radio::make('payment_method')
                            ->label(__('admin.orders.create_manual.payment_method'))
                            ->options([
                                ManualOrderFulfiller::METHOD_CASH => __('admin.orders.create_manual.method_cash'),
                                ManualOrderFulfiller::METHOD_DATAFONO => __('admin.orders.create_manual.method_datafono'),
                            ])
                            ->default(ManualOrderFulfiller::METHOD_CASH)
                            ->required(),
                    ]),
            ]);
    }

    // ─── Acciones ─────────────────────────────────────────────────────────

    /** Añade la selección en curso al carrito (validación real de aforo = al cobrar). */
    public function addLineToCart(): void
    {
        $type = $this->selectedProduct();
        $date = $this->data['sel_date'] ?? null;
        $time = $this->data['sel_time'] ?? null;
        $qty = (int) ($this->data['sel_qty'] ?? 0);

        if (! $type || ! $date || ! $time || $qty < 1) {
            Notification::make()->warning()->title(__('admin.orders.create_manual.line_incomplete'))->send();

            return;
        }

        // Rango de cantidad (packs): bloquea fuera de [min, max] ANTES de añadir.
        $min = $this->selectedMinQty();
        $max = $this->selectedMaxQty();
        if ($qty < $min || ($max !== null && $qty > $max)) {
            Notification::make()->warning()
                ->title(__('admin.orders.create_manual.qty_out_of_range', ['min' => $min, 'max' => $max ?? '∞']))
                ->send();

            return;
        }

        // Selección de complementos resuelta con la MISMA autoridad que la web (incluido /
        // obligatorio / por-invitado / grupo). El total y el desglose por complemento se derivan
        // de `AddonResolver` → coinciden con lo que `OrderCreator` cobrará al confirmar.
        $sel = $this->effectiveAddonSelection();
        $addons = AddonResolver::buildSelection($this->selectedProductAddons(), $sel['qty'], $sel['groups'], $qty);

        $eventData = is_array($this->data['event_data'] ?? null) ? $this->data['event_data'] : [];

        // #225 (DISPLAY): señal de la línea, para AVISAR en el carrito de que un producto con señal
        // cobra ahora solo la señal del principal y el resto (+ complementos) se cobra en el parque.
        // Mismo cálculo data-driven que la landing (`depositCents` sobre el subtotal del principal).
        // NO afecta al cobro real, que lo calcula `ManualOrderFulfiller` vía `Order::onlineDueCents()`
        // de forma independiente — esto es puramente informativo para el empleado.
        $principalSubtotal = ((int) (app(RateResolver::class)->priceCents($type, Carbon::parse($date)) ?? 0)) * $qty;
        $lineDepositCents = $type->depositCents($principalSubtotal);
        $hasLineDeposit = $lineDepositCents < $principalSubtotal;

        $this->cart[] = [
            'ticket_type_id' => $type->id,
            'date' => (string) Carbon::parse($date)->toDateString(),
            'time' => (string) $time,
            'qty' => $qty,
            'event_data' => $eventData,
            'addons' => $addons,
            'addon_display' => $this->resolvedAddonDisplay($type, $qty, $addons),
            'label' => $this->productLabel($type),
            'when' => Carbon::parse($date)->format('d/m/Y').' '.substr((string) $time, 0, 5),
            'line_total_cents' => $this->estimateLineCents($type, $date, $qty, $addons),
            // null = sin señal (se cobra el total). Si hay señal, son los céntimos que se cobran AHORA.
            'deposit_cents' => $hasLineDeposit ? $lineDepositCents : null,
        ];

        // Resetea la selección para la siguiente línea (preserva cliente, método y paso).
        $this->data['sel_product_id'] = null;
        $this->data['sel_date'] = null;
        $this->data['sel_time'] = null;
        $this->data['sel_qty'] = null;
        $this->data['event_data'] = [];
        $this->selAddonQty = [];
        $this->selAddonGroup = [];
        $this->addonsMemo = null;
        $this->addonsMemoFor = null;

        Notification::make()->success()->title(__('admin.orders.create_manual.line_added'))->send();
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    // ─── Complementos (misma lógica que la web vía AddonResolver) ──────────────

    /**
     * Complementos del producto en curso (con pivote + precios). Memoizado por petición.
     *
     * @return Collection<int, TicketType>
     */
    public function selectedProductAddons(): Collection
    {
        $type = $this->selectedProduct();
        if (! $type || $type->isAddon()) {
            return collect();
        }
        if ($this->addonsMemoFor !== (int) $type->id || $this->addonsMemo === null) {
            $this->addonsMemo = $type->addons()->with('prices.rateType')->get();
            $this->addonsMemoFor = (int) $type->id;
        }

        return $this->addonsMemo;
    }

    /** Pre-carga incluidos/obligatorios + default de cada grupo al elegir el producto. */
    private function initManualAddonDefaults(): void
    {
        $this->addonsMemo = null;
        $this->addonsMemoFor = null;
        $sel = AddonResolver::defaultSelection($this->selectedProductAddons());
        $this->selAddonQty = $sel['qty'];
        $this->selAddonGroup = $sel['groups'];
    }

    /**
     * Selección EFECTIVA = estado del operador + defaults del producto (lo explícito gana). Aplicar
     * los defaults en lectura hace la UI robusta aunque `afterStateUpdated` no haya corrido (el
     * default de cada grupo se ve seleccionado desde el principio).
     *
     * @return array{qty: array<int,int>, groups: array<string,int>}
     */
    private function effectiveAddonSelection(): array
    {
        $defaults = AddonResolver::defaultSelection($this->selectedProductAddons());

        return [
            'qty' => $this->selAddonQty + $defaults['qty'],
            'groups' => $this->selAddonGroup + $defaults['groups'],
        ];
    }

    /** Modelo de vista de los complementos (grupos + sueltos + total) para el partial. */
    public function manualAddonViewModel(): array
    {
        $sel = $this->effectiveAddonSelection();

        return app(AddonResolver::class)->viewModel(
            $this->selectedProductAddons(),
            $sel['qty'],
            $sel['groups'],
            max(0, (int) ($this->data['sel_qty'] ?? 0)),
            $this->selectedProduct()?->isPack() ?? false,
            Carbon::today(),
        );
    }

    public function incManualAddon(int $addonId): void
    {
        $addon = $this->selectedProductAddons()->firstWhere('id', $addonId);
        if (! $addon) {
            return;
        }
        $pivot = $addon->pivot;
        if ($pivot->isPerGuest() || $pivot->choiceGroup() !== null) {
            return;
        }
        if ($pivot->is_included && ! $pivot->allow_extra) {
            return;
        }
        // P9: respeta el máximo por complemento (data-driven); el 20 sigue como cap duro global.
        $cap = $pivot->max_qty !== null ? min(20, (int) $pivot->max_qty) : 20;
        $this->selAddonQty[$addonId] = min(($this->selAddonQty[$addonId] ?? 0) + 1, $cap);
    }

    /**
     * P10 — Activa/desactiva un complemento PER-INVITADO opcional (cantidad automática = nº de
     * invitados): checkbox sí/no, no contador. Paridad con la compra pública (`Purchase::toggleAddon`):
     * sin esto, un per-invitado opcional no tenía control en el alta manual y era inseleccionable.
     */
    public function toggleManualAddon(int $addonId): void
    {
        $addon = $this->selectedProductAddons()->firstWhere('id', $addonId);
        if (! $addon) {
            return;
        }
        $pivot = $addon->pivot;
        if (! $pivot->isPerGuest() || $pivot->choiceGroup() !== null || $pivot->is_mandatory || $pivot->is_included) {
            return;
        }
        $this->selAddonQty[$addonId] = ((int) ($this->selAddonQty[$addonId] ?? 0)) > 0 ? 0 : 1;
    }

    public function decManualAddon(int $addonId): void
    {
        $addon = $this->selectedProductAddons()->firstWhere('id', $addonId);
        if (! $addon) {
            return;
        }
        $pivot = $addon->pivot;
        if ($pivot->isPerGuest() || $pivot->choiceGroup() !== null) {
            return;
        }
        $floor = $pivot->is_mandatory ? max(1, (int) $pivot->included_quantity) : 0;
        $this->selAddonQty[$addonId] = max(($this->selAddonQty[$addonId] ?? 0) - 1, $floor);
    }

    public function selectManualAddonOption(string $group, int $addonId): void
    {
        $member = $this->selectedProductAddons()->first(
            fn (TicketType $a) => (int) $a->id === $addonId && $a->pivot->choiceGroup() === $group
        );
        if ($member !== null) {
            $this->selAddonGroup[$group] = $addonId;
        }
    }

    /**
     * Desglose por complemento para el carrito (nombre · cantidad · unidades gratis · subtotal),
     * resuelto con `AddonResolver` → coincide con el cobro.
     *
     * @param  array<int, array{ticket_type_id:int, qty:int}>  $addons
     * @return array<int, array{name:string, qty:int, free_qty:int, subtotal:int}>
     */
    private function resolvedAddonDisplay(TicketType $type, int $qty, array $addons): array
    {
        if ($addons === []) {
            return [];
        }
        $type->loadMissing('addons');
        try {
            $resolved = app(AddonResolver::class)->resolve($type, $qty, $addons, Carbon::today());
        } catch (\Throwable) {
            return [];
        }
        $names = $this->selectedProductAddons()->keyBy('id');

        return array_map(fn (array $r) => [
            'name' => (string) ($names->get($r['ticket_type_id'])?->tr('name') ?? TicketType::find($r['ticket_type_id'])?->tr('name') ?? '—'),
            'qty' => (int) $r['quantity'],
            'free_qty' => (int) $r['free_quantity'],
            'subtotal' => max(0, (int) $r['quantity'] - (int) $r['free_quantity']) * (int) $r['unit_price'],
        ], $resolved['rows']);
    }

    /**
     * Alta directa del cliente (#181): crea la cuenta y la SELECCIONA en el flujo. Cuerpo de la acción
     * `registerCustomer`; público para testearlo directo (igual que `addLineToCart`/`create`).
     *
     *  - **Con email:** cuenta verificada + contraseña aleatoria por correo. Si el email ya tiene
     *    cuenta, la selecciona sin crear ni enviar nada.
     *  - **Sin email** (#263, cliente de agenda, solo teléfono): el teléfono es OBLIGATORIO (identidad).
     *    Si el teléfono ya existe, NO crea: avisa y deja al operador elegir reutilizar o crear nuevo
     *    (decisión clienta). Si no hay coincidencia, crea una cuenta sin email (vive solo en el panel).
     *
     * @param  array{name?:string, email?:?string, phone?:?string, privacy_informed?:bool}  $data
     */
    public function registerCustomerFromData(array $data): void
    {
        abort_unless(auth()->user()?->hasPermission('orders.create_manual'), 403);

        // Defensa en profundidad: el checkbox de privacidad es obligatorio también en servidor.
        if (empty($data['privacy_informed'])) {
            Notification::make()->warning()
                ->title(__('admin.orders.create_manual.register_privacy_required'))->send();

            return;
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = CustomerRegistrar::normalizeEmail($data['email'] ?? null);
        $phone = trim((string) ($data['phone'] ?? ''));

        // El teléfono es obligatorio (identidad del cliente cuando no hay email + clave de
        // deduplicación/rate-limit). El modal ya lo exige; defensa en profundidad en servidor.
        if ($phone === '') {
            Notification::make()->warning()
                ->title(__('admin.orders.create_manual.register_phone_required'))->send();

            return;
        }

        // Sin email → aviso de duplicados por teléfono (avisar y dejar elegir). Mostrar el aviso NO
        // consume el rate-limit (se consume al CREAR, en performRegistration).
        if ($email === null) {
            $matches = app(CustomerRegistrar::class)->customersMatchingPhone($phone);
            if ($matches->isNotEmpty()) {
                $this->pendingNoEmailCustomer = ['name' => $name, 'phone' => $phone];
                $this->phoneMatchOptions = $matches
                    ->mapWithKeys(fn (User $u): array => [$u->id => $this->customerDisplay($u)])
                    ->all();

                Notification::make()->warning()
                    ->title(__('admin.orders.create_manual.phone_match_title'))
                    ->body(__('admin.orders.create_manual.phone_match_help', ['phone' => $phone]))
                    ->persistent()
                    ->send();

                return;
            }
        }

        $this->performRegistration($name, $email, $phone);
    }

    /**
     * Crea la cuenta (con o sin email) vía `CustomerRegistrar`, la selecciona y notifica. Compartido
     * por el alta normal y por «crear nuevo de todos modos» tras el aviso de duplicado. El rate-limit
     * se aplica AQUÍ (en el alta real), con clave por email o, sin email, por teléfono normalizado
     * (nunca `md5('')`, que metería todas las altas sin email en el mismo cubo).
     */
    private function performRegistration(string $name, ?string $email, string $phone): void
    {
        $rateLimitId = $email ?? 'phone:'.(CustomerRegistrar::normalizePhone($phone) ?? $phone);
        $key = 'manual-register:'.md5($rateLimitId);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            Notification::make()->warning()
                ->title(__('admin.orders.create_manual.register_throttled'))->send();

            return;
        }
        RateLimiter::hit($key, 3600);

        try {
            $result = app(CustomerRegistrar::class)->register($name, $email, $phone);
        } catch (\Throwable $e) {
            Log::warning('manual_order.register_failed', ['error' => $e->getMessage()]);
            Notification::make()->danger()
                ->title(__('admin.orders.create_manual.register_failed'))->send();

            return;
        }

        // Selecciona la cuenta en el flujo (creada o ya existente) para continuar sin volver a buscarla.
        $this->selectCustomer($result['user']->id);

        if (! $result['created']) {
            Notification::make()->warning()
                ->title(__('admin.orders.create_manual.register_exists'))->send();

            return;
        }

        // Cliente sin email: aviso específico (no recibe correos; el enlace del post-form se copia
        // desde el icono del producto). Con email: confirmación + posible aviso de fallo de envío.
        if ($email === null) {
            Notification::make()->success()
                ->title(__('admin.orders.create_manual.register_no_email_done', ['name' => $name]))->send();

            return;
        }

        Notification::make()->success()
            ->title(__('admin.orders.create_manual.register_done', ['email' => $email]))->send();

        // La cuenta se creó, pero el email de bienvenida (con la contraseña) no pudo enviarse
        // (p. ej. el proveedor de correo aún no está activo). El operador debe avisar al cliente
        // o resetear la contraseña más tarde.
        if (! ($result['email_sent'] ?? true)) {
            Notification::make()->warning()->persistent()
                ->title(__('admin.orders.create_manual.register_email_failed'))->send();
        }
    }

    /**
     * Tras el aviso de duplicado por teléfono: REUTILIZA un cliente existente. Solo acepta ids que
     * estaban entre las coincidencias mostradas (defensa anti-manipulación), igual que `create`
     * revalida el rol del cliente.
     */
    public function useExistingCustomer(int $id): void
    {
        abort_unless(auth()->user()?->hasPermission('orders.create_manual'), 403);

        if (! array_key_exists($id, $this->phoneMatchOptions)) {
            return;
        }

        $this->selectCustomer($id);
        Notification::make()->success()
            ->title(__('admin.orders.create_manual.phone_match_used'))->send();
    }

    /** Tras el aviso de duplicado por teléfono: CREA un cliente nuevo de todos modos (sin email). */
    public function createNewCustomerAnyway(): void
    {
        abort_unless(auth()->user()?->hasPermission('orders.create_manual'), 403);

        $pending = $this->pendingNoEmailCustomer;
        if ($pending === null) {
            return;
        }

        // NO limpiamos el aviso aquí (#264-audit): si `performRegistration` aborta (p. ej. rate-limit),
        // el panel de elección debe seguir visible para no perder el alta tecleada. En el camino feliz
        // lo limpia `selectCustomer()` → `clearPhoneMatch()` al crear/seleccionar.
        $this->performRegistration((string) $pending['name'], null, (string) $pending['phone']);
    }

    /** Descarta el aviso de duplicado sin actuar. */
    public function dismissPhoneMatch(): void
    {
        $this->clearPhoneMatch();
    }

    private function clearPhoneMatch(): void
    {
        $this->pendingNoEmailCustomer = null;
        $this->phoneMatchOptions = [];
    }

    /** Etiqueta de display de un cliente: nombre · (email o, si no hay, teléfono / «sin email»). */
    private function customerDisplay(User $u): string
    {
        $contact = filled($u->email)
            ? (string) $u->email
            : ((string) ($u->phone ?? '') !== '' ? (string) $u->phone : __('admin.orders.create_manual.customer_no_email'));

        return "{$u->name} · {$contact}";
    }

    /**
     * Botón final del asistente, «Cobrar y crear pedido». Action de Filament con
     * `->requiresConfirmation()` → MODAL nativo del panel (antes era un `wire:confirm`, el
     * diálogo de confirmación del NAVEGADOR, ajeno al diseño del panel). La lógica vive en
     * {@see create()} (que los tests invocan directo con `->call('create')`); aquí solo la
     * envolvemos con la confirmación + el deshabilitado por carrito vacío.
     */
    public function createOrderAction(): Action
    {
        return Action::make('createOrder')
            ->label(__('admin.orders.create_manual.submit'))
            ->color('success')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->requiresConfirmation()
            ->modalHeading(__('admin.orders.create_manual.confirm'))
            ->modalDescription(__('admin.orders.create_manual.confirm_description'))
            ->modalSubmitActionLabel(__('admin.orders.create_manual.submit'))
            ->modalIcon(Heroicon::OutlinedCheckCircle)
            ->disabled(fn (): bool => $this->cart === [])
            ->action(fn () => $this->create());
    }

    /** Cobra y crea el pedido. Defensa: permiso + cliente + carrito + aforo (OrderCreator). */
    public function create(): void
    {
        abort_unless(auth()->user()?->hasPermission('orders.create_manual'), 403);

        // Lee del estado RAW (no getState): el paso del cliente puede estar oculto al cobrar.
        $customer = User::find($this->data['customer_id'] ?? null);
        // Defense in depth: el Select filtra a rol `customer` en la UI, pero el valor enviado
        // podría manipularse — revalidamos el rol en servidor (no crear pedidos a staff/admin).
        if (! $customer || ! $customer->hasRole('customer')) {
            Notification::make()->danger()->title(__('admin.orders.create_manual.no_customer'))->send();

            return;
        }

        if ($this->cart === []) {
            Notification::make()->danger()->title(__('admin.orders.create_manual.cart_empty'))->send();

            return;
        }

        $method = (string) ($this->data['payment_method'] ?? '');

        try {
            $order = app(ManualOrderFulfiller::class)->fulfill($customer, $this->cartToOrderCart(), $method);
        } catch (ReservationException $e) {
            Notification::make()
                ->danger()
                ->title(__('admin.orders.create_manual.reservation_failed'))
                ->body(__($e->getMessage(), $e->context))
                ->send();

            return;
        } catch (\InvalidArgumentException) {
            Notification::make()->danger()->title(__('admin.orders.create_manual.invalid_method'))->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('admin.orders.create_manual.created', ['code' => $order->code]))
            ->send();

        $this->redirect(OrderResource::getUrl('view', ['record' => $order]));
    }

    // ─── Helpers de datos ─────────────────────────────────────────────────

    /** @return array<int,string> */
    private function customerSearchResults(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'customer'))
            ->where(fn ($q) => $q
                ->where('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(20)
            ->get()
            // RGPD minimización: con email mostramos nombre + email (no el teléfono, redundante porque
            // el operador suele buscar POR teléfono). SIN email, mostramos el teléfono como único
            // discriminante posible (cliente de agenda). Ver customerDisplay.
            ->mapWithKeys(fn (User $u) => [$u->id => $this->customerDisplay($u)])
            ->all();
    }

    private function customerLabel(int $id): ?string
    {
        $u = User::find($id);

        return $u ? $this->customerDisplay($u) : null;
    }

    /** @return array<int,string> */
    private function productOptions(): array
    {
        return TicketType::sellable()
            ->inOperationalZone() // una zona desactivada no vende (igual que la web)
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK])
            ->with('zone')
            ->orderBy('position')
            ->get()
            ->mapWithKeys(fn (TicketType $t) => [$t->id => $this->productLabel($t)])
            ->all();
    }

    private function productLabel(TicketType $type): string
    {
        $zone = $type->zone?->tr('name');

        return ($zone ? "{$zone} · " : '').(string) $type->tr('name');
    }

    /** Producto en curso, leído del estado del formulario (scope-independiente). */
    private function selectedProduct(): ?TicketType
    {
        $id = (int) ($this->data['sel_product_id'] ?? 0);

        return $id > 0 ? TicketType::find($id) : null;
    }

    private function isPackSelected(): bool
    {
        return $this->selectedProduct()?->isPack() ?? false;
    }

    private function defaultQtyFor(int $productId): int
    {
        $type = TicketType::find($productId);

        return $type && $type->isPack() ? (int) ($type->min_qty ?? 1) : 1;
    }

    private function selectedMinQty(): int
    {
        $type = $this->selectedProduct();

        return $type && $type->isPack() ? (int) ($type->min_qty ?? 1) : 1;
    }

    private function selectedMaxQty(): ?int
    {
        $type = $this->selectedProduct();

        return $type && $type->isPack() ? $type->max_qty : null;
    }

    /**
     * Franjas ofrecibles para el producto+fecha en curso, vía la fuente ÚNICA `SlotOffer` (idéntica
     * a la web): aplica la ventana viva del día y el cupo de pack ≥ min_qty. Las entradas llenas se
     * muestran DESHABILITADAS (sellable=false). @return array<string,string>
     */
    private function timeOptions(): array
    {
        $options = [];
        foreach ($this->timeMap() as $start => $info) {
            $options[$start] = substr((string) $start, 0, 5)
                .' · '.__('admin.orders.create_manual.seats', ['n' => $info['available']]);
        }

        return $options;
    }

    /**
     * Mapa franja → {available, sellable} de `SlotOffer`, memoizado por petición (lo consumen las
     * opciones, el `disableOptionWhen` y el helper del selector de hora).
     *
     * @return array<string, array{available:int, sellable:bool}>
     */
    private function timeMap(): array
    {
        $key = (string) ($this->data['sel_product_id'] ?? '').'|'.(string) ($this->data['sel_date'] ?? '');
        if ($this->timeMapKey === $key && $this->timeMapCache !== null) {
            return $this->timeMapCache;
        }

        $type = $this->selectedProduct();
        $date = $this->data['sel_date'] ?? null;
        $map = (! $type || ! $type->zone_id || ! $date)
            ? []
            : app(SlotOffer::class)->offerableTimes($type, Carbon::parse($date)->toDateString());

        $this->timeMapKey = $key;
        $this->timeMapCache = $map;

        return $map;
    }

    /** Ayuda del selector de hora: avisa si la fecha elegida no tiene franjas ofrecibles. */
    private function timeFieldHelp(): string
    {
        if (! empty($this->data['sel_date']) && $this->selectedProduct() && $this->timeMap() === []) {
            return __('admin.orders.create_manual.no_times_for_date');
        }

        return __('admin.orders.create_manual.time_help');
    }

    /**
     * Días SIN franja ofrecible dentro del rango con franjas, para deshabilitarlos en el calendario
     * (fuente `SlotOffer`, misma que la web). @return array<int,string>
     */
    /** Fechas (Y-m-d) con franja ofrecible del producto en curso, vía `SlotOffer`. Memoizado por petición. */
    private function offerableDates(): array
    {
        $type = $this->selectedProduct();
        $id = $type?->id ?? 0;
        if ($this->offerableDatesFor === $id && $this->offerableDatesCache !== null) {
            return $this->offerableDatesCache;
        }

        $this->offerableDatesFor = $id;

        return $this->offerableDatesCache = $type ? app(SlotOffer::class)->offerableDates($type) : [];
    }

    /**
     * Primera fecha ofrecible (tope INFERIOR real del calendario): así la ventana de antelación
     * mínima / días sin franja del principio quedan BLOQUEADOS en el calendario, no clicables-sin-horas.
     */
    private function minOfferableDate(): ?Carbon
    {
        $dates = $this->offerableDates();

        return $dates === [] ? null : Carbon::parse($dates[0]);
    }

    /** Última fecha ofrecible (tope SUPERIOR real del calendario). */
    private function maxOfferableDate(): ?Carbon
    {
        $dates = $this->offerableDates();

        return $dates === [] ? null : Carbon::parse(end($dates));
    }

    /** Días SIN franja ofrecible ENTRE la primera y la última (huecos: cerrados, etc.), para deshabilitarlos. */
    private function disabledOfferDates(): array
    {
        $offerable = $this->offerableDates();
        if ($offerable === []) {
            return [];
        }

        $set = array_flip($offerable);
        $disabled = [];
        foreach (CarbonPeriod::create(Carbon::parse($offerable[0]), Carbon::parse(end($offerable))) as $day) {
            $ymd = $day->toDateString();
            if (! isset($set[$ymd])) {
                $disabled[] = $ymd;
            }
        }

        return $disabled;
    }

    /** Campos de event_data del pack seleccionado (text/number/textarea). @return array<int,mixed> */
    private function selectionEventDataFields(): array
    {
        $type = $this->selectedProduct();
        if (! $type || ! $type->isPack()) {
            return [];
        }

        $fields = [];
        // Solo los campos de la fase de RESERVA (#217): los `postform` (nº adultos, observaciones…)
        // los rellena el cliente en el post-form, no el operador aquí (coherente con la compra y con
        // lo que `OrderCreator` persiste —filtra a `booking`—, evitando capturar datos que se descartan).
        foreach ($type->eventFields(TicketType::EVENT_STAGE_BOOKING) as $field) {
            $key = $field['key'];
            $required = (bool) ($field['required'] ?? false);
            $label = $type->eventFieldLabel($field);
            $name = "event_data.{$key}";

            $fields[] = ($field['type'] ?? 'text') === 'textarea'
                ? Textarea::make($name)->label($label)->required($required)->rows(2)
                : TextInput::make($name)->label($label)->required($required)
                    ->inputMode(($field['type'] ?? 'text') === 'number' ? 'numeric' : 'text');
        }

        return $fields;
    }

    private function estimateLineCents(TicketType $type, string $date, int $qty, array $addons): int
    {
        $rates = app(RateResolver::class);
        $cents = ((int) ($rates->priceCents($type, Carbon::parse($date)) ?? 0)) * $qty;

        // Complementos: MISMO AddonResolver que crea el pedido (incluido / por-invitado / grupo
        // excluyente + auto-inyección de obligatorios) → el total estimado coincide con lo que se
        // cobra. Si la selección es inconsistente, suma 0 y el alta dará el error apropiado.
        if (! $type->isAddon()) {
            $type->loadMissing('addons');
            try {
                $cents += app(AddonResolver::class)->resolve($type, $qty, $addons, Carbon::today())['subtotal'];
            } catch (\Throwable) {
                // Selección inválida en la previsualización: no rompemos el formulario.
            }
        }

        return $cents;
    }

    /** @return array<int,array{ticket_type_id:int,date:string,time:string,qty:int,event_data:array,addons:array}> */
    private function cartToOrderCart(): array
    {
        return array_map(fn (array $line) => [
            'ticket_type_id' => $line['ticket_type_id'],
            'date' => $line['date'],
            'time' => $line['time'],
            'qty' => $line['qty'],
            'event_data' => $line['event_data'] ?? [],
            'addons' => $line['addons'] ?? [],
        ], $this->cart);
    }

    public function cartTotalCents(): int
    {
        return array_sum(array_column($this->cart, 'line_total_cents'));
    }

    /**
     * #225 (DISPLAY): importe que se cobra AHORA = señal en las líneas con señal, total en el resto.
     * Solo informativo para el aviso del carrito; el cobro real lo calcula `ManualOrderFulfiller`.
     */
    public function cartOnlineDueCents(): int
    {
        return array_sum(array_map(
            fn (array $line): int => $line['deposit_cents'] ?? $line['line_total_cents'],
            $this->cart,
        ));
    }

    /** #225 (DISPLAY): ¿alguna línea cobra solo señal? → el carrito muestra el split señal/parque. */
    public function cartHasDeposit(): bool
    {
        foreach ($this->cart as $line) {
            if (($line['deposit_cents'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /** Etiquetas de los pasos para el indicador del asistente. @return array<int,string> */
    public function stepLabels(): array
    {
        return [
            self::STEP_CUSTOMER => __('admin.orders.create_manual.step_customer'),
            self::STEP_PRODUCTS => __('admin.orders.create_manual.step_products'),
            self::STEP_PAYMENT => __('admin.orders.create_manual.step_payment'),
        ];
    }
}
