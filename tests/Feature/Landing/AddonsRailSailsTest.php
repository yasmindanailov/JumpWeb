<?php

namespace Tests\Feature\Landing;

use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LAS VELAS DEL CARRIL DE COMPLEMENTOS Y EL PIE DE CUMPLEAÑOS** (`DECISIONES #498`).
 *
 * `[DECIDIDO owner, 2026-09-10]`: con muchos complementos el carril llega a un ancho amplio y hay
 * que decir que sigue **por los dos lados**; y bajo las tarjetas de cumpleaños había **tres bloques
 * de texto seguidos** —los días de la especial, las edades mezcladas y la cabecera del carril— sin
 * aire y con ruido: *«algo debe irse y dejarlo para la página de cumpleaños»*.
 *
 * ❗❗ **Lo que esta guarda NO puede hacer, y hay que saberlo**: no mide píxeles. Que la vela exista
 * no demuestra que se vea donde toca — eso solo lo dice una sonda en navegador, y ahí se cazaron los
 * dos defectos de esta tanda (la timeline que no encontraba su eje y el envoltorio desalineado del
 * carril). Lo que aquí se fija es lo que la sonda **no** puede vigilar en cada push: que el
 * mecanismo siga siendo el que se decidió.
 */
class AddonsRailSailsTest extends TestCase
{
    use RefreshDatabase;

    private const CSS = 'public/css/landing.css';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    /** **Guarda de la guarda**: que el escáner lea de verdad sus dos fuentes. */
    public function test_the_scan_reads_its_sources(): void
    {
        $this->assertStringContainsString('addons-rail__wrap', $this->css(),
            'el CSS ya no conoce el envoltorio del carril: esta guarda miraría el vacío');
        $this->assertStringContainsString('addons-rail__wrap', $this->home(),
            'la portada ya no pinta el envoltorio del carril');
    }

    /**
     * ❗❗❗ **LAS VELAS VAN EN EL ENVOLTORIO, NUNCA EN EL CARRIL.**
     *
     * Un pseudo-elemento dentro de un contenedor con scroll **viaja con el contenido** y se iría
     * hacia la izquierda en cuanto alguien deslizara. Es la misma razón por la que la vela del pie
     * vive en `.foot__links-wrap` y no en la fila (`#252`).
     */
    public function test_the_sails_hang_from_the_wrapper_and_not_from_the_track(): void
    {
        $css = $this->css();

        foreach (['.addons-rail__wrap::before', '.addons-rail__wrap::after'] as $sel) {
            $this->assertStringContainsString($sel, $css, "falta la vela `{$sel}`");
        }

        $this->assertDoesNotMatchRegularExpression('/\.addons-rail__track::(before|after)\s*[,{]/', $css,
            'Una vela cuelga del CARRIL, que es el elemento con scroll: viajaría con el contenido.');
    }

    /**
     * ⚠️⚠️ **LOS DEFECTOS DE LAS DOS VELAS SON OPUESTOS, y no es un descuido.**
     *
     * Donde el navegador no entienda `animation-timeline`, la de la DERECHA se queda puesta —el
     * estado seguro: una lista que parece terminada sin estarlo esconde contenido— y la de la
     * IZQUIERDA apagada, porque al principio del carril no hay nada a la izquierda y anunciarlo
     * sería falso.
     */
    public function test_the_two_sails_fail_in_opposite_directions(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.addons-rail__wrap::before\s*\{[^}]*opacity:\s*0\s*;/s', $this->css(),
            'la vela IZQUIERDA no nace apagada: sin JavaScript anunciaría contenido a la izquierda '.
            'estando al principio del carril.');

        $this->assertMatchesRegularExpression(
            '/\.addons-rail__wrap::after\s*\{[^}]*opacity:\s*1\s*;/s', $this->css(),
            'la vela DERECHA no nace puesta: su defecto seguro es decir que hay más.');
    }

    /**
     * ❗❗❗ **SIN SCROLL NO HAY VELAS, y eso es lo único que el CSS no puede saber solo.**
     *
     * Sin desbordamiento la timeline no tiene rango y la animación se queda en su fotograma inicial:
     * la vela derecha aparecería **sin nada detrás**. Medido en navegador: con dos complementos el
     * carril de tarifas cabe entero en escritorio (desborde 0) y desborda 330 px en móvil — depende
     * del ANCHO y no del número de fichas, así que **tampoco se puede decidir en el servidor**.
     */
    public function test_without_scroll_there_are_no_sails(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.addons-rail__wrap:not\(\[data-rail-scroll\]\)::(before|after)/', $this->css(),
            'Nada apaga las velas cuando el carril cabe entero: saldría una vela sin contenido detrás.');
    }

    /**
     * ⚠️ **El módulo publica un HECHO y no decide diseño** (la doctrina de `#195`): pone el
     * atributo, y el CSS decide. Si los números vivieran en el JavaScript serían la única parte del
     * tema que un cliente no puede tocar.
     */
    public function test_the_module_only_publishes_a_fact(): void
    {
        $js = (string) file_get_contents(base_path('resources/js/ui/rail-sails.js'));

        $this->assertStringContainsString('data-rail-scroll', $js, 'el módulo no publica la marca');

        foreach (['opacity', 'background', 'linear-gradient', 'style.'] as $pinta) {
            $this->assertStringNotContainsString($pinta, $js,
                "`rail-sails.js` escribe `{$pinta}`: el módulo decide diseño en vez de publicar un hecho.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El pie de la sección de cumpleaños
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **LA NOTA DE EDADES MEZCLADAS SE MOVIÓ A `/cumpleanos`; NO SE BORRÓ.**
     *
     * ⚠️⚠️ Medido antes de tocarla: esa frase **solo existía en la portada**, así que quitarla sin
     * más la habría hecho desaparecer del sitio entero — y el suplemento mixto **cobra dinero**
     * (`specs/cumple-mixto.md`). Quien lleve niños de dos edades tiene que enterarse en alguna parte.
     */
    public function test_the_mixed_age_note_moved_to_the_page_instead_of_vanishing(): void
    {
        $this->assertStringNotContainsString(__('landing.events.mixed_note'), $this->home(),
            'La nota de edades mezcladas sigue en la portada, donde era el tercero de tres bloques '.
            'de texto seguidos bajo las tarjetas.');

        $this->assertStringContainsString(__('landing.events.mixed_note'),
            (string) $this->get('/cumpleanos')->assertOk()->getContent(),
            "La nota de edades mezcladas NO está en `/cumpleanos`.\n".
            '▶ Se movió, no se borró: era el único sitio del sitio que lo decía.');
    }

    /**
     * **La nota de los días SÍ se queda en la portada**, y con aire.
     *
     * ⚠️ Sin ella «16,95 € en tarifa especial» no significa nada: el término hay que definirlo una
     * vez y en el sitio donde se usa (`#479`). El margen lo pone el CONTEXTO —`.rates__note` nace
     * con 4 px porque en tarifas va pegada a su carril—, y ésa era la queja literal del owner.
     */
    public function test_the_special_days_note_stays_on_the_home_with_room(): void
    {
        $this->assertStringContainsString('rates__note', $this->home(),
            'la nota de los días de la tarifa especial ha desaparecido de la portada');

        $this->assertMatchesRegularExpression('/#events\s+\.rates__note\s*\{[^}]*margin-top:/', $this->css(),
            "La nota bajo las tarjetas de cumpleaños ha vuelto a quedarse sin aire propio.\n".
            '▶ `.rates__note` nace con 4 px, que es lo correcto en tarifas y la deja colgando aquí.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    private function home(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    private function css(): string
    {
        return (string) file_get_contents(base_path(self::CSS));
    }
}
