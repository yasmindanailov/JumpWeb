<?php

namespace Tests\Feature\Account;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * # ⏳ TEST TEMPORAL — MUERE CON LA PÁGINA `/mi-cuenta/pedidos`
 *
 * **Fecha de caducidad: el final de la tanda 3 del área de cliente**, cuando esa página se retire
 * (`DECISIONES #120(c)`). Este fichero **se borra entero** ese día.
 *
 * ## Por qué existe, y qué sustituye
 *
 * Es el relevo de `AccountPageCaptureTest`, que hizo su trabajo y **se borró en el paso 9**: aquél
 * inventariaba lo que la página enseñaba y la API **no** publicaba, y su lista de huecos —los cuatro
 * del desglose financiero— quedó **vacía** al publicarlos. Su propia aserción lo decía: *«si ya no
 * quedan huecos, borra este fichero: su trabajo terminó»*.
 *
 * Lo que NO terminó es la otra mitad, y por eso hay relevo: mientras la página siga viva, hay **dos
 * superficies enseñando el mismo dinero**, y nada garantizaba que dijeran lo mismo. Esta guarda lo
 * exige en las dos direcciones:
 *
 *  1. la API publica **exactamente** lo que el dominio calcula —que es lo que la página pinta—;
 *  2. la página **sigue pintando** esos mismos importes.
 *
 * ▶ Cuando la página muera, esta comparación deja de tener dos lados y el fichero sobra. Lo que
 * sobrevive es el contrato (`openapi/v1.yaml`) y `MeOrdersTest`.
 *
 * ⚠️ **La guarda de la guarda va primero**: un fixture que no ejercitara el desglose dejaría todo esto
 * comparando ceros contra ceros y pasando para siempre. Es la lección de `DECISIONES #63`, y aquí es
 * especialmente fácil de pisar: **cuatro de los seis importes valen 0 en un pedido normal**.
 */
class AccountFinancialParityTest extends TestCase
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

        $this->assertSame((int) $order->total, $json['total_cents'], 'lo facturado');
        $this->assertSame($order->onlineDueCents(), $json['online_amount_cents'], 'lo cobrado online');
        $this->assertSame($summary->pendingAtGate(), $json['pending_at_gate_cents'], 'el agregado de puerta');
        $this->assertSame($summary->totalFinalNeto(), $json['total_final_cents'], 'el total final tras los cambios');
        $this->assertSame($summary->pendienteDevolucion(), $json['pending_refund_cents'], 'lo que aún se debe devolver');
        $this->assertSame($summary->depositRemainder > 0, $json['has_deposit'], 'si el pedido llevaba señal');
        $this->assertSame((int) ($order->refund_amount_cents ?? 0), $json['refund']['amount_cents'], 'lo ya devuelto');

        // ⚠️ Los dos campos que más fácil sería cruzar, y cruzarlos invierte el significado para el
        // cliente: `total` es INMUTABLE y `refund` es lo YA devuelto.
        $this->assertNotSame($json['total_cents'], $json['total_final_cents']);
        $this->assertNotSame($json['refund']['amount_cents'], $json['pending_refund_cents']);
    }

    /**
     * **El desglose de puerta: mismas líneas, mismas etiquetas y mismo ORDEN en los dos sitios.**
     *
     * ⚠️ El orden no es cosmético: la página lleva años enseñando primero los cargos por cambios y
     * después el resto de la señal. Un cliente que los pinte al revés enseña un desglose distinto del
     * que el cliente reconoce.
     */
    public function test_the_gate_breakdown_says_the_same_in_the_api_and_on_the_page(): void
    {
        [$user, $order] = $this->orderWithEverything();

        $json = $this->actingAs($user)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0');
        $html = (string) $this->actingAs($user)->get('/mi-cuenta/pedidos')->assertOk()->getContent();

        $expected = array_map(
            fn (array $line): array => ['label' => $line['label'], 'amount_cents' => (int) $line['amount']],
            $order->gateBreakdownLines(),
        );

        $this->assertSame($expected, $json['pending_at_gate_lines'], 'el desglose publicado no es el del dominio');

        // ⚠️⚠️ **La referencia del ORDEN es la PÁGINA, no el helper.** La aserción de arriba compara
        // la API contra la misma fuente que la alimenta, así que sobre el orden no dice nada: una
        // mutación que reordenara el helper movería las dos a la vez y pasaría. Lo comprobó una
        // mutación real (`DECISIONES #120(t)`). Aquí se buscan las etiquetas **en el HTML** y se exige
        // que aparezcan en el mismo orden.
        $positions = [];

        foreach ($expected as $line) {
            $at = mb_strpos($html, $line['label']);

            $this->assertNotFalse($at, "la página ya no pinta la etiqueta «{$line['label']}»");
            // La página pinta importes FORMATEADOS y la API céntimos: el mismo dato, dos formas.
            // Buscar céntimos en el HTML daría un rojo que se lee como «ya no lo pinta».
            $this->assertStringContainsString(Money::format($line['amount_cents']), $html);

            $positions[] = $at;
        }

        $sorted = $positions;
        sort($sorted);

        $this->assertSame($sorted, $positions, 'el desglose de la API va en otro orden que el de la página');

        // Y ni una línea de más: una fantasma con importe 0 no movería la suma ni el agregado.
        $this->assertSame(
            count($expected), mb_substr_count($html, 'orders__gate-line'),
            'la página y la API no pintan el mismo NÚMERO de líneas de desglose'
        );
    }

    /**
     * **Y la página sigue pintando los totales que la API publica.** Es la mitad que protege la
     * RETIRADA: mientras los dos números sean el mismo, borrar la vista no pierde nada.
     */
    public function test_the_page_still_paints_the_same_totals(): void
    {
        [$user, $order] = $this->orderWithEverything();
        $summary = $order->financialSummary();

        $html = (string) $this->actingAs($user)->get('/mi-cuenta/pedidos')->assertOk()->getContent();

        foreach ([
            'total final' => $summary->totalFinalNeto(),
            'pendiente de devolución' => $summary->pendienteDevolucion(),
            'a cobrar en el parque' => $summary->pendingAtGate(),
        ] as $what => $cents) {
            $this->assertStringContainsString(
                Money::format($cents), $html,
                "la página ya no pinta «{$what}». Si de verdad se ha retirado de ella, este fichero ".
                'sobra: su trabajo es comparar DOS superficies.'
            );
        }
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

        $this->assertTrue($json['has_deposit'], 'un pedido con señal ya saldada ha dejado de declararse con señal');
        $this->assertSame(0, $json['pending_at_gate_cents']);
        $this->assertSame([], $json['pending_at_gate_lines'], 'sin nada pendiente, el desglose va vacío');
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

        return [$user, $order->fresh()->load('items.ticketType', 'items.children', 'items.slot', 'adjustments', 'payments.refunds')];
    }
}
