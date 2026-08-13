<?php

namespace App\Domain\Booking\Contracts;

use App\Domain\Booking\Models\Order;

/**
 * Veredicto de admisión de un REINTENTO de pago (Fase 3 · paso 2).
 *
 * Se separa de {@see AdmissionDecision} porque responde a una pregunta distinta: no solo «¿puede
 * este cliente?», también «¿sobre QUÉ pedido, y sigue viva su retención de aforo?». Por eso trae la
 * `Order` cuando admite — con su ventana de hold ya extendida en la misma sentencia atómica que la
 * comprobó (`PAY-04`). Devolver el pedido evita que el llamante lo vuelva a buscar y, sobre todo,
 * que lo busque con un filtro distinto del que autorizó el reintento.
 */
final readonly class RetryAdmission
{
    /** El panel pausó las reservas online (#218): tampoco se reabre un cobro. */
    public const RESERVATIONS_PAUSED = 'reservations_paused';

    /** Demasiados intentos en el último minuto. */
    public const RATE_LIMITED = 'rate_limited';

    /**
     * No hay pedido reintentable con ese código para este titular: no existe, no es suyo, ya no
     * está `pending`, o su retención de aforo venció. **Las cuatro dan la misma respuesta**: si el
     * hold cruzó, la plaza pudo cederse a otro cliente y reabrir el cobro llevaría a sobreventa.
     */
    public const NOT_RETRYABLE = 'not_retryable';

    private function __construct(
        public bool $allowed,
        public ?string $reason = null,
        /** El pedido a cobrar, con el hold ya extendido. Solo cuando `allowed`. */
        public ?Order $order = null,
    ) {}

    public static function allow(Order $order): self
    {
        return new self(true, null, $order);
    }

    /** @param  self::*  $reason */
    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }
}
