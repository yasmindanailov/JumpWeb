<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Contracts\CounterSale;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\PriceTier;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ManualOrderFulfiller;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\SlotOffer;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Pages\CreateManualOrderPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `#329` — **EL OPERADOR PUEDE VENDER UN PACK POR DEBAJO DE SU MÍNIMO AL CREAR EL PEDIDO.**
 *
 * Es el gemelo de D7 (`specs/cumple-mixto.md` §23.4), que dio esa excepción a la EDICIÓN y dejó
 * escrito en su propio docblock que *«solo el panel: `OrderCreator` sigue exigiendo el mínimo al
 * vender»*. El caso real es la excursión de colegio que reserva por teléfono con 24 niños cuando el
 * producto empieza en 30 — justo la llamada que el owner describió como «ya toca llamar y preguntar».
 *
 * ❗❗ **Lo que hace este fichero distinto de una guarda de permiso**: el mínimo NO se impone en un
 * sitio, se impone en CUATRO, y solo dos de ellos avisan cuando fallan.
 *
 *  1. el `minValue` del campo — presentación;
 *  2. el rango de `addLineToCart()` — presentación;
 *  3. **`OrderCreator`** — la defensa real, la que rechaza;
 *  4. **`SlotOffer::offerableTimes()`** — que descarta la franja ENTERA cuando el hueco libre no
 *     llega al mínimo, y ésa es la que se lleva la feature por delante **sin dar ningún error**: sin
 *     tocarla, el operador con permiso vendería 20 en una franja vacía y no vería ni una hora en una
 *     franja con 25 plazas libres. *La función habría funcionado donde nadie la prueba y fallado
 *     donde se usa.*
 *
 * Y el precio, que es la parte que mueve dinero: `[DECIDIDO owner, 2026-09-01]` por debajo del
 * mínimo **se cobra el primer tramo**. La aritmética de esa regla y su control viven en
 * `Sales\PriceTierTest`; aquí se comprueba que el pedido REAL sale con ese número.
 */
class ManualOrderBelowPackMinimumTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $excursion;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        // ⚠️ El cupo de invitados va EN LA ZONA y hace falta declararlo: sin él,
        // `PackAvailability::availableGuestsFor()` devuelve el `max_qty` del pack pase lo que pase, y
        // los casos de oferta de franjas medirían un mundo sin aforo — verdes y vacíos.
        $this->zone = Zone::create([
            'slug' => 'excursiones', 'name' => ['es' => 'Excursiones'],
            'is_active' => true, 'show_in_landing' => false,
            'max_per_slot' => 5, 'max_guests_per_slot' => 100,
        ]);
        // Un martes: la tarifa `normal` manda (la `special` no existe en este fixture).
        $this->date = Carbon::today()->addWeek()->startOfWeek()->addDay()->toDateString();

        $this->slotsFor($this->date);

        // La excursión real del cliente: 30–100 personas, 2 h, con el cuadro de tramos.
        $this->excursion = TicketType::create([
            'name' => ['es' => 'Excursión de colegio · 2 h'],
            'zone_id' => $this->zone->id,
            'type' => TicketType::TYPE_PACK,
            'duration_min' => 120,
            'min_qty' => 30,
            'max_qty' => 100,
            'seats_per_unit' => 1,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 1,
        ]);
        $rateId = RateType::where('key', 'normal')->value('id');
        // El precio «de siempre», que con la decisión del owner NO debe ganar por debajo del mínimo.
        $this->excursion->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 9900]);
        foreach ([[30, 1500], [70, 1300], [100, 1200]] as [$min, $cents]) {
            PriceTier::create(['ticket_type_id' => $this->excursion->id, 'rate_type_id' => $rateId, 'min_qty' => $min, 'amount_cents' => $cents]);
        }
        $this->excursion->refresh()->load('prices', 'priceTiers');
    }

    /** Rejilla horaria 09:00–14:00 de un día (la fiesta dura 2 h, así que necesita franja siguiente). */
    private function slotsFor(string $date): void
    {
        foreach (range(9, 13) as $hour) {
            Slot::create([
                'zone_id' => $this->zone->id,
                'date' => $date,
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 200,
                'online_capacity' => 200,
            ]);
        }
    }

    private function customer(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    /** Un operador con el permiso de crear pedidos manuales; `$below` decide si además puede bajar del mínimo. */
    private function operator(bool $below): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        if (! $below) {
            // El rol `staff` trae la excepción por defecto (`PermissionCatalog`, grupo «operativa»):
            // para el caso negativo hay que RETIRARLA, no basta con no darla.
            $role = Role::where('name', 'staff')->first();
            $role->permissions()->detach(Permission::where('name', 'orders.edit_item_below_minimum')->value('id'));
        }

        return $u->refresh();
    }

    /** @return array<int, array<string,mixed>> */
    private function cart(int $qty): array
    {
        return [[
            'ticket_type_id' => $this->excursion->id,
            'date' => $this->date,
            'time' => '09:00:00',
            'qty' => $qty,
            'event_data' => [],
            'addons' => [],
        ]];
    }

    // ─── 1 · el dominio, que es donde el mínimo manda de verdad ──────────────────────────────────

    /**
     * ⚠️ **El default es la WEB.** `CheckoutOrchestrator` no pasa la bandera, así que este caso es
     * literalmente la conducta del cliente comprando por su cuenta: el mínimo le sigue rechazando.
     */
    public function test_the_domain_rejects_a_pack_below_its_minimum_by_default(): void
    {
        $this->expectException(ReservationException::class);

        app(OrderCreator::class)->createPendingOrder($this->customer(), $this->cart(20));
    }

    public function test_the_domain_accepts_it_with_the_operators_exception(): void
    {
        $order = app(OrderCreator::class)->createPendingOrder($this->customer(), $this->cart(20), null, CounterSale::byOperator(true));

        $this->assertCount(1, $order->items);
        $this->assertSame(20, (int) $order->items->first()->quantity);
    }

    /**
     * ⚠️ La excepción salta **solo el mínimo**, igual que en la edición: el máximo y el `>= 1` siguen
     * mandando. Si algún día alguien la convierte en «el operador decide todo», estos dos casos se
     * ponen rojos.
     */
    public function test_the_exception_does_not_lift_the_maximum(): void
    {
        // ⚠️⚠️ **Se asevera QUÉ rechaza, no que rechace.** La primera versión solo esperaba una
        // `ReservationException` y pasaba en verde con el tope del rango retirado: 101 personas
        // también las para el AFORO (`availableGuestsFor` topa por `max_qty`), así que la excepción
        // llegaba igual por otra puerta. Lo cazó el arnés de mutación.
        // *Dos defensas distintas que lanzan la misma clase se confunden si solo miras la clase.*
        try {
            app(OrderCreator::class)->createPendingOrder($this->customer(), $this->cart(101), null, CounterSale::byOperator(true));
            $this->fail('101 invitados en un pack de máximo 100 tienen que rechazarse');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.pack_guests_range_line', $e->getMessage(),
                'lo rechaza el RANGO del pack, no el aforo');
        }
    }

    /**
     * ⚠️ **El `>= 1` NO lo defiende el rango del pack: lo defiende `Cart::sanitize()`**, que sube
     * cualquier cantidad a `max(1, …)` antes de que el rango se mire. Medido al escribir esta guarda,
     * que nació esperando una excepción que nunca llega — *la premisa era mía y era falsa*.
     *
     * Se asevera igualmente porque lo que importa no es qué capa lo impide, sino que la excepción del
     * operador **no puede producir una línea de cero**: si algún día alguien retirase ese `max(1, …)`
     * creyéndolo redundante, este caso lo diría.
     */
    public function test_the_exception_can_never_produce_a_zero_quantity_line(): void
    {
        $order = app(OrderCreator::class)->createPendingOrder($this->customer(), $this->cart(0), null, CounterSale::byOperator(true));

        $this->assertSame(1, (int) $order->items->first()->quantity);
    }

    // ─── 2 · el precio, que es lo que mueve dinero ───────────────────────────────────────────────

    /**
     * `[DECIDIDO owner]`: 20 personas en una escala que empieza en 30 se cobran a **15 €**, el primer
     * tramo — no a los 99 € del precio base, que es lo que salía antes de `#329`.
     */
    public function test_below_the_minimum_the_order_is_priced_at_the_first_tier(): void
    {
        $order = app(OrderCreator::class)->createPendingOrder($this->customer(), $this->cart(20), null, CounterSale::byOperator(true));
        $item = $order->items->first();

        $this->assertSame(1500, (int) $item->unit_price, 'el primer tramo, no el precio base');
        $this->assertSame(300_00, (int) $order->total, '20 × 15 € = 300 €');
    }

    /**
     * ⚠️⚠️ **LA PREVISUALIZACIÓN Y EL COBRO TIENEN QUE DAR EL MISMO NÚMERO** — y no lo daban.
     *
     * `CreateManualOrderPage::estimateLineCents()` llamaba a `RateResolver::priceCents()` **sin la
     * cantidad**, que es opcional desde `#324` precisamente porque con tramos el precio depende de
     * ella. Medido antes del arreglo: una excursión de 70 se le presupuestaba al operador a un
     * precio y `OrderCreator` cobraba otro. *Un parámetro con valor por defecto no avisa de que
     * hacía falta.*
     */
    public function test_the_operators_preview_matches_what_gets_charged(): void
    {
        // ⚠️⚠️ **La línea la añade LA PÁGINA, no el test.** La primera versión de esta guarda
        // rellenaba `cart` a mano con un total calculado aquí, así que `estimateLineCents()` no
        // llegaba a ejecutarse nunca: quitarle la cantidad al `priceCents()` de la página dejaba el
        // caso en VERDE. Lo cazó el arnés de mutación, no una lectura — *una guarda que no ejercita
        // el disparador vigila el reposo, no la regla* (la lección de `#268`).
        //
        // ⚠️ Un DÍA distinto por cantidad, y no basta con una hora distinta: la fiesta dura 2 h, así
        // que dos franjas contiguas se solapan y comparten el cupo de invitados de la zona — las
        // cuatro cantidades juntas (220) lo agotaban.
        foreach ([20 => 1, 30 => 2, 70 => 3, 100 => 4] as $qty => $offset) {
            $date = Carbon::parse($this->date)->addWeeks($offset)->toDateString();
            $this->slotsFor($date);

            $component = Livewire::actingAs($this->operator(true))
                ->test(CreateManualOrderPage::class)
                ->call('pickProduct', $this->excursion->id)
                ->set('data.sel_below_minimum', $qty < 30)
                ->set('data.sel_date', $date)
                ->set('data.sel_time', '09:00:00')
                ->set('data.sel_qty', $qty)
                ->call('addLineToCart');

            $page = $component->instance();
            $this->assertCount(1, $page->cart, "la página añade la línea de {$qty}");

            $order = app(OrderCreator::class)->createPendingOrder($this->customer(), [[
                'ticket_type_id' => $this->excursion->id,
                'date' => $date,
                'time' => '09:00:00',
                'qty' => $qty,
                'event_data' => [],
                'addons' => [],
            ]], null, CounterSale::byOperator(true));

            $this->assertSame(
                (int) $order->total,
                $page->cartTotalCents(),
                "lo presupuestado y lo cobrado tienen que coincidir con {$qty} personas",
            );
        }
    }

    // ─── 3 · la oferta de franjas: la mitad que no se ve fallar ──────────────────────────────────

    /**
     * ❗❗ Una franja con 25 plazas libres y un pack de mínimo 30. Sin la excepción no se ofrece —
     * correcto: no cabe una fiesta legal. **Con la excepción sí**, que es lo que hace que la función
     * sirva en una franja compartida y no solo en una vacía.
     */
    public function test_a_slot_below_the_minimum_is_offered_only_with_the_exception(): void
    {
        // Ocupamos la franja de las 09:00 hasta dejarle 25 plazas de las 100 del máximo del pack.
        app(OrderCreator::class)->createPendingOrder($this->customer(), [[
            'ticket_type_id' => $this->excursion->id,
            'date' => $this->date,
            'time' => '09:00:00',
            'qty' => 75,
            'event_data' => [],
            'addons' => [],
        ]]);

        $offer = app(SlotOffer::class);

        $this->assertArrayNotHasKey('09:00:00', $offer->offerableTimes($this->excursion, $this->date),
            'sin la excepción, una franja con 25 libres y mínimo 30 no se ofrece');

        $withException = $offer->offerableTimes($this->excursion, $this->date, sale: CounterSale::byOperator(true));
        $this->assertArrayHasKey('09:00:00', $withException,
            'con la excepción, esa misma franja tiene que ofrecerse');
        $this->assertSame(25, $withException['09:00:00']['max_quantity']);
    }

    /**
     * ⚠️ **EL CONTROL: el suelo baja a 1, no a 0.** Una franja sin una sola plaza libre sigue fuera
     * con la excepción puesta — eso es AFORO (`AFORO-01`), no el mínimo del pack, y son cosas
     * distintas aunque las corte el mismo `if`.
     */
    public function test_a_full_slot_is_never_offered_not_even_with_the_exception(): void
    {
        app(OrderCreator::class)->createPendingOrder($this->customer(), [[
            'ticket_type_id' => $this->excursion->id,
            'date' => $this->date,
            'time' => '09:00:00',
            'qty' => 100,
            'event_data' => [],
            'addons' => [],
        ]]);

        $this->assertArrayNotHasKey(
            '09:00:00',
            app(SlotOffer::class)->offerableTimes($this->excursion, $this->date, sale: CounterSale::byOperator(true)),
        );
    }

    // ─── 4 · el permiso, re-exigido en el punto de ejecución ─────────────────────────────────────

    /**
     * ⚠️⚠️ **`SEC-04`: el permiso se decide al EJECUTAR, no al pintar.** `$cart` es estado de un
     * componente Livewire y viaja al navegador, así que aquí se le pone a mano el `below_minimum`
     * que el interruptor pondría — exactamente lo que puede hacer alguien sin el permiso.
     */
    public function test_a_forged_cart_line_without_the_permission_does_not_create_the_order(): void
    {
        $customer = $this->customer();

        Livewire::actingAs($this->operator(below: false))
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('cart', [[
                'ticket_type_id' => $this->excursion->id,
                'date' => $this->date,
                'time' => '09:00:00',
                'qty' => 20,
                'event_data' => [],
                'addons' => [],
                'label' => 'Excursión',
                'when' => Carbon::parse($this->date)->format('d/m/Y').' 09:00',
                'line_total_cents' => 300_00,
                'below_minimum' => true,
            ]])
            ->set('data.payment_method', ManualOrderFulfiller::METHOD_CASH)
            ->set('data.source', 'counter')
            ->call('create');

        $this->assertSame(0, $customer->orders()->count(), 'sin el permiso, el mínimo rechaza aunque la línea venga marcada');
    }

    /**
     * ⚠️ La otra mitad de «ocultar no es autorizar»: el caso de arriba prueba que sin permiso no se
     * puede, y éste que **con permiso se puede LLEGAR**. Sin él, la feature podría estar entera y
     * correcta en el dominio y ser inalcanzable desde la pantalla, con toda la suite en verde.
     */
    public function test_the_toggle_is_offered_only_to_an_operator_with_the_permission(): void
    {
        $withPermission = Livewire::actingAs($this->operator(below: true))
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_PRODUCT)
            ->call('pickProduct', $this->excursion->id);

        $withPermission->assertSee(__('admin.orders.create_manual.below_minimum_label'));

        $without = Livewire::actingAs($this->operator(below: false))
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_PRODUCT)
            ->call('pickProduct', $this->excursion->id);

        $without->assertDontSee(__('admin.orders.create_manual.below_minimum_label'));
    }

    public function test_the_operator_with_the_permission_creates_the_order_and_it_is_audited(): void
    {
        $customer = $this->customer();

        Livewire::actingAs($this->operator(below: true))
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('cart', [[
                'ticket_type_id' => $this->excursion->id,
                'date' => $this->date,
                'time' => '09:00:00',
                'qty' => 20,
                'event_data' => [],
                'addons' => [],
                'label' => 'Excursión',
                'when' => Carbon::parse($this->date)->format('d/m/Y').' 09:00',
                'line_total_cents' => 300_00,
                'below_minimum' => true,
            ]])
            ->set('data.payment_method', ManualOrderFulfiller::METHOD_CASH)
            ->set('data.source', 'counter')
            ->call('create');

        $order = $customer->orders()->first();
        $this->assertNotNull($order, 'con el permiso, el pedido se crea');
        $this->assertSame(300_00, (int) $order->total);

        $audit = AuditLog::where('action', 'orders.created_manual')->latest('id')->first();
        $this->assertNotNull($audit);
        $trace = $audit->payload['below_pack_minimum'] ?? null;
        $this->assertIsArray($trace, 'la excepción deja rastro');
        $this->assertSame(20, $trace[0]['quantity']);
        $this->assertSame(30, $trace[0]['pack_min_qty'], 'el rastro dice de qué mínimo se bajó');
    }

    /**
     * ⚠️ **EL CONTROL del rastro**: un pedido normal NO escribe la clave. Un `false` en cada pedido
     * sería ruido que entierra la señal — la misma regla que D7 en la edición.
     */
    public function test_a_normal_order_leaves_no_below_minimum_trace(): void
    {
        $customer = $this->customer();

        Livewire::actingAs($this->operator(below: true))
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('cart', [[
                'ticket_type_id' => $this->excursion->id,
                'date' => $this->date,
                'time' => '09:00:00',
                'qty' => 40,
                'event_data' => [],
                'addons' => [],
                'label' => 'Excursión',
                'when' => Carbon::parse($this->date)->format('d/m/Y').' 09:00',
                'line_total_cents' => 600_00,
                'below_minimum' => false,
            ]])
            ->set('data.payment_method', ManualOrderFulfiller::METHOD_CASH)
            ->set('data.source', 'counter')
            ->call('create');

        $this->assertNotNull($customer->orders()->first());

        $audit = AuditLog::where('action', 'orders.created_manual')->latest('id')->first();
        $this->assertArrayNotHasKey('below_pack_minimum', $audit->payload);
    }

    /**
     * ⚠️⚠️ **El rastro describe lo VENDIDO, no lo pedido** — y este es el caso que lo distingue.
     *
     * El operador marca la excepción con la línea de 20 en el carrito y, mientras tanto, alguien
     * BAJA el mínimo del producto de 30 a 10 desde el panel. Al cobrar, esa venta ya es legal: la
     * intención venía marcada pero **no se usó ninguna excepción**, así que no hay nada que
     * registrar. Sin este caso, la condición que lo distingue es código que ninguna guarda alcanza
     * (lo dijo el arnés: la mutación «escribe el rastro siempre» pasaba en verde).
     */
    public function test_a_marked_line_that_ends_up_legal_leaves_no_trace(): void
    {
        $customer = $this->customer();
        $operator = $this->operator(below: true);

        $component = Livewire::actingAs($operator)
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('cart', [[
                'ticket_type_id' => $this->excursion->id,
                'date' => $this->date,
                'time' => '09:00:00',
                'qty' => 20,
                'event_data' => [],
                'addons' => [],
                'label' => 'Excursión',
                'when' => Carbon::parse($this->date)->format('d/m/Y').' 09:00',
                'line_total_cents' => 300_00,
                'below_minimum' => true,
            ]])
            ->set('data.payment_method', ManualOrderFulfiller::METHOD_CASH)
            ->set('data.source', 'counter');

        // El catálogo cambia entre añadir la línea y cobrarla.
        $this->excursion->update(['min_qty' => 10]);

        $component->call('create');

        $this->assertNotNull($customer->orders()->first());

        $audit = AuditLog::where('action', 'orders.created_manual')->latest('id')->first();
        $this->assertArrayNotHasKey('below_pack_minimum', $audit->payload,
            'vender 20 con el mínimo ya en 10 no es una excepción: no deja rastro');
    }
}
