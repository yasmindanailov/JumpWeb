<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un día que un producto SÍ se puede reservar, con lo que cuesta ese día ({@see AvailabilityOffer}).
 *
 * Lleva el precio porque un calendario de compra sin precios obliga al cliente a entrar día a día
 * para comparar —es el hallazgo P-03 de la auditoría del origen, y por eso la web ya lo pinta— y
 * porque calcularlo fuera significaría resolver otra vez la tarifa de cada día por cuenta ajena.
 *
 * **Que un día esté ofrecido no garantiza que se pueda comprar a esa hora**: el cupo se decide por
 * franja y con la cesta delante ({@see AvailabilityOffer::times()}). Aquí solo se dice qué días
 * tienen alguna franja ofrecible.
 */
final readonly class OfferedDate
{
    public function __construct(
        /** Fecha en formato canónico `Y-m-d`. */
        public string $date,
        /**
         * Precio unitario de ESE día en céntimos, según la tarifa que le aplique. **`null` = el
         * producto no tiene precio para esa tarifa**: el día se ofrece —tiene franjas— pero el
         * checkout rechazará la línea (`PAY-12`). Se distingue de un 0, que es un producto
         * gratuito intencionado.
         */
        public ?int $priceCents,
        /**
         * Clave de la tarifa aplicada (`normal`, `special`…). Es un valor de configuración, no una
         * etiqueta traducible: sirve para que un cliente distinga y marque los días de tarifa
         * especial sin tener que deducirlos del precio.
         */
        public string $rateKey,
    ) {}
}
