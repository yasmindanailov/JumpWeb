<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un EXTRA de venta posterior tal y como se le enseña al cliente en su formulario
 * (`specs/complementos-post-reserva.md` §4.7·bis, T3 de `DECISIONES #413`).
 *
 * ⚠️⚠️ **No es `ResolvedAddon`, y la diferencia es de DINERO, no de forma.** Aquél publica el precio
 * del CATÁLOGO de hoy; aquí manda el precio de la **LÍNEA** cuando ya hay unidades compradas, que es
 * lo que se le comunicó al cliente y lo que el LIBRO dice. Con el catálogo subido de 12 a 14 €,
 * reutilizar aquel DTO habría dado **dos pantallas con dos importes y ningún fallo**.
 *
 * ⚠️ Y el estado es **POR FILA**, no por formulario: con «tapas 48 h» y «cubo 2 h» sobre la misma
 * fiesta, a 24 h uno está cerrado y el otro abierto. Un `readonly` de formulario no sabe decir eso.
 */
final readonly class PostFormAddonView
{
    public function __construct(
        public int $productId,
        public string $productName,
        /** El unitario que se aplicará: el de la LÍNEA si ya hay unidades, el del catálogo si no. */
        public int $unitPriceCents,
        /** La nota de precio ya compuesta y traducida, como en el embudo. */
        public string $note,
        /**
         * Lo que lleva dentro, traducido: el «Más info» que la landing ya enseña (`#416`).
         *
         * ⚠️ Aquí NO es adorno. Estos extras se eligen en esta pantalla y en ninguna otra, así que
         * sin esto el cliente decide entre «Combo 1 · 39 €» y «Combo 2 · 59 €» sin saber qué llevan.
         * El dato ya existía en `ticket_types.features`, en tres idiomas; solo no se pintaba.
         *
         * @var list<string>
         */
        public array $features,
        /** Lo que el cliente tiene AHORA de este extra. */
        public int $quantity,
        /** El tope del enganche, obligatorio en esta fase: la deuda máxima la declara el parque. */
        public int $maxQuantity,
        /** `quantity × unitPriceCents`. */
        public int $chargedCents,
        /** `true` si no se puede ni añadir ni quitar. */
        public bool $closed,
        /** `cutoff` (venció su plazo) · `sold_at_booking` (se compró al reservar) · `null`. */
        public ?string $closedReason,
        /** Cuándo vence su plazo, en ISO 8601, para poder avisar antes de que pase. */
        public ?string $closesAt,
    ) {}

    public const REASON_CUTOFF = 'cutoff';

    public const REASON_SOLD_AT_BOOKING = 'sold_at_booking';
}
