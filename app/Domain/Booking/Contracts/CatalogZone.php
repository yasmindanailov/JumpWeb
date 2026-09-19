<?php

namespace App\Domain\Booking\Contracts;

/**
 * Una ZONA operativa vista desde fuera de Booking (Fase 3 · paso 1b).
 *
 * Solo su IDENTIDAD: id, slug y nombre traducido. Es lo que viaja anidado dentro de cada producto
 * del catálogo, y por eso se queda flaco: ahí cada campo se paga en todas las filas.
 *
 * ▶ **Resuelto en F5 (19-09) lo que este docblock dejaba abierto.** Decía que la imagen y la
 * descripción «pertenecen a Content (Fase 5)» y que meterlas aquí ataría el catálogo de compra a
 * decisiones de presentación. La premisa cambió con `DECISIONES #632`: la app nativa de F6 **no
 * tiene landing y vende con lo que dé la API**, así que la foto y la descripción de una zona ya no
 * son material de una landing concreta — son la ficha, y son de quien venda. Lo que sigue en pie es
 * la conclusión sobre ESTE objeto: la ficha vive en {@see CatalogZoneDetail}, que lo compone, y
 * `GET /catalog/zones` la sirve; el color de acento, la superficie, el número de atracciones y
 * `show_in_landing` siguen siendo presentación y no salen.
 *
 * El `slug` viaja porque es la clave estable del deep-link: la landing enlaza «Comprar» a una zona
 * concreta y el cliente necesita reconocerla en la lista de productos sin depender de ids internos.
 */
final readonly class CatalogZone
{
    public function __construct(
        public int $id,
        /** Clave estable de la zona (la usa el deep-link de la landing). */
        public string $slug,
        /** Nombre en el idioma activo. */
        public string $name,
    ) {}
}
