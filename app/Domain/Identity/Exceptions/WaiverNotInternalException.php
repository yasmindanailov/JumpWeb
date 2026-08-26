<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Fase 6 · waiver — se intentó aceptar el waiver en una instalación que no lo gestiona aquí
 * (`waiver.mode` ≠ `interno`, `specs/waiver-probatorio.md` §4.1). No es un error del cliente que se
 * corrija reintentando: es un dato de la instalación que `GET /legal/waiver` ya publica.
 */
class WaiverNotInternalException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El waiver no se firma en esta instalación (waiver.mode ≠ interno).');
    }
}
