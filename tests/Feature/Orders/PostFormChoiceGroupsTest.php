<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\AddonChoiceGroup;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AddonResolver;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\GuestCountAdjuster;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **LOS GRUPOS DE OPCIONES en la lista de invitados** (`[DECIDIDO owner]` `DECISIONES #914`, `fiesta-sistema-nuevo.md` §4.21,
 * P3·1): «elige una» entre complementos de venta posterior —la merienda es el primer caso—, cada opción incluida o de pago y
 * por niño o fija, y el grupo con su título y «hay que elegir» en `addon_choice_groups`.
 *
 * Lo que estos casos garantizan, cada uno con su control:
 *  · las reglas 2–4 de `#413` se abren SOLO para las opciones de un grupo de la tabla (en la escritura y en el cinturón);
 *  · elegir una escribe su línea —por niño, una para cada niño— y una incluida no mueve dinero;
 *  · cambiar de opción cancela una y crea otra en el MISMO guardado, y el libro cierra;
 *  · dos a la vez no mueven nada, y un grupo con «hay que elegir» no se vacía;
 *  · una opción de pago por niño se paga en el parque y sigue al número de niños;
 *  · un grupo creado DESPUÉS de vender la fiesta no se le pide («No se les pide»).
 */
class PostFormChoiceGroupsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $pack;

    private TicketType $sandwich;

    private TicketType $pizza;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        $this->priceIt($this->pack, 2500);

        $this->sandwich = $this->addon('Sándwich', 250);
        $this->pizza = $this->addon('Pizza', 250);
    }

    // ── 1 · La guarda, en las dos direcciones ────────────────────────────────────────────────────

    public function test_an_option_of_a_defined_group_may_be_included_per_child_and_grouped(): void
    {
        $this->group('merienda');
        $this->option($this->sandwich);

        $offered = AddonResolver::forStage($this->pack->fresh()->addons, ProductAddon::STAGE_POSTFORM);

        $this->assertSame([$this->sandwich->id], $offered->pluck('id')->all());
    }

    public function test_without_its_group_row_the_same_option_is_refused_and_a_forced_one_never_offered(): void
    {
        // La escritura: sin la fila del grupo, la regla 4 sigue cerrada.
        try {
            $this->option($this->sandwich);
            $this->fail('una opción de grupo sin su fila se ha guardado');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, ProductAddon::query()->where('addon_id', $this->sandwich->id)->count());
        }

        // El cinturón: forzada por la puerta de atrás, ni se ofrece.
        DB::table('product_addons')->insert([
            'product_id' => $this->pack->id, 'addon_id' => $this->sandwich->id, 'position' => 1,
            'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'is_included' => true, 'stage' => ProductAddon::STAGE_POSTFORM,
            'choice_group' => 'merienda',
        ]);
        $this->assertCount(0, AddonResolver::forStage($this->pack->fresh()->addons, ProductAddon::STAGE_POSTFORM));

        // CONTROL: con la fila del grupo, la misma opción SÍ se ofrece.
        $this->group('merienda');
        $this->assertCount(1, AddonResolver::forStage($this->pack->fresh()->addons, ProductAddon::STAGE_POSTFORM));
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function stillClosedOutsideAGroup(): array
    {
        return [
            'incluido, sin grupo' => [['is_included' => true, 'quantity_mode' => ProductAddon::MODE_FIXED, 'max_qty' => 1, 'choice_group' => null]],
            'por niño, sin grupo' => [['quantity_mode' => ProductAddon::MODE_PER_GUEST, 'choice_group' => null]],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    #[DataProvider('stillClosedOutsideAGroup')]
    public function test_rules_two_and_three_stay_closed_outside_a_group(array $overrides): void
    {
        $this->group('merienda');

        $this->expectException(\InvalidArgumentException::class);
        $this->option($this->sandwich, $overrides);
    }

    public function test_the_options_of_a_group_are_all_sold_in_the_same_stage(): void
    {
        $this->group('merienda');
        $this->option($this->sandwich);

        $this->expectException(\InvalidArgumentException::class);
        $this->pack->configurableAddons()->attach($this->pizza->id, [
            'position' => 2, 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'stage' => ProductAddon::STAGE_BOOKING,
            'choice_group' => 'merienda', 'is_included' => true,
        ]);
    }

    public function test_a_group_with_options_cannot_be_deleted(): void
    {
        $group = $this->group('merienda');
        $this->option($this->sandwich);

        $this->expectException(\InvalidArgumentException::class);
        $group->delete();
    }

    // ── 2 · Elegir, cambiar, y lo que no se puede ────────────────────────────────────────────────

    public function test_choosing_an_included_option_per_child_writes_one_per_child_and_moves_no_money(): void
    {
        $this->group('merienda');
        $this->option($this->sandwich);
        $item = $this->party(qty: 6);

        $changes = $this->service()->reconcile($item, [$this->sandwich->id => 1], 'signed_link');

        $line = $this->liveChild($item, $this->sandwich);
        $this->assertNotNull($line);
        $this->assertSame(6, (int) $line->quantity, 'una para cada niño');
        $this->assertSame(6, (int) $line->free_quantity, 'todas incluidas');
        $this->assertSame(ProductAddon::MODE_PER_GUEST, $line->addon_quantity_mode, 'sellada por niño: así la sigue el número');
        $this->assertSame(0, $changes->deltaCents);
        $this->assertSame([], $changes->blocked);
        $this->assertBookCloses($item, 'tras elegir');
    }

    public function test_switching_option_cancels_the_old_line_and_creates_the_new_one_in_the_same_save(): void
    {
        $this->group('merienda');
        $this->option($this->sandwich);
        $this->option($this->pizza, ['position' => 2]);
        $item = $this->party(qty: 6);
        $this->service()->reconcile($item, [$this->sandwich->id => 1], 'signed_link');

        // Solo la nueva en el cuerpo, como lo mandaría un cliente de la API: la otra se va igual.
        $changes = $this->service()->reconcile($item->fresh(), [$this->pizza->id => 1], 'signed_link');

        $this->assertNull($this->liveChild($item, $this->sandwich), 'la de antes, cancelada');
        $this->assertSame(6, (int) $this->liveChild($item, $this->pizza)?->quantity);
        $this->assertSame(0, $changes->deltaCents);
        $this->assertCount(2, $changes->moves, 'dos gestos: quitar una y poner la otra');
        $this->assertBookCloses($item, 'tras cambiar de opción');
    }

    public function test_two_options_at_once_change_nothing_and_say_so(): void
    {
        $this->group('merienda');
        $this->option($this->sandwich);
        $this->option($this->pizza, ['position' => 2]);
        $item = $this->party();

        $changes = $this->service()->reconcile($item, [$this->sandwich->id => 1, $this->pizza->id => 1], 'signed_link');

        $this->assertFalse($changes->changed());
        $this->assertSame(['choice_conflict', 'choice_conflict'], array_column($changes->blocked, 'reason'));
        $this->assertNull($this->liveChild($item, $this->sandwich));
        $this->assertNull($this->liveChild($item, $this->pizza));
    }

    public function test_a_required_group_can_be_changed_but_not_emptied(): void
    {
        $this->group('merienda', required: true);
        $this->option($this->sandwich);
        $item = $this->party();
        $this->service()->reconcile($item, [$this->sandwich->id => 1], 'signed_link');

        $changes = $this->service()->reconcile($item->fresh(), [$this->sandwich->id => 0], 'signed_link');

        $this->assertFalse($changes->changed());
        $this->assertSame([['addon_id' => $this->sandwich->id, 'reason' => 'choice_required']], $changes->blocked);
        $this->assertNotNull($this->liveChild($item, $this->sandwich), 'sigue elegida');
    }

    public function test_an_optional_group_can_go_back_to_none(): void
    {
        $this->group('pinata');
        $this->option($this->sandwich, ['choice_group' => 'pinata']);
        $item = $this->party();
        $this->service()->reconcile($item, [$this->sandwich->id => 1], 'signed_link');

        $changes = $this->service()->reconcile($item->fresh(), [$this->sandwich->id => 0], 'signed_link');

        $this->assertTrue($changes->changed(), 'sin «hay que elegir», «No, gracias» se puede decir');
        $this->assertNull($this->liveChild($item, $this->sandwich));
    }

    // ── 3 · El dinero de una opción de pago, y el número de niños ────────────────────────────────

    public function test_a_paid_option_per_child_is_paid_at_the_park_and_follows_the_number(): void
    {
        $this->group('merienda');
        $this->option($this->pizza, ['is_included' => false]);
        $item = $this->party(qty: 4);

        $changes = $this->service()->reconcile($item, [$this->pizza->id => 1], 'signed_link');
        $this->assertSame(4 * 250, $changes->deltaCents, 'cuatro a 2,50 €, en el parque');
        $this->assertBookCloses($item, 'tras elegir la de pago');

        app(GuestCountAdjuster::class)->adjust($item->fresh(), 6, 'signed_link');

        $line = $this->liveChild($item, $this->pizza);
        $this->assertSame(6, (int) $line?->quantity, 'sigue al número de niños');
        $this->assertSame(6 * 250, $line?->chargedSubtotalCents());
        $this->assertBookCloses($item, 'tras subir el número');
    }

    // ── 4 · «No se les pide»: un grupo creado DESPUÉS de vender ──────────────────────────────────

    public function test_a_group_created_after_the_sale_is_not_asked(): void
    {
        $group = $this->group('merienda', required: true);
        $this->option($this->sandwich);
        $item = $this->party();
        $group->forceFill(['created_at' => $item->order->created_at->copy()->addMinute()])->save();

        $this->assertSame([], $this->service()->viewFor($item->fresh()), 'ni pregunta');
        $this->assertSame([], $this->service()->choiceGroupsFor($item->fresh()), 'ni «falta elegir»');
        $changes = $this->service()->reconcile($item->fresh(), [$this->sandwich->id => 1], 'signed_link');
        $this->assertSame([['addon_id' => $this->sandwich->id, 'reason' => 'not_offerable']], $changes->blocked);

        // CONTROL: creado ANTES de venderla, sí.
        $group->forceFill(['created_at' => $item->order->created_at->copy()->subMinute()])->save();
        $this->assertCount(1, $this->service()->viewFor($item->fresh()));
    }

    // ── 5 · Lo que se enseña ────────────────────────────────────────────────────────────────────

    public function test_the_view_says_included_and_charges_nothing_even_with_a_tariff(): void
    {
        $this->group('merienda');
        $this->option($this->sandwich);
        $item = $this->party(qty: 6);
        $this->service()->reconcile($item, [$this->sandwich->id => 1], 'signed_link');

        $row = collect($this->service()->viewFor($item->fresh()))->firstWhere('productId', $this->sandwich->id);

        $this->assertTrue($row->included);
        $this->assertTrue($row->perGuest);
        $this->assertSame('merienda', $row->group);
        $this->assertSame('Incluido', $row->note, 'no su tarifa de 2,50 €');
        $this->assertSame(6, $row->quantity);
        $this->assertSame(0, $row->chargedCents, '6 a 0 €, no 6 por su tarifa');
    }

    public function test_the_group_view_says_what_is_pending_and_what_is_chosen(): void
    {
        $this->group('merienda', required: true, title: ['es' => '¿Qué merienda?']);
        $this->option($this->sandwich);
        $this->option($this->pizza, ['position' => 2]);
        $item = $this->party();

        [$group] = $this->service()->choiceGroupsFor($item->fresh());
        $this->assertSame('¿Qué merienda?', $group->title);
        $this->assertSame([$this->sandwich->id, $this->pizza->id], $group->addonIds);
        $this->assertTrue($group->pending(), 'hay que elegir y no se ha elegido');
        $this->assertTrue($group->open);

        $this->service()->reconcile($item, [$this->pizza->id => 1], 'signed_link');
        [$group] = $this->service()->choiceGroupsFor($item->fresh());
        $this->assertSame($this->pizza->id, $group->chosenAddonId);
        $this->assertFalse($group->pending());
    }

    public function test_an_included_option_without_a_tariff_is_offered_free_and_a_paid_one_is_not(): void
    {
        $this->group('merienda');
        $sinTarifa = $this->addon('Perrito', null);
        $this->option($sinTarifa);
        $item = $this->party();

        $this->assertSame([$sinTarifa->id], array_map(fn ($r) => $r->productId, $this->service()->viewFor($item->fresh())));
        $this->assertSame(0, $this->service()->reconcile($item, [$sinTarifa->id => 1], 'signed_link')->deltaCents);
        $this->assertNotNull($this->liveChild($item, $sinTarifa));

        // CONTROL: la misma, de pago y sin tarifa, no se puede ofrecer.
        ProductAddon::query()->where('addon_id', $sinTarifa->id)->update(['is_included' => false]);
        $this->assertSame([], $this->service()->viewFor($this->party()->fresh()));
    }

    // ── Fixture ─────────────────────────────────────────────────────────────────────────────────

    /** @param  array<string, string>|null  $title */
    private function group(string $key, bool $required = false, ?array $title = null): AddonChoiceGroup
    {
        return AddonChoiceGroup::create([
            'product_id' => $this->pack->id, 'key' => $key, 'title' => $title ?? ['es' => 'Merienda'],
            'is_required' => $required, 'position' => 0,
        ]);
    }

    /**
     * Una opción de la merienda: venta posterior, por niño e incluida, como la receta (`#914`).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function option(TicketType $addon, array $overrides = []): void
    {
        $this->pack->configurableAddons()->attach($addon->id, array_merge([
            'position' => 1, 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'is_included' => true,
            'stage' => ProductAddon::STAGE_POSTFORM, 'choice_group' => 'merienda',
        ], $overrides));
        $this->pack->refresh();
    }

    private function addon(string $name, ?int $cents): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 20,
        ]);
        if ($cents !== null) {
            $this->priceIt($addon, $cents);
        }

        return $addon;
    }

    private function priceIt(TicketType $type, int $cents): void
    {
        Price::updateOrCreate(
            ['priceable_type' => $type->getMorphClass(), 'priceable_id' => $type->id, 'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id')],
            ['amount_cents' => $cents, 'currency' => 'EUR'],
        );
    }

    /** Una fiesta pagada a 25,00 € por niño, dentro de diez días. */
    private function party(int $qty = 4): OrderItem
    {
        $slot = Slot::firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => now()->addDays(10)->toDateString(), 'start_time' => '11:00:00'],
            ['end_time' => '13:00:00', 'capacity' => 40, 'online_capacity' => 40],
        );
        $total = 2500 * $qty;
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-PG'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $total, 'currency' => 'EUR', 'provider' => 'redsys', 'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (500000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => $slot->id, 'quantity' => $qty,
            'unit_price' => 2500, 'seats' => $qty, 'event_data' => ['celebrant' => 'Mara'],
        ]);

        return Order::with(['items.children', 'items.slot', 'items.ticketType.addons', 'adjustments', 'payments.refunds'])
            ->findOrFail($order->id)->items->firstWhere('parent_item_id', null);
    }

    private function service(): PostFormAddons
    {
        return app(PostFormAddons::class);
    }

    private function liveChild(OrderItem $item, TicketType $addon): ?OrderItem
    {
        return $item->fresh(['children'])->children
            ->reject(fn (OrderItem $c): bool => $c->isCancelled())
            ->firstWhere('ticket_type_id', $addon->id);
    }

    private function assertBookCloses(OrderItem $item, string $label): void
    {
        $book = OrderBook::forOrder(Order::with(['items.children', 'items.slot', 'items.ticketType', 'adjustments', 'payments.refunds'])->findOrFail($item->order_id));

        $this->assertTrue($book->isConsistent, "{$label} · las cuatro identidades cierran");
        $this->assertSame($book->totalCents, $book->movementsSumCents(), "{$label} · I3");
        $this->assertNotSame(Balance::KIND_UNDER_REVIEW, $book->balance->kind, "{$label} · no queda «en revisión»");
    }
}
