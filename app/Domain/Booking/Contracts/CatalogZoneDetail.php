<?php

namespace App\Domain\Booking\Contracts;

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
    ) {}
}
