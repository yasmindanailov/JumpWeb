<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\CredentialChangeResult;
use App\Domain\Identity\Contracts\Reconfirmation;
use App\Domain\Identity\Contracts\ResendResult;
use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\User;
use App\Notifications\ConfirmationCode;
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
     * Las acciones que se confirman con un código (A2a de `specs/acceso-con-codigo.md` §4.9, `#855`). El correo dice cuál:
     * solo se pide con la sesión abierta, y quien lo recibe sin haberlo pedido sabe que alguien la tiene.
     *
     * @var list<string>
     */
    public const CONFIRM_ACTIONS = ['delete_account', 'change_email', 'unlink_google', 'close_sessions'];

    public function __construct(private readonly LoginCodes $codes) {}

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
        // Solo con la contraseña: cambiarla es de quien la tiene, y se retira entera en la A5.
        return $this->reauthenticated($user, Reconfirmation::password($currentPassword), $ip, function () use ($user, $newPassword): void {
            $user->update(['password' => $newPassword]);

            $this->closeOtherSessions($user, $newPassword);

            Log::info('account.password_updated', ['user_id' => $user->id]);
        });
    }

    /** Cierra la sesión en los demás dispositivos, conservando la actual. */
    public function revokeOtherSessions(User $user, Reconfirmation $with, string $ip): CredentialChangeResult
    {
        return $this->reauthenticated($user, $with, $ip, function () use ($user, $with): void {
            $this->closeOtherSessions($user, $with->password);

            Log::info('account.logout_other_devices', ['user_id' => $user->id]);
        });
    }

    /**
     * **Envía el código para CONFIRMAR `$action`** al correo de la cuenta (A2a, `#855`), tras la respuesta ({@see CodeMail}).
     *
     * Con techo por CUENTA —uno por minuto y cinco por hora, los números del código de entrar—: quien tiene la sesión podría
     * si no llenar de códigos el buzón del dueño. Pedir otro anula el anterior.
     */
    public function requestConfirmationCode(User $user, string $action, string $ip): ResendResult
    {
        $hash = SelfSignup::emailHash((string) $user->email);
        $minuteKey = 'confirm-code|'.$hash;
        $hourKey = 'confirm-code-hour|'.$hash;

        if (RateLimiter::tooManyAttempts($minuteKey, EmailCodeLogin::MAX_PER_EMAIL_PER_MINUTE)
            || RateLimiter::tooManyAttempts($hourKey, EmailCodeLogin::MAX_PER_EMAIL_PER_HOUR)) {
            return ResendResult::throttled(max(RateLimiter::availableIn($minuteKey), RateLimiter::availableIn($hourKey)));
        }
        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);

        $code = $this->codes->issue((string) $user->email, LoginCode::PURPOSE_CONFIRM, $ip);
        CodeMail::sendAfterResponse($user, new ConfirmationCode($code, $action));

        Log::info('account.confirmation_code_requested', ['user_id' => $user->id, 'action' => $action, 'ip' => $ip]);

        return ResendResult::sent();
    }

    /**
     * **Reconfirma al titular —con su contraseña o con un código `confirm`—, contando el intento.** Devuelve el veredicto.
     *
     * ⚠️ **Es público porque lo necesitan MÁS gestiones que las dos de esta clase**: el cambio de
     * email del perfil y el borrado de cuenta piden lo mismo, y cada uno con su propio `Hash::check`
     * sería el limitador copiado tres veces —y la copia que se olvide es la que queda sin techo—.
     * Quien llama ejecuta su acción **solo si esto dice que sí**.
     *
     * ⚠️ **El contador se limpia al acertar**, como el limitador por (email, IP) del login: el dueño
     * legítimo que se equivocó dos veces no debe arrastrar esos fallos el resto del minuto.
     *
     * ▶ **Desde la A2a (`#855`) también con el CÓDIGO**, bajo el MISMO limitador: la contraseña y el código son la misma
     * puerta a efectos de ataque, y un código de ENTRAR no vale aquí (el propósito va en su huella). El código se gasta
     * al acertar; cada uno muere además a los cinco intentos.
     */
    public function verify(User $user, Reconfirmation $with, string $ip): CredentialChangeResult
    {
        $key = $this->key($user, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return CredentialChangeResult::rateLimited(RateLimiter::availableIn($key));
        }

        $confirmed = $with->isCode()
            ? $this->codes->consume((string) $user->email, LoginCode::PURPOSE_CONFIRM, (string) $with->code)
            : Hash::check((string) $with->password, (string) $user->password);

        if (! $confirmed) {
            RateLimiter::hit($key, self::WINDOW);

            return $with->isCode() ? CredentialChangeResult::wrongCode() : CredentialChangeResult::wrongPassword();
        }

        RateLimiter::clear($key);

        return CredentialChangeResult::success();
    }

    /** El guardián común de esta clase: reconfirmar y, si pasa, actuar. */
    private function reauthenticated(User $user, Reconfirmation $with, string $ip, callable $action): CredentialChangeResult
    {
        $verdict = $this->verify($user, $with, $ip);

        if ($verdict->failed()) {
            return $verdict;
        }

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
     *
     * ▶ **Desde la A2a (`#855`) `revokeOtherAccess()` cierra también las SESIONES vivas, con cualquier driver**: cada
     * sesión de la web va atada al `remember_token` (`SessionBinding`), y rotarlo deja fuera a las que no son esta. Por
     * eso, confirmado con un CÓDIGO —sin contraseña que rehashear—, el rehash sobra; con contraseña sigue hasta la A5.
     */
    private function closeOtherSessions(User $user, ?string $password): void
    {
        $guard = Auth::guard();

        if ($password !== null && method_exists($guard, 'logoutOtherDevices')) {
            $guard->logoutOtherDevices($password);
        }

        $user->revokeOtherAccess();
    }
}
