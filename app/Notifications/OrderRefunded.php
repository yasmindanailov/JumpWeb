<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Services\EmailBookBlock;
use App\Domain\Booking\Services\EmailProductCard;
use App\Domain\Booking\Services\EmailSlip;
use App\Domain\Payments\Models\Payment;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Audit #114 (G5) + sub-fase 7.2b ampliada (#139) — Email tras reembolso.
 *
 * Importe = preferentemente del `Payment` paid (en `Order.refund_amount_cents`
 * para devoluciones parciales futuras); fallback `Order.total`. El email es
 * EXPLÍCITO sobre el importe y el plazo bancario — única vía por la que el
 * cliente conoce esos datos.
 *
 * `$alsoCancelled` (sub-fase 7.2b ampliada): cuando la acción del panel marca
 * "También cancelar el pedido" la transición de status va a `cancelled` y este
 * email incluye una línea extra indicándolo. NO se envía además `OrderCancelled`
 * — un solo correo coherente es más claro para el cliente que dos seguidos.
 *
 * Importante: el reembolso REAL en Redsys lo ejecuta el operativo desde el portal
 * del banco (decisión #129). El panel registra el evento (`refunded_at` +
 * `refund_amount_cents`) y dispara este email; cualquier desfase con el extracto
 * bancario se resuelve operativamente.
 *
 * Texto en el idioma del usuario (User implementa HasLocalePreference).
 */
class OrderRefunded extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $manualRefund  T5 (`cumple-mixto.md` §25.5, Q3): el reembolso se registró como
     *                              `PaymentRefund::MODE_MANUAL` (fuera de la pasarela — el circuito
     *                              de parque de §20.5). Con él, la línea del plazo bancario se
     *                              sustituye por la voz del hecho: «se te ha devuelto en el parque».
     */
    public function __construct(
        public Order $order,
        public ?Payment $payment = null,
        public bool $alsoCancelled = false,
        public bool $manualRefund = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Prioridad para el importe: refund_amount_cents (registro del panel) →
        // Payment.amount (cobro real) → Order.total (último fallback defensivo).
        $cents = $this->order->refund_amount_cents
            ?? $this->payment?->amount
            ?? $this->order->total;

        $message = (new BrandedMailMessage($this))
            ->subject(__('emails.order_refunded.subject', ['code' => $this->order->code]))
            ->hero('emails.order_refunded', 'neutro', EmailSlip::forOrder($this->order))
            ->line(__('emails.order_refunded.intro', ['code' => $this->order->code]))
            // Reembolso del PEDIDO: muestra TODAS las reservas (también las canceladas antes sin
            // devolver), porque la devolución total las cubre — si no, el correo «devolución del total»
            // mostraba de menos (bug JJ-YJDCVM). Las canceladas salen marcadas «Cancelado».
            ->line(EmailProductCard::forOrder($this->order, withPrice: false, includeCancelled: true))
            ->line(__('emails.order_refunded.amount', [
                'amount' => number_format($cents / 100, 2, ',', '.'),
            ]));

        if ($this->alsoCancelled) {
            $message->line(__('emails.order_refunded.also_cancelled'));
        }

        // EL LIBRO del pedido tras la devolución (T3·3 del libro, D-T3·19): la devolución es una línea
        // más, con su fecha, y el saldo dice si queda algo que pagar o devolver — el importe de arriba
        // ya no va suelto, está en su sitio.
        $message->line(EmailBookBlock::forOrder($this->order));

        // La línea del CANAL sigue al modo real del registro (T5 §25.5): la de tarjeta solo cuando
        // el reembolso fue por la pasarela.
        return $message
            ->line(__($this->manualRefund ? 'emails.order_refunded.when_manual' : 'emails.order_refunded.when'))
            ->action(__('emails.order_refunded.action'), route('account.orders'))
            ->line(__('emails.order_refunded.contact'));
    }
}
