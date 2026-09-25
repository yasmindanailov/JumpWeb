<?php

namespace App\Notifications;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mime\Email;

/**
 * **El correo de la encuesta del día siguiente** (`docs/specs/encuestas.md` §4.3, T3; `DECISIONES #740`).
 *
 * Es un correo de SERVICIO (`[DECIDIDO owner]` §7·4): va a todo el que visitó ayer, y por eso **no lleva ni una
 * línea comercial** —ni oferta, ni producto, ni enlace a comprar: eso lo convertiría en comunicación comercial y
 * exigiría el opt-in de marketing—. Lo que sí lleva, siempre: la baja de un toque al pie y en la cabecera
 * `List-Unsubscribe`, para el gestor de correo que la enseña como botón.
 *
 * ⚠️ El botón abre la página por su TOKEN de 40 caracteres, que es la credencial entera (como la invitación):
 * sin sesión, sin cookie de medición, y un solo 404 para lo inventado, lo contestado y lo apagado.
 * ⚠️ La primera línea es la `intro` de la encuesta si el panel la escribió; si no, una frase de la casa.
 * ⚠️ Lo manda `surveys:send-external`, con la fila ya escrita: la fila es la marca de «mandado».
 */
class SurveyInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Survey $survey,
        public readonly SurveyResponse $response,
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
        $park = (string) Setting::businessName();
        $token = (string) $this->response->token;
        $optOut = route('survey.optout', ['token' => $token]);
        // La `intro` del panel SOLO si existe en el idioma del cliente: `displayIntro()` cae al español, y una línea
        // en español dentro de un correo en inglés es peor que la frase de la casa en inglés.
        $intro = trim((string) ($this->survey->intro[app()->getLocale()] ?? ''));

        $mail = (new BrandedMailMessage($this))
            ->subject(__('surveys.mail.subject', ['park' => $park]))
            ->hero('surveys.mail', 'info')
            ->line($intro !== '' ? $intro : __('surveys.mail.line1', ['park' => $park]))
            ->line(__('surveys.mail.line2'))
            ->action(__('surveys.mail.action'), route('survey.show', ['token' => $token]))
            ->outro(new HtmlString('<a href="'.e($optOut).'">'.e(__('surveys.mail.optout')).'</a>'));

        // La baja también donde el gestor de correo la pinta como botón (RFC 2369). Abre la página con UN botón:
        // un enlace que escribiera al abrirse lo pulsarían los escáneres de enlaces de los propios gestores.
        $mail->withSymfonyMessage(static function (Email $message) use ($optOut): void {
            $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$optOut.'>');
        });

        return $mail;
    }
}
