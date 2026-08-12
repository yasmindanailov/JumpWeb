<?php

namespace App\Notifications;

use App\Domain\Platform\Models\Setting;
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
        // CTA al modal de login (decisión #112, 2026-05-28): antes apuntaba a la home y
        // dejaba al usuario buscando dónde entrar. `route('login')` carga la home con el
        // modal de login ya abierto (HomeController inyecta `authModal='login'`). Si el
        // usuario abandonó una compra a mitad, la cesta sigue en sesión: al iniciar
        // sesión vuelve directamente al sidebar (#75/#79).
        $park = (string) Setting::value('business.name', config('app.name'));

        return (new MailMessage)
            ->subject(__('account.exists_mail.subject', ['park' => $park]))
            ->greeting(__('account.exists_mail.greeting'))
            ->line(__('account.exists_mail.line1'))
            ->action(__('account.exists_mail.action'), route('login'))
            ->line(__('account.exists_mail.line2'));
    }
}
