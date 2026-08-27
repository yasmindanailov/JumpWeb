<?php

namespace App\Domain\Identity\Exceptions;

use App\Domain\Identity\Models\Dependent;
use LogicException;

/**
 * Fase 6 · menores a cargo — se intentó BORRAR una persona a cargo que tiene un waiver firmado (o
 * una reserva) detrás (`docs/specs/menores-a-cargo.md` §4.4). No es un error del cliente: es un
 * error de programación, porque la salida correcta —desvincular— existe (`Dependent::unlink()`).
 */
class DependentHasReferencesException extends LogicException
{
    public static function for(Dependent $dependent): self
    {
        return new self(sprintf(
            'La persona a cargo #%d tiene un waiver firmado o reservas detrás: no se borra, se desvincula (removed_at).',
            $dependent->getKey(),
        ));
    }
}
