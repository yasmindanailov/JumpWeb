<?php

namespace App\Domain\Booking\Contracts;

/**
 * Los complementos de una línea, resueltos contra lo que el cliente lleva elegido
 * ({@see AddonOffer}).
 *
 * Lleva **dos vistas de lo mismo, y las dos hacen falta**: `groups`/`singles` es lo que se pinta, y
 * `selection` es lo que se guarda en la cesta. Traducir la primera en la segunda parece trivial y no
 * lo es —hay que saber qué miembro de cada grupo cuenta, inyectar los obligatorios que el cliente no
 * marcó y podar los dependientes huérfanos—, así que publicarla evita que cada cliente escriba su
 * propia versión de la regla.
 */
final readonly class ResolvedAddons
{
    /**
     * @param  list<AddonChoiceGroup>  $groups  grupos excluyentes, en el orden configurado
     * @param  list<ResolvedAddon>  $singles  complementos sueltos
     * @param  int  $totalCents  lo que suman los seleccionados
     * @param  list<array{ticket_type_id:int, qty:int}>  $selection  la selección resuelta en la forma
     *                                                               canónica de una línea de cesta. **Es la que hay que guardar**: ya lleva los obligatorios
     *                                                               inyectados y los dependientes huérfanos fuera, así que una cesta construida con ella es
     *                                                               la que el checkout va a aceptar
     */
    public function __construct(
        public array $groups,
        public array $singles,
        public int $totalCents,
        public array $selection,
    ) {}
}
