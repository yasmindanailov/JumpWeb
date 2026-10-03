<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Services\MarcasDePago;
use App\Domain\Payments\Services\RedsysResponseCode;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailPie;
use App\Notifications\Support\MailReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * **«EL PAGO NO HA SALIDO»** — el 5 del diseño (la R2e de `specs/correos-rediseno.md` §4.3; Audit #114, G1): cuando el banco
 * DENIEGA un cobro (`RedsysReturnHandler`, `Ds_Response ≥ 0100`, una vez por `Payment`) y la reserva sigue guardada; también lo
 * reenvía el panel (`ViewOrder`, el reintento de pago). Si el cliente cerró la pestaña, es lo único que le dice qué pasó.
 *
 * En el orden del diseño: lo que pasó y hasta qué hora sigue guardada; los botones —«Pagar con Bizum» (el principal) y «Volver
 * a intentar con tarjeta» SOLO si el parque cobra con Bizum (`payment.marks`, `#915`, c); si no, uno: «Volver a intentar el
 * pago»—, el MOTIVO del banco en mono (se dicta igual al llamar) y la ayuda por WhatsApp. Sin resguardo ni chapa: el diseño no
 * los lleva y el asunto dice cuándo.
 *
 * ⚠️ Bizum y tarjeta llevan al MISMO reintento: el producto no elige el método, la página del banco ofrece los que tiene el TPV
 * (y `payment.marks` dice que tiene Bizum). ⚠️ No se envía dentro de la transacción del handler: un fallo de correo no deshace
 * una transición de un `Payment`.
 */
class OrderPaymentDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  ?bool  $conBizum  si el parque cobra con Bizum; `null`, lo que diga el panel (`payment.marks`) al enviar. La vista
     *                           previa (R1·T2) lo fija para enseñar las dos formas.
     */
    public function __construct(public Order $order, public ?string $dsResponse = null, public ?bool $conBizum = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reserva = new MailReservation($this->order);
        $una = $reserva->unica();
        $bizum = $this->conBizum ?? in_array('bizum', MarcasDePago::elegidas(), true);
        $retry = route('account.orders');
        $guardada = $this->order->expires_at !== null && Carbon::now()->lessThan($this->order->expires_at)
            ? DisplayTime::format($this->order->expires_at, 'H:i')
            : null;

        $message = (new BrandedMailMessage($this))
            ->subject($una !== null
                ? (string) __('emails.order_declined.subject', ['day' => DisplayTime::dayAndMonth($una->slot->date), 'time' => MailReservation::hora($una)])
                : (string) __('emails.order_declined.subject_varias', ['code' => (string) $this->order->code]))
            ->hero('emails.order_declined', 'err')
            ->links(['whatsapp' => ($wa = MailPie::current()->whatsapp) !== null ? 'https://wa.me/'.$wa : null])
            ->paragraphs($guardada !== null
                ? (string) __('emails.order_declined.body', ['hora' => $guardada])
                : (string) __('emails.order_declined.body_sin_hora'));

        if ($bizum) {
            $message->buttons((string) __('emails.order_declined.action_bizum'), $retry, (string) __('emails.order_declined.action_card'), $retry);
        } else {
            $message->button((string) __('emails.order_declined.action'), $retry);
        }

        $message->reason((string) __('emails.order_declined.reason_label'), RedsysResponseCode::reasonText($this->dsResponse));

        // La ayuda, solo con un WhatsApp al que escribir: la frase lo nombra.
        if (MailPie::current()->whatsapp !== null) {
            $message->small((string) __($bizum ? 'emails.order_declined.help_bizum' : 'emails.order_declined.help'));
        }

        return $message;
    }
}
