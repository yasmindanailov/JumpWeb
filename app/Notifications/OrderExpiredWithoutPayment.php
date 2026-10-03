<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailPie;
use App\Notifications\Support\MailReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **«TU HORA SE HA LIBERADO»** — el 6 del diseño (la R2e de `specs/correos-rediseno.md` §4.3; Audit #114, G2): cuando una
 * reserva CADUCA sin completar el pago (`orders:expire`) y el cliente había llegado a la pasarela (un `Payment` pendiente).
 * Sin él no sabe que su hora ya no es suya; y si pagó de verdad, necesita por dónde reclamar.
 *
 * NO se envía si la reserva no llegó a la pasarela (un abandono limpio no merece correo) ni si el pago se DENEGÓ (ya recibió el 5:
 * dos correos serían ruido). Lo decide `ExpireOrders`.
 *
 * En el orden del diseño: lo que pasó, «Volver a reservar» y, si pagó y no ve la confirmación, por dónde escribir con su
 * número. ⚠️ El botón lleva a la PORTADA, no a la hora de antes: la compra solo se abre situada desde la calculadora de cada
 * página de la instancia, y el producto no sabe en qué página está cada producto. Por eso dice «reservar», no «reservarla».
 */
class OrderExpiredWithoutPayment extends Notification implements ShouldQueue
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
        $una = (new MailReservation($this->order))->unica();

        $message = (new BrandedMailMessage($this))
            ->subject($una !== null
                ? (string) __('emails.order_expired_without_payment.subject', ['day' => DisplayTime::dayAndMonth($una->slot->date), 'time' => MailReservation::hora($una)])
                : (string) __('emails.order_expired_without_payment.subject_varias', ['code' => (string) $this->order->code]))
            ->hero('emails.order_expired_without_payment', 'warn', [], [], $una !== null ? 'headline' : 'headline_varias')
            ->links(['contacto' => self::contacto()])
            ->paragraphs((string) __('emails.order_expired_without_payment.body'))
            ->button((string) __('emails.order_expired_without_payment.action'), route('home'));

        if (self::contacto() !== null) {
            $message->small((string) __('emails.order_expired_without_payment.contact', ['code' => (string) $this->order->code]));
        }

        return $message;
    }

    /** Por dónde ESCRIBIR al parque: su WhatsApp; si no tiene, su correo; si tampoco, su teléfono. `null` sin ninguno. */
    private static function contacto(): ?string
    {
        $pie = MailPie::current();

        return match (true) {
            $pie->whatsapp !== null => 'https://wa.me/'.$pie->whatsapp,
            $pie->correo !== null => 'mailto:'.$pie->correo,
            $pie->tel !== null => 'tel:'.$pie->tel,
            default => null,
        };
    }
}
