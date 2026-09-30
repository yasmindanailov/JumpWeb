<?php

namespace App\Domain\Identity\Services;

use App\Domain\Platform\Models\Setting;
use Illuminate\Http\Request;

/**
 * Autoridad única del consentimiento de cookies (decisión #219, `docs/sistemas/COOKIES.md`).
 *
 * Resuelve, a partir de la cookie `cookie_consent`, qué categorías NO necesarias ha aceptado
 * el visitante. La consume el composer (`AppServiceProvider`) para gobernar el **bloqueo previo**
 * de los contenidos de tercero (mapa y reseñas de Google, feed social), el banner y —desde la T3
 * de la analítica (`specs/analitica.md` §4.3)— el régimen identificado del libro de eventos y la
 * herramienta de análisis (`analytics`) y los píxeles de anuncios (`marketing`).
 *
 * **Fail-safe a privacidad:** ante cookie ausente, corrupta o de una versión de política caducada,
 * devuelve TODO en `false` (no consentido) → no se cargan terceros y el banner reaparece. Es lo
 * opuesto a `MaintenanceSettings` (fail-safe a «disponible»): aquí el fallo seguro es NO instalar
 * cookies. Nunca lanza (se invoca en cada render).
 *
 * La cookie va **sin cifrar** (la leen el servidor —aquí— y Alpine —UI—): está en la lista
 * `encryptCookies(except:)` de `bootstrap/app.php`. La ESCRIBE siempre el servidor (el
 * `CookieConsentController`) con `encode()`; Alpine solo mantiene el estado en memoria para la UI.
 *
 * ⚠️ **Las categorías viven SOLO en {@see OPTIONAL}** (T3a): `state()`, `encode()`, el controlador, los
 * `data-cookie-*` del `<body>`, el almacén de Alpine (`ui/cookie-consent.js`, que lee
 * `data-consent-categories`) y el panel del banner las recorren. Añadir una finalidad es añadirla aquí,
 * darle sus textos (`lang/{es,en,fr}/cookies.php`, `CookiePolicyContent`) y subir {@see POLICY_VERSION}.
 * ▶ Desde `#860` se OFRECEN solo las que la instalación tiene encendidas (`Http\Legal\CookieInventory::offered()`, que
 * mira Contenido y Plataforma: por eso no vive aquí); la cookie anota qué se preguntó ({@see asked()}).
 */
class CookieConsent
{
    public const COOKIE_NAME = 'cookie_consent';

    /**
     * Versión de la política de cookies (patrón `Consent::CURRENT_VERSION`). Subirla cuando cambie
     * materialmente el inventario, las finalidades o los terceros → el gate la verá «caducada» y
     * volverá a pedir el consentimiento (Guía AEPD: re-pedir si cambian finalidades/terceros).
     *
     * ▶ v1 `2026-06-08`. v2 `2026-09-13` (`#592`, `[DECIDIDO owner]`): la categoría `maps` pasa a
     * cubrir también las RESEÑAS de Google y la foto de quien las escribe. v3 `2026-09-24` (T3a de
     * `specs/analitica.md`, `#678`): dos finalidades NUEVAS —`analytics` (análisis de uso identificado)
     * y `marketing` (píxeles y comunicación de la compra)— y la medición de audiencia propia declarada
     * como exenta: finalidad nueva, consentimiento nuevo para todos, la misma noche que la v2.0.0.
     * ⚠️ Las CLAVES no se renombran: el texto cambia, el identificador guardado no.
     */
    public const POLICY_VERSION = '2026-09-24';

    /**
     * Categorías NO necesarias gobernables (granularidad por finalidad), en el orden en que el panel las
     * enseña. El resto son exentas y se declaran en la política, no se piden.
     *
     * @var list<string>
     */
    public const OPTIONAL = ['maps', 'social', 'analytics', 'marketing'];

    /** Vida del consentimiento: 24 meses (máximo de la Guía AEPD), en minutos. */
    public const LIFETIME_MINUTES = 60 * 24 * 365 * 2;

    /**
     * Estado de consentimiento del visitante: una clave por categoría de {@see OPTIONAL} y `decided`.
     *
     * @return array<string, bool>
     */
    public static function state(Request $request): array
    {
        $data = self::payload($request);
        if ($data === null) {
            return self::allSetTo(false) + ['decided' => false];
        }

        $cats = is_array($data['cats'] ?? null) ? $data['cats'] : [];

        $state = [];
        foreach (self::OPTIONAL as $category) {
            $state[$category] = (bool) ($cats[$category] ?? false);
        }

        return $state + ['decided' => true];
    }

    /**
     * **Qué se PREGUNTÓ en esa decisión** (`#860`, `politica-de-cookies.md` §6): la instalación ofrece solo las categorías
     * que tiene encendidas, y una que se enciende después es una pregunta nueva —el aviso vuelve— sin subir la versión.
     * Sin decisión válida, nada. Una decisión de esta versión SIN la lista es de antes de `#860`, cuando se ofrecían
     * siempre las cuatro.
     *
     * @return list<string>
     */
    public static function asked(Request $request): array
    {
        $data = self::payload($request);
        if ($data === null) {
            return [];
        }
        if (! is_array($data['asked'] ?? null)) {
            return self::OPTIONAL;
        }

        return array_values(array_intersect(self::OPTIONAL, $data['asked']));
    }

    /**
     * Valor de la cookie a escribir (base64 del JSON con la versión). base64 evita comas/`;` en el
     * valor de la cookie (RFC 6265) y permite extender el esquema sin tocar el formato.
     * `$asked`: las categorías que se ofrecieron al decidir (`#860`); sin ella, todas.
     *
     * @param  array<string,bool>  $cats
     * @param  list<string>|null  $asked
     */
    public static function encode(array $cats, ?array $asked = null): string
    {
        $payload = ['v' => self::POLICY_VERSION, 'cats' => [], 'asked' => array_values(array_intersect(self::OPTIONAL, $asked ?? self::OPTIONAL))];
        foreach (self::OPTIONAL as $cat) {
            $payload['cats'][$cat] = (bool) ($cats[$cat] ?? false);
        }

        return base64_encode((string) json_encode($payload));
    }

    /**
     * La decisión guardada, o `null` si no hay una válida de ESTA versión (ausente, corrupta o caducada: versión distinta
     * = política cambiada → re-pedir consentimiento).
     *
     * @return array<string, mixed>|null
     */
    private static function payload(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE_NAME);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $json = base64_decode($raw, true);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) && ($data['v'] ?? null) === self::POLICY_VERSION ? $data : null;
    }

    /**
     * Todas las categorías a un mismo valor (lo que «Aceptar todo» y «Rechazar todo» escriben).
     *
     * @return array<string, bool>
     */
    public static function allSetTo(bool $value): array
    {
        return array_fill_keys(self::OPTIONAL, $value);
    }

    /**
     * ¿Mostrar el banner de cookies? Default ON (`'1'`); solo el literal `'0'` lo apaga (fallback no
     * destructivo, patrón `PuertaSettings::waiverCheckEnabled`). Apagarlo NO desactiva el bloqueo
     * previo (los contenidos siguen gateados por `state()`): solo oculta el banner.
     */
    public static function bannerEnabled(): bool
    {
        return (string) Setting::value('cookies.banner_enabled', '1') !== '0';
    }
}
