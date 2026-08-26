<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderItemEventDataWriter;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Fase 7.2c (decisión #147ter) — Action Filament `manageItem` con tabs
 * nativas + form de `event_data` + handler con defense in depth.
 *
 * Migrado desde patrón HTTP POST tradicional (#147bis) a Filament Action
 * invocada por `mountAction('manageItem', { item: ID })` desde un botón
 * en `items-list.blade.php`. Los tests usan `Livewire::test()` con
 * `callAction()` para simular el flujo completo mount → submit.
 */
class OrderItemUpdateEventDataTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump1h;

    private TicketType $pack;

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
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);

        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump 1h'],
            'zone_id' => $this->zone->id,
            'duration_min' => 60,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 1,
        ]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños XL'],
            'zone_id' => $this->zone->id,
            'type' => TicketType::TYPE_PACK,
            'is_sellable' => true,
            'is_active' => true,
            'duration_min' => 90,
            'seats_per_unit' => 1,
            'min_qty' => 8,
            'max_qty' => 20,
            'position' => 1,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado/a']],
                ['key' => 'age', 'type' => 'number', 'required' => true, 'label' => ['es' => 'Edad']],
                ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'label' => ['es' => 'Notas']],
            ],
        ]);
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

    private int $slotCounter = 0;

    private function makeSlot(string $date = '2099-01-01'): Slot
    {
        $h = str_pad((string) ($this->slotCounter++ % 23), 2, '0', STR_PAD_LEFT);

        return Slot::create([
            'zone_id' => $this->zone->id,
            'date' => $date,
            'start_time' => "{$h}:00:00",
            'end_time' => "{$h}:59:00",
            'capacity' => 20,
            'online_capacity' => 12,
        ]);
    }

    /**
     * @param  array<string,string>  $eventData
     * @return array{0:Order,1:OrderItem}
     */
    private function makePackOrder(array $eventData = ['celebrant' => 'Mateo', 'age' => '8']): array
    {
        $user = User::factory()->create(['name' => 'Cliente Test', 'phone' => '666555444']);
        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-T'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'subtotal' => 10000,
            'total' => 10000,
            'currency' => 'EUR',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'parent_item_id' => null,
            'ticket_type_id' => $this->pack->id,
            'slot_id' => $this->makeSlot()->id,
            'quantity' => 10,
            'seats' => 10,
            'unit_price' => 1000,
            'event_data' => $eventData,
        ]);

        return [$order, $item];
    }

    /**
     * @return array{0:Order,1:OrderItem}
     */
    private function makeEntryOrder(): array
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-E'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'EUR',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'parent_item_id' => null,
            'ticket_type_id' => $this->jump1h->id,
            'slot_id' => $this->makeSlot()->id,
            'quantity' => 1,
            'seats' => 1,
            'unit_price' => 1000,
        ]);

        return [$order, $item];
    }

    /**
     * Datos válidos del form que el modal envía al action handler. El
     * `optimistic_token` se computa desde el `updated_at` del item en cada
     * test (igual que el fillForm).
     *
     * @param  array<string,string>  $eventData
     * @return array{optimistic_token:string,event_data:array<string,string>}
     */
    private function formData(OrderItem $item, array $eventData): array
    {
        return [
            'optimistic_token' => (string) ($item->updated_at?->getTimestamp() ?? ''),
            'event_data' => $eventData,
        ];
    }

    // ─── Permiso sembrado correctamente ───────────────────────────────────

    public function test_staff_default_permissions_include_edit_event_data(): void
    {
        $staff = $this->staff();
        $this->assertTrue($staff->hasPermission('orders.edit_event_data'));
        $this->assertTrue(
            Permission::where('name', 'orders.edit_event_data')->exists(),
            'El permiso debe existir tras el seeding.',
        );
    }

    // ─── Acceso al panel ──────────────────────────────────────────────────

    public function test_customer_cannot_access_view_page(): void
    {
        [$order] = $this->makePackOrder();

        $this->actingAs($this->customer())
            ->get('/admin/orders/'.$order->code)
            ->assertForbidden();
    }

    public function test_view_renders_open_button_with_mount_action_per_item(): void
    {
        [$order, $item] = $this->makePackOrder();

        $response = $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code);

        $response->assertOk();
        // El HTML del botón lleva `wire:click="mountAction('manageItem', ...)"`
        // con el JSON de los argumentos. Las comillas pueden venir HTML-encoded
        // (`&quot;`) o single-quoted según el atributo; en lugar de fijar el
        // encoding, verificamos los componentes invariantes. Los tests de
        // `callAction` cubren la correcta resolución del item ID en el handler.
        $response->assertSee('mountAction', false);
        $response->assertSee('manageItem', false);
        $response->assertSee(__('admin.orders.item_detail.btn_open'), false);  // #171: "Gestionar"
    }

    public function test_admin_card_renders_event_data_in_schema_order(): void
    {
        // #173: los datos del evento de la card admin se ordenan por el ESQUEMA
        // del pack (celebrant → age → notes), NO por el orden de guardado. Se
        // guardan en orden INVERSO al esquema y aun así se muestran ordenados.
        [$order] = $this->makePackOrder([
            'notes' => 'Sin gluten',
            'age' => '9',
            'celebrant' => 'Lucía',
        ]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSeeInOrder(['Homenajeado/a', 'Edad', 'Notas']);
    }

    // ─── Save válido ──────────────────────────────────────────────────────

    public function test_call_action_saves_event_data_and_writes_diff_audit_log(): void
    {
        [$order, $item] = $this->makePackOrder();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: $this->formData($item, [
                    'celebrant' => 'Mateo Pérez',  // changed
                    'age' => '8',                  // unchanged
                    'notes' => 'sin lactosa',      // added
                ]),
                arguments: ['item' => $item->id],
            );

        $this->assertSame([
            'celebrant' => 'Mateo Pérez',
            'age' => '8',
            'notes' => 'sin lactosa',
        ], $item->fresh()->event_data);

        $log = AuditLog::where('action', 'order_items.event_data_updated')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($item->id, $log->target_id);
        // P2 (auditoría Fase 1 · RGPD art.9): el audit guarda SOLO las CLAVES cambiadas, NUNCA los
        // valores — `event_data` lleva el nombre del homenajeado (PII de menor) y el payload del audit
        // es para acciones sin dato personal (su contrato).
        $this->assertSame(['celebrant'], $log->payload['diff']['changed']);   // clave, no [old, new]
        $this->assertNotContains('age', $log->payload['diff']['changed']);    // sin cambios → no listado
        $this->assertSame(['notes'], $log->payload['diff']['added']);         // clave, no valor
        $this->assertSame([], $log->payload['diff']['removed']);
        $this->assertStringNotContainsString('Mateo Pérez', json_encode($log->payload)); // PII ausente
    }

    public function test_call_action_removes_key_when_value_is_empty(): void
    {
        [$order, $item] = $this->makePackOrder(['celebrant' => 'Mateo', 'age' => '8', 'notes' => 'antiguo']);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: $this->formData($item, [
                    'celebrant' => 'Mateo',
                    'age' => '8',
                    'notes' => '',  // sanitize descarta vacíos
                ]),
                arguments: ['item' => $item->id],
            );

        $fresh = $item->fresh()->event_data;
        $this->assertArrayNotHasKey('notes', $fresh);

        $log = AuditLog::where('action', 'order_items.event_data_updated')->latest()->first();
        $this->assertSame(['notes'], $log->payload['diff']['removed']);          // clave, no el valor
        $this->assertStringNotContainsString('antiguo', json_encode($log->payload)); // PII ausente
    }

    public function test_call_action_with_no_changes_writes_no_audit_log(): void
    {
        [$order, $item] = $this->makePackOrder();
        AuditLog::query()->delete();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: $this->formData($item, ['celebrant' => 'Mateo', 'age' => '8']),
                arguments: ['item' => $item->id],
            );

        $this->assertSame(0, AuditLog::where('action', 'order_items.event_data_updated')->count());
    }

    // ─── Paste-defense (sanitize drop) ────────────────────────────────────

    public function test_call_action_drops_extra_keys_outside_schema(): void
    {
        [$order, $item] = $this->makePackOrder();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: $this->formData($item, [
                    'celebrant' => 'Lucía',
                    'age' => '9',
                    'notes' => 'alérgico',
                    'junk_field' => 'evil paste',
                    'admin_only_secret' => 'should not save',
                ]),
                arguments: ['item' => $item->id],
            );

        $fresh = $item->fresh()->event_data;
        $this->assertSame([
            'celebrant' => 'Lucía',
            'age' => '9',
            'notes' => 'alérgico',
        ], $fresh);
    }

    // ─── Preservación de claves legacy ────────────────────────────────────

    public function test_call_action_preserves_legacy_keys_outside_current_schema(): void
    {
        [$order, $item] = $this->makePackOrder([
            'celebrant' => 'Mateo',
            'age' => '8',
            'legacy_color' => 'rojo',
        ]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: $this->formData($item, [
                    'celebrant' => 'Mateo Pérez',
                    'age' => '8',
                ]),
                arguments: ['item' => $item->id],
            );

        $fresh = $item->fresh()->event_data;
        $this->assertSame('Mateo Pérez', $fresh['celebrant']);
        $this->assertSame('rojo', $fresh['legacy_color'], 'La clave legacy debe preservarse.');
    }

    // ─── Optimistic lock ──────────────────────────────────────────────────

    public function test_call_action_with_stale_optimistic_token_does_not_modify_and_logs_blocked(): void
    {
        [$order, $item] = $this->makePackOrder();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: [
                    'optimistic_token' => '0',  // stale
                    'event_data' => ['celebrant' => 'Lucía', 'age' => '9'],
                ],
                arguments: ['item' => $item->id],
            );

        $this->assertSame(['celebrant' => 'Mateo', 'age' => '8'], $item->fresh()->event_data);

        $log = AuditLog::where('action', 'order_items.event_data_blocked')->latest()->first();
        $this->assertSame('stale_version', $log->payload['reason']);
    }

    public function test_call_action_with_empty_optimistic_token_blocks(): void
    {
        [$order, $item] = $this->makePackOrder();

        // `fillForm()` rellena `optimistic_token` antes del `callAction`. Para
        // simular un usuario malicioso que vacía el token en DevTools, lo
        // sobrescribimos explícitamente a cadena vacía en `data`.
        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: [
                    'optimistic_token' => '',
                    'event_data' => ['celebrant' => 'Lucía', 'age' => '9'],
                ],
                arguments: ['item' => $item->id],
            );

        $this->assertSame(['celebrant' => 'Mateo', 'age' => '8'], $item->fresh()->event_data);

        $log = AuditLog::where('action', 'order_items.event_data_blocked')->latest()->first();
        $this->assertSame('stale_version', $log->payload['reason']);
    }

    // ─── Required missing ─────────────────────────────────────────────────

    public function test_call_action_with_required_missing_does_not_modify_and_logs_blocked(): void
    {
        [$order, $item] = $this->makePackOrder();

        // Filament `->required()` valida en cliente; el handler revalida con
        // `missingRequiredEventFields()` como defense-in-depth si alguien
        // envía el form vaciando los requireds (p. ej. via DevTools). Para
        // simular eso, mandamos celebrant y age explícitamente vacíos.
        try {
            Livewire::actingAs($this->staff())
                ->test(ViewOrder::class, ['record' => $order->code])
                ->callAction(
                    'manageItem',
                    data: $this->formData($item, [
                        'celebrant' => '',
                        'age' => '',
                        'notes' => 'solo notas',
                    ]),
                    arguments: ['item' => $item->id],
                );
        } catch (\Throwable) {
            // Filament puede lanzar excepción de validación cliente antes de
            // llegar al handler — coherente con `->required()` en el form. La
            // semántica del test queda en BD + audit (uno u otro debe garantizar
            // que NO se modifica).
        }

        $this->assertSame(['celebrant' => 'Mateo', 'age' => '8'], $item->fresh()->event_data);
    }

    // ─── El servicio, DIRECTO (extracción 4b): las reglas que la página no alcanza ──────

    /**
     * La validación `required()` de Filament rechaza el formulario ANTES de que el handler vea los
     * obligatorios vacíos (el test de arriba lo mide con `try/catch`), así que la guarda del
     * dominio —la defensa para un cliente que manipule el envío— solo se ejercita llamando al
     * servicio. La mutación «sin obligatorios» salía VERDE en toda la carpeta por eso.
     */
    public function test_writer_blocks_required_missing_without_touching_the_item(): void
    {
        [$order, $item] = $this->makePackOrder();
        AuditLog::query()->delete();

        $outcome = app(OrderItemEventDataWriter::class)->save(
            $order,
            $item,
            ['celebrant' => '', 'age' => '', 'notes' => 'solo notas'],
            (string) $item->updated_at->getTimestamp(),
            $this->staff(),
        );

        $this->assertTrue($outcome->isBlocked());
        $this->assertSame('required_missing', $outcome->reason);
        $this->assertSame(['celebrant', 'age'], $outcome->extra['missing_keys']);
        $this->assertSame(['celebrant' => 'Mateo', 'age' => '8'], $item->fresh()->event_data);
        $this->assertSame(0, AuditLog::where('action', 'order_items.event_data_updated')->count());
    }

    /**
     * `SEC-04`: el permiso se re-exige en el punto de ejecución, sea quien sea el llamante. Desde
     * la página es inalcanzable (el despachador ya filtra por permiso antes de llamar): solo se
     * mide aquí, con un cliente y con nadie autenticado.
     */
    public function test_writer_requires_the_permission_at_execution_time(): void
    {
        [$order, $item] = $this->makePackOrder();
        $token = (string) $item->updated_at->getTimestamp();
        $writer = app(OrderItemEventDataWriter::class);

        $asCustomer = $writer->save($order, $item, ['celebrant' => 'Lucía', 'age' => '9'], $token, $this->customer());
        $anonymous = $writer->save($order, $item, ['celebrant' => 'Lucía', 'age' => '9'], $token, null);

        $this->assertSame('permission_denied', $asCustomer->reason);
        $this->assertSame('permission_denied', $anonymous->reason);
        $this->assertSame(['celebrant' => 'Mateo', 'age' => '8'], $item->fresh()->event_data);
    }

    /**
     * «Sin cambios» es un resultado con nombre: ni `save` (el `updated_at` no se mueve, y con él
     * el optimistic token del siguiente envío), ni audit; la página lo traduce a su aviso.
     */
    public function test_writer_reports_unchanged_when_nothing_differs(): void
    {
        [$order, $item] = $this->makePackOrder();
        AuditLog::query()->delete();
        $before = $item->fresh()->updated_at;
        $this->travel(1)->minutes();

        $outcome = app(OrderItemEventDataWriter::class)->save(
            $order,
            $item,
            ['celebrant' => 'Mateo', 'age' => '8'],
            (string) $item->updated_at->getTimestamp(),
            $this->staff(),
        );

        $this->assertFalse($outcome->isBlocked());
        $this->assertFalse($outcome->changed);
        $this->assertTrue($before->equalTo($item->fresh()->updated_at));
        $this->assertSame(0, AuditLog::where('action', 'order_items.event_data_updated')->count());
    }

    // ─── Semántica: item no-pack o pack sin event_fields ─────────────────

    public function test_call_action_when_item_is_not_a_pack_logs_blocked(): void
    {
        [$order, $item] = $this->makeEntryOrder();

        // El callAction puede fallar antes del handler porque el schema no
        // expone form fields para items no-pack. Probamos defensivamente
        // el handler con un POST forzado vía `callAction` con `data` que
        // incluye `optimistic_token`.
        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: $this->formData($item, ['anything' => 'x']),
                arguments: ['item' => $item->id],
            );

        $log = AuditLog::where('action', 'order_items.event_data_blocked')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('not_pack', $log->payload['reason']);
    }

    public function test_call_action_when_pack_has_no_event_fields_logs_blocked(): void
    {
        [$order, $item] = $this->makePackOrder();
        $this->pack->update(['event_fields' => []]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction(
                'manageItem',
                data: $this->formData($item, ['anything' => 'x']),
                arguments: ['item' => $item->id],
            );

        $log = AuditLog::where('action', 'order_items.event_data_blocked')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('no_event_fields', $log->payload['reason']);
    }

    // ─── Defensa IDOR ─────────────────────────────────────────────────────

    public function test_call_action_when_item_belongs_to_other_order_does_not_modify(): void
    {
        [$orderA, $itemA] = $this->makePackOrder();
        [$orderB] = $this->makePackOrder();

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $orderB->code])
            ->callAction(
                'manageItem',
                data: $this->formData($itemA, ['celebrant' => 'X', 'age' => '7']),
                arguments: ['item' => $itemA->id],
            );

        $this->assertSame(['celebrant' => 'Mateo', 'age' => '8'], $itemA->fresh()->event_data);
    }

    // ─── Permiso de edición ───────────────────────────────────────────────

    public function test_call_action_without_edit_permission_aborts_403(): void
    {
        [$order, $item] = $this->makePackOrder();

        $staff = $this->staff();
        $perm = Permission::where('name', 'orders.edit_event_data')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        try {
            Livewire::actingAs($staff)
                ->test(ViewOrder::class, ['record' => $order->code])
                ->callAction(
                    'manageItem',
                    data: $this->formData($item, ['celebrant' => 'X', 'age' => '7']),
                    arguments: ['item' => $item->id],
                );
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // BD no modificada.
        $this->assertSame(['celebrant' => 'Mateo', 'age' => '8'], $item->fresh()->event_data);
    }

    // Los tests del snapshot del audit por OrderItem (#147ter) se eliminaron
    // en 7.2d (decisión #151): la tab "Historial" del modal del item se
    // retiró por redundancia con el audit agregado del Order. El nuevo
    // cubrimiento de la query agregada (`target_type=Order` OR
    // `target_type=OrderItem IN items`) vive en `OrderAuditModalTest`.
}
