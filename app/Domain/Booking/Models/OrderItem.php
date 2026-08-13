<?php

namespace App\Domain\Booking\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Línea de un pedido: tipo de entrada + franja + cantidad, con el precio unitario
 * de la tarifa aplicada (en céntimos) y las plazas que ocupa.
 *
 * Estado operativo del item (calculado al vuelo, no se persiste):
 *  - `active`    → su franja aún no ha pasado.
 *  - `finished`  → AUTOMÁTICO al pasar `slot.end_time`.
 *  - `cancelled` → soft-cancel (columna `cancelled_at`), estado terminal.
 */
class OrderItem extends Model
{
    public const STATUS_FINISHED = 'finished';

    public const STATUS_ACTIVE = 'active';

    /**
     * Soft-cancel: el item se marcó como cancelado (sub-fase 7.2e). Estado terminal,
     * no reversible. Visualmente aparece tachado/gris; financieramente excluido del
     * cómputo de `Order::displayOperativeStatus` y de `SlotAvailability` (libera plaza).
     */
    public const STATUS_CANCELLED = 'cancelled';

    /** Estado del post-form por-niño de un pack (#217). `null` = el item no pide ese formulario. */
    public const GUEST_FORM_STATUS_OK = 'ok';

    public const GUEST_FORM_STATUS_PENDING = 'pending';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'free_quantity' => 'integer',
        'unit_price' => 'integer',
        'seats' => 'integer',
        'event_data' => 'array',
        'guest_data' => 'array',
        'guest_form_completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * @return BelongsTo<Slot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Ventana horaria a MOSTRAR para esta línea: hora de entrada → entrada + la
     * **duración del producto** (p. ej. un pack de 2h entrando a las 10:00 →
     * "10:00–12:00"). Las franjas de aforo son siempre de 60 min (rejilla horaria),
     * así que `slot->end_time` NO refleja la duración real del producto — por eso se
     * calcula aquí desde `duration_min`. Para productos ilimitados (sin duración) se
     * muestra solo la hora de entrada (la etiqueta de duración ya dice "Ilimitada").
     * Fuente única de verdad para todas las superficies (panel, calendario, PDF, cuenta).
     */
    public function displayTimeWindow(): ?string
    {
        $slot = $this->slot;
        if ($slot === null || empty($slot->start_time)) {
            return null;
        }

        $start = CarbonImmutable::createFromFormat('H:i:s', substr((string) $slot->start_time, 0, 8));
        $duration = $this->ticketType?->duration_min;

        // Ilimitada: hora de entrada + marca explícita de que no hay fin ("10:00 – sin límite"),
        // en paralelo a la franja con duración ("10:00–12:00").
        if (empty($duration)) {
            return $start->format('H:i').' – '.__('tickets.time_no_limit');
        }

        return $start->format('H:i').'–'.$start->addMinutes((int) $duration)->format('H:i');
    }

    /**
     * Importe REAL cobrado por esta línea, en céntimos: `(quantity − free_quantity) × unit_price`.
     *
     * `free_quantity` son las unidades INCLUIDAS gratis (complementos incluidos en un pack: la
     * primera tarta, el menú por invitado…). Histórico — se fijó al crear el pedido. Es la ÚNICA
     * fuente de verdad del subtotal de una línea: todas las superficies (totales del pedido, PDFs,
     * "Mis pedidos", capacidad de reembolso) deben usar este helper en vez de multiplicar
     * `quantity × unit_price`, que ignoraría las unidades gratis. Capado a 0 por defensa.
     */
    public function chargedSubtotalCents(): int
    {
        $paidUnits = max(0, (int) $this->quantity - (int) $this->free_quantity);

        return $paidUnits * (int) $this->unit_price;
    }

    /**
     * ¿Esta línea es (total o parcialmente) GRATIS por venir incluida? Útil para etiquetar
     * "INCLUIDO/GRATIS" en las superficies de lectura.
     */
    public function hasFreeUnits(): bool
    {
        return (int) $this->free_quantity > 0;
    }

    /**
     * Etiqueta del complemento para las superficies de LECTURA (página de pedido, PDF, "Mis
     * pedidos"): `included` si tuvo unidades incluidas gratis (`free_quantity > 0`), `free` si su
     * precio es 0, o `null` (de pago). Derivada del item PERSISTIDO (histórico, como `unit_price`),
     * no del pivote —que pudo cambiar—. Las claves coinciden con `tickets.addon_badge_*`.
     */
    public function addonBadgeKey(): ?string
    {
        if ((int) $this->free_quantity > 0) {
            return 'included';
        }
        if ((int) $this->unit_price === 0 && (int) $this->quantity > 0) {
            return 'free';
        }

        return null;
    }

    /**
     * Aviso "(N incluida(s) gratis)" para el desglose cuando SOLO PARTE de la línea es gratis (la
     * 1.ª tarta de varias): aclara por qué "2 × 15 € = 15 €". Si toda la línea es gratis o no hay
     * unidades incluidas, devuelve null (el badge y el total a 0 ya lo dicen).
     */
    public function partialFreeNote(): ?string
    {
        $free = (int) $this->free_quantity;
        if ($free <= 0 || $free >= (int) $this->quantity) {
            return null;
        }

        return __('tickets.addon_included_partial', ['count' => $free]);
    }

    /**
     * Item padre (cuando este item es un addon, #87).
     *
     * @return BelongsTo<OrderItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    /**
     * Empleado que canceló este item (sub-fase 7.2e). Nullable on delete: el
     * histórico del item cancelado se conserva aunque el staff desaparezca.
     *
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Ajustes financieros generados sobre este item (sub-fase 7.2e). Por ahora
     * solo `extra_due` (subidas de importe cobradas en puerta). Histórico
     * inmutable — no se borran al editar; cada edición añade una fila nueva.
     *
     * @return HasMany<OrderAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    /**
     * Reembolsos parciales atribuidos a este item (sub-fase 7.2e). La FK
     * `payment_refunds.order_item_id` se introdujo nullable en #142 preparada
     * para este uso; cada cancelación o reducción de importe del item genera
     * una fila aquí.
     *
     * @return HasMany<PaymentRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    /**
     * Complementos (líneas hijas) de esta línea de producto, agrupados bajo ella (#87).
     *
     * @return HasMany<OrderItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }

    /**
     * ¿Este item ya pasó su slot (= "finalizado")? Lectura pura — no toca BD.
     *
     * Reglas:
     *  - Si tiene `parent_item_id` (= es un addon): hereda el estado del parent.
     *  - Si tiene `slot_id`: compara `slot.end_time < now()` (UTC, coherente con BD).
     *  - Sin slot ni parent (caso teórico, no esperado en v1): devuelve false.
     */
    public function isFinishedInPractice(): bool
    {
        if ($this->parent_item_id !== null) {
            return $this->parent?->isFinishedInPractice() ?? false;
        }

        $slot = $this->slot;
        if ($slot === null || $slot->end_time === null || $slot->date === null) {
            return false;
        }

        // Combinar fecha + hora del slot. `end_time` viene como 'HH:MM:SS' string.
        $endsAt = CarbonImmutable::parse($slot->date->format('Y-m-d').' '.$slot->end_time);

        return $endsAt->isPast();
    }

    /**
     * Estado del item: `active` / `finished` (derivado de si su franja ya pasó).
     * `cancelled` es una dimensión aparte ({@see isCancelled}). Antes existía un
     * `displayStatusForStaff()` que añadía preparado/sin-preparar; eliminado al
     * retirar ese sistema — todas las superficies usan este único helper.
     */
    public function displayStatusForCustomer(): string
    {
        return $this->isFinishedInPractice() ? self::STATUS_FINISHED : self::STATUS_ACTIVE;
    }

    /**
     * Las respuestas del evento de ESTA reserva, ya emparejadas con la etiqueta de su campo
     * (Fase 4 · paso 4.0b·4b). La composición la pone `TicketType::eventAnswers()`, que es su
     * fuente única; aquí solo se le da lo suyo.
     *
     * **Solo un pack tiene datos de evento**, y esa es la regla que aplica ya el dominio al crear
     * el pedido (`OrderCreator` solo puebla `event_data` en la rama del pack). Repetirla aquí no es
     * redundancia defensiva: es la misma guarda que la compra pone antes de pintar la cesta, y sin
     * ella una entrada con `event_fields` mal configurados en el panel devolvería respuestas que
     * ninguna superficie web enseña.
     *
     * ⚠️ **Lleva PII de un menor** (nombre, edad y alergias — dato de salud del art. 9). Quien la
     * llame responde de que la respuesta no se cachee (`RGPD-04`) y de que quien pregunta sea el
     * titular.
     *
     * @return list<array{key:string, label:string, value:string}>
     */
    public function eventAnswers(?string $stage = null): array
    {
        $type = $this->ticketType;

        if ($type === null || ! $type->isPack()) {
            return [];
        }

        return $type->eventAnswers(is_array($this->event_data) ? $this->event_data : [], $stage);
    }

    // ─── Post-form de datos por invitado (#217) ──────────────────────────────────

    /**
     * Respuestas por-niño persistidas (lista de N objetos `{<key>: valor}`, uno por invitado).
     * Vacío si aún no se rellenó. Lectura pura desde la columna JSON `guest_data`.
     *
     * @return array<int, array<string,string>>
     */
    public function guestData(): array
    {
        return is_array($this->guest_data) ? $this->guest_data : [];
    }

    /**
     * ¿El post-form por-niño está COMPLETO para la cantidad ACTUAL de invitados? Se DERIVA en
     * vivo de `guest_data` contra `quantity` (no de un flag congelado): si el empleado sube el nº
     * de niños, vuelve a estar incompleto solo (hay filas nuevas vacías). Para entradas o packs
     * sin esquema por-niño no hay nada que completar → `true`.
     */
    public function isGuestFormComplete(): bool
    {
        $type = $this->ticketType;
        // "No aplica" (entrada, o pack sin esquema por-niño) → nada que completar. Misma guarda
        // explícita que `guestFormStatus`, para que ambos compartan literalmente la condición.
        if ($type === null || ! $type->isPack() || $type->guestFields() === []) {
            return true;
        }

        return $type->guestDataComplete($this->guestData(), (int) $this->quantity);
    }

    /**
     * Estado del post-form para las superficies (calendario, ficha, "Mis pedidos"):
     *  - `null`     → el item NO pide datos por-niño (entrada, o pack sin `guest_fields`).
     *  - `ok`       → todos los niños tienen rellenas sus columnas obligatorias.
     *  - `pending`  → falta algún dato (o el cliente aún no lo ha rellenado).
     *
     * Fuente única del eje "FORM OK/NO"; el resto de superficies derivan de aquí (no recalculan).
     */
    public function guestFormStatus(): ?string
    {
        $type = $this->ticketType;
        if ($type === null || ! $type->isPack() || $type->guestFields() === []) {
            return null;
        }

        return $this->isGuestFormComplete()
            ? self::GUEST_FORM_STATUS_OK
            : self::GUEST_FORM_STATUS_PENDING;
    }

    /** ¿Este item pide el post-form por-niño y aún está pendiente? (aviso al cliente, badge). */
    public function needsGuestForm(): bool
    {
        return $this->guestFormStatus() === self::GUEST_FORM_STATUS_PENDING;
    }

    /**
     * ¿Es una RESERVA con post-form por-niño? (#217, individualización por reserva): un pack
     * PRINCIPAL (no complemento) NO cancelado cuyo tipo define `guest_fields`. Es la unidad de
     * acceso del post-form individualizado (1 post-form por reserva, no por pedido). El estado
     * PAGADO del pedido lo comprueba el controlador (necesita la relación `order`).
     */
    public function isGuestFormReservation(): bool
    {
        return $this->parent_item_id === null
            && ! $this->isCancelled()
            && $this->guestFormStatus() !== null;
    }

    /**
     * Progreso del post-form de ESTA reserva para el indicador «X/N fichas completas»: nº de niños
     * con sus columnas obligatorias rellenas sobre el total (= `quantity`). `0/0` si no aplica.
     *
     * @return array{done:int, total:int}
     */
    public function guestFormProgress(): array
    {
        $type = $this->ticketType;
        if ($type === null || ! $type->isPack() || $type->guestFields() === []) {
            return ['done' => 0, 'total' => 0];
        }

        return [
            'done' => $type->guestDataCompletedCount($this->guestData(), (int) $this->quantity),
            'total' => (int) $this->quantity,
        ];
    }

    /**
     * Caducidad del ENLACE FIRMADO del post-form de ESTA reserva (#217 + auditoría Fase 1 A7): la
     * fecha de SU franja + 14 días de gracia. Más preciso que el agregado del pedido
     * ({@see Order::guestFormLinkExpiresAt}) — cada cumpleaños caduca según su propia fecha. Da
     * acceso SIN sesión a datos de MENORES → no puede ser eterno. Sin franja → 14 días desde ahora.
     */
    public function guestFormLinkExpiresAt(): Carbon
    {
        $base = $this->slot?->date?->copy()->endOfDay() ?? now();

        return $base->addDays(14);
    }

    /**
     * Enlace FIRMADO (temporal) al post-form de invitados de ESTA reserva. Fuente ÚNICA del enlace,
     * reutilizada por el email `GuestFormRequest` y por el botón «Copiar enlace» del panel (para los
     * clientes de agenda SIN email, que no reciben el correo). La firma HMAC prueba la titularidad de
     * la reserva → acceso sin sesión; caduca según {@see guestFormLinkExpiresAt}.
     */
    public function guestFormSignedUrl(): string
    {
        return URL::temporarySignedRoute(
            'reservation.guests',
            $this->guestFormLinkExpiresAt(),
            ['reservation' => $this],
        );
    }

    /**
     * Enlaces FIRMADOS a la API del post-form de esta reserva (Fase 3 · paso 5). **Este es el
     * canje** que el spec §4.6.5 dejó pendiente.
     *
     * El problema que resuelve: la firma de Laravel cubre la URL EXACTA, así que la del correo
     * —que apunta a una ruta web— no autoriza un `PUT /api/v1/...`. Reenviar su `signature` a otro
     * path simplemente no valida. La salida no es inventar un almacén de credenciales nuevo: es
     * firmar también la URL de la API, **con la misma caducidad** ({@see guestFormLinkExpiresAt}),
     * y entregarla a quien ya ha demostrado acceso — la página que abre el enlace del correo, o el
     * propio `GET` de la API, que devuelve el de guardar.
     *
     * Es exactamente el modelo de seguridad que la web ya usaba —su formulario POSTea a una ruta
     * firmada con esa misma expiración—, portado sin relajar nada: mismo alcance (una reserva),
     * misma vida y misma prueba (HMAC del servidor).
     *
     * @return array{show: string, save: string}
     */
    public function guestFormApiUrls(): array
    {
        $expiresAt = $this->guestFormLinkExpiresAt();

        return [
            'show' => URL::temporarySignedRoute('api.v1.reservations.guest-form.show', $expiresAt, ['reservation' => $this->id]),
            'save' => URL::temporarySignedRoute('api.v1.reservations.guest-form.update', $expiresAt, ['reservation' => $this->id]),
        ];
    }

    /**
     * ¿Esta reserva ADMITE post-form ahora mismo? (Fase 3 · paso 5.)
     *
     * Es {@see isGuestFormReservation()} más la condición que faltaba y que hasta ahora comprobaba
     * cada superficie por su cuenta: **el pedido tiene que estar PAGADO**. Un pedido pendiente o
     * cancelado no tiene fiesta que preparar, y una entrada o un complemento no tienen invitados.
     *
     * Vive aquí y no en los controladores porque decide QUÉ es una reserva con post-form, que es
     * dominio; lo que sigue siendo de la capa HTTP es con qué código se responde a un «no».
     */
    public function acceptsGuestForm(): bool
    {
        return $this->isGuestFormReservation()
            && $this->order?->status === Order::STATUS_PAID;
    }

    /**
     * Guarda el post-form de esta reserva (Fase 3 · paso 5): datos por-niño, datos generales, sello
     * de completado y rastro de auditoría, en una sola operación.
     *
     * **Está aquí y no en los controladores por dos motivos.** El primero es que era la misma
     * secuencia repetida en cuanto apareció el segundo consumidor —el saneado, la mezcla que
     * PRESERVA los datos de la fase de reserva, el sello y el audit— y cada copia era una
     * oportunidad de olvidarse de una parte. El segundo es que la guarda de frontera de la API
     * prohíbe escribir modelos desde un controlador (`ApiBoundariesTest`), y con razón: quien decide
     * qué se persiste de un formulario con datos de menores no puede ser la capa HTTP.
     *
     * **Todo se sanea contra el ESQUEMA en servidor** (regla 12): los datos por-niño contra la
     * cantidad ACTUAL de invitados —si el empleado subió el número, aparecen filas nuevas vacías— y
     * los generales contra los campos de la fase `postform`.
     *
     * **La mezcla de `event_data` conserva todo lo que no sea `postform`**: así no se pierden los
     * datos que se dieron al reservar —aunque el esquema haya cambiado entre reservar y rellenar— y,
     * a la vez, vaciar un campo del post-form sí lo borra.
     *
     * @param  array<mixed>  $guests  respuestas por invitado, en bruto
     * @param  array<mixed>  $general  respuestas de los campos generales, en bruto
     * @param  string  $via  por dónde entró el cliente (`signed_link` | `account`), solo para el audit
     */
    public function submitGuestForm(array $guests, array $general, string $via): void
    {
        $type = $this->ticketType;

        if ($type === null) {
            return;
        }

        $guestData = $type->sanitizeGuestData($guests, (int) $this->quantity);

        $postformData = $type->sanitizeEventData($general, TicketType::EVENT_STAGE_POSTFORM);
        $postformKeys = array_column($type->eventFields(TicketType::EVENT_STAGE_POSTFORM), 'key');
        $preserved = array_diff_key($this->event_data ?? [], array_flip($postformKeys));

        $this->forceFill([
            'guest_data' => $guestData,
            'event_data' => array_merge($preserved, $postformData),
        ])->save();

        // Sello de «completado» solo si de verdad lo está (el estado es derivado; esto es auditoría).
        if ($this->isGuestFormComplete()) {
            $this->markGuestFormCompleted();
        }

        // `RGPD-02`: el rastro NO lleva PII. Ni un nombre de niño ni una alergia — solo qué reserva
        // se tocó y por dónde entró quien la tocó.
        AuditLogger::log('orders.guest_form_submitted', $this->order, [
            'order_code' => $this->order?->code,
            'order_item_id' => $this->id,
            'via' => $via,
        ]);
    }

    /**
     * Sella el momento en que el cliente envió el formulario por-niño completo. NO decide el
     * estado (que es derivado): es solo auditoría/visualización ("completado el X"). Se actualiza
     * a la última vez que se completó (a diferencia de `markCancelled`, que preserva la primera).
     */
    public function markGuestFormCompleted(): void
    {
        $this->forceFill(['guest_form_completed_at' => now()])->save();
    }

    /**
     * ¿Este item está cancelado (soft-cancel, sub-fase 7.2e)? Estado terminal:
     * no se revierte. Lectura pura desde la columna `cancelled_at`.
     */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Marca el item como cancelado. NO ejecuta refund — el orquestador
     * `Order::executePartialRefund` se encarga del lado financiero en la misma
     * transacción que llama a este método. Idempotente para clicks duplicados:
     * si ya estaba cancelado, no sobrescribe `cancelled_at` (preserva la fecha
     * de la primera cancelación para el audit log y para "Mis pedidos" cliente).
     */
    public function markCancelled(User $by): void
    {
        if ($this->cancelled_at !== null) {
            return;
        }

        $this->forceFill([
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
        ])->save();
    }

    /**
     * Scope: solo items NO cancelados. Útil para cómputos de aforo
     * (`SlotAvailability`) y agregados operativos del Order (`displayOperativeStatus`)
     * que deben excluir items terminados.
     *
     * Convención: por defecto las queries de OrderItem NO filtran cancelados
     * (necesitamos verlos para histórico). Quien necesite solo activos usa
     * este scope explícitamente.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    /**
     * Items "de agenda": PRINCIPALES (no complementos) CON franja, de pedidos
     * PAGADOS y NO cancelados. Es la regla compartida de "qué cuenta como reserva
     * del calendario y de los widgets del dashboard" — un único sitio para que
     * no derive entre el feed `CalendarEventsController` y los widgets. Deja fuera
     * los complementos (`parent_item_id` no nulo, `seats = 0`).
     */
    public function scopePaidScheduledPrincipal(Builder $query): Builder
    {
        return $query->active()
            ->whereNull('parent_item_id')
            ->whereNotNull('slot_id')
            ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PAID));
    }

    /**
     * Acota a los items cuya franja cae en el rango de fechas [$from, $to]
     * (YYYY-MM-DD, inclusive). Lo usan los widgets del dashboard según el periodo
     * seleccionado (hoy / esta semana / este mes). Las fechas se calculan en la
     * zona de presentación del parque (`DisplayTime`), coherente con cómo se
     * guardan `slots.date` (hora de pared, no UTC).
     */
    public function scopeSlotDateBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereHas('slot', fn ($q) => $q->whereBetween('date', [$from, $to]));
    }
}
