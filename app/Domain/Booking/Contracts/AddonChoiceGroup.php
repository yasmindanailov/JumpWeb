<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un grupo EXCLUYENTE de complementos: «elige uno» ({@see AddonOffer}).
 *
 * Va aparte de los sueltos porque su regla no se puede deducir mirando las filas: dentro de un grupo
 * **exactamente uno** está seleccionado, y elegir otro deselecciona al anterior. Un cliente que
 * recibiera todos los complementos en una lista plana tendría que reconstruir esa agrupación por su
 * cuenta —y con ella la regla—, que es lo que este contrato existe para evitar.
 */
final readonly class AddonChoiceGroup
{
    /**
     * @param  string  $key  identificador del grupo, tal como lo configuró la instalación. Es lo que
     *                       el cliente devuelve al elegir un miembro
     * @param  string  $label  título ya traducido del grupo («Elige uno»)
     * @param  list<ResolvedAddon>  $options  los miembros, en el orden configurado
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $options,
    ) {}
}
