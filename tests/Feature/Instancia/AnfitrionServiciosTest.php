<?php

namespace Tests\Feature\Instancia;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Models\LandingService;
use App\Domain\Content\Services\ThemeSettings;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **El ANFITRIÓN MÍNIMO de `/servicios`** — lo que el producto sirve sin paquete de instancia (F5 · T2b,
 * `DECISIONES #660`). Es marcado del PRODUCTO, y por eso aquí SÍ se mira el HTML (`#649`): tiene que
 * pintar TODO lo que el contrato de vista le da —el hero con su índice de anclas, una fila por servicio,
 * sus tablas de grupo o su tabla tecleada, el «desde», la foto y los packs de cumpleaños en corto— y NO
 * puede inventarse lo que no le dan.
 *
 * ❗ **Las ANCLAS son contrato hacia fuera**: el menú enlaza a `/servicios#slug`, así que perderlas es SEO
 * y navegación rotos sin que falle nada.
 *
 * ⚠️ Sin arte a propósito: sin la CINTA `C3`, que es de la instancia (`brand-band`, declarada con sus
 * cinco clases en `InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA`).
 */
class AnfitrionServiciosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    private function html(): string
    {
        return (string) $this->get('/servicios')->assertOk()->assertViewIs('anfitrion.servicios')->getContent();
    }

    public function test_it_paints_a_row_and_a_stable_anchor_per_service(): void
    {
        $servicios = $this->get('/servicios')->assertOk()->original->getData()['services'];
        $html = $this->html();

        $this->assertNotEmpty($servicios, 'el caso nace sin sujeto');
        $this->assertSame($servicios->count(), substr_count($html, 'class="svc-ed2__row'), 'no se pinta una fila por servicio');
        $this->assertStringContainsString('svc-hero__index', $html, 'el hero pierde su índice de anclas');

        foreach ($servicios as $servicio) {
            $this->assertStringContainsString('id="'.$servicio->slug.'"', $html, "la sección «{$servicio->slug}» perdió su ancla");
            $this->assertStringContainsString('href="#'.$servicio->slug.'"', $html, "el índice no enlaza a «{$servicio->slug}»");
        }
    }

    /**
     * **Un servicio con producto comprable enseña su precio y su «Reservar», que declara la INTENCIÓN**
     * (`#568`): el cajón abre EN ese producto, no en la lista. Lo lee también `SidebarSeamTest`, como texto.
     */
    public function test_a_service_with_a_purchasable_product_shows_its_price_and_books_that_product(): void
    {
        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();
        LandingService::create([
            'slug' => 'eventos-empresa',
            'title' => ['es' => 'Eventos de empresa'],
            'zone_label' => ['es' => 'Parque completo'],
            'position' => 0,
            'is_active' => true,
        ])->products()->attach($pack->id);

        $html = $this->html();

        $this->assertStringContainsString('Eventos de empresa', $html);
        $this->assertStringContainsString('svc-ed2__price', $html, 'un servicio comprable no enseña su precio');
        $this->assertStringContainsString(__('landing.pricing.book'), $html);
        $this->assertStringContainsString("openWith({ type: 'product', id: {$pack->id} })", $html,
            'el «Reservar» ya no declara QUÉ producto abre');
    }

    /**
     * ❗ **El «desde» se pinta tal cual lo escribe el producto** (`#660`): la vista no vuelve a formatear.
     * Sin «desde» —un servicio sin tablas— no se pinta el bloque, y en su lugar va «Pedir información».
     */
    public function test_the_from_price_is_painted_as_the_product_wrote_it(): void
    {
        $datos = $this->get('/servicios')->assertOk()->original->getData();
        $html = $this->html();

        foreach ($datos['groupFrom'] as $escrito) {
            if ($escrito !== null) {
                $this->assertStringContainsString('<span class="val">'.$escrito.'</span>', $html,
                    'el «desde» se pinta con otra forma que la que escribió el producto');
            }
        }

        // Las tres secciones sembradas son solo-contacto: sin producto comprable, CTA en vez de precio.
        LandingService::query()->delete();
        LandingService::create(['slug' => 'solo-contacto', 'title' => ['es' => 'Solo contacto'], 'is_active' => true]);

        $html = $this->html();
        $this->assertStringContainsString(__('services.cta_contact'), $html);
        $this->assertStringNotContainsString('svc-ed2__price', $html, 'se pinta un precio que no existe');
    }

    /**
     * **La tabla TECLEADA del panel se pinta cuando el servicio no vende productos**, con su unidad, su
     * caption y el tinte de cada zona EN LÍNEA (`#138`: las clases por acento solo existían para dos).
     * ⚠️ Con UNA sola zona no hay pestañas: un control de una opción no elige nada.
     */
    public function test_the_typed_price_table_is_painted_with_its_zones_and_units(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('svc-rates__table', $html);
        $this->assertStringContainsString(__('services.rates.title'), $html);
        $this->assertStringContainsString(ThemeSettings::zoneStyleForAccent('kids'), $html);
        $this->assertStringContainsString(ThemeSettings::zoneStyleForAccent('jump'), $html);
        $this->assertStringContainsString('Kids · 2 horas', $html);
        $this->assertStringContainsString('Desde 30 alumnos', $html, 'la unidad del colegio son alumnos');
        $this->assertStringContainsString('30 personas', $html, 'la de empresas, personas');

        LandingService::query()->delete();
        LandingService::create([
            'slug' => 'solo-jump',
            'title' => ['es' => 'Solo Jump'],
            'is_active' => true,
            'price_table' => ['unit' => 'people', 'zones' => [[
                'label' => 'Jump', 'accent' => 'jump',
                'durations' => [['minutes' => 120, 'tiers' => [['size' => 30, 'weekday' => 1500, 'weekend' => 1700]]]],
            ]]],
        ]);

        $html = $this->html();
        $this->assertStringContainsString('svc-rates__table', $html);
        $this->assertStringContainsString('Jump · 2 horas', $html);
        $this->assertStringNotContainsString('zone-tabs', $html, 'con una sola zona se pintan pestañas');
    }

    /**
     * ⚠️ **El importe de la tabla tecleada lo escribe `Money::showcase()`** (`#660`), no una tercera
     * variante de la vista: euros exactos sin decimales, y el separador decimal del IDIOMA. Antes vivía
     * aquí un `$fmt` propio que en inglés ponía coma.
     */
    public function test_the_typed_table_writes_money_with_the_products_rule(): void
    {
        LandingService::query()->delete();
        LandingService::create([
            'slug' => 'solo-jump',
            'title' => ['es' => 'Solo Jump'],
            'is_active' => true,
            'price_table' => ['unit' => 'people', 'zones' => [[
                'label' => 'Jump', 'accent' => 'jump',
                'durations' => [['minutes' => 120, 'tiers' => [['size' => 30, 'weekday' => 1500, 'weekend' => 1295]]]],
            ]]],
        ]);

        $this->assertStringContainsString('<td>15 €</td>', $this->html(), 'un euro exacto se escribe con los dos decimales de una transacción');

        $this->withSession(['locale' => 'en']);
        app()->setLocale('en');

        $this->assertStringContainsString('<td>12.95 €</td>', $this->html(),
            'en inglés el decimal vuelve a escribirse con la coma española');
    }

    /**
     * **La foto del servicio se pinta por `imageUrl()` y su ausencia es una respuesta**: sin foto, ni
     * `<img>` ni hueco. El `alt` es el título, que lo escribe el panel.
     */
    public function test_the_service_photo_is_painted_only_when_there_is_one(): void
    {
        $servicio = LandingService::first();
        $servicio->update(['image' => 'images/attractions/park_jump.webp']);

        $html = $this->html();
        $this->assertStringContainsString('src="'.$servicio->fresh()->imageUrl().'"', $html, 'la foto no sale por `imageUrl()`');
        $this->assertStringContainsString('alt="'.e($servicio->tr('title')).'"', $html, 'el `alt` no es el título del panel');

        // ⚠️ Se CUENTA, no se busca la ausencia: los otros servicios del panel también tienen foto, así
        // que «no aparece» sería falso aunque éste dejara su hueco reservado.
        $conFoto = substr_count($html, 'svc-photo__img');
        $servicio->update(['image' => null]);
        $this->assertSame($conFoto - 1, substr_count($this->html(), 'svc-photo__img'),
            'se reserva un hueco de foto que no existe');
    }

    /**
     * Con cero servicios la página NO rompe ni queda vacía: hero y bandas, sin filas ni índice.
     * ⚠️ Se asevera también el ENVOLTORIO (`svc-ed2`), y lo obligó el arnés: con solo las filas, un `@if`
     * roto pintaba el contenedor vacío —un bloque sin nada dentro— y el mutante sobrevivía.
     */
    public function test_zero_services_degrades_to_hero_and_link_bands(): void
    {
        LandingService::query()->delete();
        $html = $this->html();

        $this->assertStringContainsString('bands-thin', $html);
        $this->assertStringNotContainsString('class="svc-ed2"', $html, 'se pinta el contenedor de las filas sin filas');
        $this->assertStringNotContainsString('svc-ed2__row', $html);
        $this->assertStringNotContainsString('svc-hero__index', $html);
    }

    /** Los cumpleaños, en corto y con su puerta a `/cumpleanos` (`#588`). */
    public function test_the_page_ends_with_the_birthday_summary_and_its_door(): void
    {
        $tarjetas = $this->get('/servicios')->assertOk()->original->getData()['birthdayCards'];
        $html = $this->html();

        $this->assertNotEmpty($tarjetas, 'el caso nace sin sujeto');
        $this->assertSame(count($tarjetas), substr_count($html, 'class="svc-party__item"'));
        $this->assertStringContainsString('svc-party__cta" href="'.route('cumpleanos').'"', $html);
    }

    /** El anfitrión es el motor sin el arte: la cinta `C3` es de la instancia. */
    public function test_the_host_carries_no_art(): void
    {
        $this->assertStringNotContainsString('brand-band', $this->html());
    }
}
