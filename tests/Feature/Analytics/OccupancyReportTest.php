<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\PriceTier;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\OccupancyReport;
use App\Filament\Widgets\Analytics\OccupancyBreakdownWidget;
use App\Filament\Widgets\Analytics\OccupancyHeatmapWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **El informe de la ocupación** (`docs/specs/analitica-para-decidir.md` §4.8.ter, la T2; `DECISIONES #758`), con un
 * fixture calculado A MANO (las cifras salen de aquí, no del código):
 *
 *  · `jump` el miércoles 10-06, rejilla de 30 min, aforo 10: 15:00, 15:30, 16:00, 16:30 y una 17:00 CERRADA. Una entrada
 *    de 1 h a las 15:00 con 10 plazas (cobrada el 05-06: llena 15:00 y 15:30) y otra a las 16:00 con 3 (cobrada el mismo
 *    día) → presencia 10 · 10 · 3 · 3 = 26 de 40 ofrecidas (la cerrada no cuenta) = 65 %; llenas 2 de 4, llenadas con 5
 *    días de antelación. Plazas-hora: 10 × (30 + 30 + 30 + 30) min = 20 h (la 16:30 representa hasta la 17:00); vendido
 *    13.000 cént. → 650 cént. por plaza-hora.
 *  · `cumple` el mismo día, tope 2 fiestas por franja, sin preparación: un pack de 90 min a las 15:00 (cobrado el 01-05)
 *    → ocupa 15:00, 15:30 y 16:00 → 3 de 4 × 2 = 8 huecos = 37,5 %. NO se suma a las entradas.
 *  · Hoy (30-06) a las 18:00, una entrada de 5 plazas: son las 12:00, aún no ha pasado → fuera.
 *  · Anticipación: 5 días (entrada) · 0 días (entrada) · 40 días (fiesta) → mediana 5; entradas 0 (la de abajo de dos).
 *  · Demanda sin hueco: dos en junio y una en mayo (la comparación).
 */
class OccupancyReportTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Europe/Madrid';

    private Zone $jump;

    private Zone $cumple;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => self::TZ, 'group' => 'general']);
        Setting::flushMemo();
        $this->travelTo(Carbon::parse('2026-06-30 12:00:00', self::TZ));
    }

    private function june(): Window
    {
        return ReportPeriod::Custom->window('2026-06-01', '2026-06-30');
    }

    private function seedJune(): void
    {
        $this->jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'is_active' => true]);
        $this->cumple = Zone::create(['slug' => 'cumple', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'max_per_slot' => 2, 'max_guests_per_slot' => 40, 'prep_blocks_cupo' => false]);
        foreach (['15:00', '15:30', '16:00', '16:30'] as $start) {
            $this->slot($this->jump, '2026-06-10', $start, 10);
            $this->slot($this->cumple, '2026-06-10', $start, 50);
        }
        $this->slot($this->jump, '2026-06-10', '17:00', 10, closed: true);
        $this->slot($this->jump, '2026-06-30', '18:00', 10);

        $entry = TicketType::create(['name' => ['es' => 'Entrada 1 h'], 'zone_id' => $this->jump->id, 'type' => TicketType::TYPE_ENTRY, 'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]);
        $pack = TicketType::create(['name' => ['es' => 'Cumpleaños'], 'zone_id' => $this->cumple->id, 'type' => TicketType::TYPE_PACK, 'duration_min' => 90, 'prep_before_min' => 0, 'prep_after_min' => 0, 'min_qty' => 1, 'max_qty' => 30, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2]);

        $this->sold($entry, $this->jump, '2026-06-10', '15:00:00', 10, '2026-06-05 10:00:00');
        $this->sold($entry, $this->jump, '2026-06-10', '16:00:00', 3, '2026-06-10 08:00:00');
        $this->sold($pack, $this->cumple, '2026-06-10', '15:00:00', 12, '2026-05-01 10:00:00');
        $this->sold($entry, $this->jump, '2026-06-30', '18:00:00', 5, '2026-06-20 10:00:00');

        $this->missing('2026-06-12 10:00:00', $entry->id, '2026-06');
        $this->missing('2026-06-13 10:00:00', $entry->id, '2026-06');
        $this->missing('2026-05-20 10:00:00', $entry->id, '2026-05');
    }

    private function slot(Zone $zone, string $date, string $start, int $capacity, bool $closed = false): void
    {
        $s = Carbon::parse($start);
        Slot::create([
            'zone_id' => $zone->id, 'date' => $date, 'start_time' => $s->format('H:i:s'), 'end_time' => $s->copy()->addHour()->format('H:i:s'),
            'capacity' => $capacity, 'online_capacity' => $capacity, 'status' => $closed ? Slot::STATUS_CLOSED : Slot::STATUS_OPEN,
        ]);
    }

    private function sold(TicketType $product, Zone $zone, string $date, string $start, int $seats, string $paidAtUtc): void
    {
        $slot = Slot::query()->where('zone_id', $zone->id)->where('date', $date)->where('start_time', $start)->sole();
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'R-'.Str::upper(Str::random(6)), 'status' => Order::STATUS_PAID,
            'subtotal' => $seats * 1000, 'tax' => 0, 'total' => $seats * 1000, 'currency' => 'EUR', 'paid_at' => $paidAtUtc,
        ]);
        $order->items()->create(['ticket_type_id' => $product->id, 'slot_id' => $slot->id, 'quantity' => $seats, 'unit_price' => 1000, 'seats' => $seats]);
    }

    private function missing(string $receivedUtc, int $product, string $month): void
    {
        DB::table('analytics_events')->insert([
            'event_id' => (string) Str::ulid(), 'visitor_id' => (string) Str::uuid(), 'name' => 'availability_missing',
            'props' => json_encode(['product' => (string) $product, 'month' => $month]), 'occurred_at' => $receivedUtc, 'received_at' => $receivedUtc,
        ]);
    }

    public function test_the_occupancy_of_the_entries_the_full_slots_and_the_revenue_per_seat_hour(): void
    {
        $this->seedJune();

        $r = (new OccupancyReport)->compute($this->june());

        $this->assertSame(
            ['seats' => 26, 'capacity' => 40, 'points' => 4, 'full' => 2, 'full_lead_days' => 5, 'seat_minutes' => 1200, 'revenue_cents' => 13000, 'lines' => 2],
            $r['entries'],
            'la cerrada no cuenta como ofrecida; lo de esta tarde aún no ha pasado',
        );
        $this->assertSame(['present' => 3, 'cap' => 8, 'points' => 4, 'capped' => true], $r['parties'], 'las fiestas, aparte: nunca sumadas a las plazas');
    }

    /**
     * Los VISITANTES (T3a, `#759`): las plazas de las visitas pagadas que ya pasaron —entradas Y fiestas, que aquí sí se
     * suman: son personas, no aforo—, las reservas que las traen y la suma de sus cuadrados. Tecleado a mano: 10 + 3 plazas
     * de entrada y una fiesta de 12; la de las 18:00 del 30 aún no ha pasado. Mayo, sin visitas.
     */
    public function test_the_visitors_are_the_seats_of_the_paid_visits_already_past(): void
    {
        $this->seedJune();

        $r = (new OccupancyReport)->compute($this->june());

        $this->assertSame(['seats' => 25, 'lines' => 3, 'seats_sq' => 10 * 10 + 3 * 3 + 12 * 12], $r['visitors']);
        $this->assertSame([0, 0, 0], [$r['previous']['visitors'], $r['previous']['visitor_lines'], $r['previous']['visitors_sq']]);
    }

    /**
     * …y el periodo COMPARADO lleva los suyos: del 11 al 29 de junio (sin visitas) se compara con los 19 días de antes, del
     * 23 de mayo al 10 de junio, donde están las tres visitas del día 10.
     */
    public function test_the_compared_period_carries_its_own_visitors(): void
    {
        $this->seedJune();

        $r = (new OccupancyReport)->compute(ReportPeriod::Custom->window('2026-06-11', '2026-06-29'));

        $this->assertSame(['seats' => 0, 'lines' => 0, 'seats_sq' => 0], $r['visitors']);
        $this->assertSame([25, 3, 253], [$r['previous']['visitors'], $r['previous']['visitor_lines'], $r['previous']['visitors_sq']]);
    }

    public function test_the_anticipation_by_kind_and_by_weekday(): void
    {
        $this->seedJune();

        $a = (new OccupancyReport)->compute($this->june())['anticipation'];

        $this->assertSame(3, $a['n']);
        $this->assertSame(5, $a['median']);
        $this->assertSame(['same_day' => 1, 'd1_2' => 0, 'd3_7' => 1, 'd8_30' => 0, 'd31' => 1], $a['buckets']);
        $this->assertSame(['n' => 2, 'median' => 0, 'buckets' => ['same_day' => 1, 'd1_2' => 0, 'd3_7' => 1, 'd8_30' => 0, 'd31' => 0]], $a['by_kind']['entry']);
        $this->assertSame(40, $a['by_kind']['party']['median']);
        $this->assertSame(0, $a['by_kind']['group']['n']);
        $this->assertSame(['n' => 3, 'median' => 5], $a['by_weekday'][3], 'el 10-06-2026 es miércoles');
    }

    /**
     * El desglose, PINTADO CON RESERVAS (T3a, `#759`): las medianas se escriben en días. Sin reservas la tabla pone «—» y no
     * llama a quien escribe los días; la T3a movió ese ayudante al catálogo y solo esto lo habría visto.
     */
    public function test_the_breakdown_writes_the_medians_in_days_with_bookings(): void
    {
        $this->seedJune();

        $tables = collect((new OccupancyBreakdownWidget)->tablesFor($this->june()));

        $byKind = $tables->firstWhere('heading', 'Anticipación por tipo')['rows'];
        $this->assertSame(['Entradas', '2', 'el mismo día'], array_slice($byKind[0], 0, 3));
        $this->assertSame(['Fiestas', '1', '40 días'], array_slice($byKind[1], 0, 3));
        $this->assertSame('—', $byKind[2][2], 'sin grupos');
        $this->assertContains(['Miércoles', '3', '5 días'], $tables->firstWhere('heading', 'Anticipación por día de la visita')['rows']);
    }

    /**
     * Dos bordes de la anticipación: un pedido del PANEL apuntado DOS días después de la visita cuenta como «el mismo día»
     * (no hay antelación negativa), y un producto con tramos por volumen es un GRUPO (la regla de `PaidVisits`).
     */
    public function test_a_line_paid_after_its_visit_counts_as_the_same_day_and_a_tiered_product_is_a_group(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'is_active' => true]);
        $this->jump = $zone;
        $this->slot($zone, '2026-06-10', '11:00', 100);
        $excursion = TicketType::create(['name' => ['es' => 'Excursión'], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY, 'duration_min' => 60, 'min_qty' => 1, 'max_qty' => 100, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]);
        $rate = RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);
        PriceTier::create(['ticket_type_id' => $excursion->id, 'rate_type_id' => $rate->id, 'min_qty' => 30, 'amount_cents' => 1500]);
        $this->sold($excursion, $zone, '2026-06-10', '11:00:00', 40, '2026-06-12 10:00:00');

        $a = (new OccupancyReport)->compute($this->june())['anticipation'];

        $this->assertSame(['n' => 1, 'median' => 0, 'buckets' => ['same_day' => 1, 'd1_2' => 0, 'd3_7' => 0, 'd8_30' => 0, 'd31' => 0]], $a['by_kind']['group']);
        $this->assertSame(0, $a['by_kind']['entry']['n']);
    }

    public function test_the_missing_demand_by_product_and_month_and_since_when(): void
    {
        $this->seedJune();

        $r = (new OccupancyReport)->compute($this->june());

        $this->assertSame(2, $r['missing']['count']);
        $this->assertSame('2026-05-20', $r['missing']['since'], 'desde el primero que se midió');
        $this->assertSame([['product' => 'Entrada 1 h', 'month' => '2026-06', 'n' => 2]], $r['missing']['by_product_month']);
        $this->assertSame(1, $r['previous']['missing'], 'mayo, en la comparación');
    }

    /** El mapa de calor: el miércoles a las 15 h (15:00 y 15:30) al 100 %, a las 16 h al 30 %; el % escrito en la celda. */
    public function test_the_heatmap_writes_the_percentage_in_every_cell(): void
    {
        $this->seedJune();

        $heat = (new OccupancyReport)->compute($this->june())['heatmap'];
        $this->assertSame([15 => ['seats' => 20, 'capacity' => 20], 16 => ['seats' => 6, 'capacity' => 20]], $heat[3]);

        $widget = new OccupancyHeatmapWidget;
        $widget->pageFilters = ['period' => ReportPeriod::Custom->value, 'from' => '2026-06-01', 'to' => '2026-06-30'];
        $data = (new \ReflectionMethod($widget, 'getViewData'))->invoke($widget);
        $wednesday = $data['rows'][2]['cells'];
        $this->assertSame(['15', '16'], $data['hours']);
        $this->assertSame(['100 %', '30 %'], array_column($wednesday, 'label'));
        $this->assertTrue($wednesday[0]['strong'], 'el paso lleno lleva texto blanco');
        $this->assertSame(25, $wednesday[1]['mix'], '30 % cae en el paso 20–40');
        $this->assertTrue($data['rows'][0]['cells'][0]['empty'], 'el lunes no hubo franjas: gris y «—», no un 0 %');
    }

    public function test_the_report_runs_in_a_constant_budget_of_queries(): void
    {
        $this->seedJune();

        DB::enableQueryLog();
        DB::flushQueryLog();
        (new OccupancyReport)->compute($this->june());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(20, $queries, "el informe de la ocupación hace {$queries} consultas");
    }
}
