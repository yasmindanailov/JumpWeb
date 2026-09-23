<?php

namespace App\Notifications;

use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
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
class EmailChangeCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $newEmailMasked) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Forzamos el destino al email PREVIO (el que se acaba de sustituir). */
    public function routeNotificationForMail(object $notifiable): string
    {
        return (string) $notifiable->previous_email;
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
