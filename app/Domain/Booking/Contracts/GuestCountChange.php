<?php

namespace App\Domain\Booking\Contracts;

/**
 * El resultado de pedir un cambio en el número de INVITADOS de una reserva
 * (`specs/invitados-en-post-form.md` §4.1, `DECISIONES #444`).
 *
 * ⚠️ **Devuelve un MOTIVO en vez de lanzar, y no es estilo**: esta operación viaja dentro del mismo
 * guardado que las fichas de los invitados y los extras, y **el rechazo de la cantidad no puede
 * tumbar el guardado de los nombres y las alergias**, que es la razón de ser de esa pantalla. Es la
 * misma decisión que `PostFormAddons` tomó con sus `blocked` (§4.5.3 de su spec): la no-atomicidad
 * es deliberada, y por eso el desenlace la DICE.
 */
final class GuestCountChange
{
    /** No se pidió ningún cambio (la clave no venía, o la cantidad ya era ésa). */
    public const REASON_NOOP = 'noop';

    /** La reserva ya no admite cambios: cancelada, celebrada, pedido no pagado o sin post-form. */
    public const REASON_CLOSED = 'closed';

    /** Fuera del plazo para cambiar los invitados. */
    public const REASON_CUTOFF = 'cutoff';

    /** Por encima del máximo del producto. */
    public const REASON_ABOVE_MAX = 'above_max';

    /** Por debajo del mínimo CONTRATABLE del pack. */
    public const REASON_BELOW_MIN = 'below_min';

    /**
     * Por debajo de lo que ya tiene dueño: menores a cargo asignados + justificantes firmados.
     *
     * ⚠️ **Es un motivo DISTINTO de `below_min` a propósito**: el remedio no es el mismo. Al primero
     * se le dice el mínimo del pack; a éste, que antes tiene que quitar a alguien de la lista.
     * Fundirlos en «no puedes bajar tanto» deja al cliente sin saber qué hacer.
     */
    public const REASON_BELOW_ASSIGNED = 'below_assigned';

    /** La sala no admite tantos invitados a esa hora (re-comprobado bajo el lock). */
    public const REASON_SOLD_OUT = 'sold_out';

    /** El formulario venía de una versión anterior: alguien tocó la reserva mientras tanto. */
    public const REASON_STALE = 'stale';

    public function __construct(
        public readonly bool $applied = false,
        public readonly ?string $reason = null,
        public readonly int $from = 0,
        public readonly int $to = 0,
        public readonly int $deltaCents = 0,
        /** Fichas RELLENAS que se perdieron al bajar (0 al subir). */
        public readonly int $discardedForms = 0,
    ) {}

    public static function noop(): self
    {
        return new self(reason: self::REASON_NOOP);
    }

    public static function blocked(string $reason, int $from, int $to): self
    {
        return new self(reason: $reason, from: $from, to: $to);
    }
}
