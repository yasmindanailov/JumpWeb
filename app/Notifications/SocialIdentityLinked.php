<?php

namespace App\Notifications;

use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **Aviso al titular de que su cuenta se ha vinculado a una identidad externa**
 * (`docs/specs/auth-con-google.md` §5.2, contrapeso 2 · `[DECIDIDO owner]` Q3).
 *
 * ⚠️⚠️ **No es cortesía: es el contrapeso que hace defendible la vinculación automática.** Un vínculo
 * que se crea solo, no caduca y no se puede quitar sería **silencioso**, y ésa es justo la diferencia
 * que la revisión midió frente a un restablecimiento de contraseña —que la víctima nota porque su
 * contraseña deja de funcionar—. Con este correo, se entera.
 *
 * ▶ Y es la doctrina que el producto ya aplica: el cambio de correo avisa **al viejo** «para que el
 * dueño se entere si esto no lo ha pedido él», y el alta con un correo ya registrado avisa al titular
 * real.
 *
 * ⚠️ Para una cuenta de EQUIPO es todavía más importante (§6.6): la sesión que abre el retorno de
 * Google es la del panel, así que sin este correo una vinculación no pedida sería la única señal de
 * que alguien ha entrado por una puerta nueva — y no habría ninguna.
 */
class SocialIdentityLinked extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $provider  la clave del proveedor (`google`). Se pinta con su nombre propio.
     * @param  bool  $promoted  la cuenta estaba SIN verificar y se ha tomado: correo dado por
     *                          verificado, sesiones cerradas y contraseña anterior invalidada (P12).
     *                          Quien reciba esto tiene que entender POR QUÉ su contraseña dejó de
     *                          servir; si no, lo lee como una avería.
     */
    public function __construct(
        private readonly string $provider,
        private readonly bool $promoted = false,
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
        $park = (string) Setting::businessName();
        $provider = __('account.social_link_mail.providers.'.$this->provider);

        $mail = (new BrandedMailMessage($this))
            ->subject(__('account.social_link_mail.subject', ['provider' => $provider]))
            ->hero('account.social_link_mail', 'info')
            ->line(__('account.social_link_mail.line1', ['provider' => $provider, 'park' => $park]));

        if ($this->promoted) {
            $mail->line(__('account.social_link_mail.promoted'));
        }

        return $mail
            ->line(__('account.social_link_mail.not_you'))
            ->action(__('account.social_link_mail.action'), route('contacto'));
    }
}
