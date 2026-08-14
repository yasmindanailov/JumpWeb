<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\SidebarSettings;
use App\Http\Sidebar\SidebarEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.1 — **la convivencia de los dos motores del cajón**
 * (`docs/specs/sidebar-spa.md` §4.9).
 *
 * El flag existe para poder comparar los dos en vivo y volver atrás sin desplegar, que es lo que
 * `CE-1` pide antes de retirar nada. Estas guardas cubren lo que la revisión del spec avisó que se
 * rompe solo:
 *
 *  1. el default y el fallback son **Livewire** — el fallback no puede ser el motor en obras;
 *  2. la bifurcación **no es solo qué componente se pinta**: alcanza al hueco de montaje, al payload
 *     de i18n y a quién consume el desenlace del pago;
 *  3. `@livewireScripts` se queda en los DOS modos, porque **Alpine lo trae Livewire** y sin Alpine
 *     no hay store que abra el cajón;
 *  4. los casos de SPA se **añaden**, no sustituyen a los de Livewire: el significado de la suite no
 *     puede depender de una fila de la base de datos.
 */
class SidebarEngineTest extends TestCase
{
    use RefreshDatabase;

    private function useEngine(string $engine): void
    {
        Setting::updateOrCreate(['key' => SidebarSettings::ENGINE_KEY], ['value' => $engine]);
        Setting::flushMemo();
    }

    // ── El ajuste ─────────────────────────────────────────────────────────────────────────────

    public function test_the_default_engine_is_livewire(): void
    {
        $this->assertSame(SidebarSettings::ENGINE_LIVEWIRE, SidebarSettings::engine());
        $this->assertFalse(SidebarSettings::usesSpa());
    }

    /**
     * Un typo en el panel no puede dejar la web sin cajón de reservas, que es la única superficie
     * que vende. Por eso un valor desconocido cae al motor que funciona en vez de lanzar.
     */
    public function test_an_unknown_engine_falls_back_to_livewire_instead_of_breaking(): void
    {
        $this->useEngine('vue-3-beta');

        $this->assertSame(SidebarSettings::ENGINE_LIVEWIRE, SidebarSettings::engine());
    }

    public function test_the_spa_engine_is_recognised_when_it_is_set(): void
    {
        $this->useEngine(SidebarSettings::ENGINE_SPA);

        $this->assertTrue(SidebarSettings::usesSpa());
    }

    // ── Lo que cambia en la página ────────────────────────────────────────────────────────────

    /** Caso ESPEJO del de abajo: se añade, no sustituye. Con el default, el cajón es el de siempre. */
    public function test_with_the_default_engine_the_livewire_drawer_is_rendered(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('sidecart__body', false);
        $response->assertDontSee('id="sidecart-spa"', false);
    }

    public function test_with_the_spa_engine_the_mount_point_replaces_the_livewire_drawer(): void
    {
        $this->useEngine(SidebarSettings::ENGINE_SPA);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="sidecart-spa"', false);
    }

    /**
     * ⚠️ **Alpine lo trae Livewire**, así que retirar sus scripts en modo SPA dejaría al cajón sin el
     * store que lo abre —y a los modales de auth y a `account-context`, que siguen siendo Livewire en
     * los dos modos, sin su motor—. Es el aviso que la v1 del spec no daba.
     */
    public function test_livewire_scripts_are_served_with_the_livewire_engine(): void
    {
        $this->get('/')->assertSee('livewire.js', false);
    }

    /**
     * ⚠️ Va en un test APARTE del anterior, y no por estilo: Livewire memoiza que ya ha emitido sus
     * assets para no duplicarlos, y ese estado **estático sobrevive entre peticiones del mismo
     * test**. Con los dos motores en un solo caso, el segundo `get()` no emitía los scripts y el
     * test «demostraba» una regresión que no existía. Es la misma familia de fallo que `SUITE-02`
     * documenta para los memos estáticos, encontrada aquí al comparar los dos modos.
     */
    public function test_livewire_scripts_are_served_with_the_spa_engine_too(): void
    {
        $this->useEngine(SidebarSettings::ENGINE_SPA);

        $this->get('/')->assertSee('livewire.js', false);
    }

    // ── El payload del montaje ────────────────────────────────────────────────────────────────

    /**
     * La SPA no tiene canal de i18n propio (§4.5): son 169 claves × 3 locales que salen de `__()` en
     * servidor. Viajan en el montaje, no por un endpoint, porque ya están resueltas al pintar la
     * página y pedirlas costaría una petición más justo en el arranque.
     */
    public function test_the_mount_point_carries_the_translations_of_the_active_locale(): void
    {
        $this->useEngine(SidebarSettings::ENGINE_SPA);

        $boot = $this->bootPayload($this->get('/')->getContent());

        $this->assertIsArray($boot['messages'] ?? null);
        $this->assertSame(__('tickets.title'), $boot['messages']['title'] ?? null);
    }

    /**
     * ⚠️ **Quién es el titular viaja con el HTML, y de eso depende una defensa de seguridad.**
     *
     * La cesta del cajón SPA vive en `localStorage` y lleva su dueño dentro; si cambia, se purga
     * (`DECISIONES #38(d)`). La identidad llega en el montaje —antes de que exista ningún `fetch`, en
     * cada carga de página— porque el **logout es una navegación completa**, y ese es justo el caso que
     * la sesión resolvía sola con `invalidate()` y que `localStorage` no tiene: sin este dato, la cesta
     * de quien acaba de salir sigue en pantalla para el siguiente que use el dispositivo.
     *
     * Se comprueban las DOS formas porque la purga compara contra `null`: si `auth()->id()` dejara de
     * viajar, el visitante anónimo parecería «el mismo de siempre» y no se purgaría nada.
     */
    public function test_the_mount_point_carries_who_the_owner_is(): void
    {
        $this->useEngine(SidebarSettings::ENGINE_SPA);

        $anonymous = $this->bootPayload($this->get('/')->getContent());

        $this->assertArrayHasKey('userId', $anonymous, 'la clave debe estar siempre: la purga compara contra null');
        $this->assertNull($anonymous['userId']);

        $user = User::factory()->create();
        $authenticated = $this->bootPayload($this->actingAs($user)->get('/')->getContent());

        $this->assertSame($user->id, $authenticated['userId'] ?? null);
    }

    /**
     * ⚠️ **Con la SPA, el que consume el desenlace es el LAYOUT**, y ese matiz lo dejó anotado el
     * paso 4.0a: allí se usa `peek()` porque el componente Livewire es `lazy` y su `mount()` corre en
     * una petición POSTERIOR. Aquí el motor es este mismo documento.
     *
     * Y consumir importa: una clave que sobreviva reabre el cajón en cada página hasta que caduque
     * la sesión.
     */
    public function test_the_payment_outcome_travels_in_the_payload_and_is_consumed(): void
    {
        $this->useEngine(SidebarSettings::ENGINE_SPA);

        SidebarEntry::confirmed('R-ABC123');

        $boot = $this->bootPayload($this->get('/')->getContent());

        $this->assertSame(SidebarEntry::OUTCOME_CONFIRMED, $boot['outcome'] ?? null);
        $this->assertSame('R-ABC123', $boot['orderCode'] ?? null);

        // Segunda carga: ya no queda nada, así que el cajón no se auto-abre otra vez.
        $second = $this->bootPayload($this->get('/')->getContent());

        $this->assertArrayHasKey('outcome', $second, 'la clave debe seguir estando, con valor nulo');
        $this->assertNull($second['outcome'], 'un desenlace que sobrevive reabre el cajón en cada página');
    }

    /**
     * El `peek()` del `<body>` y el `consume()` del hueco corren en la MISMA petición. Si el segundo
     * se llevara el valor antes que el primero, el cajón no se abriría solo y el cliente volvería de
     * pagar a una página normal. `SidebarEntry::consume()` está memoizado por petición para eso.
     */
    public function test_the_drawer_still_opens_itself_on_the_request_that_consumes(): void
    {
        $this->useEngine(SidebarSettings::ENGINE_SPA);

        SidebarEntry::confirmed('R-ABC123');

        $this->get('/')->assertSee('data-purchase-open="1"', false);
    }

    /** @return array<string, mixed> */
    private function bootPayload(string $html): array
    {
        if (preg_match('/id="sidecart-spa" data-boot="([^"]*)"/', $html, $matches) !== 1) {
            $this->fail('no se ha encontrado el punto de montaje de la SPA en la página');
        }

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }
}
