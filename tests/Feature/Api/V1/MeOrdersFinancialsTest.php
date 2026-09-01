<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\ManualOrderFulfiller;
use App\Domain\Booking\Services\Movement;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\Settlement;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * **El LIBRO de un pedido: la API publica exactamente lo que compone el dominio** (T3·1 de
 * `specs/desglose-libro.md` §6.3, `DECISIONES #305`).
 *
 * ## De dónde viene, en tres relevos
 *
 * 1. `AccountPageCaptureTest` inventarió lo que `/mi-cuenta/pedidos` enseñaba y la API no publicaba.
 * 2. `AccountFinancialParityTest` lo relevó comparando las dos superficies mientras la página vivía.
 * 3. Este fichero vigiló el CABLEADO del desglose de dos ejes (`#127`); desde la T3·1 vigila el del
 *    libro: que cada campo de `ledger` sea el que `Booking\Services\OrderBook` compone, que lo
 *    publicado SUME entre sí, y que la clase del saldo la decida el servidor.
 *
 * ⚠️ **La guarda de la guarda va primero**: un fixture que no ejercitara el libro dejaría todo esto
 * comparando un nacimiento contra un nacimiento y pasando para siempre (`DECISIONES #63`).
 */
class MeOrdersFinancialsTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        // Reloj parado: el pedido lleva fechas y `isFinishedInPractice()` compara contra ahora
        // (`DECISIONES #64`).
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * **La guarda de la guarda: el fixture ejercita de verdad el libro.** Sin este caso, un fixture
     * que dejara de producir gestiones —o de cancelar la línea— haría que todo lo de abajo comparara
     * un solo movimiento y pasara solo.
     */
    public function test_the_fixture_actually_exercises_the_book(): void
    {
        [, $order] = $this->orderWithEverything();
        $book = OrderBook::forOrder($order);

        $kinds = array_map(fn (Movement $m): string => $m->kind, $book->movements);
        $this->assertContains(Movement::KIND_BOOKING, $kinds);
        $this->assertContains(Movement::KIND_EDIT, $kinds, 'sin una gestión el libro es solo el nacimiento');
        $this->assertContains(Movement::KIND_CANCEL, $kinds, 'sin una cancelación no hay línea que reste');
        $this->assertNotEmpty($book->settlements, 'sin un cobro no hay nada que cotejar');
        $this->assertTrue($book->hasDeposit, 'el pedido ya no lleva señal');
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind, 'el saldo tiene que ser positivo: es el caso que mezcla lo que se debe y lo que se paga');
        $this->assertNotSame((int) $order->total, $book->totalCents, 'el Total coincide con lo facturado: la sonda no distingue los dos');
    }

    /**
     * **Cada campo publicado es el que compone el dominio.** Comparar contra el DOMINIO y no contra un
     * número escrito a mano es lo que hace que esta guarda siga valiendo cuando cambie una tarifa del
     * fixture: lo que vigila es el cableado del recurso, no una foto de importes.
     */
    public function test_the_api_publishes_exactly_what_the_domain_composes(): void
    {
        [$user, $order] = $this->orderWithEverything();
        $book = OrderBook::forOrder($order);

        $json = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0');
        $ledger = $json['ledger'];

        $this->assertSame($book->totalCents, $ledger['total_cents'], 'lo que vale hoy');
        $this->assertSame($book->paidCents, $ledger['paid_cents'], 'lo ya saldado');
        $this->assertSame([
            'kind' => $book->balance->kind,
            'cents' => $book->balance->cents,
            'rest_at_park_cents' => $book->balance->restAtParkCents,
        ], $ledger['balance'], 'el saldo y su clase');
        $this->assertSame(array_map(fn (Movement $m): array => [
            'kind' => $m->kind, 'label' => $m->label, 'amount_cents' => $m->amountCents,
            'occurred_at' => $m->occurredAt, 'occurred_label' => $m->occurredLabel, 'reservation_id' => $m->reservationId,
        ], $book->movements), $ledger['movements'], 'las líneas de valor, con sus etiquetas y su orden');
        $this->assertSame(array_map(fn (Settlement $s): array => [
            'kind' => $s->kind, 'label' => $s->label, 'amount_cents' => $s->amountCents,
            'occurred_at' => $s->occurredAt, 'occurred_label' => $s->occurredLabel, 'status' => $s->status, 'method' => $s->method,
        ], $book->settlements), $ledger['settlements'], 'las líneas de dinero');
        $this->assertSame($book->hasDeposit, $ledger['has_deposit']);
        $this->assertSame($book->isConsistent, $ledger['is_consistent']);
        $this->assertSame($book->note, $ledger['note']);

        $this->assertSame($order->onlineDueCents(), $json['online_amount_cents'], 'lo que se cobraría al pagar ahora sigue fuera del libro');
    }

    /**
     * ⚠️⚠️ **LO QUE SE PUBLICA TIENE QUE SUMAR.** Es la identidad del libro (I3) vista desde la
     * pantalla: el Total es la Σ de las líneas de valor, el saldo es Total − Pagado, y lo pagado es lo
     * que las liquidaciones CON ÉXITO suman. Se recorre sobre escenarios distintos: el hueco de la
     * versión anterior no estaba en la aserción, estaba en el fixture.
     */
    public function test_what_is_published_adds_up_in_every_scenario(): void
    {
        $escenarios = [
            'con señal, una gestión y una cancelación' => fn () => $this->orderWithEverything(),
            'con la señal ya liquidada en el parque' => fn () => $this->orderWithSettledDeposit(),
        ];

        foreach ($escenarios as $nombre => $montar) {
            [$user, $order] = $montar();
            $l = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');

            $this->assertTrue($l['is_consistent'], "$nombre · el fixture no cierra: el caso no mide lo que dice");
            $this->assertSame(
                $l['total_cents'], array_sum(array_column($l['movements'], 'amount_cents')),
                "$nombre · I3 · las líneas de valor publicadas no suman el Total",
            );
            $this->assertSame(
                $l['paid_cents'],
                array_sum(array_map(fn (array $s): int => $s['status'] === 'succeeded' ? $s['amount_cents'] : 0, $l['settlements'])),
                "$nombre · lo pagado no es la Σ de las liquidaciones con éxito",
            );
            $this->assertSame(
                $l['total_cents'] - $l['paid_cents'], $l['balance']['cents'],
                "$nombre · el saldo no es Total − Pagado",
            );
            // ⚠️ La guarda de la guarda: que el escenario EJERCITE el libro, o compararía un solo renglón.
            $this->assertGreaterThanOrEqual(1, count($l['settlements']), "$nombre · el fixture no cobra nada");
        }
    }

    /**
     * ⚠️⚠️ **La CLASE del saldo la decide el servidor**, y no se deduce del signo: «a devolver en el
     * parque» y «pendiente de devolución» son el mismo número con distinta salida —según haya visita
     * o no—, y con la señal ya liquidada el saldo es CERO aunque el pedido tenga movimientos.
     */
    public function test_the_balance_kind_is_decided_by_the_server(): void
    {
        [$user, $order] = $this->orderWithSettledDeposit();
        $l = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');

        $this->assertSame(Balance::KIND_SETTLED, $l['balance']['kind']);
        $this->assertSame(0, $l['balance']['cents']);
        $this->assertSame($l['total_cents'], $l['paid_cents'], 'todo saldado: pagado == total');

        // Y la liquidación en el parque es una LÍNEA con su fecha, no un canal mudo.
        $gate = collect($l['settlements'])->firstWhere('kind', 'gate');
        $this->assertNotNull($gate, 'la visita pasó y el resto se dio por liquidado: tiene que verse');
        $this->assertSame(3100, $gate['amount_cents'], 'el resto de la señal');
        $this->assertSame(__('tickets.journal.gate'), $gate['label']);
        $this->assertSame('20/05/2026', $gate['occurred_label'], 'fechada al fin de la franja, con su fecha civil');

        // Un pedido cancelado sin visita por delante NO dice «en el parque»: el operador decide el canal.
        $order->update(['status' => Order::STATUS_CANCELLED]);
        $order->cancelLiveItems($user);
        $l = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');

        $this->assertSame(Balance::KIND_REFUND_PENDING, $l['balance']['kind']);
        $this->assertSame(-6000, $l['balance']['cents'], 'la señal cobrada, a devolver; con signo');
    }

    /**
     * **Sin cobrar, el saldo es «pendiente de pagar por web»** y lleva aparte lo que además se pagará
     * en el parque — publicado, no restado. Y la FRASE lo dice con palabras.
     */
    public function test_an_uncollected_order_owes_its_money_online_and_says_so(): void
    {
        [$user, $order] = $this->orderWithSettledDeposit();
        $order->payments()->delete();
        $order->forceFill(['status' => Order::STATUS_PENDING, 'paid_at' => null])->save();

        $l = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');

        $this->assertSame(Balance::KIND_PAY_ONLINE, $l['balance']['kind']);
        $this->assertSame(6000, $l['balance']['cents'], 'lo que falta por cobrar POR WEB: la señal');
        $this->assertSame(3100, $l['balance']['rest_at_park_cents'], 'y el resto, publicado');
        $this->assertSame(0, $l['paid_cents'], 'sin cobro no hay cobro');
        $this->assertSame([], $l['settlements']);
        $this->assertSame(__('tickets.ledger_note.pending_payment', ['amount' => Money::format(6000)]), $l['note']);
    }

    /**
     * **Las etiquetas viajan traducidas al idioma negociado**, y son las del dominio: un cliente en
     * inglés no puede leer castellano en su pantalla de dinero (la lección de `#154`/`#134`).
     */
    public function test_the_labels_are_translated_to_the_negotiated_locale(): void
    {
        [$user] = $this->orderWithEverything();

        $es = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');
        $en = $this->actingAs($user)->withHeader('Accept-Language', 'en')->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');

        $this->assertSame(Lang::get('tickets.journal.booking', [], 'es'), $es['movements'][0]['label']);
        $this->assertSame(Lang::get('tickets.journal.booking', [], 'en'), $en['movements'][0]['label']);
        $this->assertNotSame($es['movements'][0]['label'], $en['movements'][0]['label'], 'los dos idiomas dicen lo mismo');
        $this->assertSame(Lang::get('tickets.journal.paid_online', [], 'en'), $en['settlements'][0]['label']);
    }

    /**
     * ⚠️⚠️ **El MÉTODO no se puede quemar en la interfaz.** Un pedido cobrado en TAQUILLA publica su
     * cobro como `desk` y su etiqueta dice «en recepción»: si el rótulo asumiera «web», le diría al
     * cliente que pagó por internet un dinero que entregó en mano.
     */
    public function test_an_order_charged_at_the_desk_says_so_in_its_settlement(): void
    {
        [$user, $order] = $this->orderWithSettledDeposit();
        $order->payments()->update(['provider' => ManualOrderFulfiller::METHOD_DATAFONO]);

        $payment = collect($this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger.settlements'))
            ->firstWhere('kind', 'payment');

        $this->assertSame('desk', $payment['method']);
        $this->assertSame(__('tickets.journal.paid_desk'), $payment['label']);
        $this->assertSame(6000, $payment['amount_cents'], 'el dinero se cobró igual: lo que cambia es cómo se llama');
    }

    /**
     * **Un reembolso EN CURSO se lista y NO se cuenta** (guarda K): el cliente ve que hay una
     * devolución en camino, y el saldo sigue diciendo lo que aún no ha vuelto.
     */
    public function test_a_pending_refund_is_listed_but_not_counted(): void
    {
        [$user, $order] = $this->orderWithSettledDeposit();
        PaymentRefund::create([
            'payment_id' => $order->payments()->first()->id,
            'order_item_id' => null,
            'amount_cents' => 1500,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_PENDING,
            'mode' => PaymentRefund::MODE_REST,
            'requested_by' => $user->id,
            'requested_at' => Carbon::now(),
        ]);

        $l = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');

        $refund = collect($l['settlements'])->firstWhere('kind', 'refund');
        $this->assertSame('pending', $refund['status']);
        $this->assertSame(-1500, $refund['amount_cents']);
        $this->assertSame(__('tickets.journal.refund_pending'), $refund['label']);
        $this->assertSame($l['total_cents'], $l['paid_cents'], 'lo pagado no baja hasta que el dinero vuelve');
        $this->assertSame(Balance::KIND_SETTLED, $l['balance']['kind']);
    }

    /**
     * ⚠️⚠️ **`has_deposit` es un HECHO del pedido, no «queda algo pendiente».** Un pedido con señal
     * cuya visita ya pasó tiene el saldo a cero y **sigue siendo** un pedido con señal.
     */
    public function test_has_deposit_is_a_fact_not_a_pending_amount(): void
    {
        [$user] = $this->orderWithSettledDeposit();

        $l = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');

        $this->assertTrue($l['has_deposit'], 'un pedido con señal ya liquidada ha dejado de declararse con señal');
        $this->assertSame(0, $l['balance']['cents']);
    }

    /**
     * ⚠️⚠️ **UN LIBRO QUE NO CIERRA SE PUBLICA COMO TAL, Y SE AVISA** (`DECISIONES #132`). Las
     * identidades se evalúan sobre ESTE pedido y AHORA; un cobro que no respalda lo que las líneas
     * dicen que nació rompe la de caja (I2). Los movimientos siguen viajando —son hechos—; decidir no
     * pintarlos es de quien pinta, y `is_consistent` es la señal.
     */
    public function test_a_book_that_does_not_close_is_published_as_inconsistent_and_logged(): void
    {
        [$user, $order] = $this->orderWithSettledDeposit();
        $order->payments()->update(['amount' => 999]);

        Log::shouldReceive('warning')->atLeast()->once()
            ->with('ledger.no_cuadra', \Mockery::on(fn (array $ctx): bool => $ctx['order'] === $order->code && $ctx['cobrado'] === 999));

        $l = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');

        $this->assertFalse($l['is_consistent'], 'el libro roto se publica como si cerrara');
        $this->assertSame(Balance::KIND_UNDER_REVIEW, $l['balance']['kind']);
        $this->assertSame(0, $l['balance']['cents'], 'no se afirma ningún saldo');
        $this->assertSame(__('tickets.ledger_note.under_review'), $l['note']);
        $this->assertNotEmpty($l['movements'], 'los hechos siguen publicados: es la pantalla la que decide no pintarlos');
    }

    /** Y el caso normal sigue diciendo que cuadra: sin esto, lo de arriba pasaría con todo roto. */
    public function test_a_healthy_book_is_published_as_consistent(): void
    {
        [$user] = $this->orderWithEverything();

        $l = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');

        $this->assertTrue($l['is_consistent']);
        $this->assertNull($l['note'], 'con todo dicho por las líneas, no hay frase que añadir');
    }

    /**
     * ⚠️⚠️ **`L2` — la cantidad viaja con su SUSTANTIVO, compuesta por el servidor.**
     *
     * El cliente pintaba `quantity` pegado al importe de la línea —`8×216,00 €`—, que se lee como
     * «8 unidades a 216 € cada una» = 1.728 € cuando son 8 invitados y 216 € en total. El sustantivo
     * depende del TIPO de producto y del idioma, así que componerlo en la interfaz sería la quinta
     * copia de una regla que el dominio ya tiene.
     */
    public function test_the_quantity_travels_with_its_noun(): void
    {
        [$user, $order] = $this->orderWithEverything();

        $line = $order->items->firstWhere('parent_item_id', null);
        $line->forceFill(['quantity' => 8])->save();

        $items = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.items');

        $pack = collect($items)->firstWhere('is_pack', true);
        $entrada = collect($items)->firstWhere('is_pack', false);

        $this->assertSame(__('tickets.guests_count', ['count' => 8]), $pack['quantity_label']);
        $this->assertSame(trans_choice('tickets.entries_count', 1, ['count' => 1]), $entrada['quantity_label']);

        // ⚠️ Y el sustantivo cambia con el NÚMERO: «1 entrada» y «2 entradas». Una clave sin plural
        // escribiría «1 entradas», que es la clase de descuido que resta credibilidad a una cuenta.
        $this->assertNotSame(
            trans_choice('tickets.entries_count', 1, ['count' => 1]),
            trans_choice('tickets.entries_count', 2, ['count' => 2]),
            'la clave no distingue singular de plural'
        );
    }

    /**
     * **Lo que sigue FUERA a propósito**, que no es un hueco: las respuestas del evento.
     *
     * Nombre del homenajeado y alergias del menor (art. 9). `me/orders` no las lleva —viajarían en
     * cada página de una lista paginada— y se piden aparte con `GET orders/{code}/event-data`.
     * Publicarlas aquí sería una regresión de privacidad, no un avance.
     */
    public function test_the_event_data_stays_out_of_the_list(): void
    {
        [$user, $order] = $this->orderWithEverything();

        $line = $order->items->firstWhere('parent_item_id', null);
        $line->forceFill(['event_data' => ['birthday_child' => 'Lucía Irrepetible']])->save();

        $body = (string) $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->getContent();

        $this->assertStringNotContainsString('Lucía Irrepetible', $body);
    }

    /**
     * Un pedido que ejercita el libro entero: un pack con señal que subió en gestión, una entrada
     * cancelada sin devolver y una segunda viva; el cobro real es lo que las líneas aportaron al
     * nacer (identidad I2), así que el dinero de la cancelada sigue en la caja del parque.
     *
     * @return array{0: User, 1: Order}
     */
    private function orderWithEverything(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'accent' => 'kids',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => '2026-06-20',
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        $pack = TicketType::create([
            'name' => ['es' => 'Cumple Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 6000,
            'guest_fields' => [['key' => 'child_name', 'label' => 'Nombre', 'required' => true]],
        ]);
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada suelta'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);

        // ⚠️ `Order.total` es lo que NACIÓ (identidad I1): el pack a 74,00 (los 17,00 del extra se
        // añadieron DESPUÉS, en gestión) + la entrada cancelada (23,00) + la entrada viva (19,00).
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-PARITY',
            'status' => Order::STATUS_PAID,
            'subtotal' => 11600, 'tax' => 0, 'total' => 11600,
            'currency' => 'EUR', 'paid_at' => Carbon::now(),
        ]);

        $line = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9100, 'seats' => 1,
        ]);

        // Una línea CANCELADA sin devolver: su dinero sigue en caja y el libro lo dice.
        $order->items()->create([
            'ticket_type_id' => $entry->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 2300, 'seats' => 1,
            'cancelled_at' => Carbon::now(),
        ]);

        // Una SEGUNDA línea viva: con una sola, el Total coincide con su propio subtotal.
        $order->items()->create([
            'ticket_type_id' => $entry->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 1900, 'seats' => 1,
        ]);

        // El reparto de la señal al nacer y una gestión que subió el pack: dos hechos distintos.
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $line->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
            'amount_cents' => 3100, 'currency' => 'EUR', 'applied_by' => $user->id,
        ]);
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $line->id,
            'type' => OrderAdjustment::TYPE_EDIT,
            'amount_cents' => 1700, 'currency' => 'EUR', 'applied_by' => $user->id,
            'reason' => 'item_edit', 'context' => ['changes' => ['unit_price_change' => ['old' => 7400, 'new' => 9100]]],
        ]);

        // ⚠️ El cobro: lo que las líneas aportaron al nacer (4.300 del pack con señal + 2.300 de la
        // que se canceló después + 1.900 de la viva). Con menos, la identidad de caja no cierra.
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 8500, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => Carbon::now(),
            'gateway_order' => '0000700001',
        ]);

        return [$user, $order->fresh()->load('items.ticketType', 'items.children', 'items.slot', 'adjustments', 'payments.refunds')];
    }

    /**
     * Un pedido con señal **cuya franja ya pasó**: el resto se liquidó en el parque, así que el saldo
     * es cero y el pedido sigue siendo un pedido con señal.
     *
     * @return array{0: User, 1: Order}
     */
    private function orderWithSettledDeposit(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $zone = Zone::create([
            'slug' => 'cumples-pasados', 'name' => ['es' => 'Cumpleaños'], 'accent' => 'kids',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
        // ⚠️ Franja ANTERIOR al reloj congelado: es lo que hace que la visita cuente como pasada.
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => '2026-05-20',
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        $pack = TicketType::create([
            'name' => ['es' => 'Cumple de mayo'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 6000,
        ]);

        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-SETTLED',
            'status' => Order::STATUS_PAID,
            'subtotal' => 9100, 'tax' => 0, 'total' => 9100,
            'currency' => 'EUR', 'paid_at' => Carbon::now()->subMonth(),
        ]);
        $line = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9100, 'seats' => 1,
        ]);
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $line->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
            'amount_cents' => 3100, 'currency' => 'EUR', 'applied_by' => $user->id,
        ]);
        // El cobro de la SEÑAL, que es lo que este pedido tuvo de verdad: 60,00 € por web.
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 6000, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => Carbon::now()->subMonth(),
            'gateway_order' => '0000900001',
        ]);

        return [$user, $order->fresh()->load('items.ticketType', 'items.children', 'items.slot', 'adjustments', 'payments.refunds')];
    }
}
