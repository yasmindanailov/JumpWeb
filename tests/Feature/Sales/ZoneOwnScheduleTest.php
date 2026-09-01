<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\SlotTemplate;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ItemRescheduleOffer;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Booking\Services\SlotGenerator;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `#322` — HORARIO POR ZONA (`docs/specs/horario-por-zona.md`).
 *
 * Una zona puede declarar su propia ventana y operar con el recinto cerrado. Lo motivan las
 * excursiones de colegio: vienen entre semana y por la mañana, que es cuando hay colegio, y con los
 * datos reales del cliente (abre 10:00–21:00, martes cerrado) **no tenían ni una franja**.
 *
 * Lo que fija, en orden de importancia:
 *
 *  1. **`null` = HEREDA.** Una instalación que no toque nada se comporta igual que antes: es lo que
 *     convierte la migración en algo sin conducta nueva.
 *  2. **La zona con horario propio abre; las demás NO.** El día cerrado del recinto sigue cerrado
 *     para todo el que no lo ignore explícitamente.
 *  3. **La WEB no se entera.** `OperatingCalendar` sigue siendo el recinto — si la zona se colara
 *     ahí, la landing anunciaría que el parque abre a las 8:00 y los martes.
 *  4. **Generar y podar leen lo MISMO.** Si divergieran, la poda cerraría en la misma pasada lo que
 *     el generador acaba de crear (`AFORO-04`), y con ventas dentro dejaría una excursión vendida en
 *     una franja cerrada.
 *  5. **Ignorar el cierre SIN declarar horas no abre.** Es el fallo silencioso de este diseño: con
 *     el recinto cerrado no hay ventana que heredar, y el fallback histórico de `effectiveFor()`
 *     («abierto sin restricción») generaría todas las plantillas del día.
 */
class ZoneOwnScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const CLOSED_WEEKDAY = 2;   // martes (Carbon dayOfWeek), el día de descanso del cliente

    private Zone $excursiones;

    private Zone $jump;

    protected function setUp(): void
    {
        parent::setUp();

        // El horario REAL del cliente: 10:00–21:00 y el martes cerrado.
        foreach (range(0, 6) as $weekday) {
            OpeningHour::create([
                'weekday' => $weekday,
                'open_time' => '10:00:00',
                'close_time' => '21:00:00',
                'is_closed' => $weekday === self::CLOSED_WEEKDAY,
            ]);
        }

        $this->jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->excursiones = Zone::create(['slug' => 'excursiones', 'name' => ['es' => 'Excursiones']]);
    }

    /** Una plantilla a las 09:00 —antes de que el parque abra— en cada zona, el día de descanso. */
    private function morningTemplates(): void
    {
        foreach ([$this->jump, $this->excursiones] as $zone) {
            SlotTemplate::create([
                'zone_id' => $zone->id,
                'weekday' => self::CLOSED_WEEKDAY,
                'start_time' => '09:00:00',
                'duration_min' => 120,
                'capacity' => 100,
                'online_capacity' => 100,
                'is_active' => true,
            ]);
        }
    }

    /** El próximo día de descanso del recinto, siempre en el futuro. */
    private function nextClosedDay(): Carbon
    {
        $day = Carbon::today()->addDay();
        while ($day->dayOfWeek !== self::CLOSED_WEEKDAY) {
            $day->addDay();
        }

        return $day;
    }

    private function generate(Carbon $day, bool $prune = false): void
    {
        app(SlotGenerator::class)->generate($day, $day, prune: $prune);
    }

    // ─── 1 · el contrato: null = hereda ──────────────────────────────────────────────────────────

    public function test_a_zone_without_its_own_schedule_behaves_exactly_like_the_venue(): void
    {
        $schedule = app(OperatingSchedule::class);
        $day = $this->nextClosedDay();
        $openDay = $day->copy()->addDay();

        foreach ([$day, $openDay] as $date) {
            $this->assertSame(
                $schedule->effectiveFor($date),
                $schedule->effectiveForZone($date, $this->jump),
                'con las tres columnas a null, la zona responde EXACTAMENTE lo que el recinto',
            );
        }
    }

    public function test_the_closed_day_stays_closed_for_a_zone_that_does_not_ignore_it(): void
    {
        $this->morningTemplates();
        $this->generate($this->nextClosedDay());

        $this->assertSame(0, Slot::count(), 'nadie abre el día de descanso si no lo pide');
    }

    // ─── 2 · el caso del cliente ─────────────────────────────────────────────────────────────────

    public function test_the_excursions_zone_opens_on_the_closed_day_and_the_others_do_not(): void
    {
        $this->excursiones->update([
            'opens_at' => '08:00:00',
            'closes_at' => '15:00:00',
            'ignores_venue_closure' => true,
        ]);
        $this->morningTemplates();

        $day = $this->nextClosedDay();
        $this->generate($day);

        $this->assertSame(
            [$this->excursiones->id],
            Slot::pluck('zone_id')->all(),
            'solo la zona con horario propio tiene franja ese día',
        );
        $slot = Slot::sole();
        $this->assertSame('09:00:00', $slot->start_time);
        $this->assertSame($day->toDateString(), $slot->date->toDateString());
    }

    /**
     * ⚠️ El caso que el horario del recinto impedía aunque el parque estuviera ABIERTO: la excursión
     * empieza a las 09:00 y el parque no abre hasta las 10:00.
     */
    public function test_the_zone_window_also_widens_an_open_day(): void
    {
        $this->excursiones->update(['opens_at' => '08:00:00']);
        $this->morningTemplates();

        // Un día normal, con el parque abierto de 10:00 a 21:00.
        $day = $this->nextClosedDay()->addDay();
        SlotTemplate::query()->update(['weekday' => $day->dayOfWeek]);

        $this->generate($day);

        $this->assertSame(
            [$this->excursiones->id],
            Slot::pluck('zone_id')->all(),
            'la de excursiones cabe porque abre a las 8; la otra empieza antes de las 10 y no',
        );
    }

    // ─── 3 · la web no se entera ─────────────────────────────────────────────────────────────────

    public function test_the_public_calendar_still_reports_the_venue_and_never_a_zone(): void
    {
        $this->excursiones->update([
            'opens_at' => '08:00:00',
            'closes_at' => '15:00:00',
            'ignores_venue_closure' => true,
        ]);

        $day = $this->nextClosedDay();
        $window = app(OperatingCalendar::class)->windowFor($day);

        $this->assertFalse($window->isOpen, 'la landing sigue diciendo que el parque cierra ese día');
        $this->assertNull($window->opensAt);

        // CONTROL: la pregunta POR ZONA sí ve la diferencia, así que el `false` de arriba no es que
        // la sonda mire al sitio equivocado.
        $this->assertTrue(app(OperatingSchedule::class)->isOpenOnForZone($day, $this->excursiones));
    }

    // ─── 4 · generar y podar leen lo mismo ───────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ La trampa de esta tanda: si la poda resolviera el horario del RECINTO, neutralizaría en la
     * misma pasada la franja que el generador acaba de crear — y con una venta dentro la cerraría en
     * vez de borrarla (`AFORO-04`), dejando una excursión vendida en una franja cerrada.
     */
    public function test_pruning_does_not_destroy_what_the_generator_just_created(): void
    {
        $this->excursiones->update([
            'opens_at' => '08:00:00',
            'closes_at' => '15:00:00',
            'ignores_venue_closure' => true,
        ]);
        $this->morningTemplates();

        $day = $this->nextClosedDay();
        $this->generate($day, prune: true);
        $afterFirst = Slot::pluck('id')->all();

        $this->generate($day, prune: true);

        $this->assertSame($afterFirst, Slot::pluck('id')->all(), 'la segunda pasada no se come la primera');
        $this->assertCount(1, $afterFirst);
        $this->assertTrue(Slot::sole()->online_sales_open, 'y no queda cerrada por la poda');
    }

    // ─── 5 · el fallo silencioso ─────────────────────────────────────────────────────────────────

    /**
     * Con el recinto cerrado NO hay ventana que heredar (`open`/`close` valen null). Sin este caso,
     * una zona que ignora el cierre y no declara horas caería en el fallback histórico de
     * `effectiveFor()` —«abierto sin restricción»— y generaría TODAS sus plantillas del día.
     */
    public function test_ignoring_the_closure_without_declaring_hours_does_not_open_the_zone(): void
    {
        $this->excursiones->update(['ignores_venue_closure' => true]);   // sin opens_at/closes_at
        $this->morningTemplates();

        $day = $this->nextClosedDay();

        $this->assertFalse(app(OperatingSchedule::class)->isOpenOnForZone($day, $this->excursiones));

        $this->generate($day);
        $this->assertSame(0, Slot::count(), 'ignorar el cierre no es declarar un horario');
    }

    /** Y en un día ABIERTO sí hereda la ventana del recinto, que es el contrato de `null`. */
    public function test_ignoring_the_closure_still_inherits_the_window_on_an_open_day(): void
    {
        $this->excursiones->update(['ignores_venue_closure' => true]);

        $day = $this->nextClosedDay()->addDay();
        $hours = app(OperatingSchedule::class)->effectiveForZone($day, $this->excursiones);

        $this->assertTrue($hours['is_open']);
        $this->assertSame('10:00:00', $hours['open']);
        $this->assertSame('21:00:00', $hours['close']);
    }

    // ─── 6 · el SEGUNDO consumidor: re-programar ────────────────────────────────────────────────

    /**
     * ⚠️⚠️ `ItemRescheduleOffer` es el consumidor que estuvo a punto de quedarse fuera de esta tanda.
     * Sin él, las franjas de la zona con horario propio **existen y no se pueden usar para mover una
     * reserva**: el calendario del panel no ofrece el día y no falla nada. Un fallo mudo.
     */
    public function test_rescheduling_offers_the_venues_closed_day_for_a_zone_that_operates_then(): void
    {
        $this->excursiones->update([
            'opens_at' => '08:00:00',
            'closes_at' => '15:00:00',
            'ignores_venue_closure' => true,
        ]);

        $day = $this->nextClosedDay();

        // La MISMA situación en las dos zonas: una franja vendible el día que el recinto descansa.
        $items = [];
        foreach ([$this->excursiones, $this->jump] as $zone) {
            $slot = Slot::create([
                'zone_id' => $zone->id,
                'date' => $day->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'capacity' => 100,
                'online_capacity' => 100,
                'online_sales_open' => true,
                'status' => Slot::STATUS_OPEN,
            ]);
            $type = TicketType::create([
                'name' => ['es' => 'Producto '.$zone->slug],
                'zone_id' => $zone->id,
                'type' => TicketType::TYPE_ENTRY,
                'is_active' => true,
                'is_sellable' => true,
                'seats_per_unit' => 1,
            ]);
            $order = Order::create([
                'user_id' => User::factory()->create()->id,
                'code' => 'JJ-RS'.$zone->id,
                'status' => Order::STATUS_PAID,
                'subtotal' => 1200, 'total' => 1200, 'currency' => 'EUR',
                'paid_at' => now(),
            ]);
            $items[$zone->slug] = OrderItem::create([
                'order_id' => $order->id,
                'ticket_type_id' => $type->id,
                'slot_id' => $slot->id,
                'quantity' => 1,
                'seats' => 1,
                'unit_price' => 1200,
            ])->fresh('ticketType', 'slot');
        }

        $offer = app(ItemRescheduleOffer::class);
        $from = Carbon::today();
        $to = Carbon::today()->addMonths(2);

        $this->assertContains(
            $day->toDateString(),
            $offer->selectableDates($items['excursiones'], $from, $to),
            'la zona que opera ese día SÍ se puede re-programar a él',
        );
        $this->assertNotContains(
            $day->toDateString(),
            $offer->selectableDates($items['jump'], $from, $to),
            'y la que no opera, no — el recinto sigue cerrado para ella',
        );
        $this->assertNotSame(
            [],
            $offer->times($items['excursiones'], $day->toDateString()),
            'y sus HORAS también se ofrecen: las dos preguntas tienen que coincidir',
        );
    }

    // ─── 7 · el cierre por excepción de día ──────────────────────────────────────────────────────

    /**
     * `[DECIDIDO owner, 2026-09-01]`: la zona ignora el cierre del recinto ENTERO, también el de una
     * excepción de día — «la excursión sí puede caer en festivo». La consecuencia está declarada en
     * la spec §4.2 y su salida es cerrar esas franjas a mano, que el generador respeta para siempre.
     * Este caso existe para que la decisión sea VISIBLE y nadie la «arregle» por su cuenta.
     */
    public function test_a_special_date_closure_does_not_stop_a_zone_that_ignores_it(): void
    {
        $this->excursiones->update([
            'opens_at' => '08:00:00',
            'closes_at' => '15:00:00',
            'ignores_venue_closure' => true,
        ]);
        $this->morningTemplates();

        // Un día ABIERTO por semanal, cerrado por excepción.
        $day = $this->nextClosedDay()->addDay();
        SlotTemplate::query()->update(['weekday' => $day->dayOfWeek]);
        SpecialDate::create(['date' => $day->toDateString(), 'is_closed' => true]);

        $this->assertFalse(app(OperatingSchedule::class)->isOpenOn($day), 'el recinto sí cierra');

        $this->generate($day);

        $this->assertSame(
            [$this->excursiones->id],
            Slot::pluck('zone_id')->all(),
            'la zona que ignora el cierre opera igual; las demás no',
        );
    }
}
