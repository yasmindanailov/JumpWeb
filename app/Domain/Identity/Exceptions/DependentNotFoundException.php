<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Fase 6 · menores a cargo — la persona a cargo no existe PARA ESTE TITULAR: ajena, inexistente o
 * ya retirada dan la misma respuesta a propósito (`docs/specs/menores-a-cargo.md` §4.9, anti-IDOR:
 * distinguirlas convertiría el endpoint en una forma de enumerar los dependientes de otras cuentas).
 */
class DependentNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No hay ninguna persona a cargo con ese identificador en esta cuenta.');
    }
}
