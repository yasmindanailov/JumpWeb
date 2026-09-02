<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\SocialProfile;
use App\Domain\Identity\Exceptions\GoogleAuthException;
use Illuminate\Support\Facades\Http;

/**
 * **La raíz de confianza** (`docs/specs/auth-con-google.md` §6.3): lo único que puede convertir un
 * retorno de Google en una afirmación creíble sobre una persona.
 *
 * ## Por qué esto no es «descodificar un token»
 *
 * ⚠️⚠️ Un `id_token` es un JWT: **cualquiera puede fabricar uno** que afirme el correo que quiera con
 * `email_verified: true`. Si esta clase se limitara a descodificarlo, la feature entera sería una
 * puerta abierta a la cuenta de quien se quisiera. Lo que hace que la afirmación valga algo es de
 * dónde VIENE el token, y aquí viene de un sitio y solo de uno:
 *
 *  - **Flujo *Authorization Code* con canje SERVIDOR-A-SERVIDOR.** El navegador nos trae un `code`
 *    de un solo uso que por sí mismo no afirma nada; el `id_token` lo pedimos nosotros a
 *    `oauth2.googleapis.com` por TLS, **autenticando la petición con nuestro `client_secret`**, que
 *    solo tenemos nosotros y Google.
 *  - **Nada que llegue del navegador se acepta como token.** No hay ni una rama que lea un
 *    `id_token` de la petición. Si algún día se añadiera —One Tap, una app nativa—, ese camino
 *    NECESITA además verificar la FIRMA contra las claves públicas de Google, porque entonces el
 *    token ya no vendría por un canal autenticado. Está escrito aquí para que nadie añada la mitad.
 *
 * Sobre ese canal, OpenID Connect Core §3.1.3.7·1 permite no validar la firma; aun así se comprueban
 * **emisor, destinatario, caducidad y `nonce`**, que es lo que hace que un token capturado de otra
 * aplicación o de otra petición no sirva aquí.
 *
 * ⚠️ **El `access_token` se ignora a propósito**: no llamamos a ninguna API de Google, así que
 * guardarlo sería custodiar una credencial que no necesitamos. Pedimos el acceso `online` justo por
 * eso — sin `refresh_token` que conservar.
 */
final class GoogleOAuth
{
    /** A dónde se manda al visitante. */
    public const AUTHORIZE_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    /** Dónde se canjea el código. **Solo el servidor habla con esta URL.** */
    public const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * Los dos emisores que Google usa en sus `id_token`, con y sin esquema. Los dos son válidos y
     * están documentados; aceptar solo uno rompería el día que Google emita el otro.
     *
     * @var list<string>
     */
    public const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    /**
     * ⚠️ **Los tres ámbitos mínimos y ninguno más** (§10): cualquier ámbito sensible dispara una
     * verificación de semanas de Google, y no necesitamos ninguno. `profile` trae además `picture`,
     * `given_name` y `locale`, que {@see SocialProfile} descarta (art. 5.1.c).
     */
    public const SCOPES = 'openid email profile';

    /** Tolerancia de reloj entre nuestro servidor y el de Google. Dos minutos es lo habitual. */
    private const CLOCK_LEEWAY_SECONDS = 120;

    private const TIMEOUT_SECONDS = 10;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /**
     * A dónde se manda al visitante para que Google le pregunte.
     *
     * ⚠️ `prompt=select_account`: sin él, un dispositivo compartido entra con la última cuenta de
     * Google que quedó abierta **sin preguntar**, que es exactamente el modo de fallo que
     * `SidebarEntry::clear()` existe para limitar. Que la persona vea con qué cuenta entra es parte
     * del diseño, no una preferencia.
     */
    public function authorizationUrl(string $state, string $nonce, string $redirectUri): string
    {
        $clientId = GoogleAuth::clientId();

        if ($clientId === null) {
            throw GoogleAuthException::because(GoogleAuthException::NOT_CONFIGURED);
        }

        return self::AUTHORIZE_ENDPOINT.'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
            'nonce' => $nonce,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Canjea el código por la identidad, o lanza.
     *
     * @param  string  $redirectUri  **el MISMO** que se envió al pedir el código: Google lo compara y
     *                               rechaza el canje si no coincide al carácter.
     * @param  string  $nonce  el que esta sesión generó. Ata el token a ESTA petición.
     *
     * @throws GoogleAuthException
     */
    public function profileFromCode(string $code, string $redirectUri, string $nonce): SocialProfile
    {
        $clientId = GoogleAuth::clientId();
        $clientSecret = GoogleAuth::clientSecret();

        if ($clientId === null || $clientSecret === null) {
            throw GoogleAuthException::because(GoogleAuthException::NOT_CONFIGURED);
        }

        // Sin reintento a propósito: el código es de UN SOLO USO. Un segundo intento sobre un canje
        // que Google ya atendió falla igual y solo añade latencia a una persona que está esperando.
        $response = Http::asForm()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->post(self::TOKEN_ENDPOINT, [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            throw GoogleAuthException::because(
                GoogleAuthException::TOKEN_EXCHANGE_FAILED,
                'HTTP '.$response->status().' · '.(string) $response->json('error', '')
            );
        }

        $idToken = $response->json('id_token');

        if (! is_string($idToken) || $idToken === '') {
            throw GoogleAuthException::because(GoogleAuthException::MISSING_ID_TOKEN);
        }

        return $this->profileFromIdToken($idToken, $clientId, $nonce);
    }

    /**
     * Comprueba el token y extrae la identidad.
     *
     * ⚠️ **Es `private`, y eso es la mitad de la garantía**: la única forma de llegar aquí es el
     * canje de arriba. Hacerlo público sería la puerta por la que entraría un token del navegador.
     *
     * @throws GoogleAuthException
     */
    private function profileFromIdToken(string $idToken, string $clientId, string $nonce): SocialProfile
    {
        $claims = $this->claims($idToken);

        $issuer = $claims['iss'] ?? null;
        if (! is_string($issuer) || ! in_array($issuer, self::ISSUERS, true)) {
            throw GoogleAuthException::because(GoogleAuthException::BAD_ISSUER);
        }

        // El destinatario. **La comprobación que impide usar aquí un token emitido para otra
        // aplicación**: sin ella, cualquiera con un cliente de Google propio podría hacerse pasar por
        // cualquier persona en esta instalación.
        if (! $this->audienceMatches($claims['aud'] ?? null, $clientId)) {
            throw GoogleAuthException::because(GoogleAuthException::BAD_AUDIENCE);
        }

        $now = time();

        $expiresAt = $claims['exp'] ?? null;
        if (! is_int($expiresAt) || $expiresAt + self::CLOCK_LEEWAY_SECONDS < $now) {
            throw GoogleAuthException::because(GoogleAuthException::EXPIRED, 'exp');
        }

        $issuedAt = $claims['iat'] ?? null;
        if (! is_int($issuedAt) || $issuedAt - self::CLOCK_LEEWAY_SECONDS > $now) {
            throw GoogleAuthException::because(GoogleAuthException::EXPIRED, 'iat en el futuro');
        }

        // El `nonce` ata el token a ESTA petición: uno capturado de otra sesión de la misma
        // aplicación no vale. `hash_equals` porque es una comparación de un valor secreto.
        $tokenNonce = $claims['nonce'] ?? null;
        if (! is_string($tokenNonce) || $nonce === '' || ! hash_equals($nonce, $tokenNonce)) {
            throw GoogleAuthException::because(GoogleAuthException::BAD_NONCE);
        }

        $subject = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;
        if (! is_string($subject) || $subject === '' || ! is_string($email) || trim($email) === '') {
            throw GoogleAuthException::because(GoogleAuthException::INCOMPLETE_CLAIMS);
        }

        // `email_verified` llega como booleano de verdad; se acepta también la cadena porque algunos
        // proveedores la serializan así. **Solo el `true` explícito cuenta como verificado**: todo lo
        // demás —ausente, nulo, `0`, texto raro— es «no verificado», que es la respuesta segura.
        $verified = ($claims['email_verified'] ?? null) === true || ($claims['email_verified'] ?? null) === 'true';

        $name = $claims['name'] ?? null;

        return SocialProfile::google($subject, $email, $verified, is_string($name) ? $name : null);
    }

    /**
     * La carga del JWT, ya descodificada.
     *
     * @return array<string, mixed>
     *
     * @throws GoogleAuthException
     */
    private function claims(string $idToken): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw GoogleAuthException::because(GoogleAuthException::MALFORMED_ID_TOKEN, 'no son tres segmentos');
        }

        $payload = $this->base64UrlDecode($parts[1]);

        if ($payload === null) {
            throw GoogleAuthException::because(GoogleAuthException::MALFORMED_ID_TOKEN, 'base64url ilegible');
        }

        $claims = json_decode($payload, true);

        if (! is_array($claims)) {
            throw GoogleAuthException::because(GoogleAuthException::MALFORMED_ID_TOKEN, 'la carga no es un objeto JSON');
        }

        return $claims;
    }

    /**
     * ¿El token está emitido para NOSOTROS?
     *
     * Acepta la forma que Google usa —una cadena— y, por si acaso, un array de UN solo elemento que
     * sea el nuestro. **Un array con varios destinatarios se rechaza**: OpenID Connect obligaría
     * entonces a comprobar `azp`, y aceptar el caso sin comprobarlo sería aflojar justo la guarda que
     * sostiene todo esto.
     *
     * @param  mixed  $audience
     */
    private function audienceMatches($audience, string $clientId): bool
    {
        if (is_string($audience)) {
            return hash_equals($clientId, $audience);
        }

        if (is_array($audience) && count($audience) === 1 && is_string($audience[0] ?? null)) {
            return hash_equals($clientId, $audience[0]);
        }

        return false;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
