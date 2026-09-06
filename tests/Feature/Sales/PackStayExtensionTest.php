<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\ResolvedAddon;
use App\Domain\Booking\Contracts\ResolvedAddons;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AddonOfferReader;
use App\Domain\Booking\Services\CartOccupants;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **LA HORA EXTRA DE UN PACK** (`specs/hora-extra.md` §10; `DECISIONES #421`/`#422`/`#423`).
 *
 * Lo que se vende es que **la fiesta sigue en su sala** (`[DECIDIDO owner, 2026-09-06]`), así que el
 * dominio no añade un ocupante nuevo: **alarga la ventana de esa misma fiesta**. Una fiesta, sus
 * invitados, más rato.
 *
 * ▶ **El hueco que estos casos cierran está MEDIDO** (§10.1): antes de esto, una fiesta de 20 niños
 * de 15:00 a 17:00 con una hora extra dejaba el cupo de sala de las 17:00 en `fiestas=0 ninos=0` y
 * **aceptaba otra fiesta encima**. Y eran DOS defectos: `PackAvailability` filtra por `type = pack`
 * (la hija es un `addon` y no contaba) y la hija nacía con `seats = 1` — «una persona» donde había
 * veinte—.
 */
class PackStayExtensionTest extends TestCase
{
    use RefreshDatabase;

    private PackAvailability $packs;

    private SlotAvailability $entries;

    private OrderCreator $creator;

    private User $user;

    private Zone $zone;

    private TicketType $pack;

    private TicketType $extraHour;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packs = app(PackAvailability::class);
        $this->entries = app(SlotAvailability::class);
        $this->creator = app(OrderCreator::class);
        $this->user = User::factory()->create();
        $this->date = Carbon::today()->addDays(3)->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        // Los topes van por ZONA para no depender del memo de `Setting`.
        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false,
            'max_per_slot' => 1, 'max_guests_per_slot' => 60, 'prep_blocks_cupo' => false,
        ]);

        // Rejilla 15:00 → 20:00 (franjas de 60 min, la última 20:00-21:00).
        foreach (range(15, 20) as $hour) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 200, 'online_capacity' => 200,
            ]);
        }

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'zone_id' => $this->zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'prep_before_min' => 0, 'prep_after_min' => 0,
            'min_qty' => 8, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->pack->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1500,
        ]);

        $this->extraHour = TicketType::create([
            'name' => ['es' => 'Hora extra de sala'], 'type' => TicketType::TYPE_ADDON,
            'duration_min' => 60, 'extends_parent_stay' => true,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $this->extraHour->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 5000,
        ]);
        $this->pack->addons()->attach($this->extraHour->id, [
            'position' => 1, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
            'max_qty' => 2,
        ]);
    }

    // ─── Lo que la venta escribe ─────────────────────────────────────────────────────

    public function test_buying_an_extra_hour_records_the_minutes_on_the_parent_and_no_seats_on_the_child(): void
    {
        $order = $this->buy('15:00:00', 20, 1);

        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $child = $order->items()->whereNotNull('parent_item_id')->firstOrFail();

        $this->assertSame(60, (int) $parent->extra_minutes);
        $this->assertSame(180, $parent->fresh('ticketType')->occupiedMinutes(), 'la fiesta ocupa 2 h + 1 h');

        // ⚠️ Lo que hace correcta la cuenta: la hija NO se lleva plazas ni franja. Las plazas de la
        // fiesta siguen siendo del padre —que ahora dura más—, y dárselas a la hija sería contar
        // «1 persona» donde hay veinte (el defecto (b) de §10.1).
        $this->assertSame(0, (int) $child->seats);
        $this->assertNull($child->slot_id);
        $this->assertSame(1, (int) $child->quantity, 'la cantidad son BLOQUES de tiempo, no personas');
    }

    // ─── El hueco de §10.1, cerrado ──────────────────────────────────────────────────

    public function test_the_extra_hour_keeps_the_room_busy_for_the_next_slot(): void
    {
        $this->buy('15:00:00', 20, 1); // 15:00 → 18:00 con la hora extra

        // Sin la extensión, a las 17:00 la sala estaría libre. Con ella, sigue ocupada.
        $this->assertSame(0, $this->packs->availableGuestsFor($this->slot('17:00:00'), $this->pack));
        // Y a las 18:00, cuando de verdad termina, vuelve a estar libre.
        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot('18:00:00'), $this->pack));
    }

    public function test_control_without_the_extra_hour_the_next_slot_is_free(): void
    {
        // CONTROL del anterior: la misma fiesta sin hora extra libera la sala a las 17:00, así que
        // el 0 de arriba lo produce la extensión y no otra cosa.
        $this->buy('15:00:00', 20, 0);

        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot('17:00:00'), $this->pack));
    }

    public function test_the_extra_hour_also_holds_the_zone_seats(): void
    {
        $this->buy('15:00:00', 20, 1);

        // El otro pool: el aforo de ASIENTOS de la zona también ve a los veinte hasta las 18:00.
        $map = $this->entries->occupancyMap($this->zone->id, $this->date);
        $this->assertSame(20, $map['17:00:00'] ?? 0, 'los veinte siguen dentro durante la hora extra');
        $this->assertArrayNotHasKey('18:00:00', $map, 'y a las 18:00 ya han salido');
    }

    // ─── El checkout la rechaza cuando no cabe ───────────────────────────────────────

    public function test_the_checkout_rejects_an_extra_hour_that_collides_with_another_party(): void
    {
        $this->buy('17:00:00', 20, 0); // ocupa 17:00 → 19:00

        // ⚠️ La fiesta de las 15:00 **cabe** (15:00→17:00: el tramo es semiabierto y no llega a
        // tocar a la de las 17:00), y ésa es la mitad que hace útil el caso: lo que no cabe es la
        // MISMA fiesta alargada, que llegaría a las 18:00 y compartiría sala con la otra.
        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot('15:00:00'), $this->pack));
        $this->assertSame(0, $this->packs->availableGuestsFor($this->slot('15:00:00'), $this->pack, [], null, 60));

        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line('15:00:00', 20, 1)]);
    }

    public function test_the_checkout_rejects_an_extra_hour_that_runs_past_the_grid(): void
    {
        // La rejilla acaba a las 21:00. Una fiesta a las 19:00 cabe (19:00→21:00); con una hora
        // extra terminaría a las 22:00, fuera de horario: no se vende tiempo que el parque no tiene.
        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot('19:00:00'), $this->pack));

        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line('19:00:00', 20, 1)]);
    }

    public function test_two_blocks_of_extra_hour_add_up(): void
    {
        $this->buy('15:00:00', 20, 2); // 15:00 → 19:00

        $this->assertSame(0, $this->packs->availableGuestsFor($this->slot('18:00:00'), $this->pack));
        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot('19:00:00'), $this->pack));
    }

    // ─── La cesta provisional cuenta lo mismo que el cobro ───────────────────────────

    public function test_a_provisional_cart_line_holds_the_extended_window_too(): void
    {
        // Sin nada vendido, la cesta que ya lleva una fiesta con hora extra a las 15:00 tiene que
        // dejar las 17:00 sin sitio — o la oferta enseñaría una hora que el checkout rechaza.
        $cart = [$this->line('15:00:00', 20, 1)];
        $occupants = CartOccupants::forCart($cart, $this->zone->id, $this->date)['packs'];

        $this->assertNotSame([], $occupants, 'la cesta tiene que aportar la fiesta provisional');
        $this->assertSame(0, $this->packs->availableGuestsFor($this->slot('17:00:00'), $this->pack, $occupants));
        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot('18:00:00'), $this->pack, $occupants));
    }

    // ─── La oferta no enseña lo que el cobro va a rechazar (`#423` · A3/A4) ──────────

    public function test_the_extra_hour_is_not_offered_when_it_does_not_fit(): void
    {
        $this->buy('17:00:00', 20, 0); // la sala queda tomada de 17:00 a 19:00

        $offer = app(AddonOfferReader::class)
            ->resolve($this->pack->id, 20, [], [], $this->date, '15:00:00');

        // La fiesta de las 15:00 sí se puede comprar; su hora extra, no — así que no se ofrece.
        // ⚠️ Sin este filtro el extensor pasaría entero: el de los ocupantes está escrito sobre
        // `occupiesAfterParent()`, donde un extensor devuelve `false`.
        $this->assertNotNull($offer);
        $this->assertNull($this->extraHourRow($offer), 'la hora extra que no cabe no puede ofrecerse');
    }

    public function test_control_the_extra_hour_is_offered_when_it_fits(): void
    {
        // CONTROL del anterior sobre el mismo día y la misma hora: sin la fiesta que estorba, el
        // extensor SÍ se ofrece. Sin este caso, el de arriba pasaría con la oferta rota del todo.
        $offer = app(AddonOfferReader::class)
            ->resolve($this->pack->id, 20, [], [], $this->date, '15:00:00');

        $row = $this->extraHourRow($offer);
        $this->assertNotNull($row, 'la hora extra tiene que ofrecerse cuando cabe');
        $this->assertSame(2, $row->maxQuantity, 'con la sala libre caben los dos bloques del enganche');
    }

    public function test_the_offer_caps_the_extra_hour_to_the_blocks_that_fit(): void
    {
        $this->buy('18:00:00', 20, 0); // ocupa 18:00 → 20:00

        $offer = app(AddonOfferReader::class)
            ->resolve($this->pack->id, 20, [], [], $this->date, '15:00:00');

        // Una hora extra lleva la fiesta a las 18:00 (aún libre); dos, a las 19:00 (ocupada). El
        // enganche permite 2 y la sala solo 1: manda la sala.
        $row = $this->extraHourRow($offer);
        $this->assertNotNull($row);
        $this->assertSame(1, $row->maxQuantity);
    }

    public function test_a_forbidden_hook_smuggled_past_eloquent_is_not_offered_either(): void
    {
        // EL CINTURÓN de §10.3.1, y es el motivo de que la rama simétrica exista en el modelo de
        // vista: los guards del pivote y del modelo son eventos de Eloquent, y `Query\Builder`
        // no los dispara. Un extensor colgado de una ENTRADA por esa puerta **no se ofrece**.
        //
        // ⚠️ Sin este caso la rama de `viewModel()` es código que no vigila nada demostrable: la
        // mutación que la borra pasaba en verde, porque por las vías legítimas ese enganche no
        // puede existir.
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada suelta'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'position' => 9,
        ]);
        $entry->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000,
        ]);
        DB::table('product_addons')->insert([
            'product_id' => $entry->id, 'addon_id' => $this->extraHour->id, 'position' => 1,
            'stage' => ProductAddon::STAGE_BOOKING, 'quantity_mode' => ProductAddon::MODE_FIXED,
            'allow_extra' => 1, 'included_quantity' => 1, 'is_included' => 0, 'is_mandatory' => 0,
            'max_qty' => 2,
        ]);

        $reader = app(AddonOfferReader::class);

        $conHora = $reader->resolve($entry->id, 2, [], [], $this->date, '15:00:00');
        $this->assertNotNull($conHora);
        $this->assertNull($this->extraHourRow($conHora), 'un extensor colgado de una entrada no se ofrece');

        // ⚠️⚠️ **Y SIN fecha/hora, que es donde la otra defensa NO llega.** El filtro de aterrizaje
        // solo corre cuando hay hora que consultar (el contrato lo dice: sin `date`/`time` los
        // ocupantes «viajan sin decorar»), así que en ese modo la única que queda en pie es la rama
        // simétrica del modelo de vista. Sin este medio caso, borrarla no ponía nada en rojo.
        $sinHora = $reader->resolve($entry->id, 2);
        $this->assertNotNull($sinHora);
        $this->assertNull($this->extraHourRow($sinHora), 'tampoco sin fecha ni hora');
    }

    // ─── La puerta del editor hasta la T3 (`#423` · A5) ──────────────────────────────

    public function test_the_panel_editor_refuses_to_touch_a_stay_extension_for_now(): void
    {
        // Añadir una hora extra a una fiesta ya vendida alarga su ventana, y la familia que aterriza
        // bajo el lock del editor se compone filtrando por `occupiesAfterParent()` — donde un
        // extensor no entra. Hasta que la T3 revalide el cupo alargado, la puerta se cierra
        // EXPLÍCITAMENTE: dejarlo pasar no falla, sobrevende.
        $order = $this->buy('15:00:00', 20, 1);
        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail()->load('children.ticketType');
        $child = $parent->children->first();

        $editor = app(OrderItemEditor::class);

        $this->assertSame('addon_stay_extension_unsupported', $editor->validateAddonEdits(
            $parent, $this->pack, [['child_id' => (int) $child->id, 'quantity' => 0]], [],
        ));
        $this->assertSame('addon_stay_extension_unsupported', $editor->validateAddonEdits(
            $parent, $this->pack, [], [['ticket_type_id' => $this->extraHour->id, 'quantity' => 1]],
        ));
    }

    // ─── helpers ─────────────────────────────────────────────────────────────────────

    /**
     * La fila de la hora extra dentro de la oferta, o `null` si no se ofrece.
     *
     * ⚠️ **Se localiza por `productId`, no por `id`**: el DTO publica `productId`/`maxQuantity`, y
     * la primera versión de estos casos buscaba `id` — así que el «no se ofrece» pasaba **en vacío**
     * y habría pasado igual con la oferta rota del todo. Lo delató su CONTROL.
     */
    private function extraHourRow(ResolvedAddons $offer): ?ResolvedAddon
    {
        foreach ($offer->singles as $row) {
            if ((int) $row->productId === (int) $this->extraHour->id) {
                return $row;
            }
        }

        return null;
    }

    private function slot(string $time): Slot
    {
        return Slot::with('zone')
            ->where('zone_id', $this->zone->id)->where('date', $this->date)->where('start_time', $time)
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function line(string $time, int $guests, int $extraBlocks): array
    {
        $line = [
            'ticket_type_id' => $this->pack->id, 'date' => $this->date, 'time' => $time,
            'qty' => $guests, 'event_data' => [],
        ];
        if ($extraBlocks > 0) {
            $line['addons'] = [['ticket_type_id' => $this->extraHour->id, 'qty' => $extraBlocks]];
        }

        return $line;
    }

    private function buy(string $time, int $guests, int $extraBlocks): Order
    {
        return $this->creator->createPendingOrder(
            User::factory()->create(),
            [$this->line($time, $guests, $extraBlocks)],
        );
    }
}
