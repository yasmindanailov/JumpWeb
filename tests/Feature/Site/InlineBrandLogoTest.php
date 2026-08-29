<?php

namespace Tests\Feature\Site;

use App\Domain\Content\Services\InlineSvg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **EL LOGOTIPO EN LÍNEA — lo que lo hace posible y lo que lo hace SEGURO** (`DECISIONES #254`).
 *
 * `[DECIDIDO owner, 2026-08-29]`: el logotipo se anima, y para eso deja de servirse por `<img>` —
 * un `<img>` no se puede animar por dentro— y pasa a incrustarse en el documento.
 *
 * ⚠️⚠️ **Ese cambio abre una puerta que el `<img>` tenía cerrada.** Dentro de un `<img>`, un SVG es
 * inerte: el navegador no ejecuta sus scripts ni carga nada externo. **En línea, sí.** El fichero lo
 * pone el operador al instalar el paquete del cliente, así que no viene de un desconocido — pero
 * «lo puso alguien de confianza» es una suposición, no una defensa. Y una suposición sobre un
 * fichero que se copia por `rsync` desde la máquina de otra persona.
 *
 * ▶ Por eso la regla es **lista blanca y todo o nada**: si el fichero trae cualquier cosa
 * ejecutable, no se sirve el logotipo. Un logotipo que falta se ve; un script que se cuela, no.
 * La plantilla tiene su suelo —el nombre del sitio en texto—, así que quedarse sin dibujo degrada.
 */
class InlineBrandLogoTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/inline-svg-'.getmypid().'.svg';
        InlineSvg::forget();
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
        InlineSvg::forget();
        parent::tearDown();
    }

    private function serve(string $contents): string
    {
        file_put_contents($this->tmp, $contents);
        InlineSvg::forget();

        return InlineSvg::brand($this->tmp);
    }

    /** **Un SVG limpio SÍ se sirve** — sin esto, todo lo de abajo se cumpliría con un `return ''`. */
    public function test_a_clean_svg_is_served_whole(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><g id="fig"><path d="M0 0h10v10H0z"/></g></svg>';

        $servido = $this->serve($svg);

        $this->assertStringContainsString('id="fig"', $servido, 'la pieza que se anima tiene que llegar entera');
        $this->assertStringContainsString('<svg', $servido);
    }

    /** **Y las referencias INTERNAS se conservan**: son justamente lo que la animación necesita. */
    public function test_internal_references_survive(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><defs><path id="u1" d="M0 0"/></defs><use href="#u1"/></svg>';

        $this->assertStringContainsString('href="#u1"', $this->serve($svg));
    }

    /**
     * **Lo ejecutable NO se sanea: se descarta el fichero entero.**
     *
     * @dataProvider peligros
     */
    #[DataProvider('peligros')]
    public function test_anything_executable_makes_the_logo_disappear(string $caso, string $svg): void
    {
        $this->assertSame(
            '', $this->serve($svg),
            "un SVG con {$caso} se ha servido en línea.\n".
            '▶ La regla es todo o nada: sanear a medias deja al siguiente creyendo que está limpio. '.
            'Sin logotipo la plantilla cae a su suelo de texto, que es una degradación, no un fallo.',
        );
    }

    /** @return array<string, array{string, string}> */
    public static function peligros(): array
    {
        return [
            'un <script>' => ['un `<script>`', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'un manejador en línea' => ['un `onload=`', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><path d="M0 0"/></svg>'],
            'un onclick en un hijo' => ['un `onclick=` en un hijo', '<svg xmlns="http://www.w3.org/2000/svg"><path onclick="alert(1)" d="M0 0"/></svg>'],
            'un javascript: en un enlace' => ['un `javascript:`', '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><path d="M0 0"/></a></svg>'],
            'un foreignObject' => ['un `<foreignObject>`', '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml">x</body></foreignObject></svg>'],
            'una referencia externa' => ['un `<use>` externo', '<svg xmlns="http://www.w3.org/2000/svg"><use href="https://ajeno.example/x.svg#a"/></svg>'],
            'una imagen remota' => ['una `<image>` remota', '<svg xmlns="http://www.w3.org/2000/svg"><image href="//ajeno.example/x.png"/></svg>'],
            'algo que no es un SVG' => ['algo que no es un SVG', '<html><body>hola</body></html>'],
        ];
    }

    /** **Sin paquete instalado no hay logotipo, y eso NO es un error**: este repo no lleva marca. */
    public function test_a_missing_package_is_not_an_error(): void
    {
        $this->assertSame('', InlineSvg::brand(sys_get_temp_dir().'/no-existe-'.getmypid().'.svg'));
    }

    /**
     * **La cabecera XML y el DOCTYPE se van.**
     *
     * Dentro de un documento HTML no pintan nada, y un `<?xml` suelto en mitad del marcado es basura
     * que algunos parsers arrastran hasta el árbol.
     */
    public function test_the_xml_prologue_is_dropped(): void
    {
        $servido = $this->serve('<?xml version="1.0"?><!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>');

        $this->assertStringNotContainsString('<?xml', $servido);
        $this->assertStringNotContainsString('DOCTYPE', $servido);
        $this->assertStringStartsWith('<svg', $servido);
    }

    /**
     * **Y el armazón lo sirve DE VERDAD, con su nombre accesible.**
     *
     * ⚠️ El `<span role="img" aria-label>` no es adorno: al incrustar el SVG se pierde el `alt` que
     * daba el `<img>`, y este enlace es **el único que toda página tiene**. Sin nombre, quien navega
     * por voz se encuentra «enlace» a secas.
     */
    public function test_the_frame_serves_it_inline_with_an_accessible_name(): void
    {
        if (! is_file(public_path('img/client-logo.svg'))) {
            $this->markTestSkipped('no hay paquete de marca instalado en esta máquina: es lo normal en el producto');
        }

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('nav__brand-logo--inline', $html, 'el armazón no sirve el logotipo en línea');
        $this->assertMatchesRegularExpression(
            '/nav__brand-logo--inline"[^>]*role="img"[^>]*aria-label="[^"]+"/',
            $html,
            'el logotipo en línea se ha quedado sin nombre accesible: es el único enlace de TODA página.',
        );
        $this->assertStringContainsString('id="fig"', $html, 'no llega la pieza que se anima');
    }

    /**
     * **QUE EL LOGOTIPO LLEGUE NO ES QUE SE MUEVA** (`DECISIONES #263`).
     *
     * ⚠️⚠️ El caso anterior comprueba que `id="fig"` está en el documento, y eso **pasaba en verde
     * mientras el logotipo NO SALTABA en once de las doce vistas**. La regla que lo animaba exigía
     * `body.nav--live`, y esa clase la pone el componente del hero, **que solo existe en la
     * portada**. Medido en `/servicios`: el logotipo pintado a 70 px, `#fig` en el árbol y
     * `getAnimations()` devolviendo **cero**. El defecto entró con `#254` y ninguna guarda lo vio,
     * porque todas miran que el logo se SIRVA.
     *
     * ▶ Esto mira la CONDICIÓN, que es lo único que se puede mirar sin navegador: la animación
     * tiene que declararse en **dos ramas** —una para las vistas sin hero, que no puede depender de
     * `.nav--live`, y otra para la portada, que sí—. Un navegador de verdad lo recorre en
     * `VERIFICACION-E2E-CAJON.md`; aquí se fija que nadie vuelva a dejar una sola.
     */
    public function test_the_logo_hop_also_reaches_the_views_without_a_hero(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        // Los selectores que declaran la animación, sin el `@media` de movimiento reducido:
        // ése la APAGA, así que contarlo daría ramas que no animan nada.
        preg_match_all(
            '/([^{}]+)\{[^{}]*animation:\s*brand-hop[^{}]*\}/',
            preg_replace('/@media[^{]*\(prefers-reduced-motion[^{]*\{.*?\}\s*\}/s', '', $css) ?? $css,
            $m,
        );

        $ramas = array_values(array_filter(array_map('trim', explode(',', implode(',', $m[1] ?? [])))));

        $this->assertNotEmpty($ramas, 'nadie declara ya `animation: brand-hop`: el logotipo no salta en ninguna vista');

        $sinHero = array_filter($ramas, fn (string $s) => str_contains($s, 'body:not([data-has-hero])'));
        $conHero = array_filter($ramas, fn (string $s) => str_contains($s, 'body[data-has-hero]'));

        $this->assertNotEmpty(
            $sinHero,
            "El salto del logotipo no tiene rama para las ONCE vistas sin hero, así que ahí no se mueve.\n"
            .'Ramas encontradas: '.implode(' | ', $ramas),
        );
        foreach ($sinHero as $s) {
            $this->assertStringNotContainsString(
                'nav--live',
                $s,
                "La rama sin hero exige `.nav--live`, y esa clase la pone el componente del HERO: en esas vistas no llega nunca.\n"
                ."Selector: {$s}",
            );
        }
        $this->assertNotEmpty(
            $conHero,
            'Se ha perdido la rama de la portada: allí el armazón nace oculto y el salto tiene que esperar a `.nav--live`.',
        );
    }

    /**
     * **La coreografía es la del mockup: cada tramo con su curva** (`#265`).
     *
     * ⚠️⚠️ **Se comprueba EN QUÉ fotograma está cada curva, no cuántas hay** (`#266`). Contarlas
     * dejaba pasar dos defectos: mover una curva al 100 % —donde no gobierna ningún tramo, porque
     * no hay intervalo después del último fotograma— mantenía el total en 7 con un tramo corriendo
     * a `ease`; y un comentario que nombrara la propiedad sumaba una de más.
     */
    public function test_the_hop_declares_its_physics_frame_by_frame(): void
    {
        $css = $this->siteCssSinComentarios();

        $this->assertMatchesRegularExpression(
            '/@keyframes\s+brand-hop\s*\{/', $css, 'ha desaparecido la coreografía del salto',
        );

        $conCurva = [];

        foreach (preg_split('/(?<=\})\s*/', trim($this->cuerpoDeKeyframes($css, 'brand-hop'))) ?: [] as $trozo) {
            if (preg_match('/^(\d+)%\s*\{(.*)\}$/s', trim($trozo), $f) === 1 && str_contains($f[2], 'animation-timing-function')) {
                $conCurva[] = (int) $f[1];
            }
        }

        sort($conCurva);

        $this->assertSame(
            [0, 5, 38, 62, 70, 80, 91],
            $conCurva,
            "Los tramos del salto ya no declaran su curva donde deben.\n"
            .'Fotogramas con curva: ['.implode(', ', $conCurva)."]\n"
            .'▶ Cada `animation-timing-function` gobierna el tramo que EMPIEZA en su fotograma: el 100 % '
            .'no puede llevarla y ninguno de los otros siete puede perderla, o ese tramo pasa a la curva '
            .'por defecto del navegador — que es justo lo que esta ficha combate.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/animation:\s*brand-hop[^;]*(?:--ease-|cubic-bezier|(?<![-\w])ease(?![-\w]))/',
            $css,
            'la declaración de `brand-hop` vuelve a llevar una curva: sería inerte y parecería que decide algo',
        );
    }

    /**
     * **El asentamiento NO puede fijar el `transform`, o mata el hover del logotipo.**
     *
     * ⚠️⚠️ `both` implica `forwards`: deja el último fotograma fijado y una animación gana siempre
     * a la cascada, así que el `translateY(-2px) rotate(-1.5deg)` del `:hover` dejaba de aplicarse
     * **para siempre**, sin que fallara nada.
     *
     * ⚠️ **Y ojo con cómo se comprueba en navegador**: `matrix(1, 0, 0, 1, 0, 0)` **no es «no hay
     * transform», es la identidad**. La primera sonda preguntó «¿tiene transform?», dijo que sí, y
     * el hover estaba muerto.
     */
    public function test_the_settle_never_pins_the_transform(): void
    {
        $css = $this->siteCssSinComentarios();

        preg_match_all('/animation:\s*brand-settle[^;]*/', $css, $m);

        $this->assertNotEmpty($m[0], 'ha desaparecido el asentamiento del lockup');

        foreach ($m[0] as $declaracion) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![-\w])(?:both|forwards)(?![-\w])/',
                $declaracion,
                "El asentamiento fija el `transform` al terminar y con eso MATA el hover del logotipo.\n"
                ."Declaración: {$declaracion}\n"
                .'▶ No hace falta: su primer fotograma ya es el reposo.',
            );
        }

        // ⚠️ **Y el mismo fallo escrito APARTE** (`#266`): un `animation-fill-mode: forwards` suelto
        // hace lo mismo que el `both` del atajo, y mirar solo el atajo no lo veía.
        foreach ($this->reglasQueDeclaran($css, 'brand-settle') as $selector => $cuerpo) {
            $this->assertDoesNotMatchRegularExpression(
                '/animation-fill-mode\s*:\s*(?:both|forwards)/',
                $cuerpo,
                "El asentamiento fija el `transform` con un `animation-fill-mode` suelto.\nSelector: {$selector}",
            );
        }
    }

    /**
     * ❗❗❗ **LA ANIMACIÓN TIENE QUE CAER SOBRE ALGO QUE SE PINTE** (`#266`).
     *
     * La guarda del defecto más caro de este carril: desde `#254` el salto se declaraba sobre
     * `#fig`, que vive dentro de `<defs>` y **no se dibuja** —lo que se dibuja son los `<use>` que
     * lo referencian—, y **una animación CSS sobre el original no alcanza al clon del `<use>`**.
     * Resultado: **el logotipo nunca saltó**, en ninguna vista, durante tres tandas.
     *
     * ▶ Medido con control, que es lo que lo zanjó: `style.transform` EN LÍNEA sobre `#fig` repinta
     * 773 px —el atributo `style` sí se clona—; la misma transformación por `@keyframes`, **cero**.
     *
     * ⚠️⚠️ `#263` ya había escrito la lección —«que la pieza llegue no es que se mueva»— y la aplicó
     * un nivel por encima: comprobó que la animación EXISTE en las doce vistas. Esto es el nivel de
     * abajo: *que una animación exista y compute no es que el dibujo se mueva.* Las tres tandas que
     * tocaron esto (`#254`, `#263`, `#265`) midieron valores computados, nunca píxeles.
     *
     * ⚠️ Estática, así que solo vigila lo decidible en el CSS: que el sujeto de la animación no sea
     * un `id` de `<defs>`. Que se pinte de verdad lo dice la sonda, con su control.
     */
    public function test_the_hop_animates_something_that_is_actually_painted(): void
    {
        $svg = (string) file_get_contents(public_path('img/client-logo.svg'));
        $defs = preg_match('/<defs\b.*?<\/defs>/s', $svg, $d) === 1 ? $d[0] : '';

        preg_match_all('/id="([^"]+)"/', $defs, $ids);
        $enDefs = $ids[1] ?? [];

        $this->assertNotEmpty($enDefs, 'el logotipo instalado no declara `<defs>`: la guarda estaría vigilando el vacío');

        preg_match_all('/([^{}]*)\{[^{}]*animation:\s*brand-hop[^{}]*\}/', $this->siteCssSinComentarios(), $m);
        $selectores = array_filter(array_map('trim', explode(',', implode(',', $m[1] ?? []))));

        $this->assertNotEmpty($selectores, 'nadie declara ya `animation: brand-hop`');

        // ⚠️⚠️ **Lo que se prohíbe es que el `id` sea el SUJETO del selector, no que aparezca.** La
        // primera versión buscaba `#fig` por subcadena y se ponía roja con el producto SANO: el
        // selector correcto lo cita dentro de `use[href="#fig"]`, que es justo la forma buena. Es
        // la trampa de aseverar por subcadena que este repo ya ha pagado tres veces.
        foreach ($selectores as $selector) {
            foreach ($enDefs as $id) {
                $this->assertDoesNotMatchRegularExpression(
                    '/#'.preg_quote($id, '/').'\s*$/',
                    $selector,
                    "El salto se declara SOBRE `#{$id}`, que vive dentro de `<defs>` y NO SE PINTA.\n"
                    ."Selector: {$selector}\n"
                    .'▶ Una animación CSS sobre el original no alcanza al clon del `<use>`: el logotipo no se '
                    .'movería, y no fallaría nada. Anímese el GRUPO que dibuja la figura.',
                );
            }
        }
    }

    /**
     * **La AMPLITUD del salto es proporcional a la figura, no un número de píxeles** (`#266`).
     *
     * ⚠️⚠️ Estaban copiados del mockup en píxeles (`translateY(90px)`), y en un elemento SVG eso
     * **no son píxeles**: son unidades del `viewBox`. Con el logotipo instalado el factor es
     * 0,1249, así que la silueta entraba desde **11,24 px** donde el mockup la trae desde 90 — el
     * salto era **7,5 veces más pequeño** — y la verificación de `#265` no podía verlo, porque su
     * comparador normalizaba la escala fuera: su «0,000 px» era una cifra ADIMENSIONAL.
     *
     * ▶ En porcentaje, con `transform-box: fill-box`, el desplazamiento se mide contra la caja de
     * la propia figura: los ratios del mockup (90/30 = 300 %) valen para el logotipo de cualquier
     * instalación. Es **más white-label que el propio mockup**, que los tiene atados a su figura.
     */
    public function test_the_hop_amplitude_is_relative_to_the_figure(): void
    {
        $css = $this->siteCssSinComentarios();

        preg_match_all(
            '/translateY\(\s*(-?[\d.]+)(px|%|em|rem)\s*\)/',
            $this->cuerpoDeKeyframes($css, 'brand-hop'),
            $t,
            PREG_SET_ORDER,
        );

        $this->assertNotEmpty($t, 'el salto ya no desplaza nada');

        foreach ($t as [$todo, , $unidad]) {
            $this->assertSame(
                '%',
                $unidad,
                "El salto vuelve a desplazar en `{$unidad}` (`{$todo}`).\n"
                .'▶ Dentro de un SVG eso NO son píxeles: son unidades del `viewBox`, y con el logotipo de '
                .'esta instalación el factor es 0,1249 — el salto se vería 7,5 veces más pequeño sin que '
                .'nada fallara. La amplitud va en % de la propia figura.',
            );
        }

        // ⚠️ **Acotado a la regla DEL LOGOTIPO, y no es tiquismiquis**: la primera versión buscaba
        // `fill-box` en toda la hoja y **no mordía** al quitarlo de aquí, porque hay otros cuatro
        // usos. Una guarda que se satisface con la declaración de OTRO no vigila la propia.
        $conFillBox = array_filter(
            $this->reglasQueDeclaran($css, 'transform-box'),
            fn (string $cuerpo, string $selector): bool => str_contains($selector, 'nav__brand-logo--inline')
                && preg_match('/transform-box\s*:\s*fill-box/', $cuerpo) === 1,
            ARRAY_FILTER_USE_BOTH,
        );

        $this->assertNotEmpty(
            $conFillBox,
            'la regla del logotipo ha perdido `transform-box: fill-box`: el % del salto pasaría a medirse '.
            'contra el lienzo entero del SVG en vez de contra la figura, y la amplitud volvería a ser otra',
        );
    }

    /**
     * **La sombra del logotipo son LOS NÚMEROS DEL MOCKUP** (`#267`).
     *
     * ⚠️⚠️ La guarda de una discusión que costó **tres vueltas del owner**: `#253` («demasiada
     * sombra») bajó la tinta de 45 a 30 razonando que «copiar un filtro no es copiar un resultado
     * si el sujeto es otro»; `#263` («la suya es más suave, la nuestra densa y definida») corrigió
     * el radio y la dejó en 18; y a la tercera el owner seguía viéndola distinta.
     *
     * ▶ **Ninguna de las dos comparó contra el original.** Al renderizar su lockup con su filtro,
     * el nuestro con su filtro y el nuestro con el que teníamos, y medir la densidad de sombra
     * sobre el mismo papel: **1.193.218 · 1.252.968 · 645.997**. O sea que el mismo filtro sobre
     * nuestro sujeto SÍ da su sombra (5 % de diferencia), y la nuestra era **la mitad**.
     *
     * ⚠️ Se asevera la GEOMETRÍA (offset, radio y porcentajes), no el color: éste sigue saliendo de
     * `--paper-fg` para que dentro del menú de tinta la sombra no se vuelva luz.
     */
    public function test_the_logo_shadow_keeps_the_mockup_numbers(): void
    {
        $regla = '';

        foreach ($this->reglasQueDeclaran($this->siteCssSinComentarios(), 'drop-shadow') as $selector => $cuerpo) {
            if (str_contains($selector, '.nav__brand-logo') && ! str_contains($selector, '--inline')) {
                $regla = $cuerpo;
            }
        }

        $this->assertNotSame('', $regla, 'el logotipo ha perdido su sombra');

        foreach ([
            '/drop-shadow\(\s*0\s+6px\s+16px/' => 'la sombra difusa ya no es `0 6px 16px`, que es la del mockup',
            '/45%/' => 'la sombra difusa ya no lleva el 45 % del mockup — se midió que con menos queda a la mitad de densidad',
            '/drop-shadow\(\s*0\s+1px\s+0/' => 'ha desaparecido la línea de contacto `0 1px 0`',
            '/25%/' => 'la línea de contacto ya no lleva el 25 % del mockup',
            '/var\(--paper-fg\)/' => 'la sombra ha dejado de leer `--paper-fg`: dentro del menú de tinta se volvería luz',
        ] as $patron => $porque) {
            $this->assertMatchesRegularExpression($patron, $regla, $porque."\n▶ Son los números de `#267`, medidos contra el lockup del mockup. No se ajustan a ojo.");
        }
    }

    /** El texto de `site.css` con los comentarios blanqueados (conservando offsets). */
    private function siteCssSinComentarios(): string
    {
        return (string) preg_replace_callback(
            '#/\*.*?\*/#s',
            fn (array $m): string => str_repeat(' ', strlen($m[0])),
            (string) file_get_contents(public_path('css/site.css')),
        );
    }

    /**
     * El cuerpo de un `@keyframes`, emparejando LLAVES.
     *
     * ⚠️ No vale buscar el siguiente `}`: un `@keyframes` contiene un bloque por fotograma, así que
     * el primer cierre está a una línea del principio y el cuerpo saldría casi vacío — con la
     * guarda pasando en verde por no llegar a mirar nada.
     */
    private function cuerpoDeKeyframes(string $css, string $nombre): string
    {
        if (preg_match('/@keyframes\s+'.preg_quote($nombre, '/').'\s*\{/', $css, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }

        $inicio = $m[0][1] + strlen($m[0][0]);
        $prof = 1;

        for ($i = $inicio, $n = strlen($css); $i < $n; $i++) {
            if ($css[$i] === '{') {
                $prof++;
            } elseif ($css[$i] === '}' && --$prof === 0) {
                return substr($css, $inicio, $i - $inicio);
            }
        }

        return '';
    }

    /**
     * Las reglas cuyo cuerpo cita `$nombre`, indexadas por selector.
     *
     * @return array<string, string>
     */
    private function reglasQueDeclaran(string $css, string $nombre): array
    {
        $out = [];
        $inicio = 0;

        while (($llave = strpos($css, '{', $inicio)) !== false) {
            $cierre = strpos($css, '}', $llave);

            if ($cierre === false) {
                break;
            }

            $cuerpo = substr($css, $llave + 1, $cierre - $llave - 1);

            if (str_contains($cuerpo, $nombre)) {
                $sel = trim(substr($css, $inicio, $llave - $inicio));
                $out[preg_replace('/\s+/', ' ', substr($sel, -70)) ?? '?'] = $cuerpo;
            }

            $inicio = $cierre + 1;
        }

        return $out;
    }
}
