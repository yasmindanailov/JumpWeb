<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use Illuminate\Auth\Recaller;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * **El dispositivo RECORDADO** (`docs/specs/acceso-con-codigo.md` §4.4, `DECISIONES #848`·3): quien entra con el código
 * o se da de alta queda dentro **90 días sin uso** o hasta cerrar sesión —cada entrada cuesta un correo—. Es la cookie
 * «recuérdame» del guard `web` (`config/auth.php`), con dos piezas que el framework no trae:
 *  - **«sin uso»**: la cookie nace con 90 días y el framework no la alarga nunca; {@see refresh()} la re-emite en cada
 *    página que la usa (`RefreshRememberedDevice`), así que caduca a los 90 días de la ÚLTIMA visita;
 *  - **conservar el dispositivo en curso al rotar** (`RGPD-06`): el `remember_token` es UNO por cuenta, así que
 *    «cerrar las demás sesiones» lo rota —y las demás cookies dejan de valer— y {@see keepCurrent()} re-emite la de
 *    quien lo pidió con el token nuevo.
 *
 * ⚠️ Solo el guard `web`: el panel tiene el suyo (`admin`, `SEC-14`) y su propio recuerdo.
 */
final class RememberedDevice
{
    public const DAYS = 90;

    /** En minutos, como lo pide `config/auth.php` (`guards.web.remember`). */
    public const MINUTES = self::DAYS * 24 * 60;

    /**
     * Re-emite la cookie de recuerdo que trae la petición, con 90 días más, si sigue siendo la del titular dentro. Si la
     * petición ya decidió algo de ella —entrar la escribe, salir la borra—, manda lo decidido.
     */
    public static function refresh(Request $request): void
    {
        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard) {
            return;
        }

        $name = $guard->getRecallerName();
        $value = $request->cookies->get($name);
        $jar = $guard->getCookieJar();

        if (! is_string($value) || $value === '' || $jar->hasQueued($name)) {
            return;
        }

        $user = $guard->user();
        $recaller = new Recaller($value);

        // Una cookie con un token viejo (rotado desde otro dispositivo) no se alarga: ya no abre nada.
        if (! $user instanceof User || ! $recaller->valid()
            || (string) $recaller->id() !== (string) $user->getAuthIdentifier()
            || ! hash_equals((string) $user->getRememberToken(), (string) $recaller->token())) {
            return;
        }

        $jar->queue($jar->make($name, $value, self::MINUTES));
    }

    /**
     * El `remember_token` de `$user` acaba de rotar: si la petición en curso es de SU dispositivo recordado, su cookie
     * se re-emite con el token nuevo. Las de los demás dispositivos quedan con el viejo y dejan de valer.
     */
    public static function keepCurrent(User $user): void
    {
        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard || ! $guard->hasUser() || (string) $guard->id() !== (string) $user->getAuthIdentifier()) {
            return;
        }

        $name = $guard->getRecallerName();
        $jar = $guard->getCookieJar();
        $value = $jar->queued($name)?->getValue() ?? $guard->getRequest()->cookies->get($name);

        if (! is_string($value) || $value === '') {
            return;
        }

        $recaller = new Recaller($value);
        if (! $recaller->valid() || (string) $recaller->id() !== (string) $user->getAuthIdentifier()) {
            return;
        }

        // Solo cambia el token: el identificador y la huella de la contraseña (el tercer tramo) son los que ya llevaba.
        $jar->queue($jar->make($name, $recaller->id().'|'.$user->getRememberToken().'|'.$recaller->hash(), self::MINUTES));
    }
}
