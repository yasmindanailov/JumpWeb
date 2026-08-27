<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Services\EmailProductCard;
use App\Domain\Booking\Services\OrderLedger;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\QrCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email de confirmación de reserva PAGADA (capa 5.5c, #104).
 *
 * Originalmente (auditoría 2026-05-26 2ª ronda, P-01) se enviaba al crear la reserva firme
 * con texto "pendiente de pago". Con Redsys real, se envía SOLO tras autorización del banco
 * (`RedsysReturnHandler` → `Ds_Response` ∈ 0000–0099): cumpleel principio "no decir al
 * cliente que su reserva está confirmada hasta que el cobro está cerrado". Si el cliente
 * cierra la pestaña, este email es su único rastro fuera de "Mis pedidos".
 *
 * El email llega en el idioma del usuario (User implementa HasLocalePreference).
 */
class OrderConfirmation extends Notification implements ShouldQueue
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
        $park = (string) Setting::value('business.name', config('app.name'));

        $message = (new MailMessage)
            ->subject(__('emails.order_confirmation.subject', ['code' => $this->order->code, 'park' => $park]))
            ->greeting(__('emails.order_confirmation.greeting'))
            ->line(__('emails.order_confirmation.intro', ['code' => $this->order->code]));

        // Subcard(s) de producto (#251): identifica cada reserva del pedido (icono + fecha/hora +
        // invitados + complementos + precio). Cortesía visual; NO altera la lógica de textos de abajo.
        $message->line(EmailProductCard::forOrder($this->order));

        // #225: si el pedido cobra SEÑAL (existen ajustes `deposit_remainder` vivos), el email
        // dice «Señal pagada online: X» + «Pendiente en el parque: Y» en vez de «Total pagado».
        // Predicado canónico `depositRemainder > 0` (NO `online < total`, que se encendería por
        // una línea cancelada en un pedido sin señal). El pendiente sale de `pendingAtGate()`
        // (robusto a ediciones/cancelaciones en un reenvío), no de `total − online`.
        $this->order->loadMissing(['items.ticketType', 'items.slot', 'adjustments', 'payments.refunds']);
        $summary = $this->order->financialSummary();
        // ⚠️ Los importes salen del LEDGER, no de `onlineDueCents()` ni de `Order.total`
        // (`DECISIONES #127`). Es el mismo motivo por el que lo pendiente ya salía de
        // `pendingAtGate()`: **este correo se REENVÍA desde el panel**, y para entonces el pedido
        // puede haber cambiado. `onlineDueCents()` es «lo que se cobraría al pagar ahora» y
        // `Order.total` es lo facturado: los dos envejecen mal en un reenvío. `pagadoOnline` es lo
        // realmente pagado que respalda producto, y `valor` lo que vale hoy.
        $ledger = OrderLedger::forOrder($this->order);
        if ($summary->depositRemainder > 0) {
            $message
                ->line(__('emails.order_confirmation.deposit_paid', [
                    'amount' => number_format($ledger->pagadoOnline / 100, 2, ',', '.'),
                ]))
                ->line(__('emails.order_confirmation.pending_at_park', [
                    'amount' => number_format($ledger->pendientePuerta / 100, 2, ',', '.'),
                ]));
        } else {
            $message->line(__('emails.order_confirmation.total', [
                'amount' => number_format($ledger->valor / 100, 2, ',', '.'),
            ]));
        }

        // Audit #114 G8 — Fecha y hora del cobro (en la TZ de presentación, #111). Solo
        // cuando `paid_at` existe (rama Authorized del handler; en `IdempotentPaid` también
        // se preserva el `paid_at` original).
        if ($this->order->paid_at) {
            $message->line(__('emails.order_confirmation.paid_at', [
                'when' => DisplayTime::format($this->order->paid_at, 'd/m/Y H:i'),
            ]));
        }

        // La promesa de «te pediremos los datos de los invitados» SOLO aplica si el pedido tiene
        // post-form (pack con `guest_fields`); para entradas no existe ese paso (#217).
        $paidConfirmation = $this->order->needsGuestForm()
            ? 'emails.order_confirmation.paid_confirmation_guest_form'
            : 'emails.order_confirmation.paid_confirmation';

        // Fase 6 · subsistema A (`specs/identidad-qr-puerta.md` §4.10, §9.2 A·8): el CARNÉ QR viaja en
        // este correo como PNG ADJUNTO —no como imagen remota (los clientes las bloquean) ni como SVG
        // inline (no lo renderizan)—. El carné nace aquí si el titular aún no tiene. Si la clave de
        // cifrado rotó y no se puede repintar (§8.1), el correo sale sin adjunto en vez de fallar.
        $token = $this->order->user !== null
            ? app(CustomerCards::class)->ensureFor($this->order->user)->plainToken()
            : null;
        if ($token !== null) {
            $message
                ->line(__('emails.order_confirmation.card_attached'))
                ->attachData(QrCode::png($token), 'carne-qr.png', ['mime' => 'image/png']);
        }

        return $message
            ->action(__('emails.order_confirmation.action'), route('account.orders'))
            ->line(__($paidConfirmation))
            ->line(__('emails.order_confirmation.outro'));
    }
}
