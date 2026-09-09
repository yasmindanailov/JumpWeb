<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
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

    /** El molde del canvas: rótulo, titular y la regla, en ese orden. */
    public function test_the_section_follows_the_canvas_frame(): void
    {
        $seccion = $this->seccion();

        foreach (['zones__eyebrow', 'zones__title', 'zones__rule'] as $pieza) {
            $this->assertStringContainsString($pieza, $seccion, "falta `{$pieza}` en la cabecera de la sección");
        }

        $rotulo = strpos($seccion, 'zones__eyebrow');
        $titular = strpos($seccion, 'zones__title');
        $regla = strpos($seccion, 'zones__rule');

        $this->assertTrue(
            $rotulo < $titular && $titular < $regla,
            'el orden de la cabecera no es rótulo → titular → regla, que es el molde del sistema.',
        );
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
     * **La tarjeta lleva a la TARIFA de su zona**, que es el eslabón siguiente del recorrido.
     *
     * ⚠️ No lleva al carrusel de atracciones: eso sería mandar a elegir zona otra vez, que es el
     * defecto que `#295` midió y por el que las tarjetas se retiraron en su día.
     */
    public function test_the_card_leads_to_the_price_of_its_zone(): void
    {
        $seccion = $this->seccion();

        $this->assertMatchesRegularExpression(
            '#href="[^"]*'.preg_quote(parse_url(route('precios'), PHP_URL_PATH) ?? '/precios', '#').'\#zona-[a-z0-9-]+"#',
            $seccion,
            'la tarjeta de zona no lleva a la tarifa de esa zona.',
        );
    }

    /**
     * **Las tarjetas alternan SUPERFICIE, no color de zona.**
     *
     * ⚠️⚠️ Pintarlas con la paleta de la zona sería la **grieta 01** que el propio canvas nos
     * reportó: un color que llega desde los DATOS decidiendo el aspecto de un componente. La
     * alternancia papel/tinta es un mecanismo del producto y funciona con dos zonas o con cinco.
     */
    public function test_the_cards_alternate_surface_and_do_not_use_the_zone_palette(): void
    {
        $seccion = $this->seccion();

        $this->assertStringContainsString(
            'data-surface="ink"', $seccion,
            'las tarjetas ya no alternan superficie: el contraste del mockup lo da eso.',
        );

        $this->assertStringNotContainsString(
            '--zone-', $seccion,
            'la sección volvió a pintarse con la paleta de una zona, que es la grieta 01 del canvas: '.
            'un dato decidiendo el aspecto de un componente.',
        );
    }
}
