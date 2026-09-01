<?php

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Concerns\HasItemActionGuards;
use App\Domain\Booking\Concerns\OrderOperativeStatus;
use App\Domain\Booking\Services\LineFacts;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\Settlement;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Concerns\GuardsItemRefunds;
use App\Domain\Payments\Concerns\OrderRefundFlags;
use App\Domain\Payments\Contracts\RefundGateway;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
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
     * Importe a cobrar ONLINE = la SEÑAL/DEPÓSITO (#225): Σ de lo que cada línea NO cancelada
     * (principales + complementos) cobra online HOY —su fila menos su reparto de señal
     * ({@see LineFacts::onlineNow}, D-T3·23 de `specs/desglose-libro.md`)—. Es la FUENTE ÚNICA del
     * importe online: la consumen `Payment.amount`, el `DS_MERCHANT_AMOUNT` de la ida Redsys, el
     * reintento y el pedido manual, garantizando que el canario `amount_mismatch` nunca diverja.
     *
     * En pedidos SIN señal equivale a `Σ chargedSubtotalCents` (= el valor de los productos =
     * `Order.total`); con señal, a `Σ depositCents(línea)` que `OrderCreator` repartió al nacer.
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
            $sum += LineFacts::forItem($this, $item)->onlineNow();
        }

        return $sum;
    }

    /**
     * **El valor con el que NACIÓ el pedido, reconstruido desde los HECHOS de sus líneas**
     * (`specs/desglose-libro.md` §4.1: `Σ_i nac(i)`, con `nac(i) = fila(i) − Δ(i)`).
     *
     * Es la mitad de la identidad de NACIMIENTO (`I1`): tiene que coincidir con `Order.total`, que
     * es lo que `OrderCreator` guardó al crear. Si no coincide, o falta un hecho (una gestión que no
     * dejó su fila) o el pedido se fabricó a mano con un total que no existe — en los dos casos el
     * libro no puede fiarse y `OrderBook` lo deja «en revisión».
     *
     * ⚠️ Cuenta TODAS las líneas, canceladas incluidas: cancelar no cambia con qué nació la línea.
     * Lectura sin N+1: eager-load `items` + `adjustments`.
     */
    public function birthValueCents(): int
    {
        $sum = 0;
        foreach ($this->items as $item) {
            $sum += LineFacts::forItem($this, $item)->birthValue();
        }

        return $sum;
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
    public function executeFullRefund(User $by, string $mode, bool $alsoCancel, ?string $intent = null): array
    {
        $payment = $this->paidPayment();
        if ($payment === null) {
            return ['ok' => false, 'reason' => 'no_paid_payment'];
        }

        // Txn 1: lock + revalidate + create pending row. Serializa contra clicks
        // concurrentes y materializa la "intención" antes de la REST call.
        try {
            $refund = DB::transaction(function () use ($by, $mode, $intent, $payment) {
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
                    // POR QUÉ se devuelve (`DECISIONES #127(c)`): sin esto, a un cliente que conserva
                    // su reserva solo se le puede decir «te devolvimos X €», sin lo único que
                    // necesita saber — si sigue debiendo ese dinero.
                    'intent' => $intent,
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

            $previousStatus = $order->status;
            $alsoCancelApplied = $alsoCancel && $order->canBeCancelled();

            // ⚠️⚠️ **Cancelar el PEDIDO cancela sus RESERVAS** (`DECISIONES #127`). Sin esto el
            // pedido queda cancelado pero sus líneas vivas, así que `productsValue` las sigue
            // sumando y el cliente lee «Total 19,80 €» sobre un pedido cancelado cuyo dinero el
            // parque retiene. La conducta correcta ya existía un nivel más abajo —cancelar la
            // RESERVA sí deja el desglose correcto—; esto la sube al nivel del pedido.
            //
            // ⚠️⚠️ **Y va ANTES de medir lo debido** (T2 del libro, `specs/desglose-libro.md` §6.2):
            // un reembolso «con también cancelar» devuelve el valor que la cancelación retira. Medido
            // con la línea todavía VIVA, nada se debía y los 40,00 € salían ENTEROS como cortesía
            // sobre un pedido cancelado — el defecto de la T1 que cazó la guarda del libro
            // (`OrderBookTest`). La cancelación es parte del hecho, así que lo debido se mide con ella.
            if ($alsoCancelApplied) {
                $order->status = self::STATUS_CANCELLED;
                $order->save();
                $order->cancelLiveItems($by);
            }

            // T1 del libro: lo que se le DEBÍA al cliente ANTES de contar este reembolso, por pedido y
            // por reserva — la cortesía es lo que se devuelve por encima de eso, y se escribe en esta
            // misma transacción ({@see recordCourtesyForRefund}). Lo dice el LIBRO (T3·4: el saldo
            // «a devolver» del pedido y el de cada reserva), medido con las relaciones frescas y con
            // la fila del reembolso todavía `pending` (no cuenta como devuelto).
            $order->load(['payments.refunds', 'adjustments', 'items.children', 'items.slot', 'items.ticketType']);
            $owedBefore = OrderBook::forOrder($order)->owedToCustomerCents();
            $owedBeforeByReservation = [];
            foreach ($order->items->whereNull('parent_item_id') as $principal) {
                $owedBeforeByReservation[(int) $principal->id] = OrderBook::forReservation($order, $principal)->owedToCustomerCents();
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

            $order->refunded_at = $now;
            // Agregado de TODOS los succeeded (igual que el reembolso parcial, Order.php ~1574), no la
            // sobreescritura cruda con `payment->amount` (auditoría Fase 1, M5 p.3). Un full solo es
            // alcanzable sin refunds previos (refundBlockedReason → already_refunded), así que hoy es
            // equivalente; el agregado lo blinda si esa precondición cambiara.
            $order->load(['payments.refunds']);
            $order->refund_amount_cents = $order->totalRefundedCents();
            $order->save();

            $order->recordCourtesyForRefund($refund->refresh(), null, $owedBefore, $owedBeforeByReservation);

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

    // ▶ Hasta la T3·4 del libro (`DECISIONES #315`) aquí vivía el modelo de DOS EJES —
    // `financialSummary()`, `reservationFinancialsByPrincipal()`, `pendingAtGateLines()`,
    // `reservationGateLines()`, `gateBreakdownLines()`, `depositRemainderPendingByProduct()`,
    // `lastRefundIntent()` y sus privados—. El libro (`Booking\Services\OrderBook`) los sustituye
    // enteros: una lista de hechos con fecha, un Total, lo Pagado y un saldo con su clase.

    /**
     * **CÓMO se cobró** el dinero de este pedido: `web` (la pasarela) o `desk` (la taquilla:
     * efectivo o datáfono). `null` mientras no se haya cobrado nada.
     *
     * ⚠️⚠️ **Es regla, no presentación, y por eso vive en el dominio** (`DECISIONES #128`). El eje
     * de caja suma TODOS los pagos cobrados, sean del canal que sean —`grossPaidOnline` no mira el
     * `provider`—, así que el importe es correcto y **el rótulo no puede quemarse**: un pedido de
     * taquilla que dijera «Cobrado por web» mentiría sobre dinero que nunca pasó por la web. El
     * panel ya distinguía el método (`P1/P10`) y el cliente no: ésa era la divergencia.
     *
     * ⚠️ Vive aquí y no en `OrderBook` por la frontera de módulos: `Booking\Services` no puede
     * nombrar `Payments\Models\Payment`; el libro recibe una cadena.
     */
    public function chargeMethod(): ?string
    {
        $pago = $this->collectedPayment();
        if ($pago === null) {
            return null;
        }

        return self::paymentMethodOf($pago);
    }

    /**
     * El CANAL de un cobro en el vocabulario del libro: `web` si pasó por la pasarela, `desk` en
     * cualquier otro caso (los proveedores de taquilla los escribe `ManualOrderFulfiller`).
     * Un solo sitio para el mapeo: lo leen {@see chargeMethod()} y {@see collectedPaymentFacts()}.
     */
    private static function paymentMethodOf(Payment $payment): string
    {
        return $payment->provider === Payment::PROVIDER_REDSYS ? Settlement::METHOD_WEB : Settlement::METHOD_DESK;
    }

    /**
     * **Los COBROS con éxito de este pedido, como HECHOS** para el libro (`specs/desglose-libro.md`
     * §4.3, T2): importe, canal y cuándo. Cronológicos.
     *
     * ⚠️ Existe por la frontera de módulos, como {@see chargeMethod()}:
     * `Booking\Services\OrderBook` no puede nombrar `Payments\Models\Payment`, y `Order` —que está en
     * la costura— traduce aquí los estados y proveedores del pago al vocabulario de
     * {@see Settlement}. Lectura pura sobre `payments` ya cargada.
     *
     * @return list<array{id:int, amount_cents:int, method:string, occurred_at:\DateTimeInterface}>
     */
    public function collectedPaymentFacts(): array
    {
        $facts = [];
        foreach ($this->payments as $payment) {
            if ($payment->status !== Payment::STATUS_PAID) {
                continue;
            }
            $facts[] = [
                'id' => (int) $payment->id,
                'amount_cents' => (int) $payment->amount,
                'method' => self::paymentMethodOf($payment),
                // El sello del cobro; el pago de taquilla histórico que no lo trajera cae a su alta.
                'occurred_at' => $payment->paid_at ?? $payment->created_at,
            ];
        }
        usort($facts, static fn (array $a, array $b): int => $a['occurred_at'] <=> $b['occurred_at'] ?: $a['id'] <=> $b['id']);

        return $facts;
    }

    /**
     * **Los intentos de DEVOLUCIÓN de este pedido, como HECHOS** para el libro (T2): todos, con su
     * estado —solo lo `succeeded` es dinero que volvió; lo `pending` y lo `failed` se LISTA y no se
     * cuenta (spec §4.4)—, su canal, a qué línea se ató (`null` = el pedido entero: un reembolso
     * total) y cuándo. Cronológicos.
     *
     * Misma razón de existir que {@see collectedPaymentFacts()}: la traducción de `PaymentRefund` al
     * vocabulario de {@see Settlement} vive en la costura, no en `Booking\Services`.
     *
     * ⚠️ El `match` de estado es CERRADO a propósito: los tres valores son los únicos que escriben
     * `executeFullRefund`/`executePartialRefund`; un cuarto sería un cambio de código que tiene que
     * pasar por aquí, no un dato que se clasifique a ojo.
     *
     * @return list<array{id:int, amount_cents:int, status:string, method:string, order_item_id:?int, occurred_at:\DateTimeInterface}>
     */
    public function refundFacts(): array
    {
        $facts = [];
        foreach ($this->payments as $payment) {
            foreach ($payment->refunds as $refund) {
                $facts[] = [
                    'id' => (int) $refund->id,
                    'amount_cents' => (int) $refund->amount_cents,
                    'status' => match ($refund->status) {
                        PaymentRefund::STATUS_SUCCEEDED => Settlement::STATUS_SUCCEEDED,
                        PaymentRefund::STATUS_PENDING => Settlement::STATUS_PENDING,
                        PaymentRefund::STATUS_FAILED => Settlement::STATUS_FAILED,
                    },
                    'method' => $refund->mode === PaymentRefund::MODE_MANUAL ? Settlement::METHOD_MANUAL : Settlement::METHOD_CARD,
                    'order_item_id' => $refund->order_item_id === null ? null : (int) $refund->order_item_id,
                    'occurred_at' => $refund->processed_at ?? $refund->requested_at ?? $refund->created_at,
                ];
            }
        }
        usort($facts, static fn (array $a, array $b): int => $a['occurred_at'] <=> $b['occurred_at'] ?: $a['id'] <=> $b['id']);

        return $facts;
    }

    /**
     * **CUÁNDO se cobró**, ya formateado (`d/m/Y`), o `null` si no se ha cobrado.
     *
     * ⚠️ Sin fecha el ancla de caja **no es conciliable**: «cobrado 30,00 €» no se busca en un
     * extracto bancario; «30,00 € el 24/08/2026» sí. Se prefiere el `paid_at` del pago —el sello del
     * cobro— y se cae a su `created_at` en el pago de taquilla histórico que no lo trajera.
     */
    public function chargedAtLabel(): ?string
    {
        $pago = $this->collectedPayment();
        if ($pago === null) {
            return null;
        }

        return DisplayTime::format($pago->paid_at ?? $pago->created_at, 'd/m/Y');
    }

    /**
     * El PAGO cobrado de este pedido, resuelto sobre la colección ya cargada.
     *
     * ⚠️ No es {@see paidPayment()}: aquél consulta la BD y el libro se compone **por pedido en una
     * lista**, donde una query por fila es un N+1 garantizado. Lo leen {@see chargeMethod()} y
     * {@see chargedAtLabel()}.
     */
    private function collectedPayment(): ?Payment
    {
        $ultimo = null;
        foreach ($this->payments as $payment) {
            if ($payment->status !== Payment::STATUS_PAID) {
                continue;
            }
            if ($ultimo === null || $payment->id > $ultimo->id) {
                $ultimo = $payment;
            }
        }

        return $ultimo;
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
     * **Se reparte a prorrata de lo que cada línea aportó ONLINE** ({@see LineFacts::onlineAtBirth}), que es
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
     *
     * ▶ Pública desde la T2 del libro: `OrderBook::forReservation` atribuye con ESTA prorrata las
     * devoluciones sin línea —también las que siguen en curso o fallaron, que no cuentan en lo
     * pagado pero sí se enseñan—, para que la tarjeta de la reserva y el pedido cuenten la misma
     * historia. Es lectura pura: no muta nada.
     */
    public function unattributedRefundShareFor(OrderItem $item, int $unattributed): int
    {
        if ($unattributed <= 0) {
            return 0;
        }

        $weights = [];
        $base = 0;
        foreach ($this->items as $line) {
            // Por lo que cada línea APORTÓ al cobro (D-T3·24 de `specs/desglose-libro.md`): el dinero
            // devuelto salió de ahí, y una línea reducida después no aportó menos por reducirse.
            $w = LineFacts::forItem($this, $line)->onlineAtBirth();
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
     * ¿Es un complemento "fantasma" a OCULTAR de los desgloses? Un item CANCELADO que nunca aportó
     * nada al cobro online ({@see LineFacts::onlineAtBirth} = 0: nació por una edición, o su
     * reparto de señal era su valor entero) ni se reembolsó: típicamente uno añadido en gestión y
     * luego sustituido en un cambio de menú. Es net-cero, así que mostrarlo solo confunde (líneas
     * duplicadas). Autoridad ÚNICA del predicado, consumido por las superficies de desglose (panel
     * `items-list`, PDF `ReservationSlip`, la API) y por el libro, que no le da movimiento.
     */
    public function isVoidedLeftoverItem(OrderItem $item): bool
    {
        return $item->isCancelled()
            && LineFacts::forItem($this, $item)->onlineAtBirth() === 0
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
     * Importe aún refundable de un item concreto (`PAY-09` por línea): **lo que la línea aportó al
     * COBRO ONLINE al nacer** ({@see LineFacts::onlineAtBirth} — un HECHO: su valor de nacimiento
     * menos el reparto de señal que `OrderCreator` escribió, sin consultar el catálogo vivo) menos
     * lo ya devuelto de la línea; capado a 0.
     *
     * Un complemento AÑADIDO en gestión (cobro en puerta, nunca cobrado online) nace por una edición
     * → aportó 0 → no es refundable: el modal no OFRECE ni se DEVUELVE dinero que el cliente nunca
     * pagó (#193). Y el techo INCLUYE el sobre-cobro de una BAJADA (las unidades retiradas que no se
     * auto-reembolsaron, D8 del desglose), para que el operador pueda devolverlo con «Reembolsar»
     * (#225, D7): el libro lo enseña como saldo «a devolver».
     */
    public function itemRefundableRemainderCents(OrderItem $item): int
    {
        return max(0, LineFacts::forItem($this, $item)->onlineAtBirth() - $this->itemRefundedCents($item));
    }

    /**
     * **Escribe el HECHO de una gestión que MUEVE el valor de una línea** (T1 del libro,
     * `specs/desglose-libro.md` §4.2): una fila `edit` con el delta ENTERO y su signo — la subida
     * de una edición desde «Gestionar» (más cantidad, producto más caro, fecha re-tarificada,
     * complemento nuevo) o la bajada. Qué parte de una subida se paga en el parque y qué parte de
     * una bajada se devuelve lo dice el SALDO del libro al leer (`OrderBook`), no esta fila: el
     * cobro presencial es implícito (decisión clienta sesión 7.2e) y al pasar la franja de un
     * pedido cobrado el saldo queda liquidado.
     *
     * **No toca Redsys** (PSD2/SCA + UX: pedir un segundo cargo online fricciona) y **no actualiza
     * `Order.total`** (lo FACTURADO al nacer: la mitad de la identidad `I1`).
     *
     * Audit log automático: `orders.extra_due_applied` / `orders.value_reduction_applied`.
     *
     * @param  array<string,mixed>  $context  Diff estructurado opcional
     *                                        (slot_change/quantity_change/etc.).
     */
    public function recordEdit(
        OrderItem $item,
        int $deltaCents,
        User $by,
        ?string $reason = null,
        array $context = [],
    ): OrderAdjustment {
        if ($deltaCents === 0) {
            throw new \InvalidArgumentException('An edit movement must move the value (got 0).');
        }

        if ((int) $item->order_id !== (int) $this->id) {
            throw new \DomainException('Item does not belong to this order.');
        }

        $adjustment = DB::transaction(function () use ($item, $deltaCents, $by, $reason, $context): OrderAdjustment {
            // Lock the Order para serializar contra edits concurrentes.
            self::query()->lockForUpdate()->find($this->id);

            return OrderAdjustment::create([
                'order_id' => $this->id,
                'order_item_id' => $item->id,
                'type' => OrderAdjustment::TYPE_EDIT,
                'amount_cents' => $deltaCents,
                'currency' => $this->currency ?? 'EUR',
                'reason' => $reason,
                'context' => $context !== [] ? $context : null,
                'applied_by' => $by->id,
            ]);
        });

        // Dos acciones y no una: el operador tiene que poder leer en el historial si aquello fue
        // un cargo o una bajada sin abrir el importe. Las etiquetas viven en
        // `admin.orders.audit_modal.actions.orders.*` (`AuditActionCatalogTest` las empareja).
        AuditLogger::log(
            action: $deltaCents > 0 ? 'orders.extra_due_applied' : 'orders.value_reduction_applied',
            target: $this,
            payload: [
                'order_code' => $this->code,
                'order_item_id' => $item->id,
                'amount_cents' => $deltaCents,
                'reason' => $reason,
                'adjustment_id' => $adjustment->id,
            ],
        );

        return $adjustment;
    }

    /**
     * **La CORTESÍA de un reembolso, escrita al ocurrir** (T1 del libro, `specs/desglose-libro.md`
     * §4.2): la parte de lo devuelto que EXCEDE lo que se le debía al cliente en el ámbito del
     * reembolso, o sea dinero devuelto SIN que desapareciera producto. Hasta la T1 ese importe se
     * DERIVABA al leer («reembolsado − lo que ya no respalda producto», en el modelo de dos ejes que
     * la T3·4 retiró); ahora es una fila `courtesy` (≤ 0), atribuida a línea y fechada, y el libro
     * la pinta como un movimiento más.
     *
     * ▶ La regla, cerrada en la spec:
     *  - `cortesía = max(0, importe − debido_antes)`, con `debido_antes` = el saldo «a devolver» del
     *    LIBRO en el ÁMBITO del reembolso ANTES de contarlo (la reserva si va atado a línea; el
     *    pedido si es total): {@see OrderBook::owedToCustomerCents}, la misma cifra que el panel le
     *    sugiere al operador. Un pedido «en revisión» no debe nada que el libro pueda afirmar, así
     *    que ahí todo lo devuelto es cortesía — y el libro lo enseña, no lo esconde.
     *  - Con `intent = paid_in_person` NO hay cortesía: ese reembolso re-canaliza el dinero (el
     *    cliente lo pagará en recepción), y el saldo del libro lo dirá solo.
     *  - Un reembolso TOTAL se reparte entre las RESERVAS a prorrata de su «exceso» (lo que cada una
     *    recibió por encima de lo que se le debía), por resto mayor y desempate por `id`, y se
     *    atribuye a su principal: la Σ de las filas es EXACTAMENTE la cortesía del pedido, sin fugas
     *    de céntimos.
     *
     * Va en la MISMA transacción que la fila del reembolso: la cortesía es parte del hecho.
     *
     * @param  array<int,int>  $owedBeforeByReservation  lo que se le debía por cada RESERVA antes
     *                                                   del reembolso, indexado por el `id` de su
     *                                                   principal (solo en el reembolso total)
     * @return list<OrderAdjustment>
     */
    private function recordCourtesyForRefund(PaymentRefund $refund, ?OrderItem $item, int $owedBefore, array $owedBeforeByReservation = []): array
    {
        if ($refund->intent === PaymentRefund::INTENT_PAID_IN_PERSON) {
            return [];
        }
        $excess = max(0, (int) $refund->amount_cents - $owedBefore);
        if ($excess === 0) {
            return [];
        }

        $row = fn (int $itemId, int $cents): OrderAdjustment => OrderAdjustment::create([
            'order_id' => $this->id,
            'order_item_id' => $itemId,
            'type' => OrderAdjustment::TYPE_COURTESY,
            'amount_cents' => -$cents,
            'currency' => $this->currency ?? 'EUR',
            'reason' => $refund->intent ?? 'refund',
            'context' => ['refund_id' => (int) $refund->id],
            'applied_by' => $refund->requested_by,
        ]);

        if ($item !== null) {
            return [$row((int) $item->id, $excess)];
        }

        // Reembolso TOTAL: el exceso se reparte entre las RESERVAS por lo que cada una recibió por
        // encima de lo que se le debía, y se atribuye a su principal. Los pesos salen de la MISMA
        // prorrata con la que el propio reembolso se atribuye a las líneas
        // ({@see unattributedRefundShareFor}), sumada por reserva: los dos repartos cuentan la
        // misma historia.
        $weights = [];
        foreach ($this->items->whereNull('parent_item_id') as $principal) {
            $share = $this->unattributedRefundShareFor($principal, (int) $refund->amount_cents);
            foreach ($principal->children as $child) {
                $share += $this->unattributedRefundShareFor($child, (int) $refund->amount_cents);
            }
            $headroom = $share - ($owedBeforeByReservation[(int) $principal->id] ?? 0);
            if ($headroom > 0) {
                $weights[(int) $principal->id] = $headroom;
            }
        }
        $rows = [];
        foreach ($this->allocateByWeights($excess, $weights) as $itemId => $cents) {
            if ($cents > 0) {
                $rows[] = $row($itemId, $cents);
            }
        }

        return $rows;
    }

    /**
     * Reparte `$total` entre claves a prorrata de sus pesos, por RESTO MAYOR y con desempate por
     * clave: la Σ de las partes es exactamente `$total`. Sin pesos, todo va a la primera línea del
     * pedido (no puede perderse dinero por falta de a quién dárselo).
     *
     * @param  array<int,int>  $weights
     * @return array<int,int>
     */
    private function allocateByWeights(int $total, array $weights): array
    {
        $base = array_sum($weights);
        if ($total <= 0) {
            return [];
        }
        if ($base <= 0) {
            $first = $this->items->sortBy('id')->first();

            return $first === null ? [] : [(int) $first->id => $total];
        }

        $shares = [];
        $remainders = [];
        $assigned = 0;
        foreach ($weights as $id => $w) {
            $shares[$id] = intdiv($total * $w, $base);
            $remainders[$id] = ($total * $w) % $base;
            $assigned += $shares[$id];
        }
        uksort($remainders, static fn (int $a, int $b): int => $remainders[$b] <=> $remainders[$a] ?: $a <=> $b);
        foreach (array_keys($remainders) as $id) {
            if ($assigned >= $total) {
                break;
            }
            $shares[$id]++;
            $assigned++;
        }

        return $shares;
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
        ?string $intent = null,
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
            $refund = DB::transaction(function () use ($item, $amountCents, $by, $mode, $intent, $payment) {
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
                    // POR QUÉ se devuelve (`DECISIONES #127(c)`), para que el desglose pueda explicar
                    // en vez de adivinar. `null` = no consta, que es la verdad de las filas viejas.
                    'intent' => $intent,
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

            // Cancelar el item si se pidió Y aún no estaba cancelado. ⚠️ ANTES de medir lo debido (T2
            // del libro): la cancelación que viaja con el reembolso es parte del hecho, y medida con
            // la línea viva el importe entero saldría como cortesía (el mismo defecto que en el total).
            $alsoCancelApplied = false;
            if ($alsoCancelItem && ! $itemLocked->isCancelled()) {
                $itemLocked->markCancelled($by);
                $alsoCancelApplied = true;
            }

            // T1 del libro: lo que se le DEBÍA al cliente por ESTA reserva antes de contar el reembolso
            // (el ámbito de un reembolso atado a línea es su reserva, spec §4.2): el saldo «a
            // devolver» de su libro (T3·4). Con la fila todavía `pending`, que no cuenta como devuelto.
            $order->load(['payments.refunds', 'adjustments', 'items.children', 'items.slot', 'items.ticketType']);
            $principal = $itemLocked->parent_item_id === null
                ? $order->items->firstWhere('id', $itemLocked->id)
                : $order->items->firstWhere('id', $itemLocked->parent_item_id);
            $owedBefore = $principal === null
                ? OrderBook::forOrder($order)->owedToCustomerCents()
                : OrderBook::forReservation($order, $principal)->owedToCustomerCents();

            // Rama éxito (REST 0900 o modo manual).
            $refund->update([
                'status' => PaymentRefund::STATUS_SUCCEEDED,
                'processed_at' => $now,
                'gateway_response_code' => $mode === PaymentRefund::MODE_REST
                    ? $restResult->dsResponse
                    : PaymentRefund::MANUAL_RESPONSE_MARKER,
                'raw_response' => $restResult?->rawResponse,
            ]);

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

            $order->recordCourtesyForRefund($refund->refresh(), $itemLocked, $owedBefore);

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
     * El importe por defecto es el remanente refundable de cada item
     * (`itemRefundableRemainderCents`). ⚠️ **`$amountCentsOverride` existe por `#146`/D5**: sin él,
     * el panel no podía devolver una DIFERENCIA (medido: se debían 10,00 € tras mover la fecha a un
     * día más barato y el botón devolvía los 30,00 € de la línea — 20,00 € regalados). Solo tiene
     * sentido sobre UNA línea: con varias sería ambiguo a cuál se atribuye, y la atribución por
     * línea es lo que el eje de caja (`PAY-17`) explota para explicar el desglose. Los topes NO se
     * relajan: `executePartialRefund` re-valida bajo lock el remanente del item y la capacidad del
     * pedido (`PAY-09`), pase lo que pase aquí.
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
        ?string $intent = null,
        ?int $amountCentsOverride = null,
    ): array {
        $succeeded = [];
        $failed = [];
        $aborted = [];

        if ($itemIds === []) {
            return ['succeeded' => $succeeded, 'failed' => $failed, 'aborted' => $aborted];
        }

        // D5 (`#146`): un importe elegido exige UNA línea y un valor positivo. Se rechaza el batch
        // ENTERO antes de tocar nada — un importe ambiguo no es un caso degradado, es un error.
        if ($amountCentsOverride !== null && ($amountCentsOverride <= 0 || count($itemIds) !== 1)) {
            return [
                'succeeded' => $succeeded,
                'failed' => [[
                    'item' => new OrderItem(['id' => (int) ($itemIds[0] ?? 0)]),
                    'reason' => $amountCentsOverride <= 0
                        ? 'invalid_custom_amount'
                        : 'custom_amount_requires_single_item',
                    'gateway_response_code' => null,
                    'failure_message' => null,
                    'refund' => null,
                ]],
                'aborted' => [],
            ];
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
            $remainderCents = $this->fresh()->load('payments.refunds')->itemRefundableRemainderCents($item);
            // D5 (`#146`): el importe elegido manda si viene; el remanente sigue siendo el default.
            // Si excede el remanente, `executePartialRefund` lo rechaza bajo lock
            // (`exceeds_item_refundable`) — aquí no se capa en silencio a propósito: devolver
            // MENOS de lo que el operador tecleó sería un error callado sobre dinero.
            $amountCents = $amountCentsOverride ?? $remainderCents;

            if ($remainderCents <= 0) {
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
                intent: $intent,
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
