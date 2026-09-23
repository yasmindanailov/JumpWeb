<?php

namespace App\Domain\Platform\Services\Analytics;

/**
 * **Cómo se guarda una URL en el libro: como PATRÓN, nunca entera** (`docs/specs/analitica.md` §4.1).
 *
 * ⚠️⚠️ **Una URL lleva credenciales**: el token de una invitación (`/invitacion/{token}`), el de un
 * restablecimiento de contraseña, la firma HMAC y la caducidad de un enlace firmado, el correo en la query
 * de un enlace de recuperación. Guardarlas 25 meses —y mandarlas a un tercero con consentimiento— sería
 * regalar una puerta a quien lea el panel (spec §7.1, seguridad-2). Por eso:
 *
 *  - de la QUERY solo sobreviven las claves de atribución de la lista blanca (`utm_*`, `ref`, click ids),
 *    y saneadas: alfabeto acotado y longitud tope, porque el panel las pinta y las exporta;
 *  - del PATH se enmascaran los segmentos numéricos (`{n}`) y los opacos largos (`{token}`);
 *  - el resto se recorta a 255 bytes.
 *
 * Se aplica en el emisor Y en el servidor: la defensa es doble a propósito, y la del servidor es la que
 * cuenta (el emisor de una landing puede ser antiguo).
 */
final class RouteNormalizer
{
    /** @var list<string> */
    public const QUERY_ALLOWLIST = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'ref', 'gclid', 'fbclid', 'ttclid'];

    /** Un segmento así de largo y sin espacios es un token, no una página. */
    private const TOKEN_MIN_LENGTH = 20;

    /**
     * La ruta como patrón: `/invitacion/{token}`, `/reservas/{n}/invitados`, `/entradas`.
     */
    public static function path(string $pathOrUrl): string
    {
        $path = (string) (parse_url($pathOrUrl, PHP_URL_PATH) ?: '/');
        $path = rawurldecode($path);

        $segments = array_map(static function (string $segment): string {
            if ($segment === '') {
                return $segment;
            }
            if (preg_match('/^\d+$/', $segment) === 1) {
                return '{n}';
            }
            // Un token mezcla letras y CIFRAS (hex, base64, un ULID); un slug largo como
            // «restablecer-contrasena» no lleva ninguna cifra y es una página, no una credencial.
            if (strlen($segment) >= self::TOKEN_MIN_LENGTH
                && preg_match('/^[A-Za-z0-9_\-.=]+$/', $segment) === 1
                && preg_match('/\d/', $segment) === 1
                && preg_match('/[A-Za-z]/', $segment) === 1) {
                return '{token}';
            }

            return $segment;
        }, explode('/', $path));

        return self::truncate(implode('/', $segments), Contract::MAX_VALUE_LENGTH);
    }

    /**
     * La query, filtrada por lista blanca y saneada. Devuelve solo las claves presentes.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    public static function query(array $query): array
    {
        $kept = [];

        foreach (self::QUERY_ALLOWLIST as $key) {
            $value = $query[$key] ?? null;

            if (! is_scalar($value)) {
                continue;
            }

            $clean = self::value((string) $value, 160);

            if ($clean !== null) {
                $kept[$key] = $clean;
            }
        }

        return $kept;
    }

    /**
     * Un valor de atribución o de `props` saneado: alfabeto acotado (el panel lo pinta y lo exporta),
     * longitud tope y sin pinta de dato personal. `null` si no queda nada o si parece PII.
     */
    public static function value(string $value, int $max = Contract::MAX_VALUE_LENGTH): ?string
    {
        $value = trim($value);

        if ($value === '' || Contract::looksLikePii($value)) {
            return null;
        }

        $clean = (string) preg_replace('/[^\p{L}\p{N} _\-.\/%:+|{}]/u', '', $value);
        $clean = trim($clean);

        return $clean === '' ? null : self::truncate($clean, $max);
    }

    /** El host del referer, en minúsculas y sin puerto; `null` si no hay o no se puede leer. */
    public static function referrerHost(?string $referrer): ?string
    {
        if ($referrer === null || $referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? self::truncate(strtolower($host), Contract::MAX_VALUE_LENGTH) : null;
    }

    private static function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
