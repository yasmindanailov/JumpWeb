<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **LA DECORACIÓN VA EN LA PANTALLA, NO EN EL COMPONENTE QUE SE REPITE** (`#286`).
 *
 * ❗ **La regla no es de gusto: la dejaron TRES rechazos del owner en una tarde** — cuatro tiras de
 * marca donde su landing usa dos, una mancha por tarjeta de precio, y un friso de cinco figuras. Lo
 * que sobrevivió comparte tres cosas: **una por pantalla · integrada en su superficie · a baja
 * opacidad**. Y su propio artboard lo escribe dos veces sin que nadie se lo pidiera: los rayos «uno
 * por página», la mancha del precio «máximo una por pantalla».
 *
 * ▶ **Y hacía falta, porque la regla ya estaba incumplida cuando se escribió**: `/normas` pintaba
 * `.grain--fade` **dentro del `@foreach`** de las normas, o sea **cinco copias** en una pantalla en
 * esta instalación — y más en un parque con más normas. Nadie lo vio: no había ninguna guarda que
 * mirase esta página, ni de PHP ni de navegador.
 *
 * ⚠️⚠️ **Lo que se vigila es el BUCLE, no el número de nodos.** Contar elementos en la página
 * servida no distingue «dos colocaciones distintas» (el menú y la página, que es lo normal) de
 * «la misma pieza repetida por el catálogo», y además el número dependería de cuántas filas tenga
 * la BD de la máquina que corre la suite. El defecto vive en el MARCADO: una pieza decorativa
 * escrita dentro de un `@foreach` se multiplica por los datos.
 *
 * ⚠️ **`<x-site.ilu>` con clave VARIABLE queda fuera a propósito, y es la distinción que importa**:
 * la tarjeta de zona pinta **un dibujo distinto por zona** (`zone-{{ $zone->slug }}`), que es un
 * MARCADOR y no una textura repetida — `[DECIDIDO owner]` al estrenar el hueco. Lo que sí se caza
 * es el mismo dibujo con clave LITERAL dentro de un bucle: eso es la mancha por tarjeta otra vez.
 */
class FacadeDecorationIsPerScreenTest extends TestCase
{
    /**
     * Las clases de TEXTURA del material de fachada: piezas que no significan nada por sí mismas y
     * cuyo sitio es la pantalla. **La lista solo crece con el material que entra.**
     *
     * @var list<string>
     */
    private const TEXTURES = ['grain', 'spray', 'rays', 'brand-dots'];

    /** Aperturas y cierres de bucle de Blade. */
    private const LOOP_OPEN = ['@foreach', '@forelse', '@for', '@while'];

    private const LOOP_CLOSE = ['@endforeach', '@endforelse', '@endfor', '@endwhile'];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El localizador de bucles encuentra el interior de un `@foreach`, y solo el interior.**
     *
     * Sin este caso, un scanner que devolviera cadena vacía dejaría el test de abajo en verde
     * vigilando la nada — que es el fallo que este repo ha cometido ya cuatro veces con guardas
     * nuevas.
     */
    public function test_the_loop_scanner_sees_inside_a_loop_and_only_inside(): void
    {
        $blade = <<<'BLADE'
            <div class="fuera-a"></div>
            @foreach ($x as $y)
                <div class="dentro-a"></div>
                @if ($y)<div class="dentro-b"></div>@endif
            @endforeach
            <div class="fuera-b"></div>
            BLADE;

        $inside = $this->insideLoops($blade);

        $this->assertStringContainsString('dentro-a', $inside, 'el localizador no ve el cuerpo del bucle');
        $this->assertStringContainsString('dentro-b', $inside, 'el localizador no desciende en un `@if` anidado');
        $this->assertStringNotContainsString('fuera-a', $inside, 'el localizador se lleva marcado de ANTES del bucle');
        $this->assertStringNotContainsString('fuera-b', $inside, 'el localizador se lleva marcado de DESPUÉS del bucle');
    }

    /** **Y cuenta los bucles anidados**: un `@endforeach` interior no puede cerrar el exterior. */
    public function test_the_loop_scanner_counts_nesting(): void
    {
        $blade = <<<'BLADE'
            @foreach ($a as $b)
                @foreach ($c as $d)<i class="hondo"></i>@endforeach
                <i class="sigue-dentro"></i>
            @endforeach
            <i class="ya-fuera"></i>
            BLADE;

        $inside = $this->insideLoops($blade);

        $this->assertStringContainsString('hondo', $inside);
        $this->assertStringContainsString(
            'sigue-dentro', $inside,
            'el `@endforeach` del bucle interior ha cerrado el exterior: todo lo que va detrás '.
            'queda sin vigilar.',
        );
        $this->assertStringNotContainsString('ya-fuera', $inside);
    }

    /** **El corpus de vistas es real**: si se leyeran cero ficheros, el test de abajo sería un adorno. */
    public function test_the_view_corpus_is_populated(): void
    {
        $views = $this->bladeFiles();

        $this->assertGreaterThan(60, count($views), 'se están leyendo muy pocas vistas: el barrido no llega');
        // ⚠️ El defecto apareció en `pages/rules.blade.php`, que desde `#655` vive en la INSTANCIA: el
        // barrido ya no la ve (una instancia no es una app PHP; su juez es la huella). Lo que sigue aquí
        // es la portada, la vista con más bucles del producto, hasta que también se mude.
        $this->assertContains(
            'home.blade.php', $views,
            'el barrido no ve la portada, que es la vista con más bucles del producto',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **Ninguna textura de fachada se emite dentro de un bucle.** */
    public function test_no_facade_texture_is_emitted_inside_a_loop(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $view) {
            $inside = $this->insideLoops($this->bladeSource($view));

            if ($inside === '') {
                continue;
            }

            foreach (self::TEXTURES as $texture) {
                if (preg_match('/class="[^"]*(?<![-\w])'.preg_quote($texture, '/').'(?![-\w])/', $inside)) {
                    $offenders[] = $view.' · .'.$texture;
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'Estas texturas de fachada se pintan DENTRO de un bucle, o sea una vez por fila:',
            '  · '.implode("\n  · ", $offenders),
            '',
            'La decoración va en la PANTALLA, no en el componente que se repite (`#286`, tres',
            'rechazos del owner). Súbela al contenedor de la sección: una por pantalla, integrada',
            'en su superficie y a baja opacidad.',
        ]));
    }

    /**
     * **Y ningún dibujo del kit con clave LITERAL dentro de un bucle.**
     *
     * La clave variable es la que hace que la pieza signifique algo distinto en cada fila
     * (`zone-{{ $zone->slug }}`); una clave fija repetida por el catálogo es la mancha por tarjeta
     * que el owner ya rechazó una vez, entrando por la puerta del hueco de ilustración.
     */
    public function test_no_illustration_with_a_fixed_key_is_emitted_inside_a_loop(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $view) {
            $inside = $this->insideLoops($this->bladeSource($view));

            if ($inside === '' || ! str_contains($inside, 'x-site.ilu')) {
                continue;
            }

            // Solo delatan las claves literales: `clave="slot-x"` sin interpolación ni binding.
            if (preg_match('/<x-site\.ilu\b[^>]*\sclave="([^"{$]*)"/', $inside, $m)) {
                $offenders[] = $view.' · clave="'.$m[1].'"';
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'Estos dibujos del kit se piden con clave FIJA dentro de un bucle:',
            '  · '.implode("\n  · ", $offenders),
            '',
            'Un dibujo repetido por el catálogo es decoración por componente, no un marcador.',
            'Si de verdad tiene que salir una vez por fila, su clave debe DEPENDER de la fila.',
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    /** @return list<string> Rutas relativas a `resources/views`. */
    private function bladeFiles(): array
    {
        $base = resource_path('views');
        $out = [];

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $out[] = str_replace($base.'/', '', $file->getPathname());
            }
        }

        sort($out);

        return $out;
    }

    /**
     * El fuente de una vista **sin comentarios de Blade**.
     *
     * ⚠️⚠️ **Y aquí muerde por un motivo que no es el habitual**: lo que confunde al localizador no
     * es una CLASE citada en un comentario, es la propia palabra **`@foreach` escrita dentro de un
     * comentario** — el de `pages/rules.blade.php`, que explica que la trama ya no se pinta ahí—.
     * Con los comentarios en crudo el scanner da por abierto un bucle que no existe y **todo lo que
     * viene detrás cuenta como interior**: la vista arreglada sale delatada. Verificado por mutación.
     *
     * ▶ O sea que el limpiador no es cortesía: sin él esta guarda produce falsos POSITIVOS, que es
     * peor que no tenerla — un trinquete que grita con el producto sano acaba desactivado.
     */
    private function bladeSource(string $view): string
    {
        return (string) preg_replace(
            '/\{\{--.*?--\}\}/s', ' ',
            (string) file_get_contents(resource_path('views/'.$view)),
        );
    }

    /** Todo el marcado que queda DENTRO de un bucle de Blade, contando anidamiento. */
    private function insideLoops(string $blade): string
    {
        $tokens = preg_split(
            '/(@(?:end)?(?:foreach|forelse|for|while)\b)/', $blade, -1,
            PREG_SPLIT_DELIM_CAPTURE,
        ) ?: [];

        $depth = 0;
        $inside = '';

        foreach ($tokens as $token) {
            $bare = rtrim($token);

            if (in_array($bare, self::LOOP_OPEN, true)) {
                $depth++;

                continue;
            }

            if (in_array($bare, self::LOOP_CLOSE, true)) {
                $depth = max(0, $depth - 1);

                continue;
            }

            if ($depth > 0) {
                $inside .= $token."\n";
            }
        }

        return $inside;
    }
}
