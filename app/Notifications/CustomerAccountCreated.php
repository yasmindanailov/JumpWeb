<?php

namespace App\Notifications;

use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bienvenida a una cuenta creada en el MOSTRADOR: el operador dio de alta la cuenta del cliente desde el
 * back-office (Fase 7.5, #181). La cuenta ya está verificada; este correo le dice cómo entrar —su correo y un
 * código de un solo uso, como todos (`#848`)— para ver sus pedidos. Se envía a un User real (ya creado), en
 * español (alta presencial).
 *
 * ⚠️ **Sin ningún secreto** (A5c de `docs/specs/acceso-con-codigo.md` §4.12, `#869`): hasta la A5 llevaba una
 * contraseña temporal EN CLARO, que viajaba por la cola y quedaba en la copia del registro de correos (`#794`).
 */
class CustomerAccountCreated extends Notification implements ShouldQueue
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
        $park = (string) Setting::businessName();

        return (new BrandedMailMessage($this))
            ->subject(__('emails.customer_account_created.subject'))
            ->hero('emails.customer_account_created', 'ok')
            ->line(__('emails.customer_account_created.intro', ['park' => $park]))
            ->line('**'.__('emails.customer_account_created.email_label').':** '.$notifiable->email)
            ->line(__('emails.customer_account_created.how_to_enter'))
            ->action(__('emails.customer_account_created.action'), route('login'))
            ->line(__('emails.customer_account_created.ignore'));
    }
}
