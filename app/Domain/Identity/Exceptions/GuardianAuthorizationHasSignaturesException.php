<?php

namespace App\Domain\Identity\Exceptions;

use App\Domain\Identity\Models\GuardianAuthorization;
use LogicException;

/**
 * Fase 6 · justificante de un menor invitado — se intentó BORRAR una autorización que tiene una firma
 * detrás (`docs/specs/waiver-por-reserva.md` §4.2). No es un error del cliente: es un error de
 * programación, porque una autorización **no tiene salida manual** — solo la retira la poda por plazo
 * cuando su última firma ya se ha ido.
 *
 * La FK `waiver_signatures.subject_authorization_id` es RESTRICT y lo impide igualmente en la base de
 * datos; esto es lo que da un mensaje legible y cubre a quien borre por modelo.
 */
class GuardianAuthorizationHasSignaturesException extends LogicException
{
    public static function for(GuardianAuthorization $authorization): self
    {
        return new self(sprintf(
            'La autorización #%d tiene una firma del waiver detrás: no se borra (solo la retira la poda por plazo).',
            $authorization->getKey(),
        ));
    }
}
