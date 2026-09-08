<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\ItemActionOutcome;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ItemEditPricing;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\OrderItemEditor;
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
 * **EL SELLO DEL MODO de un complemento — la T1** (`specs/hora-extra.md` §12, `DECISIONES #448`).
 *
 * `order_items.quantity` de una línea hija significa dos cosas distintas —BLOQUES de tiempo o
 * PERSONAS— y hasta `#448` quién lo decidía era una columna de la CONFIGURACIÓN
 * (`product_addons.quantity_mode`), no un hecho de la línea. Cambiar el enganche **reinterpretaba
 * datos ya vendidos**: medido en producción, poner la hora extra de sala en «por invitado» convertía
 * 5,00 € en 40,00 € en la primera edición de cantidad de esa fiesta.
 *
 * ▶ **La T1 solo escribe el hecho: NINGUNA lectura cambia todavía.** Por eso estos casos aseveran
 * lo que queda GUARDADO, no una conducta nueva — la conducta llega en la T2. Que la suite entera
 * siga verde sin tocar un caso es parte del contrato de esta tanda.
 *
 * ⚠️⚠️ **Se compra por la PUERTA REAL** (`OrderCreator` y `OrderItemEditor`), nunca fabricando la
 * fila a mano: el sello lo escriben las puertas, así que un caso que construyera el `OrderItem`
 * directamente pasaría en verde con las puertas rotas. Es la razón por la que `age_family_seal` se
 * probó igual (`#288`).
 */
class AddonQuantityModeSealTest extends TestCase
{
    use RefreshDatabase;

    private OrderCreator $creator;

    private User $user;

    private Zone $zone;

    private TicketType $pack;

    private TicketType $extraHour;

    private TicketType $menu;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = app(OrderCreator::class);
        $this->user = User::factory()->create();
        $this->date = Carbon::today()->addDays(3)->toDateString();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false,
            'max_per_slot' => 2, 'max_guests_per_slot' => 60, 'prep_blocks_cupo' => false,
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
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1500,
        ]);

        // Un EXTENSOR: su cantidad son BLOQUES de tiempo.
        $this->extraHour = TicketType::create([
            'name' => ['es' => 'Hora extra de sala'], 'type' => TicketType::TYPE_ADDON,
            'duration_min' => 60, 'extends_parent_stay' => true,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $this->extraHour->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 500,
        ]);
        $this->pack->addons()->attach($this->extraHour->id, [
            'position' => 1, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
            'max_qty' => 2,
        ]);

        // Un POR-INVITADO: su cantidad son PERSONAS. Es el otro lado de la ambigüedad.
        $this->menu = TicketType::create([
            'name' => ['es' => 'Menú'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 3,
        ]);
        $this->menu->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 200,
        ]);
        $this->pack->addons()->attach($this->menu->id, [
            'position' => 2, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'allow_extra' => false,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
        ]);
    }

    // ─── Puerta 1 · la VENTA ─────────────────────────────────────────────────────────

    public function test_buying_seals_the_unit_each_addon_was_sold_with(): void
    {
        $order = $this->buy('15:00:00', 12, extraBlocks: 1, withMenu: true);

        $extra = $this->child($order, $this->extraHour);
        $menu = $this->child($order, $this->menu);

        // La MISMA columna dice cosas distintas, y por eso hacía falta: el 1 del extensor son
        // BLOQUES y el 12 del menú son PERSONAS.
        $this->assertSame(ProductAddon::MODE_FIXED, $extra->addon_quantity_mode);
        $this->assertSame(1, (int) $extra->quantity);

        $this->assertSame(ProductAddon::MODE_PER_GUEST, $menu->addon_quantity_mode);
        $this->assertSame(12, (int) $menu->quantity, 'un per_guest nace con la cantidad del padre');
    }

    public function test_the_seal_survives_the_catalog_changing_its_mind(): void
    {
        // ❗ Éste es el caso que da sentido a toda la tanda, y reproduce el daño MEDIDO en producción
        // (§12.2): una hora extra vendida como 1 bloque a 5,00 €, con el enganche cambiado después.
        $order = $this->buy('15:00:00', 12, extraBlocks: 1, withMenu: false);
        $child = $this->child($order, $this->extraHour);

        // El catálogo cambia de opinión DESPUÉS de la venta. Se hace por la puerta de atrás porque el
        // candado de `#443` bloquea el cambio con líneas vivas — y ese candado es justo lo que la T3
        // re-apunta. Lo que aquí importa es que el HECHO ya está escrito y no se mueve.
        DB::table('product_addons')
            ->where('product_id', $this->pack->id)->where('addon_id', $this->extraHour->id)
            ->update(['quantity_mode' => ProductAddon::MODE_PER_GUEST]);

        $this->assertSame(
            ProductAddon::MODE_FIXED,
            $child->fresh()->addon_quantity_mode,
            'lo que se compró ayer no lo reescribe el catálogo de mañana (PAY-19)',
        );
    }

    // ─── Puerta 2 · el EDITOR del panel ──────────────────────────────────────────────

    public function test_adding_an_addon_from_the_panel_seals_it_too(): void
    {
        $order = $this->buy('15:00:00', 12, extraBlocks: 0, withMenu: false);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $outcome = $this->edit($order, $item, [
            'edits' => [],
            'adds' => [['ticket_type_id' => $this->menu->id, 'quantity' => 1]],
        ]);
        // ⚠️ El aserto IMPRIME el motivo: un `assertFalse` mudo aquí costó cuatro rojos intermitentes
        // en `#447`, y lo que los cerró no fue una hipótesis mejor sino que el rojo hablara.
        $this->assertFalse($outcome->isBlocked(), 'el editor rechazó el alta: '.($outcome->reason ?? '—'));

        $child = $this->child($order->fresh(), $this->menu);
        $this->assertSame(ProductAddon::MODE_PER_GUEST, $child->addon_quantity_mode);
        // Y la cantidad la puso `effectiveQuantity` con ese mismo pivote: 12 personas, no el 1 pedido.
        $this->assertSame(12, (int) $child->quantity, 'el sello describe la cantidad que viaja con él');
    }

    public function test_changing_the_product_re_seals_the_surviving_children(): void
    {
        // ❗❗ `PAY-19` con el precedente de `sealUpdateFor()`: **pack nuevo → sello nuevo**. Una hija
        // cuyo complemento cuelga TAMBIÉN del producto nuevo sobrevive al cambio y desde ese instante
        // la gobierna OTRA fila de `product_addons`, que puede declarar otro modo — el re-escalado ya
        // lo asume (lee `$newType->addons()`). Sin el re-sello, su sello describiría un enganche que
        // ya no la gobierna, y la T2 lo leería como una divergencia del catálogo que no existe.
        $otroPack = TicketType::create([
            'name' => ['es' => 'Cumpleaños XL'], 'zone_id' => $this->zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'prep_before_min' => 0, 'prep_after_min' => 0,
            'min_qty' => 8, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 4,
        ]);
        $otroPack->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1800,
        ]);
        // El MISMO complemento, enganchado al otro pack con la unidad CONTRARIA. Son filas
        // independientes: que hoy coincidan en producción es un dato, no una garantía.
        $otroPack->addons()->attach($this->menu->id, [
            'position' => 1, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
        ]);

        $order = $this->buy('15:00:00', 12, extraBlocks: 0, withMenu: true);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $this->assertSame(ProductAddon::MODE_PER_GUEST, $this->child($order, $this->menu)->addon_quantity_mode);

        $outcome = $this->editTo($order, $item, $otroPack);
        $this->assertFalse($outcome->isBlocked(), 'el editor rechazó el cambio de producto: '.($outcome->reason ?? '—'));

        $this->assertSame(
            ProductAddon::MODE_FIXED,
            $this->child($order->fresh(), $this->menu)->fresh()->addon_quantity_mode,
            'la hija la gobierna ahora el enganche del producto nuevo, y el sello tiene que decirlo',
        );
    }

    public function test_control_editing_without_changing_product_never_overwrites_the_seal(): void
    {
        // CONTROL del anterior, y es la propiedad CENTRAL de toda la feature: el catálogo no
        // reescribe lo vendido. Sin este caso, «re-sellar siempre» pasaría en verde — y entonces
        // cualquier edición de la reserva pisaría el sello con el modo de hoy, que es exactamente el
        // daño que el sello existe para evitar. Lo dijo el arnés: la mutación no mordía.
        $order = $this->buy('15:00:00', 12, extraBlocks: 0, withMenu: true);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $this->assertSame(ProductAddon::MODE_PER_GUEST, $this->child($order, $this->menu)->addon_quantity_mode);

        // El catálogo cambia de opinión DESPUÉS de la venta, por la puerta que los eventos de
        // Eloquent no ven.
        DB::table('product_addons')
            ->where('product_id', $this->pack->id)->where('addon_id', $this->menu->id)
            ->update(['quantity_mode' => ProductAddon::MODE_FIXED]);

        // Una edición corriente: sube la cantidad, sin tocar el producto.
        $outcome = $this->edit($order, $item, ['edits' => [], 'adds' => []], null, newQuantity: 14);
        $this->assertFalse($outcome->isBlocked(), 'el editor rechazó la subida: '.($outcome->reason ?? '—'));

        $this->assertSame(
            ProductAddon::MODE_PER_GUEST,
            $this->child($order->fresh(), $this->menu)->fresh()->addon_quantity_mode,
            'lo que se vendió no lo reescribe una edición posterior (PAY-19)',
        );
    }

    // ─── Los dos SILENCIOS legítimos ─────────────────────────────────────────────────

    public function test_a_line_without_a_hookup_is_silence_and_not_fixed(): void
    {
        // Un complemento que NO cuelga de este producto no lo gobierna ningún enganche: el editor no
        // tiene modo que copiar, y `null` dice la verdad donde `fixed` afirmaría algo.
        // Se comprueba sobre el compositor, que es donde se decide (§12.6.1).
        $huerfano = TicketType::create([
            'name' => ['es' => 'Suelto'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 9,
        ]);
        $huerfano->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 100,
        ]);

        $order = $this->buy('15:00:00', 12, extraBlocks: 0, withMenu: false);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $pricing = app(ItemEditPricing::class)->computeAddonPricing(
            $item,
            $this->pack,
            [],
            [['ticket_type_id' => $huerfano->id, 'quantity' => 1]],
            $this->date,
        );

        $this->assertArrayHasKey('add_quantity_modes', $pricing);
        $this->assertNull(
            $pricing['add_quantity_modes'][$huerfano->id],
            'sin enganche no hay modo que sellar, y eso es SILENCIO',
        );
    }

    public function test_both_early_exits_still_declare_the_seal_key(): void
    {
        // ⚠️ Las dos salidas tempranas de `computeAddonPricing` enumeran el contrato A MANO, así que
        // olvidar la clave en una de ellas no rompe nada: deja un `undefined index` esperando. Este
        // caso existe porque ese olvido es invisible.
        $item = $this->buy('15:00:00', 12, extraBlocks: 0, withMenu: false)
            ->items()->whereNull('parent_item_id')->firstOrFail();
        $pricing = app(ItemEditPricing::class);

        $invalid = $pricing->computeAddonPricing($item, $this->pack, [], [['ticket_type_id' => 999999, 'quantity' => 1]], $this->date);
        $this->assertSame('invalid_product', $invalid['error']);
        $this->assertArrayHasKey('add_quantity_modes', $invalid);

        // Un complemento SIN precio ese día: la otra salida.
        $sinPrecio = TicketType::create([
            'name' => ['es' => 'Sin precio'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 8,
        ]);
        $this->pack->addons()->attach($sinPrecio->id, [
            'position' => 3, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
        ]);

        $noDate = $pricing->computeAddonPricing($item, $this->pack, [], [['ticket_type_id' => $sinPrecio->id, 'quantity' => 1]], $this->date);
        $this->assertSame('addon_unavailable_on_date', $noDate['error']);
        $this->assertArrayHasKey('add_quantity_modes', $noDate);
    }

    // ─── El SANEO del vocabulario ────────────────────────────────────────────────────

    public function test_an_unknown_mode_reads_as_fixed_instead_of_propagating(): void
    {
        // La puerta que los eventos de Eloquent no ven (`Query\Builder::update()`). Antes de `#448`
        // `isPerGuest()` comparaba la cadena cruda, así que un valor torcido caía a `fixed` **en
        // silencio**; ahora cae ahí por decisión escrita, que es el lado que reserva igual o más sala.
        DB::table('product_addons')
            ->where('product_id', $this->pack->id)->where('addon_id', $this->menu->id)
            ->update(['quantity_mode' => 'una_cosa_rara']);

        $pivot = $this->pack->fresh()->addons()->where('ticket_types.id', $this->menu->id)->firstOrFail()->pivot;

        $this->assertSame(ProductAddon::MODE_FIXED, $pivot->quantityUnit());
        $this->assertFalse($pivot->isPerGuest());
    }

    public function test_control_a_known_mode_is_not_touched_by_the_sanitiser(): void
    {
        // CONTROL del anterior: sin él, un saneo que devolviera SIEMPRE `fixed` pasaría en verde.
        $pivot = $this->pack->addons()->where('ticket_types.id', $this->menu->id)->firstOrFail()->pivot;

        $this->assertSame(ProductAddon::MODE_PER_GUEST, $pivot->quantityUnit());
        $this->assertTrue($pivot->isPerGuest());
    }

    // ─── La T1 no cambia NINGUNA lectura ─────────────────────────────────────────────

    public function test_the_seal_does_not_change_any_behaviour_yet(): void
    {
        // El contrato de esta tanda: se escribe el hecho y no lo lee nadie. Con el sello puesto y el
        // catálogo cambiado por detrás, la conducta sigue siendo la de ANTES —el pivote vivo manda—,
        // que es lo que hace la T1 segura de desplegar sola. La T2 invierte justamente esto.
        $order = $this->buy('15:00:00', 12, extraBlocks: 1, withMenu: false);
        $parent = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $this->assertSame(60, (int) $parent->extra_minutes, '1 bloque de 60 min');

        DB::table('product_addons')
            ->where('product_id', $this->pack->id)->where('addon_id', $this->extraHour->id)
            ->update(['quantity_mode' => ProductAddon::MODE_PER_GUEST]);

        // Hoy los minutos siguen saliendo del catálogo VIVO: con `per_guest`, `blocksFor()` da 1.
        // El número no cambia aquí porque la cantidad ya era 1 — lo que se asevera es que el sello
        // NO ha alterado el camino, no que el camino sea el correcto (eso es la T2).
        $this->assertSame(
            ProductAddon::MODE_FIXED,
            $this->child($order, $this->extraHour)->fresh()->addon_quantity_mode,
            'el hecho está escrito aunque todavía no lo lea nadie',
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────────

    private function child(Order $order, TicketType $addon): OrderItem
    {
        return $order->items()
            ->whereNotNull('parent_item_id')
            ->where('ticket_type_id', $addon->id)
            ->whereNull('cancelled_at')
            ->firstOrFail();
    }

    private function buy(string $time, int $guests, int $extraBlocks, bool $withMenu): Order
    {
        $line = [
            'ticket_type_id' => $this->pack->id, 'date' => $this->date, 'time' => $time,
            'qty' => $guests, 'event_data' => [],
        ];
        $addons = [];
        if ($extraBlocks > 0) {
            $addons[] = ['ticket_type_id' => $this->extraHour->id, 'qty' => $extraBlocks];
        }
        if ($withMenu) {
            $addons[] = ['ticket_type_id' => $this->menu->id, 'qty' => 1];
        }
        if ($addons !== []) {
            $line['addons'] = $addons;
        }

        return $this->creator->createPendingOrder($this->user, [$line]);
    }

    /** Conduce el editor cambiando el PRODUCTO de la reserva y conservando la cantidad. */
    private function editTo(Order $order, OrderItem $item, TicketType $nuevo): ItemActionOutcome
    {
        return $this->edit($order, $item, ['edits' => [], 'adds' => []], (int) $nuevo->id);
    }

    /**
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, array{ticket_type_id:int, quantity:int}>}  $addonEdits
     */
    private function edit(Order $order, OrderItem $item, array $addonEdits, ?int $newProductId = null, ?int $newQuantity = null): ItemActionOutcome
    {
        $order->forceFill(['status' => Order::STATUS_PAID, 'expires_at' => null])->save();

        $operator = User::factory()->create();
        $operator->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $operator->roles->first()->permissions()->sync(
            Permission::whereIn('name', ['orders.view', 'orders.edit_item'])->pluck('id'),
        );

        // ⚠️⚠️ El testigo optimista es el de la RESERVA, no el del PEDIDO, y `updated_at` tiene
        // precisión de SEGUNDO: el `save()` de arriba puede cruzarlo bajo la suite en paralelo y el
        // editor respondería `stale_item_version` con razón. Es el defecto de arnés de `#447`, y por
        // eso el testigo se relee DESPUÉS de tocar el pedido.
        $fresh = $item->fresh();

        return app(OrderItemEditor::class)->edit(
            $order->fresh(),
            $fresh,
            $this->date,
            (string) $item->slot->start_time,
            false,
            $newProductId ?? (int) $item->ticket_type_id,
            $newQuantity ?? (int) $item->quantity,
            null,
            $addonEdits,
            (string) $fresh->updated_at?->timestamp,
            $operator,
        );
    }
}
