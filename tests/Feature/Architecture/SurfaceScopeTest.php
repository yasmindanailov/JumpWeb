<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **LA SUPERFICIE DE UNA SECCIÓN ES UN ÁMBITO, Y SUS DOS MITADES NO PUEDEN SEPARARSE**
 * (`docs/specs/tema-por-instalacion.md` §4.1).
 *
 * El producto pinta sobre DOS superficies —papel, la de siempre, y tinta— redefiniendo siete
 * tokens en `[data-surface]`. Eso invierte **866 de los 1.915 usos de `var()`** de las dos hojas
 * sin tocar ni una regla, y por eso el mecanismo es barato. Lo que no es barato es lo que puede
 * romperse sin que nada falle, que es justo lo que este fichero vigila:
 *
 *  1. **Que las dos superficies declaren el MISMO juego de tokens.** Si alguien añade uno a tinta
 *     y se olvida de papel, una sección de papel ANIDADA dentro de una de tinta hereda el token de
 *     tinta: texto claro sobre fondo claro, sin error, sin aviso y solo en la página que anida.
 *  2. **Que la paleta de tinta se DERIVE y no se teclee.** Un literal ahí significa que el cliente
 *     cambia `--fg` desde su paquete y su superficie oscura sigue siendo la del primer cliente.
 *     Es la misma forma de defecto que `RawColourIsNotATokenTest` cierra para el resto de la hoja.
 *  3. **Que el gris secundario pase AA SOBRE SU PROPIA superficie**, calculado aquí y no copiado:
 *     con un gris único eran **3,28 sobre tinta**, y ningún gris puede pasar en las dos.
 *
 * ⚠️ **Y una trampa que no se ve leyendo el CSS**: las declaraciones `--ink-*` viven en `:root`
 * a propósito. La sustitución de `var()` ocurre en el elemento donde la custom property se
 * DECLARA, así que ahí resuelven contra los valores de papel y bajan ya computadas. Declararlas
 * dentro del propio ámbito —donde `--fg` se está redefiniendo— sería un CICLO, y un ciclo en CSS
 * no falla: deja la propiedad inválida y el color se cae al inicial, en silencio.
 */
class SurfaceScopeTest extends TestCase
{
    private const SHEETS = 'public/css/*.css';

    /** Los siete tokens que definen una superficie, más la hoja que va encima. */
    private const SURFACE_TOKENS = [
        '--bg', '--bg-soft', '--bg-card', '--sheet', '--fg', '--fg-mute', '--line', '--line-strong',
    ];

    /**
     * **El rol de ACCIÓN también se re-declara en las dos superficies, y NO es un token de
     * superficie** (`docs/specs/tema-por-instalacion.md` §15).
     *
     * La distinción importa y por eso son dos constantes y no una lista revuelta. Los ocho de
     * arriba se re-declaran porque **cambian de valor** con el fondo. Estos cuatro se re-declaran
     * por un motivo distinto: para que su **fallback vuelva a evaluarse** en el ámbito. Su valor
     * es `var(--action-brand, var(--fg))`, y ese `var(--fg)` tiene que resolver contra el `--fg`
     * de ESTA superficie — si solo estuvieran en `:root`, bajarían computados contra papel y el
     * botón primario dejaría de invertirse dentro del menú abierto: relleno oscuro sobre fondo
     * oscuro, sin fallar y sin avisar.
     *
     * ▶ Y con `--action-brand` puesto por el paquete de un cliente, ese mismo fallback deja de
     * usarse en los tres ámbitos a la vez: el color de acción pasa a ser **idéntico en los dos
     * fondos**, que es lo que exige el sistema del 2.º cliente («CTA primario: idéntico en ambos
     * fondos, siempre con texto tinta»). Las dos conductas salen de la MISMA declaración.
     */
    private const ROLE_TOKENS = [
        '--action', '--on-action', '--action-hover', '--on-action-hover',
    ];

    /** @var ?array<string, array<string, string>> */
    private ?array $blocks = null;

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guardas de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El escaneo ve de verdad los tres bloques.**
     *
     * Sin esto, un parser roto deja este fichero verde para siempre sin mirar nada — el modo de
     * fallo de toda comprobación por texto, y uno que este repo ya ha pagado cuatro veces.
     */
    public function test_the_scan_actually_sees_the_three_blocks(): void
    {
        $blocks = $this->blocks();

        foreach ([':root', '[data-surface="ink"]', '[data-surface="paper"]'] as $selector) {
            $this->assertArrayHasKey(
                $selector, $blocks,
                "el escaneo no encuentra el bloque `{$selector}`: ¿ha cambiado el CSS, o se ha roto ".
                'el parser? Con 0 hallazgos esta guarda estaría verde sin mirar nada.',
            );
        }

        // ⚠️ **Aquí había un contador, y una mutación demostró que no servía.** Al romper el
        // `:root` de `landing.css` la clave seguía existiendo —`site.css` declara el suyo— y el
        // recuento se mantenía por encima del umbral: el centinela pasaba mientras el parser se
        // había quedado ciego a la mitad del corpus. Un umbral no distingue «leo poco» de «leo
        // otra cosa». Se asevera por NOMBRE lo que tiene que estar.
        foreach (['--fg', '--bg', '--sheet', '--ink-bg', '--ink-fg-mute', '--paper-bg', '--paper-fg-mute'] as $token) {
            $this->assertArrayHasKey(
                $token, $blocks[':root'],
                "el `:root` no trae `{$token}`: el parser no está leyendo el bloque donde vive la ".
                'capa de superficie, y las comprobaciones de abajo estarían mirando otra cosa.',
            );
        }
    }

    /**
     * **El resolutor de color caza sus propios ejemplos.**
     *
     * Resuelve `var()`, `color-mix()` y los tres formatos de literal. Si deja de hacerlo, la
     * comprobación de contraste de abajo pasaría comparando ceros contra ceros.
     */
    public function test_the_colour_resolver_catches_its_own_examples(): void
    {
        $root = ['--fg' => '#14130F', '--bg' => '#F4EFE3'];

        $cases = [
            '#14130F' => [20, 19, 15, 1.0],
            '#fff' => [255, 255, 255, 1.0],
            'rgba(20, 19, 15, 0.10)' => [20, 19, 15, 0.1],
            'var(--fg)' => [20, 19, 15, 1.0],
            // premultiplicado: el color sobrevive entero y solo baja el alfa. Es lo que hace que
            // `color-mix(…, transparent)` rinda EXACTAMENTE el `rgba()` que sustituye (`#143`).
            'color-mix(in srgb, var(--fg) 10%, transparent)' => [20, 19, 15, 0.1],
            'color-mix(in srgb, #FFFFFF 0%, #000000)' => [0, 0, 0, 1.0],
            'color-mix(in srgb, #FFFFFF 100%, #000000)' => [255, 255, 255, 1.0],
            'color-mix(in srgb, #FFFFFF 50%, #000000)' => [128, 128, 128, 1.0],
        ];

        foreach ($cases as $value => $expected) {
            $got = $this->resolve($value, $root);

            $this->assertNotNull($got, "el resolutor no entiende «{$value}»");
            $this->assertSame(
                array_map(fn ($v) => is_float($v) ? round($v, 3) : $v, $expected),
                [(int) round($got[0]), (int) round($got[1]), (int) round($got[2]), round($got[3], 3)],
                "el resolutor ha dejado de resolver «{$value}»: la guarda de contraste sería decorativa",
            );
        }

        // Y al revés: lo que no sabe resolver tiene que decirlo, no devolver negro.
        $this->assertNull($this->resolve('var(--no-existe)', $root));
        $this->assertNull($this->resolve('inherit', $root));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Las dos superficies declaran EXACTAMENTE el mismo juego de tokens.**
     *
     * Es la aserción con más valor del fichero. Un token que solo exista en una de las dos no
     * falla al cargar: hereda del ámbito de fuera, y el síntoma —texto de tinta sobre papel— solo
     * aparece **donde una superficie anida dentro de la otra**, que es el caso raro y el que nadie
     * mira. Aquí se caza al escribirlo.
     */
    public function test_both_surfaces_declare_the_same_token_set(): void
    {
        $ink = array_keys($this->blocks()['[data-surface="ink"]']);
        $paper = array_keys($this->blocks()['[data-surface="paper"]']);
        sort($ink);
        sort($paper);

        $this->assertSame(
            $paper, $ink,
            "Las dos superficies no redefinen los mismos tokens.\n".
            '  solo en tinta: '.implode(', ', array_diff($ink, $paper))."\n".
            '  solo en papel: '.implode(', ', array_diff($paper, $ink))."\n".
            "▶ El que falte HEREDA del ámbito de fuera. Una sección de papel dentro de una de tinta\n".
            "  se quedaría con el token de tinta: no falla, no avisa, y solo se ve donde anidan.\n",
        );

        $this->assertSame(
            $this->sorted(array_merge(self::SURFACE_TOKENS, self::ROLE_TOKENS)), $ink,
            'la superficie no cubre exactamente lo que declaran `SURFACE_TOKENS` (los ocho que '.
            'CAMBIAN con el fondo) y `ROLE_TOKENS` (los cuatro del rol de acción, que se re-declaran '.
            'para que su fallback se re-evalúe). Si uno se retira de verdad, quítalo también de su '.
            'constante y di por qué — y no lo muevas de constante: significan cosas distintas.',
        );
    }

    /**
     * **El rol de ACCIÓN conserva su indirección: nadie lo ata a un color.**
     *
     * Este caso es el que sostiene el quinto mecanismo. `--action` tiene que valer
     * `var(--action-brand, …)` en los TRES ámbitos, porque de ahí salen sus dos conductas: sin
     * color de acción declarado sigue a la superficie; con él, es el mismo en los dos fondos.
     *
     * ⚠️ Y `--action-brand*` **no puede declararse en las hojas del producto**. Lo emite
     * `ThemeSettings` en `<style id="jj-theme">` SOLO cuando la instalación tiene color de acción;
     * declararlo aquí —aunque fuera vacío o «inherit»— mataría el fallback sin que nada fallara:
     * el botón primario se quedaría sin relleno y la página cargaría igual.
     */
    public function test_the_action_role_keeps_its_indirection(): void
    {
        $scopes = [':root', '[data-surface="ink"]', '[data-surface="paper"]'];
        $esperado = [
            '--action' => 'var(--action-brand, var(--fg))',
            '--on-action' => 'var(--on-action-brand, var(--bg))',
            '--action-hover' => 'var(--action-brand-hover, var(--zone-1))',
            '--on-action-hover' => 'var(--on-action-brand-hover, var(--on-brand))',
        ];

        foreach ($scopes as $scope) {
            foreach ($esperado as $token => $valor) {
                $this->assertSame(
                    $valor, $this->blocks()[$scope][$token] ?? null,
                    "En `{$scope}`, `{$token}` ya no vale «{$valor}».\n".
                    "▶ Sin el fallback, el rol pierde una de sus dos conductas: o deja de seguir a la\n".
                    "  superficie (botón oscuro sobre menú oscuro) o deja de obedecer al paquete del\n".
                    '  cliente (ningún color de acción posible). Las dos salen de esa misma línea.',
                );
            }
        }

        // Y el otro lado: el producto NO declara el conmutador.
        foreach ($this->blocks() as $scope => $tokens) {
            foreach (array_keys($tokens) as $token) {
                $this->assertStringStartsNotWith(
                    '--action-brand', $token,
                    "`{$token}` está declarado en `{$scope}` dentro de una hoja del PRODUCTO. Ese ".
                    'conmutador lo emite `ThemeSettings` desde `theme.action`, y solo cuando existe: '.
                    'declararlo aquí anula el fallback y deja el botón primario sin relleno.',
                );
                $this->assertStringStartsNotWith('--on-action-brand', $token, "idem para `{$token}`");
            }
        }
    }

    /**
     * **Ninguna de las dos paletas de superficie se teclea a mano: se DERIVA.**
     *
     * Un literal en `--ink-*` o `--paper-*` es un color que ninguna instalación puede cambiar. El
     * cliente redefine `--fg` y `--bg` en su paquete, su superficie clara le obedece y la oscura
     * se queda con la del primer cliente — sin fallar y sin avisar. Es exactamente el defecto que
     * `#143` cerró para el resto de la hoja, y volvería a entrar por aquí.
     */
    public function test_neither_surface_palette_is_typed_by_hand(): void
    {
        $findings = [];

        foreach ($this->blocks()[':root'] as $property => $value) {
            if (! str_starts_with($property, '--ink-') && ! str_starts_with($property, '--paper-')) {
                continue;
            }

            if (preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $value) === 1) {
                $findings[] = sprintf('%s: %s', $property, trim($value));
            }
        }

        $this->assertSame([], $findings, implode("\n", array_merge(
            ['Una paleta de superficie lleva un color escrito a mano:'],
            array_map(fn (string $f): string => '  · '.$f, $findings),
            ['',
                '▶ Tiene que derivarse de `--fg` / `--bg`, que son los que el cliente cambia.',
                '▶ Si no, su superficie clara obedece al paquete del cliente y la oscura no.'],
        )));
    }

    /**
     * **El gris secundario pasa AA sobre SU superficie — calculado, no copiado.**
     *
     * ⚠️ Los números NO se escriben aquí: se resuelven del CSS y se calculan. Una tabla copiada
     * envejece con el primer cambio de paleta y deja de aseverar nada. Con el gris único de antes
     * la tinta daba **3,28** y este caso caería.
     */
    public function test_the_secondary_grey_passes_aa_on_its_own_surface(): void
    {
        foreach (['[data-surface="paper"]' => 'papel', '[data-surface="ink"]' => 'tinta'] as $selector => $rotulo) {
            $ratio = $this->contrastWithin($selector, '--fg-mute', '--bg');

            $this->assertGreaterThanOrEqual(
                4.5, $ratio,
                "El gris secundario da {$ratio} sobre la superficie de {$rotulo}: incumple AA (4.5).\n".
                "▶ Con `var(--fg-mute)` en 163 sitios, son 163 textos ilegibles que no fallan ni avisan.\n".
                '▶ Y no se arregla igualando los dos grises: NINGUNO pasa en las dos superficies.',
            );
        }
    }

    /**
     * **Y los dos grises son DISTINTOS.**
     *
     * El caso de arriba pasaría con un solo gris si alguien eligiera uno intermedio… y no existe:
     * medido, el que pasa en tinta falla en papel y al revés. Esta es la mitad que impide
     * «simplificar» el mecanismo a un valor único y romperlo por el otro lado.
     */
    public function test_the_two_greys_are_not_the_same_colour(): void
    {
        $paper = $this->resolveWithin('[data-surface="paper"]', '--fg-mute');
        $ink = $this->resolveWithin('[data-surface="ink"]', '--fg-mute');

        $this->assertNotSame(
            array_map(fn ($v) => (int) round($v), array_slice($paper, 0, 3)),
            array_map(fn ($v) => (int) round($v), array_slice($ink, 0, 3)),
            'las dos superficies usan el MISMO gris secundario. No es una simplificación: es que '.
            'uno de los dos fondos deja de cumplir AA (spec §1.3).',
        );
    }

    /**
     * **`--sheet` no es `--bg` ni sigue al acento.**
     *
     * Es la distinción de rol que `DEUDA.md` pedía y que esta capa existe para hacer explícita: la
     * HOJA (polaroid, tarjeta de invitación, resumen del post-form) no es el fondo de la sección
     * —que es crema— ni `--on-brand` —que sigue al acento y sobre un acento claro es tinta—.
     * Igualarlos parece una limpieza y repinta quince superficies.
     *
     * ⚠️ **Las dos mitades se aseveran de forma DISTINTA, y la primera versión de este caso lo hizo
     * mal.** Contra `--bg` vale comparar el VALOR: son crema y blanco, y que coincidan sería el
     * defecto. Contra `--on-brand` **no**: su `#FFFFFF` en `:root` es solo el respaldo —el valor
     * real lo calcula `ThemeSettings` por luminancia y lo inyecta en `<style id="jj-theme">`—, así
     * que hoy coinciden por casualidad y comparar valores daba un rojo falso. Lo que hay que
     * prohibir ahí es el MECANISMO: que `--sheet` se declare siguiendo al acento.
     */
    public function test_the_sheet_is_neither_the_background_nor_the_accent(): void
    {
        $root = $this->blocks()[':root'];
        $declaracion = $root['--sheet'] ?? '';
        $sheet = $this->resolve($declaracion, $root);

        $this->assertNotNull($sheet, 'no se puede resolver `--sheet`: ¿sigue declarado en `:root`?');

        $bg = $this->resolve($root['--bg'] ?? '', $root);
        $this->assertNotNull($bg, 'no se puede resolver `--bg`');

        $this->assertNotSame(
            array_map(fn ($v) => (int) round($v), array_slice($sheet, 0, 3)),
            array_map(fn ($v) => (int) round($v), array_slice($bg, 0, 3)),
            '`--sheet` vale lo mismo que `--bg`. Son roles DISTINTOS —la hoja va ENCIMA de la '.
            'superficie— y unificarlos repinta las quince tarjetas que lo usan.',
        );

        foreach (['--on-brand', '--brand', '--zone-1'] as $acento) {
            $this->assertStringNotContainsString(
                "var({$acento})", $declaracion,
                "`--sheet` se declara siguiendo a `{$acento}`, que va con el ACENTO y no con la ".
                'superficie. Sobre un acento claro `--on-brand` es tinta oscura: las polaroids se '.
                'volverían negras al cambiar la marca, sin que nada fallara.',
            );
        }
    }

    /**
     * **El ámbito de superficie no se pone nunca en la raíz.**
     *
     * Ahí es donde viven las declaraciones `--ink-*`, así que ámbito y declaración coincidirían en
     * el mismo elemento: eso SÍ es un ciclo, y un ciclo deja la propiedad inválida sin avisar.
     * Es la única forma de romper este mecanismo escribiendo CSS válido.
     */
    public function test_the_surface_scope_is_never_applied_to_the_root(): void
    {
        foreach ($this->sheetContents() as $path => $css) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?:^|[\s,])(?:html|:root)\s*\[data-surface/i',
                $css,
                "En `{$path}` el ámbito de superficie se aplica a la RAÍZ. Ahí se declaran los ".
                '`--ink-*`, así que el ámbito coincide con la declaración y se forma un CICLO: la '.
                'propiedad queda inválida y el color se cae al inicial, en silencio.',
            );
        }
    }

    /**
     * **El hero DECLARA su superficie, y sin eso todo lo demás pinta al revés.**
     *
     * Desde la tanda 2b el hero ya no invierte sus colores a mano: los invierte el ámbito. Eso
     * significa que `.hero__stage { background: var(--bg) }` —que dentro de tinta rinde oscuro—
     * pasaría a rendir **crema** si alguien quita el atributo del marcado. Y no fallaría nada:
     * el CSS es válido, la página carga, y el hero se ve blanco con el texto invisible.
     *
     * ⚠️ Es la primera vez que el producto CONSUME el mecanismo de la tanda 1, y este test es
     * lo único que ata las dos mitades. Un test de CSS no puede verlo: el atributo vive en Blade.
     */
    public function test_the_hero_declares_its_surface(): void
    {
        $blade = (string) file_get_contents(base_path('resources/views/home.blade.php'));

        $this->assertMatchesRegularExpression(
            '/class="hero__stage"[^>]*data-surface="ink"/',
            $blade,
            'el `.hero__stage` de la home ha dejado de declarar `data-surface="ink"`. Sin el '.
            'atributo, los siete tokens de superficie NO se redefinen y el hero pinta con los de '.
            'papel: fondo crema, texto crema encima. No falla nada — solo deja de verse.',
        );
    }

    /**
     * **`--onvideo` no puede volver a pintar un COLOR.**
     *
     * El modificador era TRES cosas con un solo nombre: superficie (invertir los colores sobre el
     * vídeo), tamaño (el titular del hero es mayor que un `h1` normal) y sombra sobre oscuro. La
     * primera se la lleva el ámbito; las otras dos se quedan y son legítimas.
     *
     * ⚠️ Si aparece un `color` o un `background` en una regla `--onvideo`, alguien ha vuelto a
     * pintar la superficie a mano — y esa declaración **no seguirá al paquete del cliente**,
     * porque `--onvideo` es una clase escrita en el marcado, no un ámbito.
     */
    public function test_onvideo_never_paints_a_colour_again(): void
    {
        $offenders = [];
        $seen = 0;

        foreach ($this->sheetContents() as $path => $css) {
            // Comentarios BLANQUEADOS conservando longitud: un `/* … */` pegado al selector se
            // comía la cabecera y el localizador daba cero reglas donde había una.
            $blind = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m) => str_repeat(' ', strlen($m[0])),
                $css,
            );

            preg_match_all('/([^{}\n][^{}]*--onvideo[^{}]*)\{([^{}]*)\}/', $blind, $rules, PREG_SET_ORDER);

            foreach ($rules as $rule) {
                $seen++;
                $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));

                foreach (explode(';', $rule[2]) as $chunk) {
                    if (! str_contains($chunk, ':')) {
                        continue;
                    }

                    $property = trim(explode(':', $chunk, 2)[0]);

                    if (in_array($property, ['color', 'background', 'background-color', 'fill', 'stroke', 'border-color'], true)) {
                        $offenders[] = "{$path}: {$selector} → {$property}";
                    }
                }
            }
        }

        // Guarda de la guarda: si el escaneo deja de ver reglas `--onvideo`, esta comprobación
        // quedaría verde sin mirar nada. Hoy quedan diez, todas de tamaño o de sombra.
        $this->assertGreaterThanOrEqual(
            5, $seen,
            'el escaneo no encuentra reglas `--onvideo` y debería ver unas diez: el localizador '.
            'se ha roto y esta guarda estaría pasando sin mirar.',
        );

        $this->assertSame(
            [], $offenders,
            "estas reglas `--onvideo` vuelven a pintar un COLOR:\n  ".implode("\n  ", $offenders)."\n".
            'La superficie la pinta `[data-surface="ink"]`, que sigue al paquete del cliente. Una '.
            'clase en el marcado no. Si de verdad hace falta un color distinto DENTRO del hero, la '.
            'regla va como `[data-surface="ink"] .lo-que-sea` y lee los alias `--paper-*` cuando '.
            'pinte sobre el botón, que invierte respecto a la superficie.',
        );
    }

    /**
     * **Dentro del hero, el fondo es OSCURO y el texto CLARO — y se comprueba resolviéndolo.**
     *
     * ⚠️⚠️ Esta guarda existe porque el fallo ocurrió TRES veces en la misma tanda, y ninguna otra
     * comprobación lo habría visto.
     *
     * El hero pinta ahora dentro del ámbito de tinta, así que **cada `var(--fg)` que quede en una
     * de sus reglas significa lo contrario de lo que significaba**. Al convertirlo:
     *   · el scrim tiene **cuatro** paradas de degradado y se convirtieron **dos** → la mitad
     *     inferior, la que da legibilidad al titular, pasaba de tinta 65 % a **crema 65 %**;
     *   · `.hero__stage-content { color: var(--bg) }` se dio por retirado y seguía ahí → texto
     *     oscuro sobre hero oscuro;
     *   · el telón del placeholder —lo único que se ve si el vídeo no carga— quedaba **crema**.
     * En los tres casos el CSS era válido, la suite verde y la página cargaba.
     *
     * ▶ **El criterio NO es «¿invierte entre ámbitos?»**: todo lo que lee un token de superficie
     * invierte, por definición — ése es el mecanismo. El criterio es el RESULTADO: se resuelve
     * cada declaración **en el ámbito donde de verdad vive** y se exige el rol que le toca.
     */
    public function test_inside_the_hero_the_ground_is_dark_and_the_text_is_light(): void
    {
        $ink = $this->inkScope();

        // (selector, propiedad, rol esperado) — «dark» = luminancia < 0,2 · «light» = > 0,5
        $expected = [
            ['.hero__stage', 'background', 'dark'],
            ['.hero__stage-scrim', 'background', 'dark'],
            ['.hero__stage-placeholder', 'background', 'dark'],
            ['.hero__stage-label', 'color', 'light'],
        ];

        $declarations = $this->heroColourDeclarations();
        $offenders = [];
        $checked = [];

        foreach ($expected as [$selector, $property, $role]) {
            $values = array_values(array_filter(
                $declarations,
                fn (array $d) => $d[0] === $selector && $d[1] === $property,
            ));

            $this->assertNotEmpty(
                $values,
                "el escaneo no encuentra `{$selector} → {$property}`: el localizador se ha roto y ".
                'esta guarda estaría verde sin mirar nada.',
            );

            foreach ($values as [, , $value]) {
                // ⚠️ Solo se juzga lo que lee un token de SUPERFICIE. La marca —`--zone-*`,
                // `--brand*`, `--on-brand`— **no se re-escopa** (spec §4.2: «la marca no cambia
                // con el fondo»), así que una mancha de acento sobre el hero es clara a propósito
                // y no tiene por qué obedecer al rol de la superficie que hay debajo.
                if (! preg_match('/var\(\s*--(bg|fg|sheet|line)[\w-]*\s*\)/', $value)) {
                    continue;
                }

                $colour = $this->resolve($value, $ink);

                if ($colour === null) {
                    continue;
                }

                $checked[$selector][] = $value;
                $light = $this->relativeLuminance($colour);
                $ok = $role === 'dark' ? $light < 0.2 : $light > 0.5;

                if (! $ok) {
                    $offenders[] = sprintf(
                        '%s → %s: `%s` rinde luminancia %.3f dentro de tinta y se esperaba %s',
                        $selector, $property, $value, $light, $role,
                    );
                }
            }
        }

        // ⚠️ Por NOMBRE y no por umbral — la lección que este mismo fichero ya documenta. Del
        // scrim se exigen las CUATRO paradas: fue exactamente el hueco por el que se convirtieron
        // dos y se dejaron dos.
        $this->assertCount(
            4, $checked['.hero__stage-scrim'] ?? [],
            'el escaneo ve '.count($checked['.hero__stage-scrim'] ?? []).' paradas del degradado del '.
            'scrim y son CUATRO. Si vuelve a ver menos, media conversión pasaría desapercibida.',
        );

        $this->assertSame(
            [], $offenders,
            "dentro del hero hay colores que ya no cumplen su rol:\n  ".implode("\n  ", $offenders)."\n\n".
            "El hero vive dentro de `[data-surface=\"ink\"]`, donde `--fg` vale CLARO y `--bg` vale\n".
            "OSCURO — lo contrario que fuera. Una regla que decía «oscuro» diciendo `var(--fg)` ahora\n".
            "dice «claro», y no falla: solo deja de verse.\n".
            '▶ Si el color tiene que ser oscuro, escríbelo con `--bg`; si claro, con `--fg`.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Herramientas
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * Declaraciones de COLOR de las reglas que pintan dentro del hero.
     *
     * ⚠️ El filtro no puede usar `\b` tras «hero»: en `.hero__stage` el carácter siguiente es `_`,
     * que es de palabra, así que `\bhero\b` no casa y el barrido no ve NI UNA regla del stage.
     * Lo pagó el instrumento que midió esta tanda.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function heroColourDeclarations(): array
    {
        $properties = ['color', 'background', 'background-color', 'background-image', 'fill', 'stroke', 'border-color'];
        $out = [];

        foreach ($this->sheetContents() as $css) {
            $blind = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m) => str_repeat(' ', strlen($m[0])),
                $css,
            );

            preg_match_all('/([^{}\n][^{}]*)\{([^{}]*)\}/', $blind, $rules, PREG_SET_ORDER);

            foreach ($rules as $rule) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));

                // Solo lo que cuelga del STAGE: es lo único que vive dentro del ámbito.
                if (! preg_match('/\.hero__(stage|title|chip)[\w-]*/', $selector)) {
                    continue;
                }

                foreach ($this->splitDeclarations($rule[2]) as [$property, $value]) {
                    if (in_array($property, $properties, true)) {
                        // Un degradado trae varias paradas: se comprueban TODAS. Es lo que faltó.
                        preg_match_all('/color-mix\([^()]*(?:\([^()]*\)[^()]*)*\)|var\(--[\w-]+\)/', $value, $stops);

                        foreach (($stops[0] ?: [$value]) as $stop) {
                            $out[] = [$selector, $property, $stop];
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Parte un cuerpo en declaraciones respetando paréntesis anidados.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function splitDeclarations(string $body): array
    {
        $out = [];
        $buffer = '';
        $depth = 0;

        for ($i = 0, $n = strlen($body); $i < $n; $i++) {
            $char = $body[$i];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ';' && $depth === 0) {
                if (str_contains($buffer, ':')) {
                    [$p, $v] = explode(':', $buffer, 2);
                    $out[] = [trim($p), trim($v)];
                }
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        if (str_contains($buffer, ':')) {
            [$p, $v] = explode(':', $buffer, 2);
            $out[] = [trim($p), trim($v)];
        }

        return $out;
    }

    /**
     * El diccionario de tokens tal y como los ve un elemento DENTRO de `[data-surface="ink"]`.
     *
     * ⚠️⚠️ **`:root` se pre-resuelve contra sí mismo ANTES de superponer el ámbito**, y eso no es
     * un refinamiento: sin ello, `--fg` → `var(--ink-fg)` → `var(--bg)` → `var(--ink-bg)` →
     * `var(--fg)` es un **CICLO**. El resolutor se rinde, devuelve `null`, las comprobaciones
     * hacen `continue` y la guarda pasa **sin mirar nada**. Ocurrió: esta misma guarda dio verde
     * ante dos mutaciones que reproducían el fallo que la motivó.
     * ▶ Es lo que hace el navegador: la sustitución de `var()` ocurre donde la propiedad se
     *   DECLARA, así que `--ink-*` y `--paper-*` bajan al ámbito ya computados contra papel.
     *
     * @return array<string, string>
     */
    private function inkScope(): array
    {
        $declared = $this->blocks()[':root'] ?? [];
        $root = [];

        foreach ($declared as $token => $value) {
            $resolved = $this->resolve($value, $declared);
            $root[$token] = $resolved === null ? $value : $this->asRgba($resolved);
        }

        $ink = $root;

        foreach (($this->blocks()['[data-surface="ink"]'] ?? []) as $token => $value) {
            $ink[$token] = $value;
        }

        // Segunda vuelta: el ámbito declara `--fg: var(--ink-fg)`, y `--ink-fg` ya está resuelto.
        foreach (self::SURFACE_TOKENS as $token) {
            $resolved = $this->resolve($ink[$token] ?? '', $root);

            if ($resolved !== null) {
                $ink[$token] = $this->asRgba($resolved);
            }
        }

        // Control: si los dos ámbitos rindieran lo mismo, no habría nada que comprobar.
        $paper = $this->resolve('var(--fg)', $root);
        $inked = $this->resolve('var(--fg)', $ink);
        $this->assertNotNull($paper, 'no se resuelve `--fg` en papel: el diccionario está roto');
        $this->assertNotNull($inked, 'no se resuelve `--fg` en tinta: el diccionario CICLA');
        $this->assertGreaterThan(
            100, abs($paper[0] - $inked[0]),
            '`--fg` rinde casi lo mismo en las dos superficies: el diccionario de tinta no se ha '.
            'construido bien y toda la comprobación sería contra sí misma.',
        );

        return $ink;
    }

    /** @param array{0: float, 1: float, 2: float, 3: float} $rgba */
    private function asRgba(array $rgba): string
    {
        return sprintf(
            'rgba(%d, %d, %d, %.4f)',
            (int) round($rgba[0]), (int) round($rgba[1]), (int) round($rgba[2]), $rgba[3],
        );
    }

    /** Luminancia relativa WCAG de un `[r, g, b, a]`. */
    private function relativeLuminance(array $rgba): float
    {
        $channel = static function (float $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($rgba[0]) + 0.7152 * $channel($rgba[1]) + 0.0722 * $channel($rgba[2]);
    }

    /** @return array<string, string> */
    private function sheetContents(): array
    {
        $out = [];

        foreach (glob(base_path(self::SHEETS)) ?: [] as $path) {
            // ⚠️ **La hoja de una INSTALACIÓN queda fuera, y no es un descuido.** `client.css`
            // no es del producto: existe precisamente para que un cliente declare sus valores
            // —literales incluidos, que es de lo que está hecho un paquete de tema— y juzgarla
            // con las reglas del producto sería prohibirle hacer aquello para lo que existe.
            // ▶ Y además la hacía MENTIR al gate: una guarda que asevera por hoja cambiaba el
            // recuento de aserciones según si la máquina tenía o no un paquete instalado, así
            // que el `pre-push` bloqueaba en una máquina o en la otra. Medido el 2026-08-28 al
            // montar el paquete del segundo cliente. Mismo criterio que `SidebarStyleWiringTest`,
            // que enumera las hojas del producto en vez de barrer la carpeta.
            if (basename($path) === 'client.css') {
                continue;
            }

            $out[str_replace(base_path().'/', '', $path)] =
                (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
        }

        return $out;
    }

    /**
     * Las custom properties de cada bloque que nos interesa, ya sin comentarios.
     *
     * @return array<string, array<string, string>>
     */
    private function blocks(): array
    {
        if ($this->blocks !== null) {
            return $this->blocks;
        }

        $wanted = [':root', '[data-surface="ink"]', '[data-surface="paper"]'];
        $found = [];

        foreach ($this->sheetContents() as $css) {
            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

            foreach ($rules as $rule) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));

                if (! in_array($selector, $wanted, true)) {
                    continue;
                }

                foreach (explode(';', $rule[2]) as $chunk) {
                    if (! str_contains($chunk, ':')) {
                        continue;
                    }

                    [$property, $value] = explode(':', $chunk, 2);
                    $property = trim($property);

                    if (str_starts_with($property, '--')) {
                        $found[$selector][$property] = trim($value);
                    }
                }
            }
        }

        return $this->blocks = $found;
    }

    /** @param list<string> $values */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    /** @return array{float, float, float, float} */
    private function resolveWithin(string $selector, string $token): array
    {
        $root = $this->blocks()[':root'];
        $scope = $this->blocks()[$selector] ?? [];
        $value = $scope[$token] ?? $root[$token] ?? '';
        $resolved = $this->resolve($value, $root + $scope);

        $this->assertNotNull(
            $resolved,
            "no se puede resolver `{$token}` en `{$selector}` (valor: «{$value}»)",
        );

        return $resolved;
    }

    private function contrastWithin(string $selector, string $a, string $b): float
    {
        $x = $this->luminance($this->resolveWithin($selector, $a));
        $y = $this->luminance($this->resolveWithin($selector, $b));

        return round((max($x, $y) + 0.05) / (min($x, $y) + 0.05), 2);
    }

    /**
     * Resuelve un valor CSS a `[r, g, b, a]`, o `null` si no sabe.
     *
     * ⚠️ Devolver `null` en vez de negro no es cortesía: un resolutor que inventa un color
     * convierte cualquier fallo de lectura en una aserción que pasa.
     *
     * @param  array<string, string>  $vars
     * @return ?array{float, float, float, float}
     */
    private function resolve(string $value, array $vars, int $depth = 0): ?array
    {
        $value = trim($value);

        if ($depth > 8 || $value === '') {
            return null;
        }

        if (strcasecmp($value, 'transparent') === 0) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        if (preg_match('/^var\(\s*(--[\w-]+)\s*\)$/', $value, $m) === 1) {
            return isset($vars[$m[1]]) ? $this->resolve($vars[$m[1]], $vars, $depth + 1) : null;
        }

        if (preg_match('/^#([0-9a-fA-F]{3,8})$/', $value, $m) === 1) {
            $h = $m[1];

            if (strlen($h) === 3) {
                $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
            }

            if (! in_array(strlen($h), [6, 8], true)) {
                return null;
            }

            return [
                (float) hexdec(substr($h, 0, 2)), (float) hexdec(substr($h, 2, 2)),
                (float) hexdec(substr($h, 4, 2)), strlen($h) === 8 ? hexdec(substr($h, 6, 2)) / 255 : 1.0,
            ];
        }

        if (preg_match('/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/', $value, $m) === 1) {
            return [(float) $m[1], (float) $m[2], (float) $m[3], isset($m[4]) ? (float) $m[4] : 1.0];
        }

        // color-mix(in srgb, A p%, B) — mezcla PREMULTIPLICADA, que es lo que hace que mezclar
        // contra `transparent` conserve el color y solo baje el alfa.
        if (preg_match('/^color-mix\(\s*in\s+srgb\s*,\s*(.+?)\s+([\d.]+)%\s*,\s*(.+?)\s*\)$/i', $value, $m) === 1) {
            $a = $this->resolve($m[1], $vars, $depth + 1);
            $b = $this->resolve($m[3], $vars, $depth + 1);

            if ($a === null || $b === null) {
                return null;
            }

            $p = ((float) $m[2]) / 100;
            $alpha = $p * $a[3] + (1 - $p) * $b[3];

            if ($alpha <= 0.0) {
                return [0.0, 0.0, 0.0, 0.0];
            }

            $mix = fn (int $i): float => ($p * $a[3] * $a[$i] + (1 - $p) * $b[3] * $b[$i]) / $alpha;

            return [$mix(0), $mix(1), $mix(2), $alpha];
        }

        return null;
    }

    /** @param array{float, float, float, float} $rgb */
    private function luminance(array $rgb): float
    {
        $lin = static function (float $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $lin($rgb[0]) + 0.7152 * $lin($rgb[1]) + 0.0722 * $lin($rgb[2]);
    }
}
