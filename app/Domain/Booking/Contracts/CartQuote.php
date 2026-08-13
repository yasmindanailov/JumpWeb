<?php

namespace App\Domain\Booking\Contracts;

/**
 * Una cesta ya tarificada por el servidor: lo que suma, lo que se cobra ahora y el desglose por
 * línea ({@see CartPricing}).
 *
 * Los dos importes son distintos y ninguno se deriva del otro:
 *  - `totalCents` es el valor de la reserva (lo que vale todo, se pague cuando se pague);
 *  - `onlineAmountCents` es lo que la pasarela va a cobrar AHORA. Con productos de señal (#225) es
 *    menor que el total, y el resto se cobra en el parque.
 *
 * Es el mismo par que `Order::total` / `Order::onlineDueCents()` produce una vez creado el pedido:
 * si estos dos números y aquellos dos divergieran para la misma cesta, el cliente vería un importe
 * en la pantalla de pago y otro en el TPV. Por eso el nombre es `onlineAmountCents` y no
 * «pendiente»: es el IMPORTE QUE SE COBRA ONLINE, y no baja a cero al pagarse (la lección 9 del
 * spec §10.bis, que ya costó un renombrado en el paso 1a).
 */
final readonly class CartQuote
{
    /**
     * @param  list<CartQuoteLine>  $lines  una por línea VENDIBLE de la cesta, en su orden
     */
    public function __construct(
        public array $lines,
        /** Valor total de la cesta en céntimos: principales + complementos cobrados. */
        public int $totalCents,
        /**
         * Importe que se cobra ONLINE en céntimos. Sin señal coincide con `totalCents`; con señal
         * es la suma de las señales, y los complementos de un producto CON señal van íntegros al
         * parque (Opción A de #225, decisión de producto ya tomada).
         */
        public int $onlineAmountCents,
    ) {}

    /** ¿Queda algo por cobrar presencialmente? Es lo que decide si mostrar el desglose señal/parque. */
    public function hasGateRemainder(): bool
    {
        return $this->onlineAmountCents < $this->totalCents;
    }
}
