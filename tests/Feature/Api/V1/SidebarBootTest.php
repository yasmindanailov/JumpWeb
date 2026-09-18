<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\ApiTestCase;

/**
 * F4 · T1 — `GET /api/v1/sidebar/boot` y `sidebar/session` (`docs/specs/cajon-empaquetable.md` §4.5).
 *
 * El arranque del cajón tiene desde hoy DOS transportes —el atributo `data-boot` que pinta el layout
 * y estas dos rutas— y lo que aquí se fija es que sigan siendo **un solo modelo de lectura**
 * (`Http\Sidebar\SidebarBoot`): el día que alguien añada un rótulo a uno y no al otro, el cajón
 * montado desde una landing ajena lo pintaría MUDO (`i18n.js` devuelve cadena vacía cuando falta una
 * clave, `#333`) y nada más avisaría.
 */
class SidebarBootTest extends ApiTestCase
{
    /** El `data-boot` que pinta una página del producto, ya decodificado. */
    private function layoutBoot(TestResponse $page): array
    {
        $this->assertSame(1, preg_match('/id="sidecart-spa" data-boot="([^"]*)"/', (string) $page->getContent(), $m), 'La página no lleva el hueco del motor con su `data-boot`.');

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Lo que hace el cliente con las dos lecturas, que es lo que hace `SidebarBoot::forCurrentRequest()`
     * en el servidor. El subgrupo `google` viaja en el layout SOLO en su puerta y en la API siempre que
     * la instalación ofrezca Google (no sabe en qué página está quien pregunta): se aparta para comparar.
     *
     * @param  array<string, mixed>  $boot
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function merged(array $boot, array $session): array
    {
        unset($boot['account']['google']);

        return [
            'outcome' => $session['outcome'],
            'orderCode' => $session['orderCode'],
            'messages' => $boot['messages'],
            'ui' => $boot['ui'],
            'account' => array_replace($boot['account'], $session['account']),
            // En la API `locales` va siempre (lista vacía sin sesión); en el layout la clave solo viaja con sesión.
            ...($session['locales'] !== [] ? ['locales' => $session['locales']] : []),
            'auth' => $boot['auth'],
            'userId' => $session['userId'],
            'accountContext' => $session['accountContext'],
            'urls' => $boot['urls'] + $session['urls'],
        ];
    }

    public function test_the_shared_half_is_public_cacheable_and_revalidates(): void
    {
        $response = $this->getJson(self::ROOT.'/sidebar/boot?lang=es')
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonStructure(['messages', 'ui', 'account' => ['login', 'register', 'forgot', 'nav', 'sidecart', 'verify'], 'auth', 'urls' => ['contact', 'terms', 'privacy']]);

        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=300', $cache);

        $etag = (string) $response->headers->get('ETag');
        $this->assertNotSame('', $etag, 'Sin `ETag` no hay 304: el cliente se bajaría 18 kB de rótulos en cada visita.');

        $this->withHeader('If-None-Match', $etag)->get(self::ROOT.'/sidebar/boot?lang=es')->assertStatus(304);
    }

    /**
     * ⚠️ La regla que hace CIERTA la caché: la respuesta es función de su URL. Si el idioma saliera de
     * la sesión o de `Accept-Language` —como en el resto de la API—, una caché serviría francés a quien
     * pidió español.
     */
    public function test_the_language_comes_from_the_url_and_from_nowhere_else(): void
    {
        $es = $this->getJson(self::ROOT.'/sidebar/boot?lang=es')->assertOk()->json('ui.loading');
        $fr = $this->withHeader('Accept-Language', 'es')
            ->withSession(['locale' => 'es'])
            ->getJson(self::ROOT.'/sidebar/boot?lang=fr')
            ->assertOk()
            ->json('ui.loading');

        $this->assertSame(__('ui.loading', locale: 'es'), $es);
        $this->assertSame(__('ui.loading', locale: 'fr'), $fr);
        $this->assertNotSame($es, $fr);
    }

    public function test_the_language_is_required_and_bounded(): void
    {
        $this->getJson(self::ROOT.'/sidebar/boot')->assertStatus(422)->assertValidResponse(422);
        $this->getJson(self::ROOT.'/sidebar/boot?lang=xx')->assertStatus(422);
        $this->getJson(self::ROOT.'/sidebar/session')->assertStatus(422);
    }

    /** La mitad pública no sabe quién mira: con sesión y sin ella, los mismos bytes. */
    public function test_the_shared_half_is_the_same_for_everyone(): void
    {
        $anonymous = $this->getJson(self::ROOT.'/sidebar/boot?lang=es')->getContent();

        $holder = User::factory()->create(['name' => 'Ada Lovelace', 'email_verified_at' => now()]);
        $authenticated = $this->actingAs($holder, 'web')->getJson(self::ROOT.'/sidebar/boot?lang=es')->getContent();

        $this->assertSame($anonymous, $authenticated);
        $this->assertStringNotContainsString('Ada', (string) $authenticated);
    }

    public function test_the_personal_half_answers_the_anonymous_visitor_with_stable_types(): void
    {
        $response = $this->getJson(self::ROOT.'/sidebar/session?lang=es')
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('userId', null)
            ->assertJsonPath('accountContext', null)
            ->assertJsonPath('outcome', null);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // Un grupo vacío de PHP es `[]` en JSON y uno lleno `{}`: el campo no puede cambiar de tipo.
        $this->assertStringContainsString('"account":{}', (string) $response->getContent());
        $this->assertStringContainsString('"urls":{}', (string) $response->getContent());
    }

    public function test_the_personal_half_carries_the_holder_and_the_session_only_labels(): void
    {
        $holder = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($holder, 'web')
            ->getJson(self::ROOT.'/sidebar/session?lang=es')
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('userId', $holder->id)
            ->assertJsonStructure(['account' => ['account' => ['title', 'password', 'privacy', 'dependents', 'card'], 'orders', 'purchases'], 'locales', 'accountContext']);
    }

    /** Leer CONSUME el desenlace del pago, como lo consume pintar la página: si no, el cajón se reabriría siempre. */
    public function test_reading_the_personal_half_consumes_the_pending_payment_outcome(): void
    {
        $session = ['purchase.confirmed_code' => 'JW-ABC123'];

        $this->withSession($session)
            ->getJson(self::ROOT.'/sidebar/session?lang=es')
            ->assertOk()
            ->assertJsonPath('outcome', 'confirmed')
            ->assertJsonPath('orderCode', 'JW-ABC123')
            ->assertSessionMissing('purchase.confirmed_code');

        $this->getJson(self::ROOT.'/sidebar/session?lang=es')->assertJsonPath('outcome', null);
    }

    /**
     * ⚠️⚠️ **UN modelo de lectura, DOS transportes.** Lo que pinta el layout en `data-boot` es, clave a
     * clave, lo que un cliente reconstruye con las dos lecturas de la API — para el visitante anónimo y
     * para el titular con sesión, que es donde viajan los rótulos del área de cliente.
     */
    public function test_the_two_api_reads_rebuild_exactly_what_the_layout_paints(): void
    {
        $this->assertSame(
            $this->layoutBoot($this->get('/')->assertOk()),
            $this->merged(
                $this->getJson(self::ROOT.'/sidebar/boot?lang=es')->json(),
                $this->getJson(self::ROOT.'/sidebar/session?lang=es')->json(),
            ),
            'El arranque anónimo por la API ya no es el que pinta el layout.',
        );

        $holder = User::factory()->create(['email_verified_at' => now()]);

        $painted = $this->layoutBoot($this->actingAs($holder, 'web')->get('/')->assertOk());
        $rebuilt = $this->merged(
            $this->actingAs($holder, 'web')->getJson(self::ROOT.'/sidebar/boot?lang=es')->json(),
            $this->actingAs($holder, 'web')->getJson(self::ROOT.'/sidebar/session?lang=es')->json(),
        );

        $this->assertSame($holder->id, $painted['userId']);
        $this->assertArrayHasKey('purchases', $painted['account'], 'Con sesión tienen que viajar los rótulos del área.');
        $this->assertSame($painted, $rebuilt, 'El arranque CON SESIÓN por la API ya no es el que pinta el layout.');
    }

    /** El payload no vuelve a componerse DENTRO de la plantilla: sería la segunda fuente que un día discrepa. */
    public function test_the_layout_paints_the_boot_and_does_not_compose_it(): void
    {
        $layout = (string) file_get_contents(resource_path('views/components/layout.blade.php'));

        $this->assertStringContainsString('SidebarBoot::forCurrentRequest()', $layout);
        $this->assertStringNotContainsString("'messages' =>", $layout, 'El layout vuelve a componer el arranque a mano: eso vive en `Http\Sidebar\SidebarBoot`.');
        $this->assertStringNotContainsString("__('tickets')", $layout);
    }
}
