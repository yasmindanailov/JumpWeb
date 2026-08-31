<?php

namespace Tests\Feature\Theme;

use App\Domain\Content\Services\IllustrationKit;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * **LO QUE SE PINTA DEL HUECO DE ILUSTRACIÓN** (`specs/hueco-ilustracion.md` §6, U3).
 *
 * Hermano de {@see IllustrationKitTest}, que vigila el CONTRATO. Aquí se vigila el consumo: el
 * componente `<x-site.ilu>` y las tres reglas de CSS.
 *
 * ⚠️⚠️ **EL CASO DEL TROQUEL ES UN PROXY, Y HAY QUE SABERLO.** La spec exige medirlo en PÍXELES
 * porque el defecto —un símbolo con `fill="currentColor"` propio hace que el troquel pinte la caja
 * entera— **sale idéntico en el marcado**. Un test de PHP no puede pintar. Lo que se asevera aquí
 * es el MECANISMO que la medición demostró que hace falta; la medición vive en la spec, con sus
 * números y su receta para rehacerla:
 *
 *   · sin la defensa: troquel HOSTIL **100,0 %** de la caja · con ella **64,4 %**
 *   · y 64,4 % es el valor EXACTO del símbolo limpio, con la celda de control vacía a 0,0 %
 *
 * ⚠️ **Y la primera medición dio el troquel por bueno POR SUERTE**: el `color` del documento valía
 * la tinta oscura del producto, que como máscara se lee casi igual que el negro. La sonda no
 * probaba el defecto, probaba un caso donde no se nota. Se rehízo con un `color` magenta imposible
 * de confundir. *Si vuelves a medir esto, el color del documento tiene que ser uno que no se
 * parezca ni al negro ni a la tinta.*
 */
class IllustrationHoleRenderTest extends TestCase
{
    private string $publicDir = '';

    /**
     * **Corre sobre un `public/` PROPIO**, como {@see ClientThemePackageTest}: si mirara el disco
     * real, la suite del producto cambiaría de resultado según la máquina fuera o no una
     * instalación — y un gate que depende de eso no es un gate.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDir = sys_get_temp_dir().'/jw-ilu-'.getmypid().'-'.uniqid();
        mkdir($this->publicDir.'/img', 0o777, true);
        @symlink(base_path('public/build'), $this->publicDir.'/build');
        $this->app->usePublicPath($this->publicDir);

        IllustrationKit::forget();
    }

    protected function tearDown(): void
    {
        IllustrationKit::forget();

        if ($this->publicDir !== '' && is_dir($this->publicDir)) {
            @unlink($this->publicDir.'/build');
            @unlink($this->publicDir.'/'.IllustrationKit::PATH);
            @rmdir($this->publicDir.'/img');
            @rmdir($this->publicDir);
        }

        parent::tearDown();
    }

    /** Instala un kit en el `public/` del test. */
    private function instalar(string $symbolAttrs = ''): void
    {
        file_put_contents(
            public_path(IllustrationKit::PATH),
            '<svg xmlns="http://www.w3.org/2000/svg">'
            .'<symbol id="zone-a" viewBox="0 0 64 64"'.$symbolAttrs.'><title>A</title>'
            .'<path d="M8 8h48v48H8Z"/></symbol></svg>',
        );

        // ⚠️ El caché del servicio se indexa por `filemtime`, y dos escrituras dentro del mismo
        // segundo comparten marca: sin esto, un caso leería el kit del caso anterior.
        IllustrationKit::forget();
        clearstatcache();
    }

    private function pintar(string $trato = 'plano', string $clave = 'zone-a'): string
    {
        return Blade::render('<x-site.ilu :clave="$clave" :trato="$trato" />', compact('clave', 'trato'));
    }

    // ══ CASO 3 · sin paquete no se emite NADA ═══════════════════════════════════════════════════

    /**
     * **Ni el `<svg>`.** No es tacañería: una caja vacía o un rectángulo de color es peor que nada,
     * y el producto NO dibuja un juego genérico propio porque sería arte versionado del producto —
     * por donde `#257` metió 43 dibujos de un cliente y por donde el favicon se quedó con el naranja
     * del primero.
     */
    public function test_without_a_kit_the_component_emits_nothing(): void
    {
        foreach (['plano', 'contorno', 'troquel'] as $trato) {
            $this->assertSame(
                '', trim($this->pintar($trato)),
                "sin kit instalado, el tratamiento «{$trato}» emite marcado: quedaría una caja vacía ".
                'o un rectángulo de color donde el hueco tiene que fallar hacia INVISIBLE.',
            );
        }
    }

    /** Y con kit sí, con el `?v=` que es el patrón del producto para todo asset de cliente. */
    public function test_with_a_kit_it_emits_a_use_with_cache_busting(): void
    {
        $this->instalar();

        $html = $this->pintar();

        $this->assertMatchesRegularExpression(
            '#<use href="[^"]*'.preg_quote(IllustrationKit::PATH, '#').'\?v=\d+\#zone-a"#',
            $html,
            'el dibujo no se pide, o se pide sin cache-busting: un cliente que cambia su kit no '.
            'vería el cambio.',
        );
        $this->assertStringContainsString('aria-hidden="true"', $html, 'el dibujo no es decorativo');
    }

    // ══ CASO 8 · el troquel, y sus DOS defensas ═════════════════════════════════════════════════

    /**
     * **El negro de la máscara va EXPLÍCITO, y `color` va con él.**
     *
     * Las dos mitades hacen falta y las dos están medidas: `fill:#000` porque el clon no puede
     * depender de heredar, y `color:#000` porque un `fill="currentColor"` del símbolo GANA al `fill`
     * heredado — si el color del documento es claro, el recorte se invierte y el troquel pinta la
     * caja entera (100,0 % medido, contra 64,4 % con la defensa).
     */
    public function test_the_troquel_pins_both_fill_and_color_explicitly(): void
    {
        $this->instalar();

        $html = $this->pintar('troquel');

        $this->assertMatchesRegularExpression(
            '/<use[^>]+style="[^"]*fill:\s*#000/i', $html,
            'el clon de la máscara no fija su relleno en negro: depende de heredar, y un símbolo con '.
            '`fill` propio gana.',
        );
        $this->assertMatchesRegularExpression(
            '/<use[^>]+style="[^"]*color:\s*#000/i', $html,
            "la máscara fija `fill` pero NO `color`.\n".
            "▶ Medido en navegador: con un símbolo que declara `fill=\"currentColor\"` —que es como\n".
            "  exporta el artboard— el troquel pasa a pintar el **100,0 %** de la caja del color de\n".
            "  marca. Es el rectángulo que `site.css` documenta como peor que no tener default.\n".
            '▶ Con `color:#000` vuelve a 64,4 %, que es el valor exacto del símbolo limpio.',
        );

        // ⚠️ El `id` se DERIVA de la clave. Un marcado no determinista no lo puede aseverar ninguna
        // comparación byte a byte ni ningún diff de árbol, que son dos de las tres redes del repo.
        $this->assertStringContainsString('id="ilu-troquel-zone-a"', $html);
        $this->assertStringContainsString('mask="url(#ilu-troquel-zone-a)"', $html);
    }

    /** Y el blanco del fondo de la máscara tampoco se hereda. */
    public function test_the_mask_background_is_pinned_white(): void
    {
        $this->instalar();

        $this->assertMatchesRegularExpression(
            '/<rect[^>]+style="[^"]*fill:\s*#fff/i', $this->pintar('troquel'),
            'el fondo de la máscara no fija su blanco: sin él no hay nada de lo que recortar.',
        );
    }

    // ══ CASO 9 · el grosor sale del SÍMBOLO, no de una constante ════════════════════════════════

    /**
     * ⚠️⚠️ **No hay constante que valga, y está medido**: la rejilla del artboard tiene piezas a
     * `stroke-width` 8 **y** 12, y una pose de `viewBox` 396 necesita 4,1 donde una constante
     * derivada del `viewBox` daría 49,5.
     *
     * ▶ Y llega como `data-stroke`, no como `stroke-width`: el segundo GANA al del `<use>` en
     * silencio y dejaría al producto sin decir nada.
     */
    public function test_the_contour_stroke_comes_from_the_symbol(): void
    {
        $this->instalar(' data-stroke="4.1"');

        $this->assertMatchesRegularExpression(
            '/<use[^>]+stroke-width="4\.1"/', $this->pintar('contorno'),
            'el `data-stroke` del símbolo no llega al `<use>`: el grosor lo estaría decidiendo una '.
            'constante del producto, y no hay ninguna que sirva para las tres familias.',
        );
    }

    /**
     * **Y sin `data-stroke` NO se escribe grosor: vacío es una RESPUESTA, no una falta.**
     *
     * Manda el del CSS. Es el mismo criterio que `--action-brand` en `#209`.
     */
    public function test_without_data_stroke_the_component_writes_none(): void
    {
        $this->instalar();

        $this->assertStringNotContainsString(
            'stroke-width=', $this->pintar('contorno'),
            'se escribe un `stroke-width` que el símbolo no ha pedido: eso es una constante del '.
            'producto disfrazada, y pisa al CSS.',
        );
    }

    /** El grosor es cosa del CONTORNO: en plano y troquel no pinta nada y no debe aparecer. */
    public function test_the_stroke_is_only_read_for_the_contour(): void
    {
        $this->instalar(' data-stroke="4.1"');

        foreach (['plano', 'troquel'] as $trato) {
            $this->assertStringNotContainsString('stroke-width=', $this->pintar($trato));
        }
    }

    // ══ LO QUE ENCONTRÓ MEDIR EN LA PÁGINA REAL ════════════════════════════════════════════════

    /**
     * ⚠️⚠️ **El `viewBox` del símbolo se COPIA al envoltorio, y sin él el dibujo sale deformado.**
     *
     * Un `<svg>` sin `viewBox` no tiene relación de aspecto intrínseca, así que `height: auto` cae a
     * los **150 px** por defecto de un elemento reemplazado. Medido en la tarjeta de zona: la caja
     * daba **638×150** donde tocaban ~190 de ancho con la proporción del dibujo.
     *
     * ▶ No lo vio ninguna de las sondas anteriores **porque todas fijaban las dos dimensiones**: el
     * defecto solo aparece cuando el consumidor deja una en `auto`, que es el caso normal.
     */
    public function test_the_wrapper_copies_the_symbol_viewbox(): void
    {
        $this->instalar();

        $this->assertMatchesRegularExpression(
            '/<svg[^>]+viewBox="0 0 64 64"/', $this->pintar(),
            'el envoltorio no copia el `viewBox` del símbolo: sin él no hay proporción y `height: auto` '.
            'cae a los 150 px por defecto, así que el dibujo sale con la caja deformada.',
        );
    }

    /**
     * ⚠️⚠️ **`.ilu` no puede imponer `width`, y el motivo es la CASCADA, no el gusto.**
     *
     * `.ilu` vive en `site.css` y los consumidores en `landing.css`, que se carga ANTES. Misma
     * especificidad → **gana el último**, así que un `width` aquí pisa al del consumidor y el dibujo
     * sale a todo el ancho. Medido en la tarjeta de zona: **638 px donde tocaban 190**.
     *
     * ▶ Es la lección de `#277` («gana el último, no el más específico») en otra hoja.
     */
    public function test_the_base_rule_does_not_impose_a_width_over_its_consumers(): void
    {
        $bloque = (string) $this->block(
            (string) file_get_contents(base_path('public/css/site.css')), '.ilu',
        );

        $this->assertNotSame('', $bloque, 'ha desaparecido la regla base `.ilu`');

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![-\w])width\s*:/', $bloque,
            "`.ilu` declara `width`.\n".
            "▶ Vive en `site.css`, que se carga DESPUÉS de `landing.css`: con la misma especificidad\n".
            "  gana el último, así que este ancho pisa el del consumidor y el dibujo sale a todo el\n".
            "  ancho de su contenedor (medido: 638 px donde tocaban 190).\n".
            '▶ El tamaño es colocación, y la colocación es del consumidor. `max-width` sí vale.',
        );
    }

    /** Y el consumidor —la tarjeta de zona— sí lo dimensiona y le da su color de superficie. */
    public function test_the_zone_card_sizes_and_recolours_its_illustration(): void
    {
        $landing = (string) file_get_contents(base_path('public/css/landing.css'));

        $this->assertMatchesRegularExpression(
            '/(?<![-\w])width\s*:/', (string) $this->block($landing, '.zone-intro__ilu'),
            'la tarjeta de zona no dimensiona su ilustración: se quedaría con el tamaño por defecto',
        );

        $this->assertMatchesRegularExpression(
            '/--ilu-fg\s*:/', (string) $this->block($landing, '.zone-intro__card'),
            "la tarjeta de zona no redefine `--ilu-fg`.\n".
            '▶ Es una superficie del COLOR DE LA ZONA (`background: var(--zone-1)`), no papel: sin '.
            'redefinirlo el dibujo se pintaría con la tinta del papel sobre el color de la marca.',
        );
    }

    /** Y la portada la pide con el `slug` de la zona, que es lo que hace que resuelva sola. */
    public function test_the_home_asks_for_the_zone_slug(): void
    {
        $this->assertStringContainsString(
            "<x-site.ilu :clave=\"'zone-'.\$zone->slug\"",
            (string) file_get_contents(resource_path('views/home.blade.php')),
            'la portada no pide la ilustración por el `slug` de la zona: la clave dejaría de casar '.
            'con la gramática del kit y no se pintaría nada.',
        );
    }

    // ══ CASO 12 · el CSS lee un token que RESUELVE ══════════════════════════════════════════════

    /**
     * ⚠️ **Se resuelve la cadena de `var()`, no se asevera el NOMBRE del token.** Aseverar el texto
     * literal ata la guarda a una implementación y se pone roja con el producto sano — le pasó a
     * una guarda de `#251`.
     *
     * ⚠️ Y leen `--paper-fg` y no `--fg` por lo mismo que las sombras de `#196`: dentro del hero
     * `--fg` vale CLARO, así que un dibujo que leyera `--fg` desaparecería sobre papel.
     */
    public function test_every_treatment_paints_with_a_token_that_resolves(): void
    {
        $site = (string) file_get_contents(base_path('public/css/site.css'));
        $vars = $this->rootVars();

        foreach ([
            '.ilu--plano use' => 'fill',
            '.ilu--contorno use' => 'stroke',
            '.ilu--troquel > rect' => 'fill',
        ] as $selector => $prop) {
            $bloque = $this->block($site, $selector);

            $this->assertNotNull($bloque, "no existe la regla `{$selector}`: el tratamiento no se pinta");

            $this->assertMatchesRegularExpression(
                '/(?<![-\w])'.$prop.'\s*:/', $bloque,
                "`{$selector}` no declara `{$prop}`",
            );

            preg_match('/(?<![-\w])'.$prop.'\s*:\s*([^;]+);/', $bloque, $m);
            $valor = trim($m[1] ?? '');

            $this->assertNotNull(
                $this->resolve($valor, $vars),
                "`{$selector} { {$prop}: {$valor} }` no resuelve a ningún color: o el token no ".
                'existe, o la cadena de `var()` está rota. El dibujo se pintaría con el valor '.
                'inicial del navegador (negro) y nadie se enteraría.',
            );
        }
    }

    /**
     * **La defensa de `color` está en las tres**, y sin ella el símbolo del cliente manda.
     *
     * Medido con un `color` magenta: la fuga es del **35,6 %** de píxeles ajenos en plano y hace que
     * el contorno salga MACIZO (37,9 % contra 4,1 %).
     */
    public function test_the_treatments_pin_color_so_currentcolor_cannot_leak(): void
    {
        $site = (string) file_get_contents(base_path('public/css/site.css'));

        foreach (['.ilu--plano use', '.ilu--contorno use'] as $selector) {
            $bloque = (string) $this->block($site, $selector);

            $this->assertMatchesRegularExpression(
                '/(?<![-\w])color\s*:/', $bloque,
                "`{$selector}` no fija `color`.\n".
                "▶ Un símbolo con `fill=\"currentColor\"` —que es como exporta el artboard— gana al\n".
                "  `fill` que hereda del `<use>`, y el dibujo sale del color del documento.\n".
                '▶ Medido: 35,6 % de píxeles ajenos en plano; el contorno pasa de 4,1 % a 37,9 % '.
                'de tinta, o sea que sale MACIZO y el tratamiento deja de existir.',
            );
        }
    }

    // ══ UN TRATAMIENTO DESCONOCIDO FALLA A LA CARA ══════════════════════════════════════════════

    /**
     * ⚠️ Se asevera el MENSAJE y no la clase: Blade envuelve lo que lance una plantilla en su
     * `ViewException`, así que fijar `InvalidArgumentException` ponía este caso rojo con la
     * conducta correcta. Lo que importa —y lo que ve quien escribe la vista— es que el error
     * NOMBRE el tratamiento malo en vez de pintar `plano` en silencio.
     */
    public function test_an_unknown_treatment_fails_loudly(): void
    {
        $this->instalar();

        try {
            $this->pintar('difuminado');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('difuminado', $e->getMessage());
            $this->assertStringContainsString('Tratamiento de ilustración desconocido', $e->getMessage());

            // La causa original se conserva: es lo que hace legible el fallo bajo el envoltorio.
            $raiz = $e;
            while ($raiz->getPrevious() !== null) {
                $raiz = $raiz->getPrevious();
            }
            $this->assertInstanceOf(InvalidArgumentException::class, $raiz);

            return;
        }

        $this->fail(
            'un tratamiento desconocido se pinta sin protestar. Caer por defecto en `plano` '.
            'escondería el fallo hasta que alguien mirase la pantalla.',
        );
    }

    // ══ utilidades ══════════════════════════════════════════════════════════════════════════════

    /**
     * El cuerpo de una regla, **sin comentarios**, o `null`.
     *
     * ⚠️⚠️ **El `strip` de comentarios NO es limpieza: es lo que hace que esta guarda vea algo.**
     * Sin él, `test_the_treatments_pin_color_so_currentcolor_cannot_leak` pasaba en VERDE con
     * `color: transparent` BORRADO del contorno — porque el comentario de esa misma regla explica
     * la defensa y contiene el texto `color: transparent`. La aserción casaba con la prosa.
     * ▶ Lo cazó el arnés de mutación, no la lectura. Es la trampa de «el nombre vivo en un
     * comentario» que `#253` ya documentó, repetida aquí.
     */
    private function block(string $css, string $selector): ?string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $re = '/(?:^|\})\s*'.preg_quote($selector, '/').'\s*\{(?<body>[^}]*)\}/m';

        // ⚠️⚠️ **TODAS las reglas del selector, no la primera.** El CSS acumula, y este repo declara
        // el mismo selector varias veces a propósito (`.zone-intro__card` tiene tres: la caja, el
        // color de zona y el hueco de ilustración). Devolver solo la primera dejaba esta guarda
        // mirando el bloque equivocado — y por el lado peligroso: una declaración añadida en un
        // segundo bloque sería invisible.
        if (preg_match_all($re, $css, $m, PREG_PATTERN_ORDER) < 1) {
            return null;
        }

        return implode("\n", $m['body']);
    }

    /** @return array<string, string> */
    private function rootVars(): array
    {
        $vars = [];

        foreach (['landing.css', 'site.css'] as $hoja) {
            $css = (string) file_get_contents(base_path('public/css/'.$hoja));

            if (preg_match('/:root\s*\{(.*?)\}/s', $css, $m) === 1) {
                preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $m[1], $d, PREG_SET_ORDER);

                foreach ($d as $decl) {
                    $vars[$decl[1]] ??= trim($decl[2]);
                }
            }
        }

        return $vars;
    }

    /**
     * Resuelve un valor CSS a un color, o `null` si no sabe.
     *
     * ⚠️ Devolver `null` en vez de negro no es cortesía: un resolutor que inventa un color convierte
     * cualquier fallo de lectura en una aserción que pasa. Misma regla que `SurfaceScopeTest`.
     *
     * @param  array<string, string>  $vars
     */
    private function resolve(string $value, array $vars, int $depth = 0): ?string
    {
        $value = trim($value);

        if ($depth > 8 || $value === '') {
            return null;
        }

        if (in_array(strtolower($value), ['none', 'transparent'], true)) {
            return $value;
        }

        if (preg_match('/^var\(\s*(--[\w-]+)\s*(?:,\s*(.+))?\)$/', $value, $m) === 1) {
            if (isset($vars[$m[1]])) {
                return $this->resolve($vars[$m[1]], $vars, $depth + 1);
            }

            return isset($m[2]) ? $this->resolve($m[2], $vars, $depth + 1) : null;
        }

        return preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\(|hsla?\(|color-mix\()/', $value) === 1
            ? $value
            : null;
    }
}
