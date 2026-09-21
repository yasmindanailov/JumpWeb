<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Exceptions\GoogleBusinessApiException;
use Illuminate\Http\Client\ConnectionException;
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
     * `reviews.list`. ⚠️ **Es la v4 y otro HOST**: las reseñas no viven donde las fichas, y su
     * `parent` es `accounts/{id}/locations/{id}`, no el `name` que devuelve `locations.list`.
     */
    public const REVIEWS_BASE = 'https://mybusiness.googleapis.com/v4/';

    /**
     * ⚠️ **`readMask` es OBLIGATORIO** en `locations.list`: sin él Google responde 400. Y se pide lo
     * justo (§4.2·4) — el nombre de recurso, el rótulo, la dirección, la web y el `metadata` de donde
     * salen `placeId`, `mapsUri` y `newReviewUri`. Pedir de más sería traer a casa datos del parque
     * que nadie va a usar.
     */
    public const LOCATION_READ_MASK = 'name,title,storefrontAddress,websiteUri,metadata';

    /**
     * Cuántas reseñas se piden por página (§4.3·1). Es el máximo que admite `reviews.list`, y pedir
     * el máximo es lo barato: la pasada recorre **todas** las páginas, así que una página pequeña no
     * trae menos datos — trae las mismas reseñas en más viajes contra una cuota compartida.
     */
    public const REVIEWS_PAGE_SIZE = 50;

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
     * **El resumen de reseñas de una ficha** (§4.2·10): la media y el total, tal y como los da Google.
     *
     * ⚠️⚠️ **Otra API y otra forma de nombrar la ficha.** Las reseñas son de la **v4**
     * (`mybusiness.googleapis.com`) y su `parent` es `accounts/{id}/locations/{id}`, mientras que
     * `locations.list` devuelve `locations/{id}` **a secas** —las dos cosas, comprobadas contra la
     * documentación oficial el 2026-09-20—. Pasarle el `name` de la ficha devuelve un 404 que se lee
     * como «ficha perdida» y manda a reconectar para nada.
     *
     * ⚠️ `pageSize=1`: aquí solo se quieren las CIFRAS, que Google manda al nivel de la respuesta.
     * Traerse cincuenta reseñas para contar sería pagar por lo que ya viene contado.
     *
     * @param  string  $parent  `accounts/{id}/locations/{id}` ({@see GoogleBusinessLocation::reviewsParent()})
     * @return array{averageRating: float|null, totalReviewCount: int}
     *
     * @throws GoogleBusinessApiException
     */
    public function reviewSummary(#[\SensitiveParameter] string $refreshToken, string $parent): array
    {
        $pagina = $this->reviews($refreshToken, $parent, null, 1);

        return [
            'averageRating' => $pagina['averageRating'],
            'totalReviewCount' => $pagina['totalReviewCount'],
        ];
    }

    /**
     * **Una página de reseñas de la ficha** (T2·2, §4.3·1 y §4.3·2).
     *
     * Devuelve las filas **crudas**, tal y como las manda Google: interpretarlas es de Content, que es
     * donde vive la sincronización (§4.0). Aquí solo se habla por HTTP.
     *
     * ⚠️⚠️ **La media y el total viajan en CADA página, no solo en la primera**, y por eso se
     * devuelven siempre: §4.3·3 compara los de la primera con los de la última para decidir si la
     * pasada fue coherente, y una pasada incoherente **no borra nada**. Quedarse solo con los de la
     * primera dejaría esa comprobación sin su otra mitad.
     *
     * ⚠️ `nextPageToken` se devuelve como `null` cuando no lo hay **o viene vacío**: la diferencia
     * entre «no hay más» y «hay más, pero con una cadena vacía» no existe para quien pagina, y
     * tratarlas distinto es un bucle infinito esperando.
     *
     * @param  string  $parent  `accounts/{id}/locations/{id}` ({@see GoogleBusinessLocation::reviewsParent()})
     * @return array{reviews: list<array<string,mixed>>, nextPageToken: string|null, averageRating: float|null, totalReviewCount: int}
     *
     * @throws GoogleBusinessApiException
     */
    public function reviews(
        #[\SensitiveParameter] string $refreshToken,
        string $parent,
        ?string $pageToken = null,
        int $pageSize = self::REVIEWS_PAGE_SIZE,
    ): array {
        $query = ['pageSize' => $pageSize];

        if ($pageToken !== null && $pageToken !== '') {
            $query['pageToken'] = $pageToken;
        }

        $body = $this->get($refreshToken, self::REVIEWS_BASE.$parent.'/reviews', $query);

        $siguiente = $body['nextPageToken'] ?? null;
        $media = $body['averageRating'] ?? null;
        $total = $body['totalReviewCount'] ?? null;

        return [
            'reviews' => is_array($body['reviews'] ?? null) ? array_values($body['reviews']) : [],
            'nextPageToken' => is_string($siguiente) && $siguiente !== '' ? $siguiente : null,
            'averageRating' => is_numeric($media) ? (float) $media : null,
            'totalReviewCount' => is_numeric($total) ? (int) $total : 0,
        ];
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
        // ⚠️ El token se pide FUERA del `send()` de la petición, no dentro de su cierre: dentro, el
        // `send()` de fuera taparía el suyo y ninguna prueba podría demostrar que existe (`#733`).
        $acceso = $this->accessTokenFor($refreshToken);
        $response = $this->send(fn (): Response => $this->client($acceso)->get($url, $query));

        if ($response->status() === 401) {
            // El de acceso caducó: se tira y se pide otro. Si el de REFRESCO fuera el caducado, la
            // petición de abajo lanzará con estado «caducada», que es lo correcto.
            $this->accessToken = null;
            $this->accessTokenExpiresAt = null;

            $acceso = $this->accessTokenFor($refreshToken);
            $response = $this->send(fn (): Response => $this->client($acceso)->get($url, $query));
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

        $response = $this->send(fn (): Response => Http::asForm()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->post(GoogleBusinessOAuth::TOKEN_ENDPOINT, [
                'client_id' => $credentials->clientId,
                'client_secret' => $credentials->secret(),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]));

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
     * Hace la petición y convierte **«Google no ha contestado»** en un fallo pasajero (`#733`).
     *
     * ❗❗ Sin esto un corte de red o un tiempo agotado salía como `ConnectionException`, que no es
     * una negativa de Google y **nadie de arriba la atrapaba**: la pantalla del panel daba un 500, la
     * pasada diaria reventaba y `verify` terminaba con una traza. Traducirla AQUÍ, en el envoltorio
     * único, es lo que hace que los cuatro sitios que llaman se porten igual sin tocar ninguno.
     *
     * ⚠️⚠️ **Solo `ConnectionException`, nunca un `catch (Throwable)`**: uno ancho se traga el
     * `StrayRequestException` de las pruebas y deja un caso en verde sin haber llamado a nada —pasó
     * en el descargador de fotos (`#730`)—.
     *
     * @param  callable(): Response  $peticion
     *
     * @throws GoogleBusinessApiException
     */
    private function send(callable $peticion): Response
    {
        try {
            return $peticion();
        } catch (ConnectionException) {
            $error = GoogleBusinessApiException::unreachable();

            // Mismo registro que una negativa, para que un corte se vea igual en el log. Sin el
            // mensaje de cURL: trae la URL, y la de una página de reseñas lleva el testigo.
            Log::warning('google_business.api_failed', [
                'http' => $error->httpStatus,
                'reason' => $error->reason,
                'estado' => null,
            ]);

            throw $error;
        }
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
