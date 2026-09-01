<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\ReservationSlip;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #225 iter. 3 — superficies del desglose de la señal.
 *
 * Verifica las piezas de coherencia más delicadas: el CANARIO anti doble-fuente (el split
 * previsto en el sidecart == lo que `OrderCreator`/`onlineDueCents` cobran para la MISMA
 * cesta), el email de confirmación (señal + pendiente, no «total pagado»), y la caja del PDF.
 *
 * ⚠️ **Los cuatro casos de UI se fueron con `Tickets\Purchase`** (4.7·2b·3, ejecutando la
 * clasificación que `DECISIONES #91` dejó medida el 2026-08-15: el fichero **no era homogéneo** y
 * había que operar DENTRO). Eran lo que ANUNCIA el catálogo, lo que pinta el pie del paso 3, el
 * detalle del sidecart y el paso 6: su sujeto era la superficie, no la regla del dinero.
 *
 * Su cobertura equivalente se comprobó una a una antes de borrarlos, y toda está en la superficie
 * viva:
 *
 *  · el desglose del pie → `SidebarCartParityTest` desde `#80` (`nowLabel`/`now`/`park` contra el
 *    diccionario y `number_format`);
 *  · el estado del ⓘ → `SidebarDomContractTest`;
 *  · el anuncio del catálogo → la clave `deposit_catalog`, en la lista de `SidebarTextParityTest`;
 *  · la nota por producto del paso 6 → la clave `deposit_card_note`, también en
 *    `SidebarTextParityTest` (con sus dos interpolaciones), pintada por `SummaryLine.vue` y
 *    `CartStep.vue`, con su caso de interpolación en `i18n.test.js` y la superficie de cuenta en
 *    `Account\OrdersPageTest`.
 *
 * ⚠️ **Lo que NO se movió de aquí es la regla**: `PAY-10` la vigila `DepositRefundCoherenceTest`, y
 * el canario anti doble-fuente ya se había retirado por redundante en `#91`.
 *
 * El resto de casos no tocaban el componente y se quedan tal cual: el email, el PDF, el desglose por
 * producto y los dos de sobrecobro.
 * El resto de superficies leen `OrderFinancialSummary`/`ReservationFinancials` (cubiertas en
 * sus tests) y se corrigen solas.
 */
class DepositSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private OrderCreator $creator;

    private User $user;

    private Zone $zone;

    private TicketType $dep;

    private string $date;

    private int $normalRateId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creator = app(OrderCreator::class);
        $this->user = User::factory()->create();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->normalRateId = (int) RateType::where('key', 'normal')->value('id');
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 50, 'online_capacity' => 50,
            ]);
        }

        // Entrada con señal fija 30 € sobre un valor de 180 € (ejercita el path data-driven).
        $this->dep = TicketType::create([
            'name' => ['es' => 'Con señal'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'position' => 1, 'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 3000,
        ]);
        $this->dep->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 18000]);
    }

    private function attachAddon(string $name, int $priceCents): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 90,
        ]);
        $addon->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $priceCents]);
        DB::table('product_addons')->insert([
            'product_id' => $this->dep->id, 'addon_id' => $addon->id, 'position' => 0,
            'is_included' => false, 'included_quantity' => 1, 'is_mandatory' => false,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true, 'choice_group' => null,
        ]);

        return $addon;
    }

    /*
     * ⚠️ **Aquí vivían los DOS casos del canario anti doble-fuente**, retirados en 4.7·2b·2·C
     * (`DECISIONES #91`) por redundantes, y medido antes de tocarlos.
     *
     * Comparaban `Purchase::cartDepositCents()` con `Order::onlineDueCents()` para la misma cesta.
     * Pero ese método es **literalmente** `$this->quote()->onlineAmountCents`: un intermediario de
     * `CartPricing`, como el `pausedTitle()` de `#81`. El salto por Livewire no añadía nada.
     *
     * Mutando `onlineDueCents()` para que cobre el total en vez de la señal caen **catorce** casos, y
     * entre ellos los que hacen exactamente esta pregunta y SOBREVIVEN:
     *
     *   · `Sales\CartPricerTest::test_the_quote_of_a_mixed_cart_matches_what_the_order_will_charge`
     *     — el canario, sin componente de por medio;
     *   · `Sales\DepositChargeTest::test_addons_of_a_deposit_product_are_fully_charged_at_the_park`
     *     — la mitad de los complementos (Opción A), que era el segundo caso;
     *   · `Support\DepositFoundationTest`, para el cimiento.
     */

    public function test_confirmation_email_shows_deposit_and_pending_lines(): void
    {
        $order = $this->creator->createPendingOrder($this->user, [
            ['ticket_type_id' => $this->dep->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1],
        ]);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        $order->load(['items', 'adjustments']);

        App::setLocale('es');
        $mail = (new OrderConfirmation($order))->toMail($this->user);
        $body = implode(' | ', array_map(fn ($l) => (string) $l, $mail->introLines));

        $this->assertStringContainsString('Señal pagada online', $body);
        $this->assertStringContainsString('30,00', $body);                  // la señal
        $this->assertStringContainsString('Pendiente de pago en el parque', $body);
        $this->assertStringContainsString('150,00', $body);                 // el resto
        // No anuncia «Total pagado: 180,00» (sería falso: solo se cobró la señal).
        $this->assertStringNotContainsString('Total pagado: 180,00', $body);
    }

    public function test_deposit_remainder_breakdown_by_product_includes_addon_children(): void
    {
        // Auditoría Fase 1 (L4): el desglose por-producto del «resto de la señal» debe INCLUIR el
        // deposit_remainder de los COMPLEMENTOS (Opción A #225: van 100 % a puerta, ATADOS al child).
        // Antes solo sumaba el principal → el desglose no cuadraba con el titular `pendingAtGate()`.
        $socks = $this->attachAddon('Calcetines', 2000);
        $order = $this->creator->createPendingOrder($this->user, [[
            'ticket_type_id' => $this->dep->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1,
            'addons' => [['ticket_type_id' => $socks->id, 'qty' => 1]],
        ]]);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        $order->load(['items.ticketType', 'adjustments', 'payments.refunds', 'items.slot']);

        $byProduct = $order->depositRemainderPendingByProduct();
        $sum = array_sum(array_column($byProduct, 'amount'));

        // 150€ (resto del pack) + 20€ (complemento, antes OMITIDO) = 170€.
        $this->assertSame(17000, $sum, 'el desglose incluye el resto-señal del complemento');
        // Reconciliación: Σ del desglose == el resto-señal agregado del pedido (titular).
        $this->assertSame($order->financialSummary()->depositRemainder, $sum);
    }

    public function test_confirmation_email_does_not_invent_deposit_on_legacy_cancelled_line(): void
    {
        // M1 no-regresión: un pedido SIN señal con una línea cancelada NO debe mostrar «Señal
        // pagada online» (el predicado es `depositRemainder > 0`, no `online < total`).
        $full = TicketType::create([
            'name' => ['es' => 'Sin señal'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
        ]);
        $full->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 4000]);

        $order = $this->creator->createPendingOrder($this->user, [
            ['ticket_type_id' => $full->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1],
            ['ticket_type_id' => $full->id, 'date' => $this->date, 'time' => '11:00:00', 'qty' => 1],
        ]);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        $order->items()->first()->markCancelled($this->user); // cancela una línea → online<total, pero SIN señal

        App::setLocale('es');
        $mail = (new OrderConfirmation($order->fresh()))->toMail($this->user);
        $body = implode(' | ', array_map(fn ($l) => (string) $l, $mail->introLines));

        $this->assertStringContainsString('Total pagado', $body);
        $this->assertStringNotContainsString('Señal pagada online', $body);
    }

    /**
     * La caja de saldo de la hoja «con precios» es la del LIBRO de la reserva (T3·2 de
     * `specs/desglose-libro.md`): con señal, el resto va «a pagar en el parque», con su importe —
     * sustituye a la caja «A cobrar en puerta» con su ↳ «Resto de la señal» (#225).
     */
    public function test_reservation_slip_balance_box_says_the_deposit_rest_is_paid_at_the_park(): void
    {
        App::setLocale('es');
        $order = $this->creator->createPendingOrder($this->user, [
            ['ticket_type_id' => $this->dep->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1],
        ]);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        // Cobrado COMO lo cobra el canal real: sin el `Payment` de la señal el libro dice «en revisión».
        $this->payDeposit($order, $order->onlineDueCents());
        $principal = $order->items()->whereNull('parent_item_id')->first();

        $slip = ReservationSlip::make(
            $order->fresh(['items.children', 'items.slot', 'items.ticketType', 'adjustments', 'payments.refunds']),
            $principal->fresh(['children', 'adjustments']),
        );

        $book = $slip->book();
        $this->assertTrue($book->hasDeposit);
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
        $this->assertSame(15000, $book->balance->cents, 'el resto de la señal, a pagar en el parque');

        $html = view('pdf.reservation-slip', ['slip' => $slip, 'showPrices' => true])->render();
        $this->assertStringContainsString('data-book-balance="pay_at_park"', $html);
        $this->assertStringContainsString(__('admin.orders.book.balance_pay_at_park'), $html);
        $this->assertStringContainsString('150,00', $html);
    }

    private function payDeposit(Order $order, int $amountCents): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $amountCents, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(), 'gateway_order' => '17'.$order->id.'0009',
        ]);
    }

    public function test_deposit_order_has_no_phantom_pending_refund_at_order_level(): void
    {
        // Regresión del bug JJ-VDHXYH: un pedido del que solo se cobró la SEÑAL (30 €) de un
        // producto de 180 € NO debe mostrar «pendiente de devolución» a nivel pedido. La fórmula
        // se ancla al dinero REAL cobrado por web (30), no a `Order.total` (180) → 0 a devolver
        // (antes el value-axis daba 150 € fantasma, con riesgo de reembolsar de más).
        $order = $this->creator->createPendingOrder($this->user, [
            ['ticket_type_id' => $this->dep->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1],
        ]);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        $deposit = $order->fresh(['items', 'adjustments'])->onlineDueCents(); // 30,00
        $this->payDeposit($order, $deposit);

        $fresh = $order->fresh(['items', 'adjustments', 'payments.refunds']);
        $s = $fresh->financialSummary();

        $this->assertSame(3000, $deposit);
        $this->assertSame(0, $s->pendienteDevolucion());    // SIN fantasma
        $this->assertSame(15000, $s->pendingAtGate());       // el resto, en puerta
        $this->assertSame(18000, $s->totalFinalNeto());      // valor pleno = online + puerta
        // Reconcilia con las cards (Σ pendiente por ítem == pendiente del pedido).
        $this->assertSame(0, (int) $fresh->items->sum(fn ($i) => $fresh->itemPendingRefundCents($i)));
    }

    public function test_deposit_order_surfaces_real_over_collection_below_the_deposit(): void
    {
        // E4 del plan: si el valor cae por DEBAJO de la señal cobrada, el exceso SÍ aflora como
        // «pendiente de devolución». Pagó 30 € online; el producto vale ahora 20 € → 10 € a devolver.
        $order = $this->creator->createPendingOrder($this->user, [
            ['ticket_type_id' => $this->dep->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1],
        ]);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        $this->payDeposit($order, 3000);
        $item = $order->items()->whereNull('parent_item_id')->first();
        // El valor del pack cae de 180 € a 20 €: UNA bajada de −160,00 (T1 del libro: el delta
        // entero en una fila `edit`); la lectura la absorbe contra el resto de la señal (150) y el
        // sobrante (10) es lo que aflora como pendiente de devolución.
        $item->forceFill(['unit_price' => 2000, 'quantity' => 1])->save();
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_EDIT, 'amount_cents' => -16000,
            'currency' => 'EUR', 'reason' => 'item_edit_reduction', 'applied_by' => $this->user->id,
            'context' => ['changes' => ['unit_price_change' => ['old' => 18000, 'new' => 2000]]],
        ]);

        $s = $order->fresh(['items', 'adjustments', 'payments.refunds'])->financialSummary();

        $this->assertSame(1000, $s->pendienteDevolucion()); // 30 cobrado − 20 de valor = 10
        $this->assertSame(2000, $s->totalFinalNeto());      // el producto vale 20
    }
}
