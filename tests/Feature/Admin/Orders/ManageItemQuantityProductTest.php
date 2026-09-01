<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ItemEditPricing;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Payments\Services\Redsys;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\OrderItemModified;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sub-fase 7.2e.3 (decisión #167) — Modal "Gestionar producto" Tab 1: cambio
 * de CANTIDAD + PRODUCTO con la dimensión financiera real (`recordEdit`
 * para subidas, `executePartialRefund` REST para bajadas).
 *
 * Alcance acotado (decisión clienta 2026-06-01):
 *  - Producto filtrado a MISMO tipo + MISMA zona. Cross-zone / cross-type se
 *    bloquean en el handler (defense in depth ante manipulación del form).
 *  - Addons huérfanos → bloquear + avisar (el refund de complementos es 7.2e.4).
 *
 * Cobertura: cantidad (sube/baja/aforo/exclusión-de-huella-propia), producto
 * (sube/baja/igual/sin-precio/cross-zone/cross-type/huérfanos), packs (rango de
 * invitados), defense in depth (optimistic/permiso/IDOR/cancelado), combinado
 * slot+cantidad, fallo de REST recuperable, opciones del selector, render del
 * Tab y exclusión `excludeItemId` de los servicios de aforo.
 */
class ManageItemQuantityProductTest extends TestCase
{
    use RefreshDatabase;

    private Zone $jump;

    private Zone $kids;

    private Zone $cumple;

    private TicketType $entryA;     // Jump 1h, 12.00 €

    private TicketType $entryPricier; // Jump 2h, 20.00 €

    private TicketType $entryCheaper; // Jump 30min, 8.00 €

    private TicketType $entrySamePrice; // Jump alt, 12.00 €

    private TicketType $entryNoPrice; // Jump sin precio de catálogo

    private TicketType $entryKids;   // Kids 1h (otra zona)

    private TicketType $packA;       // Cumple Jump, 15.00 €

    private TicketType $addonX;      // complemento solo de entryA

    private string $day;

    private int $counter = 0;

    private int $paymentCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create([
            'key' => RateType::KEY_NORMAL,
            'label' => ['es' => 'Normal'],
            'weekdays' => null,
            'priority' => 0,
        ]);

        $this->jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->kids = Zone::create(['slug' => 'kids', 'name' => ['es' => 'KIDS']]);
        $this->cumple = Zone::create(['slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños']]);

        $this->entryA = $this->makeEntry('Jump 1h', $this->jump, 60, 1200);
        $this->entryPricier = $this->makeEntry('Jump 2h', $this->jump, 120, 2000);
        $this->entryCheaper = $this->makeEntry('Jump 30min', $this->jump, 30, 800);
        $this->entrySamePrice = $this->makeEntry('Jump alt', $this->jump, 60, 1200);
        $this->entryNoPrice = $this->makeEntry('Jump sin precio', $this->jump, 60, null);
        $this->entryKids = $this->makeEntry('Kids 1h', $this->kids, 60, 1000);

        $this->packA = TicketType::create([
            'name' => ['es' => 'Cumple Jump'],
            'zone_id' => $this->cumple->id,
            'type' => TicketType::TYPE_PACK,
            'duration_min' => 120,
            'min_qty' => 8,
            'max_qty' => 20,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 1,
        ]);

        $this->addonX = TicketType::create([
            'name' => ['es' => 'Calcetines'],
            'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 1,
        ]);
        // El complemento pertenece SOLO a entryA (entryPricier no lo admite → huérfano al cambiar).
        $this->entryA->addons()->sync([$this->addonX->id]);

        $this->day = Carbon::today()->addDays(7)->toDateString();

        // Slots de la zona jump (capacidad 10) a 10:00 y 11:00.
        foreach (['10:00:00', '11:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->jump->id,
                'date' => $this->day,
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 10,
                'online_capacity' => 10,
                'online_sales_open' => true,
                'status' => Slot::STATUS_OPEN,
            ]);
        }
        // Slots de la zona cumpleaños para packs: 10:00, 11:00 y 12:00. El pack dura 120 min, así que
        // su franja de entrada (10:00) necesita franjas contiguas que cubran toda la fiesta (hasta las
        // 12:00) — si no, la disponibilidad es 0 (no se vende una fiesta que se sale del horario).
        foreach (['10:00:00', '11:00:00', '12:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->cumple->id,
                'date' => $this->day,
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 50,
                'online_capacity' => 50,
                'online_sales_open' => true,
                'status' => Slot::STATUS_OPEN,
            ]);
        }
    }

    // ─── Cantidad (entrada) ──────────────────────────────────────────────

    public function test_quantity_increase_applies_extra_due_and_emails(): void
    {
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder(seats: 2); // 2 × 12.00 = 24.00

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['quantity' => 4]),
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame(4, (int) $item->quantity);
        $this->assertSame(4, (int) $item->seats);
        $this->assertSame(1200, (int) $item->unit_price); // tarifa histórica conservada

        $adj = OrderAdjustment::where('order_item_id', $item->id)->first();
        $this->assertNotNull($adj);
        $this->assertSame(2400, (int) $adj->amount_cents); // 2 unidades extra × 12.00

        // #171: el context del ajuste guarda el cambio ESTRUCTURADO (old/new),
        // no solo la clave — así el desglose "A cobrar en el parque" pinta el
        // delta exacto vía breakdownLabel().
        $this->assertSame(['old' => 2, 'new' => 4], $adj->context['changes']['quantity_change'] ?? null);
        $this->assertStringStartsWith('+2 ', $adj->breakdownLabel());

        $this->assertNotNull(AuditLog::where('action', 'orders.item_edited')->first());
        Notification::assertSentTo($order->user, OrderItemModified::class, function (OrderItemModified $n): bool {
            return ($n->extraDueCents === 2400)
                && isset($n->changes['quantity_change'])
                && $n->changes['quantity_change']['new'] === 4;
        });
    }

    public function test_quantity_decrease_is_cancel_only_no_auto_refund(): void
    {
        // #225 (D8): bajar cantidad = SOLO cancelar. NO se auto-reembolsa (cancelar ≠ reembolsar);
        // el sobre-cobro aflora como «pendiente de devolución» y el operador lo reembolsa aparte.
        Notification::fake();
        [$order, $item, $payment] = $this->makePaidEntryOrderWithPayment(seats: 3); // 3 × 12 = 36.00
        $this->fakeRedsysOk($payment);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['quantity' => 1]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $item->refresh();
        $this->assertSame(1, (int) $item->quantity);

        // NO hay reembolso automático ni llamada a Redsys.
        $this->assertSame(0, PaymentRefund::where('order_item_id', $item->id)->count());
        Http::assertNothingSent();
        $order->refresh()->load('adjustments', 'items.ticketType', 'payments.refunds');
        $this->assertSame(0, (int) $order->refund_amount_cents);

        // El sobre-cobro (2 uds × 12 = 24.00) aflora como «pendiente de devolución»: a nivel pedido
        // (total inmutable − valor actual) y a nivel item (reconstrucción vía el marcador #225/D8).
        $this->assertSame(2400, $order->financialSummary()->pendienteDevolucion());
        $this->assertSame(2400, $order->itemPendingRefundCents($item));

        // (T5 §25.5: el `refundedCents` que este cierre comprobaba se RETIRÓ — iba cableado a null.)
        Notification::assertSentTo($order->user, OrderItemModified::class,
            fn (OrderItemModified $n): bool => $n->extraDueCents === null);
    }

    public function test_increase_then_decrease_credits_gate_without_phantom_or_online_refund(): void
    {
        // Núcleo del bug JJ-WIMWJW por el flujo real: subir y bajar la misma
        // cantidad debe ANULAR el cargo de puerta (crédito), dejar neto 0 y NO
        // reembolsar online (lo añadido era cargo de puerta, nunca cobrado).
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder(seats: 1); // 1 × 12 = 12.00 online
        $staff = $this->staffWithEdit();

        // Subir 1 → 3: cargo de puerta +24.00 (extra_due).
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem', data: $this->editData($item, ['quantity' => 3]), arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $item->refresh();
        $this->assertSame(2400, $order->fresh()->load('adjustments')->itemExtraDueCents($item));

        // Bajar 3 → 1: crédito de puerta −24.00 (netea el cargo), sin reembolso online.
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem', data: $this->editData($item->fresh('slot'), ['quantity' => 1]), arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $item->refresh();
        $order->refresh()->load('adjustments', 'items.ticketType', 'payments.refunds');

        $this->assertSame(1, (int) $item->quantity);
        $this->assertSame(0, $order->itemExtraDueCents($item));            // neto 0, sin fantasma
        $this->assertSame(0, $order->financialSummary()->pendingAtGate());
        $this->assertSame([], $order->pendingAtGateLines());               // sin renglón fantasma
        $this->assertSame(0, PaymentRefund::where('order_item_id', $item->id)->count()); // no se reembolsó online
        $this->assertTrue($order->adjustments->contains(fn (OrderAdjustment $a): bool => (int) $a->amount_cents < 0)); // hay crédito
    }

    public function test_decrease_credits_pending_gate_then_leaves_remainder_pending(): void
    {
        // Subir y luego bajar MÁS de lo subido: el crédito anula el cargo de puerta pendiente; el
        // remanente cobrado online NO se auto-reembolsa (D8) → queda «pendiente de devolución».
        Notification::fake();
        [$order, $item, $payment] = $this->makePaidEntryOrderWithPayment(seats: 3); // 3 × 12 = 36.00 online
        $this->fakeRedsysOk($payment);
        $staff = $this->staffWithEdit();

        // Subir 3 → 4: cargo de puerta +12.00.
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem', data: $this->editData($item, ['quantity' => 4]), arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $item->refresh();

        // Bajar 4 → 1: crédito 12.00 (anula el cargo) + 24.00 online sobrante → pendiente (SIN refund).
        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem', data: $this->editData($item->fresh('slot'), ['quantity' => 1]), arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $item->refresh();
        $order->refresh()->load('adjustments', 'items.ticketType', 'payments.refunds');

        $this->assertSame(1, (int) $item->quantity);
        $this->assertSame(0, $order->itemExtraDueCents($item));            // cargo de puerta neteado a 0
        $this->assertSame(0, $order->financialSummary()->pendingAtGate());
        $this->assertSame(0, PaymentRefund::where('order_item_id', $item->id)->count()); // SIN auto-refund
        // El remanente online (24.00, las 2 uds por debajo de lo cobrado) queda pendiente de devolver.
        $this->assertSame(2400, $order->itemPendingRefundCents($item));
    }

    public function test_quantity_increase_beyond_capacity_blocked(): void
    {
        Notification::fake();
        // Slot capacidad 10; item ocupa 2. Otra orden ocupa 7 → quedan 1 libre.
        [$order, $item] = $this->makePaidEntryOrder(seats: 2);
        $this->occupySlot($this->slotAt('10:00:00'), 7);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['quantity' => 5]), // necesita 5, libres (excl. self) = 3
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame(2, (int) $item->quantity); // sin cambio
        $log = AuditLog::where('action', 'orders.item_edit_blocked')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('insufficient_capacity_at_save', $log->payload['reason']);
        $this->assertNull(OrderAdjustment::where('order_item_id', $item->id)->first());
    }

    public function test_quantity_grow_on_same_slot_excludes_own_footprint(): void
    {
        // El item ocupa 8 de 10 plazas en SU slot. Crecer a 10 debe CABER
        // (otros = 0; se descuenta su propia huella). Sin `excludeItemId` el
        // cómputo daría 2 libres y bloquearía erróneamente.
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder(seats: 8);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['quantity' => 10]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $item->refresh();
        $this->assertSame(10, (int) $item->quantity);
        $this->assertSame(10, (int) $item->seats);
    }

    public function test_item_edited_audit_carries_price_diff_and_changes(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 2);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['quantity' => 3]),
                arguments: ['item' => $item->id],
            );

        $log = AuditLog::where('action', 'orders.item_edited')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame(1200, (int) $log->payload['price_diff_cents']);
        $this->assertSame(2, (int) $log->payload['from_quantity']);
        $this->assertSame(3, (int) $log->payload['to_quantity']);
        $this->assertContains('quantity_change', $log->payload['changes']);
    }

    // ─── Producto (entrada) ──────────────────────────────────────────────

    public function test_product_change_pricier_applies_extra_due(): void
    {
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder(seats: 1); // entryA 12.00

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['product_id' => $this->entryPricier->id]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $item->refresh();
        $this->assertSame($this->entryPricier->id, (int) $item->ticket_type_id);
        $this->assertSame(2000, (int) $item->unit_price); // catálogo del producto nuevo

        $adj = OrderAdjustment::where('order_item_id', $item->id)->first();
        $this->assertNotNull($adj);
        $this->assertSame(800, (int) $adj->amount_cents); // 20.00 − 12.00
        Notification::assertSentTo($order->user, OrderItemModified::class,
            fn (OrderItemModified $n): bool => isset($n->changes['product_change']) && $n->extraDueCents === 800);
    }

    public function test_product_change_cheaper_is_cancel_only_no_auto_refund(): void
    {
        // #225 (D8): cambiar a un producto MÁS BARATO no auto-reembolsa; el sobre-cobro aflora como
        // «pendiente de devolución» (a nivel pedido) y el operador lo reembolsa aparte.
        Notification::fake();
        [$order, $item, $payment] = $this->makePaidEntryOrderWithPayment(seats: 1); // entryA 12.00
        $this->fakeRedsysOk($payment);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['product_id' => $this->entryCheaper->id]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $item->refresh();
        $this->assertSame($this->entryCheaper->id, (int) $item->ticket_type_id);
        $this->assertSame(800, (int) $item->unit_price);

        $this->assertSame(0, PaymentRefund::where('order_item_id', $item->id)->count());
        Http::assertNothingSent();
        $order->refresh()->load('adjustments', 'items.ticketType', 'payments.refunds');
        $this->assertSame(0, (int) $order->refund_amount_cents);
        $this->assertSame(400, $order->financialSummary()->pendienteDevolucion()); // 12.00 − 8.00, pendiente
    }

    public function test_product_change_same_price_no_money_just_update(): void
    {
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['product_id' => $this->entrySamePrice->id]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $item->refresh();
        $this->assertSame($this->entrySamePrice->id, (int) $item->ticket_type_id);
        $this->assertNull(OrderAdjustment::where('order_item_id', $item->id)->first());
        $this->assertNull(PaymentRefund::where('order_item_id', $item->id)->first());
        Notification::assertSentTo($order->user, OrderItemModified::class,
            fn (OrderItemModified $n): bool => $n->extraDueCents === null
                && isset($n->changes['product_change']));
    }

    public function test_product_change_to_unpriced_product_blocked(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['product_id' => $this->entryNoPrice->id]),
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame($this->entryA->id, (int) $item->ticket_type_id); // sin cambio
        $log = AuditLog::where('action', 'orders.item_edit_blocked')->latest()->first();
        $this->assertSame('product_unavailable_on_date', $log->payload['reason']);
    }

    // Los siguientes escenarios los rechaza ANTES la validación de Filament
    // (Select `in:options` + min/max del TextInput). El handler los re-rechaza
    // como defense in depth ante manipulación del form: se testea el validador
    // PURO `validateItemEditTarget` por reflexión (sin notificaciones), igual
    // que `validateNewSlot` en ManageItemSlotChangeTest.

    public function test_validate_target_blocks_cross_zone_product(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);
        $this->assertSame(
            'cross_zone_change_forbidden_product',
            $this->invokeValidateTarget($order, $item, $this->entryKids, 1),
        );
    }

    public function test_validate_target_blocks_cross_type_product(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);
        $this->assertSame(
            'cross_type_change_forbidden',
            $this->invokeValidateTarget($order, $item, $this->packA, 1),
        );
    }

    public function test_validate_target_blocks_non_sellable_product(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);
        $this->entryPricier->update(['is_sellable' => false]);
        $this->assertSame(
            'invalid_product',
            $this->invokeValidateTarget($order, $item, $this->entryPricier->fresh(), 1),
        );
    }

    public function test_validate_target_blocks_invalid_quantity(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);
        $this->assertSame('invalid_quantity', $this->invokeValidateTarget($order, $item, $this->entryA, 0));
    }

    public function test_validate_target_passes_valid_same_zone_product_change(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);
        $this->assertNull($this->invokeValidateTarget($order, $item, $this->entryPricier, 2));
    }

    public function test_orphan_addons_block_product_change(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);
        // Complemento hijo del producto actual (entryA), que entryPricier NO admite.
        OrderItem::create([
            'order_id' => $order->id,
            'parent_item_id' => $item->id,
            'ticket_type_id' => $this->addonX->id,
            'slot_id' => null,
            'quantity' => 1,
            'seats' => 0,
            'unit_price' => 200,
        ]);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['product_id' => $this->entryPricier->id]),
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame($this->entryA->id, (int) $item->ticket_type_id);
        $log = AuditLog::where('action', 'orders.item_edit_blocked')->latest()->first();
        $this->assertSame('orphan_addons', $log->payload['reason']);
        $this->assertNull(OrderAdjustment::where('order_item_id', $item->id)->first());
    }

    // ─── Packs (rango de invitados) ──────────────────────────────────────

    public function test_pack_guests_increase_within_range_applies_extra_due(): void
    {
        Notification::fake();
        [$order, $item] = $this->makePaidPackOrder(guests: 8); // 8 × 15.00

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['quantity' => 12]),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $item->refresh();
        $this->assertSame(12, (int) $item->quantity);
        $adj = OrderAdjustment::where('order_item_id', $item->id)->first();
        $this->assertSame(6000, (int) $adj->amount_cents); // 4 invitados × 15.00
    }

    /**
     * `AFORO-06` en la rama de PACK de `edit()` (la de entrada ya tenía test): un pack de 8 en una
     * franja con cupo de 10 invitados crece a 10. Sin `excludeItemId` la revalidación bajo el lock
     * se contaría a sí mismo (10 − 8 = 2 < 10) y bloquearía siempre. Ganó su test en la extracción
     * 4b: la mutación «sin excluir la huella» en la rama de pack salía VERDE.
     */
    public function test_pack_can_grow_within_its_own_slot_up_to_the_guest_cap(): void
    {
        $this->setPackGuestCap(10);
        [$order, $item] = $this->makePaidPackOrder(guests: 8);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem', data: $this->editData($item, ['quantity' => 10]), arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $this->assertSame(10, (int) $item->fresh()->quantity);
        $this->assertNull(AuditLog::where('action', 'orders.item_edit_blocked')->first());
    }

    /**
     * T1 del libro (`specs/desglose-libro.md` §4.2): una BAJADA es UN hecho con su delta entero,
     * también cuando la señal la absorbe. Hasta la T1 se escribía como crédito contra el resto de la
     * señal (y un marcador de 0 € solo si ningún cubo la absorbía); ahora la fila lleva −Δ y qué
     * parte absorbe la puerta lo deriva la lectura (`GateBuckets`), que aquí deja el resto de la
     * señal en 120,00 €.
     */
    public function test_reduction_absorbed_by_the_deposit_is_one_edit_row_with_the_whole_delta(): void
    {
        [$order, $item] = $this->makePaidPackOrder(guests: 10);
        // El pack tiene señal: el resto (10 × 15,00 € = 150,00 €) está pendiente de cobro en puerta.
        $order->adjustments()->create([
            'order_item_id' => $item->id, 'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
            'amount_cents' => 15000, 'currency' => 'EUR', 'reason' => 'deposit_split',
            'applied_by' => $this->staffWithEdit()->id,
        ]);

        // 10 → 8 invitados (el mínimo del pack del fixture es 8: una bajada mayor la rechaza el form).
        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem', data: $this->editData($item->fresh(), ['quantity' => 8]), arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $adjustments = OrderAdjustment::where('order_item_id', $item->id)->get();
        $edits = $adjustments->where('type', OrderAdjustment::TYPE_EDIT);
        $this->assertCount(1, $edits, 'una gestión, un hecho');
        $this->assertSame(-3000, (int) $edits->first()->amount_cents, 'la bajada entera: 2 × 15,00 €');
        $this->assertSame(
            0, $adjustments->where('amount_cents', 0)->count(),
            'ninguna fila de 0 €: el importe es el hecho, no un marcador que reconstruir',
        );
        $this->assertSame(
            15000, (int) $adjustments->where('type', OrderAdjustment::TYPE_DEPOSIT_SPLIT)->sum('amount_cents'),
            'el reparto de la señal al nacer no se toca: es un hecho de nacimiento',
        );
        $fresh = $order->fresh(['adjustments', 'items']);
        $this->assertSame(12000, $fresh->itemDepositRemainderCents($item->fresh()), 'la lectura absorbe la bajada contra el resto de la señal: 150,00 − 30,00');
        $this->assertSame(0, $fresh->itemExtraDueCents($item->fresh()), 'y nada queda en el cubo de ediciones');
    }

    public function test_pack_quantity_increase_rescales_per_guest_paid_addon_and_charges_delta(): void
    {
        // Auditoría Fase 1 (M4): subir invitados del pack re-escala un complemento PER-INVITADO de PAGO
        // (su cantidad efectiva = invitados) y cobra el delta en puerta (extra_due ATADO al child). Antes
        // del fix el child se quedaba en los invitados viejos → INFRA-cobro silencioso.
        Notification::fake();
        $menu = $this->makePerGuestPaidAddon(); // 5,00 €/invitado, atado a packA
        [$order, $item] = $this->makePaidPackOrder(guests: 8);
        $child = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $menu->id, 'slot_id' => null,
            'quantity' => 8, 'free_quantity' => 0, 'unit_price' => 500, 'seats' => 0,
        ]);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem', data: $this->editData($item, ['quantity' => 12]), arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $this->assertSame(12, (int) $child->fresh()->quantity, 'el complemento per-invitado sigue al nuevo nº de invitados');

        $childAdj = OrderAdjustment::where('order_item_id', $child->id)
            ->where('type', OrderAdjustment::TYPE_EDIT)->first();
        $this->assertNotNull($childAdj, 'el delta del complemento per-invitado se cobra en puerta');
        $this->assertSame(2000, (int) $childAdj->amount_cents); // (12−8) × 5,00 €
    }

    public function test_pack_quantity_decrease_rescales_per_guest_paid_addon_and_surfaces_overcharge(): void
    {
        // Auditoría Fase 1 (M4): bajar invitados re-escala el complemento per-invitado y el SOBRE-cobro
        // aflora como «pendiente de devolución» (antes era invisible). 12 → 8 invitados.
        Notification::fake();
        $menu = $this->makePerGuestPaidAddon();
        [$order, $item] = $this->makePaidPackOrder(guests: 12);
        $child = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $menu->id, 'slot_id' => null,
            'quantity' => 12, 'free_quantity' => 0, 'unit_price' => 500, 'seats' => 0,
        ]);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem', data: $this->editData($item, ['quantity' => 8]), arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $this->assertSame(8, (int) $child->fresh()->quantity);
        // El sobre-cobro del complemento (4 × 5,00 = 20,00 €) aflora como pendiente de devolución del child.
        $order->refresh()->load('adjustments', 'items.ticketType', 'payments.refunds');
        $this->assertSame(2000, (int) $order->itemPendingRefundCents($child->fresh()));
    }

    public function test_validate_target_blocks_pack_guests_above_max(): void
    {
        [$order, $item] = $this->makePaidPackOrder(guests: 8);
        $this->assertSame('pack_quantity_range', $this->invokeValidateTarget($order, $item, $this->packA, 25));
    }

    public function test_validate_target_blocks_pack_guests_below_min(): void
    {
        [$order, $item] = $this->makePaidPackOrder(guests: 8);
        $this->assertSame('pack_quantity_range', $this->invokeValidateTarget($order, $item, $this->packA, 5));
    }

    public function test_validate_target_blocks_orphan_addons_on_product_change(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);
        OrderItem::create([
            'order_id' => $order->id,
            'parent_item_id' => $item->id,
            'ticket_type_id' => $this->addonX->id,
            'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
        ]);
        // entryPricier NO admite addonX → huérfano.
        $this->assertSame(
            'orphan_addons',
            $this->invokeValidateTarget($order, $item->fresh(), $this->entryPricier, 1),
        );
    }

    // ─── Cómputo de precio (helper puro computeEditPricing) ──────────────

    public function test_compute_pricing_quantity_change_uses_historical_unit(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 2); // 2 × 12.00 = 24.00
        $pricing = $this->invokeComputePricing($order, $item, $this->entryA->id, 4, $this->day);
        $this->assertSame(2400, $pricing['old']);
        $this->assertSame(4800, $pricing['new']); // 4 × 12.00 (tarifa histórica)
        $this->assertSame(2400, $pricing['diff']);
    }

    public function test_compute_pricing_product_change_uses_catalog_rate(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1); // 12.00
        $pricing = $this->invokeComputePricing($order, $item, $this->entryPricier->id, 1, $this->day);
        $this->assertSame(2000, $pricing['new']); // catálogo entryPricier
        $this->assertSame(800, $pricing['diff']);
    }

    public function test_compute_pricing_unpriced_product_returns_null(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);
        $pricing = $this->invokeComputePricing($order, $item, $this->entryNoPrice->id, 1, $this->day);
        $this->assertNull($pricing['new']);
        $this->assertNull($pricing['diff']);
    }

    // ─── Defense in depth ────────────────────────────────────────────────

    public function test_stale_optimistic_token_blocks_edit(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 2);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['quantity' => 4, 'optimistic_token' => '0']),
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame(2, (int) $item->quantity);
        $log = AuditLog::where('action', 'orders.item_edit_blocked')->latest()->first();
        $this->assertSame('stale_item_version', $log->payload['reason']);
    }

    public function test_edit_without_permission_blocked(): void
    {
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder(seats: 2);

        Livewire::actingAs($this->staffWithoutEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item, ['quantity' => 4]),
                arguments: ['item' => $item->id],
            );

        $item->refresh();
        $this->assertSame(2, (int) $item->quantity);
        Notification::assertNothingSentTo($order->user);
    }

    public function test_item_from_other_order_rejected(): void
    {
        // IDOR: el item pertenece a otro Order. El guard temprano de
        // `executeManageItemSave` (`order_id` mismatch) lo corta como
        // `not_found` ANTES de tocar nada — la propiedad de seguridad es que
        // el item NO se modifica.
        [$orderA, $itemA] = $this->makePaidEntryOrder(seats: 2);
        [$orderB] = $this->makePaidEntryOrder(seats: 1);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $orderB->code])
            ->callAction('manageItem',
                data: $this->editData($itemA, ['quantity' => 4]),
                arguments: ['item' => $itemA->id],
            );

        $itemA->refresh();
        $this->assertSame(2, (int) $itemA->quantity);
        $this->assertNull(OrderAdjustment::where('order_item_id', $itemA->id)->first());
    }

    public function test_cancelled_item_is_not_editable(): void
    {
        // Un item cancelado es terminal: `editItemBlockedReason` lo marca
        // (capa 2 del handler) y el modal no rinde campos editables.
        [$order, $item] = $this->makePaidEntryOrder(seats: 2);
        $item->update(['cancelled_at' => now(), 'cancelled_by' => $order->user_id]);

        $this->assertSame('item_cancelled', $order->fresh()->editItemBlockedReason($item->fresh()));

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item->fresh(), ['quantity' => 4]),
                arguments: ['item' => $item->id],
            );

        $this->assertSame(2, (int) $item->fresh()->quantity);
        $this->assertNull(OrderAdjustment::where('order_item_id', $item->id)->first());
    }

    // ─── Combinado slot + cantidad ───────────────────────────────────────

    public function test_combined_slot_and_quantity_change_one_email_one_audit(): void
    {
        Notification::fake();
        [$order, $item] = $this->makePaidEntryOrder(seats: 2); // en slot 10:00

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->set('calendarItemId', $item->id)
            ->set('calendarSelectedDate', $this->day)
            ->set('calendarSelectedTime', '11:00:00')
            ->callAction('manageItem',
                data: $this->editData($item, ['quantity' => 3, 'slot_time' => '11:00:00']),
                arguments: ['item' => $item->id],
            )
            ->assertHasNoActionErrors();

        $item->refresh();
        $this->assertSame(3, (int) $item->quantity);
        $this->assertSame($this->slotAt('11:00:00')->id, (int) $item->slot_id);

        // Un solo audit de edit + un solo email con AMBOS cambios.
        $this->assertSame(1, AuditLog::where('action', 'orders.item_edited')->count());
        Notification::assertSentTo($order->user, OrderItemModified::class, function (OrderItemModified $n): bool {
            return isset($n->changes['slot_change']) && isset($n->changes['quantity_change']);
        });
    }

    // Nota (#225, D8): el escenario «fallo de REST al bajar cantidad» se eliminó: una bajada ya NO
    // auto-reembolsa (solo cancela), así que no hay refund REST que pueda fallar en este flujo. El
    // reembolso es ahora una acción manual e independiente (cubierta por RefundItemActionTest).

    // ─── Opciones del selector + render del Tab ──────────────────────────

    public function test_same_scope_options_only_same_type_zone_sellable_plus_current(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);

        $ref = new \ReflectionMethod(ViewOrder::class, 'sameScopeProductOptions');
        $ref->setAccessible(true);
        $page = new ViewOrder;
        $page->record = $order;
        $options = $ref->invoke($page, $item);

        // Incluye entradas de la zona jump (A, pricier, cheaper, samePrice, noPrice).
        $this->assertArrayHasKey($this->entryA->id, $options);
        $this->assertArrayHasKey($this->entryPricier->id, $options);
        // Excluye otra zona, otro tipo y addons.
        $this->assertArrayNotHasKey($this->entryKids->id, $options);
        $this->assertArrayNotHasKey($this->packA->id, $options);
        $this->assertArrayNotHasKey($this->addonX->id, $options);
    }

    public function test_manage_item_action_mounts_for_editable_item(): void
    {
        // El modal monta su schema completo del Tab 1 (Select de producto +
        // cantidad + Placeholder reactivo del diff) sin excepción. La
        // construcción del schema ejecuta `sameScopeProductOptions` y la
        // instanciación del Placeholder; un fallo de binding caería aquí.
        [$order, $item] = $this->makePaidEntryOrder(seats: 1);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('manageItem', ['item' => $item->id])
            ->assertActionMounted('manageItem')
            ->assertHasNoActionErrors();
    }

    // ─── Servicios de aforo: excludeItemId ───────────────────────────────

    public function test_slot_availability_exclude_item_id_frees_own_seats(): void
    {
        [$order, $item] = $this->makePaidEntryOrder(seats: 8); // ocupa 8 de 10
        $slot = $this->slotAt('10:00:00');

        $svc = app(SlotAvailability::class);
        // Sin excluir: 10 − 8 = 2 libres.
        $this->assertSame(2, $svc->availableFor($slot, 60));
        // Excluyendo el propio item: 10 − 0 = 10 libres.
        $this->assertSame(10, $svc->availableFor($slot, 60, [], $item->id));
    }

    public function test_pack_availability_exclude_item_id_frees_own_guests(): void
    {
        // Tope de invitados por franja = 10. El pack ocupa 8 → 2 libres; excluyéndolo, 10.
        Setting::updateOrCreate(
            ['key' => PackAvailability::SETTING_MAX_GUESTS_PER_SLOT],
            ['key' => PackAvailability::SETTING_MAX_GUESTS_PER_SLOT, 'value' => '10', 'group' => 'packs'],
        );
        [$order, $item] = $this->makePaidPackOrder(guests: 8);
        $slot = $this->packSlot();

        $svc = app(PackAvailability::class);
        $this->assertSame(2, $svc->availableGuestsFor($slot, $this->packA));
        $this->assertSame(10, $svc->availableGuestsFor($slot, $this->packA, [], $item->id));
    }

    // ─── Pulido #168: plazas de packs REALES (no topadas por max_qty) ────

    public function test_free_guest_slots_returns_real_remaining_uncapped_by_max_qty(): void
    {
        // Bug reportado: el slider de un pack "siempre ponía 20" (= max_qty)
        // aunque hubiera invitados reservados. Con cupo 60 y un pack de 20
        // invitados, `availableGuestsFor` topa en `min(40, 20)=20`; el nuevo
        // `freeGuestSlots` muestra las plazas REALES: 60−20 = 40, que SÍ
        // decrementan con cada invitado.
        $this->setPackGuestCap(60);
        [$order, $item] = $this->makePaidPackOrder(guests: 20);
        $slot = $this->packSlot();
        $svc = app(PackAvailability::class);

        $this->assertSame(20, $svc->availableGuestsFor($slot, $this->packA), 'availableGuestsFor sigue topado por max_qty (esperado).');
        $this->assertSame(40, $svc->freeGuestSlots($slot, $this->packA), 'freeGuestSlots muestra las plazas reales (60−20).');
    }

    public function test_free_guest_slots_decrements_with_guest_count(): void
    {
        $this->setPackGuestCap(60);
        $svc = app(PackAvailability::class);
        $slot = $this->packSlot();

        [$o10, $i10] = $this->makePaidPackOrder(guests: 10);
        $this->assertSame(50, $svc->freeGuestSlots($slot, $this->packA)); // 60 − 10

        // Otra fiesta de 8 en la misma franja → 60 − 18 = 42.
        $this->makePaidPackOrder(guests: 8);
        $this->assertSame(42, $svc->freeGuestSlots($slot, $this->packA));
    }

    public function test_free_guest_slots_null_when_no_guest_cap_configured(): void
    {
        // Sin tope de invitados (default 0) → no hay "plazas" que mostrar; el
        // display cae al comportamiento anterior (max_qty).
        [$order, $item] = $this->makePaidPackOrder(guests: 20);
        $this->assertNull(app(PackAvailability::class)->freeGuestSlots($this->packSlot(), $this->packA));
    }

    public function test_free_guest_slots_excludes_self_when_requested(): void
    {
        $this->setPackGuestCap(60);
        [$order, $item] = $this->makePaidPackOrder(guests: 20);
        $svc = app(PackAvailability::class);
        // Excluyendo la propia fiesta → 60 libres (para evaluar dónde recolocarla).
        $this->assertSame(60, $svc->freeGuestSlots($this->packSlot(), $this->packA, [], $item->id));
    }

    public function test_slider_shows_real_pack_plazas_decremented_by_guests(): void
    {
        // El chip de horas del slider muestra las plazas REALES del pack (no el
        // max_qty topado 20), CONTANDO la huella propia — FIDEDIGNO con la lógica
        // de reservas (#164 + #173, decisión clienta). Un pack de 20 en su franja
        // muestra 60 − 20 = 40. (El excluir la huella para crecer/recolocar se hará
        // en la gestión futura de reservas, no en el display.)
        $this->setPackGuestCap(60);
        [$order, $item] = $this->makePaidPackOrder(guests: 20);

        $ref = new \ReflectionMethod(ViewOrder::class, 'calendarTimesForItemWithSelection');
        $ref->setAccessible(true);
        $page = new ViewOrder;
        $page->record = $order;
        $times = $ref->invoke($page, $item->fresh('ticketType', 'slot'), $this->day, '10:00:00');

        $entry = collect($times)->firstWhere('time', '10:00:00');
        $this->assertNotNull($entry);
        $this->assertSame(40, $entry['available'], 'El slider muestra plazas reales (60−20), no el max_qty 20 ni la huella propia reclamada.');
    }

    // ─── Pulido #168: pendiente de cobro en el parque en los totales ─────

    public function test_order_totals_show_pending_at_gate_from_edit(): void
    {
        // Un cargo extra por edición (item activo) aparece como "A cobrar en el
        // parque" en la card Resumen del pedido.
        [$order, $item] = $this->makePaidEntryOrder(seats: 2);
        OrderAdjustment::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_EDIT,
            'amount_cents' => 800,
            'currency' => 'EUR',
            'reason' => 'item_edit',
            'applied_by' => $this->staffWithEdit()->id,
        ]);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertSee(__('admin.orders.order_financial.pending_at_gate'))
            ->assertSee('8,00')
            // Rediseño valor-primero (#199): el headline es "Valor final del pedido"
            // (antes "Total con cambios", que se eliminó del layout).
            ->assertSee(__('admin.orders.order_financial.valor_final'));
    }

    public function test_order_totals_hide_pending_at_gate_when_item_finished(): void
    {
        // Si el item del ajuste ya finalizó (servicio prestado), el extra se
        // considera cobrado implícitamente → NO se muestra como pendiente.
        $past = Slot::create([
            'zone_id' => $this->jump->id,
            'date' => Carbon::today()->subDays(2)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 10,
            'online_sales_open' => true, 'status' => Slot::STATUS_OPEN,
        ]);
        [$order, $item] = $this->makePaidEntryOrder(seats: 2);
        $item->update(['slot_id' => $past->id]); // item finalizado en la práctica
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_EDIT, 'amount_cents' => 800,
            'currency' => 'EUR', 'reason' => 'item_edit', 'applied_by' => $this->staffWithEdit()->id,
        ]);

        Livewire::actingAs($this->staffWithEdit())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->assertDontSee(__('admin.orders.order_financial.pending_at_gate'));
    }

    // ─── #171: botón "Reembolsar" al pie del modal Gestionar ──────────────

    // Nota: Filament renderiza el cuerpo+footer del modal CLIENT-SIDE; el HTML
    // server-side de los tests NO los incluye (ni "Guardar cambios" aparece).
    // Por eso la footer action se inspecciona estructuralmente sobre la action
    // MONTADA (`getMountedAction()->getExtraModalFooterActions()`), que sí está
    // ligada a Livewire y evalúa `isVisible()` con `mountedActions` poblado.
    private function manageItemFooterRefund(User $staff, Order $order, OrderItem $item): ?Action
    {
        $mounted = Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('manageItem', ['item' => $item->id])
            ->instance()
            ->getMountedAction();

        return collect($mounted->getExtraModalFooterActions())
            ->first(fn (Action $a): bool => $a->getName() === 'refundFromManage');
    }

    public function test_manage_modal_has_refund_footer_button_visible_for_refundable_item(): void
    {
        // El reembolso por-item se movió al pie del modal Gestionar (#171): con
        // permiso `orders.refund_item` y un item refundable, el botón existe,
        // se llama "Reembolsar" y es visible.
        [$order, $item] = $this->makePaidEntryOrderWithPayment(seats: 2);
        $staff = $this->staffWith(['orders.view', 'orders.edit_item', 'orders.refund_item']);

        $refund = $this->manageItemFooterRefund($staff, $order, $item);

        $this->assertNotNull($refund);
        $this->assertSame(__('admin.orders.manage_item.refund_button'), $refund->getLabel());
        $this->assertTrue($refund->isVisible());
    }

    public function test_manage_modal_refund_footer_button_hidden_without_permission(): void
    {
        // Sin permiso `orders.refund_item` (staffWithEdit no lo tiene), el botón
        // existe en el modal pero queda OCULTO.
        [$order, $item] = $this->makePaidEntryOrderWithPayment(seats: 2);

        $refund = $this->manageItemFooterRefund($this->staffWithEdit(), $order, $item);

        $this->assertNotNull($refund);
        $this->assertFalse($refund->isVisible());
    }

    public function test_manage_modal_refund_footer_button_hidden_for_fully_refunded_item(): void
    {
        // Item YA totalmente reembolsado (sin capacidad refundable) → oculto aun
        // con permiso: la visibilidad respeta `canRefundItem`, no solo el permiso.
        [$order, $item, $payment] = $this->makePaidEntryOrderWithPayment(seats: 2);
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $item->id,
            'amount_cents' => (int) $payment->amount,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $this->staffWithEdit()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);
        $order->update(['refund_amount_cents' => (int) $payment->amount, 'refunded_at' => now()]);
        $staff = $this->staffWith(['orders.view', 'orders.edit_item', 'orders.refund_item']);

        $refund = $this->manageItemFooterRefund($staff, $order->fresh(), $item->fresh());

        $this->assertNotNull($refund);
        $this->assertFalse($refund->isVisible());
    }

    private function manageItemFooterCancel(User $staff, Order $order, OrderItem $item): ?Action
    {
        $mounted = Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('manageItem', ['item' => $item->id])
            ->instance()
            ->getMountedAction();

        return collect($mounted->getExtraModalFooterActions())
            ->first(fn (Action $a): bool => $a->getName() === 'cancelFromManage');
    }

    public function test_manage_modal_has_cancel_footer_button_for_cancellable_item(): void
    {
        // #172: el botón "Cancelar producto" se añade al pie del modal Gestionar
        // (manteniendo el icono de la sub-card). Con permiso `orders.cancel_item`
        // y un item cancelable, existe, se llama "Cancelar producto" y es visible.
        [$order, $item] = $this->makePaidEntryOrderWithPayment(seats: 2);
        $staff = $this->staffWith(['orders.view', 'orders.edit_item', 'orders.cancel_item']);

        $cancel = $this->manageItemFooterCancel($staff, $order, $item);

        $this->assertNotNull($cancel);
        $this->assertSame(__('admin.orders.manage_item.cancel_button'), $cancel->getLabel());
        $this->assertTrue($cancel->isVisible());
    }

    public function test_manage_modal_cancel_footer_button_hidden_without_permission(): void
    {
        // Sin permiso `orders.cancel_item` (staffWithEdit no lo tiene) → oculto.
        [$order, $item] = $this->makePaidEntryOrderWithPayment(seats: 2);

        $cancel = $this->manageItemFooterCancel($this->staffWithEdit(), $order, $item);

        $this->assertNotNull($cancel);
        $this->assertFalse($cancel->isVisible());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function makeEntry(string $name, Zone $zone, int $duration, ?int $priceCents): TicketType
    {
        $type = TicketType::create([
            'name' => ['es' => $name],
            'zone_id' => $zone->id,
            'type' => TicketType::TYPE_ENTRY,
            'duration_min' => $duration,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => ++$this->counter,
        ]);

        if ($priceCents !== null) {
            $type->prices()->create([
                'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'),
                'amount_cents' => $priceCents,
            ]);
        }

        return $type;
    }

    private function staffWithEdit(): User
    {
        return $this->staffWith(['orders.view', 'orders.edit_item', 'orders.edit_event_data']);
    }

    private function staffWithoutEdit(): User
    {
        return $this->staffWith(['orders.view']);
    }

    /**
     * Invoca el validador puro `validateItemEditTarget`, que desde la extracción 4b es un método
     * PÚBLICO de `OrderItemEditor` (antes, privado de `ViewOrder` por reflexión). El `$order` ya no
     * hace falta: se conserva en la firma para no tocar los llamantes.
     */
    private function invokeValidateTarget(Order $order, OrderItem $item, TicketType $newType, int $newQty): ?string
    {
        return app(OrderItemEditor::class)->validateItemEditTarget($item, $newType, $newQty);
    }

    /**
     * Invoca el cómputo puro `computeEditPricing`, que desde la extracción 4b vive en
     * `ItemEditPricing` (antes, privado de `ViewOrder` por reflexión). El `$order` ya no hace
     * falta: se conserva en la firma para no tocar los llamantes.
     *
     * @return array{old:int, unit:int, new:?int, diff:?int}
     */
    private function invokeComputePricing(Order $order, OrderItem $item, int $newTypeId, int $newQty, ?string $dateStr): array
    {
        return app(ItemEditPricing::class)->computeEditPricing($item, $newTypeId, $newQty, $dateStr);
    }

    /** Configura el cupo de invitados por franja de packs (setting). */
    private function setPackGuestCap(int $guests): void
    {
        Setting::updateOrCreate(
            ['key' => PackAvailability::SETTING_MAX_GUESTS_PER_SLOT],
            ['key' => PackAvailability::SETTING_MAX_GUESTS_PER_SLOT, 'value' => (string) $guests, 'group' => 'packs'],
        );
    }

    private function staffWith(array $permissions): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        return $u;
    }

    private function slotAt(string $time): Slot
    {
        return Slot::query()->where('zone_id', $this->jump->id)
            ->where('date', $this->day)->where('start_time', $time)->firstOrFail();
    }

    private function packSlot(): Slot
    {
        return Slot::query()->where('zone_id', $this->cumple->id)
            ->where('date', $this->day)->where('start_time', '10:00:00')->firstOrFail();
    }

    /**
     * @return array{0: Order, 1: OrderItem}
     */
    private function makePaidEntryOrder(int $seats = 1): array
    {
        [$order, $item] = $this->makePaidEntryOrderWithPayment($seats);

        return [$order, $item];
    }

    /**
     * @return array{0: Order, 1: OrderItem, 2: Payment}
     */
    private function makePaidEntryOrderWithPayment(int $seats = 1): array
    {
        $total = 1200 * $seats;
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-QE'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        $payment = $this->attachPaidPayment($order);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $this->entryA->id,
            'slot_id' => $this->slotAt('10:00:00')->id,
            'quantity' => $seats, 'seats' => $seats, 'unit_price' => 1200,
        ]);

        return [$order->fresh(), $item->fresh('ticketType', 'slot'), $payment];
    }

    /**
     * @return array{0: Order, 1: OrderItem}
     */
    private function makePaidPackOrder(int $guests = 8): array
    {
        $total = 1500 * $guests;
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-QP'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        $this->attachPaidPayment($order);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $this->packA->id,
            'slot_id' => $this->packSlot()->id,
            'quantity' => $guests, 'seats' => $guests, 'unit_price' => 1500,
        ]);

        return [$order->fresh(), $item->fresh('ticketType', 'slot')];
    }

    /** Complemento PER-INVITADO de pago (5,00 €/invitado) atado a packA, para M4. */
    private function makePerGuestPaidAddon(): TicketType
    {
        $menu = TicketType::create([
            'name' => ['es' => 'Menú niño'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 0, 'position' => 90,
        ]);
        $menu->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'),
            'amount_cents' => 500,
        ]);
        $this->packA->configurableAddons()->attach($menu->id, ['quantity_mode' => 'per_guest', 'position' => 10]);

        return $menu;
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
            'gateway_order' => str_pad((string) (++$this->paymentCounter + 200000), 10, '0', STR_PAD_LEFT),
        ]);
    }

    /** Ocupa $seats plazas en $slot con una orden pagada aparte (presión de aforo). */
    private function occupySlot(Slot $slot, int $seats): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-OC'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1200 * $seats, 'total' => 1200 * $seats, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $this->entryA->id,
            'slot_id' => $slot->id,
            'quantity' => $seats, 'seats' => $seats, 'unit_price' => 1200,
        ]);
    }

    private function fakeRedsysOk(Payment $payment): void
    {
        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(PaymentRefund::REDSYS_REFUND_SUCCESS_CODE, $payment->gateway_order),
                200,
            ),
        ]);
    }

    private function fakeRedsysDenied(Payment $payment): void
    {
        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse('0950', $payment->gateway_order),
                200,
            ),
        ]);
    }

    /**
     * @return array<string, string>
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function editData(OrderItem $item, array $overrides = []): array
    {
        $slot = $item->slot;

        return array_merge([
            'optimistic_token' => (string) ($item->updated_at?->getTimestamp() ?? ''),
            'product_id' => (int) $item->ticket_type_id,
            'quantity' => (int) $item->quantity,
            'slot_date' => $slot?->date?->toDateString() ?? '',
            'slot_time' => $slot?->start_time ?? '',
            'event_data' => [],
        ], $overrides);
    }

    /**
     * ⚠️⚠️ **Editar un pedido con un complemento DESPUBLICADO reventaba** (2026-08-29, `#244`).
     *
     * `validateAddonEdits` indexa `$newPivots` con los complementos del producto, y `addons()`
     * filtra por VENDIBLE y ACTIVO — así que un hijo vivo cuyo producto dejó de serlo (lo normal:
     * se retira de la venta después de haberse vendido) no tiene entrada ahí. El acceso iba con
     * `?->`, que protege del `null` pero **no de una clave ausente**, y el edit moría con
     * «Undefined array key» antes de llegar a ninguna validación.
     *
     * Lo destapó la línea del suplemento de fiesta mixta, que es no vendible a propósito
     * (`specs/cumple-mixto.md` §12), pero el defecto no era suyo: cualquier instalación que
     * despublique un complemento vendido se lo encuentra.
     */
    public function test_editing_an_item_with_an_unpublished_addon_child_does_not_blow_up(): void
    {
        [$order, $item] = $this->makePaidPackOrder(guests: 8);

        $extra = TicketType::create([
            'name' => ['es' => 'Tarta retirada'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 0, 'position' => 91,
        ]);
        $this->packA->configurableAddons()->attach($extra->id, ['position' => 20]);
        OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $extra->id, 'slot_id' => null,
            'quantity' => 1, 'unit_price' => 2000, 'seats' => 0,
        ]);

        // El parque lo retira de la venta DESPUÉS de haberlo vendido.
        $extra->forceFill(['is_sellable' => false])->save();

        $item = $item->fresh(['ticketType', 'slot']);
        $outcome = app(OrderItemEditor::class)->edit(
            $order, $item, '', '', false,
            (int) $item->ticket_type_id, 9, null,
            ['edits' => [], 'adds' => []],
            (string) $item->updated_at->getTimestamp(),
            $this->staffWith(['orders.view', 'orders.edit_item']),
        );

        $this->assertFalse($outcome->isBlocked(), (string) $outcome->reason);
        $this->assertSame(9, (int) $item->fresh()->quantity);
    }
}
