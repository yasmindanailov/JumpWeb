<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 5 (Capa 2b) — Aforo de PACKS (cumpleaños) por CUPO configurable por franja (#82): dos
 * topes (nº de fiestas y nº de niños), pool propio independiente de las entradas, y la
 * preparación (montaje/limpieza) bloqueando franjas vecinas de forma ACTIVABLE/DESACTIVABLE
 * (decisión 2026-05-25). Cubre el servicio {@see PackAvailability} y su integración en
 * {@see OrderCreator} (bajo lockForUpdate). Ver docs/PLAN-COMPRA-PRODUCTOS.md (§9.3) y DECISIONES.
 */
class PackAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private PackAvailability $availability;

    private OrderCreator $creator;

    private User $user;

    private Zone $zone;

    private TicketType $pack;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->availability = app(PackAvailability::class);
        $this->creator = app(OrderCreator::class);
        $this->user = User::factory()->create();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        // Zona operativa de cumpleaños: is_active=true (opera/vende) + show_in_landing=false
        // (oculta de la landing). Desacoplado en #210.
        $this->zone = Zone::create(['slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        // Rejilla horaria 10:00–20:00 (las franjas solo aportan fecha/hora; el aforo va por cupo).
        foreach (range(10, 20) as $hour) {
            Slot::create([
                'zone_id' => $this->zone->id,
                'date' => $this->date,
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 200,
                'online_capacity' => 200,
            ]);
        }

        // Pack de ejemplo: 2h de fiesta + 1h de montaje + 30' de limpieza; 10–20 niños; 15€/niño.
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'],
            'zone_id' => $this->zone->id,
            'type' => TicketType::TYPE_PACK,
            'duration_min' => 120,
            'prep_before_min' => 60,
            'prep_after_min' => 30,
            'min_qty' => 10,
            'max_qty' => 20,
            'seats_per_unit' => 1,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 1,
        ]);
        $this->pack->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'),
            'amount_cents' => 1500,
        ]);

        // Cupo por defecto (placeholders); cada test ajusta lo que necesite.
        $this->setSetting(PackAvailability::SETTING_MAX_PER_SLOT, '5');
        $this->setSetting(PackAvailability::SETTING_MAX_GUESTS_PER_SLOT, '60');
        $this->setSetting(PackAvailability::SETTING_PREP_BLOCKS_CUPO, '1');
    }

    private function setSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['key' => $key, 'value' => $value, 'group' => 'packs']);
    }

    private function slotAt(string $time): Slot
    {
        return Slot::where('zone_id', $this->zone->id)->where('date', $this->date)->where('start_time', $time)->firstOrFail();
    }

    /** @return array{ticket_type_id:int, date:string, time:string, qty:int} */
    private function line(string $time, int $qty): array
    {
        return ['ticket_type_id' => $this->pack->id, 'date' => $this->date, 'time' => $time, 'qty' => $qty];
    }

    private function partyOrder(string $time, int $guests, string $status = Order::STATUS_PAID, ?Carbon $expiresAt = null): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id,
            'slot_id' => $this->slotAt($time)->id,
            'quantity' => $guests,
            'unit_price' => 1500,
            'seats' => $guests,
        ]);
    }

    public function test_empty_slot_offers_up_to_the_pack_max(): void
    {
        // Sin fiestas: el cupo de niños (60) es de sobra → lo limita el máximo del pack (20).
        $this->assertSame(20, $this->availability->availableGuestsFor($this->slotAt('14:00:00'), $this->pack));
    }

    public function test_pack_not_offered_when_the_party_would_run_past_the_grid(): void
    {
        // Rejilla 10:00–21:00 (franjas 10..20, cada una termina +1 h). Pack de 2 h.
        // A las 20:00 la fiesta terminaría a las 22:00 (1 h fuera de la rejilla/horario) → 0.
        $this->assertSame(0, $this->availability->availableGuestsFor($this->slotAt('20:00:00'), $this->pack));
        // A las 19:00 la fiesta (19:00–21:00) cabe justa → ofrece hasta el máximo del pack.
        $this->assertSame(20, $this->availability->availableGuestsFor($this->slotAt('19:00:00'), $this->pack));
        // El selector del operador (freeGuestSlots) aplica la misma garantía.
        $this->assertSame(0, $this->availability->freeGuestSlots($this->slotAt('20:00:00'), $this->pack));
    }

    public function test_party_count_cap_blocks_further_parties(): void
    {
        $this->setSetting(PackAvailability::SETTING_MAX_PER_SLOT, '1');
        $this->partyOrder('14:00:00', 10);

        // El tope de fiestas (1) ya está cubierto en esa franja → no cabe otra.
        $this->assertSame(0, $this->availability->availableGuestsFor($this->slotAt('14:00:00'), $this->pack));
    }

    public function test_guest_cap_limits_available_seats(): void
    {
        $this->setSetting(PackAvailability::SETTING_MAX_PER_SLOT, '0');   // sin tope de fiestas
        $this->setSetting(PackAvailability::SETTING_MAX_GUESTS_PER_SLOT, '20');
        $this->partyOrder('14:00:00', 15);

        // 20 - 15 = 5 niños libres en la franja más llena del tramo.
        $this->assertSame(5, $this->availability->availableGuestsFor($this->slotAt('14:00:00'), $this->pack));
    }

    public function test_prep_blocks_adjacent_slots_when_enabled(): void
    {
        $this->setSetting(PackAvailability::SETTING_MAX_PER_SLOT, '1');
        // Fiesta a las 14:00 → con prep ocupa [13:00, 16:30): franjas 13,14,15,16.
        $this->partyOrder('14:00:00', 10);

        // 16:00 queda bloqueada (la limpieza de la fiesta de las 14:00 toca esa franja).
        $this->assertSame(0, $this->availability->availableGuestsFor($this->slotAt('16:00:00'), $this->pack));
        // 18:00 está libre (fuera del tramo de la fiesta de las 14:00).
        $this->assertGreaterThan(0, $this->availability->availableGuestsFor($this->slotAt('18:00:00'), $this->pack));
    }

    public function test_prep_does_not_block_when_disabled(): void
    {
        $this->setSetting(PackAvailability::SETTING_MAX_PER_SLOT, '1');
        $this->setSetting(PackAvailability::SETTING_PREP_BLOCKS_CUPO, '0'); // toggle OFF
        // Sin prep, la fiesta de las 14:00 ocupa solo [14:00, 16:00): franjas 14,15.
        $this->partyOrder('14:00:00', 10);

        // 16:00 ya NO se bloquea (la preparación no cuenta para el cupo).
        $this->assertGreaterThan(0, $this->availability->availableGuestsFor($this->slotAt('16:00:00'), $this->pack));
    }

    /** Crea una zona de entradas (jump) con una franja y un tipo de entrada; devuelve la franja. */
    private function makeEntrySlot(int $online = 10): Slot
    {
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $entrySlot = Slot::create([
            'zone_id' => $jump->id, 'date' => $this->date,
            'start_time' => '14:00:00', 'end_time' => '15:00:00',
            'capacity' => $online, 'online_capacity' => $online,
        ]);
        $entryType = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $jump->id, 'type' => TicketType::TYPE_ENTRY,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $entryType->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);
        $entrySlot->setRelation('entryType', $entryType);

        return $entrySlot;
    }

    public function test_entry_capacity_does_not_reduce_pack_cupo(): void
    {
        // Franja de entradas COMPLETA (10/10) a la misma hora.
        $entrySlot = $this->makeEntrySlot(10);
        $full = Order::create(['user_id' => User::factory()->create()->id, 'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => Order::STATUS_PAID]);
        $full->items()->create(['ticket_type_id' => $entrySlot->entryType->id, 'slot_id' => $entrySlot->id, 'quantity' => 10, 'unit_price' => 1000, 'seats' => 10]);

        // El pool de cumpleaños es independiente: su cupo sigue intacto pese a la entrada llena.
        $this->assertSame(20, $this->availability->availableGuestsFor($this->slotAt('14:00:00'), $this->pack));
    }

    public function test_pack_cupo_does_not_reduce_entry_capacity(): void
    {
        $entrySlot = $this->makeEntrySlot(10);

        // Una fiesta de 18 niños a las 14:00 NO debe restar plazas de la entrada (pool propio).
        $this->partyOrder('14:00:00', 18);

        $this->assertSame(10, app(SlotAvailability::class)->availableFor($entrySlot, 60));
    }

    public function test_expired_pending_party_is_ignored_but_live_one_counts(): void
    {
        $this->setSetting(PackAvailability::SETTING_MAX_PER_SLOT, '0');
        $this->setSetting(PackAvailability::SETTING_MAX_GUESTS_PER_SLOT, '20');

        // Pendiente CADUCADA → no retiene cupo.
        $this->partyOrder('14:00:00', 15, Order::STATUS_PENDING, Carbon::now()->subMinute());
        $this->assertSame(20, $this->availability->availableGuestsFor($this->slotAt('14:00:00'), $this->pack));

        // Pendiente VIVA → sí retiene (20 - 15 = 5).
        $this->partyOrder('14:00:00', 15, Order::STATUS_PENDING, Carbon::now()->addHour());
        $this->assertSame(5, $this->availability->availableGuestsFor($this->slotAt('14:00:00'), $this->pack));
    }

    public function test_soft_cancelled_pack_items_release_cupo_and_guests(): void
    {
        // Sub-fase 7.2e.2 (decisión #159): packs soft-cancelados liberan tanto
        // el cupo de FIESTAS por franja (max_per_slot) como el cupo de NIÑOS
        // (max_guests_per_slot). Antes del fix, un pack cancelado seguía
        // consumiendo ambos cupos hasta que el operador re-creaba la fila.
        $this->setSetting(PackAvailability::SETTING_MAX_PER_SLOT, '1');
        $this->setSetting(PackAvailability::SETTING_MAX_GUESTS_PER_SLOT, '60');

        // Pack ACTIVO en la franja → consume 1 fiesta (de 1) → bloquea la franja.
        $this->partyOrder('14:00:00', 15);
        $this->assertSame(0, $this->availability->availableGuestsFor($this->slotAt('14:00:00'), $this->pack));

        // Cancelamos el último item creado → debe liberar la franja.
        $cancelledItem = OrderItem::query()->latest('id')->first();
        $cancelledItem->update(['cancelled_at' => now(), 'cancelled_by' => $this->user->id]);

        // Tras el fix: cupo liberado → max(pack) = 20 niños.
        $this->assertSame(20, $this->availability->availableGuestsFor($this->slotAt('14:00:00'), $this->pack));
    }

    public function test_order_creator_creates_a_pack_order_priced_per_child(): void
    {
        $order = $this->creator->createPendingOrder($this->user, [$this->line('14:00:00', 12)]);

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(18000, $order->total); // 1500 €/niño × 12 niños
        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertSame(12, $item->quantity);
        $this->assertSame(12, $item->seats);     // 1 niño = 1 plaza de cupo
        $this->assertSame(1500, $item->unit_price);
    }

    public function test_order_creator_rejects_guests_below_min(): void
    {
        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line('14:00:00', 5)]); // min_qty = 10
    }

    public function test_order_creator_rejects_guests_above_max(): void
    {
        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line('14:00:00', 25)]); // max_qty = 20
    }

    public function test_order_creator_rejects_when_slot_cupo_is_full(): void
    {
        $this->setSetting(PackAvailability::SETTING_MAX_PER_SLOT, '1');
        $this->partyOrder('14:00:00', 10); // ocupa el único hueco de fiesta de esa franja

        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line('14:00:00', 12)]);
    }

    public function test_order_creator_counts_other_pack_lines_in_the_cart(): void
    {
        $this->setSetting(PackAvailability::SETTING_MAX_PER_SLOT, '1');
        $this->setSetting(PackAvailability::SETTING_PREP_BLOCKS_CUPO, '0');

        // Dos fiestas en la misma franja con cupo de 1 → la cesta no cabe entera.
        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line('14:00:00', 10), $this->line('14:00:00', 12)]);
    }

    public function test_order_creator_does_not_persist_anything_when_pack_validation_fails(): void
    {
        $this->setSetting(PackAvailability::SETTING_MAX_PER_SLOT, '1');
        $this->partyOrder('14:00:00', 10);

        try {
            $this->creator->createPendingOrder($this->user, [$this->line('14:00:00', 12)]);
        } catch (ReservationException) {
            // esperado
        }

        $this->assertSame(0, Order::where('user_id', $this->user->id)->count());
    }

    public function test_order_creator_stores_sanitized_event_data_on_the_pack_item(): void
    {
        $this->pack->update(['event_fields' => [
            ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado/a']],
            ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'label' => ['es' => 'Notas']],
        ]]);

        $order = $this->creator->createPendingOrder($this->user, [[
            'ticket_type_id' => $this->pack->id, 'date' => $this->date, 'time' => '14:00:00', 'qty' => 12,
            'event_data' => ['celebrant' => '  Lucía  ', 'notes' => '', 'junk' => 'x'],
        ]]);

        // Recortado, vacíos descartados y claves fuera del esquema (junk) eliminadas (regla 12).
        $this->assertSame(['celebrant' => 'Lucía'], $order->items->first()->event_data);
    }

    public function test_party_window_crossing_midnight_is_clamped_to_end_of_day(): void
    {
        // L3 (auditoría Fase 1): una fiesta cuya ventana cruza medianoche NO debe invertirse (fin
        // lexicográficamente < inicio). Sin clamp, spannedSlots queda vacío (pack invendible,
        // fail-closed) y occupancyMaps deja de contar la fiesta en sus franjas (desaparece del cupo →
        // sobreventa de max_per_slot, fail-open). Se acota a [00:00:00, 24:00:00). Franjas tardías
        // 22:00 y 23:00; pack 120' + 60' montaje + 30' limpieza desde 22:00 → ventana [21:00, 00:30)
        // que cruza 00:00 → clampada a [21:00, 24:00).
        foreach (['22:00:00', '23:00:00'] as $t) {
            Slot::create(['zone_id' => $this->zone->id, 'date' => $this->date, 'start_time' => $t,
                'end_time' => '23:59:59', 'capacity' => 200, 'online_capacity' => 200]);
        }

        // FAIL-CLOSED arreglado: la fiesta de las 22:00 SÍ abarca franjas (no queda invendible).
        $spannedIds = $this->availability->spannedSlots($this->slotAt('22:00:00'), $this->pack)->pluck('id')->all();
        $this->assertContains($this->slotAt('22:00:00')->id, $spannedIds);
        $this->assertContains($this->slotAt('23:00:00')->id, $spannedIds);

        // FAIL-OPEN arreglado: una fiesta almacenada a las 22:00 SIGUE contando en el cupo de ambas
        // franjas (sin clamp, su huella desaparecería y otra fiesta sobre-reservaría max_per_slot).
        $this->partyOrder('22:00:00', 12);
        [$parties, $guests] = $this->availability->occupancyMaps($this->zone->id, $this->date, [], null, $this->zone);
        $this->assertSame(1, $parties['22:00:00'] ?? 0);
        $this->assertSame(1, $parties['23:00:00'] ?? 0);
        $this->assertSame(12, $guests['23:00:00'] ?? 0);
    }

    public function test_order_creator_rejects_when_a_required_event_field_is_missing(): void
    {
        $this->pack->update(['event_fields' => [
            ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado/a']],
        ]]);

        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line('14:00:00', 12)]); // sin event_data
    }
}
