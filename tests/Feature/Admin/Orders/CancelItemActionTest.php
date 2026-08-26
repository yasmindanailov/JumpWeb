<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderItemCanceller;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\OrderItemCancelled;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sub-fase 7.2e.1bis — `ViewOrder::cancelItemAction()` (decisión #154).
 *
 * Cancelar item ahora SOLO marca soft-cancel — no toca Redsys. Si procede
 * devolución, el operador la dispara por separado con `refundItem` (que ahora
 * acepta items cancelados como input).
 *
 * Cubre:
 *  - Visibility por permiso.
 *  - mountUsing valida ownership + bloqueos antes de abrir modal.
 *  - Cancel happy path: soft-cancel + email OrderItemCancelled + audit + sin Redsys.
 *  - Optimistic lock + handler revalidate.
 *  - Bloqueos varios (addon, order no operational, etc.).
 *  - Cancel idempotente: re-llamar a un item ya cancelado no rompe ni duplica audit.
 */
class CancelItemActionTest extends TestCase
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

    public function test_cancel_item_visible_with_permission(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);

        Livewire::actingAs($this->staffWith(['orders.view', 'orders.cancel_item']))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('cancelItem');
    }

    public function test_cancel_item_hidden_without_permission(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);

        Livewire::actingAs($this->staffWith(['orders.view']))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('cancelItem');
    }

    public function test_mount_blocked_for_item_from_another_order(): void
    {
        $orderA = $this->makePaidOrder(code: 'JJ-IDOR001');
        $orderB = $this->makePaidOrder(code: 'JJ-IDOR002');
        $this->attachPaidPayment($orderA);
        $this->attachPaidPayment($orderB);
        $itemOfB = $this->attachActiveItem($orderB);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $orderA->code])
            ->mountAction('cancelItem', ['item' => $itemOfB->id])
            ->assertActionHalted('cancelItem');
    }

    public function test_mount_blocked_for_addon_item(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $parent = $this->attachActiveItem($order);
        $addon = $this->attachAddon($order, $parent);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('cancelItem', ['item' => $addon->id])
            ->assertActionHalted('cancelItem');
    }

    public function test_cancel_item_happy_path_soft_cancels_and_emails_without_redsys(): void
    {
        Notification::fake();
        Http::fake();  // si llamamos a Redsys, falla — cancelItem NO debe tocarlo.
        $admin = $this->admin();
        $order = $this->makePaidOrder(2400);
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancelItem',
                data: [],  // no Radio mode — el modal de cancel no lo lleva
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        // NO se contactó con Redsys (cancelar es solo soft-cancel).
        Http::assertNothingSent();

        // Item soft-cancelled con autor.
        $item->refresh();
        $this->assertNotNull($item->cancelled_at);
        $this->assertSame($admin->id, (int) $item->cancelled_by);

        // NO se creó PaymentRefund (no hubo refund).
        $this->assertSame(0, PaymentRefund::where('order_item_id', $item->id)->count());

        // Order NO modificada (refunded_at sigue null, status sigue paid).
        $order->refresh();
        $this->assertNull($order->refunded_at);
        $this->assertSame(Order::STATUS_PAID, $order->status);

        // Audit log de cancelación (no de refund).
        $log = AuditLog::where('action', 'orders.item_cancelled')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($item->id, $log->payload['order_item_id']);

        // Email OrderItemCancelled (no OrderItemRefunded).
        Notification::assertSentTo(
            $order->user,
            OrderItemCancelled::class,
            fn (OrderItemCancelled $n): bool => $n->item->id === $item->id,
        );
    }

    public function test_cancel_cascades_to_children_complementos(): void
    {
        // Sub-fase 7.2e.1bis4 (decisión #157): cancelar el producto principal
        // CASCADEA a todos sus complementos. Operativamente: no tiene sentido
        // "cancelar el cumpleaños pero mantener la tarta". La gestión per-
        // complemento individual vendrá en 7.2e.4 (Tab Complementos del
        // modal Gestionar). El email cita explícitamente la cascada.
        Notification::fake();
        Http::fake();  // Cancel NO toca Redsys.
        $admin = $this->admin();
        $order = $this->makePaidOrder(2400);
        $this->attachPaidPayment($order);
        $pack = $this->attachActiveItem($order, unitPrice: 1500);
        $addonA = $this->attachAddon($order, $pack);
        $addonB = $this->attachAddon($order, $pack);
        $addonC = $this->attachAddon($order, $pack);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancelItem',
                data: [],
                arguments: ['item' => $pack->id],
            )
            ->assertHasNoActionErrors();

        Http::assertNothingSent();  // Cancel no toca Redsys.

        // Pack + 3 addons todos cancelled.
        $this->assertTrue($pack->fresh()->isCancelled());
        $this->assertTrue($addonA->fresh()->isCancelled());
        $this->assertTrue($addonB->fresh()->isCancelled());
        $this->assertTrue($addonC->fresh()->isCancelled());

        // Audit log con cascaded_child_ids reflejando los 3 addons.
        $log = AuditLog::where('action', 'orders.item_cancelled')->latest()->first();
        $this->assertNotNull($log);
        $this->assertCount(3, $log->payload['cascaded_child_ids']);

        // Email único OrderItemCancelled con cascadedChildrenCount = 3.
        Notification::assertSentTo(
            $order->user,
            OrderItemCancelled::class,
            fn ($n): bool => $n->cascadedChildrenCount === 3,
        );
    }

    public function test_cancel_without_children_does_not_cascade(): void
    {
        // Item sin complementos: cancel solo afecta al principal. Email sin
        // mención de cascada (cascadedChildrenCount = 0).
        Notification::fake();
        Http::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancelItem',
                data: [],
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $this->assertTrue($item->fresh()->isCancelled());
        $log = AuditLog::where('action', 'orders.item_cancelled')->latest()->first();
        $this->assertSame([], $log->payload['cascaded_child_ids']);

        Notification::assertSentTo(
            $order->user,
            OrderItemCancelled::class,
            fn ($n): bool => $n->cascadedChildrenCount === 0,
        );
    }

    public function test_handler_logs_blocked_when_item_ajeno_submitted(): void
    {
        // Capa 4: si el atacante manipula el item_id para apuntar a otro Order,
        // el handler bloquea Y loguea (capa 4 sí persiste, fuera de la txn de
        // mountUsing).
        $orderA = $this->makePaidOrder(code: 'JJ-IDORH01');
        $orderB = $this->makePaidOrder(code: 'JJ-IDORH02');
        $this->attachPaidPayment($orderA);
        $itemOfB = $this->attachActiveItem($orderB);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $orderA->code])
            ->callAction('cancelItem',
                data: [],
                arguments: ['item' => $itemOfB->id],
            );

        $log = AuditLog::where('action', 'orders.item_cancel_blocked')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('not_in_order', $log->payload['reason']);
    }

    public function test_stale_optimistic_token_blocks_cancellation(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancelItem',
                data: ['optimistic_token' => 'STALE_TOKEN'],
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertNull($item->cancelled_at);

        $log = AuditLog::where('action', 'orders.item_cancel_blocked')->latest()->first();
        $this->assertSame('stale_item_version', $log->payload['reason']);

        Notification::assertNothingSent();
    }

    /**
     * `SEC-04`: el permiso `orders.cancel_item` se re-exige en el punto de ejecución, en el
     * servicio. Desde la página es inalcanzable (la acción filtra por `visible()` en cada petición y
     * Filament no monta una acción oculta): solo se mide llamando a `OrderItemCanceller` — con un
     * staff sin ese permiso y con nadie autenticado. Ganó su test en la extracción 4b.
     */
    public function test_canceller_requires_the_permission_at_execution_time(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);
        $token = (string) $item->updated_at->getTimestamp();

        $asViewer = app(OrderItemCanceller::class)->cancel($order, $item, $token, $this->staffWith(['orders.view']));
        $anonymous = app(OrderItemCanceller::class)->cancel($order, $item, $token, null);

        $this->assertSame('permission_denied', $asViewer->reason);
        $this->assertSame('permission_denied', $anonymous->reason);
        $this->assertNull($item->fresh()->cancelled_at);
        $this->assertNull(AuditLog::where('action', 'orders.item_cancelled')->first());
        Notification::assertNothingSent();
    }

    public function test_cancellation_is_idempotent_on_double_click(): void
    {
        // Si dos clicks llegan al backend (poco probable con Livewire pero
        // defensivo), `markCancelled` preserva el timestamp original y NO
        // duplica el audit log.
        Notification::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        // Primer click: cancela normalmente.
        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancelItem',
                data: [],
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $firstCancelledAt = $item->cancelled_at->copy();

        // Segundo click: el handler revalida `cancelItemBlockedReason` con
        // fresh y bloquea por `item_cancelled` (capa 4). No duplica nada.
        // NOTA: con la nueva semántica el item ya tiene optimistic_token
        // distinto al primer call (updated_at cambió), así que el bloqueo
        // por stale_item_version pega también. Ambos son válidos.
        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancelItem',
                data: [],
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        // El timestamp original NO se sobrescribe.
        $this->assertEquals(
            $firstCancelledAt->toDateTimeString(),
            $item->cancelled_at->toDateTimeString(),
        );

        // Solo 1 evento de cancelación en audit (la 2ª llamada genera bloqueo).
        $this->assertSame(
            1,
            AuditLog::where('action', 'orders.item_cancelled')->count(),
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
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

    private function makePaidOrder(int $total = 1815, string $code = ''): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => $code !== '' ? $code : 'JJ-CI'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
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

    private function attachActiveItem(Order $order, int $quantity = 1, int $unitPrice = 1000): OrderItem
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
            'quantity' => $quantity, 'seats' => $quantity, 'unit_price' => $unitPrice,
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
