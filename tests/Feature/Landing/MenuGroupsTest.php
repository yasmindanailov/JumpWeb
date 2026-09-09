<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL MENÚ EN DOS GRUPOS, Y EL ESLOGAN DEL CIERRE** (`DECISIONES #477`, carril de diseño Fase 2 ·
 * T2a · `specs/rediseno-desde-canvas.md`).
 *
 * El marco aprobado del canvas pide que el menú separe lo que te lleva **dentro de esta página** de
 * lo que te lleva a **otra**, y que el eslogan a rotulador viva en el cierre además de en el menú
 * —una vez por SUPERFICIE—. Las dos son `[DECIDIDO owner]` y las dos sustituyen a decisiones
 * anteriores suyas (`#211`, la lista plana).
 *
 * ⚠️⚠️ **Lo que de verdad protege esto es que el grupo se DEDUCE y no se guarda.** Un destino es
 * «sección» si apunta a la portada con ancla, y «página» en cualquier otro caso. Si algún día
 * alguien lo convierte en un campo de BD o del panel, habrá dos fuentes para el mismo hecho y
 * podrán decir cosas distintas — que es justo lo que este proyecto ha pagado ya con `accent`
 * (`#295`) y con la edad de la zona.
 *
 * ⚠️ **Y con la deducción se resuelven solas las DOS CRUCES que el canvas dejaba pendientes del
 * dueño**: Tarifas y Cumpleaños son sección Y página, pero el menú enlaza a su PÁGINA, así que caen
 * ahí sin que nadie elija. *La pregunta no se contesta: se disuelve al mirar a qué enlaza.*
 */
class MenuGroupsTest extends TestCase
{
    use RefreshDatabase;

    /** **CONTROL del localizador**: la portada trae menú y trae destinos. */
    public function test_the_probe_sees_a_menu_with_destinations(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertGreaterThan(
            2, substr_count((string) $html, 'class="menu__item"'),
            'el menú no publica destinos: las comprobaciones de abajo mirarían al vacío.',
        );
    }

    /** Los dos grupos existen, con su rótulo traducido y en el ORDEN del sistema. */
    public function test_the_menu_is_painted_in_two_groups_in_order(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $seccion = strpos($html, (string) __('landing.nav.menu_group.section'));
        $pagina = strpos($html, (string) __('landing.nav.menu_group.page'));

        $this->assertNotFalse($seccion, 'falta el grupo de destinos DENTRO de la portada');
        $this->assertNotFalse($pagina, 'falta el grupo de destinos que llevan a OTRA página');

        $this->assertLessThan(
            $pagina, $seccion,
            'el grupo de «otras páginas» sale ANTES que el de esta página. El orden es del sistema '.
            '—primero lo que tienes delante, luego lo que te saca de aquí— y no el que devuelva la '.
            'agrupación, que cambia con lo que haya en la BD.',
        );
    }

    /**
     * **El grupo se deduce de la URL**, y esto lo comprueba por conducta.
     *
     * Un destino con ancla de portada tiene que caer en el primer grupo y uno con ruta propia en el
     * segundo. Se mide por POSICIÓN en el documento, que es lo único que demuestra que están dentro
     * de la lista que les toca.
     */
    public function test_an_anchor_goes_to_the_first_group_and_a_route_to_the_second(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $corte = strpos($html, (string) __('landing.nav.menu_group.page'));
        $this->assertNotFalse($corte);

        $ancla = strpos($html, 'href="'.url('/#rides').'"');
        $ruta = strpos($html, 'href="'.route('cumpleanos').'"', $corte);

        $this->assertNotFalse($ancla, 'no se encuentra ningún destino con ancla de portada');
        $this->assertLessThan(
            $corte, $ancla,
            'un destino que lleva a una sección de ESTA página está cayendo en «otras páginas».',
        );

        $this->assertNotFalse(
            $ruta,
            'Cumpleaños no aparece en el grupo de «otras páginas». Es una de las dos CRUCES que el '.
            'canvas dejaba pendientes: es sección y página a la vez, y se resuelve por dónde enlaza '.
            'el menú de verdad — que es su página.',
        );
    }

    /**
     * **La flecha dice el grupo**: hacia abajo dentro de la página, hacia la derecha fuera de ella.
     *
     * ⚠️ Se asevera contra el dibujo y no contra una clase: la clase la emite el componente igual en
     * los dos casos, así que comprobarla pasaría en verde con las dos flechas iguales.
     */
    public function test_the_arrow_says_whether_you_leave_the_page(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $corte = strpos($html, (string) __('landing.nav.menu_group.page'));
        $arriba = substr($html, 0, (int) $corte);
        $abajo = substr($html, (int) $corte);

        $this->assertStringContainsString('menu__arrow', $arriba);
        $this->assertGreaterThan(
            0, substr_count($abajo, 'menu__arrow'),
            'el grupo de «otras páginas» se quedó sin flechas',
        );
    }

    /**
     * **El eslogan a rotulador está en el cierre**, y comparte clave con el del menú.
     *
     * ⚠️ La clave compartida no es comodidad: es el MISMO eslogan, y dos claves distintas invitan a
     * que un día digan cosas distintas sin que nada falle.
     */
    public function test_the_marker_slogan_is_in_the_closing_card(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            'class="reserve__slogan"', $html,
            'el cierre perdió su eslogan a rotulador (`[DECIDIDO owner]`, marco del canvas).',
        );

        $eslogan = (string) __('landing.hero.kicker');
        $enCierre = strpos($html, 'class="reserve__slogan">'.e($eslogan));

        $this->assertNotFalse(
            $enCierre,
            'el eslogan del cierre no dice lo mismo que el del menú: los dos leen '.
            '`landing.hero.kicker` porque son el mismo eslogan.',
        );
    }

    /**
     * **El eslogan del cierre NO se pinta como un párrafo del cierre.**
     *
     * ⚠️⚠️ Éste es el caso que lo motiva, y lo encontró el NAVEGADOR con la suite en verde: el
     * eslogan es un `<p>` dentro de `.reserve`, y la regla `.reserve p` tiene más especificidad
     * (0,1,1 contra 0,1,0), así que le imponía su talla de 16,5, el gris de `--fg-mute` y un margen
     * de 22 px. Se veía un párrafo cualquiera girado dos grados.
     * ▶ Aquí se asevera que la regla está ACOTADA al cierre, que es lo que le devuelve la prioridad.
     */
    public function test_the_closing_slogan_rule_outranks_the_paragraph_rule(): void
    {
        $css = (string) file_get_contents(base_path('public/css/site.css'));

        $this->assertMatchesRegularExpression(
            '/\.reserve\s+\.reserve__slogan\s*\{/',
            $css,
            'la regla del eslogan del cierre volvió a estar suelta (`.reserve__slogan`). Dentro de '.
            '`.reserve` hay un `.reserve p` con MÁS especificidad, así que el eslogan se pintaría '.
            'como un párrafo: 16,5 px y gris apagado, con la suite en verde.',
        );
    }

    /**
     * **El scroll vive en la COLUMNA, no en cada lista.**
     *
     * Con la lista plana daba igual; con dos grupos, dejarlo en `.menu__list` le da a cada uno su
     * barra y reparte el alto entre ambos, así que un grupo de dos destinos ocuparía media pantalla.
     */
    public function test_the_rail_scrolls_as_one_and_not_group_by_group(): void
    {
        $css = (string) file_get_contents(base_path('public/css/site.css'));

        preg_match('/\.menu__col-list\s*\{([^}]*)\}/', $css, $columna);
        $this->assertNotEmpty($columna, 'no se encuentra la regla de la columna del menú');
        $this->assertStringContainsString(
            'overflow-y: auto', $columna[1],
            'la columna del menú dejó de desplazarse como un todo: con dos grupos, el scroll tiene '.
            'que ser del carril entero o cada grupo se desplaza por su cuenta.',
        );

        preg_match('/(?<![-\w])\.menu__list\s*\{([^}]*)\}/', $css, $lista);
        $this->assertNotEmpty($lista, 'no se encuentra la regla de la lista del menú');
        $this->assertStringNotContainsString(
            'overflow-y: auto', $lista[1],
            'la lista recuperó su propio scroll: con dos grupos eso son dos barras y dos mitades.',
        );
    }
}
