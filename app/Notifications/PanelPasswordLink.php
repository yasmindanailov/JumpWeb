<?php

namespace App\Notifications;

use App\Domain\Platform\Contracts\HidesSecretsInCopy;
use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * **EL ENLACE PARA CREAR LA CONTRASEÑA DEL PANEL** (A5a de `docs/specs/acceso-con-codigo.md` §4.12, `DECISIONES #870`). Lo
 * envía un administrador desde la ficha de una cuenta del panel (`Identity\Services\PanelPasswordLinks`); nadie lo pide
 * por su cuenta. Abre la página del PANEL, bajo su dirección (`App\Filament\Auth\PanelPassword`), que solo acepta
 * cuentas del panel.
 *
 * El token es el del broker (`password_reset_tokens`); el enlace va FIRMADO, como el de Filament. Y el enlace es una
 * CREDENCIAL mientras vive: el registro de correos lo tapa en su copia ({@see HidesSecretsInCopy}).
 *
 * ⚠️ Va al EQUIPO, no a un cliente: sin UTM ni marca de clic (`EmailUtm::NOT_TO_CUSTOMERS`) y fuera de los textos
 * editables del panel. `ShouldQueue` por `PAY-14`.
 */
class PanelPasswordLink extends Notification implements HidesSecretsInCopy, ShouldQueue
{
    use Queueable;

    /** La ruta de la página, en el panel (`AdminPanelProvider`, `->routes()`). */
    public const ROUTE = 'filament.admin.auth.panel-password';

    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new BrandedMailMessage($this))
            ->subject(__('emails.panel_password_link.subject'))
            ->hero('emails.panel_password_link', 'warn')
            ->line(__('emails.panel_password_link.intro', ['park' => (string) Setting::businessName()]))
            ->action(__('emails.panel_password_link.action'), $this->url($notifiable))
            ->line(__('emails.panel_password_link.expires', ['minutes' => self::minutes()]))
            ->line(__('emails.panel_password_link.ignore'));
    }

    /** @return list<string> */
    public function secretsInCopy(): array
    {
        return [$this->token];
    }

    /** Cuántos minutos vale el token: los del broker de contraseñas, no un número escrito aquí. */
    public static function minutes(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
    }

    /** La página del panel con el correo y el token, firmada (la caducidad la impone el broker). */
    private function url(object $notifiable): string
    {
        return URL::signedRoute(self::ROUTE, [
            'email' => $notifiable->getEmailForPasswordReset(),
            'token' => $this->token,
        ]);
    }
}
