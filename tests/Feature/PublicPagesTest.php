<?php

namespace Tests\Feature;

use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    /**
     * ⚠️ **El nombre va SIN el prefijo de su zona desde `#531`**: la tabla dice la zona UNA vez en su
     * cabecera y cada fila escribe lo que la distingue («2 horas»). Por eso el caso asevera las dos
     * mitades y no la cadena entera del catálogo, que ya no se pinta junta en ninguna parte.
     */
    public function test_pricing_page_lists_tickets(): void
    {
        $html = $this->get('/precios')->assertOk()->getContent();

        $this->assertStringContainsString('JUMP', $html);      // la cabecera de su zona
        $this->assertStringContainsString('2 horas', $html);   // la fila de la entrada (ES)
    }

    public function test_pricing_page_respects_locale(): void
    {
        $this->get('/lang/en')->assertRedirect();

        $this->get('/precios')
            ->assertOk()
            ->assertSee('2 hours'); // entrada (EN)
    }

    public function test_events_page_shows_package(): void
    {
        $this->get('/cumpleanos')
            ->assertOk()
            ->assertSee('Mesa reservada para el grupo'); // feature del producto pack (ES, #87/3c)
    }

    public function test_the_invitation_editor_is_gone_from_every_surface(): void
    {
        /*
         * `#231` puso la tarjeta de invitación editable (`birthdayInvite` + `html2canvas`) en
         * `/cumpleanos`; `#483` le quitó el enlace de la portada y **`#528` la retiró** con la página
         * vieja (`[DECIDIDO owner]`: el artboard de la página no la trae). Si algún día vuelve, será
         * con su propio artboard — y será una decisión, no un resto.
         * ▶ Lo que SÍ se conserva en las dos superficies son los términos de reserva.
         */
        foreach (['/', '/cumpleanos'] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('birthdayInvite', false)
                ->assertDontSee('tarjeta-invitacion', false)
                ->assertDontSee('Descargar tarjeta')
                ->assertDontSee('¿Te gustaría crear tu invitación')
                // Solo el mínimo (`#586`, `[DECIDIDO owner]`): el máximo no se publica.
                ->assertSee('Desde 8 niños')
                ->assertDontSee('De 8 a 20 niños');
        }
    }

    public function test_the_step_by_step_is_gone_and_the_zone_photo_lives_only_on_its_page(): void
    {
        /*
         * El «paso a paso» (`birthdayProcess`) salió de la portada en `#483` y de su página en `#528`
         * (`[DECIDIDO owner]`: el artboard no lo trae). Eso no ha cambiado.
         *
         * ❗❗ **ESTE CASO CAMBIÓ DE PREMISA EN `#532` Y SE REESCRIBIÓ** (el precedente de `#324`):
         * hasta entonces aseveraba que la foto de la zona **no la pinta NADIE**, porque `#484` la
         * retiró de la portada —era el comedor vacío— y `#528` dejó su página sin fotos. El owner
         * decidió en `#532`, viéndola en vivo y con el rechazo de `#484` delante, **publicarla en
         * `/cumpleanos`**.
         *
         * ▶ Y por eso las dos superficies **dejan de ir en un bucle**: hoy dicen cosas distintas, y
         * meterlas en la misma vuelta era lo que hacía que una decisión sobre una tocara a la otra.
         *  · **PORTADA**: la foto sigue FUERA, y esa mitad es la que protege `#484` —*volver a la
         *    cabecera sobre foto tiene que ser una decisión, no el efecto lateral de subir una imagen
         *    al panel*—. `#532` retiró además el `partyImage` que la portada calculaba sin pintarlo.
         *  · **`/cumpleanos`**: la foto ESTÁ, y sale de `zones.image` (el campo del panel).
         * ⚠️ La aserción de la página se acota al bloque de la foto: la ruta suelta también viaja en
         * el `data-boot` del cajón y en el JSON-LD, así que buscarla en el documento entero pasaría
         * en verde con la figura retirada.
         */
        $this->get('/')->assertOk()
            ->assertDontSee('birthdayProcess', false)
            ->assertDontSee('Cómo se reserva')
            ->assertDontSee('images/attractions/cumplea_1.webp', false);

        $pagina = (string) $this->get('/cumpleanos')->assertOk()
            ->assertDontSee('birthdayProcess', false)
            ->assertDontSee('Cómo se reserva')
            ->getContent();

        preg_match('#<figure class="party-photo">.*?</figure>#s', $pagina, $figura);

        $this->assertNotEmpty($figura, 'la página de cumpleaños perdió la foto de la zona (`#532`)');
        $this->assertStringContainsString('images/attractions/cumplea_1.webp', $figura[0]);
    }

    public function test_footer_has_account_link_in_info(): void
    {
        // #231 p8: «Mi cuenta» en la columna Información del footer (Contacto vive en su columna).
        $this->get('/')->assertOk()
            // ⚠️ `assertSeeText` (`TESTING.md` §2.ter): «Mi cuenta» viaja desde el 2026-08-22 en el
            // `data-boot` del cajón. El `href` sí va con `assertSee`: es un fragmento de HTML, y eso
            // es justo lo que `assertSeeText` no puede ver.
            ->assertSeeText('Mi cuenta')
            ->assertSee(route('account'), false);
    }

    public function test_nav_and_footer_link_to_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(url('/precios'))
            ->assertSee(url('/cumpleanos'));
    }

    public function test_services_page_renders_with_all_anchors(): void
    {
        // /servicios (diseño v2 «Editorial XL»): una fila editorial por servicio del panel, cada una
        // con un anchor ESTABLE enlazado desde el nav. ⚠️ La banda «Otros eventos» se retiró (`#586`,
        // `[DECIDIDO owner]`: solo se ofrecen excursiones de colegio) y con ella su ancla `eventos`.
        $response = $this->get('/servicios')->assertOk();

        // Títulos de los servicios.
        $response->assertSee('Excursiones de colegio');
        $response->assertSee('Empresas');
        $response->assertSee('Excursión para mayores');
        $response->assertDontSee('Otros eventos');

        // Anchors HTML (contrato: el nav enlaza /servicios#xxx directamente) + índice del hero.
        $response->assertSee('id="excursionescolegio"', false);
        $response->assertSee('id="teambuilding"', false);
        $response->assertSee('id="sesionadultos"', false);
        $response->assertDontSee('id="eventos"', false);
        $response->assertSee('href="#excursionescolegio"', false); // el índice del hero apunta al anchor

        // Estructura del layout editorial: hero con índice y filas.
        // ⚠️ Aquí se aseveraba `svc-marquee` —la marquesina heredada del cliente antiguo—, y desde la T2
        // del idioma visual, `brand-band`, la cinta `C3` que la sustituyó. **Esa aserción se fue con la
        // vista** (`#660`): la cinta es ARTE de la landing de PlayJump, hoy en su instancia, y el producto
        // no puede exigirle a una instalación que pinte una decoración. Su garantía vive en
        // `paginas/servicios.md` del paquete y su CSS está declarado en
        // `InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA`.
        $response->assertSee('svc-hero__index', false);
        $response->assertDontSee('svc-marquee', false);
        $response->assertSee('svc-ed2__row', false);
        $response->assertDontSee('svc-other', false);

        // Kicker (etiqueta · zona, SIN número) + ficha de specs derivados de los datos i18n.
        $response->assertSee('Servicio · Kids + Jump');
        $response->assertSee('Kids + Jump');        // zona del servicio de colegios
        $response->assertSee('Parque completo');    // zona de empresas / adultos
        $response->assertSee('2 o 3 horas');        // spec value (colegios; antes «30 minutos», ahora coherente con la tabla 2h/3h)
        $response->assertSee('Mínimo 30 personas'); // spec value (empresas / adultos)

        // Enumeraciones «01/02» retiradas: el chip del índice ya no lleva número; la palabra
        // grande sobre la imagen es contextual (`word`), no un número.
        $response->assertDontSee('<span class="n">', false);
        $response->assertSee('Colegio'); // palabra de acento sobre la imagen (colegios)
        $response->assertSee('Noche');   // palabra de acento sobre la imagen (adultos)

        // Fotos reales de servicio (assets generales del parque, mapa por anchor + fallback hatch).
        $response->assertSee('class="svc-photo__img"', false);
        $response->assertSee('images/attractions/kids_zone.webp', false); // colegios (Kids + Jump)
        $response->assertSee('images/attractions/park_jump.webp', false); // empresas (parque completo)

        // Lógica conservada de v1: CTA a /contacto y SIN precios (reales [PENDIENTE] del cliente).
        $response->assertSee(route('contacto'), false);
        $response->assertSee('Pedir información');
        $response->assertDontSee('svc-price', false);

        // ⚠️⚠️ **El highlight `.blink` YA NO SE EMITE, y no porque se haya roto** (`#303`,
        // `[DECIDIDO owner]`: titulares a una línea y sin eyebrow). `.blink` es una pastilla con el
        // color de zona girada −2°, y existía para **destacar dos palabras dentro de una frase**
        // («Más allá del **salto libre**»). Con el titular en una sola palabra —«Servicios»— no hay
        // nada que destacar: envolverla entera convertiría el titular en una pastilla de color.
        // ▶ Por eso `services.title_accent` se deja VACÍO, que es la rama de degradación que el
        // propio hero ya tenía escrita («si no aparece, cae con elegancia al título plano»).
        $response->assertSee(__('services.title'));
        $response->assertDontSee('<span class="blink">', false);
    }

    /**
     * **Y el MECANISMO del highlight sigue vivo: se ejercita poniéndole un acento.**
     *
     * ⚠️ Sin este caso, vaciar `title_accent` habría dejado la rama del resaltado **sin cubrir** y
     * el caso de arriba pasaría igual con el mecanismo roto. No es código muerto: es un mecanismo
     * data-driven que ESTA instalación no usa, y otra con un título más largo sí puede usar.
     */
    public function test_the_services_hero_still_highlights_an_accent_when_there_is_one(): void
    {
        app('translator')->addLines([
            'services.title' => 'Más allá del salto libre',
            'services.title_accent' => 'salto libre',
        ], 'es');

        $this->get('/servicios')->assertOk()
            ->assertSee('<span class="blink">salto libre</span>', false);
    }

    public function test_services_page_respects_locale(): void
    {
        $this->get('/lang/en')->assertRedirect();

        $this->get('/servicios')
            ->assertOk()
            ->assertSee('School trips')   // sección (EN)
            ->assertSee('Services');      // título del hero (EN)
    }

    /**
     * **El marker `data-has-hero` solo lo lleva la portada.**
     *
     * Es lo que engancha la coreografía del armazón (`#216`): en la home el logotipo, el CTA y la
     * hamburguesa nacen ocultos y entran con el scroll; en las otras once salen desde el primer
     * píxel, que es lo correcto porque **no tienen hero que ofrezca la compra en su lugar**.
     *
     * ⚠️⚠️ **ACOTADO al ATRIBUTO DEL `<body>` en `#216`, y el caso anterior aseveraba por
     * SUBCADENA**: buscaba `data-has-hero` en TODO el HTML, así que cualquier mención de la
     * cadena —un comentario, una regla dentro de un `<style>`— lo ponía en rojo sin que el body
     * llevara nada. Lo destapó el `<noscript><style>` del suelo sin JavaScript, que **nombra el
     * selector** y sale en las doce vistas. Es la cuarta vez que esta casa paga la misma lección.
     */
    public function test_pages_without_hero_do_not_carry_has_hero_marker(): void
    {
        foreach (['/precios', '/cumpleanos', '/servicios', '/contacto', '/normas'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();

            preg_match('/<body\b[^>]*>/i', $html, $body);

            $this->assertNotEmpty($body, "no hay `<body>` en {$path}");
            $this->assertStringNotContainsString(
                'data-has-hero', (string) $body[0],
                "`{$path}` marca `data-has-hero` en el `<body>` y NO tiene hero:\n".
                '▶ su armazón nacería oculto y nada lo destaparía, porque no hay hero que dé el progreso.',
            );
        }

        // Y el contrapunto, sin el cual el caso pasaría con el marker retirado de TODAS partes.
        preg_match('/<body\b[^>]*>/i', (string) $this->get('/')->assertOk()->getContent(), $home);
        $this->assertStringContainsString('data-has-hero', (string) $home[0], 'la portada perdió su marker');
    }
}
