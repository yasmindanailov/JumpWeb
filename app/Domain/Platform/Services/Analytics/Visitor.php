<?php

namespace App\Domain\Platform\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * **La identidad del visitante: una cookie propia de 13 meses** (`docs/specs/analitica.md` §4.1).
 *
 * Es la cookie que la guía AEPD de 2024 deja EXENTA de consentimiento: propia, para medir la audiencia
 * del editor, sin cruzar sitios ni cederse, con vida acotada a 13 meses **y sin renovarse en cada visita**
 * —por eso `cookie()` la escribe con una caducidad fija y nadie la vuelve a escribir mientras exista—.
 * Cuando caduca, el visitante estrena identidad.
 *
 * ⚠️ **Va SIN cifrar**, como `cookie_consent` (`COOKIES.md` D4): la ruta de ingesta es stateless —fuera del
 * grupo con `EncryptCookies`— y tiene que leerla tal cual; el valor es un ULID opaco que no abre nada.
 * `HttpOnly` porque ningún script la necesita: en el mismo origen viaja sola con cada petición.
 *
 * ⚠️ **`X-Visitor` solo con Bearer** (la app móvil, que no tiene cookies): en el mismo origen la cookie
 * gana y la cabecera se ignora, o cualquiera podría atribuirse la sesión de otro id (seguridad-4).
 */
final class Visitor
{
    public const COOKIE = 'visitor_id';

    public const HEADER = 'X-Visitor';

    /**
     * El id que `ResolveVisitor` ACUÑA en esta misma petición (web, sin cookie todavía). Es un atributo de la
     * petición —lo pone el servidor, no lo manda nadie—, y hace que la primera vista ya sepa quién mira: la
     * variante de un experimento (spec §4.4) tiene que estar en la primera página, no en la segunda.
     */
    public const ATTRIBUTE = 'analytics.visitor_id';

    /** 13 meses: el techo de la guía para una cookie de medición exenta. */
    public const LIFETIME_MONTHS = 13;

    private const ULID_RE = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    private const UUID_RE = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public static function mint(): string
    {
        return (string) Str::ulid();
    }

    public static function isValid(mixed $id): bool
    {
        return is_string($id) && (preg_match(self::ULID_RE, $id) === 1 || preg_match(self::UUID_RE, $id) === 1);
    }

    /**
     * El id del visitante que trae la petición, o `null` si no trae ninguno válido.
     *
     * La cookie manda. La cabecera solo cuenta cuando la petición viene con un token Bearer, que es la
     * marca de un cliente sin cookies (la app).
     */
    public static function fromRequest(Request $request): ?string
    {
        $minted = $request->attributes->get(self::ATTRIBUTE);

        if (self::isValid($minted)) {
            return (string) $minted;
        }

        $cookie = $request->cookie(self::COOKIE);

        if (self::isValid($cookie)) {
            return (string) $cookie;
        }

        $header = $request->header(self::HEADER);

        if ($request->bearerToken() !== null && self::isValid($header)) {
            return (string) $header;
        }

        return null;
    }

    /** La cookie ya construida, para adjuntar a una respuesta. */
    public static function cookie(string $id, Request $request): Cookie
    {
        return new Cookie(
            name: self::COOKIE,
            value: $id,
            expire: now()->addMonths(self::LIFETIME_MONTHS),
            path: '/',
            domain: null,
            secure: $request->isSecure() || (bool) config('session.secure'),
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }
}
