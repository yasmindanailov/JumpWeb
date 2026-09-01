<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **El registro de un pedido se LEE** (`DECISIONES #145`).
 *
 * ## Por qué hacía falta esta clase, y no bastaba la que ya había
 *
 * `OrderAuditModalTest` tiene once casos y **ninguno renderiza el modal**: todos ejercitan el
 * paginador —alcance, no-fuga entre pedidos, orden, tamaño de página—. Es una cobertura buena de
 * la CONSULTA y ciega a la PRESENTACIÓN, y por eso convivió durante semanas con un registro que
 * enseñaba esto y nada más:
 *
 *     orders.value_reduction_applied
 *     Motivo: item_edit_reduction
 *
 * Es la misma familia de hueco que `#113` (veinte iconos servidos vacíos) y `#119(f)` (los botones
 * de mes sin cablear): **lo que ningún test mira, nadie lo ve romperse**.
 *
 * ## Qué fija cada caso
 *
 * Los cinco corresponden uno a uno con los defectos medidos sobre el pedido `R-S9XDYB` de staging.
 * Se renderiza el partial REAL con datos reales, no se inspecciona el paginador.
 */
class OrderAuditReadabilityTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $product;

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
        $this->zone = Zone::create([
            'slug' => 'jump',
            'name' => ['es' => 'JUMP'],
            'accent' => 'jump',
            'color' => '#FF5B22',
            'position' => 1,
        ]);
        $this->product = TicketType::create([
            'name' => ['es' => 'Cumpleaños Kids'],
            'zone_id' => $this->zone->id,
            'duration_min' => 90,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 1,
        ]);
    }

    /**
     * ⚠️ **La entrada se registra sobre el PEDIDO**, que es como lo hace el código real
     * (`Order::recordEdit` pasa `target: $this`). Verificado contra staging: las seis entradas
     * de `R-S9XDYB` tienen `target_type = order`.
     */
    private function renderAuditFor(Order $order): string
    {
        $component = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code]);

        return view('filament.orders.partials.order-audit-view', [
            'order' => $order->fresh(),
            'paginator' => $component->instance()->getOrderAuditPaginatorProperty(),
            'perPageOptions' => [10, 25],
        ])->render();
    }

    public function test_an_order_level_entry_is_badged_as_order_not_as_product(): void
    {
        $order = $this->makeOrder();
        AuditLogger::log('orders.value_reduction_applied', $order, [
            'order_code' => $order->code, 'amount_cents' => -2400, 'reason' => 'item_edit_reduction',
        ]);

        $html = $this->renderAuditFor($order);

        // `enforceMorphMap` guarda `order`, no el FQCN. La comparación con `Order::class` era
        // siempre falsa y TODAS las entradas salían como «Producto», también las del pedido.
        $this->assertStringContainsString(__('admin.orders.audit_modal.target_order'), $html,
            'Una entrada cuyo target es el Order tiene que salir etiquetada como PEDIDO.');
        $this->assertStringNotContainsString(__('admin.orders.audit_modal.target_item'), $html,
            'Ninguna entrada de este pedido es de nivel producto: si sale «Producto», la comparación del morphMap volvió a romperse.');
    }

    public function test_the_action_is_shown_translated_and_never_as_a_raw_key(): void
    {
        $order = $this->makeOrder();
        AuditLogger::log('orders.value_reduction_applied', $order, [
            'order_code' => $order->code, 'amount_cents' => -2400, 'reason' => 'item_edit_reduction',
        ]);

        $html = $this->renderAuditFor($order);

        $this->assertStringContainsString(__('admin.orders.audit_modal.actions.orders.value_reduction_applied'), $html);
        $this->assertStringNotContainsString('orders.value_reduction_applied', $html,
            'La clave cruda de la acción no puede llegar al HTML: es literalmente lo que el owner encontró.');
    }

    /** El importe estaba en el payload desde el primer día y no lo pintaba nadie. */
    public function test_a_gate_adjustment_shows_its_amount_and_a_readable_reason(): void
    {
        $order = $this->makeOrder();
        AuditLogger::log('orders.value_reduction_applied', $order, [
            'order_code' => $order->code, 'amount_cents' => -2400, 'reason' => 'item_edit_reduction',
        ]);

        $html = $this->renderAuditFor($order);

        $this->assertStringContainsString('-24,00', $html, 'El importe del ajuste tiene que verse.');
        $this->assertStringContainsString(__('admin.orders.audit_modal.reasons.item_edit_reduction'), $html);
        $this->assertStringNotContainsString('item_edit_reduction', $html,
            'El motivo en crudo era la única información que el registro daba, y no es información.');
    }

    /**
     * El caso exacto de `R-S9XDYB`: bajar de 18,90 a 15,90 por mover la fecha. El registro lo tenía
     * guardado y no lo enseñaba.
     */
    public function test_an_item_edit_shows_the_unit_price_move_its_difference_and_what_changed(): void
    {
        $order = $this->makeOrder();
        AuditLogger::log('orders.item_edited', $order, [
            'order_code' => $order->code,
            'from_unit_price' => 1890,
            'to_unit_price' => 1590,
            'price_diff_cents' => -2400,
            'changes' => ['slot_change'],
        ]);

        $html = $this->renderAuditFor($order);

        $this->assertStringContainsString('18,90', $html, 'El precio unitario de origen.');
        $this->assertStringContainsString('15,90', $html, 'El precio unitario de destino.');
        $this->assertStringContainsString('-24,00', $html, 'La diferencia, que es lo que explica el abono.');
        $this->assertStringContainsString(__('admin.orders.audit_modal.change_kinds.slot_change'), $html,
            'Y POR QUÉ cambió el precio: sin esto el operador ve un movimiento sin causa.');
    }

    /**
     * T5 adenda (`cumple-mixto.md` §25.10, cazado por el owner sobre `T5-PRB01`): la forma de la
     * CANTIDAD, la que `#145` dejó fuera — el payload traía `from_quantity`/`to_quantity` desde
     * siempre y el modal decía «Cambió: cantidad» SIN los números, así que el operador veía un
     * −30,00 € sin poder saber cuántas entradas había antes (el correo del cliente sí lo decía).
     * Mutación: quitar el bloque `hasQtyMove` del partial.
     */
    public function test_a_quantity_change_shows_the_before_and_after(): void
    {
        $order = $this->makeOrder();
        AuditLogger::log('orders.item_edited', $order, [
            'order_code' => $order->code,
            'from_quantity' => 4,
            'to_quantity' => 2,
            'price_diff_cents' => -3000,
            'changes' => ['quantity_change'],
        ]);

        $html = $this->renderAuditFor($order);

        $this->assertStringContainsString(
            __('admin.orders.audit_modal.quantity_move', ['from' => 4, 'to' => 2]),
            $html,
            'La cantidad de origen y la de destino: sin ellas el −30,00 € no tiene historia.',
        );
        $this->assertStringContainsString('-30,00', $html, 'Y la diferencia que explica la deuda.');
    }

    public function test_a_slot_change_shows_the_dates_it_moved_between(): void
    {
        $order = $this->makeOrder();
        AuditLogger::log('orders.item_slot_changed', $order, [
            'order_code' => $order->code,
            'from_date' => '2026-09-05', 'from_time' => '18:00:00',
            'to_date' => '2026-09-02', 'to_time' => '19:00:00',
        ]);

        $html = $this->renderAuditFor($order);

        $this->assertStringContainsString('18:00', $html, 'La hora de origen.');
        $this->assertStringContainsString('19:00', $html, 'La hora de destino.');
        $this->assertStringContainsString('2026', $html, 'Y el año, porque una reserva puede moverse de temporada.');
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    /**
     * T5 adenda 4 (`[DECIDIDO owner]`, `cumple-mixto.md` §25.10): el historial se abre A UN CLIC
     * DESDE EL DINERO — el bloque del pedido y la card del producto ganan su acceso además del CTA
     * del final de «Detalles». La foto no lista los cambios (los reescribe), así que un «Pendiente
     * de devolución» sin historia al lado obliga al operador a buscarla enterrada. El control de
     * abajo fija la otra mitad: un pedido SIN nada que explicar no gana ruido.
     * Mutación: quitar cualquiera de los dos accesos nuevos.
     */
    public function test_the_history_is_one_click_away_from_the_money(): void
    {
        $order = $this->makeOrder();
        $item = $order->items->first();
        $order->recordEdit($item, 1500, User::factory()->create(), 'item_edit', ['changes' => ['quantity_change' => ['old' => 7, 'new' => 8]]]);

        $html = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->html();

        // ⚠️ La aguja es `wire:click=...`, no la llamada a secas: UN solo botón de Filament
        // emite la cadena 4 veces (wire:click + wire:target del botón y de su spinner) y contar
        // llamadas convertía este recuento en ruido — el instrumento primero.
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($html, 'wire:click="mountAction(\'viewOrderHistory\')"'),
            'con dinero que explicar: el CTA de «Detalles» + el del bloque del pedido + el de la card',
        );
    }

    public function test_a_simple_order_keeps_a_single_history_entry_point(): void
    {
        // ⚠️ Fixture PROPIO y de verdad simple: el compartido (`makeOrder`) lleva un total que no
        // casa con su línea y ningún pago — eso deja «pendiente online», que SÍ es algo que
        // explicar, y el control nacería midiendo otra cosa.
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-R'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'subtotal' => 15120, 'total' => 15120, 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => 15120, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => '0000AUDIT1',
        ]);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => '2099-01-01',
            'start_time' => '18:00:00', 'end_time' => '19:30:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->product->id,
            'slot_id' => $slot->id, 'quantity' => 8, 'seats' => 8, 'unit_price' => 1890,
        ]);

        $html = Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->fresh()->code])
            ->html();

        $this->assertSame(
            1,
            substr_count($html, 'wire:click="mountAction(\'viewOrderHistory\')"'),
            'sin nada que explicar, solo el CTA de «Detalles»: el caso simple no gana ruido',
        );
    }

    private function makeOrder(): Order
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-R'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'subtotal' => 20220,
            'total' => 20220,
            'currency' => 'EUR',
        ]);
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => '2099-01-01',
            'start_time' => '18:00:00',
            'end_time' => '19:30:00',
            'capacity' => 10,
            'online_capacity' => 5,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $this->product->id,
            'slot_id' => $slot->id,
            'quantity' => 8,
            'seats' => 8,
            'unit_price' => 1890,
        ]);

        return $order->fresh();
    }
}
