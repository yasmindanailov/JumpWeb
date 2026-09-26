<?php

namespace App\Domain\Booking\Models;

use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * **Lo que contesta un padre a una invitación** (`docs/specs/celebracion-e-invitacion.md` §4.4, T4·1;
 * `DECISIONES #573`).
 *
 * ⚠️⚠️ **Esto NO es una ficha del post-form, y ahí está la feature entera** (§3.1c). Una respuesta se
 * le **PROPONE** al anfitrión sobre una ficha —emparejada por nombre, o la primera vacía— y solo pasa
 * a `order_items.guest_data` cuando él **la ADOPTA al guardar**, por la puerta de siempre. Si un padre
 * escribiera `guest_data` directamente: el siguiente guardado del anfitrión **borraría** su fila
 * (`submitGuestForm()` sustituye la lista entera), cada respuesta dejaría obsoleta su página abierta
 * (`updated_at` es el testigo de los extras y del número de invitados) y una edad ajena dispararía el
 * suplemento de fiesta mixta — o sea que **un tercero movería dinero y aforo**.
 *
 * ▶ Por eso ningún fichero del `CRITICAL_RE` se toca en toda esta tanda, y `OrderCreator` tampoco.
 *
 * ⚠️ **Un nombre repetido NO se rechaza ni se anuncia** (V6, §7.2·R1): decir «ya nos habéis
 * contestado por Hugo» le confirmaría a cualquiera con el enlace que Hugo va a esa fiesta. Por eso el
 * índice `(order_item_id, child_key)` **no es único**, y por eso el padre ve el mismo desenlace que la
 * primera vez.
 */
#[Fillable([
    'party_invitation_id',
    'order_item_id',
    'attending',
    'child_name',
    'child_key',
    'data',
    'companion',
])]
class InvitationReply extends Model
{
    use Prunable;

    public const CHILD_NAME_MAX = 120;

    /** «¿Vas tú con él?» (G3). `with_adult` es el único que resuelve la entrada sin firma (D4). */
    public const COMPANION_WITH_ADULT = 'with_adult';

    public const COMPANION_ALONE = 'alone';

    public const COMPANION_UNKNOWN = 'unknown';

    /** @var list<string> Lista CERRADA: sin ella no puede existir una guarda de paridad. */
    public const COMPANIONS = [self::COMPANION_WITH_ADULT, self::COMPANION_ALONE, self::COMPANION_UNKNOWN];

    /**
     * **V3**: las respuestas se borran 14 días después de la visita. Es el mismo plazo con el que
     * caduca el enlace del post-form (`Order::guestFormLinkExpiresAt`, `RGPD-03`), y no por
     * casualidad: pasado ese margen ya no queda nada que repasar y lo que hay son nombres de menores.
     * Lo ADOPTADO sigue en `guest_data` con su régimen de siempre, que es el que la purga ya cubre.
     */
    public const RETENTION_DAYS = 14;

    protected $casts = [
        'party_invitation_id' => 'integer',
        'order_item_id' => 'integer',
        'attending' => 'boolean',
        'data' => 'array',
        'adopted_at' => 'datetime',
        'dismissed_at' => 'datetime',
        // «Al final viene» (F3c de `fiesta-sistema-nuevo.md`, `#747`): el anfitrión volvió a contar a un «no». Se escribe
        // con `forceFill` desde `PartyInvitations::rejoin()`, como la adopción: no es un dato que mande el padre.
        'host_rejoined_at' => 'datetime',
    ];

    /** Todavía sin resolver por el anfitrión: ni adoptada ni descartada. */
    public function isPending(): bool
    {
        return $this->adopted_at === null && $this->dismissed_at === null;
    }

    /** @param  Builder<InvitationReply>  $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('adopted_at')->whereNull('dismissed_at');
    }

    /**
     * @return BelongsTo<PartyInvitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(PartyInvitation::class, 'party_invitation_id');
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Las respuestas cuya visita pasó hace más de {@see RETENTION_DAYS} días.
     *
     * ⚠️⚠️ **Esto no corre solo**: el modelo tiene que estar en la lista EXPLÍCITA de `model:prune` de
     * `routes/console.php` — un `Prunable` que no entre ahí **no se poda nunca**, y sería una promesa
     * de conservación escrita y nunca cumplida. Va registrado en el mismo commit que este fichero.
     *
     * ⚠️ **La fecha se compara en la zona del PARQUE**, no en la del contenedor (UTC): las franjas se
     * guardan en hora de pared, y con `now()` el corte se movería una o dos horas según el mes.
     *
     * ⚠️ Una respuesta cuya reserva no tiene franja no la alcanza este criterio, y **no puede existir**:
     * la invitación solo nace sobre una reserva abierta (`GuestCountPolicy::isOpenFor`, que exige
     * franja), y si la franja se borrara, su `cascadeOnDelete` se llevaría la línea y con ella —en
     * cascada— la invitación y estas filas. Queda declarado en vez de inventar un segundo plazo.
     *
     * @return Builder<InvitationReply>
     */
    public function prunable(): Builder
    {
        $cutoff = DisplayTime::now()->subDays(self::RETENTION_DAYS)->toDateString();

        /** @var Builder<InvitationReply> $query */
        $query = static::query();

        return $query->whereIn(
            'order_item_id',
            OrderItem::query()
                ->select('order_items.id')
                ->join('slots', 'slots.id', '=', 'order_items.slot_id')
                ->whereDate('slots.date', '<', $cutoff),
        );
    }
}
