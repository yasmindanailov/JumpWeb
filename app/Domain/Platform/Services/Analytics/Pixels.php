<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Models\Setting;

/**
 * **Los píxeles de anuncios, por instalación** (`docs/specs/analitica.md` §4.3, T3b·1; `#678`).
 *
 * Google Ads (gtag con **Consent Mode v2 BÁSICO**), Meta y TikTok van bajo la categoría `marketing` del banner y
 * cada instalación pone sus ids PÚBLICOS desde «Ajustes» (`marketing.*`): un id vacío o con otra forma es «sin
 * píxel». Aquí vive todo lo que de ellos depende y no puede divergir: cuáles están activos y con qué ids
 * ({@see config()}), sus orígenes en la CSP por directiva ({@see csp()}, en CÓDIGO: `settings` es dato del
 * operador y no puede abrir la CSP) y lo que el `<body>` publica para el cargador ({@see forBody()}).
 *
 * ⚠️ **El gate real es no inyectar el script** (`COOKIES.md` D8): el cargador (`cajon/pixels.js`) solo los trae
 * con `data-cookie-marketing="1"`; la CSP es la segunda cerradura y se abre solo con el píxel configurado.
 * «Básico» quiere decir que gtag NO EXISTE en el DOM sin `marketing` (no hay pings «sin cookies»), y que
 * `analytics_storage` va SIEMPRE denegado: la medición es del libro propio, no de Google.
 * ⚠️ Los TOKENS de las APIs de conversiones (Meta CAPI, TikTok Events API) NO están aquí: van en
 * `config/services.php` desde `.env` (`PAY-06`), y los usa el job de conversiones (T3b·2).
 * ⚠️ Los orígenes son los que cada plataforma documenta para su etiqueta; `www.google.com` ya estaba en
 * `frame-src` por el mapa y aquí entra en `img-src`/`connect-src` por los pings de conversión.
 */
final class Pixels
{
    public const GOOGLE_ADS = 'google_ads';

    public const META = 'meta';

    public const TIKTOK = 'tiktok';

    /** @var list<string> */
    public const ALL = [self::GOOGLE_ADS, self::META, self::TIKTOK];

    public const KEY_GOOGLE_ADS_ID = 'marketing.google_ads.conversion_id';

    /**
     * La etiqueta de la acción de conversión de compra (`AW-…/AbCdEf`): sin ella gtag manda el `purchase` a la
     * cuenta y Google Ads no lo ata a ninguna acción. Opcional: sin etiqueta, solo la carga de página.
     */
    public const KEY_GOOGLE_ADS_LABEL = 'marketing.google_ads.conversion_label';

    public const KEY_META_PIXEL_ID = 'marketing.meta.pixel_id';

    public const KEY_TIKTOK_PIXEL_ID = 'marketing.tiktok.pixel_id';

    public const GOOGLE_ADS_ID_RE = '/^AW-\d{6,12}$/';

    public const GOOGLE_ADS_LABEL_RE = '/^[A-Za-z0-9_-]{6,40}$/';

    public const META_PIXEL_ID_RE = '/^\d{10,20}$/';

    public const TIKTOK_PIXEL_ID_RE = '/^[A-Z0-9]{16,24}$/';

    /** Las tres directivas que un píxel abre: su script, sus pings (`fetch`/beacon) y su imagen de 1×1. */
    public const CSP_DIRECTIVES = ['script-src', 'connect-src', 'img-src'];

    /**
     * Los orígenes de cada plataforma, por directiva (lo que cada etiqueta documenta).
     *
     * @var array<string, array<string, list<string>>>
     */
    public const ORIGINS = [
        self::GOOGLE_ADS => [
            'script-src' => ['https://www.googletagmanager.com'],
            'connect-src' => ['https://www.googletagmanager.com', 'https://www.google.com', 'https://www.googleadservices.com', 'https://googleads.g.doubleclick.net'],
            'img-src' => ['https://www.google.com', 'https://www.googleadservices.com', 'https://googleads.g.doubleclick.net'],
        ],
        self::META => [
            'script-src' => ['https://connect.facebook.net'],
            'connect-src' => ['https://www.facebook.com'],
            'img-src' => ['https://www.facebook.com'],
        ],
        self::TIKTOK => [
            'script-src' => ['https://analytics.tiktok.com'],
            'connect-src' => ['https://analytics.tiktok.com'],
            'img-src' => ['https://analytics.tiktok.com'],
        ],
    ];

    /**
     * Los píxeles ACTIVOS con sus ids, o `[]` si no hay ninguno configurado con su forma.
     *
     * @return array<string, string> plataforma → id (Google Ads: `AW-…` o `AW-…/etiqueta`)
     */
    public static function config(): array
    {
        $active = [];

        $google = self::googleAdsId(Setting::value(self::KEY_GOOGLE_ADS_ID));
        if ($google !== null) {
            $label = self::googleAdsLabel(Setting::value(self::KEY_GOOGLE_ADS_LABEL));
            $active[self::GOOGLE_ADS] = $label === null ? $google : $google.'/'.$label;
        }

        $meta = self::metaPixelId(Setting::value(self::KEY_META_PIXEL_ID));
        if ($meta !== null) {
            $active[self::META] = $meta;
        }

        $tiktok = self::tiktokPixelId(Setting::value(self::KEY_TIKTOK_PIXEL_ID));
        if ($tiktok !== null) {
            $active[self::TIKTOK] = $tiktok;
        }

        return $active;
    }

    /** @return list<string> las plataformas activas, para nombrarlas en `/cookies` y en la política. */
    public static function active(): array
    {
        return array_keys(self::config());
    }

    /** Cómo se llama cada plataforma, para nombrarla en `/cookies`. */
    public static function name(string $platform): string
    {
        return match ($platform) {
            self::GOOGLE_ADS => 'Google Ads',
            self::META => 'Meta (Facebook e Instagram)',
            self::TIKTOK => 'TikTok',
            default => $platform,
        };
    }

    /**
     * Los orígenes que los píxeles activos necesitan en la CSP, por directiva. Vacío sin píxeles: la CSP del
     * sitio no se abre para una etiqueta que no se carga.
     *
     * @return array<string, list<string>>
     */
    public static function csp(): array
    {
        $csp = [];

        foreach (self::active() as $platform) {
            foreach (self::ORIGINS[$platform] as $directive => $origins) {
                $csp[$directive] = array_values(array_unique(array_merge($csp[$directive] ?? [], $origins)));
            }
        }

        return $csp;
    }

    public static function googleAdsId(mixed $raw): ?string
    {
        return is_string($raw) && preg_match(self::GOOGLE_ADS_ID_RE, trim($raw)) === 1 ? trim($raw) : null;
    }

    public static function googleAdsLabel(mixed $raw): ?string
    {
        return is_string($raw) && preg_match(self::GOOGLE_ADS_LABEL_RE, trim($raw)) === 1 ? trim($raw) : null;
    }

    public static function metaPixelId(mixed $raw): ?string
    {
        $value = is_int($raw) ? (string) $raw : (is_string($raw) ? trim($raw) : '');

        return preg_match(self::META_PIXEL_ID_RE, $value) === 1 ? $value : null;
    }

    public static function tiktokPixelId(mixed $raw): ?string
    {
        return is_string($raw) && preg_match(self::TIKTOK_PIXEL_ID_RE, trim($raw)) === 1 ? trim($raw) : null;
    }

    /**
     * Lo que el `<body>` publica para el cargador: un atributo por píxel activo (`data-pixel-google-ads`,
     * `data-pixel-meta`, `data-pixel-tiktok`). Sin píxeles, nada: la página no descarga ni un byte del cargador.
     *
     * @return array<string, string> atributo → valor
     */
    public static function forBody(): array
    {
        $attributes = [];

        foreach (self::config() as $platform => $id) {
            $attributes['data-pixel-'.str_replace('_', '-', $platform)] = $id;
        }

        return $attributes;
    }
}
