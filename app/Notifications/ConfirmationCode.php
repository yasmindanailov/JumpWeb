<?php

namespace App\Notifications;

use App\Domain\Identity\Services\LoginCodes;
use App\Domain\Platform\Contracts\HidesSecretsInCopy;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **EL CÓDIGO PARA CONFIRMAR una acción sensible** (A2a de `docs/specs/acceso-con-codigo.md` §4.9, `DECISIONES #855`): borrar
 * la cuenta, cambiar el correo, desvincular Google o cerrar las demás sesiones. Va al correo de la CUENTA y dice PARA QUÉ
 * es: solo se pide con la sesión abierta, así que quien lo recibe sin haberlo pedido sabe que alguien la tiene.
 *
 * Como {@see LoginCode}: sin enlace, el código en el asunto y como titular, tapado en la copia del registro de correos, y
 * `ShouldQueue` aunque sale tras la respuesta (`CodeMail`, `PAY-14`).
 */
class ConfirmationCode extends Notification implements HidesSecretsInCopy, ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $action  una de `AccountCredentials::CONFIRM_ACTIONS`
     */
    public function __construct(
        private readonly string $code,
        private readonly string $action,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = LoginCodes::shown($this->code);

        return (new BrandedMailMessage($this))
            ->subject(__('emails.confirmation_code.subject', ['code' => $code]))
            ->hero('emails.confirmation_code', 'warn', [], ['code' => $code])
            ->line(__('emails.confirmation_code.for', ['action' => __('emails.confirmation_code.actions.'.$this->action)]))
            ->line(__('emails.confirmation_code.validity', ['minutes' => LoginCodes::TTL_MINUTES]))
            ->line(__('emails.confirmation_code.ignore'));
    }

    /** @return list<string> */
    public function secretsInCopy(): array
    {
        return [LoginCodes::shown($this->code)];
    }
}
