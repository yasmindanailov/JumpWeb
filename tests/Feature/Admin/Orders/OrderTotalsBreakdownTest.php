<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\MovementLabel;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * #171 (feedback clienta) — Sub-líneas del desglose del pedido.
 *
 * Verifica:
 *  - `MovementLabel::edit()` (T3·4: el ÚNICO compositor de la etiqueta de una gestión, para el
 *    panel y para el cliente): una parte por cada cosa que cambió, fiel y sin netear; los
 *    complementos «+N nombre»; y el respaldo que explica sin inventar una cantidad para los ajustes
 *    legacy (context solo con claves o null).
 *  - El partial `order-totals` TRANSCRIBE el LIBRO del pedido (T3·2 de `specs/desglose-libro.md`):
 *    movimientos con signo y fecha, Total, pagos y devoluciones con su estado, Pagado y el saldo
 *    con su clase — comparado contra `OrderBook`, no contra literales.
 */
class OrderTotalsBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private int $counter = 0;

    private int $paymentCounter = 0;

    // ─── MovementLabel::edit() ──────────────────────────────────────────

    public function test_edit_label_quantity_change_says_old_and_new(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['changes' => ['quantity_change' => ['old' => 5, 'new' => 7]]]);

        $this->assertSame(__('tickets.journal.quantity', ['old' => 5, 'new' => 7]), $label);
    }

    /**
     * **Estas etiquetas las lee TAMBIÉN el CLIENTE** (`#154`): viajan como movimientos del libro,
     * así que viven en `tickets.*` con sus TRES idiomas. Antes vivían en `admin.*` (solo ES) y un
     * cliente EN/FR recibía la clave literal en su desglose de dinero — medido por HTTP en `#149`.
     *
     * ⚠️ `Lang::has(..., fallback: false)` a propósito (la lección de `#134` §23.6): una clave que
     * falta NO se manifiesta como clave en crudo — se manifiesta como un cliente francés leyendo
     * castellano en su pantalla de dinero, que parece texto y no falla nada.
     */
    public function test_the_edit_labels_exist_in_every_client_locale(): void
    {
        foreach (['quantity', 'slot_change', 'product_change', 'edit_fallback'] as $key) {
            foreach (['es', 'en', 'fr'] as $locale) {
                $this->assertTrue(
                    Lang::has('tickets.journal.'.$key, $locale, false),
                    "tickets.journal.{$key} falta en «{$locale}» — el cliente leería otro idioma (o la clave en crudo)",
                );
            }
        }

        // Y la COMPOSICIÓN real bajo un locale de cliente no-ES: si la etiqueta volviera al
        // espacio `admin.*`, esto devolvería la clave en crudo y no la frase.
        $item = $this->makeItem();
        app()->setLocale('en');
        try {
            $label = $this->labelFor($item, ['changes' => ['slot_change' => ['old' => 'x', 'new' => '05/09/2026 10:00']]]);
            $this->assertSame(Lang::get('tickets.journal.slot_change', ['when' => '05/09/2026 10:00'], 'en'), $label);
            $this->assertStringNotContainsString('tickets.', $label, 'la clave salió en crudo');
        } finally {
            app()->setLocale('es');
        }
    }

    public function test_edit_label_product_change_shows_target_product(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['changes' => ['product_change' => ['old' => 'Pack Jump', 'new' => 'Pack Kids']]]);

        $this->assertSame(__('tickets.journal.product_change', ['name' => 'Pack Kids']), $label);
    }

    /** Fiel y sin netear: una gestión que cambió producto Y cantidad lo dice entero. */
    public function test_edit_label_says_every_change_of_the_same_edit(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['changes' => [
            'product_change' => ['old' => 'A', 'new' => 'Pack Kids'],
            'quantity_change' => ['old' => 1, 'new' => 2],
        ]]);

        $this->assertSame(
            __('tickets.journal.product_change', ['name' => 'Pack Kids']).' · '.__('tickets.journal.quantity', ['old' => 1, 'new' => 2]),
            $label,
        );
    }

    public function test_edit_label_addon_added_shows_qty_and_name(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['addon_change' => [
            'added' => [['name' => 'Calcetines', 'qty' => 3]],
            'removed' => [], 'updated' => [],
        ]]);

        $this->assertSame('+3 Calcetines', $label);
    }

    public function test_edit_label_addon_updated_shows_positive_delta(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['addon_change' => [
            'added' => [], 'removed' => [],
            'updated' => [['name' => 'Gorro', 'old' => 1, 'new' => 4]],
        ]]);

        $this->assertSame('+3 Gorro', $label);
    }

    public function test_edit_label_multiple_addons_joined(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['addon_change' => [
            'added' => [['name' => 'Calcetines', 'qty' => 2]],
            'removed' => [],
            'updated' => [['name' => 'Gorro', 'old' => 0, 'new' => 1]],
        ]]);

        $this->assertSame('+2 Calcetines · +1 Gorro', $label);
    }

    /**
     * ⚠️⚠️ **El respaldo EXPLICA, no solo nombra** (2026-08-24, `DECISIONES #131`).
     *
     * Devolvía el nombre pelado del producto, y bajo «Pendiente de pagar en el parque» eso se lee
     * como «te cobramos 96,00 € de Cumpleaños Jump» sin decir de dónde sale ese importe — mientras su
     * línea hermana, «Resto de la señal de X», sí se explica sola.
     *
     * ▶ **Y no es una rama de datos sucios**: medido sobre los OCHO `extra_due` de la BD de
     * desarrollo, **los ocho** caían aquí, incluidos los cinco escritos por el flujo REAL del panel
     * —que guarda `context = {"changes": []}`, con lo que ninguna rama de arriba puede decir nada—.
     * Es decir: **es la etiqueta que sale en producción**, no la excepción.
     */
    public function test_edit_label_legacy_keys_only_explains_the_change(): void
    {
        $item = $this->makeItem();

        // Formato legacy: context guardaba solo las CLAVES (lista de strings).
        $label = $this->labelFor($item, ['changes' => ['quantity_change']]);

        $this->assertSame(__('tickets.journal.edit_fallback', ['product' => $this->jumpType->tr('name')]), $label);
        $this->assertNotSame($this->jumpType->tr('name'), $label, 'la etiqueta vuelve a ser el nombre pelado');
    }

    public function test_edit_label_null_context_explains_the_change(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, null);

        $this->assertSame(__('tickets.journal.edit_fallback', ['product' => $this->jumpType->tr('name')]), $label);
    }

    /** ⚠️ Y el respaldo NO se come las ramas que sí saben describir el cambio. */
    public function test_the_explanatory_fallback_does_not_swallow_the_precise_labels(): void
    {
        $item = $this->makeItem();

        $this->assertSame(
            __('tickets.journal.quantity', ['old' => 1, 'new' => 3]),
            $this->labelFor($item, ['changes' => ['quantity_change' => ['old' => 1, 'new' => 3]]]),
        );
        $this->assertSame(
            __('tickets.journal.product_change', ['name' => 'Pack Kids']),
            $this->labelFor($item, ['changes' => ['product_change' => ['new' => 'Pack Kids']]])
        );
    }

    // ─── Render del partial: EL LIBRO del pedido (T3·2 de `specs/desglose-libro.md` §6.3.2) ──────

    /**
     * El bloque «Totales del pedido» TRANSCRIBE el libro: cada línea de valor con su signo y su fecha,
     * el Total, los cobros, lo Pagado y el saldo con su clase. Se compara contra
     * `OrderBook::forOrder` —etiqueta a etiqueta— y no contra literales del panel: el blade no
     * compone nada (`LedgerSingleSourceTest`), y lo que imprime es lo que el cliente lee en «Mis
     * pedidos» (guarda M, `BookSurfacesParityTest`).
     */
    public function test_the_block_prints_the_book_of_the_order_line_by_line(): void
    {
        $order = $this->lawfulPaidOrder(); // 2 × 10,00 facturados y cobrados por web
        $a = $order->items->first();
        $a->forceFill(['quantity' => 3, 'seats' => 3])->save();
        $order->recordEdit($a->fresh(), 2000, User::factory()->create(), 'item_edit',
            ['changes' => ['quantity_change' => ['old' => 1, 'new' => 3]]]);

        $order = $this->reload($order);
        $book = OrderBook::forOrder($order);
        $html = $this->renderTotals($order);

        $this->assertTrue($book->isConsistent, 'guarda del escenario: el fixture tiene que cuadrar');
        $this->assertCount(2, $book->movements, 'el nacimiento y la subida');
        foreach ($book->movements as $m) {
            $this->assertStringContainsString($m->label, $html);
            $this->assertStringContainsString($m->occurredLabel, $html);
        }
        $this->assertSame(2, substr_count($html, 'data-book-movement='));
        $this->assertStringContainsString('+20,00', $html);                 // Reserva
        $this->assertStringContainsString('40,00', $html);                  // Total = 20 + 20
        $this->assertStringContainsString(__('tickets.journal.paid_online'), $html);
        $this->assertStringContainsString('data-book-balance="pay_at_park"', $html);
        $this->assertStringContainsString(__('admin.orders.book.balance_pay_at_park'), $html);
        // Ya no hay CANALES: ni «Falta por cobrar» ni «Valor final del pedido».
        $this->assertStringNotContainsString('Falta por cobrar', $html);
        $this->assertStringNotContainsString('Valor final', $html);
    }

    /**
     * **La causa del cargo llega al libro cuando fue un cambio de FECHA** (`DECISIONES #145`): la
     * etiqueta de la línea la compone el dominio desde el contexto del ajuste, y el panel la imprime
     * tal cual — no cae a un texto de respaldo mudo.
     */
    public function test_a_date_change_charge_says_it_was_a_date_change(): void
    {
        $order = $this->lawfulPaidOrder();
        $item = $order->items->first();
        $item->forceFill(['unit_price' => 3400])->save(); // la fecha nueva es más cara: +24,00
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_EDIT, 'amount_cents' => 2400, 'currency' => 'EUR',
            'reason' => 'item_edit',
            'context' => ['changes' => ['slot_change' => ['old' => 'sáb 5 sep 18:00', 'new' => 'mié 2 sep 19:00']]],
            'applied_by' => User::factory()->create()->id,
        ]);

        $order = $this->reload($order);
        $book = OrderBook::forOrder($order);
        $html = $this->renderTotals($order);

        $this->assertTrue($book->isConsistent);
        $this->assertStringContainsString('mié 2 sep 19:00', $book->movements[1]->label, 'el libro dice a cuándo se movió');
        $this->assertStringContainsString($book->movements[1]->label, $html);
        $this->assertStringContainsString('+24,00', $html);
    }

    /**
     * «Al reservar se facturaron X» ya no es una NOTA aparte (`DECISIONES #145`): es la PRIMERA línea
     * del libro, y lo que cambió después son las siguientes, con su signo. Con dos reservas en el
     * pedido, la cancelación lleva delante el nombre de la suya (spec §4.3).
     */
    public function test_what_was_invoiced_is_the_birth_line_and_a_cancellation_is_a_negative_line(): void
    {
        $order = $this->lawfulPaidOrder();
        $order->items->first()->markCancelled(User::factory()->create());

        $order = $this->reload($order);
        $book = OrderBook::forOrder($order);
        $html = $this->renderTotals($order);

        $this->assertStringContainsString(__('tickets.journal.booking'), $html);
        $this->assertStringContainsString('+20,00', $html);
        $this->assertStringContainsString('−10,00', $html);
        $this->assertSame(Balance::KIND_REFUND_AT_PARK, $book->balance->kind, 'la otra línea sigue viva: se devuelve EN el parque');
        $this->assertStringContainsString('data-book-balance="refund_at_park"', $html);
        $this->assertStringContainsString(__('admin.orders.book.balance_refund_at_park'), $html);
        $this->assertStringNotContainsString('Pendiente de devolución', $html, 'hay visita por delante: no es una devolución pendiente de canal');
    }

    /** Un cargo sobre una visita ya HECHA consta liquidado en el parque, y el saldo queda saldado. */
    public function test_a_charge_on_a_finished_visit_is_settled_at_the_park(): void
    {
        $order = $this->lawfulPaidOrder();
        $item = $order->items->first();
        $past = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => now()->subDays(2)->format('Y-m-d'),
            'start_time' => '10:00:00', 'end_time' => '10:59:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);
        $item->update(['slot_id' => $past->id, 'quantity' => 3, 'seats' => 3]);
        $order->recordEdit($item->fresh(), 2000, User::factory()->create(), 'item_edit',
            ['changes' => ['quantity_change' => ['old' => 1, 'new' => 3]]]);

        $order = $this->reload($order);
        $book = OrderBook::forOrder($order);
        $html = $this->renderTotals($order);

        $this->assertTrue($book->isConsistent);
        $this->assertStringContainsString(__('tickets.journal.gate'), $html); // «Liquidado en el parque»
        $this->assertStringContainsString('data-book-settlement="gate"', $html);
        $this->assertStringContainsString('data-book-balance="settled"', $html);
        $this->assertStringContainsString(__('admin.orders.book.balance_settled'), $html);
        $this->assertStringNotContainsString(__('admin.orders.book.balance_pay_at_park'), $html);
    }

    /**
     * Las devoluciones se listan con su ESTADO y solo las efectivas cuentan como pagado (spec §4.4):
     * una en curso sigue en la lista, no resta, y el saldo la ignora hasta que el dinero vuelve.
     */
    public function test_refunds_are_listed_with_their_status_and_only_the_effective_ones_count(): void
    {
        $order = $this->lawfulPaidOrder();
        $item = $order->items->first();
        $payment = $order->payments->first();
        $this->attachSucceededRefund($payment, 400, $item->id);
        $order->update(['refund_amount_cents' => 400, 'refunded_at' => now()]); // I4: la columna == Σ con éxito
        PaymentRefund::create([
            'payment_id' => $payment->id, 'order_item_id' => $item->id,
            'amount_cents' => 300, 'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_PENDING, 'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'requested_by' => User::factory()->create()->id, 'requested_at' => now(),
        ]);

        $order = $this->reload($order);
        $book = OrderBook::forOrder($order);
        $html = $this->renderTotals($order);

        $this->assertTrue($book->isConsistent);
        $this->assertSame(2, substr_count($html, 'data-book-settlement="refund"'));
        $this->assertStringContainsString('−4,00', $html);
        $this->assertStringContainsString('−3,00', $html);
        $this->assertStringContainsString(__('tickets.journal.refund_pending'), $html, 'la etiqueta de la devolución en curso la compone el libro');
        $this->assertSame(1600, $book->paidCents, 'Pagado = 20,00 − 4,00: la devolución en curso no cuenta');
        $this->assertStringContainsString('16,00', $html);
        // Y devolver dinero SIN bajar el valor deja saldo a pagar: el libro lo dice, no lo esconde.
        $this->assertStringContainsString('data-book-balance="pay_at_park"', $html);
    }

    /**
     * **Guarda O**: el atajo «Ver historial» aparece solo cuando hay algo que explicar — lo decide el
     * libro (`hasHistoryToExplain()`, D-T3·16). Mutación: invertirlo.
     */
    public function test_the_history_shortcut_appears_only_when_there_is_something_to_explain(): void
    {
        $order = $this->lawfulPaidOrder();
        $this->assertStringNotContainsString("mountAction('viewOrderHistory')", $this->renderTotals($order), 'el caso simple no gana ruido');

        $item = $order->items->first();
        $item->forceFill(['quantity' => 2, 'seats' => 2])->save();
        $order->recordEdit($item->fresh(), 1000, User::factory()->create(), 'item_edit',
            ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);

        $this->assertStringContainsString("mountAction('viewOrderHistory')", $this->renderTotals($this->reload($order)));
    }

    /**
     * Un libro que NO cuadra se le enseña ENTERO al operador con el aviso rojo (D-T3·4, la asimetría
     * de `#132`): es quien puede arreglarlo. El cliente, en cambio, no ve las líneas ni el saldo.
     */
    public function test_a_book_that_does_not_close_is_shown_to_the_operator_with_the_alert(): void
    {
        $order = $this->makePaidOrder(); // «pagado» sin cobro registrado: la identidad de caja no cierra
        $this->attachActiveItem($order);
        $this->attachActiveItem($order);

        $html = $this->renderTotals($this->reload($order));

        $this->assertStringContainsString(__('admin.orders.order_financial.no_cuadra_title'), $html);
        $this->assertStringContainsString('data-book-balance="under_review"', $html);
        $this->assertStringContainsString(__('admin.orders.book.balance_under_review'), $html);
        $this->assertStringContainsString('data-book-movement="booking"', $html, 'el libro se enseña entero');
    }

    /**
     * El ejemplo REAL de la clienta, contado como LIBRO: pagó 288,00 € por 16 invitados; bajó a 8
     * (−144,00) y luego subió a 12 (+72,00). Vale 216,00; pagó 288,00; se le devuelven 72,00 en el
     * parque. Antes eran cuatro canales que había que relacionar («valor final 216 = 144 online + 72
     * en el parque, y 144 pendientes de devolución»); ahora es una resta.
     */
    public function test_the_user_example_reads_as_a_book(): void
    {
        $order = $this->makePaidOrder();
        $order->forceFill(['subtotal' => 28800, 'total' => 28800])->save();
        $item = $this->attachActiveItem($order);
        $item->forceFill(['unit_price' => 1800, 'quantity' => 16, 'seats' => 16])->save();
        $this->attachPaidPayment($order);                                   // 288,00 por web
        $by = User::factory()->create();
        $item->forceFill(['quantity' => 8, 'seats' => 8])->save();
        $order->recordEdit($item->fresh(), -14400, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 16, 'new' => 8]]]);
        $item->forceFill(['quantity' => 12, 'seats' => 12])->save();
        $order->recordEdit($item->fresh(), 7200, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 8, 'new' => 12]]]);

        $order = $this->reload($order);
        $book = OrderBook::forOrder($order);
        $html = $this->renderTotals($order);

        $this->assertTrue($book->isConsistent);
        $this->assertSame(3, substr_count($html, 'data-book-movement='));
        $this->assertStringContainsString('+288,00', $html);
        $this->assertStringContainsString('−144,00', $html);
        $this->assertStringContainsString('+72,00', $html);
        $this->assertStringContainsString('216,00', $html);   // Total
        $this->assertStringContainsString('288,00', $html);   // Pagado
        $this->assertStringContainsString('data-book-balance="refund_at_park"', $html);
        $this->assertStringContainsString('−72,00', $html);   // A devolver en el parque
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function renderTotals(Order $order): string
    {
        return view('filament.orders.partials.order-totals', ['record' => $order])->render();
    }

    /**
     * Un pedido que CUADRA: 2 × 10,00 facturados, dos líneas de 10,00 y un cobro de 20,00 por web.
     * ⚠️ `makePaidOrder()` solo factura 20,00 y NO cobra: con un ítem, o sin `attachPaidPayment()`, el
     * pedido nace DESCUADRADO y el libro dice «en revisión» con razón — un rojo del fixture que se
     * leería como un defecto del código (la trampa que este proyecto ya pagó cuatro veces).
     */
    private function lawfulPaidOrder(): Order
    {
        $order = $this->makePaidOrder();
        $this->attachActiveItem($order);
        $this->attachActiveItem($order);
        $this->attachPaidPayment($order);

        return $this->reload($order);
    }

    private function reload(Order $order): Order
    {
        return $order->fresh(['payments.refunds', 'adjustments', 'items.slot', 'items.ticketType', 'items.children.ticketType']);
    }

    private function labelFor(OrderItem $item, ?array $context): string
    {
        $adj = new OrderAdjustment;
        $adj->context = $context;

        return MovementLabel::edit($adj, $item->loadMissing('ticketType'), 'EUR');
    }

    private function makeItem(): OrderItem
    {
        $order = $this->makePaidOrder();

        return $this->attachActiveItem($order);
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
            'name' => ['es' => 'Pulsera Jump'], 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function makePaidOrder(): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-BD'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 2000, 'tax' => 0, 'total' => 2000, 'currency' => 'EUR',
            'paid_at' => now(),
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
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
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

    private function attachSucceededRefund(Payment $payment, int $amount, ?int $orderItemId): PaymentRefund
    {
        return PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $orderItemId,
            'amount_cents' => $amount,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => User::factory()->create()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);
    }
}
