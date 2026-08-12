<?php

namespace App\Domain\Identity\Services;

use App\Domain\Platform\Models\Setting;
use Illuminate\Http\Request;

/**
 * Autoridad única del consentimiento de cookies (decisión #219, `docs/PLAN-COOKIES.md`).
 *
 * Resuelve, a partir de la cookie `cookie_consent`, qué categorías NO necesarias ha aceptado
 * el visitante. La consume el composer (`AppServiceProvider`) para gobernar el **bloqueo previo**
 * de los iframes de tercero (mapa de Google, feed social) y el banner.
 *
 * **Fail-safe a privacidad:** ante cookie ausente, corrupta o de una versión de política caducada,
 * devuelve TODO en `false` (no consentido) → no se cargan terceros y el banner reaparece. Es lo
 * opuesto a `MaintenanceSettings` (fail-safe a «disponible»): aquí el fallo seguro es NO instalar
 * cookies. Nunca lanza (se invoca en cada render).
 *
 * La cookie va **sin cifrar** (la leen el servidor —aquí— y Alpine —UI—): está en la lista
 * `encryptCookies(except:)` de `bootstrap/app.php`. La ESCRIBE siempre el servidor (el
 * `CookieConsentController`) con `encode()`; Alpine solo mantiene el estado en memoria para la UI.
 */
class CookieConsent
{
    public const COOKIE_NAME = 'cookie_consent';

    /**
     * Versión de la política de cookies (patrón `Consent::CURRENT_VERSION`). Subirla cuando cambie
     * materialmente el inventario, las finalidades o los terceros → el gate la verá «caducada» y
     * volverá a pedir el consentimiento (Guía AEPD: re-pedir si cambian finalidades/terceros).
     */
    public const POLICY_VERSION = '2026-06-08';

    /** Categorías NO necesarias gobernables (granularidad por finalidad). El resto son exentas. */
    public const OPTIONAL = ['maps', 'social'];

    /** Vida del consentimiento: 24 meses (máximo de la Guía AEPD), en minutos. */
    public const LIFETIME_MINUTES = 60 * 24 * 365 * 2;

    /**
     * Estado de consentimiento del visitante.
     *
     * @return array{maps:bool,social:bool,decided:bool}
     */
    public static function state(Request $request): array
    {
        $default = ['maps' => false, 'social' => false, 'decided' => false];

        $raw = $request->cookie(self::COOKIE_NAME);
        if (! is_string($raw) || $raw === '') {
            return $default;
        }

        $json = base64_decode($raw, true);
        if ($json === false) {
            return $default;
        }

        $data = json_decode($json, true);
        if (! is_array($data) || ($data['v'] ?? null) !== self::POLICY_VERSION) {
            // Versión distinta = política cambiada → re-pedir consentimiento.
            return $default;
        }

        $cats = is_array($data['cats'] ?? null) ? $data['cats'] : [];

        return [
            'maps' => (bool) ($cats['maps'] ?? false),
            'social' => (bool) ($cats['social'] ?? false),
            'decided' => true,
        ];
    }

    /**
     * Valor de la cookie a escribir (base64 del JSON con la versión). base64 evita comas/`;` en el
     * valor de la cookie (RFC 6265) y permite extender el esquema sin tocar el formato.
     *
     * @param  array<string,bool>  $cats
     */
    public static function encode(array $cats): string
    {
        $payload = ['v' => self::POLICY_VERSION, 'cats' => []];
        foreach (self::OPTIONAL as $cat) {
            $payload['cats'][$cat] = (bool) ($cats[$cat] ?? false);
        }

        return base64_encode((string) json_encode($payload));
    }

    /**
     * ¿Mostrar el banner de cookies? Default ON (`'1'`); solo el literal `'0'` lo apaga (fallback no
     * destructivo, patrón `PuertaSettings::waiverCheckEnabled`). Apagarlo NO desactiva el bloqueo
     * previo (los iframes siguen gateados por `state()`): solo oculta el banner.
     */
    public static function bannerEnabled(): bool
    {
        return (string) Setting::value('cookies.banner_enabled', '1') !== '0';
    }
}
