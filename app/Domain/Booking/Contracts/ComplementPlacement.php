<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un complemento OFRECIDO EN UNA ZONA: el par (complemento, zona) que la landing
 * necesita evaluar para decidir si anuncia precio + CTA o degrada la tarjeta a
 * informativa (coherencia #226).
 *
 * Par de IDs y nada más: es la unidad mínima con la que preguntan los dos llamantes
 * reales — `Attraction::complementIsPurchasable()` (una) y `LandingComplementResolver`
 * (todas las de la página, en lote).
 */
final readonly class ComplementPlacement
{
    public function __construct(
        /** ID del complemento (`TicketType` de tipo addon). */
        public int $complementId,
        /** ID de la zona en la que se ofrece. */
        public int $zoneId,
    ) {}
}
