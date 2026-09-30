<?php

namespace Tests\Feature\Content;

use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Identity\Services\RememberedDevice;
use App\Domain\Platform\Models\EmailSend;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Drivers;
use App\Domain\Platform\Services\Analytics\EmailOpenMarks;
use App\Domain\Platform\Services\Analytics\Pixels;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Domain\Platform\Services\Turnstile;
use App\Http\Legal\CookieInventory;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * **El LISTADO de la política de cookies** (`docs/specs/politica-de-cookies.md` §3): lo compone la configuración de ESTA
 * instalación, no el texto guardado. Las propias salen siempre y de las MISMAS constantes que las ponen; un tercero, solo
 * si está encendido. Y viaja en `GET /legal/documents/cookies`, que pintan las vistas del producto y de la instancia.
 * Lo que dice lo mide `scripts/sonda-inventario-cookies.mjs` en el navegador.
 */
class CookieInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function set(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** @return list<string> */
    private function keys(): array
    {
        return array_column(CookieInventory::rows('es'), 'key');
    }

    /** @return array<string, string> */
    private function row(string $key): array
    {
        return collect(CookieInventory::rows('es'))->firstWhere('key', $key);
    }

    protected function tearDown(): void
    {
        Turnstile::flushCache();
        parent::tearDown();
    }

    public function test_a_bare_installation_lists_its_own_cookies_and_the_payment_gateway_only(): void
    {
        $this->assertSame(['session', 'xsrf', 'visitor', 'consent', 'remember', 'redsys'], $this->keys());
    }

    /** Los nombres y las duraciones propias salen de lo que las pone: si una cambia, el listado cambia con ella. */
    public function test_its_own_cookies_name_and_last_what_the_code_sets(): void
    {
        config(['session.cookie' => 'parque-session', 'session.lifetime' => 120]);

        $this->assertSame('parque-session', $this->row('session')['name']);
        $this->assertSame('2 horas desde tu última visita', $this->row('session')['duration']);
        $this->assertSame(Visitor::COOKIE, $this->row('visitor')['name']);
        $this->assertSame(Visitor::LIFETIME_MONTHS.' meses', $this->row('visitor')['duration']);
        $this->assertSame(CookieConsent::COOKIE_NAME, $this->row('consent')['name']);
        $this->assertStringStartsWith('24 meses', $this->row('consent')['duration']);
        $this->assertSame(Auth::guard('web')->getRecallerName(), $this->row('remember')['name']);
        $this->assertStringStartsWith(RememberedDevice::DAYS.' días', $this->row('remember')['duration']);

        config(['session.lifetime' => 90]);
        $this->assertSame('90 minutos desde tu última visita', $this->row('session')['duration']);
    }

    /** `#858`: la cookie de recuerdo dice que solo se pone si se pide, y no se hace pasar por necesaria (no está exenta). */
    public function test_the_remember_cookie_says_it_is_only_set_when_asked(): void
    {
        $this->assertStringContainsString('Solo si marcas «Mantener la sesión iniciada en este dispositivo»', $this->row('remember')['when']);
        $this->assertSame('Preferencia (la pides tú)', $this->row('remember')['category']);
    }

    private function switchEverythingOn(): void
    {
        $this->set('security.turnstile_site_key', '0x4AAAAAAAsitekey');
        $this->set('security.turnstile_secret', '0x4AAAAAAAsecret');
        Turnstile::flushCache();
        $this->set('address.maps_embed_url', 'https://www.google.com/maps/embed?pb=!1m18!1m12');
        $this->set('social.feed_embed_url', 'https://snapwidget.com/embed/123456');
        $this->set(Drivers::KEY_DRIVER, Drivers::POSTHOG);
        $this->set(Drivers::KEY_POSTHOG_PROJECT, 'phc_'.str_repeat('a', 24));
        $this->set(EmailOpenMarks::SETTING, '1');
        $this->set(Pixels::KEY_META_PIXEL_ID, '1234567890123');
    }

    /**
     * Un tercero (o el píxel de los correos, `#860`), solo si está encendido. ⚠️ El widget de redes NO sale aunque el panel
     * lo tenga: nada lo pinta desde `#309` (`DEUDA.md`), y una fila suya nombraría un tercero que no se carga.
     */
    public function test_each_third_party_appears_only_when_it_is_switched_on(): void
    {
        $this->switchEverythingOn();

        $this->assertSame(['session', 'xsrf', 'visitor', 'consent', 'remember', 'redsys', 'turnstile', 'maps', 'posthog', 'email_opens', 'meta'], $this->keys());
        $this->assertSame('Publicidad (con tu permiso)', $this->row('meta')['category']);
        $this->assertSame('Nosotros (cookie propia)', $this->row('email_opens')['holder']);
        $this->assertSame(EmailSend::RETENTION_MONTHS.' meses, con el correo enviado', $this->row('email_opens')['duration']);
    }

    /**
     * `#860`: lo que se PIDE es lo que está encendido, con las condiciones del listado. `analytics`, siempre (su parte propia
     * no tiene interruptor); `social`, nunca mientras nada la pinte.
     */
    public function test_it_offers_only_the_categories_switched_on(): void
    {
        $this->assertSame(['analytics'], CookieInventory::offered());

        $this->set('social.feed_embed_url', 'https://snapwidget.com/embed/123456');
        $this->assertSame(['analytics'], CookieInventory::offered());

        $this->set(Pixels::KEY_META_PIXEL_ID, '1234567890123');
        $this->assertSame(['analytics', 'marketing'], CookieInventory::offered());

        $this->set('address.maps_embed_url', 'https://www.google.com/maps/embed?pb=!1m18!1m12');
        $this->assertSame(['maps', 'analytics', 'marketing'], CookieInventory::offered());
    }

    /** `#860`: el aviso de siempre nombra solo lo que pide — sin píxeles no dice «publicidad». */
    public function test_the_banner_names_only_what_it_asks(): void
    {
        $intro = 'Usamos cookies propias para que la web funcione y para medir la audiencia de forma anónima. Solo con tu permiso: ';

        $this->assertSame($intro.'el análisis de uso vinculado a tu cuenta.', CookieInventory::bannerText('es'));

        $this->switchEverythingOn();
        $this->assertSame($intro.'el mapa de Google, el análisis de uso vinculado a tu cuenta y la publicidad.', CookieInventory::bannerText('es'));
        $this->assertStringEndsWith('the Google map, usage analytics linked to your account and advertising.', CookieInventory::bannerText('en'));
    }

    /** `#860`: «Análisis» dice la herramienta y el píxel de los correos solo si están encendidos. */
    public function test_the_analytics_text_names_the_tool_and_the_email_pixel_only_when_on(): void
    {
        $this->assertSame(
            'Permite vincular tu navegación a tu cuenta cuando entras o compras, para entender cómo usas la web. La medición anónima de la audiencia no necesita este permiso.',
            CookieInventory::panel('es')['analytics_desc'],
        );

        $this->switchEverythingOn();
        $desc = CookieInventory::panel('es')['analytics_desc'];
        $this->assertStringContainsString('También usa una herramienta de análisis', $desc);
        $this->assertStringContainsString('si abres los correos que te enviamos', $desc);
        $this->assertStringEndsWith('La medición anónima de la audiencia no necesita este permiso.', $desc);
        $this->assertArrayNotHasKey('analytics_tool', CookieInventory::panel('es'));
    }

    /** Cada fila, entera y en cada idioma: ninguna clave de `lang` a la vista. */
    public function test_every_row_is_complete_in_every_language(): void
    {
        $this->switchEverythingOn();

        foreach (['es', 'en', 'fr'] as $locale) {
            foreach (CookieInventory::rows($locale) as $row) {
                foreach (['name', 'holder', 'purpose', 'duration', 'category', 'when'] as $field) {
                    $this->assertNotSame('', $row[$field], "{$locale} · {$row['key']} · {$field}");
                    $this->assertStringNotContainsString('cookies.inventory.', $row[$field], "{$locale} · {$row['key']} · {$field}");
                }
            }
        }
    }

    public function test_the_cookies_document_carries_the_list_and_the_others_do_not(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->getJson('/api/v1/legal/documents/cookies?lang=es')
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('inventory.title', 'Las cookies de esta web, una a una')
            ->assertJsonPath('inventory.cookies.0.key', 'session');

        $this->getJson('/api/v1/legal/documents/privacidad?lang=es')->assertOk()->assertJsonMissingPath('inventory');
    }
}
