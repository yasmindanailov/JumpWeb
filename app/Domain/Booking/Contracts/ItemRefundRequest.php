<?php

namespace App\Domain\Booking\Contracts;

/**
 * Lo que el operador pidió en el modal «Reembolsar» de un ítem, ya
 * normalizado por la capa de entrega — extracción 4b del desmontaje de
 * `ViewOrder`, spec §9.6·1 (sub-paso E).
 *
 * Dos cosas las resuelve la PÁGINA antes de construir esto, y a propósito:
 *  - `mode` (`rest` | `manual`): forzado a manual cuando el pago no es
 *    reembolsable por Redsys (`Order::isRedsysRefundable()`), exactamente
 *    como hace el reembolso a nivel PEDIDO. El dominio de Booking no puede
 *    importar `PaymentRefund` para leer sus constantes (la baseline de
 *    módulos «solo encoge»), así que el modo llega como cadena ya válida.
 *  - `intent`: validado contra `PaymentRefund::intents()` o `null`.
 *
 * El resto llega tal cual del formulario y lo valida `OrderItemRefunder`:
 * la selección (pertenencia, vacía), el importe a medida (una sola línea,
 * positivo, no mayor que el remanente) y los dos centinelas (token del ítem,
 * capacidad reembolsable esperada).
 */
final readonly class ItemRefundRequest
{
    /**
     * @param  list<int>  $selectedIds  los `item_id` marcados en la lista de casillas
     * @param  'remainder'|'custom'  $amountMode
     * @param  string|float|int|null  $customAmount  el importe tecleado en EUROS (solo con `custom`)
     */
    public function __construct(
        public array $selectedIds,
        public string $optimisticToken,
        public int $expectedCapacityCents,
        public string $mode,
        public ?string $intent,
        public string $amountMode = 'remainder',
        public string|float|int|null $customAmount = null,
    ) {}
}
