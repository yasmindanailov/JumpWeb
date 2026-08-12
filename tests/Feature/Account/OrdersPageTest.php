<?php

namespace Tests\Feature\Account;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 5 (Pieza 3) — "Mis pedidos": el usuario ve sus reservas con detalle (código, estado,
 * líneas, total). Zona privada (auth + verified) y acotada a sus propios pedidos.
 */
class OrdersPageTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $jump1h;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08');
        $this->date = Carbon::today()->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        Slot::create([
            'zone_id' => $zone->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);
        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->jump1h->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function orderFor(User $user, int $qty = 2): Order
    {
        return app(OrderCreator::class)->createPendingOrder($user, [
            ['ticket_type_id' => $this->jump1h->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => $qty],
        ]);
    }

    public function test_orders_page_requires_authentication(): void
    {
        $this->get(route('account.orders'))->assertRedirect(route('login'));
    }

    public function test_user_sees_their_order_with_detail(): void
    {
        $user = User::factory()->create(); // verificado
        $order = $this->orderFor($user, 2);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('Jump · 1 hora')   // producto
            ->assertSee('Pendiente de pago') // estado traducido
            ->assertSee('20,00 €');         // total (2 × 10,00 €)
    }

    public function test_voided_leftover_principal_is_hidden_in_my_orders(): void
    {
        // #F11: un principal fantasma net-cero (producto gratuito 0€ cancelado) NO
        // se lista en "Mis pedidos" — saldría como "0,00 € · Cancelado" y solo
        // confunde. El principal normal sí se muestra.
        $user = User::factory()->create();
        $slot = Slot::where('date', $this->date)->first();

        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-GHOST1',
            'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'total' => 1000,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jump1h->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
        $ghostType = TicketType::create([
            'name' => ['es' => 'Producto fantasma'], 'zone_id' => $this->jump1h->zone_id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 9,
        ]);
        $ghost = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $ghostType->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 0, 'cancelled_at' => now(),
        ]);
        $this->assertTrue($order->fresh()->isVoidedLeftoverItem($ghost->fresh()));

        $this->actingAs($user)->get(route('account.orders'))->assertOk()
            ->assertSee('Jump · 1 hora')
            ->assertDontSee('Producto fantasma');
    }

    public function test_order_timestamp_is_rendered_in_display_timezone(): void
    {
        // Decisión #111 (2026-05-28) — Mis pedidos mostraba `created_at` en UTC porque la
        // vista usaba `format()` directo. Ahora va a través de `DisplayTime::format()` que
        // aplica `settings.display_timezone` (default `Europe/Madrid`).
        Setting::create(['key' => 'display_timezone', 'value' => 'Europe/Madrid', 'group' => 'display']);

        $user = User::factory()->create();
        $order = $this->orderFor($user, 1);

        // Fijamos created_at en un instante UTC verificable: 05:47 UTC = 07:47 CEST (mayo).
        DB::table('orders')->where('id', $order->id)->update(['created_at' => '2026-05-28 05:47:00']);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee('28/05/2026 07:47')   // Madrid
            ->assertDontSee('28/05/2026 05:47'); // NO UTC en la página
    }

    public function test_user_does_not_see_other_users_orders(): void
    {
        $other = User::factory()->create();
        $otherOrder = $this->orderFor($other, 1);

        $mine = User::factory()->create();

        $this->actingAs($mine)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertDontSee($otherOrder->code);
    }

    public function test_pack_order_shows_the_event_details(): void
    {
        $zone = Zone::create(['slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false]);
        // El pack dura 120 min y entra a las 11:00 → su fiesta (11:00–13:00) necesita franjas contiguas
        // que la cubran; con una sola franja la disponibilidad sería 0 (no se vende fuera de horario).
        foreach ([['11:00:00', '12:00:00'], ['12:00:00', '13:00:00']] as [$start, $end]) {
            Slot::create([
                'zone_id' => $zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => $end,
                'capacity' => 200, 'online_capacity' => 200,
            ]);
        }
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'prep_before_min' => 60, 'prep_after_min' => 30,
            'min_qty' => 10, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'event_fields' => [['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado/a']]],
        ]);
        $pack->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1500]);

        $user = User::factory()->create();
        app(OrderCreator::class)->createPendingOrder($user, [[
            'ticket_type_id' => $pack->id, 'date' => $this->date, 'time' => '11:00:00', 'qty' => 12,
            'event_data' => ['celebrant' => 'Lucía'],
        ]]);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee('12 invitados')   // nombre pack-aware
            ->assertSee('Homenajeado/a')  // etiqueta del campo del evento
            ->assertSee('Lucía');         // valor introducido por el cliente
    }

    public function test_order_shows_complements_grouped_under_their_product(): void
    {
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 50,
        ]);
        $addon->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 300]);
        DB::table('product_addons')->insert(['product_id' => $this->jump1h->id, 'addon_id' => $addon->id, 'position' => 0]);

        $user = User::factory()->create();
        app(OrderCreator::class)->createPendingOrder($user, [[
            'ticket_type_id' => $this->jump1h->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1,
            'addons' => [['ticket_type_id' => $addon->id, 'qty' => 2]],
        ]]);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee('Jump · 1 hora')  // producto
            ->assertSee('Calcetines');    // complemento agrupado bajo el producto
    }

    // ─── #146: Status renamed + refund badge + summary ─────────────────────

    public function test_paid_status_displays_as_completado_for_customer(): void
    {
        // #146: customer-facing rename. Antes "Pagado", ahora "Completado".
        $user = User::factory()->create();
        $order = $this->orderFor($user);
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee('Completado')
            ->assertDontSee('>Pagado<');  // estricto: no debe aparecer dentro de un tag
    }

    public function test_refunded_badge_appears_when_order_has_refund(): void
    {
        // El badge "Reembolsado" aparece de manera ADITIVA al status principal —
        // un Order "Completado" con devolución muestra ambos badges.
        $user = User::factory()->create();
        $order = $this->orderFor($user);
        $order->update([
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'refunded_at' => now()->subDay(),
            'refund_amount_cents' => $order->total,
        ]);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee(__('tickets.statuses.paid'))      // "Completado"
            ->assertSee(__('tickets.refunded_badge'));    // "Reembolsado"
    }

    public function test_refund_summary_shows_amount_date_and_net_when_refunded(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user);
        $refundDate = Carbon::parse('2026-05-25 10:00:00');
        $order->update([
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'refunded_at' => $refundDate,
            'refund_amount_cents' => $order->total,
        ]);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee('25/05/2026')                                    // fecha del refund
            ->assertSee('Reembolsado el')                                // label
            ->assertSee(__('tickets.subtotal'))                          // ledger: Subtotal → … → Total (#198.3, ya no "Neto")
            ->assertSee('−'.number_format($order->total / 100, 2, ',', '.').' €')
            ->assertSee('0,00 €');                                       // Total final = 0 (reembolso completo)
    }

    public function test_refund_summary_omitted_when_order_has_no_refund(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user);
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertDontSee(__('tickets.refunded_badge'))
            ->assertDontSee(__('tickets.net'))
            // Pedido limpio: sin líneas del desglose detallado (#196).
            ->assertDontSee(__('tickets.at_gate'))
            ->assertDontSee(__('tickets.pendiente_devolucion'));
    }

    public function test_at_gate_line_renders_when_edit_added_a_gate_charge(): void
    {
        // Robustez del desglose (#196): una subida de cantidad (cobro en puerta)
        // se muestra al cliente como "A cobrar en el parque".
        $user = User::factory()->create();
        $order = $this->orderFor($user, 1); // total 10,00
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);
        $item = $order->items()->whereNull('parent_item_id')->first();
        $item->forceFill(['quantity' => 2])->save(); // valor 20,00
        $order->applyExtraDue($item->fresh(), 1000, $user, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee(__('tickets.at_gate'))
            ->assertSee('+10,00 €', escape: false);
    }

    public function test_deposit_remainder_breakdown_line_renders_in_my_orders(): void
    {
        // #225 F2: «Mis pedidos» desglosa «A cobrar en el parque» en sus ↳ (espejo del panel).
        // Una reserva de la que solo se cobró la señal online muestra «Resto de la señal».
        $user = User::factory()->create();
        $order = $this->orderFor($user, 1); // valor 10,00
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);
        $item = $order->items()->whereNull('parent_item_id')->first();
        // Señal: 3,00 cobrada online + 7,00 de resto a cobrar en el parque.
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => 700, 'currency' => 'EUR', 'applied_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee(__('tickets.deposit_paid_online'))      // agregado online (ahora «Pagado online», neutro)
            ->assertSee(__('tickets.at_gate'))                  // agregado a cobrar en el parque
            ->assertSee(__('tickets.show_breakdown'))           // #225 F3: toggle «Ver desglose» del ↳
            ->assertSee(__('tickets.deposit_remainder_line'))   // ↳ resto de la señal (en el DOM, x-show)
            ->assertSee('+7,00 €', escape: false);              // el resto
    }

    public function test_my_orders_names_deposit_per_product_with_neutral_online_aggregate(): void
    {
        // #225 F3 (bug clienta): cesta MIXTA (pack con señal + entrada de pago completo). El agregado
        // online NO se etiqueta «Señal pagada» (engañaba: la entrada se paga entera) → neutro «Pagado
        // online»; la señal REAL se nombra en la card del producto que la cobra.
        $user = User::factory()->create();
        $zone = Zone::where('slug', 'jump')->first();
        $slot = Slot::where('zone_id', $zone->id)->first();
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 120, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true,
            'position' => 2, 'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 3000,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-MIX001', 'status' => Order::STATUS_PAID, 'total' => 19000, 'paid_at' => now(),
        ]);
        $packItem = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'parent_item_id' => null,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 18000,  // 180 € (señal 30 → resto 150)
        ]);
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $packItem->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER, 'amount_cents' => 15000, 'currency' => 'EUR', 'applied_by' => $user->id,
        ]);
        $order->items()->create([  // entrada de pago completo (10 €, sin señal)
            'ticket_type_id' => $this->jump1h->id, 'slot_id' => $slot->id, 'parent_item_id' => null,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee('Pagado online')                                                                 // agregado NEUTRO (no «Señal pagada»)
            ->assertSee(__('tickets.deposit_card_note', ['deposit' => '30,00 €', 'rest' => '150,00 €'])) // señal del pack en su card
            ->assertSee('orders__product-deposit', false);                                               // #3: reubicada al PIE de la card (no entre los datos del evento)
    }

    // ─── Post-form de una reserva CANCELADA: botón visible pero DESACTIVADO (decisión clienta) ───

    /**
     * Crea una reserva PAGADA de un pack con post-form (guest_fields). Devuelve el item principal.
     */
    private function paidPackReservation(User $user, bool $cancelled = false): OrderItem
    {
        $zone = Zone::where('slug', 'jump')->first();
        $slot = Slot::where('zone_id', $zone->id)->first();
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Kids'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 90, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-CANC01', 'status' => Order::STATUS_PAID,
            'total' => 3000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);

        return $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'parent_item_id' => null,
            'quantity' => 2, 'seats' => 2, 'unit_price' => 1500,
            'cancelled_at' => $cancelled ? now() : null,
        ]);
    }

    public function test_active_pack_shows_the_clickable_guest_form_button(): void
    {
        // Control: una reserva NO cancelada con post-form pendiente muestra el botón clicable
        // (enlace a la ruta del post-form), no la versión desactivada.
        $user = User::factory()->create();
        $item = $this->paidPackReservation($user, cancelled: false);

        $this->actingAs($user)->get(route('account.orders'))->assertOk()
            ->assertSee(__('account.orders.guest_form_pending', ['product' => 'Cumpleaños Kids']))
            ->assertSee(route('reservation.guests', ['reservation' => $item]), false) // enlace clicable presente
            ->assertDontSee(__('account.orders.guest_form_cancelled', ['product' => 'Cumpleaños Kids']));
    }

    public function test_cancelled_reservation_keeps_guest_form_button_disabled(): void
    {
        // Decisión clienta (2026-06-15): una reserva cancelada NO oculta el botón del post-form;
        // lo deja VISIBLE pero DESACTIVADO (no clicable). El botón es un <button disabled> (no un
        // enlace), así que pulsarlo no navega (evita el 404 del controlador, que excluye cancelados).
        $user = User::factory()->create();
        $item = $this->paidPackReservation($user, cancelled: true);

        $html = $this->actingAs($user)->get(route('account.orders'))->assertOk()
            ->assertSee(__('account.orders.guest_form_cancelled', ['product' => 'Cumpleaños Kids']))
            // El botón desactivado NO es un enlace a la ruta del post-form.
            ->assertDontSee(route('reservation.guests', ['reservation' => $item]), false)
            // Ni el CTA activo «Completa el formulario».
            ->assertDontSee(__('account.orders.guest_form_pending', ['product' => 'Cumpleaños Kids']))
            ->getContent();

        // El botón del post-form de esta reserva lleva el atributo `disabled` (no clicable).
        $this->assertMatchesRegularExpression('/<button[^>]*orders__guestform-btn[^>]*\bdisabled\b/', $html);
    }

    public function test_cancelled_order_keeps_guest_form_button_disabled(): void
    {
        // Caso «PEDIDO cancelado» (distinto de «reserva cancelada»): el pedido entero está CANCELLED
        // aunque el item del pack no se marque uno a uno (`cancelled_at` null). El botón del post-form
        // también se muestra DESACTIVADO (no clicable) — decisión clienta 2026-06-15.
        $user = User::factory()->create();
        $item = $this->paidPackReservation($user, cancelled: false);
        $item->order->update(['status' => Order::STATUS_CANCELLED]);

        $html = $this->actingAs($user)->get(route('account.orders'))->assertOk()
            ->assertSee(__('account.orders.guest_form_cancelled', ['product' => 'Cumpleaños Kids']))
            ->assertDontSee(route('reservation.guests', ['reservation' => $item]), false) // no es un enlace clicable
            ->assertDontSee(__('account.orders.guest_form_pending', ['product' => 'Cumpleaños Kids']))
            ->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*orders__guestform-btn[^>]*\bdisabled\b/', $html);
    }

    public function test_pendiente_devolucion_and_total_final_render_when_owed_back(): void
    {
        // Robustez del desglose (#196): una reducción NO reembolsada se muestra como
        // "Pendiente de devolución" + "Total final" (el desglose cuadra con las líneas).
        $user = User::factory()->create();
        $order = $this->orderFor($user, 2); // total 20,00, item qty 2
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);
        // Pagó 20,00 online (2 uds): ancla de caja de «pendiente de devolución» (#225).
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 2000, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(), 'gateway_order' => '1000000001',
        ]);
        $item = $order->items()->whereNull('parent_item_id')->first();
        $item->forceFill(['quantity' => 1])->save(); // valor 10,00 → se deben 10,00

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee(__('tickets.pendiente_devolucion'))
            ->assertSee(__('tickets.pendiente_devolucion_caption')) // #198.1: explica el porqué
            ->assertSee(__('tickets.subtotal'))                     // ledger Subtotal → … → Total
            ->assertSee('−10,00 €', escape: false);                // pendiente de devolución
    }
}
