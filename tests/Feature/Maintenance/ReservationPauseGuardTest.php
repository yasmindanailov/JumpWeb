<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Reservas en pausa (#218, item 3) — GUARDS DE SERVIDOR (defensa en profundidad).
 *
 * Cubre que, con las reservas pausadas, el reintento de pago desde «Mis pedidos» se bloquea — PERO
 * el camino del **pedido manual del panel** (`OrderCreator`) sigue funcionando (la decisión de
 * diseño clave: el cliente llama y el personal reserva a mano). Las callbacks de Redsys no pasan
 * por estos guards.
 *
 * ⚠️ **Los tres casos del flujo público de compra se fueron con `Tickets\Purchase`** (4.7·2b·3).
 * Su sujeto —el guard de servidor— SOBREVIVE entero; el componente solo era el intermediario, así
 * que antes de borrarlos se comprobó uno a uno que la superficie viva los cubre:
 *
 *  · «bloquea y no crea pedido» → `Api\V1\OrdersTest::test_a_paused_installation_refuses_to_create_
 *    and_leaves_no_order`, y el guard de dominio en `CheckoutOrchestratorTest` y
 *    `ReservationAdmissionPolicyTest`.
 *  · «se pausa a mitad de flujo, justo antes de confirmar» → lo mismo, y además es INHERENTE en la
 *    API: el guard se evalúa en la llamada que crea, no se arrastra de un paso anterior. Importa
 *    porque el cajón SPA sí cachea el estado de pausa en cliente (solo lo relee al cargar, al abrir
 *    y al pulsar «Ir a pagar»), de modo que el servidor es quien tiene que decir la última palabra.
 *  · «teléfono vacío» → su modo de fallo era interpolar un `:phone` vacío en el mensaje del Blade, y
 *    **ese mensaje ya no existe**: la API responde con el código `reservations_paused` y
 *    `ReservationErrorMap` no toca el teléfono. Lo que sí sobrevive —que un teléfono en blanco no se
 *    cuele como canal de contacto— lo cubre `Api\V1\BookingStatusTest::test_a_blank_phone_is_not_a_
 *    channel`.
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

    /**
     * ⚠️ **Re-apuntado a la API en la tanda 3** (`DECISIONES #120(u)`): estos dos casos conducían el
     * reintento desde «Mis pedidos», y esa superficie se retiró con la página. El SUJETO no cambia
     * —la pausa bloquea un reintento y no abre cobro— y hoy la puerta que queda es la que usa el
     * cajón. Es la tercera categoría de `CONVENCIONES §3.quater`: la superficie vieja era un
     * intermediario de una regla que sobrevive.
     */
    public function test_account_retry_is_blocked_when_paused(): void
    {
        $this->pause();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->makeRetryableOrder($user);

        $this->actingAs($user)
            ->postJson('/api/v1/orders/'.$order->code.'/payment')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'reservations_paused');

        // No se inició un cobro nuevo (sigue habiendo un único Payment, el original).
        $this->assertSame(1, Payment::where('payable_id', $order->id)->count());
    }

    public function test_account_retry_works_normally_when_open(): void
    {
        // Sanity: con las reservas abiertas, el reintento sigue funcionando (sin regresión).
        $user = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->makeRetryableOrder($user);

        $this->actingAs($user)
            ->postJson('/api/v1/orders/'.$order->code.'/payment')
            ->assertOk();

        $this->assertSame(2, Payment::where('payable_id', $order->id)->count());
    }
}
