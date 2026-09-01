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

    public function test_pricing_page_lists_tickets(): void
    {
        $this->get('/precios')
            ->assertOk()
            ->assertSee('Jump · 2 horas'); // entrada sembrada (ES)
    }

    public function test_pricing_page_respects_locale(): void
    {
        $this->get('/lang/en')->assertRedirect();

        $this->get('/precios')
            ->assertOk()
            ->assertSee('Jump · 2 hours'); // entrada (EN)
    }

    public function test_events_page_shows_package(): void
    {
        $this->get('/cumpleanos')
            ->assertOk()
            ->assertSee('Mesa reservada para el grupo'); // feature del producto pack (ES, #87/3c)
    }

    public function test_birthday_invitation_card_only_on_events_page(): void
    {
        // #231: la tarjeta de invitación editable (birthdayInvite) ya NO va en la landing (para no
        // saturar) — solo en /cumpleanos (ancla #tarjeta-invitacion). En la landing hay un enlace
        // sutil a esa página. Los términos de reserva (#7, mín. 8) sí van en ambas (sección 1).
        $home = $this->get('/')->assertOk();
        $home->assertDontSee('birthdayInvite', false);          // sin tarjeta en la landing
        $home->assertDontSee('Descargar tarjeta');
        $home->assertSee('¿Te gustaría crear tu invitación personalizada?'); // CTA bajo la card (ES, #231)
        $home->assertSee('/cumpleanos#tarjeta-invitacion', false);
        $home->assertSee('De 8 a 20 niños');                    // términos de reserva (sección 1): mín–máx

        $events = $this->get('/cumpleanos')->assertOk();
        $events->assertSee('birthdayInvite', false);            // tarjeta editable en /cumpleanos
        $events->assertSee('Descargar tarjeta');
        $events->assertSee('id="tarjeta-invitacion"', false);   // ancla de destino del enlace
        $events->assertDontSee('¿Te gustaría crear tu invitación'); // el CTA no va aquí (está el editor)
        $events->assertSee('De 8 a 20 niños');
    }

    public function test_birthday_process_and_zone_photo_render(): void
    {
        // #231: la sección «Proceso» (5 pasos) y la foto de la zona cumpleaños (polaroid).
        foreach (['/', '/cumpleanos'] as $url) {
            $response = $this->get($url)->assertOk();
            $response->assertSee('birthdayProcess', false);                 // stepper Alpine (#231)
            $response->assertSee('Cómo se reserva');                        // eyebrow del proceso (ES)
            $response->assertSee('images/attractions/cumplea_1.webp', false); // foto de la zona cumpleaños
        }
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
        // /servicios (diseño v2 «Editorial XL») reúne 4 líneas comerciales: 3 servicios en
        // filas editoriales (excursiones, teambuilding, sesión adultos) + «otros eventos» como
        // banda catch-all final. Cada una conserva un anchor ESTABLE enlazado desde el nav.
        $response = $this->get('/servicios')->assertOk();

        // Títulos de los servicios.
        $response->assertSee('Excursiones de colegio');
        $response->assertSee('Empresas');
        $response->assertSee('Excursión para mayores');
        $response->assertSee('Otros eventos');

        // Anchors HTML (contrato: el nav enlaza /servicios#xxx directamente) + índice del hero.
        $response->assertSee('id="excursionescolegio"', false);
        $response->assertSee('id="teambuilding"', false);
        $response->assertSee('id="sesionadultos"', false);
        $response->assertSee('id="eventos"', false);
        $response->assertSee('href="#excursionescolegio"', false); // el índice del hero apunta al anchor

        // Estructura del layout editorial: hero con índice, la CINTA, filas y banda.
        // ⚠️ Aquí se aseveraba `svc-marquee`, la marquesina heredada del cliente antiguo. La
        // sustituye la cinta `C3` (T2 del idioma visual, 2026-08-31) y el contrato pasa a ser
        // `brand-band`: misma función —los títulos en bucle— y otra forma.
        $response->assertSee('svc-hero__index', false);
        $response->assertSee('brand-band', false);
        $response->assertDontSee('svc-marquee', false);
        $response->assertSee('svc-ed2__row', false);
        $response->assertSee('svc-other', false);

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
        $response->assertSee('Servicios');
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
