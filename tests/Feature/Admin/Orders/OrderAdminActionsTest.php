<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
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
use App\Domain\Payments\Services\Redsys;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderConfirmation;
use App\Notifications\OrderPaymentDeclined;
use App\Notifications\OrderRefunded;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sub-fase 7.2b (decisiones #138 + #139 + corrección #140).
 *
 * Tres acciones administrativas en `OrderResource::ViewOrder`:
 *  - Cancelar
 *  - Reembolsar (con Toggle "También cancelar el pedido" #139)
 *  - Reenviar email (selector unificado #140 — confirmación / reembolso /
 *    cancelación / completa-pago; solo aparecen los tipos cuyo evento ocurrió).
 *
 * #139 — Reembolso como dimensión INDEPENDIENTE del status:
 *  - `refunded_at` + `refund_amount_cents` paralelos a `status`.
 *  - Combinatoria: paid+refunded_at NULL | paid+refunded_at SET (canje) |
 *    cancelled+refunded_at NULL (acuerdo) | cancelled+refunded_at SET (textbook).
 *
 * #140 — Correcciones a #139:
 *  - Refund SOLO desde status=paid (mi extensión "refund-on-cancelled" del turno
 *    anterior se revierte: no estaba en el alcance pedido y generaba un botón
 *    confuso).
 *  - Acciones de reenvío unificadas en una sola con Select. Solo se exponen los
 *    tipos cuyo evento subyacente ya ocurrió.
 *
 * Defense in depth (patrón #128 reusado en 3 capas):
 *  (a) `->visible()` oculta cuando estado del Order no permite la acción.
 *  (b) `->visible()` oculta cuando el usuario no tiene el permiso requerido.
 *  (c) Handler revalida con `$record->fresh()` y `Order::canBe*()`/`canResend()`;
 *      si falla, audit log + danger notification + sin mutar BD.
 */
class OrderAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function makePaidOrder(string $code = 'JJ-PAID001'): Order
    {
        $customer = User::factory()->create([
            'email' => 'cliente-'.strtolower(str_replace('JJ-', '', $code)).'@example.com',
        ]);

        return Order::create([
            'user_id' => $customer->id,
            'code' => $code,
            'status' => Order::STATUS_PAID,
            'subtotal' => 1500,
            'tax' => 315,
            'total' => 1815,
            'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function makePendingOrder(string $code = 'JJ-PEND001'): Order
    {
        $customer = User::factory()->create();

        return Order::create([
            'user_id' => $customer->id,
            'code' => $code,
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'EUR',
            'expires_at' => now()->addHour(),
        ]);
    }

    private int $paymentCounter = 0;

    private function attachPaidPayment(Order $order): Payment
    {
        // gateway_order es necesario para reembolso REST (#142): Redsys necesita el
        // Ds_Merchant_Order original para enlazar la devolución con su autorización.
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

    private function attachFailedPayment(Order $order, ?string $dsResponse = '0190'): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_FAILED,
            'raw_response' => $dsResponse === null ? null : ['Ds_Response' => $dsResponse],
        ]);
    }

    private function attachPendingPayment(Order $order): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    // Helpers para tests de Orders "operativamente finished" (#141): el bloqueo de
    // cancel requiere que TODOS los items principales tengan un slot cuyo end_time
    // ya haya pasado (`OrderItem::isFinishedInPractice()`).
    private TicketType $jumpType;

    private Zone $zone;

    private int $slotCounter = 0;

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

    private function makeSlot(string $date): Slot
    {
        $this->ensureTicketTypeSetup();
        $h = str_pad((string) ($this->slotCounter++ % 23), 2, '0', STR_PAD_LEFT);

        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 10, 'online_capacity' => 5,
        ]);
    }

    private function attachItemWithPastSlot(Order $order): OrderItem
    {
        $this->ensureTicketTypeSetup();

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $this->makeSlot('2000-01-01')->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => $order->total,
        ]);
    }

    /** Una reserva VIVA (franja futura) sobre la que comprobar la cascada de cancelación. */
    private function attachLiveItem(Order $order): OrderItem
    {
        $this->ensureTicketTypeSetup();

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $this->makeSlot(now()->addDays(7)->toDateString())->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => $order->total,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Cancelar el PEDIDO cancela sus RESERVAS (`DECISIONES #127`)
    //
    // ⚠️⚠️ Son DOS caminos y los dos dejaban las líneas vivas: el parque retenía el dinero, el panel
    // ya no podía devolverlo desde ahí —`refundBlockedReason` bloquea los cancelados— y el cliente
    // leía «Total 19,80 €» sin una palabra sobre lo que se le debe. Se conducen las ACCIONES REALES:
    // una guarda sobre el helper suelto no ve el cableado, y de hecho no lo vio (mutación).
    // ═══════════════════════════════════════════════════════════════════════

    public function test_cancelling_the_order_from_the_panel_cancels_its_reservations(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachLiveItem($order);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancel')
            ->assertHasNoActionErrors();

        $this->assertTrue($item->fresh()->isCancelled(), 'la reserva sigue viva en un pedido cancelado');

        $summary = $order->fresh(['items.slot', 'payments.refunds', 'adjustments'])->financialSummary();
        $this->assertSame(0, $summary->totalFinalNeto(), 'un pedido cancelado no tiene valor vivo');
        $this->assertSame(
            (int) $order->total, $summary->pendienteDevolucion(),
            'y lo cobrado tiene que aflorar como pendiente de devolver — que es lo que el cliente lee',
        );
    }

    public function test_a_full_refund_that_also_cancels_cancels_its_reservations(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachLiveItem($order);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertTrue($item->fresh()->isCancelled(), 'la reserva sigue viva tras reembolsar y cancelar');

        $summary = $order->fresh(['items.slot', 'payments.refunds', 'adjustments'])->financialSummary();
        $this->assertSame(0, $summary->totalFinalNeto());
        $this->assertSame(0, $summary->pendienteDevolucion(), 'se devolvió todo: no queda nada pendiente');
        $this->assertSame(0, $summary->retenidoOnline(), 'ni el parque retiene nada');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // El MOTIVO del reembolso (`DECISIONES #127(c)`)
    //
    // ⚠️⚠️ Reembolsar y cancelar son independientes A PROPÓSITO, y esa flexibilidad crea un estado
    // que el desglose no puede narrar sin adivinar: al cliente se le devolvió el dinero y conserva
    // su reserva. ¿Es una compensación —no debe nada— o pagará en taquilla? Sin este dato solo se le
    // puede decir «te devolvimos X €», que es honesto pero no dice lo único que necesita saber.
    // ═══════════════════════════════════════════════════════════════════════

    public function test_a_refund_without_cancelling_records_why_the_money_went_back(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => false,
                'intent' => PaymentRefund::INTENT_PAID_IN_PERSON,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(
            PaymentRefund::INTENT_PAID_IN_PERSON,
            PaymentRefund::latest('id')->first()->intent,
            'el motivo tiene que quedar registrado con el reembolso, no perderse en el modal',
        );
    }

    /**
     * Si además se CANCELA, el motivo es evidente —desapareció el producto— y no se le pregunta al
     * operador: se registra solo. Preguntar lo obvio en una acción de dinero es ruido.
     */
    public function test_a_refund_that_also_cancels_records_the_value_returned_without_asking(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(
            PaymentRefund::INTENT_VALUE_RETURNED,
            PaymentRefund::latest('id')->first()->intent,
        );
    }

    /** Un valor inventado por un cliente manipulado NO se guarda: se normaliza a «no consta». */
    public function test_an_unknown_intent_is_not_stored(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => false,
                'intent' => 'lo-que-sea',
            ]);

        $this->assertNull(PaymentRefund::latest('id')->first()?->intent);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Cancel — visibility
    // ═══════════════════════════════════════════════════════════════════════

    public function test_cancel_action_visible_for_paid_order_with_permission(): void
    {
        $order = $this->makePaidOrder();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('cancel');
    }

    public function test_cancel_action_hidden_when_order_already_cancelled(): void
    {
        $order = $this->makePaidOrder();
        $order->update(['status' => Order::STATUS_CANCELLED]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('cancel');
    }

    public function test_cancel_action_hidden_for_legacy_status_refunded(): void
    {
        $order = $this->makePaidOrder();
        $order->update(['status' => Order::STATUS_REFUNDED]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('cancel');
    }

    public function test_cancel_action_hidden_when_order_expired_in_practice(): void
    {
        $order = $this->makePendingOrder();
        $order->update(['expires_at' => now()->subHour()]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('cancel');
    }

    public function test_cancel_action_visible_when_paid_with_refunded_at_already_set(): void
    {
        // #139: refund-only (canje en parque) no impide cancelar después.
        $order = $this->makePaidOrder();
        $order->update([
            'refunded_at' => now(),
            'refund_amount_cents' => $order->total,
        ]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('cancel');
    }

    public function test_cancel_action_hidden_when_order_operatively_finished(): void
    {
        // #141: cancelar un servicio ya prestado es semánticamente incorrecto.
        // Todos los items principales con slot pasado → displayOperativeStatus = FINISHED.
        $order = $this->makePaidOrder();
        $this->attachItemWithPastSlot($order);

        $this->assertSame(
            Order::OPERATIVE_STATUS_FINISHED,
            $order->fresh()->displayOperativeStatus(),
        );

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('cancel');
    }

    public function test_refund_action_remains_visible_when_order_operatively_finished(): void
    {
        // #141: una queja post-servicio justifica reembolso aunque el servicio se
        // haya prestado. Refund permanece disponible mientras `paid` + no refunded.
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $this->attachItemWithPastSlot($order);

        $this->assertSame(
            Order::OPERATIVE_STATUS_FINISHED,
            $order->fresh()->displayOperativeStatus(),
        );

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('refund');
    }

    public function test_refund_on_finished_order_skips_also_cancel_toggle_and_keeps_status_paid(): void
    {
        // #141 — Toggle "También cancelar" oculto cuando FINISHED. El orquestador
        // #142 además revalida `canBeCancelled()` y fuerza `alsoCancelApplied=false`.
        Notification::fake();
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);
        $this->attachItemWithPastSlot($order);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => true,
            ]);

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame($payment->amount, $order->refund_amount_cents);

        $log = AuditLog::where('action', 'orders.refunded')->latest()->first();
        $this->assertFalse($log->payload['also_cancelled']);

        Notification::assertSentTo(
            $order->user,
            OrderRefunded::class,
            fn (OrderRefunded $n): bool => $n->alsoCancelled === false,
        );
    }

    public function test_resend_email_remains_visible_when_order_operatively_finished(): void
    {
        // #141: el cliente puede pedir copia de su confirmación incluso post-servicio.
        $order = $this->makePaidOrder();
        $this->attachItemWithPastSlot($order);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('resendEmail');
    }

    public function test_cancel_action_hidden_without_permission(): void
    {
        $order = $this->makePaidOrder();
        $staff = $this->staff();
        $perm = Permission::where('name', 'orders.cancel')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('cancel');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Cancel — handler + audit log + email
    // ═══════════════════════════════════════════════════════════════════════

    public function test_cancel_transitions_status_and_audits_and_notifies(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancel')
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);

        $log = AuditLog::where('action', 'orders.cancelled')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($order->code, $log->payload['order_code']);
        $this->assertSame(Order::STATUS_PAID, $log->payload['previous_status']);

        Notification::assertSentTo($order->user, OrderCancelled::class);
    }

    public function test_cancel_works_on_pending_order(): void
    {
        Notification::fake();
        $order = $this->makePendingOrder();

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancel');

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        Notification::assertSentTo($order->user, OrderCancelled::class);
    }

    public function test_cancel_works_on_order_already_refunded_only(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $order->update([
            'refunded_at' => now()->subDay(),
            'refund_amount_cents' => $order->total,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('cancel');

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->refunded_at);

        Notification::assertSentTo($order->user, OrderCancelled::class);
    }

    public function test_cancellation_email_does_not_promise_refund_unconditionally(): void
    {
        // #139: el texto previo prometía categóricamente la devolución; cancelar y
        // reembolsar son independientes. Debe usar la clave `next_steps` condicional.
        $order = $this->makePaidOrder();
        $mail = (new OrderCancelled($order))->toMail($order->user);

        $body = collect($mail->introLines)->merge($mail->outroLines)->implode(' ');

        $this->assertStringNotContainsString('el importe se devolverá a tu tarjeta', $body);
        $this->assertStringContainsString('Si la cancelación lleva asociado un reembolso', $body);
        $this->assertStringContainsString('correo aparte', $body);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Refund — visibility (#140: SOLO desde paid; refund-on-cancelled bloqueado)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_refund_visible_for_paid_order_with_permission(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('refund');
    }

    public function test_refund_hidden_when_pending(): void
    {
        $order = $this->makePendingOrder();

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('refund');
    }

    public function test_refund_hidden_when_refunded_at_already_set(): void
    {
        // #139 modelo nuevo: refunded_at es la fuente de verdad.
        $order = $this->makePaidOrder();
        $order->update([
            'refunded_at' => now(),
            'refund_amount_cents' => $order->total,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('refund');
    }

    public function test_refund_hidden_when_legacy_status_refunded(): void
    {
        $order = $this->makePaidOrder();
        $order->update(['status' => Order::STATUS_REFUNDED]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('refund');
    }

    public function test_refund_hidden_when_order_already_cancelled(): void
    {
        // #140 corrección: refund SOLO desde status=paid. La extensión del turno
        // anterior ("cancelar primero, devolver después") se revierte porque
        // generaba un botón confuso no pedido en el alcance original.
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $order->update(['status' => Order::STATUS_CANCELLED]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('refund');
    }

    public function test_refund_hidden_for_staff_without_refund_permission(): void
    {
        $order = $this->makePaidOrder();
        $staff = $this->staff();
        $perm = Permission::where('name', 'orders.refund')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('refund');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Refund — handler con/sin "También cancelar"
    // ═══════════════════════════════════════════════════════════════════════

    public function test_refund_manual_mode_with_also_cancel_transitions_and_records(): void
    {
        // Modo manual (#142): el operador ya devolvió en el portal banco. El
        // orquestador NO hace REST call. Transición de Order + email idénticos
        // al éxito REST.
        Notification::fake();
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => true,
            ])
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame($payment->amount, $order->refund_amount_cents);

        $log = AuditLog::where('action', 'orders.refunded')->latest()->first();
        $this->assertTrue($log->payload['also_cancelled']);
        $this->assertSame(PaymentRefund::MODE_MANUAL, $log->payload['mode']);
        $this->assertSame(PaymentRefund::MANUAL_RESPONSE_MARKER, $log->payload['gateway_response_code']);

        // Fila PaymentRefund creada con status=succeeded + mode=manual.
        $refund = PaymentRefund::find($log->payload['refund_id']);
        $this->assertSame(PaymentRefund::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame(PaymentRefund::MODE_MANUAL, $refund->mode);
        $this->assertSame(PaymentRefund::MANUAL_RESPONSE_MARKER, $refund->gateway_response_code);
        $this->assertSame($payment->id, $refund->payment_id);

        Notification::assertSentTo($order->user, OrderRefunded::class, fn (OrderRefunded $n): bool => $n->alsoCancelled === true);
        Notification::assertNotSentTo($order->user, OrderCancelled::class);
    }

    public function test_refund_also_cancel_is_forced_off_without_cancel_permission(): void
    {
        // Defensa server-side (auditoría Fase 1, Sistema 5): un rol con `orders.refund` pero SIN
        // `orders.cancel` (config no-default) NO puede cancelar el pedido por la rama «También
        // cancelar» del reembolso, aunque fuerce el dato. El toggle se oculta Y el handler lo re-fuerza
        // a false → el reembolso ocurre pero el pedido sigue PAID (no se libera la plaza ni se notifica
        // cancelación). Cierra la asimetría con la acción de cancelación standalone.
        Notification::fake();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $staff = $this->staff();
        $cancelPerm = Permission::where('name', 'orders.cancel')->value('id');
        $staff->roles->first()->permissions()->detach($cancelPerm);

        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => true, // forzado por el cliente; debe ignorarse
            ])
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status, 'Sin orders.cancel, el reembolso NO debe cancelar el pedido.');
        $this->assertNotNull($order->refunded_at);

        $log = AuditLog::where('action', 'orders.refunded')->latest()->first();
        $this->assertFalse($log->payload['also_cancelled']);
        Notification::assertNotSentTo($order->user, OrderCancelled::class);
    }

    public function test_refund_manual_mode_without_also_cancel_keeps_status(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => false,
                // Sin cancelar, el motivo es OBLIGATORIO (`DECISIONES #127(c)`): el cliente conserva
                // su reserva y su desglose tiene que poder decirle si sigue debiendo el importe.
                'intent' => PaymentRefund::INTENT_COMPENSATION,
            ])
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->refunded_at);
        $this->assertSame($payment->amount, $order->refund_amount_cents);

        $log = AuditLog::where('action', 'orders.refunded')->latest()->first();
        $this->assertFalse($log->payload['also_cancelled']);

        Notification::assertSentTo($order->user, OrderRefunded::class, fn (OrderRefunded $n): bool => $n->alsoCancelled === false);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Refund REST — happy path / denial / transport / inflight (#142)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Construye la respuesta JSON simulada de Redsys tras una devolución exitosa.
     * Reusa la cripto real del servicio para que la firma sea válida (verifica
     * empíricamente que `Redsys::executeRefund` también valida la firma de la
     * respuesta — defensa contra MITM).
     */
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

    public function test_refund_rest_mode_happy_path_calls_redsys_records_succeeded_and_emails(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ),
                200,
            ),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_REST,
                'also_cancel' => true,
            ])
            ->assertHasNoActionErrors();

        // POST efectivamente realizado a Redsys con los parámetros correctos.
        // El gateway_order va base64-encoded dentro de Ds_MerchantParameters, así
        // que decodificamos para comprobar el contenido empíricamente.
        Http::assertSent(function ($request) use ($payment): bool {
            if ($request->url() !== Redsys::REST_URL_TEST) {
                return false;
            }
            $body = $request->data();
            if (! isset($body['Ds_MerchantParameters'], $body['Ds_Signature'])) {
                return false;
            }
            $decoded = app(Redsys::class)
                ->decodeMerchantParameters($body['Ds_MerchantParameters']);

            return ($decoded['DS_MERCHANT_ORDER'] ?? null) === $payment->gateway_order
                && ($decoded['DS_MERCHANT_TRANSACTIONTYPE'] ?? null) === '3'
                && ($decoded['DS_MERCHANT_AMOUNT'] ?? null) === (string) $payment->amount;
        });

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->refunded_at);

        $log = AuditLog::where('action', 'orders.refunded')->latest()->first();
        $this->assertSame(PaymentRefund::MODE_REST, $log->payload['mode']);
        $this->assertSame(PaymentRefund::REDSYS_REFUND_SUCCESS_CODE, $log->payload['gateway_response_code']);

        $refund = PaymentRefund::find($log->payload['refund_id']);
        $this->assertSame(PaymentRefund::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame(PaymentRefund::MODE_REST, $refund->mode);
        $this->assertSame(PaymentRefund::REDSYS_REFUND_SUCCESS_CODE, $refund->gateway_response_code);
        $this->assertNotNull($refund->processed_at);
        $this->assertSame($payment->gateway_order, $refund->gateway_order);

        Notification::assertSentTo(
            $order->user,
            OrderRefunded::class,
            fn (OrderRefunded $n): bool => $n->alsoCancelled === true,
        );
    }

    public function test_refund_rest_mode_gateway_denial_records_failed_does_not_change_order_or_email(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse('0190', $payment->gateway_order),  // denegado
                200,
            ),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_REST,
                'also_cancel' => true,
            ]);

        $order->refresh();
        $this->assertNull($order->refunded_at);
        $this->assertSame(Order::STATUS_PAID, $order->status);

        // Fila PaymentRefund queda en `failed` con razón `gateway_denied` y código real.
        $refund = PaymentRefund::where('payment_id', $payment->id)->latest()->first();
        $this->assertSame(PaymentRefund::STATUS_FAILED, $refund->status);
        $this->assertSame(PaymentRefund::FAILURE_GATEWAY_DENIED, $refund->failure_reason);
        $this->assertSame('0190', $refund->gateway_response_code);

        $log = AuditLog::where('action', 'orders.refund_failed')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame(PaymentRefund::FAILURE_GATEWAY_DENIED, $log->payload['failure_reason']);
        $this->assertSame('0190', $log->payload['ds_response']);

        // Sin email — el dinero no se ha movido.
        Notification::assertNothingSent();
    }

    public function test_refund_rest_mode_transport_error_records_failed_with_transport_reason(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);

        // Simulamos una excepción del cliente HTTP (timeout / connection refused).
        Http::fake([
            Redsys::REST_URL_TEST => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_REST,
                'also_cancel' => true,
            ]);

        $order->refresh();
        $this->assertNull($order->refunded_at);
        $this->assertSame(Order::STATUS_PAID, $order->status);

        $refund = PaymentRefund::where('payment_id', $payment->id)->latest()->first();
        $this->assertSame(PaymentRefund::STATUS_FAILED, $refund->status);
        $this->assertSame(PaymentRefund::FAILURE_TRANSPORT, $refund->failure_reason);
        $this->assertStringContainsString('timed out', (string) $refund->failure_message);
        $this->assertNull($refund->gateway_response_code);  // no respuesta interpretable

        Notification::assertNothingSent();
    }

    public function test_refund_rest_mode_5xx_response_treated_as_transport_error(): void
    {
        // Redsys 5xx / HTML de error / cualquier no-200 → tratado como transporte.
        // Razón: el cuerpo no es interpretable como resultado de la pasarela; el
        // operador debe verificar manualmente antes de reintentar.
        Notification::fake();
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response('Internal Server Error', 503),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_REST,
                'also_cancel' => true,
            ]);

        $refund = PaymentRefund::where('payment_id', $payment->id)->latest()->first();
        $this->assertSame(PaymentRefund::STATUS_FAILED, $refund->status);
        $this->assertSame(PaymentRefund::FAILURE_TRANSPORT, $refund->failure_reason);

        Notification::assertNothingSent();
    }

    public function test_refund_inflight_blocks_second_concurrent_attempt(): void
    {
        // Materializamos manualmente una fila pending para simular un click
        // anterior aún en vuelo. El segundo intento debe ver `inflight_refund`
        // y NO disparar la REST call ni mutar la Order.
        Notification::fake();
        Http::fake();  // si se llamara, marcaría el test como fallido (assertNothingSent abajo)
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);

        PaymentRefund::create([
            'payment_id' => $payment->id,
            'amount_cents' => $payment->amount,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_PENDING,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'requested_by' => $this->admin()->id,
            'requested_at' => now()->subSecond(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_REST,
                'also_cancel' => true,
            ]);

        $order->refresh();
        $this->assertNull($order->refunded_at);
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_refund_returns_no_paid_payment_when_order_has_no_paid_payment_record(): void
    {
        // El orquestador #142 no asume cobro: si no hay Payment paid, devuelve
        // `ok=false` con reason `no_paid_payment` y NO toca Order ni envía email.
        // (Cambio respecto al comportamiento anterior, que rellenaba por fallback con
        // el total del Order — ahora exigimos un cobro consolidado real para devolver.)
        Notification::fake();
        $order = $this->makePaidOrder();

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('refund', data: [
                'mode' => PaymentRefund::MODE_MANUAL,
                'also_cancel' => true,
            ]);

        $order->refresh();
        $this->assertNull($order->refunded_at);
        $this->assertSame(Order::STATUS_PAID, $order->status);
        Notification::assertNothingSent();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // OrderRefunded — contenido del email
    // ═══════════════════════════════════════════════════════════════════════

    public function test_refund_email_includes_also_cancelled_line_when_flag_set(): void
    {
        $order = $this->makePaidOrder();
        $order->refund_amount_cents = $order->total;

        $mail = (new OrderRefunded($order, null, alsoCancelled: true))->toMail($order->user);
        $lines = collect($mail->introLines)->implode(' ');

        $this->assertStringContainsString('cancelada', $lines);
        $this->assertStringContainsString('18,15', $lines);
    }

    public function test_refund_email_omits_also_cancelled_line_when_flag_not_set(): void
    {
        $order = $this->makePaidOrder();
        $order->refund_amount_cents = $order->total;

        $mail = (new OrderRefunded($order, null, alsoCancelled: false))->toMail($order->user);
        $lines = collect($mail->introLines)->implode(' ');

        $this->assertStringNotContainsString('cancelada', $lines);
        $this->assertStringContainsString('18,15', $lines);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Resend email — visibilidad de la acción unificada (#140)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_resend_email_hidden_for_pending_bare_order(): void
    {
        // Pending sin Payment → ningún evento ocurrió aún → action oculto.
        $order = $this->makePendingOrder();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('resendEmail');
    }

    public function test_resend_email_visible_for_paid_order(): void
    {
        $order = $this->makePaidOrder();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('resendEmail');
    }

    public function test_resend_email_visible_for_cancelled_order(): void
    {
        $order = $this->makePaidOrder();
        $order->update(['status' => Order::STATUS_CANCELLED]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('resendEmail');
    }

    public function test_resend_email_visible_for_pending_with_payment_intent(): void
    {
        $order = $this->makePendingOrder();
        $this->attachFailedPayment($order);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionVisible('resendEmail');
    }

    public function test_resend_email_hidden_when_expired_in_practice(): void
    {
        $order = $this->makePendingOrder();
        $this->attachFailedPayment($order);
        $order->update(['expires_at' => now()->subHour()]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertActionHidden('resendEmail');
    }

    public function test_resend_email_hidden_without_view_permission(): void
    {
        // Sin orders.view la Resource bloquea el acceso a la página entera.
        $order = $this->makePaidOrder();
        $staff = $this->staff();
        $perm = Permission::where('name', 'orders.view')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        $this->actingAs($staff)
            ->get('/admin/orders/'.$order->code)
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Resend email — dispatch correcto por tipo
    // ═══════════════════════════════════════════════════════════════════════

    public function test_resend_email_dispatches_confirmation(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $staff = $this->staff();

        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_CONFIRMATION])
            ->assertHasNoActionErrors();

        Notification::assertSentTo($order->user, OrderConfirmation::class);

        $log = AuditLog::where('action', 'orders.email_resent')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame(Order::RESEND_TYPE_CONFIRMATION, $log->payload['type']);
        // Minimización RGPD (auditoría Fase 1, Sistema 5): el email NO se persiste en el payload (el
        // `target` Order→user ya traza al destinatario mientras la cuenta exista; tras anonimizar, no).
        $this->assertArrayNotHasKey('email', $log->payload);
        $this->assertSame($order->code, $log->payload['order_code']);
    }

    public function test_resend_email_dispatches_refund_with_also_cancelled_reflecting_current_state(): void
    {
        Notification::fake();
        // Order paid + refund registrado SIN cancel previo → resend OrderRefunded
        // con alsoCancelled = false.
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $order->update([
            'refunded_at' => now(),
            'refund_amount_cents' => $order->total,
        ]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_REFUND]);

        Notification::assertSentTo(
            $order->user,
            OrderRefunded::class,
            fn (OrderRefunded $n): bool => $n->alsoCancelled === false,
        );
    }

    public function test_resend_email_dispatches_refund_with_also_cancelled_true_for_cancelled_state(): void
    {
        // refunded + cancelled (textbook) → resend con alsoCancelled = true.
        Notification::fake();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'refunded_at' => now(),
            'refund_amount_cents' => $order->total,
        ]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_REFUND]);

        Notification::assertSentTo(
            $order->user,
            OrderRefunded::class,
            fn (OrderRefunded $n): bool => $n->alsoCancelled === true,
        );
    }

    public function test_resend_email_dispatches_cancellation(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();
        $order->update(['status' => Order::STATUS_CANCELLED]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_CANCELLATION]);

        Notification::assertSentTo($order->user, OrderCancelled::class);

        $log = AuditLog::where('action', 'orders.email_resent')->latest()->first();
        $this->assertSame(Order::RESEND_TYPE_CANCELLATION, $log->payload['type']);
    }

    public function test_resend_email_dispatches_payment_retry_with_ds_response_from_last_failed_payment(): void
    {
        Notification::fake();
        $order = $this->makePendingOrder();
        $this->attachFailedPayment($order, dsResponse: '0190');

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_PAYMENT_RETRY]);

        Notification::assertSentTo(
            $order->user,
            OrderPaymentDeclined::class,
            fn (OrderPaymentDeclined $n): bool => $n->dsResponse === '0190',
        );

        $log = AuditLog::where('action', 'orders.email_resent')->latest()->first();
        $this->assertSame(Order::RESEND_TYPE_PAYMENT_RETRY, $log->payload['type']);
    }

    public function test_resend_email_dispatches_payment_retry_with_null_ds_response_when_only_pending_intent(): void
    {
        Notification::fake();
        $order = $this->makePendingOrder();
        $this->attachPendingPayment($order);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_PAYMENT_RETRY]);

        Notification::assertSentTo(
            $order->user,
            OrderPaymentDeclined::class,
            fn (OrderPaymentDeclined $n): bool => $n->dsResponse === null,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Resend email — defense in depth (capa 3): handler revalida con canResend
    // ═══════════════════════════════════════════════════════════════════════

    public function test_resend_email_rejects_type_no_longer_applicable_via_form_validation(): void
    {
        // Defense in depth multi-capa: el Filament Select se rebuilds con las
        // opciones que devuelve `availableResendEmailTypes(fresh-record)` en el
        // momento del submit. Si el tipo elegido ya no aparece (porque otro proceso
        // cambió el status entre mount y submit), la validación del Select falla
        // ANTES de ejecutar el closure del handler. Resultado: no se envía email,
        // no se muta BD.
        //
        // El handler además revalida con `canResend()` y registra
        // `orders.email_resent_blocked` — es código defensivo para flujos que
        // bypassan el form (futuro endpoint directo, refactor, etc.). En el flujo
        // normal Filament 5 ya bloquea aquí.
        Notification::fake();
        $order = $this->makePaidOrder();
        $component = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code]);

        $order->update(['status' => Order::STATUS_CANCELLED]);

        $component->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_CONFIRMATION]);

        // Lo crítico para el cliente: no se envió ningún email.
        Notification::assertNothingSent();
        // Y el status no cambió.
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_resend_email_each_dispatch_writes_separate_audit_entry(): void
    {
        Notification::fake();
        $order = $this->makePaidOrder();

        $component = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code]);
        $component->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_CONFIRMATION]);
        $component->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_CONFIRMATION]);

        $count = AuditLog::where('action', 'orders.email_resent')
            ->where('target_id', $order->id)
            ->count();
        $this->assertSame(2, $count);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Modelo: contrato de availableResendEmailTypes
    // ═══════════════════════════════════════════════════════════════════════

    public function test_available_resend_email_types_for_paid_order_only_lists_confirmation(): void
    {
        $order = $this->makePaidOrder();

        $this->assertSame(
            [Order::RESEND_TYPE_CONFIRMATION],
            $order->availableResendEmailTypes(),
        );
    }

    public function test_available_resend_email_types_for_paid_and_refunded_lists_both(): void
    {
        $order = $this->makePaidOrder();
        $order->update(['refunded_at' => now(), 'refund_amount_cents' => $order->total]);

        $this->assertSame(
            [Order::RESEND_TYPE_CONFIRMATION, Order::RESEND_TYPE_REFUND],
            $order->fresh()->availableResendEmailTypes(),
        );
    }

    public function test_available_resend_email_types_for_cancelled_only_lists_cancellation(): void
    {
        $order = $this->makePaidOrder();
        $order->update(['status' => Order::STATUS_CANCELLED]);

        $this->assertSame(
            [Order::RESEND_TYPE_CANCELLATION],
            $order->fresh()->availableResendEmailTypes(),
        );
    }

    public function test_available_resend_email_types_for_cancelled_and_refunded_lists_both(): void
    {
        $order = $this->makePaidOrder();
        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'refunded_at' => now(),
            'refund_amount_cents' => $order->total,
        ]);

        $this->assertSame(
            [Order::RESEND_TYPE_REFUND, Order::RESEND_TYPE_CANCELLATION],
            $order->fresh()->availableResendEmailTypes(),
        );
    }

    public function test_available_resend_email_types_for_pending_with_failed_payment_only_lists_payment_retry(): void
    {
        $order = $this->makePendingOrder();
        $this->attachFailedPayment($order);

        $this->assertSame(
            [Order::RESEND_TYPE_PAYMENT_RETRY],
            $order->fresh()->availableResendEmailTypes(),
        );
    }

    public function test_available_resend_email_types_for_pending_bare_is_empty(): void
    {
        $order = $this->makePendingOrder();

        $this->assertSame([], $order->availableResendEmailTypes());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Modelo: contrato de *BlockedReason()
    // ═══════════════════════════════════════════════════════════════════════

    public function test_cancellation_blocked_reason_returns_expected_codes(): void
    {
        $order = $this->makePaidOrder();
        $this->assertNull($order->cancellationBlockedReason());

        $order->update(['status' => Order::STATUS_CANCELLED]);
        $this->assertSame('already_cancelled', $order->fresh()->cancellationBlockedReason());

        $order->update(['status' => Order::STATUS_REFUNDED]);
        $this->assertSame('already_refunded', $order->fresh()->cancellationBlockedReason());

        $pending = $this->makePendingOrder('JJ-EXP01');
        $pending->update(['expires_at' => now()->subHour()]);
        $this->assertSame('expired', $pending->fresh()->cancellationBlockedReason());

        // #139 combinatorial retenido: paid + refunded_at NO bloquea cancel.
        $refundedOnly = $this->makePaidOrder('JJ-RO01');
        $refundedOnly->update(['refunded_at' => now(), 'refund_amount_cents' => $refundedOnly->total]);
        $this->assertNull($refundedOnly->fresh()->cancellationBlockedReason());

        // #141: paid + operativamente finished → already_finished.
        $finished = $this->makePaidOrder('JJ-FIN01');
        $this->attachItemWithPastSlot($finished);
        $this->assertSame('already_finished', $finished->fresh()->cancellationBlockedReason());
    }

    public function test_refund_blocked_reason_returns_expected_codes(): void
    {
        $order = $this->makePaidOrder();
        $this->assertNull($order->refundBlockedReason());

        $order->update(['refunded_at' => now(), 'refund_amount_cents' => $order->total]);
        $this->assertSame('already_refunded', $order->fresh()->refundBlockedReason());

        $legacy = $this->makePaidOrder('JJ-LEG01');
        $legacy->update(['status' => Order::STATUS_REFUNDED]);
        $this->assertSame('already_refunded', $legacy->fresh()->refundBlockedReason());

        // #140 corrección: status=cancelled bloquea refund (revertida la
        // extensión de #139 que permitía refund-on-cancelled).
        $cancelled = $this->makePaidOrder('JJ-CAN01');
        $cancelled->update(['status' => Order::STATUS_CANCELLED]);
        $this->assertSame('already_cancelled', $cancelled->fresh()->refundBlockedReason());

        $pending = $this->makePendingOrder('JJ-NP01');
        $this->assertSame('not_paid', $pending->refundBlockedReason());
    }

    public function test_can_resend_returns_true_only_for_applicable_types(): void
    {
        $paid = $this->makePaidOrder();
        $this->assertTrue($paid->canResend(Order::RESEND_TYPE_CONFIRMATION));
        $this->assertFalse($paid->canResend(Order::RESEND_TYPE_REFUND));
        $this->assertFalse($paid->canResend(Order::RESEND_TYPE_CANCELLATION));
        $this->assertFalse($paid->canResend(Order::RESEND_TYPE_PAYMENT_RETRY));

        $cancelled = $this->makePaidOrder('JJ-CAN02');
        $cancelled->update(['status' => Order::STATUS_CANCELLED]);
        $this->assertFalse($cancelled->fresh()->canResend(Order::RESEND_TYPE_CONFIRMATION));
        $this->assertTrue($cancelled->fresh()->canResend(Order::RESEND_TYPE_CANCELLATION));
    }
}
