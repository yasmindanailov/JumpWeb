<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;

/**
 * **Una línea de DINERO del libro** (`specs/desglose-libro.md` §4.3): lo que de verdad entró, salió
 * o se liquidó, con su estado y su fecha. La compone {@see OrderBook}.
 *
 * Tres clases:
 *
 *  - `payment` · un cobro con éxito (+), por `web` (la pasarela) o en el `desk` (la taquilla);
 *  - `refund`  · una devolución (−), a la tarjeta (`card`, REST) o registrada a mano (`manual`),
 *                con su estado: `succeeded` · `pending` (en curso) · `failed`;
 *  - `gate`    · lo LIQUIDADO en el parque (+): la regla de liquidación implícita —franja pasada y
 *                pedido cobrado, `[DECIDIDO owner]` D9 de la T5 de mixtos—, fechada al fin de la franja.
 *
 * ⚠️ **Solo lo `succeeded` cuenta en `paid_cents`** (spec §4.4): un reembolso en curso o fallido se
 * LISTA —cliente y operador lo ven— pero el saldo sigue diciendo «a devolver X» mientras el dinero
 * no ha vuelto, y `PAY-09` (la capacidad cuenta los pendientes) impide devolverlo dos veces.
 *
 * ▶ Los vocabularios de `status` y `method` son de BOOKING a propósito: `Booking\Services` no puede
 * nombrar `Payments\Models` (`ModuleBoundariesTest`), así que es {@see Order}
 * —que sí está en la costura— quien traduce los estados y modos de `Payment`/`PaymentRefund` a
 * estas constantes ({@see Order::collectedPaymentFacts},
 * {@see Order::refundFacts}).
 */
final readonly class Settlement
{
    public const KIND_PAYMENT = 'payment';

    public const KIND_REFUND = 'refund';

    public const KIND_GATE = 'gate';

    /** @var list<string> */
    public const KINDS = [self::KIND_PAYMENT, self::KIND_REFUND, self::KIND_GATE];

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_SUCCEEDED, self::STATUS_PENDING, self::STATUS_FAILED];

    /** Cobro por la pasarela. */
    public const METHOD_WEB = 'web';

    /** Cobro en taquilla (efectivo o datáfono). */
    public const METHOD_DESK = 'desk';

    /** Devolución a la tarjeta por REST. */
    public const METHOD_CARD = 'card';

    /** Devolución REGISTRADA por el operador (el dinero se devolvió fuera del sistema). */
    public const METHOD_MANUAL = 'manual';

    /** @var list<string> */
    public const METHODS = [self::METHOD_WEB, self::METHOD_DESK, self::METHOD_CARD, self::METHOD_MANUAL];

    public function __construct(
        public string $kind,
        /** Compuesta por el dominio ({@see MovementLabel}), neutra de voz. */
        public string $label,
        /** Con signo: + cobros y liquidación · − devoluciones. */
        public int $amountCents,
        /** ISO-8601. */
        public string $occurredAt,
        /** `d/m/Y` en la zona del parque; para `gate`, la fecha CIVIL de la franja. */
        public string $occurredLabel,
        public string $status,
        /** `null` en `gate`: la liquidación no tiene canal, es una regla. */
        public ?string $method,
    ) {}

    /** ¿Cuenta en `paid_cents`? Solo lo que de verdad se ha movido. */
    public function isEffective(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }
}
