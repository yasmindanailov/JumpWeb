<?php

namespace Tests\Feature\Architecture;

use Database\Seeders\LandingContentSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\XPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **EL OBJETIVO TÁCTIL MÍNIMO — 48×48 AL DEDO** (`docs/specs/tema-por-instalacion.md` §26 ·
 * `docs/specs/rediseno-desde-canvas.md` §3).
 *
 * ⚠️⚠️ **Subió de 44 a 48 en `#470`** (`[DECIDIDO owner]`). Las cifras de más abajo son las
 * MEDIDAS de `#264`, cuando el mínimo valía 44: se conservan porque describen lo que se midió
 * entonces, no lo que vale hoy. El valor vigente lo dice el token, y esta guarda lo asevera.
 *
 * Medido a 390 px sobre las siete vistas públicas renderizables: **51 controles distintos** por
 * debajo del mínimo (30 enlaces, 15 botones, los dos interruptores del panel de cookies y los
 * ítems del selector de idioma). `[DECIDIDO owner, 2026-08-29]`: **tanda propia**, y con dos
 * decisiones que ordenan todo lo demás.
 *
 * ▶ **1. Donde el dibujo está ajustado 1:1 con el mockup, el objetivo crece AL DEDO y NO A LA
 * VISTA**: `[data-tap]` pone un pseudo-elemento absoluto centrado que lleva el área al mínimo sin mover
 * un píxel. Donde crecer no daña —los chips del menú, «Reservar», el desplegable de idioma— se
 * crece de verdad con `min-height`, que se lee mucho mejor en el CSS.
 *
 * ▶ **2. El bloque legal del pie pasa a TIRA QUE SE DESLIZA**, el patrón que `#252` ya dio a los
 * destinos: a 390 px envolvía en tres renglones de 14 px y no había forma de llevarlo a 44 sin
 * mover el pie —apilarlo lo hacía crecer 54 px; la tira lo deja en 44 y el pie **encoge 34**—.
 *
 * ⚠️⚠️ **Lo que esta guarda NO puede hacer, y hay que saberlo antes de confiar en ella**: no mide
 * píxeles. Que un control lleve el marcador no demuestra que su área acabe midiendo el mínimo —puede
 * recortarla un ancestro con `overflow`, o puede solaparse con la del vecino—. Eso solo lo dice
 * una sonda en navegador, y la de esta tanda vive en `VERIFICACION-E2E-CAJON.md` §5.vicies.
 * Lo que esta guarda fija es lo que la sonda **no puede** vigilar en cada push: que el mecanismo
 * siga siendo el que se decidió y que ningún control conocido pierda su marcador por el camino.
 *
 * ⚠️ **La lista de familias SOLO CRECE.** Retirar una de aquí es devolver un control al montón de
 * los 51, y eso no se hace en silencio.
 */
class TouchTargetTest extends TestCase
{
    use ReadsSiteStylesheets;
    use RefreshDatabase;

    /**
     * Las familias que crecen DE VERDAD, con el porqué de cada una.
     *
     * Todas leen el token: un `44px` escrito a mano aquí sería el mismo defecto que `#238` y
     * `#250` persiguieron con la columna — el número vive en un sitio o vive en seis.
     */
    private const GROWS = [
        '.menu__chip' => 'cápsula del menú: 38 px medidos, y ahí hay sitio de sobra',
        '.lang-dd__trigger' => 'el disparador del idioma, que es una cápsula más',
        '.lang-dd__panel a' => 'las opciones van PEGADAS (hueco 0): un área centrada se metería en la vecina',
        '.skip-link' => 'primer focusable de la página, 38 px',
        // ⚠️ `.price__cta` se fue en `#531` con la tarjeta de tarifa: `/precios` se rehizo desde su
        // artboard y **no tiene botón propio** —la acción la trae el armazón—, así que ese CTA ya no
        // existe en ninguna superficie. *Una entrada sin sujeto es una guarda que pasa sin mirar.*
        '.foot__links a' => 'la tira de destinos, dentro de un carril que RECORTA',
        '.foot__legal > *' => 'la tira legal, el mismo carril y el mismo motivo',
        // ── Las cinco que entraron en `#476`, y todas por el MISMO motivo ──────────────────────
        // ⚠️⚠️ Ninguna declaraba mínimo: su alto salía de `padding` más la línea y daba **exactamente
        // 44** —el objetivo VIEJO—, así que cumplían por casualidad aritmética. En cuanto `#470`
        // subió el token a 48 se quedaron cortas **sin que nada fallara**: la suite no mide píxeles,
        // el token ya valía 48 en su propia cascada y leerlo no delataba nada. Lo cazó la sonda de
        // navegador (`scripts/sonda-geometria.mjs`), no una relectura.
        '.btn' => 'la familia ÚNICA de botones (`#321`): sin mínimo, su alto era padding + línea = 44',
        // ⚠️ `.ride-card__cta` se fue en `#482` con el carrusel. Su lección sigue escrita en el
        // docblock de esta lista: un literal de 44 que gana por especificidad al mínimo de `.btn`.
        '.form__field input, .form__field textarea' => 'campos de formulario: medían 46, y `#407` ya los fichó a 42 con sesión',
        '.form__field select' => 'el mismo suelo que su input hermano, o el formulario tiene dos alturas',
        // ⚠️ `.bd-field input` se fue en `#528` con el editor de invitaciones de `/cumpleanos`.
    ];

    /**
     * Los controles que amplían el área sin mover el dibujo, y dónde vive cada uno.
     *
     * @var array<string, array{0: string, 1: string}> clase => [ruta, por qué no crece]
     */
    private const TAPPED = [
        // ⚠️⚠️ **`faq__q` SE RETIRA, y no porque desaparezca el control: porque desapareció su
        // MOTIVO** (`#488`). Su nota decía «acordeón de 28 px: crecerlo subiría la sección 96», y
        // con la sección 08 rehecha el pulsable mide **64 px de alto por el ancho entero de la
        // tarjeta** —16 por encima del suelo de 48—, así que el pseudo centrado de `#264` no tiene
        // nada que ampliar: solo añadiría una capa que se solapa con la de la fila de al lado.
        // ▶ **La propiedad no se pierde, se muda**: la vigila `DudasSectionTest`, que exige el
        // `min-height: 64px` **y** que `data-tap` no vuelva. Sin esta nota, el siguiente que lea el
        // censo pensaría que a la FAQ se le olvidó el marcador.
        // ⚠️ **Se mira en `/precios` y ya no en `/`** (`#479`): la sección «Cuánto» de la portada
        // estrena la pestaña del SISTEMA (`.tabset__tab`, con su propio `min-height`) y la cápsula
        // vieja se queda donde aún vive —la página de tarifas y `/servicios`—. La guarda seguía
        // buscándola en la portada y **se puso roja diciendo que vigilaba el vacío**, que es
        // exactamente lo que tenía que hacer: sin ese aviso, el día que `.zone-tab` desapareciera
        // del todo este caso pasaría en verde sin mirar nada.
        // ⚠️ **Se mira en `/servicios` desde `#531`**: `/precios` se rehizo desde su artboard y ya no
        // tiene pestaña de zona —quien abre el enlace que le han mandado no ha elegido zona, así que
        // la página enseña las dos tablas a la vez—. La cápsula vieja sigue viva en `/servicios` y en
        // el cajón, que son de otra fase: la guarda se muda con su sujeto, no se retira.
        'zone-tab' => ['/servicios', 'pestaña de zona, 37 px, con la rejilla del mockup detrás'],
        // ⚠️ `bd-tab`, `bd-invite-cta` y `bd-swatch` se fueron con la página vieja de `/cumpleanos`
        // (`#483` el enlace de la portada, `#528` el resto): la página rehecha compara los packs
        // lado a lado, sin pestañas, y ya no tiene editor de invitaciones.
        'cookie__config' => ['/', 'el «Configurar» del banner, 17'],
        'cookie__policy' => ['/', 'el enlace de la política dentro del panel, 16'],
        'ck-tgl' => ['/', 'el interruptor de finalidad: 42×24, y su ::after ya dibuja el pomo'],
        // ⚠️ **Se mira en `/contacto` desde `#533`**, y es la mudanza de `zone-tab` otra vez:
        // `/normas` se rehízo desde su artboard y **ya no lleva «Volver al inicio»** —el armazón da
        // menú y pie, y `#527` decidió que las interiores acaban ahí—. El control **no ha muerto**:
        // medido en vivo, `/contacto` lo pinta y las legales ya no. La guarda se muda con su sujeto,
        // no se retira.
        'page__back' => ['/contacto', 'el «Volver al inicio» de las páginas de contenido, 21'],
        'svc-hero__jump' => ['/servicios', 'los saltos a cada servicio, 36'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El instrumento, antes que lo que mide
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El barrido ve las hojas y sabe leer un selector de atributo.**
     *
     * Sin esto, cualquier caso de abajo podría estar pasando en verde sobre cero reglas. Y el
     * segundo control no sobra: este fichero busca por `[data-tap]`, y un barrido que solo supiera
     * casar clases devolvería vacío sin fallar.
     */
    public function test_the_scan_sees_the_sheets_and_can_read_an_attribute_selector(): void
    {
        $rules = $this->siteRules();

        $this->assertGreaterThan(500, count($rules), 'el barrido se ha quedado corto: es el instrumento, no la hoja');

        $atributo = array_filter($rules, fn (array $r) => str_contains($r['selector'], '[data-tap]'));

        $this->assertNotEmpty($atributo, 'el barrido no encuentra ninguna regla por selector de atributo');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El mecanismo
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * El mínimo es un TOKEN, vale 48 px y se declara una sola vez.
     *
     * ⚠️⚠️ **Aquí se escribió una afirmación FALSA y la cazó una mutación que no mordía**: decía que
     * el «una sola vez» se mide sobre `siteSheets()` «que incluye `client.css`, o sea que un
     * cliente no puede bajarse el suelo». El trait **excluye `client.css` a propósito** —lo dice su
     * docblock—, así que este caso no ve el paquete y un cliente **sí podía** bajarlo. Lo impide el
     * caso de abajo, que lee el fichero directamente (`#472`).
     */
    public function test_the_minimum_is_a_token_declared_once_and_worth_48(): void
    {
        $declaraciones = [];

        foreach ($this->siteSheets() as $sheet => $css) {
            preg_match_all('/--tap-min\s*:\s*([^;}]+)/', $css, $m);

            foreach ($m[1] as $valor) {
                $declaraciones[] = [$sheet, trim($valor)];
            }
        }

        $this->assertCount(
            1,
            $declaraciones,
            'el mínimo táctil se declara en '.count($declaraciones).' sitios: '.json_encode($declaraciones),
        );
        $this->assertSame('48px', $declaraciones[0][1], 'el mínimo táctil ya no vale 48 px');
    }

    /**
     * **El paquete de una instalación puede SUBIR el suelo táctil, nunca bajarlo.**
     *
     * `#470` decidió que el mínimo sube en el PRODUCTO porque es accesibilidad y no marca: WCAG
     * 2.5.5 lo pone en 44 y el sistema del 2.º cliente lo pide en 48 «sin excepciones». La
     * consecuencia es ésta — un paquete puede ser más estricto que el producto, jamás menos.
     *
     * ⚠️⚠️ **Lee `client.css` DIRECTAMENTE y no por `siteSheets()`**, que lo excluye a propósito.
     * La primera versión de esta regla se escribió apoyada en el trait y **habría vigilado una
     * cadena vacía**: verde siempre, con el cliente pudiendo bajarse a 24. Lo cazó una mutación que
     * no mordía, no una lectura.
     */
    public function test_an_installation_package_may_raise_the_touch_floor_never_lower_it(): void
    {
        $ruta = base_path('public/css/client.css');

        if (! is_file($ruta)) {
            $this->markTestSkipped(
                'sin paquete de instalación: en un clon limpio no existe `public/css/client.css` (`#468`).',
            );
        }

        if (! preg_match_all('/--tap-min\s*:\s*(\d+)px/', (string) file_get_contents($ruta), $m)) {
            $this->addToAssertionCount(1);   // no lo toca: el suelo del producto manda, que es lo normal

            return;
        }

        $suelo = 48;

        foreach ($m[1] as $valor) {
            $this->assertGreaterThanOrEqual(
                $suelo,
                (int) $valor,
                "el paquete de instalación baja el objetivo táctil a {$valor}px, por debajo de los ".
                "{$suelo} del producto. Crecer está permitido —un cliente puede ser más estricto—; ".
                'encoger no, porque entonces esa instalación deja de cumplir la accesibilidad que el '.
                'producto garantiza y no falla nada.',
            );
        }
    }

    /**
     * **El mecanismo existe, y SOLO bajo `(pointer: coarse)`.**
     *
     * ⚠️ No es un detalle: con ratón, un área 30 px más alta que su enlace dispararía el `:hover`
     * desde lejos y el cursor cambiaría a mano sobre el vacío. El defecto que se corrige es «115
     * controles por debajo del mínimo **en móvil**», y ahí es donde tiene que actuar.
     */
    public function test_the_touch_area_only_exists_for_a_coarse_pointer(): void
    {
        $css = $this->siteSheets()['site.css'] ?? '';

        $this->assertNotSame('', $css, 'no se encuentra site.css');

        $fuera = preg_replace('/@media\s*\(\s*pointer\s*:\s*coarse\s*\)\s*\{.*?\n\}/s', '', $css);

        $this->assertStringContainsString('[data-tap]::before', $css, 'el mecanismo táctil no está declarado');
        $this->assertStringNotContainsString(
            '[data-tap]::before',
            (string) $fuera,
            'el área táctil se declara FUERA de `(pointer: coarse)`: con ratón el hover saltaría desde lejos',
        );
    }

    /**
     * **El área está CENTRADA y nunca encoge un control.**
     *
     * `max(100%, var(--tap-min))` es lo que hace que la misma regla valga para un enlace de 14 px
     * de alto y para un botón de 300 de ancho. Un `width: 44px` a secas convertiría en 44 los
     * objetivos que ya son mayores, que es peor que no hacer nada.
     */
    public function test_the_area_is_centred_over_the_control_and_never_shrinks_it(): void
    {
        $body = $this->ruleBody('[data-tap]::before');

        $this->assertStringContainsString('position: absolute', $body, 'el área no está fuera del flujo');

        foreach (['width', 'height'] as $eje) {
            $this->assertMatchesRegularExpression(
                '/(?<![-\w])'.$eje.'\s*:\s*max\(\s*100%\s*,\s*var\(--tap-min\)\s*\)/',
                $body,
                "el $eje del área táctil no es `max(100%, var(--tap-min))`: o no llega al mínimo, o ENCOGE los controles que ya lo cumplen",
            );
        }

        foreach (['top: 50%', 'left: 50%', 'translate(-50%, -50%)'] as $pieza) {
            $this->assertStringContainsString($pieza, $body, "el área táctil no está centrada: falta `$pieza`");
        }
    }

    /**
     * **Es `::before` porque `::after` está ocupado, y sigue estándolo.**
     *
     * `.ck-tgl::after` dibuja el pomo del interruptor de cookies. Si alguien mueve el área táctil a
     * `::after` «porque da igual», ese pomo desaparece — y el interruptor seguiría funcionando, así
     * que no lo vería ningún test de conducta.
     */
    public function test_the_area_uses_before_because_the_cookie_toggle_owns_after(): void
    {
        $css = $this->siteSheets()['site.css'] ?? '';

        $this->assertStringNotContainsString('[data-tap]::after', $css, 'el área táctil se ha mudado a ::after, que en `.ck-tgl` dibuja el pomo');
        $this->assertStringContainsString('.ck-tgl::after', $css, 'el pomo del interruptor de cookies ha desaparecido');
    }

    /** Las familias que crecen de verdad leen el TOKEN, no un literal. */
    public function test_the_families_that_grow_read_the_token(): void
    {
        foreach (self::GROWS as $selector => $porque) {
            $body = $this->ruleBody($selector);

            $this->assertMatchesRegularExpression(
                '/(?<![-\w])min-height\s*:\s*var\(--tap-min\)/',
                $body,
                "`$selector` ($porque) ya no crece hasta el mínimo táctil, o lo escribe con un literal",
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El marcado
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Ningún control conocido pierde su marcador.**
     *
     * Es el caso que de verdad protege la tanda: el mecanismo puede seguir perfecto en el CSS y un
     * control quedarse sin `data-tap` al reescribir su plantilla, sin que falle nada.
     */
    public function test_every_known_control_still_carries_the_marker(): void
    {
        $html = [];

        foreach (self::TAPPED as $clase => [$ruta, $porque]) {
            $html[$ruta] ??= (string) $this->get($ruta)->assertOk()->getContent();

            $nodos = $this->nodes($html[$ruta], $clase);

            $this->assertNotEmpty($nodos, "`$clase` no se pinta en $ruta: la guarda estaría vigilando el vacío");

            $interactivos = array_filter($nodos, fn ($nodo) => ! $nodo->hasAttribute('aria-disabled'));

            $this->assertNotEmpty(
                $interactivos,
                "todos los `$clase` de $ruta están deshabilitados: la guarda estaría vigilando el vacío",
            );

            foreach ($interactivos as $nodo) {
                $this->assertTrue(
                    $nodo->hasAttribute('data-tap'),
                    "`$clase` ($porque) ha perdido su área táctil en $ruta",
                );
            }
        }
    }

    /**
     * **El enlace EN LÍNEA del texto de cookies se queda fuera, y es de norma.**
     *
     * Un objetivo de 44 px de alto sobre una línea de 18 se come el renglón de arriba y el de
     * abajo: texto que se lee y se selecciona, no se pulsa. WCAG exime justamente a los enlaces en
     * línea dentro de un bloque de texto (2.5.5 y 2.5.8, «inline»). Sin este caso, la excepción
     * parecería un descuido y el siguiente que pase la «arreglaría».
     */
    public function test_the_inline_link_inside_the_cookie_text_is_left_out_on_purpose(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $parrafos = $this->nodes($html, 'cookie__body');

        $this->assertNotEmpty($parrafos, 'el texto del banner de cookies ha desaparecido');

        $enlaces = [];

        foreach ($parrafos as $parrafo) {
            foreach ($parrafo->getElementsByTagName('a') as $enlace) {
                $enlaces[] = $enlace;
            }
        }

        $this->assertNotEmpty($enlaces, 'el texto del banner de cookies ya no lleva enlace');

        foreach ($enlaces as $enlace) {
            $this->assertFalse(
                $enlace->hasAttribute('data-tap'),
                'el enlace en línea del texto de cookies ha ganado un área táctil de 44: se comería los renglones vecinos',
            );
        }
    }

    /**
     * **El bloque legal es una TIRA, y su envoltorio no es decorativo.**
     *
     * Sin el envoltorio la vela viajaría con el contenido —el porqué está junto a la regla— y sin
     * el carril el bloque volvería a envolver en tres renglones de 14 px, que es de donde viene
     * toda esta tanda.
     */
    public function test_the_legal_block_is_a_rail_with_its_sail(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertGreaterThan(
            0,
            $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' foot__legal-wrap ')]//*[contains(concat(' ', normalize-space(@class), ' '), ' foot__legal ')]")->count(),
            'la tira legal ha perdido su envoltorio: la vela viajaría con el contenido al deslizar',
        );

        $carril = $this->ruleBody('.foot__links, .foot__legal');

        $this->assertStringContainsString('overflow-x: auto', $carril, 'la tira legal ya no es un carril');
        $this->assertStringContainsString('flex-wrap: nowrap', $carril, 'la tira legal puede volver a envolver');

        foreach ($this->siteRules() as $regla) {
            if (! str_contains($regla['selector'], '.foot__legal')) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/flex-wrap\s*:\s*wrap/',
                $regla['body'],
                'una regla devuelve el `flex-wrap: wrap` al bloque legal ('.$regla['selector'].'): volvería a tres renglones de 14 px',
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Apoyos
    // ─────────────────────────────────────────────────────────────────────────────────

    /** El cuerpo de la regla cuyo selector cita exactamente `$selector` (uno de la lista basta). */
    private function ruleBody(string $selector): string
    {
        $body = '';

        foreach ($this->siteRules() as $regla) {
            $partes = array_map('trim', explode(',', $regla['selector']));

            if (in_array($selector, $partes, true) || $regla['selector'] === $selector) {
                $body .= $regla['body']."\n";
            }
        }

        $this->assertNotSame('', $body, "no existe ninguna regla para `$selector`");

        return $body;
    }

    /** @return list<Element> */
    private function nodes(string $html, string $class): array
    {
        $out = [];

        foreach ($this->xpath($html)->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' ".$class." ')]") as $node) {
            $out[] = $node;
        }

        return $out;
    }

    /**
     * ⚠️⚠️ **`Dom\HTMLDocument` devuelve los nombres de etiqueta en MAYÚSCULAS y XPath distingue
     * el caso**: `//p` y `//a` dan CERO con el documento entero delante, sin error y sin aviso.
     * Por eso aquí solo se consulta por `//*` y por clase; para bajar a una etiqueta concreta se
     * usa `getElementsByTagName()`, que sí es insensible. Costó un caso en verde vigilando el
     * vacío, que es la forma en que estas guardas mienten.
     */
    private function xpath(string $html): XPath
    {
        // Los atributos de Alpine (`@click`, `:class`) no son nombres válidos para el parser.
        $limpio = (string) preg_replace('/\s(@|:|x-on:|x-bind:)([a-zA-Z0-9_.\-]+)=/', ' data-alpine-$2=', $html);

        return new XPath(HTMLDocument::createFromString($limpio, LIBXML_NOERROR, 'UTF-8'));
    }
}
