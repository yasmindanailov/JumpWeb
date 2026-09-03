<?php

namespace Tests\Feature\Theme;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * **NINGÚN TOKEN QUE UNA HOJA USA PUEDE QUEDAR SIN DECLARAR** (`DECISIONES #414`).
 *
 * `var(--x)` **sin fallback** con `--x` no declarado no es un valor vacío: la declaración entera es
 * inválida en tiempo de valor computado y el navegador **la descarta**. La propiedad cae a su valor
 * inicial o al heredado, así que la página se pinta, nada falla y la pieza se ve mal — el modo de
 * fallo más caro de encontrar de este proyecto.
 *
 * ▶ **Nace de un defecto REAL, medido en navegador**: `.gf-extra`, el recuadro de los extras de
 * venta posterior (`#413` T3), escribía `border-radius: var(--r-card)` y **`--r-card` no existe** en
 * la escala (`--r-xs · --r-sm · --r-md · --r-btn · --r · --r-lg · --r-pill`). Medido: **0px** ahí
 * contra **16px** en `.gf-fiche`, su tarjeta hermana de la misma página. El owner lo vio antes que
 * ninguna guarda.
 *
 * ⚠️⚠️ **Por qué no lo veía `ShapeScaleTest`**: aquélla prohíbe escribir un canto **LITERAL** y
 * obliga a usar un token — y `var(--r-card)` *es* un token. La guarda que vigila que uses la escala
 * no puede además vigilar que el token exista: son dos preguntas distintas. Es el mismo hueco que
 * `#253` cerró para los modificadores de clase («ningún modificador que un componente emite puede
 * quedarse sin regla»), aplicado ahora al vocabulario de tokens.
 *
 * ⚠️ **Solo se juzga el `var()` SIN fallback.** `var(--action-brand, var(--fg))` es la forma
 * deliberada de `#209` —«vacío es una RESPUESTA, no una falta»— y hay 52 usos así: acusarlos sería
 * romper un mecanismo del producto. La diferencia entre las dos formas es justo lo que hace a esta
 * guarda posible.
 *
 * ⚠️ **Los comentarios se blanquean antes de mirar** (la trampa de `#193`, y volvió a morder aquí:
 * `--jump-1`/`--jump-2` solo aparecen dentro de comentarios que explican por qué ya no se usan —
 * dos falsos positivos con toda la pinta de defecto).
 *
 * Se considera DECLARADO lo que declare cualquier hoja de `public/css` y también lo que el servidor o
 * el navegador publiquen en caliente (`setProperty('--x', …)`, `style="--x: …"`, el tema por
 * instalación desde BD): un token puede existir sin estar escrito en el CSS.
 */
class UsedTokenIsDeclaredTest extends TestCase
{
    /** Las hojas cuyo USO se juzga. */
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css'];

    /**
     * **DEUDA ENUMERADA — esta lista SOLO ENCOGE** (la regla de `#196`), y hoy está **VACÍA**.
     *
     * Nació con los tres tokens rotos PREEXISTENTES que el barrido destapó, y los tres se arreglaron
     * en la misma tanda (`[DECIDIDO owner, 2026-09-03]`, `#414`):
     *
     * - `--muted` (8 usos) → `--fg-mute`. Era el peor: `color` inválido, así que **heredaba el color
     *   del padre** en vez del gris apagado. Medido en el post-form: `rgb(20,19,15)` donde tocaba
     *   `rgb(107,103,93)`. Afectaba también a mantenimiento, compra en mantenimiento y 404.
     * - `--fw-normal` (1) → `400` literal, que es lo que la hoja hace en otros ocho sitios: la escala
     *   no declara «normal». `.orders__guests-free` heredaba el semibold en vez de anularlo.
     * - `--r-5` (1) → `--r-sm`. Parecía inocuo —`.entry__qty` no tiene borde ni fondo— pero el
     *   `outline` de `:focus-visible` **sigue al `border-radius`**: el anillo de teclado salía con
     *   esquina viva. *Un radio sin borde no es un radio sin efecto.*
     *
     * Vacía NO significa que la lista sobre: significa que el corpus está limpio y que el siguiente
     * que aparezca hay que arreglarlo o justificarlo aquí, con su efecto medido.
     *
     * @var list<string>
     */
    private const KNOWN_BROKEN = [];

    /** @return array<string, list<string>> token → hojas que lo usan sin fallback */
    private function usedWithoutFallback(): array
    {
        $used = [];
        foreach (self::SHEETS as $sheet) {
            $css = $this->stripComments((string) file_get_contents(base_path($sheet)));
            preg_match_all('/var\(\s*(--[A-Za-z0-9_-]+)\s*\)/', $css, $m);
            foreach ($m[1] as $token) {
                $used[$token][] = $sheet;
            }
        }

        return $used;
    }

    /** @return list<string> los que se usan CON fallback (la forma deliberada) */
    private function usedWithFallback(): array
    {
        $out = [];
        foreach (self::SHEETS as $sheet) {
            $css = $this->stripComments((string) file_get_contents(base_path($sheet)));
            preg_match_all('/var\(\s*(--[A-Za-z0-9_-]+)\s*,/', $css, $m);
            $out = array_merge($out, $m[1]);
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> todo token declarado: en las hojas o publicado en caliente */
    private function declared(): array
    {
        $declared = [];

        foreach (glob(base_path('public/css/*.css')) ?: [] as $sheet) {
            preg_match_all('/(--[A-Za-z0-9_-]+)\s*:/', $this->stripComments((string) file_get_contents($sheet)), $m);
            $declared = array_merge($declared, $m[1]);
        }

        // Un token puede no estar en ninguna hoja y existir igual: lo publica el servidor (el tema
        // por instalación sale de BD) o el navegador (`setProperty`, los progresos del cierre…).
        foreach ($this->sourceFiles() as $file) {
            $src = (string) file_get_contents($file);
            foreach (['/setProperty\(\s*[\'"](--[A-Za-z0-9_-]+)/', '/(--[A-Za-z0-9_-]+)\s*:/', '/[\'"](--[A-Za-z0-9_-]+)[\'"]/'] as $pattern) {
                preg_match_all($pattern, $src, $m);
                $declared = array_merge($declared, $m[1]);
            }
        }

        return array_values(array_unique($declared));
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];
        foreach (['app', 'resources', 'public/js'] as $dir) {
            $base = base_path($dir);
            if (! is_dir($base)) {
                continue;
            }
            /** @var iterable<\SplFileInfo> $it */
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && in_array($f->getExtension(), ['php', 'js', 'vue', 'css'], true)) {
                    $files[] = $f->getPathname();
                }
            }
        }

        return $files;
    }

    /** Blanquea comentarios conservando la longitud (la trampa de `#193`). */
    private function stripComments(string $css): string
    {
        return (string) preg_replace_callback(
            '#/\*.*?\*/#s',
            fn (array $m): string => str_repeat(' ', strlen($m[0])),
            $css,
        );
    }

    public function test_the_scan_sees_the_corpus(): void
    {
        $used = $this->usedWithoutFallback();
        $declared = $this->declared();

        $this->assertGreaterThan(150, count($used), 'el localizador de `var()` se ha roto');
        $this->assertGreaterThan(150, count($declared), 'el localizador de declaraciones se ha roto');

        // Control en las dos direcciones: un token que SÍ existe no puede salir como roto, y la
        // forma con fallback tiene que quedar fuera del corpus juzgado.
        $this->assertContains('--r', $declared, 'el radio por defecto tiene que verse declarado');
        $this->assertContains('--action-brand', $this->usedWithFallback(), 'el uso deliberado con fallback tiene que verse');
        $this->assertNotContains('--action-brand', array_keys($used), '`--action-brand` solo se usa con fallback: si aparece aquí, el escáner confunde las dos formas');
    }

    public function test_every_used_token_without_fallback_is_declared(): void
    {
        $declared = $this->declared();
        $culpables = [];

        foreach ($this->usedWithoutFallback() as $token => $sheets) {
            if (in_array($token, $declared, true) || in_array($token, self::KNOWN_BROKEN, true)) {
                continue;
            }
            $culpables[] = sprintf('%s (%s)', $token, implode(', ', array_unique($sheets)));
        }

        $this->assertSame([], $culpables, implode("\n", [
            'Estos tokens se usan SIN fallback y no los declara nadie, así que el navegador DESCARTA',
            'la declaración entera y la pieza se ve mal sin que falle nada:',
            '  '.implode("\n  ", $culpables),
            '',
            'Usa un token de la escala, o declara el nuevo donde vive su familia.',
        ]));
    }

    /**
     * ⚠️ Con la lista VACÍA este caso se quedaba sin aserciones y PHPUnit lo marcaba «risky» — un
     * caso sin sujeto no vigila nada (la lección de `#295`). La primera aserción le da sujeto
     * siempre: mientras esté vacía afirma que lo está, y en cuanto alguien añada una excepción pasa
     * a exigirle que siga siendo un defecto real.
     */
    public function test_the_exception_list_only_shrinks(): void
    {
        $declared = $this->declared();
        $used = array_keys($this->usedWithoutFallback());

        $this->assertSame(
            [],
            array_values(array_diff(self::KNOWN_BROKEN, $used)),
            'toda excepción tiene que seguir teniendo sujeto: si el token ya no se usa, bórrala.',
        );

        foreach (self::KNOWN_BROKEN as $token) {
            $this->assertContains(
                $token,
                $used,
                "`{$token}` ya no se usa sin fallback: bórralo de KNOWN_BROKEN — una excepción sin sujeto no vigila nada.",
            );
            $this->assertNotContains(
                $token,
                $declared,
                "`{$token}` ya está declarado: bórralo de KNOWN_BROKEN, la lista solo encoge.",
            );
        }
    }
}
