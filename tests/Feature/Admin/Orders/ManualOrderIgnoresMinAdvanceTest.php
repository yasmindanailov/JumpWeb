<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Contracts\CounterSale;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\SlotOffer;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Pages\CreateManualOrderPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `#330` — **LA ANTELACIÓN MÍNIMA NO ATA AL MOSTRADOR** (`[DECIDIDO owner, 2026-09-01]`: «el operador
 * no tenga límites para crear el pedido no respetando los X días de antelación»).
 *
 * El encuadre que ordena la tanda, y que es lo que hay que conservar si esto se toca: **`min_advance`
 * es una regla del AUTOSERVICIO**. Existe para gobernar a alguien que compra solo, sin nadie delante
 * que pueda juzgar el caso — «las fiestas se piden con tres días» protege a una cocina que no puede
 * responder. Cuando quien vende es un operador con el cliente al teléfono, esa premisa no se cumple:
 * él sabe si llega. Por eso aquí no hay interruptor ni permiso, al revés que con el mínimo del pack
 * (`ManualOrderBelowPackMinimumTest`), que sí ata hasta que alguien lo levanta a propósito.
 *
 * ❗❗ **Se impone en DOS sitios y el segundo no avisa**, la misma forma que el mínimo del pack:
 *  1. `OrderCreator` — rechaza la línea, con su error;
 *  2. **`SlotOffer::offeredSlots()`** — descarta la franja de la OFERTA, y como de ahí sale también
 *     `offerableDates()`, es **el suelo del calendario del panel** (`minOfferableDate()`). Sin tocarlo,
 *     el operador podría elegir la hora y no llegar nunca al día: media función, sin ningún error.
 *
 * ⚠️ **Lo que SIGUE atando en mostrador**, y cada caso de abajo lo comprueba: el corte intra-día (una
 * franja de hoy cuya hora ya pasó **no es antelación, es el pasado**) y la ventana de horario del
 * producto.
 */
class ManualOrderIgnoresMinAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $pack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'],
            'is_active' => true, 'show_in_landing' => false,
            'max_per_slot' => 5, 'max_guests_per_slot' => 100,
        ]);

        // Tres días con rejilla: hoy, mañana y dentro de cinco.
        foreach ([0, 1, 5] as $offset) {
            foreach (range(9, 20) as $hour) {
                Slot::create([
                    'zone_id' => $this->zone->id,
                    'date' => Carbon::today()->addDays($offset)->toDateString(),
                    'start_time' => sprintf('%02d:00:00', $hour),
                    'end_time' => sprintf('%02d:00:00', $hour + 1),
                    'capacity' => 200,
                    'online_capacity' => 200,
                ]);
            }
        }

        // Una fiesta que la web exige pedir con TRES DÍAS de antelación.
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'],
            'zone_id' => $this->zone->id,
            'type' => TicketType::TYPE_PACK,
            'duration_min' => 120,
            'min_qty' => 1,
            'max_qty' => 20,
            'seats_per_unit' => 1,
            'min_advance_value' => 3,
            'min_advance_unit' => TicketType::UNIT_DAYS,
            'is_sellable' => true,
            'is_active' => true,
        ]);
        $this->pack->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'),
            'amount_cents' => 1500,
        ]);
        $this->pack->refresh()->load('prices', 'priceTiers');
    }

    private function customer(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    /** Una hora de MAÑANA que la rejilla tiene y la antelación de tres días prohíbe. */
    private function tomorrow(): string
    {
        return Carbon::today()->addDay()->toDateString();
    }

    /** @return array<int, array<string,mixed>> */
    private function cart(string $date, string $time = '11:00:00'): array
    {
        return [[
            'ticket_type_id' => $this->pack->id,
            'date' => $date,
            'time' => $time,
            'qty' => 10,
            'event_data' => [],
            'addons' => [],
        ]];
    }

    // ─── 1 · el dominio ──────────────────────────────────────────────────────────────────────────

    /** ⚠️ El default es la WEB: `CheckoutOrchestrator` no pasa `CounterSale`, así que le sigue atando. */
    public function test_the_web_still_cannot_book_inside_the_products_notice_period(): void
    {
        try {
            app(OrderCreator::class)->createPendingOrder($this->customer(), $this->cart($this->tomorrow()));
            $this->fail('la web ha podido reservar dentro de la antelación mínima');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.too_soon_line', $e->getMessage());
        }
    }

    public function test_the_counter_can_book_inside_the_notice_period(): void
    {
        $order = app(OrderCreator::class)->createPendingOrder(
            $this->customer(), $this->cart($this->tomorrow()), null, CounterSale::byOperator(),
        );

        $this->assertCount(1, $order->items);
        $this->assertSame($this->tomorrow(), $order->items->first()->slot->date->toDateString());
    }

    /**
     * ⚠️⚠️ **EL CONTROL, y es el que separa «antelación» de «el pasado».** Una franja de HOY cuya hora
     * ya pasó sigue fuera **también en mostrador**: eso no lo decide quien vende, lo decide el reloj.
     * Si algún día alguien «termina el trabajo» metiendo el corte intra-día en la misma excepción,
     * este caso se pone rojo.
     */
    public function test_the_counter_still_cannot_book_a_slot_that_already_started_today(): void
    {
        // A las 15:30 de hoy, la franja de las 11:00 ya pasó.
        Carbon::setTestNow(DisplayTime::now()->copy()->setTime(15, 30));

        try {
            app(OrderCreator::class)->createPendingOrder(
                $this->customer(), $this->cart(Carbon::today()->toDateString(), '11:00:00'), null, CounterSale::byOperator(),
            );
            $this->fail('el mostrador ha podido vender una franja que ya empezó');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.too_late_line', $e->getMessage());
        } finally {
            Carbon::setTestNow();
        }
    }

    // ─── 2 · la oferta, que es la mitad que no se ve fallar ──────────────────────────────────────

    /**
     * ❗❗ Sin esto la función queda a medias **y sin ningún error**: `offerableDates()` es el suelo del
     * calendario del panel, así que el operador vería la hora y no podría llegar al día.
     */
    public function test_the_counter_is_offered_the_days_the_notice_period_hides(): void
    {
        $offer = app(SlotOffer::class);

        $this->assertNotContains($this->tomorrow(), $offer->offerableDates($this->pack),
            'la web no puede ver mañana con tres días de antelación');

        $this->assertContains($this->tomorrow(), $offer->offerableDates($this->pack, CounterSale::byOperator()),
            'el mostrador tiene que verlo');
    }

    public function test_the_counter_is_offered_the_hours_of_those_days(): void
    {
        $offer = app(SlotOffer::class);

        $this->assertSame([], $offer->offerableTimes($this->pack, $this->tomorrow()),
            'la web no ve ninguna hora dentro de la antelación');

        $this->assertNotEmpty($offer->offerableTimes($this->pack, $this->tomorrow(), sale: CounterSale::byOperator()));
    }

    /**
     * ⚠️⚠️ **EL SEGUNDO CONTROL: se relajó la ANTELACIÓN, no el estado de la franja.** Una franja
     * cerrada o retirada de la venta online sigue fuera también en mostrador.
     *
     * ▶ Y deja anotado lo que **hoy es así y podría no ser lo que el parque quiere**: `online_sales_open`
     * significa «no se vende ONLINE», así que un operador podría argumentar que él sí debería poder
     * venderla. **No se ha cambiado** —no estaba en el encargo— y este caso lo pinta explícitamente
     * para que la decisión sea consciente el día que se tome, en vez de descubrirse rompiendo un test.
     */
    public function test_the_counter_does_not_get_slots_that_are_closed_or_off_sale(): void
    {
        $date = Carbon::today()->addDays(5)->toDateString();

        Slot::where('zone_id', $this->zone->id)->where('date', $date)
            ->where('start_time', '09:00:00')->update(['status' => Slot::STATUS_CLOSED]);
        Slot::where('zone_id', $this->zone->id)->where('date', $date)
            ->where('start_time', '10:00:00')->update(['online_sales_open' => false]);

        $times = array_keys(app(SlotOffer::class)->offerableTimes(
            $this->pack, $date, sale: CounterSale::byOperator(),
        ));

        $this->assertNotEmpty($times, 'el resto del día sigue ofreciéndose');
        $this->assertNotContains('09:00:00', $times, 'una franja CERRADA no se vende ni en mostrador');
        $this->assertNotContains('10:00:00', $times, 'una franja retirada de la venta online tampoco, hoy');
    }

    /**
     * ❗❗ **Y que la PÁGINA se lo pida, que es distinto de que `SlotOffer` sepa hacerlo.**
     *
     * Los dos casos de arriba prueban el servicio; éste prueba que el panel lo usa. Sin él, devolver
     * la página al `offerableDates($type)` de la web pasaba en VERDE —lo dijo el arnés de mutación—
     * y el operador se quedaba con el día bloqueado en la tira y en el suelo del calendario.
     * *Que el dominio pueda hacerlo no es que la pantalla lo haga.*
     */
    public function test_the_panel_offers_those_days_in_its_own_day_strip(): void
    {
        $operator = User::factory()->create();
        $operator->roles()->sync([Role::where('name', 'staff')->value('id')]);

        $page = Livewire::actingAs($operator)
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_PRODUCT)
            ->set('data.sel_product_id', $this->pack->id)
            ->instance();

        $days = array_column($page->quickDays(), 'date');

        $this->assertContains($this->tomorrow(), $days,
            'la tira de días del panel sigue escondiendo lo que la antelación mínima reserva al autoservicio');
    }

    /** Un producto SIN antelación declarada no cambia de conducta por ningún lado. */
    public function test_a_product_without_a_notice_period_behaves_the_same_either_way(): void
    {
        $this->pack->update(['min_advance_value' => 0]);
        $offer = app(SlotOffer::class);

        $this->assertSame(
            $offer->offerableDates($this->pack),
            $offer->offerableDates($this->pack, CounterSale::byOperator()),
        );
    }
}
