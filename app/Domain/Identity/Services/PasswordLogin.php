<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\LoginResult;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Inicio de sesión por CONTRASEÑA (Fase 3 · paso 3b, `docs/specs/api-v1.md` §4.2 y §4.6.3).
 *
 * Reúne lo que era de dominio dentro de `Livewire\Auth\Login`: los **dos** limitadores de `SEC-06`,
 * la comprobación de credenciales, el sello de `last_login_at` y el rastro en el log. Lo hace para
 * que la API de este mismo paso no tenga que reescribirlo — y sobre todo para que no reescriba solo
 * una parte: una API que copiara el limitador por (email, IP) y se dejara el de IP sola volvería a
 * abrir el credential-stuffing distribuido que el segundo cubre, sin que nada lo delatara.
 *
 * **Qué NO hace, a propósito**: regenerar la sesión, decidir a dónde va el usuario después, ni
 * tocar la cesta de la compra. Eso son efectos de la sesión WEB y los pone quien atiende la
 * petición (spec §4.6.3): el anti-cesta-cruzada del sidebar no tiene sentido en un cliente de API.
 *
 * `Auth::attempt()` sigue siendo quien autentica: no se reimplementa la comprobación del hash, y
 * así el guard, el `remember` y los eventos del framework funcionan igual en las dos superficies.
 */
class PasswordLogin
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

    public function attempt(string $email, string $password, bool $remember, string $ip): LoginResult
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

        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            RateLimiter::hit($compositeKey, self::WINDOW);
            RateLimiter::hit($ipKey, self::WINDOW);
            Log::info('auth.login_failed', ['ip' => $ip]);

            return LoginResult::invalidCredentials();
        }

        RateLimiter::clear($compositeKey);

        /** @var User $user */
        $user = Auth::user();
        // `saveQuietly`: sellar la última entrada no es un cambio del titular y no debe disparar
        // observers ni eventos de modelo.
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Log::info('auth.login', ['user_id' => $user->id, 'ip' => $ip]);

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
