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
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Api\ApiTestCase;

/**
 * **EL MOTIVO MANDA EN EL REEMBOLSO** (T4 del libro, `specs/desglose-libro.md` §6.4, `DECISIONES
 * #316` `[DECIDIDO owner]`).
 *
 * Hasta la T4 la cortesía era «el exceso sobre lo debido, con cualquier motivo»: exacta en aritmética
 * y falsa en intención — el operador que elegía «devolver lo que se le debe» ANTES de registrar la
 * bajada que lo justificaba se encontraba una «Compensación» que no quiso y, tras la bajada, un
 * Total NEGATIVO con las cuatro identidades cerrando (`LB-ORDEN`, §6.3.7 hueco 4). Ahora:
 *
 *  - `value_returned` no puede exceder lo que el libro dice que se le debe en su ámbito (la reserva
 *    por línea, el pedido en el total): el modal lo capa y el dominio lo bloquea bajo lock
 *    (`exceeds_owed`) ANTES de la pasarela; nunca escribe cortesía;
 *  - `compensation` exige un motivo escrito (`compensation_without_note`), y la cortesía es el EXCESO
 *    sobre lo debido, con el motivo en `payment_refunds.reason` y en `context.note` de la fila;
 *  - el motivo es INTERNO (D-T4·1): lo pinta el panel y no viaja por la API.
 *
 * Mutaciones que muerden (medidas al escribirlo): escribir cortesía con `value_returned` · quitar el
 * tope del dominio · motivo opcional · transcribir `note` en `LedgerResource` · el modal sin capar.
 */
class RefundIntentGovernsTest extends ApiTestCase
{
    private Zone $zone;

    private TicketType $jumpType;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Notification::fake();
        App::setLocale('es');
    }

    // ─── El dominio: el tope de «devolver lo que se le debe» ─────────────────────────────────

    /** `LB-ORDEN`, cerrado: devolver «lo debido» ANTES de registrar la bajada es IMPOSIBLE. */
    public function test_value_returned_before_the_reduction_that_would_justify_it_is_blocked(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(2970);
        $item = $this->attachItem($order, qty: 3, unit: 990);
        $this->attachPaidPayment($order, 2970);

        $result = $this->fresh($order)->executePartialRefund($item, 1980, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_VALUE_RETURNED);

        $this->assertFalse($result['ok']);
        $this->assertSame('exceeds_owed', $result['reason'], 'no se le debe nada todavía: «devolver lo debido» no cabe');
        $this->assertSame(0, PaymentRefund::count(), 'el bloqueo va en la txn 1, ANTES de la pasarela: ni una fila');
        $this->assertSame(0, OrderAdjustment::where('type', OrderAdjustment::TYPE_COURTESY)->count());

        // Control: registrada la bajada, devolver exactamente lo debido pasa y no deja cortesía.
        $this->reduce($order, $item, $by, toQty: 1);
        $result = $this->fresh($order)->executePartialRefund($item->fresh(), 1980, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_VALUE_RETURNED);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(0, OrderAdjustment::where('type', OrderAdjustment::TYPE_COURTESY)->count(), 'con value_returned NUNCA hay cortesía');
        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertTrue($book->isConsistent);
        $this->assertSame(990, $book->totalCents, 'el Total es lo que vale: nunca negativo');
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
    }

    public function test_value_returned_above_what_is_owed_is_blocked_even_when_something_is_owed(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 4000);
        $this->reduce($order, $item, $by, toQty: 1);   // se le deben 20,00

        $result = $this->fresh($order)->executePartialRefund($item->fresh(), 2001, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_VALUE_RETURNED);

        $this->assertSame(['ok' => false, 'reason' => 'exceeds_owed'], $result, 'un céntimo por encima de lo debido ya no es «lo debido»');
    }

    public function test_the_total_refund_as_value_returned_is_blocked_when_the_payment_exceeds_what_is_owed(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 4000);
        $this->reduce($order, $item, $by, toQty: 1);   // se le deben 20,00; el pago entero son 40,00

        $result = $this->fresh($order)->executeFullRefund($by, PaymentRefund::MODE_MANUAL, false, PaymentRefund::INTENT_VALUE_RETURNED);

        $this->assertSame(['ok' => false, 'reason' => 'exceeds_owed'], $result);
        $this->assertSame(0, PaymentRefund::count());
    }

    /** «También cancelar» sigue sin tope explícito (D-T4·5): lo debido tras cancelar es todo lo cobrado. */
    public function test_the_total_refund_that_also_cancels_is_not_capped(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executeFullRefund($by, PaymentRefund::MODE_MANUAL, true, PaymentRefund::INTENT_VALUE_RETURNED);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(0, OrderAdjustment::where('type', OrderAdjustment::TYPE_COURTESY)->count());
    }

    /**
     * El ámbito de una línea es SU reserva: con dos reservas y la deuda en la otra, «devolver lo
     * debido» sobre la sana se bloquea aunque el PEDIDO deba dinero. (Mutación: medir con `forOrder`.)
     */
    public function test_the_cap_of_a_line_refund_is_its_own_reservation_not_the_order(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(7000);
        $a = $this->attachItem($order, qty: 1, unit: 3000);
        $b = $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 7000);
        $this->reduce($order, $b, $by, toQty: 1);   // por B se le deben 20,00; por A, nada

        $this->assertSame(2000, OrderBook::forOrder($this->fresh($order))->owedToCustomerCents(), 'fixture: el pedido debe 20,00');
        $result = $this->fresh($order)->executePartialRefund($a, 1000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_VALUE_RETURNED);

        $this->assertSame(['ok' => false, 'reason' => 'exceeds_owed'], $result, 'por A no se le debe nada');
    }

    // ─── El dominio: la compensación y su motivo ─────────────────────────────────────────────

    public function test_a_compensation_without_a_written_reason_is_rejected_in_both_refunds(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 1, unit: 4000);
        $this->attachPaidPayment($order, 4000);

        foreach ([null, '', '   '] as $blank) {
            $partial = $this->fresh($order)->executePartialRefund($item, 1000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION, $blank);
            $total = $this->fresh($order)->executeFullRefund($by, PaymentRefund::MODE_MANUAL, false, PaymentRefund::INTENT_COMPENSATION, $blank);
            $this->assertSame(['ok' => false, 'reason' => 'compensation_without_note'], $partial, var_export($blank, true));
            $this->assertSame(['ok' => false, 'reason' => 'compensation_without_note'], $total, var_export($blank, true));
        }
        $tooLong = str_repeat('x', Order::REFUND_NOTE_MAX_LENGTH + 1);
        $this->assertSame(['ok' => false, 'reason' => 'note_too_long'], $this->fresh($order)->executePartialRefund($item, 1000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION, $tooLong));
        $this->assertSame(0, PaymentRefund::count(), 'nada se escribió');
    }

    public function test_a_compensation_writes_only_the_excess_as_courtesy_with_the_reason_on_it(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(3960);
        $item = $this->attachItem($order, qty: 4, unit: 990);
        $this->attachPaidPayment($order, 3960);
        $this->reduce($order, $item, $by, toQty: 2);   // se le deben 19,80

        $result = $this->fresh($order)->executePartialRefund($item->fresh(), 2970, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION, '  Se cayó la atracción media hora  ');
        $this->assertTrue($result['ok'], json_encode($result));

        $refund = PaymentRefund::sole();
        $this->assertSame('Se cayó la atracción media hora', $refund->reason, 'el motivo, recortado, en `payment_refunds.reason`');
        $row = OrderAdjustment::where('type', OrderAdjustment::TYPE_COURTESY)->sole();
        $this->assertSame(-990, (int) $row->amount_cents, '29,70 devueltos − 19,80 debidos = 9,90 de descuento por cortesía');
        $this->assertSame(PaymentRefund::INTENT_COMPENSATION, $row->reason);
        $this->assertSame(['refund_id' => $refund->id, 'note' => 'Se cayó la atracción media hora'], $row->context);

        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertTrue($book->isConsistent);
        $courtesy = array_values(array_filter($book->movements, fn (Movement $m): bool => $m->kind === Movement::KIND_COURTESY));
        $this->assertCount(1, $courtesy);
        $this->assertSame('Descuento por cortesía', $courtesy[0]->label, 'la línea se llama así (`#316` decisión 5)');
        $this->assertSame('Se cayó la atracción media hora', $courtesy[0]->note, 'el motivo viaja en la línea… para el panel');
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
    }

    /** Una compensación que NO excede lo debido guarda el motivo igual y no deja cortesía. */
    public function test_a_compensation_within_what_is_owed_keeps_the_reason_and_writes_no_courtesy(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 4000);
        $this->reduce($order, $item, $by, toQty: 1);   // se le deben 20,00

        $result = $this->fresh($order)->executePartialRefund($item->fresh(), 1500, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION, 'Devuelvo parte ahora, el resto en el parque');
        $this->assertTrue($result['ok'], json_encode($result));

        $this->assertSame('Devuelvo parte ahora, el resto en el parque', PaymentRefund::sole()->reason);
        $this->assertSame(0, OrderAdjustment::where('type', OrderAdjustment::TYPE_COURTESY)->count());
    }

    /** Sin intención (un cliente programático, las filas viejas) tampoco hay cortesía: es una decisión, no un residuo. */
    public function test_a_refund_without_intent_writes_no_courtesy(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 1, unit: 4000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executePartialRefund($item, 1000, $by, PaymentRefund::MODE_MANUAL, false, [], null);
        $this->assertTrue($result['ok'], json_encode($result));

        $this->assertSame(0, OrderAdjustment::where('type', OrderAdjustment::TYPE_COURTESY)->count());
        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertTrue($book->isConsistent);
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind, 'el libro dice la verdad: se devolvió sin que bajara el valor');
    }

    // ─── El motivo es INTERNO: el panel lo pinta, la API no lo publica ───────────────────────

    public function test_the_reason_is_painted_by_the_panel_and_never_travels_through_the_api(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'admin')->value('id')]);
        $order = $this->makePaidOrder(4000, $customer);
        $item = $this->attachItem($order, qty: 1, unit: 4000);
        $this->attachPaidPayment($order, 4000);
        $note = 'Cortesía por la espera en recepción';

        $result = $this->fresh($order)->executePartialRefund($item, 1000, $staff, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION, $note);
        $this->assertTrue($result['ok'], json_encode($result));

        // El panel: la línea del libro y, debajo, el motivo — fuera de la línea que lee el cliente.
        $page = $this->actingAs($staff, 'web')->get('/admin/orders/'.$order->code)->assertOk()->getContent();
        $this->assertStringContainsString('Descuento por cortesía', $page);
        $this->assertStringContainsString('data-book-note', $page);
        $this->assertStringContainsString(__('admin.orders.book.movement_note', ['note' => $note]), $page);

        // La API: la misma línea, sin el motivo — ni como campo ni como texto.
        $ledger = $this->actingAs($customer)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');
        $courtesy = collect($ledger['movements'])->firstWhere('kind', 'courtesy');
        $this->assertNotNull($courtesy);
        $this->assertSame('Descuento por cortesía', $courtesy['label']);
        $this->assertArrayNotHasKey('note', $courtesy, 'D-T4·1: el motivo no viaja por el contrato');
        $this->assertStringNotContainsString($note, json_encode($ledger, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString($note, $this->actingAs($customer)->getJson(self::ROOT.'/orders/'.$order->code)->assertOk()->getContent());
    }

    // ─── El modal: la primera capa, antes del dominio ────────────────────────────────────────

    public function test_the_line_modal_caps_value_returned_to_what_is_owed(): void
    {
        $by = User::factory()->create();
        $staff = $this->admin();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 4000);
        $this->reduce($order, $item, $by, toQty: 1);   // se le deben 20,00

        // Un importe a medida por encima de lo debido: capado por el modal (no llega al dominio).
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'intent' => PaymentRefund::INTENT_VALUE_RETURNED,
                'items_to_refund' => [$item->id],
                'amount_mode' => 'custom',
                'custom_amount' => '20.01',
            ], arguments: ['item' => $item->id])
            ->assertHasActionErrors(['custom_amount']);
        $this->assertSame(0, PaymentRefund::count());

        // «Todo lo que queda» de la línea (40,00) tampoco cabe en lo debido (20,00): lo dice el motivo.
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'intent' => PaymentRefund::INTENT_VALUE_RETURNED,
                'items_to_refund' => [$item->id],
                'amount_mode' => 'remainder',
            ], arguments: ['item' => $item->id])
            ->assertHasActionErrors(['intent']);
        $this->assertSame(0, PaymentRefund::count());

        // Y exactamente lo debido, sin motivo escrito, pasa: sin cortesía.
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'intent' => PaymentRefund::INTENT_VALUE_RETURNED,
                'items_to_refund' => [$item->id],
                'amount_mode' => 'custom',
                'custom_amount' => '20.00',
            ], arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();
        $this->assertSame(2000, (int) PaymentRefund::sole()->amount_cents);
        $this->assertSame(PaymentRefund::INTENT_VALUE_RETURNED, PaymentRefund::sole()->intent);
        $this->assertSame(0, OrderAdjustment::where('type', OrderAdjustment::TYPE_COURTESY)->count());
    }

    public function test_the_line_modal_disables_value_returned_when_nothing_is_owed_and_requires_a_reason_for_compensation(): void
    {
        $staff = $this->admin();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 1, unit: 4000);
        $this->attachPaidPayment($order, 4000);

        // «Devolver lo que se le debe» con 0 debido: la opción está deshabilitada y no se acepta.
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'intent' => PaymentRefund::INTENT_VALUE_RETURNED,
                'items_to_refund' => [$item->id],
                'amount_mode' => 'custom',
                'custom_amount' => '10.00',
            ], arguments: ['item' => $item->id])
            ->assertHasActionErrors(['intent']);

        // Compensación sin motivo: error del formulario, antes del dominio.
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'intent' => PaymentRefund::INTENT_COMPENSATION,
                'items_to_refund' => [$item->id],
                'amount_mode' => 'custom',
                'custom_amount' => '10.00',
            ], arguments: ['item' => $item->id])
            ->assertHasActionErrors(['refund_note']);
        $this->assertSame(0, PaymentRefund::count());

        // Con motivo: pasa, y el motivo llega al reembolso y a la fila de cortesía.
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'intent' => PaymentRefund::INTENT_COMPENSATION,
                'items_to_refund' => [$item->id],
                'amount_mode' => 'custom',
                'custom_amount' => '10.00',
                'refund_note' => 'Cumpleaños con la pista mojada',
            ], arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();
        $this->assertSame('Cumpleaños con la pista mojada', PaymentRefund::sole()->reason);
        $this->assertSame('Cumpleaños con la pista mojada', OrderAdjustment::where('type', OrderAdjustment::TYPE_COURTESY)->sole()->context['note']);
    }

    public function test_the_order_modal_offers_value_returned_only_when_what_is_owed_covers_the_whole_payment(): void
    {
        $by = User::factory()->create();
        $staff = $this->admin();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 4000);
        $this->reduce($order, $item, $by, toQty: 1);   // se le deben 20,00; el pago entero son 40,00

        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => false,
                'intent' => PaymentRefund::INTENT_VALUE_RETURNED,
            ])
            ->assertHasActionErrors(['intent']);
        $this->assertSame(0, PaymentRefund::count());

        // Compensación sin motivo: el formulario lo exige.
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => false,
                'intent' => PaymentRefund::INTENT_COMPENSATION,
            ])
            ->assertHasActionErrors(['refund_note']);

        // Con motivo: 40,00 devueltos, 20,00 debidos → 20,00 de descuento por cortesía con el motivo.
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => false,
                'intent' => PaymentRefund::INTENT_COMPENSATION,
                'refund_note' => 'Le devolvemos todo por las molestias',
            ])
            ->assertHasNoActionErrors();
        $row = OrderAdjustment::where('type', OrderAdjustment::TYPE_COURTESY)->sole();
        $this->assertSame(-2000, (int) $row->amount_cents);
        $this->assertSame('Le devolvemos todo por las molestias', $row->context['note']);

        // Y cuando lo debido cubre el pago entero (todas las líneas canceladas), «devolver lo debido» cabe.
        $other = $this->makePaidOrder(4000);
        $line = $this->attachItem($other, qty: 1, unit: 4000);
        $this->attachPaidPayment($other, 4000);
        $line->markCancelled($by);
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $other->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => false,
                'intent' => PaymentRefund::INTENT_VALUE_RETURNED,
            ])
            ->assertHasNoActionErrors();
        $this->assertSame(PaymentRefund::INTENT_VALUE_RETURNED, PaymentRefund::latest('id')->first()->intent);
        $this->assertSame(1, OrderAdjustment::where('type', OrderAdjustment::TYPE_COURTESY)->count(), 'la del pedido anterior; ésta no dejó ninguna');
    }

    // ─── Fixtures ────────────────────────────────────────────────────────────────────────────

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

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

    private function makePaidOrder(int $total, ?User $customer = null): Order
    {
        return Order::create([
            'user_id' => ($customer ?? User::factory()->create())->id,
            'code' => 'JJ-RI'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function attachPaidPayment(Order $order, int $amount): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'gateway_order' => str_pad((string) (400000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
    }

    private function attachItem(Order $order, int $qty, int $unit): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $h = str_pad((string) ($this->counter++ % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 20, 'online_capacity' => 20,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id, 'slot_id' => $slot->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);
    }
}
