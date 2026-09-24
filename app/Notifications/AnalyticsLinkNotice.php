<?php

namespace App\Notifications;

use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **El aviso a las cuentas que existían antes de la v3 de la política de cookies** (`docs/specs/analitica.md`
 * §4.3, T3a·4; arts. 13.3 y 21 RGPD): desde esa versión, quien acepta la categoría «análisis» en el aviso de
 * cookies puede ver su navegación vinculada a su cuenta. A quien se registró bajo la política anterior hay que
 * contárselo ANTES de que el enlace exista, y decirle dónde oponerse.
 *
 * ⚠️ **No es marketing**: es información sobre un tratamiento nuevo, así que sale también a quien no dio el
 * consentimiento de comunicaciones comerciales. Lo que NO sale es a quien ya se opuso (`analytics_opt_out`) ni
 * al equipo, cuya navegación nunca se ata (`RecordLoginFact`).
 *
 * ⚠️ Lo manda `analytics:notify-accounts` una sola vez por cuenta (`users.analytics_notified_at`), la noche del
 * despliegue de la v3; el aviso del cajón es el segundo canal, para quien no lee el correo.
 * ⚠️ El botón lleva al ÍNDICE del área de cliente (`/mi-cuenta`), que es desde donde se llega a «Privacidad y
 * datos»: el cajón no tiene enlace profundo a una zona, y el aviso del índice repite el camino.
 */
class AnalyticsLinkNotice extends Notification implements ShouldQueue
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
            ->subject(__('account.analytics_mail.subject'))
            ->hero('account.analytics_mail', 'info')
            ->line(__('account.analytics_mail.line1', ['park' => $park]))
            ->line(__('account.analytics_mail.line2'))
            ->line(__('account.analytics_mail.line3'))
            ->action(__('account.analytics_mail.action'), route('account'));
    }
}
