<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Livewire\Tickets\Purchase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\OrderCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reservas en pausa (#218, item 3) — GUARDS DE SERVIDOR (defensa en profundidad).
 *
 * Cubre que, con las reservas pausadas: el flujo público de compra (`Purchase`) no crea pedidos ni
 * inicia pagos, y el reintento de pago desde «Mis pedidos» se bloquea — PERO el camino del
 * **pedido manual del panel** (`OrderCreator`) sigue funcionando (la decisión de diseño clave: el
 * cliente llama y el personal reserva a mano). Las callbacks de Redsys no pasan por estos guards.
 */
class ReservationPauseGuardTest extends TestCase
{
    use RefreshDatabase;

    private string $today;

    private Zone $zone;

    private Slot $slot;

    private TicketType $jump1h;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08'); // lunes, tarifa normal
        $this->today = Carbon::today()->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->today,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);
        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->jump1h->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000,
        ]);

        foreach ([
            ['redsys_environment', 'test'], ['redsys_merchant_code', '999008881'], ['redsys_terminal', '001'],
            ['redsys_secret_key', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'], ['redsys_currency', '978'],
            ['redsys_merchant_name', 'SaltoPark'], ['redsys_next_gateway_order', '100000'],
        ] as [$k, $v]) {
            Setting::create(['key' => $k, 'value' => $v, 'group' => 'payment']);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pause(): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1', 'group' => 'maintenance']);
    }

    // ─── Flujo público de compra (Purchase) ─────────────────────────────────────

    public function test_checkout_is_blocked_and_creates_no_order_when_paused(): void
    {
        $this->pause();

        Livewire::actingAs(User::factory()->create())
            ->test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('checkout')          // verificado → proceed() → guard de pausa
            ->assertSet('step', 4)      // re-encaminado al carrito (no avanza a pago)
            ->assertHasErrors('cart');

        $this->assertSame(0, Order::count());
    }

    public function test_confirm_reservation_is_blocked_when_paused_mid_flow(): void
    {
        // Llega al paso de pago con las reservas ABIERTAS y se pausan justo antes de confirmar.
        $component = Livewire::actingAs(User::factory()->create())
            ->test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('checkout')
            ->assertSet('step', 8);

        $this->pause();

        $component->call('confirmReservation')
            ->assertSet('step', 4)
            ->assertHasErrors('cart');

        // Ni pedido ni pago: el guard corta ANTES de crear la reserva firme.
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_checkout_blocked_with_empty_phone_creates_no_order(): void
    {
        // Defensa: aunque no haya teléfono configurado, el guard sigue impidiendo la reserva (sin
        // interpolar un `:phone` vacío que rompería la gramática). El aviso al usuario lo da el panel
        // del sidecart (ReservationPauseTest), no este mensaje, que es solo la red de servidor.
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '', 'group' => 'contact']);
        $this->pause();

        Livewire::actingAs(User::factory()->create())
            ->test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('addToCart')
            ->call('checkout')
            ->assertHasErrors('cart');

        $this->assertSame(0, Order::count());
    }

    // ─── La decisión clave: el pedido MANUAL (OrderCreator) NO se ve afectado ────

    public function test_order_creator_still_works_when_reservations_paused(): void
    {
        // El pedido manual del panel usa OrderCreator directamente (vía ManualOrderFulfiller).
        // La pausa de reservas NO debe bloquearlo: el cliente llama y el personal reserva a mano.
        $this->pause();

        $user = User::factory()->create();
        $cart = [[
            'ticket_type_id' => $this->jump1h->id,
            'date' => $this->today,
            'time' => '10:00:00',
            'qty' => 1,
            'event_data' => [],
            'addons' => [],
        ]];

        $order = app(OrderCreator::class)->createPendingOrder($user, $cart);

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $user->id]);
    }

    // ─── Reintento de pago desde «Mis pedidos» (RetryPaymentController) ──────────

    private function makeRetryableOrder(User $user): Order
    {
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-RP'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PENDING, 'subtotal' => 1000, 'total' => 1000,
            'currency' => 'EUR', 'expires_at' => now()->addMinutes(15),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->jump1h->id,
            'slot_id' => $this->slot->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1000, 'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'gateway_order' => '0000'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
        ]);

        return $order;
    }

    public function test_account_retry_is_blocked_when_paused(): void
    {
        $this->pause();

        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.retry', ['code' => $order->code]))
            ->assertRedirect(route('account.orders'))
            ->assertSessionHas('status', 'order-retry-paused');

        // No se inició un cobro nuevo (sigue habiendo un único Payment, el original).
        $this->assertSame(1, Payment::where('payable_id', $order->id)->count());
    }

    public function test_account_retry_works_normally_when_open(): void
    {
        // Sanity: con las reservas abiertas, el reintento sigue funcionando (sin regresión).
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.retry', ['code' => $order->code]))
            ->assertOk();

        $this->assertSame(2, Payment::where('payable_id', $order->id)->count());
    }
}
