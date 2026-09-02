<?php

namespace Tests\Support;

use App\Domain\Identity\Services\GoogleAuth;
use App\Domain\Identity\Services\GoogleOAuth;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * **Conducir el retorno de Google de verdad** (`docs/specs/auth-con-google.md`).
 *
 * Lo usan los dos ficheros que necesitan una identidad verificada en la sesión: el del mecanismo
 * (T1) y el de la pantalla que completa el alta (T2).
 *
 * ⚠️⚠️ **No siembra la sesión a mano, y ésa es la propiedad.** Pide la IDA de verdad, lee el `state`
 * y el `nonce` del propio redirect a Google y vuelve con ellos, así que la custodia entre las dos
 * peticiones (§6.3·3) queda ejercitada. Sembrar `auth.google.profile` con `withSession()` habría
 * dejado sin probar justo la pieza que sostiene el diseño — y un renombrado de esa clave pasaría en
 * verde.
 *
 * Lo único simulado es **Google**: `Http::fake` sobre el endpoint de canje.
 */
trait DrivesGoogleAuth
{
    protected const GOOGLE_CLIENT_ID = '1023005524676-pruebas.apps.googleusercontent.com';

    protected const GOOGLE_CLIENT_SECRET = 'GOCSPX-secreto-de-pruebas';

    /** El `sub` de OpenID Connect: 21 dígitos, como los de Google. */
    protected const GOOGLE_SUB = '110000000000000000001';

    protected const GOOGLE_EMAIL = 'ana.google@example.com';

    protected function configureGoogleKeys(): void
    {
        Setting::updateOrCreate(['key' => GoogleAuth::CLIENT_ID_KEY], ['value' => self::GOOGLE_CLIENT_ID, 'group' => 'auth']);
        Setting::updateOrCreate(['key' => GoogleAuth::CLIENT_SECRET_KEY], ['value' => self::GOOGLE_CLIENT_SECRET, 'group' => 'auth']);
        Setting::flushMemo();
        GoogleAuth::flushCache();
    }

    /**
     * La IDA de verdad. Devuelve el reto que el servidor acaba de acuñar.
     *
     * @return array{state: string, nonce: string}
     */
    protected function startGoogleFlow(): array
    {
        $response = $this->get(route('auth.google.redirect'));
        $response->assertRedirect();

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return ['state' => (string) $query['state'], 'nonce' => (string) $query['nonce']];
    }

    /**
     * El `id_token` que devolverá el próximo canje. Vive aquí, y no dentro del stub, por lo de abajo.
     */
    private ?string $googleNextIdToken = null;

    /**
     * @param  array{state: string, nonce: string}  $flow
     * @param  array<string, mixed>  $claims  lo que se manipula del token
     */
    protected function fakeGoogleExchange(array $flow, array $claims = []): void
    {
        // ⚠️⚠️ **`Http::fake()` ACUMULA stubs y gana el PRIMERO que casa** (medido, `#347`): llamarlo
        // dos veces en el mismo caso no sustituye la respuesta, la deja detrás. Con eso, un caso que
        // recorre el flujo DOS veces —el de la idempotencia, o cualquiera con su control— recibía en
        // el segundo canje el token del PRIMER reto, con su `nonce` viejo, y el veredicto salía
        // `google-failed`. *Parece un defecto del producto y es el instrumento.*
        // ▶ La salida: el stub se registra UNA vez y lee esta propiedad **en el momento de la
        // llamada**, así que siempre devuelve el token del reto en curso.
        $this->googleNextIdToken = $this->googleIdToken($claims + ['nonce' => $flow['nonce']]);

        if ($this->googleExchangeFaked) {
            return;
        }

        $this->googleExchangeFaked = true;

        Http::fake([
            GoogleOAuth::TOKEN_ENDPOINT => fn () => Http::response([
                'access_token' => 'ya29.token-que-no-usamos',
                'expires_in' => 3599,
                'token_type' => 'Bearer',
                'id_token' => $this->googleNextIdToken,
            ]),
        ]);
    }

    /** Si el stub ya está puesto. Ver el aviso de `fakeGoogleExchange()`. */
    private bool $googleExchangeFaked = false;

    /** @param  array{state: string, nonce: string}  $flow */
    protected function returnFromGoogle(array $flow): TestResponse
    {
        return $this->get(route('auth.google.callback', [
            'state' => $flow['state'],
            'code' => 'codigo-de-un-solo-uso',
        ]));
    }

    /**
     * El recorrido entero: ida real, Google simulado y vuelta.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function enterWithGoogle(array $claims = []): TestResponse
    {
        $this->configureGoogleKeys();

        $flow = $this->startGoogleFlow();
        $this->fakeGoogleExchange($flow, $claims);

        return $this->returnFromGoogle($flow);
    }

    /**
     * **La IDA de VINCULAR** (`#347`), que es otra ruta y otra intención.
     *
     * ⚠️ Se pide de verdad, como la de entrar: la intención y el titular se anotan en el reto del
     * SERVIDOR, así que sembrar la sesión a mano dejaría sin ejercitar justo la pieza que impide que
     * el vínculo aterrice en la cuenta equivocada.
     *
     * @return array{state: string, nonce: string}
     */
    protected function startGoogleLinkFlow(): array
    {
        $response = $this->get(route('auth.google.link'));
        $response->assertRedirect();

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return ['state' => (string) $query['state'], 'nonce' => (string) $query['nonce']];
    }

    /**
     * El recorrido entero de VINCULAR desde la cuenta. Exige sesión abierta por quien llama.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function linkWithGoogle(array $claims = []): TestResponse
    {
        $this->configureGoogleKeys();

        $flow = $this->startGoogleLinkFlow();
        $this->fakeGoogleExchange($flow, $claims);

        return $this->returnFromGoogle($flow);
    }

    /**
     * Un `id_token` con la forma exacta de uno de Google.
     *
     * ⚠️ **La firma es basura a propósito**: por el canal servidor-a-servidor no se comprueba, y que
     * estos casos pasen con una firma inventada es la prueba de que lo que sostiene la confianza es
     * el CANAL, no el JWT.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function googleIdToken(array $claims): string
    {
        $payload = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::GOOGLE_CLIENT_ID,
            'sub' => self::GOOGLE_SUB,
            'email' => self::GOOGLE_EMAIL,
            'email_verified' => true,
            'name' => 'Ana Pérez',
            // Con el reloj del framework, como el validador: si el token se fechara con `time()` y la
            // suite corriera con `TEST_CLOCK` en otra fecha, estos casos saldrían caducados sin que
            // nada estuviera mal.
            'iat' => now()->getTimestamp() - 10,
            'exp' => now()->getTimestamp() + 3600,
        ], $claims);

        return implode('.', [
            $this->base64Url((string) json_encode(['alg' => 'RS256', 'kid' => 'de-prueba'])),
            $this->base64Url((string) json_encode($payload)),
            $this->base64Url('firma-que-este-canal-no-necesita'),
        ]);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
