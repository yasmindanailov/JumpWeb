<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Fase 6 · menores a cargo — la cuenta ya tiene el máximo de personas a cargo
 * (`dependents.max_per_account`, `docs/specs/menores-a-cargo.md` §4.5). Lleva el tope para que la
 * capa de entrega lo publique en `params.max`, como `too_many_pending_orders`.
 */
class DependentsLimitReachedException extends RuntimeException
{
    public function __construct(public readonly int $max)
    {
        parent::__construct("La cuenta ya tiene el máximo de personas a cargo ({$max}).");
    }
}
