<?php

namespace App\Notifications;

use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\ChoosesRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Auditoría 2026-05-26 (2ª ronda, hallazgo C-07) — cierre del loop anti-takeover.
 *
 * Cuando el cambio de email se CONFIRMA con éxito desde el nuevo buzón, se notifica al
 * email VIEJO informando del cambio (el viejo deja de ser el email de la cuenta). Si la
 * víctima de un takeover ve este mensaje, sabe inmediatamente que su cuenta fue
 * comprometida y puede contactar con soporte antes de perder más.
 */
class EmailChangeCompleted extends Notification implements ChoosesRecipient, ShouldQueue
{
    use Queueable;

    /**
     * ⚠️ `$previousEmail` viaja DENTRO de la notificación (A2b, `#856`): va por la cola, y al volver de ella el titular se
     * recarga de la base, donde ya tiene el correo NUEVO. Un atributo puesto en memoria antes de encolar no sobrevive.
     */
    public function __construct(public string $newEmailMasked, public ?string $previousEmail = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * El destino es el email PREVIO (el que se acaba de sustituir). Lo lee `User::routeNotificationForMail()`: el método
     * que había aquí antes, con otro nombre, no lo llamaba nadie y el aviso salía al correo NUEVO (medido, `#856`).
     */
    public function recipientFor(object $notifiable): string
    {
        return (string) ($this->previousEmail ?? $notifiable->email);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $park = (string) Setting::businessName();

        return (new BrandedMailMessage($this))
            ->subject(__('emails.email_change_completed.subject'))
            ->hero('emails.email_change_completed', 'ok')
            ->line(__('emails.email_change_completed.intro', ['new' => $this->newEmailMasked, 'park' => $park]))
            ->line(__('emails.email_change_completed.what_means'))
            ->line(__('emails.email_change_completed.it_was_not_me'));
    }
}
