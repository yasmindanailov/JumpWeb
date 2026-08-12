<?php

namespace Tests\Feature\Admin\Calendar;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CalendarPage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.4 — página del calendario unificado (`CalendarPage`) + la Action
 * read-only `viewCalendarItem`. Cubre el gate `calendar.view` y que el modal
 * resuelva el producto y muestre la referencia del pedido.
 */
class CalendarPageTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin/calendario';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function customer(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    private function packItem(): OrderItem
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'color' => '#FF5B22']);
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $zone->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
            'event_fields' => [['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado']]],
        ]);
        $date = Carbon::today()->addDays(3)->toDateString();
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => $date,
            'start_time' => '17:00:00', 'end_time' => '19:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        $customer = User::factory()->create([
            'name' => 'Ana Pérez', 'email' => 'ana.cliente@example.com', 'phone' => '+34600111222',
        ]);
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);
        $order = Order::create([
            'user_id' => $customer->id, 'code' => 'JJ-CAL777',
            'status' => Order::STATUS_PAID, 'subtotal' => 12000, 'tax' => 2520, 'total' => 14520,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 10, 'seats' => 10, 'unit_price' => 1200,
            'event_data' => ['celebrant' => 'Lucía'],
        ]);
    }

    // ─── Gate de acceso ───────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(self::URL)->assertRedirect('/admin/login');
    }

    public function test_customer_cannot_access(): void
    {
        $this->actingAs($this->customer())->get(self::URL)->assertForbidden();
        $this->assertFalse(CalendarPage::canAccess());
    }

    public function test_staff_without_calendar_view_gets_403(): void
    {
        $staff = $this->staff();
        $perm = Permission::where('name', 'calendar.view')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        $this->actingAs($staff)->get(self::URL)->assertForbidden();
    }

    public function test_staff_with_permission_can_access(): void
    {
        $this->actingAs($this->staff())->get(self::URL)->assertOk();
    }

    // ─── Action read-only `viewCalendarItem` ──────────────────────────────

    public function test_view_calendar_item_action_mounts_without_errors(): void
    {
        $item = $this->packItem();

        Livewire::actingAs($this->staff())
            ->test(CalendarPage::class)
            ->mountAction('viewCalendarItem', ['item' => $item->id])
            ->assertActionMounted('viewCalendarItem')
            ->assertHasNoActionErrors();
    }

    public function test_view_calendar_item_action_tolerates_missing_item(): void
    {
        Livewire::actingAs($this->staff())
            ->test(CalendarPage::class)
            ->mountAction('viewCalendarItem', ['item' => 999999])
            ->assertActionMounted('viewCalendarItem')
            ->assertHasNoActionErrors();
    }

    public function test_resolve_calendar_item_rejects_cancelled_item(): void
    {
        // #F9 (defensivo): un id forjado por Livewire (mountAction) de un item
        // CANCELADO no debe resolver en el modal — el modal queda alineado con el
        // feed (CalendarEventsController), que solo emite ids paidScheduledPrincipal.
        // Sin el scope se renderizaría a precio completo un item que el feed oculta.
        $item = $this->packItem();
        $item->update(['cancelled_at' => now()]);

        $component = Livewire::actingAs($this->staff())->test(CalendarPage::class)->instance();

        $resolve = new \ReflectionMethod(CalendarPage::class, 'resolveCalendarItem');
        $resolve->setAccessible(true);
        $this->assertNull($resolve->invoke($component, ['item' => $item->id]));

        // El view-data público también devuelve item=null (modal vacío, sin precio).
        $viewData = new \ReflectionMethod(CalendarPage::class, 'calendarItemViewData');
        $viewData->setAccessible(true);
        $this->assertNull($viewData->invoke($component, ['item' => $item->id])['item']);
    }

    public function test_resolve_calendar_item_resolves_a_valid_paid_principal(): void
    {
        // Control positivo: el camino legítimo (principal pagado con franja, no
        // cancelado) sigue resolviendo igual tras añadir el scope.
        $item = $this->packItem();

        $component = Livewire::actingAs($this->staff())->test(CalendarPage::class)->instance();
        $resolve = new \ReflectionMethod(CalendarPage::class, 'resolveCalendarItem');
        $resolve->setAccessible(true);

        $this->assertSame($item->id, $resolve->invoke($component, ['item' => $item->id])?->id);
    }

    public function test_item_detail_modal_view_shows_product_totals_and_order_cta(): void
    {
        $item = $this->packItem()->load(['ticketType.zone', 'slot', 'order.user', 'children.ticketType']);
        // 10 invitados × 12,00 € = 120,00 €.
        $principalCents = $item->quantity * $item->unit_price;

        $html = view('filament.admin.calendar.item-detail', [
            'item' => $item,
            'children' => collect(),
            'orderUrl' => '/admin/orders/JJ-CAL777',
            'principalCents' => $principalCents,
            'addonsCents' => 0,
            'totalCents' => $principalCents,
        ])->render();

        $this->assertStringContainsString('Cumpleaños Jump', $html);            // producto
        $this->assertStringContainsString('120,00', $html);                     // total del producto
        $this->assertStringContainsString('JJ-CAL777', $html);                  // referencia del pedido
        $this->assertStringContainsString('/admin/orders/JJ-CAL777', $html);    // CTA al pedido
        // L4 (2026-06-13): meta inline en el título = cantidad/invitados · fecha · horario.
        $this->assertStringContainsString(__('tickets.guests_count', ['count' => 10]), $html); // «10 invitados»
        $this->assertStringContainsString($item->displayTimeWindow(), $html);   // ventana horaria (17:00–19:00)
        // La duración se retiró del modal.
        $this->assertStringNotContainsString(__('admin.calendar.item_modal.field_duration'), $html);
        // Datos del cliente: SOLO nombre + teléfono (el email se quitó, decisión clienta).
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('+34600111222', $html);
        $this->assertStringContainsString('tel:+34600111222', $html);
        $this->assertStringNotContainsString('ana.cliente@example.com', $html);
        $this->assertStringNotContainsString('mailto:', $html);
        // Punto 6: el subtítulo de zona ("JUMP") se quitó (el nombre del producto
        // ya transmite el tipo). El producto es "Cumpleaños Jump" (minúsculas),
        // así que "JUMP" en mayúsculas solo provendría del subtítulo eliminado.
        $this->assertStringNotContainsString('JUMP', $html);
        // El badge de estado superior se quitó → el modal no renderiza ningún
        // badge de Filament (`fi-badge`). Aserción estructural, independiente del locale.
        $this->assertStringNotContainsString('fi-badge', $html);
    }

    /** Pack con post-form (#217) y datos por-niño: $filled filas rellenas de $quantity. */
    private function packWithGuestForm(int $quantity, int $filled): OrderItem
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'color' => '#FF5B22']);
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños con form'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $zone->id, 'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre del niño']]],
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '17:00:00', 'end_time' => '19:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);
        $customer = User::factory()->create(['name' => 'Ana Pérez', 'phone' => '+34600111222']);
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);
        $order = Order::create([
            'user_id' => $customer->id, 'code' => 'JJ-FORM9', 'status' => Order::STATUS_PAID,
            'subtotal' => 2000, 'tax' => 420, 'total' => 2420, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $names = ['Leo', 'Mar', 'Sol'];
        $guestData = [];
        for ($i = 0; $i < $filled; $i++) {
            $guestData[] = ['name' => $names[$i] ?? 'Niño'.$i];
        }

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => $quantity, 'seats' => $quantity, 'unit_price' => 1000,
            'guest_data' => $guestData,
        ])->load(['ticketType.zone', 'slot', 'order.user', 'children.ticketType']);
    }

    public function test_modal_shows_view_form_toggle_and_guest_data_when_complete(): void
    {
        // Detalle clienta (2026-06-13): post-form COMPLETO → el modal ofrece «Ver formulario» que
        // despliega los datos por-niño. El detalle vive en un x-show (sigue en el DOM) → el render
        // lo incluye y `assertStringContainsString` basta.
        $item = $this->packWithGuestForm(quantity: 2, filled: 2);
        $this->assertSame(OrderItem::GUEST_FORM_STATUS_OK, $item->guestFormStatus());

        $cents = $item->quantity * $item->unit_price;
        $html = view('filament.admin.calendar.item-detail', [
            'item' => $item, 'children' => collect(), 'orderUrl' => '/admin/orders/JJ-FORM9',
            'principalCents' => $cents, 'addonsCents' => 0, 'totalCents' => $cents,
        ])->render();

        $this->assertStringContainsString(__('admin.calendar.item_modal.guest_form_show'), $html); // toggle
        $this->assertStringContainsString('Nombre del niño', $html);                                // etiqueta
        $this->assertStringContainsString('Leo', $html);                                            // dato 1
        $this->assertStringContainsString('Mar', $html);                                            // dato 2
    }

    public function test_modal_hides_view_form_toggle_when_form_pending(): void
    {
        // Post-form PENDIENTE (faltan filas) → solo el aviso «!», sin «Ver formulario» ni datos.
        $item = $this->packWithGuestForm(quantity: 2, filled: 1);
        $this->assertSame(OrderItem::GUEST_FORM_STATUS_PENDING, $item->guestFormStatus());

        $cents = $item->quantity * $item->unit_price;
        $html = view('filament.admin.calendar.item-detail', [
            'item' => $item, 'children' => collect(), 'orderUrl' => '/admin/orders/JJ-FORM9',
            'principalCents' => $cents, 'addonsCents' => 0, 'totalCents' => $cents,
        ])->render();

        $this->assertStringContainsString(__('admin.calendar.guest_form_pending'), $html);
        $this->assertStringNotContainsString(__('admin.calendar.item_modal.guest_form_show'), $html);
    }

    // ─── Imprimir la reserva concreta desde el modal (hoja individual #183) ───

    public function test_item_detail_modal_renders_print_slip_link(): void
    {
        $item = $this->packItem()->load(['ticketType.zone', 'slot', 'order.user', 'children.ticketType']);
        $cents = $item->quantity * $item->unit_price;

        $html = view('filament.admin.calendar.item-detail', [
            'item' => $item, 'children' => collect(), 'orderUrl' => '/admin/orders/X',
            'canToggle' => true, 'principalCents' => $cents, 'addonsCents' => 0, 'totalCents' => $cents,
            'slipUrl' => '/admin/pedidos/JJ-X/items/9/imprimir',
        ])->render();

        $this->assertStringContainsString('/admin/pedidos/JJ-X/items/9/imprimir', $html);
        $this->assertStringContainsString('_blank', $html);                              // pestaña nueva
        $this->assertStringContainsString(__('admin.calendar.item_modal.print_slip'), $html);

        // Sin slipUrl (p. ej. sin permiso `orders.view`) → no aparece el botón.
        $htmlNoSlip = view('filament.admin.calendar.item-detail', [
            'item' => $item, 'children' => collect(), 'orderUrl' => '/admin/orders/X',
            'canToggle' => true, 'principalCents' => $cents, 'addonsCents' => 0, 'totalCents' => $cents,
            'slipUrl' => null,
        ])->render();
        $this->assertStringNotContainsString(__('admin.calendar.item_modal.print_slip'), $htmlNoSlip);
    }

    public function test_calendar_item_view_data_includes_slip_url_for_staff(): void
    {
        $staff = $this->staff();
        $item = $this->packItem();

        $component = Livewire::actingAs($staff)->test(CalendarPage::class)->instance();
        $method = new \ReflectionMethod(CalendarPage::class, 'calendarItemViewData');
        $method->setAccessible(true);
        $data = $method->invoke($component, ['item' => $item->id]);

        $this->assertSame(
            route('admin.orders.items.slip', ['order' => $item->order, 'item' => $item]),
            $data['slipUrl'],
        );
    }

    public function test_calendar_item_view_data_omits_slip_url_without_orders_view(): void
    {
        $staff = $this->staff();
        $staff->roles->first()->permissions()->detach(Permission::where('name', 'orders.view')->value('id'));
        $item = $this->packItem();

        $component = Livewire::actingAs($staff)->test(CalendarPage::class)->instance();
        $method = new \ReflectionMethod(CalendarPage::class, 'calendarItemViewData');
        $method->setAccessible(true);
        $data = $method->invoke($component, ['item' => $item->id]);

        $this->assertNull($data['slipUrl']);
    }

    public function test_can_access_helper_true_for_staff(): void
    {
        $this->actingAs($this->staff());
        $this->assertTrue(CalendarPage::canAccess());
    }

    public function test_modal_customer_name_links_to_user_page_when_url_present(): void
    {
        // #182: con `userUrl` (operador con `users.manage`), el nombre del cliente es un enlace.
        $item = $this->packItem()->load(['ticketType.zone', 'slot', 'order.user']);

        $html = view('filament.admin.calendar.item-detail', [
            'item' => $item,
            'children' => collect(),
            'orderUrl' => '/admin/orders/'.$item->order->code,
            'userUrl' => '/admin/users/'.$item->order->user->id,
            'canToggle' => false,
            'principalCents' => $item->quantity * $item->unit_price,
            'addonsCents' => 0,
            'totalCents' => $item->quantity * $item->unit_price,
        ])->render();

        $this->assertStringContainsString('href="/admin/users/'.$item->order->user->id.'"', $html);
    }

    public function test_modal_customer_name_is_plain_text_without_url(): void
    {
        // Sin `userUrl` (operador sin `users.manage`): el nombre se muestra como texto plano.
        $item = $this->packItem()->load(['ticketType.zone', 'slot', 'order.user']);

        $html = view('filament.admin.calendar.item-detail', [
            'item' => $item,
            'children' => collect(),
            'orderUrl' => '/admin/orders/'.$item->order->code,
            'userUrl' => null,
            'canToggle' => false,
            'principalCents' => $item->quantity * $item->unit_price,
            'addonsCents' => 0,
            'totalCents' => $item->quantity * $item->unit_price,
        ])->render();

        $this->assertStringNotContainsString('href="/admin/users/', $html);
        $this->assertStringContainsString($item->order->user->name, $html); // el nombre sí aparece
    }
}
