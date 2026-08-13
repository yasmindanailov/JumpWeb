<?php

namespace App\Domain\Booking\Contracts;

/**
 * Una ZONA operativa vista desde fuera de Booking (Fase 3 · paso 1b).
 *
 * Solo su identidad: id, slug y nombre traducido. Lo que la zona lleva ADEMÁS —imagen, color de
 * acento, superficie, número de atracciones, `show_in_landing`— es material de la landing y
 * pertenece a Content (Fase 5, «contenido consumible también vía API»); meterlo aquí ataría el
 * catálogo de compra a decisiones de presentación que no son suyas.
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
