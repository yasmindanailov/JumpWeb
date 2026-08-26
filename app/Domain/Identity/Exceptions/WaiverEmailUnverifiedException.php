<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Fase 6 · waiver — `[DECIDIDO owner, 2026-08-26]` (`specs/waiver-probatorio.md` §7·5): la firma del
 * titular exige el correo VERIFICADO. Hasta `#179` se firmaba con `email_verified_at = null` —el alta
 * firmaba en la misma transacción que creaba la cuenta— y la revisión (`#169` §10.2·3) lo señaló:
 * cualquiera podía aceptar «en nombre» del correo de un tercero. Solo la firma DECLARADA por un
 * operador (mostrador, §8.4) queda fuera: ahí la identidad la asegura el operador, no el buzón.
 */
class WaiverEmailUnverifiedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El waiver solo se firma con el correo verificado.');
    }
}
