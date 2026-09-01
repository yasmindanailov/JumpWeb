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
use App\Domain\Booking\Services\LineFacts;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LAS IDENTIDADES DEL LIBRO, escenario a escenario** (`INVARIANTES.md` `PAY-16`/`PAY-17`;
 * `specs/desglose-libro.md` §4.1 y §6.3.6).
 *
 * Nació como la red del modelo de DOS EJES (`DECISIONES #127`): tres compositores del mismo dinero
 * —por ítem, por reserva y por pedido— que coincidían «por disciplina + tests». Desde la T3·4 del
 * libro (`#313`) hay UN compositor, `OrderBook`, y lo que este fichero ancla son sus cuatro
 * identidades sobre pedidos VARIADOS (sin actividad, cargo de puerta pendiente y liquidado,
 * cancelación, reembolso parcial y total, señal, descuento de fiesta mixta):
 *
 *   I1 · `Order.total == Σ nac(i)`            (lo facturado es lo que nació)
 *   I2 · `cobrado == Σ online_nac(i)`         (el cobro online es lo que las líneas aportaron)
 *   I3 · `Total == Σ líneas de valor`         (por pedido y por reserva)
 *   I4 · `refund_amount_cents == Σ filas`     (la columna agregada nunca diverge)
 *   H  · `Σ Total(r) == Total` y `Σ Pagado(r) == Pagado`   (las reservas suman el pedido)
 *
 * y que la CLASE del saldo sigue a su signo. No prueba un cómputo nuevo: ANCLA lo que un cambio en
 * la composición rompería en silencio, con el escenario y la cifra delante.
 */
class OrderFinancialInvariantsTest extends TestCase
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

    public function test_plain_paid_order_with_addon_closes(): void
    {
        $order = $this->makePaidOrder();
        $principal = $this->attachActiveItem($order, unitPrice: 1000);
        $this->attachAddon($order, $principal, unitPrice: 300);
        $this->syncTotalToOnline($order);

        $book = $this->assertBookCloses($order, 'sin actividad (principal + complemento)');

        $this->assertSame(1300, $book->totalCents);
        $this->assertSame(1300, $book->paidCents);
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
    }

    public function test_active_item_with_pending_gate_charge_closes(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        // El producto vale 1500 hoy (subió en gestión); de eso 500 se paga en el parque.
        $item = $this->attachActiveItem($order, unitPrice: 1500);
        $order->recordEdit($item, 500, $by);
        $this->syncTotalToOnline($order);

        $book = $this->assertBookCloses($order, 'cargo de puerta PENDIENTE (ítem activo)');

        $this->assertSame(1500, $book->totalCents);
        $this->assertSame(1000, $book->paidCents);
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
        $this->assertSame(500, $book->balance->cents);
    }

    public function test_finished_item_with_collected_gate_charge_closes(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItemWithPastSlot($order);   // qty1 × 1000, slot pasado → finalizado
        $order->recordEdit($item, 400, $by);
        $item->forceFill(['unit_price' => 1400])->save();   // la subida deja la fila en 14,00 (nació en 10,00)
        $this->syncTotalToOnline($order);

        $book = $this->assertBookCloses($order, 'cargo de puerta LIQUIDADO (ítem finalizado)');

        $this->assertSame(1400, $book->totalCents);
        $this->assertSame(400, $book->settledAtGateCents(), 'finalizado y cobrado → el cargo consta liquidado en el parque');
        $this->assertSame(1400, $book->paidCents);
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
    }

    public function test_cancelled_collected_item_owes_the_money_back_and_closes(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1000);
        $this->syncTotalToOnline($order);   // total = 1000 (lo pagado online)
        $item->markCancelled($by);          // cancelado, sin reembolsar todavía

        $book = $this->assertBookCloses($order, 'cancelación con devolución pendiente');

        $this->assertSame(0, $book->totalCents, 'ya no hay producto');
        $this->assertSame(1000, $book->paidCents);
        $this->assertSame(1000, $book->owedToCustomerCents(), 'todo lo cobrado, por devolver');
    }

    public function test_partial_per_item_refund_closes(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1000);
        $this->syncTotalToOnline($order);
        // Reembolso parcial de CORTESÍA, por el flujo real: desde la T1 del libro escribe su hecho
        // (`courtesy`) en la misma transacción, y una fila de reembolso puesta a mano sin él es un
        // mundo que no existe (la lección de los tres fixtures ilegales, spec §6.1).
        $result = $this->freshOrder($order)->executePartialRefund($item, 400, User::factory()->create(), PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION);
        $this->assertTrue($result['ok'], json_encode($result));

        $book = $this->assertBookCloses($order, 'reembolso parcial por ítem (producto activo)');

        $this->assertSame(600, $book->totalCents, '10,00 − 4,00 de cortesía');
        $this->assertSame(600, $book->paidCents, '10,00 cobrados − 4,00 devueltos');
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
        $order = $this->freshOrder($order);
        $this->assertSame(400, $order->itemRefundedCents($order->items->firstWhere('id', $item->id)));
    }

    public function test_combo_active_with_addon_plus_cancelled_principal_closes(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);

        $p1 = $this->attachActiveItem($order, unitPrice: 1000);
        $this->attachAddon($order, $p1, unitPrice: 300);
        $p2 = $this->attachActiveItem($order, unitPrice: 800);
        $this->syncTotalToOnline($order);   // total = 1000 + 300 + 800 = 2100
        $p2->markCancelled($by);            // se cancela el segundo principal (sin reembolsar)

        $book = $this->assertBookCloses($order, 'combo: activo+complemento y principal cancelado');

        $this->assertSame(1300, $book->totalCents, 'solo P1 + su complemento');
        $this->assertSame(800, $book->owedToCustomerCents(), 'P2 cancelado, por devolver');
    }

    // ─── Los escenarios que la TERCERA auditoría destapó ───────────────────
    //
    // ⚠️ Los seis de arriba pasaban ya antes de `DECISIONES #127`: su hueco no estaba en la
    // aserción, estaba en el FIXTURE. Estos cinco son los que ejercitan los defectos que se
    // arreglaron, y sin ellos las guardas nuevas no verían nada.

    public function test_cancelled_order_has_no_live_value_and_owes_the_money_back(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $this->attachActiveItem($order, unitPrice: 1000);
        $this->syncTotalToOnline($order);

        // El camino del panel: cancelar el PEDIDO. Antes de #127 dejaba las líneas VIVAS.
        $order = $this->freshOrder($order);
        $order->update(['status' => Order::STATUS_CANCELLED]);
        $order->cancelLiveItems($by);

        $book = $this->assertBookCloses($order, 'pedido CANCELADO sin reembolsar');

        $this->assertSame(0, $book->totalCents, 'un pedido cancelado no tiene valor vivo');
        $this->assertSame(1000, $book->owedToCustomerCents(), 'y lo cobrado aflora como saldo a devolver');
    }

    public function test_an_order_never_collected_claims_no_money_even_after_its_slot_passes(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachItemWithPastSlot($order);          // franja PASADA
        $order->recordEdit($item, 400, User::factory()->create());
        $item->forceFill(['unit_price' => 1400])->save();
        $this->syncTotalToOnline($order);
        // Nadie llegó a pagarlo: ni `paid_at` ni pago cobrado. Es el checkout abandonado cuya
        // franja pasa — el caso que destapó STAGING.
        $this->freshOrder($order)->update(['status' => Order::STATUS_PENDING, 'paid_at' => null]);
        $order->payments()->delete();

        $book = $this->assertBookCloses($order, 'pedido NUNCA cobrado con la franja ya pasada');

        $this->assertSame(0, $book->settledAtGateCents(), 'sin cobro no se cobró nada en el parque');
        $this->assertSame(0, $book->paidCents, 'ni se pagó nada por web');
        $this->assertSame(Balance::KIND_PAY_ONLINE, $book->balance->kind, 'lo cobrable sigue PENDIENTE, no pagado');
        $this->assertGreaterThan(0, $book->balance->cents);
    }

    public function test_a_full_refund_is_attributed_to_its_reservation(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order, unitPrice: 1000);
        $this->syncTotalToOnline($order);
        // Un reembolso TOTAL se escribe SIN atar a ninguna línea: es la operación sobre el `Payment`.
        // Por el flujo real (modo manual), que además deja su cortesía: no se le debía nada.
        $result = $this->freshOrder($order)->executeFullRefund(User::factory()->create(), PaymentRefund::MODE_MANUAL, false, PaymentRefund::INTENT_COMPENSATION);
        $this->assertTrue($result['ok'], json_encode($result));

        $this->assertBookCloses($order, 'reembolso TOTAL, sin atar a línea');

        $order = $this->freshOrder($order);
        $principal = $order->items->firstWhere('id', $item->id);
        $reservation = OrderBook::forReservation($order, $principal);
        $this->assertSame(0, $reservation->paidCents, 'la reserva tiene que ver el reembolso total, no un 0,00 € devuelto');
        $this->assertSame(0, $reservation->totalCents, 'y la cortesía que lo explica: 10,00 − 10,00');
        $this->assertSame(1000, $order->itemRefundedCents($principal));
    }

    /**
     * **La prorrata de un reembolso TOTAL entre dos reservas** (D-T3·24 de la spec): el dinero vuelve
     * a cada reserva en proporción a lo que APORTÓ al cobro — ni a partes iguales ni por su valor de
     * hoy. Mutación que muerde: pesar las líneas a partes iguales (la reserva barata «devolvería»
     * 20,00 sobre 10,00 pagados, y su libro se iría a −10,00).
     */
    public function test_a_full_refund_is_prorated_by_what_each_reservation_paid(): void
    {
        $order = $this->makePaidOrder();
        $a = $this->attachActiveItem($order, unitPrice: 1000);
        $b = $this->attachActiveItem($order, unitPrice: 3000);
        $this->syncTotalToOnline($order);   // 40,00 pagados
        $result = $this->freshOrder($order)->executeFullRefund(User::factory()->create(), PaymentRefund::MODE_MANUAL, true, PaymentRefund::INTENT_VALUE_RETURNED);
        $this->assertTrue($result['ok'], json_encode($result));

        $this->assertBookCloses($order, 'reembolso TOTAL con dos reservas, cancelando');

        $order = $this->freshOrder($order);
        $ra = OrderBook::forReservation($order, $order->items->firstWhere('id', $a->id));
        $rb = OrderBook::forReservation($order, $order->items->firstWhere('id', $b->id));
        $this->assertSame(1000, $order->itemRefundedCents($order->items->firstWhere('id', $a->id)), 'a la reserva de 10,00 le vuelven 10,00');
        $this->assertSame(3000, $order->itemRefundedCents($order->items->firstWhere('id', $b->id)), 'y a la de 30,00, 30,00');
        $this->assertSame(0, $ra->paidCents);
        $this->assertSame(0, $rb->paidCents);
        $this->assertSame(0, OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->count(), 'se devolvió lo que la cancelación dejó a deber: sin cortesía');
    }

    public function test_a_deposit_pack_whose_slot_passed_collects_the_rest_at_the_gate(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachItemWithPastSlot($order);          // 1000, franja pasada
        $this->attachDepositRemainder($order, $item, 700);      // señal 300, resto 700 en puerta
        $this->syncTotalToOnline($order);

        $book = $this->assertBookCloses($order, 'pack con señal cuya franja YA PASÓ');

        $this->assertTrue($book->hasDeposit);
        $this->assertSame(700, $book->settledAtGateCents(), 'el resto de la señal se cobró en el parque');
        $this->assertSame(1000, $book->paidCents, '300 por web + 700 en el parque');
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
    }

    public function test_a_pending_order_owes_its_money_online_not_paid_it(): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-IVP'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR',
        ]);
        $this->attachActiveItem($order, unitPrice: 1000);

        $book = $this->assertBookCloses($order, 'pedido PENDIENTE, todavía sin cobrar');

        $this->assertSame(0, $book->paidCents);
        $this->assertSame(Balance::KIND_PAY_ONLINE, $book->balance->kind);
        $this->assertSame(1000, $book->balance->cents, 'es lo que FALTA por pagar, no lo pagado');
    }

    // ─── T4 · el −X € del descuento de fiesta mixta (`specs/cumple-mixto.md` §20 y §24) ─────
    //
    // Los tres casos A/B/C del diseño entran aquí ANTES del reconciliador (§16.8/§20.8), con la
    // línea de crédito construida A MANO: lo que estos escenarios prueban es la CONTABILIDAD del
    // espejo —una línea `is_credit` con su hecho `mixed` negativo— contra las identidades del libro,
    // no el mecanismo que la escribe.

    public function test_mixed_party_credit_with_deposit_closes(): void
    {
        // CASO A (§20.3): fiesta 8 × 15,00 € = 120,00 · señal 30,00 online · resto 90,00 en
        // puerta · descuento 8,00 (2 invitados de un pack 4,00 € más barato). La puerta absorbe:
        // el cliente pagará 82,00 en el parque.
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order, quantity: 8, unitPrice: 1500);
        $this->attachDepositRemainder($order, $item, 9000);
        $this->attachCreditLine($order, $item, 800, guests: 2, unitCents: 400);
        $this->syncTotalToOnline($order);

        $book = $this->assertBookCloses($order, 'T4·A crédito con señal (la puerta absorbe)');

        $this->assertSame(11200, $book->totalCents, 'el valor baja con el descuento');
        $this->assertSame(3000, $book->paidCents, 'la señal no se toca');
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
        $this->assertSame(8200, $book->balance->cents, '90,00 − 8,00: en la puerta le pedirán 82,00');
    }

    public function test_mixed_party_credit_fully_online_is_written_entire_and_owed_at_the_park(): void
    {
        // CASO B (`DECISIONES #305` D4 · `#312`): pagado 100 % online, el descuento se escribe ENTERO
        // (8,00) y el libro debe 8,00 «a devolver en el parque». La línea se construye a mano: lo que
        // se prueba es la CONTABILIDAD del libro; el mecanismo que la escribe (y su mutación:
        // restaurar el tope) vive en `MixedPartySurchargeTest`.
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order, quantity: 8, unitPrice: 1500);
        $this->attachCreditLine($order, $item, 800, guests: 2, unitCents: 400);
        $this->syncTotalToOnline($order);

        $book = $this->assertBookCloses($order, 'T4·B crédito 100 % online');

        $this->assertSame(11200, $book->totalCents, '120,00 − 8,00');
        $this->assertSame(12000, $book->paidCents, 'todo se cobró online');
        $this->assertSame(Balance::KIND_REFUND_AT_PARK, $book->balance->kind, 'hay visita por delante');
        $this->assertSame(-800, $book->balance->cents);
    }

    public function test_mixed_party_credit_with_partial_coverage_is_written_entire(): void
    {
        // CASO C: señal grande (117,00 online, 3,00 en puerta) y descuento de 8,00. Hasta la T3·3 el
        // tope dejaba escribir SOLO 3,00 y los 5,00 restantes eran el «a tu favor»; ahora se escriben
        // los 8,00, el Total baja a 112,00 y el libro debe 5,00 en el parque.
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order, quantity: 8, unitPrice: 1500);
        $this->attachDepositRemainder($order, $item, 300);
        $this->attachCreditLine($order, $item, 800, guests: 2, unitCents: 400);
        $this->syncTotalToOnline($order);

        $book = $this->assertBookCloses($order, 'T4·C crédito cubierto a medias');

        $this->assertSame(11200, $book->totalCents);
        $this->assertSame(11700, $book->paidCents, 'la señal grande');
        $this->assertSame(Balance::KIND_REFUND_AT_PARK, $book->balance->kind);
        $this->assertSame(-500, $book->balance->cents, '3,00 de resto − 8,00 de descuento');
    }

    public function test_mixed_party_credit_resolves_with_the_finished_reservation(): void
    {
        // Y al FINALIZAR la fiesta, el saldo se liquida en el parque: «liquidado» dice 82,00 —lo que
        // de verdad se le cobró—, no 90,00.
        $order = $this->makePaidOrder();
        $item = $this->attachItemWithPastSlot($order);
        $item->forceFill(['quantity' => 8, 'seats' => 8, 'unit_price' => 1500])->save();
        $this->attachDepositRemainder($order, $item, 9000);
        $this->attachCreditLine($order, $item, 800, guests: 2, unitCents: 400);
        $this->syncTotalToOnline($order);

        $book = $this->assertBookCloses($order, 'T4 crédito con la reserva FINALIZADA');

        $this->assertSame(11200, $book->totalCents);
        $this->assertSame(8200, $book->settledAtGateCents(), 'se cobró 82,00 en el parque, no 90,00');
        $this->assertSame(Balance::KIND_SETTLED, $book->balance->kind);
    }

    // ─── La aserción: el libro CIERRA ──────────────────────────────────────

    private function assertBookCloses(Order $order, string $label): OrderBook
    {
        $order = $this->freshOrder($order);
        $book = OrderBook::forOrder($order);

        $this->assertTrue($book->isConsistent, "$label · el libro tiene que CERRAR (I1–I4)");

        // I1 · lo facturado es lo que nació.
        $this->assertSame((int) $order->total, $order->birthValueCents(), "$label · I1 · Order.total == Σ nac(i)");

        // I2 · lo cobrado online es lo que las líneas aportaron (si hubo cobro).
        $collected = (int) $order->payments->where('status', Payment::STATUS_PAID)->sum('amount');
        $online = (int) $order->items->sum(fn (OrderItem $i): int => LineFacts::forItem($order, $i)->onlineAtBirth());
        if ($collected > 0) {
            $this->assertSame($collected, $online, "$label · I2 · cobrado == Σ online_nac(i)");
        }

        // I3 · Total == Σ líneas de valor, por pedido y por reserva; H · las reservas suman el pedido.
        $this->assertSame($book->totalCents, $book->movementsSumCents(), "$label · I3 · Σ líneas de valor == Total");
        $sumTotal = $sumPaid = 0;
        foreach ($order->items->whereNull('parent_item_id')->sortBy('id') as $principal) {
            $rb = OrderBook::forReservation($order, $principal);
            $this->assertSame($rb->totalCents, $rb->movementsSumCents(), "$label · I3 por reserva #{$principal->id}");
            $sumTotal += $rb->totalCents;
            $sumPaid += $rb->paidCents;
        }
        $this->assertSame($book->totalCents, $sumTotal, "$label · H · Σ Total(r) == Total");
        $this->assertSame($book->paidCents, $sumPaid, "$label · H · Σ Pagado(r) == Pagado");

        // I4 · la columna agregada del reembolso es SIEMPRE Σ de las filas (`DECISIONES #127`).
        $this->assertSame((int) ($order->refund_amount_cents ?? 0), $order->totalRefundedCents(), "$label · I4 · refund_amount_cents == Σ payment_refunds con éxito");

        // La CLASE del saldo sigue a su signo (spec §4.4).
        $saldo = $book->totalCents - $book->paidCents;
        switch ($book->balance->kind) {
            case Balance::KIND_PAY_ONLINE:
                $this->assertGreaterThan(0, $book->balance->cents, "$label · pay_online: lo que falta por cobrar por web");
                break;
            case Balance::KIND_PAY_AT_PARK:
                $this->assertGreaterThan(0, $saldo, "$label · pay_at_park exige saldo positivo");
                $this->assertSame($saldo, $book->balance->cents, "$label · el saldo con signo");
                break;
            case Balance::KIND_REFUND_AT_PARK:
            case Balance::KIND_REFUND_PENDING:
                $this->assertLessThan(0, $saldo, "$label · refund_* exige saldo negativo");
                $this->assertSame($saldo, $book->balance->cents, "$label · el saldo con signo");
                break;
            case Balance::KIND_SETTLED:
                $this->assertSame(0, $saldo, "$label · settled exige saldo cero");
                break;
            default:
                $this->fail("$label · clase de saldo inesperada en un libro que cierra: {$book->balance->kind}");
        }

        return $book;
    }

    // ─── Fixtures (patrón de OrderPerItemHelpersTest) ──────────────────────

    private function freshOrder(Order $order): Order
    {
        return Order::with(['items.children', 'items.slot', 'items.ticketType', 'payments.refunds', 'adjustments'])
            ->findOrFail($order->id);
    }

    /**
     * Deja el pedido como lo deja un ALTA REAL: `Order.total` = el valor con el que NACIÓ (lo que
     * `OrderCreator` guarda: el subtotal completo, señal incluida) y el pago cobrado = lo que entró
     * ONLINE (Σ de lo que cada línea aportó al cobro, `LineFacts::onlineAtBirth`).
     *
     * ⚠️⚠️ **Hasta la T1 del libro (`specs/desglose-libro.md`) este fixture ponía `Order.total` = la
     * parte ONLINE**, y en los cuatro escenarios con señal eso fabricaba un pedido que ningún alta
     * produce: `OrderCreator` guarda `'total' => $subtotal` (el valor entero), así que un pack de
     * 120,00 € con señal de 30,00 nace con `total = 12000`, no `3000`. *Un fixture que necesita un
     * estado que el dominio no produce prueba un mundo que no existe* (la lección de la T6 de
     * mixtos): se LEGALIZA, no se excepciona.
     *
     * ⚠️⚠️ **Y si no había pago, se crea**: un pedido marcado `paid` SIN ninguna fila `Payment` no lo
     * produce ningún cobro real —ni el web ni el de taquilla— y hace que la identidad de caja (I2)
     * compare contra un cobro de 0,00 €. Es el mismo defecto de datos que la auditoría encontró en
     * los pedidos sembrados a mano (`specs/desglose-dinero-cliente.md` §9.7): un fixture irreal
     * inventa defectos tan bien como los oculta.
     */
    private function syncTotalToOnline(Order $order): void
    {
        $order->refresh()->load(['items', 'adjustments']);
        $online = (int) $order->items->sum(fn (OrderItem $i): int => LineFacts::forItem($order, $i)->onlineAtBirth());
        $birth = $order->birthValueCents();
        $order->update(['subtotal' => $birth, 'total' => $birth]);
        if ($order->payments()->where('status', Payment::STATUS_PAID)->doesntExist()) {
            $this->attachPaidPayment($order);
        }
        $order->payments()->where('status', Payment::STATUS_PAID)->update(['amount' => $online]);
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

    private function makePaidOrder(int $total = 1000): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-IV'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
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

    private function attachItemWithPastSlot(Order $order): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => '2000-01-01',
            'start_time' => '10:00:00', 'end_time' => '10:59:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
    }

    /**
     * La LÍNEA DE CRÉDITO del descuento de fiesta mixta (T4, `specs/cumple-mixto.md` §24.3), tal
     * como la escribe el reconciliador: una línea hija `is_credit` (su subtotal RESTA vía
     * `chargedSubtotalCents`) con su hecho `mixed` NEGATIVO del mismo importe — el patrón exacto del
     * cargo, con el signo cambiado. ⚠️ El `context` lleva la marca `mixed_party` con `credit: true`
     * y JAMÁS `changes.*` (`MixedPartySurchargeTest` lo asevera).
     */
    private function attachCreditLine(Order $order, OrderItem $principal, int $writtenCents, int $guests, int $unitCents): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $credit = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $principal->id,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => null,
            'quantity' => 1, 'free_quantity' => 0, 'unit_price' => $writtenCents,
            'is_credit' => true,
            'seats' => 0,
        ]);
        OrderAdjustment::create([
            'order_id' => $order->id,
            'order_item_id' => $credit->id,
            'type' => OrderAdjustment::TYPE_MIXED,
            'amount_cents' => -$writtenCents,
            'currency' => 'EUR',
            'applied_by' => User::factory()->create()->id,
            'reason' => 'mixed_party_credit',
            'context' => ['mixed_party' => [
                'credit' => true,
                'guests' => $guests,
                'targets' => [['name' => 'Kids', 'count' => $guests, 'unit_cents' => $unitCents]],
                'derived_cents' => $guests * $unitCents,
            ]],
        ]);

        return $credit;
    }

    /** Resto de la SEÑAL (#225): la parte del valor que no se cobra online y se paga en el parque. */
    private function attachDepositRemainder(Order $order, OrderItem $item, int $cents): void
    {
        OrderAdjustment::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
            'amount_cents' => $cents,
            'currency' => 'EUR',
            'applied_by' => User::factory()->create()->id,
        ]);
    }

    private function attachAddon(Order $order, OrderItem $parent, int $unitPrice = 300): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $parent->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => $unitPrice,
        ]);
    }
}
