<?php

namespace App\Domain\Booking\Services;

/**
 * **El SALDO del libro y su sentido** (`specs/desglose-libro.md` §4.4, `[DECIDIDO owner]` D2):
 * `Saldo = Total − Pagado − Liquidado`, y el signo dice qué pasa en el parque — positivo se paga,
 * negativo se devuelve. Nada se cobra ni se devuelve online después de la reserva.
 *
 * Las siete clases, cerradas en la spec:
 *
 *  - `pay_at_park`    · pedido cobrado, `Saldo > 0` → «A pagar en el parque»;
 *  - `refund_at_park` · cobrado, `Saldo < 0` y hay una reserva viva sin finalizar → «A devolver en el parque»;
 *  - `refund_pending` · cobrado, `Saldo < 0` y NO habrá visita (pedido cancelado, o todas sus reservas
 *                       finalizadas o canceladas) → «Pendiente de devolución» (el operador decide el canal, D5);
 *  - `settled`        · cobrado y `Saldo = 0`;
 *  - `pay_online`     · sin cobrar y aún pagable → «Pendiente de pagar por web», con lo que falta por
 *                       cobrar POR WEB en `cents` y el resto —la señal— en `rest_at_park_cents`;
 *  - `expired`        · caducado sin cobro;
 *  - `under_review`   · las identidades no cierran: ningún saldo es cierto y no se afirma ninguno.
 *
 * ⚠️ `cents` va CON SIGNO (contrato §4.5): positivo se paga, negativo se devuelve. Quien pinta un
 * rótulo pinta la magnitud y elige la frase por `kind`; quien opera lee el signo.
 */
final readonly class Balance
{
    public const KIND_PAY_AT_PARK = 'pay_at_park';

    public const KIND_REFUND_AT_PARK = 'refund_at_park';

    public const KIND_REFUND_PENDING = 'refund_pending';

    public const KIND_SETTLED = 'settled';

    public const KIND_PAY_ONLINE = 'pay_online';

    public const KIND_EXPIRED = 'expired';

    public const KIND_UNDER_REVIEW = 'under_review';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_PAY_AT_PARK,
        self::KIND_REFUND_AT_PARK,
        self::KIND_REFUND_PENDING,
        self::KIND_SETTLED,
        self::KIND_PAY_ONLINE,
        self::KIND_EXPIRED,
        self::KIND_UNDER_REVIEW,
    ];

    public function __construct(
        public string $kind,
        /**
         * Con signo. En `pay_online` es lo que falta por cobrar POR WEB (no el saldo entero); en
         * `expired` y `under_review` es 0: no hay nada que afirmar.
         */
        public int $cents,
        /**
         * Solo en `pay_online`: lo que, además de lo online, se pagará en el parque — el resto de la
         * señal. Publicado y no derivado (`Total − cents`), porque una superficie que lo restara por
         * su cuenta sería la clase de duplicado que `LedgerSingleSourceTest` prohíbe.
         */
        public int $restAtParkCents = 0,
    ) {}
}
