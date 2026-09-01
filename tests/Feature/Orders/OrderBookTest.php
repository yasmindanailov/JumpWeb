<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\Movement;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\Settlement;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\DisplayTime;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL LIBRO DEL PEDIDO, en el dominio** (T2 de `specs/desglose-libro.md`, guardas G · J · K y las
 * etiquetas): cada gestión es una línea con su signo y su fecha, el Total es la suma y el saldo se
 * liquida en el parque. Aquí se fija la COMPOSICIÓN —qué línea sale de cada hecho, con qué importe,
 * qué fecha y qué frase— y las siete clases de saldo. La equivalencia con el modelo de dos ejes
 * (la guarda PUENTE) vive en `OrderFinancialInvariantsTest`, sobre sus 15 escenarios.
 *
 * ⚠️ Los reembolsos con éxito van por el FLUJO REAL (`executePartialRefund`/`executeFullRefund`, en
 * modo manual): desde la T1 cada reembolso escribe su cortesía en la misma transacción, y una fila
 * de reembolso creada a mano sin ese hecho es un mundo que no existe. Los que NO tienen éxito
 * (en curso, fallidos) sí se crean a mano: son la fila que el flujo REST deja cuando la pasarela no
 * responde, y el libro los lista sin contarlos.
 *
 * Mutaciones que muerden (medidas al escribirlo): omitir las cancelaciones del compositor (I3 →
 * «en revisión» en cuatro casos) · sumar un reembolso en curso a lo pagado (K) · invertir la
 * condición de visita del saldo (J) · fechar la línea mixta con `created_at` (la línea viva) ·
 * atribuir un reembolso total a una sola reserva (H, en el puente).
 */
class OrderBookTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    // ─── Las líneas de VALOR ────────────────────────────────────────────────────────────────

    public function test_a_plain_paid_order_is_one_booking_line_already_settled(): void
    {
        $order = $this->makePaidOrder(1300);
        $item = $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachChild($order, $item, qty: 1, unit: 300);
        $this->attachPaidPayment($order, 1300);

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertCount(1, $book->movements, 'sin gestiones, el libro es solo el nacimiento');
        $this->assertSame(Movement::KIND_BOOKING, $book->movements[0]->kind);
        $this->assertSame('Reserva realizada', $book->movements[0]->label);
        $this->assertSame(1300, $book->movements[0]->amountCents);
        $this->assertNull($book->movements[0]->reservationId, 'el nacimiento es del PEDIDO');
        $this->assertSame(1300, $book->totalCents);
        $this->assertSame(1300, $book->paidCents);
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
        $this->assertSame(0, $book->balance->cents);
        $this->assertNull($book->note, 'nada que explicar: una frase de relleno enseña a ignorar las que importan');
        $this->assertFalse($book->hasDeposit);
        $this->assertCount(1, $book->settlements);
        $this->assertSame([Settlement::KIND_PAYMENT, 'Pagado online', 1300, Settlement::STATUS_SUCCEEDED, Settlement::METHOD_WEB],
            [$book->settlements[0]->kind, $book->settlements[0]->label, $book->settlements[0]->amountCents, $book->settlements[0]->status, $book->settlements[0]->method]);
    }

    public function test_a_reduction_paid_online_is_owed_back_at_the_park(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(2000);
        $item = $this->attachItem($order, qty: 2, unit: 1000);
        $this->attachPaidPayment($order, 2000);
        $this->travel(1)->days();
        $this->reduce($order, $item, $by, toQty: 1);

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame(['booking', 'edit'], array_column($book->movements, 'kind'));
        $edit = $book->movements[1];
        $this->assertSame(-1000, $edit->amountCents, 'el delta ENTERO, con su signo');
        $this->assertSame('Cantidad: 2 → 1', $edit->label, 'la etiqueta dice QUÉ pasó; la dirección la pone el signo');
        // ⚠️ En la ZONA del parque, no en UTC: a las 23:59 UTC en Madrid ya es mañana, y `audit-clock`
        // lo dijo en tres fronteras («fin de mes», «fin de año», «medianoche UTC»).
        $this->assertSame(DisplayTime::format(now(), 'd/m/Y'), $edit->occurredLabel);
        $this->assertSame($item->id, $edit->reservationId);
        $this->assertSame(1000, $book->totalCents);
        $this->assertSame(2000, $book->paidCents, 'lo cobrado no cambia porque baje el valor');
        $this->assertSame(Balance::KIND_REFUND_AT_PARK, $book->balance->kind, 'la reserva sigue viva: se devuelve EN el parque (D2)');
        $this->assertSame(-1000, $book->balance->cents, 'con signo: negativo se devuelve');
    }

    public function test_a_raise_is_paid_at_the_park(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(1000);
        $item = $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachPaidPayment($order, 1000);
        $order->recordEdit($item, 1000, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);
        $item->forceFill(['quantity' => 2, 'seats' => 2])->save();

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame('Cantidad: 1 → 2', $book->movements[1]->label);
        $this->assertSame(1000, $book->movements[1]->amountCents);
        $this->assertSame(2000, $book->totalCents);
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
        $this->assertSame(1000, $book->balance->cents);
    }

    public function test_a_finished_visit_settles_what_was_left_at_the_gate(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(1000);
        $item = $this->attachItem($order, qty: 1, unit: 1000, past: true);
        $this->attachPaidPayment($order, 1000);
        $order->recordEdit($item, 400, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);
        $item->forceFill(['quantity' => 2, 'seats' => 2, 'unit_price' => 700])->save();

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame(1400, $book->totalCents);
        $gate = array_values(array_filter($book->settlements, fn (Settlement $s): bool => $s->kind === Settlement::KIND_GATE));
        $this->assertCount(1, $gate, 'la visita pasó y el pedido se cobró: lo que faltaba se dio por liquidado (D9)');
        $this->assertSame(400, $gate[0]->amountCents);
        $this->assertSame('Liquidado en el parque', $gate[0]->label);
        $this->assertSame('01/01/2000', $gate[0]->occurredLabel, 'fechada con la fecha CIVIL de la franja, no con un instante desplazado de zona');
        $this->assertSame('2000-01-01T10:59:00+00:00', $gate[0]->occurredAt, 'al FIN de la franja');
        $this->assertNull($gate[0]->method, 'la liquidación no tiene canal: es una regla');
        $this->assertSame(1400, $book->paidCents, 'cobrado 10,00 + liquidado 4,00');
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
    }

    public function test_a_pack_with_a_deposit_has_it_as_a_fact_and_pays_the_rest_at_the_park(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(6000);
        $item = $this->attachItem($order, qty: 4, unit: 1500);
        $this->attachDepositSplit($order, $item, 2000);
        $this->attachPaidPayment($order, 4000);

        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertBookCloses($book);
        $this->assertTrue($book->hasDeposit);
        $this->assertSame(6000, $book->totalCents);
        $this->assertSame(4000, $book->paidCents, 'la señal: lo que la línea aportó al cobro online al nacer');
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
        $this->assertSame(2000, $book->balance->cents, 'el resto de la señal, sin regímenes (D3)');

        // Una bajada que la señal absorbe ENTERA deja el resto en 0 — y el hecho de que la línea nació
        // con reparto no desaparece: `has_deposit` es un hecho, no «queda algo pendiente».
        $this->reduce($order, $item, $by, toQty: 1);
        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertBookCloses($book);
        $this->assertTrue($book->hasDeposit, 'nació con señal; que una bajada absorbiera el resto no lo cambia');
        $this->assertSame(1500, $book->totalCents);
        $this->assertSame(Balance::KIND_REFUND_AT_PARK, $book->balance->kind);
        $this->assertSame(-2500, $book->balance->cents, '40,00 cobrados − 15,00 que vale: 25,00 a devolver en el parque');
    }

    public function test_a_cancelled_line_extinguishes_its_courtesy_and_the_money_is_pending_refund(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 1, unit: 4000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executePartialRefund($item, 1000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->travel(1)->seconds();
        $item->fresh()->markCancelled($by);

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame(['booking', 'courtesy', 'cancel'], array_column($book->movements, 'kind'));
        $this->assertSame([4000, -1000, -3000], array_column($book->movements, 'amountCents'),
            'la cancelación retira la línea CON su cortesía: −(40,00 − 10,00)');
        $this->assertSame('Compensación', $book->movements[1]->label);
        $this->assertSame('Cancelado: Jump 1h · 1 entrada', $book->movements[2]->label);
        $this->assertSame(0, $book->totalCents, 'un pedido sin líneas vivas no vale nada');
        $this->assertSame(3000, $book->paidCents, '40,00 cobrados − 10,00 devueltos');
        $this->assertSame(Balance::KIND_REFUND_PENDING, $book->balance->kind, 'no habrá visita: pendiente de devolución, y el operador decide el canal (D5)');
        $this->assertSame(-3000, $book->balance->cents);
        $refund = $book->settlements[1];
        $this->assertSame([Settlement::KIND_REFUND, 'Devuelto en el parque (registrado)', -1000, Settlement::STATUS_SUCCEEDED, Settlement::METHOD_MANUAL],
            [$refund->kind, $refund->label, $refund->amountCents, $refund->status, $refund->method]);
    }

    public function test_a_total_refund_after_cancelling_the_order_settles_the_book(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $this->attachItem($order, qty: 1, unit: 4000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executeFullRefund($by, PaymentRefund::MODE_MANUAL, true, PaymentRefund::INTENT_VALUE_RETURNED);
        $this->assertTrue($result['ok'], json_encode($result));

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame(['booking', 'cancel'], array_column($book->movements, 'kind'), 'se devolvió lo debido: sin cortesía');
        $this->assertSame(0, $book->totalCents);
        $this->assertSame(0, $book->paidCents);
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
        $this->assertSame([Settlement::KIND_PAYMENT, Settlement::KIND_REFUND], array_column($book->settlements, 'kind'));
        $this->assertSame(-4000, $book->settlements[1]->amountCents);
    }

    /** Guarda K · lo que no ha vuelto se LISTA y no se CUENTA. (Mutación: sumarlo a lo pagado.) */
    public function test_a_pending_or_failed_refund_is_listed_but_never_counted(): void
    {
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 1, unit: 4000);
        $payment = $this->attachPaidPayment($order, 4000);
        $this->attachRefundRow($payment, 500, PaymentRefund::STATUS_PENDING, null);          // un total EN VUELO
        $this->attachRefundRow($payment, 300, PaymentRefund::STATUS_FAILED, $item->id);     // uno que la pasarela denegó

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame(4000, $book->paidCents, 'ni el pendiente ni el fallido han devuelto un céntimo');
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
        $refunds = array_values(array_filter($book->settlements, fn (Settlement $s): bool => $s->kind === Settlement::KIND_REFUND));
        $this->assertCount(2, $refunds, 'pero los dos se ven: cliente y operador saben que hay una devolución en curso');
        $this->assertSame(['Devolución en curso', 'Devolución fallida'], array_column($refunds, 'label'));
        $this->assertSame([Settlement::STATUS_PENDING, Settlement::STATUS_FAILED], array_column($refunds, 'status'));
        $this->assertSame([-500, -300], array_column($refunds, 'amountCents'));
        $this->assertFalse($refunds[0]->isEffective());

        // Y por reserva, atribuidos: el total en vuelo por la MISMA prorrata que un total con éxito.
        $reservation = OrderBook::forReservation($this->fresh($order), $item);
        $this->assertSame(4000, $reservation->paidCents);
        $this->assertSame([-500, -300], array_column(array_values(array_filter($reservation->settlements, fn (Settlement $s): bool => $s->kind === Settlement::KIND_REFUND)), 'amountCents'));
    }

    public function test_the_mixed_party_lines_are_movements_with_their_own_phrase(): void
    {
        $order = $this->makePaidOrder(12000);
        $item = $this->attachItem($order, qty: 8, unit: 1500);
        $this->attachDepositSplit($order, $item, 9000);
        $this->attachPaidPayment($order, 3000);
        $this->travel(1)->days();
        $credit = $this->attachCreditLine($order, $item, 800, guests: 2);

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame(['booking', 'mixed'], array_column($book->movements, 'kind'));
        $mixed = $book->movements[1];
        $this->assertSame(-800, $mixed->amountCents, 'la línea de crédito RESTA (T4)');
        $this->assertSame('Descuento por 2 invitados que corresponden a Kids', $mixed->label, 'la frase de hoy, del mismo compositor');
        $this->assertSame($item->id, $mixed->reservationId, 'atribuida a la reserva de su principal, no a la línea hija');
        $this->assertSame(11200, $book->totalCents);
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
        $this->assertSame(8200, $book->balance->cents, '90,00 − 8,00: en la puerta le pedirán 82,00');

        // La línea VIVA lleva la fecha de su ÚLTIMO importe: el reconciliador la mueve en el sitio.
        $this->travel(3)->days();
        $adjustment = OrderAdjustment::where('order_item_id', $credit->id)->firstOrFail();
        $credit->forceFill(['unit_price' => 400])->save();
        $adjustment->forceFill(['amount_cents' => -400])->save();
        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertBookCloses($book);
        $this->assertSame(-400, $book->movements[1]->amountCents);
        $this->assertSame(DisplayTime::format(now(), 'd/m/Y'), $book->movements[1]->occurredLabel, 'lo que hoy vale y desde cuándo');
    }

    // ─── Las siete clases del saldo (guarda J) ───────────────────────────────────────────────

    public function test_a_pending_order_owes_its_money_online_and_the_deposit_rest_at_the_park(): void
    {
        $order = $this->makeOrder(Order::STATUS_PENDING, 6000, paidAt: null);
        $item = $this->attachItem($order, qty: 4, unit: 1500);
        $this->attachDepositSplit($order, $item, 2000);

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame(6000, $book->totalCents);
        $this->assertSame(0, $book->paidCents, 'sin cobro no hay cobro');
        $this->assertSame(Balance::KIND_PAY_ONLINE, $book->balance->kind);
        $this->assertSame(4000, $book->balance->cents, 'lo que falta por cobrar POR WEB: la señal');
        $this->assertSame(2000, $book->balance->restAtParkCents, 'y el resto, publicado —no restado por cada superficie—');
        $this->assertSame(__('tickets.ledger_note.pending_payment', ['amount' => '40,00 €']), $book->note);
        $this->assertSame([], $book->settlements);
    }

    public function test_an_expired_order_has_nothing_to_settle(): void
    {
        $order = $this->makeOrder(Order::STATUS_PENDING, 1000, paidAt: null, expiresAt: now()->subHour());
        $this->attachItem($order, qty: 1, unit: 1000);

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame(Balance::KIND_EXPIRED, $book->balance->kind);
        $this->assertSame(0, $book->balance->cents);
        $this->assertSame(__('tickets.ledger_note.expired'), $book->note);
    }

    public function test_a_cancelled_line_next_to_a_live_one_is_refunded_at_the_park_but_its_own_book_says_pending(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(1800);
        $live = $this->attachItem($order, qty: 1, unit: 1000);
        $gone = $this->attachItem($order, qty: 1, unit: 800);
        $this->attachPaidPayment($order, 1800);
        $gone->markCancelled($by);

        $fresh = $this->fresh($order);
        $book = OrderBook::forOrder($fresh);
        $this->assertBookCloses($book);
        $this->assertSame(Balance::KIND_REFUND_AT_PARK, $book->balance->kind, 'hay una visita: se devuelve en el parque');
        $this->assertSame(-800, $book->balance->cents);

        $liveBook = OrderBook::forReservation($fresh, $live);
        $goneBook = OrderBook::forReservation($fresh, $gone);
        $this->assertSame(Balance::KIND_SETTLED, $liveBook->balance->kind);
        $this->assertSame(Balance::KIND_REFUND_PENDING, $goneBook->balance->kind, 'ESTA reserva no tendrá visita');
        $this->assertSame(-800, $goneBook->balance->cents);
        $this->assertSame($book->totalCents - $book->paidCents,
            ($liveBook->totalCents - $liveBook->paidCents) + ($goneBook->totalCents - $goneBook->paidCents),
            'H · Σ Saldo(r) == Saldo');
        // Y el libro del pedido lleva el nombre de la reserva delante de cada línea de valor.
        $this->assertSame('Jump 1h · Cancelado: Jump 1h · 1 entrada', $book->movements[1]->label);
        $this->assertSame('Reserva realizada', $book->movements[0]->label, 'el nacimiento es del pedido: sin prefijo');
        $this->assertSame('Cancelado: Jump 1h · 1 entrada', $goneBook->movements[1]->label, 'en su propio libro, sin prefijo');
    }

    // ─── Las identidades (guarda G y los datos sucios de la spec §1.4) ──────────────────────

    public function test_a_fabricated_total_puts_the_book_under_review(): void
    {
        $order = $this->makePaidOrder(1000);
        $this->attachItem($order, qty: 4, unit: 1500);   // nació valiendo 60,00, no 10,00
        $this->attachPaidPayment($order, 1000);

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertFalse($book->isConsistent, 'I1: lo facturado no es lo que las líneas dicen que nació');
        $this->assertSame(Balance::KIND_UNDER_REVIEW, $book->balance->kind);
        $this->assertSame(0, $book->balance->cents, 'no se afirma ningún saldo');
        $this->assertSame(__('tickets.ledger_note.under_review'), $book->note);
        $this->assertSame(6000, $book->totalCents, 'el Total sí es un hecho: las líneas');
        $this->assertNotSame($book->totalCents, $book->movementsSumCents(), 'y por eso I3 tampoco cierra: el nacimiento publicado es Order.total');

        // Control: con el total que le corresponde, cuadra.
        $order->update(['total' => 6000, 'subtotal' => 6000]);
        $order->payments()->update(['amount' => 6000]);
        $this->assertBookCloses(OrderBook::forOrder($this->fresh($order)));
    }

    public function test_a_paid_at_behind_a_payment_that_never_succeeded_is_under_review(): void
    {
        // La siembra de `R-IBX8B1` (spec §1.4): `paid_at` puesto con un pago que no está pagado.
        $order = $this->makePaidOrder(1000);
        $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachPaidPayment($order, 1000)->update(['status' => Payment::STATUS_FAILED]);

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertFalse($book->isConsistent, 'I2: cobrado 0 contra 10,00 que las líneas dicen haber aportado');
        $this->assertSame(Balance::KIND_UNDER_REVIEW, $book->balance->kind);
    }

    public function test_a_paid_status_without_a_collection_is_under_review(): void
    {
        $order = $this->makeOrder(Order::STATUS_PAID, 1000, paidAt: null);
        $this->attachItem($order, qty: 1, unit: 1000);

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertFalse($book->isConsistent, 'I2: un pedido `paid` sin cobro es caja que no cierra');
        $this->assertSame(Balance::KIND_UNDER_REVIEW, $book->balance->kind);
    }

    public function test_a_refund_column_that_diverges_from_the_rows_is_under_review(): void
    {
        $order = $this->makePaidOrder(1000);
        $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachPaidPayment($order, 1000);
        $order->forceFill(['refund_amount_cents' => 200])->save();   // escrita a mano, sin fila

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertFalse($book->isConsistent, 'I4: la columna dice algo que las filas no');
        $this->assertSame(Balance::KIND_UNDER_REVIEW, $book->balance->kind);
    }

    // ─── Cronología, prefijos y etiquetas ──────────────────────────────────────────────────

    public function test_movements_are_chronological_across_reservations(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(2000);
        $a = $this->attachItem($order, qty: 1, unit: 1000);
        $b = $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachPaidPayment($order, 2000);

        $this->travel(1)->days();
        $order->recordEdit($b, 500, $by, 'item_edit', ['changes' => ['unit_price_change' => ['old' => 1000, 'new' => 1500]]]);
        $b->forceFill(['unit_price' => 1500])->save();
        $this->travel(1)->days();
        $order->recordEdit($a, 1000, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);
        $a->forceFill(['quantity' => 2, 'seats' => 2])->save();

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame([null, $b->id, $a->id], array_column($book->movements, 'reservationId'), 'por FECHA, no por reserva');
        $this->assertSame('Jump 1h · Precio del día: 10,00 € → 15,00 €', $book->movements[1]->label);
        $this->assertSame('Jump 1h · Cantidad: 1 → 2', $book->movements[2]->label);
        $dates = array_map(fn (Movement $m): string => $m->occurredAt, $book->movements);
        $this->assertSame($dates, (function (array $d): array {
            sort($d);

            return $d;
        })($dates));
    }

    public function test_edit_labels_follow_the_structured_context_and_never_invent_a_quantity(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(1000);
        $item = $this->attachItem($order, qty: 1, unit: 1000);
        $child = $this->attachChild($order, $item, qty: 1, unit: 300);
        $this->attachPaidPayment($order, 1300);
        $order->update(['total' => 1300, 'subtotal' => 1300]);

        $write = function (OrderItem $line, int $delta, array $context) use ($order, $by): void {
            $this->travel(1)->seconds();
            $order->recordEdit($line, $delta, $by, 'item_edit', $context);
        };
        $write($item, 500, ['changes' => ['product_change' => ['old' => 'Jump 1h', 'new' => 'Jump 2h'], 'quantity_change' => ['old' => 1, 'new' => 2]]]);
        $write($item, 200, ['changes' => ['slot_change' => ['old' => 'lun 1', 'new' => 'mar 2 sep, 12:00'], 'unit_price_change' => ['old' => 1000, 'new' => 1200]]]);
        $write($child, 600, ['addon_change' => ['added' => [['name' => 'Calcetines', 'qty' => 3]], 'removed' => [], 'updated' => [['name' => 'Tarta', 'old' => 1, 'new' => 2]]]]);
        $write($child, 300, ['changes' => ['quantity_change' => ['old' => 4, 'new' => 5], 'addon_change' => ['added' => [], 'removed' => [], 'updated' => [['name' => 'Menú', 'old' => 4, 'new' => 5]]]]]);
        $write($item, 100, ['changes' => []]);   // el contexto vacío de las filas anteriores a `#150`

        $book = OrderBook::forOrder($this->fresh($order));

        $labels = array_column(array_slice($book->movements, 1), 'label');
        $this->assertSame([
            'Cambio a Jump 2h · Cantidad: 1 → 2',
            'Cambio de fecha a mar 2 sep, 12:00',      // el precio no habla con una fecha delante
            '+3 Calcetines · +1 Tarta',
            'Jump 1h: 4 → 5',                          // la re-escala per-invitado lleva el nombre del complemento
            'Cambios en Jump 1h',                      // el respaldo no inventa una cantidad
        ], $labels);
        // El Total no depende de las etiquetas: la fila manda. (Las filas no se tocaron aquí a
        // propósito: el libro es coherente aunque las etiquetas sean de contextos sintéticos.)
        $this->assertSame(1300 + 500 + 200 + 600 + 300 + 100, $book->movementsSumCents());
    }

    public function test_a_desk_payment_is_labelled_as_paid_at_the_desk(): void
    {
        $order = $this->makePaidOrder(1000);
        $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachPaidPayment($order, 1000, provider: 'cash');

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame('Pagado en recepción', $book->settlements[0]->label);
        $this->assertSame(Settlement::METHOD_DESK, $book->settlements[0]->method);
    }

    public function test_a_paid_in_person_refund_re_channels_the_money_to_the_park(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 1, unit: 4000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executePartialRefund($item, 2000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_PAID_IN_PERSON);
        $this->assertTrue($result['ok'], json_encode($result));

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertBookCloses($book);
        $this->assertSame(['booking'], array_column($book->movements, 'kind'), 'no es una cortesía: el valor no se mueve');
        $this->assertSame(4000, $book->totalCents);
        $this->assertSame(2000, $book->paidCents);
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind, 'lo que se le devolvió lo pagará en recepción: eso significa la intención');
        $this->assertSame(2000, $book->balance->cents);
    }

    public function test_the_reservation_book_attributes_the_order_refund_by_prorata(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $a = $this->attachItem($order, qty: 1, unit: 3000);
        $b = $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executeFullRefund($by, PaymentRefund::MODE_MANUAL, false, PaymentRefund::INTENT_COMPENSATION);
        $this->assertTrue($result['ok'], json_encode($result));

        $fresh = $this->fresh($order);
        $book = OrderBook::forOrder($fresh);
        $this->assertBookCloses($book);
        $this->assertSame(0, $book->totalCents, '40,00 de nacimiento − 40,00 de cortesía');
        $this->assertSame(0, $book->paidCents);
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);

        $bookA = OrderBook::forReservation($fresh, $a);
        $bookB = OrderBook::forReservation($fresh, $b);
        foreach ([[$bookA, 3000], [$bookB, 1000]] as [$rb, $cents]) {
            $this->assertSame($cents, $rb->movements[0]->amountCents, 'el nacimiento de la reserva es nac(r)');
            $this->assertSame(-$cents, $rb->movements[1]->amountCents, 'su parte de la cortesía');
            $this->assertSame(0, $rb->totalCents);
            $this->assertSame([$cents, -$cents], array_column($rb->settlements, 'amountCents'), 'su cobro y su parte del reembolso total');
            $this->assertSame(Balance::KIND_SETTLED, $rb->balance->kind);
        }
        $this->assertSame($book->totalCents - $book->paidCents,
            ($bookA->totalCents - $bookA->paidCents) + ($bookB->totalCents - $bookB->paidCents), 'H');
    }

    public function test_the_reservation_book_rejects_a_line_that_is_not_a_principal(): void
    {
        $order = $this->makePaidOrder(1300);
        $item = $this->attachItem($order, qty: 1, unit: 1000);
        $child = $this->attachChild($order, $item, qty: 1, unit: 300);

        $this->expectException(\DomainException::class);
        OrderBook::forReservation($this->fresh($order), $child);
    }

    // ─── Aserciones ─────────────────────────────────────────────────────────────────────────

    /** I3 en el propio test: las líneas de valor suman el Total, y el pedido cuadra. */
    private function assertBookCloses(OrderBook $book): void
    {
        $this->assertSame($book->totalCents, $book->movementsSumCents(), 'I3 · Σ líneas de valor == Total');
        $this->assertTrue($book->isConsistent, 'las cuatro identidades cierran');
        $this->assertNotSame(Balance::KIND_UNDER_REVIEW, $book->balance->kind);
    }

    // ─── Fixtures ────────────────────────────────────────────────────────────────────────────

    private function reduce(Order $order, OrderItem $item, User $by, int $toQty): void
    {
        $item = $item->fresh();
        $delta = ($toQty - (int) $item->quantity) * (int) $item->unit_price;
        $order->recordEdit($item, $delta, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => (int) $item->quantity, 'new' => $toQty]]]);
        $item->forceFill(['quantity' => $toQty, 'seats' => $toQty])->save();
    }

    private function fresh(Order $order): Order
    {
        return Order::with(['items.children', 'items.slot', 'items.ticketType', 'payments.refunds', 'adjustments'])->findOrFail($order->id);
    }

    private function ensureTicketTypeSetup(): void
    {
        if (isset($this->jumpType)) {
            return;
        }
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->jumpType = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function makePaidOrder(int $total): Order
    {
        return $this->makeOrder(Order::STATUS_PAID, $total, paidAt: now());
    }

    private function makeOrder(string $status, int $total, ?\DateTimeInterface $paidAt, ?\DateTimeInterface $expiresAt = null): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-OB'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => $paidAt,
            'expires_at' => $expiresAt,
        ]);
    }

    private function attachPaidPayment(Order $order, int $amount, string $provider = 'redsys'): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'provider' => $provider,
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'gateway_order' => $provider === 'redsys' ? str_pad((string) (300000 + $this->counter), 10, '0', STR_PAD_LEFT) : null,
        ]);
    }

    /** Una fila de reembolso que NO tuvo éxito: lo que deja el flujo REST cuando la pasarela no responde o deniega. */
    private function attachRefundRow(Payment $payment, int $amount, string $status, ?int $itemId): PaymentRefund
    {
        return PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $itemId,
            'amount_cents' => $amount,
            'currency' => 'EUR',
            'status' => $status,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'requested_by' => User::factory()->create()->id,
            'requested_at' => now(),
            'processed_at' => $status === PaymentRefund::STATUS_PENDING ? null : now(),
        ]);
    }

    private function attachItem(Order $order, int $qty, int $unit, bool $past = false): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $h = str_pad((string) ($this->counter++ % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $past ? '2000-01-01' : now()->addDays(7)->format('Y-m-d'),
            'start_time' => $past ? '10:00:00' : "{$h}:00:00", 'end_time' => $past ? '10:59:00' : "{$h}:59:00",
            'capacity' => 20, 'online_capacity' => 20,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id, 'slot_id' => $slot->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);
    }

    private function attachChild(Order $order, OrderItem $parent, int $qty, int $unit): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jumpType->id, 'slot_id' => $parent->slot_id,
            'quantity' => $qty, 'seats' => 0, 'unit_price' => $unit,
        ]);
    }

    private function attachDepositSplit(Order $order, OrderItem $item, int $cents): void
    {
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT, 'amount_cents' => $cents,
            'currency' => 'EUR', 'reason' => 'deposit_split', 'applied_by' => $order->user_id,
        ]);
    }

    /** La línea de CRÉDITO de fiesta mixta tal como la escribe el reconciliador (T4): hija `is_credit` + gemelo `mixed` negativo. */
    private function attachCreditLine(Order $order, OrderItem $principal, int $cents, int $guests): OrderItem
    {
        $credit = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $principal->id,
            'ticket_type_id' => $this->jumpType->id, 'slot_id' => null,
            'quantity' => 1, 'free_quantity' => 0, 'unit_price' => $cents, 'is_credit' => true, 'seats' => 0,
        ]);
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $credit->id,
            'type' => OrderAdjustment::TYPE_MIXED, 'amount_cents' => -$cents, 'currency' => 'EUR',
            'applied_by' => User::factory()->create()->id, 'reason' => 'mixed_party_credit',
            'context' => ['mixed_party' => ['credit' => true, 'guests' => $guests, 'targets' => [['name' => 'Kids', 'count' => $guests, 'unit_cents' => intdiv($cents, $guests)]], 'derived_cents' => $cents]],
        ]);

        return $credit;
    }
}
