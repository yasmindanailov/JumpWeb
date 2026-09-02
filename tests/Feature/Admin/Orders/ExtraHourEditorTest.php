<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Contracts\ItemActionOutcome;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ItemRescheduleOffer;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * La HORA EXTRA en el EDITOR del panel (`specs/hora-extra.md` §4.4·3, §4.4·6 y los bordes 1, 4 y 5
 * de §4.6, `#410`): mover el padre MUEVE a la hija (o no se mueve nada) · la huella excluida es la
 * de TODA la familia · bajar el padre por debajo de los que se quedan se RECHAZA · subir la hija
 * recalcula sus plazas · y una hora extra añadida desde «Gestionar → Complementos» NACE ocupando —
 * el punto de nacimiento con literales que la primera revisión adversarial cazó (§4.10·1).
 *
 * Se conduce el SERVICIO directamente (como `ManageItemSlotChangeTest` hace en sus capas 4/5): la
 * página de Filament solo decora, y estos son contratos del dominio.
 */
class ExtraHourEditorTest extends TestCase
{
    use RefreshDatabase;

    private OrderItemEditor $editor;

    private Zone $zone;

    private string $date;

    private TicketType $entry;

    private TicketType $extraHour;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->editor = app(OrderItemEditor::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(7)->toDateString();

        foreach (['10:00:00', '11:00:00', '12:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 50, 'online_capacity' => 50,
            ]);
        }

        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => (int) RateType::value('id'), 'amount_cents' => 1000]);

        $this->extraHour = TicketType::create([
            'name' => ['es' => 'Hora extra'], 'type' => TicketType::TYPE_ADDON, 'zone_id' => null,
            'duration_min' => 60, 'occupies_after_parent' => true,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
        ]);
        $this->extraHour->prices()->create(['rate_type_id' => (int) RateType::value('id'), 'amount_cents' => 500]);

        $this->entry->addons()->attach($this->extraHour->id, [
            'position' => 1, 'is_included' => false, 'included_quantity' => 1,
            'is_mandatory' => false, 'quantity_mode' => 'fixed', 'allow_extra' => true,
            'choice_group' => null, 'max_qty' => null, 'requires_addon_id' => null,
        ]);
    }

    // ─── Mover el padre MUEVE a la hija (borde 4) ───────────────────────────────────────────────

    public function test_moving_the_parent_drags_the_child(): void
    {
        [$order, $item, $child] = $this->paidFamily('10:00:00', qty: 4, staying: 1);

        $outcome = $this->editor->changeSlot(
            $order, $item, $this->date, '11:00:00',
            (string) $item->updated_at->getTimestamp(), null, $this->staffWithEdit(),
        );

        $this->assertFalse($outcome->isBlocked(), (string) $outcome->reason);
        $this->assertSame($this->slotAt('11:00:00')->id, (int) $item->fresh()->slot_id);
        $this->assertSame(
            $this->slotAt('12:00:00')->id, (int) $child->fresh()->slot_id,
            'la hija se muda CON el padre: su franja es la siguiente al tramo NUEVO',
        );
    }

    public function test_the_family_footprint_is_excluded_as_one(): void
    {
        // Borde 1: la edición del padre no puede competir contra su PROPIA hora extra. Las 11:00
        // se dejan con hueco EXACTO (padre 4 + hija 1 ya dentro): si la exclusión fuera solo del
        // padre, la hija se contaría y el movimiento legítimo se bloquearía.
        $this->slotAt('11:00:00')->update(['capacity' => 4, 'online_capacity' => 4]);
        [$order, $item, $child] = $this->paidFamily('10:00:00', qty: 4, staying: 1);

        $outcome = $this->editor->changeSlot(
            $order, $item, $this->date, '11:00:00',
            (string) $item->updated_at->getTimestamp(), null, $this->staffWithEdit(),
        );

        $this->assertFalse($outcome->isBlocked(), 'con la huella FAMILIAR excluida, 4 plazas de 4 caben: '.$outcome->reason);
        $this->assertSame($this->slotAt('12:00:00')->id, (int) $child->fresh()->slot_id);
    }

    public function test_a_destination_where_the_child_cannot_land_is_blocked(): void
    {
        [$order, $item, $child] = $this->paidFamily('10:00:00', qty: 4, staying: 1);

        // A las 12:00 no hay «franja siguiente» (el día acaba): la familia no aterriza → nada se mueve.
        $outcome = $this->editor->changeSlot(
            $order, $item, $this->date, '12:00:00',
            (string) $item->updated_at->getTimestamp(), null, $this->staffWithEdit(),
        );

        $this->assertTrue($outcome->isBlocked());
        $this->assertSame('addon_occupancy_at_destination', $outcome->reason);
        $this->assertSame($this->slotAt('10:00:00')->id, (int) $item->fresh()->slot_id, 'el padre NO se movió');
        $this->assertSame($this->slotAt('11:00:00')->id, (int) $child->fresh()->slot_id, 'la hija tampoco');

        // El CONTROL: el mismo movimiento sin hora extra entra sin problema.
        [$order2, $item2] = $this->paidFamily('10:00:00', qty: 2, staying: 0);
        $outcome2 = $this->editor->changeSlot(
            $order2, $item2, $this->date, '12:00:00',
            (string) $item2->updated_at->getTimestamp(), null, $this->staffWithEdit(),
        );
        $this->assertFalse($outcome2->isBlocked(), (string) $outcome2->reason);
    }

    // ─── Bajar el padre por debajo de los que se quedan (borde 5) ───────────────────────────────

    public function test_lowering_the_parent_below_the_staying_is_rejected(): void
    {
        [$order, $item] = $this->paidFamily('10:00:00', qty: 4, staying: 2);

        $outcome = $this->edit($order, $item, newQty: 1);

        $this->assertTrue($outcome->isBlocked());
        $this->assertSame('addon_stay_exceeds_quantity', $outcome->reason);
        $this->assertSame(4, (int) $item->fresh()->quantity, 'no se aceptó en silencio');

        // El borde exacto es legítimo: quedan 2 de 2.
        $outcome2 = $this->edit($order, $item->fresh(), newQty: 2);
        $this->assertFalse($outcome2->isBlocked(), (string) $outcome2->reason);
    }

    // ─── Subir la hija recalcula sus plazas (§4.4·6, el «gemelo silencioso») ────────────────────

    public function test_raising_the_child_recalculates_its_seats(): void
    {
        [$order, $item, $child] = $this->paidFamily('10:00:00', qty: 4, staying: 1);

        $outcome = $this->edit($order, $item, newQty: 4, addonEdits: [
            'edits' => [['child_id' => $child->id, 'quantity' => 3]],
            'adds' => [],
        ]);

        $this->assertFalse($outcome->isBlocked(), (string) $outcome->reason);
        $this->assertSame(3, (int) $child->fresh()->quantity);
        $this->assertSame(3, (int) $child->fresh()->seats, 'tres personas ocupando TRES plazas, no una');
    }

    public function test_raising_the_child_beyond_the_next_slot_capacity_is_blocked(): void
    {
        $this->slotAt('11:00:00')->update(['capacity' => 2, 'online_capacity' => 2]);
        [$order, $item, $child] = $this->paidFamily('10:00:00', qty: 4, staying: 1);

        $outcome = $this->edit($order, $item, newQty: 4, addonEdits: [
            'edits' => [['child_id' => $child->id, 'quantity' => 3]],
            'adds' => [],
        ]);

        $this->assertTrue($outcome->isBlocked());
        $this->assertSame('addon_occupancy_at_destination', $outcome->reason);
        $this->assertSame(1, (int) $child->fresh()->quantity, 'nada se escribió');
    }

    // ─── El NACIMIENTO desde el panel (§4.10·1: los literales) ──────────────────────────────────

    public function test_an_extra_hour_added_from_the_panel_is_born_occupying(): void
    {
        [$order, $item] = $this->paidFamily('10:00:00', qty: 4, staying: 0);

        $outcome = $this->edit($order, $item, newQty: 4, addonEdits: [
            'edits' => [],
            'adds' => [['ticket_type_id' => $this->extraHour->id, 'quantity' => 2]],
        ]);

        $this->assertFalse($outcome->isBlocked(), (string) $outcome->reason);
        $child = $item->fresh()->children()->whereNull('cancelled_at')->first();
        $this->assertNotNull($child);
        $this->assertSame($this->slotAt('11:00:00')->id, (int) $child->slot_id, 'nace con su franja, no con el literal null');
        $this->assertSame(2, (int) $child->seats, 'y con sus plazas, no con el literal 0');
    }

    // ─── La oferta de re-programación (borde 4, la otra mitad) ──────────────────────────────────

    public function test_the_reschedule_offer_hides_hours_where_the_child_does_not_fit(): void
    {
        [, $item] = $this->paidFamily('10:00:00', qty: 2, staying: 1);

        $times = array_column(app(ItemRescheduleOffer::class)->times($item->fresh(['ticketType', 'slot']), $this->date), 'time');

        $this->assertContains('10:00:00', $times, 'la hora ACTUAL siempre se ofrece');
        $this->assertContains('11:00:00', $times, 'a las 11:00 la hija aterriza a las 12:00 y cabe');
        $this->assertNotContains('12:00:00', $times, 'a las 12:00 la hija no tiene franja siguiente: ofrecerla es AFORO-02 por la puerta de la edición');
    }

    public function test_the_reschedule_offer_control_without_children_keeps_the_last_hour(): void
    {
        // El CONTROL del caso anterior: sin hora extra, las 12:00 se ofrecen — si también
        // desaparecieran, el filtro nuevo estaría escondiendo horas a TODOS los ítems.
        [, $item] = $this->paidFamily('10:00:00', qty: 2, staying: 0);

        $times = array_column(app(ItemRescheduleOffer::class)->times($item->fresh(['ticketType', 'slot']), $this->date), 'time');

        $this->assertContains('12:00:00', $times);
    }

    // ─── Fixture ────────────────────────────────────────────────────────────────────────────────

    /**
     * Un pedido PAGADO con una entrada de `$qty` y, si `$staying > 0`, su hora extra ya vendida
     * ocupando la franja siguiente — las filas como las escribe `OrderCreator` (hechos, no catálogo).
     *
     * @return array{0: Order, 1: OrderItem, 2: OrderItem|null}
     */
    private function paidFamily(string $time, int $qty, int $staying): array
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-XH'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000 * $qty + 500 * $staying,
            'total' => 1000 * $qty + 500 * $staying,
            'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) ($this->counter + 200000), 10, '0', STR_PAD_LEFT),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->entry->id, 'slot_id' => $this->slotAt($time)->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => 1000,
        ]);

        $child = null;
        if ($staying > 0) {
            $childStart = Carbon::parse($time)->addHour()->format('H:i:s');
            $child = OrderItem::create([
                'order_id' => $order->id, 'parent_item_id' => $item->id,
                'ticket_type_id' => $this->extraHour->id, 'slot_id' => $this->slotAt($childStart)->id,
                'quantity' => $staying, 'seats' => $staying, 'unit_price' => 500,
            ]);
        }

        return [$order->fresh(), $item->fresh(['ticketType', 'slot']), $child];
    }

    private function edit(Order $order, OrderItem $item, int $newQty, array $addonEdits = ['edits' => [], 'adds' => []]): ItemActionOutcome
    {
        return $this->editor->edit(
            $order, $item, '', '', false,
            (int) $item->ticket_type_id, $newQty, null, $addonEdits,
            (string) $item->updated_at->getTimestamp(),
            $this->staffWithEdit(),
        );
    }

    private function staffWithEdit(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(
            Permission::whereIn('name', ['orders.view', 'orders.edit_item'])->pluck('id'),
        );

        return $u->fresh();
    }

    private function slotAt(string $time): Slot
    {
        return Slot::where('zone_id', $this->zone->id)->where('date', $this->date)
            ->where('start_time', $time)->firstOrFail();
    }
}
