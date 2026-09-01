<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GateBuckets;
use App\Domain\Booking\Services\OrderLedger;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Los HECHOS de una gestión y su LECTURA** (T1 del libro, `specs/desglose-libro.md` §4.2 y §6·T1,
 * guardas A · B · C).
 *
 * Hasta la T1 una bajada se ESCRIBÍA repartida en cubos —un crédito contra el cubo de ediciones,
 * otro contra el resto de la señal, un marcador de 0 € cuando ninguno la absorbía— y el resto de
 * una bajada cubierta a medias **no se persistía**. Ahora cada gestión deja UNA fila con su delta
 * entero y el reparto entre cubos lo replica la lectura (`GateBuckets`) en el orden en que
 * ocurrió. Estos casos fijan las dos mitades: que el hecho esté ENTERO en la fila, y que la
 * lectura dé exactamente lo que daba la cascada (la foto puente de `OrderFinancialInvariantsTest`
 * lo vigila sobre los 15 escenarios; aquí se ejercitan los que la cascada no sabía guardar).
 *
 * Sustituye a `OrderGateCreditTest`, que aseveraba la cascada como escritura.
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
        $item = $fresh->items->firstWhere('id', $item->id);
        $buckets = GateBuckets::forItem($fresh, $item);
        $this->assertSame(2000, $buckets->birthValue(), 'nació valiendo 2 × 10,00: fila (10,00) − delta (−10,00)');
        $this->assertSame(2000, $buckets->onlineAtBirth(), 'y todo se cobró online (sin reparto de señal)');
        $this->assertSame(1000, $buckets->uncovered, 'ningún cubo de puerta absorbió la bajada');
        $this->assertSame(2000, $fresh->itemOriginalOnlineCents($item));
        $this->assertSame(1000, $fresh->itemPendingRefundCents($item), 'el sobre-cobro aflora');
        $this->assertSame(1000, $fresh->financialSummary()->pendienteDevolucion());
        $this->assertTrue(OrderLedger::forOrder($fresh)->cuadra);
    }

    /**
     * Guarda B · una bajada que la puerta cubre A MEDIAS deja el delta ENTERO — lo que antes se
     * perdía era exactamente el resto. Pack 4 × 15,00 con señal 40,00 (reparto 20,00 en puerta);
     * baja a 1 invitado: −45,00 → la puerta absorbe 20,00 y 25,00 aflora como pendiente de
     * devolución. (Mutación que muerde: escribir solo la parte cubierta.)
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
        $item = $fresh->items->firstWhere('id', $item->id);
        $buckets = GateBuckets::forItem($fresh, $item);
        $this->assertSame(0, $buckets->depositRemainder, 'el resto de la señal quedó absorbido entero');
        $this->assertSame(2500, $buckets->uncovered, 'y lo que la puerta no cubrió');
        $this->assertSame(6000, $buckets->birthValue());
        $this->assertSame(4000, $buckets->onlineAtBirth(), '60,00 de nacimiento − 20,00 de reparto');
        $this->assertSame(0, $fresh->financialSummary()->pendingAtGate());
        $this->assertSame(2500, $fresh->financialSummary()->pendienteDevolucion());
        $this->assertTrue(OrderLedger::forOrder($fresh)->cuadra);
    }

    /**
     * Guarda C · la identidad de NACIMIENTO: `Order.total` tiene que ser lo que las líneas dicen
     * que nació. Un total FABRICADO (el de una sonda, o una gestión que no dejó su fila) deja el
     * desglose «en revisión». (Mutación que muerde: quitar `I1` de `OrderLedger::cierra`.)
     */
    public function test_a_total_that_does_not_match_the_birth_facts_puts_the_ledger_under_review(): void
    {
        $order = $this->makePaidOrder(total: 1000);
        $this->attachItem($order, qty: 4, unit: 1500);   // nació valiendo 60,00, no 10,00
        $this->attachPaidPayment($order, 1000);

        $ledger = OrderLedger::forOrder($this->fresh($order));

        $this->assertFalse($ledger->cuadra, 'lo facturado no es lo que nació: falta un hecho o el total se fabricó');
        $this->assertSame(6000, $this->fresh($order)->birthValueCents());

        // Y el control: con el total que le corresponde, cuadra.
        $order->update(['total' => 6000, 'subtotal' => 6000]);
        $order->payments()->update(['amount' => 6000]);
        $this->assertTrue(OrderLedger::forOrder($this->fresh($order))->cuadra);
    }

    // ─── La lectura da lo que daba la cascada (los casos de `OrderGateCreditTest`) ───────────

    public function test_a_raise_and_a_smaller_reduction_net_in_the_edit_bucket(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 3000);
        $item = $this->attachItem($order, qty: 3, unit: 1000);

        $order->recordEdit($item, 2000, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 3, 'new' => 5]]]);
        $order->recordEdit($item, -500, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 5, 'new' => 4]]]);

        $fresh = $this->fresh($order);
        $this->assertSame(1500, $fresh->itemExtraDueCents($item));
        $this->assertSame(1500, $fresh->financialSummary()->pendingAtGate());
    }

    public function test_up_then_down_the_same_quantity_nets_to_zero_and_paints_no_line(): void
    {
        // El núcleo del bug JJ-WIMWJW del origen, a nivel de lectura: subir y bajar la misma
        // cantidad deja neto 0 y NO pinta ninguna línea.
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 2290);
        $item = $this->attachItem($order, qty: 1, unit: 2290);

        $order->recordEdit($item, 2290, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);
        $order->recordEdit($item, -2290, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 1]]]);

        $fresh = $this->fresh($order);
        $this->assertSame(0, $fresh->itemExtraDueCents($item));
        $this->assertSame(0, $fresh->financialSummary()->pendingAtGate());
        $this->assertSame([], $fresh->pendingAtGateLines());
    }

    public function test_gate_lines_group_by_item_with_the_net_quantity_and_add_up_to_the_total(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 9000);
        $item = $this->attachItem($order, qty: 5, unit: 1800);

        // Dos subidas (+2 y +2) y una bajada (−1) → neto +3 unidades.
        $order->recordEdit($item, 3600, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 4]]]);
        $order->recordEdit($item, 3600, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 4, 'new' => 6]]]);
        $order->recordEdit($item, -1800, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 6, 'new' => 5]]]);

        $fresh = $this->fresh($order);
        $lines = $fresh->pendingAtGateLines();
        $this->assertCount(1, $lines);
        $this->assertSame(5400, $lines[0]['amount']);           // 3 × 1800 neto
        $this->assertSame('+3 Jump 1h', $lines[0]['label']);    // cantidad NETA, no la suma de subidas
        $this->assertSame($fresh->financialSummary()->pendingAtGate(), array_sum(array_column($lines, 'amount')));
    }

    /**
     * El ORDEN del replay es el de los hechos: una bajada que llega ANTES de una subida no tiene
     * cubo contra el que netear (aflora como pendiente de devolución), y la subida posterior queda
     * entera en puerta. Es lo que la cascada hacía al escribir, y lo que la foto de `78265ec`
     * fija: el cliente ve «a pagar en el parque 5,00» y «pendiente de devolverte 3,00» a la vez.
     */
    public function test_the_replay_follows_the_order_in_which_things_happened(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 1000);
        $item = $this->attachItem($order, qty: 1, unit: 1200);   // nació a 10,00; hoy vale 12,00
        $this->attachPaidPayment($order, 1000);

        $order->recordEdit($item, -300, $by, 'item_edit_reduction', ['changes' => ['unit_price_change' => ['old' => 1000, 'new' => 700]]]);
        $this->travel(1)->seconds();
        $order->recordEdit($item, 500, $by, 'item_edit', ['changes' => ['unit_price_change' => ['old' => 700, 'new' => 1200]]]);

        $fresh = $this->fresh($order);
        $buckets = GateBuckets::forItem($fresh, $fresh->items->firstWhere('id', $item->id));
        $this->assertSame(500, $buckets->extraDue, 'la subida posterior no se come la bajada anterior');
        $this->assertSame(300, $buckets->uncovered);
        $this->assertSame(1000, $buckets->birthValue());
        $this->assertSame(500, $fresh->financialSummary()->pendingAtGate());
        $this->assertSame(300, $fresh->financialSummary()->pendienteDevolucion());
    }

    public function test_gate_lines_exclude_cancelled_items(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(total: 1000);

        $cancelled = $this->attachItem($order, qty: 2, unit: 1000);
        $order->recordEdit($cancelled, 1000, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);
        $cancelled->markCancelled($by);

        $fresh = $this->fresh($order);
        $this->assertSame([], $fresh->pendingAtGateLines());
        $this->assertSame(0, $fresh->financialSummary()->pendingAtGate());
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
