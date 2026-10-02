<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\CredentialChangeResult;
use App\Domain\Identity\Contracts\ResendResult;
use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\User;
use App\Notifications\ConfirmationCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * **Las gestiones de credenciales del titular**: reconfirmar una acción sensible y cerrar sesión en los demás
 * dispositivos (`specs/area-cliente.md` §9).
 *
 * Reúne lo que era de dominio dentro de los componentes Livewire de «Mi cuenta» (retirados), para que la API no lo
 * reescriba y, sobre todo, para que no reescriba **solo una parte** (`RGPD-06`).
 *
 * ▶ **Desde la A5 de `specs/acceso-con-codigo.md` (`#869`) se reconfirma SOLO con un código `confirm`** al correo de la
 * cuenta: la contraseña del cliente se retiró, y con ella cambiarla (`PUT /me/password`). El código lo pide
 * `requestConfirmationCode()` y lo gasta `verify()`.
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
     * ⚠️⚠️ **La web no tenía ninguno, y eso es lo que este limitador cierra** (medido el 2026-08-22,
     * `DECISIONES #120(n)`): con una sesión secuestrada se podía probar **sin techo** antes de borrar la cuenta. La
     * doctrina del producto para auth es la contraria (`SEC-06`), y reconfirmar **es** auth. Cada código muere además a
     * los cinco intentos (`LoginCodes`).
     */
    public const MAX_ATTEMPTS = 5;

    /** Ventana del limitador, en segundos. */
    private const WINDOW = 60;

    /**
     * ⚠️ **Un solo limitador, por (titular, IP), y NO uno por IP sola como en la puerta.** Aquel segundo existe porque
     * un atacante prueba contra mil cuentas **sin tener ninguna sesión**; aquí cada intento exige ya una sesión válida de
     * esa cuenta concreta, así que el barrido no es el escenario: la escalada sobre una sesión ya comprometida sí. Se
     * dice para que nadie lo lea como un olvido.
     */
    private function key(User $user, string $ip): string
    {
        return 'account-credentials:'.$user->id.'|'.$ip;
    }

    /** Cierra la sesión en los demás dispositivos, conservando la actual. */
    public function revokeOtherSessions(User $user, string $code, string $ip): CredentialChangeResult
    {
        $verdict = $this->verify($user, $code, $ip);

        if ($verdict->failed()) {
            return $verdict;
        }

        // ▶ Desde la A2a (`#855`) `revokeOtherAccess()` cierra también las SESIONES vivas, con cualquier driver: cada
        // sesión de la web va atada al `remember_token` (`SessionBinding`), y rotarlo deja fuera a las que no son esta.
        // El rehash del guard que hacía falta con la contraseña (`logoutOtherDevices()`) se fue con ella (A5, `#869`).
        $user->revokeOtherAccess();

        Log::info('account.logout_other_devices', ['user_id' => $user->id]);

        return CredentialChangeResult::success();
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
            return ResendResult::throttled(EmailCodeLogin::secondsToWait([
                $minuteKey => EmailCodeLogin::MAX_PER_EMAIL_PER_MINUTE, $hourKey => EmailCodeLogin::MAX_PER_EMAIL_PER_HOUR,
            ]));
        }
        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);

        $code = $this->codes->issue((string) $user->email, LoginCode::PURPOSE_CONFIRM, $ip);
        CodeMail::sendAfterResponse($user, new ConfirmationCode($code, $action));

        Log::info('account.confirmation_code_requested', ['user_id' => $user->id, 'action' => $action, 'ip' => $ip]);

        return ResendResult::sent();
    }

    /**
     * **Reconfirma al titular con un código `confirm`, contando el intento.** Devuelve el veredicto.
     *
     * ⚠️ **Es público porque lo necesitan MÁS gestiones que las de esta clase**: el cambio de correo del perfil, el
     * borrado de la cuenta y desvincular Google piden lo mismo, y cada uno con su propia comprobación sería el limitador
     * copiado tres veces —y la copia que se olvide es la que queda sin techo—. Quien llama ejecuta su acción **solo si
     * esto dice que sí**.
     *
     * ⚠️ **El contador se limpia al acertar**: el dueño legítimo que se equivocó dos veces no debe arrastrar esos fallos el
     * resto del minuto. Un código de ENTRAR no vale aquí (el propósito va en su huella); se gasta al acertar, y cada uno
     * muere además a los cinco intentos.
     */
    public function verify(User $user, string $code, string $ip): CredentialChangeResult
    {
        $key = $this->key($user, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return CredentialChangeResult::rateLimited(RateLimiter::availableIn($key));
        }

        if (! $this->codes->consume((string) $user->email, LoginCode::PURPOSE_CONFIRM, $code)) {
            RateLimiter::hit($key, self::WINDOW);

            return CredentialChangeResult::wrongCode();
        }

        RateLimiter::clear($key);

        return CredentialChangeResult::success();
    }
}
