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
 * **EL SELLO DEL MODO — la T2: las TRES lecturas** (`specs/hora-extra.md` §12.7, `DECISIONES #448`).
 *
 * La T1 escribió el hecho y no lo leía nadie. Ésta es la tanda que **cambia la conducta**: los tres
 * únicos sitios que preguntaban al catálogo por la unidad de una línea YA VENDIDA se lo preguntan a
 * la línea — DINERO (el re-escalado), AFORO (los minutos que alarga) y PERMISO (qué se puede editar).
 *
 * ❗❗❗ **Todos estos casos tuercen el enganche DESPUÉS de vender**, que es el escenario real medido
 * en producción: la línea del «Menú 2» de `R-BOMAZH` se vendió el 01/09 y su enganche cambió el
 * 06/09. Antes de `#448` eso reinterpretaba lo vendido; ahora no lo toca.
 *
 * ⚠️ **Y cada caso lleva su CONTROL sin sello**, porque lo que hay que demostrar no es que el número
 * salga bien: es que sale bien **porque manda el sello**. Sin el control, un caso verde no distingue
 * «lo arregló el sello» de «aquí nunca hubo diferencia».
 */
class AddonQuantityModeReadsTest extends TestCase
{
    use RefreshDatabase;

    private OrderCreator $creator;

    private Zone $zone;

    private TicketType $pack;

    private TicketType $extraHour;

    private TicketType $menu;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = app(OrderCreator::class);
        $this->date = Carbon::today()->addDays(3)->toDateString();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false,
            'max_per_slot' => 3, 'max_guests_per_slot' => 90, 'prep_blocks_cupo' => false,
        ]);

        foreach (range(10, 23) as $hour) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 300, 'online_capacity' => 300,
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
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 500,
        ]);

        $this->menu = TicketType::create([
            'name' => ['es' => 'Menú'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 3,
        ]);
        $this->menu->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 200,
        ]);
    }

    // ─── Lectura 1 · AFORO, que es la mitad que no estaba escrita ────────────────────

    public function test_a_stay_extension_sold_per_guest_keeps_its_minutes_when_the_hookup_flips(): void
    {
        // ❗❗ **El caso de los 900 minutos.** Vendida por invitados, la hora extra son 60 min —*una
        // hora es una hora, la compren 8 o 20*—. Si el enganche pasa a `fixed`, sin sello los
        // minutos saldrían de la CANTIDAD (15 bloques × 60 = 900): la sala de una fiesta ya vendida
        // pediría quince horas. Con el sello, sigue siendo una.
        $this->hookExtraHour(ProductAddon::MODE_PER_GUEST);
        $order = $this->buyWithExtraHour(guests: 15);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $this->assertSame(15, (int) $this->child($order)->quantity, 'per_guest: la cantidad son PERSONAS');
        $this->assertSame(60, (int) $item->extra_minutes);

        $this->flipHookup(ProductAddon::MODE_FIXED);

        // Mover el día RECALCULA los minutos (`changeSlot`), que es el disparador más ancho: no hace
        // falta tocar la cantidad para que el aforo se re-derive.
        $outcome = $this->moveTo($order, $item, '12:00:00');
        $this->assertFalse($outcome->isBlocked(), 'el editor rechazó el movimiento: '.($outcome->reason ?? '—'));

        $this->assertSame(
            60,
            (int) $item->fresh()->extra_minutes,
            'la fiesta se vendió con UNA hora extra: el catálogo de mañana no la alarga a quince',
        );
    }

    public function test_control_without_a_seal_the_same_flip_stretches_the_room(): void
    {
        // CONTROL del anterior, y es el que demuestra que lo arregla el SELLO y no otra cosa: la
        // MISMA línea sin sello (como las vendidas antes de `#448`) sí se re-deriva del catálogo.
        $this->hookExtraHour(ProductAddon::MODE_PER_GUEST);
        $order = $this->buyWithExtraHour(guests: 15);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();

        // Se le quita el sello: vuelve al mundo anterior a esta feature.
        DB::table('order_items')->where('id', $this->child($order)->id)
            ->update(['addon_quantity_mode' => null]);

        $this->flipHookup(ProductAddon::MODE_FIXED);
        $this->moveTo($order, $item, '12:00:00');

        $this->assertSame(
            900,
            (int) $item->fresh()->extra_minutes,
            'sin sello manda el catálogo vivo: 15 × 60 min — el daño que esta tanda cierra',
        );
    }

    // ─── Lectura 2 · DINERO ──────────────────────────────────────────────────────────

    public function test_a_child_sold_per_guest_still_rescales_after_the_hookup_flips(): void
    {
        // Se vendió por invitados, así que su cantidad SIGUE al nº de invitados — aunque el enganche
        // diga hoy `fixed`. Lo contrario dejaría a la fiesta cobrando menús para 12 con 16 comiendo.
        $this->hookMenu(ProductAddon::MODE_PER_GUEST);
        $order = $this->buyWithMenu(guests: 12);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $this->assertSame(12, (int) $this->child($order, $this->menu)->quantity);

        $this->flipHookup(ProductAddon::MODE_FIXED, $this->menu);

        $outcome = $this->edit($order, $item, newQuantity: 16);
        $this->assertFalse($outcome->isBlocked(), 'el editor rechazó la subida: '.($outcome->reason ?? '—'));

        $this->assertSame(16, (int) $this->child($order->fresh(), $this->menu)->fresh()->quantity);
    }

    public function test_a_child_sold_fixed_is_not_rescaled_when_the_hookup_turns_per_guest(): void
    {
        // ❗❗ **El espejo, y es el defecto MEDIDO en producción** (`R-BOMAZH`): la línea se vendió
        // como 1 unidad y el enganche pasó a `per_guest` después. Sin sello, la primera edición de
        // cantidad la multiplicaba por los invitados — 1 × 2,00 € → 17 × 2,00 €, +32,00 € que nadie
        // vendió. Con sello, no se la toca.
        $this->hookMenu(ProductAddon::MODE_FIXED);
        $order = $this->buyWithMenu(guests: 12, menuQty: 1);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $this->assertSame(1, (int) $this->child($order, $this->menu)->quantity);

        $this->flipHookup(ProductAddon::MODE_PER_GUEST, $this->menu);

        $outcome = $this->edit($order, $item, newQuantity: 16);
        $this->assertFalse($outcome->isBlocked(), 'el editor rechazó la subida: '.($outcome->reason ?? '—'));

        $this->assertSame(
            1,
            (int) $this->child($order->fresh(), $this->menu)->fresh()->quantity,
            'se vendió UNA unidad: subir invitados no la convierte en dieciséis',
        );
    }

    // ─── Lectura 3 · PERMISO, y la regla de DIVERGENCIA ──────────────────────────────

    public function test_a_child_sold_per_guest_stays_locked_even_if_the_hookup_turns_fixed(): void
    {
        // Su cantidad la manda el nº de invitados, así que no es editable a mano. Que el enganche
        // cambie no convierte en editable una línea que se vendió bloqueada.
        $this->hookMenu(ProductAddon::MODE_PER_GUEST);
        $order = $this->buyWithMenu(guests: 12);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();

        $this->flipHookup(ProductAddon::MODE_FIXED, $this->menu);

        $meta = app(OrderItemEditor::class)->childAddonMeta($item->fresh());
        $child = $this->child($order, $this->menu);

        $this->assertTrue($meta[$child->id]['locked'], 'se vendió por invitados: sigue bloqueada');
    }

    public function test_divergence_caps_a_line_at_its_own_quantity_instead_of_leaving_it_uncapped(): void
    {
        // ⚠️⚠️ La regla de §12.8. Al pasar un enganche a `per_guest` el panel BORRA su tope, así que
        // una hija sellada `fixed` bajo ese enganche saldría **editable y SIN TECHO** — un estado que
        // no existe en ninguna configuración real. Su techo pasa a ser su propia cantidad: se puede
        // bajar o quitar, nunca subir.
        $this->hookMenu(ProductAddon::MODE_FIXED, maxQty: 5);
        $order = $this->buyWithMenu(guests: 12, menuQty: 2);
        $item = $order->items()->whereNull('parent_item_id')->firstOrFail();
        $child = $this->child($order, $this->menu);

        $antes = app(OrderItemEditor::class)->childAddonMeta($item)[$child->id];
        $this->assertSame(5, $antes['max'], 'sin divergencia manda el tope del enganche');

        // El enganche pasa a per_guest Y pierde su tope, que es lo que hace el panel de verdad.
        DB::table('product_addons')
            ->where('product_id', $this->pack->id)->where('addon_id', $this->menu->id)
            ->update(['quantity_mode' => ProductAddon::MODE_PER_GUEST, 'max_qty' => null]);

        $despues = app(OrderItemEditor::class)->childAddonMeta($item->fresh())[$child->id];

        $this->assertFalse($despues['locked'], 'se vendió como cantidad fija: sigue siendo editable');
        $this->assertSame(
            2,
            $despues['max'],
            'y su techo es lo que ya tiene: sin esta regla saldría `null`, o sea sin techo',
        );
    }

    // ─── La DIVERGENCIA se DICE, no solo se aplica ───────────────────────────────────

    public function test_a_diverging_line_says_so(): void
    {
        // La mitad de PRESENTACIÓN de §12.8: con el sello y el enganche declarando unidades
        // distintas la línea queda acotada a su propia cantidad, y sin decirlo el operador lo
        // descubre al no poder subirla — sin que nada se lo explique.
        $this->hookMenu(ProductAddon::MODE_FIXED);
        $order = $this->buyWithMenu(guests: 12, menuQty: 2);
        $child = $this->child($order, $this->menu);

        $this->assertFalse($child->addonUnitDivergesFromCatalogue(), 'sin divergencia no se dice nada');

        $this->flipHookup(ProductAddon::MODE_PER_GUEST, $this->menu);

        $this->assertTrue($child->fresh()->addonUnitDivergesFromCatalogue());
    }

    public function test_control_silence_is_not_divergence(): void
    {
        // CONTROL: una línea SIN sello no diverge de nada — no declara unidad, así que no hay
        // discrepancia que afirmar. Sin este caso, «lo que no coincide, diverge» pasaría en verde y
        // marcaría toda línea anterior al despliegue.
        $this->hookMenu(ProductAddon::MODE_FIXED);
        $order = $this->buyWithMenu(guests: 12, menuQty: 2);
        $child = $this->child($order, $this->menu);

        DB::table('order_items')->where('id', $child->id)->update(['addon_quantity_mode' => null]);
        $this->flipHookup(ProductAddon::MODE_PER_GUEST, $this->menu);

        $this->assertFalse($child->fresh()->addonUnitDivergesFromCatalogue());
    }

    // ─── T3 · el CANDADO re-apuntado ─────────────────────────────────────────────────

    public function test_a_sealed_line_no_longer_locks_the_hookup(): void
    {
        // ❗❗ **Éste es el desbloqueo.** Antes, una sola fiesta viva impedía cambiar el modo del
        // enganche — y con venta continua esa ventana no se abría nunca. Ahora la línea declara su
        // unidad, así que cambiar el catálogo ya no puede reinterpretarla: no hay nada que candar.
        $this->hookExtraHour(ProductAddon::MODE_FIXED);
        $order = $this->buyWithExtraHour(guests: 15);

        $this->assertNotNull($this->child($order)->addon_quantity_mode, 'la venta nueva nace sellada');

        $pivot = $this->pack->addons()->where('ticket_types.id', $this->extraHour->id)->firstOrFail()->pivot;
        $this->assertFalse(
            $pivot->hasEditableSoldLines(),
            'con la línea sellada el candado se suelta: es lo que desbloquea al operador',
        );
    }

    public function test_control_an_unsealed_line_still_locks_the_hookup(): void
    {
        // CONTROL, y es la mitad que impide que esto sea «retirar el candado»: una línea SIN sello
        // —las vendidas antes de `#448`— sigue bloqueando, porque a ella sí la reinterpretaría un
        // cambio de catálogo. El candado muere por VACIAMIENTO, no por decreto.
        $this->hookExtraHour(ProductAddon::MODE_FIXED);
        $order = $this->buyWithExtraHour(guests: 15);

        DB::table('order_items')->where('id', $this->child($order)->id)
            ->update(['addon_quantity_mode' => null]);

        $pivot = $this->pack->addons()->where('ticket_types.id', $this->extraHour->id)->firstOrFail()->pivot;
        $this->assertTrue(
            $pivot->hasEditableSoldLines(),
            'sin sello la línea sigue expuesta al pivote vivo: el candado tiene que seguir cerrado',
        );
    }

    public function test_a_finished_party_never_locked_and_still_does_not(): void
    {
        // Control del OTRO eje, para que el caso de arriba no pase por el motivo equivocado: una
        // fiesta ya celebrada no la puede re-escalar nadie (`item_finished`), así que nunca contó.
        $this->hookExtraHour(ProductAddon::MODE_FIXED);
        $order = $this->buyWithExtraHour(guests: 15);

        DB::table('order_items')->where('id', $this->child($order)->id)
            ->update(['addon_quantity_mode' => null]);
        // La fiesta se movió al pasado: sigue viva y sin sello, pero ya no es editable.
        DB::table('slots')->where('date', $this->date)
            ->update(['date' => Carbon::today()->subDays(5)->toDateString()]);

        $pivot = $this->pack->addons()->where('ticket_types.id', $this->extraHour->id)->firstOrFail()->pivot;
        $this->assertFalse($pivot->hasEditableSoldLines());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────────

    private function hookExtraHour(string $mode): void
    {
        $this->pack->addons()->attach($this->extraHour->id, [
            'position' => 1, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => $mode, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
            'max_qty' => 2, // obligatorio en un extensor (§10.5·2)
        ]);
    }

    private function hookMenu(string $mode, ?int $maxQty = null): void
    {
        $this->pack->addons()->attach($this->menu->id, [
            'position' => 2, 'stage' => ProductAddon::STAGE_BOOKING,
            'quantity_mode' => $mode, 'allow_extra' => true,
            'included_quantity' => 1, 'is_included' => false, 'is_mandatory' => false,
            'max_qty' => $maxQty,
        ]);
    }

    /** Tuerce el modo del enganche por la puerta que los eventos de Eloquent no ven. */
    private function flipHookup(string $mode, ?TicketType $addon = null): void
    {
        DB::table('product_addons')
            ->where('product_id', $this->pack->id)
            ->where('addon_id', ($addon ?? $this->extraHour)->id)
            ->update(['quantity_mode' => $mode]);
    }

    private function child(Order $order, ?TicketType $addon = null): OrderItem
    {
        return $order->items()
            ->whereNotNull('parent_item_id')
            ->where('ticket_type_id', ($addon ?? $this->extraHour)->id)
            ->whereNull('cancelled_at')
            ->firstOrFail();
    }

    private function buyWithExtraHour(int $guests): Order
    {
        return $this->creator->createPendingOrder(User::factory()->create(), [[
            'ticket_type_id' => $this->pack->id, 'date' => $this->date, 'time' => '15:00:00',
            'qty' => $guests, 'event_data' => [],
            'addons' => [['ticket_type_id' => $this->extraHour->id, 'qty' => 1]],
        ]]);
    }

    private function buyWithMenu(int $guests, int $menuQty = 1): Order
    {
        return $this->creator->createPendingOrder(User::factory()->create(), [[
            'ticket_type_id' => $this->pack->id, 'date' => $this->date, 'time' => '15:00:00',
            'qty' => $guests, 'event_data' => [],
            'addons' => [['ticket_type_id' => $this->menu->id, 'qty' => $menuQty]],
        ]]);
    }

    private function moveTo(Order $order, OrderItem $item, string $time): ItemActionOutcome
    {
        return $this->drive($order, $item, $time, (int) $item->quantity);
    }

    private function edit(Order $order, OrderItem $item, int $newQuantity): ItemActionOutcome
    {
        return $this->drive($order, $item, (string) $item->slot->start_time, $newQuantity);
    }

    private function drive(Order $order, OrderItem $item, string $time, int $quantity): ItemActionOutcome
    {
        $order->forceFill(['status' => Order::STATUS_PAID, 'expires_at' => null])->save();

        $operator = User::factory()->create();
        $operator->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $operator->roles->first()->permissions()->sync(
            Permission::whereIn('name', ['orders.view', 'orders.edit_item'])->pluck('id'),
        );

        // ⚠️ El testigo es el de la RESERVA y se relee tras tocar el pedido (`#447`).
        $fresh = $item->fresh();

        return app(OrderItemEditor::class)->edit(
            $order->fresh(),
            $fresh,
            $this->date,
            $time,
            false,
            (int) $item->ticket_type_id,
            $quantity,
            null,
            ['edits' => [], 'adds' => []],
            (string) $fresh->updated_at?->timestamp,
            $operator,
        );
    }
}
