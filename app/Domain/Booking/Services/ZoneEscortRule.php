<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Concerns\WritesLandingValues;

/**
 * **Las reglas de «con un adulto» de una zona, ya redactadas** (T4a·1, `DECISIONES #699` y `#761`).
 *
 * La hermana de {@see ZoneHeightRule}, por la misma razón: los metros se escriben con el separador decimal del
 * IDIOMA (`#660`), y una landing que reciba `90` y lo escriba sola vuelve a ese defecto. Las cifras viajan igual.
 *
 * ⚠️ **Dos reglas y no una con un valor opcional**, porque dicen cosas distintas:
 *  - por debajo de la EDAD de la zona, se entra con un adulto DESDE una altura (Kids: los menores de 4, desde
 *    0,90 m);
 *  - por debajo de una ALTURA, se entra con un adulto (Jump: con menos de 1,30 m). No es la altura mínima de la
 *    zona: aquélla deja fuera a quien no llega; ésta lo deja entrar acompañado.
 *
 * ⚠️ La primera no nombra la edad («los menores de 4»): la zona no la guarda en números —es del PRODUCTO,
 * `guest_age_min`, `#676`— y deducirla de sus productos sería una regla implícita. Quien quiera la cifra la tiene
 * en el catálogo.
 */
final class ZoneEscortRule
{
    use WritesLandingValues;

    /**
     * @param  ?int  $underAgeFromCm  por debajo de la edad, con un adulto desde esta altura
     * @param  ?int  $belowCm  por debajo de esta altura, con un adulto
     */
    public function written(?int $underAgeFromCm, ?int $belowCm): ?string
    {
        $partes = array_filter([
            $underAgeFromCm !== null ? __('landing.zones.escort_under_age_from', ['h' => $this->metros($underAgeFromCm)]) : null,
            $belowCm !== null ? __('landing.zones.escort_below', ['h' => $this->metros($belowCm)]) : null,
        ]);

        return $partes === [] ? null : implode('; ', $partes);
    }
}
