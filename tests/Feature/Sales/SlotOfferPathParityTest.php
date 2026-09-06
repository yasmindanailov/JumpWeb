<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\CounterSale;
use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\SlotOffer;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **LAS DOS VÍAS DE LA OFERTA TIENEN QUE CONTESTAR LO MISMO** (`#465`).
 *
 * `SlotOffer` responde a la misma pregunta por dos caminos, y desde esta tanda ya no cuestan igual:
 *
 *  - **`offerableDates()`** —qué DÍAS se venden— lee filas crudas del horizonte, resuelve la ventana
 *    **una vez por día** y para en cuanto un día pasa. Es la vía LIGERA: 329 ms → 18.
 *  - **`offerableTimes()`** —qué HORAS de un día— carga **solo ese día** y lo hidrata, porque el cupo
 *    se calcula sobre el modelo `Slot`. 364 ms → 30.
 *
 * ❗❗❗ **El peligro de tener dos vías no es el rendimiento: es que se separen.** Una que ofrezca un
 * día cuyas horas la otra rechaza es un calendario que miente, y **eso no lo ve ninguna prueba que
 * mire una sola vía**. Por eso la guarda central de este fichero no comprueba números concretos:
 * comprueba la EQUIVALENCIA —«un día está en `offerableDates()` **si y solo si** `offerableTimes()`
 * de ese día devuelve algo»— sobre un horizonte sembrado con todos los motivos por los que un día
 * puede caerse: cerrado, fuera de la ventana, ya pasado, fuera del horizonte.
 *
 * ⚠️⚠️ **Y el riesgo que introduce acotar por día**: mientras la consulta abarcaba el horizonte
 * entero, **el techo de venta lo ponía ella**. Al preguntar por un día suelto ese techo desaparece —
 * un día a dos años vista se vendería sin que nada fallara—, así que el recorte se re-pone en
 * `clampToHorizon()` y aquí se comprueba en las dos direcciones.
 */
class SlotOfferPathParityTest extends TestCase
{
    use RefreshDatabase;

    private SlotOffer $offer;

    private Zone $zona;

    private TicketType $entrada;

    private Carbon $hoy;

    protected function setUp(): void
    {
        parent::setUp();
        // Miércoles 16 de septiembre de 2026, a las 12:31: con la hora a media mañana, las franjas de
        // HOY anteriores caen por el corte intra-día — uno de los motivos que la paridad tiene que ver.
        Carbon::setTestNow('2026-09-16 12:31:00');

        $this->offer = app(SlotOffer::class);
        $this->hoy = DisplayTime::today();

        // Horario del recinto: 10:00–20:00 todos los días. Con él, una franja de las 21:00 queda
        // FUERA de la ventana viva aunque exista en la tabla — el segundo motivo.
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '10:00:00', 'close_time' => '20:00:00']);
        }

        $this->zona = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        $this->entrada = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zona->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);

        // ⚠️ Fixture LEGALIZADO (`#429`): desde que la oferta exige precio para la tarifa del día, un
        // producto sin tarifa no se ofrece **ningún** día — y las dos vías seguirían siendo idénticas
        // (las dos vacías), o sea que la paridad pasaría midiendo la nada.
        $tarifa = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
        $this->entrada->prices()->create(['rate_type_id' => $tarifa->id, 'amount_cents' => 1200]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * ❗❗❗ **La guarda que ata las dos vías.** Si alguien optimiza una y no la otra, o cambia una
     * regla en un sitio, este caso se pone rojo — sin depender de qué días sean los buenos.
     *
     * El horizonte se siembra con los CUATRO motivos por los que un día se cae, y con su control
     * (días que sí se ofrecen), porque una equivalencia entre dos listas vacías también es cierta.
     */
    public function test_a_day_is_offered_if_and_only_if_it_has_offerable_times(): void
    {
        $this->seedHorizonteVariado();

        $fechas = $this->offer->offerableDates($this->entrada);
        $enFechas = array_flip($fechas);

        $this->assertNotEmpty($fechas, 'CONTROL: sin días ofrecibles la equivalencia sería trivial.');

        $desacuerdos = [];
        $dia = $this->hoy->copy();
        $limite = $this->hoy->copy()->addMonths(SlotOffer::horizonMonths())->addDays(3);

        while ($dia <= $limite) {
            $ymd = $dia->toDateString();
            $enDias = isset($enFechas[$ymd]);
            $tieneHoras = $this->offer->offerableTimes($this->entrada, $ymd) !== [];

            if ($enDias !== $tieneHoras) {
                $desacuerdos[] = $ymd.($enDias ? ' (día SÍ / horas NO)' : ' (día NO / horas SÍ)');
            }

            $dia->addDay();
        }

        $this->assertSame(
            [], $desacuerdos,
            "Las dos vías de la oferta se han separado.\n".
            "Un día que el calendario enciende y cuyas horas están vacías es un calendario que miente;\n".
            "al revés, son horas que nadie puede alcanzar.\n".
            'Días en desacuerdo: '.implode(' · ', array_slice($desacuerdos, 0, 10)),
        );
    }

    /**
     * ⚠️⚠️ **El techo de venta lo ponía la consulta del horizonte, y acotar por día se lo lleva.**
     *
     * Con el control delante: el MISMO producto, la MISMA franja, un día dentro y otro fuera.
     */
    public function test_the_horizon_still_caps_the_hours_of_a_single_day(): void
    {
        $dentro = $this->hoy->copy()->addMonths(SlotOffer::horizonMonths())->subDays(2);
        $fuera = $this->hoy->copy()->addMonths(SlotOffer::horizonMonths())->addDays(2);

        $this->slot($dentro->toDateString(), '11:00:00');
        $this->slot($fuera->toDateString(), '11:00:00');

        $this->assertNotSame(
            [], $this->offer->offerableTimes($this->entrada, $dentro->toDateString()),
            'CONTROL: un día DENTRO del horizonte tiene que ofrecer sus horas.',
        );

        $this->assertSame(
            [], $this->offer->offerableTimes($this->entrada, $fuera->toDateString()),
            "Un día MÁS ALLÁ del horizonte de venta se está ofreciendo.\n".
            'Es el techo que ponía la consulta del horizonte y que acotar por día retira.',
        );

        $this->assertNotContains($fuera->toDateString(), $this->offer->offerableDates($this->entrada));
    }

    /**
     * El corte intra-día vale en las DOS vías: una franja de hoy cuya hora ya pasó no se ofrece, y si
     * es la única de hoy, hoy tampoco es un día ofrecible.
     */
    public function test_todays_past_hour_falls_in_both_paths(): void
    {
        $hoy = $this->hoy->toDateString();
        $this->slot($hoy, '10:00:00');   // ya pasó (son las 12:31)

        $this->assertSame([], $this->offer->offerableTimes($this->entrada, $hoy));
        $this->assertNotContains($hoy, $this->offer->offerableDates($this->entrada));

        // Control: con una franja posterior, hoy vuelve a ser ofrecible por las dos vías.
        $this->slot($hoy, '18:00:00');

        $this->assertSame(['18:00:00'], array_keys($this->offer->offerableTimes($this->entrada, $hoy)));
        $this->assertContains($hoy, $this->offer->offerableDates($this->entrada));
    }

    /**
     * ⚠️ **La vía ligera tiene que ver la ventana del día igual que la pesada.** Es el filtro que se
     * resuelve una vez por día en vez de once, así que es justo donde un atajo mal hecho se notaría:
     * una franja fuera del horario existe en la tabla y no se vende.
     */
    public function test_a_slot_outside_the_day_window_is_invisible_to_both_paths(): void
    {
        $dia = $this->hoy->copy()->addDays(3)->toDateString();
        $this->slot($dia, '21:00:00');   // el recinto cierra a las 20:00

        $this->assertSame([], $this->offer->offerableTimes($this->entrada, $dia));
        $this->assertNotContains($dia, $this->offer->offerableDates($this->entrada));

    }

    /**
     * Una fecha ESPECIAL que recorta el día filtra EN VIVO, sin regenerar franjas — y también por las
     * dos vías.
     *
     * ⚠️⚠️ **Este caso va aparte y siembra ANTES de preguntar, y no es cosmética**: `OperatingSchedule`
     * memoiza las fechas especiales, el horario y las temporadas **por instancia**, así que un caso
     * que pregunte primero y siembre después mide el horario de antes y falla con el producto sano.
     * Es la trampa que `#268` documentó en `GuestAgeMixReader`, aquí por otra puerta. La primera
     * versión de este fichero cayó en ella.
     */
    public function test_a_special_date_that_shortens_the_day_filters_both_paths(): void
    {
        $dia = $this->hoy->copy()->addDays(4)->toDateString();
        SpecialDate::create(['date' => $dia, 'is_closed' => false, 'open_time' => '10:00:00', 'close_time' => '13:00:00']);
        $this->slot($dia, '11:00:00');
        $this->slot($dia, '19:00:00');   // fuera del recorte de ese día

        $offer = app(SlotOffer::class);   // instancia limpia: el horario se lee después de sembrarlo

        $this->assertSame(['11:00:00'], array_keys($offer->offerableTimes($this->entrada, $dia)));
        $this->assertContains($dia, $offer->offerableDates($this->entrada));
    }

    /**
     * `#330` — la antelación mínima no ata al MOSTRADOR, y eso tiene que valer igual en las dos vías:
     * si el calendario del panel encendiera el día pero sus horas salieran vacías, el operador
     * pulsaría un día que no le lleva a ninguna parte.
     */
    public function test_the_counter_sale_reaches_the_same_days_in_both_paths(): void
    {
        $this->entrada->update(['min_advance_value' => 7, 'min_advance_unit' => TicketType::UNIT_DAYS]);
        $manana = $this->hoy->copy()->addDay()->toDateString();
        $this->slot($manana, '11:00:00');

        $mostrador = CounterSale::byOperator();

        $this->assertContains($manana, $this->offer->offerableDates($this->entrada, $mostrador));
        $this->assertNotSame([], $this->offer->offerableTimes($this->entrada, $manana, [], [], $mostrador));

        // Control: la WEB sigue sin poder, por las dos vías.
        $this->assertNotContains($manana, $this->offer->offerableDates($this->entrada));
        $this->assertSame([], $this->offer->offerableTimes($this->entrada, $manana));
    }

    /**
     * ⚠️ **Una franja RETIRADA de la venta online, o CERRADA, existe en la tabla y no se vende** — y
     * las dos vías lo tienen que ver igual, porque comparten la consulta y su *scope*
     * `sellableOnline()`. Lo dijo el arnés: sin este caso, quitar el scope pasaba en verde.
     */
    public function test_a_slot_that_is_not_sellable_online_is_invisible_to_both_paths(): void
    {
        $retirada = $this->hoy->copy()->addDays(3)->toDateString();
        $this->slot($retirada, '11:00:00')->update(['online_sales_open' => false]);

        $cerrada = $this->hoy->copy()->addDays(4)->toDateString();
        $this->slot($cerrada, '11:00:00')->update(['status' => Slot::STATUS_CLOSED]);

        $normal = $this->hoy->copy()->addDays(5)->toDateString();
        $this->slot($normal, '11:00:00');

        $fechas = $this->offer->offerableDates($this->entrada);

        $this->assertContains($normal, $fechas, 'CONTROL: una franja normal sí se ofrece.');
        $this->assertNotContains($retirada, $fechas, 'Una franja retirada de la venta online se está ofreciendo.');
        $this->assertNotContains($cerrada, $fechas, 'Una franja CERRADA se está ofreciendo.');

        $this->assertSame([], $this->offer->offerableTimes($this->entrada, $retirada));
        $this->assertSame([], $this->offer->offerableTimes($this->entrada, $cerrada));
        $this->assertNotSame([], $this->offer->offerableTimes($this->entrada, $normal));
    }

    /**
     * ⚠️⚠️ **La oferta es de la ZONA del producto.** Una franja de otra zona a una hora en la que la
     * del producto no tiene ninguna **no puede** encender ese día: sería vender una entrada de JUMP
     * en la sala de cumpleaños. Lo dijo el arnés: sin dos zonas en el caso, quitar el filtro de zona
     * pasaba en verde — *un filtro sin dos sujetos no se puede ver fallar*.
     */
    public function test_the_offer_only_sees_the_zone_of_its_product(): void
    {
        $otraZona = Zone::create(['slug' => 'kids', 'name' => ['es' => 'KIDS'], 'position' => 2]);
        $dia = $this->hoy->copy()->addDays(3)->toDateString();

        Slot::create([
            'zone_id' => $otraZona->id, 'date' => $dia,
            'start_time' => '11:00:00', 'end_time' => '12:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);

        $this->assertNotContains($dia, $this->offer->offerableDates($this->entrada), 'La oferta está viendo franjas de otra zona.');
        $this->assertSame([], $this->offer->offerableTimes($this->entrada, $dia));

        // Control: con una franja de SU zona ese mismo día, las dos vías lo encienden.
        $this->slot($dia, '11:00:00');

        $this->assertContains($dia, app(SlotOffer::class)->offerableDates($this->entrada));
        $this->assertNotSame([], app(SlotOffer::class)->offerableTimes($this->entrada, $dia));
    }

    // ─── Andamio ──────────────────────────────────────────────────────────────────────────────

    /**
     * Un horizonte con los cuatro motivos de caída y su control:
     * días normales · un día CERRADO · un día con todas sus franjas fuera de la ventana · hoy con
     * horas pasadas · y días sin ninguna franja.
     */
    private function seedHorizonteVariado(): void
    {
        // ⚠️⚠️ **El día CERRADO tiene franjas NORMALES, y eso es lo que le da sujeto**: la primera
        // versión lo puso en un día cuyas franjas ya caían fuera del horario, así que el cierre no
        // decidía nada — y la mutación «la vía ligera reutiliza la ventana del primer día» pasaba en
        // VERDE. *Un caso sin sujeto no vigila nada, y aquí el sujeto es que el cierre sea LO ÚNICO
        // que tumba ese día.*
        $cerrado = $this->hoy->copy()->addDays(6)->toDateString();
        SpecialDate::create(['date' => $cerrado, 'is_closed' => true]);

        // Otro día con horario RECORTADO: si la ventana se resolviera una sola vez para todo el
        // horizonte, este día ofrecería su franja de tarde igual que los demás.
        $recortado = $this->hoy->copy()->addDays(8)->toDateString();
        SpecialDate::create(['date' => $recortado, 'is_closed' => false, 'open_time' => '10:00:00', 'close_time' => '13:00:00']);

        for ($i = 0; $i <= 40; $i++) {
            $dia = $this->hoy->copy()->addDays($i);
            $ymd = $dia->toDateString();

            if ($i % 7 === 3) {
                continue;   // días sin ninguna franja
            }

            if ($i % 11 === 5) {
                $this->slot($ymd, '21:00:00');   // solo fuera de la ventana → el día no se ofrece
                $this->slot($ymd, '22:00:00');

                continue;
            }

            // Días normales: mañana y tarde. En HOY, la de la mañana ya pasó.
            $this->slot($ymd, '10:00:00');
            $this->slot($ymd, '17:00:00');
        }

        // Una franja RETIRADA de la venta online y otra CERRADA: existen en la tabla y no se venden.
        $this->slot($this->hoy->copy()->addDays(10)->toDateString(), '12:00:00')
            ->update(['online_sales_open' => false]);
        $this->slot($this->hoy->copy()->addDays(12)->toDateString(), '12:00:00')
            ->update(['status' => Slot::STATUS_CLOSED]);

        // Un día MÁS ALLÁ del horizonte, con franjas perfectamente válidas.
        $this->slot($this->hoy->copy()->addMonths(SlotOffer::horizonMonths())->addDays(2)->toDateString(), '11:00:00');
    }

    private function slot(string $date, string $start): Slot
    {
        return Slot::create([
            'zone_id' => $this->zona->id, 'date' => $date,
            'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
            'capacity' => 20, 'online_capacity' => 20,
        ]);
    }
}
