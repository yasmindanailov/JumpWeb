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
use App\Domain\Booking\Services\LineFacts;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Los HECHOS de una gestión y su LECTURA** (T1 del libro, `specs/desglose-libro.md` §4.2 y §6·T1,
 * guardas A · B · C; re-apuntada al LIBRO en la T3·4, §6.3.6).
 *
 * Hasta la T1 una bajada se ESCRIBÍA repartida en cubos —un crédito contra el cubo de ediciones,
 * otro contra el resto de la señal, un marcador de 0 € cuando ninguno la absorbía— y el resto de
 * una bajada cubierta a medias **no se persistía**. Ahora cada gestión deja UNA fila con su delta
 * entero, `LineFacts` la suma SIN cascada, y qué parte absorbe la puerta y qué parte se devuelve lo
 * dice el SALDO del libro (`Total − Pagado`), no un cubo. Estos casos fijan las dos mitades: que el
 * hecho esté ENTERO en la fila, y que el libro lea de él lo que el cliente tiene que ver.
 *
 * Sustituye a `OrderGateCreditTest`, que aseveraba la cascada como escritura; y desde la T3·4 ya
 * no cruza con el modelo de dos ejes, que se retiró.
 */
class EditMovementTest extends TestCase
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

    /**
     * Guarda A · una bajada de algo pagado ÍNTEGRO online deja UNA fila `edit` con −Δ, ninguna de
     * 0 €, y lo cobrado al nacer sale de los hechos. (Mutación que muerde: volver a escribir un
     * marcador de 0 € y reconstruir.)
     */
    public function test_a_fully_online_reduction_is_one_edit_row_with_the_whole_delta(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 2000);
        $item = $this->attachItem($order, qty: 2, unit: 1000);
        $this->attachPaidPayment($order, 2000);

        // 2 → 1: el editor escribe −10,00 y deja la fila en 1 × 10,00.
        $order->recordEdit($item, -1000, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 1]]]);
        $item->forceFill(['quantity' => 1, 'seats' => 1])->save();

        $rows = $order->adjustments()->where('order_item_id', $item->id)->get();
        $this->assertCount(1, $rows, 'una gestión, un hecho');
        $this->assertSame(OrderAdjustment::TYPE_EDIT, $rows->first()->type);
        $this->assertSame(-1000, (int) $rows->first()->amount_cents);
        $this->assertSame(0, $rows->where('amount_cents', 0)->count(), 'ningún marcador de 0 €');

        $fresh = $this->fresh($order);
        $facts = LineFacts::forItem($fresh, $fresh->items->firstWhere('id', $item->id));
        $this->assertSame(2000, $facts->birthValue(), 'nació valiendo 2 × 10,00: fila (10,00) − delta (−10,00)');
        $this->assertSame(2000, $facts->onlineAtBirth(), 'y todo se cobró online (sin reparto de señal)');
        $this->assertSame(-1000, $facts->editDelta);

        $book = OrderBook::forOrder($fresh);
        $this->assertTrue($book->isConsistent);
        $this->assertSame(1000, $book->totalCents, 'hoy vale 10,00');
        $this->assertSame(2000, $book->paidCents, 'y se pagaron 20,00');
        $this->assertSame(1000, $book->owedToCustomerCents(), 'el sobre-cobro aflora como saldo a devolver');
    }

    /**
     * Guarda B · una bajada que la puerta cubre A MEDIAS deja el delta ENTERO — lo que antes se
     * perdía era exactamente el resto. Pack 4 × 15,00 con señal 40,00 (reparto 20,00 en puerta);
     * baja a 1 invitado: −45,00 → el libro debe 25,00: los 20,00 que la puerta iba a cobrar ya no
     * se cobran, y no hace falta ningún cubo para saberlo. (Mutación que muerde: escribir solo la
     * parte cubierta.)
     */
    public function test_a_partially_covered_reduction_keeps_the_whole_delta_and_surfaces_the_rest(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 6000);
        $item = $this->attachItem($order, qty: 4, unit: 1500);
        $this->attachDepositSplit($order, $item, 2000);
        $this->attachPaidPayment($order, 4000);

        $order->recordEdit($item, -4500, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 4, 'new' => 1]]]);
        $item->forceFill(['quantity' => 1, 'seats' => 1])->save();

        $rows = $order->adjustments()->where('order_item_id', $item->id)->where('type', OrderAdjustment::TYPE_EDIT)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(-4500, (int) $rows->first()->amount_cents, 'el delta entero, no los 20,00 que la puerta cubre');

        $fresh = $this->fresh($order);
        $facts = LineFacts::forItem($fresh, $fresh->items->firstWhere('id', $item->id));
        $this->assertSame(6000, $facts->birthValue());
        $this->assertSame(4000, $facts->onlineAtBirth(), '60,00 de nacimiento − 20,00 de reparto');
        $this->assertSame(2000, $facts->depositSplit);

        $book = OrderBook::forOrder($fresh);
        $this->assertTrue($book->isConsistent);
        $this->assertSame(1500, $book->totalCents, '1 × 15,00');
        $this->assertSame(4000, $book->paidCents, 'la señal que entró');
        $this->assertSame(Balance::KIND_REFUND_AT_PARK, $book->balance->kind, 'hay visita por delante');
        $this->assertSame(2500, $book->owedToCustomerCents(), '40,00 − 15,00: el resto de la señal se absorbió solo');
    }

    /**
     * Guarda C · la identidad de NACIMIENTO: `Order.total` tiene que ser lo que las líneas dicen
     * que nació. Un total FABRICADO (el de una sonda, o una gestión que no dejó su fila) deja el
     * libro «en revisión». (Mutación que muerde: quitar `I1` de la consistencia del libro.)
     */
    public function test_a_total_that_does_not_match_the_birth_facts_puts_the_book_under_review(): void
    {
        $order = $this->makePaidOrder(total: 1000);
        $this->attachItem($order, qty: 4, unit: 1500);   // nació valiendo 60,00, no 10,00
        $this->attachPaidPayment($order, 1000);

        $book = OrderBook::forOrder($this->fresh($order));

        $this->assertFalse($book->isConsistent, 'lo facturado no es lo que nació: falta un hecho o el total se fabricó');
        $this->assertSame(Balance::KIND_UNDER_REVIEW, $book->balance->kind);
        $this->assertSame(6000, $this->fresh($order)->birthValueCents());

        // Y el control: con el total que le corresponde, cuadra.
        $order->update(['total' => 6000, 'subtotal' => 6000]);
        $order->payments()->update(['amount' => 6000]);
        $this->assertTrue(OrderBook::forOrder($this->fresh($order))->isConsistent);
    }

    // ─── El libro lee de los hechos lo que antes decían los cubos (los casos de `OrderGateCreditTest`) ──

    public function test_a_raise_and_a_smaller_reduction_leave_the_difference_to_pay_at_the_park(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 3000);
        $item = $this->attachItem($order, qty: 3, unit: 1000);
        $this->attachPaidPayment($order, 3000);

        $order->recordEdit($item, 2000, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 3, 'new' => 5]]]);
        $item->forceFill(['quantity' => 5, 'seats' => 5])->save();
        $order->recordEdit($item, -500, $by, 'item_edit_reduction', ['changes' => ['unit_price_change' => ['old' => 1000, 'new' => 900]]]);
        $item->forceFill(['unit_price' => 900])->save();

        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertTrue($book->isConsistent);
        $this->assertSame(4500, $book->totalCents);
        $this->assertSame(4500, $book->movementsSumCents(), 'I3: alta + subida + bajada');
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
        $this->assertSame(1500, $book->balance->cents, '45,00 − 30,00 pagados');
    }

    public function test_up_then_down_the_same_quantity_leaves_the_book_settled(): void
    {
        // El núcleo del bug JJ-WIMWJW del origen, a nivel de lectura: subir y bajar la misma
        // cantidad deja el saldo en cero — sin renglón fantasma de «+1» por cada subida.
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 2290);
        $item = $this->attachItem($order, qty: 1, unit: 2290);
        $this->attachPaidPayment($order, 2290);

        $order->recordEdit($item, 2290, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);
        $order->recordEdit($item, -2290, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 1]]]);

        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertTrue($book->isConsistent);
        $this->assertSame(2290, $book->totalCents);
        $this->assertSame(0, $book->balance->cents);
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
    }

    public function test_several_edits_add_up_to_one_balance(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 9000);
        $item = $this->attachItem($order, qty: 5, unit: 1800);
        $this->attachPaidPayment($order, 9000);

        // Dos subidas (+2 y +2) y una bajada (−1) → neto +3 unidades. Cada una es un movimiento del
        // libro; lo que el cliente debe es UN saldo.
        $order->recordEdit($item, 3600, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 5, 'new' => 7]]]);
        $order->recordEdit($item, 3600, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 7, 'new' => 9]]]);
        $order->recordEdit($item, -1800, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 9, 'new' => 8]]]);
        $item->forceFill(['quantity' => 8, 'seats' => 8])->save();

        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertTrue($book->isConsistent);
        $this->assertSame(14400, $book->totalCents);
        $this->assertSame(5400, $book->balance->cents, '3 × 18,00 neto, a pagar en el parque');
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
    }

    /**
     * Una bajada que llega ANTES de una subida: el modelo de dos ejes enseñaba «a pagar en el
     * parque 5,00» y «pendiente de devolverte 3,00» A LA VEZ (era lo que su cascada escribía, y la
     * foto de `78265ec` lo fijaba). El libro no tiene dos cubos: el saldo es UNO, con signo
     * (`DECISIONES #305`): 2,00 a pagar en el parque.
     */
    public function test_the_book_nets_a_reduction_and_a_later_raise_into_one_balance(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 1000);
        $item = $this->attachItem($order, qty: 1, unit: 1200);   // nació a 10,00; hoy vale 12,00
        $this->attachPaidPayment($order, 1000);

        $order->recordEdit($item, -300, $by, 'item_edit_reduction', ['changes' => ['unit_price_change' => ['old' => 1000, 'new' => 700]]]);
        $this->travel(1)->seconds();
        $order->recordEdit($item, 500, $by, 'item_edit', ['changes' => ['unit_price_change' => ['old' => 700, 'new' => 1200]]]);

        $fresh = $this->fresh($order);
        $this->assertSame(1000, LineFacts::forItem($fresh, $fresh->items->firstWhere('id', $item->id))->birthValue());

        $book = OrderBook::forOrder($fresh);
        $this->assertTrue($book->isConsistent);
        $this->assertSame(1200, $book->totalCents);
        $this->assertSame(1000, $book->paidCents);
        $this->assertSame(200, $book->balance->cents, 'un saldo, no dos cubos');
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
    }

    public function test_a_cancelled_line_owes_back_what_it_paid_and_none_of_its_raise(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 1000);
        $this->attachPaidPayment($order, 1000);

        $cancelled = $this->attachItem($order, qty: 2, unit: 1000);   // nació 1 × 10,00; subió a 2
        $order->recordEdit($cancelled, 1000, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);
        $cancelled->markCancelled($by);

        $book = OrderBook::forOrder($this->fresh($order));
        $this->assertTrue($book->isConsistent);
        $this->assertSame(0, $book->totalCents, 'cancelada: no vale nada, tampoco su subida');
        $this->assertSame(1000, $book->paidCents);
        $this->assertSame(1000, $book->owedToCustomerCents(), 'se le deben los 10,00 que pagó, no los 20,00 de la subida');
    }

    // ─── Fixtures ────────────────────────────────────────────────────────────────────────────

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
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-EM'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
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
            'gateway_order' => str_pad((string) (100000 + $this->counter), 10, '0', STR_PAD_LEFT),
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

    private function attachDepositSplit(Order $order, OrderItem $item, int $cents): void
    {
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT, 'amount_cents' => $cents,
            'currency' => 'EUR', 'reason' => 'deposit_split', 'applied_by' => $order->user_id,
        ]);
    }
}
