<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\CredentialChangeResult;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * **Las gestiones de credenciales del titular**: cambiar la contraseña y cerrar sesión en los demás
 * dispositivos (`specs/area-cliente.md` §9).
 *
 * Reúne lo que era de dominio dentro de `Livewire\Account\UpdatePassword` y
 * `Livewire\Account\LogoutOtherDevices`, por el mismo motivo por el que Fase 3 creó
 * {@see PasswordLogin}: para que la API no lo reescriba, y sobre todo para que no reescriba **solo
 * una parte**. Las dos acciones cierran las otras sesiones por **dos vías complementarias** —el
 * rehash del guard y el borrado de filas de `revokeOtherAccess()`— y una superficie que copiara solo
 * la primera dejaría vivas las filas de `sessions` y los tokens, sin que nada lo delatara (`RGPD-06`).
 *
 * **Qué NO hace, a propósito**: validar el formato de la contraseña nueva (eso es
 * {@see PasswordPolicy}, y la exige cada superficie con su formulario), decidir a dónde va el usuario
 * después, ni tocar la sesión propia. Eso es de quien atiende la petición.
 */
class AccountCredentials
{
    /**
     * Fallos seguidos por (titular, IP) antes del bloqueo temporal.
     *
     * ⚠️⚠️ **La web no tiene ninguno, y eso es lo que este limitador cierra** (medido el 2026-08-22,
     * `DECISIONES #120(n)`): con una sesión secuestrada se podían probar contraseñas **sin techo**
     * antes de cambiarla o borrar la cuenta. La doctrina del producto para auth es la contraria
     * (`SEC-06`), y reconfirmar la contraseña **es** auth.
     */
    public const MAX_ATTEMPTS = 5;

    /** Ventana del limitador, en segundos. */
    private const WINDOW = 60;

    /**
     * ⚠️ **Un solo limitador, por (titular, IP), y NO uno por IP sola como en el login.** Aquel
     * segundo existe porque un atacante prueba una contraseña contra mil cuentas **sin tener ninguna
     * sesión**; aquí cada intento exige ya una sesión válida de esa cuenta concreta, así que el
     * barrido no es el escenario: la escalada sobre una sesión ya comprometida sí. Se dice para que
     * nadie lo lea como un olvido.
     */
    private function key(User $user, string $ip): string
    {
        return 'account-credentials:'.$user->id.'|'.$ip;
    }

    /**
     * Cambia la contraseña y **cierra las demás sesiones**.
     *
     * ⚠️ El cierre va con la contraseña NUEVA: `logoutOtherDevices()` rehashea el sello del guard, y
     * pasarle la vieja dejaría la sesión en curso invalidada a sí misma en la siguiente petición.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword, string $ip): CredentialChangeResult
    {
        return $this->reauthenticated($user, $currentPassword, $ip, function () use ($user, $newPassword): void {
            $user->update(['password' => $newPassword]);

            $this->closeOtherSessions($user, $newPassword);

            Log::info('account.password_updated', ['user_id' => $user->id]);
        });
    }

    /** Cierra la sesión en los demás dispositivos, conservando la actual. */
    public function revokeOtherSessions(User $user, string $currentPassword, string $ip): CredentialChangeResult
    {
        return $this->reauthenticated($user, $currentPassword, $ip, function () use ($user, $currentPassword): void {
            $this->closeOtherSessions($user, $currentPassword);

            Log::info('account.logout_other_devices', ['user_id' => $user->id]);
        });
    }

    /**
     * El guardián común: limitador → contraseña → acción.
     *
     * ⚠️ **El contador se limpia al acertar**, como el limitador por (email, IP) del login: el dueño
     * legítimo que se equivocó dos veces no debe arrastrar esos fallos el resto del minuto.
     */
    private function reauthenticated(User $user, string $currentPassword, string $ip, callable $action): CredentialChangeResult
    {
        $key = $this->key($user, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return CredentialChangeResult::rateLimited(RateLimiter::availableIn($key));
        }

        if (! Hash::check($currentPassword, (string) $user->password)) {
            RateLimiter::hit($key, self::WINDOW);

            return CredentialChangeResult::wrongPassword();
        }

        RateLimiter::clear($key);

        $action();

        return CredentialChangeResult::success();
    }

    /**
     * **Las DOS vías, siempre juntas.**
     *
     * ⚠️⚠️ `logoutOtherDevices()` rehashea el sello del guard —invalida las cookies de sesión de otros
     * navegadores— y `revokeOtherAccess()` **borra las filas** de `sessions` y los tokens de Sanctum
     * (`RGPD-06`, sitio único). Hacen cosas distintas y las dos hacen falta: sin la primera, una
     * cookie viva seguiría autenticando hasta que su fila caducara; sin la segunda, quedan filas y
     * **tokens** que ninguna cookie necesita — y con un emisor de Bearer (Fase 6) ésa es la vía que
     * de verdad importa.
     *
     * ⚠️ El guard solo sabe hacer lo primero si es de SESIÓN. Un cliente por token no tiene sello que
     * rehashear, y ahí `revokeOtherAccess()` es todo lo que hay — motivo de más para no separarlas.
     */
    private function closeOtherSessions(User $user, string $password): void
    {
        $guard = Auth::guard();

        if (method_exists($guard, 'logoutOtherDevices')) {
            $guard->logoutOtherDevices($password);
        }

        $user->revokeOtherAccess();
    }
}
