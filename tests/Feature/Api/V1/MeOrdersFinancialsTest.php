<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ManualOrderFulfiller;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **El desglose financiero de un pedido: la API publica exactamente lo que calcula el dominio.**
 *
 * ## De dónde viene, en dos relevos
 *
 * 1. `AccountPageCaptureTest` inventarió lo que `/mi-cuenta/pedidos` enseñaba y la API **no**
 *    publicaba. Su lista de huecos —los cuatro del desglose— quedó vacía en el paso 9 y el fichero
 *    se borró, como pedía su propia aserción.
 * 2. `AccountFinancialParityTest` lo relevó con la otra mitad: mientras la página siguiera viva
 *    había **dos superficies enseñando el mismo dinero**, y nada garantizaba que dijeran lo mismo.
 *    Con la página retirada esa comparación se quedó sin un lado, y lo que sobrevive es esto.
 *
 * ▶ Lo que queda vigilado es el **cableado del recurso**: seis importes que se parecen mucho entre
 * sí y que, cruzados, invierten el mensaje para el cliente —`total_cents` es lo FACTURADO y
 * `total_final_cents` lo que acaba pagando; `refund` es lo YA devuelto y `pending_refund_cents` lo
 * que aún se le debe—.
 *
 * ⚠️ **La guarda de la guarda va primero**: un fixture que no ejercitara el desglose dejaría todo
 * esto comparando ceros contra ceros y pasando para siempre. Es la lección de `DECISIONES #63`, y
 * aquí es fácil de pisar: **cuatro de los seis importes valen 0 en un pedido normal**.
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
     * **La guarda de la guarda: el fixture ejercita de verdad los seis importes.**
     *
     * ⚠️ Sin este caso, un fixture que dejara de producir ajustes —o de cancelar la línea— haría que
     * todo lo de abajo comparara **0 contra 0** y pasara solo. Cuatro de los seis valen 0 en un pedido
     * corriente, así que aquí no basta con que el test exista: hay que comprobar que mide.
     */
    public function test_the_fixture_actually_exercises_every_figure(): void
    {
        [, $order] = $this->orderWithEverything();
        $summary = $order->financialSummary();

        $this->assertGreaterThan(0, $summary->pendingAtGate(), 'nada pendiente en puerta: el desglose iría vacío');
        $this->assertGreaterThan(0, $summary->pendienteDevolucion(), 'sin devolución pendiente, ese campo se compara con 0');
        $this->assertTrue($summary->depositRemainder > 0, 'el pedido ya no lleva señal');

        $this->assertNotSame(
            (int) $order->total, $summary->totalFinalNeto(),
            'el total final coincide con el facturado: la sonda no distingue los dos campos'
        );

        $lines = $order->gateBreakdownLines();

        $this->assertGreaterThanOrEqual(2, count($lines), 'el desglose tiene una sola línea: no prueba el orden');
        $this->assertSame(
            $summary->pendingAtGate(), array_sum(array_column($lines, 'amount')),
            'el desglose no suma el agregado que la API publica'
        );
    }

    /**
     * **Cada importe de la API es el que calcula el dominio**, que es el que la página pinta.
     *
     * ⚠️ Comparar contra el DOMINIO y no contra un número escrito a mano es lo que hace que esta
     * guarda siga valiendo cuando cambie una tarifa del fixture: lo que vigila es el cableado del
     * recurso, no una foto de importes.
     */
    public function test_the_api_publishes_exactly_what_the_domain_calculates(): void
    {
        [$user, $order] = $this->orderWithEverything();
        $summary = $order->financialSummary();

        $json = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0');

        $v = $json['ledger']['value'];
        $c = $json['ledger']['cash'];

        $this->assertSame($summary->totalFinalNeto(), $v['total_cents'], 'el valor de lo que sigue vivo');
        $this->assertSame($summary->pagadoOnline(), $v['paid_online_cents'], 'lo ya pagado por web');
        $this->assertSame($summary->pendienteOnline(), $v['pending_online_cents'], 'lo que falta por pagar por web');
        $this->assertSame($summary->cobradoPuerta(), $v['paid_at_gate_cents'], 'lo ya pagado en recepción');
        $this->assertSame($summary->pendingAtGate(), $v['pending_at_gate_cents'], 'lo que queda en recepción');
        $this->assertSame($summary->compensado(), $v['compensated_cents'], 'lo devuelto sin quitar producto');

        $this->assertSame($summary->grossPaidOnline, $c['charged_online_cents'], 'el ancla de caja');
        $this->assertSame($summary->effectiveRefunded(), $c['refunded_cents'], 'lo ya devuelto');
        $this->assertSame($summary->retenidoOnline(), $c['held_cents'], 'lo que el parque retiene');
        $this->assertSame($summary->pendienteDevolucion(), $c['pending_refund_cents'], 'lo que aún se debe devolver');
        $this->assertSame($order->chargeMethod(), $c['charged_method'], 'cómo se cobró');
        $this->assertSame($order->chargedAtLabel(), $c['charged_at_label'], 'cuándo se cobró');

        $this->assertSame((int) $order->total, $json['ledger']['invoiced_cents'], 'lo facturado al reservar');
        $this->assertSame($order->onlineDueCents(), $json['online_amount_cents'], 'lo que se cobraría al pagar ahora');
        $this->assertSame($summary->depositRemainder > 0, $json['ledger']['has_deposit'], 'si el pedido llevaba señal');

        // ⚠️ Los dos campos que más fácil sería cruzar, y cruzarlos invierte el significado para el
        // cliente: lo facturado es INMUTABLE y lo devuelto NO es lo que se debe devolver.
        $this->assertNotSame($json['ledger']['invoiced_cents'], $v['total_cents']);
        $this->assertNotSame($c['refunded_cents'], $c['pending_refund_cents']);
    }

    /**
     * ⚠️⚠️ **LA GUARDA QUE FALTABA: lo que se PUBLICA tiene que sumar.**
     *
     * Hasta la tanda B ninguna aserción miraba lo que la PANTALLA puede pintar: se verificaba que
     * cada cifra coincidía con el dominio —cableado— pero no que las cifras publicadas cerraran entre
     * sí. Y no cerraban: la API publicaba 2 de las 6 dimensiones, así que la columna del cliente
     * **no podía** sumar, y de hecho no sumaba en el 100 % de los pedidos con algo cobrado en puerta.
     *
     * Se recorre sobre los escenarios que el dominio sabe distinguir, no sobre un pedido: el hueco
     * de la versión anterior no estaba en la aserción, estaba en el fixture.
     */
    public function test_what_is_published_adds_up_in_every_scenario(): void
    {
        $escenarios = [
            'con señal, cambios y una cancelación' => fn () => $this->orderWithEverything(),
            'con la señal ya cobrada en recepción' => fn () => $this->orderWithSettledDeposit(),
        ];

        foreach ($escenarios as $nombre => $montar) {
            [$user, $order] = $montar();
            $json = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0');
            $v = $json['ledger']['value'];
            $c = $json['ledger']['cash'];

            // PAY-16 · el eje del VALOR cierra con lo publicado.
            $this->assertSame(
                $v['total_cents'],
                $v['paid_online_cents'] + $v['pending_online_cents'] + $v['paid_at_gate_cents']
                    + $v['pending_at_gate_cents'] + $v['compensated_cents'],
                "$nombre · los cinco canales publicados no suman el valor",
            );
            // PAY-17 · el eje de CAJA cierra con lo publicado.
            $this->assertSame(
                $c['held_cents'], $c['charged_online_cents'] - $c['refunded_cents'],
                "$nombre · lo retenido no es lo cobrado menos lo devuelto",
            );
            $this->assertSame(
                $c['held_cents'], $v['paid_online_cents'] + $c['pending_refund_cents'],
                "$nombre · lo retenido ni respalda producto ni se debe devolver",
            );
            // Los dos canales web son excluyentes: publicar el mismo importe en los dos sería
            // exactamente el defecto que separarlos vino a arreglar.
            $this->assertTrue(
                $v['paid_online_cents'] === 0 || $v['pending_online_cents'] === 0,
                "$nombre · «pagado por web» y «pendiente de pagar por web» a la vez",
            );
            // Y el desglose ↳ suma su titular.
            $this->assertSame(
                $v['pending_at_gate_cents'],
                array_sum(array_column($json['ledger']['gate_lines'], 'amount_cents')),
                "$nombre · el desglose de puerta no suma su titular",
            );

            // ⚠️ La guarda de la guarda: que el escenario EJERCITE los canales, o compararía ceros.
            $this->assertGreaterThan(0, $v['paid_online_cents'] + $v['paid_at_gate_cents'],
                "$nombre · el fixture no ejercita ningún canal cobrado");
        }
    }

    /**
     * **La FRASE de estado la compone el servidor**, y dice lo que el número no dice.
     *
     * ⚠️ Es el encargo del owner: «trazabilidad y explicación ante cualquier situación». Un pedido
     * con dinero pendiente de devolver tiene que DECIRLO, no dejar que el cliente lo deduzca de una
     * línea negativa.
     */
    public function test_the_ledger_explains_the_state_in_words(): void
    {
        [$user, $order] = $this->orderWithEverything();
        $json = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0');

        $this->assertGreaterThan(0, $json['ledger']['cash']['pending_refund_cents'], 'el fixture no debe dinero');
        $this->assertNotNull($json['ledger']['note'], 'un pedido con dinero pendiente de devolver no lo dice');
        $this->assertStringContainsString(
            Money::amount($json['ledger']['cash']['pending_refund_cents']),
            $json['ledger']['note'],
            'la frase no nombra el importe que se le debe',
        );
    }

    /**
     * **El desglose de puerta: las líneas del dominio, con sus etiquetas y en su ORDEN.**
     *
     * ⚠️ El orden no es cosmético: primero los cargos por cambios y después el resto de la señal, que
     * es como lo enseñó la web durante años. Pintarlo al revés cambia el desglose que el cliente
     * reconoce.
     * ⚠️ **La suma tiene que cuadrar con el agregado que se publica al lado**: un desglose que no
     * sume `pending_at_gate_cents` deja al cliente con dos cifras que se contradicen.
     */
    public function test_the_gate_breakdown_is_the_one_the_domain_composes(): void
    {
        [$user, $order] = $this->orderWithEverything();

        $json = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0');

        $expected = array_map(
            fn (array $line): array => ['label' => $line['label'], 'amount_cents' => (int) $line['amount']],
            $order->gateBreakdownLines(),
        );

        $this->assertSame($expected, $json['ledger']['gate_lines'], 'el desglose publicado no es el del dominio');
        $this->assertSame(
            $json['ledger']['value']['pending_at_gate_cents'],
            array_sum(array_column($json['ledger']['gate_lines'], 'amount_cents')),
            'el desglose no suma el agregado que se publica al lado'
        );

    }

    /**
     * ⚠️⚠️ **`has_deposit` NO es «hay algo pendiente en puerta», y este caso es el único que los
     * distingue.**
     *
     * En el pedido de arriba los dos son ciertos a la vez, así que publicando `pending_at_gate_cents
     * > 0` en su lugar el resto de la suite seguiría verde. El caso frontera se elige por el
     * MECANISMO del fallo (`DECISIONES #68`): un pedido con señal **cuya franja ya pasó** tiene el
     * resto cobrado en recepción —nada pendiente— y **sigue siendo** un pedido con señal. Si el
     * cliente dedujera el uno del otro, ese pedido dejaría de enseñar «Pagado online» y cambiaría de
     * leyenda justo cuando el titular consulta qué pagó.
     */
    public function test_has_deposit_is_not_the_same_as_having_something_pending(): void
    {
        [$user, $order] = $this->orderWithSettledDeposit();
        $summary = $order->financialSummary();

        $this->assertSame(0, $summary->pendingAtGate(), 'el fixture no tiene el resto ya cobrado');
        $this->assertTrue($summary->depositRemainder > 0, 'el fixture no lleva señal');

        $json = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0');

        $this->assertTrue($json['ledger']['has_deposit'], 'un pedido con señal ya saldada ha dejado de declararse con señal');
        $this->assertSame(0, $json['ledger']['value']['pending_at_gate_cents']);
        $this->assertSame([], $json['ledger']['gate_lines'], 'sin nada pendiente, el desglose va vacío');
    }

    /**
     * ⚠️⚠️ **`L1` — EL ANCLA DE CAJA SE PUBLICA COMO VISIBLE AUNQUE NO HAYA DEVOLUCIONES**
     * (`DECISIONES #128`, `specs/desglose-dinero-cliente.md` §17.1).
     *
     * `hasCash()` omitía el primer término, así que en un pedido normal el bloque no se pintaba y
     * **el cliente nunca veía cuánto había salido de su banco**. Es lo único que puede cotejar con su
     * extracto: sin ello el desglose es legible pero **no verificable**, que es justo lo que hizo
     * indescifrable el caso `R-L6UTIA` —«pagado por web 114,00 €» con un cobro real de 30,00 €—.
     *
     * ⚠️ El caso usa el pedido **con la señal ya saldada**: no tiene reembolsos ni nada pendiente de
     * devolver, así que con la condición vieja el ancla sería invisible. Es el pedido corriente.
     */
    public function test_the_cash_anchor_is_published_even_without_any_refund(): void
    {
        [$user, $order] = $this->orderWithSettledDeposit();
        $summary = $order->financialSummary();

        $this->assertSame(0, $summary->effectiveRefunded(), 'el fixture tiene devoluciones: no mide lo que dice medir');
        $this->assertSame(0, $summary->pendienteDevolucion(), 'el fixture debe dinero: no mide lo que dice medir');
        $this->assertGreaterThan(0, $summary->grossPaidOnline, 'sin cobro real no hay ancla que enseñar');

        $c = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger.cash');

        $this->assertTrue($c['has_cash'], 'el cliente vuelve a quedarse sin el único número que puede cotejar con su banco');
        $this->assertSame($summary->grossPaidOnline, $c['charged_online_cents']);
        $this->assertSame('web', $c['charged_method']);
        $this->assertNotNull($c['charged_at_label'], 'un importe sin fecha no se busca en un extracto bancario');
    }

    /** Sin ningún cobro no hay ancla: el eje de caja no tiene nada que contar. */
    public function test_an_order_never_charged_publishes_no_cash_axis(): void
    {
        [$user, $order] = $this->orderWithSettledDeposit();
        $order->payments()->delete();
        $order->forceFill(['status' => Order::STATUS_PENDING, 'paid_at' => null])->save();

        $c = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger.cash');

        $this->assertFalse($c['has_cash']);
        $this->assertNull($c['charged_method'], 'sin cobro no hay método que rotular');
        $this->assertNull($c['charged_at_label']);
    }

    /**
     * ⚠️⚠️ **El MÉTODO no se puede quemar en la interfaz.** El eje de caja suma TODOS los pagos
     * cobrados sin mirar el `provider` —eso es correcto: mide dinero movido, no medios—, así que un
     * pedido cobrado en TAQUILLA (efectivo o datáfono) publica el mismo importe. Si el rótulo
     * asumiera «web», ese pedido le diría al cliente que pagó por internet un dinero que entregó en
     * mano. El panel ya distinguía el método desde `P1/P10`; el cliente no, y con el ancla siempre
     * visible esa divergencia pasaba a ser una afirmación falsa en pantalla.
     */
    public function test_an_order_charged_at_the_desk_does_not_claim_it_was_charged_online(): void
    {
        [$user, $order] = $this->orderWithSettledDeposit();
        $order->payments()->update(['provider' => ManualOrderFulfiller::METHOD_DATAFONO]);

        $c = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger.cash');

        $this->assertSame('desk', $c['charged_method']);
        $this->assertTrue($c['has_cash'], 'el dinero se cobró igual: lo que cambia es cómo se llama');
        $this->assertSame($order->financialSummary()->grossPaidOnline, $c['charged_online_cents']);
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
     * Un pedido que ejercita **los seis importes a la vez**, con valores que no se repiten.
     *
     * Hereda el fixture de `AccountPageCaptureTest` —una línea con señal y extras, una cancelada y
     * una segunda viva— y le añade **el pago online**, que aquél no necesitaba: sin una fila de
     * `payments` pagada, `pendienteDevolucion()` vale 0 contra cualquier pedido, que es exactamente
     * por lo que aquel test lo declaraba «no sondeable». Con el pago delante sí se puede: el dinero
     * de la línea cancelada sigue retenido y aún no se ha devuelto.
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

        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-PARITY',
            'status' => Order::STATUS_PAID,
            'subtotal' => 13300, 'tax' => 0, 'total' => 13300,
            'currency' => 'EUR', 'paid_at' => Carbon::now(),
        ]);

        $line = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9100, 'seats' => 1,
        ]);

        // ⚠️ **Una línea CANCELADA, y es la que hace medibles DOS campos**: sin ella
        // `totalFinalNeto()` coincidiría con `total_cents` —que la API ya publicaba— y
        // `pendienteDevolucion()` valdría 0. Es además el caso donde los dos importan.
        $order->items()->create([
            'ticket_type_id' => $entry->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 2300, 'seats' => 1,
            'cancelled_at' => Carbon::now(),
        ]);

        // Una SEGUNDA línea viva: con una sola, el total final coincide con su propio subtotal y la
        // comparación no distingue dos campos distintos.
        $order->items()->create([
            'ticket_type_id' => $entry->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 1900, 'seats' => 1,
        ]);

        // El resto de la señal y un extra añadido en gestión, con importes distintos entre sí y del
        // total: son las dos familias del desglose de puerta.
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $line->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => 3100, 'currency' => 'EUR', 'applied_by' => $user->id,
        ]);
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $line->id,
            'type' => OrderAdjustment::TYPE_EXTRA_DUE,
            'amount_cents' => 1700, 'currency' => 'EUR', 'applied_by' => $user->id,
            'reason' => 'Extra de gestión',
        ]);

        // ⚠️ **El pago online**, que es lo que hace medible «pendiente de devolución». 8.500 = lo que
        // respaldan las líneas vivas (4.300 del pack con señal + 1.900 de la entrada) MÁS los 2.300
        // de la que se canceló después: dinero cobrado por web que ya no tiene producto detrás y que
        // todavía no se ha devuelto.
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 8500, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => Carbon::now(),
            'gateway_order' => '0000700001',
        ]);

        return [$user, $order->fresh()->load('items.ticketType', 'items.children', 'items.slot', 'adjustments', 'payments.refunds')];
    }

    /**
     * Un pedido con señal **cuya franja ya pasó**: el resto se cobró en recepción, así que no queda
     * nada pendiente en puerta y el pedido sigue siendo un pedido con señal.
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
        // ⚠️ Franja ANTERIOR al reloj congelado: es lo que hace que el ajuste cuente como resuelto.
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
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => 3100, 'currency' => 'EUR', 'applied_by' => $user->id,
        ]);
        // ⚠️ El cobro de la SEÑAL, que es lo que este pedido tuvo de verdad: 60,00 € por web y el
        // resto en recepción. Un pedido `paid` sin ninguna fila `Payment` no lo produce ningún cobro
        // real, y hace que el eje de caja (`PAY-17`) compare contra un cobro de 0,00 €.
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 6000, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => Carbon::now()->subMonth(),
            'gateway_order' => '0000900001',
        ]);

        return [$user, $order->fresh()->load('items.ticketType', 'items.children', 'items.slot', 'adjustments', 'payments.refunds')];
    }
}
