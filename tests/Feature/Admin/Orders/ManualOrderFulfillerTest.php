<?php

namespace Tests\Feature\Admin\Orders;

use App\Exceptions\ReservationException;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\OrderConfirmation;
use App\Support\ManualOrderFulfiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 7.3 (iteración 1) — pedido manual cobrado al momento (efectivo/datáfono).
 * El orquestador reutiliza `OrderCreator` (validación + aforo) y `TicketIssuer`, y deja la
 * Order pagada con tickets emitidos + email de confirmación al cliente + auditoría.
 */
class ManualOrderFulfillerTest extends TestCase
{
    use RefreshDatabase;

    private ManualOrderFulfiller $fulfiller;

    private User $staff;

    private User $customer;

    private Zone $zone;

    private TicketType $h1;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fulfiller = app(ManualOrderFulfiller::class);
        $this->staff = User::factory()->create();
        $this->customer = User::factory()->create();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $this->date,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'capacity' => 10,
            'online_capacity' => 10,
        ]);

        $this->h1 = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'],
            'zone_id' => $this->zone->id,
            'duration_min' => 60,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 1,
        ]);
        $this->h1->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'),
            'amount_cents' => 1000,
        ]);
    }

    /** @return array{ticket_type_id:int, date:string, time:string, qty:int} */
    private function line(int $qty, ?int $typeId = null): array
    {
        return ['ticket_type_id' => $typeId ?? $this->h1->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => $qty];
    }

    public function test_cash_creates_a_paid_order_with_tickets_confirmation_and_audit(): void
    {
        Notification::fake();
        $this->actingAs($this->staff);

        $order = $this->fulfiller->fulfill($this->customer, [$this->line(2)], ManualOrderFulfiller::METHOD_CASH);

        // Order pagada, firme (sin caducidad) y del cliente.
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNull($order->expires_at);
        $this->assertSame($this->customer->id, $order->user_id);
        $this->assertSame(2000, $order->total);

        // Payment efectivo, pagado, por el importe total.
        $payment = $order->payments()->first();
        $this->assertSame('cash', $payment->provider);
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(2000, $payment->amount);
        $this->assertNotNull($payment->paid_at);

        // Un ticket por plaza (seats = 2), todos comprados con qr_token.
        $tickets = Ticket::where('order_id', $order->id)->get();
        $this->assertCount(2, $tickets);
        $this->assertTrue($tickets->every(fn (Ticket $t) => $t->status === Ticket::STATUS_PURCHASED && strlen((string) $t->qr_token) === 32));

        // Email de confirmación al cliente.
        Notification::assertSentTo($this->customer, OrderConfirmation::class);

        // Auditoría: acción, operador y datos clave.
        $log = AuditLog::where('action', 'orders.created_manual')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->staff->id, $log->user_id);
        $this->assertSame('cash', $log->payload['method']);
        $this->assertSame($this->customer->id, $log->payload['customer_id']);
        $this->assertSame($order->code, $log->payload['order_code']);
    }

    public function test_datafono_records_the_provider_as_datafono(): void
    {
        Notification::fake();
        $this->actingAs($this->staff);

        $order = $this->fulfiller->fulfill($this->customer, [$this->line(1)], ManualOrderFulfiller::METHOD_DATAFONO);

        $this->assertSame('datafono', $order->payments()->first()->provider);
        $this->assertSame(Order::STATUS_PAID, $order->status);
    }

    public function test_full_slot_throws_and_creates_nothing(): void
    {
        Notification::fake();
        // Ocupa la franja por completo (aforo 10).
        $blocker = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
        ]);
        $slot = Slot::where('zone_id', $this->zone->id)->where('date', $this->date)->first();
        $blocker->items()->create([
            'ticket_type_id' => $this->h1->id, 'slot_id' => $slot->id,
            'quantity' => 10, 'unit_price' => 1000, 'seats' => 10,
        ]);

        try {
            $this->fulfiller->fulfill($this->customer, [$this->line(1)], ManualOrderFulfiller::METHOD_CASH);
            $this->fail('Expected ReservationException');
        } catch (ReservationException) {
            // esperado
        }

        // Nada creado para el cliente (transacción intacta) y ningún email/ticket.
        $this->assertSame(0, Order::where('user_id', $this->customer->id)->count());
        $this->assertSame(0, Payment::whereIn('payable_id', Order::where('user_id', $this->customer->id)->pluck('id'))->count());
        Notification::assertNothingSent();
    }

    public function test_unsupported_method_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->fulfiller->fulfill($this->customer, [$this->line(1)], 'redsys');
    }

    public function test_addons_do_not_emit_tickets(): void
    {
        Notification::fake();
        $this->actingAs($this->staff);

        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 50,
        ]);
        $addon->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 200]);
        \DB::table('product_addons')->insert(['product_id' => $this->h1->id, 'addon_id' => $addon->id, 'position' => 0]);

        $order = $this->fulfiller->fulfill($this->customer, [[
            'ticket_type_id' => $this->h1->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1,
            'addons' => [['ticket_type_id' => $addon->id, 'qty' => 1]],
        ]], ManualOrderFulfiller::METHOD_CASH);

        // 1 entrada (1 plaza) → 1 ticket; el complemento (sin franja) no emite ticket.
        $this->assertCount(1, Ticket::where('order_id', $order->id)->get());
        $this->assertSame(1200, $order->total); // 1000 + 200
    }
}
