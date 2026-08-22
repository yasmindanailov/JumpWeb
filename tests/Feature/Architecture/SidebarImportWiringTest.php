<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **Lo que un fichero del cajón USA de un módulo hermano, lo IMPORTA** (2026-08-22).
 *
 * ⚠️ Nace de un fallo propio cometido durante la reorganización del SPA: al mover secuencias a los
 * stores se retiró `toApiItems` del import de `Sidebar.vue` **mientras seguía usándose** en
 * `confirmReservation()`. **`npm run build` pasó en verde**: un identificador no declarado no es un
 * error de compilación en JavaScript, es un `ReferenceError` en TIEMPO DE EJECUCIÓN — y solo en el
 * momento exacto de crear la reserva, que es el paso más caro del cajón.
 *
 * Ni el build, ni el diff de árbol, ni el recorrido en navegador (que no llega a pagar) lo habrían
 * visto. Es la misma familia que el resto de esta fase —una pieza declarada en un sitio y usada en
 * otro, con el medio sin comprobar— y se cierra igual: con una guarda barata que mira el cableado.
 *
 * ⚠️ **Ámbito deliberadamente ESTRECHO**: solo se comprueban los nombres que exportan los módulos
 * planos del propio cajón. No es un linter y no pretende serlo; es el centinela de la clase de error
 * que esta reorganización hace probable.
 */
class SidebarImportWiringTest extends TestCase
{
    private const ROOT = 'resources/js/sidebar';

    /** @return array<int, string> */
    private function files(string ...$extensions): array
    {
        $found = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path(self::ROOT), \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            $name = $file->getFilename();

            if (str_contains($name, '.test.')) {
                continue;
            }

            foreach ($extensions as $extension) {
                if (str_ends_with($name, $extension)) {
                    $found[] = $file->getPathname();
                }
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Qué exporta cada módulo plano del cajón: nombre → fichero.
     *
     * @return array<string, string>
     */
    private function exportedNames(): array
    {
        $names = [];

        foreach ($this->files('.js') as $path) {
            $source = (string) file_get_contents($path);

            preg_match_all('/^export\s+(?:async\s+)?(?:function|const|class)\s+(\w+)/m', $source, $matches);

            foreach ($matches[1] as $name) {
                $names[$name] = str_replace(base_path().'/', '', $path);
            }
        }

        return $names;
    }

    public function test_the_scan_sees_the_flat_modules(): void
    {
        $this->assertGreaterThan(
            40, count($this->exportedNames()),
            'El escaneo ve muy pocos exports: ¿ha cambiado el cajón de sitio? Un barrido vacío pasa en '.
            'verde sin comprobar nada, que es el peor resultado posible.',
        );
    }

    public function test_every_sibling_helper_used_is_actually_imported(): void
    {
        $exported = $this->exportedNames();
        $orphans = [];

        foreach ($this->files('.js', '.vue') as $path) {
            $relative = str_replace(base_path().'/', '', $path);
            $source = (string) file_get_contents($path);

            // Lo que ESTE fichero importa (incluye alias: `x as y` → cuenta el alias, que es el usado).
            preg_match_all('/import\s*\{([^}]*)\}\s*from/s', $source, $blocks);
            $imported = [];

            foreach ($blocks[1] as $block) {
                foreach (explode(',', $block) as $piece) {
                    $parts = preg_split('/\s+as\s+/', trim($piece));
                    $imported[] = trim(end($parts));
                }
            }

            // ⚠️ Se mira el CÓDIGO, no los comentarios: este repo documenta muchísimo y media docena
            // de docblocks citan `buildFooter()` o `save()` sin llamarlos. Sin esto, la guarda es un
            // generador de falsos positivos — medido: once de once en el primer intento.
            $code = preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $source) ?? '';

            // Y lo que declara por su cuenta. ⚠️ Incluye los MÉTODOS de objeto (`clear() {`), que es
            // como se escriben las acciones de un store: sin ellos, su propia definición se leería
            // como una llamada sin importar.
            preg_match_all('/(?:^|\s)(?:function|const|let|var|class)\s+(\w+)/m', $code, $own);
            preg_match_all('/^\s*(?:async\s+)?(\w+)\s*\([^)]*\)\s*\{/m', $code, $methods);
            $declared = array_merge($own[1], $methods[1]);

            foreach ($exported as $name => $home) {
                if ($home === $relative || in_array($name, $imported, true) || in_array($name, $declared, true)) {
                    continue;
                }

                // Se busca como LLAMADA (`nombre(`), que es donde el `ReferenceError` ocurre.
                if (preg_match('/(?<![\w.$])'.preg_quote($name, '/').'\s*\(/', $code)) {
                    $orphans[] = "{$relative} llama a «{$name}()» y no lo importa (vive en {$home})";
                }
            }
        }

        $this->assertSame(
            [], $orphans,
            "Hay ficheros del cajón que USAN un ayudante de un módulo hermano SIN importarlo:\n  ".
            implode("\n  ", $orphans)."\n\n".
            '⚠️ Esto NO lo caza `npm run build`: en JavaScript un identificador no declarado no es un '.
            'error de compilación, es un `ReferenceError` en tiempo de EJECUCIÓN — y solo cuando ese '.
            'camino se recorre. Pasó de verdad el 2026-08-22 con `toApiItems` en `confirmReservation()`.',
        );
    }
}
