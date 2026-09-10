<?php

namespace App\Notifications;

use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bienvenida con credenciales: el operador dio de alta la cuenta del cliente desde el
 * back-office (Fase 7.5, #181). La cuenta ya está verificada; este correo le entrega su
 * **contraseña temporal** para que pueda iniciar sesión y ver sus pedidos, y le recomienda
 * cambiarla. Se envía a un User real (ya creado), en español (alta presencial).
 */
class CustomerAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $temporaryPassword) {}

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

        return (new BrandedMailMessage)
            ->subject(__('emails.customer_account_created.subject', ['park' => $park]))
            ->hero('emails.customer_account_created', 'ok')
            ->line(__('emails.customer_account_created.intro', ['park' => $park]))
            ->line('**'.__('emails.customer_account_created.email_label').':** '.$notifiable->email)
            ->line('**'.__('emails.customer_account_created.password_label').':** '.$this->temporaryPassword)
            ->action(__('emails.customer_account_created.action'), route('login'))
            ->line(__('emails.customer_account_created.recommend_change'))
            ->line(__('emails.customer_account_created.ignore'));
    }
}
