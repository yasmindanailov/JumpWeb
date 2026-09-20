<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Exceptions\GoogleBusinessApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * **El único sitio del producto que habla con la API de la ficha**
 * (`docs/specs/google-business-profile.md` §4.2·6 y §4.2·10).
 *
 * Es el «único envoltorio HTTP» que pide el §4.2·6, y ser único es la mitad del valor: mientras toda
 * llamada pase por aquí, **hay un solo sitio donde comprobar que un token no acaba en un log**. Diez
 * llamadas repartidas serían diez sitios donde el descuido de uno se lleva la credencial de todos los
 * parques.
 *
 * ⚠️⚠️ **El token de acceso vive SOLO en memoria de este objeto** (§4.2·5). No se guarda, no se
 * serializa y no sale en un volcado: se pide una vez por pasada con margen y se tira con el proceso.
 * Lo que se custodia es el de refresco, cifrado y en su tabla.
 *
 * ⚠️ **Del fallo se registra el ESTADO y la razón corta de Google, nunca el cuerpo**: una respuesta de
 * error de Google puede traer de vuelta trozos de lo que se le mandó.
 */
final class GoogleBusinessApi
{
    /** `accounts.list` — qué cuentas de Google administra quien conectó. */
    public const ACCOUNTS_ENDPOINT = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';

    /** `locations.list` — las fichas de una cuenta. Se completa con el nombre de recurso de la cuenta. */
    public const LOCATIONS_BASE = 'https://mybusinessbusinessinformation.googleapis.com/v1/';

    /**
     * ⚠️ **`readMask` es OBLIGATORIO** en `locations.list`: sin él Google responde 400. Y se pide lo
     * justo (§4.2·4) — el nombre de recurso, el rótulo, la dirección, la web y el `metadata` de donde
     * salen `placeId`, `mapsUri` y `newReviewUri`. Pedir de más sería traer a casa datos del parque
     * que nadie va a usar.
     */
    public const LOCATION_READ_MASK = 'name,title,storefrontAddress,websiteUri,metadata';

    /**
     * Margen con el que se considera caducado el token de acceso. Google los emite para una hora; con
     * cinco minutos de colchón, una pasada larga no se queda a medias por un segundo de diferencia
     * entre su reloj y el nuestro.
     */
    private const EXPIRY_MARGIN_SECONDS = 300;

    private const TIMEOUT_SECONDS = 15;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /** Solo en memoria. Nunca una propiedad de un job ni de un componente de Livewire. */
    private ?string $accessToken = null;

    private ?int $accessTokenExpiresAt = null;

    /**
     * Las cuentas que administra el token.
     *
     * @return list<array<string,mixed>>
     *
     * @throws GoogleBusinessApiException
     */
    public function accounts(#[\SensitiveParameter] string $refreshToken): array
    {
        $body = $this->get($refreshToken, self::ACCOUNTS_ENDPOINT);

        return is_array($body['accounts'] ?? null) ? array_values($body['accounts']) : [];
    }

    /**
     * Las fichas de una cuenta.
     *
     * @param  string  $account  el nombre de RECURSO (`accounts/123…`), tal y como lo devuelve Google.
     * @return list<array<string,mixed>>
     *
     * @throws GoogleBusinessApiException
     */
    public function locations(#[\SensitiveParameter] string $refreshToken, string $account): array
    {
        $body = $this->get($refreshToken, self::LOCATIONS_BASE.$account.'/locations', [
            'readMask' => self::LOCATION_READ_MASK,
            'pageSize' => 100,
        ]);

        return is_array($body['locations'] ?? null) ? array_values($body['locations']) : [];
    }

    /**
     * Todas las fichas que el token alcanza, de todas sus cuentas.
     *
     * ⚠️ **Es lo que sostiene la revalidación del §4.2·4**: «la ficha está en el `locations.list` de
     * ESE token» no se puede comprobar contra una sola cuenta, porque un administrador puede tener
     * varias y la ficha del parque vivir en cualquiera.
     *
     * @return list<array<string,mixed>>
     *
     * @throws GoogleBusinessApiException
     */
    public function allLocations(#[\SensitiveParameter] string $refreshToken): array
    {
        $fichas = [];

        foreach ($this->accounts($refreshToken) as $cuenta) {
            $nombre = $cuenta['name'] ?? null;

            if (is_string($nombre) && $nombre !== '') {
                $fichas = array_merge($fichas, $this->locations($refreshToken, $nombre));
            }
        }

        return $fichas;
    }

    /**
     * Una GET autenticada, con **un único reintento ante un 401** (§4.2·5).
     *
     * ⚠️ El reintento es exactamente uno y solo para el 401: significa «el token de acceso caducó
     * mientras hablábamos», que se arregla pidiendo otro. Reintentar un 403 o un 404 no arregla nada
     * y multiplica las llamadas contra una cuota compartida entre todos los parques.
     *
     * @param  array<string,mixed>  $query
     * @return array<string,mixed>
     *
     * @throws GoogleBusinessApiException
     */
    private function get(#[\SensitiveParameter] string $refreshToken, string $url, array $query = []): array
    {
        $response = $this->client($this->accessTokenFor($refreshToken))->get($url, $query);

        if ($response->status() === 401) {
            // El de acceso caducó: se tira y se pide otro. Si el de REFRESCO fuera el caducado, la
            // petición de abajo lanzará con estado «caducada», que es lo correcto.
            $this->accessToken = null;
            $this->accessTokenExpiresAt = null;

            $response = $this->client($this->accessTokenFor($refreshToken))->get($url, $query);
        }

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * El token de acceso, pidiéndolo si hace falta.
     *
     * @throws GoogleBusinessApiException
     */
    private function accessTokenFor(#[\SensitiveParameter] string $refreshToken): string
    {
        if ($this->accessToken !== null
            && $this->accessTokenExpiresAt !== null
            && $this->accessTokenExpiresAt > now()->getTimestamp()
        ) {
            return $this->accessToken;
        }

        $credentials = GoogleBusinessCredentials::fresh();

        if (! $credentials->configured()) {
            throw GoogleBusinessApiException::from(401, 'invalid_client');
        }

        $response = Http::asForm()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->post(GoogleBusinessOAuth::TOKEN_ENDPOINT, [
                'client_id' => $credentials->clientId,
                'client_secret' => $credentials->secret(),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful()) {
            throw $this->failure($response);
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            // Un 200 sin token es Google contestando algo que no entendemos: pasajero, no avería.
            throw GoogleBusinessApiException::from(502, 'missing_access_token');
        }

        $vida = $response->json('expires_in');
        $this->accessToken = $token;
        $this->accessTokenExpiresAt = now()->getTimestamp()
            + max(0, (is_int($vida) ? $vida : 3600) - self::EXPIRY_MARGIN_SECONDS);

        return $token;
    }

    /**
     * ⚠️⚠️ **El token va en la CABECERA, jamás en la URL** (`?access_token=`). Una URL se registra en
     * el log del servidor, en el del proxy y en el historial de cualquier herramienta por medio; una
     * cabecera `Authorization`, no.
     */
    private function client(#[\SensitiveParameter] string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS);
    }

    /**
     * Convierte una respuesta fallida en la excepción que dice qué clase de «no» es.
     *
     * ⚠️ **Del cuerpo solo se toca `error.status` / `error`**, y solo eso llega al log: el resto de la
     * respuesta de Google puede traer de vuelta trozos de lo que se le mandó (§4.2·6).
     */
    private function failure(Response $response): GoogleBusinessApiException
    {
        $reason = $response->json('error.status')
            ?? $response->json('error')
            ?? '';

        // Cuando `error` es un objeto, `json('error')` devuelve el array entero: nos quedamos con su
        // `status`, y si tampoco lo hay, con nada. Nunca se serializa el array al log.
        $reason = is_string($reason) ? $reason : '';

        $error = GoogleBusinessApiException::from($response->status(), $reason);

        Log::warning('google_business.api_failed', [
            'http' => $error->httpStatus,
            'reason' => $error->reason,
            'estado' => $error->status?->value,
        ]);

        return $error;
    }
}
