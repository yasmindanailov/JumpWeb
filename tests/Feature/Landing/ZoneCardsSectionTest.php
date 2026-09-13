<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LA SECCIÓN «PARA QUIÉN»: DOS TARJETAS DE ZONA, TODO DESDE LA BD**
 * (`docs/specs/rediseno-desde-canvas.md` §5.4 · T2b · `DECISIONES #478`).
 *
 * Es la primera sección de la portada y la que abre el recorrido: presenta las zonas, dice la regla
 * del parque —manda la edad, la altura desempata— y lleva a la tarifa de la zona elegida.
 *
 * ⚠️⚠️ **Lo que de verdad protege esto es que NADA esté escrito en la plantilla.** Nombre,
 * descripción, edad, altura y precio salen de la BD, y la tarjeta se adapta a lo que falte. Un
 * literal aquí no rompe esta instalación —sigue diciendo lo mismo— y **rompe la siguiente**, que es
 * el modo de fallo que `DECISIONES #1` existe para evitar: el repo es el producto, sin marca.
 */
class ZoneCardsSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        app()->setLocale('es');
    }

    private function seccion(): string
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<section id="zones"', $html, 'la portada perdió la sección `#zones`');
        preg_match('#<section id="zones".*?</section>#s', $html, $m);
        $this->assertNotEmpty($m, 'no encuentro dónde acaba la sección');

        return $m[0];
    }

    /** **CONTROL**: el recorte tiene tarjetas y no está vacío. */
    public function test_the_probe_frames_the_cards(): void
    {
        $seccion = $this->seccion();

        $this->assertGreaterThan(
            1, substr_count($seccion, 'class="zone-card"'),
            'la sección no pinta dos tarjetas de zona: lo de abajo miraría al vacío.',
        );
    }

    /**
     * El molde: rótulo y titular, en ese orden, y SIN regla debajo (`#587`, `[DECIDIDO owner]`: repetía
     * lo que ya dicen las tarjetas).
     */
    public function test_the_section_follows_the_canvas_frame(): void
    {
        $seccion = $this->seccion();

        foreach (['zones__eyebrow', 'zones__title'] as $pieza) {
            $this->assertStringContainsString($pieza, $seccion, "falta `{$pieza}` en la cabecera de la sección");
        }

        $this->assertTrue(
            strpos($seccion, 'zones__eyebrow') < strpos($seccion, 'zones__title'),
            'el orden de la cabecera no es rótulo → titular, que es el molde del sistema.',
        );
        $this->assertStringNotContainsString('zones__rule', $seccion, 'vuelve la regla bajo el titular');
    }

    /**
     * **La nota de acceso va en una tarjeta DEBAJO de las zonas, y la escribe el panel** (`#587`).
     *
     * ⚠️ Sin nota no hay tarjeta: es un dato del parque y el producto no tiene respaldo que inventar.
     */
    public function test_the_access_note_sits_under_the_cards_and_comes_from_the_panel(): void
    {
        $this->assertStringNotContainsString('zones__access', $this->seccion(), 'sin nota en el panel no hay tarjeta');

        $nota = 'Los menores de 4 entran en Kids con un adulto si miden más de 90 cm.';
        Setting::updateOrCreate(['key' => 'landing.zones_access.es'], ['value' => $nota, 'group' => 'landing']);

        $seccion = $this->seccion();
        $this->assertStringContainsString('<p class="zones__access-text">'.e($nota).'</p>', $seccion);
        // Con la MISMA «i» de información que `/cumpleanos` (`#589`).
        $this->assertMatchesRegularExpression('#<aside class="zones__access">\s*<span class="party-info__mark" aria-hidden="true">i</span>#', $seccion);
        $this->assertGreaterThan(
            strrpos($seccion, 'class="zone-card"'), strpos($seccion, 'zones__access'),
            'la nota de acceso no va debajo de las tarjetas',
        );
    }

    /** **La edad y la altura viven solo en el SELLO**, no repetidas bajo el nombre (`#587`). */
    public function test_age_and_height_are_not_repeated_in_the_card_body(): void
    {
        $this->assertStringNotContainsString('zone-card__fact', $this->seccion());
    }

    /**
     * **La tarjeta ENTERA es el enlace y no lleva botón dentro.**
     *
     * ⚠️ No es estilo: un control dentro de un `<a>` es marcado inválido y da dos dianas para el
     * mismo destino. Es la decisión del marco aprobado del canvas.
     */
    public function test_the_whole_card_is_the_link_and_carries_no_button(): void
    {
        $seccion = $this->seccion();

        $this->assertMatchesRegularExpression(
            '/<a class="zone-card"/', $seccion,
            'la tarjeta de zona dejó de ser un enlace.',
        );

        preg_match_all('#<a class="zone-card".*?</a>#s', $seccion, $tarjetas);
        $this->assertNotEmpty($tarjetas[0], 'no se han podido acotar las tarjetas');

        foreach ($tarjetas[0] as $tarjeta) {
            $this->assertStringNotContainsString(
                '<button', $tarjeta,
                'hay un botón DENTRO de la tarjeta-enlace: marcado inválido y dos dianas para el '.
                'mismo sitio.',
            );
        }
    }

    /**
     * **Todo el contenido sale de la BD.**
     *
     * Se cambia el nombre de una zona y la sección tiene que decirlo. Es la comprobación que
     * distingue una sección data-driven de una plantilla con el texto de este cliente dentro.
     */
    public function test_every_word_of_the_card_comes_from_the_database(): void
    {
        $zona = Zone::where('show_in_landing', true)->orderBy('position')->firstOrFail();
        $zona->forceFill(['name' => ['es' => 'ZONA DE PRUEBA', 'en' => 'X', 'fr' => 'X']])->save();

        preg_match_all('#<h3 class="zone-card__name">(.*?)</h3>#s', $this->seccion(), $nombres);
        $this->assertNotEmpty($nombres[1], 'no se encuentra el nombre de ninguna tarjeta');

        // ⚠️⚠️ **Se compara el contenido EXACTO, no «contiene», y lo exigió la mutación**: con
        // `assertStringContainsString` la guarda pasaba en verde aunque la plantilla escribiera
        // «KIDS» delante del nombre de la BD — el dato seguía apareciendo, con un literal de este
        // cliente pegado. *Que el dato salga no es que salga SOLO el dato.*
        $this->assertContains(
            'ZONA DE PRUEBA', array_map('trim', $nombres[1]),
            'el nombre de la tarjeta no es exactamente el de la BD: hay texto escrito en la '.
            'plantilla, y eso es la marca de un cliente dentro del producto.',
        );
    }

    /**
     * **La regla de altura se DIBUJA desde el número, y sin número no se pinta.**
     *
     * ⚠️⚠️ Éste es el caso que justifica la columna nueva: «hasta 1,30 m» y «desde 1,30 m» dicen lo
     * CONTRARIO con la misma cifra, así que el sentido tiene que salir de qué columna la lleva y no
     * de adivinar qué zona es. Y **vacío es una respuesta**: una instalación que no restrinja por
     * altura no pinta regla, en vez de escribir un cero.
     */
    public function test_the_height_rule_is_drawn_from_the_number_and_omitted_without_it(): void
    {
        $zona = Zone::where('show_in_landing', true)->orderBy('position')->firstOrFail();

        $zona->forceFill(['height_min_cm' => null, 'height_max_cm' => null])->save();
        $this->assertStringNotContainsString(
            'zone-card__height', $this->seccion(),
            'sin altura en la BD la tarjeta pinta una regla igualmente: vacío tiene que ser una '.
            'respuesta, no un cero.',
        );

        $zona->forceFill(['height_max_cm' => 130])->save();
        $this->assertStringContainsString(
            (string) __('landing.zones.height_up_to', ['h' => '1,30']), $this->seccion(),
            'un tope de altura tiene que leerse como «hasta».',
        );

        $zona->forceFill(['height_max_cm' => null, 'height_min_cm' => 130])->save();
        $this->assertStringContainsString(
            (string) __('landing.zones.height_from', ['h' => '1,30']), $this->seccion(),
            'un mínimo de altura tiene que leerse como «desde» — la MISMA cifra, el sentido '.
            'contrario. Si las dos columnas dan la misma frase, el dato no significa nada.',
        );
    }

    /**
     * **Sin entrada vendible, la tarjeta no inventa un precio.**
     *
     * El sello es de escaparate: sin producto detrás, no se pinta un «consultar» ni un cero. Es la
     * misma regla que el resto de la landing aplica a los packs no vendibles.
     */
    public function test_a_zone_without_a_sellable_ticket_shows_no_price(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        preg_match_all('#<a class="zone-card".*?</a>#s', $html, $tarjetas);

        foreach ($tarjetas[0] as $tarjeta) {
            if (! str_contains($tarjeta, 'zone-card__seal')) {
                $this->assertStringNotContainsString('€', $tarjeta, 'una tarjeta sin sello pinta un importe suelto');
            }
        }

        $this->assertNotEmpty($tarjetas[0]);
    }

    /**
     * **La tarjeta lleva a las ATRACCIONES de su zona, con la zona ya elegida** (`#586`).
     *
     * ⚠️ Hasta entonces apuntaba a `/precios#zona-<slug>`, un ancla que esa página no emite: se
     * aterrizaba arriba de la tabla. La zona viaja en `?zona=`, nunca en el hash (`#481`), así que
     * no obliga a elegirla otra vez.
     */
    public function test_the_card_leads_to_the_attractions_of_its_zone(): void
    {
        $seccion = $this->seccion();

        $this->assertMatchesRegularExpression(
            '#href="[^"]*'.preg_quote(parse_url(route('atracciones'), PHP_URL_PATH) ?? '/atracciones', '#').'\?zona=[a-z0-9-]+"#',
            $seccion,
            'la tarjeta de zona no lleva a las atracciones de esa zona.',
        );
        $this->assertStringNotContainsString('#zona-', $seccion);
    }

    /**
     * **Cada zona tiñe SU velo con SU color, y el color no sale de ningún control.**
     *
     * ⚠️⚠️ **Esta guarda afirmaba lo contrario y estaba equivocada.** La primera versión hacía
     * alternar papel/tinta e impedía todo uso del color de zona, «por la grieta 01». Al leer el
     * artboard resultó que **el mockup sí tiñe con el color de la zona** —un velo al 24 % detrás del
     * nombre— y que eso es exactamente para lo que `#436` dejó `--zone-*`: *«lo que IDENTIFICA una
     * zona»*. La grieta 01 era otra cosa: el **botón de comprar** pintado con la paleta de un dato.
     * ▶ Lo que se vigila, entonces, es la frontera correcta: el color de zona puede estar en el
     * VELO y no puede estar en el enlace ni en ningún control.
     *
     * ⚠️ Y el color sale de `zones.color`, no de un token `--zone-N`: ésos los reparte el JS por
     * posición, y las dos tarjetas caían al mismo fallback — medido, el velo salía cian en las dos.
     */
    public function test_each_zone_tints_its_own_veil_and_no_control(): void
    {
        $seccion = $this->seccion();

        preg_match_all('/--zone-tint:\s*(#[0-9a-f]{3,6})/i', $seccion, $tintes);
        $this->assertGreaterThan(
            1, count($tintes[1]),
            'las tarjetas no traen su color de zona: el velo del mockup lo da eso.',
        );
        $this->assertSame(
            count($tintes[1]), count(array_unique($tintes[1])),
            'dos zonas comparten velo: el color sale de un token repartido por posición en vez de '.
            'del dato de cada zona, que es como salía cian en las dos.',
        );

        // El velo es lo ÚNICO teñido: la afordancia de destino lee el rol de enlace.
        $this->assertMatchesRegularExpression(
            '/\.zone-card__go\s*\{[^}]*color:\s*var\(--interactive\)/s',
            (string) file_get_contents(base_path('public/css/landing.css')),
            'el enlace de la tarjeta dejó de leer `--interactive`. Pintarlo con el color de la zona '.
            'sería la grieta 01 del canvas: un dato decidiendo el aspecto de un control.',
        );
    }

    /**
     * **La tarjeta trae las cuatro piezas del mockup**: foto, sello, eje de altura y frontera.
     *
     * ⚠️ La primera versión de esta sección tenía solo texto, y «se parecía» sin serlo. Éstas son
     * las piezas que la hacen la del artboard, y cada una se cae sola si su dato falta.
     */
    public function test_the_card_carries_the_pieces_of_the_mockup(): void
    {
        // ⚠️ **El eje y la frontera necesitan SUJETO**: sin altura en la BD no se pintan —y eso es
        // correcto—, así que sin sembrarla esta guarda comprobaría que no está lo que no puede
        // estar. Es el caso «un test sin sujeto no vigila nada» de `#302`.
        $zonas = Zone::where('show_in_landing', true)->orderBy('position')->take(2)->get();
        $zonas[0]->forceFill(['height_min_cm' => 130])->save();
        $zonas[1]?->forceFill(['height_max_cm' => 130])->save();

        $seccion = $this->seccion();

        foreach ([
            'zone-card__viz' => 'el hueco de la foto a 16:9',
            'zone-card__seal' => 'el sello de precio',
            'zone-card__axis' => 'el eje de altura',
            'zone-card__border' => 'la frontera con su chapa',
        ] as $clase => $que) {
            $this->assertStringContainsString($clase, $seccion, "falta {$que} en la tarjeta.");
        }
    }

    /**
     * **Las cuatro reglas de geometría que costaron cuatro rondas con el owner delante.**
     *
     * ⚠️⚠️ Ninguna se ve leyendo el código y ninguna rompe nada: la tarjeta sigue pintándose. Se
     * encontraron **comparando contra el artboard renderizado** (`scripts/comparar-con-mockup.mjs`),
     * y por eso se fijan aquí — el siguiente que toque esta sección no va a repetir esa comparación.
     */
    public function test_the_geometry_rules_that_the_mockup_comparison_pinned_down(): void
    {
        $css = (string) file_get_contents(base_path('public/css/landing.css'));

        // 1 · El velo cubre su bloque ENTERO. Con un margen negativo se salía 74 px de la tarjeta;
        //     con el sangrado del eje encima, empezaba en 90 y dejaba de ser el tramo de la escala.
        $this->assertMatchesRegularExpression(
            // ⚠️ El `;` final NO sobra: sin él, `inset: 0 0 0 76px` —la forma que deja el velo sin
            //    llegar a la regla— casa igual, y la mutación lo demostró pasando en VERDE.
            '/\.zone-card__tint\s*\{[^}]*inset:\s*0\s*;/s', $css,
            'el velo dejó de cubrir su bloque entero: o se sale de la tarjeta, o no llega a la regla.',
        );

        // 2 · El bloque teñido NO crece. Con `flex: 1 1 auto` absorbía el sobrante de `stretch` y
        //     dejaba 68 px de color vacío bajo el texto.
        $this->assertMatchesRegularExpression(
            '/\.zone-card__text\s*\{\s*flex:\s*0 0 auto/s', $css,
            'el bloque teñido vuelve a crecer: el velo se estira bajo el texto y aparece color vacío.',
        );

        // 3 · El sangrado del eje va en cada PIEZA, nunca en el cuerpo: ahí arrastra al velo y a la
        //     línea, y las dos dejan de llegar al borde.
        $this->assertDoesNotMatchRegularExpression(
            '/\.zone-card__body\[data-axis\]\s*\{[^}]*padding-left/s', $css,
            'el sangrado del eje volvió al cuerpo: arrastra al velo y a la línea del 1,30, y las dos '.
            'dejan de llegar al borde de la tarjeta.',
        );

        // 4 · El hueco de la vecina es ASIMÉTRICO, y es del artboard: 60 arriba con 8 de margen
        //     antes de la línea, 56 abajo. Con 56 simétricos la chapa tapaba el rótulo del eje.
        $this->assertMatchesRegularExpression(
            '/data-side="below"\]\s*>\s*\.zone-card__neighbour\s*\{[^}]*min-height:\s*60px/s', $css,
            'el hueco de la vecina perdió su asimetría: cuando va ENCIMA mide 60 y deja 8 px antes '.
            'de la línea. Con 56 la frontera sube y la chapa del 1,30 tapa el «altura · 1,90 m».',
        );
    }

    /**
     * **La frontera CRUZA la regla vertical**, que es como el artboard la dibuja.
     *
     * ⚠️⚠️ Se comprobó **renderizando el marcado del propio mockup** y comparando la misma zona: su
     * línea de puntos es hija directa del cuerpo, sin sangrado, así que va de canto a canto y pasa
     * por debajo del eje, con la chapa encima de la intersección.
     * ▶ Hubo dos intentos de colocarla por porcentaje del umbral. El primero la separaba **124 px**
     * de la marca del eje en cuanto el texto no cabía en su tramo —la zona de «desde 1,30 m» tiene
     * el 31,6 % de la escala y su descripción ocupa el doble—; el segundo dejaba el hueco de la
     * vecina tan corto que **se montaba encima del CTA**. *Dos piezas que tienen que coincidir no se
     * calculan dos veces: se dibujan una dentro de la otra.*
     */
    public function test_the_border_line_crosses_the_vertical_rule(): void
    {
        Zone::where('show_in_landing', true)->orderBy('position')->firstOrFail()
            ->forceFill(['height_min_cm' => 130])->save();

        $css = (string) file_get_contents(base_path('public/css/landing.css'));

        // La frontera no lleva sangrado: si lo llevara, dejaría de tocar la regla.
        $this->assertMatchesRegularExpression(
            '/\.zone-card__border\s*\{(?:(?!padding)[^}])*\}/s', $css,
            'la frontera ganó sangrado: así ya no cruza la regla vertical, que es lo que el mockup '.
            'dibuja y lo que hace que la línea y la escala se lean como una sola cosa.',
        );

        // Y no vuelve una segunda marca calculada aparte, que es de donde salía la desalineación.
        $this->assertStringNotContainsString(
            '--axis-pct', $this->seccion(),
            'volvió el porcentaje del umbral: la frontera es la costura entre los dos bloques y no '.
            'necesita un segundo número que pueda discrepar del primero.',
        );
    }
}
