<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Permission;
use App\Models\RateType;
use App\Models\Role;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\OrderItemRefunded;
use App\Support\Redsys;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sub-fase 7.2e.1bis — `ViewOrder::refundItemAction()` (decisión #154).
 *
 * Reembolsar item ahora usa CHECKBOXES — el operador marca qué items refundar
 * (el principal + sus complementos individualmente). Cada marcado → soft-cancel
 * + refund REST del importe completo del item. Sin importe libre.
 *
 * Cubre:
 *  - Visibility por permiso.
 *  - mountUsing valida ownership/bloqueos.
 *  - Items cancelados SÍ permiten refund posterior (decisión #154).
 *  - Items addon NO se ofrecen directamente como entrada del modal (van vía pack).
 *  - Single-item refund (item simple sin complementos).
 *  - Pack-only refund: auto-marca todos los complementos.
 *  - Pack con solo complementos seleccionados: pack queda activo.
 *  - Manual mode sin REST call.
 *  - Optimistic lock + capacity sentinel.
 *  - Selección vacía bloqueada.
 *  - Selección con item ajeno (manipulación form) bloqueada con audit.
 */
class RefundItemActionTest extends TestCase
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

    public function test_refund_item_visible_with_permission(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);

        Livewire::actingAs($this->staffWith(['orders.view', 'orders.refund_item']))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('refundItem');
    }

    public function test_refund_item_hidden_without_permission(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);

        Livewire::actingAs($this->staffWith(['orders.view']))
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('refundItem');
    }

    public function test_mount_allowed_for_cancelled_item(): void
    {
        // Decisión #154: items cancelados SÍ permiten refund posterior.
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);
        $item->markCancelled($admin);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('refundItem', ['item' => $item->id])
            ->assertActionMounted('refundItem');
    }

    public function test_mount_blocked_for_addon_item(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $parent = $this->attachActiveItem($order);
        $addon = $this->attachAddon($order, $parent);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('refundItem', ['item' => $addon->id])
            ->assertActionHalted('refundItem');
    }

    public function test_single_item_refund_succeeds_without_cancelling(): void
    {
        // Sub-fase 7.2e.1bis4 (decisión #157): refund desacoplado de cancel.
        // El item refundado sigue ACTIVO (no cancelled) — el cliente puede
        // seguir disfrutando del servicio aunque le hayan devuelto dinero
        // (caso típico: refund de cortesía sin cancelación).
        Notification::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ),
                200,
            ),
        ]);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => [$item->id],
                ],
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        // Importe enviado a Redsys = item completo (1200).
        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $decoded = app(Redsys::class)->decodeMerchantParameters($body['Ds_MerchantParameters']);

            return ($decoded['DS_MERCHANT_AMOUNT'] ?? null) === '1200';
        });

        // Refund creado, pero item NO cancelado.
        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->first();
        $this->assertSame(PaymentRefund::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame(1200, (int) $refund->amount_cents);
        $this->assertFalse($item->fresh()->isCancelled());

        // Email enviado SIN línea de cancelación.
        Notification::assertSentTo(
            $order->user,
            OrderItemRefunded::class,
            fn (OrderItemRefunded $n): bool => $n->alsoCancelledItem === false,
        );
    }

    public function test_pack_refund_propagates_to_complementos_without_cancelling(): void
    {
        // Sub-fase 7.2e.1bis4 (decisión #157): si el operador marca el pack
        // principal, los complementos del pack se auto-marcan server-side
        // (regla "refundar pack implica refundar su contenido financiero").
        // PERO ningún item queda cancelled — refund es solo dinero, no
        // estado del servicio. Si el operador quiere cancelar también,
        // usa el botón 🗑️ por separado (cancela con cascada).
        Notification::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $pack = $this->attachActiveItem($order, unitPrice: 1500);
        $addonA = $this->attachAddon($order, $pack, unitPrice: 500);
        $addonB = $this->attachAddon($order, $pack, unitPrice: 400);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ),
                200,
            ),
        ]);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => [$pack->id],
                ],
                arguments: ['item' => $pack->id],
            )
            ->assertHasNoActionErrors();

        // 3 refunds creados (pack + 2 addons auto-marcados).
        $this->assertSame(1, PaymentRefund::where('order_item_id', $pack->id)->count());
        $this->assertSame(1, PaymentRefund::where('order_item_id', $addonA->id)->count());
        $this->assertSame(1, PaymentRefund::where('order_item_id', $addonB->id)->count());

        // NINGÚN item cancelled — refund no toca el servicio.
        $this->assertFalse($pack->fresh()->isCancelled());
        $this->assertFalse($addonA->fresh()->isCancelled());
        $this->assertFalse($addonB->fresh()->isCancelled());

        // 3 emails al cliente (uno por refund), SIN línea de cancelación.
        Notification::assertSentToTimes($order->user, OrderItemRefunded::class, 3);
    }

    public function test_only_selected_complementos_refunded_all_stay_active(): void
    {
        // Sub-fase 7.2e.1bis4: operador marca solo un addon, NO el pack.
        // Solo ese addon se refunda. NINGÚN item cancelled — refund es
        // independiente de cancellation con la nueva semántica.
        Notification::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $pack = $this->attachActiveItem($order, unitPrice: 1500);
        $addonA = $this->attachAddon($order, $pack, unitPrice: 500);
        $addonB = $this->attachAddon($order, $pack, unitPrice: 400);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ),
                200,
            ),
        ]);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => [$addonA->id],
                ],
                arguments: ['item' => $pack->id],
            )
            ->assertHasNoActionErrors();

        // Solo addonA refundado, sin cancellation.
        $this->assertSame(0, PaymentRefund::where('order_item_id', $pack->id)->count());
        $this->assertSame(1, PaymentRefund::where('order_item_id', $addonA->id)->count());
        $this->assertSame(0, PaymentRefund::where('order_item_id', $addonB->id)->count());

        // Todos los items siguen activos.
        $this->assertFalse($pack->fresh()->isCancelled());
        $this->assertFalse($addonA->fresh()->isCancelled());
        $this->assertFalse($addonB->fresh()->isCancelled());
    }

    public function test_empty_selection_blocked(): void
    {
        Http::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => [],  // vacío
                ],
                arguments: ['item' => $item->id],
            );

        // CheckboxList ->required() bloquea cliente-side; si bypasseado,
        // server-side el handler bloquea con `no_items_selected`. Ambos casos
        // resultan en Http::assertNothingSent. Con Filament validation, la
        // action puede tener errores en lugar de ejecutar el handler — en
        // cualquier caso, ningún refund se procesa.
        Http::assertNothingSent();
        $item->refresh();
        $this->assertFalse($item->isCancelled());
        $this->assertSame(0, PaymentRefund::where('order_item_id', $item->id)->count());
    }

    public function test_selection_with_item_from_another_pack_blocked(): void
    {
        // Defense anti-IDOR (multi-capa): si el form se manipula para incluir
        // un item ajeno, Filament CheckboxList valida cliente/server-side que
        // los valores marcados pertenezcan a `options()` (que solo expone
        // pack+children) — bloquea SIN siquiera invocar el handler. Si esa
        // validación fallara por algún cambio futuro, mi handler revalida
        // ownership con `invalid_item_selection` (defensa en profundidad).
        Http::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $pack = $this->attachActiveItem($order);
        $otherOrder = $this->makePaidOrder(code: 'JJ-EVIL');
        $otherItem = $this->attachActiveItem($otherOrder);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => [$otherItem->id],  // item de otro Order
                    'optimistic_token' => (string) $pack->updated_at->getTimestamp(),
                    'expected_capacity_cents' => $order->refundableCapacityCents(),
                ],
                arguments: ['item' => $pack->id],
            );

        // Lo crítico: SIN refund creado + SIN Redsys tocado.
        Http::assertNothingSent();
        $this->assertSame(0, PaymentRefund::where('order_item_id', $otherItem->id)->count());
        $this->assertSame(0, PaymentRefund::where('order_item_id', $pack->id)->count());
    }

    public function test_manual_mode_records_without_rest_call(): void
    {
        Notification::fake();
        Http::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder(2400);
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_MANUAL,
                    'items_to_refund' => [$item->id],
                ],
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        Http::assertNothingSent();
        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->first();
        $this->assertSame(PaymentRefund::MODE_MANUAL, $refund->mode);
        $this->assertSame(PaymentRefund::MANUAL_RESPONSE_MARKER, $refund->gateway_response_code);
        $this->assertSame(1200, (int) $refund->amount_cents);

        Notification::assertSentTo($order->user, OrderItemRefunded::class);
    }

    public function test_refund_succeeds_on_previously_cancelled_item(): void
    {
        // Flujo operativo: operador cancela primero, refund después.
        Notification::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        // Paso 1: cancelar (no toca Redsys).
        $item->markCancelled($admin);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ),
                200,
            ),
        ]);

        // Paso 2: refundar el item ya cancelado.
        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => [$item->id],
                ],
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        // Refund procesado pese a estar cancelado.
        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->first();
        $this->assertSame(PaymentRefund::STATUS_SUCCEEDED, $refund->status);

        Notification::assertSentTo($order->user, OrderItemRefunded::class);
    }

    public function test_stale_optimistic_token_blocks_refund(): void
    {
        Http::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder(2400);
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => [$item->id],
                    'optimistic_token' => 'STALE',
                ],
                arguments: ['item' => $item->id],
            );

        Http::assertNothingSent();
        $log = AuditLog::where('action', 'orders.item_refund_blocked')->latest()->first();
        $this->assertSame('stale_item_version', $log->payload['reason']);
    }

    public function test_refund_complementos_when_principal_already_fully_refunded(): void
    {
        // Bug fix #155bis (feedback 2026-05-30): caso real reportado por la
        // clienta — pack con principal refundado completamente pero NO
        // cancelled + complementos vivos. Al abrir el modal refund:
        //  1. `buildRefundItemsOptions` excluye el principal (fully refunded).
        //  2. `fillForm` ANTES del fix pre-marcaba el principal igual →
        //     Filament CheckboxList validation rechazaba el value fuera de
        //     options con "El campo X seleccionado no es válido".
        //  3. Tras el fix, fillForm pre-fill items_to_refund = [].
        // Operador marca solo los complementos → submit funciona limpio.
        Notification::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder(2500);
        $payment = $this->attachPaidPayment($order);
        $pack = $this->attachActiveItem($order, unitPrice: 1800);
        $addonA = $this->attachAddon($order, $pack, unitPrice: 300);
        $addonB = $this->attachAddon($order, $pack, unitPrice: 400);

        // Pre-refund del principal (NO cancelled — sigue activo).
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $pack->id,
            'amount_cents' => 1800,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $admin->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ),
                200,
            ),
        ]);

        // Operador marca solo los addons (el pack NI aparece en options).
        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => [$addonA->id, $addonB->id],
                ],
                arguments: ['item' => $pack->id],
            )
            ->assertHasNoActionErrors();

        // Ambos complementos refundados (NO cancelled con nueva semántica #157).
        $this->assertFalse($addonA->fresh()->isCancelled());
        $this->assertFalse($addonB->fresh()->isCancelled());
        $this->assertSame(1, PaymentRefund::where('order_item_id', $addonA->id)->count());
        $this->assertSame(1, PaymentRefund::where('order_item_id', $addonB->id)->count());

        // Pack sigue activo (no se tocó).
        $this->assertFalse($pack->fresh()->isCancelled());

        // Order agregados: 1800 (pack inicial) + 300 + 400 = 2500.
        $order->refresh();
        $this->assertSame(2500, (int) $order->refund_amount_cents);

        // Emails al cliente (2 — uno por cada complemento).
        Notification::assertSentToTimes($order->user, OrderItemRefunded::class, 2);
    }

    public function test_capacity_sentinel_blocks_refund(): void
    {
        Http::fake();
        $admin = $this->admin();
        $order = $this->makePaidOrder(2400);
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refundItem',
                data: [
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => [$item->id],
                    'expected_capacity_cents' => 9999999,
                ],
                arguments: ['item' => $item->id],
            );

        Http::assertNothingSent();
        $log = AuditLog::where('action', 'orders.item_refund_blocked')->latest()->first();
        $this->assertSame('capacity_changed', $log->payload['reason']);
    }

    public function test_refund_options_exclude_item_exceeding_capacity(): void
    {
        // #172: un item cuyo importe refundable supera la capacidad reembolsable
        // del pedido NO se ofrece en el modal (antes se podía marcar y saltaba
        // `exceeds_refundable_capacity` al enviar). Un complemento asequible sí.
        $order = $this->makePaidOrder(12400);
        $payment = $this->attachPaidPayment($order);        // amount 12400
        $pack = $this->attachActiveItem($order, 12, 1500);  // 180,00 € (> capacidad)
        $addon = $this->attachAddon($order, $pack, 200);    // 2,00 €
        // Refund previo de 27,00 € → capacidad = 124 − 27 = 97,00 €.
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => null,
            'amount_cents' => 2700,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'requested_by' => User::factory()->create()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $staff = $this->staffWith(['orders.view', 'orders.refund_item']);
        $instance = Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->instance();

        $ref = new \ReflectionMethod($instance, 'buildRefundItemsOptions');
        $ref->setAccessible(true);
        /** @var array<int, string> $options */
        $options = $ref->invoke($instance, ['item' => $pack->id]);

        $this->assertArrayNotHasKey($pack->id, $options, 'El pack (180€) excede la capacidad (97€) → no se ofrece.');
        $this->assertArrayHasKey($addon->id, $options, 'El complemento asequible (2€) sí se ofrece.');
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
            'code' => $code !== '' ? $code : 'JJ-RI'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
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

    private function attachAddon(Order $order, OrderItem $parent, int $unitPrice = 200): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $parent->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => $unitPrice,
        ]);
    }

    private function fakeRedsysRefundResponse(string $dsResponse, string $gatewayOrder): array
    {
        $redsys = app(Redsys::class);
        $cfg = $redsys->config();
        $params = $redsys->createMerchantParameters([
            'Ds_Order' => $gatewayOrder,
            'Ds_Response' => $dsResponse,
            'Ds_MerchantCode' => $cfg['merchant_code'],
            'Ds_Terminal' => $cfg['terminal'],
        ]);
        $sig = $redsys->createMerchantSignature($cfg['secret_key'], $params, $gatewayOrder);

        return [
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $sig,
        ];
    }
}
