<?php

namespace App\Domain\Platform\Services;

/**
 * **Una ficha de Google, ya saneada** (`docs/specs/google-business-profile.md` §4.2·4 y §5·SEC-07).
 *
 * Nace de una respuesta de la API y existe para que **el saneado ocurra UNA vez, donde nace el dato**,
 * y no en cada sitio que lo pinta. Lo que sale de aquí ya se puede poner en un `href` de la portada
 * del parque sin volver a pensarlo.
 *
 * ⚠️⚠️ **Más estricto que `AppServiceProvider::safeExternalUrl`, a propósito.** Aquél admite
 * `http://` y cualquier host, porque protege un campo que un admin escribió en un formulario que ya
 * valida. Éstas llegan de **una respuesta de red**: nadie las ha mirado, van a un enlace público, y lo
 * único que las hace de fiar es que **el host esté en la lista**. Todo lo demás cae a `null`, que la
 * vista ya sabe tratar (§4.3·10: si no hay enlace, no se pinta el enlace).
 *
 * ⚠️ **Los acortadores quedan FUERA de la lista** aunque sean de Google (`maps.app.goo.gl`): en un
 * acortador, «es un host de Google» **no implica** «lleva a Google», que es justo lo que la lista
 * blanca pretende garantizar.
 */
final readonly class GoogleBusinessLocation
{
    /**
     * Hosts de Google que pueden acabar en un enlace de la portada. **Coincidencia EXACTA**, nunca por
     * sufijo: `google.com.lo-que-sea.net` termina en algo que un `str_ends_with` mal escrito daría por
     * bueno, y es el truco más viejo del oficio.
     *
     * @var list<string>
     */
    private const GOOGLE_HOSTS = [
        'maps.google.com',
        'www.google.com',
        'google.com',
        'search.google.com',
        'business.google.com',
        'g.page',
    ];

    private function __construct(
        /** El nombre de RECURSO (`locations/123…`): es por donde Google la identifica. */
        public string $name,
        public string $title,
        public ?string $placeId,
        public ?string $mapsUri,
        public ?string $newReviewUri,
        /**
         * La web que el parque tiene puesta en su ficha. **No se pinta nunca**: existe solo para la
         * comprobación de host del §4.2·4.
         */
        public ?string $websiteUri,
        /** Dirección en una línea, para que el admin distinga dos fichas parecidas al elegir. */
        public ?string $address,
    ) {}

    /**
     * @param  array<string,mixed>  $row  una entrada de `locations.list`
     * @return self|null `null` si no trae nombre de recurso: sin él no hay ficha que pedir después.
     */
    public static function fromApi(array $row): ?self
    {
        $name = $row['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];

        return new self(
            name: trim($name),
            title: is_string($row['title'] ?? null) ? trim($row['title']) : trim($name),
            placeId: is_string($metadata['placeId'] ?? null) && $metadata['placeId'] !== '' ? $metadata['placeId'] : null,
            mapsUri: self::googleUrl($metadata['mapsUri'] ?? null),
            newReviewUri: self::googleUrl($metadata['newReviewUri'] ?? null),
            websiteUri: is_string($row['websiteUri'] ?? null) && trim($row['websiteUri']) !== '' ? trim($row['websiteUri']) : null,
            address: self::address($row['storefrontAddress'] ?? null),
        );
    }

    /**
     * ¿La web de esta ficha es la de este sitio? (§4.2·4)
     *
     * ⚠️ **Es la guarda contra conectar la ficha EQUIVOCADA**, que en un administrador con varias
     * cuentas no es raro: un descuido publicaría en la portada de un parque las reseñas de otro.
     *
     * ⚠️ `www.` no cuenta: una ficha con `https://www.parque.es` y un sitio en `parque.es` son el
     * mismo negocio, y rechazarlo sería un falso positivo garantizado.
     */
    public function matchesHost(string $siteHost): bool
    {
        $suyo = self::normalizeHost((string) parse_url((string) $this->websiteUri, PHP_URL_HOST));
        $nuestro = self::normalizeHost($siteHost);

        return $suyo !== '' && $nuestro !== '' && $suyo === $nuestro;
    }

    /** El host del sitio, tal y como lo declara la instalación. */
    public static function siteHost(): string
    {
        return self::normalizeHost((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    private static function normalizeHost(string $host): string
    {
        $host = mb_strtolower(trim($host));

        return str_starts_with($host, 'www.') ? mb_substr($host, 4) : $host;
    }

    /**
     * `https` **y** host en la lista, o `null`. Sin excepciones y sin normalizar «casi»: si no se
     * reconoce, no se publica.
     */
    private static function googleUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $url = trim($value);
        $parts = parse_url($url);

        if (! is_array($parts) || mb_strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }

        $host = mb_strtolower((string) ($parts['host'] ?? ''));

        return in_array($host, self::GOOGLE_HOSTS, true) ? $url : null;
    }

    /**
     * La dirección en una línea. Solo para que el admin sepa cuál elige; no se publica.
     *
     * @param  mixed  $address  el `storefrontAddress` estructurado de Google
     */
    private static function address(mixed $address): ?string
    {
        if (! is_array($address)) {
            return null;
        }

        $lineas = is_array($address['addressLines'] ?? null)
            ? array_filter($address['addressLines'], 'is_string')
            : [];

        $piezas = array_filter([
            implode(', ', $lineas),
            is_string($address['postalCode'] ?? null) ? $address['postalCode'] : null,
            is_string($address['locality'] ?? null) ? $address['locality'] : null,
        ], static fn (?string $pieza): bool => $pieza !== null && trim($pieza) !== '');

        return $piezas === [] ? null : implode(' · ', $piezas);
    }
}
