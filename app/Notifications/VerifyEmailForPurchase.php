<?php

namespace App\Notifications;

use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Fase 5 (Pieza 2, compra) — Email de verificación CON contexto de compra: el cliente nuevo
 * verifica su cuenta para CONFIRMAR su reserva (provisional, que ya retiene su plaza). Reutiliza
 * la URL firmada del verificador estándar (misma ruta `verification.verify`), solo cambia el texto.
 * Se envía en el idioma del usuario (User implementa HasLocalePreference). Matiza la #46 para la compra.
 */
class VerifyEmailForPurchase extends VerifyEmail implements ShouldQueue
{
    public function __construct(public string $orderCode) {}

    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new BrandedMailMessage)
            ->subject(__('emails.verify_purchase.subject', ['code' => $this->orderCode]))
            ->hero('emails.verify_purchase', 'warn')
            ->line(__('emails.verify_purchase.intro', ['code' => $this->orderCode]))
            ->action(__('emails.verify_purchase.action'), $url)
            ->line(__('emails.verify_purchase.hold_note'))
            ->line(__('emails.verify_purchase.outro'));
    }
}
