<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Identity\Models\User;
use App\Http\Sidebar\SidebarEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.7·2b·3 — **el punto de montaje del cajón SPA**, que es el único que queda.
 *
 * Nació como `SidebarEngineTest` para vigilar la CONVIVENCIA de los dos motores tras el flag
 * `sidebar.engine` (paso 4.1). Retirado `Purchase.php` y con él el flag, sus tres casos del ajuste y
 * el que comparaba un motor con el otro se quedaron sin sujeto y se fueron con ellos
 * (`CONVENCIONES §3.quater`: se clasifica por el SUJETO, no por la regla que mencionan). Lo que
 * sigue vivo —y por eso el fichero se queda, con nombre nuevo— es lo que el SERVIDOR le pone al
 * cajón en el HTML y que el cliente no puede pedir:
 *
 *  1. que el hueco de montaje exista dentro del cuerpo del cajón;
 *  2. el payload de i18n, la identidad del titular y el desenlace del pago ya CONSUMIDO;
 *  3. y que `@livewireScripts` se siga sirviendo, que es la guarda delicada de todo esto.
 */
class SidebarMountTest extends TestCase
{
    use RefreshDatabase;

    // ── El hueco de montaje ───────────────────────────────────────────────────────────────────

    /**
     * El cuerpo del cajón y su hueco van JUNTOS en un caso: `sidecart__body` es el contenedor que
     * `app.js` abre y cierra, y el hueco es lo que se monta dentro. Antes eran dos casos espejo —uno
     * por motor— y el del motor viejo aseveraba `assertDontSee('id="sidecart-spa"')`, que es
     * exactamente lo que ahora tiene que verse.
     */
    public function test_the_page_carries_the_drawer_body_with_the_spa_mount_point_inside(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('sidecart__body', false);
        $response->assertSee('id="sidecart-spa"', false);
    }

    /**
     * ⚠️⚠️ **La ÚNICA aserción de `livewire.js` de toda la suite, y lo que de verdad vigila NO es lo
     * que decía la nota que heredó.** Lo que sigue está MEDIDO con la tabla de verdad entera, no
     * leído (`CONVENCIONES §3.quater`), porque la primera mutación salió INERTE.
     *
     * Lo que hay que proteger es el RESULTADO: que el bundle de Livewire llegue a la página, porque
     * **Alpine lo trae Livewire** —`app.js` no lo importa ni lo arranca, usa el global que Livewire
     * expone—. Sin él caen a la vez `$store.purchase` (lo que ABRE este cajón desde los once puntos
     * de la landing), `$store.auth` y los tres modales de auth.
     *
     * Y a ese resultado llegan **DOS fuentes redundantes**, que es justo por lo que una mutación de
     * una sola rama no dice nada (§3.quater, trampa 4):
     *
     *  · la directiva `@livewireScripts`, que emite **incondicionalmente**;
     *  · la **auto-inyección** de Livewire (`inject_assets`, default del paquete: el proyecto no
     *    publica `config/livewire.php`), que inyecta el bundle al terminar la petición **solo si un
     *    componente Livewire llegó a renderizarse** (`SupportAutoInjectedAssets`:
     *    `$hasRenderedAComponentThisRequest`). Hoy el layout renderiza cuatro: los tres modales de
     *    auth y `account-context`.
     *
     * Tabla medida el 2026-08-21 sobre este mismo caso:
     * directiva SÍ + componentes SÍ → verde (estado real) · directiva NO + componentes SÍ → **verde**
     * · directiva SÍ + componentes NO → **verde** · directiva NO + componentes NO → **ROJO**.
     *
     * ▶ De ahí las dos consecuencias que importan al siguiente que pase por aquí:
     *  1. **Retirar hoy `@livewireScripts` NO rompe nada** —la auto-inyección lo tapa—, así que la
     *     nota de `layout.blade.php` que lo daba por fatal estaba equivocada y se ha corregido.
     *  2. **El peligro real es el ÁREA DE CLIENTE** (`DECISIONES #66`): ese trabajo retira el modal
     *     de auth de la cabecera y puede llevarse `account-context`. El día que caiga el último
     *     componente Livewire del layout, la directiva pasa a ser la **fuente única** y retirarla sí
     *     deja la web sin Alpine. Este caso es el que se pondrá rojo entonces, y por eso asevera el
     *     resultado y no la directiva.
     *
     * Eran dos casos —uno por motor— porque Livewire memoiza que ya emitió sus assets y ese estado
     * estático sobrevive entre peticiones del mismo test (familia `SUITE-02`): con los dos modos en
     * un solo caso, el segundo `get()` no los emitía. Con un motor único basta una petición, así que
     * el motivo de partirlos se fue con el flag.
     */
    public function test_livewire_scripts_are_still_served(): void
    {
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
        $anonymous = $this->bootPayload($this->get('/')->getContent());

        $this->assertArrayHasKey('userId', $anonymous, 'la clave debe estar siempre: la purga compara contra null');
        $this->assertNull($anonymous['userId']);

        $user = User::factory()->create();
        $authenticated = $this->bootPayload($this->actingAs($user)->get('/')->getContent());

        $this->assertSame($user->id, $authenticated['userId'] ?? null);
    }

    /**
     * ⚠️ **El que consume el desenlace es el LAYOUT.** El paso 4.0a dejó anotado el contraste: con el
     * componente `lazy` se usaba `peek()` aquí porque su `mount()` corría en una petición POSTERIOR;
     * con la SPA **el motor es este mismo documento**, así que el que consume es este.
     *
     * Y consumir importa: una clave que sobreviva reabre el cajón en cada página hasta que caduque
     * la sesión.
     */
    public function test_the_payment_outcome_travels_in_the_payload_and_is_consumed(): void
    {
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
