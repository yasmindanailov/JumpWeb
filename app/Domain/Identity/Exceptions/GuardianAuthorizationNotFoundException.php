<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Fase 6 · justificante de un menor invitado — la autorización que se quiere firmar no existe
 * (`docs/specs/waiver-por-reserva.md` §4.9).
 *
 * En el flujo normal **no puede ocurrir**: `GuardianAuthorizationSigner` crea o encuentra la fila y
 * firma en la MISMA transacción, bajo el mismo lock. Existe porque `WaiverSigner` recibe un
 * identificador de fuera y una prueba no se escribe sobre un sujeto que no se ha visto: sin esta
 * comprobación, la FK dejaría un error de base de datos en vez de un fallo con nombre.
 */
class GuardianAuthorizationNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No existe ninguna autorización con ese identificador.');
    }
}
