<?php

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountAnalytics;
use App\Domain\Platform\Services\Analytics\Recorder;
use Illuminate\Auth\Events\Login;

/**
 * **`user_logged_in`, como hecho del servidor** (`docs/specs/analitica.md` §4.1). Escucha el `Login` del
 * framework —el que disparan `Auth::attempt()` y `Auth::login()` en todas las puertas: contraseña, Google y
 * verificación de correo— en vez de tocar cada servicio.
 *
 * ⚠️ El equipo no cuenta: un operador que entra al panel no es un cliente que vuelve. Se mira el rol y no la
 * ruta, porque «esconder no es autorizar» vale también al revés.
 */
final class RecordLoginFact
{
    public function __construct(private readonly Recorder $recorder, private readonly AccountAnalytics $analytics) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        if ($user->roles()->whereIn('name', User::PANEL_ROLES)->exists()) {
            return;
        }

        $this->recorder->fact('user_logged_in', ['method' => $event->guard], ['user_id' => (int) $user->getAuthIdentifier()]);

        // El régimen IDENTIFICADO (`specs/analitica.md` §4.3, T3a·3): al entrar, si esta petición trae la
        // categoría `analytics` y la cuenta no se opuso, su navegación se ata a la cuenta. Nunca tumba el login.
        $this->analytics->linkIfConsented($user, request()->ip());
    }
}
