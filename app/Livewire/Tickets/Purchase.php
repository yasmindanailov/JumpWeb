<?php

namespace App\Livewire\Tickets;

use App\Domain\Booking\Contracts\AdmissionDecision;
use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\CartPricing;
use App\Domain\Booking\Contracts\CartQuote;
use App\Domain\Booking\Contracts\CartQuoteAddon;
use App\Domain\Booking\Contracts\CartQuoteLine;
use App\Domain\Booking\Contracts\CatalogProduct;
use App\Domain\Booking\Contracts\OfferedDate;
use App\Domain\Booking\Contracts\OfferedTime;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Contracts\ReservationCheckout;
use App\Domain\Booking\Contracts\RetryAdmission;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\AddonResolver;
use App\Domain\Booking\Services\Cart;
use App\Domain\Booking\Services\CatalogSettings;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\ReservationFinancials;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Contracts\PaymentInitiationException;
use App\Domain\Payments\Contracts\PaymentTicket;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\Redsys;
use App\Domain\Payments\Services\RedsysResponseCode;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\MaintenanceSettings;
use App\Http\Sidebar\RegistrationLink;
use App\Http\Sidebar\SidebarEntry;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
     * Límites anti-abuso (auditoría 2026-05-26, hallazgo E): sin ellos, un usuario autenticado
     * podría iterar la confirmación y agotar el aforo del día sin pagar.
     *
     * Solo queda aquí el cap de LÍNEAS, y reflejado: su autoridad está en `OrderCreator` (`PAY-12`)
     * y el sidecart lo lee para no ofrecer un botón que el servidor va a rechazar. El tope de
     * pedidos pendientes y el de frecuencia se mudaron a `ReservationAdmissionPolicy` en Fase 3 ·
     * paso 2, con sus constantes: una regla que aplican tres superficies no puede tener su número
     * en la clase de UI de una de ellas.
     */
    /** @see OrderCreator::MAX_LINES_PER_CART — el cap es invariante de SERVIDOR (PAY-12); aquí solo se refleja para la UI. */
    public const MAX_LINES_PER_CART = OrderCreator::MAX_LINES_PER_CART;

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

    private AddonResolver $addonResolver;

    private ProductCatalog $catalog;

    private ReservationAdmission $admission;

    /**
     * La SECUENCIA de la compra (cierre de Fase 3). Convive con `$admission` a propósito y no la
     * sustituye: el paso 4 usa `mayReserve()`, que solo CONSULTA, para avisar temprano sin gastar
     * ficha por una pantalla que no crea nada. Lo que consume va dentro del orquestador.
     */
    private ReservationCheckout $checkout;

    private CartPricing $pricing;

    private AvailabilityOffer $availabilityOffer;

    /**
     * Memo de la oferta de días del producto elegido. `availableDates()` se consulta hasta seis
     * veces por petición (validar el día, acotar los meses, pintar la rejilla), y sin memo cada una
     * sería una consulta.
     *
     * @var list<OfferedDate>|null
     */
    private ?array $offeredDatesMemo = null;

    private ?int $offeredDatesMemoFor = null;

    /** Memo por petición de los complementos del producto elegido (evita N consultas por render). */
    private ?Collection $selectedAddonsMemo = null;

    private ?int $selectedAddonsMemoFor = null;

    /**
     * Memo de la cesta tarificada, atado al CONTENIDO de la cesta (no a la petición).
     *
     * Se hace así, y no con `once()`, porque una misma petición puede tarificar dos cestas
     * distintas: `addToCart()`/`removeLine()` cambian `$this->cart` y `render()` corre después. Un
     * memo por petición devolvería el importe de la cesta ANTERIOR — un error de dinero silencioso.
     */
    private ?CartQuote $quoteMemo = null;

    private ?string $quoteMemoFor = null;

    public function boot(AddonResolver $addonResolver, ProductCatalog $catalog, ReservationAdmission $admission, CartPricing $pricing, AvailabilityOffer $availabilityOffer, ReservationCheckout $checkout): void
    {
        $this->addonResolver = $addonResolver;
        $this->catalog = $catalog;
        $this->admission = $admission;
        $this->pricing = $pricing;
        $this->availabilityOffer = $availabilityOffer;
        $this->checkout = $checkout;
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

        // El DESENLACE del pago que dejó esperando la vuelta de la pasarela. Este componente es hoy
        // el motor del cajón, así que es quien lo CONSUME (`SidebarEntry` documenta por qué el
        // consumidor depende del motor: aquí corre en la petición del `lazy`, no en la del layout).
        // Antes, las tres claves de sesión se nombraban a mano justo aquí, y este era el único sitio
        // del sistema que las olvidaba.
        $entry = SidebarEntry::consume();

        match ($entry->outcome) {
            // Vuelta desde el enlace de verificación (Pieza 2, #76) o desde Redsys OK (capa 5.5c,
            // #104). En ambos casos la reserva queda confirmada: paso 6 + feedback.
            SidebarEntry::OUTCOME_CONFIRMED => $this->enterConfirmed($entry->orderCode),
            // Vuelta KO de Redsys (capa 5.5c, #104): pago denegado por el banco. La Order queda
            // pending y caducará por `orders:expire` al cruzar su `expires_at` (#105), liberando el
            // aforo. El cliente puede reintentar; el paso 10 muestra el motivo del rechazo.
            SidebarEntry::OUTCOME_FAILED => $this->enterDeclined($entry->orderCode),
            // Vuelta SIN datos firmados (capa 5.5c, #106): el terminal no incluye los `Ds_*` en la
            // redirección. No se puede confirmar en este lado, así que se muestra «verificando» y se
            // depende de la notificación on-line (5.5d) o del correo que se enviará al confirmarse.
            SidebarEntry::OUTCOME_VERIFYING => $this->enterVerifying($entry->orderCode),
            default => null,
        };
    }

    private function enterConfirmed(?string $orderCode): void
    {
        $this->orderCode = $orderCode;
        $this->confirmed = true;
        $this->step = 6;
    }

    private function enterDeclined(?string $orderCode): void
    {
        $this->orderCode = $orderCode;
        $this->step = 10;

        // Audit #114 G7: extraer el `Ds_Response` del Payment failed más reciente del user para
        // mostrarlo traducido al cliente («tarjeta caducada», «CVV erróneo»…). El campo
        // `Payment.raw_response` está filtrado por allowlist (#113 M1).
        $this->declinedReasonText = $orderCode === null
            ? null
            : $this->resolveDeclinedReason(auth()->user(), $orderCode);
    }

    private function enterVerifying(?string $orderCode): void
    {
        $this->orderCode = $orderCode;
        $this->step = 11;
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
     * Traduce a la UI del sidecart un veredicto DENEGADO de la política de admisión
     * (`Booking\Contracts\ReservationAdmission`, Fase 3 · paso 2). El dominio dice por qué no; qué
     * se pinta y a qué paso se vuelve es de esta interfaz.
     *
     * El aviso de PAUSA (#218) tiene dos variantes porque sin teléfono configurado el mensaje
     * interpolaría un `:phone` vacío y quedaría una gramática rota («Llámanos al  para reservar»);
     * la variante sin teléfono invita a Contacto, mismo criterio que el componente `reserve-cta`.
     */
    private function reportAdmissionDenial(AdmissionDecision $decision): void
    {
        if ($decision->reason === AdmissionDecision::RESERVATIONS_PAUSED) {
            $phone = trim((string) Setting::value('contact.phone', ''));
            $this->addError('cart', $phone !== ''
                ? __('tickets.errors.reservations_paused', ['phone' => $phone])
                : __('tickets.errors.reservations_paused_no_phone'));

            return;
        }

        $this->addError('cart', $decision->reason === AdmissionDecision::TOO_MANY_PENDING
            ? __('tickets.errors.too_many_pending', ['max' => $decision->context['max'] ?? 0])
            : __('tickets.errors.try_later'));
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

    private function proceed(): void
    {
        $user = auth()->user();
        if (! $user) {
            $this->step = 5;

            return;
        }

        // Admisión (Fase 3 · paso 2): pausa de reservas (#218) y topes anti-abuso (auditoría
        // 2026-05-26, hallazgo E), decididos por el dominio.
        //
        // Aquí se CONSULTA sin consumir: este paso no crea nada —solo lleva a la pantalla de pago—,
        // y gastar una ficha del limitador por navegar hacía que la SEGUNDA compra del mismo minuto
        // se bloqueara al confirmar, aunque el tope sean 3 reservas. La ficha se gasta donde nace el
        // pedido que retiene aforo: en `confirmReservation()`. El aviso temprano se conserva.
        $decision = $this->admission->mayReserve((int) $user->getAuthIdentifier());
        if ($decision->denied()) {
            $this->step = 4;
            $this->reportAdmissionDenial($decision);

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
    public function confirmReservation(): void
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

        // La SECUENCIA entera —admitir consumiendo ficha, crear el pedido con su ventana de
        // retención (`AFORO-10`) y abrir el cobro sobre el pedido ya persistido— la aplica
        // `Booking\Contracts\ReservationCheckout` desde el cierre de Fase 3. Antes estaba escrita
        // aquí a mano, y otras tres veces en las demás superficies. Lo que queda en este método es
        // lo que de verdad es del sidebar: a qué paso se vuelve y qué se le dice al cliente.
        try {
            $outcome = $this->checkout->start($user, $this->cart, ReservationCheckout::SOURCE_CHECKOUT);
        } catch (ReservationException $e) {
            $this->step = 4;
            $this->addError('cart', __($e->getMessage(), $e->context));

            return;
        } catch (PaymentInitiationException) {
            // El pedido ya lo soltó el dominio (retendría una plaza que nadie va a pagar) y el
            // diagnóstico ya está en el log y en `audit_logs` (#169).
            $this->step = 4;
            $this->addError('cart', __('tickets.errors.payment_unavailable'));

            return;
        }

        if ($outcome->denied()) {
            /** @var AdmissionDecision $decision Garantizado por `denied()`. */
            $decision = $outcome->denial;
            $this->step = 4;
            $this->reportAdmissionDenial($decision);

            return;
        }

        /** @var Order $order Garantizado por `allow`; ya retiene aforo. */
        $order = $outcome->order;
        /** @var PaymentTicket $ticket Garantizado por `allow`. */
        $ticket = $outcome->ticket;

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
        $this->redsysFormData = $ticket->formData;
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
     *
     * Desde el cierre de Fase 3 lo decide el dominio entero, incluido el ORDEN: admitir el reintento
     * —que extiende la retención con la sentencia atómica de `PAY-04`— y solo después reabrir el
     * cobro es `Booking\Contracts\ReservationCheckout::retry()`. Aquí solo queda a qué paso se
     * vuelve y qué se le dice al cliente.
     */
    public function retryPayment(): void
    {
        if ($this->step !== 10) {
            return;
        }

        $user = auth()->user();
        if (! $user || ! $this->orderCode) {
            $this->step = $user ? 1 : 5;

            return;
        }

        try {
            $outcome = $this->checkout->retry($user, $this->orderCode, ReservationCheckout::SOURCE_RETRY_SIDEBAR);
        } catch (PaymentInitiationException) {
            // El diagnóstico ya está registrado (log + `audit_logs`). El pedido NO se toca: sigue
            // vivo con su hold recién extendido, así que el cliente puede volver a intentarlo.
            $this->addError('cart', __('tickets.errors.payment_unavailable'));

            return;
        }

        if ($outcome->denied()) {
            /** @var RetryAdmission $verdict Garantizado por `denied()`. */
            $verdict = $outcome->denial;
            // Pausa (#218) y límite de frecuencia dejan al cliente donde está: su reserva sigue
            // viva y puede reintentar en cuanto se levante el aviso. `NOT_RETRYABLE` es otra cosa
            // —el hold cruzó y la plaza pudo cederse—, así que ahí sí hay que rehacer la selección.
            if ($verdict->reason === RetryAdmission::NOT_RETRYABLE) {
                $this->orderCode = null;
                $this->declinedReasonText = null;
                $this->redsysFormData = [];
                $this->step = 1;
                $this->addError('cart', __('tickets.errors.retry_expired'));

                return;
            }

            $this->reportRetryDenial($verdict);

            return;
        }

        /** @var PaymentTicket $ticket Garantizado por `allow`. */
        $ticket = $outcome->ticket;

        $this->resetErrorBag('cart');
        $this->declinedReasonText = null;
        $this->redsysFormData = $ticket->formData;
        $this->step = 9;
    }

    /**
     * Traduce a la UI del sidecart un reintento DENEGADO que no obliga a rehacer la reserva. Espeja
     * a `reportAdmissionDenial()` —mismos textos para los mismos motivos— porque para el cliente es
     * la misma situación venga de crear o de reintentar.
     */
    private function reportRetryDenial(RetryAdmission $verdict): void
    {
        if ($verdict->reason === RetryAdmission::RESERVATIONS_PAUSED) {
            $phone = trim((string) Setting::value('contact.phone', ''));
            $this->addError('cart', $phone !== ''
                ? __('tickets.errors.reservations_paused', ['phone' => $phone])
                : __('tickets.errors.reservations_paused_no_phone'));

            return;
        }

        $this->addError('cart', __('tickets.errors.try_later'));
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
        if (! $this->typeId || ! $this->date || ! $this->time) {
            return 0;
        }

        return $this->availabilityOffer->maxQuantity($this->typeId, $this->date, $this->time, $this->cart);
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
     * La oferta de DÍAS del producto elegido, tal y como la describe el dominio (Fase 3 · paso 4b).
     *
     * Memoizada por producto: `availableDates()` se consulta varias veces en la misma petición
     * —validar el día elegido, acotar la navegación de meses, pintar la rejilla— y cada una sería
     * una consulta. Se ata al producto y no a la petición porque `selectType()` lo cambia en medio.
     *
     * @return list<OfferedDate>
     */
    private function offeredDates(): array
    {
        $productId = (int) ($this->typeId ?? 0);

        if ($this->offeredDatesMemoFor !== $productId) {
            // Sin producto elegido no hay oferta que pedir: el calendario solo se pinta en el paso 2,
            // al que se llega por `selectType()`, que fija el mes con el primer día del producto.
            $this->offeredDatesMemo = $productId > 0 ? $this->availabilityOffer->dates($productId) : [];
            $this->offeredDatesMemoFor = $productId;
        }

        return $this->offeredDatesMemo;
    }

    /** @return array<int,string> fechas (Y-m-d) con franjas ofrecibles para la entrada elegida. */
    private function availableDates(): array
    {
        return array_map(static fn (OfferedDate $day): string => $day->date, $this->offeredDates());
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

        // Qué días se ofrecen, a qué tarifa y a qué precio lo dice el dominio de una vez. Antes se
        // resolvían aquí día a día (`RateResolver::priceCents` consulta por llamada, hasta 42 veces
        // por render); ahora es una consulta de tarifa por día distinto OFRECIDO, no por celda.
        $offered = [];
        foreach ($this->offeredDates() as $day) {
            $offered[$day->date] = $day;
        }

        $weeks = [];
        $week = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $ymd = $day->toDateString();
            $offer = $offered[$ymd] ?? null;
            $week[] = [
                'date' => $ymd,
                'day' => $day->day,
                'in_month' => $day->month === $month->month,
                'selectable' => $offer !== null,
                'type' => $offer?->rateKey,
                'price_cents' => $offer?->priceCents,
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
        if (! $this->typeId || ! $this->date) {
            return [];
        }

        // La oferta de horas la decide el dominio (`AvailabilityOffer`, Fase 3 · paso 4b) sobre la
        // fuente única `SlotOffer` (`AFORO-02`), y **con la cesta delante**: las líneas que el
        // cliente ya tiene elegidas retienen cupo, así que sin ellas se ofrecerían horas que el
        // checkout rechazaría. Derivar esos ocupantes vivía aquí, en una clase de interfaz.
        return array_map(
            static fn (OfferedTime $slot): string => $slot->time,
            $this->availabilityOffer->times($this->typeId, $this->date, $this->cart),
        );
    }

    /**
     * Todos los productos vendibles del flujo (entradas + packs + complementos), para resolver el
     * carrito MIXTO y la selección en curso. Cargado una vez por petición.
     *
     * El CATÁLOGO ya no sale de aquí: lo describe `ProductCatalog` (Fase 3 · paso 1b), que aplica
     * el mismo filtro sobre los tipos seleccionables. Esta consulta sigue existiendo porque el
     * carrito necesita además los complementos y los MODELOS (precio del día, franjas, aforo), no
     * la ficha de catálogo.
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
     * Productos SELECCIONABLES, para validar por id lo que el cliente elige: entradas
     * (`type=entry`) + servicios/packs (`type=pack`). Los complementos (`type=addon`) viven DENTRO
     * de un producto (paso 3), no en el catálogo. El CARRITO es único y mezcla entradas y servicios
     * (OrderCreator los soporta).
     *
     * Mismo conjunto que expone `ProductCatalog`, resuelto sobre los modelos ya cargados: lo que se
     * puede ELEGIR y lo que se OFRECE no pueden ser dos cosas distintas.
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
     * Adapta a la VISTA los productos que describe el dominio (`Booking\Contracts\ProductCatalog`).
     * Qué se vende y qué se cuenta de cada producto —precio «desde», etiqueta de señal (#225 F2),
     * ventajas, unidad de precio— lo decide el read-model desde Fase 3 · paso 1b; aquí solo queda
     * lo que es de ESTA interfaz y ningún otro cliente necesitaría:
     *
     *  - `features` unido con « · » y `deposit_label`/`period_label` como cadena (el blade los
     *    imprime tal cual; el contrato los da como lista y como `null`, que es lo que son);
     *  - `search` (#226): nombre + ventajas en minúsculas, el índice que el buscador progresivo
     *    filtra EN CLIENTE. Un índice de búsqueda no es dominio: cada cliente construye el suyo;
     *  - `zone_anchor` (#228): el slug de la zona SOLO en el primer producto de cada zona; la vista
     *    coloca ahí un ancla invisible para que el deep-link «Comprar» de una atracción de pago
     *    abra Entradas y haga scroll a su zona (evento `catalog-open-entries-zone`). Se precalcula
     *    aquí para no meter `@php` en el blade (gotcha PCRE de `purchase.blade.php`).
     *
     * Lista plana ordenada por `position`; los nombres ya distinguen la zona («Jump · 1 hora»,
     * «Cumpleaños Jump»), por eso NO se sub-agrupa por zona (la zona es un constructo operativo
     * —franjas + aforo—, no una categoría de catálogo; decisión 2026-06-10).
     *
     * @param  list<CatalogProduct>  $products
     * @return list<array{id:int,name:string,badge:string|null,features:string,from:int|null,deposit_label:string,is_pack:bool,period_label:string,featured:bool,search:string,zone_anchor:string|null}>
     */
    private function catalogSection(array $products): array
    {
        $seenZones = [];
        $rows = [];

        foreach ($products as $product) {
            $zoneSlug = $product->zone?->slug;
            $anchor = ($zoneSlug !== null && ! in_array($zoneSlug, $seenZones, true)) ? $zoneSlug : null;
            if ($anchor !== null) {
                $seenZones[] = $anchor;
            }

            $rows[] = [
                'id' => $product->id,
                'name' => $product->name,
                'badge' => $product->badge,
                'features' => implode(' · ', $product->features),
                'from' => $product->fromPriceCents,
                'deposit_label' => (string) $product->depositLabel,
                'is_pack' => $product->isPack(),
                // Unidad de precio configurable («/niño», «por persona»…) — misma fuente que la
                // landing (`period_label`); el catálogo la usaba hardcodeada (`tickets.per_child`).
                'period_label' => (string) $product->periodLabel,
                'featured' => $product->featured,
                'search' => Str::lower(trim($product->name.' '.implode(' ', $product->features))),
                'zone_anchor' => $anchor,
            ];
        }

        return $rows;
    }

    /**
     * Precio del día para la entrada elegida (según la fecha).
     *
     * Sale de la MISMA oferta que pinta el calendario ({@see offeredDates()}), no de una resolución
     * de tarifa aparte: si se calculara por su cuenta, el precio del día elegido y el que se ve en
     * su celda del calendario podrían no coincidir. Un día que no está entre los ofrecidos no tiene
     * precio que anunciar —`selectDate()` no deja elegirlo— y devuelve null.
     */
    public function dayPriceCents(): ?int
    {
        if (! $this->date) {
            return null;
        }

        foreach ($this->offeredDates() as $day) {
            if ($day->date === $this->date) {
                return $day->priceCents;
            }
        }

        return null;
    }

    /**
     * La cesta TARIFICADA por el dominio (Fase 3 · paso 4a).
     *
     * Los importes ya no se calculan aquí: los da `Booking\Contracts\CartPricing`, el mismo contrato
     * que sirve `POST /api/v1/orders/quote`. Antes esta clase de interfaz contenía la aritmética del
     * dinero en tres métodos que recorrían la cesta por separado —el desglose, el total y lo que se
     * cobra online—, así que la API habría sido una cuarta copia y cualquier retoque en una sola de
     * ellas habría separado lo que se MUESTRA de lo que se COBRA.
     */
    private function quote(): CartQuote
    {
        $key = md5(serialize($this->cart));

        if ($this->quoteMemoFor !== $key) {
            $this->quoteMemo = $this->pricing->quote($this->cart);
            $this->quoteMemoFor = $key;
        }

        return $this->quoteMemo;
    }

    /**
     * Carrito enriquecido para la VISTA. El dinero viene tarificado del dominio ({@see quote()}) y
     * aquí solo se le da la forma que consume el blade.
     *
     * Lo único que se añade es `event`: las respuestas de los campos del pack, emparejadas con sus
     * etiquetas. No viaja en el contrato de tarificación porque no es dinero —y porque son datos
     * personales de un menor (`RGPD` §3): la API no los devuelve, el cliente ya los tiene—; la
     * pareja etiqueta/valor la resuelve `resolveEventData()`, que esta misma clase comparte con la
     * pantalla de confirmación.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cartLines(): array
    {
        return array_map(function (CartQuoteLine $line): array {
            $type = $this->allSellableTypes()->firstWhere('id', $line->productId);

            return [
                'index' => $line->index,
                'name' => $line->name,
                'date' => $line->date,
                'time' => $line->time,
                'qty' => $line->quantity,
                'is_pack' => $line->isPack,
                'event' => $this->resolveEventData($type, $this->cart[$line->index]['event_data'] ?? []),
                'addons' => array_map(fn (CartQuoteAddon $addon): array => [
                    'name' => $addon->name,
                    'qty' => $addon->quantity,
                    'free_qty' => $addon->freeQuantity,
                    'subtotal' => $addon->subtotalCents,
                ], $line->addons),
                'subtotal' => $line->subtotalCents,
                'has_deposit' => $line->hasDeposit,
                'deposit' => $line->depositCents,
                'gate_remainder' => $line->gateRemainderCents,
            ];
        }, $this->quote()->lines);
    }

    /**
     * Nº de líneas del carrito — cuenta SOLO las que resuelven a un producto vendible/operativo, la
     * MISMA fuente que `cartLines()`/`cartTotalCents()`. Así el badge nunca diverge del render
     * (antes `count($this->cart)` contaba líneas fantasma de productos ya no vendibles). (P8)
     *
     * Desde el paso 4a esa coincidencia deja de ser una convención y pasa a ser estructural: quien
     * decide qué línea se tarifica es el dominio, y el badge cuenta exactamente lo que devuelve.
     */
    public function cartCount(): int
    {
        return count($this->quote()->lines);
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

    /** Valor total de la cesta (principales + complementos), tarificado en servidor. */
    public function cartTotalCents(): int
    {
        return $this->quote()->totalCents;
    }

    /**
     * Importe a cobrar ONLINE de la cesta = la SEÑAL/DEPÓSITO (#225). ESPEJO EXACTO de lo que
     * `OrderCreator`/`Order::onlineDueCents()` cobrarán para ESTA misma cesta (canario anti
     * doble-fuente): por cada línea, `TicketType::depositCents(valor_línea)` (none → total);
     * y los complementos de un producto CON señal van 100% al parque (online 0; Opción A), los
     * de un producto sin señal se cobran online al completo.
     *
     * Desde el paso 4a la regla vive en `Booking\Services\CartPricer` y el «espejo exacto» dejó de
     * ser una promesa escrita en un comentario: `CartPricerTest` crea el pedido de verdad con la
     * misma cesta y compara los dos importes.
     */
    public function cartDepositCents(): int
    {
        return $this->quote()->onlineAmountCents;
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
        // La composición vive en `Http\Sidebar\RegistrationLink` desde que `GET /api/v1/config` es
        // el segundo consumidor (Fase 4 · paso 4.0b). Aquí solo queda la forma de array que esta
        // vista consume desde antes, y que muere con el componente en el paso 4.7.
        return RegistrationLink::current()?->toArray();
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
        //
        // Qué se vende lo decide el DOMINIO (`ProductCatalog`), no este componente: la API sirve
        // exactamente los mismos productos con los mismos campos (Fase 3 · paso 1b). Se pide UNA
        // vez y se parte por tipo en memoria — pedir dos veces filtrando por tipo costaría el doble
        // de consultas para el mismo resultado.
        $entries = [];
        $services = [];
        if ($this->step === 1) {
            $products = collect($this->catalog->products());
            $entries = $this->catalogSection($products->where('type', CatalogProduct::TYPE_ENTRY)->all());
            $services = $this->catalogSection($products->where('type', CatalogProduct::TYPE_PACK)->all());
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
