<?php

namespace App\Notifications;

use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mime\Email;

/**
 * **«El cumple se acerca»** (`docs/specs/avisame-de-fechas.md` §4.3, `[DECIDIDO owner]` `#750`; el correo nº 12 del mockup
 * de la instancia): unas semanas antes del cumpleaños de un niño, a quien marcó «Avísame de fechas» al firmar su
 * autorización. Es COMERCIAL y va solo con esa casilla marcada.
 *
 * ⚠️ Lleva siempre por qué lo recibe y la baja de un toque al pie, y la baja también en `List-Unsubscribe` (LSSI art.
 * 22.1). La baja abre una página con UN botón: un enlace que escribiera al abrirse lo pulsarían los escáneres de enlaces.
 * ⚠️ El precio «desde» llega hecho del catálogo (el pack de cumpleaños más barato, `PartyCards::cheapest()`); sin él, la
 * frase del precio no sale. Lo demás no lleva ni un dato de PlayJump.
 * ⚠️ Va a una RUTA de correo, no a una cuenta (quien firma casi nunca la tiene), con el idioma en que marcó la casilla.
 */
class BirthdayComingNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $reminderId,
        public readonly string $nombre,
        public readonly int $edad,
        public readonly string $mes,
        public readonly ?string $desde,
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
        $baja = URL::signedRoute('birthday-reminder.unsubscribe', ['reminder' => $this->reminderId]);
        $datos = ['nombre' => $this->nombre, 'edad' => $this->edad, 'mes' => $this->mes];

        // El asunto con sus datos EN LÍNEA: el censo de la bandeja (`MailInboxLineTest`) los busca dentro de su `__()`.
        $mail = (new BrandedMailMessage($this))
            ->subject(__('fiesta.cumple_mail.subject', ['nombre' => $this->nombre, 'edad' => $this->edad, 'mes' => $this->mes]))
            ->hero('fiesta.cumple_mail', 'info', [], $datos)
            ->line(__('fiesta.cumple_mail.linea'));
        if ($this->desde !== null) {
            $mail->line(__('fiesta.cumple_mail.desde', ['precio' => $this->desde]));
        }
        $mail->line(__('fiesta.cumple_mail.pronto'))
            ->action(__('fiesta.cumple_mail.boton'), route('cumpleanos'))
            ->outro(new HtmlString(e(__('fiesta.cumple_mail.porque', ['nombre' => $this->nombre])).' <a href="'.e($baja).'">'.e(__('fiesta.cumple_mail.baja')).'</a>'));

        $mail->withSymfonyMessage(static function (Email $message) use ($baja): void {
            $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$baja.'>');
        });

        return $mail;
    }
}
