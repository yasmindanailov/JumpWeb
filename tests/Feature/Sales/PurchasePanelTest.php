<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\RateResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 5 (Capa 1, flujo ROLLER) — Compra de entradas (sidebar): elegir entrada →
 * fecha → hora + cantidad (topada al aforo, precio del día) → carrito de líneas.
 * Precio y aforo se calculan en servidor (regla 12). El carrito vive en sesión.
 */
class PurchasePanelTest extends TestCase
{
    use RefreshDatabase;

    private string $today;

    private Zone $zone;

    private TicketType $jump1h;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08'); // lunes → tarifa normal, fechas deterministas

        $this->today = Carbon::today()->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        RateType::create(['key' => RateType::KEY_SPECIAL, 'label' => ['es' => 'Especial'], 'is_special' => true, 'weekdays' => [0, 6], 'priority' => 10]);

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);

        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->today,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 2, // aforo online pequeño para probar el tope
        ]);

        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->jump1h->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);
        $this->jump1h->prices()->create(['rate_type_id' => RateType::where('key', 'special')->value('id'), 'amount_cents' => 1200]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_starts_at_step_one_choosing_a_ticket(): void
    {
        Livewire::test(Purchase::class)
            ->assertSet('step', 1)
            ->assertSet('typeId', null)
            ->assertSee('Jump · 1 hora');     // el catálogo lista la entrada
    }

    public function test_choosing_a_ticket_advances_to_the_date(): void
    {
        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->assertSet('step', 2)
            ->assertSet('typeId', $this->jump1h->id);
    }

    public function test_calendar_marks_the_day_with_its_rate_type(): void
    {
        $type = (new RateResolver)->for(Carbon::today())->key;

        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->assertSeeHtml("selectDate('".$this->today."')") // día seleccionable
            ->assertSeeHtml("cal__day--{$type}");             // coloreado según su tarifa
    }

    public function test_can_navigate_to_a_month_with_availability(): void
    {
        $next = Carbon::today()->addMonthNoOverflow()->startOfMonth()->addDays(9);
        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $next->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);

        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('nextMonth')
            ->assertSet('month', $next->format('Y-m'));
    }

    public function test_picking_date_then_time_shows_quantity(): void
    {
        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->assertSet('step', 3)
            ->call('selectTime', '10:00:00')
            ->assertSet('time', '10:00:00')
            ->assertSet('qty', 1);   // arranca en 1 porque hay aforo
    }

    public function test_back_walks_the_steps_in_reverse(): void
    {
        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->assertSet('step', 3)
            ->call('back')->assertSet('step', 2)
            ->call('back')->assertSet('step', 1);
    }

    public function test_quantity_is_capped_by_online_capacity(): void
    {
        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00');

        foreach (range(1, 5) as $ignored) {
            $component->call('inc');
        }

        $component->assertSet('qty', 2); // online_capacity = 2
    }

    public function test_add_to_cart_requires_a_quantity(): void
    {
        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('dec')            // 1 → 0
            ->call('addToCart')
            ->assertHasErrors('selection')
            ->assertSet('cart', []);
    }

    public function test_add_to_cart_stores_the_line_and_opens_the_cart(): void
    {
        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->assertHasNoErrors()
            ->assertSet('step', 4)
            ->assertSet('typeId', null); // la selección queda limpia

        $cart = $component->get('cart');
        $this->assertCount(1, $cart);
        $this->assertEquals($this->jump1h->id, $cart[0]['ticket_type_id']);
        $this->assertSame($this->today, $cart[0]['date']);
        $this->assertSame('10:00:00', $cart[0]['time']);
        $this->assertEquals(1, $cart[0]['qty']);
    }

    public function test_cart_seats_count_against_capacity_on_the_next_add(): void
    {
        // La franja 10:00 tiene online_capacity = 2 (ver setUp).
        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('inc')          // 1 → 2 (tope del aforo)
            ->call('addToCart')    // 2 plazas en la cesta
            ->assertViewHas('cartCount', 1);

        // Vuelvo a la misma franja: el aforo ya está lleno por la cesta → 0 disponibles.
        $component
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->assertViewHas('maxQty', 0)
            ->assertSet('qty', 0)        // selectTime no arranca en 1 si no queda aforo
            ->call('addToCart')
            ->assertHasErrors('selection');

        $cart = $component->get('cart');
        $this->assertCount(1, $cart);
        $this->assertEquals(2, $cart[0]['qty']); // no se metió ninguna de más
    }

    public function test_add_another_returns_to_the_catalog(): void
    {
        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('addAnother')
            ->assertSet('step', 1)
            ->assertSet('typeId', null);
    }

    public function test_same_ticket_date_time_merges_quantities(): void
    {
        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart');

        $cart = $component->get('cart');
        $this->assertCount(1, $cart);            // una sola línea
        $this->assertEquals(2, $cart[0]['qty']); // cantidades sumadas
    }

    public function test_remove_line_empties_cart_and_returns_to_step_one(): void
    {
        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart');

        $this->assertCount(1, $component->get('cart'));

        $component->call('removeLine', 0)->assertSet('step', 1);

        $this->assertSame([], $component->get('cart'));
    }

    public function test_cart_persists_in_session(): void
    {
        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart');

        $this->assertEquals($this->jump1h->id, session('purchase.cart')[0]['ticket_type_id'] ?? null);
    }

    public function test_total_uses_the_day_rate_on_the_server(): void
    {
        $price = (new RateResolver)->priceCents($this->jump1h, Carbon::parse($this->today));
        $expected = number_format($price / 100, 2, ',', '.').' €';

        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')   // qty = 1, precio del día visible
            ->assertSee($expected);
    }

    public function test_product_window_filters_the_offered_times(): void
    {
        // Parque abierto 10:00–21:00 hoy y una franja extra a las 18:00.
        OpeningHour::create([
            'weekday' => Carbon::parse($this->today)->dayOfWeek,
            'open_time' => '10:00:00', 'close_time' => '21:00:00',
        ]);
        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->today,
            'start_time' => '18:00:00', 'end_time' => '19:00:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);

        // La entrada solo se vende a partir de 2 h tras abrir (desde 12:00) → la franja de 10:00 cae fuera.
        $this->jump1h->update(['available_after_open_min' => 120]);

        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->assertViewHas('times', ['18:00:00']); // 10:00 excluida por la ventana
    }

    public function test_checkout_requires_a_non_empty_cart(): void
    {
        Livewire::test(Purchase::class)
            ->call('checkout')
            ->assertHasErrors('cart')
            ->assertSet('confirmed', false);
    }

    public function test_guest_checkout_opens_the_identification_step(): void
    {
        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('checkout')
            ->assertHasNoErrors()
            ->assertSet('step', 5)          // identificación (login/registro)
            ->assertSet('confirmed', false);

        $this->assertSame(0, Order::count()); // un invitado no crea pedido
    }

    public function test_authenticated_verified_checkout_advances_to_the_payment_step(): void
    {
        $user = User::factory()->create(); // factory verifica el email por defecto
        $this->actingAs($user);

        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('checkout')
            ->assertHasNoErrors()
            ->assertSet('step', 8)        // paso de pago (placeholder Redsys), antes de reservar
            ->assertSet('confirmed', false)
            ->assertSee('Redsys')          // aviso "pago próximamente"
            ->assertSee('Jump · 1 hora')   // resumen de la reserva a punto de confirmar
            ->assertSee('10,00 €');        // total del servidor

        $this->assertSame(0, Order::count()); // el pago va ANTES de crear el pedido
    }

    public function test_confirm_reservation_creates_a_pending_order(): void
    {
        $user = User::factory()->create(); // verificado
        $this->actingAs($user);

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('checkout')
            ->assertSet('step', 8)
            ->call('confirmReservation')
            ->assertHasNoErrors()
            // 5.5b (#104): el pago no es ya un placeholder. `confirmReservation` crea la reserva
            // firme + el `Payment` `pending` y avanza al paso 9 (redirect al sandbox Redsys).
            // El éxito (step 6) solo se alcanza tras la vuelta firmada — capa 5.5c.
            ->assertSet('confirmed', false)
            ->assertSet('step', 9)
            ->assertSet('cart', []);      // cesta vaciada

        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        // #105 (2026-05-26): con Redsys real, "firme" significa "retiene plaza durante
        // `sales.hold_minutes` mientras el cliente paga"; sólo la vuelta OK (5.5c) la fija
        // con `expires_at=null`. Si el cliente abandona, `orders:expire` libera el aforo.
        $this->assertNotNull($order->expires_at);
        $this->assertTrue($order->expires_at->isFuture());
        $this->assertCount(1, $order->items);
        $this->assertSame($order->code, $component->get('orderCode'));
        $this->assertSame([], session('purchase.cart'));
        // Detalles del Payment los cubre RedsysIdaTest; aquí solo verificamos que existe el link.
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_confirm_reservation_does_nothing_outside_the_payment_step(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Purchase::class)
            ->set('step', 4)
            ->call('confirmReservation')
            ->assertSet('step', 4);

        $this->assertSame(0, Order::count()); // sin pasar por el paso de pago no se crea nada
    }

    public function test_authenticated_unverified_checkout_goes_to_payment(): void
    {
        // Pay-first (decisión clienta 2026-06-14): un usuario identificado AUNQUE NO esté verificado
        // pasa al paso de PAGO (8), no a verificación (antes era el paso 7 con reserva provisional).
        // El pedido firme se crea —y se cobra— en confirmReservation; aquí todavía no hay pedido.
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('checkout')
            ->assertSet('step', 8)          // paso de pago
            ->assertSet('confirmed', false);

        $this->assertSame(0, Order::count()); // no se retiene reserva; se crea al pagar
        Notification::assertNothingSent();    // pay-first: sin email de verificación
    }

    public function test_confirmation_shows_the_order_summary(): void
    {
        $user = User::factory()->create(); // verificado
        $this->actingAs($user);

        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('inc')              // cantidad 2 (aforo online = 2)
            ->call('addToCart')
            ->call('checkout')
            ->assertSet('step', 8)         // paso de pago
            ->call('confirmReservation')
            // Tras 5.5b (#104), confirmReservation lleva al paso 9 (redirect a Redsys).
            // El total del servidor se firma en el payload y viaja en `Ds_Merchant_Amount`
            // (verificado en RedsysIdaTest). Aquí basta con afirmar el step.
            ->assertSet('step', 9);
    }

    public function test_unverified_user_can_pay_pay_first(): void
    {
        // Pay-first (regresión 2026-06-14): un usuario SIN verificar debe poder COMPLETAR el pago
        // (confirmReservation → paso 9, redirect a Redsys). Antes el candado `hasVerifiedEmail()` en
        // confirmReservation lo rebotaba al carrito al pulsar «pagar con tarjeta» aunque proceed() ya
        // le dejaba llegar al paso 8. El pago auto-verifica luego (RedsysReturnHandler).
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('checkout')
            ->assertSet('step', 8)         // pay-first: llega al pago aunque no esté verificado
            ->call('confirmReservation')
            ->assertSet('step', 9);        // y COMPLETA → redirect a Redsys (sin rebotar al carrito)

        // El pedido firme se creó con su pago pendiente (lo cobrará Redsys).
        $this->assertSame(1, Order::where('user_id', $user->id)->count());
    }

    public function test_registration_submitted_event_shows_generic_verification(): void
    {
        // El registro embebido avisa de forma genérica (sin código → anti-enumeración, #46).
        Livewire::test(Purchase::class)
            ->set('step', 5)
            ->dispatch('registration-submitted')
            ->assertSet('step', 7)
            ->assertSet('orderCode', null);
    }

    public function test_returning_from_verification_shows_the_confirmed_reservation(): void
    {
        session(['purchase.confirmed_code' => 'JJ-ABC123']);

        Livewire::test(Purchase::class)
            ->assertSet('step', 6)
            ->assertSet('orderCode', 'JJ-ABC123')
            ->assertSet('confirmed', true);

        $this->assertNull(session('purchase.confirmed_code')); // se consume al mostrarla
    }

    public function test_logged_in_event_advances_to_payment_then_confirms(): void
    {
        $user = User::factory()->create(); // verificado
        $this->actingAs($user);

        // Simula: el invitado llega al paso de identificación con su cesta y se identifica.
        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->set('step', 5)
            ->dispatch('logged-in')
            ->assertSet('step', 8);        // identificado + verificado → paso de pago

        $this->assertSame(0, Order::count()); // aún no hay pedido (el pago va antes)

        $component
            ->call('confirmReservation')
            // 5.5b (#104): tras confirmar, paso 9 = redirect al sandbox Redsys. La señal de
            // éxito (`confirmed=true`, step 6) la pone la vuelta firmada en 5.5c.
            ->assertSet('step', 9)
            ->assertSet('confirmed', false);

        $this->assertSame(1, Order::where('user_id', $user->id)->count());
    }

    public function test_incompatible_cart_in_session_is_discarded(): void
    {
        // Cesta de una versión anterior del flujo (visitas con 'lines', sin 'ticket_type_id').
        session(['purchase.cart' => [
            ['date' => $this->today, 'time' => '10:00:00', 'lines' => [$this->jump1h->id => 2]],
        ]]);

        Livewire::test(Purchase::class)
            ->assertSet('cart', [])   // se descarta el formato incompatible en vez de romper
            ->assertSet('step', 1);
    }

    // ---- Capa 2c: flujo de PACKS (cumpleaños) en la pestaña "Cumpleaños" ----

    /**
     * Crea el mundo de packs: zona "Cumpleaños" oculta, franjas horarias hoy, un pack 10–20 niños a
     * 15€/niño (montaje 60' + limpieza 30') y la config de cupo. Devuelve el pack.
     */
    private function seedPack(int $maxPerSlot = 5, int $maxGuests = 60, string $prepBlocks = '1', array $eventFields = []): TicketType
    {
        // Zona de packs: operativa (is_active=true → aparece en el flujo de compra) pero oculta
        // de la landing (show_in_landing=false). Antes se marcaba is_active=false solo para
        // ocultarla de la web, lo que la sacaba también del flujo de compra (#82, desacoplado).
        $zone = Zone::create(['slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false, 'position' => 3]);

        foreach (['10:00:00', '11:00:00', '12:00:00', '13:00:00', '14:00:00'] as $start) {
            Slot::create([
                'zone_id' => $zone->id, 'date' => $this->today,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 200, 'online_capacity' => 200,
            ]);
        }

        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'prep_before_min' => 60, 'prep_after_min' => 30,
            'min_qty' => 10, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'event_fields' => $eventFields,
        ]);
        $pack->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1500]);

        Setting::updateOrCreate(['key' => 'packs.max_per_slot'], ['key' => 'packs.max_per_slot', 'value' => (string) $maxPerSlot, 'group' => 'packs']);
        Setting::updateOrCreate(['key' => 'packs.max_guests_per_slot'], ['key' => 'packs.max_guests_per_slot', 'value' => (string) $maxGuests, 'group' => 'packs']);
        Setting::updateOrCreate(['key' => 'packs.prep_blocks_cupo'], ['key' => 'packs.prep_blocks_cupo', 'value' => $prepBlocks, 'group' => 'packs']);

        return $pack;
    }

    public function test_catalog_lists_entries_and_services_together(): void
    {
        // Ya NO hay pestañas que conmuten: el catálogo muestra entradas Y servicios a la vez,
        // en dos secciones por TIPO. Un solo render contiene ambos.
        $this->seedPack();

        Livewire::test(Purchase::class)
            ->assertSet('step', 1)
            ->assertSee(__('tickets.section_entries'))   // sección Entradas
            ->assertSee(__('tickets.section_services'))  // sección Servicios
            ->assertSee('Jump · 1 hora')                 // entrada
            ->assertSee('Cumpleaños Jump');              // servicio (pack), en el mismo render
    }

    public function test_pack_flow_books_a_party_into_the_cart(): void
    {
        $pack = $this->seedPack();

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->assertSet('step', 2)
            ->call('selectDate', $this->today)->call('goToTime')
            ->assertSet('step', 3)
            ->call('selectTime', '11:00:00')
            ->assertSet('qty', 10)                // arranca en el mínimo de invitados
            ->call('inc')
            ->call('addToCart')
            ->assertSet('step', 4)
            ->assertHasNoErrors();

        $cart = $component->get('cart');
        $this->assertCount(1, $cart);
        $this->assertSame($pack->id, $cart[0]['ticket_type_id']);
        $this->assertSame(11, $cart[0]['qty']);   // 11 invitados
    }

    public function test_pack_quantity_respects_min_and_max_guests(): void
    {
        $pack = $this->seedPack(); // 10–20 niños

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '11:00:00')
            ->assertSet('qty', 10);

        $component->call('dec')->assertSet('qty', 10); // no baja del mínimo

        for ($i = 0; $i < 15; $i++) {
            $component->call('inc');
        }
        $this->assertSame(20, $component->get('qty')); // no sube del máximo
    }

    public function test_pack_guest_cupo_caps_the_quantity(): void
    {
        // Cupo de 15 niños por franja, sin tope de fiestas → el stepper no pasa de 15.
        $pack = $this->seedPack(maxPerSlot: 0, maxGuests: 15);

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '11:00:00')
            ->assertViewHas('maxQty', 15);

        for ($i = 0; $i < 20; $i++) {
            $component->call('inc');
        }
        $this->assertSame(15, $component->get('qty'));
    }

    public function test_entry_and_pack_coexist_in_one_cart(): void
    {
        $pack = $this->seedPack();

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)   // entrada (pestaña por defecto)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('addAnother')
            ->call('selectType', $pack->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '11:00:00')
            ->call('addToCart')
            ->assertSet('step', 4);

        $cart = $component->get('cart');
        $this->assertCount(2, $cart); // entrada + pack en el mismo carrito
    }

    public function test_show_packs_event_lands_on_the_catalog_and_requests_services_open(): void
    {
        // El CTA de cumpleaños lleva al catálogo (paso 1) y pide a la vista abrir «Servicios»
        // (evento de navegador `catalog-open-services`, lo consume Alpine tras el render).
        $this->seedPack();

        Livewire::test(Purchase::class)
            ->dispatch('show-packs')
            ->assertSet('step', 1)
            ->assertSee('Cumpleaños Jump')
            ->assertDispatched('catalog-open-services');
    }

    public function test_verified_checkout_creates_a_pack_order(): void
    {
        $pack = $this->seedPack();
        $user = User::factory()->create(); // verificado
        $this->actingAs($user);

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '11:00:00') // qty = 10 (mínimo)
            ->call('addToCart')
            ->call('checkout')
            ->assertSet('step', 8)            // paso de pago
            ->call('confirmReservation')
            // 5.5b (#104): paso 9 = redirect a Redsys; éxito (step 6) llega en 5.5c.
            ->assertSet('step', 9)
            ->assertSet('confirmed', false);

        $order = Order::where('user_id', $user->id)->with('items')->first();
        $this->assertNotNull($order);
        $this->assertSame(15000, $order->total); // 1500 €/niño × 10 niños
        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertSame($pack->id, $item->ticket_type_id);
        $this->assertSame(10, $item->quantity);
        $this->assertSame(10, $item->seats);
    }

    public function test_pack_required_event_field_blocks_add_until_filled(): void
    {
        $pack = $this->seedPack(eventFields: [
            ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado/a']],
        ]);

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '11:00:00')
            ->assertSee('Homenajeado/a')        // el campo configurado se renderiza
            ->call('addToCart')
            ->assertHasErrors('selection')      // obligatorio sin rellenar → bloquea
            ->assertSet('step', 3);

        $this->assertCount(0, $component->get('cart'));

        $component
            ->set('eventData', ['celebrant' => 'Lucía'])
            ->call('addToCart')
            ->assertHasNoErrors()
            ->assertSet('step', 4);

        $cart = $component->get('cart');
        $this->assertSame('Lucía', $cart[0]['event_data']['celebrant']);
    }

    public function test_pack_event_data_persists_to_the_order(): void
    {
        $pack = $this->seedPack(eventFields: [
            ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado/a']],
        ]);
        $user = User::factory()->create(); // verificado
        $this->actingAs($user);

        Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '11:00:00')
            ->set('eventData', ['celebrant' => 'Lucía'])
            ->call('addToCart')
            ->call('checkout')
            ->assertSet('step', 8)
            ->call('confirmReservation')
            ->assertSet('step', 9);   // 5.5b (#104): redirect a Redsys

        $item = Order::where('user_id', $user->id)->first()->items->first();
        $this->assertSame('Lucía', $item->event_data['celebrant']);
    }

    // ---- Capa 3b: paso de COMPLEMENTOS (add-ons) ----

    /** Crea un complemento (type=addon) con precio y lo asocia a la entrada jump1h (pivote). */
    private function makeAddon(int $price = 300): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 50,
        ]);
        $addon->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => $price]);
        DB::table('product_addons')->insert(['product_id' => $this->jump1h->id, 'addon_id' => $addon->id, 'position' => 0]);

        return $addon;
    }

    public function test_complements_show_in_the_product_step(): void
    {
        $this->makeAddon();

        // Los complementos aplicables se ofrecen EN el paso del producto (no en un paso aparte).
        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->assertSee('Calcetines');
    }

    public function test_complements_are_nested_under_their_product_in_cart_and_order(): void
    {
        $addon = $this->makeAddon(300);
        $user = User::factory()->create(); // verificado
        $this->actingAs($user);

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')   // entrada × 1 (1000)
            ->call('incAddon', $addon->id)
            ->call('incAddon', $addon->id)     // complemento × 2 (600)
            ->call('addToCart')
            ->assertSet('step', 4)
            ->assertHasNoErrors();

        // El complemento queda ANIDADO en la línea del producto en la cesta.
        $cart = $component->get('cart');
        $this->assertCount(1, $cart);
        $this->assertSame($addon->id, $cart[0]['addons'][0]['ticket_type_id']);
        $this->assertSame(2, $cart[0]['addons'][0]['qty']);

        // Checkout → pedido con producto + complemento como HIJO (parent_item_id).
        // 5.5b (#104): tras confirmReservation, step 9 (redirect Redsys), no 6.
        $component->call('checkout')->assertSet('step', 8)->call('confirmReservation')->assertSet('step', 9);

        $order = Order::where('user_id', $user->id)->with('items')->first();
        $this->assertCount(2, $order->items);
        $this->assertSame(1600, $order->total); // 1000 entrada + 2 × 300 complemento

        $product = $order->items->firstWhere('ticket_type_id', $this->jump1h->id);
        $addonItem = $order->items->firstWhere('ticket_type_id', $addon->id);
        $this->assertNull($addonItem->slot_id);                  // sin franja
        $this->assertSame($product->id, $addonItem->parent_item_id); // agrupado bajo su producto
    }
}
