<?php

namespace App\Domain\Booking\Contracts;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Contracts\PaymentTicket;

/**
 * Resultado de {@see ReservationCheckout::start()}: o la política dijo que no, o hay reserva creada
 * y cobro abierto.
 *
 * **Devuelve un resultado; no lanza ni pinta** — mismo criterio que {@see AdmissionDecision}, del
 * que transporta el veredicto entero (y no solo su `reason`) porque las superficies necesitan
 * también su `context`: el aviso de «demasiados pedidos pendientes» dice cuántos son.
 *
 * Lo que sí sale por excepción es lo que NO es una decisión sobre el cliente: una cesta no vendible
 * (`ReservationException`, con sus doce códigos de negocio) y una pasarela que no abre
 * (`PaymentInitiationException`, ya compensada). Ver `ReservationCheckout`.
 */
final readonly class CheckoutOutcome
{
    private function __construct(
        public bool $allowed,
        /** El veredicto que lo impidió. Solo cuando NO `allowed`. */
        public ?AdmissionDecision $denial = null,
        /** La reserva creada, ya reteniendo aforo. Solo cuando `allowed`. */
        public ?Order $order = null,
        /** El cobro abierto y su formulario firmado. Solo cuando `allowed`. */
        public ?PaymentTicket $ticket = null,
    ) {}

    public static function allow(Order $order, PaymentTicket $ticket): self
    {
        return new self(true, null, $order, $ticket);
    }

    public static function deny(AdmissionDecision $denial): self
    {
        return new self(false, $denial);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }
}
