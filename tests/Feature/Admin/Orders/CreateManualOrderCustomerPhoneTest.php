<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CheckoutDuties;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Pages\CreateManualOrderPage as Cmo;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `#440` · EL TELÉFONO QUE FALTA, en el asistente de crear pedido
 * (`docs/specs/telefono-del-cliente.md` §4).
 *
 * **El hueco que cierra, medido**: al CREAR un cliente el teléfono ya era obligatorio, pero al
 * ELEGIR uno existente no se comprobaba nada — y `customerDisplay()` pinta el correo cuando lo hay,
 * así que **un cliente sin teléfono se veía exactamente igual que uno completo**. Y no hay ninguna
 * otra vía en el panel para ponérselo: `UserResource` no tiene `EditUser`.
 *
 * Lo que vigila, en orden de daño:
 *
 *  1. **Que la puerta esté en las CUATRO capas y no solo en la navegación.** La revisión adversarial
 *     reprodujo que `addLineToCart()` es **público y no mira el paso del cliente**: desde el paso 1,
 *     sin tocar `step`, una llamada suelta aterriza la línea en el carrito **y mueve `step` ella
 *     misma**. El paso 1 no era precondición de nada aguas abajo — la lección de `#464` con
 *     `$calMonth`, donde la propiedad pública tampoco era el agujero.
 *  2. **Que el aviso y el rótulo del cliente digan LO MISMO.** Había tres redacciones del predicado
 *     «tiene teléfono» y la de `customerDisplay()` divergía con un teléfono de espacios.
 *  3. ❗❗ **Que `create()` NO bloquee la venta** (`[DECIDIDO owner]`). Este caso existe para que
 *     nadie «termine el trabajo» convirtiendo el aviso en un `return`: sería lo primero en la
 *     historia del panel capaz de tumbar una venta de mostrador por un teléfono que falta.
 *
 * ⚠️ **Los casos miden el MOVIMIENTO y el EFECTO, no el predicado** (`#464`): que `canAdvance()`
 * devuelva `false` no dice nada si el operador puede llegar igual por otra puerta.
 */
class CreateManualOrderCustomerPhoneTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entrada;

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
            'capacity' => 60, 'online_capacity' => 60,
        ]);

        $this->entrada = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entrada->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'),
            'amount_cents' => 1000,
        ]);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    /**
     * ⚠️ **El sujeto hay que construirlo**: `UserFactory` genera teléfono
     * (`fake()->numerify('6########')`), así que un caso escrito con la factory a pelo mide el
     * mundo en el que el defecto no existe.
     */
    private function customer(?string $phone): User
    {
        $u = User::factory()->create(['phone' => $phone]);
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    // ─── 1 · El paso 1 retiene ───────────────────────────────────────────────────────────────

    public function test_choosing_a_customer_without_phone_does_not_advance(): void
    {
        Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $this->customer(null)->id)
            ->assertSet('step', Cmo::STEP_CUSTOMER);
    }

    public function test_choosing_a_customer_with_phone_advances(): void
    {
        // CONTROL. Sin él, un `canAdvance()` que devolviera siempre `false` en el paso 1 pasaría
        // el caso de arriba y el asistente quedaría inservible para TODOS los clientes.
        Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $this->customer('600111222')->id)
            ->assertSet('step', Cmo::STEP_PRODUCT);
    }

    public function test_a_phone_of_spaces_is_not_a_phone(): void
    {
        // La misma frase que `CheckoutDuties` aplica en el checkout: un teléfono de espacios no es
        // un teléfono. Si el panel usara otra redacción, pediría el campo a quien el servidor da
        // por completo, o al revés.
        Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $this->customer('   ')->id)
            ->assertSet('step', Cmo::STEP_CUSTOMER);
    }

    public function test_the_customer_label_agrees_with_the_gate(): void
    {
        // ⚠️ El rótulo NO puede pintar un teléfono de espacios como si fuera un contacto: con el
        // correo vacío se cae a «(sin email)», que es lo mismo que dice la puerta.
        $sinContacto = $this->customer('   ');
        $sinContacto->forceFill(['email' => null])->save();

        $texto = Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $sinContacto->id)
            ->instance()
            ->currentCustomerLabel();

        $this->assertStringContainsString(__('admin.orders.create_manual.customer_no_email'), (string) $texto);
    }

    // ─── 2 · Escribirlo lo guarda EN LA CUENTA ───────────────────────────────────────────────

    public function test_writing_the_phone_saves_it_on_the_account_and_advances(): void
    {
        $cliente = $this->customer(null);

        Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $cliente->id)
            ->assertSet('step', Cmo::STEP_CUSTOMER)
            ->set('data.customer_phone', ' 600 33 44 55 ')
            ->call('next')
            ->assertSet('step', Cmo::STEP_PRODUCT);

        // Se guarda en la CUENTA, no solo en el pedido — que es lo que hace que el parque pueda
        // llamarle el día de la visita aunque el pedido lo cree otra persona.
        $this->assertSame('600 33 44 55', $cliente->fresh()->phone);

        $this->assertDatabaseHas('audit_logs', ['action' => 'orders.manual_customer_phone_added']);
    }

    public function test_writing_the_phone_enables_the_advance_button(): void
    {
        // ⚠️⚠️ **El caso que faltaba, y lo dijo la MUTACIÓN.** Sin la mitad `|| filled(...)` de
        // `canAdvance()`, el operador escribe el teléfono y el botón «Siguiente» **sigue
        // deshabilitado**: no hay forma de llegar a `next()` —que es quien lo guarda— y el asistente
        // queda muerto sin decir qué falta. Se mide ANTES de llamar a `next()`, porque después el
        // teléfono ya está guardado y el defecto es invisible.
        $instancia = Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $this->customer(null)->id)
            ->set('data.customer_phone', '600334455')
            ->instance();

        $this->assertTrue($instancia->canAdvance());
    }

    public function test_an_existing_phone_is_never_overwritten(): void
    {
        // ⚠️ **Se ejercita el SERVICIO, no la página, y también lo dijo la mutación**: un cliente
        // CON teléfono auto-avanza al elegirlo, así que conduciendo el asistente `saveMissingPhone()`
        // ni siquiera corre — el caso pasaba por el motivo equivocado y una mutación que pisaba el
        // teléfono seguía en verde. La regla vive en `CheckoutDuties`, y ahí se mide.
        $cliente = $this->customer('600111222');

        $escribio = app(CheckoutDuties::class)->recordPhone($cliente, '699999999');

        $this->assertFalse($escribio);
        $this->assertSame('600111222', $cliente->fresh()->phone);
    }

    // ─── 3 · La puerta de aguas abajo: el bypass REPRODUCIDO ─────────────────────────────────

    public function test_the_line_does_not_reach_the_cart_from_the_customer_step(): void
    {
        // ⚠️⚠️ **Éste es el caso que la primera versión de la spec no tenía.** `addLineToCart()` es
        // público y no mira el paso del cliente: sin la puerta, esta llamada mete la línea en el
        // carrito Y mueve `step` a `STEP_CART` ella misma, saltándose el paso 1 entero.
        $page = Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $this->customer(null)->id)
            ->set('data.sel_product_id', $this->entrada->id)
            ->set('data.sel_qty', 2)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->call('addLineToCart');

        $page->assertSet('cart', [])->assertSet('step', Cmo::STEP_CUSTOMER);
    }

    public function test_the_line_reaches_the_cart_once_the_phone_is_there(): void
    {
        // CONTROL del caso anterior: sin él, un `addLineToCart()` que no añadiera NUNCA lo pasaría.
        $page = Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $this->customer('600111222')->id)
            ->set('data.sel_product_id', $this->entrada->id)
            ->set('data.sel_qty', 2)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->call('addLineToCart');

        $this->assertNotSame([], $page->get('cart'));
    }

    // ─── 4 · Y aun así, la venta NO se bloquea ───────────────────────────────────────────────

    public function test_a_sale_is_never_blocked_by_a_missing_phone(): void
    {
        // ❗❗ `[DECIDIDO owner]`: con el cliente delante y el carrito montado, negarse a cobrar por
        // un número es peor que vender sin él. Se avisa y se deja rastro, y **se cobra**.
        //
        // Se llega aquí por el único camino que queda: montar el carrito con el teléfono puesto y
        // que el cliente lo pierda después (una pestaña vieja, un estado desincronizado). Es raro,
        // y es exactamente el escenario para el que existe esta rama.
        $cliente = $this->customer('600111222');

        $page = Livewire::actingAs($this->staff())
            ->test(Cmo::class)
            ->set('data.customer_id', $cliente->id)
            ->set('data.sel_product_id', $this->entrada->id)
            ->set('data.sel_qty', 2)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->call('addLineToCart');

        $cliente->forceFill(['phone' => null])->save();

        $page->set('data.payment_method', 'cash')->set('data.source', 'counter')->call('create');

        $this->assertSame(1, Order::query()->where('user_id', $cliente->id)->count(),
            'La venta de mostrador NO puede bloquearse por un teléfono que falta.');

        $this->assertTrue(
            AuditLog::query()->where('action', 'orders.manual_created_without_phone')->exists(),
            'Vender sin teléfono deja rastro, aunque no se bloquee.'
        );
    }
}
