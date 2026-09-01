<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Fase 6 · justificante de un menor invitado — el pedido **no admite** (o ya no admite) una
 * autorización más (`docs/specs/waiver-por-reserva.md` §4.6, §4.7).
 *
 * Un solo tipo con tres motivos, porque los tres significan lo mismo de cara a la pantalla —«por aquí
 * no se puede firmar»— y solo cambia la FRASE. Separarlos en tres clases obligaría a la capa de
 * entrega a conocer tres, y a la vista a repetir el mismo `match`.
 *
 * ⚠️ **Los tres se comprueban DENTRO de la transacción y bajo el lock del responsable**, no al pintar
 * el formulario (`SEC-04` aplicado): entre que el padre abre el enlace y lo envía puede pasar la
 * visita, cancelarse el pedido o llenarse el cupo — y un `POST` forjado desde una pestaña vieja es el
 * caso real, no el hipotético.
 */
class GuardianAuthorizationRefusedException extends RuntimeException
{
    /** El pedido no está pagado (o se canceló): no hay reserva que autorizar. */
    public const REASON_NOT_PAID = 'not_paid';

    /** La visita ya pasó: autorizar a posteriori algo que ya ocurrió no prueba nada. */
    public const REASON_CLOSED = 'closed';

    /** No se puede autorizar a más gente de la que se compró. */
    public const REASON_FULL = 'full';

    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function notPaid(int $orderId): self
    {
        return new self(self::REASON_NOT_PAID, "El pedido #{$orderId} no está pagado: no admite justificantes.");
    }

    public static function closed(int $orderId): self
    {
        return new self(self::REASON_CLOSED, "La visita del pedido #{$orderId} ya pasó: el justificante se cerró.");
    }

    public static function full(int $orderId, int $capacity): self
    {
        return new self(self::REASON_FULL, "El pedido #{$orderId} ya tiene {$capacity} justificantes, que es toda su capacidad.");
    }
}
