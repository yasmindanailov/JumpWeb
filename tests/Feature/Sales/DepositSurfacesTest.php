<?php

namespace Tests\Feature\Sales;

use App\Livewire\Tickets\Purchase;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\Payment;
use App\Models\ProductAddon;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\OrderConfirmation;
use App\Support\OrderCreator;
use App\Support\ReservationSlip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * #225 iter. 3 — superficies del desglose de la señal.
 *
 * Verifica las piezas de coherencia más delicadas: el CANARIO anti doble-fuente (el split
 * previsto en el sidecart == lo que `OrderCreator`/`onlineDueCents` cobran para la MISMA
 * cesta), el email de confirmación (señal + pendiente, no «total pagado»), y la caja del PDF.
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

    public function test_cart_split_reconciles_and_matches_what_order_creator_charges(): void
    {
        // CANARIO anti doble-fuente (riesgo #7): el split previsto del sidecart DEBE coincidir
        // con lo que OrderCreator/onlineDueCents cobran para la MISMA cesta. Si divergen, el
        // canario `amount_mismatch` de Redsys rechazaría el pago.
        $cart = [['ticket_type_id' => $this->dep->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1]];

        $component = Livewire::actingAs($this->user)->test(Purchase::class)->set('cart', $cart);
        $deposit = $component->instance()->cartDepositCents();
        $total = $component->instance()->cartTotalCents();

        $this->assertSame(3000, $deposit);            // pagas ahora (señal)
        $this->assertSame(18000, $total);             // valor total
        $this->assertSame(15000, $total - $deposit);  // en el parque

        $order = $this->creator->createPendingOrder($this->user, $cart);
        $this->assertSame($deposit, $order->fresh(['items', 'adjustments'])->onlineDueCents());
    }

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

    public function test_cart_canary_holds_with_addons_of_a_deposit_product(): void
    {
        // L1: el canario también debe cuadrar con complementos (Opción A: van 100% a puerta).
        // Si cartDepositCents y onlineDueCents divergieran aquí, Redsys rechazaría el pago.
        $socks = $this->attachAddon('Calcetines', 2000);
        $cart = [[
            'ticket_type_id' => $this->dep->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1,
            'addons' => [['ticket_type_id' => $socks->id, 'qty' => 1]],
        ]];

        $deposit = Livewire::actingAs($this->user)->test(Purchase::class)->set('cart', $cart)
            ->instance()->cartDepositCents();

        $order = $this->creator->createPendingOrder($this->user, $cart);

        // Solo la señal (30€); el complemento (20€) va al parque (Opción A).
        $this->assertSame(3000, $deposit);
        $this->assertSame($deposit, $order->fresh(['items', 'adjustments'])->onlineDueCents());
        $this->assertSame(20000, (int) $order->total); // 180 pack + 20 addon
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

    public function test_reservation_slip_gate_box_includes_the_deposit_remainder(): void
    {
        App::setLocale('es');
        $order = $this->creator->createPendingOrder($this->user, [
            ['ticket_type_id' => $this->dep->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1],
        ]);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        $principal = $order->items()->whereNull('parent_item_id')->first();

        $slip = ReservationSlip::make(
            $order->fresh(['items.children', 'adjustments', 'payments.refunds']),
            $principal->fresh(['children', 'adjustments']),
        );

        // La caja «A cobrar en puerta» incluye el resto de la señal (no solo extra_due), nombrando
        // su producto («Resto de la señal de Con señal»), #225 feedback clienta.
        $this->assertSame(15000, $slip->pendingAtGateCents());
        $breakdown = implode(' | ', $slip->pendingAtGateBreakdown());
        $this->assertStringContainsString(__('admin.orders.slip.deposit_remainder_line'), $breakdown);
        $this->assertStringContainsString($slip->productName(), $breakdown);
    }

    public function test_sidecart_details_deposit_per_product_and_keeps_aggregate_neutral(): void
    {
        // #225 F2: en una cesta MIXTA (entrada de pago completo + pack con señal) el agregado «Pagas
        // ahora» NO debe etiquetarse «(señal)» (engañaba: la entrada se paga entera). La señal se
        // detalla en la CARD del producto que la cobra; el agregado queda neutro.
        $full = TicketType::create([
            'name' => ['es' => 'Entrada completa'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 3,
        ]);
        $full->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 4000]);

        $cart = [
            ['ticket_type_id' => $full->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1],     // 40 €, pago completo
            ['ticket_type_id' => $this->dep->id, 'date' => $this->date, 'time' => '11:00:00', 'qty' => 1], // 180 €, señal 30 €
        ];

        App::setLocale('es');
        Livewire::actingAs($this->user)->test(Purchase::class)
            ->set('cart', $cart)
            ->set('step', 8)
            // Sidebar v2: el footer ancla en el TOTAL (220 €) y desglosa NEUTRO «Pagas ahora» (40 + 30
            // = 70 €) + «En el parque» (150 €). El agregado NO se etiqueta «(señal)» (engañaba en cesta
            // mixta: la entrada se paga entera).
            ->assertSee(__('tickets.footer_pay_now'))
            ->assertDontSee('Pagas ahora (señal)')
            ->assertSee('220,00 €')   // Total (ancla)
            ->assertSee('70,00 €')    // pagas ahora (neutro)
            ->assertSee('150,00 €')   // en el parque
            // La señal se detalla en la card del PACK (no de la entrada de pago completo).
            ->assertSee(__('tickets.deposit_card_note', ['deposit' => '30,00 €', 'rest' => '150,00 €']));
    }

    public function test_catalog_announces_the_deposit_on_a_deposit_product(): void
    {
        // #225 F2: el catálogo anuncia la señal CONFIGURADA (data-driven). La entrada con señal fija
        // de 30 € muestra «Señal 30,00 €»; los productos sin señal no muestran nada. La señal vive en
        // la columna del precio (`.catalog__pricecol`), DEBAJO del precio (no bajo la descripción).
        $full = TicketType::create([
            'name' => ['es' => 'Entrada completa'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 4,
        ]);
        $full->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 4000]);

        App::setLocale('es');
        Livewire::actingAs($this->user)->test(Purchase::class)
            ->set('step', 1)
            ->assertSee(__('tickets.deposit_catalog', ['amount' => '30,00 €']))   // producto con señal
            ->assertSee('catalog__pricecol', false)                               // señal bajo el precio (#225 F2 reubicada)
            ->assertSee('Entrada completa');                                       // el sin-señal aparece, sin nota
    }

    public function test_quantity_step_announces_the_deposit_in_the_footer(): void
    {
        // #225 F2 + Sidebar v2: el footer ancla en el TOTAL (180 €) y desglosa la señal: «Pagas ahora
        // (señal) 30 €» + «En el parque 150 €». Producto único → la etiqueta lleva «(señal)» (aclara
        // por qué se cobra menos que el total). No se repite en el cuerpo del paso.
        App::setLocale('es');
        Livewire::actingAs($this->user)->test(Purchase::class)
            ->call('selectType', $this->dep->id)   // entrada con señal 30 € sobre 180 €
            ->call('selectDate', $this->date)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->set('qty', 1)
            ->assertSee(__('tickets.footer_pay_now_deposit'))   // «Pagas ahora (señal)»
            ->assertSee(__('tickets.pay_at_park'))              // «En el parque»
            ->assertSee('180,00 €')                             // Total (ancla)
            ->assertSee('30,00 €')                              // pagas ahora (señal)
            ->assertSee('150,00 €');                            // en el parque
    }

    public function test_confirmation_step_6_names_deposit_per_product_not_in_aggregate(): void
    {
        // #225 F3: en el paso 6 (confirmación), una cesta MIXTA (producto con señal + entrada de
        // pago completo) NO etiqueta el agregado como «Señal pagada» (engañaba: la entrada se paga
        // entera). La señal se nombra en la card del producto; el agregado es neutro «Pagado online».
        $full = TicketType::create([
            'name' => ['es' => 'Entrada completa'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 5,
        ]);
        $full->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 4000]);

        $order = $this->creator->createPendingOrder($this->user, [
            ['ticket_type_id' => $this->dep->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1],  // 180, señal 30
            ['ticket_type_id' => $full->id, 'date' => $this->date, 'time' => '11:00:00', 'qty' => 1],        // 40, pago completo
        ]);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        $this->payDeposit($order, $order->fresh(['items', 'adjustments'])->onlineDueCents()); // 70 (30 señal + 40)

        App::setLocale('es');
        Livewire::actingAs($this->user)->test(Purchase::class)
            ->set('orderCode', $order->code)
            ->set('step', 6)
            ->assertSee(__('tickets.paid_online_confirmed'))                                          // «Pagado online» (neutro)
            ->assertDontSee('Señal pagada')                                                           // etiqueta antigua retirada
            ->assertSee(__('tickets.deposit_card_note', ['deposit' => '30,00 €', 'rest' => '150,00 €'])) // señal por-producto
            ->assertSee('70,00 €')                                                                    // pagado online (30 + 40)
            ->assertSee('150,00 €');                                                                  // pendiente en el parque
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
        // El valor del pack cae a 20 € y el resto de la señal (150) se credita a 0 (bajada honda).
        $item->forceFill(['unit_price' => 2000, 'quantity' => 1])->save();
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER, 'amount_cents' => -15000,
            'currency' => 'EUR', 'reason' => 'item_edit_reduction', 'applied_by' => $this->user->id,
        ]);

        $s = $order->fresh(['items', 'adjustments', 'payments.refunds'])->financialSummary();

        $this->assertSame(1000, $s->pendienteDevolucion()); // 30 cobrado − 20 de valor = 10
        $this->assertSame(2000, $s->totalFinalNeto());      // el producto vale 20
    }
}
