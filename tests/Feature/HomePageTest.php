<?php

namespace Tests\Feature;

use App\Domain\Platform\Models\Setting;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Providers\AppServiceProvider;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        // Cache del precio mínimo de los CTAs (jerarquía nueva) limpio por test:
        // el driver `array` en pruebas comparte memoria entre tests del mismo run.
        Cache::flush();
    }

    public function test_home_loads_in_spanish(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Saltos libres');          // atracción real desde BD (ES)
        $response->assertSee('El parque');              // trigger del 1.º desplegable del nav (ES)
    }

    public function test_language_can_switch_to_english(): void
    {
        $this->get('/lang/en')->assertRedirect();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Free jump');              // atracción real desde BD (EN)
        $response->assertSee('The park');               // trigger del 1.º desplegable del nav (EN)
    }

    public function test_unsupported_locale_is_ignored(): void
    {
        $this->get('/lang/de'); // alemán no soportado → se ignora

        $this->get('/')->assertOk()->assertSee('Saltos libres');
    }

    public function test_home_exposes_seeded_content(): void
    {
        $response = $this->get('/');

        $response->assertSee('Mesa reservada para el grupo'); // feature del producto pack (ES, #87/3c)
        $response->assertSee('Jump · 2 horas');          // entrada destacada (ES)
        $response->assertSee('hola@saltopark.example');    // setting de contacto
    }

    public function test_landing_hides_a_non_sellable_pack_from_its_cta_section(): void
    {
        // Coherencia CTA⟺catálogo (#210): el CTA «Reservar ahora» de la landing dispara el
        // deep-link al catálogo del sidebar, que SOLO vende packs `sellable()->inOperationalZone()`.
        // La landing usa el MISMO predicado → no anuncia un pack que el flujo no puede vender (si lo
        // hiciera, el deep-link `show-packs` apuntaría a una sección «Servicios» inexistente). Un
        // pack `is_active=true` pero NO vendible queda fuera de la landing.
        TicketType::ofType(TicketType::TYPE_PACK)
            ->where('position', 10)            // «Cumpleaños Kids» (seed)
            ->update(['is_sellable' => false]);

        foreach (['/', '/cumpleanos'] as $url) {
            $this->get($url)
                ->assertSee('Acceso exclusivo a la zona Jump')       // pack vendible → se anuncia
                ->assertDontSee('Acceso exclusivo a la zona Kids');  // pack no vendible → fuera
        }
    }

    public function test_landing_hides_packs_whose_operational_zone_is_disabled(): void
    {
        // `inOperationalZone()` también en la landing: si la zona operativa de los packs se
        // desactiva, dejan de anunciarse (coherente con el catálogo, que tampoco los vende).
        Zone::where('slug', 'cumpleanos')->update(['is_active' => false]);

        $this->get('/')->assertDontSee('Acceso exclusivo a la zona Jump');
        $this->get('/cumpleanos')->assertDontSee('Acceso exclusivo a la zona Kids');
    }

    public function test_entry_cards_show_a_book_cta_and_call_fallback_when_not_sellable(): void
    {
        // #226 punto 5: cada card de entrada tiene un CTA. Las vendibles → «Reservar» (abre el
        // sidebar); las que NO tienen venta online (is_sellable=false) → fallback «Llamar» (tel:
        // del contacto), porque la entrada sigue apareciendo en la landing (filtro is_active).
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '968 22 22 22', 'group' => 'contact']);
        TicketType::ofType(TicketType::TYPE_ENTRY)->where('position', 1)->update(['is_sellable' => false]);

        $this->get('/')
            ->assertSee(__('landing.pricing.book'))   // CTA «Reservar» (entradas vendibles)
            ->assertSee(__('landing.pricing.call'))   // fallback «Llamar» (la no vendible)
            ->assertSee('tel:968222222', false);      // enlace de llamada con los dígitos del teléfono
    }

    public function test_socks_note_renders_under_the_price_grid(): void
    {
        // Nota general «calcetines antideslizantes obligatorios» bajo el grid de precios, con el
        // icono de marca S1. Vive en `ticket-prices` → aparece en la landing Y en /precios.
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Calcetines antideslizantes obligatorios')          // título (ES)
            ->assertSee('Puedes traerlos de casa', false)                    // el cliente los puede traer de casa
            ->assertSee('ic-s1', false)                                      // icono de marca S1 (animado, white-label)
            ->assertSee('socks-note__title', false);                        // callout reutilizable

        // La misma nota acompaña al catálogo en la página de tarifas dedicada (/precios).
        $this->get('/precios')->assertOk()->assertSee('Calcetines antideslizantes obligatorios');
    }

    public function test_socks_note_is_translated(): void
    {
        $this->get('/lang/en');
        $this->get('/')->assertSee('Non-slip socks required')->assertSee('Bring your own from home', false);

        $this->get('/lang/fr');
        $this->get('/')->assertSee('Chaussettes antidérapantes obligatoires')->assertSee('Apporte les tiennes', false);
    }

    public function test_home_includes_the_purchase_sidebar(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('sidecart', false)            // el sidebar de compra está en el layout
            ->assertSee('Reservas');                  // título del sidebar (ES, #216)
    }

    public function test_entradas_route_opens_the_sidebar(): void
    {
        // /entradas renderiza la home y marca el sidebar para abrirse (enlace profundo).
        $this->get('/entradas')
            ->assertOk()
            ->assertSee('data-purchase-open="1"', false);
    }

    public function test_home_does_not_open_sidebar_by_default(): void
    {
        // Regresión: sin enlace profundo ni outcome de Redsys, el sidebar arranca cerrado.
        $this->get('/')
            ->assertOk()
            ->assertSee('data-purchase-open=""', false);
    }

    public function test_home_opens_sidebar_when_redsys_confirmed_code_in_session(): void
    {
        // #106 (refinado tras validación visual 2026-05-26): tras la vuelta OK firmada de
        // Redsys, HomeController escribe `purchase.confirmed_code` en sesión y redirige a
        // la home. El layout debe abrir el sidebar (Alpine lee `data-purchase-open`); el
        // Purchase Livewire consume la flag en mount() y muestra el paso 6.
        $this->withSession(['purchase.confirmed_code' => 'JJ-ABC123'])
            ->get('/')
            ->assertOk()
            ->assertSee('data-purchase-open="1"', false);
    }

    public function test_home_opens_sidebar_when_redsys_failed_code_in_session(): void
    {
        // Mismo mecanismo para el outcome KO (paso 10 — pago denegado).
        $this->withSession(['purchase.failed_code' => 'JJ-DEF456'])
            ->get('/')
            ->assertOk()
            ->assertSee('data-purchase-open="1"', false);
    }

    public function test_home_opens_sidebar_when_redsys_verifying_code_in_session(): void
    {
        // Y para el outcome "data-less" del fallback #106 (paso 11 — verificando pago).
        $this->withSession(['purchase.verifying_code' => 'JJ-GHI789'])
            ->get('/')
            ->assertOk()
            ->assertSee('data-purchase-open="1"', false);
    }

    /* ====================================================================
       Jerarquía de CTAs (mockup `design_mockup/jerarquia-ctas.html`):
       blindamos el contrato visual + de comportamiento de los 3 pesos.
       ==================================================================== */

    public function test_guest_nav_renders_register_ghost_cta(): void
    {
        // Ghost CTA: solo guests; abre el modal de registro (#46, Fase 4.2).
        // Selector `nav-cta-ghost` + handler `$store.auth.open('register')` son contrato.
        $response = $this->get('/')->assertOk();

        $response->assertSee('nav-cta-ghost', false);
        $response->assertSee('cta-ghost__t', false);
        $response->assertSee("\$store.auth.open('register')", false);
        $response->assertSee('Registrarse'); // copy ES de `nav.reserve`
    }

    public function test_authenticated_nav_hides_ghost_and_shows_account_menu(): void
    {
        // Usuario con sesión: el ghost de "Registrarse" desaparece y emerge el
        // dropdown `nav__acct` (#79). El filled "Comprar entradas" permanece.
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/')->assertOk();

        $response->assertDontSee('nav-cta-ghost', false);
        $response->assertSee('nav__acct', false);
        $response->assertSee('nav-cta-med', false); // filled SÍ visible auth
    }

    public function test_nav_renders_buy_filled_cta_for_everyone(): void
    {
        // Filled CTA del header: visible para guest y auth. Abre el sidebar de
        // compra (#66). Copy "Comprar entradas" + anclaje "desde X,XX €" en el
        // subtítulo `.cta-med__s` (data-driven con el catálogo activo) — replica
        // el `.cta-med` del mockup `design_mockup/jerarquia-ctas.html`.
        $expected = AppServiceProvider::formatPriceLabel($this->cheapestEntryCents());

        $response = $this->get('/')->assertOk();

        $response->assertSee('nav-cta-med', false);
        $response->assertSee('cta-med__t', false);
        $response->assertSee('cta-med__s', false);
        $response->assertSee('cta-med__body', false);
        $response->assertSee('$store.purchase.open()', false);
        $response->assertSee('Reservas aquí');   // #216 «Reservar ahora» → #222 «Reservas aquí»
        $response->assertSeeText("desde {$expected}");
    }

    public function test_nav_cta_omits_price_subtitle_when_catalog_empty(): void
    {
        // Sin catálogo vendible, el view composer global pasa `ctaMinPriceLabel`
        // = null y la vista omite el subtítulo del nav (botón sigue clicable,
        // sin línea vacía de subtítulo). Mismo contrato que el hero (`cta_buy_no_price`).
        TicketType::query()->update(['is_sellable' => false]);
        Cache::flush();

        $response = $this->get('/')->assertOk();

        $response->assertSee('nav-cta-med', false);
        $response->assertSee('cta-med__t', false);
        $response->assertDontSee('cta-med__s', false); // sin subtítulo
    }

    public function test_hero_uses_cta_prime_structure_with_subtitle(): void
    {
        // Hero CTA "prime" (peso 5/5): variante `--onvideo` para destacar sobre
        // el vídeo de fondo. Estructura icono + body (t + s) + arrow. Subtítulo con
        // el precio "desde X" cuando hay catálogo vendible (copy `hero.cta_buy_from`;
        // se retiró "sin colas" — claridad clienta 2026-06-13).
        $expected = AppServiceProvider::formatPriceLabel($this->cheapestEntryCents());

        $response = $this->get('/')->assertOk();

        $response->assertSee('cta-prime', false);
        $response->assertSee('cta-prime--onvideo', false);
        $response->assertSee('cta-prime__t', false);
        $response->assertSee('cta-prime__s', false);
        $response->assertSee('cta-prime__arrow', false);
        $response->assertSeeText("desde {$expected}");
    }

    public function test_hero_cta_falls_back_to_no_price_subtitle_when_catalog_empty(): void
    {
        // Sin entradas vendibles (estado real hoy): el hero SIGUE mostrando subtítulo
        // (la clase `cta-prime__s` permanece) con el fallback "Cumpleaños online" (copy
        // `hero.cta_buy_no_price`) — honesto: lo único reservable online hoy. El botón nunca
        // queda mudo y el CTA "Reservas aquí" se mantiene general (claridad clienta 2026-06-13).
        TicketType::query()->update(['is_sellable' => false]);
        Cache::flush();

        $response = $this->get('/')->assertOk();

        $response->assertSee('cta-prime', false);
        $response->assertSee('cta-prime__s', false);
        $response->assertSeeText('Cumpleaños online');
    }

    public function test_mobile_filled_renders_mobile_book_label(): void
    {
        // El CTA filled del nav lleva dos spans con clases `__t--desktop`/`__t--mobile`
        // controladas por @media (CSS decide cuál se ve). #231 p7: en móvil ahora también
        // es «Reservas aquí» (hay espacio tras ocultar «Hola, nombre»); ambas labels están
        // en el HTML.
        $response = $this->get('/')->assertOk();

        $response->assertSee('cta-med__t--desktop', false);
        $response->assertSee('cta-med__t--mobile', false);
        $response->assertSee('Reservas aquí'); // copy ES de `nav.cta_book` (#231 p7)
    }

    /* ====================================================================
       Reorganización del nav (2026-05-27): dos desplegables temáticos +
       dos atajos directos. Blindamos copy + URLs + estado Alpine.
       ==================================================================== */

    public function test_nav_renders_park_dropdown_with_anchor_items(): void
    {
        // Dropdown 1: "El parque" → 4 items que apuntan a anclas de la home.
        // Estado Alpine `parkOpen` controla la visibilidad del panel.
        $response = $this->get('/')->assertOk();

        $response->assertSee('El parque');
        $response->assertSee('parkOpen', false);
        $response->assertSee('Zona Kids');
        $response->assertSee('Zona Jump');
        $response->assertSeeInOrder(['Zona Kids', 'Zona Jump', 'Atracciones', 'Ubicación y horario']);
        // Las 3 primeras zonas apuntan al ancla #zones / #rides de la home;
        // "Ubicación y horario" apunta a #info.
        $response->assertSee('href="'.url('/#zones').'"', false);
        $response->assertSee('href="'.url('/#rides').'"', false);
        $response->assertSee('href="'.url('/#info').'"', false);
    }

    public function test_nav_renders_services_dropdown_with_section_links(): void
    {
        // Dropdown 2: "Servicios" → cumpleaños + 4 secciones de /servicios.
        // Estado Alpine `servicesOpen`.
        $response = $this->get('/')->assertOk();

        $response->assertSee('Servicios');
        $response->assertSee('servicesOpen', false);
        $response->assertSeeInOrder([
            'Cumpleaños', 'Excursiones de colegio', 'Empresas', 'Excursión para mayores', 'Otros eventos',
        ]);
        $response->assertSee(route('servicios').'#excursionescolegio', false);
        $response->assertSee(route('servicios').'#teambuilding', false);
        $response->assertSee(route('servicios').'#sesionadultos', false);
        $response->assertSee(route('servicios').'#eventos', false);
    }

    public function test_nav_renders_direct_links_for_birthdays_and_tickets(): void
    {
        // Atajos directos en el nav: "Cumpleaños" (también aparece dentro de
        // Servicios, por destacado intencional) y "Entradas" → /precios.
        $response = $this->get('/')->assertOk();

        $response->assertSee('Entradas');
        // El href "Cumpleaños" del atajo va a /cumpleanos (no a un anchor).
        $response->assertSee('href="'.route('cumpleanos').'"', false);
        $response->assertSee('href="'.route('precios').'"', false);
    }

    public function test_home_marks_body_with_data_has_hero(): void
    {
        // El marker `data-has-hero` indica al CSS que el `.nav-cta-med` debe
        // empezar oculto y al JS (`navCtaReveal`) que monte el IntersectionObserver
        // sobre el `.hero__stage-bottom`. Sin él, el CTA del nav queda visible
        // por defecto (otras páginas mantienen el atajo siempre accesible).
        $this->get('/')->assertOk()->assertSee('data-has-hero="1"', false);
    }

    public function test_nav_cta_med_carries_reveal_on_scroll_directive(): void
    {
        // El botón `.cta-med` del nav lleva `x-data="navCtaReveal"` — combinado
        // con el marker del body, dispara el observador del hero. Sin la
        // directiva no habría binding y el CSS dejaría el CTA oculto para
        // siempre en home (peor escenario evitado).
        $this->get('/')->assertOk()->assertSee('x-data="navCtaReveal"', false);
    }

    /* ====================================================================
       Auditoría Fase 1 · Sistema 6 (Landing/CMS público) — W1/W2/W4.
       ==================================================================== */

    public function test_anonymous_home_get_stays_within_query_budget(): void
    {
        // W1 (HIGH): el composer global `View::composer('*')` corre una vez por CADA subvista de la
        // home (~79) y antes reconstruía `$site` con ~20 `Setting::value` sin cachear → ~1.900 queries
        // por GET anónimo (amplificación DoS). Ahora memoiza el payload en `request()->attributes` y lee
        // toda la tabla `settings` UNA vez. Regresión: presupuesto holgado pero muy por debajo de lo roto.
        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $count = count($queries);
        $this->assertLessThan(
            120,
            $count,
            "GET / disparó {$count} queries — regresión de la amplificación del composer (esperado « 120; estaba en ~1.900)."
        );

        // El corazón del fix: la tabla `settings` se lee un puñado de veces por request (el pluck único
        // del composer + algunos helpers acotados: ThemeSettings, PuertaSettings, CookieConsent…), NO
        // ~20 por cada una de las ~79 subvistas (que daba ~1.600). Umbral holgado que distingue sin
        // ambigüedad el estado memoizado (~11) de la regresión de amplificación (cientos).
        $settingsReads = collect($queries)
            ->filter(fn (array $q): bool => str_contains(strtolower((string) $q['query']), 'settings'))
            ->count();
        $this->assertLessThan(
            50,
            $settingsReads,
            "El composer leyó la tabla `settings` {$settingsReads} veces — debe memoizar por petición (esperado « 50, estaba en ~1.600)."
        );
    }

    public function test_cta_min_price_excludes_entries_of_a_disabled_zone(): void
    {
        // W2 (medium): el «desde X €» del nav/hero usa la MISMA comprabilidad que el catálogo de compra
        // (`inOperationalZone()`). Con el seed: kids · 1h = 7,90 € (la más barata, zona kids); jump · 1h =
        // 9,90 € (zona jump). Si se desactiva la zona de la más barata, el ancla salta a la siguiente
        // entrada realmente comprable, no anuncia un precio que el flujo no puede vender.
        $this->get('/')->assertOk()->assertSeeText('desde 7,90 €'); // estado por defecto: kids manda

        Zone::where('slug', 'kids')->update(['is_active' => false]);
        Cache::flush(); // el update directo del modelo no pasa por EditZone::afterSave (eso es W3)

        $response = $this->get('/')->assertOk();
        $response->assertSeeText('desde 9,90 €');  // ahora manda jump (zona operativa)
        $response->assertDontSee('desde 7,90 €');  // ya NO se ancla al precio de la zona desactivada
    }

    public function test_landing_hides_entries_of_a_disabled_zone(): void
    {
        // W4 (low): las entradas de una zona desactivada no se pintan con CTA «Reservar» que el flujo de
        // compra no puede vender (espejo de packs e `inOperationalZone()`). La zona kids sigue en la
        // landing (`show_in_landing`), pero sus ENTRADAS desaparecen de la rejilla de precios.
        $this->get('/')->assertSee('Kids · 1 hora'); // estado por defecto: la entrada se anuncia

        Zone::where('slug', 'kids')->update(['is_active' => false]);

        foreach (['/', '/precios'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Jump · 1 hora')       // entrada de zona operativa: sigue
                ->assertDontSee('Kids · 1 hora');  // entrada de zona desactivada: fuera
        }
    }

    /**
     * Precio mínimo (céntimos) de entrada vendible en el catálogo actual.
     * Se calcula en vivo del seeder activo para que los tests sigan funcionando
     * si el cliente ajusta precios desde el panel admin sin invalidar las pruebas.
     */
    private function cheapestEntryCents(): int
    {
        return (int) TicketType::query()
            ->where('is_sellable', true)
            ->where('is_active', true)
            ->where('type', TicketType::TYPE_ENTRY)
            ->with('prices')
            ->get()
            ->flatMap(fn (TicketType $type) => $type->prices)
            ->where('amount_cents', '>', 0)
            ->min('amount_cents');
    }
}
