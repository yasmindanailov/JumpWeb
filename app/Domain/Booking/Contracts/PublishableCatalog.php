<?php

namespace App\Domain\Booking\Contracts;

/**
 * Qué del catálogo es PUBLICABLE, para quien vive fuera de Booking (Fase 2, paso 1:
 * `docs/specs/modulos-dominio.md` §5.1 — «catálogo publicable para Content»).
 *
 * La landing (Content) solo puede anunciar precio + CTA de lo que la cesta puede COBRAR
 * de verdad (coherencia #226). Esa regla es de Booking: mira el pivote `product_addons`,
 * el tipo/estado del producto, la existencia de precio y el estado de la zona. Antes vivía
 * DUPLICADA en dos sitios de Content —`Attraction::complementIsPurchasable()` (unitaria) y
 * `App\Support\LandingComplementResolver` (en lote, sin N+1)— con dos consultas distintas
 * que podían divergir en silencio. El contrato las unifica: una sola regla, dos formas de
 * preguntarla.
 *
 * El PRECIO no entra aquí: se lee por la relación Eloquent
 * (`$attraction->ticketType?->displayPriceCents()`), la costura de BD que el spec deja
 * exenta (§4).
 *
 * Implementación actual: `App\Support\PublishableCatalogReader` (bind en
 * `BookingServiceProvider`; viaja a `App\Domain\Booking` en el paso 6).
 */
interface PublishableCatalog
{
    /**
     * ¿Se puede COMPRAR este complemento en esta zona? Consulta unitaria (panel/aviso).
     */
    public function isComplementPurchasable(ComplementPlacement $placement): bool;

    /**
     * Igual que `isComplementPurchasable` pero en LOTE, con UNA sola consulta: la landing
     * evalúa todas las atracciones de la página de golpe y no puede pagar un N+1.
     *
     * @param  list<ComplementPlacement>  $placements
     * @return list<ComplementPlacement> el subconjunto comprable (orden no garantizado)
     */
    public function purchasableComplements(array $placements): array;
}
