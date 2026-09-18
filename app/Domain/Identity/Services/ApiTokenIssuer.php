<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * El EMISOR de tokens Bearer (F4 del programa, `docs/specs/token-bearer.md` §4.2, `DECISIONES #630`).
 *
 * Llega el último: desde la Fase 3 existían el trait, la caducidad, la poda y —sobre todo— la
 * revocación por las cinco vías de `RGPD-06`, hecha antes de que hubiera un solo token emitido. Lo
 * que faltaba es esto, y es deliberadamente poco: **quién comprueba la contraseña no vive aquí**
 * ({@see PasswordLogin::verify()}, con los dos limitadores de `SEC-06`) y **quién retira un token
 * tampoco** (`User`, el punto único). Aquí solo se decide con qué nace un token.
 *
 * Tres reglas, las tres con su mutación en `scripts/mutar-token-bearer.sh`:
 *  · nace con UNA ability, {@see ABILITY}, y nunca con el comodín: el día que exista una superficie
 *    privilegiada, los tokens de la app ya emitidos no la abren;
 *  · nace CON caducidad propia (`expires_at`), aunque alguien vacíe `sanctum.expiration`;
 *  · cada cuenta conserva {@see MAX_TOKENS} como mucho: el siguiente retira el más olvidado.
 */
class ApiTokenIssuer
{
    /** La única ability que llevan los tokens de un cliente, y la que exige el grupo autenticado de `/api/v1`. */
    public const ABILITY = 'api-v1';

    /** Tokens vivos por cuenta. Diez dispositivos es más de lo que tiene nadie y acota a quien tiene la contraseña. */
    public const MAX_TOKENS = 10;

    /** Caducidad de reserva, en minutos (30 días), si `sanctum.expiration` llegara vacío: un token SIEMPRE caduca. */
    private const FALLBACK_LIFETIME = 60 * 24 * 30;

    public function issue(User $user, string $deviceName, string $ip): NewAccessToken
    {
        return DB::transaction(function () use ($user, $deviceName, $ip): NewAccessToken {
            $token = $user->createToken($deviceName, [self::ABILITY], $this->expiresAt());
            $user->revokeStalestTokens(self::MAX_TOKENS);

            // Sin PII: ni el correo ni el nombre del dispositivo, que lo escribe el titular (`RGPD-02`).
            Log::info('auth.token_issued', ['user_id' => $user->id, 'ip' => $ip]);

            return $token;
        });
    }

    /**
     * Cambia el token de ESTA petición por uno nuevo con el mismo nombre de dispositivo (§4.4).
     *
     * Devuelve `null` si la petición no viene por Bearer: con cookie de sesión Sanctum entrega un
     * `TransientToken`, que no es una fila y no se puede rotar.
     *
     * ⚠️ Crear y revocar van en UNA transacción: si se revocara primero y la emisión fallara, el
     * cliente se quedaría sin credencial y sin forma de pedir otra que no sea la contraseña.
     */
    public function rotate(User $user, string $ip): ?NewAccessToken
    {
        // ⚠️ El tipo que declara Sanctum (`PersonalAccessToken`) es más estrecho que lo que llega: con
        // cookie de sesión es un `TransientToken`. Los dos cumplen `HasAbilities`, que es el tipo REAL.
        /** @var HasAbilities|null $current */
        $current = $user->currentAccessToken();

        if (! $current instanceof PersonalAccessToken) {
            return null;
        }

        return DB::transaction(function () use ($user, $current, $ip): NewAccessToken {
            $token = $user->createToken((string) $current->name, [self::ABILITY], $this->expiresAt());
            $user->revokeCurrentAccessToken();

            Log::info('auth.token_rotated', ['user_id' => $user->id, 'ip' => $ip]);

            return $token;
        });
    }

    private function expiresAt(): Carbon
    {
        $minutes = (int) config('sanctum.expiration');

        return now()->addMinutes($minutes > 0 ? $minutes : self::FALLBACK_LIFETIME);
    }
}
