<?php

namespace App\Notifications;

use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Se envía cuando alguien intenta registrarse con un email que YA tiene cuenta.
 * Evita revelar en el formulario si un email existe (anti-enumeración, SEGURIDAD §2):
 * el visitante ve el mismo mensaje genérico y, si es el titular, recibe este aviso.
 */
class AccountAlreadyExists extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // CTA a la pantalla de identificarse (decisión #112, 2026-05-28): antes apuntaba a la
        // home y dejaba al titular buscando dónde entrar.
        // ⚠️ **Adónde lleva `route('login')` cambió el 2026-08-23** (`DECISIONES #122`) y el
        // destino sigue siendo el correcto: ya no abre un modal, sino que sirve la home y el
        // CAJÓN se abre solo en su zona de entrar — es una PUERTA (`Http\Sidebar\AccountDoor`).
        // La ruta sobrevive por eso y porque es el destino del middleware `auth` de Laravel.
        // ⚠️ Que este CTA apunte aquí lo fija `Api\V1\AuthRegistrationTest`, y era **guardián
        // único** en un test del modal hasta que la auditoría de A8 lo re-apuntó.
        $park = (string) Setting::businessName();

        return (new BrandedMailMessage($this))
            ->subject(__('account.exists_mail.subject'))
            ->hero('account.exists_mail', 'info')
            ->line(__('account.exists_mail.line1'))
            ->action(__('account.exists_mail.action'), route('login'))
            ->line(__('account.exists_mail.line2'));
    }
}
