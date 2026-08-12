<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ManualOrderFulfiller;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerRegistrar;
use App\Filament\Pages\CreateManualOrderPage;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\CustomerAccountCreated;
use App\Notifications\OrderCancelled;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Email OPCIONAL en el alta de pedidos manuales (#263): reservas de agenda con SOLO teléfono. El email
 * sigue siendo obligatorio en el registro web; aquí se permite crear fichas sin correo (viven solo en
 * el panel). Cubre el modelo de datos (email nullable, varios NULL conviven bajo el UNIQUE), el alta
 * sin email (servicio + página), la dedup por teléfono «avisar y dejar elegir», que NO se envían
 * correos a un cliente sin email y que «Reenviar email» se oculta. (El icono «enlace» del post-form
 * lo cubre `GuestFormLinkCopyTest`.)
 */
class ManualOrderWithoutEmailTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private TicketType $pack;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);
        $this->date = Carbon::today()->addDays(3)->toDateString();

        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 10, 'online_capacity' => 10,
        ]);
        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->date,
            'start_time' => '16:00:00', 'end_time' => '18:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ],
        ]);
        $this->pack->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 9000]);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function customerRole(): int
    {
        return (int) Role::where('name', 'customer')->value('id');
    }

    // ─── Modelo de datos ──────────────────────────────────────────────────────

    public function test_two_customers_without_email_coexist_under_the_unique_index(): void
    {
        // La migración hace `email` nullable conservando el UNIQUE: MySQL/SQLite permiten múltiples
        // NULL bajo un índice único → varios clientes de agenda sin email conviven sin colisión.
        User::factory()->withoutEmail()->create(['phone' => '600000001']);
        User::factory()->withoutEmail()->create(['phone' => '600000002']);

        $this->assertSame(2, User::whereNull('email')->count());
    }

    // ─── CustomerRegistrar (capa de servicio) ───────────────────────────────────

    public function test_register_without_email_creates_panel_only_customer_and_sends_no_mail(): void
    {
        Notification::fake();

        $result = app(CustomerRegistrar::class)->register('Cliente Agenda', null, '600 11 22 33');

        $this->assertTrue($result['created']);
        $this->assertFalse($result['email_sent']);

        $user = $result['user'];
        $this->assertNull($user->email);                 // NULL, no cadena vacía
        $this->assertSame('Cliente Agenda', $user->name);
        $this->assertFalse($user->hasVerifiedEmail());   // sin email no hay nada que verificar
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame(1, $user->consents()->where('type', 'privacy')->count());

        Notification::assertNothingSent();               // sin email no se envía bienvenida
    }

    public function test_empty_string_email_is_normalized_to_null(): void
    {
        Notification::fake();

        $user = app(CustomerRegistrar::class)->register('Sin Correo', '   ', '600222111')['user'];

        $this->assertNull($user->email);
    }

    public function test_matching_by_phone_ignores_spaces_and_symbols_but_not_prefix(): void
    {
        $existing = User::factory()->create(['phone' => '600 11 22 33']);
        $existing->roles()->sync([$this->customerRole()]);

        $registrar = app(CustomerRegistrar::class);

        // Normaliza espacios/símbolos → casa.
        $this->assertTrue($registrar->customersMatchingPhone('600112233')->contains('id', $existing->id));
        // Prefijo distinto (+34) → NO casa (no asumimos país).
        $this->assertFalse($registrar->customersMatchingPhone('+34600112233')->contains('id', $existing->id));
    }

    public function test_register_with_email_still_dedups_and_sends_welcome(): void
    {
        // Regresión: el camino CON email no cambia (dedup por email + bienvenida).
        Notification::fake();

        $first = app(CustomerRegistrar::class)->register('Ada', 'ada@example.com', '600')['user'];
        Notification::assertSentTo($first, CustomerAccountCreated::class);

        $again = app(CustomerRegistrar::class)->register('Otra', 'ADA@example.com', '601');
        $this->assertFalse($again['created']);
        $this->assertSame($first->id, $again['user']->id);
        $this->assertSame(1, User::where('email', 'ada@example.com')->count());
    }

    // ─── Página: alta sin email + deduplicación por teléfono ─────────────────────

    public function test_page_registers_customer_without_email_and_selects_it(): void
    {
        Notification::fake();

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('registerCustomerFromData', [
                'name' => 'Agenda Cliente', 'email' => '', 'phone' => '600 12 34 56', 'privacy_informed' => true,
            ]);

        $user = User::whereNull('email')->where('name', 'Agenda Cliente')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('customer'));
        $component->assertSet('data.customer_id', $user->id);

        Notification::assertNothingSent();
    }

    public function test_page_requires_phone_when_no_email(): void
    {
        Notification::fake();

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('registerCustomerFromData', [
                'name' => 'Sin Teléfono', 'email' => '', 'phone' => '', 'privacy_informed' => true,
            ]);

        $this->assertSame(0, User::where('name', 'Sin Teléfono')->count());
    }

    public function test_phone_duplicate_warns_and_lets_operator_choose(): void
    {
        Notification::fake();

        $existing = User::factory()->create(['name' => 'Ya Existe', 'email' => null, 'phone' => '600123456']);
        $existing->roles()->sync([$this->customerRole()]);

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('registerCustomerFromData', [
                'name' => 'Repetido', 'email' => '', 'phone' => '600 12 34 56', 'privacy_informed' => true,
            ]);

        // NO crea: solo avisa con las coincidencias y guarda la alta pendiente.
        $this->assertSame(0, User::where('name', 'Repetido')->count());
        $component->assertSet('data.customer_id', null);
        $this->assertArrayHasKey($existing->id, $component->get('phoneMatchOptions'));
        $this->assertNotNull($component->get('pendingNoEmailCustomer'));

        // «Usar existente» selecciona y limpia el aviso.
        $component->call('useExistingCustomer', $existing->id)
            ->assertSet('data.customer_id', $existing->id)
            ->assertSet('phoneMatchOptions', []);
    }

    public function test_create_new_anyway_creates_a_second_customer(): void
    {
        Notification::fake();

        $existing = User::factory()->create(['name' => 'Familia', 'email' => null, 'phone' => '600123456']);
        $existing->roles()->sync([$this->customerRole()]);

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('registerCustomerFromData', [
                'name' => 'Hermano', 'email' => '', 'phone' => '600123456', 'privacy_informed' => true,
            ]);

        // Aviso pendiente → «Crear nuevo de todos modos» crea el segundo (familias que comparten tel.).
        $component->call('createNewCustomerAnyway');

        $new = User::where('name', 'Hermano')->first();
        $this->assertNotNull($new);
        $this->assertNull($new->email);
        $component->assertSet('data.customer_id', $new->id)->assertSet('phoneMatchOptions', []);
        $this->assertSame(2, User::where('phone', '600123456')->count());
    }

    public function test_use_existing_only_accepts_ids_from_the_shown_matches(): void
    {
        // Defensa anti-manipulación: useExistingCustomer ignora ids que no estaban en las coincidencias.
        $other = User::factory()->create();
        $other->roles()->sync([$this->customerRole()]);

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('useExistingCustomer', $other->id)        // sin aviso pendiente → phoneMatchOptions vacío
            ->assertSet('data.customer_id', null);
    }

    // ─── Pedido manual de un cliente sin email: sin correos ─────────────────────

    public function test_manual_entry_order_for_emailless_customer_sends_no_confirmation(): void
    {
        Notification::fake();
        $customer = User::factory()->withoutEmail()->create();
        $this->actingAs($this->staff());

        $order = app(ManualOrderFulfiller::class)->fulfill(
            $customer,
            [['ticket_type_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 2]],
            ManualOrderFulfiller::METHOD_CASH,
        );

        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(2, Ticket::where('order_id', $order->id)->count());
        Notification::assertNothingSent();   // sin email → ni confirmación
    }

    public function test_manual_pack_order_for_emailless_customer_sends_no_guest_form(): void
    {
        Notification::fake();
        $customer = User::factory()->withoutEmail()->create();
        $this->actingAs($this->staff());

        $order = app(ManualOrderFulfiller::class)->fulfill(
            $customer,
            [['ticket_type_id' => $this->pack->id, 'date' => $this->date, 'time' => '16:00:00', 'qty' => 2, 'event_data' => []]],
            ManualOrderFulfiller::METHOD_CASH,
        );

        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertTrue($order->load('items.ticketType')->needsGuestForm());
        Notification::assertNothingSent();   // sin email → tampoco el post-form
    }

    // ─── Panel: «Reenviar email» oculto sin email ───────────────────────────────

    public function test_emailless_paid_order_hides_resend_email(): void
    {
        $customer = User::factory()->withoutEmail()->create();
        $order = Order::create([
            'user_id' => $customer->id, 'code' => 'JJ-NOEM01',
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => null, 'quantity' => 2,
            'unit_price' => 9000, 'seats' => 2, 'event_data' => ['celebrant' => 'Mara'],
        ]);

        // Sin email no hay ningún correo que reenviar.
        $this->assertSame([], $order->load('items.ticketType', 'user')->availableResendEmailTypes());
        $this->assertFalse($order->canResendAnyEmail());

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('resendEmail');
    }

    public function test_order_with_email_keeps_resend_available(): void
    {
        // Regresión: con email, «Reenviar email» sigue disponible.
        $customer = User::factory()->create();   // con email
        $order = Order::create([
            'user_id' => $customer->id, 'code' => 'JJ-WITH01',
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => null, 'quantity' => 2,
            'unit_price' => 9000, 'seats' => 2, 'event_data' => ['celebrant' => 'Mara'],
        ]);

        $this->assertNotEmpty($order->load('items.ticketType', 'user')->availableResendEmailTypes());

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('resendEmail');
    }

    // ─── #264-audit: refinamientos ──────────────────────────────────────────────

    public function test_order_notify_customer_skips_without_email(): void
    {
        // Order::notifyCustomer no encola correos a clientes sin email (jobs muertos), pero sí a los que
        // tienen email. Centraliza la guarda usada por las acciones de ViewOrder.
        Notification::fake();
        $noEmail = User::factory()->withoutEmail()->create();
        $withEmail = User::factory()->create();
        $orderNo = Order::create(['user_id' => $noEmail->id, 'code' => 'JJ-NN01', 'status' => Order::STATUS_PAID, 'paid_at' => now()]);
        $orderYes = Order::create(['user_id' => $withEmail->id, 'code' => 'JJ-YY01', 'status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $orderNo->notifyCustomer(new OrderCancelled($orderNo));
        $orderYes->notifyCustomer(new OrderCancelled($orderYes));

        Notification::assertNotSentTo($noEmail, OrderCancelled::class);
        Notification::assertSentTo($withEmail, OrderCancelled::class);
    }

    public function test_create_new_anyway_keeps_panel_when_rate_limited(): void
    {
        // Si «crear nuevo de todos modos» aborta por rate-limit, el panel de duplicados PERSISTE
        // (no se limpia antes de crear) → el operador no pierde el alta tecleada.
        Notification::fake();
        $phone = '611222333';
        $existing = User::factory()->create(['name' => 'Ya Existe', 'email' => null, 'phone' => $phone]);
        $existing->roles()->sync([$this->customerRole()]);

        $component = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->call('registerCustomerFromData', [
                'name' => 'Repe', 'email' => '', 'phone' => $phone, 'privacy_informed' => true,
            ]);
        $this->assertNotNull($component->get('pendingNoEmailCustomer'));

        // Agota el rate-limit para la clave por teléfono que usará performRegistration.
        $key = 'manual-register:'.md5('phone:'.CustomerRegistrar::normalizePhone($phone));
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 3600);
        }

        $component->call('createNewCustomerAnyway');

        $this->assertSame(0, User::where('name', 'Repe')->count());        // no creó (rate-limited)
        $this->assertNotNull($component->get('pendingNoEmailCustomer'));   // panel persiste
        $this->assertArrayHasKey($existing->id, $component->get('phoneMatchOptions'));
    }

    public function test_phone_match_ignores_plus_prefix(): void
    {
        // Unificación con la puerta (#264-audit): la normalización es solo-dígitos → «+34…» casa con
        // «34…» (mismo número escrito con/sin '+').
        $existing = User::factory()->create(['phone' => '+34 600 11 22 33']);
        $existing->roles()->sync([$this->customerRole()]);

        $this->assertTrue(
            app(CustomerRegistrar::class)->customersMatchingPhone('34600112233')->contains('id', $existing->id),
        );
    }
}
