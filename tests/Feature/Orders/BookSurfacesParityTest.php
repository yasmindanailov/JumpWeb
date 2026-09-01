<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\ReservationSlip;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\Money;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\App;
use Tests\Feature\Api\ApiTestCase;

/**
 * **Guarda M de la T3·2** (`specs/desglose-libro.md` §6.3.2): las superficies del PARQUE —el bloque
 * «Totales del pedido», la tarjeta de cada reserva, la hoja con precios y la fila de la puerta—
 * imprimen EXACTAMENTE la lista (etiqueta, importe) que el cliente recibe en `GET /me/orders` y en
 * `GET /orders/{code}`. No se compara contra literales: se compara la respuesta de la API con lo
 * que cada superficie renderiza, sobre un pedido que tiene de todo (dos reservas, señal, subida,
 * cancelación, devolución).
 *
 * Es la guarda de D1 («las nueve superficies enseñan el MISMO libro») y del porqué de que el panel
 * no reescriba ninguna etiqueta: la última vez que cada superficie compuso lo suyo, 18 de 23
 * gestiones del panel dejaron la columna del cliente ilegible (`DECISIONES #127`).
 *
 * Mutaciones: el pintor imprime `kind` en vez de `label` · la hoja vuelve a `grandTotalCents()`.
 */
class BookSurfacesParityTest extends ApiTestCase
{
    private User $customer;

    private User $staff;

    private Order $order;

    private OrderItem $entry;

    private OrderItem $pack;

    private string $entryDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        App::setLocale('es');
        $this->buildTheRichOrder();
    }

    /** Guarda del escenario: si el pedido no tuviera de todo, o no cuadrara, la paridad sería vacía. */
    public function test_the_fixture_has_everything_and_closes(): void
    {
        $book = OrderBook::forOrder($this->order);

        $this->assertTrue($book->isConsistent);
        $this->assertEqualsCanonicalizing(['booking', 'edit', 'cancel'], array_map(fn ($m) => $m->kind, $book->movements));
        $this->assertEqualsCanonicalizing(['payment', 'refund'], array_map(fn ($s) => $s->kind, $book->settlements));
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
        $this->assertSame(1000, $book->balance->cents, 'vale 30,00 (la entrada subida), pagó 20,00 (30,00 − 10,00 devueltos)');
        $this->assertTrue($book->hasHistoryToExplain());
        // Guarda P (D-T3·15): «a pagar» no es «se le debe» — el sugerido al reembolsar es 0 aquí.
        $this->assertSame(0, $book->owedToCustomerCents());
    }

    public function test_the_order_block_prints_exactly_what_the_customer_receives(): void
    {
        $api = $this->actingAs($this->customer)->getJson(self::ROOT.'/me/orders')->assertOk()->json('data.0.ledger');

        $html = view('filament.orders.partials.order-totals', ['record' => $this->order])->render();

        $this->assertParityWithTheApi($api, $html);
    }

    public function test_each_reservation_card_prints_its_own_book_as_the_api_publishes_it(): void
    {
        $items = $this->actingAs($this->customer)->getJson(self::ROOT.'/orders/'.$this->order->code)->assertOk()->json('items');
        $this->assertCount(2, $items, 'la entrada viva y el pack cancelado');

        foreach ($items as $item) {
            $principal = $this->order->items->firstWhere('id', $item['id']);
            $html = view('filament.orders.partials.reservation-financials', [
                'book' => OrderBook::forReservation($this->order, $principal),
            ])->render();

            $this->assertParityWithTheApi($item['ledger'], $html);
        }
    }

    /** La ficha entera lleva el bloque del pedido y una tarjeta por reserva, del MISMO pintor. */
    public function test_the_order_page_has_the_block_and_one_card_per_reservation(): void
    {
        $page = $this->actingAs($this->staff, 'web')->get('/admin/orders/'.$this->order->code)->assertOk()->getContent();

        $this->assertSame(3, substr_count($page, 'data-book>'), 'el bloque del pedido y una tarjeta por reserva');
        $this->assertStringContainsString(__('admin.orders.order_financial.heading'), $page);
    }

    public function test_the_priced_slip_prints_the_reservation_book_as_the_api_publishes_it(): void
    {
        $items = $this->actingAs($this->customer)->getJson(self::ROOT.'/orders/'.$this->order->code)->assertOk()->json('items');
        $entry = collect($items)->firstWhere('id', $this->entry->id);

        $html = view('pdf.reservation-slip', [
            'slip' => ReservationSlip::make($this->order, $this->order->items->firstWhere('id', $this->entry->id)),
            'showPrices' => true,
        ])->render();

        $this->assertParityWithTheApi($entry['ledger'], $html);
    }

    public function test_the_gate_row_carries_the_balance_the_api_publishes(): void
    {
        $items = $this->actingAs($this->customer)->getJson(self::ROOT.'/orders/'.$this->order->code)->assertOk()->json('items');
        $entry = collect($items)->firstWhere('id', $this->entry->id);

        $rows = app(GateReservations::class)->forHolder($this->customer->id, $this->entryDate, $this->entryDate);

        $this->assertCount(1, $rows);
        $this->assertSame($entry['ledger']['balance']['kind'], $rows[0]->balanceKind);
        $this->assertSame($entry['ledger']['balance']['cents'], $rows[0]->balanceCents);
        $this->assertSame($entry['ledger']['paid_cents'], $rows[0]->paidCents);
    }

    // ─── La comparación ───────────────────────────────────────────────────────

    /**
     * Cada línea que publica la API está en el HTML con su etiqueta, su fecha y su importe con signo;
     * ni una de más ni de menos; y el Total, lo Pagado y la clase del saldo coinciden.
     *
     * @param  array<string, mixed>  $ledger
     */
    private function assertParityWithTheApi(array $ledger, string $html): void
    {
        $signed = fn (int $cents): string => ($cents < 0 ? '−' : '+').Money::format(abs($cents));

        $this->assertNotEmpty($ledger['movements']);
        foreach ($ledger['movements'] as $m) {
            $this->assertStringContainsString($m['label'], $html, "falta la línea de valor «{$m['label']}»");
            $this->assertStringContainsString($m['occurred_label'], $html, "falta la fecha de «{$m['label']}»");
            $this->assertStringContainsString($signed((int) $m['amount_cents']), $html, "falta el importe de «{$m['label']}»");
        }
        $this->assertSame(count($ledger['movements']), substr_count($html, 'data-book-movement='), 'ni una línea de valor de más ni de menos');

        foreach ($ledger['settlements'] as $s) {
            $this->assertStringContainsString($s['label'], $html, "falta la liquidación «{$s['label']}»");
            $this->assertStringContainsString($signed((int) $s['amount_cents']), $html, "falta el importe de «{$s['label']}»");
        }
        $this->assertSame(count($ledger['settlements']), substr_count($html, 'data-book-settlement='), 'ni una liquidación de más ni de menos');

        $this->assertStringContainsString(Money::format((int) $ledger['total_cents']), $html, 'el Total');
        $this->assertStringContainsString(Money::format((int) $ledger['paid_cents']), $html, 'lo Pagado');
        $this->assertStringContainsString('data-book-balance="'.$ledger['balance']['kind'].'"', $html, 'la clase del saldo');
        if ((int) $ledger['balance']['cents'] !== 0) {
            $this->assertStringContainsString(Money::format(abs((int) $ledger['balance']['cents'])), $html, 'el importe del saldo');
        }
    }

    // ─── El pedido que tiene de todo ──────────────────────────────────────────

    /**
     * Dos reservas: una entrada (2 × 10,00) que sube a 3 desde el panel, y un pack de 40,00 con señal
     * (10,00 online, 30,00 en el parque) que se cancela y cuya señal se devuelve a la tarjeta. Cobro
     * de 30,00 por web (lo que las líneas aportaron online al nacer). Cuadra: I1 (60,00 facturados =
     * 20,00 + 40,00 al nacer), I2 (30,00 cobrados = 20,00 + 10,00 online) e I4 (10,00 devueltos).
     */
    private function buildTheRichOrder(): void
    {
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $entryType = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $packType = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 120, 'min_qty' => 1, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
            'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 1000,
        ]);
        $this->entryDate = now()->addDays(5)->toDateString();
        $slotA = Slot::create(['zone_id' => $zone->id, 'date' => $this->entryDate, 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'online_capacity' => 50]);
        $slotB = Slot::create(['zone_id' => $zone->id, 'date' => now()->addDays(6)->toDateString(), 'start_time' => '16:00:00', 'end_time' => '18:00:00', 'capacity' => 50, 'online_capacity' => 50]);

        $this->customer = User::factory()->create();
        $this->staff = User::factory()->create();
        $this->staff->roles()->sync([Role::where('name', 'admin')->value('id')]);

        $this->order = Order::create([
            'user_id' => $this->customer->id, 'code' => 'R-PARITY1', 'status' => Order::STATUS_PAID,
            'subtotal' => 6000, 'tax' => 0, 'total' => 6000, 'currency' => 'EUR', 'paid_at' => now()->subDay(),
        ]);
        $this->entry = $this->order->items()->create(['ticket_type_id' => $entryType->id, 'slot_id' => $slotA->id, 'quantity' => 2, 'unit_price' => 1000, 'seats' => 2]);
        $this->pack = $this->order->items()->create(['ticket_type_id' => $packType->id, 'slot_id' => $slotB->id, 'quantity' => 1, 'unit_price' => 4000, 'seats' => 1]);
        // La señal del pack: 10,00 online y 30,00 en el parque (el reparto que escribe `OrderCreator`).
        OrderAdjustment::create([
            'order_id' => $this->order->id, 'order_item_id' => $this->pack->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT, 'amount_cents' => 3000, 'currency' => 'EUR', 'applied_by' => $this->staff->id,
        ]);
        $payment = Payment::create([
            'payable_type' => $this->order->getMorphClass(), 'payable_id' => $this->order->id,
            'amount' => 3000, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now()->subDay(), 'gateway_order' => '0000900001',
        ]);
        // La entrada sube de 2 a 3 desde el panel.
        $this->entry->forceFill(['quantity' => 3, 'seats' => 3])->save();
        $this->order->recordEdit($this->entry->fresh(), 1000, $this->staff, 'item_edit', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 3]]]);
        // El pack se cancela y su señal vuelve a la tarjeta.
        $this->pack->markCancelled($this->staff);
        PaymentRefund::create([
            'payment_id' => $payment->id, 'order_item_id' => $this->pack->id,
            'amount_cents' => 1000, 'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED, 'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order, 'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $this->staff->id, 'requested_at' => now(), 'processed_at' => now(),
        ]);
        $this->order->forceFill(['refund_amount_cents' => 1000, 'refunded_at' => now()])->save();

        $this->order = $this->order->fresh(['payments.refunds', 'adjustments', 'items.slot', 'items.ticketType']);
    }
}
