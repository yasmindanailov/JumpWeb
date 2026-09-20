<?php

namespace Tests\Feature\Site;

use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LA CABECERA DE PÁGINA DEL ARMAZÓN** (`DECISIONES #525`, carril de diseño Fase 3 · T3a·3,
 * `specs/rediseno-desde-canvas.md` §5.5). Artboard `Layout Paginas PJP` 1a/1b.
 *
 * El canvas: «una página no tiene hero: tiene cabecera de página» —rótulo con la RUTA, titular en
 * Display L y entradilla— y «una página no estrena tipografía»: es la cabecera de las secciones.
 *
 * ▶ Lo que se vigila NO es el aspecto —eso lo mide la sonda y lo mira el owner—: son las cosas que
 * se rompen **en silencio**, con la página cargando y la suite en verde.
 *
 * ⚠️⚠️ **Las rutas esperadas van ESCRITAS en el caso, no derivadas.** La guarda del inventario de
 * `#521` nació calculando lo esperado desde la misma constante que mutaba, y dos de nueve
 * mutaciones sobrevivían. Aquí la especificación es lo que el visitante lee: «/precios».
 */
class PageHeadTest extends TestCase
{
    use RefreshDatabase;

    /** Ruta → ¿lleva entradilla? Las del inventario que la estrenan en la T3a·3, más una legal. */
    private const PAGINAS = [
        '/precios' => true,
        '/normas' => true,
        '/contacto' => true,
        '/atracciones' => true,
        '/privacidad' => false,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    /**
     * **Cada página abre con UNA cabecera, SIN rótulo, y su único `<h1>` vive dentro.**
     *
     * ⚠️⚠️ Hasta `#586` el rótulo era la ruta escrita («/precios»); el owner la retiró por jerga
     * (`[DECIDIDO owner]`: «nada arriba»). Lo que se vigila es que no vuelva: ni la ruta ni ningún
     * otro rótulo encima del titular de una página interior.
     * ⚠️ Y **sin entradilla no hay párrafo**: uno vacío deja 16/20 px colgando bajo el titular.
     */
    public function test_every_interior_page_opens_with_one_h1_and_no_route_label(): void
    {
        foreach (self::PAGINAS as $ruta => $conEntradilla) {
            $x = $this->xpath($this->get($ruta)->assertOk()->getContent());

            $cabeceras = $x->query('//main//'.$this->clase('div', 'page__head'));
            $this->assertSame(1, $cabeceras->length, "«{$ruta}» no tiene exactamente una cabecera de página");
            $cabecera = $cabeceras->item(0);

            $rotulo = $x->query('.//'.$this->clase('p', 'page__eyebrow'), $cabecera);
            $this->assertSame(0, $rotulo->length, "«{$ruta}» vuelve a pintar un rótulo encima del titular");
            $this->assertStringNotContainsString($ruta, $cabecera->textContent, "la cabecera de «{$ruta}» escribe su ruta");

            $h1 = $x->query('//h1');
            $this->assertSame(1, $h1->length, "«{$ruta}» tiene {$h1->length} `<h1>`");
            $this->assertStringContainsString('page__title', $h1->item(0)->getAttribute('class'));
            $this->assertTrue($this->dentro($h1->item(0), $cabecera), "el `<h1>` de «{$ruta}» no está en su cabecera");

            $entradilla = $x->query('.//'.$this->clase('p', 'page__lede'), $cabecera);
            if ($conEntradilla) {
                $this->assertSame(1, $entradilla->length, "«{$ruta}» perdió su entradilla");
                $this->assertNotSame('', trim($entradilla->item(0)->textContent), "la entradilla de «{$ruta}» está vacía");
            } else {
                $this->assertSame(0, $entradilla->length, "«{$ruta}» pinta un párrafo de entradilla sin texto");
            }
        }
    }

    /**
     * **Una pantalla de SERVICIO se nombra a sí misma y NO escribe su URL.**
     *
     * ⚠️⚠️ Y en una de ellas no es estilo: la de restablecer la contraseña lleva el TOKEN en la ruta,
     * así que un rótulo derivado de la URL lo imprimiría en pantalla. La de mantenimiento sustituye a
     * la página caída, y escribir su ruta anunciaría un destino que ahora mismo no está.
     */
    public function test_a_service_screen_names_itself_and_never_writes_its_url(): void
    {
        $pantallas = [
            '/restablecer-contrasena/token-de-prueba' => [200, 'account.reset.eyebrow'],
            '/email/verificar' => [200, 'account.verify.eyebrow'],
        ];

        Setting::updateOrCreate(['key' => 'maintenance.page.precios'], ['value' => '1', 'group' => 'maintenance']);
        $pantallas['/precios'] = [503, 'site.page_maintenance.eyebrow'];

        foreach ($pantallas as $ruta => [$estado, $clave]) {
            $x = $this->xpath($this->get($ruta)->assertStatus($estado)->getContent());

            $rotulo = $x->query('//main//'.$this->clase('p', 'page__eyebrow'));
            $this->assertSame(1, $rotulo->length, "«{$ruta}» no pinta el rótulo de la cabecera");

            $texto = trim($rotulo->item(0)->textContent);
            $this->assertSame(__($clave), $texto, "«{$ruta}» no se nombra con su rótulo");
            $this->assertStringNotContainsString('/', $texto, "«{$ruta}» escribe una ruta en su rótulo");
            // ⚠️ Acotado a la CABECERA: el token SÍ tiene que estar en la página —el formulario lo
            // lleva en un campo oculto y la URL canónica lo repite—; lo que no puede es leerse.
            $cabecera = $x->query('//main//'.$this->clase('div', 'page__head'))->item(0);
            $this->assertStringNotContainsString('token-de-prueba', $cabecera->textContent, "la cabecera de «{$ruta}» imprime el token de la URL");
        }
    }

    /**
     * **La decoración de una página vive DENTRO del conjunto rótulo + titular, que la recorta; la
     * entradilla queda fuera.**
     *
     * Las dos piezas que la llevan tienen escrita la misma regla —«detrás del titular y NUNCA detrás
     * de un párrafo»— y las dos la incumplían contra la cabecera entera: medido en `#525`, en móvil el
     * abanico de `/precios` ya caía sobre su entradilla ANTES de esta tanda, y la trama de `/normas`
     * cayó sobre la nueva. Esto lo impide por ESTRUCTURA, que es lo que se puede vigilar sin ventana.
     */
    public function test_the_decoration_lives_in_the_lockup_and_never_reaches_the_lede(): void
    {
        // ⚠️ `/precios` estuvo en este bucle con su abanico en la ranura `deco`, y salió en `#580`: el
        // owner lo vio recortado en una banda («A3 Rayos… recortado») y hoy vive detrás de la figura de
        // su fachada, fuera de la cabecera. Lo que se vigila de él es justo eso, al final del caso.
        // ⚠️ Y `/normas` estuvo aquí con su trama hasta `#655`: se fue con su vista a la instancia, y con
        // ella la última decoración de cabecera del producto. La propiedad es del COMPONENTE, así que
        // se prueba en el componente, renderizado solo con una pieza en su ranura y una entradilla.
        $html = (string) $this->blade(
            '<x-site.page-head title="Titular" lede="Una entradilla"><x-slot:deco><div class="grain" aria-hidden="true"></div></x-slot:deco></x-site.page-head>',
        );
        $x = $this->xpath($html);
        $cabecera = $x->query('//'.$this->clase('div', 'page__head'))->item(0);
        $this->assertNotNull($cabecera, 'el componente no pinta la cabecera');

        $enConjunto = $x->query('./'.$this->clase('div', 'page__lockup--deco').'/'.$this->clase('div', 'grain'), $cabecera);
        $this->assertSame(1, $enConjunto->length, 'la pieza no está dentro del conjunto que la recorta');

        $sueltas = $x->query('./'.$this->clase('div', 'grain'), $cabecera);
        $this->assertSame(0, $sueltas->length, 'la pieza cuelga de la cabecera entera');

        $entradilla = $x->query('.//'.$this->clase('p', 'page__lede'), $cabecera)->item(0);
        $this->assertNotNull($entradilla, 'el componente no pinta la entradilla: el caso no distingue nada');
        $conjunto = $x->query('./'.$this->clase('div', 'page__lockup'), $cabecera)->item(0);
        $this->assertFalse($this->dentro($entradilla, $conjunto), 'la entradilla está dentro del conjunto decorado');

        // CONTROL: una página sin decoración no lleva el conjunto recortado (recortar sin motivo
        // cortaría la coma de un titular de Bungee a `line-height: .95`).
        $x = $this->xpath($this->get('/contacto')->assertOk()->getContent());
        $this->assertSame(0, $x->query('//'.$this->clase('div', 'page__lockup--deco'))->length);

        $reglas = $this->reglas('public/css/landing.css');
        $this->assertMatchesRegularExpression('/overflow:\s*(hidden|clip)/', $this->declaracion($reglas, '.page__lockup--deco'),
            'el conjunto decorado no recorta: la decoración puede volver a alcanzar a la entradilla');

        // `/precios` (`#580`): el abanico NO vuelve a la cabecera, donde el conjunto lo recorta, y
        // sigue en su ranura de fachada. ⚠️ Con él se fue `.page__head.pricing__head` (la cabecera a la
        // columna entera existía para que el abanico no pisara el titular dentro de 820 px).
        $x = $this->xpath($this->get('/precios')->assertOk()->getContent());
        $cabecera = $x->query('//main//'.$this->clase('div', 'page__head'))->item(0);
        $this->assertNotNull($cabecera, '«/precios» no pinta su cabecera: el caso no distingue nada');
        $this->assertSame(0, $x->query('.//'.$this->clase('div', 'rays'), $cabecera)->length,
            'el abanico de «/precios» ha vuelto a la cabecera, donde se recorta en una banda');
        $this->assertSame(1, $x->query('//'.$this->clase('div', 'fac-slot').'/'.$this->clase('div', 'rays'))->length,
            'el abanico de «/precios» ya no está en su ranura de fachada');
    }

    /**
     * **La cabecera de página COMPARTE declaración con la de sección** (el canvas: «una página no
     * estrena tipografía»), y ninguna regla le vuelve a poner un titular propio.
     *
     * ⚠️⚠️ El que tenía (`site.css`, 40/80 px) pedía `font-weight: 800` y `font-stretch: 75%` sobre
     * `--font-display`, que en esta instalación es Bungee y **trae una sola cara**: el navegador
     * FALSIFICABA negrita y condensada (las 77 reglas de `DEUDA.md`, `#479`). No falla nada: se ve.
     */
    public function test_the_page_head_shares_the_section_head_declaration_and_nothing_fakes_the_face(): void
    {
        $reglas = $this->reglas('public/css/landing.css');

        // ⚠️⚠️ **Tiene que ser la regla que declara la TIPOGRAFÍA** (la que lleva `font-size`), no
        // cualquiera: la entradilla comparte también la regla de su MARGEN, y con eso la primera
        // versión de este caso daba por compartida una entradilla que ya tenía cara propia. Lo cazó
        // `scripts/mutar-cabecera.py`: 17 de 18 hasta aquí.
        foreach (['.sec-head__eyebrow' => '.page__eyebrow', '.sec-head__title' => '.page__title', '.sec-head__lede' => '.page__lede'] as $seccion => $pagina) {
            $juntas = array_filter($reglas, fn (array $r): bool => in_array($seccion, $r[0], true)
                && in_array($pagina, $r[0], true)
                && str_contains($r[1], 'font-size'));
            $this->assertNotEmpty($juntas, "`{$pagina}` no comparte la declaración TIPOGRÁFICA de `{$seccion}`: la página estrena tipografía");
        }

        foreach (['public/css/landing.css', 'public/css/site.css'] as $hoja) {
            foreach ($this->reglas($hoja) as [$selectores, $cuerpo]) {
                if (! array_filter($selectores, fn (string $s): bool => str_contains($s, '.page__title'))) {
                    continue;
                }

                $donde = $hoja.' · '.implode(', ', $selectores);
                $this->assertDoesNotMatchRegularExpression('/font-stretch/', $cuerpo, "{$donde} vuelve a condensar el titular");
                $this->assertDoesNotMatchRegularExpression('/font-weight:\s*(?!400)\d+|font-weight:\s*bold/', $cuerpo, "{$donde} vuelve a pedir otra cara");

                if (preg_match('/font-size:\s*([^;]+)/', $cuerpo, $m)) {
                    $this->assertSame('var(--fs-display-l)', trim($m[1]), "{$donde} le da al titular un tamaño propio");
                }
            }
        }
    }

    /**
     * **El hueco de arriba se DERIVA del racimo, no de la ventana.**
     *
     * Era `clamp(108px, 14vh, 156px)`: un hueco en `vh` para dejar sitio a algo que mide siempre lo
     * mismo acierta por tramos — la lección de `#252` con el hero. A 390 la derivación da 88, que es
     * exactamente donde el canvas pone el rótulo.
     */
    public function test_the_top_air_derives_from_the_racimo_and_not_from_the_window(): void
    {
        $pages = array_filter($this->reglas('public/css/site.css'), fn (array $r): bool => $r[0] === ['.page']);
        $this->assertCount(2, $pages, 'se esperaban las dos reglas de `.page` (base y escritorio)');

        foreach ($pages as [, $cuerpo]) {
            $this->assertMatchesRegularExpression('/padding-top:\s*calc\([^;]*--nav-pad-block[^;]*--nav-logo-h[^;]*--nav-btn-h/', $cuerpo);
            $this->assertDoesNotMatchRegularExpression('/vh/', $cuerpo, 'el hueco de la página vuelve a depender de la ventana');
            $this->assertMatchesRegularExpression('/padding-bottom:\s*var\(--sec-air(-mobile)?\)/', $cuerpo,
                'lo último de una página no deja el aire entre secciones del sistema');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    /** Un elemento por clase EXACTA: por subcadena, `page__lockup` casaría con `page__lockup--deco`. */
    private function clase(string $tag, string $clase): string
    {
        return $tag."[contains(concat(' ', normalize-space(@class), ' '), ' {$clase} ')]";
    }

    private function dentro(DOMElement $nodo, DOMElement $contenedor): bool
    {
        for ($n = $nodo->parentNode; $n !== null; $n = $n->parentNode) {
            if ($n->isSameNode($contenedor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Las reglas de una hoja: `[lista de selectores, cuerpo]`. Sin comentarios —una lápida que nombra
     * lo retirado lo haría parecer vivo— y bajando a las de dentro de un `@media`.
     *
     * @return list<array{0: list<string>, 1: string}>
     */
    private function reglas(string $hoja): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(base_path($hoja)));
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER);

        return array_map(fn (array $r): array => [
            array_values(array_filter(array_map('trim', explode(',', $r[1])))),
            $r[2],
        ], $m);
    }

    /** El cuerpo de la regla cuyo selector es EXACTAMENTE ése (una sola). */
    private function declaracion(array $reglas, string $selector): string
    {
        $hay = array_values(array_filter($reglas, fn (array $r): bool => $r[0] === [$selector]));
        $this->assertCount(1, $hay, "se esperaba una regla `{$selector}`");

        return $hay[0][1];
    }
}
