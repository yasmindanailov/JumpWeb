<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\CodeRequestResult;
use App\Domain\Identity\Contracts\LoginResult;
use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\User;
use App\Notifications\LoginCode as LoginCodeMail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * **Entrar con un código al correo** (A1 de `docs/specs/acceso-con-codigo.md` §4.2–§4.4, `DECISIONES #848`/`#849`): la
 * PUERTA —el correo decide si va al código o al alta— y VERIFICAR el código. Sirve a las dos superficies, la sesión del
 * navegador (`POST /auth/login`) y el token de la app (`POST /auth/tokens`); lo que cada una hace después —abrir la
 * sesión recordada o emitir el token— es de quien atiende la petición. Desde la A5 (`#869`) es la ÚNICA entrada del
 * cliente con un secreto: la contraseña se retiró.
 *
 * Las defensas (`SEC-06`, de DOMINIO: ningún controlador las reimplementa):
 *  - **pedir**: un techo por IP para el rociado y la enumeración (la puerta dice si un correo tiene cuenta, `#849`), y
 *    por CORREO uno por minuto y cinco por hora —el buzón de la víctima—;
 *  - **verificar**: los 5 intentos de cada código ({@see LoginCodes}) y los DOS limitadores del login, los mismos cubos
 *    que la contraseña ({@see LoginGate}): cinco fallos con una bloquean la otra.
 * Con 5 intentos por código y 5 códigos por hora, acertar a ciegas es 2,5·10⁻⁵ por hora y correo (§4.2).
 *
 * ⚠️⚠️ **El correo sale TRAS la respuesta, no por la cola** (§4.3, {@see CodeMail}): la cola la vacía el cron cada minuto
 * en producción, y un código que tarda 60 s en la puerta del parque es un código que no llega.
 */
class EmailCodeLogin
{
    /** Peticiones a la puerta por minuto y por IP (con cuenta o sin ella). */
    public const MAX_PER_IP = 10;

    /** Códigos por minuto y por correo: el reenvío no puede ser una ráfaga. */
    public const MAX_PER_EMAIL_PER_MINUTE = 1;

    /** Códigos por hora y por correo: el techo del buzón de quien no ha pedido nada. */
    public const MAX_PER_EMAIL_PER_HOUR = 5;

    public function __construct(
        private readonly LoginGate $gate,
        private readonly LoginCodes $codes,
    ) {}

    /**
     * **Cuánto esperar, de los límites que de verdad están AGOTADOS** (`[clave => máximo]`). Lo usan también el código de
     * confirmar ({@see AccountCredentials}) y el reenvío del correo nuevo ({@see AccountProfile}): los mismos dos techos.
     *
     * ⚠️⚠️ `RateLimiter::availableIn()` devuelve lo que le queda a la VENTANA de una clave aunque no esté agotada: tras un código,
     * la de la hora tiene 3.599 s por delante con solo 1 de 5. El `max()` de las dos —lo que se hacía— decía «espera una hora»
     * a quien solo tenía que esperar el minuto (medido el 01-10 en la sonda del cajón al pedir otro código; la isla, igual).
     *
     * @param  array<string, int>  $limits
     */
    public static function secondsToWait(array $limits): int
    {
        $waits = [0];

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $waits[] = RateLimiter::availableIn($key);
            }
        }

        return max($waits);
    }

    /**
     * LA PUERTA: ¿tiene cuenta este correo? Con cuenta, se le envía un código (tras la respuesta); sin ella, al alta.
     */
    public function request(string $email, string $ip): CodeRequestResult
    {
        $email = Str::lower(trim($email));

        // 1) Por IP, TODAS las peticiones: es lo que acota el barrido de correos y el rociado de códigos desde un origen.
        $ipKey = 'login-code-ip|'.$ip;
        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_PER_IP)) {
            Log::info('auth.code_request_throttled', ['ip' => $ip]);

            return CodeRequestResult::rateLimited(RateLimiter::availableIn($ipKey));
        }
        RateLimiter::hit($ipKey, 60);

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return CodeRequestResult::register();
        }

        // 2) Por CORREO, solo cuando de verdad se enviaría uno. Con el correo en hash: la caché no guarda direcciones.
        $minuteKey = 'login-code-email|'.SelfSignup::emailHash($email);
        $hourKey = 'login-code-email-hour|'.SelfSignup::emailHash($email);
        if (RateLimiter::tooManyAttempts($minuteKey, self::MAX_PER_EMAIL_PER_MINUTE)
            || RateLimiter::tooManyAttempts($hourKey, self::MAX_PER_EMAIL_PER_HOUR)) {
            Log::info('auth.code_request_email_throttled', ['ip' => $ip]);

            return CodeRequestResult::rateLimited(
                self::secondsToWait([$minuteKey => self::MAX_PER_EMAIL_PER_MINUTE, $hourKey => self::MAX_PER_EMAIL_PER_HOUR]),
                CodeRequestResult::CODE,
            );
        }
        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);

        $code = $this->codes->issue($email, LoginCode::PURPOSE_LOGIN, $ip);
        CodeMail::sendAfterResponse($user, new LoginCodeMail($code));

        Log::info('auth.code_requested', ['user_id' => $user->id, 'ip' => $ip]);

        return CodeRequestResult::code();
    }

    /**
     * Verifica el código SIN abrir sesión: devuelve el titular y quien llama decide qué abre (sesión o token).
     *
     * Un código bueno prueba que quien lo escribe lee ese buzón, así que además CONFIRMA el correo si no lo estaba
     * (§4.4) —con el evento del framework, que convierte en firma el descargo pendiente del alta—.
     */
    public function verify(string $email, string $code, string $ip): LoginResult
    {
        $result = $this->gate->guarded($email, $ip, 'auth.code_login', function (string $email) use ($code): ?User {
            if (! $this->codes->consume($email, LoginCode::PURPOSE_LOGIN, $code)) {
                return null;
            }

            return User::query()->where('email', $email)->first();
        });

        $user = $result->user;
        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $result;
    }
}
