<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Models\LandingService;
use App\Domain\Content\Services\ThemeSettings;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F6 (#256) — /servicios data-driven: variante comprable (pack vinculado → precio + «Reservar»
 * + deep-link al sidebar) vs solo-contacto, degradación a 0 servicios, y nav data-driven.
 * (Los anchors/contenido editorial sembrado ya los cubre `PublicPagesTest`.)
 */
class ServicesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_a_linked_pack_shows_price_and_book_cta(): void
    {
        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();
        LandingService::create([
            'slug' => 'eventos-empresa',
            'title' => ['es' => 'Eventos de empresa'],
            'zone_label' => ['es' => 'Parque completo'],
            'ticket_type_id' => $pack->id,
            'position' => 0,
            'is_active' => true,
        ]);

        $res = $this->get('/servicios')->assertOk();
        $res->assertSee('Eventos de empresa');
        $res->assertSee('svc-ed2__price', false);          // muestra precio (pack comprable)
        $res->assertSee(__('landing.pricing.book'));        // CTA «Reservar»
        // Deep-link al sidebar de compra. Desde Fase 4 · paso 4.0a la landing declara la INTENCIÓN
        // en vez de despachar un evento de Livewire: el mismo destino, pero sin atar la página al
        // motor del cajón (con otro motor, aquel `dispatch` no fallaba — no hacía nada).
        // ▶ `#568`: la intención es ESTE pack, no la sección de packs — el cajón lo abre listo para
        // elegir día. Se asevera con su id, que es lo que distingue abrir el producto de abrir la lista.
        $res->assertSee("openWith({ type: 'product', id: {$pack->id} })", false);
    }

    public function test_contact_only_services_show_get_in_touch_without_price(): void
    {
        // Las 3 secciones sembradas son solo-contacto (sin pack).
        $this->get('/servicios')
            ->assertOk()
            ->assertSee('Pedir información')
            ->assertDontSee('svc-ed2__price', false);
    }

    public function test_colegio_service_renders_the_group_rate_table(): void
    {
        // El servicio «Excursiones de colegio» (sembrado) define `price_table` → la card muestra la
        // tabla de tarifas (pestañas de zona + precios), aunque siga siendo solo-contacto.
        $this->get('/servicios')
            ->assertOk()
            ->assertSee('svc-rates__table', false)         // el bloque de tarifas se renderiza
            ->assertSee(__('services.rates.title'))        // «Tarifas de grupo»
            // ⚠️ El tinte se aseveraba por el NOMBRE DE LA CLASE, que solo decía que la plantilla
            // escribió el acento. Desde `#138` el color llega en línea: se asevera EL COLOR de cada
            // zona, que es lo que se ve, y funciona con cualquier acento (antes solo con jump/kids).
            ->assertSee(ThemeSettings::zoneStyleForAccent('kids'), false)
            ->assertSee(ThemeSettings::zoneStyleForAccent('jump'), false)
            ->assertSee('Kids 2H')                         // caption «{zona} {N}H» (en mayúsculas por CSS)
            ->assertSee('Jump 3H')
            ->assertSee('10 €')                            // Kids · 2 h · L–V · 100 niños (precio mínimo, único)
            ->assertSee('20 €')                            // Jump · 3 h · finde · 30 niños (panel oculto, pero en el DOM)
            ->assertSee('30 niños')                        // unidad «niños» (colegio)
            ->assertSee('Pedir información');              // sigue siendo contact-only
    }

    public function test_empresas_service_shows_a_jump_only_rate_table_in_people(): void
    {
        // El servicio «Empresas» (teambuilding) reusa la tabla Jump (2h/3h) con unidad «personas»
        // (no «niños»: es un servicio de adultos). «30 personas» sólo lo pinta Empresas (colegio usa niños).
        $this->get('/servicios')
            ->assertOk()
            ->assertSee('30 personas')
            ->assertSee('100 personas')
            ->assertSee('Jump 2H')
            ->assertSee('Jump 3H');
    }

    public function test_a_single_zone_price_table_renders_without_tabs(): void
    {
        // Con UNA sola zona NO hay toggle: la tabla se pinta directamente, sin `.zone-tabs`.
        LandingService::query()->delete();
        LandingService::create([
            'slug' => 'solo-jump',
            'title' => ['es' => 'Solo Jump'],
            'price_table' => [
                'unit' => 'people',
                'zones' => [[
                    'label' => 'Jump', 'accent' => 'jump',
                    'durations' => [['minutes' => 120, 'tiers' => [['size' => 30, 'weekday' => 1500, 'weekend' => 1700]]]],
                ]],
            ],
            'is_active' => true,
        ]);

        $res = $this->get('/servicios')->assertOk();
        $res->assertSee('svc-rates__table', false);    // la tabla se renderiza
        $res->assertSee('Jump 2H');                    // caption «{zona} {N}H»
        $res->assertSee('30 personas');                // unidad personas
        $res->assertDontSee('zone-tabs', false);       // PERO sin pestañas (1 sola zona)
    }

    public function test_price_table_is_cast_to_array(): void
    {
        $colegio = LandingService::where('slug', 'excursionescolegio')->first();

        $this->assertIsArray($colegio->price_table);
        // Kids · 2 h · L–V · 30 niños = 12,00 € (céntimos).
        $this->assertSame(1200, $colegio->price_table['zones'][0]['durations'][0]['tiers'][0]['weekday']);
    }

    public function test_services_without_a_price_table_render_no_rate_block(): void
    {
        // Solo los servicios que definen `price_table` muestran tarifas; los demás sin tabla, no.
        // (`sesionadultos` = sesión de adultos, solo-contacto sin tarifas; colegio y empresas SÍ las tienen.)
        $this->assertNull(LandingService::where('slug', 'sesionadultos')->value('price_table'));
    }

    public function test_zero_services_degrades_to_hero_and_other_events(): void
    {
        LandingService::query()->delete();

        $this->get('/servicios')
            ->assertOk()
            ->assertSee('Otros eventos')               // banda catch-all estática sobrevive
            ->assertDontSee('svc-ed2__row', false)     // sin filas editoriales
            ->assertDontSee('svc-hero__index', false); // sin índice del hero
    }

    public function test_inactive_service_is_hidden(): void
    {
        LandingService::query()->delete();
        LandingService::create(['slug' => 'oculto', 'title' => ['es' => 'Servicio oculto'], 'is_active' => false]);

        $this->get('/servicios')->assertOk()->assertDontSee('Servicio oculto');
    }

    // ⚠️⚠️ **AQUÍ VIVÍAN `test_nav_lists_data_driven_services_and_respects_show_in_nav` Y
    // `test_an_inactive_service_is_excluded_from_the_nav`, Y SE RETIRAN CON SU SUJETO** (`#521`,
    // `[DECIDIDO owner, 2026-09-11]`): los destinos del menú son el INVENTARIO de páginas del canvas,
    // y un servicio del panel ya no es un destino suelto — el interruptor «Sale en el menú» se retiró
    // del panel con ellos. Clasificados por sujeto (`CONVENCIONES §3.quater`): lo que comprobaban era
    // una capacidad que el owner ha quitado, no una regla que siga viva en otro sitio.
    // ▶ Lo que SÍ sigue vivo —que el menú no ofrezca nada que no sea del inventario— lo vigila
    // `ArmazonContractTest::test_the_menu_offers_exactly_the_inventory_in_its_order`.
}
