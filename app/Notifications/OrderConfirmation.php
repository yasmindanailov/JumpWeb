<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **LA RESERVA HECHA** — el correo al confirmarse el pago (capa 5.5c, `#104`): solo tras la autorización del banco
 * (`RedsysReturnHandler`, `Ds_Response` 0000–0099) o al crear un pedido en el panel (`ManualOrderFulfiller`), y se REENVÍA
 * desde la ficha del pedido. Si el cliente cierra la pestaña, es su único rastro fuera de Mi cuenta.
 *
 * ▶ **Desde la R2b** (`specs/correos-rediseno.md` §4.3) es el diseño, con sus TRES CARAS (`MailReservation::cara()`):
 *  · **el 1, unas entradas**: «¡Nos vemos el sábado 26!», el resguardo, el QR (el principal), «Antes de venir» —quién firma
 *    (`#875`), lo comprado, lo del producto y la hora— y «Si cambian los planes»; responder llega al parque;
 *  · **el 1b, un grupo** (un pack sin lista de invitados: una excursión): lo mismo, con la señal y el resto en el resguardo;
 *  · **el 2, una fiesta**: «¡Fiesta reservada!», el resguardo con su señal, el QR (claro: el trabajo es otro) y los PASOS —el
 *    formulario de invitados y la invitación—, «Y si quieres…» y el plazo. Es UN correo (`#915`): el formulario ya no sale
 *    aparte al pagar; `GuestFormRequest` se queda para el reenvío del panel.
 *
 * ⚠️ Cada reserva del pedido, su resguardo (y, si es una fiesta, sus pasos detrás); el QR, uno, tras el primero. El asunto y
 * el titular dicen el día solo si el pedido tiene UNO (`#506`).
 * ⚠️ Al ENVIAR, no al gestionar: si al pedido le ha pasado algo después de pagarse, el LIBRO entero va detrás de los
 * resguardos (`MailReservation::libro()`, D-T3·5 de `desglose-libro.md`).
 *
 * El correo llega en el idioma del cliente (`User` implementa `HasLocalePreference`).
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
        $reserva = new MailReservation($this->order);
        $cara = $reserva->cara();
        $fiesta = $cara === MailReservation::FIESTA;
        $fecha = $this->order->singleVisitDate();
        $dia = $fecha !== null ? DisplayTime::dayInSentence($fecha) : '';

        $message = (new BrandedMailMessage($this))->subject($this->asunto($reserva, $fiesta));

        // La CABECERA de cada cara. En una fiesta, sin chapa (el diseño no la lleva) y sin el día en el titular; en las otras,
        // sin un día único, «¡Reservado!» (`headline_varias`): «Nos vemos el sábado 4» en un pedido de dos días mentiría.
        if ($fiesta) {
            $message->hero('emails.fiesta_reservada', 'ok');
        } else {
            $message->hero('emails.reservado', 'ok', [], ['day' => $dia], match (true) {
                $dia === '' => 'headline_varias',
                $cara === MailReservation::GRUPO => 'headline_grupo',
                default => 'headline',
            });
        }
        $message->links($reserva->enlaces());

        // El QR, tras el PRIMER resguardo (sin ninguno —un pedido sin franja—, tras la cabecera): uno, el del titular.
        $qr = $reserva->qr();
        if ($qr !== null && $reserva->reservas()->isEmpty()) {
            $message->qr(...$qr, secundario: $fiesta);
        }
        foreach ($reserva->reservas() as $i => $r) {
            $message->slip(...$reserva->resguardo($r));
            if ($i === 0 && $qr !== null) {
                $message->qr(...$qr, secundario: $fiesta);
            }
            if (($pasos = $reserva->pasos($r)) !== null) {
                $message->steps($pasos['titulo'], $pasos['pasos']);
            }
        }
        if ($qr !== null) {
            // Y ADJUNTO, como el diseño: el mismo QR, para guardarlo en el móvil (Fase 6 · A, `identidad-qr-puerta.md` §4.10).
            $message->attachData($qr['png'], 'carne-qr.png', ['mime' => 'image/png']);
        }

        if (($libro = $reserva->libro()) !== null) {
            $message->markup($libro);
        }

        $antes = $reserva->antesDeVenir();
        if ($antes !== []) {
            $message->checklist((string) __('emails.reserva.before_title'), $antes);
        }
        if (($extras = $reserva->extras()) !== null) {
            $message->small($extras);
        }
        if (($cambios = $reserva->cambios()) !== null) {
            $message->section((string) __('emails.reserva.changes_title'), $cambios);
        }
        if (! $fiesta) {
            $message->replies((string) __('emails.reserva.replies_label'));
        }

        return $message;
    }

    /**
     * EL ASUNTO, con el dato delante (`#506`): el día, la hora y qué —en una fiesta, de quién es el cumple si ya se sabe—. Con
     * varias reservas, el número del pedido: un día y una hora solos esconderían las otras.
     */
    private function asunto(MailReservation $reserva, bool $fiesta): string
    {
        $una = $reserva->unica();
        if ($una === null) {
            return (string) __('emails.reservado.subject_varias', ['count' => $reserva->reservas()->count(), 'code' => (string) $this->order->code]);
        }
        $cuando = DisplayTime::dayAndMonth($una->slot->date);
        $hora = MailReservation::hora($una);
        $producto = $una->displayProductName();
        // Quien cumple: su ficha de la lista si ya la tiene y, si no, lo que se escribió al reservar.
        $quien = $fiesta ? trim((string) ($una->honoreeName() ?? $una->celebrantName())) : '';

        return match (true) {
            ! $fiesta => (string) __('emails.reservado.subject', ['day' => $cuando, 'time' => $hora, 'product' => $producto]),
            $quien !== '' => (string) __('emails.fiesta_reservada.subject_nombre', ['day' => $cuando, 'time' => $hora, 'name' => $quien]),
            default => (string) __('emails.fiesta_reservada.subject', ['day' => $cuando, 'time' => $hora, 'product' => $producto]),
        };
    }
}
