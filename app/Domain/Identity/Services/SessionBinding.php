<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * **Cada sesión de la web, ATADA al `remember_token` de su cuenta** (A2a de `docs/specs/acceso-con-codigo.md` §4.9,
 * `DECISIONES #855`, `RGPD-06`): la revocación que no depende del driver de sesión.
 *
 * ⚠️⚠️ Medido antes: `User::purgeSessions()` solo borra filas con el driver `database`, y producción tiene `redis`
 * (`ENTORNOS.md` §6). Allí «cerrar las demás sesiones» dejaba viva la SESIÓN del otro dispositivo —hasta 2 h sin uso, o
 * para siempre si seguía usándola—; `logoutOtherDevices()` lo cubría rehasheando la contraseña, y sin contraseña ya no
 * hay nada que rehashear. Aquí cada sesión guarda, al entrar, la HUELLA del token de la cuenta, y
 * `EnsureSessionIsCurrent` cierra la que ya no casa. El token es la palanca única de `User`: `revokeOtherAccess()` lo
 * rota y re-ata la sesión en curso ({@see rebindCurrent()}), `revokeAllAccess()` lo vacía.
 *
 * ⚠️ Solo el guard `web`: el panel tiene el suyo (`admin`, `SEC-14`) y su `AuthenticateSession`.
 */
final class SessionBinding
{
    /** La clave de sesión con la huella del token al que va atada. */
    public const KEY = 'auth_binding_web';

    /**
     * Ata la sesión a la cuenta que acaba de entrar. Si la cuenta no tenía token, se le crea: una sesión atada a «nada» no
     * se podría cerrar desde otro dispositivo.
     */
    public static function bind(Session $session, User $user): void
    {
        self::ensureToken($user);

        $session->put(self::KEY, self::fingerprint($user));
    }

    /**
     * Cierra la sesión de la web de esta petición si la cuenta ya no la reconoce (su token cambió desde que se ató). Una
     * sesión de antes de este mecanismo, sin huella, se ata ahora.
     */
    public static function enforce(Request $request): void
    {
        $guard = Auth::guard('web');
        $session = $request->session();

        // Nadie dentro por la SESIÓN: una entrada por la cookie de recuerdo se ata al disparar su `Login`.
        if (! $guard instanceof SessionGuard || ! $session->has($guard->getName())) {
            return;
        }

        $user = $guard->user();
        if (! $user instanceof User) {
            return;
        }

        $bound = $session->get(self::KEY);
        if (! is_string($bound)) {
            self::bind($session, $user);

            return;
        }

        if (hash_equals($bound, self::fingerprint($user))) {
            return;
        }

        // Solo la web de ESTE dispositivo: la sesión puede llevar también el panel del personal, y ése es otro guard.
        $guard->logoutCurrentDevice();
        $session->forget(self::KEY);
        // ⚠️ Y los guards ya resueltos, fuera: el de `sanctum` puede tener ya al titular en su caché —lo resuelve antes el
        // `AuthenticateSession` de Sanctum—, y cerrar el `web` no la vacía. Medido: sin esto, `/me` seguía dando 200. Es
        // el mismo remedio que `AuthSessionController::logout`.
        Auth::forgetGuards();
        Log::info('auth.session_revoked', ['user_id' => $user->id]);
    }

    /**
     * El token de `$user` acaba de rotar: si la petición en curso es de SU sesión de la web, se re-ata al token nuevo. Las
     * de los demás dispositivos quedan atadas al viejo y se cierran en su próxima petición.
     */
    public static function rebindCurrent(User $user): void
    {
        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard || ! $guard->hasUser() || (string) $guard->id() !== (string) $user->getAuthIdentifier()) {
            return;
        }

        $request = $guard->getRequest();
        if ($request instanceof Request && $request->hasSession() && $request->session()->has(self::KEY)) {
            $request->session()->put(self::KEY, self::fingerprint($user));
        }
    }

    /** La huella del token: la sesión (una fila o una clave de Redis) no guarda la credencial en claro. */
    private static function fingerprint(User $user): string
    {
        return hash('sha256', (string) $user->getRememberToken());
    }

    private static function ensureToken(User $user): void
    {
        if ((string) $user->getRememberToken() !== '') {
            return;
        }

        $token = Str::random(60);
        // Por consulta y no guardando el modelo: quien llama puede tener otros cambios sin guardar.
        $user->newQuery()->whereKey($user->getKey())->update(['remember_token' => $token]);
        $user->forceFill(['remember_token' => $token])->syncOriginalAttribute('remember_token');
    }
}
