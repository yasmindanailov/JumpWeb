<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **La COSTURA DE INTENCIÓN está cableada de punta a punta** (`docs/specs/sidebar-spa.md` §4.1).
 *
 * Tres vistas de la landing no abren el cajón «vacío»: lo abren PIDIENDO algo concreto —los packs, o
 * las entradas de una zona—. La costura de 4.0a existe justo para que eso no dependa del motor:
 * la landing declara la intención y **cada motor registra cómo se aplica**.
 *
 * ⚠️⚠️ **Este test nace de un fallo REAL medido en staging con un navegador el 2026-08-21**
 * (`DECISIONES #117`). La cadena estaba construida hasta el penúltimo eslabón:
 *
 *   `@click` de la landing → `$store.purchase.openWith()` → `flushIntent()` → `applyIntent()` →
 *   `machine.queueIntent()` → … **y ahí se acababa.**
 *
 * `takeIntent()` —lo único que CONSUME la intención— no lo llamaba **nadie** en código de producción:
 * solo `machine.test.js`. Medido en vivo: tras pulsar el enlace profundo, con el cajón abierto y el
 * catálogo cargado, `machine.takeIntent()` **seguía devolviendo `{type:'packs'}`**. La intención se
 * encolaba y se quedaba ahí para siempre; el cajón abría en el catálogo raíz.
 *
 * ⚠️ **Y por qué ningún test lo vio, que es la lección**: `machine.test.js` prueba `queueIntent` y
 * `takeIntent` **como par, en aislamiento**, y pasa. Los dos extremos estaban probados y **nadie
 * cableaba el medio**. Un test unitario verde no dice nada sobre si alguien llama a lo que prueba.
 *
 * De ahí este test, que es del mismo tipo que {@see ScrollLockOwnerTest}: no prueba la CONDUCTA
 * —eso lo hace `machine.test.js`— sino que la pieza **tiene consumidor**.
 */
class SidebarIntentWiringTest extends TestCase
{
    /** Dónde vive la máquina que guarda la intención. */
    private const MACHINE = 'resources/js/sidebar/machine.js';

    /**
     * Dónde se busca al consumidor: código de producción del cajón y de la landing.
     *
     * ⚠️ Los `*.test.js` quedan FUERA a propósito, y es el corazón del caso: si contaran, el test
     * pasaría en verde con exactamente el fallo que existe —`machine.test.js` llama a `takeIntent()`
     * y la aplicación no—. Un consumidor que solo existe en un test no es un consumidor.
     */
    private function productionSources(): array
    {
        $root = base_path('resources/js');
        $files = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            $path = $file->getPathname();

            if (! preg_match('/\.(js|vue)$/', $path) || str_contains($path, '.test.')) {
                continue;
            }

            $files[str_replace(base_path().'/', '', $path)] = (string) file_get_contents($path);
        }

        return $files;
    }

    public function test_the_machine_still_offers_a_way_to_consume_the_queued_intent(): void
    {
        $machine = (string) file_get_contents(base_path(self::MACHINE));

        $this->assertStringContainsString('queueIntent(', $machine, 'La máquina ya no guarda intenciones.');
        $this->assertStringContainsString('takeIntent(', $machine, 'La máquina ya no ofrece consumir la intención.');
    }

    /**
     * ⚠️⚠️ **Y lo mismo con el aviso de «hay sesión»** (2026-08-23,
     * `specs/account-context-vue.md` §4.6).
     *
     * Es la MISMA familia de fallo y por eso vive aquí: una pieza declarada en un sitio, usada en
     * otro, y el medio sin comprobar. `account/session-gained.js` hace tres cosas —repintar el bloque
     * de cuenta, invalidar las próximas reservas y marcar `authChanged`—, y su `node --test` prueba
     * que las hace **cuando se le pide**. Lo que ese test **no puede decir** es que alguien se lo
     * pida: si `enterWith()` dejara de llamarlo, el módulo seguiría siendo correcto y su suite verde.
     *
     * ▶ Y tampoco lo dice el centinela del bundle: `/me/account-context` está en el chunk mientras el
     * store se importe, porque `refresh()` es una acción de Pinia y Rollup no la poda por no usarse.
     *
     * ⚠️ **El síntoma de que faltara sería silencioso**: quien entra en el paso 5 del embudo y vuelve
     * al catálogo seguiría viendo «Hola, saltador/a» y un botón de «Entrar» — y cerrar el cajón
     * dejaría de recargar, así que el nav se quedaría de invitado. Nada fallaría. Es literalmente el
     * fallo que hizo que este bloque fuera Livewire en 2026-06-14.
     */
    public function test_something_in_production_actually_announces_the_gained_session(): void
    {
        $modulo = 'account/session-gained.js';
        $consumers = [];

        foreach ($this->productionSources() as $path => $code) {
            if (str_contains($path, $modulo)) {
                continue;   // su propia definición no cuenta como uso
            }

            if (str_contains($code, 'sessionGained')) {
                $consumers[] = $path;
            }
        }

        $this->assertNotEmpty(
            $consumers,
            "NADIE avisa de que el cajón ha conseguido sesión, en código de producción.\n".
            "El módulo existe y su `node --test` está verde, pero probar los dos extremos de una\n".
            "costura no la cablea (`DECISIONES #117`). Sin llamante: el bloque de cuenta sigue\n".
            "saludando como invitado a quien acaba de entrar, el índice del área sale vacío y cerrar\n".
            'el cajón deja de recargar la página. Y nada falla.',
        );
    }

    /**
     * ⚠️ EL CASO. Alguien de PRODUCCIÓN tiene que consumir la intención, o la costura entera —cinco
     * eslabones, un paso de fase y una decisión de diseño— no sirve para nada y nadie se entera.
     */
    public function test_something_in_production_actually_consumes_the_queued_intent(): void
    {
        $consumers = [];

        foreach ($this->productionSources() as $path => $code) {
            if ($path === self::MACHINE) {
                continue;   // su propia definición no cuenta como uso
            }

            if (str_contains($code, 'takeIntent')) {
                $consumers[] = $path;
            }
        }

        $this->assertNotEmpty(
            $consumers,
            "NADIE consume la intención de entrada en código de producción.\n".
            "La landing la declara (`openWith`), Alpine la entrega (`applyIntent`) y la máquina la\n".
            "guarda (`queueIntent`) — pero si nadie llama a `takeIntent()` y actúa sobre ella, los tres\n".
            "enlaces profundos abren el cajón en el CATÁLOGO RAÍZ y no falla nada: «no falla, no hace\n".
            "nada», que es el modo de fallo que la costura de 4.0a existe para impedir.\n".
            '⚠️ `machine.test.js` NO cuenta: probar los dos extremos de una costura no la cablea '.
            '(`DECISIONES #117`).',
        );
    }
}
