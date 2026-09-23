<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Filament\Pages\CreateManualOrderPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.3 (iteración 1) — página Filament "Crear pedido manual".
 * Cubre el gate de permiso, el cobro efectivo/datáfono (vía ManualOrderFulfiller) y el carrito.
 */
class CreateManualOrderPageTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin/crear-pedido';

    private Zone $zone;

    private TicketType $h1;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);

        $this->h1 = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->h1->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function customer(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    /** @return array<string,mixed> */
    private function cartLine(int $qty = 2): array
    {
        return [
            'ticket_type_id' => $this->h1->id,
            'date' => $this->date,
            'time' => '10:00:00',
            'qty' => $qty,
            'event_data' => [],
            'addons' => [],
            'label' => 'JUMP · Jump · 1 hora',
            'when' => Carbon::parse($this->date)->format('d/m/Y').' 10:00',
            'line_total_cents' => 1000 * $qty,
        ];
    }

    public function test_cart_shows_deposit_split_for_products_with_deposit(): void
    {
        // P5 (#225, display): una línea con señal expone el split «a cobrar ahora / en el parque»
        // (informativo para el empleado). NO cambia el cobro real, que lo decide el alta.
        $line = $this->cartLine(1);     // line_total_cents = 1000 (10,00 €)
        $line['deposit_cents'] = 300;   // señal 3,00 € → resto 7,00 € al parque
        $lineFull = $this->cartLine(1); // sin señal (se cobra el total)
        $lineFull['deposit_cents'] = null;

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('cart', [$line, $lineFull]);

        // Helpers: total = 2000; a cobrar ahora = señal(300) + total de la línea sin señal(1000).
        $this->assertTrue($component->instance()->cartHasDeposit());
        $this->assertSame(2000, $component->instance()->cartTotalCents());
        $this->assertSame(1300, $component->instance()->cartOnlineDueCents());

        // Render: bloque de split (el carrito se incluye siempre en la página).
        $component->assertSee(__('admin.orders.create_manual.pay_now_deposit'))
            ->assertSee(__('admin.orders.create_manual.pay_at_park'));
    }

    public function test_cart_has_no_deposit_split_without_deposit_products(): void
    {
        // Sin productos con señal: cartHasDeposit false, lo online = el total, sin bloque de split.
        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('cart', [$this->cartLine(2)]);

        $this->assertFalse($component->instance()->cartHasDeposit());
        $this->assertSame(2000, $component->instance()->cartOnlineDueCents());
        $component->assertDontSee(__('admin.orders.create_manual.pay_now_deposit'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(self::URL)->assertRedirect('/admin/login');
    }

    public function test_customer_cannot_access(): void
    {
        $this->actingAs($this->customer())->get(self::URL)->assertForbidden();
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $staff = $this->staff();
        $perm = Permission::where('name', 'orders.create_manual')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        $this->actingAs($staff)->get(self::URL)->assertForbidden();
    }

    public function test_staff_with_permission_can_access(): void
    {
        $this->actingAs($this->staff())->get(self::URL)->assertOk();
    }

    public function test_cash_order_is_created_and_charged(): void
    {
        Notification::fake();
        $customer = $this->customer();

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('data.payment_method', 'cash')
            ->set('data.source', 'counter')
            ->set('cart', [$this->cartLine(2)])
            ->call('create');

        $order = Order::where('user_id', $customer->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame('cash', $order->payments()->first()->provider);
        $this->assertSame(Payment::STATUS_PAID, $order->payments()->first()->status);
        $this->assertSame(2, Ticket::where('order_id', $order->id)->count());
    }

    public function test_create_with_empty_cart_creates_nothing(): void
    {
        Notification::fake();
        $customer = $this->customer();

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('data.payment_method', 'cash')
            ->set('data.source', 'counter')
            ->set('cart', [])
            ->call('create');

        $this->assertSame(0, Order::where('user_id', $customer->id)->count());
    }

    /**
     * **POR DÓNDE LLEGÓ EL PEDIDO** (`specs/analitica.md` §4.1, T1d): sin decirlo no se cobra —ni por el botón
     * ni por `create()` a pelo—, y con ello el pedido nace SELLADO con la fuente del operador, no con la cookie
     * de su navegador. Sin valor por defecto: una fuente preseleccionada contaría mal justo lo que mide.
     */
    public function test_a_manual_order_needs_its_source_and_is_sealed_with_it(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $staff = $this->staff();

        $page = Livewire::actingAs($staff)
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('data.payment_method', 'cash')
            ->set('cart', [$this->cartLine(2)]);

        $this->assertFalse($page->instance()->hasSource(), 'la fuente no puede venir preseleccionada');
        $page->assertActionDisabled('createOrder');
        $page->call('create');
        $this->assertSame(0, Order::where('user_id', $customer->id)->count(), 'sin fuente no se cobra');

        $page->set('data.source', 'phone')->assertActionEnabled('createOrder')->call('create');

        $order = Order::where('user_id', $customer->id)->sole();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame('panel', $order->attribution_channel);
        $this->assertSame('phone', $order->attribution_source);
        $this->assertSame('offline', $order->attribution_medium);
        $this->assertSame(['operator_id' => $staff->id], $order->attribution);

        // Y el siguiente pedido vuelve a preguntarlo: el desenlace limpia la fuente con el resto.
        $page->call('startAnotherOrder');
        $this->assertFalse($page->instance()->hasSource());
    }

    public function test_a_source_that_is_not_of_the_panel_never_reaches_the_seal(): void
    {
        Notification::fake();
        $customer = $this->customer();

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('data.payment_method', 'cash')
            ->set('data.source', 'fax')
            ->set('cart', [$this->cartLine(1)])
            ->call('create');

        $this->assertSame(0, Order::where('user_id', $customer->id)->count());
    }

    public function test_add_line_to_cart_builds_an_entry_and_resets_selection(): void
    {
        $customer = $this->customer();

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->call('pickProduct', $this->h1->id)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->set('data.sel_qty', 3)
            ->call('addLineToCart');

        $cart = $component->get('cart');
        $this->assertCount(1, $cart);
        $this->assertSame($this->h1->id, $cart[0]['ticket_type_id']);
        $this->assertSame(3, $cart[0]['qty']);
        $this->assertSame(3000, $cart[0]['line_total_cents']);

        // ⚠️ **La selección se reinicia ENTERA para la siguiente línea**, y hasta `#464` esto solo
        // aseveraba el producto: el arnés de mutación borró el olvido de la HORA y pasó en verde,
        // con el nombre del caso prometiendo justo lo contrario. Una línea que hereda la hora, la
        // cantidad o los complementos de la anterior es un pedido mal montado sin ningún error.
        foreach (['sel_product_id', 'sel_date', 'sel_time', 'sel_qty'] as $campo) {
            $component->assertSet('data.'.$campo, null);
        }
        $component->assertSet('data.sel_dependent_ids', []);
        $component->assertSet('data.event_data', []);
        $component->assertSet('selAddonQty', []);
    }

    public function test_changing_customer_empties_the_cart(): void
    {
        $a = $this->customer();
        $b = $this->customer();

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $a->id)
            ->set('cart', [$this->cartLine(2)]);

        $this->assertCount(1, $component->get('cart'));

        // ⚠️ `#462`: elegir cliente AVANZA, así que para cambiarlo hay que volver al paso 1 — que es
        // exactamente lo que hace el operador. Sin este `back`, el `set` cae sobre un campo OCULTO,
        // su `afterStateUpdated` no se llama y el carrito no se vacía: sería un defecto del arnés.
        $component->call('back');

        // Cambiar de cliente debe vaciar el carrito (nunca cobrar a B las líneas de A).
        $component->set('data.customer_id', $b->id);
        $this->assertCount(0, $component->get('cart'));
    }

    public function test_cannot_create_order_for_a_non_customer_user(): void
    {
        Notification::fake();
        $staffAsTarget = $this->staff(); // un usuario con rol staff, NO customer

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $staffAsTarget->id)
            ->set('data.payment_method', 'cash')
            ->set('data.source', 'counter')
            ->set('cart', [$this->cartLine(1)])
            ->call('create');

        // Defense in depth IDOR: no se crea pedido para un usuario sin rol customer.
        $this->assertSame(0, Order::where('user_id', $staffAsTarget->id)->count());
    }

    public function test_create_uses_a_filament_confirmation_modal_not_a_browser_confirm(): void
    {
        // Antes el botón final llevaba `wire:confirm` → diálogo del NAVEGADOR. Ahora es una Action de
        // Filament con `->requiresConfirmation()` → MODAL del panel. Verificamos: (1) la action existe;
        // (2) al montarla aparece el modal con su heading (si NO requiriese confirmación, montar
        // ejecutaría la acción directa, sin modal); (3) confirmar el modal crea el pedido.
        $customer = $this->customer();

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('data.payment_method', 'cash')
            ->set('data.source', 'counter')
            ->set('cart', [$this->cartLine(1)]);

        $component->assertActionExists('createOrder');

        // Montar la action NO crea el pedido todavía: requiere confirmación (modal). Si NO la
        // requiriese, montarla la ejecutaría y crearía el pedido aquí mismo → la aserción cazaría
        // una regresión a confirmación implícita o a un botón directo.
        $component->mountAction('createOrder');
        $this->assertSame(0, Order::where('user_id', $customer->id)->count());

        // Confirmar el modal SÍ crea el pedido (la lógica sigue en create()).
        $component->callMountedAction();
        $this->assertSame(1, Order::where('user_id', $customer->id)->count());
    }

    private function makeAddon(int $price = 200): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 50,
        ]);
        $addon->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => $price]);
        \DB::table('product_addons')->insert(['product_id' => $this->h1->id, 'addon_id' => $addon->id, 'position' => 0]);

        return $addon;
    }

    public function test_addon_options_resolve_for_the_selected_product(): void
    {
        $addon = $this->makeAddon();

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('pickProduct', $this->h1->id);

        // El complemento del pivote debe aparecer en el view-model compartido (como single opcional).
        $model = $component->instance()->manualAddonViewModel();
        $ids = collect($model['singles'])->pluck('id')->all();

        $this->assertContains($addon->id, $ids);
    }

    public function test_per_guest_addon_is_activated_with_a_checkbox_not_a_counter(): void
    {
        // P10 — paridad con la compra pública: un complemento per-invitado OPCIONAL se activa con
        // checkbox (toggleManualAddon); el contador NO lo activa (su cantidad la fija el aforo).
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 50,
        ]);
        $addon->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 200]);
        \DB::table('product_addons')->insert([
            'product_id' => $this->h1->id, 'addon_id' => $addon->id, 'position' => 0,
            'quantity_mode' => 'per_guest', 'allow_extra' => false,
        ]);

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('pickProduct', $this->h1->id);

        $socks = collect($component->instance()->manualAddonViewModel()['singles'])->firstWhere('id', $addon->id);
        $this->assertTrue($socks['can_toggle']);
        $this->assertFalse($socks['selected']);

        // El contador no lo activa…
        $component->call('incManualAddon', $addon->id);
        $this->assertSame(0, (int) ($component->get('selAddonQty')[$addon->id] ?? 0));

        // …el checkbox sí.
        $component->call('toggleManualAddon', $addon->id);
        $this->assertSame(1, (int) $component->get('selAddonQty')[$addon->id]);
    }

    public function test_order_with_addon_is_created(): void
    {
        Notification::fake();
        $addon = $this->makeAddon(200);
        $customer = $this->customer();

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('data.payment_method', 'cash')
            ->set('data.source', 'counter')
            ->call('pickProduct', $this->h1->id)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->set('data.sel_qty', 1)
            ->set('selAddonQty', [$addon->id => 2])
            ->call('addLineToCart')
            ->call('create');

        $order = Order::where('user_id', $customer->id)->first();
        $this->assertNotNull($order);
        $addonItem = $order->items()->where('ticket_type_id', $addon->id)->first();
        $this->assertNotNull($addonItem);
        $this->assertSame(2, $addonItem->quantity);
        $this->assertNotNull($addonItem->parent_item_id);   // agrupado bajo el producto
        $this->assertSame(1000 + 200 * 2, $order->total);     // entrada + 2 × complemento
    }

    public function test_included_addon_is_preloaded_and_charged_as_free(): void
    {
        Notification::fake();
        $cake = $this->makeAddon(1500);
        $this->h1->configurableAddons()->updateExistingPivot($cake->id, [
            'is_included' => true, 'is_mandatory' => true, 'included_quantity' => 1,
            'quantity_mode' => 'fixed', 'allow_extra' => true,
        ]);
        $customer = $this->customer();

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('data.payment_method', 'cash')
            ->set('data.source', 'counter')
            ->call('pickProduct', $this->h1->id);

        // Pre-carga: el obligatorio incluido arranca seleccionado a 1 en el view-model.
        $cakeRow = collect($component->instance()->manualAddonViewModel()['singles'])->firstWhere('id', $cake->id);
        $this->assertNotNull($cakeRow);
        $this->assertTrue($cakeRow['selected']);
        $this->assertSame(1, $cakeRow['qty']);

        $component->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->set('data.sel_qty', 1)
            ->call('addLineToCart')
            ->call('create');

        $order = Order::where('user_id', $customer->id)->first();
        $cakeItem = $order->items()->where('ticket_type_id', $cake->id)->first();
        $this->assertNotNull($cakeItem);
        $this->assertSame(1, (int) $cakeItem->free_quantity);   // 1.ª gratis
        $this->assertSame(0, $cakeItem->chargedSubtotalCents());
        $this->assertSame(1000, $order->total);                  // solo la entrada
    }

    /**
     * ⚠️ Se llamaba `..._on_customer_then_on_cart` y describía el asistente de TRES pasos. El SUJETO
     * no cambia —el avance está cerrado hasta que la pregunta del paso está contestada—, pero desde
     * `#462` los pasos son siete y **elegir cliente ya avanza solo**, así que lo que se comprueba es
     * la PUERTA de cada paso, no el número de toques.
     */
    public function test_each_step_gates_the_advance_until_its_question_is_answered(): void
    {
        $customer = $this->customer();

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->assertSet('step', CreateManualOrderPage::STEP_CUSTOMER)
            // Sin cliente no se avanza ni pulsando.
            ->call('next')->assertSet('step', CreateManualOrderPage::STEP_CUSTOMER)
            // Elegir cliente avanza SOLO (`[DECIDIDO owner]`).
            ->set('data.customer_id', $customer->id)
            ->assertSet('step', CreateManualOrderPage::STEP_PRODUCT)
            // Sin producto elegido, el paso de producto no deja pasar.
            ->call('next')->assertSet('step', CreateManualOrderPage::STEP_PRODUCT);

        // Y desde el carrito: vacío no deja ir a pagar; con una línea, sí.
        $component->set('step', CreateManualOrderPage::STEP_CART)
            ->set('cart', [])
            ->call('next')->assertSet('step', CreateManualOrderPage::STEP_CART)
            ->set('cart', [$this->cartLine(1)])
            ->call('next')->assertSet('step', CreateManualOrderPage::STEP_PAYMENT)
            ->call('back')->assertSet('step', CreateManualOrderPage::STEP_CART);
    }

    public function test_can_advance_reflects_customer_and_cart_state(): void
    {
        $component = Livewire::actingAs($this->staff())->test(CreateManualOrderPage::class);
        $this->assertFalse($component->instance()->canAdvance());           // paso 1 sin cliente

        $component->set('data.customer_id', $this->customer()->id);
        // Ya está en el paso 2 (elegir cliente avanza), y ahí la puerta es el PRODUCTO.
        $component->assertSet('step', CreateManualOrderPage::STEP_PRODUCT);
        $this->assertFalse($component->instance()->canAdvance());           // paso 2 sin producto

        $component->set('step', CreateManualOrderPage::STEP_CART)->set('cart', []);
        $this->assertFalse($component->instance()->canAdvance());           // carrito vacío

        $component->set('cart', [$this->cartLine(1)]);
        $this->assertTrue($component->instance()->canAdvance());            // carrito con una línea
    }

    public function test_full_stepped_flow_creates_the_order_and_persists_hidden_step_state(): void
    {
        Notification::fake();
        $customer = $this->customer();

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            // Paso 1 · cliente: elegirlo avanza solo al paso de producto.
            ->set('data.customer_id', $customer->id)
            ->assertSet('step', CreateManualOrderPage::STEP_PRODUCT)
            // Paso 2 · producto: elegirlo avanza solo al de «cuándo».
            ->call('pickProduct', $this->h1->id)
            ->assertSet('step', CreateManualOrderPage::STEP_WHEN)
            // Paso 3 · cuántos, qué día y a qué hora.
            ->set('data.sel_qty', 2)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->call('addLineToCart')
            // Añadir la línea deja al operador EN EL CARRITO (`[DECIDIDO owner]`).
            ->assertSet('step', CreateManualOrderPage::STEP_CART)
            ->call('next')->assertSet('step', CreateManualOrderPage::STEP_PAYMENT)
            // Pago. El `customer_id` del paso 1 (oculto desde hace cinco pasos) persiste y se usa.
            ->set('data.payment_method', 'datafono')
            // …y por dónde llegó (T1d): con el cliente delante, el mostrador.
            ->set('data.source', 'counter')
            ->call('create');

        $order = Order::where('user_id', $customer->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame('datafono', $order->payments()->first()->provider);
        $this->assertSame(2000, $order->total);
    }

    public function test_add_line_rejects_pack_quantity_out_of_range(): void
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'position' => 2,
        ]);
        $pack->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 18000]);

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('pickProduct', $pack->id)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->set('data.sel_qty', 5)            // por debajo del mínimo (8)
            ->call('addLineToCart');

        // No se añade la línea (rango bloqueado antes de tocar aforo).
        $this->assertCount(0, $component->get('cart'));
    }

    public function test_manual_order_form_shows_only_booking_event_fields(): void
    {
        // #217: el operador solo ve los campos de la fase de RESERVA; los `postform` los rellena el
        // cliente en el post-form (y `OrderCreator` los descartaría aquí al filtrar a `booking`).
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'min_qty' => 8, 'max_qty' => 20, 'position' => 2,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Homenajeado']],
                ['key' => 'adults', 'type' => 'number', 'required' => false, 'stage' => 'postform', 'label' => ['es' => 'Adultos']],
            ],
        ]);

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('pickProduct', $pack->id);

        $method = new \ReflectionMethod(CreateManualOrderPage::class, 'selectionEventDataFields');
        $method->setAccessible(true);
        $names = array_map(fn ($f) => $f->getName(), $method->invoke($component->instance()));

        $this->assertContains('event_data.celebrant', $names);
        $this->assertNotContains('event_data.adults', $names); // postform → no se pide al operador
    }

    public function test_full_slot_surfaces_error_and_creates_nothing(): void
    {
        Notification::fake();
        $customer = $this->customer();

        // Ocupa la franja (aforo 10).
        $blocker = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-BLOCK1', 'status' => Order::STATUS_PAID,
        ]);
        $slot = Slot::where('zone_id', $this->zone->id)->first();
        $blocker->items()->create([
            'ticket_type_id' => $this->h1->id, 'slot_id' => $slot->id,
            'quantity' => 10, 'unit_price' => 1000, 'seats' => 10,
        ]);

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $customer->id)
            ->set('data.payment_method', 'cash')
            ->set('data.source', 'counter')
            ->set('cart', [$this->cartLine(1)])
            ->call('create');

        $this->assertSame(0, Order::where('user_id', $customer->id)->count());
    }
}
