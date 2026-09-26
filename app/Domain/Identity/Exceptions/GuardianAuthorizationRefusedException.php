<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Fase 6 · justificante de un menor invitado — el pedido **no admite** (o ya no admite) una
 * autorización más (`docs/specs/waiver-por-reserva.md` §4.6, §4.7).
 *
 * Un solo tipo con sus motivos (tres, y desde `#752` dos más de quien cumple), porque significan lo mismo de cara a la pantalla —«por aquí
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

    /** El justificante de QUIEN CUMPLE en una reserva que no lo sella (§4.13 de `fiesta-sistema-nuevo.md`, `#752`). */
    public const REASON_NOT_HONOREE = 'not_honoree';

    /** A quien cumple ya lo cubre otra prueba: su ficha de menor a cargo, o el justificante de su otro progenitor. */
    public const REASON_HONOREE_COVERED = 'honoree_covered';

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

    /**
     * ⚠️ Decía «ya tiene {$capacity} justificantes, que es toda su capacidad» y era falso (medido en `#752`: con CERO
     * justificantes): las plazas las ocupan también los «sí» sin firma, los menores a cargo y quien cumple.
     */
    public static function full(int $orderId, int $capacity): self
    {
        return new self(self::REASON_FULL, "La reserva #{$orderId} no tiene plazas libres para otro justificante: sus {$capacity} plazas ya tienen dueño.");
    }

    public static function notHonoree(int $orderId): self
    {
        return new self(self::REASON_NOT_HONOREE, "La reserva #{$orderId} no sella a quien cumple: no hay a quién atar el justificante.");
    }

    public static function honoreeCovered(int $orderId): self
    {
        return new self(self::REASON_HONOREE_COVERED, "A quien cumple en la reserva #{$orderId} ya lo cubre otra prueba.");
    }
}
