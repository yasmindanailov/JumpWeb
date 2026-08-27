<?php

namespace App\Domain\Booking\Contracts;

/**
 * **Las LÍNEAS PRINCIPALES de un pedido recién creado, en el orden de la cesta**, para quien vive
 * fuera de Booking — hoy, la asignación de entradas a menores a cargo (`Identity\Services\
 * DependentAssigner`, `docs/specs/menores-a-cargo.md` §9.9.3 D2).
 *
 * Existe por la misma regla que {@see CustomerReservations}: consultar datos de Booking desde otro
 * módulo exige contrato. Identity necesita saber, de cada línea del pedido, su `id`, cuántas
 * unidades tiene, si es una ENTRADA (solo las entradas admiten asignación, §4.7) y para qué día es
 * (la minoría de edad se decide en la FECHA DE LA VISITA, D13) — y no puede importar `OrderItem`.
 *
 * ⚠️ **La promesa que vale el contrato es el ORDEN.** `OrderCreator` crea los ítems principales
 * recorriendo la cesta en su orden y no persiste ningún índice de línea; lo único estable es el `id`,
 * y `orderBy('id')` reproduce el orden de la cesta porque así nacieron. Es Booking quien lo sabe y
 * por eso es Booking quien lo promete: `CheckoutLine::$index` es la posición en la cesta que el
 * cliente mandó. Si algún día los ítems dejaran de crearse en ese orden, este contrato es lo que hay
 * que cambiar, no el consumidor.
 *
 * Recibe `int $orderId` e `int $userId`, no modelos: un pedido que no sea del titular devuelve la
 * lista VACÍA, como si no existiera (misma doctrina anti-IDOR que `GET /orders/{code}`).
 *
 * Implementación actual: `App\Domain\Booking\Services\CheckoutLinesReader` (bind en
 * `BookingServiceProvider`).
 */
interface CheckoutLines
{
    /**
     * Los ítems PRINCIPALES (sin `parent_item_id`) del pedido del titular, en el orden de la cesta.
     *
     * @return list<CheckoutLine>
     */
    public function forOrder(int $orderId, int $userId): array;
}
