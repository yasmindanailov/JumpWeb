<?php

namespace Tests\Feature\Sales;

use App\Domain\Identity\Models\User;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Pages\CreateManualOrderPage;
use App\Livewire\Tickets\Purchase;
use App\Models\Order;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\SpecialDate;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\SlotOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Auditoría Fase 1 — `SlotOffer` es la fuente ÚNICA de "qué fechas/horas se pueden ofrecer",
 * compartida por la compra pública (`Purchase`) y el pedido manual del panel
 * (`CreateManualOrderPage`). Estos tests blindan que la oferta solo salga de franjas reales,
 * dentro del horizonte y de la ventana viva del día — el bug que hacía que el panel ofreciera
 * fechas/horas que la web no (calendarios "que no correspondían").
 */
class SlotOfferTest extends TestCase
{
    use RefreshDatabase;

    private SlotOffer $offer;

    private Zone $zone;

    private TicketType $entry;

    private Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-14 09:00:00');

        $this->offer = app(SlotOffer::class);
        $this->today = DisplayTime::today();
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function slot(string $date, string $start = '10:00:00', int $online = 10): Slot
    {
        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
            'capacity' => $online + 10, 'online_capacity' => $online,
        ]);
    }

    private function fill(Slot $slot, int $seats): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->entry->id, 'slot_id' => $slot->id,
            'quantity' => $seats, 'seats' => $seats, 'unit_price' => 1000,
        ]);
    }

    public function test_offerable_dates_only_within_horizon_and_not_past(): void
    {
        $this->slot($this->today->copy()->subDay()->toDateString());   // ayer (pasado)
        $this->slot($this->today->copy()->addDays(3)->toDateString()); // dentro
        $this->slot($this->today->copy()->addDays(20)->toDateString()); // dentro
        $beyond = $this->today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths())->addMonth();
        $this->slot($beyond->toDateString());                          // más allá del horizonte

        $dates = $this->offer->offerableDates($this->entry);

        $this->assertContains($this->today->copy()->addDays(3)->toDateString(), $dates);
        $this->assertContains($this->today->copy()->addDays(20)->toDateString(), $dates);
        $this->assertNotContains($this->today->copy()->subDay()->toDateString(), $dates, 'el pasado no se ofrece');
        $this->assertNotContains($beyond->toDateString(), $dates, 'más allá del horizonte no se ofrece');
    }

    public function test_closed_special_date_is_not_offerable(): void
    {
        $date = $this->today->copy()->addDays(5)->toDateString();
        $this->slot($date);
        SpecialDate::create(['date' => $date, 'is_closed' => true]);

        $this->assertNotContains($date, $this->offer->offerableDates($this->entry));
        $this->assertSame([], $this->offer->offerableTimes($this->entry, $date), 'día cerrado → sin horas');
    }

    public function test_special_date_window_filters_times_outside_the_day_hours(): void
    {
        $date = $this->today->copy()->addDays(5)->toDateString();
        $this->slot($date, '10:00:00'); // fuera de la ventana especial
        $this->slot($date, '18:00:00'); // dentro
        SpecialDate::create(['date' => $date, 'is_closed' => false, 'open_time' => '12:00:00', 'close_time' => '20:00:00']);

        $times = array_keys($this->offer->offerableTimes($this->entry, $date));

        $this->assertSame(['18:00:00'], $times, 'solo las franjas dentro de la ventana viva del día');
    }

    public function test_full_entry_slot_is_still_listed_but_marked_unsellable(): void
    {
        $date = $this->today->copy()->addDays(2)->toDateString();
        $slot = $this->slot($date, '10:00:00', online: 4);
        $this->fill($slot, 4); // aforo completo

        $times = $this->offer->offerableTimes($this->entry, $date);

        $this->assertArrayHasKey('10:00:00', $times, 'la franja llena se LISTA (no se oculta)');
        $this->assertSame(0, $times['10:00:00']['available']);
        $this->assertFalse($times['10:00:00']['sellable'], 'pero marcada no-vendible (se mostrará deshabilitada)');
    }

    public function test_pack_below_min_guests_is_filtered_out(): void
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'position' => 2,
        ]);
        $date = $this->today->copy()->addDays(4)->toDateString();
        // Cupo de niños por franja = 5 (< min_qty 8 del pack) → no reservable.
        $this->zone->update(['max_guests_per_slot' => 5]);
        $this->slot($date, '11:00:00');

        $this->assertSame([], array_keys($this->offer->offerableTimes($pack, $date)), 'pack bajo su mínimo no se ofrece');

        // Con cupo suficiente sí aparece.
        $this->zone->update(['max_guests_per_slot' => 30]);
        $this->assertSame(['11:00:00'], array_keys($this->offer->offerableTimes($pack, $date)));
    }

    public function test_panel_offer_matches_shared_source(): void
    {
        // Paridad web↔panel: el panel (CreateManualOrderPage) deriva fechas/horas del MISMO
        // `SlotOffer`. Una fecha en el horizonte pero SIN franja (el bug original) no es ofrecible,
        // y el tope del calendario del panel es la última franja real (no hoy+horizonte aritmético).
        $d1 = $this->today->copy()->addDays(3)->toDateString();
        $d2 = $this->today->copy()->addDays(6)->toDateString();
        $this->slot($d1, '10:00:00');
        $this->slot($d2, '12:00:00');

        $page = new CreateManualOrderPage;
        $page->data = ['sel_product_id' => $this->entry->id, 'sel_date' => $d1];

        $maxOfferable = $this->invoke($page, 'maxOfferableDate');
        $this->assertSame($d2, $maxOfferable?->toDateString(), 'el tope del panel es la última franja REAL');

        $panelTimes = $this->invoke($page, 'timeOptions');
        $sourceTimes = $this->offer->offerableTimes($this->entry, $d1);
        $this->assertSame(array_keys($sourceTimes), array_keys($panelTimes), 'el panel ofrece exactamente lo que el servicio compartido');

        // Una fecha del horizonte SIN franja no es ofrecible (no existe en la fuente).
        $emptyDay = $this->today->copy()->addDays(10)->toDateString();
        $this->assertNotContains($emptyDay, $this->offer->offerableDates($this->entry));
    }

    public function test_past_times_today_are_not_offered(): void
    {
        // Son las 12:00 en Madrid: la franja de las 10:00 de HOY ya pasó (no se ofrece); la de las 15:00 sí.
        Carbon::setTestNow(Carbon::parse('2026-09-14 12:00:00', 'Europe/Madrid'));
        $today = DisplayTime::today()->toDateString();
        $this->slot($today, '10:00:00');
        $this->slot($today, '15:00:00');

        $times = array_keys($this->offer->offerableTimes($this->entry, $today));

        $this->assertSame(['15:00:00'], $times, 'las horas ya pasadas de hoy no se ofrecen');
    }

    public function test_min_advance_days_excludes_dates_too_soon(): void
    {
        // Antelación mínima 2 días (calendario): a 1 día no llega; a 2+ días sí.
        $this->entry->update(['min_advance_value' => 2, 'min_advance_unit' => TicketType::UNIT_DAYS]);
        $this->slot($this->today->copy()->addDay()->toDateString());   // +1 día → no
        $this->slot($this->today->copy()->addDays(2)->toDateString()); // +2 días → sí
        $this->slot($this->today->copy()->addDays(5)->toDateString()); // +5 días → sí

        $dates = $this->offer->offerableDates($this->entry);

        $this->assertNotContains($this->today->copy()->addDay()->toDateString(), $dates, 'a 1 día no llega al mínimo de 2');
        $this->assertContains($this->today->copy()->addDays(2)->toDateString(), $dates);
        $this->assertContains($this->today->copy()->addDays(5)->toDateString(), $dates);
    }

    public function test_min_advance_hours_excludes_times_too_soon(): void
    {
        // Antelación mínima 6 horas (rodante): a las 12:00, la franja en +3h no; en +7h sí.
        Carbon::setTestNow(Carbon::parse('2026-09-14 12:00:00', 'Europe/Madrid'));
        $this->entry->update(['min_advance_value' => 6, 'min_advance_unit' => TicketType::UNIT_HOURS]);
        $today = DisplayTime::today()->toDateString();
        $this->slot($today, '15:00:00'); // +3h → no
        $this->slot($today, '19:00:00'); // +7h → sí

        $this->assertSame(['19:00:00'], array_keys($this->offer->offerableTimes($this->entry, $today)));
    }

    public function test_meets_min_advance_semantics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 12:00:00', 'Europe/Madrid'));
        $now = DisplayTime::now();

        // 0 = sin restricción.
        $this->entry->min_advance_value = 0;
        $this->assertTrue($this->entry->meetsMinAdvance('2026-09-14', '13:00:00', $now));

        // días (calendario): 1 día → hoy NO (ni a última hora), mañana SÍ (toda la jornada).
        $this->entry->min_advance_value = 1;
        $this->entry->min_advance_unit = TicketType::UNIT_DAYS;
        $this->assertFalse($this->entry->meetsMinAdvance('2026-09-14', '20:00:00', $now));
        $this->assertTrue($this->entry->meetsMinAdvance('2026-09-15', '10:00:00', $now));

        // horas (rodante): 6h → +3h NO, +7h SÍ.
        $this->entry->min_advance_value = 6;
        $this->entry->min_advance_unit = TicketType::UNIT_HOURS;
        $this->assertFalse($this->entry->meetsMinAdvance('2026-09-14', '15:00:00', $now));
        $this->assertTrue($this->entry->meetsMinAdvance('2026-09-14', '19:00:00', $now));
    }

    public function test_public_purchase_flow_excludes_past_times_today(): void
    {
        // End-to-end del flujo público real (Livewire `Purchase`, no solo el servicio): al elegir HOY
        // a las 12:00, la franja de las 10:00 (ya pasada) NO aparece en las horas ofrecidas.
        Carbon::setTestNow(Carbon::parse('2026-09-14 12:00:00', 'Europe/Madrid'));
        // El render del calendario público resuelve la tarifa del día (RateResolver) → necesita la base.
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);
        $today = DisplayTime::today()->toDateString();
        $this->slot($today, '10:00:00');
        $this->slot($today, '15:00:00');

        Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $today)
            ->assertViewHas('times', fn (array $times): bool => ! in_array('10:00:00', $times, true)
                && in_array('15:00:00', $times, true));
    }

    public function test_panel_calendar_blocks_lead_time_window_via_min_date(): void
    {
        // UX: con antelación mínima 7 días, el calendario del panel debe EMPEZAR en la primera fecha
        // ofrecible → la ventana de antelación queda BLOQUEADA (gris), no clicable-sin-horas.
        $this->entry->update(['min_advance_value' => 7, 'min_advance_unit' => TicketType::UNIT_DAYS]);
        $this->slot($this->today->copy()->addDay()->toDateString(), '10:00:00');    // dentro de la ventana → bloqueada
        $this->slot($this->today->copy()->addDays(8)->toDateString(), '10:00:00');  // ofrecible (primera)
        $this->slot($this->today->copy()->addDays(10)->toDateString(), '11:00:00'); // ofrecible

        $page = new CreateManualOrderPage;
        $page->data = ['sel_product_id' => $this->entry->id];

        $this->assertSame(
            $this->today->copy()->addDays(8)->toDateString(),
            $this->invoke($page, 'minOfferableDate')?->toDateString(),
            'el calendario del panel empieza en la primera fecha ofrecible'
        );
    }

    private function invoke(object $obj, string $method): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);

        return $ref->invoke($obj);
    }
}
