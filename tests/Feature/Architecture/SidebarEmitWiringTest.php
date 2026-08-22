<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **Un evento DECLARADO tiene que emitirse desde algún sitio** (2026-08-22).
 *
 * ⚠️⚠️ Nace de un fallo REAL, y es la tercera vez que el mismo modo de fallo muerde en este cajón.
 * `DateStep.vue` declaraba `defineEmits(['select', 'prev-month', 'next-month'])`, `Sidebar.vue` ataba
 * manejadores a los tres… y **los dos botones de navegación de mes no llevaban `@click`**. O sea que
 * **la navegación de mes del cajón SPA no funcionó NUNCA**, desde que se transcribió el calendario
 * (4.2·2, 2026-08-14). El Blade retirado sí la tenía (`wire:click="prevMonth"`).
 *
 * ⚠️ **Y por qué ninguna prueba lo vio, que es lo que hay que llevarse**: el diff de árbol compara el
 * DOM RENDERIZADO, y un manejador de eventos **no es un atributo del DOM** — ni `wire:click` ni
 * `@click` sobreviven a la normalización, que los descarta como andamiaje de cada motor. Los dos
 * botones salían idénticos con y sin cableado. Es la misma familia que el interior de un `<svg>`
 * (`#113`), que `takeIntent` sin consumidor (`#117`) y que la señal que no llegaba (`#118`): **una
 * pieza declarada, un consumidor atado, y el medio sin conectar**.
 *
 * Este caso es la versión general y barata: si un componente ANUNCIA un evento, alguien dentro de él
 * tiene que emitirlo. No prueba que el manejador haga lo correcto —eso es del navegador— pero cierra
 * el hueco de «declarado y muerto», que es el que se cuela en verde.
 */
class SidebarEmitWiringTest extends TestCase
{
    private const ROOT = 'resources/js/sidebar';

    /** @return array<int, string> */
    private function components(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path(self::ROOT), \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if (str_ends_with($file->getFilename(), '.vue')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function test_the_scan_actually_sees_the_components(): void
    {
        $this->assertGreaterThan(
            10, count($this->components()),
            'El escaneo ve muy pocos componentes: ¿ha cambiado el cajón de sitio? Un barrido que no '.
            'encuentra nada pasa en verde sin comprobar nada.',
        );
    }

    public function test_every_declared_event_is_emitted_somewhere_in_its_component(): void
    {
        $orphans = [];

        foreach ($this->components() as $path) {
            $source = (string) file_get_contents($path);
            $relative = str_replace(base_path().'/', '', $path);

            if (! preg_match('/defineEmits\(\s*\[([^\]]*)\]/s', $source, $block)) {
                continue;
            }

            preg_match_all("/'([^']+)'/", $block[1], $events);

            foreach ($events[1] as $event) {
                $quoted = preg_quote($event, '/');

                if (! preg_match("/\\\$?emit\(\s*'{$quoted}'/", $source)) {
                    $orphans[] = "{$relative} declara «{$event}» y no lo emite nunca";
                }
            }
        }

        $this->assertSame(
            [], $orphans,
            "Hay eventos declarados que NO se emiten desde ningún sitio:\n  ".implode("\n  ", $orphans)."\n\n".
            '⚠️ El componente de arriba ANUNCIA algo que nunca ocurre, y quien lo usa ata un manejador '.
            'que no se dispara jamás. **No falla: no hace nada**, y el diff de árbol no puede verlo '.
            "porque un `@click` no es un atributo del DOM.\n".
            'Es lo que dejó la navegación de mes muerta desde 4.2·2 hasta el 2026-08-22.',
        );
    }
}
