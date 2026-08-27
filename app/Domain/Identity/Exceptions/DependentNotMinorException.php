<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Fase 6 · menores a cargo — la fecha de nacimiento no es la de un MENOR: hoy, en el reloj del
 * parque, ya tiene 18 o más (`docs/specs/menores-a-cargo.md` §4.1). Un adulto firma su propio
 * waiver; declararlo «a cargo» produciría una firma que no cubre a nadie.
 */
class DependentNotMinorException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La persona a cargo tiene que ser menor de edad.');
    }
}
