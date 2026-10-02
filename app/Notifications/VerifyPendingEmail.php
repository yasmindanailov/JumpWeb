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

/**
 * Auditoría 2026-05-26 (hallazgo A) — Mi cuenta · cambio de email.
 * Se envía al NUEVO email cuando el usuario solicita cambiar el suyo. El email viejo NO se sobrescribe hasta que el
 * cliente prueba el buzón nuevo.
 *
 * ▶ **Lleva un CÓDIGO** de seis cifras (A2b, `#856`) que se escribe en la pantalla donde se pidió el cambio
 * (`POST /me/pending-email/confirm`): el buzón nuevo se prueba en ese mismo dispositivo. Desde la A5 (`#869`) es lo único
 * que lleva: el ENLACE firmado de antes se retiró. Sale tras la respuesta (`CodeMail`) y tapado en la copia.
 *
 * ⚠️⚠️ **Y sale al buzón NUEVO de verdad** ({@see ChoosesRecipient}): hasta el 30-09 llegaba al VIEJO (medido).
 */
class VerifyPendingEmail extends Notification implements ChoosesRecipient, HidesSecretsInCopy, ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $code  el código del correo nuevo (`LoginCode::PURPOSE_NEW_EMAIL`)
     */
    public function __construct(#[\SensitiveParameter] private readonly string $code) {}

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
        $code = $this->shown();

        return (new BrandedMailMessage($this))
            ->subject(__('emails.verify_pending_email_code.subject', ['code' => $code]))
            ->hero('emails.verify_pending_email_code', 'warn', [], ['code' => $code])
            ->line(__('emails.verify_pending_email_code.intro'))
            ->line(__('emails.verify_pending_email_code.validity', ['minutes' => LoginCodes::TTL_MINUTES]))
            ->line(__('emails.verify_pending_email.ignore'));
    }

    /** @return list<string> */
    public function secretsInCopy(): array
    {
        return [$this->shown()];
    }

    /** «482 913»: el código tal como se enseña. */
    private function shown(): string
    {
        return substr($this->code, 0, 3).' '.substr($this->code, 3);
    }
}
