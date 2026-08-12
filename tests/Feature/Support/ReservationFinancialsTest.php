<?php

namespace Tests\Feature\Support;

use App\Domain\Identity\Models\User;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\ReservationFinancials;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Desglose por reserva (principal + complementos) — fuente única del bloque
 * "Totales del producto" de todas las superficies. Verifica la reconciliación
 * `valor = pagadoOnline + aCobrarPuerta + cobradoPuerta` y las dimensiones de
 * devolución, incluida la herencia de "finalizado" de los complementos.
 */
class ReservationFinancialsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private TicketType $addon;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'zone_id' => $this->zone->id,
            'type' => TicketType::TYPE_ADDON, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 0, 'position' => 2,
        ]);
    }

    public function test_plain_product_has_value_paid_online_and_no_activity(): void
    {
        $order = $this->makeOrder(2400);
        $item = $this->makeItem($order, qty: 2, unit: 1200);

        $f = ReservationFinancials::make($order->fresh(['items', 'adjustments', 'payments.refunds']), $item);

        $this->assertSame(2400, $f->valor);
        $this->assertSame(2400, $f->pagadoOnline);
        $this->assertSame(0, $f->aCobrarPuerta);
        $this->assertSame(0, $f->cobradoPuerta);
        $this->assertSame(0, $f->devuelto);
        $this->assertSame(0, $f->pendienteReembolso);
        $this->assertFalse($f->hasActivity());
    }

    public function test_gate_charge_splits_value_into_online_and_gate(): void
    {
        // Subida de 2 → 3 (cobro en puerta de 1 ud) sobre reserva NO finalizada.
        $order = $this->makeOrder(2400);
        $by = User::factory()->create();
        $item = $this->makeItem($order, qty: 3, unit: 1200); // estado tras subir
        $order->applyExtraDue($item, 1200, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 3]]]);

        $f = ReservationFinancials::make($order->fresh(['items', 'adjustments', 'payments.refunds']), $item->fresh());

        $this->assertSame(3600, $f->valor);          // 3 × 12
        $this->assertSame(2400, $f->pagadoOnline);   // las 2 pagadas online
        $this->assertSame(1200, $f->aCobrarPuerta);  // la añadida, pendiente en puerta
        $this->assertSame(0, $f->cobradoPuerta);
        // Reconciliación.
        $this->assertSame($f->valor, $f->pagadoOnline + $f->aCobrarPuerta + $f->cobradoPuerta);
    }

    public function test_finished_reservation_marks_gate_as_collected(): void
    {
        $order = $this->makeOrder(2400);
        $by = User::factory()->create();
        $item = $this->makeItem($order, qty: 3, unit: 1200, past: true); // slot pasado → finalizado
        $order->applyExtraDue($item, 1200, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 3]]]);

        $f = ReservationFinancials::make($order->fresh(['items', 'adjustments', 'payments.refunds']), $item->fresh());

        $this->assertSame(0, $f->aCobrarPuerta);     // ya pasó → no pendiente
        $this->assertSame(1200, $f->cobradoPuerta);  // cobrado en puerta
        $this->assertSame($f->valor, $f->pagadoOnline + $f->cobradoPuerta);
    }

    public function test_cancelled_child_surfaces_pending_refund(): void
    {
        $order = $this->makeOrder(2400);
        $by = User::factory()->create();
        $item = $this->makeItem($order, qty: 1, unit: 1200);
        $child = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id,
            'ticket_type_id' => $this->addon->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 500,
        ]);
        $child->markCancelled($by); // cobrado online 500, no devuelto

        $f = ReservationFinancials::make($order->fresh(['items.children', 'adjustments', 'payments.refunds']), $item->fresh('children'));

        $this->assertSame(1200, $f->valor);             // el complemento cancelado NO cuenta en el valor
        $this->assertSame(500, $f->pendienteReembolso); // su cobrado online aún no devuelto
    }

    public function test_succeeded_refund_shows_as_devuelto(): void
    {
        $order = $this->makeOrder(1200);
        $item = $this->makeItem($order, qty: 1, unit: 1200);
        $payment = Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 1200, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (++$this->counter + 700000), 10, '0', STR_PAD_LEFT),
        ]);
        PaymentRefund::create([
            'payment_id' => $payment->id, 'order_item_id' => $item->id,
            'amount_cents' => 500, 'currency' => 'EUR', 'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST, 'gateway_order' => $payment->gateway_order,
            'requested_by' => User::factory()->create()->id, 'requested_at' => now(), 'processed_at' => now(),
        ]);

        $f = ReservationFinancials::make($order->fresh(['items', 'adjustments', 'payments.refunds']), $item->fresh());

        $this->assertSame(500, $f->devuelto);
        $this->assertSame(0, $f->pendienteReembolso); // no está cancelado, es un refund de cortesía
    }

    public function test_reduced_active_item_surfaces_pending_refund_via_reconstruction(): void
    {
        // Patrón JJ-WIMWJW: el item se subió y bajó (deja `quantity_change` con old=2)
        // y acabó en qty 1 con extra_due neto 0; el cliente pagó 2 online → se le debe 1.
        // Robustez #198: el "Pendiente de devolución" sale también en la card del producto.
        $order = $this->makeOrder(2400);
        $by = User::factory()->create();
        $item = $this->makeItem($order, qty: 1, unit: 1200);
        $order->applyExtraDue($item, 1200, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 3]]]);
        $order->applyGateCredit($item->fresh(), 1200, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 3, 'new' => 1]]]);

        $f = ReservationFinancials::make($order->fresh(['items', 'adjustments', 'payments.refunds']), $item->fresh());

        $this->assertSame(1200, $f->valor);              // qty 1 actual
        $this->assertSame(0, $f->aCobrarPuerta);         // las subidas/bajadas netearon
        $this->assertSame(1200, $f->pendienteReembolso); // pagó 2, tiene 1 → se le debe 1
    }

    public function test_deposit_remainder_splits_value_into_deposit_and_gate(): void
    {
        // #225: una línea con señal (valor 180, señal 30 → resto 150 a puerta), NO finalizada.
        $order = $this->makeOrder(18000);
        $item = $this->makeItem($order, qty: 1, unit: 18000);
        $this->makeDepositRemainder($order, $item, 15000);

        $f = ReservationFinancials::make($order->fresh(['items', 'adjustments', 'payments.refunds']), $item->fresh());

        $this->assertSame(18000, $f->valor);
        $this->assertSame(3000, $f->pagadoOnline);    // la señal cobrada online
        $this->assertSame(15000, $f->aCobrarPuerta);  // el resto, pendiente en puerta
        $this->assertSame(0, $f->cobradoPuerta);
        $this->assertSame($f->valor, $f->pagadoOnline + $f->aCobrarPuerta + $f->cobradoPuerta);
        $this->assertTrue($f->hasActivity());
    }

    public function test_finished_deposit_marks_remainder_as_collected_at_gate(): void
    {
        $order = $this->makeOrder(18000);
        $item = $this->makeItem($order, qty: 1, unit: 18000, past: true); // slot pasado → finalizado
        $this->makeDepositRemainder($order, $item, 15000);

        $f = ReservationFinancials::make($order->fresh(['items', 'adjustments', 'payments.refunds']), $item->fresh());

        $this->assertSame(3000, $f->pagadoOnline);
        $this->assertSame(0, $f->aCobrarPuerta);      // pasó → ya no pendiente
        $this->assertSame(15000, $f->cobradoPuerta);  // cobrado en puerta
        $this->assertSame($f->valor, $f->pagadoOnline + $f->cobradoPuerta);
    }

    public function test_no_phantom_pending_refund_when_increasing_quantity_of_a_deposit_pack(): void
    {
        // #225 F1: subir la cantidad de un pack con señal NO debe hacer aparecer un «Pendiente
        // de devolución» fantasma en la card (bug reportado: pack 10→11 invitados → 150 € fantasma).
        // Causa: itemOriginalOnlineCents reconstruía el VALOR original (180), no la SEÑAL (30).
        $by = User::factory()->create();
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'position' => 5, 'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 3000,
        ]);
        $order = $this->makeOrder(19800);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => '18:00:00', 'end_time' => '20:00:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);
        // Estado tras subir a 11 invitados (valor 198 €, unit 18 €).
        $item = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null, 'ticket_type_id' => $pack->id,
            'slot_id' => $slot->id, 'quantity' => 11, 'seats' => 11, 'unit_price' => 1800,
        ]);
        // Resto-señal del valor ORIGINAL (qty 10 → 180 − 30 = 150) + subida 10→11 (+18 a puerta).
        $this->makeDepositRemainder($order, $item, 15000);
        $order->applyExtraDue($item, 1800, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 10, 'new' => 11]]]);

        $f = ReservationFinancials::make($order->fresh(['items', 'adjustments', 'payments.refunds']), $item->fresh());

        $this->assertSame(19800, $f->valor);
        $this->assertSame(3000, $f->pagadoOnline);     // la señal (cobrado online), CONGELADA
        $this->assertSame(16800, $f->aCobrarPuerta);   // 150 resto-señal + 18 subida
        $this->assertSame(0, $f->pendienteReembolso);  // SIN fantasma (F1)
        $this->assertSame($f->valor, $f->pagadoOnline + $f->aCobrarPuerta + $f->cobradoPuerta);
    }

    // ─── reservationGateLines() — desglose ↳ de «A cobrar en el parque» (#225 F2) ───

    public function test_reservation_gate_lines_name_the_deposit_remainder(): void
    {
        // Pack con señal (valor 180, señal 30 → resto 150 a puerta), NO finalizado: el
        // desglose ↳ de la card lo nombra «Resto de la señal» y cuadra con el agregado.
        $order = $this->makeOrder(18000);
        $item = $this->makeItem($order, qty: 1, unit: 18000);
        $this->makeDepositRemainder($order, $item, 15000);

        $fresh = $order->fresh(['items.children', 'adjustments']);
        $principal = $fresh->items->firstWhere('id', $item->id);

        $lines = $fresh->reservationGateLines($principal);

        $this->assertCount(1, $lines);
        // La línea «Resto de la señal» nombra su producto («de Jump 1h»), #225 feedback clienta.
        $this->assertStringContainsString(__('admin.orders.item_financial.deposit_remainder_line'), $lines[0]['label']);
        $this->assertStringContainsString($this->entry->tr('name'), $lines[0]['label']);
        $this->assertSame(15000, $lines[0]['amount']);
        // Reconciliación: Σ(↳) == aCobrarPuerta de la card.
        $rf = ReservationFinancials::make($fresh, $principal);
        $this->assertSame($rf->aCobrarPuerta, array_sum(array_column($lines, 'amount')));
    }

    public function test_reservation_gate_lines_combine_edit_charge_and_deposit_remainder(): void
    {
        // Señal + subida de cantidad (10→11): dos líneas ↳ —el cargo de edición y el resto
        // de la señal— cuya Σ == aCobrarPuerta (mismo caso que el bug-fantasma F1).
        $by = User::factory()->create();
        $order = $this->makeOrder(19800);
        $item = $this->makeItem($order, qty: 11, unit: 1800);
        $this->makeDepositRemainder($order, $item, 15000);
        $order->applyExtraDue($item, 1800, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 10, 'new' => 11]]]);

        $fresh = $order->fresh(['items.children', 'adjustments']);
        $principal = $fresh->items->firstWhere('id', $item->id);

        $lines = $fresh->reservationGateLines($principal);
        $labels = array_column($lines, 'label');

        $this->assertContains('+1 '.$this->entry->tr('name'), $labels);                          // cargo de edición
        $this->assertStringContainsString(__('admin.orders.item_financial.deposit_remainder_line'), implode(' | ', $labels)); // resto-señal (nombrado «de X»)
        $rf = ReservationFinancials::make($fresh, $principal);
        $this->assertSame(16800, $rf->aCobrarPuerta);                                             // 150 + 18
        $this->assertSame($rf->aCobrarPuerta, array_sum(array_column($lines, 'amount')));
    }

    public function test_reservation_gate_lines_empty_for_finished_reservation(): void
    {
        // Reserva finalizada → su puerta ya se cobró (implícito) → sin desglose pendiente.
        $order = $this->makeOrder(18000);
        $item = $this->makeItem($order, qty: 1, unit: 18000, past: true);
        $this->makeDepositRemainder($order, $item, 15000);

        $fresh = $order->fresh(['items.children', 'adjustments']);
        $principal = $fresh->items->firstWhere('id', $item->id);

        $this->assertSame([], $fresh->reservationGateLines($principal));
        $this->assertSame(0, ReservationFinancials::make($fresh, $principal)->aCobrarPuerta);
    }

    public function test_reservation_gate_lines_have_no_deposit_line_without_a_deposit(): void
    {
        // No-regresión legacy: un pedido SIN señal (solo una subida de cantidad) muestra el
        // cargo de edición pero NUNCA la línea «Resto de la señal».
        $by = User::factory()->create();
        $order = $this->makeOrder(2400);
        $item = $this->makeItem($order, qty: 3, unit: 1200);
        $order->applyExtraDue($item, 1200, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 3]]]);

        $fresh = $order->fresh(['items.children', 'adjustments']);
        $principal = $fresh->items->firstWhere('id', $item->id);

        $lines = $fresh->reservationGateLines($principal);

        $this->assertCount(1, $lines);
        $this->assertSame('+1 '.$this->entry->tr('name'), $lines[0]['label']);
        $this->assertSame(1200, $lines[0]['amount']);
        $this->assertNotContains(__('admin.orders.item_financial.deposit_remainder_line'), array_column($lines, 'label'));
    }

    public function test_deposit_remainder_pending_by_product_splits_per_product(): void
    {
        // #225 (feedback clienta 2026-06-10): DOS productos con señal → DOS líneas «Resto de la señal
        // de X / de Y». Σ == el resto-señal pendiente del pedido (depositRemainder − resolved).
        $kids = TicketType::create([
            'name' => ['es' => 'Cumpleaños Kids'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'position' => 9, 'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 6000,
        ]);
        $order = $this->makeOrder(27000);
        $a = $this->makeItem($order, qty: 1, unit: 18000);   // «Jump 1h» (señal → resto 150)
        $this->makeDepositRemainder($order, $a, 15000);
        $slotB = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(8)->format('Y-m-d'),
            'start_time' => '12:00:00', 'end_time' => '14:00:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);
        $b = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null, 'ticket_type_id' => $kids->id,
            'slot_id' => $slotB->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 9000,
        ]);
        $this->makeDepositRemainder($order, $b, 6000);       // resto 60

        $fresh = $order->fresh(['items.ticketType', 'items.slot', 'adjustments', 'payments.refunds']);
        $byProduct = $fresh->depositRemainderPendingByProduct();

        $this->assertCount(2, $byProduct);                                                       // una línea por producto
        $this->assertSame(21000, array_sum(array_column($byProduct, 'amount')));                 // 150 + 60
        $s = $fresh->financialSummary();
        $this->assertSame(                                                                       // Σ == resto-señal del pedido
            (int) $s->depositRemainder - (int) $s->depositRemainderResolved,
            array_sum(array_column($byProduct, 'amount')),
        );
        $names = array_column($byProduct, 'name');
        $this->assertContains($this->entry->tr('name'), $names);                                 // «Jump 1h»
        $this->assertContains('Cumpleaños Kids', $names);
    }

    public function test_reservation_gate_lines_handle_an_edited_addon_child_without_n_plus_one(): void
    {
        // #225 F2 (revisión adversarial): un cargo de edición atado a un COMPLEMENTO (no al
        // principal) debe (a) salir en el desglose con su etiqueta y cuadrar con aCobrarPuerta, y
        // (b) NO disparar N+1 por la relación perezosa `parent` cuando los items están eager-loaded
        // (itemFinishedInPractice resuelve el principal desde la colección `items` ya cargada).
        $by = User::factory()->create();
        $order = $this->makeOrder(19000);
        $principal = $this->makeItem($order, qty: 1, unit: 18000);   // pack NO finalizado
        $this->makeDepositRemainder($order, $principal, 15000);       // resto de la señal (150)
        $child = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $principal->id,
            'ticket_type_id' => $this->addon->id, 'slot_id' => null,
            'quantity' => 2, 'seats' => 0, 'unit_price' => 500,
        ]);
        // Subida del complemento 1→2 → +5,00 a cobrar en puerta, atado al CHILD.
        $order->applyExtraDue($child, 500, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);

        // Eager-load EXACTO de la superficie del panel (items-list.blade).
        $fresh = $order->fresh(['items.ticketType', 'items.slot', 'items.children.ticketType', 'adjustments']);
        $principalFresh = $fresh->items->firstWhere('id', $principal->id);

        DB::connection()->enableQueryLog();
        $lines = $fresh->reservationGateLines($principalFresh);
        $queries = DB::connection()->getQueryLog();
        DB::connection()->disableQueryLog();

        // (b) cero consultas: el parent del complemento se resuelve desde la colección cargada.
        $this->assertCount(0, $queries, 'reservationGateLines no debe ejecutar consultas con los items eager-loaded');

        // (a) etiqueta del cargo del complemento + resto-señal, y reconciliación con el agregado.
        $labels = array_column($lines, 'label');
        $this->assertContains('+1 '.$this->addon->tr('name'), $labels);
        $this->assertStringContainsString(__('admin.orders.item_financial.deposit_remainder_line'), implode(' | ', $labels));
        $rf = ReservationFinancials::make($fresh, $principalFresh);
        $this->assertSame(15500, $rf->aCobrarPuerta);                 // 150 resto + 5 subida complemento
        $this->assertSame($rf->aCobrarPuerta, array_sum(array_column($lines, 'amount')));
    }

    private function makeDepositRemainder(Order $order, OrderItem $item, int $cents): void
    {
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => $cents, 'currency' => 'EUR',
            'applied_by' => $order->user_id,
        ]);
    }

    private function makeOrder(int $total): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-RF'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => $total, 'total' => $total,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
    }

    private function makeItem(Order $order, int $qty, int $unit, bool $past = false): OrderItem
    {
        $h = str_pad((string) ($this->counter % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $past ? '2000-01-01' : now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 50, 'online_capacity' => 50,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->entry->id, 'slot_id' => $slot->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);
    }
}
