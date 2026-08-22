<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Cuándo un pedido se puede reintentar, y qué estado enseña de verdad** — dos reglas del dominio.
 *
 * ⚠️ **Nace en la tanda 3 al retirar `/mi-cuenta/pedidos`** (`DECISIONES #120(u)`). Vivían dentro de
 * `PaymentRetryFromOrdersTest`, un fichero que mezclaba tres sujetos: la vista (murió con la página),
 * el controlador web del reintento (murió con ella, porque ya no tenía consumidor) y **estas dos
 * reglas del modelo**, que no eran de ninguno de los dos. `CONVENCIONES §3.quater` llama a esto «el
 * tercero, que hay que buscar activamente»: la superficie retirada era un intermediario.
 *
 * Las dos las consume hoy la API —`can_be_retried` y `status` de `OrderResource`— y por ahí llegan al
 * cajón; que el endpoint las respete lo cubre `OrdersTest`. Aquí se prueban **donde viven**.
 */
class OrderRetryEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump1h;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => '2026-06-08',
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);
        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->jump1h->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);

        // Settings sandbox para que `Redsys::buildPaymentFormData` funcione.
        Setting::create(['key' => 'redsys_environment', 'value' => 'test', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_merchant_code', 'value' => '999008881', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_terminal', 'value' => '001', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_secret_key', 'value' => 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_currency', 'value' => '978', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_merchant_name', 'value' => 'SaltoPark', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_next_gateway_order', 'value' => '100000', 'group' => 'payment']);
    }

    /**
     * Crea un Order pending con su Payment fallido (= cliente intentó pagar y Redsys denegó).
     * Este es el caso típico donde el botón "Reintentar el pago" debe aparecer.
     */
    private function makeRetryableOrder(User $user, string $paymentStatus = Payment::STATUS_FAILED): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-RT'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'expires_at' => now()->addMinutes(15),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->jump1h->id,
            'slot_id' => $this->slot->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1000, 'currency' => 'EUR',
            'status' => $paymentStatus,
            'gateway_order' => '0000'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
        ]);

        return $order;
    }

    public function test_can_be_retried_when_pending_not_expired_with_failed_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_FAILED);

        $this->assertTrue($order->load('payments')->canBeRetried());
    }

    public function test_can_be_retried_when_pending_with_pending_payment(): void
    {
        // Caso: cliente entró en paso 9, nunca volvió. Payment sigue pending. Mientras la
        // Order no expire, el cliente puede reintentar (en lugar de quedarse colgado).
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_PENDING);

        $this->assertTrue($order->load('payments')->canBeRetried());
    }

    public function test_cannot_be_retried_when_order_paid(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_PAID);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();

        $this->assertFalse($order->load('payments')->canBeRetried());
    }

    public function test_cannot_be_retried_when_order_expired(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);
        $order->forceFill(['expires_at' => now()->subMinutes(5)])->save();

        $this->assertFalse($order->load('payments')->canBeRetried());
    }

    public function test_cannot_be_retried_when_order_has_no_payment(): void
    {
        // Provisional / abandono limpio: la Order existe pero el cliente nunca llegó a la
        // pasarela → no hay Payment. El flujo normal es `proceed`/`confirmReservation`,
        // no "reintentar".
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-NOPAY',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertFalse($order->load('payments')->canBeRetried());
    }

    public function test_display_status_returns_expired_when_pending_with_past_expires_at(): void
    {
        // Caso reportado por la clienta: Orders con status='pending' en BD pero `expires_at`
        // ya cruzado. El cron del sistema no corre en local → orders:expire no las actualiza
        // entre ticks. La UI debe mostrar el estado efectivo, no el desfasado.
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);
        $order->forceFill(['expires_at' => now()->subMinutes(10)])->save();

        $this->assertTrue($order->isExpiredInPractice());
        $this->assertSame(Order::STATUS_EXPIRED, $order->displayStatus());
    }

    public function test_display_status_returns_pending_when_not_expired(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);

        $this->assertFalse($order->isExpiredInPractice());
        $this->assertSame(Order::STATUS_PENDING, $order->displayStatus());
    }

    public function test_display_status_returns_paid_unchanged(): void
    {
        // Una Order ya paid no debe ser "re-clasificada" por el accessor — devolvemos
        // el status real (paid) aunque su expires_at original esté en el pasado.
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_PAID);
        $order->forceFill(['status' => Order::STATUS_PAID, 'expires_at' => null, 'paid_at' => now()])->save();

        $this->assertFalse($order->isExpiredInPractice());
        $this->assertSame(Order::STATUS_PAID, $order->displayStatus());
    }
}
