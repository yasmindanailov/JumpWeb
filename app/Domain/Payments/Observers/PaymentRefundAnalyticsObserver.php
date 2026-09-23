<?php

namespace App\Domain\Payments\Observers;

use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\Analytics\Recorder;

/**
 * **LA DEVOLUCIÓN, como hecho** (`docs/specs/analitica.md` §4.1). Un reembolso no es un estado del pedido
 * —`Order::STATUS_REFUNDED` está retirado y el pedido sigue `paid`—: son filas de `payment_refunds`, cada
 * una con SU importe (`PAY-17` I4). El hecho se registra cuando una pasa a `succeeded`, con
 * `refunded_cents` de ESA devolución, nunca el agregado del pedido (spec §7.1, dinero-2, dinero-3).
 */
final class PaymentRefundAnalyticsObserver
{
    /** @var array<int, array{from: string, to: string}> */
    private array $transitions = [];

    public function __construct(private readonly Recorder $recorder) {}

    public function saving(PaymentRefund $refund): void
    {
        if (! $refund->exists || ! $refund->isDirty('status')) {
            return;
        }

        $this->transitions[spl_object_id($refund)] = [
            'from' => (string) $refund->getOriginal('status'),
            'to' => (string) $refund->status,
        ];
    }

    public function created(PaymentRefund $refund): void
    {
        // Una devolución que nace ya `succeeded` (reembolso síncrono) no pasa por `saving` con cambio.
        if ($refund->status === PaymentRefund::STATUS_SUCCEEDED) {
            $this->record($refund);
        }
    }

    public function saved(PaymentRefund $refund): void
    {
        $key = spl_object_id($refund);
        $transition = $this->transitions[$key] ?? null;
        unset($this->transitions[$key]);

        if ($transition === null || $transition['to'] !== PaymentRefund::STATUS_SUCCEEDED || $transition['from'] === $transition['to']) {
            return;
        }

        $this->record($refund);
    }

    private function record(PaymentRefund $refund): void
    {
        $payment = $refund->payment;

        if ($payment === null || $payment->payable_type !== 'order') {
            return;
        }

        $this->recorder->fact('order_refunded', ['refunded_cents' => (int) $refund->amount_cents], [
            'order_id' => (int) $payment->payable_id,
            'payment_id' => (int) $payment->id,
            'refund_id' => (int) $refund->id,
        ]);
    }
}
