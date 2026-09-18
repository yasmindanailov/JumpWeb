<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\LoginResult;
use App\Domain\Identity\Models\User;
use Closure;
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
        return $this->guarded($email, $ip, 'auth.login', static function (string $email) use ($password, $remember): ?User {
            if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
                return null;
            }

            /** @var User */
            return Auth::user();
        });
    }

    /**
     * Comprueba las credenciales **sin abrir sesión** (F4, `docs/specs/token-bearer.md` §4.2): es la
     * puerta del emisor de tokens Bearer, que atiende a quien no tiene sesión ni la quiere.
     *
     * ⚠️ No puede llamar a {@see attempt()}: `Auth::attempt()` inicia sesión en el guard `web` y
     * encola la cookie `remember`, que en una petición sin `StartSession` es estado a medias. Aquí
     * se usa `validate()`, que comprueba el hash por el mismo proveedor y no toca la sesión.
     *
     * ⚠️⚠️ Y **no es una segunda puerta para un atacante**: comparte con `attempt()` el núcleo
     * {@see guarded()} —los DOS limitadores, con las MISMAS claves—, así que cinco fallos en el login
     * bloquean también la emisión de tokens, y al revés. Dos cubos separados habrían duplicado los
     * intentos que `SEC-06` concede.
     */
    public function verify(string $email, string $password, string $ip): LoginResult
    {
        return $this->guarded($email, $ip, 'auth.credentials_verified', static function (string $email) use ($password): ?User {
            // Por el GUARD y no por el proveedor a pelo: `validate()` comprueba el hash dentro de la
            // misma caja de tiempo que `attempt()`, así que la puerta nueva tampoco delata por el
            // reloj qué correos existen (`SEC-06`).
            if (! Auth::guard('web')->validate(['email' => $email, 'password' => $password])) {
                return null;
            }

            // Las credenciales ya casaron: es la misma búsqueda que acaba de hacer el proveedor.
            return User::query()->where('email', $email)->first();
        });
    }

    /**
     * El núcleo de las dos puertas: limitadores, veredicto, sello de la última entrada y rastro.
     * Lo único que cambia entre ellas es CÓMO se comprueban las credenciales, que llega en `$check`
     * (devuelve el titular, o `null` si no casan).
     *
     * @param  Closure(string): ?User  $check
     */
    private function guarded(string $email, string $ip, string $successEvent, Closure $check): LoginResult
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
