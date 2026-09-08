<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\ItemActionOutcome;
use App\Domain\Booking\Contracts\ResolvedAddon;
use App\Domain\Booking\Contracts\ResolvedAddons;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AddonOfferReader;
use App\Domain\Booking\Services\CartOccupants;
use App\Domain\Booking\Services\ItemRescheduleOffer;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
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

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
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

    // ─── El editor del panel: la ventana se revalida y el hecho se reescribe (T3) ────

    public function test_the_panel_can_remove_an_extra_hour_and_the_room_frees_up(): void
    {
        $order = $this->buy('15:00:00', 20, 1);
        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $child = $parent->children()->firstOrFail();
        $this->assertSame(0, $this->packs->availableGuestsFor($this->slot('17:00:00'), $this->pack));

        $outcome = $this->edit($order, $parent, ['edits' => [['child_id' => (int) $child->id, 'quantity' => 0]], 'adds' => []]);

        $this->assertFalse($outcome->isBlocked(), 'quitar una hora extra siempre cabe: libera sala; el editor dijo: '.($outcome->reason ?? '—'));
        $this->assertSame(0, (int) $parent->fresh()->extra_minutes);
        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot('17:00:00'), $this->pack));
    }

    public function test_the_panel_can_add_an_extra_hour_and_the_room_gets_busy(): void
    {
        $order = $this->buy('15:00:00', 20, 0);
        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot('17:00:00'), $this->pack));

        $outcome = $this->edit($order, $parent, ['edits' => [], 'adds' => [['ticket_type_id' => (int) $this->extraHour->id, 'quantity' => 1]]]);

        $this->assertFalse($outcome->isBlocked(), 'el editor bloqueó: '.($outcome->reason ?? '—'));
        $this->assertSame(60, (int) $parent->fresh()->extra_minutes);
        $this->assertSame(0, $this->packs->availableGuestsFor($this->slot('17:00:00'), $this->pack));
    }

    public function test_the_panel_cannot_add_an_extra_hour_that_does_not_fit(): void
    {
        // ⚠️⚠️ EL CASO QUE JUSTIFICA LA TANDA (`#423` · A5): sin revalidar la ventana alargada bajo
        // el lock, esto **no falla** — alarga la fiesta encima de otra y sobrevende la sala desde el
        // mostrador, que es donde nadie lo ve.
        $order = $this->buy('15:00:00', 20, 0);
        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $this->buy('17:00:00', 20, 0); // la sala queda tomada justo después

        $outcome = $this->edit($order, $parent, ['edits' => [], 'adds' => [['ticket_type_id' => (int) $this->extraHour->id, 'quantity' => 1]]]);

        $this->assertTrue($outcome->isBlocked(), 'la hora extra no cabe: el editor tiene que bloquearla');
        $this->assertSame(0, (int) $parent->fresh()->extra_minutes, 'y no puede quedar escrita a medias');
        $this->assertSame(0, $parent->fresh()->children()->whereNull('cancelled_at')->count());
    }

    public function test_moving_the_party_to_a_day_that_does_not_sell_the_extra_hour_shortens_it(): void
    {
        // ⚠️⚠️ **EL ORDEN es la propiedad** (la lección de `#417` §9.8·H1 aplicada a la extensión):
        // si el día nuevo no vende la hora extra, esos minutos **no pueden contar contra el cupo**
        // que se pide — la reserva se estaría bloqueando por un aforo que nadie iba a consumir.
        // Por eso el plan de fechas se calcula ANTES de validar, y no después.
        $manana = Carbon::parse($this->date)->addDay();
        foreach (range(15, 20) as $hour) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $manana->toDateString(),
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 200, 'online_capacity' => 200,
            ]);
        }
        // Ese día manda otra tarifa, y el pack la tiene… pero la hora extra no: no se vende.
        $special = RateType::create([
            'key' => 'special', 'label' => ['es' => 'Especial'],
            'weekdays' => [$manana->dayOfWeek], 'priority' => 10, 'is_active' => true,
        ]);
        $this->pack->prices()->create(['rate_type_id' => $special->id, 'amount_cents' => 1500]);

        $order = $this->buy('15:00:00', 20, 1);
        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $this->assertSame(60, (int) $parent->extra_minutes);

        // Y la sala del día nuevo está ocupada a partir de las 17:00: con la hora extra la fiesta
        // NO cabría… pero es que la hora extra no viaja a ese día.
        $this->buyOn($manana->toDateString(), '17:00:00', 20);

        $outcome = $this->edit($order, $parent, ['edits' => [], 'adds' => []], $manana->toDateString());

        $this->assertFalse($outcome->isBlocked(), 'la fiesta cabe sin su hora extra, que ese día no se vende; el editor dijo: '.($outcome->reason ?? '—'));
        $this->assertSame(0, (int) $parent->fresh()->extra_minutes);
        $this->assertSame(0, $parent->fresh()->children()->whereNull('cancelled_at')->count());
    }

    public function test_the_reschedule_offer_does_not_offer_hours_where_the_extended_party_would_not_fit(): void
    {
        // ⚠️⚠️ **La hora candidata tiene que caer FUERA del tramo propio actual**, o el caso no mide
        // nada: la oferta cuenta la huella propia a propósito (`#173`), así que cualquier hora dentro
        // del tramo de la fiesta sale excluida con o sin extensión. La primera versión de este caso
        // usaba las 16:00 y **pasaba con el cambio revertido**.
        foreach ([21, 22] as $hour) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 200, 'online_capacity' => 200,
            ]);
        }

        $order = $this->buy('15:00:00', 20, 1);   // 15:00 → 18:00 con su hora extra
        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $this->buy('21:00:00', 20, 0);            // la sala se ocupa de 21:00 a 23:00

        $horas = array_column(app(ItemRescheduleOffer::class)->times($parent->fresh(), $this->date), 'time');

        // Mover la fiesta a las 19:00 la llevaría hasta las 22:00 y pisaría a la otra: no se ofrece.
        // Sin contar la extensión esa hora parecería libre (19:00 → 21:00 no toca nada), y el editor
        // la rechazaría bajo el lock — el primo de `AFORO-02` por la puerta de la re-programación.
        $this->assertNotContains('19:00:00', $horas);
        // CONTROL: a las 18:00 SÍ cabe alargada (18:00 → 21:00, justo antes de la otra), así que la
        // ausencia de arriba no es que la oferta se haya vaciado.
        $this->assertContains('18:00:00', $horas);
        $this->assertContains('15:00:00', $horas, 'la hora actual siempre se ofrece');
    }

    // ─── Lo que se LEE: la ventana de la reserva (T4) ────────────────────────────────

    public function test_the_displayed_window_includes_the_extra_hour(): void
    {
        // ⚠️ Esta ventana es la que el operador lee en la hoja de sala y el cliente en su reserva, y
        // sale de UN sitio para todas las superficies. Decir «15:00–17:00» de una fiesta que acaba a
        // las 18:00 no es un detalle de estilo: es la sala dada por libre una hora antes de tiempo.
        $conExtra = $this->buy('15:00:00', 20, 1)->items()->whereNull('parent_item_id')->firstOrFail();
        $sinExtra = $this->buy('18:00:00', 20, 0)->items()->whereNull('parent_item_id')->firstOrFail();

        $this->assertSame('15:00–18:00', $conExtra->fresh('ticketType')->displayTimeWindow());
        $this->assertSame('18:00–20:00', $sinExtra->fresh('ticketType')->displayTimeWindow());
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

    /**
     * Conduce el editor del panel sobre la reserva, con el operador con permisos.
     *
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, array{ticket_type_id:int, quantity:int}>}  $addonEdits
     */
    private function edit(Order $order, OrderItem $item, array $addonEdits, ?string $date = null): ItemActionOutcome
    {
        // El editor solo opera sobre pedidos FIRMES: los de `createPendingOrder` nacen `pending`
        // (con su retención de aforo), que es correcto para medir cupo pero no para editar.
        $order->forceFill(['status' => Order::STATUS_PAID, 'expires_at' => null])->save();

        $operator = User::factory()->create();
        $operator->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $operator->roles->first()->permissions()->sync(
            Permission::whereIn('name', ['orders.view', 'orders.edit_item'])->pluck('id'),
        );

        return app(OrderItemEditor::class)->edit(
            $order->fresh(),
            $item->fresh(),
            $date ?? $this->date,
            (string) $item->slot->start_time,
            $date !== null && $date !== $this->date,
            (int) $item->ticket_type_id,
            (int) $item->quantity,
            null,
            $addonEdits,
            (string) $order->fresh()->updated_at?->timestamp,
            $operator,
        );
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

    private function buyOn(string $date, string $time, int $guests): Order
    {
        return $this->creator->createPendingOrder(User::factory()->create(), [[
            'ticket_type_id' => $this->pack->id, 'date' => $date, 'time' => $time,
            'qty' => $guests, 'event_data' => [],
        ]]);
    }
}
