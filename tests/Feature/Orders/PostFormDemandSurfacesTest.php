<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Notifications\GuestFormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Las superficies de DEMANDA de los extras de venta posterior** (D15 de
 * `specs/complementos-post-reserva.md` §4.7·quinquies, T3 de `DECISIONES #413`).
 *
 * ⚠️⚠️ Lo que estas guardas protegen es que la feature **se venda**. Medido antes de construirla: el
 * único correo que lleva al post-form habla solo de «los datos de cada invitado», el rótulo del cajón
 * dice «Ver o editar el formulario de reserva» —que no insinúa que ahí se compre— y el aviso de la
 * cuenta **muere en cuanto el cliente completa las fichas**, que es justo cuando le quedan extras por
 * elegir. *La feature podía construirse entera y no venderse un solo cubo de refrescos.*
 *
 * ⚠️ Y la otra mitad de cada guarda es la INSTALACIÓN SIN EXTRAS, que es el caso por defecto: ninguna
 * de las tres superficies puede prometer algo que ese parque no ofrece.
 */
class PostFormDemandSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);
    }

    // ── El CORREO del post-form ────────────────────────────────────────────────────────────────

    public function test_the_post_form_email_names_the_extras_when_there_are_any(): void
    {
        $item = $this->party();
        $this->attachPostFormAddon($item->ticketType);

        $lines = (new GuestFormRequest($item->fresh(['ticketType.addons', 'order', 'slot'])))
            ->toMail($item->order->user)->introLines;

        $this->assertContains(__('emails.guest_form.extras'), $lines);
    }

    /** Y la instalación que no configura ninguno —el caso por defecto— no promete nada. */
    public function test_without_open_extras_the_email_says_nothing_about_them(): void
    {
        $item = $this->party();

        $lines = (new GuestFormRequest($item))->toMail($item->order->user)->introLines;

        $this->assertNotContains(__('emails.guest_form.extras'), $lines);
    }

    /**
     * ⚠️ **Y tampoco cuando el plazo ya venció**: el correo sale al pagar, y un pack comprado la
     * víspera con un corte de 48 h no tiene extras que ofrecer. «Hay extras en el catálogo» y «este
     * cliente puede añadir algo» son dos preguntas distintas.
     */
    public function test_an_expired_cutoff_keeps_the_email_quiet(): void
    {
        $item = $this->party(daysAhead: 1);
        $this->attachPostFormAddon($item->ticketType, cutoff: 48);

        $lines = (new GuestFormRequest($item->fresh(['ticketType.addons', 'order', 'slot'])))
            ->toMail($item->order->user)->introLines;

        $this->assertNotContains(__('emails.guest_form.extras'), $lines);
    }

    // ── El dato que deja al cajón NOMBRARLOS ───────────────────────────────────────────────────

    public function test_the_api_says_whether_this_reservation_can_still_add_extras(): void
    {
        $item = $this->party();
        $user = $item->order->user;

        $this->actingAs($user)->getJson('/api/v1/me/orders')
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.items.0.can_add_extras', false);

        $this->attachPostFormAddon($item->ticketType);

        $this->actingAs($user)->getJson('/api/v1/me/orders')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.can_add_extras', true);
    }

    /**
     * ⚠️⚠️ **El coste no puede crecer con las RESERVAS.** `can_add_extras` pregunta por los enganches
     * del pack, y sin precargarlos son una consulta por reserva — el N+1 más fácil de meter en esta
     * API y el más difícil de ver, porque la respuesta es correcta.
     *
     * ⚠️ **Se miden reservas dentro de UN pedido, no pedidos**, o el presupuesto crecería con algo
     * que no es su sujeto.
     *
     * ⚠️⚠️ **Y escribirla destapó un N+1 PREEXISTENTE**: salía roja también con CONTROL —el campo
     * devuelto a secas— porque media docena de predicados de `OrderItemResource` preguntan por el
     * pedido de la línea y **la relación inversa no estaba puesta**, así que la lista del cliente
     * hacía una consulta por tarjeta. *Cuando un presupuesto acusa también al control, el defecto
     * está debajo del sujeto.* Hoy la pone `toArray()` antes de nada.
     */
    public function test_publishing_it_does_not_cost_a_query_per_reservation(): void
    {
        $user = User::factory()->create();
        $first = $this->party(user: $user);
        $this->attachPostFormAddon($first->ticketType);

        $withOne = $this->countQueriesListingOrders($user);

        // Tres reservas MÁS en el MISMO pedido, cada una con su pack y su enganche.
        foreach (range(1, 3) as $ignored) {
            $this->addPartyTo($first->order);
        }

        $this->assertSame(
            $withOne,
            $this->countQueriesListingOrders($user),
            'el coste crece con las reservas: falta el eager-load de `ticketType.addons`'
        );
    }

    private function countQueriesListingOrders(User $user): int
    {
        $this->actingAs($user)->getJson('/api/v1/me/orders')->assertOk();

        \DB::flushQueryLog();
        \DB::enableQueryLog();

        $this->actingAs($user)->getJson('/api/v1/me/orders')->assertOk();

        $count = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        return $count;
    }

    // ── Fixtures ───────────────────────────────────────────────────────────────────────────────

    /** Una fiesta pagada con su franja en el futuro. */
    private function party(int $daysAhead = 10, ?User $user = null): OrderItem
    {
        $this->counter++;

        $pack = $this->pack();

        // ⚠️ Una franja por fiesta y con hora distinta: `slots` tiene UNIQUE(zone_id, date,
        // start_time), así que dos fiestas del mismo día chocarían al sembrar.
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays($daysAhead)->toDateString(),
            'start_time' => sprintf('%02d:00:00', 9 + $this->counter),
            'end_time' => sprintf('%02d:00:00', 11 + $this->counter),
            'capacity' => 20, 'online_capacity' => 20,
        ]);

        $order = Order::create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'code' => 'JJ-PD'.str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 10000, 'tax' => 0, 'total' => 10000, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 10000, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (500000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'quantity' => 4,
            'unit_price' => 2500, 'seats' => 4, 'event_data' => ['celebrant' => 'Mara'],
        ]);

        return $item->fresh(['ticketType.addons', 'order', 'slot', 'children']);
    }

    /**
     * Otra fiesta DENTRO del mismo pedido, con su propio pack enganchado.
     *
     * ⚠️ Se crea aquí y no con `party()`: aquélla abre pedido propio, y mover el ítem después dejaría
     * el pedido vacío en la lista del cliente — o sea midiendo pedidos otra vez.
     */
    private function addPartyTo(Order $order): OrderItem
    {
        $this->counter++;

        $pack = $this->pack();
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(10)->toDateString(),
            'start_time' => sprintf('%02d:00:00', 9 + $this->counter),
            'end_time' => sprintf('%02d:00:00', 11 + $this->counter),
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        $this->attachPostFormAddon($pack);

        return $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'quantity' => 4,
            'unit_price' => 2500, 'seats' => 4, 'event_data' => ['celebrant' => 'Mara'],
        ]);
    }

    private function pack(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
    }

    private function attachPostFormAddon(TicketType $pack, int $cutoff = 48): void
    {
        $addon = TicketType::create([
            'name' => ['es' => 'Cubo de refrescos'], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 40,
        ]);
        Price::create([
            'priceable_type' => $addon->getMorphClass(), 'priceable_id' => $addon->id,
            'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->firstOrFail()->id,
            'amount_cents' => 1200, 'currency' => 'EUR',
        ]);
        $pack->configurableAddons()->attach($addon->id, [
            'position' => 1, 'quantity_mode' => ProductAddon::MODE_FIXED,
            'stage' => ProductAddon::STAGE_POSTFORM,
            'postform_cutoff_hours' => $cutoff, 'max_qty' => 10,
        ]);
        $pack->refresh();
    }
}
