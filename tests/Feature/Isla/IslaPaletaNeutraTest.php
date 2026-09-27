<?php

namespace Tests\Feature\Isla;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * **La isla es del producto, y el producto no sabe de qué color es PlayJump** (`isla-y-landing-nueva.md` §0 y §4.9).
 *
 * `isla.css` da a cada rol un respaldo NEUTRO que la instalación sustituye (PlayJump: su `publico/instancia/css/isla.css`
 * y el bloque `[data-surface="ink"]` de `saltia.css`). Hasta el 2026-09-27 catorce respaldos eran la paleta de
 * PlayJump —el cian `--aqua-400` en siete, el naranja `--flare-*` en cuatro y tres de los avisos, siete de ellos
 * escritos como `rgba(r, g, b, …)`—: con la hoja de PlayJump no se veía (la tapa entera); en cualquier otra instalación,
 * sí. Lo avisó el SPA (`PaletaNeutraTest` de la fiesta), que solo mira el hex y solo la fiesta.
 *
 * Tres preguntas: ningún NOMBRE de primitivo de PlayJump en la isla; ningún VALOR, ni en hex (también con alfa,
 * `#74ddfa99`) ni como tripleta `r, g, b`; y todo `var(--x)` SIN respaldo que la isla usa, declarado en la isla. La
 * tercera es la que hacía daño de verdad: cinco piezas de la calculadora pintaban con `var(--ink-900)`, `var(--snow)`…
 * sin respaldo, así que fuera de PlayJump el calendario y compartir se veían ROTOS, no de otro color. Los valores se
 * leen de la hoja de la instancia si esta máquina la tiene (el producto no los versiona); sin ella, ese caso lo dice
 * y se juzgan los nombres y los respaldos.
 *
 * ⚠️ Los comentarios se blanquean antes de mirar (`#193`): la cabecera de `isla.css` nombra a PlayJump para explicar
 * que aquí no está. Los `.test.js` no entran: prueban la forma, no pintan.
 */
class IslaPaletaNeutraTest extends TestCase
{
    /**
     * Los primitivos de PlayJump, por su forma: las siete familias de `PaletaNeutraTest` y las tres de estado que su
     * hoja también define con valor (`--danger-*`, `--success-*`, `--warn-*`; medido en `saltia.css` el 2026-09-27).
     */
    private const PRIMITIVOS = '/--(?:ink-\d{3}|snow|aqua-\d{3}|sun-\d{3}|volt-\d{3}|berry-\d{3}|flare-\d{3}|danger-\d{3}|success-\d{3}|warn-\d{3})\b/';

    /** @return array<string, string> ruta relativa → contenido con los comentarios blanqueados */
    private function corpus(): array
    {
        $corpus = [];
        /** @var iterable<\SplFileInfo> $it */
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('resources/js/isla'), RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $ruta = $f->getPathname();
            if (! $f->isFile() || str_ends_with($ruta, '.test.js') || ! preg_match('/\.(css|js|vue)$/', $ruta)) {
                continue;
            }
            $corpus[str_replace(base_path().'/', '', $ruta)] = $this->sinComentarios((string) file_get_contents($ruta));
        }
        ksort($corpus);

        return $corpus;
    }

    /** Blanquea `/* *\/`, `<!-- -->` y los `//` de línea conservando la longitud. */
    private function sinComentarios(string $src): string
    {
        return (string) preg_replace_callback(
            '#/\*.*?\*/|<!--.*?-->|(?<=^|\s)//[^\n]*#s',
            fn (array $m): string => str_repeat(' ', strlen($m[0])),
            $src,
        );
    }

    /** @return list<string> los hex (6 cifras, minúsculas) de los primitivos, de la hoja de la instancia; vacío sin ella */
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
                '/'.trim(self::PRIMITIVOS, '/').'\s*:\s*(#[0-9a-fA-F]{6})\b/',
                $this->sinComentarios((string) file_get_contents($hoja)),
                $m,
            );

            // El blanco y el negro no son de nadie: `--snow` es `#ffffff` y una guarda de paleta no puede prohibirlos.
            return array_values(array_diff(array_unique(array_map('strtolower', $m[1])), ['#ffffff', '#000000']));
        }

        return [];
    }

    public function test_the_scan_sees_the_corpus(): void
    {
        $corpus = $this->corpus();

        $this->assertGreaterThan(150, count($corpus), 'el corpus de la isla se ha quedado corto: ¿se movieron las piezas?');
        $this->assertArrayHasKey('resources/js/isla/isla.css', $corpus);
        $this->assertArrayHasKey('resources/js/isla/IslaFlotante.vue', $corpus);
        $this->assertArrayNotHasKey('resources/js/isla/forma.test.js', $corpus);
        // Control del blanqueado: la cabecera de `isla.css` nombra a Play Jump en un comentario y no puede acusar.
        $this->assertStringContainsString('Play Jump', (string) file_get_contents(base_path('resources/js/isla/isla.css')));
        $this->assertStringNotContainsString('Play Jump', $corpus['resources/js/isla/isla.css']);
    }

    public function test_no_playjump_primitive_enters_the_island(): void
    {
        $culpables = [];
        foreach ($this->corpus() as $ruta => $src) {
            if (preg_match_all(self::PRIMITIVOS, $src, $m) > 0) {
                $culpables[] = $ruta.': '.implode(', ', array_unique($m[0]));
            }
        }

        $this->assertSame([], $culpables, "Un primitivo de PlayJump en la isla es un color que ninguna instalación puede cambiar. Usa su rol `--isla-*` (isla.css):\n  ".implode("\n  ", $culpables));
    }

    public function test_no_playjump_value_enters_the_island_neither_hex_nor_rgb(): void
    {
        $hex = $this->hexDePlayJump();
        if ($hex === []) {
            $this->assertSame([], $hex, 'sin la hoja de la instancia en esta máquina solo se juzgan los nombres');

            return;
        }
        $this->assertGreaterThan(20, count($hex), 'la hoja de la instancia tiene que dar sus primitivos con valor');
        // Control del lector de tripletas: el cian de PlayJump (`--aqua-400`) está en la hoja y se lee como 116, 221, 250.
        $this->assertContains('#74ddfa', $hex, 'la hoja de la instancia ya no trae el cian que motivó esta guarda: revisa el lector');

        $culpables = [];
        foreach ($this->corpus() as $ruta => $src) {
            $vistos = [];
            preg_match_all('/#([0-9a-fA-F]{6})(?:[0-9a-fA-F]{2})?\b/', $src, $m);
            foreach ($m[1] as $h) {
                $vistos[] = '#'.strtolower($h);
            }
            preg_match_all('/\b(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\b/', $src, $m, PREG_SET_ORDER);
            foreach ($m as [, $r, $g, $b]) {
                if (max((int) $r, (int) $g, (int) $b) <= 255) {
                    $vistos[] = sprintf('#%02x%02x%02x', (int) $r, (int) $g, (int) $b);
                }
            }
            $deplayjump = array_values(array_intersect(array_unique($vistos), $hex));
            if ($deplayjump !== []) {
                $culpables[] = $ruta.': '.implode(', ', $deplayjump);
            }
        }

        $this->assertSame([], $culpables, "Un valor de la paleta de PlayJump escrito a mano en la isla (en hex o como `r, g, b`); su sitio es la hoja de la instancia:\n  ".implode("\n  ", $culpables));
    }

    public function test_every_token_the_island_uses_without_fallback_is_declared_in_the_island(): void
    {
        $corpus = $this->corpus();

        $declarados = [];
        foreach ($corpus as $src) {
            // En CSS (`--x:`), como clave de un estilo de JS o Vue (`'--a': …`) y con `setProperty('--x', …)`.
            foreach (['/[\'"]?(--[A-Za-z0-9_-]+)[\'"]?\s*:/', '/setProperty\(\s*[\'"](--[A-Za-z0-9_-]+)/'] as $patron) {
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

        $this->assertSame([], $huerfanos, "Estos tokens la isla los usa SIN respaldo y ella no los declara: fuera de la instalación que los defina, la propiedad es inválida y la pieza se ve rota sin que falle nada. Dale su respaldo en `isla.css` (o usa el rol que ya lo tiene):\n  ".implode("\n  ", $huerfanos));
    }
}
