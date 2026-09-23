<?php

namespace App\Domain\Payments\Observers;

use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\Analytics\Recorder;

/**
 * **EL RECHAZO DEL BANCO, como hecho** (`docs/specs/analitica.md` §4.1). Un pago denegado NO es una transición
 * del pedido —`RedsysReturnHandler` marca el `Payment` como `failed` y «Order sigue pending» (spec §7.1,
 * dinero-2)—, así que el hecho se escucha aquí, en el modelo que sí cambia. Sin tocar el handler.
 *
 * ⚠️ El `de → a` se captura en `saving` y se registra en `saved`, como en `OrderAnalyticsObserver`: un
 * callback diferido ya no vería el cambio.
 *
 * ⚠️ Sin canal ni visitante: la notificación del banco es un POST del propio Redsys, sin cookie, y el sello
 * vive en el pedido, que es de otro módulo. El hecho se ata por `order_id` y basta para el embudo.
 */
final class PaymentAnalyticsObserver
{
    /** @var array<int, array{from: string, to: string}> */
    private array $transitions = [];

    public function __construct(private readonly Recorder $recorder) {}

    public function saving(Payment $payment): void
    {
        if (! $payment->exists || ! $payment->isDirty('status')) {
            return;
        }

        $this->transitions[spl_object_id($payment)] = [
            'from' => (string) $payment->getOriginal('status'),
            'to' => (string) $payment->status,
        ];
    }

    public function saved(Payment $payment): void
    {
        $key = spl_object_id($payment);
        $transition = $this->transitions[$key] ?? null;
        unset($this->transitions[$key]);

        if ($transition === null || $transition['to'] !== Payment::STATUS_FAILED || $transition['from'] === $transition['to']) {
            return;
        }

        // El alias del morph, no la clase: Payments no mira a Booking (`ModuleBoundariesTest`).
        if ($payment->payable_type !== 'order') {
            return;
        }

        $this->recorder->fact('order_declined', [], [
            'order_id' => (int) $payment->payable_id,
            'payment_id' => (int) $payment->id,
        ]);
    }
}
