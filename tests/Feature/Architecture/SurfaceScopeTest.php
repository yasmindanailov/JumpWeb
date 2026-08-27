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
            $this->sorted(self::SURFACE_TOKENS), $ink,
            'la superficie no cubre los ocho tokens que declara `SURFACE_TOKENS`: si uno se retira '.
            'de verdad, quítalo también de la constante y di por qué.',
        );
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

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Herramientas
    // ─────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function sheetContents(): array
    {
        $out = [];

        foreach (glob(base_path(self::SHEETS)) ?: [] as $path) {
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
