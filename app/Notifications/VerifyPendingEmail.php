<?php

namespace App\Notifications;

use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
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
 */
class VerifyPendingEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** El destinatario es el `pending_email`, no el email actual (que sigue siendo el válido). */
    public function routeNotificationForMail(object $notifiable): string
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

        $park = (string) Setting::businessName();

        return (new BrandedMailMessage)
            ->subject(__('emails.verify_pending_email.subject', ['park' => $park]))
            ->hero('emails.verify_pending_email', 'warn')
            ->line(__('emails.verify_pending_email.intro'))
            ->action(__('emails.verify_pending_email.action'), $url)
            ->line(__('emails.verify_pending_email.expires'))
            ->line(__('emails.verify_pending_email.ignore'));
    }
}
