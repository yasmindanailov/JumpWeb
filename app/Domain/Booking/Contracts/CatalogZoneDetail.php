<?php

namespace App\Domain\Booking\Contracts;

use App\Domain\Booking\Services\ZoneHeightRule;

/**
 * La FICHA de una zona: su identidad más lo que se cuenta de ella (T6 del menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1; `DECISIONES #632` P1).
 *
 * **Compone en vez de heredar**, igual que {@see CatalogProductDetail} con {@see CatalogProduct}:
 * una ficha *tiene* una identidad, así que los tres campos que identifican a una zona se definen
 * UNA vez y la ficha no puede divergir de la zona anidada en un producto.
 *
 * ⚠️⚠️ **Por qué existe este DTO y no se engordó `CatalogZone`.** La zona identidad viaja anidada
 * en cada producto del catálogo, y ése es el payload de la primera pantalla del flujo de compra.
 * Medido el 19-09: `/catalog/products` son 4.079 bytes con 24 productos, 9 de ellos con zona; meter
 * la ficha en la zona anidada repetiría las descripciones de 4 zonas a lo largo de 9 productos y
 * subiría ese payload alrededor de un 47 % — para un dato que el cajón no pinta. La misma regla que
 * `CatalogProductDetail` escribió para `guardianAuthorization`: cada campo de la lista se paga en
 * todas las filas.
 *
 * Quien necesite la ficha de las zonas las pide de una vez a `GET /catalog/zones` (cuatro filas) y
 * las une por `id`. Es una petición, no una por producto.
 */
final readonly class CatalogZoneDetail
{
    public function __construct(
        public CatalogZone $zone,
        /**
         * Qué es esta zona, en el idioma activo, o `null` si la instalación no lo escribió.
         *
         * `null` y no `''`: «no lo han rellenado» y «lo han dejado en blanco» son lo mismo para
         * quien pinta, y publicar la cadena vacía obliga a cada cliente a tratar los dos casos.
         */
        public ?string $description,
        /**
         * URL ABSOLUTA de la foto de la zona, o `null` si no tiene.
         *
         * ⚠️ Absoluta y no la ruta guardada: quien consume esto puede ser una landing en otro
         * dominio o la app nativa (`#632`), y ninguna de las dos puede resolver
         * `images/attractions/park_jump.webp`. La resuelve `Zone::imageUrl()`, que además es el
         * único sitio que sabe que la zona guarda una ruta de `public/` y el producto una del disco
         * de subidas.
         */
        public ?string $imageUrl,
        /**
         * «A partir de» en centímetros, o `null`. **La zona va de esta cifra HACIA ARRIBA.**
         *
         * ⚠️⚠️ Se llama `from` y no `min` a propósito (`#676`): la misma cifra significa lo
         * contrario según la columna —130 aquí es «a partir de 1,30 m» y 130 en la de abajo es
         * «hasta 1,30 m»— y con `min`/`max` el sentido queda fuera del nombre, que es el tipo de
         * regla implícita que nadie encuentra después. En el nombre, nadie puede confundirla.
         */
        public ?int $heightFromCm,
        /** «Hasta» en centímetros, o `null`. **La zona va del suelo a esta cifra.** */
        public ?int $heightUpToCm,
        /**
         * La regla de altura YA REDACTADA en el idioma activo, o `null` si la zona no tiene ninguna.
         *
         * ⚠️ Viaja además de las cifras, y no en su lugar: los metros se escriben con el separador
         * decimal del IDIOMA, que es justo lo que `#660` cazó mal escrito en una landing. Quien
         * quiera pintar otra cosa tiene las cifras; quien solo quiera la frase, la tiene hecha.
         * La escribe {@see ZoneHeightRule}, la misma que usa la web.
         */
        public ?string $heightWritten,
        /**
         * El rango de edad tal y como lo escribe el panel («+8 años», «4 — 8 años»), o `null`.
         *
         * ⚠️ Es TEXTO y no dos números, y así se queda: lo que el checkout aplica de verdad son los
         * campos de edad del PRODUCTO, no este rótulo. Publicarlo como cifras invitaría a calcular
         * con él.
         */
        public ?string $ageRange,
        /**
         * **Por debajo de la edad de la zona, con un adulto DESDE esta altura**, en centímetros, o `null` si la zona
         * no tiene esa excepción (T4a·1, `#699`, `#761`; Kids: 90).
         */
        public ?int $escortUnderAgeFromCm = null,
        /**
         * **Por debajo de esta altura, con un adulto**, en centímetros, o `null` (Jump: 130). ⚠️ No es
         * `heightFromCm`: aquélla deja FUERA a quien no llega; ésta lo deja entrar acompañado.
         */
        public ?int $escortBelowCm = null,
        /** Las dos reglas ya redactadas en el idioma activo, o `null` si no hay ninguna ({@see ZoneEscortRule}). */
        public ?string $escortWritten = null,
    ) {}
}
