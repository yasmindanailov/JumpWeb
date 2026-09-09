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
     */
    private function euros(int $cents): string
    {
        return $this->numero($cents).' €';
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

    /** El separador decimal del idioma. */
    private function coma(): string
    {
        return app()->getLocale() === 'en' ? '.' : ',';
    }
}
