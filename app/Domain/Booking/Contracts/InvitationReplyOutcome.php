<?php

namespace App\Domain\Booking\Contracts;

use App\Domain\Booking\Models\InvitationReply;

/**
 * **El desenlace de lo que contesta un padre a una invitación**
 * (`specs/celebracion-e-invitacion.md` §4.5; `DECISIONES #574`).
 *
 * Devuelve un MOTIVO en vez de lanzar, como {@see GuestCountChange} y por lo mismo: quien llama es una
 * superficie pública que tiene que **decirle algo** a una persona que acaba de escribir el nombre de
 * su hijo, y una excepción no se puede traducir a una frase sin inventarla.
 *
 * ⚠️⚠️ **Lo que un padre puede saber por el desenlace está ACOTADO a propósito** (§7.2·R1, V6). Un
 * nombre REPETIDO no tiene motivo propio: se acepta y devuelve `accepted`, exactamente igual que la
 * primera vez. Decirle «ya nos habéis contestado por Hugo» le confirmaría a cualquiera con el enlace
 * —que se reparte a un grupo de clase entero— **quién va a esa fiesta**, y bastaba con probar nombres.
 * La hoja es en blanco también en sus errores.
 */
final class InvitationReplyOutcome
{
    /** La lista está completa y este «sí» ocuparía una plaza nueva (D2). No hay lista de espera. */
    public const REASON_FULL = 'full';

    /** La reserva ya no admite respuestas: cancelada, celebrada o pedido no pagado. */
    public const REASON_CLOSED = 'closed';

    /** Pasó el plazo de contestar, que es el mismo de cambiar invitados (D14). */
    public const REASON_CUTOFF = 'cutoff';

    /** El nombre venía vacío o no dejaba nada normalizable. */
    public const REASON_NO_NAME = 'no_name';

    /** Esta invitación ha recibido ya demasiadas respuestas (tope anti-spam de §4.5·12). */
    public const REASON_TOO_MANY = 'too_many';

    private function __construct(
        public readonly bool $accepted,
        public readonly ?string $reason = null,
        public readonly ?InvitationReply $reply = null,
        /**
         * ¿Este «sí» se unió a una plaza que YA tenía dueño —porque empareja con una ficha escrita o
         * repite un `child_key` pendiente— en vez de ocupar una nueva?
         *
         * ⚠️ Es información para el ANFITRIÓN y para las pruebas, **nunca para el padre**: decírselo
         * sería el oráculo que V6 existe para cerrar.
         */
        public readonly bool $joinedExistingPlace = false,
    ) {}

    public static function accepted(InvitationReply $reply, bool $joinedExistingPlace = false): self
    {
        return new self(accepted: true, reply: $reply, joinedExistingPlace: $joinedExistingPlace);
    }

    public static function refused(string $reason): self
    {
        return new self(accepted: false, reason: $reason);
    }
}
