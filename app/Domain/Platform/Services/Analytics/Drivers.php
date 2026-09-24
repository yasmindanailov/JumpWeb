<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Jobs\ForgetPersonInDriver;
use App\Domain\Platform\Models\Setting;

/**
 * **La herramienta de análisis externa, por instalación** (`docs/specs/analitica.md` §4.3, T3a·2; `#678`).
 *
 * El libro propio (T1) es exento y no necesita a nadie; la herramienta —grabaciones de sesión, mapas de calor—
 * va bajo la categoría `analytics` del banner y la elige cada instalación desde «Ajustes»: `posthog` (la nube
 * europea de PostHog), `matomo` (una instalación propia, por su host) o `none`. Aquí vive TODO lo que de ella
 * depende y que no puede divergir: si está activa y con qué datos ({@see config()}), sus orígenes en la CSP
 * ({@see csp()}) —en CÓDIGO, no en `settings`, que es dato del operador y no puede abrir la CSP—, cómo se
 * nombra en `/cookies` ({@see name()}) y el id OPACO con el que se identifica a una persona ({@see personId()}).
 *
 * ⚠️ **El gate real es no inyectar el script** (`COOKIES.md` D8): el cargador (`cajon/driver.js`) solo lo trae
 * con `data-cookie-analytics="1"` en el `<body>`; la CSP es la segunda cerradura, y se abre solo con el driver
 * activo. Sin driver, `config()` es `null` y el `<body>` no lleva ni un atributo `data-analytics-*`.
 * ⚠️ **Un ajuste incompleto es «ninguno»**: `posthog` sin token válido o `matomo` sin host `https://` o sin
 * id de sitio no cargan nada ni abren la CSP. El panel lo rechaza al guardar, y esto es la red por si llega
 * por otra vía (un seeder, un `settings` editado a mano).
 * ⚠️ El token de PostHog es PÚBLICO (viaja en el HTML de cada página, como en cualquier web que lo use); la
 * clave PERSONAL de su API —la del olvido, {@see ForgetPersonInDriver}— va en
 * `.env` (`PAY-06`), nunca aquí.
 */
final class Drivers
{
    public const NONE = 'none';

    public const POSTHOG = 'posthog';

    public const MATOMO = 'matomo';

    /** @var list<string> */
    public const ALL = [self::NONE, self::POSTHOG, self::MATOMO];

    public const KEY_DRIVER = 'analytics.driver';

    public const KEY_POSTHOG_PROJECT = 'analytics.posthog_project';

    public const KEY_MATOMO_HOST = 'analytics.matomo_host';

    public const KEY_MATOMO_SITE_ID = 'analytics.matomo_site_id';

    /** La nube EUROPEA de PostHog: los datos no salen de la UE (transferencias, §4.3). */
    public const POSTHOG_HOST = 'https://eu.i.posthog.com';

    /** Lo que PostHog pide en la CSP: el script, la ingesta y los recursos de la grabación viven en subdominios. */
    public const POSTHOG_ORIGIN = 'https://*.posthog.com';

    public const POSTHOG_TOKEN_RE = '/^phc_[A-Za-z0-9]{20,}$/';

    public const MATOMO_SITE_ID_RE = '/^[1-9]\d{0,8}$/';

    /** Las tres directivas que un driver abre: su script, su ingesta (`fetch`/beacon) y su píxel. */
    public const CSP_DIRECTIVES = ['script-src', 'connect-src', 'img-src'];

    public static function active(): string
    {
        return self::config()['driver'] ?? self::NONE;
    }

    /**
     * La configuración EFECTIVA, o `null` si no hay driver (o el que hay está incompleto).
     *
     * @return array{driver: string, key: string, host: string}|null
     */
    public static function config(): ?array
    {
        $driver = Setting::value(self::KEY_DRIVER, self::NONE);

        if ($driver === self::POSTHOG) {
            $token = self::posthogProject(Setting::value(self::KEY_POSTHOG_PROJECT));

            return $token === null ? null : ['driver' => self::POSTHOG, 'key' => $token, 'host' => self::POSTHOG_HOST];
        }

        if ($driver === self::MATOMO) {
            $host = self::matomoHost(Setting::value(self::KEY_MATOMO_HOST));
            $site = self::matomoSiteId(Setting::value(self::KEY_MATOMO_SITE_ID));

            return $host === null || $site === null ? null : ['driver' => self::MATOMO, 'key' => $site, 'host' => $host];
        }

        return null;
    }

    /** Cómo se llama la herramienta activa, para nombrarla en `/cookies`; `null` sin driver. */
    public static function name(): ?string
    {
        return match (self::active()) {
            self::POSTHOG => 'PostHog',
            self::MATOMO => 'Matomo',
            default => null,
        };
    }

    /**
     * Los orígenes que el driver activo necesita en la CSP, por directiva. Vacío sin driver: la CSP del sitio no
     * se abre para una herramienta que no se carga.
     *
     * @return array<string, list<string>>
     */
    public static function csp(): array
    {
        $config = self::config();

        if ($config === null) {
            return [];
        }

        $origin = $config['driver'] === self::POSTHOG ? self::POSTHOG_ORIGIN : $config['host'];

        return array_fill_keys(self::CSP_DIRECTIVES, [$origin]);
    }

    /** El token PÚBLICO de proyecto de PostHog, o `null` si no tiene su forma. */
    public static function posthogProject(mixed $raw): ?string
    {
        return is_string($raw) && preg_match(self::POSTHOG_TOKEN_RE, trim($raw)) === 1 ? trim($raw) : null;
    }

    /**
     * El host de una instalación de Matomo, RECONSTRUIDO: `https://` + host (+ puerto). Sin ruta, sin query,
     * sin credenciales, sin `http://`: es un origen que va a la CSP y a un `<script src>`, y lo que teclea el
     * operador no puede abrir más que eso.
     */
    public static function matomoHost(mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $parts = parse_url(trim($raw));

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) !== 1) {
            return null;
        }

        return 'https://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    public static function matomoSiteId(mixed $raw): ?string
    {
        $value = is_int($raw) ? (string) $raw : (is_string($raw) ? trim($raw) : '');

        return preg_match(self::MATOMO_SITE_ID_RE, $value) === 1 ? $value : null;
    }

    /**
     * El id OPACO de una persona para el driver: un HMAC del id de la cuenta con la clave de la aplicación.
     * Nunca el id, nunca el correo: la herramienta ve «alguien» estable, no «quién»; y sin la clave no hay
     * camino de vuelta. `[T3a·3]` lo usa también el olvido en el driver.
     */
    public static function personId(int $userId): string
    {
        return hash_hmac('sha256', (string) $userId, (string) config('app.key'));
    }

    /**
     * Lo que el `<body>` publica para el cargador: la configuración y, SOLO con sesión y con la categoría
     * `analytics` consentida, la persona. Sin driver, nada.
     *
     * @return array{driver: string, key: string, host: string, person: ?string}|null
     */
    public static function forBody(?int $userId, bool $analyticsConsented): ?array
    {
        $config = self::config();

        if ($config === null) {
            return null;
        }

        return $config + ['person' => $userId !== null && $analyticsConsented ? self::personId($userId) : null];
    }
}
