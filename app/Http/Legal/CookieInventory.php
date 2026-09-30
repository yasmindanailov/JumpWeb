<?php

namespace App\Http\Legal;

use App\Domain\Content\Services\MapsEmbed;
use App\Domain\Content\Services\SocialEmbed;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Identity\Services\RememberedDevice;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Drivers;
use App\Domain\Platform\Services\Analytics\Pixels;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Domain\Platform\Services\Turnstile;
use Illuminate\Auth\SessionGuard;
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
        if (MapsEmbed::clean(Setting::value('address.maps_embed_url')) !== null) {
            $rows[] = self::third('maps', 'maps', $t);
        }
        if (($provider = self::socialProvider()) !== null) {
            $rows[] = self::third('social', 'social', $t, ['provider' => $provider]);
        }
        if (($tool = Drivers::config()) !== null) {
            $rows[] = self::third($tool['driver'], 'analytics', $t, ['host' => (string) $tool['host']]);
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

    /** El proveedor del widget de redes, si hay uno configurado (`SocialEmbed` solo admite esos dominios). */
    private static function socialProvider(): ?string
    {
        $url = SocialEmbed::clean(Setting::value('social.feed_embed_url'));
        $host = $url === null ? '' : (string) parse_url($url, PHP_URL_HOST);

        return match (true) {
            str_ends_with($host, 'snapwidget.com') => 'SnapWidget',
            str_ends_with($host, 'lightwidget.com') => 'LightWidget',
            default => null,
        };
    }
}
