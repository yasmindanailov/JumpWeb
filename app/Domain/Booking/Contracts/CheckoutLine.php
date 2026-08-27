<?php

namespace App\Domain\Booking\Contracts;

/**
 * Una línea PRINCIPAL de un pedido, vista desde fuera de Booking ({@see CheckoutLines}).
 *
 * Solo lo que el consumidor real (`Identity\Services\DependentAssigner`) necesita para decidir si
 * una asignación cabe: la posición en la cesta, el `id` del ítem, cuántas unidades tiene, si es
 * una entrada y para qué día es. Ni precio, ni producto, ni franja: eso ya lo sirve
 * `OrderItemResource` y aquí sería una copia.
 */
final readonly class CheckoutLine
{
    public function __construct(
        /** Posición en la cesta que el cliente mandó (0-based): la misma que `QuoteLine.index`. */
        public int $index,
        /** El `id` del `order_item`: la ÚNICA llave estable de la línea. */
        public int $orderItemId,
        /** Unidades de la línea. Una asignación no puede tener más menores que unidades (§4.7). */
        public int $quantity,
        /** Solo las ENTRADAS admiten asignación (§4.7): los packs ya piden sus invitados por `guest_fields`. */
        public bool $isEntry,
        /** El día de la visita en `Y-m-d`, o `null` si la línea no tiene franja. */
        public ?string $date,
    ) {}
}
