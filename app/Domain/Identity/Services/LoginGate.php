<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\LoginResult;
use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * **La puerta común de TODA entrada de cliente con un secreto** (`SEC-06`): los dos limitadores, el veredicto, el sello
 * de la última entrada y el rastro. La usa el código al correo ({@see EmailCodeLogin}) en sus dos superficies: el login
 * (`POST /auth/login`) y la emisión de tokens (`POST /auth/tokens`).
 *
 * ▶ **Salió de `PasswordLogin` TAL CUAL** (A1 de `docs/specs/acceso-con-codigo.md`, `DECISIONES #853`): era su núcleo
 * privado `guarded()`, y el código al correo tenía que compartirlo —no copiarlo—. La contraseña de los clientes se retiró
 * en la A5 (`#869`) y `PasswordLogin` con ella: el núcleo sobrevive a su primer dueño.
 *
 * ⚠️⚠️ **Compartir los cubos ES la regla, no un ahorro**: cinco fallos en el login bloquean también la emisión de tokens,
 * y al revés. Con cubos propios, cada puerta nueva sería otra tanda de intentos gratis contra la misma cuenta.
 */
class LoginGate
{
    /**
     * Fallos seguidos por (email, IP) antes del bloqueo temporal. Es el caso «alguien intenta
     * entrar en ESTA cuenta»: se limpia al acertar, porque el dueño legítimo no debe arrastrar los
     * fallos de antes.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Fallos por IP SOLA (auditoría Fase 1, A5). La clave compuesta no frena el
     * credential-stuffing ni el password-spraying: un intento por (cuenta, IP) nunca acumula 5 en
     * ninguna clave, así que un atacante que prueba una contraseña contra mil cuentas pasa entero.
     * Este segundo limitador corta el barrido. Generoso para no penalizar un NAT compartido, y
     * **NO se limpia al acertar**: la clave es de la IP, no del titular, y limpiarla dejaría que
     * un login válido intercalado reiniciara el contador del atacante.
     */
    public const MAX_ATTEMPTS_PER_IP = 30;

    /** Ventana de los limitadores, en segundos. */
    private const WINDOW = 60;

    /**
     * El núcleo de las puertas: limitadores, veredicto, sello de la última entrada y rastro.
     * Lo único que cambia entre ellas es CÓMO se comprueban las credenciales, que llega en `$check`
     * (devuelve el titular, o `null` si no casan).
     *
     * @param  Closure(string): ?User  $check
     */
    public function guarded(string $email, string $ip, string $successEvent, Closure $check): LoginResult
    {
        $email = Str::lower(trim($email));
        $compositeKey = $this->compositeKey($email, $ip);
        $ipKey = $this->ipKey($ip);

        if ($retryAfter = $this->lockoutSeconds($compositeKey, $ipKey)) {
            // El evento del framework mantiene el comportamiento de siempre (lo escuchan
            // integraciones y tests); el log deja el rastro sin PII: solo la IP.
            event(new Lockout(request()));
            Log::info('auth.login_lockout', ['ip' => $ip]);

            return LoginResult::rateLimited($retryAfter);
        }

        $user = $check($email);

        if ($user === null) {
            RateLimiter::hit($compositeKey, self::WINDOW);
            RateLimiter::hit($ipKey, self::WINDOW);
            Log::info('auth.login_failed', ['ip' => $ip]);

            return LoginResult::invalidCredentials();
        }

        RateLimiter::clear($compositeKey);

        // `saveQuietly`: sellar la última entrada no es un cambio del titular y no debe disparar
        // observers ni eventos de modelo.
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Log::info($successEvent, ['user_id' => $user->id, 'ip' => $ip]);

        return LoginResult::success($user);
    }

    /**
     * Segundos que faltan para poder reintentar, o 0 si no hay bloqueo. Se consultan **las dos**
     * claves y gana la más larga: la que esté bloqueada manda, y la otra devuelve 0.
     */
    private function lockoutSeconds(string $compositeKey, string $ipKey): int
    {
        $blocked = RateLimiter::tooManyAttempts($compositeKey, self::MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_ATTEMPTS_PER_IP);

        if (! $blocked) {
            return 0;
        }

        return max(
            RateLimiter::availableIn($compositeKey),
            RateLimiter::availableIn($ipKey),
        );
    }

    /** Clave por (email, IP). `transliterate` evita que un email con acentos genere claves distintas. */
    private function compositeKey(string $email, string $ip): string
    {
        return Str::transliterate($email.'|'.$ip);
    }

    private function ipKey(string $ip): string
    {
        return 'login-ip|'.$ip;
    }
}
