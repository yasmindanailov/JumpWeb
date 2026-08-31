<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * T5 · D8 (`cumple-mixto.md` §25.4, `#284`) — se intentó ejercer la supresión (art. 17) con una
 * reserva POR CELEBRAR: pagada, no cancelada y con franja cuyo fin no ha pasado. La cuenta no se
 * puede borrar hasta que pase o se cancele, y hay que explicárselo al titular — no es un error que
 * se corrija reintentando.
 *
 * ⚠️ La puerta vive en `AccountPrivacy::anonymize()` (las vías del cliente) y en la acción del
 * panel — NUNCA en `User::anonymize()`: metería Booking en un modelo de Identity y arrastraría el
 * censo y la idempotencia de `AnonymizeCoversEveryUserColumnTest`.
 */
class AccountHasUpcomingReservationsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La cuenta tiene reservas por celebrar: la supresión espera a que pasen o se cancelen.');
    }
}
