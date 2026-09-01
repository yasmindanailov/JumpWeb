<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 4 · paso 4.0b — lo que le faltaba al pedido para poder pintar el resumen de la reserva
 * confirmada (`docs/specs/sidebar-spa.md` §4.4, hueco 4).
 *
 * Seis campos que el sidebar componía por su cuenta y que ningún endpoint publicaba. Los pedidos se
 * crean con `OrderCreator` —el de verdad— en vez de a mano: comparar contra el pedido REAL es lo que
 * convierte «el desglose coincide» en un hecho comprobado y no en una promesa.
 *
 * Dos casos importan más que los otros cuatro:
 *  · el desglose de señal es **por RESERVA**, no por pedido: en una cesta mixta entrada+pack,
 *    etiquetar el agregado engaña, que es lo que #225 F3 vino a arreglar;
 *  · `guest_form_pending` **no es el `any()` de las líneas**, y un cliente que lo agregara a mano
 *    obtendría otra cosa.
 */
class OrderSummaryFieldsTest extends ApiTestCase
{
    private User $user;

    private TicketType $entry;

    private TicketType $pack;

    private TicketType $addon;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        // Rejilla de 10:00 a 15:00: un pack de 120 min necesita hueco para su fiesta entera, y sin
        // rejilla suficiente `OrderCreator` lo rechaza con `pack_sold_out_line`.
        foreach (range(10, 15) as $hour) {
            Slot::create([
                'zone_id' => $zone->id, 'date' => $this->date,
                'start_time' => sprintf('%02d:00:00', $hour), 'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 200, 'online_capacity' => 200,
            ]);
        }

        // El cupo de los packs es *data-driven* y vive en `settings`, no en una columna del producto.
        foreach ([PackAvailability::SETTING_MAX_PER_SLOT => '5',
            PackAvailability::SETTING_MAX_GUESTS_PER_SLOT => '60'] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['key' => $key, 'value' => $value, 'group' => 'packs']);
        }
        Setting::flushMemo();

        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 990]);

        // Pack CON SEÑAL: paga 30 € online y el resto en puerta. Es el que hace interesante el
        // desglose — y el que en una cesta mixta se etiquetaría mal con el agregado del pedido.
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 120, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 2,
            'min_qty' => 10, 'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 3000,
        ]);
        $this->pack->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1500]);

        $this->addon = TicketType::create([
            'name' => ['es' => 'Tarta'], 'type' => TicketType::TYPE_ADDON, 'zone_id' => $zone->id,
            'is_sellable' => true, 'is_active' => true, 'position' => 3,
        ]);
        $this->addon->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 2000]);

        // Y colgado del pack por el pivote: un complemento que no cuelga del producto no se ofrece
        // ni se cobra, así que sin esto la línea no tendría complementos que serializar.
        $this->pack->addons()->attach($this->addon->id, [
            'position' => 1, 'is_included' => false, 'is_mandatory' => false,
            'allow_extra' => false, 'included_quantity' => 0,
        ]);
    }

    /** @param array<int, array<string, mixed>> $cart */
    /**
     * Cobra el pedido COMO lo hace el canal real: estado + `paid_at` (el predicado de «este pedido se
     * cobró», `DECISIONES #127`) + el cobro de lo que sus líneas aportan online. Un pedido `paid`
     * sin `Payment` no lo produce nadie, y desde la T2 del libro el saldo lo sabe —la identidad I2
     * (cobrado == Σ online al nacer) no cierra— y respondería «en revisión» en vez de la clase de
     * saldo que el caso quiere ejercitar.
     */
    private function markPaid(Order $order): void
    {
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->onlineDueCents(), 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => Carbon::now(),
            'gateway_order' => sprintf('%010d', $order->id),
        ]);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
    }

    private function order(array $cart): Order
    {
        return app(OrderCreator::class)->createPendingOrder($this->user, $cart, OrderCreator::checkoutHoldUntil());
    }

    /** @return array<string, mixed> */
    private function entryLine(int $qty = 2): array
    {
        return ['ticket_type_id' => (int) $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => $qty];
    }

    /** @return array<string, mixed> */
    private function packLine(int $guests = 10): array
    {
        return ['ticket_type_id' => (int) $this->pack->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => $guests];
    }

    private function show(Order $order): TestResponse
    {
        return $this->actingAs($this->user)
            ->getJson(self::ROOT.'/orders/'.$order->code)
            ->assertOk()
            ->assertValidResponse(200);
    }

    // ── Lo que el cliente no puede deducir ────────────────────────────────────────────────────

    public function test_a_pack_and_an_entry_are_told_apart(): void
    {
        $order = $this->order([$this->entryLine(), $this->packLine()]);

        $response = $this->show($order);

        $flags = collect($response->json('items'))->pluck('is_pack', 'product_name');

        $this->assertFalse($flags['Entrada · 1 hora']);
        $this->assertTrue($flags['Cumpleaños']);
    }

    public function test_the_raw_start_time_travels(): void
    {
        $order = $this->order([$this->entryLine()]);

        $this->show($order)->assertJsonPath('items.0.start_time', '10:00:00');
    }

    // ── El desglose es POR RESERVA, no por pedido ─────────────────────────────────────────────

    /**
     * ⚠️ **El caso de #225 F3.** Una cesta mixta: la entrada se paga entera online y el pack deja
     * resto en puerta. Si el desglose fuera del PEDIDO, las dos líneas dirían lo mismo y la entrada
     * anunciaría un «resto en el parque» que no existe.
     */
    public function test_the_deposit_breakdown_is_per_reservation_in_a_mixed_cart(): void
    {
        $order = $this->order([$this->entryLine(), $this->packLine()]);
        $this->markPaid($order);

        $items = collect($this->show($order)->json('items'))->keyBy('product_name');

        // La entrada: pagada entera, nada en el parque, sin aviso de señal. (Desde la T3·1 del libro
        // el desglose de la reserva es SU libro: el saldo dice de qué clase es, no un canal.)
        $this->assertSame('settled', $items['Entrada · 1 hora']['ledger']['balance']['kind']);
        $this->assertFalse($items['Entrada · 1 hora']['shows_deposit_note']);

        // El pack: parte online y parte en el parque, con su aviso.
        $this->assertSame('pay_at_park', $items['Cumpleaños']['ledger']['balance']['kind']);
        $this->assertGreaterThan(0, $items['Cumpleaños']['ledger']['balance']['cents']);
        $this->assertGreaterThan(0, $items['Cumpleaños']['ledger']['paid_cents']);
        $this->assertTrue($items['Cumpleaños']['shows_deposit_note']);
    }

    /**
     * El aviso son TRES condiciones, y sin el pedido pagado no procede: enseñar «señal pagada» sobre
     * una reserva que todavía no se ha cobrado sería mentir.
     */
    public function test_an_unpaid_order_shows_no_deposit_note(): void
    {
        $order = $this->order([$this->packLine()]);

        $items = collect($this->show($order)->json('items'))->keyBy('product_name');

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertFalse($items['Cumpleaños']['shows_deposit_note']);
        // Los NÚMEROS sí viajan: es el aviso lo que no procede, no el libro. Sin cobrar, el saldo es
        // «pendiente de pagar por web» y lleva aparte lo que además se pagará en el parque.
        $this->assertSame('pay_online', $items['Cumpleaños']['ledger']['balance']['kind']);
        $this->assertGreaterThan(0, $items['Cumpleaños']['ledger']['balance']['rest_at_park_cents']);
    }

    /** Un producto sin señal nunca lo enseña, por pagado que esté el pedido. */
    public function test_a_product_without_deposit_never_shows_the_note(): void
    {
        $order = $this->order([$this->entryLine()]);
        $this->markPaid($order);

        $this->show($order)->assertJsonPath('items.0.shows_deposit_note', false);
    }

    /**
     * ⚠️ **La mutación que motivó este caso**: `shows_deposit_note` SIN la clase del saldo (solo
     * «cobrado ∧ hay señal») pasaba en VERDE — ningún caso tenía un pack con señal cuyo saldo ya no
     * fuera «a pagar en el parque». Y ése es el estado NORMAL de una fiesta después de celebrarse: el
     * resto se liquidó en la puerta y el libro dice `settled`, con la liquidación fechada al fin de
     * la franja. Avisar ahí «el resto se paga en el parque» sería anunciar una deuda que no existe.
     */
    public function test_the_note_goes_away_once_the_rest_was_settled_at_the_park(): void
    {
        $order = $this->order([$this->packLine()]);
        $this->markPaid($order);

        // La visita ya pasó: la franja de la reserva, a ayer.
        $order->items()->whereNull('parent_item_id')->firstOrFail()->slot
            ->forceFill(['date' => Carbon::yesterday()->toDateString()])->save();

        $item = $this->show($order)->json('items.0');

        $this->assertTrue($item['ledger']['has_deposit'], 'la señal es un HECHO del libro: no se borra al liquidar');
        $this->assertSame('settled', $item['ledger']['balance']['kind']);
        $this->assertNotNull(collect($item['ledger']['settlements'])->firstWhere('kind', 'gate'), 'el resto consta como liquidado en el parque');
        $this->assertFalse($item['shows_deposit_note']);
    }

    /**
     * Y una reserva CANCELADA con señal tampoco: lo que hay es dinero que devolver, no resto que pagar.
     *
     * ⚠️ Aquí `has_deposit` es FALSE a propósito: el libro cuenta el reparto de las líneas VIVAS (T2,
     * la definición que puentea con el modelo viejo a nivel de pedido), así que la nota cae por la
     * segunda condición y no por la clase — este caso NO discrimina la mutación de la clase; el de
     * arriba sí. Está para que «cancelada con señal» tenga su respuesta escrita, no deducida.
     */
    public function test_a_cancelled_reservation_with_a_deposit_shows_no_note(): void
    {
        $order = $this->order([$this->packLine()]);
        $this->markPaid($order);
        $order->items()->whereNull('parent_item_id')->firstOrFail()->forceFill(['cancelled_at' => now()])->save();

        $item = $this->show($order)->json('items.0');

        $this->assertFalse($item['ledger']['has_deposit'], 'el reparto de una línea cancelada ya no es «señal» del libro');
        $this->assertSame('refund_pending', $item['ledger']['balance']['kind']);
        $this->assertLessThan(0, $item['ledger']['balance']['cents'], 'la señal cobrada ya no respalda producto');
        $this->assertFalse($item['shows_deposit_note']);
    }

    // ── Complementos ──────────────────────────────────────────────────────────────────────────

    public function test_an_addon_publishes_its_free_units(): void
    {
        $order = $this->order([
            $this->packLine() + ['addons' => [['ticket_type_id' => (int) $this->addon->id, 'qty' => 1]]],
        ]);

        $addons = collect($this->show($order)->json('items'))
            ->firstWhere('product_name', 'Cumpleaños')['addons'];

        $this->assertNotEmpty($addons, 'el complemento no se ha serializado');
        $this->assertArrayHasKey('free_quantity', $addons[0]);
        $this->assertIsInt($addons[0]['free_quantity']);
    }

    // ── El agregado que NO es el agregado ─────────────────────────────────────────────────────

    /**
     * ⚠️ **`guest_form_pending` no es `any(items[].needs_guest_form)`.** El servidor descarta primero
     * las líneas CANCELADAS. Este test monta justo ese caso: la única línea que pide formulario está
     * cancelada, así que la línea sigue diciendo `needs_guest_form: true` —es verdad de la línea— y
     * el pedido dice `guest_form_pending: false`, que es la verdad que importa.
     *
     * Un cliente que agregara el campo de las líneas prometería un formulario que nadie va a pedir.
     */
    public function test_a_cancelled_line_does_not_keep_the_order_waiting_for_a_guest_form(): void
    {
        $this->pack->forceFill(['guest_fields' => [
            ['key' => 'nombre', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
        ]])->save();

        $order = $this->order([$this->packLine()]);

        $principal = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $this->assertTrue($principal->needsGuestForm(), 'el fixture no pide formulario: el test no probaría nada');

        $before = $this->show($order->fresh());
        $before->assertJsonPath('guest_form_pending', true);

        // Se cancela la ÚNICA línea que lo pedía.
        $principal->forceFill(['cancelled_at' => now()])->save();

        $after = $this->show($order->fresh());

        $after->assertJsonPath('guest_form_pending', false);
        // Y la línea sigue diciendo la verdad SOBRE SÍ MISMA: los dos campos no son el mismo dato.
        $after->assertJsonPath('items.0.needs_guest_form', true);
    }
}
