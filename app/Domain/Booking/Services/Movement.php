<?php

namespace App\Domain\Booking\Services;

/**
 * **Una línea de VALOR del libro** (`specs/desglose-libro.md` §4.3, `DECISIONES #305`): lo que una
 * gestión hizo con lo que vale el pedido, con su signo y su fecha. La compone {@see OrderBook}.
 *
 * Cinco clases, y son las CINCO cosas que pueden mover el valor:
 *
 *  - `booking`  · con qué NACIÓ el pedido (`Order.total`, lo facturado) — o, en el libro de una
 *                 reserva, con qué nació ella (`nac(r)`, la suma de sus líneas);
 *  - `edit`     · el delta ENTERO de una gestión (cantidad · producto · fecha con re-tarifa ·
 *                 complemento añadido o subido · re-escala per-invitado): la fila `edit`, con signo;
 *  - `mixed`    · la línea VIVA de fiesta mixta —suplemento (+) o descuento (−)—, que
 *                 `MixedPartySurcharge` reconcilia en el sitio; su fecha es la de su ÚLTIMO importe;
 *  - `cancel`   · lo que una cancelación retira: `−(fila + cortesía)` de la línea en ese momento —
 *                 la cortesía de una línea cancelada se extingue con ella (spec §4.1);
 *  - `courtesy` · dinero devuelto SIN que desapareciera producto (≤ 0), escrito al reembolsar.
 *
 * `Σ amount_cents` de las líneas de valor tiene que ser el Total del libro: es la identidad `I3`,
 * y {@see OrderBook} la evalúa en ejecución. Un compositor que olvide una clase deja el pedido
 * «en revisión», no un total que miente.
 *
 * `occurred_at` es un instante (ISO-8601, UTC); `occurred_label` es ese instante en la zona del
 * parque como `d/m/Y` — la hora vive en el historial, no aquí (spec §4.9).
 */
final readonly class Movement
{
    public const KIND_BOOKING = 'booking';

    public const KIND_EDIT = 'edit';

    public const KIND_MIXED = 'mixed';

    public const KIND_CANCEL = 'cancel';

    public const KIND_COURTESY = 'courtesy';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_BOOKING,
        self::KIND_EDIT,
        self::KIND_MIXED,
        self::KIND_CANCEL,
        self::KIND_COURTESY,
    ];

    public function __construct(
        public string $kind,
        /** Compuesta por el dominio ({@see MovementLabel}), neutra de voz: la misma para cliente y panel. */
        public string $label,
        /** Con signo: + sube el valor, − lo baja. */
        public int $amountCents,
        /** ISO-8601. */
        public string $occurredAt,
        /** `d/m/Y` en la zona de presentación del parque. */
        public string $occurredLabel,
        /** El principal de la reserva a la que pertenece; `null` en el nacimiento del PEDIDO. */
        public ?int $reservationId,
    ) {}
}
