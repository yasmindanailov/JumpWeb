<?php

namespace Tests\Feature\Sales;

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
use App\Domain\Booking\Services\AddonResolver;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * **LA HORA EXTRA DE UN PACK COBRADA POR INVITADO** (`specs/hora-extra.md` §11, `DECISIONES #443`).
 *
 * `[DECIDIDO owner, 2026-09-07]`, la opción A de §10.2: *«la hora extra para todos los invitados,
 * pero se cobra por invitado»*. Una hora es una hora —la compren 8 invitados o 20—, y lo que escala
 * con los invitados es el PRECIO.
 *
 * ❗❗❗ **La regla que lo hace posible vive en UN sitio**: `AddonOccupancy::blocksFor()`. Hasta el
 * 2026-09-07 los minutos salían de la cantidad (`cantidad × duration_min`), así que un extensor
 * por-invitado habría alargado la sala **900 minutos** en una fiesta de 15 — por eso estaba
 * prohibido, y la prohibición era correcta. Lo que cambia no es el guard: es de dónde salen los
 * minutos.
 *
 * ⚠️ **Cada caso de dinero lleva su CONTROL de aforo al lado y viceversa**: la trampa de esta feature
 * es exactamente que las dos cosas se muevan juntas cuando solo una debe hacerlo.
 */
class StayExtensionPerGuestTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST_PRICE_CENTS = 400;   // 4,00 € por invitado (el de producción para KIDS)

    private const PACK_PRICE_CENTS = 1500;   // 15,00 € por invitado

    private PackAvailability $packs;

    private OrderCreator $creator;

    private Zone $zone;

    private TicketType $pack;

    private TicketType $extraHour;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packs = app(PackAvailability::class);
        $this->creator = app(OrderCreator::class);
        $this->date = Carbon::today()->addDays(3)->toDateString();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false,
            'max_per_slot' => 1, 'max_guests_per_slot' => 60, 'prep_blocks_cupo' => false,
        ]);

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
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => self::PACK_PRICE_CENTS,
        ]);

        $this->extraHour = TicketType::create([
            'name' => ['es' => 'Hora extra de sala'], 'type' => TicketType::TYPE_ADDON,
            'duration_min' => 60, 'extends_parent_stay' => true,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $this->extraHour->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => self::GUEST_PRICE_CENTS,
        ]);
    }

    // ─── Lo que la venta escribe ─────────────────────────────────────────────────────

    public function test_a_per_guest_extra_hour_charges_every_guest(): void
    {
        $this->hookPerGuest();
        $order = $this->buy('15:00:00', 15);

        $child = $order->items()->whereNotNull('parent_item_id')->firstOrFail();

        // La cantidad de la hija son los INVITADOS y su precio unitario es el de UN invitado: los dos
        // hechos honestos, y el cobro sale del núcleo de siempre sin tocarlo
        // (`chargedSubtotalCents = (cantidad − gratis) × unit_price`).
        $this->assertSame(15, (int) $child->quantity);
        $this->assertSame(self::GUEST_PRICE_CENTS, (int) $child->unit_price);
        $this->assertSame(0, (int) $child->free_quantity);
        $this->assertSame(6000, $child->chargedSubtotalCents(), '15 invitados × 4,00 € = 60,00 €');

        // Y el pedido cobra la fiesta más la hora extra, sin inventarse nada por el camino.
        $this->assertSame(15 * self::PACK_PRICE_CENTS + 6000, (int) $order->total);
    }

    public function test_the_child_of_a_per_guest_extender_still_takes_no_seats_and_no_slot(): void
    {
        // ⚠️⚠️ El defecto (b) de §10.1 sigue cerrado, y **este caso es el que impide reabrirlo**: al
        // pasar la cantidad de «bloques» a «personas», dar plazas a la hija sería contar a los quince
        // DOS veces —una en el padre y otra en ella—. Las plazas siguen siendo del padre, alargado.
        $this->hookPerGuest();
        $order = $this->buy('15:00:00', 15);

        $child = $order->items()->whereNotNull('parent_item_id')->firstOrFail();

        $this->assertSame(0, (int) $child->seats);
        $this->assertNull($child->slot_id);
    }

    // ─── El aforo: una hora es una hora ──────────────────────────────────────────────

    public function test_the_room_is_extended_by_one_block_no_matter_how_many_guests(): void
    {
        $this->hookPerGuest();
        $order = $this->buy('15:00:00', 15);

        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();

        // ❗❗❗ **El número que decide esta feature.** Con la derivación vieja serían 900.
        $this->assertSame(60, (int) $parent->extra_minutes);
        $this->assertSame(180, $parent->fresh('ticketType')->occupiedMinutes());

        // Y se ve en el cupo: la sala sigue ocupada a las 17:00 y libre a las 18:00.
        $this->assertSame(0, $this->packs->availableGuestsFor($this->slot('17:00:00'), $this->pack));
        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot('18:00:00'), $this->pack));
    }

    public function test_control_a_fixed_extender_still_counts_its_quantity_as_blocks(): void
    {
        // CONTROL del anterior, y el que impide «arreglarlo» de más: sin `per_guest` la cantidad
        // SIGUE siendo bloques de tiempo (§10.3.1). Dos bloques → dos horas.
        $this->hookFixed(maxQty: 2);
        $order = $this->buy('15:00:00', 15, blocks: 2);

        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $this->assertSame(120, (int) $parent->extra_minutes);
        $this->assertSame(240, $parent->fresh('ticketType')->occupiedMinutes());
    }

    // ─── El re-precio cuando cambian los invitados ───────────────────────────────────

    public function test_raising_the_guest_count_reprices_the_extra_hour(): void
    {
        $this->hookPerGuest();
        $order = $this->buy('15:00:00', 15);
        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $this->edit($order, $parent, newQty: 18);

        $child = $order->fresh()->items()->whereNotNull('parent_item_id')->firstOrFail();
        $this->assertSame(18, (int) $child->quantity, 'la hora extra sigue al nº de invitados');
        $this->assertSame(self::GUEST_PRICE_CENTS, (int) $child->unit_price, 'el precio por invitado es HISTÓRICO (PAY-19)');
        $this->assertSame(7200, $child->chargedSubtotalCents());

        // Y el movimiento de dinero de esa diferencia queda ESCRITO, atado a la hija.
        $this->assertDatabaseHas('order_adjustments', [
            'order_item_id' => $child->id,
            'amount_cents' => 3 * self::GUEST_PRICE_CENTS,
            'reason' => 'addon_per_guest_rescale',
        ]);

        // ⚠️ Y la sala NO se alarga por tener tres invitados más: sigue siendo UNA hora.
        $this->assertSame(60, (int) $order->fresh()->items()->whereNull('parent_item_id')->firstOrFail()->extra_minutes);
    }

    public function test_lowering_the_guest_count_reprices_the_extra_hour_down(): void
    {
        $this->hookPerGuest();
        $order = $this->buy('15:00:00', 15);
        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $this->edit($order, $parent, newQty: 10);

        $child = $order->fresh()->items()->whereNotNull('parent_item_id')->firstOrFail();
        $this->assertSame(10, (int) $child->quantity);
        $this->assertSame(4000, $child->chargedSubtotalCents());
        $this->assertDatabaseHas('order_adjustments', [
            'order_item_id' => $child->id,
            'amount_cents' => -5 * self::GUEST_PRICE_CENTS,
            'reason' => 'addon_per_guest_rescale_reduction',
        ]);
    }

    // ─── El candado del MODO (§11.11 · A1) ───────────────────────────────────────────

    public function test_changing_the_mode_of_a_hook_with_editable_sold_lines_is_rejected(): void
    {
        // ❗❗❗ Sin este candado, pasar el enganche de `fixed` a `per_guest` haría que la primera
        // edición de cantidad de una fiesta YA VENDIDA convirtiera su hija de 1 bloque a N unidades:
        // `PAY-19` roto por la puerta de la configuración.
        $this->hookFixed();
        $this->buy('15:00:00', 15, blocks: 1);

        $pivot = $this->pivot();
        $this->assertTrue($pivot->hasEditableSoldLines());

        $this->expectException(InvalidArgumentException::class);
        $pivot->fill(['quantity_mode' => ProductAddon::MODE_PER_GUEST])->save();
    }

    public function test_control_the_mode_is_free_to_change_without_editable_sold_lines(): void
    {
        // CONTROL: el candado tiene que soltarse, o sería un cerrojo. Sin ventas, se cambia.
        $this->hookFixed();

        $pivot = $this->pivot();
        $this->assertFalse($pivot->hasEditableSoldLines());
        $pivot->fill(['quantity_mode' => ProductAddon::MODE_PER_GUEST])->save();

        $this->assertTrue($this->pivot()->isPerGuest());
    }

    public function test_a_cancelled_line_does_not_lock_the_mode(): void
    {
        // La otra mitad del control: lo que bloquea son las líneas VIVAS en reservas editables. Una
        // reserva cancelada ya no se re-escala, así que no puede re-preciarse.
        $this->hookFixed();
        $order = $this->buy('15:00:00', 15, blocks: 1);
        $order->items()->update(['cancelled_at' => now()]);

        $this->assertFalse($this->pivot()->hasEditableSoldLines());
    }

    public function test_a_finished_party_does_not_lock_the_mode(): void
    {
        // ❗❗ **La otra mitad del candado, y es la que le da su adjetivo.** Lo que bloquea no es
        // «tener ventas», es tener ventas que TODAVÍA SE PUEDEN EDITAR: el editor rechaza una reserva
        // ya celebrada (`item_finished`), así que una fiesta pasada no puede re-escalarse y no hay
        // nada que proteger. Sin este caso, el candado sería un cerrojo permanente y nadie lo notaría.
        $this->hookFixed();
        $this->buy('15:00:00', 15, blocks: 1);

        $this->assertTrue($this->pivot()->hasEditableSoldLines(), 'antes de la fiesta, bloqueado');

        // La fiesta empieza a las 15:00 y con su hora extra acaba a las 18:00: dos días después ya pasó.
        $this->travelTo(Carbon::parse($this->date)->addDays(2)->setTime(12, 0));

        $this->assertFalse($this->pivot()->hasEditableSoldLines(), 'pasada la fiesta, libre');
        $this->pivot()->fill(['quantity_mode' => ProductAddon::MODE_PER_GUEST])->save();
        $this->assertTrue($this->pivot()->isPerGuest());
    }

    // ─── Lo que ve el cliente ────────────────────────────────────────────────────────

    public function test_the_offer_says_for_how_many_guests_and_at_what_price_each(): void
    {
        // §11.5.4: sin esta rama la nota decía «4,00 €/invitado» sobre un cargo de 60,00 € — una
        // promesa que el pedido no respalda.
        $this->hookPerGuest();
        $row = $this->offerRow(selected: true, guests: 15);

        $this->assertStringContainsString('15', $row['note']);
        $this->assertStringContainsString('4,00', $row['note']);
        $this->assertSame(6000, $row['charged']);
        $this->assertSame(15, $row['qty']);
    }

    public function test_the_offer_of_an_unselected_per_guest_hour_shows_the_price_per_guest(): void
    {
        $this->hookPerGuest();
        $row = $this->offerRow(selected: false, guests: 15);

        $this->assertStringContainsString('4,00', $row['note']);
        $this->assertStringContainsString('invitado', $row['note']);
        $this->assertSame(0, $row['charged']);
    }

    public function test_a_per_guest_extra_hour_is_a_checkbox_and_not_a_counter(): void
    {
        // «Una hora más para la fiesta» es un sí/no: la cantidad no la elige nadie. Lo decide el
        // dominio y el cajón solo lo pinta.
        $this->hookPerGuest();
        $row = $this->offerRow(selected: false, guests: 15);

        $this->assertTrue($row['can_toggle']);
        $this->assertFalse($row['can_inc']);
        $this->assertFalse($row['can_dec']);
    }

    public function test_a_per_guest_hour_is_not_offered_when_the_room_is_busy_afterwards(): void
    {
        // A3 de §10.8, por la vía nueva: la oferta tiene que mirar si la fiesta cabe ALARGADA, y esa
        // pregunta la hace con BLOQUES (`maxBlocks` + `minutesForBlocks`). Con el modo por-invitado
        // el barrido es de un solo bloque; sin ese tope repetiría el mismo cálculo y ofrecería
        // bloques que nadie puede comprar (§11.11·A2).
        $this->hookPerGuest();
        $this->buy('17:00:00', 20, blocks: 0);  // otra fiesta ocupa 17:00 → 19:00

        $offer = app(AddonOfferReader::class)
            ->resolve($this->pack->id, 15, [], [], $this->date, '15:00:00');

        $this->assertNotNull($offer);
        $this->assertNull($this->offerDto($offer), 'la hora extra que no cabe no puede ofrecerse');
    }

    public function test_control_the_per_guest_hour_is_offered_when_it_fits(): void
    {
        // CONTROL del anterior: sin la fiesta que estorba, SÍ se ofrece — y con UN solo bloque, que
        // es lo que un por-invitado puede vender.
        $this->hookPerGuest();

        $offer = app(AddonOfferReader::class)
            ->resolve($this->pack->id, 15, [], [], $this->date, '15:00:00');

        $dto = $this->offerDto($offer);
        $this->assertNotNull($dto, 'la hora extra tiene que ofrecerse cuando cabe');
        $this->assertTrue($dto->perGuest);
        $this->assertTrue($dto->canToggle);
        $this->assertFalse($dto->canIncrease);
    }

    public function test_a_per_guest_hour_never_offers_more_than_one_block_even_with_a_maximum_set(): void
    {
        // ⚠️⚠️ **Este caso nació de una mutación que NO mordía**, y el motivo era el fixture: con
        // `max_qty = null` el techo viejo (`max(1, max_qty ?? 1)`) y el nuevo (`maxBlocks`) valen lo
        // MISMO, así que la mutación era DÉBIL y no un hueco. Con un máximo puesto —que es lo que hay
        // hoy en producción, `max_qty = 1`, y lo que quedaría si alguien sube el enganche a 3— los
        // dos se separan: sin `maxBlocks`, la oferta publicaría un tope de 3 para un control que solo
        // puede valer 1.
        $this->pack->addons()->attach($this->extraHour->id, [
            'position' => 1, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
            'max_qty' => 3,
        ]);
        $this->pack->unsetRelation('addons');

        $dto = $this->offerDto(app(AddonOfferReader::class)
            ->resolve($this->pack->id, 15, [], [], $this->date, '15:00:00'));

        $this->assertNotNull($dto);
        $this->assertSame(1, $dto->maxQuantity, 'un por-invitado vende UN bloque, diga lo que diga el máximo');
    }

    public function test_a_cancelled_reservation_does_not_lock_the_mode_even_if_its_child_survives(): void
    {
        // La otra mitad del filtro del candado. Cancelar una reserva CASCADEA a sus hijas
        // (`OrderItemCanceller`), así que este estado no se alcanza por la puerta normal — y por eso
        // el filtro del PADRE parecía redundante y su mutación no mordía. Se construye a mano: lo
        // que decide es la RESERVA, no la hija, porque una reserva cancelada ya no se edita.
        $this->hookFixed();
        $order = $this->buy('15:00:00', 15, blocks: 1);
        $order->items()->whereNull('parent_item_id')->update(['cancelled_at' => now()]);

        $this->assertFalse($this->pivot()->hasEditableSoldLines());
    }

    // ─── Los cinturones, con la fila torcida por la puerta de atrás ──────────────────

    public function test_the_belt_rejects_an_included_extender_written_behind_eloquent(): void
    {
        // ⚠️⚠️ **Este caso nació de otra mutación que no mordía**, y ahí sí había hueco: el cinturón
        // del resolutor existe EXACTAMENTE para lo que los guards del modelo no ven —
        // `Query\Builder::update()`, SQL crudo o un seeder—, así que ningún caso que pase por
        // Eloquent puede ejercitarlo. Sin esto, el cinturón entero era código sin red.
        $this->hookPerGuest();
        DB::table('product_addons')
            ->where('product_id', $this->pack->id)->where('addon_id', $this->extraHour->id)
            ->update(['is_included' => true]);
        $this->pack->unsetRelation('addons');

        $this->expectException(ReservationException::class);
        app(AddonResolver::class)->resolve(
            $this->pack->fresh(),
            15,
            [['ticket_type_id' => $this->extraHour->id, 'qty' => 1]],
            Carbon::parse($this->date),
        );
    }

    public function test_the_switch_cannot_be_turned_on_over_an_included_hook(): void
    {
        // La dirección inversa que faltaba: el complemento nace NEUTRO, se engancha a un pack como
        // INCLUIDO y después se le enciende «extiende». El guard del pivote no lo ve —ya pasó— y el
        // del modelo tiene que mirar los enganches que ya existen (la lección de `#324`: dos reglas
        // cruzadas necesitan guarda en las DOS direcciones).
        $neutral = TicketType::create([
            'name' => ['es' => 'Neutro'], 'type' => TicketType::TYPE_ADDON, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'position' => 3,
        ]);
        $this->pack->addons()->attach($neutral->id, [
            'position' => 2, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => true, 'is_mandatory' => false, 'max_qty' => 1,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $neutral->update(['extends_parent_stay' => true]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────────

    private function offerDto(?ResolvedAddons $offer): ?ResolvedAddon
    {
        foreach ($offer?->singles ?? [] as $row) {
            if ((int) $row->productId === (int) $this->extraHour->id) {
                return $row;
            }
        }

        return null;
    }

    private function hookPerGuest(): void
    {
        $this->pack->addons()->attach($this->extraHour->id, [
            'position' => 1, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
            'max_qty' => null,
        ]);
        $this->pack->unsetRelation('addons');
    }

    private function hookFixed(int $maxQty = 1): void
    {
        $this->pack->addons()->attach($this->extraHour->id, [
            'position' => 1, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
            'max_qty' => $maxQty,
        ]);
        $this->pack->unsetRelation('addons');
    }

    private function pivot(): ProductAddon
    {
        return ProductAddon::query()
            ->where('product_id', $this->pack->id)
            ->where('addon_id', $this->extraHour->id)
            ->firstOrFail();
    }

    private function slot(string $time): Slot
    {
        return Slot::with('zone')
            ->where('zone_id', $this->zone->id)->where('date', $this->date)->where('start_time', $time)
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function offerRow(bool $selected, int $guests): array
    {
        $offered = $this->pack->fresh()->addons;
        $view = app(AddonResolver::class)->viewModel(
            $offered,
            $selected ? [$this->extraHour->id => 1] : [],
            [],
            $guests,
            true,
            Carbon::parse($this->date),
        );

        foreach ($view['singles'] as $row) {
            if ((int) $row['id'] === (int) $this->extraHour->id) {
                return $row;
            }
        }

        $this->fail('La hora extra no se ofreció.');
    }

    private function buy(string $time, int $guests, int $blocks = 1): Order
    {
        return $this->creator->createPendingOrder(User::factory()->create(), [[
            'ticket_type_id' => $this->pack->id, 'date' => $this->date, 'time' => $time,
            'qty' => $guests, 'event_data' => [],
            'addons' => $blocks > 0 ? [['ticket_type_id' => $this->extraHour->id, 'qty' => $blocks]] : [],
        ]]);
    }

    private function edit(Order $order, OrderItem $item, int $newQty): void
    {
        $order->forceFill(['status' => Order::STATUS_PAID, 'expires_at' => null])->save();

        $operator = User::factory()->create();
        $operator->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $operator->roles->first()->permissions()->sync(
            Permission::whereIn('name', ['orders.view', 'orders.edit_item'])->pluck('id'),
        );

        $outcome = app(OrderItemEditor::class)->edit(
            $order->fresh(),
            $item->fresh(),
            $this->date,
            (string) $item->slot->start_time,
            false,
            (int) $item->ticket_type_id,
            $newQty,
            null,
            ['edits' => [], 'adds' => []],
            (string) ($item->fresh()->updated_at?->getTimestamp() ?? ''),
            $operator,
        );

        $this->assertTrue($outcome->ok, 'la edición debería pasar: '.($outcome->reason ?? ''));
    }
}
