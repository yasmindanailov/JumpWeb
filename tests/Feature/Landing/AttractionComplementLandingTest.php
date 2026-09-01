<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * #228 — Landing: una atracción cuyo complemento es comprable en su zona sale PRIMERA en el grid
 * de su zona, con precio + CTA «Comprar» (deep-link a Entradas posicionado en la zona). El test
 * vincula la atracción de Jump en pos 3 («Tirolina») a un complemento de 5 € enganchado a una
 * entrada de Jump, y marca la de pos 2 («Salto a la nube») como destacada.
 */
class AttractionComplementLandingTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $addon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);

        $jump = Zone::where('slug', 'jump')->firstOrFail();

        // Complemento de pago (5 €) enganchado a una entrada vendible de Jump.
        $this->addon = TicketType::create([
            'name' => ['es' => 'Acceso tirolina'], 'type' => TicketType::TYPE_ADDON,
            'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1, 'position' => 90,
        ]);
        $this->addon->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'),
            'amount_cents' => 500,
        ]);
        TicketType::ofType(TicketType::TYPE_ENTRY)->where('zone_id', $jump->id)->firstOrFail()
            ->configurableAddons()->attach($this->addon->id, ['position' => 1]);

        // Atracción de Jump en pos 3 («Tirolina») → vendible; la de pos 2 («Salto a la nube») → destacada.
        Attraction::where('zone_id', $jump->id)->where('position', 3)->update(['ticket_type_id' => $this->addon->id]);
        Attraction::where('zone_id', $jump->id)->where('position', 2)->update(['is_special' => true]);

        Cache::flush();
    }

    public function test_paid_attraction_renders_with_price_and_buy_cta(): void
    {
        $res = $this->get('/')->assertOk();

        $res->assertSee('ride-card--sellable', false);
        $res->assertSee('ride-card__cta', false);
        $res->assertSee('5,00');                                  // precio del complemento (5 €)
        $res->assertSee(__('landing.rides.buy'));                 // «Comprar»
        // Deep-link al catálogo de Entradas, posicionado en la zona Jump. Desde Fase 4 · paso 4.0a
        // la tarjeta declara la INTENCIÓN en vez de despachar un evento de Livewire (ver
        // `SidebarSeamTest`): mismo destino, sin atar la landing al motor del cajón.
        $res->assertSee("openWith({ type: 'zone'", false);
        $res->assertSee("slug: 'jump'", false);
    }

    public function test_paid_attraction_sorts_first_within_its_zone(): void
    {
        // La atracción vendible (pos 3, «Tirolina») debe pintarse ANTES que la de pos 1 («Saltos
        // libres», no vendible) dentro del grid de Jump → vendibles primero.
        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Tirolina', 'Saltos libres']);
    }

    public function test_special_attraction_gets_highlight_class(): void
    {
        // La atracción de pos 2 («Salto a la nube») está marcada como destacada → variante visual.
        $this->get('/')->assertOk()->assertSee('ride-card--special', false);
    }

    public function test_attraction_in_a_hidden_zone_is_not_rendered(): void
    {
        // La zona cumpleaños es operativa pero oculta en la landing (show_in_landing=false).
        $cumple = Zone::where('slug', 'cumpleanos')->firstOrFail();
        Attraction::create([
            'zone_id' => $cumple->id, 'name' => ['es' => 'AtraccionOcultaXyz'], 'position' => 1, 'is_active' => true,
        ]);

        $this->get('/')->assertOk()->assertDontSee('AtraccionOcultaXyz');
    }

    /**
     * **Si el complemento deja de ser vendible, la atracción NO enseña precio ni «Comprar».**
     *
     * ⚠️⚠️ **Este caso cambió de contrato el 2026-08-31** (`#303`, `[DECIDIDO owner]`: «añade un CTA
     * a las cards para que el usuario sepa que tiene que clicarlo»). Antes aseveraba que **no había
     * CTA ninguno**; ahora TODAS las tarjetas llevan uno —el que abre el cajón en la zona—, así que
     * esa aserción se habría puesto roja con el producto sano.
     *
     * ▶ **No se retira: se re-apunta a lo que de verdad protegía, y queda más fuerte.** Lo peligroso
     * nunca fue el CTA, era **enseñar un PRECIO de algo que no se vende**. Eso se asevera igual, y
     * además se exige que la tarjeta **siga ofreciendo el camino a reservar la zona**: sin esa
     * segunda mitad, un cambio que dejara la tarjeta muda pasaría en verde.
     */
    public function test_complement_not_purchasable_shows_no_price_but_keeps_a_way_in(): void
    {
        $this->addon->update(['is_sellable' => false]);
        Cache::flush();

        $res = $this->get('/')->assertOk();
        $res->assertDontSee('ride-card--sellable', false);
        $res->assertDontSee('ride-card__price', false);
        $res->assertSee('ride-card__cta', false);
    }
}
