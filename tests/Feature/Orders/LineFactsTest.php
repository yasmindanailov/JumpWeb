<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\LineFacts;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Los hechos de una línea, sumados** (T3·4 de `specs/desglose-libro.md` §6.3.6): `LineFacts`
 * sustituye a `GateBuckets` y NO replica ninguna cascada — una suma con signo por clase de hecho,
 * y de ahí el valor de nacimiento, lo que la línea aportó al cobro online y lo que cobraría hoy.
 *
 * Mutaciones: `birthValue` ignora `editDelta` · `onlineAtBirth` ignora `depositSplit` · `mixed`
 * deja de contar como delta · `courtesy` entra en el delta.
 */
class LineFactsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $type;

    private User $by;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump']]);
        $this->type = TicketType::create([
            'name' => ['es' => 'Pulsera Jump'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->by = User::factory()->create();
    }

    public function test_a_line_without_facts_was_born_as_it_is_and_paid_it_all_online(): void
    {
        [$order, $item] = $this->orderWith(qty: 2, unit: 1000);

        $facts = LineFacts::forItem($order, $item);

        $this->assertSame(2000, $facts->charged);
        $this->assertSame(0, $facts->depositSplit);
        $this->assertSame(0, $facts->editDelta);
        $this->assertSame(0, $facts->courtesy);
        $this->assertSame(2000, $facts->birthValue());
        $this->assertSame(2000, $facts->onlineAtBirth());
        $this->assertSame(2000, $facts->onlineNow());
    }

    public function test_the_deposit_split_is_what_was_not_charged_online(): void
    {
        [$order, $item] = $this->orderWith(qty: 1, unit: 18000);
        $this->row($order, $item, OrderAdjustment::TYPE_DEPOSIT_SPLIT, 15000);

        $facts = LineFacts::forItem($order->fresh(['adjustments']), $item);

        $this->assertSame(15000, $facts->depositSplit);
        $this->assertSame(18000, $facts->birthValue(), 'el reparto no mueve el valor');
        $this->assertSame(3000, $facts->onlineAtBirth(), 'la señal: lo que aportó al cobro');
        $this->assertSame(3000, $facts->onlineNow());
    }

    public function test_edits_and_mixed_rows_move_the_value_but_not_the_birth(): void
    {
        // Nació 2 × 10,00; subió a 3 (+10,00) y luego un suplemento mixto de +7,00. Vale 37,00 hoy.
        [$order, $item] = $this->orderWith(qty: 3, unit: 1000);
        $item->forceFill(['unit_price' => 1000])->save();
        $this->row($order, $item, OrderAdjustment::TYPE_EDIT, 1000, ['changes' => ['quantity_change' => ['old' => 2, 'new' => 3]]]);
        // (La línea mixta vive en un hijo en producción; aquí se ata a la misma línea para sumar Δ.)
        $item->forceFill(['unit_price' => 1000, 'quantity' => 3])->save();
        $this->row($order, $item, OrderAdjustment::TYPE_MIXED, 700);
        $item->forceFill(['quantity' => 1, 'unit_price' => 3700])->save(); // fila = 37,00

        $facts = LineFacts::forItem($order->fresh(['adjustments']), $item->fresh());

        $this->assertSame(3700, $facts->charged);
        $this->assertSame(1700, $facts->editDelta, 'edit + mixed, con signo');
        $this->assertSame(2000, $facts->birthValue(), '37,00 − 17,00: con lo que nació');
        $this->assertSame(2000, $facts->onlineAtBirth());
    }

    public function test_a_reduction_is_a_negative_delta_without_any_cascade(): void
    {
        // Pack 4 × 15,00 con señal 40,00 (reparto 20,00); baja a 1 invitado: −45,00. Sin cubos: la
        // línea vale 15,00, nació valiendo 60,00 y aportó 40,00 al cobro; qué parte «absorbe la
        // puerta» lo dice el SALDO del libro, no un cubo.
        [$order, $item] = $this->orderWith(qty: 1, unit: 1500);
        $this->row($order, $item, OrderAdjustment::TYPE_DEPOSIT_SPLIT, 2000);
        $this->row($order, $item, OrderAdjustment::TYPE_EDIT, -4500, ['changes' => ['quantity_change' => ['old' => 4, 'new' => 1]]]);

        $facts = LineFacts::forItem($order->fresh(['adjustments']), $item);

        $this->assertSame(1500, $facts->charged);
        $this->assertSame(-4500, $facts->editDelta);
        $this->assertSame(6000, $facts->birthValue());
        $this->assertSame(4000, $facts->onlineAtBirth(), '60,00 − 20,00 de reparto');
        $this->assertSame(0, $facts->onlineNow(), '15,00 − 20,00: hoy no cobraría nada online (cinturón a 0)');
    }

    public function test_courtesy_is_summed_apart_and_never_moves_the_birth(): void
    {
        [$order, $item] = $this->orderWith(qty: 1, unit: 3000);
        $this->row($order, $item, OrderAdjustment::TYPE_COURTESY, -1000);

        $facts = LineFacts::forItem($order->fresh(['adjustments']), $item);

        $this->assertSame(-1000, $facts->courtesy);
        $this->assertSame(0, $facts->editDelta, 'la cortesía no es un delta de valor');
        $this->assertSame(3000, $facts->birthValue());
    }

    public function test_it_only_reads_the_rows_of_its_own_line(): void
    {
        [$order, $a] = $this->orderWith(qty: 1, unit: 1000);
        $b = OrderItem::create(['order_id' => $order->id, 'ticket_type_id' => $this->type->id, 'slot_id' => $a->slot_id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 2000]);
        $this->row($order, $b, OrderAdjustment::TYPE_EDIT, 500);

        $order = $order->fresh(['adjustments']);

        $this->assertSame(0, LineFacts::forItem($order, $a)->editDelta);
        $this->assertSame(500, LineFacts::forItem($order, $b)->editDelta);
        $this->assertSame(1500, LineFacts::forItem($order, $b)->birthValue());
    }

    // ─── Fixtures ─────────────────────────────────────────────────────────────

    /** @return array{0: Order, 1: OrderItem} */
    private function orderWith(int $qty, int $unit): array
    {
        $order = Order::create([
            'user_id' => $this->by->id,
            'code' => 'R-LF'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => $qty * $unit, 'tax' => 0, 'total' => $qty * $unit,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => '10:00:00', 'end_time' => '10:59:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->type->id, 'slot_id' => $slot->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);

        return [$order->fresh(['adjustments', 'items']), $item];
    }

    /** @param  array<string, mixed>|null  $context */
    private function row(Order $order, OrderItem $item, string $type, int $cents, ?array $context = null): void
    {
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id, 'type' => $type,
            'amount_cents' => $cents, 'currency' => 'EUR', 'applied_by' => $this->by->id, 'context' => $context,
        ]);
    }
}
