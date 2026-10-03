<?php

namespace App\Notifications;

use App\Domain\Identity\Models\BirthdayReminder;
use App\Domain\Identity\Models\User;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MarketingMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * **«El cumple se acerca»** (`docs/specs/avisame-de-fechas.md` §4.3, `[DECIDIDO owner]` `#750`; el correo nº 12 del diseño):
 * unas semanas antes del cumpleaños de un niño. Es COMERCIAL y sale a DOS públicos, cada uno con su porqué y su baja:
 *  · a quien marcó «Avísame de fechas» al firmar su autorización (`$reminderId`): a una RUTA de correo, no a una cuenta
 *    (quien firma casi nunca la tiene), con la baja de esa casilla;
 *  · desde la C1a (`specs/correos-rediseno.md` §4.4, `#920`), a una CUENTA con «novedades» por un menor que declaró
 *    (`$reminderId = null`), con la baja de «novedades» ({@see MarketingMail}) y el consentimiento releído al salir
 *    (`User::canReceiveMarketing()`).
 *
 * Como su diseño (la C1a): sin chapa; el texto frase a frase, el precio en negrita y «mira los días libres» enlazado; el botón;
 * y el pie comercial (`BrandedMailMessage::commercial()`), también en `List-Unsubscribe` (LSSI art. 22.1). La baja abre una
 * página con UN botón: un enlace que escribiera al abrirse lo pulsarían los escáneres de enlaces.
 * ⚠️ El precio «desde» llega hecho del catálogo (el pack de cumpleaños más barato, `PartyCards::cheapest()`); sin él, la
 * frase del precio no sale. Lo demás no lleva ni un dato de PlayJump (`#920`: solo lo del catálogo).
 */
class BirthdayComingNotice extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int|null  $reminderId  la fila de «Avísame de fechas» de la que sale, o `null` si va a una cuenta con «novedades»
     */
    public function __construct(
        public readonly ?int $reminderId,
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

    /**
     * El consentimiento, RELEÍDO cuando el correo sale de la cola (`#920`, `#921`): entre el comando y el envío pasa un rato, y
     * quien se dio de baja en él no lo recibe. El de «Avísame de fechas», si su casilla SIGUE viva (ni de baja ni borrada a
     * petición desde el panel); el de una cuenta, si sigue con «novedades».
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($this->reminderId !== null) {
            return BirthdayReminder::query()->whereKey($this->reminderId)->whereNull('revoked_at')->exists();
        }

        return $notifiable instanceof User && $notifiable->canReceiveMarketing();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $datos = ['nombre' => $this->nombre, 'edad' => $this->edad, 'mes' => $this->mes];

        // El asunto con sus datos EN LÍNEA: el censo de la bandeja (`MailInboxLineTest`) los busca dentro de su `__()`.
        $mail = (new BrandedMailMessage($this))
            ->subject(__('fiesta.cumple_mail.subject', ['nombre' => $this->nombre, 'edad' => $this->edad, 'mes' => $this->mes]))
            ->hero('fiesta.cumple_mail', 'info', [], $datos)
            ->links(['dias' => route('cumpleanos')])
            ->paragraphs(...array_values(array_filter([
                (string) __('fiesta.cumple_mail.linea'),
                $this->desde !== null ? (string) __('fiesta.cumple_mail.desde', ['precio' => $this->desde]) : null,
                (string) __('fiesta.cumple_mail.pronto'),
            ])))
            ->button((string) __('fiesta.cumple_mail.boton'), route('cumpleanos'));

        if ($this->reminderId !== null) {
            return $mail->commercial(
                (string) __('fiesta.cumple_mail.porque', ['nombre' => $this->nombre]),
                URL::signedRoute('birthday-reminder.unsubscribe', ['reminder' => $this->reminderId]),
            );
        }
        if (! $notifiable instanceof User) {
            throw new \LogicException('«El cumple se acerca» por «novedades» va a una cuenta: su baja es la de esa cuenta.');
        }

        return MarketingMail::footer($mail, $notifiable);
    }
}
