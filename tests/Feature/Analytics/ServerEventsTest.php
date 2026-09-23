<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **LOS HECHOS DEL SERVIDOR, cada uno desde su fuente REAL** (`docs/specs/analitica.md` §4.1, §6;
 * `DECISIONES #678`; la revisión adversarial, §7.1: dinero-1, dinero-2, dinero-6, medicion-1).
 *
 * Lo que se rompería EN SILENCIO y esto sostiene: la caducidad se escribe por UPDATE de query builder y
 * ningún observador la ve (la registra `ExpireOrders`) · el rechazo del banco vive en `Payment`, no en el
 * pedido · la devolución es una fila de `payment_refunds` con SU importe · un cobro tardío sin tickets es
 * INCIDENCIA, no compra · dos `save()` en una transacción son dos hechos · y **la analítica nunca tumba a
 * quien la llama**: con la tabla del libro rota, el pago queda pagado y la llamada termina sin excepción.
 */
class ServerEventsTest extends TestCase
{
    use RefreshDatabase;

    private function pedido(string $code = 'JJ-HECHO', string $status = Order::STATUS_PENDING, ?User $user = null): Order
    {
        return Order::create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'code' => $code,
            'status' => $status,
            'total' => 4000,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    private function pago(Order $order, string $status, int $amount = 1000): Payment
    {
        return Payment::create([
            'payable_type' => (new Order)->getMorphClass(),
            'payable_id' => $order->id,
            'provider' => 'redsys',
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => $status,
            'gateway_order' => Str::random(10),
        ]);
    }

    private function conTickets(Order $order): void
    {
        $zone = Zone::create(['slug' => 'z-'.Str::lower(Str::random(5)), 'name' => ['es' => 'Zona']]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => now()->addDays(7)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);
        $type = TicketType::create(['name' => ['es' => 'Jump'], 'zone_id' => $zone->id, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]);

        Ticket::create(['order_id' => $order->id, 'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'qr_token' => Str::random(32), 'status' => Ticket::STATUS_PURCHASED]);
    }

    /** @return list<string> */
    private function hechos(Order $order): array
    {
        return AnalyticsEvent::query()->where('order_id', $order->id)->orderBy('id')->pluck('name')->all();
    }

    public function test_a_new_order_is_a_fact_with_its_total_and_channel(): void
    {
        $order = $this->pedido();

        $event = AnalyticsEvent::query()->where('name', 'order_created')->sole();
        $this->assertSame($order->id, $event->order_id);
        $this->assertSame(['total_cents' => 4000, 'channel' => 'system'], $event->props);
        // Régimen agregado: sin visitante ni persona.
        $this->assertNull($event->visitor_id);
        $this->assertNull($event->user_id);
    }

    /** ❗ La caducidad es un UPDATE de query builder: la registra `ExpireOrders`, no un observador (spec §7.1, dinero-2). */
    public function test_the_expiry_command_records_each_expired_order(): void
    {
        $vencido = $this->pedido('JJ-VENCIDO');
        $vencido->forceFill(['expires_at' => now()->subMinutes(5)])->saveQuietly();
        $vivo = $this->pedido('JJ-VIVO');

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(['order_created', 'order_expired'], $this->hechos($vencido));
        $this->assertSame(['order_created'], $this->hechos($vivo));
    }

    /** El rechazo del banco NO es una transición del pedido: vive en `Payment` (spec §7.1, dinero-2). */
    public function test_a_declined_payment_is_a_fact_of_the_order(): void
    {
        $order = $this->pedido();
        $payment = $this->pago($order, Payment::STATUS_PENDING);

        $payment->forceFill(['status' => Payment::STATUS_FAILED])->save();

        $event = AnalyticsEvent::query()->where('name', 'order_declined')->sole();
        $this->assertSame($order->id, $event->order_id);
        $this->assertSame($payment->id, $event->payment_id);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    /** `paid_cents` es lo que ENTRÓ por la pasarela —la señal, si la hay—, no el total (spec §7.1, dinero-3). */
    public function test_a_paid_order_with_tickets_is_a_purchase_with_what_the_gateway_took(): void
    {
        $order = $this->pedido();
        $this->pago($order, Payment::STATUS_PAID, amount: 1000);
        $this->conTickets($order);

        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();

        $event = AnalyticsEvent::query()->where('name', 'order_paid')->sole();
        $this->assertSame(['paid_cents' => 1000, 'total_cents' => 4000, 'channel' => 'system'], $event->props);
        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'order_paid_incident')->count());
    }

    /** ⚠️ Un cobro tardío SIN tickets es la incidencia de `PAY-02`, no una compra (spec §7.1, dinero-6). */
    public function test_a_late_capture_without_tickets_is_an_incident_not_a_purchase(): void
    {
        $order = $this->pedido();
        $this->pago($order, Payment::STATUS_PAID, amount: 1000);

        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();

        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'order_paid')->count(), 'una incidencia se contó como compra');
        $incident = AnalyticsEvent::query()->where('name', 'order_paid_incident')->sole();
        $this->assertSame(['kind' => 'late_capture', 'paid_cents' => 1000, 'channel' => 'system'], $incident->props);
    }

    /** La devolución es una fila con SU importe, nunca el agregado ni el total (spec §7.1, dinero-2, dinero-3). */
    public function test_a_succeeded_refund_is_a_fact_with_its_own_amount(): void
    {
        $order = $this->pedido('JJ-DEV', Order::STATUS_PAID);
        $payment = $this->pago($order, Payment::STATUS_PAID, amount: 4000);
        $refund = PaymentRefund::create([
            'payment_id' => $payment->id, 'amount_cents' => 1200, 'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_PENDING, 'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order, 'requested_by' => $order->user_id, 'requested_at' => now(),
        ]);

        $refund->forceFill(['status' => PaymentRefund::STATUS_SUCCEEDED])->save();

        $event = AnalyticsEvent::query()->where('name', 'order_refunded')->sole();
        $this->assertSame($order->id, $event->order_id);
        $this->assertSame($refund->id, $event->refund_id);
        $this->assertSame(['refunded_cents' => 1200], $event->props);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status, 'el reembolso no es un estado del pedido');
    }

    /**
     * ⚠️ **Dos `save()` en una transacción son DOS hechos** (spec §7.1, dinero-1): capturados en `saving`
     * como escalares, no leídos del modelo en un callback diferido, que ya solo vería el último.
     */
    public function test_two_saves_in_one_transaction_are_two_facts(): void
    {
        $order = $this->pedido('JJ-DOS', Order::STATUS_PAID);

        DB::transaction(function () use ($order): void {
            $order->status = Order::STATUS_CANCELLED;
            $order->save();
            $order->refunded_at = now();
            $order->refund_amount_cents = 4000;
            $order->save();
        });

        $this->assertSame(['order_created', 'order_cancelled'], $this->hechos($order));
    }

    /** El único `save()` a `expired` es un fallo nuestro al abrir el cobro, no un abandono (spec §7.1, dinero-2). */
    public function test_releasing_after_a_failed_payment_start_is_not_an_expiry(): void
    {
        $order = $this->pedido('JJ-INIT');

        $order->releaseAfterFailedPaymentStart();

        $this->assertSame(['order_created', 'order_payment_init_failed'], $this->hechos($order));
        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'order_expired')->count());
    }

    /**
     * ❗❗ **La analítica NUNCA tumba un pago.** Con la tabla del libro rota, el pedido queda pagado y la
     * llamada termina sin excepción: el callback diferido traga el error (un `throw` tras el commit
     * propagaría con el pago ya confirmado, spec §7.1, dinero-5).
     */
    public function test_a_broken_book_never_reaches_the_money(): void
    {
        $order = $this->pedido('JJ-ROTO');
        $this->pago($order, Payment::STATUS_PAID, amount: 1000);
        $this->conTickets($order);
        Log::shouldReceive('warning')->atLeast()->once()->withArgs(fn (string $m): bool => $m === 'analytics.record_failed');
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Schema::drop('analytics_events');

        DB::transaction(fn () => $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save());

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_a_login_is_a_fact_unless_it_is_the_team(): void
    {
        $cliente = User::factory()->create();
        $operador = User::factory()->create();
        $operador->roles()->attach(Role::firstOrCreate(['name' => 'staff'], ['label' => 'Staff']));

        Auth::login($cliente);
        Auth::logout();
        Auth::login($operador);

        $logins = AnalyticsEvent::query()->where('name', 'user_logged_in')->get();
        $this->assertCount(1, $logins);
        $this->assertSame($cliente->id, $logins[0]->user_id);
    }
}
