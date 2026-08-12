<?php

namespace Tests\Feature\Seeders;

use App\Models\OpeningHour;
use App\Models\Order;
use App\Models\RateType;
use App\Models\Season;
use App\Models\Setting;
use App\Models\SlotTemplate;
use App\Models\SpecialDate;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 2 — Verifica el seed de PRODUCCIÓN (datos reales de Jumpingjump · San Javier, 2026-06-12).
 * Reproducible en BD limpia. Distinto del fixture de tests (`LandingContentSeeder`): aquí las
 * ENTRADAS NO se venden online (solo se muestran) y la venta online es solo de CUMPLEAÑOS con señal.
 */
class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductionSeeder::class);
    }

    public function test_entries_are_shown_but_not_sellable_online(): void
    {
        $entries = TicketType::ofType(TicketType::TYPE_ENTRY)->get();

        $this->assertCount(4, $entries, 'Jump/Kids × 1h/2h');
        $this->assertSame(4, $entries->where('is_active', true)->count(), 'todas visibles en la landing');
        $this->assertSame(0, $entries->where('is_sellable', true)->count(), 'ninguna se vende online (CTA «Llamar»)');
        $this->assertSame(1, $entries->where('min_advance_value', 1)->where('zone_id', Zone::where('slug', 'jump')->value('id'))->where('duration_min', 60)->count());
    }

    public function test_entry_prices_match_real_data_with_weekend_surcharge(): void
    {
        $normalId = RateType::where('key', RateType::KEY_NORMAL)->value('id');
        $specialId = RateType::where('key', RateType::KEY_SPECIAL)->value('id');
        $jumpId = Zone::where('slug', 'jump')->value('id');

        $jump1h = TicketType::ofType(TicketType::TYPE_ENTRY)->where('zone_id', $jumpId)->where('duration_min', 60)->with('prices')->first();
        $this->assertSame(1200, $jump1h->prices->firstWhere('rate_type_id', $normalId)->amount_cents);   // 12 €
        $this->assertSame(1500, $jump1h->prices->firstWhere('rate_type_id', $specialId)->amount_cents);  // +3 € finde/festivo

        // La entrada más barata (Kids · 1h) = 10 € normal.
        $cheapest = TicketType::ofType(TicketType::TYPE_ENTRY)->with('prices')->get()
            ->flatMap->prices->where('rate_type_id', $normalId)->min('amount_cents');
        $this->assertSame(1000, $cheapest);
    }

    public function test_birthday_packs_are_sellable_with_a_30eur_deposit(): void
    {
        $packs = TicketType::ofType(TicketType::TYPE_PACK)->get();

        $this->assertCount(4, $packs, 'Kids/Jump × 90/120 min');
        $this->assertSame(4, $packs->where('is_sellable', true)->count());
        foreach ($packs as $pack) {
            $this->assertSame(TicketType::DEPOSIT_FIXED, $pack->deposit_type);
            $this->assertSame(3000, (int) $pack->deposit_value, 'señal 30 €');
            $this->assertSame(8, (int) $pack->min_qty);
            $this->assertSame(20, (int) $pack->max_qty);
            $this->assertSame(0, (int) $pack->prep_before_min, 'sin montaje');
            $this->assertSame(0, (int) $pack->prep_after_min, 'sin limpieza');
            $this->assertSame(60, (int) $pack->available_after_open_min, '≥1h tras abrir');
            $this->assertSame(60, (int) $pack->available_before_close_min, '≤1h antes de cerrar');
            $this->assertSame(3, (int) $pack->min_advance_value);
            $this->assertSame('days', $pack->min_advance_unit, '3 días de antelación');
        }

        // Precios reales por niño (normal): 15,90 / 17,90 / 17,90 / 19,90.
        $normalId = RateType::where('key', RateType::KEY_NORMAL)->value('id');
        $prices = TicketType::ofType(TicketType::TYPE_PACK)->with('prices')->orderBy('position')->get()
            ->map(fn (TicketType $p) => $p->prices->firstWhere('rate_type_id', $normalId)->amount_cents)->all();
        $this->assertSame([1590, 1790, 1790, 1990], $prices);
    }

    public function test_addons_include_a_menu_choice_group_and_pack_extras(): void
    {
        $addons = TicketType::ofType(TicketType::TYPE_ADDON)->get();
        $this->assertCount(6, $addons, 'calcetines, tirolina, tarta, 2ª tarta, menú 1, menú 2');

        $packs = TicketType::ofType(TicketType::TYPE_PACK)->pluck('id');

        // Menú 1 ⊻ Menú 2: grupo de elección excluyente «menu», por invitado, en los 4 packs.
        $menuLinks = DB::table('product_addons')->where('choice_group', 'menu')->get();
        $this->assertCount(8, $menuLinks, 'menú1 + menú2 en cada uno de los 4 packs');
        $this->assertTrue($menuLinks->every(fn ($l) => $l->quantity_mode === 'per_guest'));

        // Menú 1 viene incluido (gratis); Menú 2 es +2 €/niño.
        $menu1 = TicketType::where('position', 24)->first();
        $menu2 = TicketType::where('position', 25)->first();
        $this->assertSame(4, DB::table('product_addons')->where('addon_id', $menu1->id)->where('is_included', true)->count());
        $this->assertSame(0, (int) $menu1->prices()->first()->amount_cents, 'Menú 1 incluido = 0 €');
        $this->assertSame(200, (int) $menu2->prices()->first()->amount_cents, 'Menú 2 = +2 €');

        // Tarta y 2ª tarta solo en packs; tirolina solo en entradas de la zona Jump.
        $tarta = TicketType::where('position', 22)->first();
        $this->assertSame($packs->count(), DB::table('product_addons')->where('addon_id', $tarta->id)->count());
        $tirolina = TicketType::where('position', 21)->first();
        $jumpEntryIds = TicketType::ofType(TicketType::TYPE_ENTRY)->where('zone_id', Zone::where('slug', 'jump')->value('id'))->pluck('id');
        $this->assertSame($jumpEntryIds->count(), DB::table('product_addons')->where('addon_id', $tirolina->id)->count());
    }

    public function test_opening_hours_season_and_holidays(): void
    {
        // L–V 16–22 · finde 11–22.
        $this->assertSame('16:00:00', OpeningHour::where('weekday', 1)->value('open_time'));  // lunes
        $this->assertSame('11:00:00', OpeningHour::where('weekday', 6)->value('open_time'));  // sábado
        $this->assertSame('11:00:00', OpeningHour::where('weekday', 0)->value('open_time'));  // domingo
        $this->assertSame('22:00:00', OpeningHour::where('weekday', 1)->value('close_time'));

        // Verano jul–ago 11–22.
        $summer = Season::where('name', 'Horario de verano')->first();
        $this->assertNotNull($summer);
        $this->assertSame('2026-07-01', $summer->start_date->toDateString());
        $this->assertSame('2026-08-31', $summer->end_date->toDateString());

        // 14 festivos 2026 (San Javier), todos abiertos 11–22 con tarifa especial.
        $specialId = RateType::where('key', RateType::KEY_SPECIAL)->value('id');
        $holidays = SpecialDate::all();
        $this->assertCount(14, $holidays);
        $this->assertSame(0, $holidays->where('is_closed', true)->count(), 'ninguno cerrado por defecto');
        $this->assertTrue($holidays->every(fn (SpecialDate $d) => (int) $d->rate_type_id === (int) $specialId));
        $dates = $holidays->map(fn (SpecialDate $d) => $d->date->toDateString());
        $this->assertTrue($dates->contains('2026-02-03'));  // fiesta local San Javier
        $this->assertTrue($dates->contains('2026-06-09'));  // Día de la Región de Murcia
    }

    public function test_only_birthday_zone_has_slot_templates(): void
    {
        $cumpleId = Zone::where('slug', 'cumpleanos')->value('id');
        $landingIds = Zone::whereIn('slug', ['jump', 'kids'])->pluck('id');

        // Cumpleaños = lo único vendible online → 11:00–21:00 × 7 días = 77 plantillas.
        $this->assertSame(77, SlotTemplate::where('zone_id', $cumpleId)->count());
        // Las entradas no se venden online → sin franjas para jump/kids.
        $this->assertSame(0, SlotTemplate::whereIn('zone_id', $landingIds)->count());
    }

    public function test_business_settings_reflect_san_javier(): void
    {
        $this->assertSame('San Javier', Setting::value('business.city'));
        $this->assertSame('21', Setting::value('payment.tax_rate'));
        $this->assertSame('5', Setting::value('packs.max_per_slot'), '5 cumpleaños por franja');
        $this->assertSame('0', Setting::value('packs.max_guests_per_slot'), 'sin tope total de niños');
        $this->assertSame('https://jumpingjump.web.hipos.es', Setting::value('registration.url'), 'waiver externo');
        $this->assertStringStartsWith('https://maps.app.goo.gl/', Setting::value('address.maps_url'));
        $this->assertStringContainsString('San Javier', Setting::value('address.line2'));
    }

    public function test_birthday_cancellation_policy_and_jurisdiction_are_published(): void
    {
        $terms = $this->get('/condiciones')->assertOk();

        // La cláusula 10 ya recoge la política real (no-show / reembolso de la señal #225); sin marcador.
        $terms->assertSee('la señal no es reembolsable');
        $terms->assertSee('hasta 3 días naturales antes');
        $terms->assertDontSee('detallar plazos, condiciones de no-show');

        // Fuero/jurisdicción = San Javier (Murcia); sin marcador pendiente.
        $terms->assertSee('los juzgados y tribunales de San Javier (Murcia)');
        $terms->assertDontSee('fuero/población');

        $notice = $this->get('/aviso-legal')->assertOk();
        $notice->assertSee('San Javier (Murcia)');
        $notice->assertDontSee('fuero/jurisdicción');
    }

    public function test_reseeding_aborts_when_orders_exist_to_prevent_data_loss(): void
    {
        // `setUp` ya sembró ProductionSeeder en una BD SIN pedidos. Simulamos producción con un pedido
        // de cliente: re-sembrar DEBE abortar, porque el seed borra `ticket_types` y los `order_items`/
        // `tickets` tienen FK `cascadeOnDelete` → un re-seed borraría pedidos reales en cascada.
        $user = User::factory()->create();
        Order::create(['user_id' => $user->id, 'code' => 'JJ-GUARD-1']);

        $this->expectException(RuntimeException::class);
        (new ProductionSeeder)->run();
    }
}
