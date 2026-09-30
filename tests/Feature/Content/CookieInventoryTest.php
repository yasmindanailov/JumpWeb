<?php

namespace Tests\Feature\Content;

use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Identity\Services\RememberedDevice;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Drivers;
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

    public function test_each_third_party_appears_only_when_it_is_switched_on(): void
    {
        $this->set('security.turnstile_site_key', '0x4AAAAAAAsitekey');
        $this->set('security.turnstile_secret', '0x4AAAAAAAsecret');
        Turnstile::flushCache();
        $this->set('address.maps_embed_url', 'https://www.google.com/maps/embed?pb=!1m18!1m12');
        $this->set('social.feed_embed_url', 'https://snapwidget.com/embed/123456');
        $this->set(Drivers::KEY_DRIVER, Drivers::POSTHOG);
        $this->set(Drivers::KEY_POSTHOG_PROJECT, 'phc_'.str_repeat('a', 24));
        $this->set(Pixels::KEY_META_PIXEL_ID, '1234567890123');

        $this->assertSame(['session', 'xsrf', 'visitor', 'consent', 'remember', 'redsys', 'turnstile', 'maps', 'social', 'posthog', 'meta'], $this->keys());
        $this->assertStringContainsString('SnapWidget', $this->row('social')['name']);
        $this->assertSame('Publicidad (con tu permiso)', $this->row('meta')['category']);
    }

    /** Cada fila, entera y en cada idioma: ninguna clave de `lang` a la vista. */
    public function test_every_row_is_complete_in_every_language(): void
    {
        $this->set('address.maps_embed_url', 'https://www.google.com/maps/embed?pb=!1m18!1m12');

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
