<?php

namespace App\Domain\Platform\Listeners;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Domain\Platform\Services\Analytics\Recorder;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * **`email_sent`, como hecho del servidor** (`docs/specs/analitica.md` §4.1, `#678`, T1c). Escucha el
 * `NotificationSent` del framework —que se dispara al salir CADA correo, también desde el worker de la cola—
 * en vez de tocar los veinticinco `toMail()`.
 *
 * ⚠️ Con la MISMA clave que llevan sus enlaces (`EmailUtm::keyOf()`): es lo que permite cruzar «enviados» con
 * `email_clicked` y decir cuántos vuelven por cada correo. Y solo los que lee un cliente: el aviso al negocio de
 * la ficha de Google no es audiencia.
 *
 * ⚠️ Sin visitante ni sesión: un envío no ocurre en la petición de nadie. El `user_id` sí, cuando el
 * destinatario es una cuenta — un `Notification::route('mail', …)` (el padre sin cuenta) queda anónimo.
 */
final class RecordEmailSent
{
    public function __construct(private readonly Recorder $recorder) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $key = EmailUtm::keyOf($event->notification);

        if (! EmailUtm::isCustomerKey($key)) {
            return;
        }

        $user = $event->notifiable instanceof User ? (int) $event->notifiable->getAuthIdentifier() : null;

        $this->recorder->fact('email_sent', ['key' => $key], ['user_id' => $user]);
    }
}
