<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Exceptions\GoogleBusinessException;
use Illuminate\Support\Facades\Http;

/**
 * **El canje del permiso sobre la ficha** (`docs/specs/google-business-profile.md` §4.2·2 y §4.2·3).
 *
 * Primo de `Identity\Services\GoogleOAuth`, y con **tres diferencias que son el diseño entero**:
 *
 *  1. **Se pide acceso `offline`.** Aquél no guardaba nada porque no llamaba a ninguna API de Google;
 *     éste existe justo para llamarla todos los días sin nadie delante, así que lo que se busca es el
 *     **token de refresco**. El de acceso se usa y se tira.
 *  2. **PKCE S256.** No lo había en el repo. El `code_verifier` no sale del servidor: a Google solo
 *     viaja su `sha256`, y el canje lo presenta para demostrar que quien canjea es quien pidió.
 *  3. **No se lee ningún `id_token`.** Aquí no se afirma quién es nadie —el admin ya entró por su
 *     cuenta del panel—; lo único que se obtiene es un permiso sobre una ficha.
 *
 * ⚠️⚠️ **`prompt=consent` es obligatorio, no una preferencia.** Google entrega el token de refresco
 * **solo** con un consentimiento nuevo: si la cuenta ya había autorizado antes, sin él la vuelta trae
 * un token de acceso y nada más, y la conexión moriría en una hora sin que nada fallara hoy.
 */
final class GoogleBusinessOAuth
{
    public const AUTHORIZE_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    /** Dónde se canjea. **Solo el servidor habla con esta URL.** */
    public const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /** Dónde se retira el permiso (§4.2·8). El token va en el CUERPO, nunca en la URL. */
    public const REVOKE_ENDPOINT = 'https://oauth2.googleapis.com/revoke';

    /**
     * ⚠️ **Un solo ámbito, y es SENSIBLE** (§4.2·3): Google no publica uno de solo lectura para las
     * reseñas, así que el mismo permiso que deja leerlas deja editar la ficha. De ahí que toda
     * escritura exija un gesto del admin y un interruptor (§4.4·4): el ámbito no nos protege.
     */
    public const SCOPE = 'https://www.googleapis.com/auth/business.manage';

    private const TIMEOUT_SECONDS = 10;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /**
     * A dónde se manda al admin para que Google le pregunte.
     *
     * @param  string  $verifier  el `code_verifier` de PKCE. **Se queda en el servidor**: aquí solo se
     *                            usa para calcular el reto que viaja.
     *
     * @throws GoogleBusinessException
     */
    public function authorizationUrl(string $state, #[\SensitiveParameter] string $verifier, string $redirectUri): string
    {
        $credentials = GoogleBusinessCredentials::fresh();

        if (! $credentials->configured()) {
            throw GoogleBusinessException::because(GoogleBusinessException::NOT_CONFIGURED);
        }

        return self::AUTHORIZE_ENDPOINT.'?'.http_build_query([
            'client_id' => $credentials->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
            'code_challenge' => self::challengeFor($verifier),
            'code_challenge_method' => 'S256',
            // Sin esto no hay token de refresco que guardar.
            'access_type' => 'offline',
            // `consent` fuerza la pantalla de permisos aunque la cuenta ya hubiera autorizado, que es
            // lo único que hace que Google vuelva a emitir el token de refresco. `select_account`
            // evita que una sesión de Google abierta en el navegador del parque decida por el admin
            // con qué cuenta se conecta la ficha.
            'prompt' => 'consent select_account',
            // Sin él, desmarcar el permiso en la pantalla granular de Google devolvería un `scope`
            // vacío y tendríamos que adivinar; con él, Google siempre dice qué concedió.
            'include_granted_scopes' => 'false',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Canjea el código por el **token de refresco**, o lanza.
     *
     * El orden de las comprobaciones es la defensa: primero que Google atendiera el canje, después que
     * concediera el ámbito que necesitamos, y **solo entonces** que haya token de refresco. Al revés,
     * un consentimiento a medias se guardaría como una conexión buena.
     *
     * @param  string  $redirectUri  **el MISMO** que se envió al pedir el código: Google lo compara y
     *                               rechaza el canje si no coincide al carácter.
     * @return string el token de refresco
     *
     * @throws GoogleBusinessException
     */
    public function refreshTokenFromCode(
        #[\SensitiveParameter] string $code,
        #[\SensitiveParameter] string $verifier,
        string $redirectUri,
    ): string {
        $credentials = GoogleBusinessCredentials::fresh();

        if (! $credentials->configured()) {
            throw GoogleBusinessException::because(GoogleBusinessException::NOT_CONFIGURED);
        }

        // Sin reintento a propósito: el código es de UN SOLO USO, y un segundo intento sobre un canje
        // que Google ya atendió falla igual.
        $response = Http::asForm()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->post(self::TOKEN_ENDPOINT, [
                'code' => $code,
                'client_id' => $credentials->clientId,
                'client_secret' => $credentials->secret(),
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
                'code_verifier' => $verifier,
            ]);

        if (! $response->successful()) {
            // ⚠️ Solo el estado y el código `error` de Google. El cuerpo entero llevaría material
            // (§4.2·6) y acabaría en el log y en `failed_jobs`.
            throw GoogleBusinessException::because(
                GoogleBusinessException::TOKEN_EXCHANGE_FAILED,
                'HTTP '.$response->status().' · '.(string) $response->json('error', '')
            );
        }

        if (! self::grants($response->json('scope'))) {
            throw GoogleBusinessException::because(GoogleBusinessException::SCOPE_NOT_GRANTED);
        }

        $refreshToken = $response->json('refresh_token');

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw GoogleBusinessException::because(GoogleBusinessException::MISSING_REFRESH_TOKEN);
        }

        return $refreshToken;
    }

    /**
     * ¿El `scope` que Google dice haber concedido incluye el nuestro?
     *
     * ⚠️ Google lo devuelve como una lista separada por espacios y **puede traer más de los pedidos**.
     * Se compara elemento a elemento: un `str_contains` daría por bueno un ámbito que solo empiece
     * igual, y aquí lo que hay en juego es el permiso de escritura sobre la ficha del parque.
     *
     * @param  mixed  $granted
     */
    public static function grants($granted): bool
    {
        if (! is_string($granted) || trim($granted) === '') {
            return false;
        }

        return in_array(self::SCOPE, preg_split('/\s+/', trim($granted)) ?: [], true);
    }

    /** El reto de PKCE: `base64url(sha256(verifier))`, sin relleno (RFC 7636 §4.2). */
    private static function challengeFor(#[\SensitiveParameter] string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
