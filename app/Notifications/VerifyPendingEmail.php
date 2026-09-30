<?php

namespace App\Notifications;

use App\Domain\Identity\Services\LoginCodes;
use App\Domain\Platform\Contracts\HidesSecretsInCopy;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\ChoosesRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Auditoría 2026-05-26 (hallazgo A) — Mi cuenta · cambio de email.
 * Se envía al NUEVO email cuando el usuario solicita cambiar el suyo. El email viejo NO se
 * sobrescribe hasta que el cliente hace click aquí. El enlace va firmado y caduca (60 min);
 * el id+hash identifican al usuario y al pending_email exacto (anti-tampering).
 *
 * ▶ **Desde la A2b (`#856`) lleva un CÓDIGO** de seis cifras que se escribe en la pantalla (`POST /me/pending-email/
 * confirm`): con él, el buzón nuevo se prueba en el dispositivo donde se pidió el cambio. El ENLACE sigue mientras la isla
 * y el cajón no pinten el código (A3/A4) y se va en la A5. Sale tras la respuesta (`CodeMail`) y tapado en la copia.
 *
 * ⚠️⚠️ **Y ahora sale al buzón NUEVO de verdad** ({@see ChoosesRecipient}): hasta el 30-09 llegaba al VIEJO (medido).
 */
class VerifyPendingEmail extends Notification implements ChoosesRecipient, HidesSecretsInCopy, ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $code  el código del correo nuevo; sin él, el correo de siempre (solo el enlace)
     */
    public function __construct(private readonly ?string $code = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** El destinatario es el `pending_email`, no el email actual (que sigue siendo el válido). */
    public function recipientFor(object $notifiable): string
    {
        return (string) $notifiable->pending_email;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'account.email.confirm',
            now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1((string) $notifiable->pending_email)],
        );

        if ($this->code === null) {
            return (new BrandedMailMessage($this))
                ->subject(__('emails.verify_pending_email.subject'))
                ->hero('emails.verify_pending_email', 'warn')
                ->line(__('emails.verify_pending_email.intro'))
                ->action(__('emails.verify_pending_email.action'), $url)
                ->line(__('emails.verify_pending_email.expires'))
                ->line(__('emails.verify_pending_email.ignore'));
        }

        $code = $this->shown();

        return (new BrandedMailMessage($this))
            ->subject(__('emails.verify_pending_email_code.subject', ['code' => $code]))
            ->hero('emails.verify_pending_email_code', 'warn', [], ['code' => $code])
            ->line(__('emails.verify_pending_email_code.intro'))
            ->line(__('emails.verify_pending_email_code.validity', ['minutes' => LoginCodes::TTL_MINUTES]))
            // El botón, mientras la isla y el cajón no tengan dónde escribir el código (A3/A4); se va en la A5.
            ->action(__('emails.verify_pending_email.action'), $url)
            ->line(__('emails.verify_pending_email.ignore'));
    }

    /** @return list<string> */
    public function secretsInCopy(): array
    {
        return $this->code === null ? [] : [$this->shown()];
    }

    /** «482 913»: el código tal como se enseña. */
    private function shown(): string
    {
        return substr((string) $this->code, 0, 3).' '.substr((string) $this->code, 3);
    }
}
