<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * `RGPD-04` en la WEB: toda página lleva `Cache-Control: no-store`, 2026-08-23.
 *
 * ⚠️⚠️ **Este fichero nace de un hueco que llevaba abierto desde siempre y que nadie podía ver.**
 * Hasta hoy la cabecera no la ponía ningún middleware del proyecto: la ponía **Livewire**
 * —`SupportDisablingBackButtonCache::boot()` es un hook de componente que enciende un flag, y un
 * middleware global del paquete estampa la cabecera si se encendió—, o sea que el sitio entero
 * llevaba `no-store` **como efecto colateral de que el layout renderizara un componente Livewire**.
 *
 * ▶ **Y las doce aserciones de `no-store` que ya tenía la suite no lo cubrían**: son de `/api/v1`,
 * de los dos PDF operativos, del post-form y del export. **Ninguna miraba una página web del
 * layout.** Así que el día que el último componente Livewire se retirara —que es justo lo que hace
 * `specs/account-context-vue.md`— el sitio entero habría pasado a `no-cache, private` **con la
 * suite en verde**. Es el cuño de `DECISIONES #112`: una guarda que estaba en verde sin medir nada,
 * salvo que aquí ni siquiera existía.
 *
 * ⚠️ **Se asevera `no-store`, NO la cadena exacta**, y es deliberado: mientras queden componentes
 * Livewire en alguna ruta (hoy `/restablecer-contrasena/{token}`) su middleware global corre por
 * FUERA y gana con su propia cadena. Las dos llevan `no-store`, que es la propiedad; fijar la cadena
 * haría fallar el caso por un motivo que no es el que vigila —el error de `DECISIONES #115`—.
 *
 * ⚠️⚠️ **HAY DOS FUENTES REDUNDANTES, ASÍ QUE MUTAR UNA SOLA NO DICE NADA.** Es literalmente la
 * lección de `DECISIONES #112` (la aserción de `livewire.js` llevaba tiempo inerte y la doc la daba
 * por crítica), y por eso la tabla se midió ENTERA el 2026-08-23, corriendo este fichero en cada
 * combinación:
 *
 *     middleware SÍ + componente Livewire SÍ ..... 5 verdes   ← el estado real de hoy
 *     middleware NO + componente Livewire SÍ ..... 5 verdes   ← ⚠️ Livewire tapa la ausencia
 *     middleware NO + componente Livewire NO ..... 4 ROJOS    ← la migración sin este arreglo
 *     middleware SÍ + componente Livewire NO ..... 5 verdes   ← la migración con él
 *     middleware SÍ, sin la puerta de `/api/v1` .. 1 ROJO     ← la puerta también discrimina
 *
 * ▶ La fila que importa es la tercera: **hoy este fichero pasaría igual sin el middleware**, y solo
 * se vuelve discriminante el día que el último componente Livewire se retire. Escribirlo aquí es lo
 * que impide que el siguiente que pase lo lea como una guarda ya ganada.
 *
 * ▶ Y la CUARTA fila costó un rediseño: con el middleware registrado en el grupo `web` daba **un
 * rojo** —el de la página de error—, porque un middleware de grupo solo corre en rutas que casan y
 * un 404 de URI desconocida no entra en `web` **pero sí pinta el layout, con el nav y su PII**. De
 * ahí que se registre GLOBAL, como el de Livewire al que sustituye.
 */
class NoStoreWebResponsesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    /**
     * La home ANÓNIMA. Va primero porque es la ruta de más tráfico y la que se quedaría sin cabecera
     * en cuanto el layout deje de renderizar Livewire.
     */
    public function test_the_anonymous_home_is_no_store(): void
    {
        $this->assertNoStore($this->get('/'));
    }

    /**
     * ⚠️ **Y la página CON SESIÓN es la que de verdad lo necesita**: `site/nav.blade.php` pinta el
     * nombre de pila del titular y el aviso de formulario pendiente en toda página autenticada, y el
     * nav **no** se migra a Vue. Sin `no-store`, esos bytes persisten en la caché en disco de un
     * dispositivo compartido y el bfcache los devuelve al pulsar «Atrás» tras cerrar sesión.
     */
    public function test_an_authenticated_page_is_no_store(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->assertNoStore($this->actingAs($user)->get('/'));
    }

    /**
     * Una página cualquiera que NO es la home: la cabecera es de TODA la web, no de una ruta.
     * Si alguien la moviera a un controlador concreto, este caso lo diría.
     */
    public function test_it_covers_the_whole_site_and_not_one_route(): void
    {
        $this->assertNoStore($this->get(route('contacto')));
    }

    /**
     * ⚠️⚠️ **EL CASO QUE OBLIGÓ A REGISTRARLO GLOBAL.** Con el middleware en el grupo `web` este caso
     * era **rojo**: un middleware de grupo solo corre en rutas que CASAN, y un 404 de URI desconocida
     * no entra en el grupo — **pero sí pinta el layout**, o sea el nav con el nombre del titular. Hoy
     * esa página lleva `no-store` únicamente porque el middleware de Livewire es global.
     *
     * ▶ Así que además de vigilar la conducta, este caso vigila **el registro**: devolverlo al grupo
     * `web` lo pone rojo y dice exactamente por qué.
     */
    public function test_it_also_reaches_an_error_page(): void
    {
        $response = $this->get('/una-ruta-que-no-existe-'.__FUNCTION__);

        $response->assertNotFound();
        $this->assertNoStore($response);
    }

    /**
     * ⚠️⚠️ **LA PUERTA: `/api/v1` NO se toca, y esto es lo que impide que este middleware global se
     * lleve por delante la cacheabilidad del catálogo anónimo.**
     *
     * Allí decide `Api\NoStoreWhenAuthenticated`, **por identidad**: un endpoint público pedido por
     * un anónimo conserva su caché, que es la que `PERF-02` protege. Sin esta guarda, el día que
     * alguien «simplifique» quitando el `ApiSurface::handles()` nadie lo notaría — la API seguiría
     * funcionando, solo que sin caché, y eso no falla: se paga.
     */
    public function test_the_public_api_keeps_its_cacheability(): void
    {
        $cacheControl = (string) $this->getJson('/api/v1/config')
            ->assertOk()
            ->headers->get('Cache-Control');

        $this->assertStringNotContainsString(
            'no-store', $cacheControl,
            "Un endpoint PÚBLICO de la API ha salido `no-store` (`{$cacheControl}`). Quien decide ".
            'ahí es `Api\NoStoreWhenAuthenticated`, y lo hace por identidad a propósito: el catálogo '.
            'y la disponibilidad anónimos tienen que conservar su caché (`PERF-02`).'
        );
    }

    private function assertNoStore(TestResponse $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString(
            'no-store', $cacheControl,
            "La respuesta sale con `Cache-Control: {$cacheControl}`, sin `no-store`.\n".
            "⚠️ `no-cache` NO basta: permite que los bytes persistan en la caché EN DISCO del\n".
            "navegador de un dispositivo compartido, y `no-store` es además lo ÚNICO que inhabilita\n".
            "el bfcache — sin él, cerrar sesión y pulsar «Atrás» devuelve la página anterior entera.\n".
            'Y la web lleva PII con sesión pase lo que pase: el nav pinta el nombre del titular.'
        );
    }
}
