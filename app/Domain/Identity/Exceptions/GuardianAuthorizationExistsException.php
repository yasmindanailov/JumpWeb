<?php

namespace App\Domain\Identity\Exceptions;

use App\Domain\Identity\Models\GuardianAuthorization;
use RuntimeException;

/**
 * Fase 6 · justificante de un menor invitado — ese menor **ya tiene su justificante** en este pedido,
 * firmado por otro adulto (`[DECIDIDO owner, 2026-09-01]`, `docs/specs/waiver-por-reserva.md` §7·9:
 * «un niño, un papel»).
 *
 * Es el caso de los dos progenitores separados, cada uno con el enlace. **No es un error del que
 * firma**: la pantalla se lo dice con el nombre del menor, y admitir el segundo haría que la puerta y
 * la hoja de sala enseñaran al mismo niño dos veces.
 *
 * ⚠️ Lleva el nombre del menor porque la pantalla lo necesita para ser útil («Ana ya tiene su
 * justificante»), y **nada más**: quien firma no puede enterarse por aquí de quién lo firmó ni de
 * cómo contactarle.
 */
class GuardianAuthorizationExistsException extends RuntimeException
{
    public function __construct(public readonly string $minorName)
    {
        parent::__construct("El menor «{$minorName}» ya tiene un justificante firmado en esta reserva.");
    }

    public static function for(GuardianAuthorization $authorization): self
    {
        return new self($authorization->minorFullName());
    }
}
