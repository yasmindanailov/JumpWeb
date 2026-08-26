<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Fase 6 · waiver — el identificador de versión que trae la aceptación no es el del texto VIGENTE:
 * no existe, no es del waiver, o el texto se publicó de nuevo entre servirlo y aceptarlo
 * (`specs/waiver-probatorio.md` §4.4: «el servidor solo emite la aceptación si la petición trae el
 * identificador de la versión que él sirvió»). El cliente tiene que volver a pedir el texto.
 */
class WaiverDocumentStaleException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El texto del waiver ha cambiado desde que se sirvió: hay que volver a leerlo y aceptarlo.');
    }
}
