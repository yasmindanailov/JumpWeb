<?php

namespace Tests\Feature\Cookies;

use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * #219 — Bloqueo previo (LSSI art. 22.2 + Guía AEPD): los iframes de tercero (Google Maps, feed
 * social) NO se cargan hasta que el visitante consiente la categoría correspondiente. Sin
 * consentimiento se renderiza un placeholder y la URL va en `data-src` (no `src`) → el navegador
 * no la solicita. Cubre home (mapa + feed) y /contacto (mapa).
 *
 * Discriminador `src` cargado vs `data-src` bloqueado: ` src="…"` con espacio inicial solo matchea
 * el atributo real (en `data-src="…"` el carácter previo a `src` es `-`).
 */
class CookieGateBlockingTest extends TestCase
{
    use RefreshDatabase;

    private const MAP = 'https://www.google.com/maps/embed?pb=MAPTOKENXYZ';

    private const FEED = 'https://snapwidget.com/embed/FEEDTOKENXYZ';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
        Setting::updateOrCreate(['key' => 'address.maps_embed_url'], ['value' => self::MAP, 'group' => 'contact']);
        Setting::updateOrCreate(['key' => 'social.feed_embed_url'], ['value' => self::FEED, 'group' => 'social']);
    }

    private function consent(bool $maps, bool $social): static
    {
        return $this->withUnencryptedCookie(
            CookieConsent::COOKIE_NAME,
            CookieConsent::encode(['maps' => $maps, 'social' => $social]),
        );
    }

    public function test_home_map_is_blocked_without_consent(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('consent-frame__ph', false);               // placeholder de bloqueo previo
        $response->assertSee('data-src="'.self::MAP.'"', false);        // URL presente pero NO solicitada
        $response->assertDontSee(' src="'.self::MAP.'"', false);        // el navegador no carga el iframe
    }

    public function test_home_map_loads_with_consent(): void
    {
        // Consentir ambas categorías → ningún placeholder y el iframe real del mapa carga.
        $response = $this->consent(maps: true, social: true)->get('/')->assertOk();

        $response->assertSee(' src="'.self::MAP.'"', false);            // iframe real
        $response->assertDontSee('consent-frame__ph', false);          // sin placeholders
    }

    public function test_contact_map_is_also_gated(): void
    {
        $this->get('/contacto')->assertOk()
            ->assertSee('consent-frame__ph', false)
            ->assertDontSee(' src="'.self::MAP.'"', false);

        $this->consent(maps: true, social: false)->get('/contacto')->assertOk()
            ->assertSee(' src="'.self::MAP.'"', false);
    }

    // ⚠️⚠️ **AQUÍ HABÍA DOS CASOS DEL FEED SOCIAL Y SE HAN RETIRADO CON SU SUJETO** (`#309`):
    // `test_social_feed_blocked_without_consent` y `test_social_feed_loads_with_consent`. La
    // sección «En directo» era el ÚNICO consumidor de la categoría `social`, y el owner la retiró.
    // ▶ **Lo que queda dicho, porque importa**: hoy la categoría `social` no gatea NADA — el
    // ajuste `social.feed_embed_url`, el servicio `SocialEmbed` y la línea del banner que promete
    // «contenido de redes sociales» se han quedado sin consumidor. Ficha en `DEUDA.md` con sus dos
    // salidas: vuelve con las reseñas (`specs/google-reviews.md`) o se retira entera con su texto
    // legal. **No se reescriben estos casos contra un consumidor inventado.**

    public function test_categories_are_independent(): void
    {
        // ⚠️ **Re-apuntado, no debilitado** (`#309`). Antes probaba la independencia por el lado del
        // feed —consentir el mapa no carga el feed—, y ese consumidor ya no existe. Se prueba por el
        // lado que SÍ tiene sujeto: consentir SOLO las redes **no** carga el mapa. Es la misma
        // propiedad (granularidad por finalidad, Guía AEPD) vista desde la otra categoría, y sigue
        // fallando si alguien colapsa las dos en un único «acepto».
        $response = $this->consent(maps: false, social: true)->get('/')->assertOk();

        $response->assertDontSee(' src="'.self::MAP.'"', false);
        $response->assertSee('data-src="'.self::MAP.'"', false);
        $response->assertSee('consent-frame__ph', false);
    }

    public function test_banner_state_attributes_reflect_server(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-cookie-enabled="1"', false)
            ->assertSee('data-cookie-decided=""', false);

        $this->consent(maps: true, social: false)->get('/')->assertOk()
            ->assertSee('data-cookie-decided="1"', false)
            ->assertSee('data-cookie-maps="1"', false)
            ->assertSee('data-cookie-social=""', false);
    }

    public function test_banner_is_wrapped_in_an_alpine_root(): void
    {
        // El banner va al final del <body>, fuera del `x-data="landing"` de la página. Necesita su
        // PROPIO `x-data` para que Alpine lo inicialice (si no, el x-cloak no se retira → invisible,
        // y los @click no enganchan → el panel no abre). Guard de regresión del bug de runtime.
        $this->get('/')->assertOk()->assertSee('<div x-data class="cookie-consent-root">', false);
    }

    public function test_consent_buttons_have_equal_styling(): void
    {
        // Igualdad de condiciones (Guía AEPD): ningún botón del banner se resalta con el color de
        // marca (`.btn--zone`). Aceptar/Rechazar/Configurar comparten la misma clase neutra.
        $this->get('/')->assertOk()
            ->assertSee('btn btn--ghost cookie-btn', false)
            ->assertDontSee('btn--zone cookie-btn', false);
    }

    public function test_banner_can_be_disabled_by_setting(): void
    {
        Setting::updateOrCreate(['key' => 'cookies.banner_enabled'], ['value' => '0', 'group' => 'cookies']);

        // El banner se oculta, pero el bloqueo previo SIGUE: el mapa no carga sin consentimiento.
        $this->get('/')->assertOk()
            ->assertSee('data-cookie-enabled=""', false)
            ->assertDontSee(' src="'.self::MAP.'"', false);
    }

    public function test_banner_uses_the_card_layout_with_the_real_categories(): void
    {
        // Rediseño #222 (mockup «Banner Cookies»): UNA tarjeta (tira de bloques + chip + toggles),
        // y las finalidades son las REALES del inventario #219 (Mapa Google + Redes), NO categorías
        // inventadas como «Analíticas/Marketing» (cero analítica → consentimiento inexacto).
        $this->get('/')->assertOk()
            ->assertSee('cookie__chip', false)              // tarjeta del mockup (chip + título)
            ->assertSee('ck-tgl', false)                    // toggles de finalidad
            ->assertSee(__('cookies.banner.title'))         // título de la tarjeta
            ->assertSee(__('cookies.panel.maps_title'))     // categoría real: Mapa
            ->assertSee(__('cookies.panel.social_title'));  // categoría real: Redes
    }
}
