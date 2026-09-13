<?php

namespace App\Filament\Pages;

use App\Domain\Booking\Contracts\CounterSale;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\AddonResolver;
use App\Domain\Booking\Services\CartOccupants;
use App\Domain\Booking\Services\ManualOrderFulfiller;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\RateResolver;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Booking\Services\SlotOffer;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CheckoutDuties;
use App\Domain\Identity\Services\CustomerRegistrar;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Orders\OrderResource;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;

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

    public const STEP_PRODUCT = 2;

    /** Cantidad + día + hora: las tres preguntas de «cuándo viene» (`#462`, D1 del owner). */
    public const STEP_WHEN = 3;

    /** Campos del evento · menores a cargo · justificante. CONDICIONAL: se salta si no hay nada. */
    public const STEP_DETAILS = 4;

    /** Complementos. CONDICIONAL. */
    public const STEP_EXTRAS = 5;

    /** El carrito, a pantalla completa y en UNA columna (`[DECIDIDO owner]`). */
    public const STEP_CART = 6;

    public const STEP_PAYMENT = 7;

    /**
     * El DESENLACE: qué ha pasado de verdad con el pedido que se acaba de crear (`#466`, T4).
     *
     * ⚠️ **No está en `stepLabels()` y por tanto no sale en el indicador, a propósito.** No es un
     * paso del asistente: es su final. Pintarlo como un octavo chip invitaría a volver atrás a un
     * asistente cuyo pedido **ya está cobrado**, que es justo lo que {@see goToStep()} y sus hermanas
     * bloquean mientras haya desenlace en pantalla.
     */
    public const STEP_DONE = 8;

    /**
     * Los pasos que componen UNA LÍNEA, en orden. Del último con algo que preguntar cuelga «Añadir
     * al carrito».
     *
     * ⚠️ **Ese último paso es VARIABLE** —Extras si el producto tiene, si no Datos, si no Cuándo—,
     * y por eso el botón no puede vivir dentro del formulario de un paso fijo: vive en la
     * navegación, que es la única que sabe en qué paso está.
     *
     * @var list<int>
     */
    public const LINE_STEPS = [self::STEP_PRODUCT, self::STEP_WHEN, self::STEP_DETAILS, self::STEP_EXTRAS];

    /** Los días de la semana empiezan en LUNES: el panel es de un parque español. */
    private const WEEK_STARTS_ON = CarbonInterface::MONDAY;

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

    /** Paso actual del asistente (§4 de `specs/asistente-crear-pedido.md`). */
    public int $step = self::STEP_CUSTOMER;

    /**
     * El pedido que se acaba de crear, mientras se enseña su desenlace (`#466`). `null` = no hay.
     *
     * ⚠️⚠️ **Es también lo que impide crear el mismo pedido dos veces.** Al terminar, `create()`
     * VACÍA el carrito, así que una segunda llamada —un doble clic, un `wire:click` repetido— se
     * encuentra la cesta vacía y no crea nada. Antes esto lo garantizaba la redirección a la ficha
     * del pedido; sin ella, lo garantiza el estado. Hay caso propio.
     */
    public ?int $createdOrderId = null;

    /**
     * Menores que la asignación NO pudo colocar tras cobrar (`specs/menores-a-cargo.md` D14·5).
     *
     * Se guarda para que el DESENLACE lo diga: la asignación corre fuera de la transacción del cobro
     * y su aviso era un *toast*, que desaparece — y lo que hay que hacer (asignarlos desde la ficha)
     * queda para después.
     */
    public int $dependentsSkipped = 0;

    /**
     * El mes que enseña el calendario del paso «Cuándo», `Y-m`. `null` = el que toque solo.
     *
     * ⚠️ **Es un OVERRIDE, no el estado del mes** ({@see calendarMonthKey()}): mientras nadie
     * navegue, el mes lo decide la oferta —el del día elegido, y si no, el del primer día
     * ofrecible—, y **el override se descarta en cuanto deja de tener oferta**. Por eso cambiar de
     * producto no puede dejar el calendario plantado en un mes donde el nuevo no se vende, y no hace
     * falta reiniciarlo a mano: si los dos productos venden en ese mes, quedarse es lo que el
     * operador espera.
     */
    public ?string $calMonth = null;

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

    /**
     * Memo por petición de los menores a cargo del cliente en la fecha en curso (tanda 5, D14·5): el
     * selector lo pide desde `options`, `descriptions`, `disableOptionWhen` y `visible` en el mismo render.
     *
     * @var array{key: string, options: array<int, string>, descriptions: array<int, string>, disabled: list<int>, names: array<int, string>}|null
     */
    private ?array $dependentOptionsMemo = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('orders.create_manual') ?? false;
    }

    /**
     * #223 — fuera del menú lateral: esta pantalla es de puesta en marcha, no del día a
     * día, y se entra por «Ajustes» (`AdminSettingsHub`, menú del avatar). Ocultar NO es
     * autorizar: quien decide el acceso sigue siendo `canAccess()`/`canViewAny()`.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.orders.create_manual.nav_label');
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
                $this->productStep(),
                $this->whenStep(),
                $this->detailsStep(),
                $this->extrasStep(),
                // El CARRITO (paso 6) no es un componente del formulario: no pregunta nada, enseña
                // lo elegido. Lo pinta la vista a una columna (`[DECIDIDO owner]`).
                $this->paymentStep(),
            ]);
    }

    // ─── Navegación del stepper ───────────────────────────────────────────

    /**
     * ¿Este paso tiene algo que preguntar? (`#462`)
     *
     * ⚠️⚠️ **Sin esto, dos de cada tres pasos saldrían EN BLANCO.** Medido sobre el catálogo real:
     * de los 18 productos vendibles, **16 no tienen ni un campo que rellenar** y 7 no tienen ni
     * campos ni complementos — o sea que vender una entrada obligaría a pasar por una pantalla
     * vacía y una excursión por dos. Eso es MÁS fricción, que es lo contrario del encargo.
     *
     * ▶ Un paso sin nada que preguntar **se salta**, y el indicador de arriba lo pinta saltado:
     * nunca en silencio, porque entonces el operador no entendería por qué el asistente «da
     * saltos».
     */
    public function stepHasSomethingToAsk(int $step): bool
    {
        return match ($step) {
            self::STEP_DETAILS => $this->selectionEventDataFields() !== []
                || $this->manualDependentOptions()['options'] !== []
                || ($this->selectedProduct()?->offersGuardianAuthorization() ?? false)
                || ($this->selectedProduct()?->requiresGuardianAuthorization() ?? false),
            self::STEP_EXTRAS => $this->selectedProductAddons()->isNotEmpty(),
            default => true,
        };
    }

    /**
     * ¿Este paso se pinta SALTADO en el indicador?
     *
     * ⚠️⚠️ **No es lo mismo que «no tiene nada que preguntar».** Lo que decide si «Datos» y «Extras»
     * preguntan algo es el PRODUCTO, así que antes de elegirlo la respuesta no se sabe — y sin esta
     * distinción el indicador arrancaba diciendo «sin nada que rellenar» en el paso 1, con el
     * operador aún sin haber elegido nada. *Afirmar lo que todavía no se sabe es peor que callar.*
     * Lo vio la sonda de navegador, no un test.
     */
    public function stepIsSkipped(int $step): bool
    {
        return filled($this->data['sel_product_id'] ?? null) && ! $this->stepHasSomethingToAsk($step);
    }

    /** ¿Es éste el ÚLTIMO paso de la línea con algo que preguntar? De él cuelga «Añadir al carrito». */
    public function isLastLineStep(): bool
    {
        if (! in_array($this->step, self::LINE_STEPS, true)) {
            return false;
        }

        foreach (self::LINE_STEPS as $candidate) {
            if ($candidate > $this->step && $this->stepHasSomethingToAsk($candidate)) {
                return false;
            }
        }

        return true;
    }

    /** ¿Puede avanzarse desde el paso actual? (gobierna el `:disabled` del botón de avanzar). */
    public function canAdvance(): bool
    {
        return match ($this->step) {
            // ⚠️ El teléfono que falta RETIENE aquí (`#440`): el operador lo tiene delante y es el
            // único momento en que puede pedírselo. Basta con haberlo ESCRITO —lo guarda
            // {@see next()}—, o el botón quedaría muerto sin decir qué falta.
            self::STEP_CUSTOMER => filled($this->data['customer_id'] ?? null)
                && (! $this->customerNeedsPhone() || filled($this->data['customer_phone'] ?? null)),
            self::STEP_PRODUCT => filled($this->data['sel_product_id'] ?? null),
            self::STEP_WHEN => filled($this->data['sel_date'] ?? null) && filled($this->data['sel_time'] ?? null),
            self::STEP_DETAILS, self::STEP_EXTRAS => true,
            self::STEP_CART => $this->cart !== [],
            default => false,
        };
    }

    public function next(): void
    {
        if ($this->isDone()) {
            return;
        }

        // El teléfono escrito en el paso del cliente se salda AQUÍ, que es el único momento
        // definido: ni en cada tecla, ni al perder el foco. Si no había nada que saldar, no hace
        // nada (`CheckoutDuties::recordPhone()` no pisa lo que ya existe).
        if ($this->step === self::STEP_CUSTOMER) {
            $this->saveMissingPhone();
        }

        if (! $this->canAdvance()) {
            return;
        }

        // El último paso de la línea no «avanza»: añade al carrito. Que el botón de avanzar acabe
        // aquí es lo que evita tener DOS acciones que hacen lo mismo con nombres distintos.
        if ($this->isLastLineStep()) {
            $this->addLineToCart();

            return;
        }

        $this->step = $this->stepAfter($this->step);
    }

    public function back(): void
    {
        if ($this->isDone()) {
            return;
        }

        $this->step = $this->stepBefore($this->step);
    }

    /**
     * ¿Hay un pedido recién creado en pantalla? (`#466`)
     *
     * ⚠️ **Mientras lo haya, el asistente NO navega.** Volver «atrás» desde el desenlace llevaría a
     * un asistente con el carrito vacío y un pedido ya cobrado detrás: un estado que no es ni el de
     * antes ni el de después. La única salida es {@see startAnotherOrder()}, que lo limpia todo.
     */
    private function isDone(): bool
    {
        return $this->createdOrderId !== null;
    }

    /**
     * Empezar OTRO pedido desde el desenlace (`#466`, `[owner]`: la pantalla nueva es el final del
     * flujo, y del final se sale volviendo a empezar).
     *
     * ⚠️ **Limpia el CLIENTE también.** En un mostrador el siguiente pedido es de otra persona; dejar
     * al anterior seleccionado es la forma más fácil de cobrarle a quien no era — el mismo motivo por
     * el que la puerta tiene «Nueva búsqueda» (`panel-navegacion.md` §8.3).
     */
    public function startAnotherOrder(): void
    {
        $this->createdOrderId = null;
        $this->dependentsSkipped = 0;
        $this->cart = [];
        $this->calMonth = null;
        $this->selAddonQty = [];
        $this->selAddonGroup = [];
        $this->addonsMemo = null;
        $this->addonsMemoFor = null;
        $this->clearPhoneMatch();
        $this->form->fill();
        $this->step = self::STEP_CUSTOMER;
    }

    /** Vuelve al paso de PRODUCTO para añadir otra línea (`[DECIDIDO owner]`: «añadir más productos»). */
    public function addMoreProducts(): void
    {
        $this->step = self::STEP_PRODUCT;
    }

    /**
     * Salta al paso pedido desde el indicador de arriba. **Solo hacia ATRÁS y solo a pasos con algo
     * que preguntar**: hacia adelante saltaría preguntas sin contestar, y a un paso vacío llevaría
     * a una pantalla en blanco.
     */
    public function goToStep(int $step): void
    {
        if ($this->isDone()) {
            return;
        }

        if ($step < $this->step && $step >= self::STEP_CUSTOMER && $this->stepHasSomethingToAsk($step)) {
            $this->step = $step;
        }
    }

    /** El siguiente paso con algo que preguntar (o el de pago, que siempre lo tiene). */
    private function stepAfter(int $step): int
    {
        for ($n = $step + 1; $n <= self::STEP_PAYMENT; $n++) {
            if ($this->stepHasSomethingToAsk($n)) {
                return $n;
            }
        }

        return self::STEP_PAYMENT;
    }

    /** El anterior con algo que preguntar (o el primero). */
    private function stepBefore(int $step): int
    {
        for ($n = $step - 1; $n >= self::STEP_CUSTOMER; $n--) {
            if ($this->stepHasSomethingToAsk($n)) {
                return $n;
            }
        }

        return self::STEP_CUSTOMER;
    }

    /**
     * Avanza SOLO si el gesto que lo dispara es una ELECCIÓN del operador (`#462`).
     *
     * ⚠️⚠️ **El auto-avance se engancha al CAMBIO, jamás al estado.** Si mirase el estado, volver
     * atrás a «Cuándo» con la hora ya puesta rebotaría hacia adelante otra vez y el operador no
     * podría corregir nada: quedaría atrapado en el último paso.
     */
    private function advanceAfterChoice(): void
    {
        if ($this->canAdvance() && ! $this->isLastLineStep()) {
            $this->step = $this->stepAfter($this->step);
        }
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

        // `#462`: el alta en mostrador termina igual que elegir de la lista — «o se registre un
        // cliente manualmente, automáticamente seguimos». Si solo avanzara una de las dos puertas,
        // el operador que da de alta se quedaría mirando un paso ya contestado.
        $this->advanceAfterChoice();
    }

    // ─── Paso 1: cliente ──────────────────────────────────────────────────

    /**
     * ¿Al cliente ELEGIDO le falta el teléfono? (`#440`, `specs/telefono-del-cliente.md` §4.1)
     *
     * ⚠️⚠️ **La autoridad es `CheckoutDuties::pendingFor()` y NUNCA una segunda copia.** El docblock
     * de ese servicio cuenta por qué existe: la pregunta *«¿qué le falta a esta cuenta?»* se estaba
     * respondiendo en dos sitios, cada uno con su `trim($user->phone) === ''`, y divergir significa
     * **pedir un campo que el servidor no pide, o al revés**.
     *
     * Sin cliente elegido devuelve `false`: no se afirma lo que todavía no se sabe (la lección de
     * `#462` con el indicador de pasos, que decía «sin nada que rellenar» antes de elegir producto).
     */
    public function customerNeedsPhone(): bool
    {
        $customer = $this->selectedCustomer();

        return $customer !== null && app(CheckoutDuties::class)->pendingFor($customer)['phone'];
    }

    /** El cliente elegido, o `null`. Memo por petición: lo consultan la puerta, el campo y la vista. */
    private function selectedCustomer(): ?User
    {
        $id = (int) ($this->data['customer_id'] ?? 0);

        if ($id <= 0) {
            $this->selectedCustomerMemo = null;
            $this->selectedCustomerFor = null;

            return null;
        }

        if ($this->selectedCustomerFor !== $id) {
            $this->selectedCustomerMemo = User::find($id);
            $this->selectedCustomerFor = $id;
        }

        return $this->selectedCustomerMemo;
    }

    private ?User $selectedCustomerMemo = null;

    private ?int $selectedCustomerFor = null;

    /**
     * Salda el teléfono que el operador acaba de escribir. Escribe el MÓDULO, no esta página.
     *
     * ⚠️⚠️ **`ApiBoundariesTest` ya puso en rojo exactamente esta escritura** cuando vivía en el
     * controlador del checkout: un `save()` de dominio en la capa de entrega. De ahí nació
     * `CheckoutDuties`, y por eso aquí solo se pregunta y se pasa lo que el operador tecleó.
     */
    private function saveMissingPhone(): void
    {
        $customer = $this->selectedCustomer();
        $typed = trim((string) ($this->data['customer_phone'] ?? ''));

        if ($customer === null || $typed === '') {
            return;
        }

        if (app(CheckoutDuties::class)->recordPhone($customer, $typed)) {
            $this->selectedCustomerFor = null;   // el memo tiene la foto de antes de escribir
            $this->data['customer_phone'] = null;

            AuditLogger::log('orders.manual_customer_phone_added', $customer);
        }
    }

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
                            // ⚠️ Cambiar de cliente vacía el carrito (nunca cobrar a B las líneas de
                            // A) y descarta cualquier aviso de duplicado pendiente. Y AVANZA
                            // (`#462`, `[DECIDIDO owner]`: «en cuanto se elija un cliente,
                            // automáticamente seguimos»): elegir es la respuesta a la única
                            // pregunta del paso, así que pedir además un «Siguiente» es un toque
                            // que no decide nada.
                            ->afterStateUpdated(function (): void {
                                $this->cart = [];
                                $this->clearPhoneMatch();
                                $this->advanceAfterChoice();
                            })
                            ->required(),

                        // `#440` · el teléfono que falta se pide AQUÍ, con el cliente al teléfono o
                        // delante del mostrador. Solo se pinta cuando de verdad falta: a un cliente
                        // completo no se le enseña un campo vacío que no hay que rellenar.
                        //
                        // ⚠️ `live(onBlur: true)` y no en cada tecla: lo que tiene que reaccionar es
                        // el botón de avanzar (`canAdvance()`), y hacerlo por pulsación sería una
                        // petición por letra sobre una página que ya es grande.
                        TextInput::make('customer_phone')
                            ->label(__('admin.orders.create_manual.customer_phone'))
                            ->helperText(__('admin.orders.create_manual.customer_phone_help'))
                            ->tel()
                            ->maxLength(30)
                            ->live(onBlur: true)
                            ->visible(fn (): bool => $this->customerNeedsPhone()),

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
                                    // Fase 6 · waiver, `[DECIDIDO owner]` (spec §7·4, `#178`): la firma
                                    // DECLARADA en mostrador exige enseñar el texto vigente y decirlo.
                                    // Sin la casilla no hay firma; solo existe en interno con versión.
                                    Placeholder::make('waiver_text')
                                        ->label(fn (): string => __('admin.orders.create_manual.register_waiver_text', ['version' => $this->counterWaiverVersion()?->version ?? '']))
                                        ->content(fn (): HtmlString => $this->counterWaiverText())
                                        ->visible(fn (): bool => $this->counterWaiverVersion() !== null),
                                    Checkbox::make('waiver_declared')
                                        ->label(__('admin.orders.create_manual.register_waiver'))
                                        ->helperText(__('admin.orders.create_manual.register_waiver_help'))
                                        ->visible(fn (): bool => $this->counterWaiverVersion() !== null),
                                ])
                                ->action(fn (array $data) => $this->registerCustomerFromData($data)),
                        ]),
                    ]),
            ]);
    }

    /** Memo por petición de la versión firmable que enseña el mostrador (F-06). */
    private ?LegalDocumentVersion $counterWaiverVersionMemo = null;

    private bool $counterWaiverVersionResolved = false;

    /** Memo por petición del mapa de horas ofrecibles (`SlotOffer`); clave "producto|fecha". */
    private ?array $timeMapCache = null;

    private ?string $timeMapKey = null;

    /** Memo por petición de las fechas ofrecibles del producto (las consumen minDate/maxDate/disabledDates). */
    private ?array $offerableDatesCache = null;

    private ?int $offerableDatesFor = null;

    // ─── Paso 2: productos ────────────────────────────────────────────────

    /**
     * PASO 2 · el PRODUCTO. Hoy es un desplegable; la T2 lo convierte en tarjetas con icono, que es
     * lo que cierra el crítico C1 de la auditoría (un cumpleaños vendido como diez entradas).
     */
    private function productStep(): Group
    {
        return Group::make()
            ->visible(fn (): bool => $this->step === self::STEP_PRODUCT)
            ->schema([
                Section::make(__('admin.orders.create_manual.add_product'))
                    ->schema([
                        // ⚠️⚠️ **Aquí vivía un `Select` PLANO de 18 opciones, y es el crítico C1 de la
                        // auditoría**: su rótulo era `«{zona} · {nombre}»` y **nada decía si aquello
                        // era una entrada o un pack** —ni el prefijo de zona servía: «JUMP ·
                        // Cumpleaños E2E extras» es un PACK—. Con él, una admin vendió un cumpleaños
                        // como diez entradas sueltas: **119,00 € en vez de 180,00 €**, la sala sin
                        // reservar, 60 min de ocupación en vez de 120 y sin formulario de invitados.
                        //
                        // ▶ Las tarjetas van **AGRUPADAS POR TIPO**, que es lo que cierra el agujero:
                        // el operador ya no elige de una lista donde las dos cosas se parecen, elige
                        // dentro de «Entradas» o dentro de «Packs y celebraciones».
                        ViewComponent::make('filament.pages.partials.manual-order-products')
                            ->viewData(fn (): array => ['grupos' => $this->productCards()]),
                    ]),
            ]);
    }

    private function whenStep(): Group
    {
        return Group::make()
            ->visible(fn (): bool => $this->step === self::STEP_WHEN)
            ->schema([
                Section::make(__('admin.orders.create_manual.step_when'))
                    ->schema([
                        TextInput::make('sel_qty')
                            ->label(fn (): string => $this->isPackSelected()
                                ? __('admin.orders.create_manual.guests')
                                : __('admin.orders.create_manual.quantity'))
                            ->numeric()
                            // Min/máx aplicados en el campo (no solo al validar): packs respetan
                            // su rango [min_qty, max_qty]; entradas mínimo 1, sin tope.
                            // `#329`: con el interruptor puesto el suelo es 1; el tope no se mueve.
                            ->minValue(fn (): int => $this->selectedMinQty())
                            ->maxValue(fn (): ?int => $this->selectedMaxQty())
                            ->default(1)
                            ->helperText(fn (): ?string => $this->belowMinimumActive()
                                ? __('admin.orders.create_manual.below_minimum_active', [
                                    'min' => $this->selectedProduct()?->contractableMinimum() ?? 1,
                                ])
                                : null)
                            // Reactivo: al cambiar el nº de invitados, el widget de complementos
                            // recalcula los `per_guest` (uno por invitado) y su importe.
                            ->live(onBlur: true),

                        // `#329` — el gemelo de D7 al CREAR: el interruptor del mínimo solo se
                        // OFRECE con su permiso y con un mínimo que rebajar, y va JUNTO al campo de
                        // cantidad porque es lo que decide su suelo. `live()` sin `onBlur` para que
                        // el campo de al lado se re-evalúe en el mismo gesto.
                        Toggle::make('sel_below_minimum')
                            ->label(__('admin.orders.create_manual.below_minimum_label'))
                            ->helperText(fn (): string => __('admin.orders.create_manual.below_minimum_help', [
                                'min' => $this->selectedProduct()?->contractableMinimum() ?? 1,
                            ]))
                            ->default(false)
                            ->live()
                            ->visible(fn (): bool => $this->canGoBelowPackMinimum())
                            // Al apagarlo, una cantidad que solo era válida con la excepción dejaría
                            // el campo por debajo de su suelo: se sube al mínimo del pack en el mismo
                            // gesto en vez de esperar a que el operador choque con la validación.
                            ->afterStateUpdated(function (Get $get, callable $set): void {
                                $min = $this->selectedMinQty();
                                if ((int) $get('sel_qty') < $min) {
                                    $set('sel_qty', $min);
                                }
                            }),

                        // ❗❗ **EL CALENDARIO, GRANDE Y SIEMPRE VISIBLE** (`#464`, T3, `[owner]`: «la
                        // fecha la selecciona de un calendario grande, bien visible»). Sustituye a la
                        // tira de 14 días y al `DatePicker` plegado tras su CTA que traía `#241`:
                        // medido antes de tocarlo, aquel calendario era un popover de **259×248 px con
                        // celdas de 29×28** —bajo el mínimo táctil— y costaba dos toques abrirlo.
                        //
                        // ⚠️ **La tira SE RETIRA** (`[DECIDIDO owner, 2026-09-04]`, preguntado con el
                        // coste delante): con el calendario desplegado eran DOS puertas a la misma
                        // pregunta, y «hoy» sigue estando a un toque en el calendario, así que la tira
                        // no ahorraba ninguno — solo 90 px. Eso corrige a `auditoria-panel-admin.md`
                        // §7, que la daba por buena cuando el calendario vivía escondido.
                        //
                        // ⚠️ **El modelo de vista lo compone el SERVIDOR** ({@see calendarMonth()}) y
                        // el clic va a `pickDay()`, que **vuelve a comprobar** que el día esté en la
                        // oferta: el navegador propone, el servidor decide (`AFORO-02`).
                        ViewComponent::make('filament.pages.partials.manual-order-calendar')
                            ->viewData(fn (): array => [
                                'label' => __('admin.orders.create_manual.date'),
                                'mes' => $this->calendarMonth(),
                            ]),

                        // ⚠️ **CHIPS de dos niveles** (`#241`, `[OWNER]`: «lo de las plazas debería
                        // mostrarse de manera más sutil, no al mismo nivel que la hora»). En `#240`
                        // esto era un `ToggleButtons` nativo, y su etiqueta es TEXTO PLANO —no admite
                        // `allowHtml`—, así que «10:00 · 20 plazas» salía todo con el mismo peso.
                        // Con un partial propio la hora manda y el cupo queda de contexto.
                        // ❗ **Cambiar el control NO cambia la regla**: una franja no vendible sigue
                        // saliendo deshabilitada, lo decide el mismo `timeMap()` y lo comprueba
                        // `pickTime()` en el servidor. Hay guarda.
                        ViewComponent::make('filament.pages.partials.manual-order-times')
                            ->viewData(fn (): array => [
                                'times' => $this->timeChips(),
                                'label' => __('admin.orders.create_manual.time'),
                                'help' => $this->timeFieldHelp(),
                            ]),
                    ]),
            ]);
    }

    /**
     * PASO 4 · DATOS de la reserva. **CONDICIONAL**: la mayoría de los productos no tiene ninguno de
     * los tres (campos de evento, menores asignables, justificante), y entonces el paso se salta.
     */
    private function detailsStep(): Group
    {
        return Group::make()
            ->visible(fn (): bool => $this->step === self::STEP_DETAILS)
            ->schema([
                Section::make(__('admin.orders.create_manual.step_details'))
                    ->schema([
                        Toggle::make('sel_guardian_authorization')
                            ->label(__('admin.orders.create_manual.guardian_label'))
                            ->helperText(__('admin.orders.create_manual.guardian_help'))
                            ->default(false)
                            ->visible(fn (): bool => $this->selectedProduct()?->offersGuardianAuthorization() ?? false),

                        // Con `required` no hay nada que preguntar: se INFORMA, para que el operador
                        // sepa decírselo al cliente que tiene delante.
                        Placeholder::make('sel_guardian_required')
                            ->hiddenLabel()
                            ->content(__('admin.orders.create_manual.guardian_required'))
                            ->visible(fn (): bool => $this->selectedProduct()?->requiresGuardianAuthorization() ?? false),

                        CheckboxList::make('sel_dependent_ids')
                            ->label(__('admin.orders.dependents.field_label'))
                            ->helperText(__('admin.orders.dependents.manual_hint'))
                            ->options(fn (): array => $this->manualDependentOptions()['options'])
                            ->descriptions(fn (): array => $this->manualDependentOptions()['descriptions'])
                            ->disableOptionWhen(fn (string $value): bool => in_array((int) $value, $this->manualDependentOptions()['disabled'], true))
                            ->columns(1)
                            ->visible(fn (): bool => $this->manualDependentOptions()['options'] !== []),

                        // Datos del evento del pack (reactivo: solo hay UNA selección en curso).,

                        Group::make()
                            ->schema(fn (): array => $this->selectionEventDataFields())
                            ->visible(fn (): bool => $this->selectionEventDataFields() !== []),

                        // Complementos del producto seleccionado: MISMA lógica/condiciones que la web
                        // (incluido/obligatorio/por-invitado/grupo de elección) vía el view-model
                        // compartido `AddonResolver::viewModel`. La data se pasa por `viewData` (el
                        // `$this` del partial sería el View, no la página — lección #161).,
                    ]),
            ]);
    }

    /** PASO 5 · COMPLEMENTOS. **CONDICIONAL**: se salta si el producto no tiene ninguno. */
    private function extrasStep(): Group
    {
        return Group::make()
            ->visible(fn (): bool => $this->step === self::STEP_EXTRAS)
            ->schema([
                Section::make(__('admin.orders.create_manual.step_extras'))
                    ->schema([
                        ViewComponent::make('filament.pages.partials.manual-order-addons')
                            ->viewData(fn (): array => ['model' => $this->manualAddonViewModel()])
                            ->visible(fn (): bool => $this->selectedProductAddons()->isNotEmpty()),

                        // "Añadir al carrito" alineado a la derecha.,
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
                        // ⚠️ **DOS TARJETAS con icono, no dos radios** (`#242`, `[OWNER]`). Con la
                        // tablet en la mano y un cliente delante, el último gesto del pedido es el que
                        // menos margen de error admite: una diana de 44 px con un dibujo se acierta sin
                        // mirar, un círculo de 16 px no.
                        // ❗ **El control cambia; la regla NO.** `ToggleButtons` es el componente NATIVO
                        // —conserva `required` y `default`, y las claves siguen siendo las de
                        // `ManualOrderFulfiller`—, así que el cobro se decide exactamente igual: la
                        // forma de tarjeta la pone el CSS, no una segunda implementación del campo.
                        ToggleButtons::make('payment_method')
                            ->label(__('admin.orders.create_manual.payment_method'))
                            ->options([
                                ManualOrderFulfiller::METHOD_CASH => __('admin.orders.create_manual.method_cash'),
                                ManualOrderFulfiller::METHOD_DATAFONO => __('admin.orders.create_manual.method_datafono'),
                            ])
                            ->icons([
                                ManualOrderFulfiller::METHOD_CASH => Heroicon::OutlinedBanknotes,
                                ManualOrderFulfiller::METHOD_DATAFONO => Heroicon::OutlinedCreditCard,
                            ])
                            ->inline()
                            ->extraAttributes(['class' => 'cmo-pay'])
                            ->default(ManualOrderFulfiller::METHOD_CASH)
                            ->required(),
                    ]),
            ]);
    }

    // ─── Acciones ─────────────────────────────────────────────────────────

    /** Añade la selección en curso al carrito (validación real de aforo = al cobrar). */
    public function addLineToCart(): void
    {
        // ⚠️⚠️ **La puerta del paso 1 NO basta, y está medido** (`#440`, spec §4.3): este método es
        // PÚBLICO y no mira en qué paso está el asistente, así que desde el paso del cliente una
        // llamada suelta aterriza la línea en el carrito **y mueve `step` a `STEP_CART` ella misma**
        // (línea de abajo). Es la lección de `#464` con `$calMonth`: la propiedad pública no era el
        // agujero — el agujero es que el paso 1 no sea precondición de nada aguas abajo.
        if ($this->customerNeedsPhone()) {
            Notification::make()->warning()
                ->title(__('admin.orders.create_manual.customer_phone_required'))
                ->send();

            return;
        }

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

        // La edad del cumpleañero fuera del tramo del pack (`#588`, `[DECIDIDO owner]`): en el mostrador
        // AVISA y deja añadir —el parque tiene al cliente delante—; la web, en cambio, no lo deja.
        if (($mismatch = $type->celebrantAgeMismatch($eventData)) !== null) {
            Notification::make()->warning()
                ->title(__('admin.orders.create_manual.celebrant_age_warning'))
                ->body($mismatch->sentence())
                ->send();
        }

        // Menores a cargo (D14·5): solo los ASIGNABLES del cliente en esa fecha (un id forzado que no lo
        // sea se descarta aquí y lo volvería a rechazar `check()`), y nunca más menores que unidades —
        // con aviso, no recortando en silencio.
        $dependentIds = [];
        $dependentDisplay = [];
        if (! $type->isPack()) {
            $dependentOptions = $this->manualDependentOptions();
            $assignable = array_values(array_diff(array_keys($dependentOptions['options']), $dependentOptions['disabled']));
            $dependentIds = array_values(array_intersect(
                array_values(array_unique(array_map('intval', (array) ($this->data['sel_dependent_ids'] ?? [])))),
                $assignable,
            ));
            if (count($dependentIds) > $qty) {
                Notification::make()->warning()->title(__('admin.orders.dependents.manual_too_many'))->send();

                return;
            }
            $dependentDisplay = array_map(fn (int $id): string => $dependentOptions['names'][$id], $dependentIds);
        }

        // #225 (DISPLAY): señal de la línea, para AVISAR en el carrito de que un producto con señal
        // cobra ahora solo la señal del principal y el resto (+ complementos) se cobra en el parque.
        // Mismo cálculo data-driven que la landing (`depositCents` sobre el subtotal del principal).
        // NO afecta al cobro real, que lo calcula `ManualOrderFulfiller` vía `Order::onlineDueCents()`
        // de forma independiente — esto es puramente informativo para el empleado.
        // ⚠️ La CANTIDAD va también aquí, por lo mismo que en `estimateLineCents()`: con tramos, la
        // señal se calcula sobre el subtotal del principal y ese subtotal depende de cuántos son.
        $principalSubtotal = ((int) (app(RateResolver::class)->priceCents($type, Carbon::parse($date), $qty) ?? 0)) * $qty;
        $lineDepositCents = $type->depositCents($principalSubtotal);
        $hasLineDeposit = $lineDepositCents < $principalSubtotal;

        $this->cart[] = [
            'ticket_type_id' => $type->id,
            'date' => (string) Carbon::parse($date)->toDateString(),
            'time' => (string) $time,
            'qty' => $qty,
            'event_data' => $eventData,
            'addons' => $addons,
            'addon_display' => $this->resolvedAddonDisplay($type, $qty, $addons, Carbon::parse($date)),
            'label' => $this->productLabel($type),
            'when' => Carbon::parse($date)->format('d/m/Y').' '.substr((string) $time, 0, 5),
            'line_total_cents' => $this->estimateLineCents($type, $date, $qty, $addons),
            // null = sin señal (se cobra el total). Si hay señal, son los céntimos que se cobran AHORA.
            'deposit_cents' => $hasLineDeposit ? $lineDepositCents : null,
            // Menores a cargo (D14·5): ids para Identity (NUNCA llegan a Booking: `cartToOrderCart()` no
            // los copia) y nombres solo para el resumen del operador.
            'dependent_ids' => $dependentIds,
            'dependent_display' => $dependentDisplay,
            // `#329`: la excepción se guarda POR LÍNEA, no como un modo del formulario — el operador
            // la activó para ESTE pack. `create()` la vuelve a resolver contra el permiso.
            'below_minimum' => $type->isPack() && $qty < $type->contractableMinimum(),
            // El JUSTIFICANTE (`specs/waiver-por-reserva.md` §12.2). Va POR LÍNEA como la excepción de
            // arriba: el operador lo marcó para ESTE producto. `OrderCreator` lo vuelve a resolver
            // contra el catálogo, así que con `required` la línea nace marcada aunque esto sea `false`.
            'guardian_authorization' => (bool) ($this->data['sel_guardian_authorization'] ?? false),
        ];

        // Resetea la selección para la siguiente línea (preserva cliente, método y paso).
        $this->data['sel_product_id'] = null;
        $this->data['sel_date'] = null;
        $this->data['sel_time'] = null;
        $this->data['sel_qty'] = null;
        $this->data['sel_below_minimum'] = false;
        $this->data['sel_guardian_authorization'] = false;
        $this->data['event_data'] = [];
        $this->data['sel_dependent_ids'] = [];
        $this->selAddonQty = [];
        $this->selAddonGroup = [];
        $this->addonsMemo = null;
        $this->addonsMemoFor = null;

        Notification::make()->success()->title(__('admin.orders.create_manual.line_added'))->send();

        // `#462`: añadir una línea TERMINA la línea, así que la pantalla siguiente es el carrito
        // (`[DECIDIDO owner]`: «después de añadir al carrito, quiero que el carrito ocupe toda la
        // pantalla»). Volver a productos es una acción propia, no el camino por defecto.
        $this->step = self::STEP_CART;
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
            // ⚠️ El alta manual **VENDE**, así que filtra como el embudo (`#413` §4.4 y D2): un
            // complemento de venta POSTERIOR no puede nacer con el pedido ni siquiera desde el
            // mostrador — si naciera, su línea valdría > 0 y el cliente podría retirar desde su
            // post-form algo que SÍ se cobró, que es lo que la propiedad de §1.3 impide.
            // *«El panel» no es la unidad: lo es VENDER frente a GESTIONAR una línea que ya existe.*
            $this->addonsMemo = AddonResolver::forStage(
                $type->addons()->with('prices.rateType')->get(),
                ProductAddon::STAGE_BOOKING,
            );
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

    /**
     * Modelo de vista de los complementos (grupos + sueltos + total) para el partial.
     *
     * ⚠️ Se tarifica con el DÍA ELEGIDO, no con hoy (`#415`): lo que el mostrador ve tiene que ser lo
     * que se cobrará. Mientras el operador no haya elegido fecha se cae a hoy, que es el estado en
     * que todavía no hay día de visita con el que preguntar.
     */
    public function manualAddonViewModel(): array
    {
        $sel = $this->effectiveAddonSelection();
        $date = (string) ($this->data['sel_date'] ?? '');

        return app(AddonResolver::class)->viewModel(
            $this->selectedProductAddons(),
            $sel['qty'],
            $sel['groups'],
            max(0, (int) ($this->data['sel_qty'] ?? 0)),
            $this->selectedProduct()?->isPack() ?? false,
            $date !== '' ? Carbon::parse($date) : Carbon::today(),
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
     * ⚠️ `$date` es el día de la VISITA de la línea (`#415`), el mismo con el que se tarifica el
     * principal: si un complemento solo tiene precio en días `special`, el mostrador tiene que ver
     * exactamente lo que se va a cobrar.
     *
     * @param  array<int, array{ticket_type_id:int, qty:int}>  $addons
     * @return array<int, array{name:string, qty:int, free_qty:int, subtotal:int}>
     */
    private function resolvedAddonDisplay(TicketType $type, int $qty, array $addons, CarbonInterface $date): array
    {
        if ($addons === []) {
            return [];
        }
        $type->loadMissing('addons');
        try {
            $resolved = app(AddonResolver::class)->resolve($type, $qty, $addons, $date);
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
        // La declaración del waiver viaja hasta `performRegistration`, también por el camino del cliente sin email (`#178`).
        $waiverDeclared = (bool) ($data['waiver_declared'] ?? false);

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
                $this->pendingNoEmailCustomer = ['name' => $name, 'phone' => $phone, 'waiver_declared' => $waiverDeclared];
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

        $this->performRegistration($name, $email, $phone, $waiverDeclared);
    }

    /**
     * Crea la cuenta (con o sin email) vía `CustomerRegistrar`, la selecciona y notifica. Compartido
     * por el alta normal y por «crear nuevo de todos modos» tras el aviso de duplicado. El rate-limit
     * se aplica AQUÍ (en el alta real), con clave por email o, sin email, por teléfono normalizado
     * (nunca `md5('')`, que metería todas las altas sin email en el mismo cubo).
     */
    private function performRegistration(string $name, ?string $email, string $phone, bool $waiverDeclared = false): void
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
            $result = app(CustomerRegistrar::class)->register($name, $email, $phone, waiverDeclared: $waiverDeclared);
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
        // F-01 (`#181`): la declaración marcada en el modal vale también para el cliente EXISTENTE elegido.
        if ((bool) ($this->pendingNoEmailCustomer['waiver_declared'] ?? false)) {
            app(CustomerRegistrar::class)->declareAtCounter(User::findOrFail($id));
        }
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
        $this->performRegistration((string) $pending['name'], null, (string) $pending['phone'], (bool) ($pending['waiver_declared'] ?? false));
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
    /**
     * El titular del pedido en curso, para la cabecera del resumen (`#241`, `[OWNER]`: «añadimos el
     * nombre del cliente, o su correo»).
     *
     * ⚠️ **Reutiliza `customerDisplay()`**, que es la misma cadena que pinta el buscador de clientes:
     * nombre + correo, o nombre + teléfono cuando no hay correo (cliente de agenda, `#263`). Componer
     * aquí una segunda forma sería tener dos maneras de nombrar a la misma persona en la misma página.
     *
     * `null` mientras no hay cliente elegido — el resumen entonces no pinta cabecera.
     */
    public function currentCustomerLabel(): ?string
    {
        $id = (int) ($this->data['customer_id'] ?? 0);

        return $id > 0 ? $this->customerLabel($id) : null;
    }

    private function customerDisplay(User $u): string
    {
        // ⚠️ `#440` · el «tiene teléfono» lo decide la MISMA autoridad que la puerta del paso 1, no
        // una tercera redacción: con un teléfono de espacios, el rótulo del cliente y el aviso del
        // paso se contradecían («Nombre · <espacios>» mientras el asistente decía que faltaba).
        $hasPhone = ! app(CheckoutDuties::class)->pendingFor($u)['phone'];

        $contact = filled($u->email)
            ? (string) $u->email
            : ($hasPhone ? trim((string) $u->phone) : __('admin.orders.create_manual.customer_no_email'));

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

        // ❗❗ `#440`, `[DECIDIDO owner]` — **una venta NUNCA se bloquea por el teléfono.**
        // Llegar aquí sin él exige haber esquivado las tres puertas de arriba (una pestaña vieja, un
        // estado desincronizado, un refactor que llame a `addLineToCart()` desde otra puerta), así
        // que es raro; pero con el cliente delante y el carrito montado, **negarse a cobrar por un
        // número es peor que vender sin él**. Se avisa y se deja rastro, y se sigue.
        //
        // ⚠️⚠️ **Esto es deliberado: NO lo conviertas en un `return`.** Sería lo primero en la
        // historia del panel capaz de tumbar una venta de mostrador por un teléfono que falta.
        if (app(CheckoutDuties::class)->pendingFor($customer)['phone']) {
            Notification::make()->warning()
                ->persistent()
                ->title(__('admin.orders.create_manual.customer_phone_missing_warning'))
                ->send();

            AuditLogger::log('orders.manual_created_without_phone', $customer);
        }

        $method = (string) ($this->data['payment_method'] ?? '');

        // Menores a cargo (D14·5, D3): FASE 1 ANTES del dinero. Un rechazo —el menor dejó de ser del
        // cliente, no tiene la exención vigente, es adulto ese día— no crea ni cobra NADA: el operador
        // lo arregla con el cliente delante. Sin menores en el carrito no comprueba nada.
        $dependentRequests = $this->dependentRequests();
        $rejections = app(DependentAssigner::class)->check($customer, $dependentRequests);
        if ($rejections !== []) {
            $reasons = array_values(array_unique(array_merge(...array_values($rejections))));
            Notification::make()
                ->danger()
                ->persistent()
                ->title(__('admin.orders.dependents.manual_check_failed', ['reasons' => implode(' · ', $reasons)]))
                ->send();

            return;
        }

        // `#329` — la excepción del mínimo se resuelve AQUÍ, en el punto de ejecución, y no en la
        // visibilidad del interruptor: `$this->cart` es estado de un componente Livewire y viaja al
        // navegador, así que un `below_minimum` a `true` puede llegar sin que nadie haya pulsado nada
        // (`SEC-04`, y la misma razón por la que `OrderItemEditor::edit()` re-exige el permiso pese a
        // que `ViewOrder` ya decide si pinta el interruptor). Sin permiso, el mínimo manda y
        // `OrderCreator` rechaza la línea como siempre.
        $belowMinimum = (auth()->user()?->hasPermission('orders.edit_item_below_minimum') ?? false)
            && collect($this->cart)->contains(fn (array $line): bool => (bool) ($line['below_minimum'] ?? false));

        try {
            $order = app(ManualOrderFulfiller::class)->fulfill($customer, $this->cartToOrderCart(), $method, $belowMinimum);
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

        // Menores a cargo (D14·5, §9.9.1): FASE 2 DESPUÉS de que `fulfill()` devuelva, FUERA de su
        // transacción. El cobro ya está tomado y el pedido en pie; si esto falla, el operador lo ve y lo
        // asigna desde la ficha del pedido («Asignar menores»). Nunca lanza.
        $outcome = app(DependentAssigner::class)->assign($customer, (int) $order->id, $dependentRequests);
        $this->dependentsSkipped = ($outcome->skipped > 0 || $outcome->abortedBecause !== null)
            ? max(1, $outcome->skipped)
            : 0;

        // `#466` — el DESENLACE sustituye a la redirección a la ficha (`[owner]`: «después de crear el
        // pedido, una pantalla nueva: pedido creado correctamente…»).
        //
        // ⚠️⚠️ **Vaciar el carrito no es limpieza: es lo que impide cobrar dos veces.** Sin la
        // redirección, la página sigue viva con su estado, así que un segundo `create()` —doble clic,
        // un `wire:click` repetido— crearía OTRO pedido idéntico. Con la cesta vacía se encuentra la
        // guarda de arriba y no crea nada. Hay caso propio.
        //
        // ⚠️ El aviso de los menores sin asignar deja de ser un *toast*: los toasts desaparecen y lo
        // que hay que hacer (asignarlos desde la ficha) queda para después. Lo dice la pantalla.
        $this->createdOrderId = (int) $order->id;
        $this->cart = [];
        $this->step = self::STEP_DONE;
    }

    /**
     * El DESENLACE del pedido recién creado (`#466`, T4), o `null` si no hay ninguno en pantalla.
     *
     * ❗❗❗ **Su única regla es no prometer nada que no haya pasado.** El encargo del owner era «una
     * pantalla nueva: pedido creado correctamente…», y lo que la hace útil —y peligrosa si se
     * escribe a ojo— es que el mostrador la lee en voz alta: qué se ha cobrado, qué queda por pagar
     * en el parque y **qué se le ha enviado al cliente**.
     *
     * ⚠️⚠️ **Hay clientes SIN correo** (`#263`: el alta de mostrador solo pide teléfono), y con ellos
     * `ManualOrderFulfiller` **no envía NADA** —ni la confirmación, ni el post-form, ni el
     * justificante— y lo deja en el log. Una pantalla que dijera «se lo hemos enviado» mandaría al
     * operador a casa creyendo que el cliente tiene su enlace. Por eso el correo se pregunta con el
     * MISMO predicado que usa el fulfiller (`filled($user->email)`) y, cuando no lo hay, la pantalla
     * lo dice y entrega los enlaces para que el operador los mande por WhatsApp.
     *
     * ⚠️ **Y la lista de ENTREGABLES sale de las MISMAS autoridades que el fulfiller consulta**
     * (`needsGuestForm()` por reserva y `guardianReservations()`), no de una regla nueva: si la
     * pantalla dedujera por su cuenta a quién hay que mandarle qué, diría una cosa y el correo haría
     * otra. Lo fija `CreateManualOrderDoneTest` comparando con lo que se ha NOTIFICADO de verdad.
     *
     * ▶ `#467` — **un solo bloque en vez de dos** (`[owner]`: «más profesional»): cada cosa que el
     * cliente tiene que recibir es una FILA con su estado y su enlace, en vez de una lista de
     * «enviado» arriba y otra de «enlaces» abajo que el operador tenía que emparejar de cabeza.
     *
     * @return array<string, mixed>|null
     */
    public function doneSummary(): ?array
    {
        if ($this->createdOrderId === null) {
            return null;
        }

        /** @var Order|null $order */
        $order = Order::with(['user', 'items.ticketType', 'items.slot'])->find($this->createdOrderId);

        if ($order === null) {
            return null;   // borrado entre medias: la pantalla cae al asistente en vez de reventar
        }

        $email = $order->user?->email;
        $postForm = $order->guestFormItems()->filter(fn (OrderItem $item): bool => $item->needsGuestForm())->values();
        $guardian = $order->guardianReservations();

        return [
            'code' => (string) $order->code,
            'url' => OrderResource::getUrl('view', ['record' => $order]),
            'customer' => $order->user ? $this->customerDisplay($order->user) : null,
            'email' => $email,
            'method' => (string) ($this->data['payment_method'] ?? ''),
            'book' => OrderBook::forOrder($order),
            'reservations' => $order->items
                ->whereNull('parent_item_id')
                ->map(fn (OrderItem $item): array => [
                    'label' => $item->displayProductName(),
                    'when' => $this->slotLabel($item),
                    'qty' => (int) $item->quantity,
                ])->values()->all(),
            // ❗❗❗ **UNA sola lista: lo que el cliente TIENE QUE RECIBIR** (`#467`, `[owner]`: «la parte
            // de que al cliente le ha llegado un formulario o lo que sea, más profesional»).
            //
            // Antes eran dos bloques —«se le ha enviado» arriba y «enlaces para entregar a mano»
            // abajo— y el operador tenía que atar mentalmente cada correo con su enlace. Aquí cada
            // ENTREGABLE es una fila: qué es, de qué reserva, si ha salido, y su enlace al lado.
            //
            // ⚠️ El enlace viaja aunque el correo haya salido: el cliente que dice «no me ha llegado»
            // está delante, y el operador ya tiene aquí lo que necesita sin ir a la ficha.
            'deliverables' => [
                [
                    'kind' => 'confirmation',
                    'subject' => null,
                    'when' => null,
                    'sent' => filled($email),
                    'url' => null,
                ],
                ...$postForm->map(fn (OrderItem $item): array => [
                    'kind' => 'guest_form',
                    'subject' => $item->displayProductName(),
                    'when' => $this->slotLabel($item),
                    'sent' => filled($email),
                    'url' => $item->guestFormSignedUrl(),
                ])->all(),
                ...$guardian->map(fn (OrderItem $item): array => [
                    'kind' => 'guardian',
                    'subject' => $item->displayProductName(),
                    'when' => $this->slotLabel($item),
                    'sent' => filled($email),
                    'url' => $item->guardianAuthorizationSignedUrl(),
                ])->all(),
            ],
            'dependents_skipped' => $this->dependentsSkipped,
        ];
    }

    /** Cuándo es una reserva, para leerlo en voz alta. `null` si la línea no tiene franja. */
    private function slotLabel(OrderItem $item): ?string
    {
        return $item->slot
            ? $item->slot->date->format('d/m/Y').' '.substr((string) $item->slot->start_time, 0, 5)
            : null;
    }

    /**
     * Las líneas del carrito en la forma que `DependentAssigner` espera (índice = posición en la cesta,
     * que es el orden en que `OrderCreator` crea los ítems, D2). Las líneas sin menores viajan igual:
     * el asignador las ignora, pero el RECUENTO de líneas es la guarda de correlación.
     *
     * @return list<array{index:int, product_id:int, date:string, quantity:int, dependent_ids:list<int>}>
     */
    private function dependentRequests(): array
    {
        $lines = [];
        foreach (array_values($this->cart) as $index => $line) {
            $lines[] = [
                'index' => $index,
                'product_id' => (int) $line['ticket_type_id'],
                'date' => (string) $line['date'],
                'quantity' => (int) $line['qty'],
                'dependent_ids' => array_values(array_map('intval', (array) ($line['dependent_ids'] ?? []))),
            ];
        }

        return $lines;
    }

    /**
     * Los menores a cargo del cliente en la fecha en curso, como opciones del selector (D14·5): vacío sin
     * cliente, sin fecha, sin producto o con un pack. Memo por (cliente, fecha).
     *
     * @return array{key: string, options: array<int, string>, descriptions: array<int, string>, disabled: list<int>, names: array<int, string>}
     */
    private function manualDependentOptions(): array
    {
        $empty = ['key' => '', 'options' => [], 'descriptions' => [], 'disabled' => [], 'names' => []];
        $customerId = (int) ($this->data['customer_id'] ?? 0);
        $rawDate = (string) ($this->data['sel_date'] ?? '');
        $type = $this->selectedProduct();
        if ($customerId <= 0 || $rawDate === '' || $type === null || $type->isPack()) {
            return $empty;
        }

        $date = Carbon::parse($rawDate)->toDateString();
        $key = "{$customerId}|{$date}";
        if (($this->dependentOptionsMemo['key'] ?? null) === $key) {
            return $this->dependentOptionsMemo;
        }

        $customer = User::find($customerId);
        if ($customer === null) {
            return $empty;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');
        $options = [];
        $descriptions = [];
        $disabled = [];
        $names = [];
        foreach (app(DependentAssigner::class)->candidates($customer, $date) as $candidate) {
            $dependent = $candidate['dependent'];
            $id = (int) $dependent->getKey();
            $names[$id] = (string) $dependent->name;
            // `#236`: nombre COMPLETO en el panel — el operador que crea un pedido a mano necesita
            // distinguir a dos hermanos, y el nombre de pila solo no siempre basta.
            $options[$id] = __('admin.orders.dependents.option', ['name' => $dependent->fullName(), 'age' => $dependent->ageOn($day)]);
            if ($candidate['reason'] !== null) {
                $descriptions[$id] = __('admin.orders.dependents.reasons.'.$candidate['reason']);
                $disabled[] = $id;
            }
        }

        return $this->dependentOptionsMemo = compact('key', 'options', 'descriptions', 'disabled', 'names');
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
    /**
     * Los productos vendibles AGRUPADOS POR TIPO, listos para pintar en tarjetas (`#462`, T2).
     *
     * ⚠️⚠️ **El agrupado es la corrección, no la decoración.** Antes esto devolvía un mapa plano
     * `id => "{zona} · {nombre}"` para un `Select`, y en él una entrada y un pack se parecían: la
     * ÚNICA señal era el nombre del producto, que lo escribe el cliente desde el panel. Con las dos
     * familias separadas y rotuladas, elegir mal deja de ser un descuido y pasa a ser otra pantalla.
     *
     * ⚠️ **El orden de los grupos no es alfabético: entradas primero.** Es lo que más se vende en
     * mostrador, y poner las celebraciones arriba obligaría a pasar por delante de ellas cada vez.
     *
     * ⚠️ Cada tarjeta lleva lo que DISTINGUE, no lo que decora: el icono que el propio panel ya deja
     * elegir, la zona, la duración, el precio y —solo en los packs— el rango de invitados, que es la
     * marca inconfundible de un producto de grupo.
     *
     * @return list<array{type: string, label: string, items: list<array<string, mixed>>}>
     */
    public function productCards(): array
    {
        $productos = TicketType::sellable()
            ->inOperationalZone() // una zona desactivada no vende (igual que la web)
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK])
            // ⚠️ **`prices.rateType` y `priceTiers` van en la precarga porque la TARJETA los pinta**:
            // `displayPriceCents()` busca la tarifa `normal` dentro de cada precio y `priceVaries()`
            // compara importes. Sin esto cada tarjeta abre TRES consultas y la pantalla costaba **54
            // para 18 productos** (medido en `#464`) — el N+1 que `#259` ya documentó en la web, aquí
            // por la puerta del panel. La misma precarga que hacen los controladores de la landing.
            ->with(['zone', 'prices.rateType', 'priceTiers'])
            ->orderBy('position')
            ->get();

        $elegido = (int) ($this->data['sel_product_id'] ?? 0);
        $grupos = [];

        foreach ([TicketType::TYPE_ENTRY, TicketType::TYPE_PACK] as $tipo) {
            $items = $productos
                ->where('type', $tipo)
                ->map(fn (TicketType $t): array => [
                    'id' => (int) $t->id,
                    'name' => (string) $t->tr('name'),
                    'zone' => $t->zone?->tr('name'),
                    // ⚠️ El MARCADOR del producto, el que el panel ya deja elegir en el catálogo
                    // (`ticket_types.icon`). Se resuelve con `iconKey()`, que es el puente único:
                    // una clave desconocida cae al defecto de su TIPO en vez de quedarse en blanco.
                    'icon' => $t->iconKey(),
                    'duration' => $t->duration_min,
                    'price' => $t->displayPriceCents(),
                    'price_varies' => $t->priceVaries(),
                    'is_pack' => $t->isPack(),
                    'min' => $t->isPack() ? $t->contractableMinimum() : null,
                    'max' => $t->isPack() ? $t->max_qty : null,
                    'selected' => $elegido === (int) $t->id,
                    // Para el «Más info»: si no hay NADA público que enseñar, la tarjeta no ofrece
                    // un botón que abriría un modal vacío.
                    'has_info' => filled($t->tr('description')) || filled($t->tr('features')),
                ])
                ->values()
                ->all();

            if ($items === []) {
                continue;
            }

            $grupos[] = [
                'type' => $tipo,
                // Mismo vocabulario que el catálogo (`admin.catalog.types.*`), en plural: dos
                // nombres para «pack» en el mismo panel sería el problema que esto viene a resolver.
                'label' => __('admin.orders.create_manual.product_group.'.$tipo),
                'items' => $items,
            ];
        }

        return $grupos;
    }

    /**
     * «Más info» de un producto: lo que el CLIENTE ve de él (`#462`, T2, `[owner]`: «un CTA de "más
     * info" abre un modal con la descripción y demás datos públicos, o sea datos de la landing»).
     *
     * ⚠️⚠️ **Es de LECTURA y de datos PÚBLICOS, y eso es lo que la hace segura de enseñar delante de
     * un cliente**: sale de los mismos campos que pinta la web (descripción, condiciones,
     * características, duración, rango de invitados). Ni precios de coste, ni aforo, ni nada que el
     * operador no pueda leer en voz alta.
     *
     * ⚠️ **No decide nada.** Abrirla no elige el producto: se puede consultar y cerrar. Si eligiera,
     * el operador no podría comparar dos productos sin comprometerse con el primero que abre.
     */
    public function productInfoAction(): Action
    {
        return Action::make('productInfo')
            ->modalHeading(fn (array $arguments): string => (string) (TicketType::find($arguments['product'] ?? 0)?->tr('name') ?? ''))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.orders.create_manual.product_info_close'))
            ->schema(fn (array $arguments): array => $this->productInfoFields((int) ($arguments['product'] ?? 0)));
    }

    /**
     * Los campos del modal de «Más info», en el orden en que se cuentan por teléfono.
     *
     * ⚠️ **Público como `productCards()`**: los dos son compositores de vista, y el modal de Filament
     * es un `wire:partial` que `assertSee` **no ve** (la trampa de `#161`, pagada otra vez aquí).
     * Se asevera por CONDUCTA sobre lo que compone, no por el HTML de la página.
     *
     * @return array<int, mixed>
     */
    public function productInfoFields(int $id): array
    {
        $type = TicketType::with('zone')->find($id);

        if ($type === null) {
            return [];
        }

        $filas = [];

        // La ficha seca primero: es lo que el operador necesita para contestar «¿cuánto dura?».
        $ficha = array_filter([
            __('admin.catalog.col_type') => __('admin.catalog.types.'.$type->type),
            __('admin.catalog.col_zone') => $type->zone?->tr('name'),
            __('admin.catalog.col_duration') => $type->duration_min ? __('admin.orders.create_manual.product_minutes', ['n' => $type->duration_min]) : null,
            __('admin.orders.create_manual.product_guests') => $type->isPack()
                ? __('admin.orders.create_manual.product_guest_range', ['min' => $type->contractableMinimum(), 'max' => $type->max_qty ?? '∞'])
                : null,
        ], fn ($v): bool => filled($v));

        foreach ($ficha as $rotulo => $valor) {
            $filas[] = TextEntry::make('info_'.md5((string) $rotulo))
                ->label($rotulo)
                ->state($valor);
        }

        // ⚠️ El rótulo es el MISMO que usa la ficha del catálogo (`admin.catalog.field_description`):
        // el operador acaba de escribir ese campo ahí, y llamarlo de otra forma aquí le haría dudar
        // de si está mirando lo mismo.
        //
        // ⚠️⚠️ **`ticket_types.conditions` NO se enseña, y no es un olvido**: medido, esa columna
        // **no la lee nadie y el catálogo no la edita** —cero consumidores en todo el repo—. Pintarla
        // aquí la convertiría en el único sitio donde aparece un texto que el operador no puede
        // rellenar desde ninguna pantalla. Ficha en `DEUDA.md`: es columna muerta, preexistente.
        if (filled($type->tr('description'))) {
            $filas[] = TextEntry::make('info_description')
                ->label(__('admin.catalog.field_description'))
                ->state($type->tr('description'));
        }

        // ⚠️⚠️ **`features` es TRADUCIBLE y `tr()` es su puente**: un `(array) $type->features` da el
        // mapa de idiomas entero (`{en: [...], es: [...], fr: [...]}`) y recorrerlo a mano sacaba
        // **las tres lenguas juntas** — medido en navegador: «Access to the Jump zone · Acceso a la
        // zona Jump · Accès à la zone Jump». *Inventar un recorrido donde ya hay un puente es cómo
        // se cuela un idioma equivocado sin que nada falle.*
        $features = collect((array) $type->tr('features'))
            ->filter(fn ($f): bool => is_string($f) && filled($f))
            ->all();

        if ($features !== []) {
            $filas[] = TextEntry::make('info_features')
                ->label(__('admin.catalog.field_features'))
                ->state(implode(' · ', $features));
        }

        return $filas;
    }

    /**
     * El rótulo de un producto en UNA línea: «{zona} · {nombre}».
     *
     * ⚠️ **Sobrevive al `Select` que lo estrenó** porque tiene otro consumidor: es la etiqueta con
     * la que la línea aparece en el carrito, donde no hay tarjeta que enseñe la zona aparte.
     */
    private function productLabel(TicketType $type): string
    {
        $zone = $type->zone?->tr('name');

        return ($zone ? "{$zone} · " : '').(string) $type->tr('name');
    }

    /**
     * Elegir producto. **Es la ÚNICA puerta** (`#462`, T2).
     *
     * ⚠️⚠️ **Aquí vivía un `afterStateUpdated` de Filament y ahora es un `wire:click`, y eso cambia
     * DÓNDE se puede escribir**: dentro de un `afterStateUpdated` escribir en `$this->data` a mano
     * se pierde —el formulario vuelve a sincronizar su estado después— y hay que usar el `$set` del
     * campo; desde un `wire:click` es al revés. La regla de `#240` sigue valiendo: **un escritor por
     * puerta**. Con una sola puerta no hay dos escrituras que puedan divergir.
     *
     * ⚠️ **El servidor vuelve a comprobar el producto** (`AFORO-02`): el navegador propone un id y
     * aquí se confirma que sigue siendo vendible y de una zona operativa. Un `wire:click` se puede
     * llamar con cualquier número.
     */
    public function pickProduct(int $id): void
    {
        $ofrecible = TicketType::sellable()
            ->inOperationalZone()
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK])
            ->whereKey($id)
            ->exists();

        if (! $ofrecible) {
            return;
        }

        $this->data['sel_product_id'] = $id;

        // Los mismos olvidos que hacía el `afterStateUpdated`: cambiar de producto invalida la hora,
        // la excepción del mínimo, los campos del evento y los menores asignados.
        $this->data['sel_time'] = null;
        $this->data['sel_below_minimum'] = false;
        $this->data['sel_qty'] = $this->defaultQtyFor($id);
        $this->data['event_data'] = [];
        $this->data['sel_dependent_ids'] = [];
        $this->initManualAddonDefaults();

        $this->advanceAfterChoice();
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
        return TicketType::find($productId)?->contractableMinimum() ?? 1;
    }

    /**
     * `#329` — ¿se le puede OFRECER a este operador bajar del mínimo del pack al crear el pedido?
     *
     * Mismo trío que D7 en la edición (`ViewOrder::productAndQuantityFields()`): tiene que ser un
     * pack, tiene que haber un mínimo que rebajar y el operador tiene que tener el permiso.
     * ⚠️ **Esto solo decide qué se PINTA.** El permiso se re-exige en {@see create()} antes de pasar
     * la excepción al dominio (`SEC-04`: se decide en el punto de ejecución, no en la visibilidad
     * de un formulario que cualquiera puede manipular desde el navegador).
     */
    private function canGoBelowPackMinimum(): bool
    {
        $type = $this->selectedProduct();

        return $type !== null
            && $type->isPack()
            && $type->contractableMinimum() > 1
            && (auth()->user()?->hasPermission('orders.edit_item_below_minimum') ?? false);
    }

    /** ¿El interruptor está puesto Y el operador puede usarlo? (lo que de verdad rebaja el suelo). */
    private function belowMinimumActive(): bool
    {
        return (bool) ($this->data['sel_below_minimum'] ?? false) && $this->canGoBelowPackMinimum();
    }

    private function selectedMinQty(): int
    {
        $type = $this->selectedProduct();

        if ($type === null) {
            return 1;
        }

        // `#329`: con la excepción activa el suelo baja a 1, nunca a 0 — por debajo de 1 no hay
        // reserva que crear. El MÁXIMO no se toca (mismo reparto que D7).
        return $this->belowMinimumActive() ? 1 : $type->contractableMinimum();
    }

    private function selectedMaxQty(): ?int
    {
        $type = $this->selectedProduct();

        return $type && $type->isPack() ? $type->max_qty : null;
    }

    /**
     * Mapa franja → {available, sellable} de la fuente ÚNICA `SlotOffer` (la misma que la web,
     * `AFORO-02`): ventana viva del día, cupo de pack ≥ min_qty y **los ocupantes que la propia
     * cesta de este pedido ya está reteniendo**. Las franjas llenas viajan igual, marcadas
     * `sellable=false` — se enseñan deshabilitadas, no se esconden. Memoizado por petición.
     *
     * ❗❗ **La CESTA entra aquí desde `#464`, y es la única corrección de dominio del asistente.**
     * Hasta hoy el panel llamaba a `offerableTimes()` sin ocupantes provisionales mientras la web sí
     * se los pasaba: dos líneas del mismo pedido sobre la misma franja se ofrecían **como si la
     * primera no existiera**, y el operador veía 40 plazas después de haber metido 20 en la línea
     * anterior. Era un borde declarado (`specs/hora-extra.md` §8.3) hasta que el asistente puso
     * «Añadir más productos» en el camino normal.
     *
     * ⚠️ **La cuenta NO se escribe aquí**: es `CartOccupants::forCart()`, la derivación ÚNICA que
     * comparten el cobro (`OrderCreator`) y la oferta (web y API). Una copia local contaría distinto
     * las hijas que ocupan o las líneas de pack, y ofrecería horas que el propio checkout rechaza.
     *
     * ⚠️ **La línea en curso NO está en `$this->cart`** —entra al pulsar «Añadir al carrito»—, así
     * que no se cuenta a sí misma. Es la misma semántica que en la web.
     *
     * @return array<string, array{available:int, max_quantity:int, sellable:bool}>
     */
    private function timeMap(): array
    {
        // `#329`: el interruptor del mínimo entra en la CLAVE del memo. Sin él, activarlo no
        // recalcularía las horas y el operador seguiría viendo la lista filtrada por el mínimo — el
        // defecto que esta tanda existe para evitar, escondido en una caché.
        // `#464`: y la CESTA también. Es la misma lección: un memo que no ve entrar un dato devuelve
        // el número de antes, y aquí «el número de antes» son plazas que ya no están libres.
        $belowMinimum = $this->belowMinimumActive();
        $key = (string) ($this->data['sel_product_id'] ?? '').'|'.(string) ($this->data['sel_date'] ?? '')
            .'|'.($belowMinimum ? '1' : '0').'|'.$this->cartFingerprint();
        if ($this->timeMapKey === $key && $this->timeMapCache !== null) {
            return $this->timeMapCache;
        }

        $type = $this->selectedProduct();
        $date = $this->data['sel_date'] ?? null;

        if (! $type || ! $type->zone_id || ! $date) {
            $this->timeMapKey = $key;

            return $this->timeMapCache = [];
        }

        $day = Carbon::parse($date)->toDateString();
        $occupants = CartOccupants::forCart($this->cart, (int) $type->zone_id, $day);

        $map = app(SlotOffer::class)->offerableTimes(
            $type,
            $day,
            $occupants['entries'],
            $occupants['packs'],
            CounterSale::byOperator($belowMinimum),
        );

        $this->timeMapKey = $key;
        $this->timeMapCache = $map;

        return $map;
    }

    /**
     * Huella de la cesta para la clave del memo de {@see timeMap()}: lo que cambia las plazas.
     *
     * ⚠️ **Contar líneas no vale**: quitar una y añadir otra deja el mismo número y otra ocupación.
     * Entra lo que `CartOccupants` mira —producto, día, hora, cantidad y complementos— y nada más:
     * el rótulo o los menores asignados no mueven una plaza.
     */
    private function cartFingerprint(): string
    {
        if ($this->cart === []) {
            return '-';
        }

        return md5(json_encode(array_map(static fn (array $line): array => [
            $line['ticket_type_id'] ?? null,
            $line['date'] ?? null,
            $line['time'] ?? null,
            $line['qty'] ?? null,
            $line['addons'] ?? [],
        ], $this->cart), JSON_THROW_ON_ERROR));
    }

    /** Ayuda del selector de hora: avisa si la fecha elegida no tiene franjas ofrecibles. */
    /**
     * Las FRANJAS del día elegido, listas para pintar (`#241`).
     *
     * ⚠️ **`available` y `sellable` salen del MISMO `timeMap()`** que usaba el control anterior: el
     * cupo lo decide `SlotOffer` (`AFORO-02`) y aquí solo se le pone forma. Una franja llena viaja
     * igualmente, marcada como no vendible: **se enseña deshabilitada, no se esconde**, igual que en
     * la web — así el operador ve que esa hora existe y está llena, en vez de que le falte.
     *
     * @return array<int, array{time: string, label: string, seats: int, sellable: bool, selected: bool}>
     */
    public function timeChips(): array
    {
        $selected = (string) ($this->data['sel_time'] ?? '');

        $chips = [];
        foreach ($this->timeMap() as $start => $info) {
            $chips[] = [
                'time' => (string) $start,
                'label' => substr((string) $start, 0, 5),
                'seats' => (int) $info['available'],
                'sellable' => (bool) $info['sellable'],
                'selected' => (string) $start === $selected,
            ];
        }

        return $chips;
    }

    /**
     * Elegir franja desde los chips.
     *
     * ⚠️ **Vuelve a comprobar que la franja sea VENDIBLE**, y no es ceremonia: el control lo pinta el
     * navegador y un `wire:click` se puede llamar con cualquier hora. Es la misma defensa que
     * {@see pickQuickDay()} — el navegador propone, el servidor decide.
     */
    public function pickTime(string $time): void
    {
        if (! ($this->timeMap()[$time]['sellable'] ?? false)) {
            return;
        }

        $this->data['sel_time'] = $time;

        // `#462`, `[DECIDIDO owner]`: «al elegir la hora, siguiente pantalla automáticamente».
        // ⚠️ Va DESPUÉS de la guarda: una franja no vendible no elige nada, así que tampoco avanza
        // —si avanzara, el operador creería haber elegido una hora que el servidor ya rechazó—.
        $this->advanceAfterChoice();
    }

    /**
     * El CALENDARIO del paso «Cuándo», compuesto en el servidor (`#464`, T3).
     *
     * `[owner]`: «la fecha la selecciona de un calendario grande, bien visible, que se vean las
     * plazas disponibles del producto en concreto». Las PLAZAS van con las horas y no por día
     * (`[DECIDIDO owner]` D2): pintarlas por día cuesta **709 consultas y 11,4 s** —`offerableTimes()`
     * resuelve día a día y no existe vía agregada por rango—, así que aquí un día solo dice si se
     * puede reservar o no, que sale de las **5 consultas** que ya cuesta `offerableDates()`.
     *
     * ⚠️ **Los días de otro mes salen VACÍOS, no atenuados.** Un número gris que no es de este mes,
     * al lado de otro número gris que sí lo es pero no se vende, son dos grises que significan cosas
     * distintas — y en una tablet se pulsan igual de mal.
     *
     * @return array{ym: string, label: string, prev: ?string, next: ?string, weekdays: list<string>, weeks: list<list<?array{day: int, date: string, offerable: bool, selected: bool, today: bool}>>}
     */
    public function calendarMonth(): array
    {
        $ofrecibles = array_flip($this->offerableDates());
        $ym = $this->calendarMonthKey();
        $primero = Carbon::createFromFormat('Y-m-d', $ym.'-01')->startOfDay();
        $hoy = DisplayTime::today()->toDateString();
        $elegido = (string) ($this->data['sel_date'] ?? '');

        $cursor = $primero->copy()->startOfWeek(self::WEEK_STARTS_ON);
        $fin = $primero->copy()->endOfMonth()->endOfWeek(self::WEEK_STARTS_ON);

        $semanas = [];
        $semana = [];
        while ($cursor <= $fin) {
            $ymd = $cursor->toDateString();
            $semana[] = $cursor->format('Y-m') === $ym
                ? [
                    'day' => (int) $cursor->day,
                    'date' => $ymd,
                    'offerable' => isset($ofrecibles[$ymd]),
                    'selected' => $ymd === $elegido,
                    'today' => $ymd === $hoy,
                ]
                : null;   // relleno: ni se pinta ni se pulsa

            if (count($semana) === 7) {
                $semanas[] = $semana;
                $semana = [];
            }

            $cursor->addDay();
        }

        $meses = $this->offerableMonths();

        return [
            'ym' => $ym,
            'label' => mb_convert_case($primero->translatedFormat('F Y'), MB_CASE_TITLE),
            // ⚠️ Las flechas saltan al mes OFRECIBLE anterior/siguiente, no al mes de al lado: con un
            // mes entero cerrado por medio, «mes anterior» llevaría a una rejilla apagada y el
            // operador tendría que adivinar cuántas veces pulsar.
            'prev' => $this->neighbourMonth($meses, $ym, before: true),
            'next' => $this->neighbourMonth($meses, $ym, before: false),
            'weekdays' => $this->weekdayLabels(),
            'weeks' => $semanas,
        ];
    }

    /**
     * Mover el calendario de mes.
     *
     * ⚠️⚠️ **Aquí NO se valida, y no es un olvido: `$calMonth` es una propiedad PÚBLICA de Livewire,
     * o sea que el navegador puede escribirla sin pasar por este método.** Una comprobación aquí
     * daría la sensación de defensa y no defendería nada; la que vale está en el LECTOR
     * ({@see calendarMonthKey()}), que descarta cualquier mes sin oferta venga de donde venga.
     *
     * ▶ Y esto no contradice a `pickDay()`, donde la comprobación sí vive en la acción: allí lo que
     * se escribe es `sel_date`, que sale de la página hacia el COBRO, así que el valor tiene que ser
     * bueno en el momento de guardarse. Aquí lo que se escribe solo decide qué rejilla se pinta.
     */
    public function goToMonth(string $mes): void
    {
        $this->calMonth = $mes;
    }

    /**
     * Elegir día. **Es la única puerta** desde que la tira se retiró (`#464`).
     *
     * ⚠️ **El servidor vuelve a comprobar que el día esté en la oferta** (`AFORO-02`): que la rejilla
     * solo pinte días buenos no basta, porque quien decide qué se vende es `SlotOffer` y no el
     * marcado. Un `wire:click` se puede llamar con cualquier fecha.
     */
    public function pickDay(string $ymd): void
    {
        if (! in_array($ymd, $this->offerableDates(), true)) {
            return;
        }

        $this->data['sel_date'] = $ymd;

        // ⚠️ La hora y los menores dependen de la FECHA (`D13`): quedarse con los del día anterior es
        // ofrecer algo que el checkout rechazaría. Desde `#464` hay UNA sola puerta, así que la regla
        // ya no puede divergir entre dos escritores — que era el motivo de `onDateChosen()`.
        $this->data['sel_time'] = null;
        $this->data['sel_dependent_ids'] = [];

        // Las FRANJAS aparecen debajo del calendario y en una tablet de 810 px caen fuera de la
        // ventana: sin esto el operador elige día y no ve pasar nada. Lo escucha el partial de horas.
        $this->dispatch('cmo-day-chosen');
    }

    /**
     * Los meses (`Y-m`) que tienen algún día ofrecible, en orden.
     *
     * Sale de `offerableDates()`, que ya está memoizado: el calendario no abre ninguna consulta
     * nueva sobre aforo.
     *
     * @return list<string>
     */
    private function offerableMonths(): array
    {
        $meses = [];
        foreach ($this->offerableDates() as $ymd) {
            $mes = substr($ymd, 0, 7);
            $meses[$mes] = true;
        }

        return array_keys($meses);
    }

    /**
     * El mes que se está enseñando: el override si lo hay y sigue teniendo oferta; si no, el del día
     * elegido; si no, el del primer día ofrecible; y en último término el mes en curso.
     *
     * ⚠️ **El override se descarta cuando deja de tener oferta** —cambiar de producto o que se agote
     * un mes—: si no, el calendario se quedaría en un mes donde ese producto no se vende y el
     * operador vería una rejilla entera apagada sin saber por qué.
     */
    private function calendarMonthKey(): string
    {
        $meses = $this->offerableMonths();

        if ($this->calMonth !== null && in_array($this->calMonth, $meses, true)) {
            return $this->calMonth;
        }

        $elegido = (string) ($this->data['sel_date'] ?? '');
        if ($elegido !== '') {
            return substr($elegido, 0, 7);
        }

        return $meses[0] ?? DisplayTime::today()->format('Y-m');
    }

    /** El mes ofrecible anterior o siguiente a `$ym`, o `null` si no hay. @param list<string> $meses */
    private function neighbourMonth(array $meses, string $ym, bool $before): ?string
    {
        $vecinos = array_values(array_filter(
            $meses,
            static fn (string $mes): bool => $before ? $mes < $ym : $mes > $ym,
        ));

        if ($vecinos === []) {
            return null;
        }

        return $before ? end($vecinos) : $vecinos[0];
    }

    /**
     * Los rótulos de los días de la semana, empezando en LUNES y **en el idioma del panel**: quien
     * mira esta rejilla es el operador.
     *
     * ⚠️⚠️ **Es la ABREVIATURA del idioma, no su inicial**, y lo dijo una guarda: en español «martes»
     * y «miércoles» empiezan igual, así que una fila de iniciales sale `L M M J V S D` y las dos
     * columnas del medio dejan de distinguirse. Recortar un nombre a mano es inventarse una
     * abreviatura que el idioma ya tiene resuelta —y en otro idioma puede no tener ni sentido—.
     *
     * @return list<string>
     */
    private function weekdayLabels(): array
    {
        $dia = DisplayTime::today()->copy()->startOfWeek(self::WEEK_STARTS_ON);
        $rotulos = [];
        for ($i = 0; $i < 7; $i++) {
            $rotulos[] = mb_convert_case(rtrim($dia->translatedFormat('D'), '.'), MB_CASE_TITLE);
            $dia->addDay();
        }

        return $rotulos;
    }

    private function timeFieldHelp(): string
    {
        if (! empty($this->data['sel_date']) && $this->selectedProduct() && $this->timeMap() === []) {
            return __('admin.orders.create_manual.no_times_for_date');
        }

        // ⚠️ **Sin día elegido el campo de la hora se queda VACÍO**, y eso lo trajo el cambio de
        // control (`#240`): un `<select>` siempre pintaba su «Seleccione una opción», pero unos chips
        // sin opciones no pintan nada y el rótulo queda huérfano. Así que el hueco lo explica el
        // propio texto de ayuda en vez de dejar al operador mirando un espacio en blanco.
        if (empty($this->data['sel_date'])) {
            return __('admin.orders.create_manual.time_pick_date_first');
        }

        return __('admin.orders.create_manual.time_help');
    }

    /**
     * Fechas (`Y-m-d`) con franja ofrecible del producto en curso, vía `SlotOffer`. Memoizado por
     * petición: de aquí salen la rejilla del calendario, sus meses navegables y la defensa de
     * `pickDay()`, y las tres se resuelven en las MISMAS 5 consultas.
     *
     * @return list<string>
     */
    private function offerableDates(): array
    {
        $type = $this->selectedProduct();
        $id = $type?->id ?? 0;
        if ($this->offerableDatesFor === $id && $this->offerableDatesCache !== null) {
            return $this->offerableDatesCache;
        }

        $this->offerableDatesFor = $id;

        // `#330` — el mostrador ve TODOS los días con franja, incluidos los que la antelación
        // mínima del producto reserva al autoservicio. ⚠️ Y esto gobierna el CALENDARIO entero: sin
        // pasar la venta de mostrador aquí, esos días saldrían apagados en la rejilla y el operador
        // podría elegir la hora pero no llegar al día — la mitad de la función, y sin ningún error.
        return $this->offerableDatesCache = $type
            ? app(SlotOffer::class)->offerableDates($type, CounterSale::byOperator())
            : [];
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
                    ->inputMode(TicketType::isNumericFieldType($field['type'] ?? null) ? 'numeric' : 'text');
        }

        return $fields;
    }

    private function estimateLineCents(TicketType $type, string $date, int $qty, array $addons): int
    {
        $rates = app(RateResolver::class);
        // ⚠️⚠️ **`$qty` NO es opcional aquí, y su ausencia era dinero** (`#329`). `priceCents()`
        // admite la cantidad desde `#324` porque con tramos de volumen el precio DEPENDE de ella, y
        // esta llamada la omitía: en una excursión de 70 con la escala 30→15 € / 70→13 €, el
        // operador veía un total y `OrderCreator` —que sí la pasa (`OrderCreator:251`)— cobraba
        // otro. Medido: 140,00 € de diferencia en una sola línea.
        // ▶ Es uno de los SEIS sitios que resuelven el precio de una línea y todos tienen que dar el
        // mismo número (`specs/precio-por-tramo.md`); *un parámetro con valor por defecto no avisa
        // de que hacía falta*.
        $cents = ((int) ($rates->priceCents($type, Carbon::parse($date), $qty) ?? 0)) * $qty;

        // Complementos: MISMO AddonResolver que crea el pedido (incluido / por-invitado / grupo
        // excluyente + auto-inyección de obligatorios) → el total estimado coincide con lo que se
        // cobra. Si la selección es inconsistente, suma 0 y el alta dará el error apropiado.
        if (! $type->isAddon()) {
            $type->loadMissing('addons');
            try {
                $cents += app(AddonResolver::class)->resolve($type, $qty, $addons, Carbon::parse($date))['subtotal'];
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
            // ⚠️ **Esto SÍ cruza a Booking**, a diferencia de `dependent_ids`: es una propiedad de la
            // línea, no una persona de Identity. Si no se copiara aquí, el operador marcaría la
            // casilla y la reserva nacería sin marca — sin que nada fallara.
            'guardian_authorization' => $line['guardian_authorization'] ?? false,
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
            self::STEP_PRODUCT => __('admin.orders.create_manual.step_products'),
            self::STEP_WHEN => __('admin.orders.create_manual.step_when'),
            self::STEP_DETAILS => __('admin.orders.create_manual.step_details'),
            self::STEP_EXTRAS => __('admin.orders.create_manual.step_extras'),
            self::STEP_CART => __('admin.orders.create_manual.step_cart'),
            self::STEP_PAYMENT => __('admin.orders.create_manual.step_payment'),
        ];
    }

    /**
     * Lo elegido en cada paso ya contestado, para que el indicador de arriba no sea solo un número.
     *
     * ⚠️ Es lo que hace SEGURO el auto-avance: si el operador se equivoca de producto, lo ve escrito
     * y vuelve con un toque, en vez de retroceder a ciegas.
     *
     * @return array<int, string|null>
     */
    public function stepChoices(): array
    {
        $type = $this->selectedProduct();
        $date = $this->data['sel_date'] ?? null;
        $time = $this->data['sel_time'] ?? null;
        $qty = (int) ($this->data['sel_qty'] ?? 0);

        $cuando = null;
        if ($date && $time) {
            $cuando = trim(($qty > 0 ? $qty.' · ' : '')
                .Carbon::parse((string) $date)->isoFormat('D MMM').' · '.substr((string) $time, 0, 5));
        }

        return [
            self::STEP_CUSTOMER => $this->currentCustomerLabel(),
            self::STEP_PRODUCT => $type?->tr('name'),
            self::STEP_WHEN => $cuando,
            self::STEP_DETAILS => null,
            self::STEP_EXTRAS => null,
            self::STEP_CART => $this->cart === [] ? null : (string) count($this->cart),
            self::STEP_PAYMENT => null,
        ];
    }

    /** La versión firmable que se enseña en mostrador, o `null` fuera del modo interno / sin versión. */
    public function counterWaiverVersion(): ?LegalDocumentVersion
    {
        // F-06 (`#181`): cuatro closures del modal lo piden por render; se resuelve una vez por petición.
        if (! $this->counterWaiverVersionResolved) {
            $this->counterWaiverVersionMemo = WaiverSettings::isInternal() ? LegalDocuments::current(WaiverSettings::SLUG, 'es') : null;
            $this->counterWaiverVersionResolved = true;
        }

        return $this->counterWaiverVersionMemo;
    }

    /**
     * El texto vigente, escapado, para que el operador lo enseñe antes de declarar la aceptación.
     *
     * ⚠️ Público a propósito: el modal es un `wire:partial` y el arnés de Livewire NO lo renderiza, así
     * que un fallo aquí (`$version->sections` en vez de `sections()`: 500 al abrir el modal, cazado
     * en headless, `#178`) no lo ve ningún `assertSee`. `RegisterCustomerActionTest` lo llama directo.
     */
    public function counterWaiverText(): HtmlString
    {
        $version = $this->counterWaiverVersion();
        if ($version === null) {
            return new HtmlString('');
        }

        $html = '';
        foreach ($version->sections() as $section) {
            $h = trim((string) ($section['h'] ?? ''));
            $p = trim((string) ($section['p'] ?? ''));
            $html .= '<p class="text-sm">'.($h !== '' ? '<strong>'.e($h).'</strong> ' : '').e($p).'</p>';
        }

        return new HtmlString($html);
    }
}
