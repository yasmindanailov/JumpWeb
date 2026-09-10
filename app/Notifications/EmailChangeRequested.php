<?php

namespace App\Notifications;

use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Auditoría 2026-05-26 (hallazgo A) — Mi cuenta · cambio de email.
 * Aviso al EMAIL VIEJO de que alguien solicitó cambiar el email de la cuenta. Si la víctima
 * de un takeover lo ve a tiempo, sabe que algo va mal (su email original sigue siendo el
 * válido — el cambio solo se aplica si el atacante también accede al nuevo buzón y verifica).
 */
class EmailChangeRequested extends Notification implements ShouldQueue
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new BrandedMailMessage)
            ->subject(__('emails.email_change_requested.subject'))
            ->hero('emails.email_change_requested', 'warn')
            ->line(__('emails.email_change_requested.intro', ['new' => $this->newEmailMasked]))
            ->line(__('emails.email_change_requested.it_was_me'))
            ->line(__('emails.email_change_requested.it_was_not_me'));
    }
}
