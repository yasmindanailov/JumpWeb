<?php

namespace App\Domain\Booking\Contracts;

use App\Domain\Booking\Services\ProductIcon;

/**
 * Una línea de la cesta ya tarificada ({@see CartQuote}).
 *
 * **`subtotalCents` es solo el principal.** Lo que suman los complementos va en cada
 * {@see CartQuoteAddon}, no agregado aquí: el carrito de la web los pinta como sublíneas y sumarlos
 * en el principal impediría distinguir «3 entradas» de «3 entradas + comida». El total de la cesta
 * (`CartQuote::totalCents`) sí los incluye.
 *
 * **La señal es POR LÍNEA** (#225 F2), no del pedido: en una cesta mixta —una entrada que se paga
 * entera y un cumpleaños que cobra señal— etiquetar el agregado como «señal» confundía. Cada línea
 * dice cuánto de ELLA se cobra online (`depositCents`) y cuánto queda para el parque
 * (`gateRemainderCents`, que incluye ya sus complementos por la Opción A de #225).
 */
final readonly class CartQuoteLine
{
    /**
     * @param  list<CartQuoteAddon>  $addons
     */
    public function __construct(
        /**
         * Posición de la línea en la cesta que se pasó a tarificar. Se conserva porque el llamante
         * necesita poder señalar ESTA línea —quitarla, editarla— y porque una línea descartada
         * (producto ya no vendible) se detecta por su ausencia en la secuencia.
         */
        public int $index,
        public int $productId,
        /** Nombre en el idioma activo. */
        public string $name,
        /** Un pack cuenta INVITADOS en `quantity`; una entrada cuenta unidades. */
        public bool $isPack,
        /** Fecha de la franja (`Y-m-d`). */
        public string $date,
        /** Hora de inicio de la franja (`H:i:s`), en formato canónico de BD. */
        public string $time,
        public int $quantity,
        /**
         * Precio unitario del día, en céntimos, según la tarifa que aplique a `date`
         * (`RateResolver`). **`null` = el producto no tiene precio para esa tarifa**, y entonces no
         * es vendible ese día: el checkout rechazará la línea (`PAY-12`) y `subtotalCents` vale 0.
         * Se distingue de un 0 explícito, que sí es un producto gratuito intencionado.
         */
        public ?int $unitPriceCents,
        /** `quantity × unitPriceCents` del PRINCIPAL, sin complementos. */
        public int $subtotalCents,
        /** @var list<CartQuoteAddon> */
        public array $addons,
        /**
         * ¿Esta línea cobra solo señal? Es `depositCents < subtotalCents`, no
         * `TicketType::hasDeposit()`: un producto con señal configurada al 100 % no deja nada en el
         * parque y anunciar un desglose vacío sería ruido.
         */
        public bool $hasDeposit,
        /** Parte del principal que se cobra ONLINE. Sin señal, es el subtotal entero. */
        public int $depositCents,
        /**
         * Lo que queda por cobrar en el parque de esta línea: el resto del principal MÁS sus
         * complementos íntegros (Opción A de #225). Cero si la línea no cobra señal.
         */
        public int $gateRemainderCents,
        /**
         * **La clave del icono que marca el producto** (`DECISIONES #140`).
         *
         * ⚠️ Viaja resuelta por el DOMINIO, como `is_pack` o las etiquetas ya compuestas. Antes el
         * cajón la derivaba de `is_pack` con la geometría copiada dentro, dos veces — así que un
         * catálogo entero se pintaba con dos dibujos y elegir otro exigía tocar Vue.
         */
        public string $icon = ProductIcon::DEFAULT_OTHER,
    ) {}

    /** Lo que suman los complementos cobrados de la línea. */
    public function addonsSubtotalCents(): int
    {
        return array_sum(array_map(
            static fn (CartQuoteAddon $addon): int => $addon->subtotalCents,
            $this->addons,
        ));
    }
}
