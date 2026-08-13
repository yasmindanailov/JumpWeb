<?php

namespace App\Domain\Booking\Contracts;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Contracts\PaymentTicket;

/**
 * Resultado de {@see ReservationCheckout::retry()}: o la política dijo que no, o hay cobro nuevo
 * abierto sobre el pedido de siempre.
 *
 * Se separa de {@see CheckoutOutcome} porque el veredicto que transporta es otro
 * ({@see RetryAdmission}, que además distingue `NOT_RETRYABLE`) y porque las superficies ramifican
 * sobre él de forma distinta: quien pulsa dos veces seguidas debe leer «espera un minuto, tu reserva
 * sigue viva», no «has perdido la plaza».
 *
 * El pedido que devuelve es el que autorizó la política, con su hold **ya extendido** en la misma
 * sentencia atómica que lo comprobó (`PAY-04`): no hay que volver a buscarlo, y sobre todo no hay
 * que buscarlo con un filtro distinto del que autorizó el reintento.
 */
final readonly class RetryOutcome
{
    private function __construct(
        public bool $allowed,
        /** El veredicto que lo impidió. Solo cuando NO `allowed`. */
        public ?RetryAdmission $denial = null,
        /** El pedido que se va a cobrar, con el hold ya extendido. Solo cuando `allowed`. */
        public ?Order $order = null,
        /** El cobro abierto y su formulario firmado. Solo cuando `allowed`. */
        public ?PaymentTicket $ticket = null,
    ) {}

    public static function allow(Order $order, PaymentTicket $ticket): self
    {
        return new self(true, null, $order, $ticket);
    }

    public static function deny(RetryAdmission $denial): self
    {
        return new self(false, $denial);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }
}
