<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sub-fase 7.2e.1 — UI de la sub-card del item en `items-list.blade.php`.
 *
 * Verifica:
 *  - 2 controles renderizan cuando hay permisos + item activo + Order operativo:
 *    `Gestionar` (botón texto) + tic-checkbox preparado. #173: cancelar y
 *    reembolsar por-item ya NO son iconos de la sub-card — ambos viven como
 *    botones al pie del modal Gestionar (#171 reembolso, #172 cancelar).
 *  - Item cancelado renderiza visualmente atenuado con badge "Cancelado", meta
 *    "Cancelado el DD/MM/YYYY por X", precio tachado y SIN iconos de acción.
 *  - Banner "Order no operativa" se mantiene (sub-fase 7.1b) cuando Order
 *    pending/cancelled/expired/refunded/all_finished.
 */
class ItemsListSubCardTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private int $counter = 0;

    private int $paymentCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_renders_manage_control_for_active_item_with_full_permissions(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);

        $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            // Botón Gestionar — texto del label de admin.orders.item_detail.btn_open.
            ->assertSee(__('admin.orders.item_detail.btn_open'))
            // #173: cancelar y reembolsar por-item YA NO son iconos de la lista
            // (viven como botones al pie del modal Gestionar). La sub-card solo
            // tiene Gestionar (el toggle "Preparado" se retiró con el sistema, #202).
            ->assertDontSee("mountAction('cancelItem'", escape: false)
            ->assertDontSee("mountAction('refundItem'", escape: false);
    }

    public function test_cancel_icon_hidden_without_permission(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);

        $this->actingAs($this->staffWith([
            'orders.view', 'orders.refund_item',  // sin cancel_item
        ]))
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertDontSee("mountAction('cancelItem'", escape: false)
            // #171: el reembolso ya no se renderiza como icono en la lista.
            ->assertDontSee("mountAction('refundItem'", escape: false);
    }

    public function test_cancel_and_refund_icons_not_in_items_list(): void
    {
        // #171/#172/#173: ni cancelar ni reembolsar por-item se renderizan como
        // iconos en la sub-card; ambos viven al pie del modal Gestionar. Aun con
        // todos los permisos, la lista solo tiene Gestionar + toggle Preparado.
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);

        $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertDontSee("mountAction('cancelItem'", escape: false)
            ->assertDontSee("mountAction('refundItem'", escape: false)
            ->assertSee("mountAction('manageItem'", escape: false);
    }

    public function test_cancelled_item_renders_visually_attenuated_and_offers_refund_pending(): void
    {
        // Sub-fase 7.2e.1bis (decisión #154): un item CANCELADO sigue siendo
        // refundable (cancelar no implica refund). La sub-card:
        //  - Atenuada con badge "Cancelado" + meta + precio tachado.
        //  - SIN icono cancelar (ya está cancelado).
        //  - CON badge "Pendiente reembolso" amarillo (avisa al operador).
        //  - SIN toggle preparado.
        //  - CON botón Gestionar (consulta histórica + reembolso en su pie, #171).
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);
        $item->markCancelled($admin);

        $response = $this->actingAs($admin)
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        // Badge "Cancelado".
        $response->assertSee(__('admin.orders.item_status.cancelled'));
        // Meta de cancelación.
        $response->assertSee($admin->name);
        // Cancelar oculto (ya cancelado).
        $response->assertDontSee("mountAction('cancelItem', { item: {$item->id}", escape: false);
        // #171: el reembolso ya no es un icono aquí; vive en el pie del modal
        // Gestionar. El badge "Pendiente reembolso" (abajo) avisa de que queda
        // importe por devolver tras cancelar.
        $response->assertDontSee("mountAction('refundItem', { item: {$item->id}", escape: false);
        // Botón Gestionar mantiene visible.
        $response->assertSee(__('admin.orders.item_detail.btn_open'));
        // Visual tachado.
        $response->assertSee('line-through', escape: false);
        // Línea "Pendiente de devolución" presente (importe del item entero pendiente).
        // #198: la etiqueta se unificó a "Pendiente de devolución".
        $response->assertSee(__('admin.orders.item_financial.pending_refund_label'));
    }

    public function test_banner_appears_for_non_operational_order(): void
    {
        // Order cancelled → banner de coherencia Order ↔ Item específico
        // (`item_actions.banner.order_cancelled`): explica que las acciones
        // individuales (cancelar/reembolsar producto) están bloqueadas, para que
        // el operador entienda por qué no ve iconos en cada sub-card.
        $order = $this->makePaidOrder();
        $order->status = Order::STATUS_CANCELLED;
        $order->save();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        // Banner explicativo de coherencia Order cancelled → items bloqueados.
        $response->assertSee(__('admin.orders.item_actions.banner.order_cancelled'));

        // Iconos cancel/refund: cancelados todos porque Order cancelled bloquea
        // tanto `canCancelItem` (vía editItemBlockedReason → order_not_operational)
        // como `canRefundItem` (vía status !== paid).
        $response->assertDontSee("mountAction('cancelItem', { item: {$item->id}", escape: false);
        $response->assertDontSee("mountAction('refundItem', { item: {$item->id}", escape: false);
    }

    public function test_totals_section_renders_principal_addons_total_and_refund_badges(): void
    {
        // Sub-fase 7.2e.1bis2 (feedback 2026-05-30): bloque "Totales del
        // producto" abajo del item con principal + complementos + total +
        // badges financieros agregados (refunded + pending_refund).
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);
        $pack = $this->attachActiveItem($order);                          // 1200 €
        $addonA = $this->attachAddon($order, $pack);                       // 200 €
        $addonB = $this->attachAddon($order, $pack);                       // 200 €

        // Refund parcial del pack (50€) para que aparezca "Devuelto".
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $pack->id,
            'amount_cents' => 5000,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => '0900',
            'requested_by' => $admin->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        // Bloque de totales DESGLOSADO por línea (principal + cada complemento con su precio
        // unitario "cantidad × precio") + total.
        $response->assertSee(__('admin.orders.item_financial.principal'));
        $response->assertSee(__('admin.orders.item_financial.total'));

        // Importes: principal=12,00 €; cada complemento 1 × 2,00 € = 2,00 €; total=16,00 €.
        $response->assertSee('12,00 €');
        $response->assertSee('2,00 €');
        $response->assertSee('16,00 €');

        // Badge "Devuelto" con el importe (50€ = 50,00 €).
        $response->assertSee(__('admin.orders.item_financial.refunded_label'));
        $response->assertSee('−50,00 €', escape: false);
    }

    public function test_totals_section_shows_online_and_gate_split_for_gate_charge(): void
    {
        // Robustez del desglose (#196): al añadir un cargo de puerta (subir cantidad),
        // el "Total del producto" se desglosa en pagado online + a cobrar en el parque.
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);          // 1 × 12,00
        $item->forceFill(['quantity' => 2])->save();      // ahora vale 24,00
        $order->applyExtraDue($item->fresh(), 1200, $admin, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);

        $response = $this->actingAs($admin)->get('/admin/orders/'.$order->code)->assertOk();

        $response->assertSee(__('admin.orders.item_financial.paid_online'));
        $response->assertSee(__('admin.orders.item_financial.at_gate'));
        $response->assertSee('24,00 €'); // total del producto
        $response->assertSee('+12,00 €', escape: false); // a cobrar en el parque
    }

    public function test_totals_section_shows_deposit_remainder_breakdown_line(): void
    {
        // #225 F2: la card del producto desglosa «A cobrar en el parque» en sus ↳: aquí, una
        // reserva de la que solo se cobró la señal online muestra la línea «Resto de la señal».
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $order->forceFill(['subtotal' => 1200, 'total' => 1200])->save(); // = valor del item (sin pendiente devolución)
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);                          // valor 12,00
        // Señal de 2,00 cobrada online → 10,00 de resto a cobrar en el parque.
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => 1000, 'currency' => 'EUR', 'applied_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/admin/orders/'.$order->code)->assertOk();

        $response->assertSee(__('admin.orders.item_financial.at_gate'));               // agregado
        $response->assertSee(__('admin.orders.item_financial.deposit_remainder_line')); // ↳
        $response->assertSee(__('admin.orders.item_financial.paid_online'));            // señal cobrada
        $response->assertSee('+10,00 €', escape: false);                                // resto a puerta
        $response->assertSee('2,00 €');                                                 // señal online
    }

    public function test_addon_item_does_not_render_action_icons(): void
    {
        // Los addons (parent_item_id !== null) NO tienen iconos cancel/refund
        // propios. Decisión #127: se gestionan vía el parent.
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $parent = $this->attachActiveItem($order);
        $addon = $this->attachAddon($order, $parent);

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        // El addon se renderiza dentro del parent (no como item suelto), pero
        // si por algún bug del template apareciera con sus propios botones,
        // el wire:click apuntaría a su id. Verificamos que NO existe.
        $response->assertDontSee("mountAction('cancelItem', { item: {$addon->id} })", escape: false);
        $response->assertDontSee("mountAction('refundItem', { item: {$addon->id} })", escape: false);
    }

    public function test_voided_leftover_principal_is_hidden_from_items_list(): void
    {
        // #F11: un principal fantasma net-cero (producto gratuito 0€ cancelado) no
        // se pinta como sub-card — saldría como "0,00 € · Cancelado" y solo
        // confunde. El principal normal sí se muestra. Un cancelado con cargo real
        // (no net-cero) sigue mostrándose (cubierto por el test de cancelado).
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $normal = $this->attachActiveItem($order);   // 12,00 €, visible

        $this->ensureTicketTypeSetup();
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(8)->format('Y-m-d'),
            'start_time' => '21:00:00', 'end_time' => '21:59:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);
        $ghost = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 0, 'cancelled_at' => now(),
        ]);
        $this->assertTrue($order->fresh()->isVoidedLeftoverItem($ghost->fresh()));

        $response = $this->actingAs($admin)->get('/admin/orders/'.$order->code)->assertOk();

        // El normal renderiza su sub-card (Gestionar con su id); el fantasma no.
        $response->assertSee("mountAction('manageItem', { item: {$normal->id} })", escape: false);
        $response->assertDontSee("mountAction('manageItem', { item: {$ghost->id} })", escape: false);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function staffWithFullItemPermissions(): User
    {
        return $this->staffWith([
            'orders.view',
            'orders.cancel_item', 'orders.refund_item',
        ]);
    }

    private function staffWith(array $permissions): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id'),
        );

        return $u;
    }

    private function ensureTicketTypeSetup(): void
    {
        if (isset($this->jumpType)) {
            return;
        }
        RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0,
        ]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->jumpType = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function makePaidOrder(): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-UI'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 2400, 'tax' => 0, 'total' => 2400, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function attachPaidPayment(Order $order): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'gateway_order' => str_pad((string) (++$this->paymentCounter + 100000), 10, '0', STR_PAD_LEFT),
        ]);
    }

    private function attachActiveItem(Order $order): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $h = str_pad((string) ($this->counter++ % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 10, 'online_capacity' => 5,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1200,
        ]);
    }

    private function attachAddon(Order $order, OrderItem $parent): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $parent->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
        ]);
    }
}
