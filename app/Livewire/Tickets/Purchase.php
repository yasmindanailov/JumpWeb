<?php

namespace App\Livewire\Tickets;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\MaintenanceSettings;
use App\Exceptions\ReservationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\AddonResolver;
use App\Support\Cart;
use App\Support\CatalogSettings;
use App\Support\OrderCreator;
use App\Support\PackAvailability;
use App\Support\PaymentSettings;
use App\Support\ProductAvailability;
use App\Support\RateResolver;
use App\Support\Redsys;
use App\Support\RedsysResponseCode;
use App\Support\ReservationFinancials;
use App\Support\SlotAvailability;
use App\Support\SlotOffer;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Fase 5 (Capa 1, flujo ROLLER) — Compra de entradas en el sidebar. Orden:
 * elegir ENTRADA (catálogo, "desde X€") → fecha → hora + cantidad (topada al aforo,
 * con el precio del día ya visible) → carrito acumulativo de líneas. El precio y el
 * aforo se calculan SIEMPRE en servidor (regla 12 de SEGURIDAD); el carrito vive en
 * sesión (solo IDs y cantidades). Packs y complementos = capas siguientes
 * (ver docs/PLAN-COMPRA-PRODUCTOS.md). La identificación y el pago llegan después.
 */
class Purchase extends Component
{
    /**
     * Límites anti-abuso (auditoría 2026-05-26, hallazgo E).
     *
     * Sin Redsys los pedidos `pending` no caducan: un usuario autenticado podría iterar
     * `confirmReservation` y agotar el aforo del día sin pagar. Lo cerramos con dos topes:
     *  - MAX_LINES_PER_CART: nº de productos distintos por cesta (anti-DoS de contención BD).
     *  - MAX_PENDING_PER_USER: pedidos pending vivos a la vez por usuario.
     *  - 3 confirmReservation/min por usuario (RateLimiter).
     * Hay holgura suficiente para familias con varias compras legítimas, y bloquea el spam.
     */
    public const MAX_LINES_PER_CART = 50;

    public const MAX_PENDING_PER_USER = 5;

    public const RESERVATIONS_PER_MINUTE = 3;

    /**
     * Paso del asistente (el entero es estado interno; no se muestra al cliente). En orden de
     * flujo: 1=entrada · 2=fecha · 3=hora+cantidad · 4=carrito · 5=identificación (login/registro
     * embebido) · 8=pago (paso de pago de Redsys, ANTES de crear la reserva firme) · 9=redirect
     * a Redsys (auto-POST, capa 5.5b) · 6=reserva pagada y confirmada (vuelta OK de Redsys,
     * capa 5.5c) · 7=pendiente de verificar email (reserva provisional, pieza 2, #76) · 10=pago
     * denegado (vuelta KO de Redsys, capa 5.5c: el cliente puede reintentar) · 11=verificando
     * pago (vuelta sin datos firmados; #106: terminal Redsys no incluye datos en redirección,
     * esperamos la notificación on-line para confirmar). Los pasos no van en orden numérico a
     * propósito: así no se renumeran 6/7 ni el flujo de verificación ya validado (#76); 9, 10
     * y 11 se añadieron al integrar Redsys real (#104/#106).
     */
    public int $step = 1;

    /** Entrada/pack elegido en el paso 1. */
    public ?int $typeId = null;

    public ?string $date = null;

    public ?string $time = null;

    /** Cantidad de la entrada elegida (paso 3). */
    public int $qty = 0;

    /** Respuestas de los campos del evento del pack elegido (clave→valor), paso 3 (#86). */
    public array $eventData = [];

    /** Cantidades de complementos elegidas en el paso "Complementos" (addonId→cantidad, #87). */
    public array $addonQty = [];

    /**
     * Elección actual de cada grupo de complementos EXCLUYENTES (Menú 1 ⊻ Menú 2):
     * `claveDeGrupo → addonId elegido`. El default es el incluido (gratis); el cliente puede
     * cambiarlo por el de pago. Solo uno por grupo a la vez (comportamiento radio).
     *
     * @var array<string, int>
     */
    public array $addonGroupChoice = [];

    /** Mes mostrado en el calendario (Y-m). */
    public string $month = '';

    /**
     * Carrito de líneas (persistido en sesión). Cada línea:
     * ['ticket_type_id' => int, 'date' => 'Y-m-d', 'time' => 'H:i:s', 'qty' => int].
     *
     * @var array<int, array{ticket_type_id:int, date:string, time:string, qty:int}>
     */
    // Auditoría Fase 1 (L6): el cap de líneas por cesta es un INVARIANTE de SERVIDOR, reaplicado en
    // `OrderCreator::createPendingOrder` (regla 12) — `addToCart` ya lo comprueba en la UI, pero una
    // cesta forjada con más líneas se rechaza igualmente al crear el pedido. (Se evaluó `#[Locked]`
    // pero la cesta es client-syncable por diseño y OrderCreator ya re-valida cada línea en servidor.)
    public array $cart = [];

    /** Carrito listo para identificarse y pagar (placeholder hasta el pago). */
    public bool $confirmed = false;

    /** Código del pedido creado (paso 6, confirmación de la reserva). */
    public ?string $orderCode = null;

    /**
     * Payload del formulario auto-POST a la pasarela Redsys (paso 9, ida 5.5b).
     *
     * Se rellena en `confirmReservation()` con los datos firmados por el servidor:
     *   ['gatewayUrl' => string, 'signatureVersion' => string, 'params' => string, 'signature' => string].
     * La vista lo renderiza como `<form action="…" method="POST" target="_top">` con auto-submit.
     * Importes y `gateway_order` se calculan en servidor (regla 12 SEGURIDAD); cualquier
     * manipulación cliente invalidaría la firma y Redsys rechazaría la operación (SIS0042).
     *
     * @var array{gatewayUrl?: string, signatureVersion?: string, params?: string, signature?: string}
     */
    public array $redsysFormData = [];

    /** Pestaña activa del paso de identificación: 'login' | 'register'. */
    public string $authMode = 'login';

    /**
     * Motivo traducido del último pago denegado para mostrar en el paso 10 (#114 G7).
     * Se calcula en `mount()` a partir del `Ds_Response` guardado en `Payment.raw_response`
     * del último Payment failed del usuario. `null` si no hay rama KO en curso.
     */
    public ?string $declinedReasonText = null;

    private RateResolver $rates;

    private SlotAvailability $availability;

    private ProductAvailability $productWindow;

    private PackAvailability $packAvailability;

    private AddonResolver $addonResolver;

    private SlotOffer $slotOffer;

    /** Memo por petición de los complementos del producto elegido (evita N consultas por render). */
    private ?Collection $selectedAddonsMemo = null;

    private ?int $selectedAddonsMemoFor = null;

    public function boot(RateResolver $rates, SlotAvailability $availability, ProductAvailability $productWindow, PackAvailability $packAvailability, AddonResolver $addonResolver, SlotOffer $slotOffer): void
    {
        $this->rates = $rates;
        $this->availability = $availability;
        $this->productWindow = $productWindow;
        $this->packAvailability = $packAvailability;
        $this->addonResolver = $addonResolver;
        $this->slotOffer = $slotOffer;
    }

    public function mount(): void
    {
        // Saneamos la cesta de sesión: descarta líneas con formato incompatible (p. ej. de una
        // versión anterior del flujo) en lugar de romper. El precio/total se recalcula en servidor.
        $stored = session('purchase.cart', []);
        $this->cart = Cart::sanitize(is_array($stored) ? $stored : []);
        if ($this->cart !== $stored) {
            $this->persistCart();
        }

        // P8: poda las líneas cuyo producto ya no es vendible/operativo (tras desactivar «En venta
        // online» o su zona). Sin esto quedaban "fantasma": contaban en el badge pero no se
        // renderizaban (sin botón de quitar) ni sumaban (0 €), y rompían el checkout con «:product = —».
        $this->pruneUnsellableLines();

        // El asistente arranca eligiendo la entrada; el mes inicial es el del primer día con franjas.
        $dates = $this->availableDates();
        $this->month = $dates ? Carbon::parse($dates[0])->format('Y-m') : Carbon::today()->format('Y-m');
        // Si la sesión ya tiene una cesta válida, ábrela en el carrito.
        if ($this->cart !== []) {
            $this->step = 4;
        }

        // Vuelta desde el enlace de verificación (Pieza 2, #76) o desde Redsys OK (capa 5.5c,
        // #104). En ambos casos la reserva queda confirmada: mismo paso 6 + feedback.
        if ($code = session('purchase.confirmed_code')) {
            session()->forget('purchase.confirmed_code');
            $this->orderCode = $code;
            $this->confirmed = true;
            $this->step = 6;
        }

        // Vuelta KO de Redsys (capa 5.5c, #104): pago denegado por el banco. La Order queda
        // pending y caducará por `orders:expire` cuando cruce su `expires_at` (#105), liberando
        // el aforo. El cliente puede reintentar. Paso 10 muestra la pantalla de error.
        if ($failedCode = session('purchase.failed_code')) {
            session()->forget('purchase.failed_code');
            $this->orderCode = $failedCode;
            $this->step = 10;

            // Audit #114 G7: extraer el `Ds_Response` del Payment failed más reciente del
            // user para mostrarlo traducido al cliente ("tarjeta caducada", "CVV erróneo"…).
            // El campo `Payment.raw_response` está filtrado por allowlist (#113 M1).
            $this->declinedReasonText = $this->resolveDeclinedReason($user = auth()->user(), $failedCode);
        }

        // Vuelta SIN datos firmados de Redsys (capa 5.5c, #106): terminal sandbox no incluye
        // los `Ds_*` en la redirección. No podemos confirmar el pago en este lado; mostramos
        // "verificando" al cliente y dependemos de la notificación on-line (5.5d) o del propio
        // email que enviará al usuario cuando se confirme.
        if ($verifyingCode = session('purchase.verifying_code')) {
            session()->forget('purchase.verifying_code');
            $this->orderCode = $verifyingCode;
            $this->step = 11;
        }
    }

    /** Mientras el componente carga en diferido (lazy) se muestra el spinner. Ver docs/UI-SPINNER.md. */
    public function placeholder()
    {
        return view('livewire.tickets.purchase-placeholder');
    }

    /**
     * El CTA de la página de cumpleaños lleva el sidebar al catálogo (paso 1) y pide a la vista
     * que abra y haga scroll a la sección «Servicios». El catálogo muestra TODOS los tipos
     * (entradas + servicios) a la vez; ya no hay «modo» que conmutar. El evento de navegador
     * `catalog-open-services` lo emite Livewire DESPUÉS del render → la sección ya está en el DOM,
     * así que funciona tanto si el sidebar ya estaba en el paso 1 como si venía de otro paso.
     */
    #[On('show-packs')]
    public function showPacks(): void
    {
        if ($this->step !== 1) {
            $this->clearSelection();
            $this->month = $this->minMonth();
            $this->step = 1;
        }
        $this->dispatch('catalog-open-services');
    }

    /**
     * El CTA «Comprar» de una atracción de pago (#228) lleva el sidebar al catálogo (paso 1) y pide
     * a la vista que abra «Entradas» y haga scroll a la ZONA de esa atracción (donde está su entrada
     * y, dentro de ella, el complemento). Mismo patrón que `showPacks`: el evento de navegador
     * `catalog-open-entries-zone` lo emite Livewire tras el render → la sección ya está en el DOM.
     */
    #[On('show-entradas-zone')]
    public function showEntradasZone(string $slug = ''): void
    {
        if ($this->step !== 1) {
            $this->clearSelection();
            $this->month = $this->minMonth();
            $this->step = 1;
        }
        $this->dispatch('catalog-open-entries-zone', slug: $slug);
    }

    /** Paso 1 → elige la entrada/pack y pasa a la fecha. Solo productos de la pestaña activa. */
    public function selectType(int $ticketTypeId): void
    {
        if (! $this->catalogTypes()->contains('id', $ticketTypeId)) {
            return;
        }
        $this->typeId = $ticketTypeId;
        $this->date = null;
        $this->time = null;
        $this->qty = 0;
        $this->eventData = [];
        $this->initAddonDefaults();
        $this->month = $this->minMonth();
        $this->step = 2;
    }

    /** Vuelve al paso anterior (el carrito usa "añadir otra", no este botón). */
    public function back(): void
    {
        if ($this->step === 3) {
            $this->time = null;
            $this->qty = 0;
            $this->step = 2;
        } elseif ($this->step === 2) {
            $this->step = 1;
        }
    }

    /**
     * Elige el día. Sidebar v2: NO auto-avanza — el usuario revisa y pulsa «Continuar» en el footer
     * (`goToTime`). Da control y hace que el sticky footer tenga su CTA en cada paso.
     */
    public function selectDate(string $date): void
    {
        if (! in_array($date, $this->availableDates(), true)) {
            return;
        }
        $this->date = $date;
        $this->month = Carbon::parse($date)->format('Y-m');
        $this->time = null;
        $this->qty = 0;
    }

    /** «Continuar» del paso de fecha → paso de hora. Requiere un día elegido (server-guard del CTA). */
    public function goToTime(): void
    {
        if ($this->step === 2 && $this->date !== null && in_array($this->date, $this->availableDates(), true)) {
            $this->step = 3;
        }
    }

    public function prevMonth(): void
    {
        $target = Carbon::parse($this->month.'-01')->subMonthNoOverflow()->format('Y-m');
        if ($target >= $this->minMonth()) {
            $this->month = $target;
        }
    }

    public function nextMonth(): void
    {
        $target = Carbon::parse($this->month.'-01')->addMonthNoOverflow()->format('Y-m');
        if ($target <= $this->maxMonth()) {
            $this->month = $target;
        }
    }

    /** Paso 3 → elige la hora; arranca la cantidad en el mínimo (1 para entradas; min_qty para packs) si hay aforo. */
    public function selectTime(string $time): void
    {
        if (! in_array($time, $this->availableTimes(), true)) {
            return;
        }
        $this->time = $time;
        $min = $this->minSelectableQty();
        $this->qty = $this->maxQty() >= $min ? $min : 0;
        $this->resetErrorBag('selection');
    }

    public function inc(): void
    {
        if ($this->qty < $this->maxQty()) {
            $this->qty++;
        }
    }

    public function dec(): void
    {
        // En packs no se baja del mínimo de invitados (min_qty); en entradas, hasta 0.
        $floor = $this->selectedType()?->isPack() ? $this->minSelectableQty() : 0;
        if ($this->qty > $floor) {
            $this->qty--;
        }
    }

    /** Añade la entrada/pack elegido (con su fecha/hora/cantidad) al carrito y va al carrito. */
    public function addToCart(): void
    {
        // Validar-al-pulsar (#UX): el CTA no se deshabilita por campos; aquí damos feedback claro de
        // lo que falta. Limpiamos errores previos para que un reintento re-valide de cero.
        $this->resetErrorBag();

        $type = $this->selectedType();
        $max = $this->maxQty();
        $min = $this->minSelectableQty();
        if (! $type || $this->qty < $min || $max < $min) {
            $this->addError('selection', __('tickets.errors.choose_one'));

            return;
        }

        // Cap de líneas (auditoría 2026-05-26, hallazgo L): un atacante podría inflar la cesta a
        // miles de líneas para hacer DoS de contención sobre los locks de slots en `OrderCreator`.
        // El tope es generoso (50 productos distintos) — bloquea solo el abuso, no flujos reales.
        if (count($this->cart) >= self::MAX_LINES_PER_CART) {
            $this->addError('selection', __('tickets.errors.cart_too_large'));

            return;
        }

        // Datos del evento del pack (#86): los campos OBLIGATORIOS de la fase de reserva deben venir
        // rellenos. Los campos `postform` (#217) se piden después, en el formulario por-niño.
        // Feedback ESPECÍFICO (#UX): error por-campo (resalta cada input que falta) + un resumen que
        // los NOMBRA, para que el usuario sepa exactamente qué corregir.
        if ($type->isPack()) {
            $missing = $type->missingRequiredEventFields($this->eventData, TicketType::EVENT_STAGE_BOOKING);
            if ($missing !== []) {
                $fields = collect($type->eventFields(TicketType::EVENT_STAGE_BOOKING))->keyBy('key');
                $labels = [];
                foreach ($missing as $key) {
                    $this->addError("eventData.{$key}", __('tickets.errors.field_required'));
                    if ($field = $fields->get($key)) {
                        $labels[] = $type->eventFieldLabel($field);
                    }
                }
                $this->addError('selection', __('tickets.errors.fields_missing', ['fields' => implode(', ', $labels)]));

                return;
            }
        }

        $this->qty = min($this->qty, $max); // re-tope en servidor (anti-manipulación)
        $this->resetErrorBag('selection');

        // Complementos elegidos para este producto, anidados en su línea (#87).
        $addons = $this->buildSelectedAddons();

        // Se fusionan SOLO entradas sin complementos (los packs y los bundles con complementos
        // nunca se fusionan: cada uno es su propio bloque).
        $index = (! $type->isPack() && $addons === [])
            ? $this->findCartIndex($this->typeId, $this->date, $this->time)
            : null;
        if ($index !== null) {
            $this->cart[$index]['qty'] += $this->qty;
        } else {
            $this->cart[] = [
                'ticket_type_id' => $this->typeId,
                'date' => $this->date,
                'time' => $this->time,
                'qty' => $this->qty,
                'event_data' => $type->isPack() ? $type->sanitizeEventData($this->eventData, TicketType::EVENT_STAGE_BOOKING) : [],
                'addons' => $addons,
            ];
        }

        $this->persistCart();
        $this->clearSelection();
        $this->step = 4;
    }

    /**
     * Empieza otra compra (vuelve al catálogo). Limpia el contexto del pedido anterior
     * (orderCode, redsysFormData, declinedReasonText) para que la pantalla del paso 1
     * arranque limpia (#114 audit edge cases). Sin esto, datos del flujo previo quedaban
     * latentes (no visibles en paso 1 pero higiénicos de limpiar).
     */
    public function addAnother(): void
    {
        $this->clearSelection();
        $this->orderCode = null;
        $this->redsysFormData = [];
        $this->declinedReasonText = null;
        $this->step = 1;
    }

    /** Quita una línea del carrito. Si queda vacío, vuelve al catálogo. */
    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->persistCart();

        if ($this->cart === []) {
            $this->step = 1;
        }
    }

    /** Vuelve al carrito desde una compra en curso (si hay cesta). */
    public function goToCart(): void
    {
        if ($this->cart !== []) {
            $this->step = 4;
        }
    }

    /**
     * Sube la cantidad de un complemento de cantidad LIBRE (la tarta y similares). No aplica a
     * los `per_guest` (la cantidad la marca el aforo del pack), a los de grupo excluyente (se
     * eligen, no se incrementan) ni a los incluidos sin extras permitidos.
     */
    public function incAddon(int $addonId): void
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
        $this->addonQty[$addonId] = min(($this->addonQty[$addonId] ?? 0) + 1, $cap);
    }

    /**
     * Baja la cantidad de un complemento de cantidad libre, sin bajar nunca de su MÍNIMO (lo
     * incluido si es obligatorio). No aplica a `per_guest` ni a los de grupo.
     */
    public function decAddon(int $addonId): void
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
        $this->addonQty[$addonId] = max(($this->addonQty[$addonId] ?? 0) - 1, $floor);
    }

    /**
     * Activa/desactiva un complemento PER-INVITADO (su cantidad es automática = nº de invitados,
     * así que la única decisión del cliente es sí/no → checkbox, no contador). No aplica a los de
     * grupo (radio), ni a los obligatorios/incluidos (siempre activos). Sin esto, un complemento
     * per-invitado OPCIONAL de pago no tendría ningún control y sería imposible de seleccionar.
     */
    public function toggleAddon(int $addonId): void
    {
        $addon = $this->selectedProductAddons()->firstWhere('id', $addonId);
        if (! $addon) {
            return;
        }
        $pivot = $addon->pivot;
        if (! $pivot->isPerGuest() || $pivot->choiceGroup() !== null || $pivot->is_mandatory || $pivot->is_included) {
            return;
        }
        // qtyMap > 0 = activo; `AddonResolver` fija la cantidad efectiva al nº de invitados.
        $this->addonQty[$addonId] = ((int) ($this->addonQty[$addonId] ?? 0)) > 0 ? 0 : 1;
    }

    /**
     * Elige una opción de un grupo EXCLUYENTE (Menú 1 ⊻ Menú 2): selecciona ese complemento y
     * deselecciona los demás del grupo (comportamiento radio). Solo acepta miembros reales del
     * grupo en el producto seleccionado (regla 12).
     */
    public function selectAddonOption(string $group, int $addonId): void
    {
        $member = $this->selectedProductAddons()->first(
            fn (TicketType $a) => (int) $a->id === $addonId && $a->pivot->choiceGroup() === $group
        );
        if ($member !== null) {
            $this->addonGroupChoice[$group] = $addonId;
        }
    }

    /**
     * Inicializa la selección por defecto de los complementos del producto elegido: el default de
     * cada grupo excluyente (el incluido, o el primero por orden) y los obligatorios sueltos a su
     * cantidad mínima. Se llama al elegir el producto (`selectType`).
     */
    private function initAddonDefaults(): void
    {
        $sel = AddonResolver::defaultSelection($this->selectedProductAddons());
        $this->addonQty = $sel['qty'];
        $this->addonGroupChoice = $sel['groups'];
    }

    /**
     * Complementos (type=addon) aplicables al PRODUCTO seleccionado en el paso 3 (#87, pivote
     * `product_addons`), con la config del pivote y los precios cargados. Vacío si no hay producto
     * seleccionado o no tiene complementos. Memoizado por petición (lo consultan render + carrito).
     *
     * @return Collection<int, TicketType>
     */
    private function selectedProductAddons(): Collection
    {
        $type = $this->selectedType();
        if (! $type || $type->isAddon()) {
            return collect();
        }
        if ($this->selectedAddonsMemoFor !== (int) $type->id || $this->selectedAddonsMemo === null) {
            $this->selectedAddonsMemo = $type->addons()->with('prices.rateType')->get();
            $this->selectedAddonsMemoFor = (int) $type->id;
        }

        return $this->selectedAddonsMemo;
    }

    /**
     * Selección de complementos para anidar en la línea del producto. Envía la INTENCIÓN del
     * cliente (el miembro elegido de cada grupo, los obligatorios y los opcionales con qty>0);
     * `AddonResolver` la re-valida y la completa en servidor (regla 12). Para `per_guest` envía la
     * cantidad = invitados, que el servidor recalcula igualmente.
     *
     * @return array<int, array{ticket_type_id:int, qty:int}>
     */
    private function buildSelectedAddons(): array
    {
        return AddonResolver::buildSelection(
            $this->selectedProductAddons(),
            $this->addonQty,
            $this->addonGroupChoice,
            max(0, (int) $this->qty),
        );
    }

    /**
     * Modelo de vista de los complementos del paso 3 (grupos excluyentes + sueltos, con badges
     * INCLUIDO/GRATIS, cantidad, gratis, importe y features para "Más info"). Delega en
     * `AddonResolver::viewModel` — fuente ÚNICA compartida con el alta manual del panel.
     *
     * @return array{groups: array<int, array<string, mixed>>, singles: array<int, array<string, mixed>>, total: int}
     */
    private function addonViewModel(): array
    {
        return $this->addonResolver->viewModel(
            $this->selectedProductAddons(),
            $this->addonQty,
            $this->addonGroupChoice,
            max(0, (int) $this->qty),
            $this->selectedType()?->isPack() ?? false,
            Carbon::today(),
        );
    }

    /**
     * "Continuar" desde el carrito. Exige usuario identificado (#10): si es invitado, abre el paso
     * de identificación (login/registro embebido, #69); si ya está identificado, intenta cerrar la
     * reserva. El candado real es estar VERIFICADO (la clienta lo pidió para la compra).
     */
    public function checkout(): void
    {
        if ($this->cart === []) {
            $this->addError('cart', __('tickets.errors.cart_empty'));

            return;
        }

        if (! auth()->check()) {
            $this->resetErrorBag('cart');
            $this->authMode = 'login';
            $this->step = 5;

            return;
        }

        $this->proceed();
    }

    /** Conmuta entre iniciar sesión y crear cuenta en el paso de identificación. */
    public function setAuthMode(string $mode): void
    {
        $this->authMode = $mode === 'register' ? 'register' : 'login';
    }

    /**
     * Tras identificarse en el sidebar (evento del componente de login embebido), continúa la
     * reserva. Solo actúa si estamos en el paso de identificación.
     */
    #[On('logged-in')]
    public function onAuthenticated(): void
    {
        if ($this->step === 5) {
            $this->proceed();
        }
    }

    /**
     * El registro embebido (#69) avisa de que se envió el correo. Para mantener la anti-enumeración
     * (#46) NO mostramos aquí el código: si la cuenta es nueva, su reserva provisional se ha creado
     * en segundo plano (el cliente la verá al verificar). Mensaje genérico "revisa tu correo".
     */
    #[On('registration-submitted')]
    public function onRegistrationSubmitted(): void
    {
        if ($this->step === 5) {
            $this->orderCode = null;
            $this->step = 7;
        }
    }

    /**
     * Escape "¿ya tienes cuenta? Inicia sesión" desde la pantalla `sent` del Register embebido
     * (decisión #112, 2026-05-28). El usuario que se quedó colgado en "verifica tu correo"
     * (porque su email ya tenía cuenta, #46 anti-enumeración) o que simplemente cambió de
     * idea, vuelve al paso 5 con la pestaña de login activa. La cesta sigue intacta en
     * sesión, así que al loguearse continúa el flujo de compra sin perder nada.
     */
    #[On('purchase:switch-to-login')]
    public function onSwitchToLoginTab(): void
    {
        $this->authMode = 'login';
        $this->step = 5;
    }

    /**
     * Audit #114 G10 — Polling del paso 11 ("verificando tu pago"). El cliente vuelve a la
     * web sin datos firmados de Redsys (#106 fallback data-less); esperamos a la
     * notificación on-line para confirmar. Sin polling, el cliente tiene que recargar la
     * página manualmente. Con `wire:poll.5s="checkPaymentStatus"`:
     *  - Si la notificación llegó y la Order está `paid` → step 6 (confeti + resumen).
     *  - Si la Order ya está `expired` (caducó antes de llegar la notif) → step 1 +
     *    mensaje claro; la pantalla 11 nunca queda colgada para siempre.
     *  - Si sigue `pending` → no hace nada; el polling continúa.
     *
     * Defensa: solo actúa si el `orderCode` en sesión coincide con un Order del user
     * actual (anti-IDOR). El método NO crea estado nuevo.
     */
    public function checkPaymentStatus(): void
    {
        if ($this->step !== 11 || ! $this->orderCode) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $order = $user->orders()->where('code', $this->orderCode)->first();
        if (! $order) {
            return; // Defensa: el código no corresponde a este user, no hacer nada.
        }

        if ($order->status === Order::STATUS_PAID) {
            // La notificación llegó y procesó el pago: paso 6 con todos los detalles.
            $this->confirmed = true;
            $this->step = 6;

            return;
        }

        if ($order->status === Order::STATUS_EXPIRED) {
            // Caducó antes de llegar la notificación → el cliente recibirá email
            // `OrderExpiredWithoutPayment` por separado (G2). En la UI cerramos la pantalla
            // 11: volver al paso 1 con mensaje (la pantalla en "verificando" indefinido sería
            // engañosa).
            $this->orderCode = null;
            $this->step = 1;
            $this->addError('cart', __('tickets.errors.retry_expired'));
        }
    }

    /**
     * Tras identificarse, decide el siguiente paso (todavía NO crea la reserva firme):
     * - Si el usuario NO ha verificado su email, crea una reserva PROVISIONAL que retiene su plaza
     *   y le pide verificar para confirmarla (#76; reusa la retención de #62 + `orders:expire`).
     * - Si está verificado, pasa al paso de PAGO (placeholder de Redsys); la reserva firme se crea
     *   al pulsar "Confirmar reserva" (confirmReservation()).
     */
    /**
     * Aplica los topes anti-abuso a una intención de crear pedido (auditoría 2026-05-26, hallazgo E).
     * Si supera los límites, re-encamina al paso 4 con un error de carro y devuelve false (el
     * llamador debe abortar). Si los respeta, registra el intento en el rate limiter y devuelve true.
     *
     * Combina dos topes:
     *  - MAX_PENDING_PER_USER (volumen): cuántas reservas pending vivas tiene el usuario.
     *  - RESERVATIONS_PER_MINUTE (frecuencia): rate limit por user_id.
     */
    /**
     * Guard de RESERVAS EN PAUSA (#218, item 3). Si el panel pausó las reservas online, ninguna
     * superficie pública debe crear pedidos ni iniciar pagos. Los CTAs ya caen al teléfono; esto es
     * la red de SERVIDOR para los casos en que el sidecart ya estaba abierto, un deep-link, o una
     * manipulación por consola. Registra un aviso (con el teléfono) y devuelve true → el llamador
     * aborta y decide a qué paso vuelve. El **pedido manual del panel** (`OrderCreator` directo) y
     * las **callbacks de Redsys** NO pasan por aquí, así que un pago ya iniciado finaliza igual.
     */
    private function blockedByReservationPause(): bool
    {
        if (! MaintenanceSettings::reservationsPaused()) {
            return false;
        }

        // Sin teléfono configurado, el mensaje NO interpola un `:phone` vacío (gramática rota
        // «Llámanos al  para reservar»): cae a una variante que invita a Contacto (igual criterio
        // que el componente `reserve-cta`, que sin teléfono enlaza a /contacto).
        $phone = trim((string) Setting::value('contact.phone', ''));
        $this->addError('cart', $phone !== ''
            ? __('tickets.errors.reservations_paused', ['phone' => $phone])
            : __('tickets.errors.reservations_paused_no_phone'));

        return true;
    }

    /**
     * Reservas en pausa (#218, item 3): pasos de RESERVA en los que el sidecart muestra el aviso de
     * mantenimiento (con tel/WhatsApp) en lugar del flujo de compra. Los pasos de RESULTADO de pago
     * (6 confirmado / 9 redirigiendo / 10 fallido / 11 verificando) y la verificación de email (7)
     * siguen rindiendo normales — son acciones YA iniciadas que deben poder completarse.
     */
    public function showPausedNotice(): bool
    {
        return MaintenanceSettings::reservationsPaused()
            && in_array($this->step, [1, 2, 3, 4, 5, 8], true);
    }

    /**
     * Canales de contacto para el aviso de mantenimiento del sidecart (#218). `phone_tel` sin
     * espacios para `tel:`; `whatsapp` en dígitos para `https://wa.me/{n}`. Cadenas vacías si el
     * dato no está configurado (la vista oculta el CTA correspondiente).
     *
     * @return array{phone: string, phone_tel: string, whatsapp: string}
     */
    public function pausedContact(): array
    {
        $phone = trim((string) Setting::value('contact.phone', ''));

        return [
            'phone' => $phone,
            'phone_tel' => preg_replace('/\s+/', '', $phone) ?? '',
            'whatsapp' => preg_replace('/\D/', '', (string) Setting::value('contact.whatsapp', '')) ?? '',
        ];
    }

    /**
     * Texto del aviso de pausa del sidecart (#218). Override editable del panel
     * (Mantenimiento → Reservas online → mensaje por idioma) con fallback al texto i18n por defecto.
     */
    public function pausedMessage(): string
    {
        return MaintenanceSettings::reservationMessage();
    }

    /**
     * Título del aviso de pausa del sidecart (#218). Override editable del panel
     * (Mantenimiento → Reservas online → título por idioma) con fallback al texto i18n por defecto.
     */
    public function pausedTitle(): string
    {
        return MaintenanceSettings::reservationTitle();
    }

    private function withinReservationLimits(User $user): bool
    {
        $livePending = $user->orders()
            ->where('status', Order::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
        if ($livePending >= self::MAX_PENDING_PER_USER) {
            $this->step = 4;
            $this->addError('cart', __('tickets.errors.too_many_pending', ['max' => self::MAX_PENDING_PER_USER]));

            return false;
        }

        $rateKey = 'reservation-confirm:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateKey, self::RESERVATIONS_PER_MINUTE)) {
            $this->step = 4;
            $this->addError('cart', __('tickets.errors.try_later'));

            return false;
        }
        RateLimiter::hit($rateKey, 60);

        return true;
    }

    private function proceed(): void
    {
        $user = auth()->user();
        if (! $user) {
            $this->step = 5;

            return;
        }

        // Reservas en pausa (#218): no se crea ningún pedido público. Vuelve al carrito con el aviso.
        if ($this->blockedByReservationPause()) {
            $this->step = 4;

            return;
        }

        // Topes anti-abuso (auditoría 2026-05-26, hallazgo E): el pedido `pending` se crea en
        // confirmReservation() y retiene aforo; el tope evita que un usuario agote el día sin pagar.
        if (! $this->withinReservationLimits($user)) {
            return;
        }

        // Pay-first (decisión clienta 2026-06-14): NO se exige email verificado para pagar. Cualquier
        // usuario identificado pasa al paso de PAGO; el email se verifica solo (auto-verify) al
        // completar el pago en Redsys —un bot no paga— (ver RedsysReturnHandler). La verificación por
        // correo queda como vía alternativa para acceder a «mi cuenta» sin comprar (reenvío desde la
        // página de aviso). Antes, la rama «sin verificar» creaba una reserva PROVISIONAL que al
        // verificar quedaba «firme pero sin pagar» — estado incoherente heredado de la Capa 4
        // pre-Redsys (DECISIONES #76/#78).
        $this->resetErrorBag('cart');
        $this->step = 8;
    }

    /**
     * Paso de PAGO → "Confirmar reserva". Crea la reserva FIRME (pedido `pending`, retiene plaza
     * y no caduca) para el usuario verificado, prepara el cobro Redsys (capa 5.5b, #104), firma el
     * payload server-side y avanza al paso 9 ("Redirigiendo a Redsys"). La vista renderiza un
     * formulario auto-POST a `https://sis-t.redsys.es:25443/sis/realizarPago` (sandbox) — la
     * tarjeta NO toca nuestro servidor. La vuelta firmada (UrlOK/UrlKO) se procesa en 5.5c;
     * el email de confirmación se envía SOLO al recibir `Ds_Response` 0000–0099 (no aquí: hasta
     * que Redsys autoriza, no hay "reserva confirmada al cliente").
     *
     * Seguridad (regla 5–6 de SEGURIDAD; cf. docs/PLAN-REDSYS.md §6/§8):
     *  - importes, `gateway_order` y URLs los CALCULA el servidor; el cliente no participa.
     *  - cualquier manipulación del formulario invalida la firma → Redsys rechaza (SIS0042).
     *  - el `Payment` se crea ANTES de redirigir → el vuelta puede reconciliar sin sesión.
     */
    public function confirmReservation(OrderCreator $creator, Redsys $redsys): void
    {
        if ($this->step !== 8) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            // Pay-first (decisión clienta 2026-06-14): NO se exige email verificado para pagar — el
            // pago auto-verifica (RedsysReturnHandler). Solo exigimos sesión: si se perdió entre pasos
            // (p. ej. cambio de pestaña), re-encaminamos a identificarse sin crear nada. ANTES esto
            // también bloqueaba a los NO verificados → rebotaban al carrito al pulsar pagar (bug del
            // pay-first incompleto: el candado se quitó en proceed() pero no aquí).
            $this->step = 5;

            return;
        }

        // Reservas en pausa (#218): no se crea la reserva firme ni se inicia el cobro Redsys.
        if ($this->blockedByReservationPause()) {
            $this->step = 4;

            return;
        }

        if (! $this->withinReservationLimits($user)) {
            return;
        }

        try {
            // El pedido nace con su ventana de retención (`sales.hold_minutes`) YA fijada (auditoría
            // Fase 1, L7): si el proceso se interrumpe entre crear el pedido e iniciar el pago, queda
            // auto-liberable por `orders:expire`. Antes el hold se aplicaba en un `save()` posterior →
            // un crash en medio dejaba un pending con `expires_at=null` que retenía aforo para siempre.
            $order = $creator->createPendingOrder($user, $this->cart, now()->addMinutes(PaymentSettings::holdMinutes()));
        } catch (ReservationException $e) {
            $this->step = 4;
            $this->addError('cart', __($e->getMessage(), $e->context));

            return;
        }

        // Reserva del `gateway_order` (atómico, único de por vida) y creación del `Payment`
        // `pending` que ata `gateway_order ↔ Order` ANTES de redirigir. La vuelta de Redsys
        // viene sin sesión válida (POST cross-site, SameSite=Lax): este link en BD es la
        // única forma robusta de identificar el pedido al recibir la respuesta. Si esto
        // fallara, deshacemos el pedido para no dejar aforo retenido sin un pago asociado.
        try {
            $payment = DB::transaction(function () use ($order, $redsys): Payment {
                $gatewayOrder = $redsys->nextGatewayOrder();

                return Payment::create([
                    'payable_type' => (new Order)->getMorphClass(),
                    'payable_id' => $order->id,
                    'provider' => 'redsys',
                    'amount' => $order->onlineDueCents(), // #225: importe ONLINE (señal/depósito), no el total
                    'currency' => $order->currency,
                    'status' => Payment::STATUS_PENDING,
                    'gateway_order' => $gatewayOrder,
                ]);
            });

            $locale = $user->locale ?? app()->getLocale();
            $formData = $redsys->buildPaymentFormData($order, $payment, $locale);
        } catch (\Throwable $e) {
            // Observabilidad (auditoría 2026-05-26, hallazgo en validación de 5.5b): el catch
            // anterior tragaba la excepción sin logging → diagnóstico imposible en producción.
            // Loguear con contexto rico (NO el secret_key ni la firma; sí la traza para depurar).
            Log::error('redsys.ida_failed', [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            // Feedback ADMIN (#169): rastro estructurado y visible en el panel
            // (historial del pedido, 7.2d). Sin esto, un fallo de inicio de pago
            // solo vivía en `laravel.log`, invisible para la operadora — el
            // pedido se auto-caducaba "sin explicación". Aquí queda el porqué.
            AuditLogger::log('orders.payment_init_failed', $order, [
                'order_code' => $order->code,
                'source' => 'checkout',
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            // Si la preparación del pago falla, revertimos la reserva para liberar el aforo:
            // no podemos dejar un pedido `pending` que retiene plaza sin un pago asociado.
            $order->forceFill(['status' => Order::STATUS_EXPIRED, 'expires_at' => now()->subSecond()])->save();
            $this->step = 4;
            $this->addError('cart', __('tickets.errors.payment_unavailable'));

            return;
        }

        // Retención de plaza (#62/#105): el Order ya nació con `expires_at = now + sales.hold_minutes`
        // (L7, arriba). Si el cliente no completa el cobro en esa ventana (≥ timeout del TPV), la Order
        // caduca y `orders:expire` la marca `expired` — liberando aforo y evitando que basura cuente
        // contra el cap antiabuso (#92). Tras la vuelta OK firmada (5.5c) se pone `expires_at = null`.

        // Reserva firme creada: vaciamos la cesta, persistimos el código del pedido y exponemos
        // el formulario auto-POST. El email NO se envía aquí (sólo tras Redsys OK, 5.5c).
        $this->cart = [];
        $this->persistCart();
        $this->resetErrorBag('cart');
        $this->orderCode = $order->code;
        $this->redsysFormData = $formData;
        $this->step = 9;
    }

    /**
     * Reintento de pago desde el paso 10 (#114 G6): el cliente vio "pago denegado" y pulsa
     * "Reintentar". Reusamos la Order pending existente (si no caducó) y creamos un NUEVO
     * Payment con un nuevo `gateway_order` — Redsys exige `gateway_order` único por
     * comercio+terminal de por vida (manual §5, error 0913), así que no podemos reusar el
     * Payment fallido. Ventajas vs. crear Order nueva:
     *  - No consume aforo adicional (la misma plaza sigue retenida).
     *  - No cuenta contra el cap `MAX_PENDING_PER_USER` (#92).
     *  - Misma trazabilidad: el cliente sigue viendo el mismo `JJ-XXXX` en sus pedidos.
     *
     * Edge cases:
     *  - Order ya caducó (`expires_at` cruzado) → no se puede reusar; mensaje + vuelta al
     *    paso 1 (el aforo ya pudo haberse cedido lazy). El cliente debe rehacer su selección.
     *  - User no logueado (sesión expirada) → re-encamina al login.
     *  - Order no encontrada o no perteneciente al user → defensa anti-IDOR: vuelve al paso 1.
     */
    public function retryPayment(Redsys $redsys): void
    {
        if ($this->step !== 10) {
            return;
        }

        $user = auth()->user();
        if (! $user || ! $this->orderCode) {
            $this->step = $user ? 1 : 5;

            return;
        }

        // Reservas en pausa (#218): no se reinicia un cobro nuevo. La callback de un pago YA
        // iniciado finaliza igual (no pasa por aquí). El cliente puede llamar para completar.
        if ($this->blockedByReservationPause()) {
            return;
        }

        // Topes anti-abuso siguen aplicando (mismo patrón que confirmReservation).
        if (! $this->withinReservationLimits($user)) {
            return;
        }

        // Buscamos la Order pending del USER actual con el código mostrado en paso 10.
        // Defensa IDOR: el código ya viene de la sesión del propio user, pero filtramos
        // explícitamente por user_id para evitar reusos accidentales si la sesión migró.
        // Check + extensión de `expires_at` ATÓMICOS (auditoría Fase 1, L2): un UPDATE condicionado a
        // pending + no-vencida que fija la nueva ventana en la misma sentencia (espejo de
        // `RetryPaymentController`). Evita resucitar un hold ya cruzado sin recontar aforo.
        $extended = $user->orders()
            ->where('code', $this->orderCode)
            ->where('status', Order::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update(['expires_at' => now()->addMinutes(PaymentSettings::holdMinutes())]);

        if ($extended === 0) {
            // La Order caducó (orders:expire la marcó expired) o se cedió aforo lazy. El
            // cliente debe rehacer la reserva — devolvemos al paso 1 con un mensaje claro.
            $this->orderCode = null;
            $this->declinedReasonText = null;
            $this->redsysFormData = [];
            $this->step = 1;
            $this->addError('cart', __('tickets.errors.retry_expired'));

            return;
        }

        /** @var Order $order */
        $order = $user->orders()->where('code', $this->orderCode)->firstOrFail();

        try {
            $payment = DB::transaction(function () use ($order, $redsys): Payment {
                $gatewayOrder = $redsys->nextGatewayOrder();

                // Descarta los intentos `pending` previos (auditoría Fase 1, complemento C1):
                // ver `RetryPaymentController` y `Payment::STATUS_SUPERSEDED`.
                Payment::where('payable_type', (new Order)->getMorphClass())
                    ->where('payable_id', $order->id)
                    ->where('status', Payment::STATUS_PENDING)
                    ->update(['status' => Payment::STATUS_SUPERSEDED]);

                return Payment::create([
                    'payable_type' => (new Order)->getMorphClass(),
                    'payable_id' => $order->id,
                    'provider' => 'redsys',
                    'amount' => $order->onlineDueCents(), // #225: importe ONLINE (señal/depósito), no el total
                    'currency' => $order->currency,
                    'status' => Payment::STATUS_PENDING,
                    'gateway_order' => $gatewayOrder,
                ]);
            });

            $locale = $user->locale ?? app()->getLocale();
            $formData = $redsys->buildPaymentFormData($order, $payment, $locale);
        } catch (\Throwable $e) {
            Log::error('redsys.retry_failed', [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            AuditLogger::log('orders.payment_init_failed', $order, [
                'order_code' => $order->code,
                'source' => 'retry_sidebar',
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            $this->addError('cart', __('tickets.errors.payment_unavailable'));

            return;
        }

        // (La ventana `expires_at` ya se extendió arriba, atómicamente con el check — L2.)

        $this->resetErrorBag('cart');
        $this->declinedReasonText = null;
        $this->redsysFormData = $formData;
        $this->step = 9;
    }

    /**
     * Lee el `Ds_Response` del Payment failed más reciente del user para el código de
     * pedido dado (#114 G7). El campo `raw_response` está filtrado por allowlist (#113 M1),
     * pero `Ds_Response` está en la allowlist. Defensivo: si falta, devuelve null (el
     * paso 10 muestra el mensaje genérico sin motivo específico).
     */
    private function resolveDeclinedReason(?User $user, string $orderCode): ?string
    {
        if (! $user) {
            return null;
        }

        $payment = Payment::where('payable_type', (new Order)->getMorphClass())
            ->whereHas('payable', fn ($q) => $q->where('code', $orderCode)->where('user_id', $user->id))
            ->where('status', Payment::STATUS_FAILED)
            ->latest('id')
            ->first();

        $raw = $payment?->raw_response;
        $dsResponse = is_array($raw) ? ($raw['Ds_Response'] ?? null) : null;

        return RedsysResponseCode::reasonText(is_string($dsResponse) ? $dsResponse : null);
    }

    private function findCartIndex(?int $typeId, ?string $date, ?string $time): ?int
    {
        foreach ($this->cart as $i => $line) {
            if ($line['ticket_type_id'] === $typeId && ($line['date'] ?? null) === $date && ($line['time'] ?? null) === $time && empty($line['addons'])) {
                return $i;
            }
        }

        return null;
    }

    private function persistCart(): void
    {
        session(['purchase.cart' => $this->cart]);
    }

    private function clearSelection(): void
    {
        $this->typeId = null;
        $this->date = null;
        $this->time = null;
        $this->qty = 0;
        $this->eventData = [];
        $this->addonQty = [];
        $this->addonGroupChoice = [];
        $this->resetErrorBag('selection');
    }

    // ---- Datos derivados (siempre desde la BD / servidor) ----

    private function selectedType(): ?TicketType
    {
        return $this->typeId ? $this->allSellableTypes()->firstWhere('id', $this->typeId) : null;
    }

    /**
     * Aforo máximo para el producto elegido en la franja elegida (0 si falta selección). Para
     * entradas, plazas libres por ocupación (`SlotAvailability`); para packs, invitados libres
     * por cupo (`PackAvailability`, #82). En ambos casos descuenta lo que ya hay en la cesta para
     * esa franja (5.4b): no se puede sobrevender desde la propia cesta. La selección en curso no
     * está aún en $this->cart, por lo que no se cuenta dos veces.
     */
    public function maxQty(): int
    {
        $type = $this->selectedType();
        if (! $type || ! $this->date || ! $this->time) {
            return 0;
        }
        $slot = $type->zone_id ? $this->slotFor($type->zone_id) : null;
        if (! $slot) {
            return 0;
        }

        if ($type->isPack()) {
            return $this->packAvailability->availableGuestsFor(
                $slot,
                $type,
                $this->cartPackOccupants((int) $type->zone_id, $this->date),
            );
        }

        return $this->availability->availableFor(
            $slot,
            $type->duration_min,
            $this->cartOccupants((int) $type->zone_id, $this->date),
        );
    }

    /** Cantidad mínima seleccionable: 1 para entradas; el mínimo de invitados (min_qty) para packs. */
    public function minSelectableQty(): int
    {
        $type = $this->selectedType();

        return $type && $type->isPack() ? max(1, (int) ($type->min_qty ?? 1)) : 1;
    }

    /**
     * Respuestas del evento como pares [label, value] en el orden del esquema del pack, para
     * mostrarlas (carrito, pago, confirmación, "Mis pedidos"). Vacío si no es pack o no hay datos.
     *
     * @param  array<string,mixed>|null  $data
     * @return array<int, array{label:string, value:string}>
     */
    private function resolveEventData(?TicketType $type, ?array $data): array
    {
        if (! $type || ! $type->isPack() || empty($data)) {
            return [];
        }

        $out = [];
        foreach ($type->eventFields() as $field) {
            $value = $data[$field['key']] ?? null;
            if ($value !== null && $value !== '') {
                $out[] = ['label' => $type->eventFieldLabel($field), 'value' => (string) $value];
            }
        }

        return $out;
    }

    /**
     * Complementos anidados de una línea, resueltos para mostrar (nombre, cantidad, subtotal).
     *
     * @param  array<int, array{ticket_type_id:int, qty:int}>  $addons
     * @return array<int, array{name:mixed, qty:int, subtotal:int}>
     */
    private function resolveCartLineAddons(TicketType $product, int $lineQty, array $requested): array
    {
        if ($product->isAddon() || $requested === []) {
            return ['rows' => [], 'subtotal' => 0];
        }
        $product->loadMissing('addons');
        try {
            $resolved = $this->addonResolver->resolve($product, $lineQty, $requested, Carbon::today());
        } catch (\Throwable) {
            // Cesta inconsistente (p. ej. un complemento dejó de estar disponible): no rompemos la
            // vista — el checkout volverá a validar y dará el error apropiado.
            return ['rows' => [], 'subtotal' => 0];
        }

        $rows = [];
        foreach ($resolved['rows'] as $r) {
            $addonType = $this->allSellableTypes()->firstWhere('id', $r['ticket_type_id']);
            $rows[] = [
                'name' => $addonType?->tr('name'),
                'qty' => (int) $r['quantity'],
                'free_qty' => (int) $r['free_quantity'],
                'subtotal' => max(0, (int) $r['quantity'] - (int) $r['free_quantity']) * (int) $r['unit_price'],
            ];
        }

        return ['rows' => $rows, 'subtotal' => (int) $resolved['subtotal']];
    }

    /**
     * Ocupantes provisionales de la cesta para una ENTRADA en una zona y día: las líneas de
     * entrada ya añadidas que restan plazas a una nueva selección (5.4b). Los packs no cuentan
     * aquí (pool propio, #82).
     *
     * @return array<int, array{entry_start:string, duration_min:int|null, seats:int}>
     */
    private function cartOccupants(int $zoneId, string $date): array
    {
        $occupants = [];
        foreach ($this->cart as $line) {
            if (! isset($line['ticket_type_id'], $line['date'], $line['time'], $line['qty'])) {
                continue;
            }
            $type = $this->allSellableTypes()->firstWhere('id', $line['ticket_type_id']);
            if (! $type || $type->isPack() || (int) $type->zone_id !== $zoneId || $line['date'] !== $date) {
                continue;
            }
            $occupants[] = [
                'entry_start' => $line['time'],
                'duration_min' => $type->duration_min,
                'seats' => (int) $line['qty'] * (int) ($type->seats_per_unit ?? 1),
            ];
        }

        return $occupants;
    }

    /**
     * Fiestas provisionales de la cesta para el cupo de packs en una zona y día (pool propio, #82):
     * las líneas de PACK ya añadidas que restan cupo a una nueva selección. Cada línea es una fiesta.
     *
     * @return array<int, array{start:string, prep_before_min:int, duration_min:int|null, prep_after_min:int, guests:int}>
     */
    private function cartPackOccupants(int $zoneId, string $date): array
    {
        $occupants = [];
        foreach ($this->cart as $line) {
            if (! isset($line['ticket_type_id'], $line['date'], $line['time'], $line['qty'])) {
                continue;
            }
            $type = $this->allSellableTypes()->firstWhere('id', $line['ticket_type_id']);
            if (! $type || ! $type->isPack() || (int) $type->zone_id !== $zoneId || $line['date'] !== $date) {
                continue;
            }
            $occupants[] = [
                'start' => $line['time'],
                'prep_before_min' => (int) $type->prep_before_min,
                'duration_min' => $type->duration_min,
                'prep_after_min' => (int) $type->prep_after_min,
                'guests' => (int) $line['qty'] * (int) ($type->seats_per_unit ?? 1),
            ];
        }

        return $occupants;
    }

    /**
     * Franjas ofrecibles para la entrada elegida: abiertas (online, no cerradas) Y dentro de la
     * ventana de disponibilidad del producto sobre el horario del día (§9.2). Fuente única de la
     * que derivan las fechas y las horas. Memoizada por petición (la selección es constante).
     *
     * @return Collection<int, Slot>
     */
    private function offeredSlots(): Collection
    {
        // Fuente ÚNICA compartida con el panel (pedido manual): `SlotOffer` centraliza la lógica
        // correcta (sellableOnline + zona + horizonte + ventana viva del día), para que web y panel
        // no puedan divergir (auditoría Fase 1, #bug-calendario). Memoizada por petición.
        return once(fn () => $this->slotOffer->offeredSlots($this->selectedType()));
    }

    /** @return array<int,string> fechas (Y-m-d) con franjas ofrecibles para la entrada elegida. */
    private function availableDates(): array
    {
        return $this->offeredSlots()
            ->map(fn (Slot $s) => $s->date->toDateString())
            ->unique()->values()->all();
    }

    private function minMonth(): string
    {
        $dates = $this->availableDates();

        return $dates ? Carbon::parse($dates[0])->format('Y-m') : Carbon::today()->format('Y-m');
    }

    private function maxMonth(): string
    {
        $dates = $this->availableDates();

        return $dates ? Carbon::parse(end($dates))->format('Y-m') : Carbon::today()->format('Y-m');
    }

    /**
     * Rejilla del mes mostrado (semanas de lunes a domingo). Cada celda indica si es
     * seleccionable (tiene franja abierta), su tarifa (normal/especial) y su PRECIO del día
     * (en céntimos) para que el cliente compare sin tener que hacer click día a día (P-03,
     * auditoría 2026-05-26 2ª ronda).
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function calendarWeeks(): array
    {
        $month = Carbon::parse($this->month.'-01');
        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $available = array_flip($this->availableDates());
        $type = $this->selectedType();

        $weeks = [];
        $week = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $ymd = $day->toDateString();
            $selectable = isset($available[$ymd]);
            $price = $selectable && $type ? $this->rates->priceCents($type, $day) : null;
            $week[] = [
                'date' => $ymd,
                'day' => $day->day,
                'in_month' => $day->month === $month->month,
                'selectable' => $selectable,
                'type' => $selectable ? $this->rates->for($day)->key : null,
                'price_cents' => $price,
                'selected' => $this->date === $ymd,
            ];
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    /** @return array<int,string> cabeceras de día (lun..dom) en el idioma activo. */
    private function weekdayHeaders(): array
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);

        return collect(range(0, 6))
            ->map(fn (int $i) => Str::ucfirst($monday->copy()->addDays($i)->locale(app()->getLocale())->isoFormat('dd')))
            ->all();
    }

    /** @return array<int,string> horas ofrecibles el día elegido, para el producto elegido. */
    private function availableTimes(): array
    {
        $type = $this->selectedType();
        if (! $this->date || ! $type) {
            return [];
        }

        // Fuente única compartida con el panel (`SlotOffer`): mismas reglas de oferta de horas
        // (ventana del día + cupo de pack ≥ min_qty), descontando la cesta provisional. Las
        // entradas llenas vienen marcadas sellable=false; la vista las muestra deshabilitadas.
        return array_keys($this->slotOffer->offerableTimes(
            $type,
            $this->date,
            $this->cartOccupants((int) $type->zone_id, $this->date),
            $this->cartPackOccupants((int) $type->zone_id, $this->date),
        ));
    }

    /**
     * Todos los productos vendibles del flujo (entradas + packs), para resolver el carrito MIXTO.
     * El catálogo de cada pestaña sale de aquí filtrado por tipo ({@see catalogTypes()}). Cargado
     * una vez por petición.
     *
     * @return Collection<int,TicketType>
     */
    private function allSellableTypes(): Collection
    {
        return once(fn () => TicketType::sellable()
            ->inOperationalZone() // una zona desactivada no vende: fuera del catálogo y de la selección
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK, TicketType::TYPE_ADDON])
            ->with(['zone', 'prices.rateType'])
            ->orderBy('position')->get());
    }

    /**
     * Productos SELECCIONABLES del paso 1 y fuente única para validar la selección por id:
     * entradas (`type=entry`) + servicios/packs (`type=pack`). Los complementos (`type=addon`)
     * viven DENTRO de un producto (paso 3), no en el catálogo. El render los agrupa por TIPO en
     * dos secciones; el CARRITO es único y mezcla entradas y servicios (OrderCreator los soporta).
     *
     * @return Collection<int,TicketType>
     */
    private function catalogTypes(): Collection
    {
        return $this->allSellableTypes()
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK])
            ->values();
    }

    /**
     * Modela una colección de productos para el catálogo del paso 1: precio «desde», etiqueta de
     * señal (data-driven, valor CONFIGURADO #225 F2), si es pack (para el sufijo «por niño») y una
     * cadena de búsqueda normalizada (nombre + features) que el buscador progresivo filtra en
     * cliente. Lista plana ordenada por `position`; los nombres ya distinguen la zona
     * («Jump · 1 hora», «Cumpleaños Jump»), por eso NO se sub-agrupa por zona (la zona es un
     * constructo operativo —franjas + aforo—, no una categoría de catálogo; decisión 2026-06-10).
     *
     * `zone_anchor` (#228): el slug de la zona SOLO en el primer ítem de cada zona; la vista coloca
     * ahí un ancla invisible para que el deep-link «Comprar» de una atracción de pago abra Entradas
     * y haga scroll a su zona (evento `catalog-open-entries-zone`). Precalculado aquí para no meter
     * `@php` en el blade (gotcha PCRE de `purchase.blade.php`).
     *
     * @param  Collection<int,TicketType>  $types
     * @return list<array{id:int,name:string,badge:string|null,features:string,from:int|null,deposit_label:string,is_pack:bool,featured:bool,search:string,zone_anchor:string|null}>
     */
    private function catalogSection(Collection $types): array
    {
        $seenZones = [];

        return $types->map(function (TicketType $type) use (&$seenZones) {
            $features = $type->tr('features');
            $featuresText = is_array($features) ? implode(' ', $features) : (string) $features;

            $zoneSlug = $type->zone?->slug;
            $anchor = ($zoneSlug !== null && ! in_array($zoneSlug, $seenZones, true)) ? $zoneSlug : null;
            if ($anchor !== null) {
                $seenZones[] = $anchor;
            }

            return [
                'id' => $type->id,
                'name' => (string) $type->tr('name'),
                'badge' => $type->tr('badge') ?: null,
                'features' => is_array($features) ? implode(' · ', $features) : (string) $features,
                'from' => $this->fromPriceCents($type),
                'deposit_label' => (string) $type->depositLabel(),
                'is_pack' => $type->isPack(),
                // Unidad de precio configurable («/niño», «por persona»…) — misma fuente que la
                // landing (`period_label`); el catálogo la usaba hardcodeada (`tickets.per_child`).
                'period_label' => (string) $type->tr('period_label'),
                'featured' => (bool) $type->featured,
                'search' => Str::lower(trim($type->tr('name').' '.$featuresText)),
                'zone_anchor' => $anchor,
            ];
        })->values()->all();
    }

    private function slotFor(int $zoneId): ?Slot
    {
        if (! $this->date || ! $this->time) {
            return null;
        }

        return $this->slotForTime($zoneId, $this->time);
    }

    /** Franja concreta (zona + día elegido + hora dada), o null. */
    private function slotForTime(int $zoneId, string $time): ?Slot
    {
        if (! $this->date) {
            return null;
        }

        return Slot::where('date', $this->date)
            ->where('start_time', $time)
            ->where('zone_id', $zoneId)
            ->first();
    }

    /** Precio de referencia ("desde X€") para el catálogo: el más bajo de sus tarifas. */
    public function fromPriceCents(TicketType $type): ?int
    {
        $min = $type->prices->min('amount_cents');

        return $min !== null ? (int) $min : null;
    }

    /** Precio del día para la entrada elegida (según la fecha). */
    public function dayPriceCents(): ?int
    {
        $type = $this->selectedType();

        return $type && $this->date ? $this->rates->priceCents($type, Carbon::parse($this->date)) : null;
    }

    /**
     * Carrito enriquecido para la vista (nombre, zona, precio del día y subtotal desde servidor).
     *
     * @return array<int, array<string, mixed>>
     */
    private function cartLines(): array
    {
        $lines = [];
        foreach ($this->cart as $i => $line) {
            if (! isset($line['ticket_type_id'], $line['qty'])) {
                continue;
            }
            $type = $this->allSellableTypes()->firstWhere('id', $line['ticket_type_id']);
            if (! $type) {
                continue;
            }
            // Los complementos no tienen fecha: su precio (plano) se resuelve con hoy.
            $unit = (int) $this->rates->priceCents($type, $type->isAddon() ? Carbon::today() : Carbon::parse($line['date']));
            $addonResult = $this->resolveCartLineAddons($type, (int) $line['qty'], $line['addons'] ?? []);
            $principalSubtotal = $line['qty'] * $unit;
            // #225 (F2): señal/depósito POR LÍNEA. La «señal» se muestra en la CARD del producto que
            // la cobra (no como etiqueta del agregado, que confundía en cestas mixtas entrada+pack).
            // `depositCents` es data-driven (sin señal → valor pleno = sin señal). Espejo de
            // `cartDepositCents`: los complementos de un producto CON señal van 100% al parque (Opción A).
            $deposit = $type->depositCents($principalSubtotal);
            $hasDeposit = $deposit < $principalSubtotal;
            $gateRemainder = $hasDeposit ? ($principalSubtotal - $deposit) + (int) $addonResult['subtotal'] : 0;
            $lines[] = [
                'index' => $i,
                'name' => $type->tr('name'),
                'date' => $line['date'] ?? null,
                'time' => $line['time'] ?? null,
                'qty' => $line['qty'],
                'is_pack' => $type->isPack(),
                'is_addon' => $type->isAddon(),
                'event' => $this->resolveEventData($type, $line['event_data'] ?? []),
                'addons' => $addonResult['rows'],
                'subtotal' => $principalSubtotal,
                'has_deposit' => $hasDeposit,
                'deposit' => $deposit,
                'gate_remainder' => $gateRemainder,
            ];
        }

        return $lines;
    }

    /**
     * Nº de líneas del carrito — cuenta SOLO las que resuelven a un producto vendible/operativo, la
     * MISMA fuente que `cartLines()`/`cartTotalCents()`. Así el badge nunca diverge del render
     * (antes `count($this->cart)` contaba líneas fantasma de productos ya no vendibles). (P8)
     */
    public function cartCount(): int
    {
        $ids = $this->allSellableTypes()->pluck('id')->all();

        return count(array_filter(
            $this->cart,
            fn ($line) => isset($line['ticket_type_id']) && in_array((int) $line['ticket_type_id'], $ids, true)
        ));
    }

    /**
     * Poda de la cesta: descarta líneas cuyo producto dejó de ser vendible u operativo (toggle «En
     * venta online», zona desactivada). Se llama en `mount()` (cada carga de página). Evita líneas
     * fantasma irremovibles —el botón «quitar» solo existe para las líneas renderizadas— y el error
     * «:product = —» del checkout, manteniendo cesta, badge, total y checkout en una sola verdad. (P8)
     */
    private function pruneUnsellableLines(): void
    {
        if ($this->cart === []) {
            return;
        }
        $ids = $this->allSellableTypes()->pluck('id')->all();
        $kept = array_values(array_filter(
            $this->cart,
            fn ($line) => isset($line['ticket_type_id']) && in_array((int) $line['ticket_type_id'], $ids, true)
        ));
        if (count($kept) !== count($this->cart)) {
            $this->cart = $kept;
            $this->persistCart();
        }
    }

    /**
     * Resumen del pedido confirmado para la pantalla final (paso 6): líneas (cantidad, producto,
     * fecha/hora, subtotal), total y estado. Se carga del SERVIDOR y SOLO el del usuario actual
     * (acotado por user_id + código) para que nadie vea pedidos ajenos.
     *
     * @return array<string, mixed>|null
     */
    private function confirmationSummary(): ?array
    {
        if ($this->step !== 6 || ! $this->orderCode || ! auth()->check()) {
            return null;
        }

        $order = Order::with(['items.ticketType', 'items.slot', 'items.children.ticketType', 'adjustments', 'payments.refunds'])
            ->where('user_id', auth()->id())
            ->where('code', $this->orderCode)
            ->first();

        if (! $order) {
            return null;
        }

        // Solo las líneas de producto (las de complemento se anidan como hijas, #87).
        // #225 F3: por línea, la SEÑAL de ESA reserva (`pagadoOnline`) y el resto en el parque
        // (`aCobrarPuerta`) vía `ReservationFinancials` → la nota «Señal X€ · resto en el parque»
        // se muestra en la card del producto que la cobra (coherente con el sidecart), en vez de
        // etiquetar el agregado como «Señal pagada» (engañoso en cestas mixtas entrada+pack).
        $paid = $order->status === Order::STATUS_PAID;
        $lines = $order->items->whereNull('parent_item_id')->map(function ($item) use ($order, $paid) {
            $rf = ReservationFinancials::make($order, $item);

            return [
                'name' => $item->ticketType?->tr('name'),
                'qty' => $item->quantity,
                'is_pack' => $item->ticketType?->isPack() ?? false,
                'is_addon' => false,
                'has_deposit' => $paid && ($item->ticketType?->hasDeposit() ?? false) && $rf->aCobrarPuerta > 0,
                'deposit' => $rf->pagadoOnline,
                'gate_remainder' => $rf->aCobrarPuerta,
                'event' => $this->resolveEventData($item->ticketType, $item->event_data ?? []),
                'date' => $item->slot?->date?->toDateString(),
                'time' => $item->slot?->start_time,
                'addons' => $item->children->map(fn ($child) => [
                    'name' => $child->ticketType?->tr('name'),
                    'qty' => $child->quantity,
                    'free_qty' => (int) $child->free_quantity,
                    'subtotal' => $child->chargedSubtotalCents(),
                ])->all(),
                'subtotal' => $item->chargedSubtotalCents(),
            ];
        })->values()->all();

        return [
            'code' => $order->code,
            'status' => $order->status,
            'total' => $order->total,
            // #225: split señal/pendiente para el paso 6. `online` = lo cobrado (la señal);
            // `pending_at_park` = el resto a cobrar en el parque (0 si no hay señal). El blade
            // solo lo muestra si el pedido está PAID y queda algo en puerta.
            'online' => $order->onlineDueCents(),
            'pending_at_park' => $order->financialSummary()->pendingAtGate(),
            'lines' => $lines,
            // ¿Algún producto pide el formulario de reserva post-pago (#217)? Solo entonces el
            // sidecart adelanta el aviso — un pack SIN `guest_fields` no promete un formulario que
            // no existe (coherencia del flujo: email, post-form y aviso van juntos por `needsGuestForm`).
            'has_guest_form' => $order->needsGuestForm(),
        ];
    }

    public function cartTotalCents(): int
    {
        $total = 0;
        foreach ($this->cart as $line) {
            if (! isset($line['ticket_type_id'], $line['qty'])) {
                continue;
            }
            $type = $this->allSellableTypes()->firstWhere('id', $line['ticket_type_id']);
            if (! $type) {
                continue;
            }
            $total += $line['qty'] * (int) $this->rates->priceCents($type, $type->isAddon() ? Carbon::today() : Carbon::parse($line['date']));

            // Complementos anidados de la línea: el MISMO AddonResolver del checkout (incluido /
            // por-invitado / grupo excluyente) → el total previsualizado coincide con el cobro.
            $total += $this->resolveCartLineAddons($type, (int) $line['qty'], $line['addons'] ?? [])['subtotal'];
        }

        return $total;
    }

    /**
     * Importe a cobrar ONLINE de la cesta = la SEÑAL/DEPÓSITO (#225). ESPEJO EXACTO de lo que
     * `OrderCreator`/`Order::onlineDueCents()` cobrarán para ESTA misma cesta (canario anti
     * doble-fuente): por cada línea, `TicketType::depositCents(valor_línea)` (none → total);
     * y los complementos de un producto CON señal van 100% al parque (online 0; Opción A), los
     * de un producto sin señal se cobran online al completo. Reusa `allSellableTypes()` memoizado.
     */
    public function cartDepositCents(): int
    {
        $online = 0;
        foreach ($this->cart as $line) {
            if (! isset($line['ticket_type_id'], $line['qty'])) {
                continue;
            }
            $type = $this->allSellableTypes()->firstWhere('id', $line['ticket_type_id']);
            if (! $type) {
                continue;
            }
            $unit = (int) $this->rates->priceCents($type, $type->isAddon() ? Carbon::today() : Carbon::parse($line['date']));
            $lineCharged = $line['qty'] * $unit;
            $deposit = $type->depositCents($lineCharged);
            $online += $deposit;

            // Complementos: solo se cobran online si el principal NO tiene señal (Opción A).
            if ($deposit >= $lineCharged) {
                $online += $this->resolveCartLineAddons($type, (int) $line['qty'], $line['addons'] ?? [])['subtotal'];
            }
        }

        return $online;
    }

    /**
     * Bloque de «registro del parque» para la pantalla final de la compra (paso 6, #216 config):
     * si hay una URL de registro externo configurada, devolvemos su texto informativo + la
     * etiqueta del botón (POR IDIOMA, con fallback al texto i18n). Null si no hay URL → no se
     * muestra nada. La URL se sanea (solo http(s)) reusando la defensa del composer.
     *
     * @return array{url: string, label: string, description: string}|null
     */
    private function registrationPrompt(): ?array
    {
        $url = AppServiceProvider::safeExternalUrl(Setting::value('registration.url'));
        if ($url === null) {
            return null;
        }

        $loc = app()->getLocale();

        return [
            'url' => $url,
            'label' => Setting::value('registration.label.'.$loc) ?: __('landing.nav.register'),
            'description' => Setting::value('registration.description.'.$loc) ?: __('landing.nav.register_info'),
        ];
    }

    /**
     * Mapa paso → «modo» del sidebar. Fuente única que consumen: (a) el panel `.sidecart__panel`,
     * que recibe la clase `is-{modo}` vía Alpine (`x-effect` sobre `$wire.step`) para minimizar la
     * cuenta y posicionar el footer SIN viaje al servidor; (b) `footer()` y `bookingProgress()`.
     */
    public function stepModeMap(): array
    {
        return [
            1 => 'catalog',
            2 => 'booking', 3 => 'booking',
            4 => 'cart', 5 => 'cart', 8 => 'cart',
            6 => 'result', 7 => 'result', 9 => 'result', 10 => 'result', 11 => 'result',
        ];
    }

    /** Modo actual del sidebar derivado de `$step` (catalog | booking | cart | result). */
    public function sidebarMode(): string
    {
        return $this->stepModeMap()[$this->step] ?? 'catalog';
    }

    /**
     * Stepper detallado de 3 fases que sustituye a las 3 barras mudas. Solo en modo booking
     * (pasos 2-3). La 3.ª fase depende del TIPO de producto: en una ENTRADA es «Extras»; en un
     * PACK es «Datos» (reúne datos de la reserva + extras). El progreso avanza DENTRO del paso 3
     * según si ya se eligió la hora. El contexto se formatea AQUÍ (gotcha @php).
     */
    public function bookingProgress(): ?array
    {
        if ($this->sidebarMode() !== 'booking') {
            return null;
        }

        $type = $this->selectedType();
        $hasTime = $this->time !== null;

        // Paso activo: 1 = Fecha (paso 2), 2 = Hora (paso 3 sin hora), 3 = Extras/Datos (paso 3 con hora).
        $active = $this->step === 2 ? 1 : ($hasTime ? 3 : 2);
        $third = $type?->isPack() ? __('tickets.phase_details') : __('tickets.phase_extras');

        $context = implode(' · ', array_filter([
            $type?->tr('name'),
            $this->date ? Str::ucfirst(Carbon::parse($this->date)->locale(app()->getLocale())->isoFormat('ddd D MMM')) : null,
            $hasTime ? Str::substr($this->time, 0, 5) : null,
        ]));

        return [
            'active' => $active,
            'total' => 3,
            'steps' => [
                ['label' => __('tickets.phase_date'), 'state' => $this->step === 2 ? 'current' : 'done'],
                ['label' => __('tickets.phase_time'), 'state' => $this->step === 2 ? 'todo' : ($hasTime ? 'done' : 'current')],
                ['label' => $third, 'state' => ($this->step === 3 && $hasTime) ? 'current' : 'todo'],
            ],
            'context' => $context,
        ];
    }

    /** Placeholder del total cuando aún no hay precio definido (paso de fecha, y hora sin elegir). */
    private const AMOUNT_PLACEHOLDER = '—';

    /**
     * Footer único, sticky y contextual. Devuelve null en los pasos sin barra (login embebido y
     * pantallas terminales). Toda cifra se formatea aquí (gotcha @php). Las acciones (`goToCart`,
     * `addToCart`, `checkout`, `confirmReservation`) son las EXISTENTES del componente.
     */
    public function footer(): ?array
    {
        return match ($this->step) {
            // Catálogo: barra-carrito al estilo del mockup (badge de cantidad + info + «Ir al carrito»).
            1 => $this->cartCount() > 0 ? [
                'type' => 'cart',
                'action' => 'goToCart',
                'cta' => __('tickets.go_to_cart'),
                'count' => $this->cartCount(),
                'label' => trans_choice('tickets.cart_items', $this->cartCount(), ['count' => $this->cartCount()]),
                'amount' => $this->money($this->cartTotalCents()),
            ] : null,
            // Paso de fecha: MISMO footer (Total + CTA + nota de IVA). El precio necesita fecha, así
            // que el total muestra el placeholder «—»; «Continuar» se habilita al elegir un día. No
            // auto-avanza: da control y mantiene el footer coherente con los demás pasos.
            2 => [
                'type' => 'bar',
                'action' => 'goToTime',
                'cta' => __('tickets.continue'),
                'label' => __('tickets.total'),
                'amount' => self::AMOUNT_PLACEHOLDER,
                'disabled' => $this->date === null,
                'icon' => 'arrow',
                'splitMode' => null,
                'split' => null,
                'note' => __('tickets.iva_note'),
            ],
            // Paso de hora/extras: MISMO footer, SIEMPRE presente. Total «—» hasta elegir hora; al
            // elegirla aparece la cantidad mínima y se genera el total (luego cambia con cantidad/extras).
            3 => $this->stepFooter(),
            // Carrito: desglose en popover (ⓘ), para no romper «Total — CTA». Pago: desglose en banda
            // sticky propia (siempre visible), porque ahí el cliente debe ver lo que pagará.
            4 => $this->cartCount() > 0 ? $this->cartFooter('checkout', __('tickets.go_to_pay'), 'arrow', 'popover') : null,
            8 => $this->cartFooter('confirmReservation', __('tickets.pay_confirm'), 'card', 'band'),
            default => null,
        };
    }

    /**
     * Footer del paso 3 (producto único). SIEMPRE presente. Hasta elegir hora, el total es «—»
     * (sin cantidad). Al elegir hora se fija la cantidad mínima y se genera el total; cambia al
     * modificar cantidad/complementos. El CTA «Añadir al carrito» se deshabilita de forma INTELIGENTE
     * (`canAddToCart`): hora elegida, cantidad ≥ mínimo con aforo, y campos de evento obligatorios del
     * pack rellenos. Si hay señal, el desglose «(señal)» va en el ⓘ.
     */
    private function stepFooter(): array
    {
        $hasTime = $this->time !== null;
        $total = (int) ($this->dayPriceCents() ?? 0) * (int) $this->qty + (int) $this->addonViewModel()['total'];
        $deposit = $hasTime ? $this->stepDepositCents() : null;

        return [
            'type' => 'bar',
            'action' => 'addToCart',
            'cta' => __('tickets.add_to_cart'),
            'label' => __('tickets.total'),
            'amount' => $hasTime ? $this->money($total) : self::AMOUNT_PLACEHOLDER,
            // El CTA solo se inactiva hasta elegir HORA (estructural: aún no hay nada que añadir).
            // Lo demás (cantidad mínima, campos obligatorios) se valida AL PULSAR, con feedback claro
            // y específico — no con un botón muerto que no explica qué falta (anti-patrón de UX).
            'disabled' => ! $hasTime,
            'icon' => 'arrow',
            'splitMode' => 'popover',
            // Producto único: «Pagas ahora (señal)» aclara por qué se cobra menos que el total.
            'split' => $deposit !== null ? [
                'nowLabel' => __('tickets.footer_pay_now_deposit'),
                'now' => $this->money($deposit),
                'park' => $this->money($total - $deposit),
            ] : null,
            'note' => __('tickets.iva_note'),
        ];
    }

    /** Barra del carrito/pago. Ancla en el TOTAL (coherente con el catálogo); si hay señal, desglose
        NEUTRO «Pagas ahora / En el parque» (en cestas mixtas no es solo señal; #225). `$splitMode`
        decide cómo se presenta el desglose: «popover» (ⓘ, carrito) o «band» (banda sticky, pago). */
    private function cartFooter(string $action, string $cta, string $icon, string $splitMode): array
    {
        $hasDeposit = $this->cartDepositCents() < $this->cartTotalCents();

        return [
            'type' => 'bar',
            'action' => $action,
            'cta' => $cta,
            'label' => __('tickets.total'),
            'amount' => $this->money($this->cartTotalCents()),
            'disabled' => false,
            'icon' => $icon,
            'splitMode' => $splitMode,
            'split' => $hasDeposit ? [
                'nowLabel' => __('tickets.footer_pay_now'),
                'now' => $this->money($this->cartDepositCents()),
                'park' => $this->money($this->cartTotalCents() - $this->cartDepositCents()),
            ] : null,
            'note' => __('tickets.iva_note'),
        ];
    }

    /** Señal (céntimos) de la selección actual del paso 3, o null si el producto se paga entero. */
    private function stepDepositCents(): ?int
    {
        $type = $this->selectedType();
        if (! $type) {
            return null;
        }
        $principal = (int) ($this->dayPriceCents() ?? 0) * (int) $this->qty;
        $deposit = $type->depositCents($principal);

        return $deposit < $principal ? $deposit : null;
    }

    /** Formatea céntimos como importe en euros (mismo formato que el resto del flujo). */
    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }

    public function render()
    {
        // Catálogo del paso 1 agrupado por TIPO en dos secciones: «Entradas» (entry) y «Servicios»
        // (pack). Lista plana ordenada por `position`; NO se sub-agrupa por zona (los nombres ya la
        // llevan y la zona es un constructo operativo, no una categoría de catálogo). El anuncio de
        // señal (#225 F2) viaja por ítem en `catalogSection()`.
        $entries = [];
        $services = [];
        if ($this->step === 1) {
            $types = $this->catalogTypes();
            $entries = $this->catalogSection($types->where('type', TicketType::TYPE_ENTRY));
            $services = $this->catalogSection($types->where('type', TicketType::TYPE_PACK));
        }
        $catalogTotal = count($entries) + count($services);

        return view('livewire.tickets.purchase', [
            'catalog' => [
                ['key' => 'entries', 'items' => $entries],
                ['key' => 'services', 'items' => $services],
            ],
            'catalogHasItems' => $catalogTotal > 0,
            // Buscador progresivo: solo si el catálogo supera el umbral CONFIGURABLE desde el panel
            // (#226; helper defensivo `CatalogSettings`, default 12). Catálogo pequeño → sin buscador.
            'catalogSearchEnabled' => $catalogTotal > CatalogSettings::searchMinItems(),
            // Índice de búsqueda por sección (cadenas normalizadas) para el filtrado client-side.
            'catalogSearchIndex' => [
                'entries' => array_column($entries, 'search'),
                'services' => array_column($services, 'search'),
            ],
            // Bloque de registro externo en la pantalla final (#216 config); solo se calcula en el paso 6.
            'registration' => $this->step === 6 ? $this->registrationPrompt() : null,
            'selectedType' => $this->selectedType(),
            'selectedIsPack' => $this->selectedType()?->isPack() ?? false,
            'selectedPeriodLabel' => (string) $this->selectedType()?->tr('period_label'),
            'eventFields' => $this->selectedType()?->isPack() ? $this->selectedType()->eventFields(TicketType::EVENT_STAGE_BOOKING) : [],
            'minQty' => $this->minSelectableQty(),
            'addonModel' => $this->step === 3
                ? $this->addonViewModel()
                : ['groups' => [], 'singles' => [], 'total' => 0],
            'weeks' => $this->calendarWeeks(),
            'weekdayHeaders' => $this->weekdayHeaders(),
            'monthLabel' => Str::ucfirst(Carbon::parse($this->month.'-01')->locale(app()->getLocale())->isoFormat('MMMM YYYY')),
            'canPrev' => $this->month > $this->minMonth(),
            'canNext' => $this->month < $this->maxMonth(),
            'times' => $this->availableTimes(),
            'maxQty' => $this->maxQty(),
            'dayPriceCents' => $this->dayPriceCents(),
            'cartLines' => $this->cartLines(),
            'cartTotalCents' => $this->cartTotalCents(),
            'cartDepositCents' => $this->cartDepositCents(),
            'cartCount' => $this->cartCount(),
            'confirmation' => $this->confirmationSummary(),
            // Sidebar v2: modo (clase de estado del panel), stepper detallado y footer dinámico.
            // El blade pasa `stepModeMap` a Alpine; el modo se refleja reactivamente desde `$wire.step`.
            'sidebarMode' => $this->sidebarMode(),
            'stepModeMap' => $this->stepModeMap(),
            // Paso de identificación (login/registro embebido; ver doc de $step). El blade lo pasa a
            // Alpine para que el bloque de cuenta de invitado BLOQUEE su «Iniciar sesión» en ese paso
            // (señal `$store.purchase.identifying`). Único en código aquí (el flujo no usa constantes).
            'identifyStep' => 5,
            'bookingProgress' => $this->bookingProgress(),
            'footer' => $this->footer(),
        ]);
    }
}
