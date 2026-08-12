<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CalendarPage;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pulidos #179 sobre la lista/detalle de pedidos:
 *  - #4 «Pagado» real: `Order::amountCollectedCents()` + agregado de la tabla.
 *  - #3 fila clicable al detalle (recordUrl) + columna «Pagado» en la lista.
 *  - #1 icono de calendario en la card del producto → día de la reserva, y la
 *    página del calendario respeta `?date=`.
 */
class OrdersPolish179Test extends TestCase
{
    use RefreshDatabase;

    private Zone $jump;

    private TicketType $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'color' => '#FF5B22']);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1 hora'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->jump->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function order(string $code, string $status = Order::STATUS_PAID, int $total = 1000): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id, 'code' => $code,
            'status' => $status, 'subtotal' => $total, 'tax' => 0, 'total' => $total,
            'currency' => 'EUR', 'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);
    }

    private function payment(Order $order, int $amount, string $status = Payment::STATUS_PAID): Payment
    {
        return Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => $amount, 'currency' => 'EUR',
            'status' => $status, 'gateway_order' => '0000'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
        ]);
    }

    private function refund(Payment $payment, int $cents, string $status = PaymentRefund::STATUS_SUCCEEDED): PaymentRefund
    {
        return PaymentRefund::create([
            'payment_id' => $payment->id, 'order_item_id' => null,
            'amount_cents' => $cents, 'currency' => 'EUR', 'status' => $status,
            'mode' => PaymentRefund::MODE_REST, 'requested_by' => User::factory()->create()->id,
            'requested_at' => now(), 'processed_at' => now(),
        ]);
    }

    private function itemWithSlot(Order $order, string $date): OrderItem
    {
        $slot = Slot::create([
            'zone_id' => $this->jump->id, 'date' => $date,
            'start_time' => '17:00:00', 'end_time' => '18:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->entry->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => $order->total,
        ]);
    }

    // ─── #4: amountCollectedCents (por pedido) ────────────────────────────

    public function test_collected_is_full_amount_for_paid(): void
    {
        $order = $this->order('JJ-COL01');
        $this->payment($order, 1000);

        $this->assertSame(1000, $order->fresh()->load('payments.refunds')->amountCollectedCents());
    }

    public function test_collected_is_net_of_succeeded_refunds(): void
    {
        $order = $this->order('JJ-COL02');
        $payment = $this->payment($order, 1000);
        $this->refund($payment, 300); // succeeded
        $this->refund($payment, 999, PaymentRefund::STATUS_FAILED); // no cuenta

        $this->assertSame(700, $order->fresh()->load('payments.refunds')->amountCollectedCents());
    }

    public function test_collected_is_zero_without_successful_payment(): void
    {
        $order = $this->order('JJ-COL03', Order::STATUS_PENDING);
        $this->payment($order, 1000, Payment::STATUS_PENDING); // no pagado

        $this->assertSame(0, $order->fresh()->load('payments.refunds')->amountCollectedCents());
    }

    public function test_collected_never_negative(): void
    {
        $order = $this->order('JJ-COL04');
        $payment = $this->payment($order, 1000);
        $this->refund($payment, 5000); // refund absurdo > cobrado

        $this->assertSame(0, $order->fresh()->load('payments.refunds')->amountCollectedCents());
    }

    // ─── #2 (pulido): el Total refleja el "Total con cambios" ─────────────

    public function test_total_with_changes_includes_extra_due_and_refunds(): void
    {
        $order = $this->order('JJ-TWC1', Order::STATUS_PAID, 33380); // 333,80 €
        OrderAdjustment::create([ // complemento añadido, a cobrar en puerta (+40,00)
            'order_id' => $order->id, 'order_item_id' => null,
            'type' => OrderAdjustment::TYPE_EXTRA_DUE, 'amount_cents' => 4000,
            'currency' => 'EUR', 'reason' => 'item_edit', 'context' => [],
            'applied_by' => User::factory()->create()->id,
        ]);

        // 33380 + 4000 − 0 = 37380.
        $this->assertSame(37380, $order->fresh()->load('adjustments')->totalWithChangesCents());
    }

    // ─── #2 + #3: la lista muestra el Total REAL y enlaza al detalle ───────

    public function test_list_shows_real_total_pagado_and_row_links(): void
    {
        // Escenario de la clienta: principal pagado 333,80 online + complemento añadido
        // en gestión a cobrar en puerta (+40,00) → **Valor final 373,80, Pagado online
        // 333,80** (#200, fidedigno con el bloque valor-primero del detalle).
        $order = $this->order('JJ-LIST1', Order::STATUS_PAID, 33380);
        $this->payment($order, 33380);
        $principal = $this->itemWithSlot($order, now()->addDays(5)->toDateString()); // charged 33380
        $addon = OrderItem::create([ // complemento añadido en gestión (extra_due, a cobrar en puerta)
            'order_id' => $order->id, 'parent_item_id' => $principal->id,
            'ticket_type_id' => $this->entry->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 4000,
        ]);
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $addon->id,
            'type' => OrderAdjustment::TYPE_EXTRA_DUE, 'amount_cents' => 4000,
            'currency' => 'EUR', 'reason' => 'item_edit', 'context' => [],
            'applied_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($this->staff())
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee(__('admin.orders.col_collected'))                     // columna «Pagado»
            ->assertSee('373,80')                                             // Valor final (Total)
            ->assertSee('333,80')                                             // Pagado online
            ->assertSee(OrderResource::getUrl('view', ['record' => $order])); // fila → detalle (recordUrl)
    }

    public function test_list_total_pagado_match_value_first_breakdown(): void
    {
        // Ejemplo REAL de la clienta: pagó 288,00 € online; valor final 216,00 € =
        // 144,00 € online + 72,00 € a cobrar en el parque; 144,00 € pendientes de
        // devolución. La lista debe ser fidedigna con el bloque del detalle:
        // Total = 216,00 (valor final), Pagado = 144,00 (online que respalda productos).
        $order = $this->order('JJ-VF1', Order::STATUS_PAID, 28800);
        $this->payment($order, 28800);
        $item = $this->itemWithSlot($order, now()->addDays(6)->toDateString());
        $item->forceFill(['unit_price' => 1800, 'quantity' => 12])->save(); // charged 21600 = 216,00
        $order->applyExtraDue($item->fresh(), 7200, $this->staff(), 'item_edit',
            ['changes' => ['quantity_change' => ['old' => 8, 'new' => 12]]]); // +72,00 a cobrar

        $this->actingAs($this->staff())
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('216,00')        // Total = Valor final
            ->assertSee('144,00')        // Pagado = online que respalda productos
            ->assertDontSee('360,00');   // ya NO el "Total con cambios" bruto
    }

    public function test_list_pagado_is_zero_for_pending_order(): void
    {
        // Un pedido pendiente (sin cobro) muestra Pagado 0,00 y Total = su valor.
        $order = $this->order('JJ-PEND1', Order::STATUS_PENDING, 5000);
        $this->itemWithSlot($order, now()->addDays(7)->toDateString()); // charged 5000

        $this->actingAs($this->staff())
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('50,00')   // Total = valor del pedido
            ->assertSee('0,00');   // Pagado = 0 (sin cobro)
    }

    // ─── #1: icono de calendario en la card → día de la reserva ───────────

    public function test_view_order_item_card_links_to_calendar_day(): void
    {
        $order = $this->order('JJ-CAL178');
        $this->itemWithSlot($order, '2026-09-15');

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(CalendarPage::getUrl(['date' => '2026-09-15']));
    }

    public function test_calendar_link_requires_calendar_view_permission(): void
    {
        $order = $this->order('JJ-NOCAL');
        $this->itemWithSlot($order, '2026-09-15');
        $url = CalendarPage::getUrl(['date' => '2026-09-15']);

        // Con calendar.view (staff por defecto): el enlace aparece.
        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee($url);

        // Sin calendar.view (rol staff sin ese permiso): el enlace NO aparece,
        // aunque el usuario siga teniendo orders.view para abrir el pedido.
        $perm = Permission::where('name', 'calendar.view')->value('id');
        Role::where('name', 'staff')->first()->permissions()->detach($perm);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertDontSee($url);
    }

    public function test_calendar_page_honours_date_query_param(): void
    {
        $staff = $this->staff();

        // Con ?date → vista de día anclada a esa fecha.
        $this->actingAs($staff)
            ->get('/admin/calendario?date=2026-09-15')
            ->assertOk()
            ->assertSee('2026-09-15')
            ->assertSee('timeGridDay');

        // Sin ?date → vista de mes (default).
        $this->actingAs($staff)
            ->get('/admin/calendario')
            ->assertOk()
            ->assertSee('dayGridMonth');
    }
}
