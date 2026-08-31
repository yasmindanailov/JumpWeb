<?php

namespace App\Domain\Booking\Exceptions;

use App\Domain\Booking\Models\TicketType;
use RuntimeException;

/**
 * T6 de mixtos (`cumple-mixto.md` §26, el hueco G de `#284`): el tramo de edad de un pack pisa al
 * de otro producto de la MISMA familia. Hasta la T6 esto solo lo impedía el formulario del panel
 * («por construcción es imposible» era verdad solo ahí); ahora lo impide el dominio en `saving`,
 * para toda escritura Eloquent — semillas, comandos, tinker y superficies futuras incluidas.
 *
 * Lleva el HERMANO pisado dentro: quien capture la excepción puede nombrarle (el form del catálogo
 * compone con él su aviso de siempre).
 */
class OverlappingAgeRangeException extends RuntimeException
{
    public function __construct(public readonly TicketType $sibling)
    {
        parent::__construct(sprintf(
            'El tramo de edad pisa al del producto #%d («%s») de la misma familia.',
            $sibling->getKey(),
            $sibling->tr('name'),
        ));
    }
}
