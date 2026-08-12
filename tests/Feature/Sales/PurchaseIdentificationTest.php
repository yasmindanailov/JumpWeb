<?php

namespace Tests\Feature\Sales;

use App\Domain\Platform\Models\Setting;
use App\Livewire\Auth\Register;
use App\Livewire\Tickets\Purchase;
use App\Models\Order;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 5 (Pieza 2) — Identificación + verificación en la compra (robusta, matiza #46):
 * el cliente NUEVO se registra dentro del sidebar, lo que crea una reserva PROVISIONAL que
 * retiene su plaza; verifica su email para CONFIRMARLA y vuelve a su compra. Anti-enumeración:
 * un email ya existente no crea reserva y muestra el mismo aviso genérico.
 */
class PurchaseIdentificationTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump1h;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08'); // lunes → tarifa normal, fechas deterministas
        $this->today = Carbon::today()->toDateString();

        $this->seed(RoleSeeder::class);

        // HIBP no debe llamar a la red en tests.
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data): bool
            {
                return true;
            }
        });
        RateLimiter::clear('register:127.0.0.1');

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->today,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);
        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->jump1h->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<int, array{ticket_type_id:int, date:string, time:string, qty:int}> */
    private function cart(int $qty = 2): array
    {
        return [['ticket_type_id' => $this->jump1h->id, 'date' => $this->today, 'time' => '10:00:00', 'qty' => $qty]];
    }

    /** @param array<string, mixed> $overrides */
    private function validRegister(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'phone' => '600123123',
            'password' => 'una-frase-larga-y-segura',
            'accept_privacy' => true,
            'accept_terms' => true,
            'marketing' => false,
        ], $overrides);
    }

    public function test_embedded_register_new_account_logs_in_and_proceeds_to_payment(): void
    {
        // Pay-first (decisión clienta 2026-06-14): al registrarse en la compra NO se manda email de
        // verificación ni se retiene reserva; se INICIA SESIÓN y se avisa al sidebar (`logged-in`)
        // para continuar al paso de pago. El pedido se crea —y cobra— en confirmReservation (Redsys),
        // y el pago auto-verifica la cuenta. La cesta se conserva (se usa al pagar).
        Notification::fake();
        session(['purchase.cart' => $this->cart()]);

        Livewire::test(Register::class, ['embedded' => true])
            ->set($this->validRegister(['email' => 'nuevo@example.com']))
            ->call('register')
            ->assertHasNoErrors()
            ->assertDispatched('logged-in'); // el sidebar continúa a pago

        $user = User::where('email', 'nuevo@example.com')->first();
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);                  // ha iniciado sesión
        $this->assertFalse($user->hasVerifiedEmail());        // sin verificar (lo hará el pago)

        $this->assertSame(0, Order::count());                 // NO se retiene reserva (se crea al pagar)
        Notification::assertNothingSent();                    // NO se manda email de verificación
        $this->assertNotEmpty(session('purchase.cart'));      // la cesta se conserva para el pago
    }

    public function test_embedded_register_creates_account_without_order_even_when_paused(): void
    {
        // Pay-first: el registro embebido nunca crea pedido (se crea al pagar). Con reservas en
        // pausa, la cuenta SÍ se crea y se inicia sesión; el bloqueo del pago lo aplica
        // Purchase::proceed() (cubierto aparte). No se manda ningún email (auto-verify al pagar).
        Notification::fake();
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1', 'group' => 'maintenance']);
        session(['purchase.cart' => $this->cart()]);

        Livewire::test(Register::class, ['embedded' => true])
            ->set($this->validRegister(['email' => 'pausa@example.com']))
            ->call('register')
            ->assertHasNoErrors();

        $user = User::where('email', 'pausa@example.com')->first();
        $this->assertNotNull($user);                    // la cuenta SÍ se crea
        $this->assertSame(0, Order::count());            // nunca se retiene reserva en el registro
        Notification::assertNothingSent();               // pay-first: sin email de verificación
    }

    public function test_embedded_register_existing_email_creates_no_order_and_keeps_the_cart(): void
    {
        Notification::fake();
        $existing = User::factory()->create(['email' => 'ya@example.com']);
        session(['purchase.cart' => $this->cart()]);

        Livewire::test(Register::class, ['embedded' => true])
            ->set($this->validRegister(['email' => 'ya@example.com']))
            ->call('register')
            ->assertHasErrors(['email']); // [decisión clienta] feedback claro: el email ya existe

        $this->assertGuest();                                 // un email ajeno NO inicia sesión
        $this->assertSame(0, Order::count());                 // no se reserva para un email ajeno
        $this->assertNotEmpty(session('purchase.cart'));      // la cesta se conserva (puede ir a login)
    }

    public function test_authenticated_unverified_user_checkout_goes_to_payment(): void
    {
        // Pay-first: un usuario identificado AUNQUE NO esté verificado pasa al paso de PAGO (8); el
        // pedido firme se crea —y cobra— en confirmReservation. Antes esto creaba una reserva
        // provisional y mandaba a verificar (paso 7).
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        session(['purchase.cart' => $this->cart()]);

        Livewire::test(Purchase::class)
            ->call('checkout')
            ->assertSet('step', 8);

        $this->assertSame(0, Order::count()); // el pedido se crea al pulsar pagar, no aquí
    }

    public function test_paused_blocks_authenticated_checkout_to_payment(): void
    {
        // El bloqueo de reservas en pausa (#218) sigue vigente con pay-first: el usuario identificado
        // NO llega a pago; vuelve al carrito (paso 4) con el aviso.
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1', 'group' => 'maintenance']);
        $user = User::factory()->create(); // verificado: aislamos el efecto de la pausa
        $this->actingAs($user);
        session(['purchase.cart' => $this->cart()]);

        Livewire::test(Purchase::class)
            ->call('checkout')
            ->assertSet('step', 4)
            ->assertHasErrors('cart');

        $this->assertSame(0, Order::count());
    }
}
