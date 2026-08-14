<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Services\CatalogSettings;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Turnstile;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 4 · paso 4.0b — `GET /api/v1/config`.
 *
 * Los cuatro ajustes de instalación que Blade le inyectaba a la vista y que ningún endpoint
 * publicaba. Sin ellos un cliente **aprende las reglas chocándose** —descubre el tope de cesta con
 * un 422—, que es una regla de negocio naciendo en el cliente.
 *
 * Los dos casos que de verdad importan aquí no son «devuelve los valores», sino:
 *  · que la URL del bloque de registro llegue **saneada**, porque es la única defensa que queda
 *    (`SEC-07`): un cliente JSON no tiene escape de plantilla que remate la jugada;
 *  · que el endpoint **no aprenda** a devolver cosas del titular. Es público y cacheable.
 */
class PublicConfigTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/config';

    /** El camino normal de una instalación que no ha configurado nada opcional. */
    public function test_it_publishes_the_defaults_of_a_bare_installation(): void
    {
        $this->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('registration', null)
            ->assertJsonPath('turnstile_site_key', null)
            ->assertJsonPath('catalog_search_min_items', CatalogSettings::searchMinItems())
            ->assertJsonPath('cart_max_lines', OrderCreator::MAX_LINES_PER_CART);
    }

    /** Público de verdad: el cajón se abre sin cuenta, y estos valores hacen falta antes de haberla. */
    public function test_it_needs_no_session(): void
    {
        $this->assertGuest();

        $this->getJson(self::PATH)->assertOk();
    }

    // ── El bloque de registro externo (#216) ──────────────────────────────────────────────────

    public function test_the_registration_block_travels_translated(): void
    {
        Setting::updateOrCreate(['key' => 'registration.url'], ['value' => 'https://registro.example.com']);
        Setting::updateOrCreate(['key' => 'registration.label.es'], ['value' => 'Regístrate']);
        Setting::updateOrCreate(['key' => 'registration.description.es'], ['value' => 'Ahorra tiempo en la puerta']);
        Setting::flushMemo();

        $this->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('registration.url', 'https://registro.example.com')
            ->assertJsonPath('registration.label', 'Regístrate')
            ->assertJsonPath('registration.description', 'Ahorra tiempo en la puerta');
    }

    /** Sin traducir, cae al literal por defecto — no al vacío ni a la clave. */
    public function test_an_untranslated_block_falls_back_to_the_default_wording(): void
    {
        Setting::updateOrCreate(['key' => 'registration.url'], ['value' => 'https://registro.example.com']);
        Setting::flushMemo();

        $response = $this->getJson(self::PATH)->assertOk()->assertValidResponse(200);

        $this->assertNotSame('', $response->json('registration.label'));
        $this->assertNotSame('', $response->json('registration.description'));
        $this->assertStringNotContainsString('landing.nav.', (string) $response->json('registration.label'));
    }

    /**
     * **`SEC-07`, y es el caso crítico de este endpoint.** La URL la edita un operador en el panel.
     * En la web el escape de Blade remataba la defensa; un cliente JSON no tiene escape que la
     * remate —Vue no filtra esquemas en un `:href`—, así que si el saneado no ocurre antes de
     * serializar, no ocurre en ninguna parte.
     *
     * Con una URL que no es `http(s)`, el bloque entero llega `null` en vez de viajar a medias.
     */
    public function test_a_dangerous_registration_url_never_reaches_the_client(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,<script>', 'ftp://x.example', 'noesunaurl'] as $hostile) {
            Setting::updateOrCreate(['key' => 'registration.url'], ['value' => $hostile]);
            Setting::flushMemo();

            $response = $this->getJson(self::PATH)->assertOk();

            $this->assertNull(
                $response->json('registration'),
                "«{$hostile}» ha llegado al cliente: el saneado de SEC-07 no se está aplicando"
            );
            $this->assertStringNotContainsString('javascript:', $response->getContent() ?: '');
        }
    }

    // ── Los dos números, con su operador ──────────────────────────────────────────────────────

    /** El umbral es *data-driven*: si el operador lo cambia, el cliente se entera. */
    public function test_the_search_threshold_follows_the_setting(): void
    {
        Setting::updateOrCreate(['key' => 'catalog.search_min_items'], ['value' => '7']);
        Setting::flushMemo();

        $this->getJson(self::PATH)->assertOk()->assertJsonPath('catalog_search_min_items', 7);
    }

    /**
     * El tope publicado tiene que ser EL DEL SERVIDOR, no una copia. Si divergen, el cliente ofrece
     * un botón que el checkout rechaza — o bloquea uno que habría funcionado.
     */
    public function test_the_cart_cap_is_the_servers_own(): void
    {
        $this->getJson(self::PATH)
            ->assertOk()
            ->assertJsonPath('cart_max_lines', OrderCreator::MAX_LINES_PER_CART);
    }

    // ── Anti-bot ──────────────────────────────────────────────────────────────────────────────

    /** La *sitekey* es pública por diseño; la secreta no puede salir de aquí jamás. */
    public function test_it_publishes_the_public_turnstile_key_and_never_the_secret(): void
    {
        Setting::updateOrCreate(['key' => 'security.turnstile_site_key'], ['value' => 'site-visible', 'group' => 'security']);
        Setting::updateOrCreate(['key' => 'security.turnstile_secret'], ['value' => 'secreto-jamas', 'group' => 'security']);
        Setting::flushMemo();
        Turnstile::flushCache();

        $response = $this->getJson(self::PATH)->assertOk()->assertValidResponse(200);

        $response->assertJsonPath('turnstile_site_key', 'site-visible');
        $this->assertStringNotContainsString('secreto-jamas', $response->getContent() ?: '');
    }

    /**
     * ⚠️ **Media configuración es NO configuración, y el campo tiene que decirlo.**
     *
     * El anti-bot exige las DOS claves: con solo la pública, `Turnstile::enabled()` es `false`, la web
     * **no pinta el widget** y `verify()` deja pasar el alta. Publicar la clave en ese estado le decía
     * a un cliente que dibujara un captcha **que su propio servidor no comprueba**: un árbol distinto
     * al de la web, un script de terceros de más y un obstáculo para el usuario a cambio de ninguna
     * defensa.
     *
     * Es un estado alcanzable de verdad —se configura una clave y se deja la otra para luego— y lo
     * destapó el paso de registro de la SPA, que es el primer cliente que lee este campo para decidir
     * si pinta el widget.
     */
    public function test_half_configured_anti_bot_reads_as_not_configured(): void
    {
        foreach ([
            'solo la clave pública' => ['security.turnstile_site_key' => 'site-visible'],
            'solo la secreta' => ['security.turnstile_secret' => 'secreto-jamas'],
        ] as $label => $settings) {
            Setting::query()->whereIn('key', ['security.turnstile_site_key', 'security.turnstile_secret'])->delete();

            foreach ($settings as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'security']);
            }

            Setting::flushMemo();
            Turnstile::flushCache();

            $this->assertFalse(
                Turnstile::enabled(),
                "con «{$label}» el anti-bot no está activo: si esto cambia, este test ya no prueba lo que dice"
            );

            $this->getJson(self::PATH)
                ->assertOk()
                ->assertValidResponse(200)
                ->assertJsonPath(
                    'turnstile_site_key', null,
                    "Con «{$label}» el endpoint anuncia un anti-bot que NO está activo. Un cliente ".
                    'fiel al contrato pintaría un captcha que el servidor no verifica.'
                );
        }
    }

    // ── Lo que NUNCA puede llevar ─────────────────────────────────────────────────────────────

    /**
     * La regla de admisión del endpoint, hecha ejecutable: **es público y cacheable**, así que un
     * campo del titular filtraría datos de una cuenta a cualquiera que compartiera caché. Se
     * comprueba comparando la respuesta con y sin sesión: tienen que ser IDÉNTICAS.
     */
    public function test_the_answer_is_the_same_with_and_without_a_session(): void
    {
        $anonymous = $this->getJson(self::PATH)->assertOk()->json();

        $authenticated = $this->actingAs(User::factory()->create())
            ->getJson(self::PATH)->assertOk()->json();

        $this->assertSame(
            $anonymous, $authenticated,
            'la respuesta cambia con la sesión: este endpoint es público y cacheable, no puede depender del titular'
        );
    }
}
