<?php

namespace Tests\Feature\Account;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.1b — Badge "Finalizado" en "Mis pedidos" del cliente (decisión #127).
 *
 * El cliente NO ve "preparado/sin preparar" (operativa interna); solo ve si el
 * producto ya finalizó (slot pasado) o sigue activo.
 */
class OrdersFinishedBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump1h;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function makeSlot(string $date, string $startTime = '10:00:00', string $endTime = '11:00:00'): Slot
    {
        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => $startTime, 'end_time' => $endTime,
            'capacity' => 10, 'online_capacity' => 5,
        ]);
    }

    private function makeOrderWithItem(User $user, Slot $slot): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-F'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jump1h->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);

        return $order;
    }

    public function test_active_item_does_not_show_finished_badge(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeOrderWithItem($user, $this->makeSlot('2099-01-01'));

        $this->actingAs($user)
            ->get('/mi-cuenta/pedidos')
            ->assertOk()
            ->assertDontSee('Finalizado');
    }

    public function test_finished_item_shows_finished_badge(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeOrderWithItem($user, $this->makeSlot('2000-01-01', '10:00:00', '10:59:00'));

        $this->actingAs($user)
            ->get('/mi-cuenta/pedidos')
            ->assertOk()
            ->assertSeeText('Finalizado');
    }

    public function test_cancelled_and_refunded_addon_shows_both_badges(): void
    {
        // #172: un complemento cancelado Y reembolsado muestra AMBOS badges
        // ("Cancelado" + "Reembolsado") en la card del cliente, en un wrapper
        // alineado a la derecha (antes salía como activo, sin reflejar nada).
        $user = User::factory()->create(['email_verified_at' => now()]);
        $slot = $this->makeSlot('2099-01-01');
        $order = $this->makeOrderWithItem($user, $slot);
        $main = $order->items()->first();
        $addon = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $main->id,
            'ticket_type_id' => $this->jump1h->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
        ]);
        $addon->markCancelled(User::factory()->create());

        $payment = Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => '0000100001',
        ]);
        PaymentRefund::create([
            'payment_id' => $payment->id, 'order_item_id' => $addon->id,
            'amount_cents' => 200, 'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED, 'mode' => PaymentRefund::MODE_REST,
            'requested_by' => User::factory()->create()->id, 'requested_at' => now(), 'processed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/mi-cuenta/pedidos')->assertOk();

        $response->assertSeeText(__('account.orders.item_cancelled'));  // "Cancelado"
        $response->assertSeeText(__('tickets.refunded_badge'));         // "Reembolsado"
        $response->assertSee('orders__line-badges', escape: false);     // wrapper alineado a la dcha
    }
}
