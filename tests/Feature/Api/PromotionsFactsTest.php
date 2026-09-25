<?php

namespace Tests\Feature\Api;

use App\Domain\Booking\Models\Promotion;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Http\Instancia\PageFacts;
use Illuminate\Support\Carbon;

/**
 * **Las PROMOCIONES del menú de hechos** (`docs/specs/promociones.md` §4.3, `#770`): `GET /api/v1/promotions?lang=`.
 *
 * Lo que una landing no puede comprobar por su cuenta: que solo viajen las VIGENTES en el día DEL PARQUE (una oferta que
 * «acaba el 30» vale el 30 entero), que se apilen con la que acaba antes primero, que una oferta sin traducir no salga
 * en ese idioma y un regalo sí, que lo de una zona apagada o un producto retirado no se anuncie, y que la página de la
 * instancia reciba EL MISMO JSON.
 */
class PromotionsFactsTest extends ApiTestCase
{
    private Zone $kids;

    protected function setUp(): void
    {
        parent::setUp();
        // El 30-09 a las 23:30 en Madrid ya es el 1-10 en UTC: la oferta que acaba el 30 tiene que seguir.
        Carbon::setTestNow(Carbon::parse('2026-09-30 23:30', 'Europe/Madrid'));
        $this->kids = Zone::create(['name' => ['es' => 'Kids'], 'slug' => 'kids', 'accent' => 'kids', 'color' => '#C6FF3A', 'is_active' => true, 'position' => 1]);
    }

    private function oferta(array $atributos): Promotion
    {
        return Promotion::create(['kind' => Promotion::KIND_OFFER, 'text' => ['es' => 'Oferta'], 'ends_on' => '2026-09-30', ...$atributos]);
    }

    /** @return list<array<string, mixed>> */
    private function promociones(string $idioma = 'es'): array
    {
        return $this->getJson("/api/v1/promotions?lang={$idioma}")->assertOk()->json('promotions');
    }

    public function test_only_the_current_ones_travel_in_the_park_calendar_stacked_by_nearest_end(): void
    {
        $this->oferta(['text' => ['es' => 'Acaba el 5'], 'ends_on' => '2026-10-05']);
        $this->oferta(['text' => ['es' => 'Acaba hoy']]);
        $this->oferta(['text' => ['es' => 'Acabó ayer'], 'ends_on' => '2026-09-29']);
        $this->oferta(['text' => ['es' => 'Empieza mañana'], 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-10']);
        $this->oferta(['text' => ['es' => 'Apagada'], 'is_active' => false]);
        Promotion::create(['kind' => Promotion::KIND_GIFT, 'text' => ['es' => 'Regalo sin fin']]);

        $this->assertSame(['Acaba hoy', 'Acaba el 5', 'Regalo sin fin'], array_column($this->promociones(), 'text'));
    }

    public function test_each_one_says_its_kind_its_end_and_its_target(): void
    {
        $producto = TicketType::create([
            'name' => ['es' => 'Kids · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->kids->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->oferta(['text' => ['es' => 'De la zona'], 'zone_id' => $this->kids->id]);
        $this->oferta(['text' => ['es' => 'De todo'], 'ends_on' => '2026-10-02']);
        Promotion::create(['kind' => Promotion::KIND_GIFT, 'text' => ['es' => 'Del producto'], 'ticket_type_id' => $producto->id]);

        // Y la respuesta, contra el CONTRATO: los tres objetivos y el fin que falta en el regalo.
        $this->getJson(self::ROOT.'/promotions?lang=es')->assertOk()->assertValidRequest()->assertValidResponse(200);
        $this->assertSame([
            ['id' => 1, 'kind' => 'offer', 'text' => 'De la zona', 'ends_on' => '2026-09-30', 'target' => ['type' => 'zone', 'zone' => 'kids']],
            ['id' => 2, 'kind' => 'offer', 'text' => 'De todo', 'ends_on' => '2026-10-02', 'target' => ['type' => 'installation']],
            ['id' => 3, 'kind' => 'gift', 'text' => 'Del producto', 'target' => ['type' => 'product', 'product' => $producto->id]],
        ], $this->promociones());
    }

    public function test_an_untranslated_offer_stays_home_and_a_gift_falls_back(): void
    {
        $this->oferta(['text' => ['es' => 'Solo en español']]);
        $this->oferta(['text' => ['es' => 'También', 'en' => 'Also']]);
        Promotion::create(['kind' => Promotion::KIND_GIFT, 'text' => ['es' => 'Cono de chuches']]);

        $this->assertSame(['Also', 'Cono de chuches'], array_column($this->promociones('en'), 'text'));
    }

    public function test_what_a_dead_target_would_announce_does_not_travel(): void
    {
        $this->oferta(['text' => ['es' => 'De una zona apagada'], 'zone_id' => $this->kids->id]);
        $this->kids->update(['is_active' => false]);

        $this->assertSame([], $this->promociones());
    }

    public function test_lang_is_required(): void
    {
        $this->getJson('/api/v1/promotions')->assertStatus(422);
    }

    public function test_the_page_of_the_instance_gets_the_same_json(): void
    {
        $this->oferta(['text' => ['es' => 'Hasta el 30'], 'zone_id' => $this->kids->id]);
        app()->setLocale('es');

        $this->assertSame(
            $this->getJson('/api/v1/promotions?lang=es')->json(),
            app(PageFacts::class)->resolver(['promotions'])['promotions'],
        );
    }
}
