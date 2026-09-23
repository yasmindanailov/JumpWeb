<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Services\EmailSlip;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Audit hardening #113 (C1, 2026-05-28) — Email de **incidencia** cuando una notificación
 * autorizada de Redsys llega DESPUÉS de que `orders:expire` haya caducado la Order.
 *
 * Escenario: el cliente pagó, el banco capturó el cobro, pero la notificación on-line tardó
 * lo suficiente como para que el aforo se cediera lazy a otro cliente (race documentada en
 * `docs/PLAN-REDSYS.md §14` y decisión #113). El cobro NO se puede rechazar (dinero en
 * cuenta), pero la reserva NO se puede mantener firme sin riesgo de sobreventa física.
 *
 * Comportamiento operativo:
 *  - El cliente recibe ESTE email (no `OrderConfirmation`) con tono "hubo una incidencia".
 *  - Sabe que el cobro ocurrió + que será contactado en 24h.
 *  - El operador (panel admin Fase 7) ve el log `redsys.return.overbooked_alert` y decide:
 *    reagendar manualmente o devolver el cobro.
 *
 * Texto en el idioma del usuario (User implementa HasLocalePreference).
 */
class OrderProcessedAfterExpiration extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // #225: el banco solo capturó lo cobrado online (la señal si la había), NO el total del
        // pedido. `onlineDueCents()` (espejo de OrderConfirmation) → el importe mostrado y el
        // reembolso prometido reflejan lo realmente cobrado, no el valor pleno.
        $this->order->loadMissing(['items', 'adjustments']);

        return (new BrandedMailMessage($this))
            ->subject(__('emails.order_after_expiration.subject', ['code' => $this->order->code]))
            ->hero('emails.order_after_expiration', 'warn', EmailSlip::forOrder($this->order))
            ->line(__('emails.order_after_expiration.intro', ['code' => $this->order->code]))
            ->line(__('emails.order_after_expiration.amount', [
                'amount' => number_format($this->order->onlineDueCents() / 100, 2, ',', '.'),
            ]))
            ->line(__('emails.order_after_expiration.next_steps'))
            ->line(__('emails.order_after_expiration.contact'));
    }
}
