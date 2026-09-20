<?php

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Services\AgeFamilySeal;
use App\Domain\Booking\Services\GuestAgeMixReader;
use App\Domain\Booking\Services\GuestCardOrder;
use App\Domain\Booking\Services\MixedPartySurcharge;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /**
     * Memo por instancia del veredicto de fiesta mixta: una superficie pregunta varias veces al
     * pintar la misma fila (el nombre, la pastilla, un aviso) y el recorrido de `guest_data` no
     * tiene por qué repetirse. No se persiste ni se serializa.
     */
    private ?bool $mixedPartyMemo = null;

    protected $casts = [
        'quantity' => 'integer',
        'free_quantity' => 'integer',
        'unit_price' => 'integer',
        'is_credit' => 'boolean',
        'seats' => 'integer',
        'extra_minutes' => 'integer',
        'event_data' => 'array',
        'guest_data' => 'array',
        'guest_form_completed_at' => 'datetime',
        // La VERSIÓN del enlace firmado del post-form (`#413` D14): subirla invalida los enlaces ya
        // emitidos. Casteada porque viaja DENTRO de la firma y se compara con `===` contra el `v` de
        // la URL: un `'0'` de SQLite frente a un `0` de MySQL sería una diferencia de motor
        // decidiendo quién entra.
        'guest_form_link_version' => 'integer',
        // El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2): lo que se
        // acordó AL COMPRAR. Es un hecho de la línea y no una consulta al catálogo — cambiar el
        // interruptor del producto mañana no reescribe lo que este cliente marcó ayer.
        'guardian_authorization' => 'boolean',
        'age_family_seal' => 'array',
        'cancelled_at' => 'datetime',
        // Cuándo salió el AVISO DE LA VÍSPERA de esta reserva (T7·2b). ⚠️ Se escribe por el
        // constructor de consultas, nunca por el modelo: `updated_at` es el testigo del post-form
        // (§1.3·2) y mandar un correo no puede dejar obsoleta la página del cliente.
        'eve_notice_at' => 'datetime',
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
     * La INVITACIÓN DIGITAL de esta reserva (`specs/celebracion-e-invitacion.md` §4.4, `#573`).
     * `null` mientras el anfitrión no haya abierto su formulario: nace sola al pintarlo.
     *
     * @return HasOne<PartyInvitation, $this>
     */
    public function partyInvitation(): HasOne
    {
        return $this->hasOne(PartyInvitation::class);
    }

    /**
     * Lo que han contestado los padres. Cuelga también de la reserva —y no solo de la invitación—
     * porque la puerta y la hoja de sala leen por LOTES de reservas del día.
     *
     * @return HasMany<InvitationReply, $this>
     */
    public function invitationReplies(): HasMany
    {
        return $this->hasMany(InvitationReply::class);
    }

    /**
     * Ventana horaria a MOSTRAR para esta línea: hora de entrada → entrada + la
     * **duración EFECTIVA de la reserva** ({@see occupiedMinutes()}: la del producto más lo que la
     * alarguen sus horas extra), p. ej. un pack de 2h entrando a las 10:00 → "10:00–12:00", y
     * "10:00–13:00" si compró una hora extra. Las franjas de aforo son siempre de 60 min (rejilla horaria),
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
        // ⚠️ La duración EFECTIVA, no la del producto (`specs/hora-extra.md` §10.3): una fiesta con
        // hora extra dura más, y esta ventana es lo que el operador lee en la hoja de sala y el
        // cliente en su reserva. Decir «15:00–17:00» de una fiesta que acaba a las 18:00 no es un
        // detalle de estilo: es la sala vacía una hora antes de tiempo, o el grupo dentro cuando el
        // parque cree que ya se ha ido.
        $duration = $this->occupiedMinutes();

        // Ilimitada: hora de entrada + marca explícita de que no hay fin ("10:00 – sin límite"),
        // en paralelo a la franja con duración ("10:00–12:00").
        if (empty($duration)) {
            return $start->format('H:i').' – '.__('tickets.time_no_limit');
        }

        return $start->format('H:i').'–'.$start->addMinutes((int) $duration)->format('H:i');
    }

    /**
     * La cantidad de esta línea **con su nombre**, en la voz del CLIENTE: «8 invitados» para un
     * pack, «2 entradas» para entradas y complementos («1 unidad» / «3 unidades»).
     *
     * ⚠️⚠️ **Existe por el defecto `L2`** (`DECISIONES #128`,
     * `specs/desglose-dinero-cliente.md` §17.1). La tarjeta del cliente pintaba el número pelado
     * seguido del importe de la línea —`8×216,00 €`—, que se lee como «8 unidades a 216 € cada una»
     * = 1.728 € **cuando son 8 invitados y 216 € en total**. Un número sin sustantivo no distingue
     * cantidad de importe, y en dinero esa ambigüedad no es un detalle de estilo.
     *
     * ⚠️ Va en el DOMINIO y se publica compuesta, como `displayTimeWindow()`: el sustantivo depende
     * del tipo de producto y del idioma, y era la quinta copia de la misma regla —el panel la
     * rehacía en cuatro blades y el widget, cada uno con su matiz—. La voz es la del cliente
     * (`tickets.*`): el panel conserva la suya, que es tercera persona (§10.4).
     */
    public function displayQuantityLabel(): string
    {
        $cantidad = (int) $this->quantity;

        if ($this->parent_item_id !== null) {
            return trans_choice('tickets.units_count', $cantidad, ['count' => $cantidad]);
        }

        return $this->ticketType?->isPack()
            ? __('tickets.guests_count', ['count' => $cantidad])
            : trans_choice('tickets.entries_count', $cantidad, ['count' => $cantidad]);
    }

    /**
     * ¿Esta reserva es una fiesta MIXTA? (`docs/specs/cumple-mixto.md` §13). Predicado DERIVADO de
     * las edades declaradas, memoizado por instancia — una superficie puede preguntarlo varias
     * veces al pintar la misma fila.
     *
     * ⚠️ Lee el veredicto y NO el suplemento escrito, y la diferencia importa: una fiesta con
     * invitados de otro tramo es mixta aunque los dos packs cuesten lo mismo y no haya nada que
     * cobrar (§8.8). La etiqueta describe un HECHO; el dinero es otra pregunta.
     *
     * ⚠️⚠️ **Necesita `ticketType` y `slot` cargadas.** Sin ellas son dos consultas por fila, y
     * quien pinte una lista las paga sin enterarse. Las superficies que lo usan las traen con
     * `with()`; lo vigila `MixedPartyLabelSurfacesTest`.
     */
    public function isMixedParty(): bool
    {
        return $this->mixedPartyMemo ??= app(GuestAgeMixReader::class)->for($this)->mixed;
    }

    /**
     * El SELLO de condiciones de esta reserva (`specs/cumple-mixto.md` §21, `DECISIONES #284` D1): la
     * familia por edad, sus tramos y sus precios TAL COMO SE VENDIÓ. Lo escribe `AgeFamilySealer` al
     * nacer y al cambiar de producto o de día; el veredicto de fiesta mixta deriva de esto y no del
     * catálogo, así que ningún cambio de catálogo mueve una reserva vendida.
     *
     * `null` = la línea no lo lleva (una entrada, un complemento, o una reserva anterior al sello).
     * Para el veredicto eso es SILENCIO, no «no aplica»: no se afirma nada y no se mueve nada.
     */
    public function ageFamilySeal(): ?AgeFamilySeal
    {
        return AgeFamilySeal::fromArray($this->age_family_seal);
    }

    /**
     * ¿Esta línea HIJA se vendió con una unidad distinta de la que su enganche declara hoy?
     * (`specs/hora-extra.md` §12.8, `#448`.)
     *
     * ▶ Existe para que la ficha del pedido lo DIGA: con divergencia, la línea queda acotada a su
     * propia cantidad —no se puede subir— y sin este aviso el operador lo descubre al no poder
     * hacerlo, **sin que nada se lo explique**. Es la mitad de presentación de la regla.
     *
     * ⚠️ Devuelve `false` en cuanto falta algo (no es hija, no hay sello, no hay enganche): el
     * silencio no es divergencia, y afirmar una discrepancia que no se sabe sería peor que callar.
     *
     * ⚠️ **Es de PRESENTACIÓN, y por eso vive aquí y no en el `CRITICAL_RE`**: quien DECIDE sobre la
     * unidad de una línea vendida es `AddonResolver::soldQuantityUnit()`, y este método no gobierna
     * nada — solo compara para poder contarlo.
     */
    public function addonUnitDivergesFromCatalogue(): bool
    {
        $sealed = $this->addon_quantity_mode;
        if ($this->parent_item_id === null || ! is_string($sealed) || $sealed === '') {
            return false;
        }

        $pivot = $this->parent?->ticketType?->addons()
            ->where('ticket_types.id', $this->ticket_type_id)
            ->first()?->pivot;

        return $pivot !== null && $pivot->quantityUnit() !== $sealed;
    }

    /**
     * El nombre del producto de ESTA reserva, con la etiqueta «MIXTA» si lo es.
     *
     * ▶ **Existe por la misma razón que sus hermanas** `displayTimeWindow()` y
     * `displayQuantityLabel()`: la regla se compone UNA vez en el dominio y las superficies la leen.
     * `[owner, 2026-08-29]`: «¿no podemos añadir esa etiqueta al nombre y que el resto lo coja de
     * ahí, en vez de añadirlo a cada superficie?». Sí — pero el sitio único es la RESERVA, no el
     * producto: `TicketType` lo comparten todas las fiestas y no puede saber si ESTA es mixta.
     *
     * ⚠️ **Es para las superficies de TEXTO PLANO** —los dos PDF, los correos, la puerta, el
     * calendario—, las que no pueden pintar una pastilla. Las que sí (la ficha del pedido, y el
     * cajón cuando llegue) leen `isMixedParty()` y la pintan aparte: si la etiqueta viviera SOLO
     * dentro del nombre, dejaría de ser un dato —nadie podría filtrar «las fiestas mixtas de
     * mañana»— y no habría forma de darle estilo propio.
     *
     * ⚠️ **No lo usan el catálogo ni el editor**: ahí el nombre es el del PRODUCTO (el destino de un
     * cambio, una fila del catálogo), no el de una fiesta concreta.
     */
    public function displayProductName(): string
    {
        $name = (string) ($this->ticketType?->tr('name') ?? '—');

        return $this->isMixedParty()
            ? __('tickets.mixed_party_product_name', ['name' => $name, 'badge' => __('tickets.mixed_party_badge')])
            : $name;
    }

    /**
     * Importe REAL cobrado por esta línea, en céntimos: `(quantity − free_quantity) × unit_price`.
     *
     * `free_quantity` son las unidades INCLUIDAS gratis (complementos incluidos en un pack: la
     * primera tarta, el menú por invitado…). Histórico — se fijó al crear el pedido. Es la ÚNICA
     * fuente de verdad del subtotal de una línea: todas las superficies (totales del pedido, PDFs,
     * "Mis pedidos", capacidad de reembolso) deben usar este helper en vez de multiplicar
     * `quantity × unit_price`, que ignoraría las unidades gratis. Capado a 0 por defensa.
     *
     * ▶ **Una línea de CRÉDITO devuelve su subtotal EN NEGATIVO** (T4 de reservas mixtas,
     * `specs/cumple-mixto.md` §24.2): el −X € del descuento es el espejo del suplemento y su línea
     * RESTA del valor de la reserva. La columna no puede llevar el signo (`unit_price` es
     * UNSIGNED, medido): lo pone este helper, en UN solo sitio, para que ningún consumidor decida
     * el signo por su cuenta ni multiplique a mano.
     */
    public function chargedSubtotalCents(): int
    {
        $paidUnits = max(0, (int) $this->quantity - (int) $this->free_quantity);
        $subtotal = $paidUnits * (int) $this->unit_price;

        return $this->is_credit ? -$subtotal : $subtotal;
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
     * **Los minutos que esta reserva OCUPA de verdad**: la duración de su producto más lo que se
     * alargó con complementos que extienden la estancia (`specs/hora-extra.md` §10.3.2).
     *
     * Fuente ÚNICA para quien tenga el modelo cargado. Los dos mapas de ocupación NO pasan por
     * aquí a propósito —son SQL puro por rendimiento (`#465`) y suman la columna en el `SELECT`—,
     * pero la aritmética es la misma y **`extra_minutes` es el único dato que las une**.
     *
     * ⚠️ `null` (producto ilimitado) se queda en `null`: lo que ya llega al cierre no se puede
     * alargar, y sumarle minutos daría un número donde el aforo espera «hasta el cierre». El guard
     * del pivote impide vender una extensión sobre un producto sin duración.
     */
    public function occupiedMinutes(): ?int
    {
        $base = $this->ticketType?->duration_min;

        return $base === null ? null : (int) $base + (int) ($this->extra_minutes ?? 0);
    }

    /**
     * ¿Este item ya TERMINÓ de verdad? Lectura pura — no toca BD.
     *
     * ❗❗❗ **Tenía DOS defectos, y en direcciones OPUESTAS** (`DECISIONES #423` · A1,
     * `specs/hora-extra.md` §10.8): comparaba el fin de la **FRANJA** —que con la rejilla de 60 min
     * se queda corto para todo lo que dure más, una fiesta de 2 h incluida— y lo parseaba en
     * `config('app.timezone')` = **UTC**, cuando las franjas guardan **hora de pared del parque**.
     * Uno adelantaba ~1 h y el otro atrasaba 1–2 h, así que **se compensaban por accidente**: medido
     * sobre una fiesta de 2 h que empieza a las 15:00 y acaba a las 17:00 del parque, se declaraba
     * terminada a las **18:00**; arreglando solo la duración habría pasado a las **19:00**. *Un
     * defecto que se compensa con otro no se arregla por mitades.*
     *
     * Reglas:
     *  - Si tiene `parent_item_id` (= es un addon): hereda el estado del padre.
     *  - Si tiene franja: **inicio + duración EFECTIVA** ({@see occupiedMinutes()}, que incluye las
     *    horas extra), en la zona OPERATIVA del parque (`AFORO-09`).
     *  - **Duración ilimitada** → cae al fin de la franja, que es lo único que hay; también en hora
     *    del parque.
     *  - Sin franja ni padre (caso teórico, no esperado en v1): devuelve false.
     *
     * ⚠️ De este predicado cuelgan el `readonly` del post-form (web y API), el `item_finished` del
     * gate de edición del panel, la ventana de dinero del suplemento mixto, el `$finished` del LIBRO
     * (`OrderBook`, la liquidación implícita) y «Mis reservas»: mover esta frontera los mueve a
     * todos, y por eso el arreglo fue tanda propia.
     */
    public function isFinishedInPractice(): bool
    {
        if ($this->parent_item_id !== null) {
            return $this->parent?->isFinishedInPractice() ?? false;
        }

        $slot = $this->slot;
        if ($slot === null || $slot->date === null) {
            return false;
        }

        $minutes = $this->occupiedMinutes();
        $day = $slot->date->format('Y-m-d');
        $zone = DisplayTime::timezone();

        // Ilimitada (sin duración propia): lo único que acota es el fin de su franja.
        if ($minutes === null) {
            return $slot->end_time === null
                ? false
                : CarbonImmutable::parse($day.' '.$slot->end_time, $zone)->isPast();
        }

        if ($slot->start_time === null) {
            return false;
        }

        return CarbonImmutable::parse($day.' '.$slot->start_time, $zone)
            ->addMinutes($minutes)
            ->isPast();
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

        // ▶ `[DECIDIDO owner, 2026-08-31]` (`#284` D6, spec §22.2): una edad SIN PRODUCTO en las
        // condiciones selladas de la reserva no deja completar el formulario. Se guarda lo escrito y
        // no toca el desglose, pero la ficha no está resuelta hasta que el parque decida (T3).
        return $type->guestDataComplete($this->guestData(), (int) $this->quantity)
            && $this->guestAgesWithoutProduct() === [];
    }

    /**
     * Índices de las fichas cuya EDAD no tiene producto en las condiciones SELLADAS de esta reserva
     * (`#284` D6, spec §22.2). Vacío si la reserva no participa en una familia por edad, si no lleva
     * sello, o si todas las edades caen en algún régimen.
     *
     * ⚠️ Se atajan ANTES de derivar los casos que no pueden tener ninguna: sin sello o sin familia
     * sellada no hay nada que mirar, y esto lo llaman superficies de LISTA (`guestFormStatus()`)
     * que no siempre traen `slot` cargada — derivar ahí sería una consulta por fila.
     *
     * @return list<int>
     */
    public function guestAgesWithoutProduct(): array
    {
        $seal = $this->ageFamilySeal();
        if ($seal === null || ! $seal->participates()) {
            return [];
        }

        $out = [];
        foreach (app(GuestAgeMixReader::class)->guestRegimes($this) as $i => $row) {
            if ($row['state'] === GuestAgeMixReader::ROW_OUT_OF_RANGE) {
                $out[] = (int) $i;
            }
        }

        return $out;
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

        // Una ficha con todas sus columnas pero con una edad SIN PRODUCTO no cuenta como hecha
        // (D6, spec §22.2): el «8 de 8» diría que no queda nada por resolver, y sí queda.
        $done = array_diff(
            $type->guestDataCompletedIndexes($this->guestData(), (int) $this->quantity),
            $this->guestAgesWithoutProduct(),
        );

        return [
            'done' => count($done),
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
        return $this->signedGuestFormRoute('reservation.guests');
    }

    /**
     * El enlace firmado del POST (guardar). Vive aquí y no en el controlador —donde estaba— por lo
     * mismo que su hermano: **las URLs firmadas del mismo formulario tienen que llevar todas la
     * versión del enlace** ({@see guestFormLinkVersion}), y una compuesta a mano en una plantilla es
     * la que se queda sin ella el día que alguien añade un parámetro.
     */
    public function guestFormSignedStoreUrl(): string
    {
        return $this->signedGuestFormRoute('reservation.guests.store');
    }

    /**
     * El enlace firmado del POST que PERSONALIZA la invitación (T6·1,
     * `specs/celebracion-e-invitacion.md` §4.7).
     *
     * ⚠️⚠️ **Es una puerta más del post-form, así que va por el MISMO sitio.** El anfitrión llega
     * muchas veces sin sesión —el enlace viaja por correo semanas antes—, de modo que este POST
     * necesita su propia firma; componerla en la plantilla la dejaría **sin la versión del enlace**
     * (D14) y rotar cerraría el formulario dejando abierta la personalización, que es la misma
     * credencial por otro path. Es el mismo error que `guestFormApiUrls()` evita en la API.
     */
    public function invitationSignedUpdateUrl(): string
    {
        return $this->signedGuestFormRoute('reservation.invitation.update');
    }

    /** El enlace firmado de «No lo apuntes» (T6·3, §7.2·R11), por la misma puerta y con la misma versión. */
    public function invitationSignedDismissUrl(): string
    {
        return $this->signedGuestFormRoute('reservation.invitation.dismiss');
    }

    /** El enlace firmado de «Escribir el recordatorio» (T6·6, §4.7), tercera puerta del mismo formulario. */
    public function invitationSignedRemindUrl(): string
    {
        return $this->signedGuestFormRoute('reservation.invitation.remind');
    }

    /**
     * La VERSIÓN del enlace del post-form (`specs/complementos-post-reserva.md` §4.6.bis, `#413` D14).
     *
     * Viaja como parámetro `v` dentro de la URL firmada y la autorización la compara con ésta: subirla
     * invalida en el acto todos los enlaces emitidos antes, que es la palanca que `RGPD-06` no tenía
     * para esta credencial (es HMAC: no hay fila que revocar). ⚠️ Los enlaces emitidos ANTES de que
     * la columna existiera viajan sin `v` y se leen como versión 0, así que **no se rompe ninguno al
     * desplegar**: la versión solo separa cuando alguien rota.
     */
    public function guestFormLinkVersion(): int
    {
        return (int) ($this->guest_form_link_version ?? 0);
    }

    /**
     * Rota el enlace del post-form: sube la versión y con ello los enlaces anteriores dejan de abrir
     * (403). Idempotente en el sentido que importa —cada llamada emite una versión nueva— y **NO
     * toca lo que ya se hizo con el enlace viejo**: rotar retira una credencial, no deshace una
     * gestión. Quitar lo que un tercero añadió es otro gesto del operador.
     */
    public function rotateGuestFormLink(): int
    {
        $next = $this->guestFormLinkVersion() + 1;
        $this->forceFill(['guest_form_link_version' => $next])->save();

        return $next;
    }

    /**
     * La firma de una ruta web del post-form de ESTA reserva, con su caducidad y su versión. Punto
     * único: la caducidad la fija `RGPD-03` y la versión, D14.
     */
    private function signedGuestFormRoute(string $name): string
    {
        return URL::temporarySignedRoute(
            $name,
            $this->guestFormLinkExpiresAt(),
            ['reservation' => $this, 'v' => $this->guestFormLinkVersion()],
        );
    }

    /**
     * Enlace FIRMADO (temporal) al JUSTIFICANTE de un menor invitado **de ESTA reserva**
     * (`docs/specs/waiver-por-reserva.md` §13). **Fuente ÚNICA del enlace**, como
     * {@see guestFormSignedUrl()} lo es del post-form — y ahora, por fin, su gemelo exacto.
     *
     * ⚠️⚠️ **Vivía en `Order` hasta `#401` y lo cazó el owner con datos reales**: un pedido con dos
     * visitas en días distintos daba UN enlace, y la hoja que firmaba el padre decía *«Días de la
     * visita: 03/09/2026 · 07/09/2026»* sin decir a cuál iba su hijo. *Un padre no autoriza un
     * pedido: autoriza que su hijo entre a una visita concreta.*
     *
     * ⚠️ **La caducidad sigue saliendo del PEDIDO** (`guestFormLinkExpiresAt()`, la última franja +
     * 14 días): la fija `RGPD-03` y dos enlaces de la misma compra con plazos distintos serían dos
     * reglas.
     *
     * ⚠️⚠️ **Es una credencial portadora y NO puede publicarse en el contexto de cuenta**, que se
     * siembra en el HTML de cada página con sesión (la prohibición que `AccountContextResource`
     * documenta). Se sirve bajo demanda, por una acción explícita.
     */
    /**
     * @param  array<string, string|int>  $extra  parámetros que viajan DENTRO de la firma
     *
     * ⚠️⚠️ **`$extra` se firma, no se pega después** (`DECISIONES #703`). Medido: añadir un `&x=y` a una
     * URL ya firmada la invalida —el HMAC cubre la query entera—, así que componerla concatenando
     * habría llevado a un **403** justo al padre que acaba de decir que su hijo viene. Lo usa el recibo
     * de la invitación para atar la firma a su respuesta (§4.5·7) y prerrellenar el nombre del menor.
     */
    public function guardianAuthorizationSignedUrl(array $extra = []): string
    {
        return URL::temporarySignedRoute(
            'reservation.authorization',
            $this->guestFormLinkExpiresAt(),
            ['reservation' => $this] + $extra,
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
        // La versión del enlace (D14) viaja también en las URLs de la API: si no, rotar cerraría la
        // web y dejaría abierta la puerta de la app, que es la misma credencial por otro path.
        $params = ['reservation' => $this->id, 'v' => $this->guestFormLinkVersion()];

        return [
            'show' => URL::temporarySignedRoute('api.v1.reservations.guest-form.show', $expiresAt, $params),
            'save' => URL::temporarySignedRoute('api.v1.reservations.guest-form.update', $expiresAt, $params),
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
     * ▶ **T3 · F (`specs/cumple-mixto.md` §23.3): el PANEL entra por esta MISMA puerta**, con
     * `$general = null` y el operador en `$by`. `null` en los generales significa «no los toques»
     * —la pestaña «Invitados» edita fichas, no lo demás— y es distinto de `[]`, que significa
     * «vacíalos». Con `$by`, la reconciliación toma al operador como actor y una razón propia
     * (`MixedPartySurcharge::REASON_PANEL_GUEST_FORM`), así el correo del cambio de importe dice
     * «el parque» y no «has actualizado…» — antes de la T3 el operador solo podía usar el enlace
     * del cliente, y el rastro mentía (`via: signed_link`).
     *
     * ⚠️⚠️ **`$guests` es NULABLE desde la T0 de `specs/complementos-post-reserva.md` (`#413`), y es
     * un arreglo de PÉRDIDA DE DATOS, no una comodidad.** Hasta el 2026-09-03 las dos superficies de
     * cliente convertían la ausencia de la clave en `[]` (`$validated['guests'] ?? []`,
     * `$request->input('guests', [])`), y `sanitizeGuestData([], N)` **devuelve N filas VACÍAS**:
     * medido sobre la reserva 159 (`R-BEEL3E`, 8 invitados), un `PUT` que solo mandaba `general`
     * **borraba los nombres y las edades de los ocho niños**, respondiendo 200. La misma forma que
     * ya se conocía de `$general` —y que la propia API documentaba como una virtud, «el formulario
     * se guarda a trozos»— pero sobre el dato del art. 9. Ahora las dos claves significan lo mismo:
     * **ausente = no lo toques · `[]` = vacíalo**, y quien decide cuál es cada caso es la capa HTTP
     * mirando si la clave VIENE, nunca un `?? []`.
     *
     * ⚠️ **El sello NO se re-estampa cuando nada cambió** (misma tanda): `guest_form_completed_at`
     * se escribía con `now()` en cada guardado completo, y como `updated_at` del ítem es el token
     * optimista de CINCO puertas del operador, un cliente repasando su formulario invalidaba los
     * modales que el operador tuviera abiertos. Se sella la PRIMERA vez y cada vez que el contenido
     * cambia de verdad; un guardado idéntico es ahora un no-op para la fila.
     *
     * @param  array<mixed>|null  $guests  respuestas por invitado, en bruto; `null` = conservarlas
     * @param  array<mixed>|null  $general  respuestas de los campos generales, en bruto; `null` = conservarlos
     * @param  string  $via  por dónde entró quien guardó (`signed_link` | `account` | `panel`), solo para el audit
     * @param  User|null  $by  el OPERADOR cuando guarda el panel; `null` = el propio titular
     */
    public function submitGuestForm(?array $guests, ?array $general, string $via, ?User $by = null): void
    {
        $type = $this->ticketType;

        if ($type === null) {
            return;
        }

        $attributes = [];

        if ($guests !== null) {
            // ⚠️ `orderGuestRows()` PRIMERO: compactar sobre las claves sin ordenar deshacía el
            // arreglo de `#571` —el orden de la página no es el de las posiciones— y lo cazó su caso.
            $rows = TicketType::orderGuestRows($guests);

            // ⚠️⚠️ **Se COMPACTA, pero SOLO cuando el saneo va a TIRAR algo** (`§7.1·5` de
            // `celebracion-e-invitacion.md`, `DECISIONES #718`).
            //
            // El saneo recorta por el FINAL, y lo que llega aquí viene en el orden que el navegador
            // pintó: con la cantidad ya bajada por `GuestCountAdjuster`, recortar sin más tiraba a
            // los niños que YA habían confirmado y dejaba fichas vacías. La regla vive en
            // `GuestCardOrder`, la misma que usa el ajuste — una punta de cada lado de la costura.
            //
            // ❗ **La condición NO es cosmética.** Compactar en todo guardado mueve las fichas del
            // anfitrión sin que él lo pida, y de la posición cuelgan el emparejado de las propuestas
            // y la hoja de sala: lo cazó `InvitationApiTest`, donde una ficha vacía delante de «Hugo»
            // es justo lo que se está midiendo. Si no se recorta, no hay nada que proteger.
            if (count($rows) > (int) $this->quantity) {
                $rows = app(GuestCardOrder::class)->confirmedFirst($this, $rows);
            }

            $attributes['guest_data'] = $type->sanitizeGuestData($rows, (int) $this->quantity);
        }

        if ($general !== null) {
            $postformData = $type->sanitizeEventData($general, TicketType::EVENT_STAGE_POSTFORM);
            $postformKeys = array_column($type->eventFields(TicketType::EVENT_STAGE_POSTFORM), 'key');
            $preserved = array_diff_key($this->event_data ?? [], array_flip($postformKeys));
            $attributes['event_data'] = array_merge($preserved, $postformData);
        }

        // ¿Cambió algo DE VERDAD? Se pregunta ANTES de guardar y sobre los atributos ya saneados,
        // que es lo único que distingue «el cliente corrigió una alergia» de «el cliente volvió a
        // pulsar Guardar». De ahí cuelga el sello (y con él, el token optimista del operador).
        $this->forceFill($attributes);
        $changed = $this->isDirty();
        $this->save();

        // Sello de «completado» solo si de verdad lo está (el estado es derivado; esto es auditoría)
        // y solo si es la PRIMERA vez o si el contenido se movió: re-estamparlo por un guardado
        // idéntico bumpea `updated_at` y le rompe el modal al operador sin que nadie haya cambiado nada.
        if ($this->isGuestFormComplete() && ($this->guest_form_completed_at === null || $changed)) {
            $this->markGuestFormCompleted();
        }

        // `RGPD-02`: el rastro NO lleva PII. Ni un nombre de niño ni una alergia — solo qué reserva
        // se tocó y por dónde entró quien la tocó.
        AuditLogger::log('orders.guest_form_submitted', $this->order, [
            'order_code' => $this->order?->code,
            'order_item_id' => $this->id,
            'via' => $via,
        ]);

        // El suplemento de fiesta MIXTA sigue a las edades (`specs/cumple-mixto.md` §12,
        // `[DECIDIDO owner]`). Va aquí, después del guardado, por el mismo motivo que el resto de
        // esta secuencia: es el ÚNICO punto por el que entran los datos por-niño —web, API y desde
        // la T3 el panel— y una copia por superficie sería una oportunidad de olvidarse.
        //
        // ⚠️ El reconciliador vuelve a LEER la reserva con la fila bloqueada: no se le pasa nada
        // calculado aquí. De lo contrario, dos guardados simultáneos escribirían dos suplementos.
        // Con `$by` (panel), el actor del ajuste es el OPERADOR y la razón la del panel: el rastro
        // y el correo dicen quién movió el dato de verdad (§23.3).
        $actor = $by ?? $this->order?->user;
        if ($actor !== null) {
            app(MixedPartySurcharge::class)->reconcile(
                $this,
                $actor,
                $by !== null ? MixedPartySurcharge::REASON_PANEL_GUEST_FORM : MixedPartySurcharge::REASON_GUEST_FORM,
            );
        }
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
