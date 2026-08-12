<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un post-form de invitados (#217) PENDIENTE de rellenar, visto desde fuera de Booking.
 *
 * Viaja el ID de la reserva (el `OrderItem` principal), no una URL: construir rutas es
 * trabajo de la capa que las muestra, no del dominio. `route('reservation.guests', $id)`
 * produce exactamente la misma URL que antes — la ruta liga `{reservation}` por clave
 * primaria (`routes/web.php`).
 */
final readonly class PendingGuestForm
{
    public function __construct(
        /** ID de la reserva (OrderItem principal) que pide el formulario. */
        public int $reservationId,
        /** Nombre del producto en el idioma activo (cadena vacía si el producto ya no existe). */
        public string $productName,
    ) {}
}
