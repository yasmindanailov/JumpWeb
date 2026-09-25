<?php

namespace Tests\Feature\Fiesta;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * **LA FIESTA DEL SISTEMA NUEVO ES DEL PRODUCTO, Y EL PRODUCTO NO SABE DE QUÉ COLOR ES PLAYJUMP**
 * (`specs/fiesta-sistema-nuevo.md` §4.3 y §6, `#743`).
 *
 * Las tres páginas se portan 1:1 del diseño de la instancia, y el diseño escribe sus primitivos (`--ink-900`,
 * `--snow`, `--aqua-400`…). En el producto esos primitivos son ROLES `--fiesta-*` con un respaldo neutro; el valor de
 * PlayJump lo pone `publico/instancia/css/fiesta.css` DE LA INSTANCIA, cargada después. Un primitivo que se cuele en
 * una pieza es un color que ninguna otra instalación puede cambiar, y no falla: se ve.
 *
 * Tres preguntas, tres casos:
 *  1. ningún NOMBRE de primitivo de PlayJump en los ficheros de la fiesta del producto;
 *  2. ningún VALOR (hex) de esos primitivos, leído de la hoja de la instancia si esta máquina la tiene (el producto
 *     no versiona esos valores; sin la hoja, el caso afirma solo los nombres, que siempre tienen sujeto);
 *  3. todo `var(--x)` SIN respaldo que la fiesta usa está declarado en su hoja, en `isla.css` (que importa) o en
 *     línea: la trampa de `UsedTokenIsDeclaredTest` (`#414`), que ahí solo mira `site.css` y `landing.css`.
 *
 * ⚠️ Los comentarios se blanquean antes de mirar (`#193`): la cabecera de `fiesta.css` NOMBRA los primitivos para
 * explicar que aquí no están. ⚠️ `--ink-surface`, `--ink-bg`… son tokens de Saltia y del producto, no primitivos:
 * el primitivo de PlayJump lleva tres cifras (`--ink-900`) o es `--snow` a secas.
 */
class PaletaNeutraTest extends TestCase
{
    /** Los primitivos de PlayJump: las siete familias del diseño, por su forma. */
    private const PRIMITIVOS = '/--(?:ink-\d{3}|snow|aqua-\d{3}|sun-\d{3}|volt-\d{3}|berry-\d{3}|flare-\d{3})\b/';

    /** Las hojas del producto que RESPALDAN a la fiesta: la suya y la de la isla, que importa. */
    private const RESPALDO = ['resources/js/fiesta/fiesta.css', 'resources/js/isla/isla.css'];

    /** @return array<string, string> ruta relativa → contenido con los comentarios blanqueados */
    private function corpus(): array
    {
        $ficheros = [
            base_path('resources/views/components/pagina-enfocada.blade.php'),
            ...glob(base_path('resources/js/fiesta/*.css')) ?: [],
            ...glob(base_path('resources/js/fiesta/*.js')) ?: [],
            ...glob(base_path('lang/*/fiesta.php')) ?: [],
        ];
        foreach (['resources/views/fiesta', 'resources/views/components/fiesta', 'resources/views/components/pieza', 'app/Http/Fiesta'] as $dir) {
            /** @var iterable<\SplFileInfo> $it */
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $ficheros[] = $f->getPathname();
                }
            }
        }

        $corpus = [];
        foreach ($ficheros as $ruta) {
            if (str_ends_with($ruta, '.test.js')) {
                continue;
            }
            $corpus[str_replace(base_path().'/', '', $ruta)] = $this->sinComentarios((string) file_get_contents($ruta));
        }
        ksort($corpus);

        return $corpus;
    }

    /** Blanquea `/* *\/`, `{{-- --}}` y los `//` de línea conservando la longitud. */
    private function sinComentarios(string $src): string
    {
        return (string) preg_replace_callback(
            '#/\*.*?\*/|\{\{--.*?--\}\}|(?<=^|\s)//[^\n]*#s',
            fn (array $m): string => str_repeat(' ', strlen($m[0])),
            $src,
        );
    }

    /** @return list<string> los valores hex de los primitivos, de la hoja de la instancia; vacío sin ella */
    private function hexDePlayJump(): array
    {
        $candidatas = [public_path('instancia/css/saltia.css')];
        if (is_string(config('instancia.ruta')) && config('instancia.ruta') !== '') {
            $candidatas[] = rtrim(config('instancia.ruta'), '/').'/publico/instancia/css/saltia.css';
        }
        foreach ($candidatas as $hoja) {
            if (! is_file($hoja)) {
                continue;
            }
            preg_match_all(
                '/'.trim(self::PRIMITIVOS, '/').'\s*:\s*(#[0-9a-fA-F]{3,8})\b/',
                $this->sinComentarios((string) file_get_contents($hoja)),
                $m,
            );

            // El blanco y el negro no son de nadie: `--snow` es `#ffffff` y una guarda de paleta no puede prohibirlos.
            return array_values(array_diff(array_unique(array_map('strtolower', $m[1])), ['#ffffff', '#fff', '#000000', '#000']));
        }

        return [];
    }

    public function test_the_scan_sees_the_corpus(): void
    {
        $corpus = $this->corpus();

        $this->assertGreaterThan(30, count($corpus), 'el corpus de la fiesta se ha quedado corto: ¿se movieron las piezas?');
        $this->assertArrayHasKey('resources/js/fiesta/fiesta.css', $corpus);
        $this->assertArrayHasKey('resources/views/fiesta/lista.blade.php', $corpus);
        $this->assertArrayHasKey('app/Http/Fiesta/ListaDeInvitados.php', $corpus);
        // Control del blanqueado: la cabecera de `fiesta.css` nombra `--ink-900` en un comentario y no puede acusar.
        $this->assertStringContainsString('--ink-900', (string) file_get_contents(base_path('resources/js/fiesta/fiesta.css')));
        $this->assertStringNotContainsString('--ink-900', $corpus['resources/js/fiesta/fiesta.css']);
        $this->assertStringContainsString('@import "../isla/isla.css"', $corpus['resources/js/fiesta/fiesta.css'], 'la fiesta respalda sobre la isla: si deja de importarla, RESPALDO miente');
    }

    public function test_no_playjump_primitive_enters_the_product(): void
    {
        $culpables = [];
        foreach ($this->corpus() as $ruta => $src) {
            if (preg_match_all(self::PRIMITIVOS, $src, $m) > 0) {
                $culpables[] = $ruta.': '.implode(', ', array_unique($m[0]));
            }
        }

        $this->assertSame([], $culpables, "Un primitivo de PlayJump en el producto es un color que ninguna instalación puede cambiar. Usa su rol `--fiesta-*` (fiesta.css):\n  ".implode("\n  ", $culpables));
    }

    public function test_no_playjump_hex_enters_the_product(): void
    {
        $hex = $this->hexDePlayJump();
        if ($hex === []) {
            $this->assertSame([], $hex, 'sin la hoja de la instancia en esta máquina solo se juzgan los nombres');

            return;
        }
        $this->assertGreaterThan(20, count($hex), 'la hoja de la instancia tiene que dar sus primitivos con valor');

        $culpables = [];
        foreach ($this->corpus() as $ruta => $src) {
            preg_match_all('/#[0-9a-fA-F]{6}\b/', $src, $m);
            $vistos = array_intersect(array_unique(array_map('strtolower', $m[0])), $hex);
            if ($vistos !== []) {
                $culpables[] = $ruta.': '.implode(', ', $vistos);
            }
        }

        $this->assertSame([], $culpables, "Un valor de la paleta de PlayJump escrito a mano en el producto:\n  ".implode("\n  ", $culpables));
    }

    public function test_every_token_the_party_uses_without_fallback_is_backed(): void
    {
        $corpus = $this->corpus();

        $declarados = [];
        foreach (self::RESPALDO as $hoja) {
            preg_match_all('/(--[A-Za-z0-9_-]+)\s*:/', $this->sinComentarios((string) file_get_contents(base_path($hoja))), $m);
            $declarados = array_merge($declarados, $m[1]);
        }
        foreach ($corpus as $src) {
            foreach (['/(--[A-Za-z0-9_-]+)\s*:/', '/setProperty\(\s*[\'"](--[A-Za-z0-9_-]+)/'] as $patron) {
                preg_match_all($patron, $src, $m);
                $declarados = array_merge($declarados, $m[1]);
            }
        }
        $declarados = array_unique($declarados);

        $usados = [];
        foreach ($corpus as $ruta => $src) {
            preg_match_all('/var\(\s*(--[A-Za-z0-9_-]+)\s*\)/', $src, $m);
            foreach (array_unique($m[1]) as $token) {
                $usados[$token][] = $ruta;
            }
        }
        $this->assertGreaterThan(100, count($usados), 'el localizador de `var()` se ha roto');

        $huerfanos = [];
        foreach ($usados as $token => $rutas) {
            if (! in_array($token, $declarados, true)) {
                $huerfanos[] = $token.' ('.implode(', ', $rutas).')';
            }
        }

        $this->assertSame([], $huerfanos, "Estos tokens la fiesta los usa SIN respaldo y nadie los declara: el navegador descarta la declaración entera y la pieza se ve mal sin que falle nada:\n  ".implode("\n  ", $huerfanos));
    }
}
