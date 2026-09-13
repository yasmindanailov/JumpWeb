<?php

namespace App\Domain\Booking\Contracts;

/**
 * **La edad del cumpleañero NO cabe en el tramo del pack** (`DECISIONES #588`, `[DECIDIDO owner]`).
 *
 * Un pack de cumpleaños declara su tramo de edades (`guest_age_min`/`guest_age_max`) y pide la edad
 * del cumpleañero en un campo `celebrant_age`. Si la edad contestada queda fuera, **la web no deja
 * reservar ese pack** y le recomienda al cliente el de su misma familia que sí la admite; el panel
 * **avisa y deja**, porque el parque tiene al cliente delante.
 *
 * ▶ Lleva los DATOS y compone la frase en UN sitio, para que el cajón, el pedido y el panel digan lo
 * mismo. El cajón compone la misma frase con los mismos textos (`line-problems.js`).
 */
final readonly class CelebrantAgeMismatch
{
    public function __construct(
        /** La clave del campo de edad del cumpleañero, para resaltar ESE input. */
        public string $field,
        public int $age,
        public ?int $min,
        public ?int $max,
        /** El pack de la misma familia que admite esa edad y se vende online, o `null`. */
        public ?int $suggestedProductId = null,
        public ?string $suggestedProductName = null,
    ) {}

    /** «Este pack es para cumpleaños de 4 a 7 años. Para esa edad, elige «Pack Jump».» */
    public function sentence(): string
    {
        $tramo = match (true) {
            $this->min !== null && $this->max !== null => __('tickets.errors.celebrant_age_between', ['min' => $this->min, 'max' => $this->max]),
            $this->min !== null => __('tickets.errors.celebrant_age_from', ['min' => $this->min]),
            $this->max !== null => __('tickets.errors.celebrant_age_up_to', ['max' => $this->max]),
            default => __('tickets.errors.celebrant_age_generic'),
        };

        return $this->suggestedProductName === null
            ? (string) $tramo
            : $tramo.' '.__('tickets.errors.celebrant_age_try', ['product' => $this->suggestedProductName]);
    }
}
