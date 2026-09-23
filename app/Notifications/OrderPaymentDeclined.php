<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Services\EmailSlip;
use App\Domain\Payments\Services\RedsysResponseCode;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Audit #114 (2026-05-28, G1) — Email cuando un pago Redsys es DENEGADO.
 *
 * Trigger: `RedsysReturnHandler` recibe `Ds_Response ≥ 0100` (vuelta firmada del navegador
 * o notificación on-line) sobre un Payment todavía pending. Se envía UNA SOLA VEZ por
 * Payment (idempotencia simétrica al `STATUS_PAID`, ver `RedsysReturnHandler` §rama Denied).
 *
 * Por qué este email:
 *  - El cliente puede haber cerrado la pestaña antes de ver el paso 10 ("pago denegado"):
 *    sin email no sabe qué pasó. Pierde confianza y duplica intentos.
 *  - El motivo (`Ds_Response`) se traduce a un texto humano vía `RedsysResponseCode`
 *    (manual §4, Anexo 2) — el cliente sabe si tiene que cambiar tarjeta, llamar al banco
 *    o simplemente reintentar.
 *
 * No bloqueante: se envía FUERA de la transacción del handler (un fallo SMTP no debe
 * deshacer una transición de estado Payment paid).
 *
 * Texto en el idioma del usuario (User implementa HasLocalePreference).
 */
class OrderPaymentDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public ?string $dsResponse = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reasonText = RedsysResponseCode::reasonText($this->dsResponse);

        return (new BrandedMailMessage($this))
            ->subject(__('emails.order_declined.subject', ['code' => $this->order->code]))
            ->hero('emails.order_declined', 'err', EmailSlip::forOrder($this->order))
            ->line(__('emails.order_declined.intro', ['code' => $this->order->code]))
            ->line(__('emails.order_declined.no_charge'))
            // EL MOTIVO en su propio aviso (`#503`): es lo que el cliente busca al abrir este
            // correo, y como frase suelta en medio del cuerpo se leía como una más.
            ->notice(__('emails.order_declined.notice_title'), $reasonText, 'err')
            ->action(__('emails.order_declined.action'), route('account.orders'))
            // ❗ UNO DE LOS DOS ÚNICOS CORREOS QUE VENDEN (`#503`, el mapa del naranja): su botón
            // va en relleno de ACCIÓN y los otros diecinueve en tinta. `level` es la única palanca
            // que Laravel da aquí, porque `action()` no acepta color. Lo vigila `MailMoldTest::test_exactly_two_mails_carry_the_selling_button`.
            ->level('sell')
            ->line(__('emails.order_declined.contact'));
    }
}
