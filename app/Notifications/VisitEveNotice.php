<?php

namespace App\Notifications;

use App\Domain\Booking\Contracts\PendingWork;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailSlip;
use App\Domain\Platform\Services\Money;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **EL AVISO DE LA VÍSPERA** (T7·2b, `specs/celebracion-e-invitacion.md` §4.9 y §10.17).
 *
 * Al titular, la tarde de antes, y **solo si queda algo por hacer** ({@see PendingWork::any()}): el
 * comando no lo construye siquiera cuando no hay nada. Un correo que dice «no tienes que hacer nada»
 * enseña a ignorar los correos, y éste llega justo cuando aún se puede arreglar.
 *
 * ⚠️⚠️ **Dice POR QUÉ no es grave**, y eso no es cortesía: la cifra sola («12 de 20») se lee como un
 * reproche a las nueve de la noche del día antes de la fiesta de tu hijo. Lo que falta se puede
 * resolver en el mostrador —un adulto que acompaña entra igual—, y decirlo es la diferencia entre un
 * recordatorio y un susto.
 *
 * ⚠️ **No pide dinero por web.** El saldo que trae es el del parque ({@see PendingWork}), y el lector
 * ya descarta `pay_online` por eso mismo.
 *
 * `ShouldQueue` por `PAY-14`, como el resto: el envío no bloquea al comando.
 */
class VisitEveNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrderItem $reservation,
        public PendingWork $pending,
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
        $code = (string) $this->reservation->order->code;

        $message = (new BrandedMailMessage)
            // ❗ El dato DELANTE (`#506`): en el corte de una lista de móvil tiene que entrar el
            // código, no la instrucción.
            ->subject(__('emails.visit_eve.subject', ['code' => $code]))
            ->line(__('emails.visit_eve.intro'));

        // Cada cosa en su línea y solo si falta: el correo de una reserva a la que solo le falta
        // pagar no habla de fichas, y el de una que las tiene todas no las nombra.
        if ($this->pending->guestsMissing() > 0) {
            $message->line(__('emails.visit_eve.guests', [
                'done' => $this->pending->guestsDone,
                'total' => $this->pending->guestsTotal,
            ]));
        }

        if ($this->pending->repliesToReview > 0) {
            $message->line(trans_choice('emails.visit_eve.replies', $this->pending->repliesToReview, [
                'count' => $this->pending->repliesToReview,
            ]));
        }

        if ($this->pending->minorsUnresolved > 0) {
            $message->line(trans_choice('emails.visit_eve.minors', $this->pending->minorsUnresolved, [
                'count' => $this->pending->minorsUnresolved,
            ]));
        }

        $message->hero('emails.visit_eve', 'warn', EmailSlip::forItem($this->reservation));

        // ⚠️ El saldo va en el AVISO y no como una línea más: es DINERO y dice dónde se paga, el
        // mismo trato que los extras del post-form (`#503`). Y va **después** de las cifras aunque
        // se escriba aquí: el molde pinta el aviso al final (su propio docblock lo dice).
        if ($this->pending->balanceAtParkCents > 0) {
            $message->notice(
                __('emails.visit_eve.balance_title'),
                __('emails.visit_eve.balance', [
                    'amount' => Money::format($this->pending->balanceAtParkCents, (string) ($this->reservation->order->currency ?: 'EUR')),
                ]),
            );
        }

        return $message
            // ❗❗ **La frase que quita el susto**, y va junto al botón, que es donde se decide si se
            // hace algo esta noche o mañana por la mañana.
            ->line(__('emails.visit_eve.not_serious'))
            ->action(
                __('emails.visit_eve.action'),
                // Fuente ÚNICA del enlace firmado, la misma que el correo del post-form.
                $this->reservation->guestFormSignedUrl(),
            )
            ->line(__('emails.visit_eve.outro'));
    }
}
