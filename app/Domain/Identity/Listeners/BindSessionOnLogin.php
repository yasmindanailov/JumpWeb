<?php

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SessionBinding;
use Illuminate\Auth\Events\Login;

/**
 * Ata la sesión de la web a su cuenta en el momento de ENTRAR, por cualquier puerta —el código, el alta, Google, el enlace
 * de verificación, la cookie de recuerdo— (`SessionBinding`, A2a, `DECISIONES #855`). Escucha el `Login` del framework en
 * vez de tocar cada puerta: la que se olvidara sería una sesión que «cerrar las demás» no alcanza.
 *
 * Solo el guard `web` y solo con sesión: el panel tiene el suyo, y una petición por Bearer no dispara `Login`.
 */
final class BindSessionOnLogin
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $request = request();
        if (! $request->hasSession()) {
            return;
        }

        SessionBinding::bind($request->session(), $event->user);
    }
}
