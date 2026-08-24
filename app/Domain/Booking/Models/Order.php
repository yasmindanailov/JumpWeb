<?php

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Concerns\HasItemActionGuards;
use App\Domain\Booking\Concerns\OrderOperativeStatus;
use App\Domain\Booking\Services\OrderFinancialSummary;
use App\Domain\Booking\Services\ReservationFinancials;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Concerns\GuardsItemRefunds;
use App\Domain\Payments\Concerns\OrderRefundFlags;
use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pedido (cesta confirmada). Nace `pending` con `expires_at` (retención de plaza);
 * pasa a `paid` con la confirmación de Redsys, que emite los `tickets`. Importes
 * en céntimos, calculados en servidor.
 */
class Order extends Model
{
    use GuardsItemRefunds;
    use HasItemActionGuards;
    use OrderOperativeStatus;
    use OrderRefundFlags;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @deprecated Desde sub-fase 7.2b ampliada (#139). El reembolso se modela ahora
     * como dimensión independiente (`refunded_at` + `refund_amount_cents`), no como
     * estado. Nuevas operaciones de reembolso transitan a `cancelled` (si también
     * se cancela el servicio) o mantienen `paid` (si solo se devuelve el dinero,
     * caso flexible del parque físico). Esta constante sobrevive para datos legacy.
     */
    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_EXPIRED = 'expired';

    /**
     * Lista blanca de asignación masiva (recomendación B, 2026-06-15). Antes era `$guarded = []`
     * (todo asignable): no había vector explotable —todos los `create()/update()` usan arrays
     * literales con valores de servidor— pero es defensa en profundidad. Excluye `id` y los
     * timestamps. Columnas que solo se fijan con `forceFill` (transición a paid en
     * `RedsysReturnHandler`, reembolsos) no necesitan estar, pero se incluyen las de negocio que
     * algún `create()/update()` sí asigna en masa.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'user_id',
        'code',
        'status',
        'subtotal',
        'tax',
        'total',
        'currency',
        'expires_at',
        'paid_at',
        'refunded_at',
        'refund_amount_cents',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'tax' => 'integer',
        'total' => 'integer',
        'refund_amount_cents' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Route Model Binding por `code` (`JJ-XXXX`) en lugar de `id` (sub-fase 7.2a refinada,
     * decisión #130). Anti-enumeración + URLs coherentes con el identificador público.
     *
     * Razón de ser global (no localizado a la Resource Filament): el `Resource::getUrl()`
     * de Filament 5 termina llamando al helper `route()` de Laravel, que SIEMPRE invoca
     * `$model->getRouteKey()` para resolver el valor a inyectar en la URL —
     * **`getRecordRouteKeyName()` solo afecta a la RESOLUCIÓN del binding, NO a la
     * generación de URLs**. Sin este override, al clicar una fila del listado Filament
     * generaba `/admin/orders/{id}` y el resolver buscaba por `code` → 404.
     *
     * Efecto colateral controlado: la ruta `/admin/pedidos/{order}/items/{item}/...`
     * (hoja de reserva PDF #183) también pasa a usar `code` en su URL. El controller
     * sigue funcionando porque Laravel resuelve el `Order $order` automáticamente por
     * el `getRouteKeyName()` configurado aquí.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Notifica al CLIENTE del pedido SOLO si tiene email enrutable (#263/#264-audit). Un cliente de
     * agenda sin email no debe encolar jobs de notificación muertos (el `MailChannel` los descarta en
     * silencio en el worker). Centraliza la guarda `filled(email)` que ya aplica `ManualOrderFulfiller`,
     * para usarla en todas las acciones del panel que avisan al cliente (cancelar/reembolsar/editar item).
     */
    public function notifyCustomer(Notification $notification): void
    {
        if ($this->user && filled($this->user->email)) {
            $this->user->notify($notification);
        }
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Items principales de PACK (no complementos, no cancelados) que piden el post-form de datos
     * por invitado (#217): los cuyo tipo define `guest_fields` (`guestFormStatus() !== null`). Lee
     * la relación `items` ya cargada (carga `items.ticketType` antes para evitar N+1). Fuente única
     * del «este pedido tiene / le falta el formulario».
     *
     * @return Collection<int, OrderItem>
     */
    public function guestFormItems(): Collection
    {
        return $this->items
            ->whereNull('parent_item_id')
            ->reject(fn (OrderItem $item): bool => $item->isCancelled())
            ->filter(fn (OrderItem $item): bool => $item->guestFormStatus() !== null)
            ->values();
    }

    /** ¿Este pedido tiene algún pack que pida el post-form por-niño (#217)? */
    public function hasGuestForm(): bool
    {
        return $this->guestFormItems()->isNotEmpty();
    }

    /** ¿Algún pack de este pedido tiene el post-form por-niño AÚN pendiente de rellenar (#217)? */
    public function needsGuestForm(): bool
    {
        return $this->guestFormItems()->contains(fn (OrderItem $item): bool => $item->needsGuestForm());
    }

    /**
     * Caducidad del ENLACE FIRMADO del post-form de invitados (auditoría Fase 1, A7). El enlace da
     * acceso SIN sesión a datos personales de MENORES (nombres, alergias), así que no puede ser
     * eterno: caduca en la fecha del EVENTO (la última franja del pedido) + 14 días de gracia —
     * cubre el uso legítimo (rellenar antes y poco después del cumpleaños) y acota el acceso RGPD.
     * Sin franjas (caso teórico) → 14 días desde ahora. Fuente ÚNICA para el email y las recargas.
     */
    public function guestFormLinkExpiresAt(): Carbon
    {
        $maxDate = Slot::whereIn('id', $this->items()->whereNotNull('slot_id')->select('slot_id'))->max('date');
        $base = $maxDate !== null ? Carbon::parse($maxDate)->endOfDay() : now();

        return $base->copy()->addDays(14);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Ajustes financieros del pedido fuera de Redsys (sub-fase 7.2e cimientos):
     * extras pendientes de cobro en puerta por ediciones que subieron precio.
     * Histórico inmutable — una fila por edición.
     *
     * @return HasMany<OrderAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    /**
     * ¿La Order admite un nuevo intento de pago? (audit edge cases 2026-05-28)
     *
     * Requisitos:
     *  - Order `pending` (no paid/expired/cancelled/refunded).
     *  - `expires_at` futuro (la plaza sigue retenida; si caducó, `orders:expire`
     *    o el filtro lazy de `SlotAvailability` ya cedió el aforo a otros).
     *  - Existe al menos un Payment intentado (`pending` o `failed`) — sin esto
     *    no tiene sentido un "reintento" (es una reserva provisional sin pasarela
     *    todavía: el flujo normal va por `proceed`/`confirmReservation`).
     *
     * Para usar desde Blade sin N+1: la query debe haber cargado `payments` con
     * `with('payments')`. Si no, `$this->payments` ejecuta una consulta extra.
     */
    public function canBeRetried(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->payments->contains(
            fn (Payment $p) => in_array($p->status, [Payment::STATUS_PENDING, Payment::STATUS_FAILED], true)
        );
    }

    /** No hay ningún intento de cobro abierto todavía. */
    public const PAYMENT_STATUS_NONE = 'none';

    /**
     * En qué ha quedado el ÚLTIMO intento de cobro (Fase 3 · paso 4d).
     *
     * Es el segundo eje del estado, y hacía falta porque el primero no lo cuenta todo: un pedido
     * `pending` cuya tarjeta fue rechazada y otro `pending` que nadie ha intentado pagar son la
     * misma cosa mirando solo a `status`. Hasta ahora ese matiz solo existía en la SESIÓN de la web
     * (`purchase.failed_code`), así que un cliente de API veía «pendiente» durante toda la ventana
     * de retención y después «caducado», **nunca «reintenta»** — teniendo el reintento disponible.
     *
     * Un pedido PAGADO responde `paid` aunque después se haya abierto otro intento: quien manda es
     * el cobro que triunfó (`PAY-01`), no el orden de creación.
     *
     * Sin N+1: carga `payments` con `with('payments')` antes de llamarlo.
     *
     * @return 'none'|'pending'|'authorized'|'paid'|'failed'|'superseded'
     */
    public function paymentStatus(): string
    {
        $payments = $this->payments;

        if ($payments->isEmpty()) {
            return self::PAYMENT_STATUS_NONE;
        }

        if ($payments->contains(fn (Payment $payment) => $payment->status === Payment::STATUS_PAID)) {
            return Payment::STATUS_PAID;
        }

        // El último ABIERTO, por orden de creación: `PaymentInitiator::reopen()` marca `SUPERSEDED`
        // los pendientes anteriores y crea uno nuevo, así que el de mayor id es el intento en curso.
        return (string) $payments->sortByDesc('id')->first()->status;
    }

    /**
     * Código `Ds_Response` del rechazo que el cliente acaba de sufrir, o `null`.
     *
     * Solo lo devuelve cuando el ÚLTIMO intento es el fallido: enseñar el motivo de un rechazo
     * anterior mientras hay otro cobro en curso le diría al cliente que su tarjeta ha fallado
     * cuando en realidad está esperando respuesta.
     *
     * Devuelve el CÓDIGO en crudo y no un texto: traducirlo es de quien pinta, y en la API además
     * el código es lo que un cliente puede programar (`RedsysResponseCode::reasonKey()`).
     * `raw_response` está filtrado por allowlist (#113 M1) y `Ds_Response` está dentro.
     */
    public function declinedResponseCode(): ?string
    {
        if ($this->paymentStatus() !== Payment::STATUS_FAILED) {
            return null;
        }

        $raw = $this->payments->sortByDesc('id')->first()?->raw_response;
        $code = is_array($raw) ? ($raw['Ds_Response'] ?? null) : null;

        return is_string($code) ? $code : null;
    }

    /**
     * ¿La Order está caducada *de hecho*, aunque su `status` siga siendo `pending`?
     * (Audit edge cases pulido 2026-05-28 #116.)
     *
     * Caso típico: el scheduler `orders:expire` corre cada 5 min (#113 A1) pero entre
     * dos ejecuciones, o en local SIN cron del sistema, una Order puede tener su
     * `expires_at` ya pasado mientras su `status` en BD sigue `pending`. La plaza,
     * sin embargo, ya pudo cederse lazy a otro cliente vía `SlotAvailability`
     * — la reserva ES expired aunque la BD no lo refleje aún.
     *
     * Sirve como input para `displayStatus()` y para cualquier vista que necesite
     * presentar el estado real al usuario sin esperar al próximo tick del scheduler.
     */
    public function isExpiredInPractice(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Estado a mostrar al cliente (vs el `status` crudo de BD). Resuelve el desfase
     * entre el `expires_at` real y el momento en que `orders:expire` actualiza la
     * columna `status`. Devuelve `'expired'` cuando la Order está caducada de hecho
     * (ver `isExpiredInPractice`); en cualquier otro caso, el `status` real.
     *
     * Conveniencia para Blade:
     *   <span class="orders__status orders__status--{{ $order->displayStatus() }}">
     *     {{ __('tickets.statuses.'.$order->displayStatus()) }}
     *   </span>
     */
    public function displayStatus(): string
    {
        return $this->isExpiredInPractice() ? self::STATUS_EXPIRED : $this->status;
    }

    /**
     * Suelta la plaza de un pedido cuyo cobro NUNCA llegó a abrirse (Fase 3 · paso 2).
     *
     * Si la preparación del pago falla, el pedido `pending` ya nació reteniendo aforo pero no tiene
     * ningún `Payment` asociado: nadie va a pagarlo y nadie va a cancelarlo. Dejarlo vivo
     * inmovilizaría plazas durante toda la ventana de retención por un fallo que ya sabemos que
     * ocurrió, así que se caduca en el acto y `orders:expire` no tiene que esperar a su hora.
     *
     * `expires_at` va un segundo en el PASADO —no `now()`— para que `isExpiredInPractice()` lo dé
     * por caducado sin depender de en qué microsegundo se lea.
     *
     * Solo tiene sentido sobre el primer cobro: en un REINTENTO el pedido ya existía y sigue vivo,
     * así que un fallo al reabrir el cobro no debe tocarlo.
     */
    public function releaseAfterFailedPaymentStart(): void
    {
        $this->forceFill([
            'status' => self::STATUS_EXPIRED,
            'expires_at' => now()->subSecond(),
        ])->save();
    }

    public const OPERATIVE_STATUS_ACTIVE = 'active';

    public const OPERATIVE_STATUS_IN_PROGRESS = 'in_progress';

    public const OPERATIVE_STATUS_FINISHED = 'finished';

    /**
     * Importe REALMENTE cobrado por el parque a día de hoy (#179): suma de los
     * pagos con éxito (`paid`) menos los reembolsos con éxito. Es la "caja" real
     * del pedido — distinta de `Order.total` (lo facturado): cubre pagos
     * parciales/señales, devoluciones y pedidos sin cobrar (→ 0). Nunca negativo.
     *
     * Lectura sin N+1: eager-load `payments.refunds`.
     */
    public function amountCollectedCents(): int
    {
        $paid = (int) $this->payments
            ->where('status', Payment::STATUS_PAID)
            ->sum('amount');

        $refunded = (int) $this->payments
            ->flatMap(fn (Payment $payment) => $payment->refunds)
            ->where('status', PaymentRefund::STATUS_SUCCEEDED)
            ->sum('amount_cents');

        return max(0, $paid - $refunded);
    }

    /**
     * Lo cobrado ONLINE que RESPALDA los productos actuales: la "caja" real (pagos
     * con éxito − reembolsos, legacy-safe) MENOS lo que aún se debe devolver
     * ({@see OrderFinancialSummary::pendienteDevolucion}). Es el "Pagado online" del
     * bloque valor-primero a nivel PEDIDO y la columna "Pagado" de la lista (junto a
     * "Total" = {@see OrderFinancialSummary::totalFinalNeto}). Fidedigno en TODOS los
     * estados: un pedido sin cobro da 0; uno con una bajada pendiente de reembolsar
     * no cuenta el dinero que sobra. Para un pedido pagado coincide con
     * `productsValue − extraDue` (= lo que muestran las cards).
     *
     * Lectura sin N+1: eager-load `payments.refunds` + `adjustments` + `items.slot`.
     */
    public function onlineBackingProductsCents(): int
    {
        $paid = (int) $this->payments
            ->where('status', Payment::STATUS_PAID)
            ->sum('amount');
        $refundedRows = (int) $this->payments
            ->flatMap(fn (Payment $payment) => $payment->refunds)
            ->where('status', PaymentRefund::STATUS_SUCCEEDED)
            ->sum('amount_cents');
        // Legacy-safe: los refunds pre-#142 viven solo en la columna agregada.
        $refunded = max($refundedRows, (int) ($this->refund_amount_cents ?? 0));
        $netHeld = max(0, $paid - $refunded);

        return max(0, $netHeld - $this->financialSummary()->pendienteDevolucion());
    }

    /**
     * Importe a cobrar ONLINE = la SEÑAL/DEPÓSITO (#225): Σ `itemCollectedCents` de los items
     * NO cancelados (principales + complementos). Es la FUENTE ÚNICA del importe online — la
     * consumirán `Payment.amount`, el `DS_MERCHANT_AMOUNT` de la ida Redsys, el reintento y el
     * pedido manual (iter. 2), garantizando que el canario `amount_mismatch` nunca diverja.
     *
     * En pedidos SIN señal (sin filas `deposit_remainder`) y recién creados equivale a
     * `Σ chargedSubtotalCents` (= el valor de los productos = `Order.total`). En cuanto
     * `OrderCreator` registre el resto-señal (iter. 2), bajará a `Σ depositCents(línea)`.
     *
     * Lectura sin N+1: eager-load `items` + `adjustments`.
     */
    public function onlineDueCents(): int
    {
        $sum = 0;
        foreach ($this->items as $item) {
            if ($item->isCancelled()) {
                continue;
            }
            $sum += $this->itemCollectedCents($item);
        }

        return $sum;
    }

    /**
     * Total REAL del pedido incluyendo cambios (#179): total original + cargos
     * extra por ediciones (`extra_due`; p. ej. un complemento añadido a cobrar en
     * puerta) − reembolsos. Es el "Total con cambios" que muestra el detalle del
     * pedido (`order-totals.blade`), reusado por la columna Total de la lista para
     * que refleje la realidad.
     *
     * Los `extra_due` de un item CANCELADO se ANULAN (mismo criterio que
     * {@see OrderFinancialSummary::extraDue()}): el cargo era por algo que se quitó
     * antes de cobrarlo (p. ej. un complemento sustituido en un cambio de menú), así
     * que NO infla el total — si no, la columna Total de la lista divergiría del
     * detalle. Reembolsos vía `refund_amount_cents` (legacy-safe, deliberado, igual
     * que `order-totals.blade`). Lectura sin N+1: eager-load `adjustments` + `items`.
     */
    public function totalWithChangesCents(): int
    {
        $cancelledItemIds = $this->items
            ->filter(fn (OrderItem $i) => $i->isCancelled())
            ->map(fn (OrderItem $i) => (int) $i->id)
            ->all();

        $extraDue = (int) $this->adjustments
            ->where('type', OrderAdjustment::TYPE_EXTRA_DUE)
            ->reject(fn (OrderAdjustment $adj) => $adj->order_item_id !== null
                && in_array((int) $adj->order_item_id, $cancelledItemIds, true))
            ->sum('amount_cents');

        return (int) $this->total + $extraDue - (int) ($this->refund_amount_cents ?? 0);
    }

    /**
     * ¿Razón por la que la Order NO puede cancelarse? `null` = sí puede.
     *
     * Defense in depth (sub-fase 7.2b, patrón #128 reusado): la acción del panel
     * comprueba aquí ANTES de tocar BD; si devuelve un motivo, no transiciona el
     * status y registra `orders.cancel_blocked` con la razón estructurada.
     *
     * Bloqueos:
     *  - `already_cancelled` — el Order ya está en estado cancelled.
     *  - `already_refunded`  — solo data legacy con `status=refunded` (el modelo nuevo
     *    no usa ese status; refund-only deja `status=paid` y permite cancelar después).
     *  - `expired`           — la Order caducó (status DB o `displayStatus()` lo refleja).
     *  - `already_finished`  — todos los items principales pasaron por la puerta
     *    (`OPERATIVE_STATUS_FINISHED`). Cancelar un servicio ya prestado es semánticamente
     *    incorrecto. El reembolso sí queda disponible — una queja post-servicio
     *    es razón legítima para devolver dinero, pero la cancelación del servicio
     *    no aplica si ya ocurrió (#141).
     *
     * Las Orders `pending` y `paid` (con servicio aún no finalizado) SÍ pueden
     * cancelarse. Crucialmente, una Order `paid` con `refunded_at` ya seteado
     * (reembolso sin cancelación, flexibilidad del parque físico) PUEDE cancelarse
     * después si el servicio aún no ha empezado — el reembolso no es bloqueante
     * para la cancelación del servicio (#139).
     */
    public function cancellationBlockedReason(): ?string
    {
        return match (true) {
            $this->status === self::STATUS_CANCELLED => 'already_cancelled',
            $this->status === self::STATUS_REFUNDED => 'already_refunded',
            $this->displayStatus() === self::STATUS_EXPIRED => 'expired',
            $this->status === self::STATUS_PAID
                && $this->displayOperativeStatus() === self::OPERATIVE_STATUS_FINISHED => 'already_finished',
            default => null,
        };
    }

    public function canBeCancelled(): bool
    {
        return $this->cancellationBlockedReason() === null;
    }

    /**
     * ¿Razón por la que la Order NO puede reembolsarse? `null` = sí puede.
     *
     * Solo accesible si Order `paid` y NO reembolsado previamente. El flujo
     * operativo del parque (#139) ofrece una alternativa explícita en el modal:
     * Toggle "También cancelar el pedido" — refund-only (paid + refunded_at SET)
     * cubre el caso del canje en persona, refund+cancel cubre el textbook.
     *
     * Sub-fase 7.2b corrección (#140): SE BLOQUEA refund sobre Orders ya canceladas.
     * Mi extensión del turno anterior (que permitía "cancelar primero, devolver
     * después") era una sobreextensión no pedida y generaba un botón confuso. Si
     * un caso de uso real requiere reembolsar lo previamente cancelado, eso es
     * operativa banco directa (portal Redsys) y queda fuera del panel.
     *
     * Importante: el panel NO ejecuta el reembolso real en Redsys — eso es operativa
     * banco. Solo registra `refunded_at` + `refund_amount_cents` y dispara
     * `OrderRefunded` con texto explícito sobre el plazo bancario.
     */
    public function refundBlockedReason(): ?string
    {
        return match (true) {
            $this->isRefunded() => 'already_refunded',
            $this->status === self::STATUS_CANCELLED => 'already_cancelled',
            $this->status !== self::STATUS_PAID => 'not_paid',
            default => null,
        };
    }

    public function canBeRefunded(): bool
    {
        return $this->refundBlockedReason() === null;
    }

    // ─── Reenvío unificado de emails (sub-fase 7.2b corrección #140) ──────
    //
    // Una sola acción "Reenviar email" en el panel con Select de tipos. Cada tipo
    // solo aparece si el evento subyacente OCURRIÓ en el pedido — reenviar un
    // email sobre un evento que no pasó es comunicación falsa al cliente. Las 3
    // reglas que decide este modelo:
    //  - confirmation:  $status === paid (Order tiene `paid_at`, servicio activo).
    //  - refund:        $this->isRefunded() (refunded_at SET o legacy refunded).
    //  - cancellation:  $status === cancelled.
    //  - payment_retry: $this->canBeRetried() (pending + expires futuro + intento).
    //
    // El handler del panel revalida con `canResend($type)` ANTES de notify
    // (defense in depth #128).

    public const RESEND_TYPE_CONFIRMATION = 'confirmation';

    public const RESEND_TYPE_REFUND = 'refund';

    public const RESEND_TYPE_CANCELLATION = 'cancellation';

    public const RESEND_TYPE_PAYMENT_RETRY = 'payment_retry';

    /** Reenvío del enlace al post-form de datos por invitado (#217). Solo packs pagados que lo piden. */
    public const RESEND_TYPE_GUEST_FORM = 'guest_form';

    /**
     * @return list<string> subconjunto de RESEND_TYPE_* aplicables al estado actual.
     */
    public function availableResendEmailTypes(): array
    {
        // Cliente de agenda SIN email (#263): NO hay ningún email que reenviar (todos los tipos son
        // correos). Devolver vacío oculta la acción «Reenviar email» del panel y evita el falso
        // «Reenviado a » (con dirección vacía) y el audit log engañoso. El enlace del post-form se
        // entrega con el icono «enlace» del producto, no por correo.
        if (! filled($this->user?->email)) {
            return [];
        }

        $types = [];

        if ($this->status === self::STATUS_PAID) {
            $types[] = self::RESEND_TYPE_CONFIRMATION;
        }

        if ($this->isRefunded()) {
            $types[] = self::RESEND_TYPE_REFUND;
        }

        if ($this->status === self::STATUS_CANCELLED) {
            $types[] = self::RESEND_TYPE_CANCELLATION;
        }

        if ($this->canBeRetried()) {
            $types[] = self::RESEND_TYPE_PAYMENT_RETRY;
        }

        // Reenviar el enlace del post-form por-niño (#217): pedido pagado con un pack que lo pide
        // (relleno o no — el operador puede reenviar el enlace si el cliente perdió el email).
        if ($this->status === self::STATUS_PAID && $this->hasGuestForm()) {
            $types[] = self::RESEND_TYPE_GUEST_FORM;
        }

        return $types;
    }

    public function canResend(string $type): bool
    {
        return in_array($type, $this->availableResendEmailTypes(), true);
    }

    public function canResendAnyEmail(): bool
    {
        return $this->availableResendEmailTypes() !== [];
    }

    /**
     * Último `Payment` con `status=failed` — usado para reenviar el email "completa
     * tu pago" con el `dsResponse` real del banco, para que el cliente vea el motivo
     * concreto en el correo (igual que cuando recibe el email tras el denial #114).
     *
     * Si no hay Payment failed (el cliente abandonó sin intentar, o solo hay pending),
     * devuelve `null` y `OrderPaymentDeclined` muestra el motivo genérico.
     */
    public function lastFailedPayment(): ?Payment
    {
        return $this->payments()
            ->where('status', Payment::STATUS_FAILED)
            ->latest('updated_at')
            ->first();
    }

    /**
     * Último `Payment` con estado `paid`. Usado en el flujo de reembolso (#7.2b) para
     * pasar el `amount` al notification `OrderRefunded`: el plan reserva importes
     * parciales para v2; en v1 igual al `Order.total`, pero pasamos el Payment
     * concreto para que el email cite el cobro real y no un agregado del Order.
     *
     * Devuelve `null` si no hay Payment paid (caso defensivo: el flujo debería
     * haberlo bloqueado antes por `canBeRefunded()`).
     */
    public function paidPayment(): ?Payment
    {
        return $this->payments()
            ->where('status', Payment::STATUS_PAID)
            ->latest('paid_at')
            ->first();
    }

    /**
     * Orquesta el reembolso TOTAL del pedido (sub-fase 7.2b extendida, #142).
     *
     * Dos modos:
     *  - `rest` (default operativo): hace la llamada REST a Redsys (TransactionType=3)
     *    y actualiza estado SOLO si Ds_Response=0900. Si REST falla por red/timeout o
     *    el banco deniega, el Order queda intacto y se devuelve `ok=false` con el
     *    motivo categorizado para que la UI muestre el mensaje correcto al operador.
     *  - `manual`: registra el reembolso sin tocar Redsys (caso back-fill — el
     *    operador ya devolvió desde el portal antes y quiere reflejarlo aquí).
     *
     * Defense in depth + concurrencia:
     *  - Txn 1 con `lockForUpdate` sobre el Order: revalida `refundBlockedReason()` y
     *    verifica que no haya OTRO `PaymentRefund` pending sobre el mismo Payment
     *    (un segundo click concurrente verá la fila y se quedará fuera).
     *  - REST call **fuera** de cualquier txn: 10s de timeout no deben mantener locks.
     *  - Txn 2: vuelve a tomar el lock, finaliza la fila refund + actualiza Order +
     *    audit log. Si REST falló, Order intacto.
     *  - Email POST-commit (responsabilidad del caller — devolvemos los datos).
     *
     * @return array{
     *   ok:bool,
     *   reason?:string,
     *   refund?:PaymentRefund,
     *   order?:Order,
     *   payment?:Payment,
     *   also_cancelled?:bool,
     *   gateway_response_code?:?string,
     *   failure_message?:?string,
     * }
     */
    public function executeFullRefund(User $by, string $mode, bool $alsoCancel): array
    {
        $payment = $this->paidPayment();
        if ($payment === null) {
            return ['ok' => false, 'reason' => 'no_paid_payment'];
        }

        // Txn 1: lock + revalidate + create pending row. Serializa contra clicks
        // concurrentes y materializa la "intención" antes de la REST call.
        try {
            $refund = DB::transaction(function () use ($by, $mode, $payment) {
                /** @var Order $locked */
                $locked = self::query()->lockForUpdate()->find($this->id);

                $reason = $locked->refundBlockedReason();
                if ($reason !== null) {
                    throw new \DomainException('blocked:'.$reason);
                }

                $inflight = PaymentRefund::query()
                    ->where('payment_id', $payment->id)
                    ->where('status', PaymentRefund::STATUS_PENDING)
                    ->exists();
                if ($inflight) {
                    throw new \DomainException('inflight_refund');
                }

                return PaymentRefund::create([
                    'payment_id' => $payment->id,
                    'order_item_id' => null,
                    'amount_cents' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => PaymentRefund::STATUS_PENDING,
                    'mode' => $mode,
                    'gateway_order' => $payment->gateway_order,
                    'requested_by' => $by->id,
                    'requested_at' => now(),
                ]);
            });
        } catch (\DomainException $e) {
            $reason = str_replace('blocked:', '', $e->getMessage());

            return ['ok' => false, 'reason' => $reason];
        }

        // Step 2: REST call FUERA de la txn. En modo manual saltamos esta fase.
        $restResult = $mode === PaymentRefund::MODE_REST
            ? app(RefundGateway::class)->executeRefund($payment, $payment->amount)
            : null;

        // Txn 2: finalizar refund + actualizar Order o registrar fallo.
        return DB::transaction(function () use ($by, $mode, $alsoCancel, $payment, $refund, $restResult): array {
            /** @var Order $order */
            $order = self::query()->lockForUpdate()->find($this->id);
            $now = now();

            // Rama "fallo de REST": registramos fallo en la fila, NO tocamos Order,
            // NO email. El operador ve el mensaje y decide reintentar (REST) o
            // verificar manualmente en el portal Redsys (transport_error).
            if ($mode === PaymentRefund::MODE_REST && ! $restResult->success) {
                $refund->update([
                    'status' => PaymentRefund::STATUS_FAILED,
                    'processed_at' => $now,
                    'gateway_response_code' => $restResult->dsResponse,
                    'raw_response' => $restResult->rawResponse,
                    'failure_reason' => $restResult->failureReason,
                    'failure_message' => $restResult->message,
                ]);

                AuditLogger::log(
                    action: 'orders.refund_failed',
                    target: $order,
                    payload: [
                        'order_code' => $order->code,
                        'mode' => $mode,
                        'refund_id' => $refund->id,
                        'failure_reason' => $restResult->failureReason,
                        'ds_response' => $restResult->dsResponse,
                    ],
                );

                return [
                    'ok' => false,
                    'reason' => 'gateway_failed',
                    'refund' => $refund->refresh(),
                    'gateway_response_code' => $restResult->dsResponse,
                    'failure_message' => $restResult->message,
                ];
            }

            // Rama éxito (REST 0900 o modo manual): marca fila succeeded, transita Order.
            $refund->update([
                'status' => PaymentRefund::STATUS_SUCCEEDED,
                'processed_at' => $now,
                'gateway_response_code' => $mode === PaymentRefund::MODE_REST
                    ? $restResult->dsResponse
                    : PaymentRefund::MANUAL_RESPONSE_MARKER,
                'raw_response' => $restResult?->rawResponse,
            ]);

            $previousStatus = $order->status;
            $alsoCancelApplied = $alsoCancel && $order->canBeCancelled();

            $order->refunded_at = $now;
            // Agregado de TODOS los succeeded (igual que el reembolso parcial, Order.php ~1574), no la
            // sobreescritura cruda con `payment->amount` (auditoría Fase 1, M5 p.3). Un full solo es
            // alcanzable sin refunds previos (refundBlockedReason → already_refunded), así que hoy es
            // equivalente; el agregado lo blinda si esa precondición cambiara.
            $order->load(['payments.refunds']);
            $order->refund_amount_cents = $order->totalRefundedCents();
            if ($alsoCancelApplied) {
                $order->status = self::STATUS_CANCELLED;
            }
            $order->save();

            // ⚠️⚠️ **Cancelar el PEDIDO cancela sus RESERVAS** (`DECISIONES #127`). Sin esto el
            // pedido queda cancelado pero sus líneas vivas, así que `productsValue` las sigue
            // sumando y el cliente lee «Total 19,80 €» sobre un pedido cancelado cuyo dinero el
            // parque retiene. La conducta correcta ya existía un nivel más abajo —cancelar la
            // RESERVA sí deja el desglose correcto—; esto la sube al nivel del pedido.
            if ($alsoCancelApplied) {
                $order->cancelLiveItems($by);
            }

            AuditLogger::log(
                action: 'orders.refunded',
                target: $order,
                payload: [
                    'order_code' => $order->code,
                    'mode' => $mode,
                    'refund_id' => $refund->id,
                    'previous_status' => $previousStatus,
                    'also_cancel_requested' => $alsoCancel,
                    'also_cancelled' => $alsoCancelApplied,
                    'payment_id' => $payment->id,
                    'amount_cents' => $payment->amount,
                    'gateway_response_code' => $restResult?->dsResponse ?? PaymentRefund::MANUAL_RESPONSE_MARKER,
                ],
            );

            return [
                'ok' => true,
                'refund' => $refund->refresh(),
                'order' => $order->refresh(),
                'payment' => $payment,
                'also_cancelled' => $alsoCancelApplied,
                'gateway_response_code' => $restResult?->dsResponse ?? PaymentRefund::MANUAL_RESPONSE_MARKER,
            ];
        });
    }

    // ─── Sub-fase 7.2e cimientos — gestión per-item ────────────────────────
    //
    // Las acciones operativas (modal Gestionar, botones cancelar/reembolsar item)
    // se entregan en 7.2e.1+. Aquí van los HELPERS DE DEFENSA y los ORQUESTADORES
    // backend que esas acciones invocarán. Defense in depth en 3 capas: la UI
    // comprueba `can*Item()` para mostrar/ocultar, el handler revalida con la fila
    // fresca + `*BlockedReason()`, y el orquestador toma `lockForUpdate` y vuelve
    // a comprobar dentro de la transacción.

    /**
     * Resumen financiero canónico (sub-fase 7.2e cimientos). Encapsula los 7
     * cálculos que el panel y "Mis pedidos" muestran. Para evitar N+1 cargar
     * `with(['payments.refunds', 'adjustments', 'items.slot'])` antes.
     */
    public function financialSummary(): OrderFinancialSummary
    {
        return OrderFinancialSummary::fromOrder($this);
    }

    /**
     * Desglose financiero POR RESERVA (cada principal NO cancelado + sus
     * complementos), para el bloque "Totales del pedido" valor-primero. Reusa la
     * fuente ÚNICA {@see ReservationFinancials} de las cards de producto → el
     * bloque del pedido es la SUMA EXACTA de las cards, con el mismo vocabulario
     * (pagado online / a cobrar en el parque / pagado en el parque / devuelto /
     * pendiente de devolución) por construcción.
     *
     * Excluye los principales "fantasma" net-cero ({@see isVoidedLeftoverItem},
     * #F11). Lectura sin N+1: eager-load `items.children.ticketType` + `items.slot`
     * + `adjustments` + `payments.refunds`.
     *
     * @return list<array{name:string, rf:ReservationFinancials}>
     */
    public function reservationFinancialsByPrincipal(): array
    {
        return $this->items
            ->whereNull('parent_item_id')
            ->reject(fn (OrderItem $i) => $this->isVoidedLeftoverItem($i))
            ->map(fn (OrderItem $principal) => [
                'name' => $principal->ticketType?->tr('name') ?? '—',
                'rf' => ReservationFinancials::make($this, $principal),
            ])
            ->values()
            ->all();
    }

    /**
     * Líneas NETAS del desglose "A cobrar en el parque", AGRUPADAS por item.
     *
     * Cada item suma sus `extra_due` CON SIGNO: el crédito de una bajada
     * ({@see applyGateCredit}) netea contra el cargo de una subida
     * ({@see applyExtraDue}), de modo que subir y bajar la misma cantidad deja
     * neto 0 y NO aparece. Reemplaza el listado fila-por-ajuste, que pintaba un
     * renglón por cada subida histórica → cargos fantasma al subir y bajar
     * (bug JJ-WIMWJW: "↳ +1 Jump" ×3 cuando el neto real era 0). Los items
     * finalizados o cancelados quedan fuera (su cargo ya se cobró en puerta o se
     * anuló), igual que {@see OrderFinancialSummary::pendingAtGate()} — por eso
     * `Σ amount de estas líneas == pendingAtGate()` (el desglose cuadra con el
     * total), garantizado por el invariante "neto por item ≥ 0".
     *
     * Etiqueta: si el neto es múltiplo exacto del precio unitario actual y no
     * hubo cambio de producto, se reconstruye "+N producto" con la cantidad
     * NETA (fidedigna); si hubo cambio de producto con un único cargo, se reusa
     * su etiqueta compacta ("Cambio a X"); en otro caso, el nombre del producto.
     * El importe es siempre el neto autoritativo.
     *
     * Lectura sin N+1: eager-load `adjustments` + `items.ticketType` + `items.slot` (este último
     * para que {@see itemFinishedInPractice} resuelva el estado del principal sin tocar BD).
     *
     * @param  list<int>|null  $onlyItemIds  si se pasa, acota a esos items (scope
     *                                       reserva: principal + sus complementos;
     *                                       reusado por la hoja de reserva PDF).
     * @return list<array{label:string, amount:int}>
     */
    public function pendingAtGateLines(?array $onlyItemIds = null): array
    {
        $byItem = [];
        foreach ($this->adjustments as $adj) {
            if ($adj->type !== OrderAdjustment::TYPE_EXTRA_DUE) {
                continue;
            }
            $itemId = $adj->order_item_id;
            if ($onlyItemIds !== null && ($itemId === null || ! in_array((int) $itemId, $onlyItemIds, true))) {
                continue;
            }
            $item = $itemId !== null ? $this->items->firstWhere('id', $itemId) : null;
            // Item cerrado (finalizado/cancelado) → su extra_due ya está resuelto
            // o anulado: no pendiente. Mismo criterio que pendingAtGate(). Usamos el
            // resolvedor SIN N+1 (el parent de un complemento se busca en `items`, ya
            // cargada, no vía la relación perezosa `parent`).
            if ($item !== null && ($this->itemGateResolved($item) || $item->isCancelled())) {
                continue;
            }
            $key = $itemId ?? 'order';
            if (! isset($byItem[$key])) {
                $byItem[$key] = ['amount' => 0, 'item' => $item, 'hasProductChange' => false, 'positives' => []];
            }
            $byItem[$key]['amount'] += (int) $adj->amount_cents;
            $ctx = is_array($adj->context) ? $adj->context : [];
            if (isset($ctx['changes']['product_change'])) {
                $byItem[$key]['hasProductChange'] = true;
            }
            if ((int) $adj->amount_cents > 0) {
                $byItem[$key]['positives'][] = $adj;
            }
        }

        $lines = [];
        foreach ($byItem as $entry) {
            if ($entry['amount'] <= 0) {
                continue;
            }
            $lines[] = ['label' => $this->gateLineLabel($entry), 'amount' => $entry['amount']];
        }

        return $lines;
    }

    /**
     * Etiqueta de una línea neteada del desglose de puerta. Ver el contrato en
     * {@see pendingAtGateLines()}.
     *
     * @param  array{amount:int, item:?OrderItem, hasProductChange:bool, positives:array<int,OrderAdjustment>}  $entry
     */
    private function gateLineLabel(array $entry): string
    {
        $item = $entry['item'];
        $name = $item?->ticketType?->tr('name') ?? '—';
        $unit = (int) ($item?->unit_price ?? 0);

        // "+N producto" con la cantidad NETA cuando el cargo deriva de cambios de
        // cantidad (no de producto) y el neto es múltiplo exacto del precio actual.
        if (! $entry['hasProductChange'] && $unit > 0 && $entry['amount'] % $unit === 0) {
            return '+'.intdiv($entry['amount'], $unit).' '.$name;
        }

        // Cambio de producto con un único cargo → su etiqueta compacta ("Cambio a X").
        if (count($entry['positives']) === 1) {
            return $entry['positives'][0]->breakdownLabel();
        }

        return $name;
    }

    /**
     * ¿Item finalizado en la práctica, resuelto SIN N+1? Un complemento HEREDA el estado
     * «finalizado» de su principal ({@see OrderItem::isFinishedInPractice}); aquí buscamos ese
     * principal en la colección `items` (YA cargada en todas las superficies de desglose) en vez
     * de tirar de la relación perezosa `parent`, que dispararía una consulta por cada cargo de
     * puerta atado a un complemento (N+1 detectado en la revisión adversarial de F2). Requiere
     * `items` (con `items.slot` para resolver el slot del principal) eager-loaded en el caller.
     */
    private function itemFinishedInPractice(OrderItem $item): bool
    {
        if ($item->parent_item_id !== null) {
            return $this->items->firstWhere('id', $item->parent_item_id)?->isFinishedInPractice() ?? false;
        }

        return $item->isFinishedInPractice();
    }

    /**
     * ¿El cargo de puerta de este item está RESUELTO, es decir, ya cobrado en el parque?
     *
     * ⚠️⚠️ Son DOS condiciones y hay que cumplirlas las dos: que su franja haya pasado **y que el
     * pedido se haya cobrado** (`DECISIONES #127`). Un checkout abandonado cuya franja pasa no cobró
     * nada en recepción, y darlo por cobrado hacía que el panel anunciara «Pagado en el parque X €»
     * de dinero que nunca existió.
     *
     * ⚠️ Este predicado y el de {@see OrderFinancialSummary} tienen que decir LO MISMO: si divergen,
     * el desglose ↳ deja de sumar su titular. La identidad `D` del test de invariantes lo caza —de
     * hecho lo cazó al introducir esta regla, cuando solo se había corregido el agregado—.
     */
    private function itemGateResolved(OrderItem $item): bool
    {
        return $this->itemFinishedInPractice($item) && $this->paid_at !== null;
    }

    /**
     * Desglose ↳ de «A cobrar en el parque» de UNA reserva (principal + sus complementos),
     * con etiqueta + importe por componente (#225 F2). Reúne los dos «buckets» de puerta:
     *  - los cargos NETOS de EDICIÓN ({@see pendingAtGateLines} acotado a la reserva), p. ej.
     *    «+2 Cumpleaños Jump» (las subidas y bajadas se netean por item);
     *  - el RESTO DE LA SEÑAL pendiente (una sola línea «Resto de la señal»), que NO aparece en
     *    `pendingAtGateLines` (esa solo netea `extra_due` de ediciones).
     *
     * **Invariante de reconciliación** (test obligatorio): la Σ de los importes que devuelve
     * == `ReservationFinancials::make($this, $principal)->aCobrarPuerta`, de modo que el desglose
     * de la card del producto SIEMPRE cuadra con su agregado. Usa el MISMO criterio
     * finalizado/cancelado que {@see ReservationFinancials} (los complementos heredan el estado
     * del principal) → una reserva finalizada o cancelada devuelve `[]`.
     *
     * Helper de PANEL: etiqueta el resto-señal con la clave i18n
     * `admin.orders.item_financial.deposit_remainder_line`. «Mis pedidos» del cliente arma su
     * propio desglose a nivel PEDIDO (claves `tickets.*`) con {@see pendingAtGateLines}.
     *
     * @return list<array{label:string, amount:int}>
     */
    public function reservationGateLines(OrderItem $principal): array
    {
        $items = collect([$principal])->merge($principal->children);
        $itemIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Bucket 1: cargos de edición netos, ya etiquetados ("+N producto" / "Cambio a X").
        $lines = $this->pendingAtGateLines($itemIds);

        // Bucket 2: resto de la señal pendiente de la reserva. Mismo filtro que
        // ReservationFinancials (el principal manda el estado «finalizado»; los complementos
        // lo heredan) para que la Σ cuadre con `aCobrarPuerta`. En pedidos sin señal no hay
        // filas `deposit_remainder` → 0 → ninguna línea extra (legacy idéntico).
        $principalFinished = $principal->isFinishedInPractice();
        $depositRemainder = 0;
        foreach ($items as $item) {
            $finished = $item->parent_item_id === null
                ? $item->isFinishedInPractice()
                : $principalFinished;
            if ($finished || $item->isCancelled()) {
                continue;
            }
            $depositRemainder += $this->itemDepositRemainderCents($item);
        }

        if ($depositRemainder > 0) {
            // #225 (feedback clienta 2026-06-10): la línea «Resto de la señal» nombra SU producto
            // («de Cumpleaños Jump») para dar contexto al importe. La card es de una sola reserva,
            // así que el producto es el principal.
            $lines[] = [
                'label' => __('admin.orders.item_financial.deposit_remainder_line')
                    .' '.__('admin.orders.deposit_for_product', ['product' => $principal->ticketType?->tr('name') ?? '—']),
                'amount' => $depositRemainder,
            ];
        }

        return $lines;
    }

    /**
     * **El desglose ENTERO de «a cobrar en el parque», con sus etiquetas ya compuestas.**
     *
     * Es la suma de los dos buckets que el cliente ve como una sola lista: los cargos por cambios
     * ({@see pendingAtGateLines}) y el resto de la señal por producto
     * ({@see depositRemainderPendingByProduct}). Σ de los importes == `OrderFinancialSummary::
     * pendingAtGate()`, que es el agregado que ya publican todas las superficies.
     *
     * ⚠️ **Nace en la tanda 3 del área de cliente (2026-08-22) y el motivo es una regla del proyecto,
     * no una comodidad**: la etiqueta del resto de la señal —«Resto de la señal de Cumple Jump»— se
     * componía **en Blade**, juntando dos claves de `lang/` en la propia plantilla. Publicarla por la
     * API habría hecho que esa fórmula viviera en dos sitios, que es exactamente cómo divergieron las
     * cuatro copias del rótulo de día (`DECISIONES #120(j)`). Aquí se compone UNA vez y la consumen
     * la página y el contrato.
     *
     * ⚠️ **El orden importa y es el de la página**: primero los cambios, después el resto de la señal.
     * Un cliente que las pinte en otro orden enseña un desglose distinto del que el cliente ya conoce.
     *
     * @return list<array{label:string, amount:int}>
     */
    public function gateBreakdownLines(): array
    {
        $lines = $this->pendingAtGateLines();

        foreach ($this->depositRemainderPendingByProduct() as $remainder) {
            $lines[] = [
                'label' => __('tickets.deposit_remainder_line').' '.__('tickets.deposit_for_product', ['product' => $remainder['name']]),
                'amount' => $remainder['amount'],
            ];
        }

        return $lines;
    }

    /**
     * «Resto de la señal» pendiente DESGLOSADO POR PRODUCTO (#225, feedback clienta 2026-06-10).
     * Una línea por producto PRINCIPAL con señal cuyo resto sigue pendiente (no finalizado ni
     * cancelado): si hay dos packs con señal, salen dos líneas «Resto de la señal de X / de Y».
     * Σ de los importes == `OrderFinancialSummary`: `depositRemainder − depositRemainderResolved`
     * (lo que las superficies a nivel PEDIDO muestran como resto-señal).
     *
     * Auditoría Fase 1 (L4): el resto-señal de los COMPLEMENTOS de un producto con señal (Opción A
     * #225: el complemento cobrable se materializa como `deposit_remainder` ATADO al child, no como
     * `extra_due`) se AGREGA a la línea de su principal. Antes se omitía (solo se iteraban
     * principales) y el desglose no sumaba el titular `pendingAtGate()`. Los complementos heredan el
     * estado finalizado/cancelado del principal (mismo criterio que {@see reservationGateLines}).
     *
     * @return list<array{name:string, amount:int}>
     */
    public function depositRemainderPendingByProduct(): array
    {
        $lines = [];
        foreach ($this->items as $item) {
            if ($item->parent_item_id !== null || $item->isCancelled() || $this->itemGateResolved($item)) {
                continue;
            }
            // Resto-señal del principal + el de SUS complementos no cancelados (el principal, no
            // finalizado por el check de arriba, "tira" del estado de los hijos).
            $rem = $this->itemDepositRemainderCents($item);
            foreach ($item->children as $child) {
                if ($child->isCancelled()) {
                    continue;
                }
                $rem += $this->itemDepositRemainderCents($child);
            }
            if ($rem > 0) {
                $lines[] = ['name' => $item->ticketType?->tr('name') ?? '—', 'amount' => $rem];
            }
        }

        return $lines;
    }

    /**
     * Importe ya devuelto a este Order (suma de `payment_refunds.succeeded`).
     * Atajo defensivo para no recalcular en sitios que ya saben qué buscan.
     */
    /** Σ de lo devuelto sobre una RESERVA entera (principal + sus complementos). */
    public function reservationRefundedCents(OrderItem $principal): int
    {
        $sum = $this->itemRefundedCents($principal);
        foreach ($principal->children as $child) {
            $sum += $this->itemRefundedCents($child);
        }

        return $sum;
    }

    /**
     * La parte de la COMPENSACIÓN del pedido que le toca a ESTA reserva.
     *
     * La compensación —dinero devuelto sin que desapareciera producto— se define **a nivel de
     * PEDIDO y anclada a caja** ({@see OrderFinancialSummary::compensado}), porque la versión
     * por-línea sobre-reporta cuando la pérdida de valor no deja huella en el ítem (`#225`). Aquí se
     * REPARTE, no se redefine: **una sola fórmula, un solo número**.
     *
     * Reparto en CASCADA por `id` de principal, tomando cada reserva como mucho lo que ella misma
     * tiene devuelto. Es exacto —la compensación nunca supera el total devuelto— y determinista.
     * Con una sola reserva, que es el caso normal, se la lleva entera.
     */
    public function reservationCompensatedCents(OrderItem $principal): int
    {
        $remaining = $this->financialSummary()->compensado();
        if ($remaining <= 0) {
            return 0;
        }

        foreach ($this->items->whereNull('parent_item_id')->sortBy('id') as $p) {
            $take = min($remaining, $this->reservationRefundedCents($p));
            if ((int) $p->id === (int) $principal->id) {
                return $take;
            }
            $remaining -= $take;
        }

        return 0;
    }

    /**
     * Cancela las RESERVAS vivas de este pedido — la cascada que faltaba al cancelar el PEDIDO.
     *
     * ⚠️⚠️ **Un pedido cancelado no tiene valor vivo** (`DECISIONES #127`). Hasta esta corrección,
     * cancelar el pedido —por la acción del panel o por un reembolso total con «también cancelar»—
     * dejaba sus líneas ACTIVAS: `productsValue` las seguía sumando, el cliente leía «Total 19,80 €»
     * sobre un pedido cancelado, y **nada anunciaba que el parque retenía ese dinero**. Medido: la
     * MISMA operación un nivel más abajo (cancelar la reserva) sí dejaba el desglose correcto.
     *
     * Reusa {@see OrderItem::markCancelled}, que es idempotente y preserva la fecha de la primera
     * cancelación, así que una línea ya cancelada a mano conserva la suya. Los complementos se
     * cancelan explícitamente porque `isCancelled()` mira su propia columna, no la del principal.
     *
     * No toca Redsys ni ajustes: el lado financiero lo resuelven las dimensiones al recalcularse
     * (lo cobrado online sin producto detrás aflora como «pendiente de devolución»).
     */
    public function cancelLiveItems(User $by): void
    {
        $this->load('items');
        foreach ($this->items as $item) {
            if (! $item->isCancelled()) {
                $item->markCancelled($by);
            }
        }
        $this->load('items');
    }

    public function totalRefundedCents(): int
    {
        $sum = 0;
        foreach ($this->payments as $payment) {
            foreach ($payment->refunds as $refund) {
                if ($refund->status === PaymentRefund::STATUS_SUCCEEDED) {
                    $sum += (int) $refund->amount_cents;
                }
            }
        }

        return $sum;
    }

    /**
     * Importe COBRADO del item en céntimos (sin descuentos de refunds previos sobre
     * el mismo item), descontando las unidades INCLUIDAS gratis. Delega en
     * `OrderItem::chargedSubtotalCents()` (`(quantity − free_quantity) × unit_price`):
     * la base refundable nunca incluye lo que el cliente no pagó.
     */
    public function itemOriginalTotal(OrderItem $item): int
    {
        return $item->chargedSubtotalCents();
    }

    /**
     * Importe ya devuelto específicamente sobre este item (suma de
     * `payment_refunds.succeeded` con `order_item_id=$item->id`). Útil para
     * calcular cuánto resta refundable por item en refunds parciales sucesivos.
     */
    public function itemRefundedCents(OrderItem $item): int
    {
        $sum = 0;
        $unattributed = 0;
        foreach ($this->payments as $payment) {
            foreach ($payment->refunds as $refund) {
                if ($refund->status !== PaymentRefund::STATUS_SUCCEEDED) {
                    continue;
                }
                if ($refund->order_item_id === null) {
                    $unattributed += (int) $refund->amount_cents;
                } elseif ((int) $refund->order_item_id === (int) $item->id) {
                    $sum += (int) $refund->amount_cents;
                }
            }
        }

        return $sum + $this->unattributedRefundShareFor($item, $unattributed);
    }

    /**
     * Parte que le toca a ESTE item de los reembolsos que no se ataron a ninguna línea.
     *
     * ⚠️⚠️ **El reembolso TOTAL se escribe con `order_item_id = null`** —es una operación sobre el
     * `Payment` entero, no sobre una línea— y hasta esta corrección eso dejaba a las reservas
     * diciendo «devuelto 0,00 €» mientras el pedido decía 19,80 € (`DECISIONES #127`). No es un
     * hueco teórico: lo consumen la sub-card del panel y la hoja PDF.
     *
     * **Se reparte a prorrata de lo que cada línea aportó ONLINE** ({@see itemCollectedCents}), que es
     * exactamente de dónde salió el dinero devuelto. Reparto por RESTO MAYOR: los enteros se asignan
     * por defecto y el céntimo sobrante va a la línea con el resto más grande (desempate por `id`,
     * para que sea determinista) → **la Σ de las partes es EXACTAMENTE el importe devuelto**, sin
     * fugas de redondeo.
     *
     * ⚠️ Incluye las líneas CANCELADAS en la base: su importe también entró en el cobro, así que
     * también sale en la devolución.
     * ⚠️ Si nada aportó online (base 0) no hay a quién repartir y devuelve 0; la invariante del eje
     * de caja lo destaparía si alguna vez ocurriera con dinero de por medio.
     *
     * Vive aquí, en {@see itemRefundedCents}, y no en cada superficie: así el tope de capacidad
     * ({@see itemRefundableRemainderCents}), el pendiente por línea, la sub-card, el PDF y el
     * cliente leen la MISMA atribución. Como efecto colateral corrige el tope, que tras un reembolso
     * total seguía diciendo que quedaba todo por devolver.
     */
    private function unattributedRefundShareFor(OrderItem $item, int $unattributed): int
    {
        if ($unattributed <= 0) {
            return 0;
        }

        $weights = [];
        $base = 0;
        foreach ($this->items as $line) {
            $w = $this->itemCollectedCents($line);
            if ($w > 0) {
                $weights[(int) $line->id] = $w;
                $base += $w;
            }
        }
        if ($base <= 0 || ! isset($weights[(int) $item->id])) {
            return 0;
        }

        $shares = [];
        $assigned = 0;
        foreach ($weights as $id => $w) {
            $exact = $unattributed * $w / $base;
            $shares[$id] = (int) floor($exact);
            $assigned += $shares[$id];
        }

        // Resto mayor: el sobrante se reparte de céntimo en céntimo, empezando por la fracción más
        // grande. Determinista por `id` en el desempate.
        $remainders = [];
        foreach ($weights as $id => $w) {
            $remainders[$id] = ($unattributed * $w) % $base;
        }
        arsort($remainders);
        foreach (array_keys($remainders) as $id) {
            if ($assigned >= $unattributed) {
                break;
            }
            $shares[$id]++;
            $assigned++;
        }

        return $shares[(int) $item->id];
    }

    /**
     * Suma NETA (con signo) de `extra_due` atados a ESTE item — su cargo de puerta
     * PENDIENTE. Incluye los CRÉDITOS negativos de {@see applyGateCredit}: subir y
     * bajar la misma cantidad netea a 0 (no queda cargo fantasma). Es la parte de su
     * importe que NO se cobró online, sino pendiente de cobro presencial (o anulada al
     * cancelar el item). Los cargos de complementos se atan a su propio child (no al
     * principal), así que esto es exacto por línea. Por el invariante de
     * {@see applyGateCredit}, nunca es negativo.
     */
    public function itemExtraDueCents(OrderItem $item): int
    {
        $sum = 0;
        foreach ($this->adjustments as $adj) {
            if ($adj->type === OrderAdjustment::TYPE_EXTRA_DUE
                && (int) $adj->order_item_id === (int) $item->id) {
                $sum += (int) $adj->amount_cents;
            }
        }

        return $sum;
    }

    /**
     * Suma NETA (con signo) de los ajustes `deposit_remainder` atados a ESTE item — la parte
     * del valor base que NO se cobró online por pagar solo la SEÑAL (#225), pendiente de cobro
     * presencial. Espejo de {@see itemExtraDueCents} para el otro "bucket de puerta". Admite
     * créditos negativos (al bajar cantidad, §5.8 del plan). En pedidos sin señal NO existen
     * estas filas → devuelve 0 (cálculo legacy idéntico).
     */
    public function itemDepositRemainderCents(OrderItem $item): int
    {
        $sum = 0;
        foreach ($this->adjustments as $adj) {
            if ($adj->type === OrderAdjustment::TYPE_DEPOSIT_REMAINDER
                && (int) $adj->order_item_id === (int) $item->id) {
                $sum += (int) $adj->amount_cents;
            }
        }

        return $sum;
    }

    /**
     * Importe de este item efectivamente COBRADO ONLINE (en el `total` pagado): su importe
     * cargado MENOS lo NO cobrado online — el `extra_due` de ediciones (pendiente/anulable) Y
     * el `deposit_remainder` de la señal (#225, resto a cobrar en puerta conocido desde la
     * creación). Es la base de un eventual reembolso: un complemento añadido en gestión y luego
     * quitado tiene cobrado 0 → ni se reembolsa ni cuenta como pendiente; un pack del que solo
     * se cobró la señal tiene cobrado = la señal (no su valor). En pedidos sin señal,
     * `deposit_remainder` es 0 → cálculo idéntico al histórico (no-regresión).
     */
    public function itemCollectedCents(OrderItem $item): int
    {
        return max(0, $item->chargedSubtotalCents()
            - $this->itemExtraDueCents($item)
            - $this->itemDepositRemainderCents($item));
    }

    /**
     * ¿Es un complemento "fantasma" a OCULTAR de los desgloses? Un item CANCELADO que
     * nunca se cobró online (`itemCollectedCents == 0`) ni se reembolsó: típicamente uno
     * añadido en gestión por `extra_due` y luego sustituido en un cambio de menú. Es
     * net-cero (su `extra_due` ya se anuló), así que mostrarlo solo confunde (líneas
     * duplicadas). Autoridad ÚNICA del predicado, consumido por las 3 superficies de
     * desglose (panel `items-list`, PDF `ReservationSlip`, "Mis pedidos" del cliente).
     */
    public function isVoidedLeftoverItem(OrderItem $item): bool
    {
        return $item->isCancelled()
            && $this->itemCollectedCents($item) === 0
            && $this->itemRefundedCents($item) === 0;
    }

    /**
     * Importe máximo refundable AGREGADO sobre el Payment paid. No se puede
     * devolver más de lo que se cobró. Considera refunds previos (totales y
     * parciales) — Redsys también lo enforce server-side pero validamos antes
     * para dar feedback inmediato al operador.
     */
    public function refundableCapacityCents(): int
    {
        $payment = $this->paidPayment();
        if ($payment === null) {
            return 0;
        }

        // Resta lo ya devuelto (succeeded) Y lo RESERVADO por refunds EN VUELO (pending): un refund
        // pending —parcial o TOTAL (`order_item_id` NULL)— reserva su importe, de modo que dos refunds
        // concurrentes no puedan devolver más de lo cobrado (auditoría Fase 1, M5: cierra el hueco del
        // «total pendiente en vuelo que no bloqueaba un parcial»). Un refund FAILED no reserva nada.
        $reserved = 0;
        foreach ($this->payments as $p) {
            foreach ($p->refunds as $refund) {
                if (in_array($refund->status, [PaymentRefund::STATUS_SUCCEEDED, PaymentRefund::STATUS_PENDING], true)) {
                    $reserved += (int) $refund->amount_cents;
                }
            }
        }

        return max(0, (int) $payment->amount - $reserved);
    }

    /**
     * ¿El reembolso por Redsys (REST) es EJECUTABLE para este pedido? (#225, D7). Solo si el pago
     * cobrado tiene `gateway_order` (operación Redsys real). Los pedidos cobrados en CAJA
     * (efectivo/datáfono, `provider` cash/datafono → sin `gateway_order`) NO se pueden reembolsar
     * por Redsys: solo cabe el «reembolso manual» (record-only: badge + log de constancia; el abono
     * físico lo gestiona el empleado fuera del sistema). Antes el REST se ofrecía igualmente y
     * abortaba en `RefundGateway::executeRefund` («Payment has no gateway_order»).
     */
    public function isRedsysRefundable(): bool
    {
        $payment = $this->paidPayment();

        return $payment !== null && $payment->gateway_order !== null && $payment->gateway_order !== '';
    }

    /**
     * Importe aún refundable de un item concreto (sub-fase 7.2e.1bis).
     *
     * Base = lo COBRADO ONLINE (`itemCollectedCents` = subtotal cargado − `extra_due`
     * pendiente), NO el subtotal cargado: un complemento AÑADIDO en gestión por `extra_due`
     * (cobro en puerta, nunca cobrado online) tiene cobrado 0 → no es refundable. Así el
     * modal de reembolso no OFRECE ni se DEVUELVE dinero que el cliente nunca pagó (#193;
     * caso real: un complemento de un grupo cambiado por el gratis aparecía como refundable
     * por su importe cargado). Menos lo ya devuelto del item; capado a 0.
     */
    public function itemRefundableRemainderCents(OrderItem $item): int
    {
        // #225 (D7): el techo es lo cobrado online ORIGINAL de la línea − lo ya devuelto. Incluye
        // el sobre-cobro de una BAJADA (las unidades retiradas que no se auto-reembolsaron, D8 →
        // «pendiente de devolución»), para que el operador pueda reembolsarlo con «Reembolsar». Para
        // una línea sin editar, `itemOriginalOnlineCents == itemCollectedCents` → legacy idéntico.
        return max(0, $this->itemOriginalOnlineCents($item) - $this->itemRefundedCents($item));
    }

    /**
     * Importe ORIGINAL pagado ONLINE por este item (lo que aportó al `Order.total`),
     * reconstruido (robustez del desglose #198) para poder atribuir la "pendiente de
     * devolución" a la línea concreta cuando se REDUJO la cantidad — tras un `forceFill`
     * el item ya no guarda su cantidad original.
     *
     * Reglas (deterministas, sin tocar BD si `adjustments` está eager-loaded):
     *  - Si tiene cambios de CANTIDAD en su histórico → `cantidad_original × unit_price`
     *    (cantidad_original = `old` del PRIMER `quantity_change`). EXACTO para subir/bajar
     *    cantidad (caso de JJ-WIMWJW).
     *  - En otro caso → `itemCollectedCents` (lo cobrado online ACTUAL): correcto para una
     *    línea sin editar (= su importe) y para un complemento añadido en puerta (= 0, su
     *    `collected` es 0). Así un addon de puerta nunca cuenta como "pendiente de
     *    devolución". (Aproximado solo si hubo cambio de PRODUCTO sin cambio de cantidad.)
     */
    public function itemOriginalOnlineCents(OrderItem $item): int
    {
        $earliestAt = null;
        $originalQty = null;
        foreach ($this->adjustments as $adj) {
            // El `quantity_change` de una bajada lo porta el ajuste que la registró: el crédito de
            // puerta (`extra_due`), el crédito del resto-señal (`deposit_remainder`, #225/D8) o el
            // marcador de reconstrucción de una bajada puramente-online ({@see recordReductionMarker}).
            $isGateBucket = $adj->type === OrderAdjustment::TYPE_EXTRA_DUE
                || $adj->type === OrderAdjustment::TYPE_DEPOSIT_REMAINDER;
            if (! $isGateBucket || (int) $adj->order_item_id !== (int) $item->id) {
                continue;
            }
            $ctx = is_array($adj->context) ? $adj->context : [];
            $old = $ctx['changes']['quantity_change']['old'] ?? null;
            if ($old !== null && ($earliestAt === null || $adj->created_at < $earliestAt)) {
                $earliestAt = $adj->created_at;
                $originalQty = (int) $old;
            }
        }

        if ($originalQty !== null) {
            $originalValue = $originalQty * (int) $item->unit_price;

            // #225 (auditoría Fase 1 · P1): un COMPLEMENTO de un pack CON señal se cobró ÍNTEGRO en
            // PUERTA (Opción A, {@see OrderCreator}: se le creó un `deposit_remainder` por su importe
            // completo) → su online original es **0**, NO `depositCents()` del propio addon (cuyo
            // `deposit_type=none` devolvería el valor pleno → «pendiente de devolución» FANTASMA en
            // la card/PDF/«Mis pedidos» + techo de reembolso inflado contra una línea nunca cobrada
            // online). El fix #225-F1 cubrió el PRINCIPAL pero no estos children. Detecta la huella
            // real (tiene fila `deposit_remainder`), robusto aun si el depósito del pack ≥ su valor
            // (ahí `has_deposit` es false y el child SÍ se cobró online → cae al cálculo de abajo).
            if ($item->parent_item_id !== null && $this->itemChargedAtGateAsDeposit($item)) {
                return 0;
            }

            // #225: lo cobrado ONLINE originalmente fue la SEÑAL del valor original, NO el valor
            // pleno. `depositCents` es data-driven (sin señal → valor completo, legacy idéntico).
            // Sin esto, subir la cantidad de un pack con señal hacía aflorar un «Pendiente de
            // devolución» FANTASMA en la card del producto (originalValor − señal actual).
            return $item->ticketType?->depositCents($originalValue) ?? $originalValue;
        }

        return $this->itemCollectedCents($item);
    }

    /**
     * ¿Este item se cobró ÍNTEGRO en PUERTA como parte de la señal (#225, Opción A)? Es cierto
     * cuando {@see OrderCreator} le creó un ajuste `deposit_remainder` POSITIVO en la creación —
     * el principal de un pack con señal y CADA complemento de pago de ese pack. Un crédito por
     * bajada (fila negativa) NO lo desmiente: la huella original persiste. Lo usa
     * {@see itemOriginalOnlineCents} para que el «online original» de un COMPLEMENTO de pack con
     * señal sea 0 (nunca se cobró online), no su valor pleno.
     */
    private function itemChargedAtGateAsDeposit(OrderItem $item): bool
    {
        foreach ($this->adjustments as $adj) {
            if ($adj->type === OrderAdjustment::TYPE_DEPOSIT_REMAINDER
                && (int) $adj->order_item_id === (int) $item->id
                && (int) $adj->amount_cents > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * "Pendiente de devolución" de un item (robustez del desglose #198): dinero pagado
     * ONLINE que ya no tiene producto detrás y aún NO se ha devuelto. Unifica:
     *  - CANCELACIÓN → todo lo COBRADO online no devuelto (`itemCollectedCents − devuelto`,
     *    robusto, sin reconstrucción).
     *  - REDUCCIÓN de cantidad de un item ACTIVO por debajo de lo pagado online →
     *    `originalOnline − devuelto − cobrado online actual` (reconstruido).
     * Capado a 0. Para el caso solo-cantidad `Σ` sobre los items ==
     * {@see OrderFinancialSummary::pendienteDevolucion()} (el desglose por línea cuadra
     * con el del pedido).
     *
     * Nota: una reducción puramente ONLINE sin ningún cargo de puerta previo no deja
     * `quantity_change` reconstruible; en producción esa bajada SÍ se reembolsa con éxito
     * (sale como "Devuelto") y el pendiente por-línea queda 0 — el pendiente solo persiste
     * si el reembolso falla, y entonces sigue visible a nivel PEDIDO.
     */
    public function itemPendingRefundCents(OrderItem $item): int
    {
        $refunded = $this->itemRefundedCents($item);

        if ($item->isCancelled()) {
            return max(0, $this->itemCollectedCents($item) - $refunded);
        }

        return max(0, $this->itemOriginalOnlineCents($item) - $refunded - $this->itemCollectedCents($item));
    }

    /**
     * Aplica un extra pendiente de cobrar en puerta (sub-fase 7.2e cimientos).
     *
     * Caso típico: una edición desde el modal Gestionar sube el importe del
     * pedido (más cantidad, ticket más caro, addon nuevo). Se crea una fila
     * `OrderAdjustment.extra_due` que el operador cobrará al cliente al
     * llegar al parque. Decisión clienta sesión 7.2e: el cobro presencial es
     * implícito — al pasar el slot del item, `OrderFinancialSummary` lo
     * considera resuelto.
     *
     * **No toca Redsys** (PSD2/SCA + UX: pedir segundo cargo online fricciona).
     * **No actualiza `Order.total`** (`Order.total` refleja lo cobrado online).
     *
     * Audit log automático: `orders.extra_due_applied` con payload.
     *
     * @param  array<string,mixed>  $context  Diff estructurado opcional
     *                                        (slot_change/quantity_change/etc.).
     */
    public function applyExtraDue(
        OrderItem $item,
        int $amountCents,
        User $by,
        ?string $reason = null,
        array $context = [],
    ): OrderAdjustment {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Extra due amount must be positive (got '.$amountCents.').');
        }

        if ((int) $item->order_id !== (int) $this->id) {
            throw new \DomainException('Item does not belong to this order.');
        }

        $adjustment = DB::transaction(function () use ($item, $amountCents, $by, $reason, $context): OrderAdjustment {
            // Lock the Order para serializar contra edits concurrentes.
            self::query()->lockForUpdate()->find($this->id);

            return OrderAdjustment::create([
                'order_id' => $this->id,
                'order_item_id' => $item->id,
                'type' => OrderAdjustment::TYPE_EXTRA_DUE,
                'amount_cents' => $amountCents,
                'currency' => $this->currency ?? 'EUR',
                'reason' => $reason,
                'context' => $context !== [] ? $context : null,
                'applied_by' => $by->id,
            ]);
        });

        AuditLogger::log(
            action: 'orders.extra_due_applied',
            target: $this,
            payload: [
                'order_code' => $this->code,
                'order_item_id' => $item->id,
                'amount_cents' => $amountCents,
                'reason' => $reason,
                'adjustment_id' => $adjustment->id,
            ],
        );

        return $adjustment;
    }

    /**
     * Aplica un CRÉDITO de puerta: una fila `extra_due` con `amount_cents`
     * NEGATIVO que ANULA (total o parcialmente) un cargo de puerta PENDIENTE del
     * mismo item cuando una edición BAJA su cantidad/importe.
     *
     * Por qué firmado y no un reembolso: el cargo que se deshace era dinero a
     * cobrar EN PUERTA (presencial, NUNCA cobrado online), así que deshacerlo no
     * es una devolución bancaria — es "ya no lo debes". El crédito netea contra
     * los cargos previos del MISMO item ({@see itemExtraDueCents} suma con signo):
     * subir y bajar la misma cantidad deja neto 0, sin cargos fantasma (bug
     * JJ-WIMWJW). Solo la parte de la bajada que cae POR DEBAJO de lo pagado
     * online se reembolsa de verdad — responsabilidad del caller, que llama a
     * {@see executePartialRefund} con el remanente.
     *
     * **Invariante que el caller DEBE respetar:** `amountCents ≤ itemExtraDueCents(item)`
     * (el crédito nunca supera el cargo pendiente del item), de modo que el neto
     * por item nunca baja de 0. Con eso, `Σ pendingAtGateLines == pendingAtGate()`.
     *
     * **No toca Redsys** ni `Order.total`. Audit: `orders.gate_credit_applied`.
     *
     * @param  int  $amountCents  importe POSITIVO a acreditar (se persiste negado)
     * @param  array<string,mixed>  $context  diff estructurado opcional
     */
    public function applyGateCredit(
        OrderItem $item,
        int $amountCents,
        User $by,
        ?string $reason = null,
        array $context = [],
    ): OrderAdjustment {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Gate credit amount must be positive (got '.$amountCents.').');
        }

        if ((int) $item->order_id !== (int) $this->id) {
            throw new \DomainException('Item does not belong to this order.');
        }

        $adjustment = DB::transaction(function () use ($item, $amountCents, $by, $reason, $context): OrderAdjustment {
            self::query()->lockForUpdate()->find($this->id);

            return OrderAdjustment::create([
                'order_id' => $this->id,
                'order_item_id' => $item->id,
                'type' => OrderAdjustment::TYPE_EXTRA_DUE,
                'amount_cents' => -$amountCents,
                'currency' => $this->currency ?? 'EUR',
                'reason' => $reason,
                'context' => $context !== [] ? $context : null,
                'applied_by' => $by->id,
            ]);
        });

        AuditLogger::log(
            action: 'orders.gate_credit_applied',
            target: $this,
            payload: [
                'order_code' => $this->code,
                'order_item_id' => $item->id,
                'amount_cents' => -$amountCents,
                'reason' => $reason,
                'adjustment_id' => $adjustment->id,
            ],
        );

        return $adjustment;
    }

    /**
     * Aplica un CRÉDITO sobre el RESTO DE LA SEÑAL (#225, D8): una fila `deposit_remainder` con
     * `amount_cents` NEGATIVO que reduce el resto-señal pendiente del mismo item cuando una BAJADA
     * de cantidad encoge la reserva. Espejo exacto de {@see applyGateCredit} para el otro bucket de
     * puerta — el `deposit_remainder` se cobra presencialmente, así que reducirlo es «ya no lo
     * debes», NO una devolución bancaria.
     *
     * **Invariante que el caller DEBE respetar:** `amountCents ≤ itemDepositRemainderCents(item)`
     * (el crédito nunca supera el resto-señal pendiente del item) → el neto por item nunca < 0.
     * **No toca Redsys** ni `Order.total`. Audit: `orders.deposit_remainder_credit_applied`.
     *
     * @param  int  $amountCents  importe POSITIVO a acreditar (se persiste negado)
     * @param  array<string,mixed>  $context  diff estructurado opcional
     */
    public function applyDepositRemainderCredit(
        OrderItem $item,
        int $amountCents,
        User $by,
        ?string $reason = null,
        array $context = [],
    ): OrderAdjustment {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Deposit remainder credit amount must be positive (got '.$amountCents.').');
        }

        if ((int) $item->order_id !== (int) $this->id) {
            throw new \DomainException('Item does not belong to this order.');
        }

        $adjustment = DB::transaction(function () use ($item, $amountCents, $by, $reason, $context): OrderAdjustment {
            self::query()->lockForUpdate()->find($this->id);

            return OrderAdjustment::create([
                'order_id' => $this->id,
                'order_item_id' => $item->id,
                'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
                'amount_cents' => -$amountCents,
                'currency' => $this->currency ?? 'EUR',
                'reason' => $reason,
                'context' => $context !== [] ? $context : null,
                'applied_by' => $by->id,
            ]);
        });

        AuditLogger::log(
            action: 'orders.deposit_remainder_credit_applied',
            target: $this,
            payload: [
                'order_code' => $this->code,
                'order_item_id' => $item->id,
                'amount_cents' => -$amountCents,
                'reason' => $reason,
                'adjustment_id' => $adjustment->id,
            ],
        );

        return $adjustment;
    }

    /**
     * Marcador de reconstrucción de una BAJADA de cantidad (#225, D8). Una bajada de un producto
     * pagado ÍNTEGRO online (sin señal ni cargos de puerta que crediten) no deja ninguna fila que
     * porte el `quantity_change`. Sin ese dato, {@see itemOriginalOnlineCents} no puede reconstruir
     * la cantidad ORIGINAL y el sobre-cobro (cobrado online sin producto detrás) quedaría INVISIBLE
     * en vez de aflorar como «pendiente de devolución» (cancelar ≠ reembolsar: el operador lo
     * devuelve aparte). Registramos un ajuste de **0 €** que SOLO porta el contexto: amount 0 → no
     * afecta a `extraDue`/`pendingAtGate`/líneas de puerta ni a ningún total.
     *
     * @param  array<string,mixed>  $context  diff estructurado (debe incluir `changes.quantity_change`)
     */
    public function recordReductionMarker(OrderItem $item, User $by, array $context = []): OrderAdjustment
    {
        if ((int) $item->order_id !== (int) $this->id) {
            throw new \DomainException('Item does not belong to this order.');
        }

        return DB::transaction(function () use ($item, $by, $context): OrderAdjustment {
            self::query()->lockForUpdate()->find($this->id);

            return OrderAdjustment::create([
                'order_id' => $this->id,
                'order_item_id' => $item->id,
                'type' => OrderAdjustment::TYPE_EXTRA_DUE,
                'amount_cents' => 0,
                'currency' => $this->currency ?? 'EUR',
                'reason' => 'reduction_marker',
                'context' => $context !== [] ? $context : null,
                'applied_by' => $by->id,
            ]);
        });
    }

    /**
     * Orquesta el REEMBOLSO PARCIAL de un item del pedido (sub-fase 7.2e cimientos,
     * decisión #150). Simétrico a `executeFullRefund` (#142) — clona la estructura
     * 2-txn + lock + mutex + REST fuera de lock + audit categorizado. **El email
     * NO se dispara aquí** (responsabilidad del caller — patrón ya establecido en
     * `executeFullRefund`); el caller dispara `OrderItemRefunded` cuando `ok=true`.
     * Diferencias clave:
     *
     *  - `payment_refunds.order_item_id = $item->id` (vs NULL del total).
     *  - `amount_cents` es libre (lo decide el caller), con validaciones:
     *      * > 0
     *      * ≤ capacidad refundable del Payment (no se puede devolver más de lo
     *        cobrado, considerando refunds previos)
     *  - `alsoCancelItem === true` → `OrderItem::markCancelled` en la misma 2ª txn.
     *    Caso típico del icono 🗑️ "Cancelar item" (decisión #150: cancelar
     *    item dispara refund del importe completo del item con `alsoCancelItem=true`).
     *  - `Order.refunded_at` se setea si no estaba (mantiene badge "Reembolsado"
     *    coherente con parciales). `Order.refund_amount_cents` se recalcula al
     *    AGREGADO de todos los refunds succeeded del Order (fuente de verdad).
     *
     * @param  array<string,mixed>  $context  Diff estructurado opcional
     *                                        (motivo: cancellation/quantity_reduction/etc.).
     * @return array{
     *   ok:bool,
     *   reason?:string,
     *   refund?:PaymentRefund,
     *   order?:Order,
     *   item?:OrderItem,
     *   payment?:Payment,
     *   also_cancelled_item?:bool,
     *   gateway_response_code?:?string,
     *   failure_message?:?string,
     * }
     */
    public function executePartialRefund(
        OrderItem $item,
        int $amountCents,
        User $by,
        string $mode,
        bool $alsoCancelItem,
        array $context = [],
    ): array {
        if ($amountCents <= 0) {
            return ['ok' => false, 'reason' => 'invalid_amount'];
        }
        if (! in_array($mode, [PaymentRefund::MODE_REST, PaymentRefund::MODE_MANUAL], true)) {
            return ['ok' => false, 'reason' => 'invalid_mode'];
        }

        $payment = $this->paidPayment();
        if ($payment === null) {
            return ['ok' => false, 'reason' => 'no_paid_payment'];
        }

        // Txn 1: lock + revalidate + create pending row. Serializa contra clicks
        // concurrentes y materializa la intención antes de la REST call.
        try {
            $refund = DB::transaction(function () use ($item, $amountCents, $by, $mode, $payment) {
                /** @var Order $locked */
                $locked = self::query()->lockForUpdate()->find($this->id);

                // Re-validar pertenencia y estado del item con la fila fresca.
                /** @var OrderItem $itemFresh */
                $itemFresh = OrderItem::query()->lockForUpdate()->find($item->id);
                if ($itemFresh === null || (int) $itemFresh->order_id !== (int) $locked->id) {
                    throw new \DomainException('blocked:not_in_order');
                }
                // Nota (sub-fase 7.2e.1bis, decisión #154): el orquestador
                // acepta tanto items principales como children/addons. El
                // bloqueo "no refundes addons directamente desde la UI" vive
                // en `refundItemBlockedReason()` (que rechaza addons como
                // entrada de la action) — el orquestador es flexible para
                // soportar el batch de refund de pack+complementos.

                // Capacidad agregada: no se puede devolver más de lo cobrado (cuenta succeeded + pending).
                $locked->load(['payments.refunds', 'adjustments']);
                $remaining = $locked->refundableCapacityCents();
                if ($amountCents > $remaining) {
                    throw new \DomainException('blocked:exceeds_refundable_capacity');
                }

                // Remanente refundable DEL ITEM bajo lock (auditoría Fase 1, L1): la capacidad AGREGADA
                // no basta —el mismo item podría reembolsarse dos veces respaldado por el dinero de
                // OTROS items—. El importe no puede exceder lo aún refundable de ESTA línea (lo cobrado
                // online original − lo ya devuelto del item). Coincide con el importe que calcula el
                // batch (`itemRefundableRemainderCents`), así que no rechaza un batch legítimo.
                if ($amountCents > $locked->itemRefundableRemainderCents($itemFresh)) {
                    throw new \DomainException('blocked:exceeds_item_refundable');
                }

                // Mutex anti-doble-click PER ITEM (no per Order). Refunds parciales
                // sobre items distintos del mismo Order pueden ir en paralelo (caso
                // teórico raro pero modelado por correctness). Refunds parciales
                // sobre el mismo item se serializan.
                $inflight = PaymentRefund::query()
                    ->where('payment_id', $payment->id)
                    ->where('order_item_id', $itemFresh->id)
                    ->where('status', PaymentRefund::STATUS_PENDING)
                    ->exists();
                if ($inflight) {
                    throw new \DomainException('inflight_refund');
                }

                return PaymentRefund::create([
                    'payment_id' => $payment->id,
                    'order_item_id' => $itemFresh->id,
                    'amount_cents' => $amountCents,
                    'currency' => $payment->currency,
                    'status' => PaymentRefund::STATUS_PENDING,
                    'mode' => $mode,
                    'gateway_order' => $payment->gateway_order,
                    'requested_by' => $by->id,
                    'requested_at' => now(),
                ]);
            });
        } catch (\DomainException $e) {
            $reason = str_replace('blocked:', '', $e->getMessage());

            return ['ok' => false, 'reason' => $reason];
        }

        // Step 2: REST call FUERA de la txn. En modo manual saltamos.
        $restResult = $mode === PaymentRefund::MODE_REST
            ? app(RefundGateway::class)->executeRefund($payment, $amountCents)
            : null;

        // Txn 2: finalizar refund + actualizar Order/Item o registrar fallo.
        return DB::transaction(function () use ($item, $alsoCancelItem, $by, $mode, $payment, $refund, $restResult, $context, $amountCents): array {
            /** @var Order $order */
            $order = self::query()->lockForUpdate()->find($this->id);
            /** @var OrderItem $itemLocked */
            $itemLocked = OrderItem::query()->lockForUpdate()->find($item->id);
            $now = now();

            // Rama "fallo de REST": registrar fallo en la fila, NO tocar Order/Item.
            if ($mode === PaymentRefund::MODE_REST && ! $restResult->success) {
                $refund->update([
                    'status' => PaymentRefund::STATUS_FAILED,
                    'processed_at' => $now,
                    'gateway_response_code' => $restResult->dsResponse,
                    'raw_response' => $restResult->rawResponse,
                    'failure_reason' => $restResult->failureReason,
                    'failure_message' => $restResult->message,
                ]);

                AuditLogger::log(
                    action: 'orders.item_refund_failed',
                    target: $order,
                    payload: [
                        'order_code' => $order->code,
                        'order_item_id' => $itemLocked->id,
                        'mode' => $mode,
                        'refund_id' => $refund->id,
                        'amount_cents' => $amountCents,
                        'failure_reason' => $restResult->failureReason,
                        'ds_response' => $restResult->dsResponse,
                    ],
                );

                return [
                    'ok' => false,
                    'reason' => 'gateway_failed',
                    'refund' => $refund->refresh(),
                    'item' => $itemLocked->refresh(),
                    'gateway_response_code' => $restResult->dsResponse,
                    'failure_message' => $restResult->message,
                ];
            }

            // Rama éxito (REST 0900 o modo manual).
            $refund->update([
                'status' => PaymentRefund::STATUS_SUCCEEDED,
                'processed_at' => $now,
                'gateway_response_code' => $mode === PaymentRefund::MODE_REST
                    ? $restResult->dsResponse
                    : PaymentRefund::MANUAL_RESPONSE_MARKER,
                'raw_response' => $restResult?->rawResponse,
            ]);

            // Cancelar el item si se pidió Y aún no estaba cancelado.
            $alsoCancelApplied = false;
            if ($alsoCancelItem && ! $itemLocked->isCancelled()) {
                $itemLocked->markCancelled($by);
                $alsoCancelApplied = true;
            }

            // Actualizar agregados a nivel Order: refunded_at + refund_amount_cents
            // agregado (suma de todos los succeeded). Mantiene `hasAnyRefund` +
            // badge "Reembolsado" coherente con refunds parciales.
            $order->load(['payments.refunds']);  // fresh refunds incluyendo este
            $aggregateRefunded = $order->totalRefundedCents();
            if ($order->refunded_at === null) {
                $order->refunded_at = $now;
            }
            $order->refund_amount_cents = $aggregateRefunded;
            $order->save();

            AuditLogger::log(
                action: 'orders.item_refunded',
                target: $order,
                payload: [
                    'order_code' => $order->code,
                    'order_item_id' => $itemLocked->id,
                    'mode' => $mode,
                    'refund_id' => $refund->id,
                    'amount_cents' => $amountCents,
                    'also_cancel_item_requested' => $alsoCancelItem,
                    'also_cancelled_item' => $alsoCancelApplied,
                    'payment_id' => $payment->id,
                    'gateway_response_code' => $restResult?->dsResponse ?? PaymentRefund::MANUAL_RESPONSE_MARKER,
                    'context' => $context !== [] ? $context : null,
                ],
            );

            return [
                'ok' => true,
                'refund' => $refund->refresh(),
                'order' => $order->refresh(),
                'item' => $itemLocked->refresh(),
                'payment' => $payment,
                'also_cancelled_item' => $alsoCancelApplied,
                'gateway_response_code' => $restResult?->dsResponse ?? PaymentRefund::MANUAL_RESPONSE_MARKER,
            ];
        });
    }

    /**
     * Refund BATCH de múltiples items en secuencia (sub-fase 7.2e.1bis,
     * decisión #154; sub-fase 7.2e.1bis4 — decisión #157 desacopla cancel
     * de refund). Cada item es UNA llamada a `executePartialRefund` con el
     * `alsoCancelItems` recibido como parámetro (default `false`: refund
     * NO implica cancellation; los servicios siguen ofrecidos al cliente).
     *
     * Operativamente: el operador usa la action `cancelItem` para cancelar
     * un producto entero (con cascada a sus complementos) y la action
     * `refundItem` para devolver dinero — ambas dimensiones independientes.
     * El parámetro `alsoCancelItems=true` queda para clientes programáticos
     * o futuras actions que necesiten la cancelación atómica con el refund.
     *
     * El importe es siempre el remanente refundable de cada item
     * (`itemRefundableRemainderCents`) — no importe libre.
     *
     * **Atomicidad parcial**: cada item es una transacción independiente
     * (cada call a `executePartialRefund` tiene su propio 2-txn pattern). Si
     * el item N falla por REST/gateway, los items < N quedan aplicados y los
     * items > N **NO se intentan** — abortamos el batch tras el primer fallo.
     * Razón: si Redsys devolvió error en el item 3, probablemente está caído
     * temporalmente; aplicar el item 4 sería desperdiciar más llamadas REST
     * (o duplicar errores de transport). Mejor parar y dejar al operador
     * decidir el reintento manual.
     *
     * **Defensa pre-batch**: validamos ANTES de iniciar que todos los items
     * pasan `refundItemBlockedReason()` con la fila fresca. Si alguno está
     * bloqueado, abortamos el batch ENTERO sin tocar nada (evita el caso
     * "refundé 2, el 3º estaba ya refundado y la operación queda mixta").
     *
     * @param  list<int>  $itemIds
     * @return array{
     *   succeeded:list<array{item:OrderItem,amount_cents:int,refund:PaymentRefund}>,
     *   failed:list<array{item:OrderItem,reason:string,gateway_response_code:?string,failure_message:?string,refund:?PaymentRefund}>,
     *   aborted:list<int>,
     * }
     */
    public function executePartialRefundBatch(
        array $itemIds,
        User $by,
        string $mode,
        bool $alsoCancelItems = false,
    ): array {
        $succeeded = [];
        $failed = [];
        $aborted = [];

        if ($itemIds === []) {
            return ['succeeded' => $succeeded, 'failed' => $failed, 'aborted' => $aborted];
        }

        // Pre-validación: todos los items deben existir, pertenecer al Order,
        // y pasar `refundItemBlockedReason()`. Si alguno falla, abortamos
        // entero ANTES de tocar nada.
        $items = OrderItem::with('ticketType')->whereIn('id', $itemIds)->get()->keyBy('id');
        foreach ($itemIds as $id) {
            $item = $items->get($id);
            if ($item === null) {
                return [
                    'succeeded' => $succeeded,
                    'failed' => [[
                        'item' => new OrderItem(['id' => $id]),
                        'reason' => 'not_found',
                        'gateway_response_code' => null,
                        'failure_message' => null,
                        'refund' => null,
                    ]],
                    'aborted' => array_values(array_diff($itemIds, [$id])),
                ];
            }
            if ((int) $item->order_id !== (int) $this->id) {
                return [
                    'succeeded' => $succeeded,
                    'failed' => [[
                        'item' => $item,
                        'reason' => 'not_in_order',
                        'gateway_response_code' => null,
                        'failure_message' => null,
                        'refund' => null,
                    ]],
                    'aborted' => array_values(array_diff($itemIds, [$id])),
                ];
            }
        }

        // Ejecutar secuencialmente. Tras éxito de cada uno, refrescamos $this
        // para que `refundableCapacityCents()` refleje la capacity actualizada
        // (el orquestador interno re-valida igualmente con lockForUpdate).
        $pending = $itemIds;
        foreach ($itemIds as $idx => $id) {
            array_shift($pending);  // este ya entra en la iteración
            $item = $items->get($id);
            $amountCents = $this->fresh()->load('payments.refunds')->itemRefundableRemainderCents($item);

            if ($amountCents <= 0) {
                $failed[] = [
                    'item' => $item,
                    'reason' => 'item_already_fully_refunded',
                    'gateway_response_code' => null,
                    'failure_message' => null,
                    'refund' => null,
                ];
                $aborted = $pending;
                break;
            }

            $result = $this->fresh()->executePartialRefund(
                item: $item,
                amountCents: $amountCents,
                by: $by,
                mode: $mode,
                alsoCancelItem: $alsoCancelItems,
            );

            if (! ($result['ok'] ?? false)) {
                $failed[] = [
                    'item' => $item,
                    'reason' => (string) ($result['reason'] ?? 'unknown'),
                    'gateway_response_code' => $result['gateway_response_code'] ?? null,
                    'failure_message' => $result['failure_message'] ?? null,
                    'refund' => $result['refund'] ?? null,
                ];
                // Abortamos el batch — los pending no se intentan.
                $aborted = $pending;
                break;
            }

            $succeeded[] = [
                'item' => $result['item'],
                'amount_cents' => $amountCents,
                'refund' => $result['refund'],
            ];
        }

        return [
            'succeeded' => $succeeded,
            'failed' => $failed,
            'aborted' => $aborted,
        ];
    }
}
