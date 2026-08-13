<?php

namespace App\Domain\Booking\Contracts;

/**
 * Una hora de entrada ofrecible, con el cupo que le queda ({@see AvailabilityOffer}).
 *
 * ⚠️ **`available` y `maxQuantity` NO son el mismo número, y confundirlos vende de más.** En una
 * ENTRADA coinciden. En un PACK no: `available` son las plazas de invitados que realmente le quedan
 * a la franja, y `maxQuantity` es cuántos invitados puede tener ESTA fiesta, que además está topado
 * por el `max_qty` del propio pack. Con un cupo de 60 y un pack de máximo 20, una franja vacía
 * ofrece `available = 60` y `maxQuantity = 20`: un selector de cantidad construido sobre el primero
 * dejaría pedir 60 invitados que el checkout rechazaría.
 *
 * La regla, dicha de una vez: **`available` es para contar, `maxQuantity` es para elegir.**
 */
final readonly class OfferedTime
{
    public function __construct(
        /** Hora de inicio de la franja en formato canónico de BD (`H:i:s`). */
        public string $time,
        /** Plazas —o invitados— que le quedan a la franja. Para MOSTRAR («quedan N»). */
        public int $available,
        /** Máximo que se puede contratar en esta franja. Para ACOTAR el selector de cantidad. */
        public int $maxQuantity,
        /**
         * ¿Queda sitio? Una entrada llena se ofrece igualmente con `sellable = false` —para que el
         * cliente vea que esa hora existe y está completa, en vez de que desaparezca sin
         * explicación—. Un pack por debajo de su mínimo de invitados NO llega a ofrecerse: no es
         * reservable de ninguna forma, así que listarlo solo confundiría.
         */
        public bool $sellable,
    ) {}
}
