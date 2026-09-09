<?php

namespace Tests\Feature\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.0a — **la landing no sabe qué motor mueve el cajón**
 * (`docs/specs/sidebar-spa.md` §4.1).
 *
 * El sidebar no es un nodo: es un nodo MÁS una costura que cruza la landing. Tres vistas
 * —la sección de servicios, la de eventos y las tarjetas de atracción de la home— no abren el
 * cajón «vacío», lo abren PIDIENDO algo: la sección de packs, o las entradas de una zona
 * concreta. Hasta el paso 4.0a lo hacían despachando un evento de Livewire desde el propio
 * `@click`, y ahí estaba el problema: **con otro motor esos `dispatch` no fallan, no hacen
 * nada**. El cliente aterriza en el catálogo raíz, la intención del deep-link se pierde y no
 * hay error en ninguna parte — la peor clase de regresión.
 *
 * Ahora la intención se declara (`$store.purchase.openWith({…})`) y cada motor registra su
 * adaptador. Este test impide que alguien vuelva a atar la landing al motor, que es lo que
 * pasaría de forma natural al añadir el cuarto punto de entrada.
 *
 * ⚠️ Es una guarda de la COSTURA, no del motor: cuando el cajón sea Vue, este test no debería
 * cambiar ni una línea. Si hay que tocarlo, es señal de que la costura se ha roto.
 */
class SidebarSeamTest extends TestCase
{
    /**
     * Eventos del componente de compra. Despacharlos desde fuera de su propia vista es atar la
     * landing a Livewire.
     *
     * @var list<string>
     */
    private const ENGINE_EVENTS = ['show-packs', 'show-entradas-zone'];

    /** La vista del propio componente: ahí el evento es del motor y está en su sitio. */
    private const ENGINE_VIEW = 'livewire/tickets/purchase.blade.php';

    /** El escaneo nunca puede pasar en vacío. */
    public function test_the_scan_actually_sees_the_landing_views(): void
    {
        $views = $this->bladeFiles();

        $this->assertNotEmpty($views);
        $this->assertContains('home.blade.php', $views, 'no se ve la home: ¿han cambiado de sitio las vistas?');
    }

    /** La guarda: ninguna vista de la landing despacha eventos del motor. */
    public function test_no_landing_view_dispatches_engine_events(): void
    {
        $violations = [];

        foreach ($this->bladeFiles() as $relative) {
            if ($relative === self::ENGINE_VIEW) {
                continue;
            }

            $source = (string) file_get_contents(resource_path('views/'.$relative));

            foreach (self::ENGINE_EVENTS as $event) {
                if (str_contains($source, "dispatch('{$event}'") || str_contains($source, "dispatch(\"{$event}\"")) {
                    $violations[] = "  {$relative}  →  {$event}";
                }
            }
        }

        $this->assertSame(
            [], $violations,
            "Vistas de la landing atadas al motor del cajón:\n".implode("\n", $violations)."\n\n".
            "Usa la intención declarativa, que cualquier motor puede aplicar:\n".
            "  \$store.purchase.openWith({ type: 'packs' })\n".
            "  \$store.purchase.openWith({ type: 'zone', slug: '…' })"
        );
    }

    /**
     * Y la fachada existe de verdad. Sin esto, la guarda de arriba se podría satisfacer borrando
     * la funcionalidad en vez de migrándola.
     */
    public function test_the_store_publishes_the_intent_facade(): void
    {
        $store = (string) file_get_contents(resource_path('js/app.js'));

        foreach (['openWith(', 'useIntentAdapter(', 'flushIntent('] as $member) {
            $this->assertStringContainsString(
                $member, $store,
                "El store de compra ya no publica «{$member}»: la landing se queda sin forma de pedir nada"
            );
        }

        $this->assertStringContainsString(
            "openWith({ type: 'packs' })",
            (string) file_get_contents(resource_path('views/pages/services.blade.php')),
            'la sección de servicios ha dejado de declarar su intención'
        );

        /*
         * ⚠️⚠️ **AQUÍ SE ASEVERABA `openWith({ type: 'zone'` EN LA PORTADA, Y ESE SUJETO YA NO
         * EXISTE** (`#482`): la única superficie que lo declaraba era el CTA de la tarjeta de
         * atracción, y el carrusel se retiró con la sección 03. Medido tras la retirada: `type:
         * 'zone'` tiene **cero** consumidores en todo `resources/views`.
         *
         * ▶ **No se borra la aserción: se convierte en CENSO**, que es más fuerte que lo que
         * sustituye. Antes vigilaba UNA superficie; ahora vigila **todas** las que declaran
         * intención, así que retirar cualquiera —o añadir una que se salte la fachada— pone rojo
         * este caso y obliga a decidirlo.
         *
         * ❗ **La pérdida está fichada en `DEUDA.md`**: el sitio ya no sabe abrir el cajón
         * posicionado en una zona, y el botón de la tarjeta de tarifa dice «Comprar 1 hora en Jump»
         * y llama a `open()` **sin intención**.
         */
        $declaran = [];

        foreach ($this->bladeFiles() as $rel) {
            if (str_contains((string) file_get_contents(resource_path('views/'.$rel)), 'openWith(')) {
                $declaran[] = $rel;
            }
        }

        sort($declaran);

        $this->assertSame(
            ['components/site/events-section.blade.php', 'pages/services.blade.php'],
            $declaran,
            "la lista de superficies que declaran su intención de compra ha cambiado.\n".
            "Si es una nueva, añádela aquí; si una la ha perdido, ese camino de compra se ha cerrado\n".
            'y hay que decirlo — que es exactamente lo que pasó con `type: \'zone\'` en `#482`.'
        );
    }

    /** @return list<string> rutas relativas a `resources/views` */
    private function bladeFiles(): array
    {
        $base = resource_path('views');
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = mb_substr($file->getPathname(), mb_strlen($base) + 1);
            }
        }

        sort($files);

        return $files;
    }
}
