<?php

namespace App\Notifications;

use App\Domain\Booking\Contracts\PendingWork;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Services\Money;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **«UN REPASO ANTES DE MAÑANA»** — el 4 del diseño, la víspera de una FIESTA (T7·2b de `specs/celebracion-e-invitacion.md`
 * §4.9; desde la R2d, el diseño de `specs/correos-rediseno.md` §4.3). Al titular, la tarde de antes, y **solo si queda algo
 * por hacer** ({@see PendingWork::any()}, `#714`): un correo que dice «no tienes que hacer nada» enseña a ignorar los correos.
 * Las reservas que no son una fiesta reciben el 3 (`VisitReminderNotice`), siempre.
 *
 * En el orden del diseño: «Lo que queda» —solo lo que falta: las fichas de los invitados, las respuestas por repasar, las
 * autorizaciones, el descargo de quien cumple y lo que se paga en el parque—, la frase que quita el susto, «Repasar la
 * fiesta» (la lista, con su enlace FIRMADO) y el QR en claro: el trabajo del correo es el botón.
 *
 * ⚠️⚠️ **Dice POR QUÉ no es grave**, y eso no es cortesía: la cifra sola («12 de 20») se lee como un reproche a las nueve de la
 * noche del día antes de la fiesta de tu hijo. Lo que falta se resuelve en el mostrador.
 * ⚠️ **No pide dinero por web.** El saldo que trae es el del parque ({@see PendingWork}). Y el QR lleva SUS textos
 * (`emails.visit_eve.qr_*`): se editan en la página de este correo.
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
        $r = $this->reservation;
        $hora = MailReservation::hora($r);
        // Quien cumple: su ficha de la lista si ya la tiene y, si no, lo que se escribió al reservar (como la confirmación).
        $quien = trim((string) ($r->honoreeName() ?? $r->celebrantName()));

        $message = (new BrandedMailMessage($this))
            ->subject($quien !== ''
                ? (string) __('emails.visit_eve.subject_nombre', ['time' => $hora, 'name' => $quien])
                : (string) __('emails.visit_eve.subject', ['time' => $hora, 'product' => $r->displayProductName()]))
            ->hero('emails.visit_eve', 'warn')
            ->checklist((string) __('emails.visit_eve.list_title'), $this->loQueQueda($r), raya: false)
            ->small((string) __('emails.visit_eve.not_serious'))
            ->button((string) __('emails.visit_eve.action'), $r->guestFormSignedUrl());

        $qr = (new MailReservation($r->order))->qr(
            (string) __('emails.visit_eve.qr_title'),
            (string) __('emails.visit_eve.qr_dictate_label'),
            (string) __('emails.visit_eve.action_qr'),
        );
        if ($qr !== null) {
            $message->qr(...$qr, secundario: true)->attachData($qr['png'], 'carne-qr.png', ['mime' => 'image/png']);
        }

        return $message;
    }

    /**
     * «Lo que queda», cada línea SOLO si falta: un «2 de 2» es ruido que enseña a no leer.
     *
     * @return list<array{texto: string, icono: string}>
     */
    private function loQueQueda(OrderItem $r): array
    {
        $p = $this->pending;
        $lineas = [];

        if ($p->guestsMissing() > 0) {
            $lineas[] = ['texto' => (string) __('emails.visit_eve.guests', ['done' => $p->guestsDone, 'total' => $p->guestsTotal]), 'icono' => 'users'];
        }
        if ($p->repliesToReview > 0) {
            $lineas[] = ['texto' => trans_choice('emails.visit_eve.replies', $p->repliesToReview, ['count' => $p->repliesToReview]), 'icono' => 'message-circle'];
        }
        if ($p->minorsUnresolved > 0) {
            $total = max((int) $r->quantity, $p->minorsUnresolved);
            $lineas[] = ['texto' => (string) __('emails.visit_eve.guardians', ['done' => $total - $p->minorsUnresolved, 'total' => $total]), 'icono' => 'pen-line'];
        }
        if ($p->honoreeWaiverMissing) {
            $nombre = (string) $r->honoreeName();
            $lineas[] = [
                'texto' => $nombre !== '' ? (string) __('emails.visit_eve.honoree', ['name' => $nombre]) : (string) __('emails.visit_eve.honoree_unnamed'),
                'icono' => 'pen-line',
            ];
        }
        if ($p->balanceAtParkCents > 0) {
            $lineas[] = ['texto' => (string) __('emails.visit_eve.balance', [
                'amount' => Money::showcaseWithSymbol($p->balanceAtParkCents, (string) ($r->order?->currency ?: 'EUR')),
            ]), 'icono' => 'banknote'];
        }

        return $lineas;
    }
}
