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
            ->assertSee('Mi cuenta')
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

        // Estructura del layout editorial (mockup v2): hero con índice, marquee, filas y banda.
        $response->assertSee('svc-hero__index', false);
        $response->assertSee('svc-marquee', false);
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

        // Highlight `.blink` del título del hero (fidelidad al mockup v2): el fragmento «salto
        // libre» se envuelve en `<span class="blink">`.
        $response->assertSee('<span class="blink">salto libre</span>', false);
    }

    public function test_services_page_respects_locale(): void
    {
        $this->get('/lang/en')->assertRedirect();

        $this->get('/servicios')
            ->assertOk()
            ->assertSee('School trips')                              // sección (EN)
            ->assertSee('Beyond ', false)                            // título plano (EN, antes del highlight)
            ->assertSee('<span class="blink">open jump</span>', false); // highlight del título (EN)
    }

    public function test_pages_without_hero_do_not_carry_has_hero_marker(): void
    {
        // El marker `data-has-hero` SOLO está presente en páginas con hero (home).
        // En `/precios`, `/cumpleanos`, `/servicios`, etc. el `.nav-cta-med` queda
        // visible siempre (sin observer ni reveal-on-scroll) — coherente con que
        // no hay un CTA "prime" alternativo en pantalla al cargar.
        foreach (['/precios', '/cumpleanos', '/servicios', '/contacto', '/normas'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('data-has-hero', false);
        }
    }
}
