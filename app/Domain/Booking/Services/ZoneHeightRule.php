<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Concerns\WritesLandingValues;

/**
 * **La regla de altura de una zona, ya redactada** (F5, `#676`).
 *
 * ⚠️ **La misma cifra significa lo contrario según la columna**: 130 en `height_max_cm` es
 * «hasta» y en `height_min_cm` es «a partir de». Por eso son dos columnas y no un número con el
 * sentido deducido de qué zona sea, que es el tipo de regla implícita que nadie encuentra luego.
 *
 * ⚠️⚠️ **Existe porque ya estaba escrita en DOS sitios y la API iba a ser el tercero.** Vivía
 * completa en `ZoneCards::heightRule()` —de donde se movió TAL CUAL— y su semántica está además en
 * `Content\Services\RuleBoard::heightScale()`, que produce otra cosa (la escala de `/normas`, no
 * una frase). *Cuando algo está en dos sitios la salida no es retirarlo de uno, es que haya UNA
 * definición*, y es lo mismo que `#661` hizo con el registro de escaparate de `Money`.
 * ▶ **Lo que queda declarado y NO se hizo aquí**: unificar también `RuleBoard`. Produce un artefacto
 * distinto y arrastraría `/normas` a esta tanda; su semántica sigue duplicada en su docblock.
 *
 * ⚠️ **Por qué el producto la escribe en vez de publicar solo las cifras.** Los metros salen con el
 * separador decimal del IDIOMA (`WritesLandingValues::coma()`), que es exactamente la regla que
 * `#660` cazó mal escrita en una landing —«from 14.95 €» junto a «12,00 €»—. Una landing que reciba
 * `130` y lo escriba sola vuelve a ese defecto. Las cifras viajan igual, para quien quiera pintar
 * otra cosa: la frase es un atajo fiable, no una obligación.
 */
final class ZoneHeightRule
{
    use WritesLandingValues;

    /**
     * @param  ?int  $minCm  «a partir de» — la zona va de esta cifra hacia arriba
     * @param  ?int  $maxCm  «hasta» — la zona va del suelo a esta cifra
     */
    public function written(?int $minCm, ?int $maxCm): ?string
    {
        if ($minCm === null && $maxCm === null) {
            return null;
        }

        if ($minCm !== null && $maxCm !== null) {
            return __('landing.zones.height_between', ['a' => $this->metros($minCm), 'b' => $this->metros($maxCm)]);
        }

        return $minCm !== null
            ? __('landing.zones.height_from', ['h' => $this->metros($minCm)])
            : __('landing.zones.height_up_to', ['h' => $this->metros($maxCm)]);
    }
}
