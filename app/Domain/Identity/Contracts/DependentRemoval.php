<?php

namespace App\Domain\Identity\Contracts;

/**
 * Fase 6 · menores a cargo — lo que pasó al «quitar» (`docs/specs/menores-a-cargo.md` §4.4). Para el
 * titular las dos salidas se ven igual —la persona desaparece de su lista—; se distinguen para la
 * auditoría y para los tests, que tienen que poder aseverar que una fila con firma NO se borró.
 */
enum DependentRemoval: string
{
    /** Sin waiver ni referencias: la fila se borró de verdad. */
    case Deleted = 'deleted';

    /** Con un waiver firmado (o una reserva) detrás: `removed_at`; el registro sigue apuntando ahí. */
    case Unlinked = 'unlinked';
}
