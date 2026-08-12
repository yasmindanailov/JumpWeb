<?php

namespace Tests\Feature\Sales;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderExpiredWithoutPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase 5.0 — `orders:expire` caduca los pedidos pendientes cuya retención de plaza
 * expiró (libera aforo, #DECIDIDO-C), sin tocar los vigentes ni los pagados.
 */
class ExpireOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_only_stale_pending_orders(): void
    {
        $user = User::factory()->create();

        $stale = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-STALE',
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->subMinutes(5),
        ]);

        $fresh = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-FRESH',
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        $paid = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-PAID',
            'status' => Order::STATUS_PAID,
            'expires_at' => now()->subMinutes(5),
            'paid_at' => now(),
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(Order::STATUS_EXPIRED, $stale->refresh()->status);
        $this->assertSame(Order::STATUS_PENDING, $fresh->refresh()->status);
        $this->assertSame(Order::STATUS_PAID, $paid->refresh()->status);
    }

    public function test_does_not_clobber_an_order_that_becomes_paid_after_being_loaded(): void
    {
        // Auditoría Fase 1 (M2): la carrera entre `orders:expire` y el handler C1 de Redsys. El
        // comando carga la Order pending+vencida; ANTES del UPDATE por-fila, una autorización tardía
        // la marca paid (C1). El UPDATE CONDICIONADO (status=pending) debe ser un no-op: la Order
        // sigue paid (cobro real, reembolsable), no la pisa a expired. Inyectamos el cambio de estado
        // en el evento `retrieved` (no hay otros listeners de ese evento → forget seguro).
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-RACE',
            'status' => Order::STATUS_PENDING, 'expires_at' => now()->subMinutes(5),
        ]);

        $dispatcher = Order::getEventDispatcher();
        $event = 'eloquent.retrieved: '.Order::class;
        $flipped = false;
        $dispatcher->listen($event, function (Order $model) use ($order, &$flipped): void {
            if (! $flipped && $model->id === $order->id) {
                $flipped = true;
                // C1 gana la carrera: la Order pasa a paid (UPDATE directo: no re-dispara el evento).
                Order::whereKey($model->id)->update([
                    'status' => Order::STATUS_PAID, 'paid_at' => now(), 'expires_at' => null,
                ]);
            }
        });

        try {
            $this->artisan('orders:expire')->assertSuccessful();
        } finally {
            $dispatcher->forget($event);
        }

        $this->assertTrue($flipped, 'el evento retrieved debió dispararse (sanity del test)');
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status, 'NO debe pisarse a expired un cobro real');
    }

    public function test_sends_email_when_order_with_pending_payment_expires(): void
    {
        // Audit #114 G2: Order pending + Payment pending (cliente intentó pagar pero la
        // notificación nunca llegó / cerró la pestaña en la pasarela). orders:expire la
        // caduca + envía email para que el cliente sepa Y tenga un canal de reclamación
        // si en realidad pagó.
        Notification::fake();
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-G2P',
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->subMinutes(5),
        ]);
        Payment::create([
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'provider' => 'redsys',
            'amount' => 1000,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'gateway_order' => '8888000111',
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(Order::STATUS_EXPIRED, $order->refresh()->status);
        Notification::assertSentTo($user, OrderExpiredWithoutPayment::class);
    }

    public function test_does_not_send_email_when_order_had_no_payment_attempt(): void
    {
        // Order pending sin Payment = abandono limpio antes de la pasarela (o reserva
        // provisional #76 que el usuario no verificó). NO debe enviar email — sería ruido.
        Notification::fake();
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-G2N',
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(Order::STATUS_EXPIRED, $order->refresh()->status);
        Notification::assertNotSentTo($user, OrderExpiredWithoutPayment::class);
    }

    public function test_does_not_send_email_when_payment_was_already_failed(): void
    {
        // El cliente ya recibió `OrderPaymentDeclined` (G1) al denegarse el pago.
        // Enviar también `OrderExpiredWithoutPayment` sería doble email confuso.
        Notification::fake();
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-G2F',
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->subMinutes(5),
        ]);
        Payment::create([
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'provider' => 'redsys',
            'amount' => 1000,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'gateway_order' => '8888000222',
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(Order::STATUS_EXPIRED, $order->refresh()->status);
        Notification::assertNotSentTo($user, OrderExpiredWithoutPayment::class);
    }
}
