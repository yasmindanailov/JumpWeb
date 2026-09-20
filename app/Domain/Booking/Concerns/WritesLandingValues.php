<?php

namespace App\Domain\Booking\Concerns;

use App\Domain\Platform\Services\Money;

/**
 * **CÓMO SE ESCRIBEN UN IMPORTE Y UNA ESTATURA EN LA LANDING.**
 *
 * ▶ **Nace en `#479` extrayendo lo que `#478` había escrito en privado dentro de `ZoneCards`.** No
 * es refactor por gusto: la sección «Cuánto» escribe los MISMOS dos valores que la sección «Para
 * quién» —el precio de una entrada y el umbral de altura de una zona— y a un palmo de distancia en
 * la misma página. Con dos copias, el día que alguien cambie el separador decimal de una, la
 * portada dirá «1,30 m» arriba y «1.30 m» abajo **sin que nada falle**.
 *
 * ⚠️⚠️ **El importe lo escribe `Money::showcase()`, no este fichero.** Aquí solo se le pone el
 * símbolo al lado. La distinción entre importe de TRANSACCIÓN (`Money::amount()`, dos decimales
 * siempre) y de ESCAPARATE (`showcase()`, sin ceros a la derecha) vive junto al resto del formateo
 * de dinero, que es donde se puede encontrar. *El mismo número no se escribe igual en un precio
 * anunciado que en uno cobrado, pero las dos formas viven en la misma clase.*
 *
 * ⚠️ **El separador decimal es del IDIOMA, no del dato**: «1,30 m» en español y francés, «1.30 m»
 * en inglés. Dejarlo al `number_format` por defecto lo deja mal en dos de los tres idiomas.
 */
trait WritesLandingValues
{
    /**
     * Céntimos → importe escrito, **sin decimales cuando son cero**.
     *
     * ⚠️ «10 €» y no «10,00 €»: es una cifra de escaparate. Cuando hay céntimos —14,95— se
     * escriben, porque ahí sí dicen algo.
     *
     * ⚠️⚠️ **Ya no compone nada: delega en `Money::showcaseWithSymbol()`** (`#661`). Aquí estaba
     * escrito `.' €'` con espacio NORMAL, y a un palmo —en las tres vistas de la landing— el mismo
     * precio se escribía con espacio duro. Con el símbolo puesto aquí, este trait decidía una regla
     * de escritura de dinero que no es suya: la suya es CUÁNDO se escribe un importe de escaparate.
     */
    private function euros(int $cents): string
    {
        return Money::showcaseWithSymbol($cents);
    }

    /**
     * El mismo importe **sin el símbolo**, para cuando el marcado lo coloca aparte.
     *
     * ⚠️ Existe porque la tarjeta de tarifa pinta la cifra y el «€» en dos tamaños distintos —44 y
     * 22 en el artboard—, y eso no se puede hacer con una sola cadena. *No es una segunda forma de
     * escribir un precio: es la misma, partida donde el diseño la parte.*
     */
    private function numero(int $cents): string
    {
        return Money::showcase($cents);
    }

    /**
     * **El precio ANTES de una rebaja del `$pct` %**, en céntimos, o `null` sin rebaja.
     *
     * ⚠️⚠️ **Es una CHAPUZA declarada** (`[DECIDIDO owner, 2026-09-18]`: *«no quiero spec, ni sistema
     * ni nada; más adelante haremos un sistema de ofertas»*). El catálogo ya guarda el precio
     * REBAJADO —la promo del 17-09 se aplicó como dato, `ENTORNOS.md` §6— y el «antes» se deshace
     * de esa rebaja: `nuevo × 100 / (100 − pct)`. Con las nueve filas de PlayJump sale exacto.
     * ⚠️ El porcentaje NO vive aquí: es el ajuste `promo.percent` de la instalación, que los
     * controladores leen y pasan. Sin ajuste (0), no hay «antes» y la tarjeta es la de siempre.
     * ⚠️ **No es un precio que se cobre** (`#329`): es presentación, y nunca entra en un cálculo.
     */
    private function antes(?int $cents, int $pct): ?int
    {
        if ($cents === null || $pct <= 0 || $pct >= 100) {
            return null;
        }

        return (int) round($cents * 100 / (100 - $pct));
    }

    /**
     * Centímetros enteros → metros escritos en el idioma que toca.
     *
     * ⚠️ Siempre con dos decimales, también cuando son cero: una estatura es una medida, y «1,30 m»
     * y «1,3 m» no se leen igual en un cartel. Es justo lo contrario que el importe, y por eso son
     * dos métodos y no uno con una bandera.
     */
    private function metros(int $cm): string
    {
        return number_format($cm / 100, 2, $this->coma(), '');
    }

    /**
     * Minutos → duración escrita para el ESCAPARATE: «2 h», «45 min», «1 h 30 min».
     *
     * ⚠️⚠️ **No se usa `Duration::formatHumane()`, y no es un olvido**: aquél escribe con el
     * diccionario del PANEL (`admin.orders.item_detail.duration.*`), y una página pública que lea
     * los textos del operador es una fuga de dominio que además nadie ve —las tres cadenas se leen
     * bien en los tres idiomas—.
     *
     * ⚠️ **`HeroStatus` conserva su propia copia de esta regla** y no puede usar ésta: vive en
     * `Content`, que solo puede mirar a `Booking\Contracts`, y este trait está en `Booking\Concerns`.
     * Ficha en `DEUDA.md`: son dos escrituras de la misma regla a un palmo en la misma página.
     */
    private function duracion(int $minutos): ?string
    {
        if ($minutos <= 0) {
            return null;
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return match (true) {
            $horas === 0 => $resto.' min',
            $resto === 0 => $horas.' h',
            default => $horas.' h '.$resto.' min',
        };
    }

    /** El separador decimal del idioma. */
    private function coma(): string
    {
        return app()->getLocale() === 'en' ? '.' : ',';
    }
}
