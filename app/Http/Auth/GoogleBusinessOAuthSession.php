<?php

namespace App\Http\Auth;

/**
 * **La custodia entre la ida y la vuelta** de conectar la ficha de Google
 * (`docs/specs/google-business-profile.md` §4.2·2).
 *
 * Hermana de {@see GoogleAuthSession} y **deliberadamente separada de ella**: aquélla guarda el reto
 * de un VISITANTE que entra en su cuenta, y ésta el de un ADMIN que va a entregar un permiso sobre la
 * ficha del parque. Compartir clave de sesión haría que abrir uno pisara el otro —el admin del parque
 * también es un usuario que entra— y que un reto de entrar valiera para conectar la ficha.
 *
 * Guarda tres cosas:
 *  - el **`state`**, que demuestra que esta vuelta corresponde a nuestra ida;
 *  - el **`code_verifier` de PKCE**, que no sale del servidor **nunca** (a Google solo viaja su hash);
 *  - el **`holder`**, el usuario del panel que la pidió.
 *
 * ⚠️⚠️ **El `holder` no es ceremonia.** Entre la ida y la vuelta caben un `logout` y un `login` con
 * otra cuenta; sin él, el token de refresco de la ficha del parque quedaría anotado a nombre de quien
 * volviera, y el rastro de «quién conectó» diría una mentira que nadie podría descubrir después.
 *
 * ⚠️ **Un solo uso y con caducidad**, como su hermana: el reto se borra SIEMPRE al comprobarlo, acierte
 * o falle. Un reto que sobreviviera a un intento fallido sería un reto reutilizable, que es justo lo
 * que el `state` existe para impedir. Las horas, con el reloj del FRAMEWORK (`now()`) y nunca `time()`,
 * o la caducidad queda fuera del alcance de `travel()` y **no se puede probar**.
 */
final readonly class GoogleBusinessOAuthSession
{
    /** Clave PROPIA. Ver el docblock: compartirla con la de entrar sería el defecto. */
    private const CHALLENGE_KEY = 'google_business.oauth.challenge';

    /**
     * Lo que puede tardar un admin en elegir cuenta, leer la pantalla de permisos de Google y aceptar.
     * Quince minutos, como el de entrar: holgado para el caso normal y corto para cualquier otra cosa.
     */
    public const CHALLENGE_TTL_SECONDS = 900;

    /**
     * Abre el reto y devuelve lo que hay que mandarle a Google.
     *
     * El `code_verifier` de PKCE son 43 caracteres del alfabeto que permite el RFC 7636 §4.1, sacados
     * de `random_bytes` explícito y no de un ayudante de cadenas: es un secreto de un solo uso y quien
     * lea esto tiene que ver de dónde sale su aleatoriedad.
     *
     * @return array{state: string, verifier: string}
     */
    public static function start(int $holder): array
    {
        $challenge = [
            'state' => bin2hex(random_bytes(32)),
            'verifier' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            'holder' => $holder,
            'at' => now()->getTimestamp(),
        ];

        session([self::CHALLENGE_KEY => $challenge]);

        return ['state' => $challenge['state'], 'verifier' => $challenge['verifier']];
    }

    /**
     * Comprueba el `state` de la vuelta y **consume el reto pase lo que pase**.
     *
     * @return array{verifier: string, holder: int}|null `null` = esta vuelta no corresponde a ninguna
     *                                                   ida nuestra: no se hace nada.
     */
    public static function consume(?string $state): ?array
    {
        $challenge = session(self::CHALLENGE_KEY);
        session()->forget(self::CHALLENGE_KEY);

        if (! is_array($challenge) || ! is_string($state) || $state === '') {
            return null;
        }

        $stored = $challenge['state'] ?? null;
        $verifier = $challenge['verifier'] ?? null;
        $holder = $challenge['holder'] ?? null;
        $at = $challenge['at'] ?? null;

        if (! is_string($stored) || ! is_string($verifier) || ! is_int($holder) || ! is_int($at)) {
            return null;
        }

        if ($at + self::CHALLENGE_TTL_SECONDS < now()->getTimestamp()) {
            return null;
        }

        if (! hash_equals($stored, $state)) {
            return null;
        }

        return ['verifier' => $verifier, 'holder' => $holder];
    }
}
