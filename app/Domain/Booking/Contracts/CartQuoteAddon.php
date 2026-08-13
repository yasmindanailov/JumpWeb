<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un complemento resuelto DENTRO de una línea de la cesta ({@see CartQuoteLine}).
 *
 * No es lo que el cliente pidió, sino lo que `AddonResolver` decidió que se cobra: con los
 * obligatorios inyectados aunque no se pidieran, el default de cada grupo excluyente, la cantidad
 * efectiva de los per-invitado y las unidades incluidas ya descontadas. Esa resolución es la MISMA
 * que aplica `OrderCreator` al crear el pedido, así que lo que se muestra aquí es lo que se cobrará.
 */
final readonly class CartQuoteAddon
{
    public function __construct(
        public int $productId,
        /** Nombre en el idioma activo. */
        public string $name,
        /** Cantidad EFECTIVA tras aplicar la config del pivote (no la pedida). */
        public int $quantity,
        /**
         * Unidades incluidas (gratis) de esas `quantity`. Si iguala a `quantity`, el complemento
         * sale a cero: es «incluido», no «gratis por error».
         */
        public int $freeQuantity,
        /** Lo que se cobra por el complemento: `(quantity − freeQuantity) × precio unitario`. */
        public int $subtotalCents,
    ) {}
}
