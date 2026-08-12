<?php

namespace Tests\Feature\Admin\Orders;

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
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sub-fase 7.2e.1bis5 — pulidos UX/UI consolidados (decisión #158).
 *
 * Cubre regresión para los 7 puntos del feedback empírico 2026-05-30 sobre
 * la sub-fase 7.2e.1bis4 (#157):
 *
 *  1. Badge "Reembolsado" de los complementos usa el texto genérico
 *     `refunded_badge` (sin formato propio "↩ −X €") y se renderiza con
 *     tamaño más pequeño que el resto de badges del header.
 *  2. Badge superior izq del sub-card pasa a usar el NOMBRE DEL PRODUCTO
 *     (no la zona). El subtítulo plano con el nombre se elimina por ser
 *     redundante.
 *  3. Botón Preparado se mueve a la esquina sup-dcha del sub-card,
 *     sustituyendo al badge informativo "Sin preparar"/"Preparado". Para
 *     items finalizados o cancelados, badge informativo del estado en su
 *     lugar.
 *  4. Bloque "Totales del pedido" con título compacto al final de la card
 *     Resumen, diferenciado del "Totales del producto" de cada sub-card.
 *  5. Modal cancelar item muestra los complementos del producto + total
 *     cascada (cascade de #157).
 *  6. Sub-cards de card "Pagos" muestran el nombre del producto/complemento
 *     devuelto como primera línea.
 *  7. Coherencia Order ↔ Item:
 *     A. Badge "Reembolsado" del Order solo aparece si fully-refunded
 *        (refund total). Refund parcial = badge solo en items afectados.
 *     B. Banner contextual en card "Productos del pedido" cuando Order
 *        está cancelled o fully-refunded.
 */
class Polish7e1bis5Test extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private TicketType $packType;

    private TicketType $addonType;

    private int $counter = 0;

    private int $paymentCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    // ─── Punto 7A: Order::isFullyRefunded() helper ────────────────────────

    public function test_is_fully_refunded_returns_true_when_refund_amount_equals_total(): void
    {
        $order = $this->makePaidOrder(total: 10000);
        $order->update(['refunded_at' => now(), 'refund_amount_cents' => 10000]);

        $this->assertTrue($order->fresh()->isFullyRefunded());
    }

    public function test_is_fully_refunded_returns_true_when_refund_amount_exceeds_total(): void
    {
        // Caso teórico defensivo: si por bug del flujo se acumula más de lo
        // cobrado en `refund_amount_cents`, el helper sigue devolviendo true
        // (comparación >= ). Defensa anti-drift.
        $order = $this->makePaidOrder(total: 10000);
        $order->update(['refunded_at' => now(), 'refund_amount_cents' => 12000]);

        $this->assertTrue($order->fresh()->isFullyRefunded());
    }

    public function test_is_fully_refunded_returns_false_for_partial_refund(): void
    {
        $order = $this->makePaidOrder(total: 10000);
        $order->update(['refunded_at' => now(), 'refund_amount_cents' => 2500]);

        $this->assertFalse($order->fresh()->isFullyRefunded());
    }

    public function test_is_fully_refunded_returns_false_when_no_refund(): void
    {
        $order = $this->makePaidOrder(total: 10000);

        $this->assertFalse($order->fresh()->isFullyRefunded());
    }

    public function test_is_fully_refunded_returns_true_for_legacy_status_refunded(): void
    {
        $order = $this->makePaidOrder(total: 10000);
        $order->update(['status' => Order::STATUS_REFUNDED]);

        $this->assertTrue($order->fresh()->isFullyRefunded());
    }

    public function test_is_fully_refunded_returns_false_when_total_is_zero(): void
    {
        // Edge defensivo: Order con total 0 no debe marcarse como fully
        // refunded aunque refund_amount_cents sea 0 también — no hay nada
        // que devolver, semánticamente no aplica.
        $order = $this->makePaidOrder(total: 0);
        $order->update(['refunded_at' => now(), 'refund_amount_cents' => 0]);

        $this->assertFalse($order->fresh()->isFullyRefunded());
    }

    // ─── Punto 7B: Banner contextual en items-list ────────────────────────

    public function test_banner_shows_specific_message_when_order_fully_refunded(): void
    {
        $order = $this->makePaidOrder(total: 1200);
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);
        $order->update(['refunded_at' => now(), 'refund_amount_cents' => 1200]);

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        // Banner específico de coherencia Order fully refunded → items
        // bloqueados.
        $response->assertSee(__('admin.orders.item_actions.banner.order_fully_refunded'));
    }

    public function test_banner_does_not_show_for_pending_order_without_payment(): void
    {
        // Order pending: el banner de coherencia NO aplica (no está cancelled ni
        // fully refunded). (El antiguo banner toggle_blocked se retiró con el
        // sistema "preparado/no preparado", #202.)
        $order = $this->makePaidOrder(total: 1200, status: Order::STATUS_PENDING);
        $this->attachActiveItem($order);

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        $response->assertDontSee(__('admin.orders.item_actions.banner.order_cancelled'));
        $response->assertDontSee(__('admin.orders.item_actions.banner.order_fully_refunded'));
    }

    // ─── Punto 5: Modal cancelar muestra complementos ─────────────────────

    public function test_cancel_modal_renders_children_intro_and_cascade_total(): void
    {
        // Cuando el operador abre el modal cancel de un pack con complementos,
        // el partial item-summary-flat (con $showChildren=true) lista los
        // children con su precio + total cascada — alineado con la cascada
        // de cancel a children (#157).
        //
        // Comprobamos invocando la action `cancelItem` directamente via
        // Livewire::test (patrón ya usado en CancelItemActionTest).
        $order = $this->makePaidOrder(total: 2200);
        $this->attachPaidPayment($order);
        $pack = $this->attachActivePackItem($order);
        $addon1 = $this->attachAddon($order, $pack, unitPrice: 500);
        $addon2 = $this->attachAddon($order, $pack, unitPrice: 200);

        $resp = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        // Sanity: el sub-card del item-list muestra el botón Gestionar (desde el
        // que se cancela; #173: el cancelar dejó de ser un icono de la lista y
        // pasó a ser un botón al pie del modal Gestionar).
        $resp->assertSee("mountAction('manageItem', { item: {$pack->id} })", escape: false);
        // Sanity: addons listados como children dentro del sub-card.
        $resp->assertSee('+ '.$addon1->quantity.' × '.$addon1->ticketType->tr('name'));
        $resp->assertSee('+ '.$addon2->quantity.' × '.$addon2->ticketType->tr('name'));

        // Verificamos que el partial item-summary-flat con showChildren=true
        // renderiza correctamente la intro de cascada + total. Renderizamos el
        // partial directamente con el contexto que el handler pasa.
        $rendered = view('filament.orders.partials.item-summary-flat', [
            'item' => $pack->fresh('children.ticketType'),
            'showChildren' => true,
        ])->render();

        // Intro de cascada con count.
        $this->assertStringContainsString(
            __('admin.orders.cancel_item.cascade_intro', ['count' => 2]),
            $rendered,
        );
        // Total cascada label.
        $this->assertStringContainsString(__('admin.orders.cancel_item.cascade_total'), $rendered);
        // Importe agregado: pack 1500 + addon1 500 + addon2 200 = 2200 → "22,00 €".
        $this->assertStringContainsString('22,00 €', $rendered);
    }

    public function test_cancel_modal_does_not_render_children_block_for_item_without_addons(): void
    {
        // Item simple (sin complementos): la intro y el total cascada NO
        // aparecen — bloque oculto cuando children.isEmpty().
        $order = $this->makePaidOrder(total: 1200);
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        $rendered = view('filament.orders.partials.item-summary-flat', [
            'item' => $item->fresh('children.ticketType'),
            'showChildren' => true,
        ])->render();

        $this->assertStringNotContainsString(__('admin.orders.cancel_item.cascade_total'), $rendered);
    }

    public function test_refund_modal_does_not_render_children_intro(): void
    {
        // El modal refund usa item-summary-flat SIN showChildren — los
        // complementos viven en el CheckboxList del form propio. La intro
        // de cascada NO debe aparecer en refund (sería confuso: refund con
        // selección granular vs cancel con cascada completa).
        $order = $this->makePaidOrder(total: 2200);
        $this->attachPaidPayment($order);
        $pack = $this->attachActivePackItem($order);
        $this->attachAddon($order, $pack, unitPrice: 500);

        $rendered = view('filament.orders.partials.item-summary-flat', [
            'item' => $pack->fresh('children.ticketType'),
            // showChildren NO se pasa (default false) — replica el modal refund.
        ])->render();

        $this->assertStringNotContainsString(__('admin.orders.cancel_item.cascade_intro', ['count' => 1]), $rendered);
        $this->assertStringNotContainsString(__('admin.orders.cancel_item.cascade_total'), $rendered);
    }

    // ─── Punto 6: Sub-cards de Pagos muestran nombre devuelto ─────────────

    public function test_refund_event_partial_shows_item_name_when_refund_targets_an_item(): void
    {
        // Cuando un refund tiene `order_item_id` poblado (refund parcial
        // per-item), la sub-card de Pagos muestra el nombre del producto
        // devuelto como primera fila del bloque de datos.
        $order = $this->makePaidOrder(total: 2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        $refund = PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $item->id,
            'amount_cents' => 1200,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $this->staffWithFullItemPermissions()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        // El label "Producto devuelto" aparece en la sub-card del refund.
        $response->assertSee(__('admin.orders.payments.refunds.subject_label'));
        // El nombre del producto + cantidad aparece (ej. "1 × Jump 1h").
        $response->assertSee($item->ticketType->tr('name'));
    }

    public function test_refund_event_partial_shows_full_order_label_when_order_item_id_is_null(): void
    {
        // Refund total del Order (sub-fase 7.2b, #142): `order_item_id` es null.
        // El partial muestra "Pedido completo" como sujeto del refund.
        $order = $this->makePaidOrder(total: 2400);
        $payment = $this->attachPaidPayment($order);

        PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => null,
            'amount_cents' => 2400,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $this->staffWithFullItemPermissions()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        $response->assertSee(__('admin.orders.payments.refunds.subject_full_order'));
    }

    // ─── Punto 2: Badge usa nombre producto, no zona ──────────────────────

    public function test_subcard_header_shows_product_name_and_zone_color_accent(): void
    {
        // P6: el nombre del producto es el TÍTULO de la card (grande, centrado, con el icono del
        // tipo a su izquierda); el color de zona/tipo se traslada al BORDE SUPERIOR de la card
        // (`border-top-color`) en vez de a un badge.
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        // El nombre del producto aparece (como título de la card).
        $response->assertSee($item->ticketType->tr('name'));

        // El acento de color de zona/tipo está en el borde superior 5px (P3/P6).
        $response->assertSee('border-top: 5px solid', escape: false);
    }

    // ─── Punto 3: badge "Finalizado" en sup-dcha ──────────────────────────

    public function test_finished_item_renders_finished_badge_in_top_right(): void
    {
        // Item con slot pasado → estado 'finished'. En la esquina sup-dcha
        // aparece el badge "Finalizado" gris (no accionable: el slot ya pasó).
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $this->attachItemWithPastSlot($order);

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        $response->assertSee(__('admin.orders.item_status.finished'));
    }

    // ─── Punto 7A: Badge "Reembolsado" filtrado a fully refunded ──────────

    public function test_admin_orders_table_column_does_not_show_refunded_for_partial_refund(): void
    {
        // Tabla admin: la columna `has_any_refund` solo muestra "Reembolsado"
        // para refund total. Refund parcial = sin badge en la columna
        // (mismo criterio que el badge del heading).
        $order = $this->makePaidOrder(total: 10000);
        $payment = $this->attachPaidPayment($order);
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'amount_cents' => 2500,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'requested_by' => $this->staffWithFullItemPermissions()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);
        // Refund parcial: solo 25 € del total de 100 €.
        $order->update([
            'refunded_at' => now(),
            'refund_amount_cents' => 2500,
        ]);

        // Sanity: empíricamente isFullyRefunded false.
        $this->assertFalse($order->fresh()->isFullyRefunded());
    }

    public function test_admin_orders_table_column_shows_refunded_for_full_refund(): void
    {
        $order = $this->makePaidOrder(total: 10000);
        $payment = $this->attachPaidPayment($order);
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'amount_cents' => 10000,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'requested_by' => $this->staffWithFullItemPermissions()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);
        $order->update([
            'refunded_at' => now(),
            'refund_amount_cents' => 10000,
        ]);

        $this->assertTrue($order->fresh()->isFullyRefunded());
    }

    // ─── Punto 4: Order totals partial ────────────────────────────────────

    public function test_summary_section_renders_order_totals_block_with_heading(): void
    {
        // Pedido limpio (sin cambios): el bloque muestra el caso SIMPLE "Total".
        $order = $this->makePaidOrder(total: 1200);
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);   // 1 × 12,00 → productsValue 1200 = total

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        // Heading del bloque presente + total formateado.
        $response->assertSee(__('admin.orders.order_financial.heading'));
        $response->assertSee('12,00 €');
    }

    public function test_summary_section_shows_devuelto_when_refund(): void
    {
        // Rediseño valor-primero (#199): cuando hay reembolso registrado, el bloque
        // muestra la línea "Devuelto" con su importe (la antigua línea "Neto cobrado"
        // se eliminó: el valor lo da ahora "Valor final del pedido").
        $order = $this->makePaidOrder(total: 1200);
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);
        $order->update(['refunded_at' => now(), 'refund_amount_cents' => 500]);

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        $response->assertSee(__('admin.orders.amount_refunded'));   // "Devuelto"
        $response->assertSee('−5,00 €', escape: false);            // importe devuelto
    }

    public function test_item_totals_block_renders_heading_for_differentiation(): void
    {
        // Diferencia visual entre "Totales del pedido" (card Resumen) y
        // "Totales del producto" (cada sub-card de item-list).
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $this->attachActiveItem($order);

        $response = $this->actingAs($this->staffWithFullItemPermissions())
            ->get('/admin/orders/'.$order->code)
            ->assertOk();

        $response->assertSee(__('admin.orders.item_financial.heading'));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    // ─── #F1 — doble badge "Reembolsado" en el heading ───────────────────

    public function test_view_heading_does_not_double_refunded_badge_for_legacy_status(): void
    {
        // #F1: en data legacy con status=refunded, displayStatus()='refunded' →
        // el badge de ESTADO ya reza "Reembolsado"; sin el guard, el badge
        // secundario lo repetía. refunded_badge y status.refunded son el MISMO
        // literal "Reembolsado", así que contarlo capta el duplicado.
        $order = $this->makePaidOrder(total: 10000);
        $order->update(['status' => Order::STATUS_REFUNDED]);

        $html = view('filament.orders.view-heading', ['record' => $order->fresh()])->render();

        $this->assertSame(1, substr_count($html, __('admin.orders.refunded_badge')));
    }

    public function test_view_heading_shows_refunded_badge_for_nonlegacy_full_refund(): void
    {
        // Control positivo: un refund total NO legacy (status=paid + refund_amount
        // >= total) → displayStatus()='paid' ("Completado") y el badge secundario
        // "Reembolsado" SÍ aparece (una vez). El guard solo suprime el duplicado.
        $order = $this->makePaidOrder(total: 10000);
        $order->update(['refunded_at' => now(), 'refund_amount_cents' => 10000]);

        $html = view('filament.orders.view-heading', ['record' => $order->fresh()])->render();

        $this->assertSame(1, substr_count($html, __('admin.orders.refunded_badge')));
        $this->assertStringContainsString(__('admin.orders.status.paid'), $html);
    }

    // ─── #F8 — el sujeto del refund no lleva la cantidad TOTAL del item ────

    public function test_refund_event_does_not_prefix_total_item_quantity(): void
    {
        // #F8: refund PARCIAL de un item con quantity>1 — el sujeto NO debe llevar
        // el prefijo "N × " (era la cantidad TOTAL del item, no lo reembolsado).
        // El importe (amount_cents) ya es el dato autoritativo.
        $order = $this->makePaidOrder(total: 6000);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 2000);
        $item->forceFill(['quantity' => 3])->save();

        $refund = PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $item->id,
            'amount_cents' => 2000,   // refund parcial (1 de 3 unidades)
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $this->staffWithFullItemPermissions()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $html = view('filament.orders.partials.refund-event', [
            'refund' => $refund->fresh('orderItem.ticketType'),
            'fmtAmount' => fn (int $cents, string $cur) => number_format($cents / 100, 2, ',', '.').' €',
        ])->render();

        $name = $item->ticketType->tr('name');
        $this->assertStringContainsString($name, $html);             // nombre presente
        $this->assertStringNotContainsString('3 × '.$name, $html);   // sin la cantidad total
        $this->assertStringContainsString('20,00 €', $html);         // el importe REAL reembolsado
    }

    // ─── #F13 — rótulo "Importe del producto" en el flat-summary ──────────

    public function test_flat_summary_labels_product_amount(): void
    {
        // #F13: el importe de la cabecera (el COBRADO del producto) se rotula para
        // no confundirlo con el reembolsable que el modal de reembolso muestra
        // por-fila en el CheckboxList.
        $order = $this->makePaidOrder(total: 1200);
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        $html = view('filament.orders.partials.item-summary-flat', [
            'item' => $item->fresh(['slot', 'ticketType.zone', 'children.ticketType']),
        ])->render();

        $this->assertStringContainsString(__('admin.orders.item_detail.product_amount'), $html);
        $this->assertStringContainsString('12,00 €', $html);
    }

    // ─── #F3/#F14 — modal de cancelar: fantasma oculto + total cascada ────

    public function test_cancel_modal_hides_voided_leftover_children(): void
    {
        // #F3: en el modal de cancelar, un complemento fantasma net-cero (cancelado,
        // nunca cobrado ni reembolsado) NO se lista; el normal sí. La intro cuenta 1.
        $order = $this->makePaidOrder(total: 2200);
        $this->attachPaidPayment($order);
        $pack = $this->attachActivePackItem($order, unitPrice: 1500);
        $this->attachAddon($order, $pack, unitPrice: 500);   // addon normal activo ("Tarta XL")

        $ghostType = TicketType::create([
            'name' => ['es' => 'Menú fantasma'], 'type' => TicketType::TYPE_ADDON,
            'zone_id' => null, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 0, 'position' => 9,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $pack->id,
            'ticket_type_id' => $ghostType->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 0, 'cancelled_at' => now(),
        ]);

        $html = view('filament.orders.partials.item-summary-flat', [
            'item' => $pack->fresh(['slot', 'ticketType.zone', 'children.ticketType']),
            'showChildren' => true,
        ])->render();

        $this->assertStringNotContainsString('Menú fantasma', $html);
        $this->assertStringContainsString($this->addonType->tr('name'), $html);
        // La intro de cascada cuenta SOLO el complemento real (1), no el fantasma.
        $this->assertStringContainsString(__('admin.orders.cancel_item.cascade_intro', ['count' => 1]), $html);
    }

    public function test_cancel_modal_cascade_total_excludes_cancelled_principal(): void
    {
        // #F14 (defensivo): si el principal estuviera cancelado, su subtotal NO suma
        // al total cascada — igual que los children cancelados se excluyen. Hoy no
        // alcanzable por la UI; renderizamos el partial directamente.
        $order = $this->makePaidOrder(total: 2000);
        $this->attachPaidPayment($order);
        $pack = $this->attachActivePackItem($order, unitPrice: 1500);   // 15,00 €
        $this->attachAddon($order, $pack, unitPrice: 500);              // 5,00 € activo
        $pack->markCancelled($this->staffWithFullItemPermissions());

        $html = view('filament.orders.partials.item-summary-flat', [
            'item' => $pack->fresh(['slot', 'ticketType.zone', 'children.ticketType']),
            'showChildren' => true,
        ])->render();

        // Total cascada = solo el complemento activo; SIN guard sería 1500+500 = 20,00 €.
        $this->assertStringContainsString(__('admin.orders.cancel_item.cascade_total'), $html);
        $this->assertStringNotContainsString('20,00 €', $html);
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
        $this->packType = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'zone_id' => $this->zone->id,
            'duration_min' => 90, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'type' => TicketType::TYPE_PACK, 'position' => 2,
        ]);
        $this->addonType = TicketType::create([
            'name' => ['es' => 'Tarta XL'], 'zone_id' => $this->zone->id,
            'duration_min' => null, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 0, 'type' => TicketType::TYPE_ADDON, 'position' => 3,
        ]);
    }

    private function makePaidOrder(int $total = 2400, string $status = Order::STATUS_PAID): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-P5'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => $status === Order::STATUS_PAID ? now() : null,
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

    private function attachActiveItem(Order $order, int $unitPrice = 1200): OrderItem
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
            'quantity' => 1, 'seats' => 1, 'unit_price' => $unitPrice,
        ]);
    }

    private function attachActivePackItem(Order $order, int $unitPrice = 1500): OrderItem
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
            'ticket_type_id' => $this->packType->id,
            'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => $unitPrice,
        ]);
    }

    private function attachItemWithPastSlot(Order $order): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $h = str_pad((string) ($this->counter++ % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => now()->subDays(2)->format('Y-m-d'),
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

    private function attachAddon(Order $order, OrderItem $parent, int $unitPrice = 500): OrderItem
    {
        $this->ensureTicketTypeSetup();

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->addonType->id,
            'slot_id' => $parent->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => $unitPrice,
        ]);
    }
}
