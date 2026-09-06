<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estado de un OrderItem: `finished` automático al pasar el slot + `active`/`finished`
 * de cara al cliente. (Antes `OrderItemPreparedTest`; las ramas de "preparado/sin
 * preparar" —markPrepared/displayStatusForStaff— se retiraron con el sistema, #202.)
 */
class OrderItemStatusTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump1h;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function makeSlot(string $date, string $startTime = '10:00:00', string $endTime = '11:00:00'): Slot
    {
        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => $startTime, 'end_time' => $endTime,
            'capacity' => 10, 'online_capacity' => 5,
        ]);
    }

    private function makeOrder(): Order
    {
        $user = User::factory()->create();

        return Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-T'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function makeItem(Order $order, Slot $slot, ?int $parentId = null): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'parent_item_id' => $parentId,
            'ticket_type_id' => $this->jump1h->id,
            'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
    }

    // ─── isFinishedInPractice ─────────────────────────────────────────────

    public function test_item_with_future_slot_is_not_finished(): void
    {
        $slot = $this->makeSlot('2099-01-01', '10:00:00', '11:00:00');
        $item = $this->makeItem($this->makeOrder(), $slot);

        $this->assertFalse($item->isFinishedInPractice());
    }

    public function test_item_with_past_slot_is_finished(): void
    {
        $slot = $this->makeSlot('2000-01-01', '10:00:00', '11:00:00');
        $item = $this->makeItem($this->makeOrder(), $slot);

        $this->assertTrue($item->isFinishedInPractice());
    }

    /**
     * ⚠️⚠️ **Este caso estaba ROJO UN MINUTO AL DÍA, y se cazó al cerrar sesión a las 00:01**
     * (`DECISIONES #255`). Construía la franja con `now()` —de `00:00:00` a `00:01:00` de HOY— y
     * afirmaba que ya había terminado. Durante los primeros sesenta segundos de cada día, no había
     * terminado: era la franja en curso.
     *
     * ▶ **Ni el fallo ni el arreglo son del sujeto**: `isFinishedInPractice()` respondía bien: la
     * pregunta estaba mal hecha. Un test que depende del reloj de pared no prueba la conducta, prueba
     * a qué hora se ejecuta — y `main` tiene dos carriles empujando, así que un rojo de un minuto le
     * cuesta la sesión a quien no lo escribió.
     *
     * ▶ Se fija el reloj con `travelTo`, que es la convención del repo para esto (`TESTING.md`), y
     * la franja pasa a ser una hora ya pasada del mismo día: **lo que el caso quería decir era «hoy,
     * pero ya terminada», no «hoy a las 00:00»**. `audit-clock.sh` existe para encontrar esta
     * familia; aquí la encontró el propio reloj.
     */
    public function test_item_with_today_slot_after_end_time_is_finished(): void
    {
        $this->travelTo('2026-05-14 18:30:00');

        $slot = $this->makeSlot('2026-05-14', '10:00:00', '11:00:00');
        $item = $this->makeItem($this->makeOrder(), $slot);

        $this->assertTrue($item->isFinishedInPractice());
    }

    /**
     * **Y su simétrico, que es el que faltaba**: la franja de HOY que aún no ha terminado.
     *
     * Sin este caso, el de arriba se podría cumplir con un `isFinishedInPractice()` que devolviera
     * `true` para cualquier fecha de hoy — que es exactamente el fallo que el caso roto tapaba.
     */
    public function test_item_with_today_slot_still_running_is_not_finished(): void
    {
        // ⚠️⚠️ **ESTE CASO CAMBIÓ DE PREMISA** (`DECISIONES #426`, `specs/hora-extra.md` §10.8·A1):
        // afirmaba que las franjas se interpretan en UTC, y lo que guardan es **hora de pared del
        // parque** — el defecto que `isFinishedInPractice()` arrastraba. `travelTo()` mueve el reloj
        // en la zona de la app (UTC), así que las 08:30 UTC son **las 10:30 del parque** en mayo:
        // media hora dentro de una franja que va de 10:00 a 11:00. Con la hora anterior (10:30 UTC =
        // 12:30 del parque) la visita llevaba una hora y media terminada.
        $this->travelTo('2026-05-14 08:30:00');

        $slot = $this->makeSlot('2026-05-14', '10:00:00', '11:00:00');
        $item = $this->makeItem($this->makeOrder(), $slot);

        $this->assertFalse($item->isFinishedInPractice());
    }

    public function test_the_end_is_the_products_duration_and_not_the_slots(): void
    {
        // La otra mitad de `#426`: el fin sale de la duración EFECTIVA de la reserva, no del fin de
        // la FRANJA. Una entrada de 1 h en una franja de 1 h coincide; lo que no coincidía —y es lo
        // que este caso fija— es todo lo que dura más que su franja, empezando por un pack de 2 h.
        $this->travelTo('2026-05-14 09:30:00');   // 11:30 del parque

        $pack = TicketType::create([
            'name' => ['es' => 'Cumple 2h'], 'zone_id' => $this->zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'min_qty' => 1, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
        ]);
        $slot = $this->makeSlot('2026-05-14', '10:00:00', '11:00:00');
        $item = $this->makeItem($this->makeOrder(), $slot);
        $item->forceFill(['ticket_type_id' => $pack->id])->save();

        // La franja acabó a las 11:00 del parque, pero la fiesta dura hasta las 12:00: sigue viva.
        $this->assertFalse($item->fresh('ticketType')->isFinishedInPractice());

        // Y con una hora extra comprada, hasta las 13:00.
        $item->forceFill(['extra_minutes' => 60])->save();
        $this->travelTo('2026-05-14 10:30:00');   // 12:30 del parque
        $this->assertFalse($item->fresh('ticketType')->isFinishedInPractice());
        $this->travelTo('2026-05-14 11:30:00');   // 13:30 del parque
        $this->assertTrue($item->fresh('ticketType')->isFinishedInPractice());
    }

    public function test_addon_inherits_finished_state_from_parent(): void
    {
        $order = $this->makeOrder();
        $parentSlot = $this->makeSlot('2000-01-01');
        $parent = $this->makeItem($order, $parentSlot);

        // Addon sin slot propio.
        $addon = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jump1h->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
        ]);

        $this->assertTrue($parent->isFinishedInPractice());
        $this->assertTrue($addon->isFinishedInPractice(),
            'Addon (parent_item_id != null) hereda finished del parent.');
    }

    public function test_addon_inherits_active_state_from_active_parent(): void
    {
        $order = $this->makeOrder();
        $parent = $this->makeItem($order, $this->makeSlot('2099-01-01'));
        $addon = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jump1h->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
        ]);

        $this->assertFalse($addon->isFinishedInPractice());
    }

    // ─── displayStatusForCustomer ─────────────────────────────────────────

    public function test_customer_sees_active_or_finished(): void
    {
        $active = $this->makeItem($this->makeOrder(), $this->makeSlot('2099-01-01'));
        $finished = $this->makeItem($this->makeOrder(), $this->makeSlot('2000-01-01'));

        $this->assertSame(OrderItem::STATUS_ACTIVE, $active->displayStatusForCustomer());
        $this->assertSame(OrderItem::STATUS_FINISHED, $finished->displayStatusForCustomer());
    }
}
