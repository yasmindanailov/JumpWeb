<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\OrderItemModified;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sub-fase 7.2e.4 (decisión #170) — Modal "Gestionar producto" Tab 2:
 * gestión de COMPLEMENTOS (añadir, subir cantidad, quitar) integrada en el
 * handler unificado `executeItemEdit`.
 *
 * Modelo financiero (decisión #170, 2 preguntas a la clienta):
 *  - Añadir / subir cantidad → `applyExtraDue` (cobro en puerta, sin Redsys),
 *    MOVIMIENTO SEPARADO del diff de Tab 1 (no se netea).
 *  - Quitar (cantidad 0) → solo `markCancelled` (SIN auto-refund; el operador
 *    reembolsa aparte con `refundItem`).
 *  - Reducción parcial a un valor intermedio → bloqueada (sin refund automático
 *    no hay forma de representarla sin corromper el importe pagado).
 *  - Complementos NEUTROS al aforo (`seats=0`/`slot_id=null`).
 *  - Huérfanos de un cambio de producto dejan de bloquear si se quitan en el
 *    mismo guardado (orphan-resolution).
 */
class ManageItemAddonsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $jump;

    private TicketType $entryA;        // Jump 1h, 12.00 €

    private TicketType $entryPricier;  // Jump 2h, 20.00 € (no admite addonSocks → huérfano)

    private TicketType $addonSocks;    // 2.00 €, pivote de entryA (presente como child)

    private TicketType $addonDrink;    // 3.00 €, pivote de entryA (disponible para añadir)

    private TicketType $addonOther;    // 5.00 €, NO pivote de entryA (incompatible)

    private TicketType $addonNoPrice;  // pivote de entryA, sin tarifa de catálogo

    private string $day;

    private int $counter = 0;

    private int $paymentCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create([
            'key' => RateType::KEY_NORMAL,
            'label' => ['es' => 'Normal'],
            'weekdays' => null,
            'priority' => 0,
        ]);

        $this->jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);

        $this->entryA = $this->makeEntry('Jump 1h', 60, 1200);
        $this->entryPricier = $this->makeEntry('Jump 2h', 120, 2000);

        $this->addonSocks = $this->makeAddon('Calcetines', 200);
        $this->addonDrink = $this->makeAddon('Bebida', 300);
        $this->addonOther = $this->makeAddon('Otro', 500);
        $this->addonNoPrice = $this->makeAddon('Sin precio', null);

        // addonSocks/Drink/NoPrice pertenecen a entryA; addonOther NO.
        // entryPricier NO admite addonSocks → al cambiar entryA→entryPricier,
        // un child addonSocks queda huérfano.
        $this->entryA->addons()->sync([$this->addonSocks->id, $this->addonDrink->id, $this->addonNoPrice->id]);
        $this->entryPricier->addons()->sync([$this->addonDrink->id]);

        $this->day = Carbon::today()->addDays(7)->toDateString();

        foreach (['10:00:00', '11:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->jump->id,
                'date' => $this->day,
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 10,
                'online_capacity' => 10,
                'online_sales_open' => true,
                'status' => Slot::STATUS_OPEN,
            ]);
        }
    }

    // ─── Añadir complemento ──────────────────────────────────────────────

    public function test_add_addon_applies_extra_due_and_creates_capacity_neutral_child(): void
    {
        Notification::fake();
        [$order, $item] = $this->paidEntryOrderWithSocks();

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['addon_adds' => [['ticket_type_id' => $this->addonDrink->id, 'quantity' => 2]]]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $child = $item->children()->where('ticket_type_id', $this->addonDrink->id)->first();
        $this->assertNotNull($child);
        $this->assertSame(2, (int) $child->quantity);
        $this->assertSame(300, (int) $child->unit_price);
        $this->assertSame(0, (int) $child->seats);          // neutro al aforo
        $this->assertNull($child->slot_id);
        $this->assertSame($item->id, (int) $child->parent_item_id);

        // Cobro en puerta = 2 × 3.00 = 6.00; sin Redsys. El ajuste se ata al CHILD
        // (el complemento), no al principal, para poder anularlo si se cancela luego.
        $adj = OrderAdjustment::where('order_item_id', $child->id)
            ->where('type', OrderAdjustment::TYPE_EXTRA_DUE)->first();
        $this->assertNotNull($adj);
        $this->assertSame(600, (int) $adj->amount_cents);
        $this->assertSame(0, PaymentRefund::count());

        $this->assertSame(1, AuditLog::where('action', 'orders.item_edited')->count());
        Notification::assertSentTo($order->user, OrderItemModified::class,
            fn (OrderItemModified $n): bool => $n->extraDueCents === 600 && ! empty($n->changes['addon_change']));
    }

    public function test_adding_an_included_addon_via_panel_respects_free_quantity(): void
    {
        Notification::fake();

        // Tarta INCLUIDA (1.ª gratis) enganchada a entryA con precio para los extras.
        $cake = $this->makeAddon('Tarta', 1500);
        $this->entryA->configurableAddons()->attach($cake->id, [
            'is_included' => true, 'included_quantity' => 1, 'is_mandatory' => false,
            'quantity_mode' => 'fixed', 'allow_extra' => true, 'position' => 9,
        ]);

        [$order, $item] = $this->paidEntryOrderWithSocks();

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['addon_adds' => [['ticket_type_id' => $cake->id, 'quantity' => 2]]]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $child = $item->children()->where('ticket_type_id', $cake->id)->first();
        $this->assertNotNull($child);
        $this->assertSame(2, (int) $child->quantity);
        $this->assertSame(1, (int) $child->free_quantity);       // 1.ª unidad incluida gratis
        $this->assertSame(1500, $child->chargedSubtotalCents());  // solo 1 unidad de pago × 1500

        // El cobro en puerta refleja SOLO la unidad de pago, no las 2. Ajuste atado al child.
        $adj = OrderAdjustment::where('order_item_id', $child->id)
            ->where('type', OrderAdjustment::TYPE_EXTRA_DUE)->latest('id')->first();
        $this->assertNotNull($adj);
        $this->assertSame(1500, (int) $adj->amount_cents);
    }

    public function test_mandatory_included_addon_cannot_be_removed(): void
    {
        $cake = $this->makeAddon('Tarta', 1500);
        $this->entryA->configurableAddons()->attach($cake->id, [
            'is_included' => true, 'is_mandatory' => true, 'included_quantity' => 1,
            'quantity_mode' => 'fixed', 'allow_extra' => true, 'position' => 9,
        ]);

        [$order, $item] = $this->paidEntryOrderWithSocks();
        $cakeChild = $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $cake->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 1, 'unit_price' => 1500, 'seats' => 0,
        ]);
        $item->load('children.ticketType');

        // Poner a 0 (quitar) un incluido obligatorio se bloquea (mismas condiciones que la web).
        $reason = $this->invokeValidateAddon($item, $this->entryA, [['child_id' => $cakeChild->id, 'quantity' => 0]], []);
        $this->assertSame('addon_locked', $reason);
    }

    /**
     * La regla del BLOQUEO, no la del mínimo: un complemento PER-INVITADO o miembro de un GRUPO de
     * elección no admite que el operador le cambie la cantidad — la fija el nº de invitados o la
     * elección de menú, y se cambia con «elige menú». Ganó su test en la extracción 4b: la mutación
     * «nada bloqueado» (`childAddonMeta` con `locked = false`) salía VERDE en los 591 tests de la
     * carpeta, porque `addon_locked` solo se aseveraba por la rama del mínimo (el test de arriba).
     */
    public function test_per_guest_and_group_addons_reject_quantity_edits_as_locked(): void
    {
        $water = $this->makeAddon('Agua', 200);
        $this->entryA->configurableAddons()->attach($water->id, ['quantity_mode' => 'per_guest', 'position' => 12]);
        $menu = $this->makeAddon('Menú', 800);
        $this->entryA->configurableAddons()->attach($menu->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 13]);

        [$order, $item] = $this->paidEntryOrderWithSocks();
        $guests = (int) $item->quantity;
        $waterChild = $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $water->id,
            'slot_id' => null, 'quantity' => $guests, 'free_quantity' => 0, 'unit_price' => 200, 'seats' => 0,
        ]);
        $menuChild = $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $menu->id,
            'slot_id' => null, 'quantity' => $guests, 'free_quantity' => 0, 'unit_price' => 800, 'seats' => 0,
        ]);
        $item->load('children.ticketType');

        // SUBIR la cantidad (no bajarla: así no cae en el mínimo ni en la reducción parcial) se
        // bloquea por el BLOQUEO, tanto en el per-invitado suelto como en el miembro de grupo.
        $this->assertSame('addon_locked', $this->invokeValidateAddon(
            $item, $this->entryA, [['child_id' => $waterChild->id, 'quantity' => $guests + 1]], [],
        ));
        $this->assertSame('addon_locked', $this->invokeValidateAddon(
            $item, $this->entryA, [['child_id' => $menuChild->id, 'quantity' => $guests + 1]], [],
        ));

        // Control negativo: dejar la cantidad como está no es un cambio y pasa.
        $this->assertNull($this->invokeValidateAddon(
            $item, $this->entryA, [['child_id' => $waterChild->id, 'quantity' => $guests]], [],
        ));
    }

    public function test_two_group_members_in_one_batch_are_rejected(): void
    {
        $menu1 = $this->makeAddon('Menú 1', 0);
        $menu2 = $this->makeAddon('Menú 2', 800);
        $this->entryA->configurableAddons()->attach($menu1->id, ['is_included' => true, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 10]);
        $this->entryA->configurableAddons()->attach($menu2->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 11]);

        [, $item] = $this->paidEntryOrderWithSocks();
        $item->load('children.ticketType');

        $reason = $this->invokeValidateAddon($item, $this->entryA, [], [
            ['ticket_type_id' => $menu1->id, 'quantity' => 1],
            ['ticket_type_id' => $menu2->id, 'quantity' => 1],
        ]);
        $this->assertSame('addon_group_conflict', $reason);
    }

    public function test_switching_a_group_member_replaces_the_present_one(): void
    {
        Notification::fake();
        $menu1 = $this->makeAddon('Menú 1', 0);
        $menu2 = $this->makeAddon('Menú 2', 800);
        $this->entryA->configurableAddons()->attach($menu1->id, ['is_included' => true, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 10]);
        $this->entryA->configurableAddons()->attach($menu2->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 11]);

        [$order, $item] = $this->paidEntryOrderWithSocks();
        $m1Child = $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $menu1->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 1, 'unit_price' => 0, 'seats' => 0,
        ]);
        $item->load('children.ticketType');

        // ②b: el cambio de menú se hace con el RADIO del grupo (los miembros de grupo ya no se
        // ofrecen en el selector «Añadir»). Elegir el Menú 2 (de pago) SUSTITUYE al Menú 1 (incluido).
        $groupField = 'group_choice_'.md5('menu');
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, [$groupField => $menu2->id]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $this->assertTrue($m1Child->fresh()->isCancelled());        // Menú 1 sustituido
        $m2 = $item->children()->where('ticket_type_id', $menu2->id)->whereNull('cancelled_at')->first();
        $this->assertNotNull($m2);
        $this->assertSame(1, (int) $m2->quantity);                   // uno por invitado (1)
        $this->assertSame(0, (int) $m2->free_quantity);
        $this->assertSame(800, $m2->chargedSubtotalCents());         // 1 × 800
    }

    public function test_radio_group_choice_replaces_the_present_member(): void
    {
        // ②b: el control Radio del modal Gestionar materializa el cambio de menú vía el MISMO
        // group-replacement que el flujo de «añadir» (sin tocar la lógica de conteo). Elegir Menú 2
        // en el radio sustituye al Menú 1 presente.
        Notification::fake();
        $menu1 = $this->makeAddon('Menú 1', 0);
        $menu2 = $this->makeAddon('Menú 2', 800);
        $this->entryA->configurableAddons()->attach($menu1->id, ['is_included' => true, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 10]);
        $this->entryA->configurableAddons()->attach($menu2->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 11]);

        [$order, $item] = $this->paidEntryOrderWithSocks();
        $m1Child = $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $menu1->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 1, 'unit_price' => 0, 'seats' => 0,
        ]);
        $item->load('children.ticketType');

        $groupField = 'group_choice_'.md5('menu');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, [$groupField => $menu2->id]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $this->assertTrue($m1Child->fresh()->isCancelled());          // Menú 1 sustituido
        $m2 = $item->children()->where('ticket_type_id', $menu2->id)->whereNull('cancelled_at')->first();
        $this->assertNotNull($m2);
        $this->assertSame(800, $m2->chargedSubtotalCents());          // 1 × 800
    }

    public function test_radio_group_choice_unchanged_is_a_noop(): void
    {
        // ②b: mantener el menú actual en el radio NO duplica ni cancela nada (chosen == present).
        Notification::fake();
        $menu1 = $this->makeAddon('Menú 1', 0);
        $menu2 = $this->makeAddon('Menú 2', 800);
        $this->entryA->configurableAddons()->attach($menu1->id, ['is_included' => true, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 10]);
        $this->entryA->configurableAddons()->attach($menu2->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 11]);

        [$order, $item] = $this->paidEntryOrderWithSocks();
        $m1Child = $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $menu1->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 1, 'unit_price' => 0, 'seats' => 0,
        ]);
        $item->load('children.ticketType');

        $groupField = 'group_choice_'.md5('menu');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, [$groupField => $menu1->id]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $this->assertFalse($m1Child->fresh()->isCancelled());         // Menú 1 sigue presente
        $this->assertNull($item->children()->where('ticket_type_id', $menu2->id)->whereNull('cancelled_at')->first()); // Menú 2 no se añadió
    }

    public function test_group_swap_blocks_when_a_dependent_requires_the_replaced_member(): void
    {
        // Defensa en profundidad (②b + ①): si un complemento fijo «requiere» el menú PRESENTE y se
        // cambia de menú, el guardado cancelaría ese menú → el dependiente quedaría huérfano. El guard
        // de validateAddonEdits lo BLOQUEA (espeja la poda de la autoridad pública AddonResolver).
        $menu1 = $this->makeAddon('Menú 1', 0);
        $menu2 = $this->makeAddon('Menú 2', 800);
        $dep = $this->makeAddon('Depende del Menú 1', 300);
        $this->entryA->configurableAddons()->attach($menu1->id, ['is_included' => true, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 10]);
        $this->entryA->configurableAddons()->attach($menu2->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 11]);
        // Config «patológica» (que el panel ya no permite crear, pero blindamos en runtime).
        $this->entryA->configurableAddons()->attach($dep->id, ['quantity_mode' => 'fixed', 'requires_addon_id' => $menu1->id, 'position' => 12]);

        [$order, $item] = $this->paidEntryOrderWithSocks();
        $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $menu1->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 1, 'unit_price' => 0, 'seats' => 0,
        ]);
        $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $dep->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 0, 'unit_price' => 300, 'seats' => 0,
        ]);
        $item->load('children.ticketType');

        // El cambio de menú (materializado como add de Menú 2) dejaría a «dep» sin su requisito → bloqueado.
        $reason = $this->invokeValidateAddon($item, $this->entryA, [], [
            ['ticket_type_id' => $menu2->id, 'quantity' => 1],
        ]);
        $this->assertSame('addon_requires_missing', $reason);
    }

    public function test_modal_prefills_the_group_radio_with_the_present_member(): void
    {
        // ②b (fix): al abrir el modal, el Radio del grupo arranca MARCADO en el miembro que el cliente
        // eligió al comprar (aquí Menú 2, el de pago), no en el default ni vacío.
        $menu1 = $this->makeAddon('Menú 1', 0);
        $menu2 = $this->makeAddon('Menú 2', 800);
        $this->entryA->configurableAddons()->attach($menu1->id, ['is_included' => true, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 10]);
        $this->entryA->configurableAddons()->attach($menu2->id, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 11]);

        [$order, $item] = $this->paidEntryOrderWithSocks();
        $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $menu2->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 0, 'unit_price' => 800, 'seats' => 0,
        ]);
        $item->load('children.ticketType');

        $groupField = 'group_choice_'.md5('menu');

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('manageItem', ['item' => $item->id])
            ->assertActionMounted('manageItem')
            ->assertSchemaStateSet([$groupField => $menu2->id]);
    }

    /**
     * Reproducción del bug crítico reportado: cambiar de menú IDA Y VUELTA entre un
     * complemento GRATIS (incluido) y uno DE PAGO del mismo grupo debe NETEAR a cero.
     *
     * El complemento de pago se añadió por `extra_due` (cobro PENDIENTE en puerta, NO
     * cobrado online); al sustituirlo en el siguiente cambio de menú su cargo debe
     * ANULARSE — ni queda "a cobrar" ni genera "pendiente de reembolso" (nunca se cobró).
     * Antes: el `extra_due` se ataba al PRINCIPAL (no al complemento), así que cancelar
     * el complemento no lo anulaba → "a cobrar" fantasma + total inflado + reembolso
     * pendiente espurio (el mismo importe contado como cargo Y como reembolso).
     */
    public function test_menu_swap_back_and_forth_nets_to_zero(): void
    {
        Notification::fake();
        $menuFree = $this->makeAddon('Menú 1', 0);
        $menuPaid = $this->makeAddon('Menú 2', 800);
        $this->entryA->configurableAddons()->attach($menuFree->id, ['is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'fixed', 'choice_group' => 'menu', 'position' => 10]);
        $this->entryA->configurableAddons()->attach($menuPaid->id, ['quantity_mode' => 'fixed', 'choice_group' => 'menu', 'position' => 11]);

        // Pedido pagado con el Menú 1 (gratis) presente. Total cobrado online = solo la entrada.
        [$order, $item] = $this->paidEntryOrderWithSocks();
        $order->items()->where('ticket_type_id', $this->addonSocks->id)->delete();  // dejamos solo entrada + menú
        $order->forceFill(['subtotal' => 1200, 'total' => 1200])->save();
        $order->payments()->update(['amount' => 1200]);
        $freeChild = $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $menuFree->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 1, 'unit_price' => 0, 'seats' => 0,
        ]);
        $item->load('children.ticketType');

        // ②b: cambios de menú vía el RADIO del grupo (group_choice_<md5(grupo)>).
        $groupField = 'group_choice_'.md5('menu');

        // Paso 1: cambiar a Menú 2 (de pago) → sustituye al Menú 1 (gratis); +8,00 € a cobrar.
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item->fresh(['slot', 'children.ticketType']), [$groupField => $menuPaid->id]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        // Paso 2: volver al Menú 1 (gratis) → sustituye al Menú 2 (de pago). Debe NETEAR a cero.
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item->fresh(['slot', 'children.ticketType']), [$groupField => $menuFree->id]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $order->refresh()->load(['adjustments.orderItem', 'items', 'payments.refunds']);
        $s = $order->financialSummary();

        // El cargo del Menú 2 (cancelado) queda ANULADO: ni pendiente ni en el total con cambios.
        $this->assertSame(0, $s->pendingAtGate(), 'No debe quedar nada "a cobrar" tras netear el cambio de menú.');
        $this->assertSame(1200, $s->totalWithChanges(), 'El total con cambios vuelve al original (1200).');
        $this->assertSame(0, $s->extraDue, 'El extra_due del complemento cancelado se anula.');

        // Nunca se cobró online → nada que reembolsar.
        $this->assertSame(0, PaymentRefund::count());

        // Solo queda activo un Menú 1 (el reañadido); los demás cancelados.
        $activeMenu = $item->children()->whereNull('cancelled_at')->whereIn('ticket_type_id', [$menuFree->id, $menuPaid->id])->get();
        $this->assertCount(1, $activeMenu);
        $this->assertSame($menuFree->id, (int) $activeMenu->first()->ticket_type_id);

        // Invariantes del DESGLOSE (la otra mitad del bug): el Menú 2 (de pago) cancelado
        // nunca se cobró online → cobrado 0 → ni reembolso pendiente ni cuenta en el total,
        // y NO es refundable (el modal de reembolso no lo ofrece → sin dinero fantasma).
        $paidChild = $item->children()->where('ticket_type_id', $menuPaid->id)->whereNotNull('cancelled_at')->first();
        $this->assertNotNull($paidChild);
        $this->assertSame(800, $order->itemExtraDueCents($paidChild));   // su cargo se atÓ a él
        $this->assertSame(0, $order->itemCollectedCents($paidChild));    // pero nunca se cobró → 0
        $this->assertSame(0, $order->itemRefundableRemainderCents($paidChild)); // no refundable
        $this->assertTrue($order->isVoidedLeftoverItem($paidChild));     // se oculta del desglose
    }

    /**
     * Escenario pedido por la clienta: el cliente PAGA online el complemento DE PAGO de un
     * grupo (elegido en la compra), luego el empleado lo cambia por el GRATIS del mismo grupo
     * → hay que DEVOLVERLE lo pagado. El complemento cancelado debe quedar como reembolso
     * PENDIENTE y ser refundable por su importe (no se oculta, sí se cobró online).
     */
    public function test_paid_group_addon_swapped_to_free_is_refundable(): void
    {
        Notification::fake();
        $menuFree = $this->makeAddon('Menú 1', 0);
        $menuPaid = $this->makeAddon('Menú 2', 800);
        $this->entryA->configurableAddons()->attach($menuFree->id, ['is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'fixed', 'choice_group' => 'menu', 'position' => 10]);
        $this->entryA->configurableAddons()->attach($menuPaid->id, ['quantity_mode' => 'fixed', 'choice_group' => 'menu', 'position' => 11]);

        // Pedido PAGADO con el Menú 2 (de pago) elegido en la COMPRA → cobrado online (en el total).
        [$order, $item] = $this->paidEntryOrderWithSocks();
        $order->items()->where('ticket_type_id', $this->addonSocks->id)->delete();
        $order->forceFill(['subtotal' => 2000, 'total' => 2000])->save();   // 1200 entrada + 800 menú 2
        $order->payments()->update(['amount' => 2000]);
        $paidChild = $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $menuPaid->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 0, 'unit_price' => 800, 'seats' => 0,
        ]);
        $item->load('children.ticketType');

        // El empleado cambia al Menú 1 (gratis) con el RADIO → cancela el Menú 2 (pagado).
        $groupField = 'group_choice_'.md5('menu');
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item->fresh(['slot', 'children.ticketType']), [$groupField => $menuFree->id]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $order->refresh()->load(['items.children.ticketType', 'adjustments', 'payments.refunds']);
        $paidChild->refresh();

        $this->assertTrue($paidChild->isCancelled());
        // SÍ se cobró online → refundable por 800, NO oculto, y aparece como reembolso pendiente.
        $this->assertSame(800, $order->itemCollectedCents($paidChild));
        $this->assertSame(800, $order->itemRefundableRemainderCents($paidChild));
        $this->assertFalse($order->isVoidedLeftoverItem($paidChild));
        // No se añadió nada de pago (el menú gratis es 0) → nada que cobrar en puerta.
        $this->assertSame(0, $order->financialSummary()->pendingAtGate());
    }

    // Nota: los bloqueos por complemento INCOMPATIBLE con el producto y por
    // complemento DUPLICADO se cubren en `test_validate_addon_edits_reasons`
    // (reflexión sobre el validador puro `validateAddonEdits`). No se prueban
    // vía `callAction` porque el `Select` de Filament rechaza opciones fuera de
    // su lista (`addableAddonsFor` ya las excluye), así que el handler no llega
    // a ejecutarse — misma estrategia que 7.2e.3 con cross-type/cross-zone.

    public function test_add_addon_without_catalog_price_blocked(): void
    {
        Notification::fake();
        [$order, $item] = $this->paidEntryOrderWithSocks();

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['addon_adds' => [['ticket_type_id' => $this->addonNoPrice->id, 'quantity' => 1]]]),
                arguments: ['item' => $item->id],
            );

        $this->assertNull($item->children()->where('ticket_type_id', $this->addonNoPrice->id)->first());
        $this->assertNotNull(AuditLog::where('action', 'orders.item_edit_blocked')
            ->where('payload->reason', 'addon_unavailable_on_date')->first());
    }

    // ─── Subir cantidad ──────────────────────────────────────────────────

    public function test_increase_addon_quantity_applies_extra_due(): void
    {
        Notification::fake();
        [$order, $item, $socks] = $this->paidEntryOrderWithSocks(socksQty: 1);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['addon_edits' => [['child_id' => $socks->id, 'quantity' => 3]]]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $socks->refresh();
        $this->assertSame(3, (int) $socks->quantity);
        $this->assertNull($socks->cancelled_at);

        // Ajuste atado al CHILD (socks), no al principal.
        $adj = OrderAdjustment::where('order_item_id', $socks->id)
            ->where('type', OrderAdjustment::TYPE_EXTRA_DUE)->first();
        $this->assertNotNull($adj);
        $this->assertSame(400, (int) $adj->amount_cents); // 2 extra × 2.00
        $this->assertSame(0, PaymentRefund::count());
    }

    public function test_raise_included_addon_without_extra_is_blocked(): void
    {
        // P4 (auditoría Fase 1): un complemento INCLUIDO SIN extras (is_included && !allow_extra) NO
        // admite subir la cantidad por encima de lo incluido — la compra pública lo capa en
        // AddonResolver::effectiveQuantity; el edit del panel debe heredar el MISMO tope (antes cobraba
        // extra_due de unidades que la config declara no vendibles).
        $cake = $this->makeAddon('Tarta', 1500);
        $this->entryA->configurableAddons()->attach($cake->id, [
            'is_included' => true, 'included_quantity' => 1, 'is_mandatory' => false,
            'quantity_mode' => 'fixed', 'allow_extra' => false, 'position' => 9,
        ]);

        [$order, $item] = $this->paidEntryOrderWithSocks();
        $cakeChild = $item->children()->create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $cake->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 1, 'unit_price' => 1500, 'seats' => 0,
        ]);
        $item->load('children.ticketType');

        // Subir a 4 (>1 incluido, sin extras) → bloqueado con la razón nueva.
        $blocked = $this->invokeValidateAddon($item, $this->entryA, [['child_id' => $cakeChild->id, 'quantity' => 4]], []);
        $this->assertSame('addon_no_extra', $blocked);

        // Mantener la cantidad incluida (1) sí es válido.
        $ok = $this->invokeValidateAddon($item, $this->entryA, [['child_id' => $cakeChild->id, 'quantity' => 1]], []);
        $this->assertNull($ok);
    }

    // ─── Quitar (cantidad 0) → soft-cancel, SIN auto-refund ──────────────

    public function test_remove_addon_soft_cancels_without_refund(): void
    {
        Notification::fake();
        [$order, $item, $socks] = $this->paidEntryOrderWithSocks(socksQty: 1);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['addon_edits' => [['child_id' => $socks->id, 'quantity' => 0]]]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $socks->refresh();
        $this->assertNotNull($socks->cancelled_at);           // soft-cancel
        $this->assertSame(1, (int) $socks->quantity);         // cantidad/precio intactos (importe pagado preservado)

        // Decisión #170: NADA de dinero automático al quitar.
        $this->assertSame(0, PaymentRefund::count());
        $this->assertSame(0, OrderAdjustment::count());

        $order->refresh();
        $this->assertNull($order->refunded_at);                // sigue sin reembolso real

        $this->assertSame(1, AuditLog::where('action', 'orders.item_edited')->count());
        // (T5 §25.5: el `refundedCents` que este cierre comprobaba se RETIRÓ — iba cableado a null.)
        Notification::assertSentTo($order->user, OrderItemModified::class,
            fn (OrderItemModified $n): bool => $n->extraDueCents === null
                && ! empty($n->changes['addon_change']['removed']));
    }

    public function test_partial_reduce_addon_blocked(): void
    {
        Notification::fake();
        [$order, $item, $socks] = $this->paidEntryOrderWithSocks(socksQty: 3);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['addon_edits' => [['child_id' => $socks->id, 'quantity' => 1]]]),
                arguments: ['item' => $item->id],
            );

        $socks->refresh();
        $this->assertSame(3, (int) $socks->quantity);          // intacto
        $this->assertNull($socks->cancelled_at);
        $this->assertNotNull(AuditLog::where('action', 'orders.item_edit_blocked')
            ->where('payload->reason', 'addon_partial_reduce_unsupported')->first());
    }

    public function test_edit_addon_not_in_parent_blocked(): void
    {
        Notification::fake();
        [$order, $item] = $this->paidEntryOrderWithSocks();
        // Un child de OTRO pedido.
        [, $otherItem, $otherSocks] = $this->paidEntryOrderWithSocks();

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['addon_edits' => [['child_id' => $otherSocks->id, 'quantity' => 0]]]),
                arguments: ['item' => $item->id],
            );

        $otherSocks->refresh();
        $this->assertNull($otherSocks->cancelled_at);          // intacto (IDOR cross-item)
        $this->assertNotNull(AuditLog::where('action', 'orders.item_edit_blocked')
            ->where('payload->reason', 'addon_not_in_parent')->first());
    }

    // ─── Orphan-resolution (deuda heredada de 7.2e.3) ────────────────────

    public function test_product_change_with_orphan_removed_in_same_save_succeeds(): void
    {
        Notification::fake();
        [$order, $item, $socks] = $this->paidEntryOrderWithSocks(socksQty: 1);

        // Cambiar entryA → entryPricier ORPHANaría addonSocks, PERO lo quitamos
        // en el mismo guardado (cantidad 0) → debe pasar.
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, [
                    'product_id' => $this->entryPricier->id,
                    'addon_edits' => [['child_id' => $socks->id, 'quantity' => 0]],
                ]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $item->refresh();
        $socks->refresh();
        $this->assertSame($this->entryPricier->id, (int) $item->ticket_type_id);  // producto cambiado
        $this->assertNotNull($socks->cancelled_at);                                // huérfano quitado
    }

    public function test_product_change_with_orphan_kept_still_blocks(): void
    {
        Notification::fake();
        [$order, $item] = $this->paidEntryOrderWithSocks(socksQty: 1);

        // Cambiar producto SIN quitar el huérfano → sigue bloqueando (regresión 7.2e.3).
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['product_id' => $this->entryPricier->id]),
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame($this->entryA->id, (int) $item->ticket_type_id);  // sin cambio
        $this->assertNotNull(AuditLog::where('action', 'orders.item_edit_blocked')
            ->where('payload->reason', 'orphan_addons')->first());
    }

    // ─── Router + defense in depth ───────────────────────────────────────

    public function test_addon_only_change_is_processed_by_router(): void
    {
        Notification::fake();
        [$order, $item] = $this->paidEntryOrderWithSocks();

        // Sin cambio de producto/cantidad/slot: solo añadir un complemento.
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['addon_adds' => [['ticket_type_id' => $this->addonDrink->id, 'quantity' => 1]]]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $this->assertNotNull($item->children()->where('ticket_type_id', $this->addonDrink->id)->first());
    }

    public function test_stale_optimistic_token_blocks_addon_changes(): void
    {
        Notification::fake();
        [$order, $item] = $this->paidEntryOrderWithSocks();

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, [
                    'optimistic_token' => 'stale-123',
                    'addon_adds' => [['ticket_type_id' => $this->addonDrink->id, 'quantity' => 1]],
                ]),
                arguments: ['item' => $item->id],
            );

        $this->assertNull($item->children()->where('ticket_type_id', $this->addonDrink->id)->first());
        $this->assertNotNull(AuditLog::where('action', 'orders.item_edit_blocked')
            ->where('payload->reason', 'stale_item_version')->first());
    }

    public function test_without_permission_addon_changes_blocked(): void
    {
        Notification::fake();
        [$order, $item] = $this->paidEntryOrderWithSocks();

        Livewire::actingAs($this->staffWithoutEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['addon_adds' => [['ticket_type_id' => $this->addonDrink->id, 'quantity' => 1]]]),
                arguments: ['item' => $item->id],
            );

        $this->assertNull($item->children()->where('ticket_type_id', $this->addonDrink->id)->first());
    }

    public function test_combined_addon_changes_produce_single_audit_and_email(): void
    {
        Notification::fake();
        [$order, $item, $socks] = $this->paidEntryOrderWithSocks(socksQty: 1);

        // Añadir bebida (×1 = 3.00) + subir calcetines (1→2 = +2.00) en un guardado.
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, [
                    'addon_adds' => [['ticket_type_id' => $this->addonDrink->id, 'quantity' => 1]],
                    'addon_edits' => [['child_id' => $socks->id, 'quantity' => 2]],
                ]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $this->assertSame(1, AuditLog::where('action', 'orders.item_edited')->count());
        // Un solo audit + un solo email, PERO un apunte de cobro POR complemento (atado a su
        // child): bebida añadida (3.00) + calcetines subidos (2.00) = 5.00 en total.
        $adjs = OrderAdjustment::where('type', OrderAdjustment::TYPE_EXTRA_DUE)->get();
        $this->assertCount(2, $adjs);
        $this->assertSame(500, (int) $adjs->sum('amount_cents'));
        $drinkChild = $item->children()->where('ticket_type_id', $this->addonDrink->id)->first();
        $this->assertSame(300, (int) OrderAdjustment::where('order_item_id', $drinkChild->id)->sum('amount_cents'));
        $this->assertSame(200, (int) OrderAdjustment::where('order_item_id', $socks->id)->sum('amount_cents'));
        Notification::assertSentTimes(OrderItemModified::class, 1);
    }

    // ─── Helpers puros (reflexión) ───────────────────────────────────────

    public function test_validate_addon_edits_reasons(): void
    {
        [, $item, $socks] = $this->paidEntryOrderWithSocks(socksQty: 3);
        $item->load('children.ticketType');

        // OK: añadir compatible + subir existente.
        $this->assertNull($this->invokeValidateAddon($item, $this->entryA,
            [['child_id' => $socks->id, 'quantity' => 5]],
            [['ticket_type_id' => $this->addonDrink->id, 'quantity' => 1]],
        ));
        // Incompatible con el producto.
        $this->assertSame('addon_incompatible_with_product', $this->invokeValidateAddon($item, $this->entryA,
            [], [['ticket_type_id' => $this->addonOther->id, 'quantity' => 1]]));
        // Duplicado de uno ya presente.
        $this->assertSame('addon_already_added', $this->invokeValidateAddon($item, $this->entryA,
            [], [['ticket_type_id' => $this->addonSocks->id, 'quantity' => 1]]));
        // Cantidad de add inválida.
        $this->assertSame('addon_quantity_invalid', $this->invokeValidateAddon($item, $this->entryA,
            [], [['ticket_type_id' => $this->addonDrink->id, 'quantity' => 0]]));
        // Reducción parcial no soportada.
        $this->assertSame('addon_partial_reduce_unsupported', $this->invokeValidateAddon($item, $this->entryA,
            [['child_id' => $socks->id, 'quantity' => 1]], []));
        // Child que no pertenece al item.
        $this->assertSame('addon_not_in_parent', $this->invokeValidateAddon($item, $this->entryA,
            [['child_id' => 999999, 'quantity' => 0]], []));
    }

    public function test_dependency_blocks_adding_a_dependent_addon_without_its_requirement(): void
    {
        // addonDrink REQUIERE addonSocks (dependencia «requiere» data-driven en el pivote de entryA).
        $this->entryA->configurableAddons()->updateExistingPivot($this->addonDrink->id, [
            'requires_addon_id' => $this->addonSocks->id,
        ]);

        // Item entryA SIN socks: añadir Bebida (requiere Calcetines, ausente) → bloqueado.
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-DEP01', 'status' => Order::STATUS_PAID,
            'subtotal' => 1200, 'total' => 1200, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->entryA->id,
            'slot_id' => $this->slotAt('10:00:00')->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1200,
        ]);
        $item = $item->fresh(['ticketType', 'slot', 'children.ticketType']);

        $this->assertSame('addon_requires_missing', $this->invokeValidateAddon($item, $this->entryA,
            [], [['ticket_type_id' => $this->addonDrink->id, 'quantity' => 1]]));

        // Añadir Calcetines + Bebida en la MISMA tanda → el requisito queda activo → OK.
        $this->assertNull($this->invokeValidateAddon($item, $this->entryA, [], [
            ['ticket_type_id' => $this->addonSocks->id, 'quantity' => 1],
            ['ticket_type_id' => $this->addonDrink->id, 'quantity' => 1],
        ]));
    }

    public function test_dependency_blocks_removing_a_requirement_a_dependent_still_needs(): void
    {
        [, $item, $socks] = $this->paidEntryOrderWithSocks();
        $this->entryA->configurableAddons()->updateExistingPivot($this->addonDrink->id, [
            'requires_addon_id' => $this->addonSocks->id,
        ]);
        // Bebida presente (requiere socks, presente).
        $drink = OrderItem::create([
            'order_id' => $item->order_id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $this->addonDrink->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 300,
        ]);
        $item = $item->fresh(['ticketType', 'slot', 'children.ticketType']);

        // Quitar Calcetines (qty 0) dejaría a Bebida huérfana → bloqueado.
        $this->assertSame('addon_requires_missing', $this->invokeValidateAddon($item, $this->entryA,
            [['child_id' => $socks->id, 'quantity' => 0], ['child_id' => $drink->id, 'quantity' => 1]], []));
    }

    public function test_per_guest_included_menu_does_not_block_adding_other_addons(): void
    {
        // BUG (clienta, pedido JJ-RGI1RR): un Menú INCLUIDO PER-INVITADO recibía max = unidades
        // incluidas (1), pero su cantidad real = nº de invitados → al añadir/cambiar CUALQUIER
        // complemento del pack, la validación veía «12 > 1» y lo rechazaba con `addon_no_extra`.
        // Un per-invitado no tiene «extras»: su cantidad es fija (= invitados), bloqueada.
        $menu1 = TicketType::create([
            'name' => ['es' => 'Menú 1 incluido'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 80,
        ]);
        $this->entryA->configurableAddons()->attach($menu1->id, [
            'is_included' => true, 'quantity_mode' => 'per_guest', 'allow_extra' => false,
            'choice_group' => 'menu', 'position' => 20,
        ]);

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-MENU01', 'status' => Order::STATUS_PAID,
            'subtotal' => 0, 'total' => 0, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->entryA->id,
            'slot_id' => $this->slotAt('10:00:00')->id,
            'quantity' => 12, 'seats' => 12, 'unit_price' => 1200,
        ]);
        $menuChild = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $menu1->id, 'slot_id' => null,
            'quantity' => 12, 'seats' => 0, 'unit_price' => 0, 'free_quantity' => 12,
        ]);
        $item = $item->fresh(['ticketType', 'slot', 'children.ticketType']);

        // Añadir un complemento, con el Menú incluido (per-invitado, 12) entre los edits existentes.
        $reason = $this->invokeValidateAddon($item, $this->entryA,
            [['child_id' => $menuChild->id, 'quantity' => 12]],
            [['ticket_type_id' => $this->addonDrink->id, 'quantity' => 1]],
        );

        $this->assertNull($reason, 'un Menú incluido per-invitado no debe bloquear añadir complementos');
    }

    public function test_addable_addons_excludes_present(): void
    {
        [, $item] = $this->paidEntryOrderWithSocks();
        $item->load('children.ticketType');

        $addable = $this->invokeAddableAddons($item);

        // addonDrink + addonNoPrice disponibles; addonSocks excluido (ya presente);
        // addonOther excluido (no es del pivote de entryA).
        $this->assertArrayHasKey($this->addonDrink->id, $addable);
        $this->assertArrayHasKey($this->addonNoPrice->id, $addable);
        $this->assertArrayNotHasKey($this->addonSocks->id, $addable);
        $this->assertArrayNotHasKey($this->addonOther->id, $addable);
    }

    // ─── Fábricas / helpers ──────────────────────────────────────────────

    private function makeEntry(string $name, int $duration, ?int $priceCents): TicketType
    {
        $type = TicketType::create([
            'name' => ['es' => $name],
            'zone_id' => $this->jump->id,
            'type' => TicketType::TYPE_ENTRY,
            'duration_min' => $duration,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => ++$this->counter,
        ]);
        if ($priceCents !== null) {
            $type->prices()->create([
                'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'),
                'amount_cents' => $priceCents,
            ]);
        }

        return $type;
    }

    private function makeAddon(string $name, ?int $priceCents): TicketType
    {
        $type = TicketType::create([
            'name' => ['es' => $name],
            'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => ++$this->counter,
        ]);
        if ($priceCents !== null) {
            $type->prices()->create([
                'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'),
                'amount_cents' => $priceCents,
            ]);
        }

        return $type;
    }

    /**
     * Pedido pagado con un item entryA + un child addonSocks.
     *
     * @return array{0: Order, 1: OrderItem, 2: OrderItem}
     */
    private function paidEntryOrderWithSocks(int $socksQty = 1): array
    {
        $entryTotal = 1200;
        $socksTotal = 200 * $socksQty;
        $total = $entryTotal + $socksTotal;

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-AD'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        $this->attachPaidPayment($order);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $this->entryA->id,
            'slot_id' => $this->slotAt('10:00:00')->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1200,
        ]);
        $socks = OrderItem::create([
            'order_id' => $order->id,
            'parent_item_id' => $item->id,
            'ticket_type_id' => $this->addonSocks->id,
            'slot_id' => null,
            'quantity' => $socksQty, 'seats' => 0, 'unit_price' => 200,
        ]);

        return [$order->fresh(), $item->fresh(['ticketType', 'slot', 'children.ticketType']), $socks->fresh()];
    }

    private function attachPaidPayment(Order $order): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'gateway_order' => str_pad((string) (++$this->paymentCounter + 300000), 10, '0', STR_PAD_LEFT),
        ]);
    }

    private function slotAt(string $time): Slot
    {
        return Slot::query()->where('zone_id', $this->jump->id)
            ->where('date', $this->day)->where('start_time', $time)->firstOrFail();
    }

    private function staffWithEdit(): User
    {
        return $this->staffWith(['orders.view', 'orders.edit_item', 'orders.edit_event_data']);
    }

    private function staffWithoutEdit(): User
    {
        return $this->staffWith(['orders.view']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function staffWith(array $permissions): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        return $u;
    }

    /**
     * Invoca el validador puro `validateAddonEdits`, que desde la extracción 4b es un método
     * PÚBLICO de `OrderItemEditor` (antes, privado de `ViewOrder` por reflexión).
     *
     * @param  array<int, array{child_id:int, quantity:int}>  $edits
     * @param  array<int, array{ticket_type_id:int, quantity:int}>  $adds
     */
    private function invokeValidateAddon(OrderItem $item, TicketType $newType, array $edits, array $adds): ?string
    {
        return app(OrderItemEditor::class)->validateAddonEdits($item, $newType, $edits, $adds);
    }

    /**
     * @return array<int, string>
     */
    private function invokeAddableAddons(OrderItem $item): array
    {
        $page = new ViewOrder;
        $ref = new \ReflectionMethod(ViewOrder::class, 'addableAddonsFor');
        $ref->setAccessible(true);

        return $ref->invoke($page, $item, null);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function editData(OrderItem $item, array $overrides = []): array
    {
        $slot = $item->slot;

        return array_merge([
            'optimistic_token' => (string) ($item->updated_at?->getTimestamp() ?? ''),
            'product_id' => (int) $item->ticket_type_id,
            'quantity' => (int) $item->quantity,
            'slot_date' => $slot?->date?->toDateString() ?? '',
            'slot_time' => $slot?->start_time ?? '',
            'event_data' => [],
            'addon_edits' => [],
            'addon_adds' => [],
        ], $overrides);
    }
}
