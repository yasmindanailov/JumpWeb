<?php

namespace App\Notifications;

use App\Domain\Booking\Contracts\PendingWork;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\Money;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailPie;
use App\Notifications\Support\MailReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **«MAÑANA OS ESPERAMOS»** — el 3 del diseño (la R2d de `specs/correos-rediseno.md` §4.3): a TODA reserva que no sea una
 * fiesta, SIEMPRE (`#915`, a: hasta la R2d, solo si le quedaba algo), la víspera por la tarde o —reservada para el mismo día—
 * dos horas antes, con «Hoy» (`SendVisitEveNotices`). Su trabajo: el QR a mano, la hora, lo que hay que traer y cómo llegar.
 *
 * El cuerpo, en el orden del diseño: el QR (el principal) y una lista sin filete —la hora, lo comprado (el aviso de cada
 * complemento), lo de su producto («Antes de venir», `before_visit`), la dirección con «Cómo llegar» (solo con mapa en el
 * panel)— más lo que QUEDA de verdad: lo que se paga en el parque y las autorizaciones sin firmar. Y, si el titular aún no
 * tiene menores a su cargo y es una compra de entradas, el aviso ámbar (`#875`).
 *
 * ⚠️ El QR lleva SUS textos (`emails.manana.qr_*`): se editan en la página de este correo, no en la de la confirmación.
 */
class VisitReminderNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrderItem $reservation,
        public PendingWork $pending,
        public bool $hoy = false,
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
        $reserva = new MailReservation($r->order);
        $hora = MailReservation::hora($r);
        $producto = $r->displayProductName();
        $grupo = $r->ticketType?->isPack() === true;

        $message = (new BrandedMailMessage($this))
            ->subject($this->hoy
                ? (string) __('emails.manana.subject_hoy', ['time' => $hora, 'product' => $producto])
                : (string) __('emails.manana.subject', ['time' => $hora, 'product' => $producto]))
            ->hero('emails.manana', 'ok', [], [], $this->hoy ? 'headline_hoy' : 'headline')
            ->links([
                'mapa' => MailReservation::mapa(),
                'menores' => route('account.dependents'),
                // El enlace de las autorizaciones, el que se reparte a las familias: solo si el producto las pide.
                'autorizacion' => $r->ticketType?->guardianMode() !== TicketType::GUARDIAN_NONE ? $r->guardianAuthorizationSignedUrl() : null,
            ]);

        $qr = $reserva->qr(
            (string) __('emails.manana.qr_title'),
            (string) __('emails.manana.qr_dictate_label'),
            (string) __('emails.manana.action_qr'),
        );
        if ($qr !== null) {
            $message->qr(...$qr)->attachData($qr['png'], 'carne-qr.png', ['mime' => 'image/png']);
        }

        $message->checklist(null, $this->lineas($r, $reserva, $hora), raya: false);

        if (! $grupo && $reserva->faltanMenores()) {
            $message->callout((string) __('emails.manana.minors'));
        }

        return $message;
    }

    /**
     * La lista, en el orden del diseño (la hora, lo que traer, cómo llegar) y después lo que queda.
     *
     * @return list<array{texto: string, icono: string}>
     */
    private function lineas(OrderItem $r, MailReservation $reserva, string $hora): array
    {
        $lineas = [['texto' => (string) __('emails.manana.arrival', ['time' => $hora]), 'icono' => 'clock']];
        array_push($lineas, ...$reserva->loComprado($r), ...MailReservation::delProducto($r->ticketType));

        $direccion = MailPie::current()->direccion;
        if ($direccion !== null && MailReservation::mapa() !== null) {
            $lineas[] = ['texto' => (string) __('emails.manana.address', ['address' => $direccion]), 'icono' => 'map-pin'];
        }
        if ($this->pending->balanceAtParkCents > 0) {
            $lineas[] = ['texto' => (string) __('emails.manana.balance', [
                'amount' => Money::showcaseWithSymbol($this->pending->balanceAtParkCents, (string) ($r->order?->currency ?: 'EUR')),
            ]), 'icono' => 'banknote'];
        }
        if ($this->pending->minorsUnresolved > 0) {
            $total = max((int) $r->quantity, $this->pending->minorsUnresolved);
            $lineas[] = ['texto' => (string) __('emails.manana.guardians', [
                'done' => $total - $this->pending->minorsUnresolved,
                'total' => $total,
            ]), 'icono' => 'pen-line'];
        }

        return $lineas;
    }
}
