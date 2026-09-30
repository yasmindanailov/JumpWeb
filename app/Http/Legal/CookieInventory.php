<?php

namespace App\Http\Legal;

use App\Domain\Content\Services\MapsEmbed;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Identity\Services\RememberedDevice;
use App\Domain\Platform\Models\EmailSend;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Drivers;
use App\Domain\Platform\Services\Analytics\EmailOpenMarks;
use App\Domain\Platform\Services\Analytics\Pixels;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Domain\Platform\Services\Turnstile;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * **LAS COOKIES DE ESTA INSTALACIÓN, una a una** (`docs/specs/politica-de-cookies.md` §3; el encargo del owner, 30-09: la
 * política de `/cookies` para producción, «con rigor»). El LISTADO de la política no se escribe a mano: lo compone esto con
 * la configuración de la instalación, así que un mapa, un anti-bot, una herramienta de análisis o un píxel aparecen en la
 * política cuando se encienden y se van cuando se apagan. El texto que explica vive en la BD (`CookiePolicyContent`).
 *
 * ⚠️ Vive en `Http` y no en un dominio: lee de TRES —las cookies de la identidad (`CookieConsent`, `RememberedDevice`), los
 * incrustados del contenido (el mapa, las redes) y la plataforma (la medición, el anti-bot, el análisis, los píxeles)— y
 * `ModuleBoundariesTest` no deja que `Content` e `Identity` se miren entre sí. Es composición de la aplicación, como
 * `Http\Instancia` o `Http\Cuenta`.
 *
 * Viaja en `GET /legal/documents/cookies` (`inventory`) y lo pintan la vista del producto y la de la instancia. Lo que
 * dice se midió en el navegador (`scripts/sonda-inventario-cookies.mjs`, que falla si una cookie no está aquí): los
 * nombres y las duraciones PROPIAS salen de las mismas constantes que las ponen; las de un tercero, de su documentación
 * («p. ej.»: dependen de él).
 */
final class CookieInventory
{
    /**
     * @return list<array{key: string, name: string, holder: string, purpose: string, duration: string, category: string, when: string}>
     */
    public static function rows(?string $locale = null): array
    {
        $t = static fn (string $key, array $replace = []): string => (string) __('cookies.inventory.'.$key, $replace, $locale);
        $own = $t('own');
        $minutes = (int) config('session.lifetime', 120);
        $session = $minutes % 60 === 0 ? $t('hours', ['n' => intdiv($minutes, 60)]) : $t('minutes', ['n' => $minutes]);
        $guard = Auth::guard('web');

        $rows = [
            self::row('session', (string) config('session.cookie'), $own, $t('session.purpose'), $session, $t('category.necessary'), $t('session.when')),
            self::row('xsrf', 'XSRF-TOKEN', $own, $t('xsrf.purpose'), $session, $t('category.necessary'), $t('xsrf.when')),
            self::row('visitor', Visitor::COOKIE, $own, $t('visitor.purpose'), $t('months', ['n' => Visitor::LIFETIME_MONTHS]), $t('category.measurement'), $t('visitor.when')),
            self::row('consent', CookieConsent::COOKIE_NAME, $own, $t('consent.purpose'), $t('consent.duration', ['n' => intdiv(CookieConsent::LIFETIME_MINUTES, 60 * 24 * 30)]), $t('category.necessary'), $t('consent.when')),
            // No es «necesaria»: persistente, no está exenta, y la pone solo la casilla sin marcar (`#858`, GT29 4/2012 §3.2).
            self::row('remember', $guard instanceof SessionGuard ? $guard->getRecallerName() : 'remember_web', $own, $t('remember.purpose'), $t('remember.duration', ['n' => RememberedDevice::DAYS]), $t('category.on_request'), $t('remember.when')),
            self::third('redsys', 'necessary', $t),
        ];

        if (Turnstile::enabled()) {
            $rows[] = self::third('turnstile', 'necessary', $t);
        }
        if (self::mapsOn()) {
            $rows[] = self::third('maps', 'maps', $t);
        }
        // ⚠️ Sin fila de REDES SOCIALES aunque el panel tenga un widget: NADA lo pinta desde `#309` (medido el 30-09: ni el
        // producto ni la instancia leen `social_feed`; el pie de PlayJump lleva ENLACES, sin cookies). Ficha en `DEUDA.md`.
        if (($tool = Drivers::config()) !== null) {
            $rows[] = self::third($tool['driver'], 'analytics', $t, ['host' => (string) $tool['host']]);
        }
        // El píxel de apertura de los correos (`#797`): su doc pide que `/cookies` lo nombre antes de encenderlo (`#860`).
        if (EmailOpenMarks::enabled()) {
            $rows[] = self::row('email_opens', $t('email_opens.name'), $own, $t('email_opens.purpose'), $t('email_opens.duration', ['n' => EmailSend::RETENTION_MONTHS]), $t('category.analytics'), $t('email_opens.when'));
        }
        foreach (Pixels::active() as $platform) {
            $rows[] = self::third($platform, 'marketing', $t);
        }

        return $rows;
    }

    /** @return array{key: string, name: string, holder: string, purpose: string, duration: string, category: string, when: string} */
    private static function row(string $key, string $name, string $holder, string $purpose, string $duration, string $category, string $when): array
    {
        return compact('key', 'name', 'holder', 'purpose', 'duration', 'category', 'when');
    }

    /**
     * Las de un TERCERO: todos sus textos, de `cookies.inventory.<clave>`.
     *
     * @param  \Closure(string, array<string, string>=): string  $t
     * @param  array<string, string>  $replace
     * @return array{key: string, name: string, holder: string, purpose: string, duration: string, category: string, when: string}
     */
    private static function third(string $key, string $category, \Closure $t, array $replace = []): array
    {
        return self::row(
            $key, $t($key.'.name', $replace), $t($key.'.holder', $replace), $t($key.'.purpose', $replace), $t($key.'.duration', $replace),
            $t('category.'.$category), $t($key.'.when', $replace),
        );
    }

    /**
     * **Las categorías con permiso que ESTA instalación pide** (`#860`, `[DECIDIDO owner]`; `politica-de-cookies.md` §6): las de
     * `CookieConsent::OPTIONAL`, en su orden, que tienen algo detrás —con las MISMAS condiciones que las filas de arriba—. Es
     * lo que el `<body>` pone en `data-consent-categories` (de ahí leen el almacén y los dos «Configurar») y con lo que se
     * componen los textos del aviso; el servidor guarda en `false` lo que no se ofreció (`CookieConsentController`).
     * ⚠️ `analytics` SIEMPRE: su parte propia —atar la navegación a la cuenta al entrar (`AccountAnalytics`)— no tiene
     * interruptor; la herramienta y el píxel de los correos, si están, van dentro. Por eso nunca sale vacía: sin lista, el
     * almacén volvería a sus dos categorías de respaldo (`ui/cookie-consent.js`).
     * ⚠️ `social` NUNCA, hoy: no la gatea nada desde `#309` (`DEUDA.md`), así que pedirla sería pedir permiso para nada. El
     * día que un contenido la use, entra aquí con SU condición y su fila arriba, en el mismo cambio.
     *
     * @return list<string>
     */
    public static function offered(): array
    {
        $on = [
            'maps' => self::mapsOn(),
            'social' => false,
            'analytics' => true,
            'marketing' => Pixels::active() !== [],
        ];

        return array_values(array_filter(CookieConsent::OPTIONAL, static fn (string $category): bool => $on[$category]));
    }

    /**
     * ¿Está contestado el aviso? Lo está si hay decisión y en ella se preguntó todo lo que hoy se ofrece: una categoría que
     * se enciende DESPUÉS es una pregunta nueva, y el aviso vuelve (con lo ya contestado marcado); hasta contestarla, la
     * nueva está apagada, porque nunca se aceptó (`#860`).
     */
    public static function decided(Request $request): bool
    {
        return CookieConsent::state($request)['decided']
            && array_diff(self::offered(), CookieConsent::asked($request)) === [];
    }

    /**
     * El texto del aviso de siempre (`site/cookie-banner`): lo propio y, «solo con tu permiso», lo que se ofrece (`#860`).
     * La isla compone el suyo con las mismas categorías (`isla/pagina/pagina.js`, `avisoDeCookies`).
     */
    public static function bannerText(?string $locale = null): string
    {
        $purposes = array_map(static fn (string $category): string => (string) __('cookies.banner.purposes.'.$category, [], $locale), self::offered());

        return (string) __('cookies.banner.text', ['purposes' => Arr::join($purposes, ', ', (string) __('cookies.banner.and', [], $locale))], $locale);
    }

    /**
     * Los textos de cada finalidad para los dos «Configurar» (`cookies.panel`): los de `lang/`, y el de «Análisis» se
     * COMPONE (`#860`: se nombra lo que se pide): lo propio, la herramienta si hay una y el píxel de los correos si está
     * encendido, y al final que la medición anónima no lo necesita.
     *
     * @return array<string, string>
     */
    public static function panel(?string $locale = null): array
    {
        $panel = (array) __('cookies.panel', [], $locale);

        $panel['analytics_desc'] = implode(' ', array_filter([
            $panel['analytics_desc'],
            Drivers::config() !== null ? $panel['analytics_tool'] : null,
            EmailOpenMarks::enabled() ? $panel['analytics_opens'] : null,
            $panel['analytics_note'],
        ]));
        unset($panel['analytics_tool'], $panel['analytics_opens'], $panel['analytics_note']);

        return $panel;
    }

    private static function mapsOn(): bool
    {
        return MapsEmbed::clean(Setting::value('address.maps_embed_url')) !== null;
    }
}
