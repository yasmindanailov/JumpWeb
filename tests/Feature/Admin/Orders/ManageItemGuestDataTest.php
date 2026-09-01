<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Contracts\ItemActionOutcome;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AgeFamilySealer;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Booking\Services\OrderItemGuestDataWriter;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\MixedPartySurchargeChanged;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T3 · F y D7 — el PARQUE decide (`docs/specs/cumple-mixto.md` §23.3 y §23.4).
 *
 * **F**: el operador corrige las edades desde el panel por la MISMA puerta que el cliente
 * (`OrderItemGuestDataWriter` → `OrderItem::submitGuestForm`), con rastro PROPIO: `via: panel`,
 * el operador de actor en la reconciliación y el correo con la voz de «el parque». Medido antes
 * de la T3: `guest_data` tenía UN escritor y el operador solo podía abrir el enlace del cliente —
 * el rastro decía que lo hizo el cliente.
 *
 * **D7**: bajar del mínimo del pack exige un permiso propio y un interruptor explícito, y queda
 * AUDITADO (`below_pack_minimum`). «Al final él decide sobre su producto» — y la excepción se
 * registra, no se silencia.
 */
class ManageItemGuestDataTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private Slot $slot;

    private TicketType $kids;

    private TicketType $jump;

    private int $counter = 0;

    private const DAY = '2026-07-15';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(self::DAY.' 08:00:00');
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
        $this->zone = Zone::create(['slug' => 'cumples', 'name' => ['es' => 'Cumpleaños']]);
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => self::DAY,
            'start_time' => '11:00:00', 'end_time' => '13:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);

        $this->kids = $this->pack('Cumpleaños Kids', 1, 6, 1800, $rate, min: 5);
        $this->jump = $this->pack('Cumpleaños Jump', 7, 99, 2500, $rate, min: 5);
    }

    private function pack(string $name, int $ageMin, int $ageMax, int $cents, RateType $rate, int $min = 1): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1,
            'min_qty' => $min, 'max_qty' => 30, 'is_sellable' => true, 'is_active' => true,
            'duration_min' => 120,
            'position' => (int) TicketType::max('position') + 1,
            'guest_fields' => [
                ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => true, 'label' => ['es' => 'Edad']],
            ],
            // Un campo del POST-FORM para la guarda F: el panel edita fichas y NO puede llevarse
            // por delante lo que el cliente contestó en los campos generales.
            'event_fields' => [
                ['key' => 'nota', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Nota']],
            ],
            'guest_age_family' => 'cumple', 'guest_age_min' => $ageMin, 'guest_age_max' => $ageMax,
        ]);
        Price::create([
            'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
            'rate_type_id' => $rate->id, 'amount_cents' => $cents, 'currency' => 'EUR',
        ]);

        return $pack;
    }

    /** Una fiesta Kids PAGADA y SELLADA con las edades declaradas por el CLIENTE. @param list<int|null> $ages */
    private function party(array $ages): OrderItem
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-GD'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'subtotal' => 1800 * count($ages), 'total' => 1800 * count($ages), 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (500000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $this->kids->id, 'slot_id' => $this->slot->id,
            'quantity' => count($ages), 'unit_price' => 1800, 'seats' => count($ages),
        ]);
        app(AgeFamilySealer::class)->seal($item, $this->kids, $this->slot->date);

        $item->fresh(['ticketType', 'slot', 'order'])->submitGuestForm($this->rows($ages), ['nota' => 'globos'], 'signed_link');

        return $item->fresh(['ticketType', 'slot', 'order']);
    }

    /** @param  list<int|null>  $ages
     * @return list<array<string,string>> */
    private function rows(array $ages): array
    {
        $rows = [];
        foreach ($ages as $i => $age) {
            $row = ['name' => 'Invitado '.($i + 1)];
            if ($age !== null) {
                $row['edad'] = (string) $age;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * ⚠️ Cada empleado con su PROPIO rol: dos usuarios sobre el rol `staff` compartido harían que
     * el `sync()` del segundo le quitara los permisos al primero — y el caso de D7 necesita a los
     * dos vivos a la vez (con y sin el permiso de bajar del mínimo).
     *
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions): User
    {
        $role = Role::create(['name' => 'staff-t3-'.(++$this->counter), 'label' => 'Staff T3']);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        $staff = User::factory()->create();
        $staff->roles()->sync([$role->id]);

        return $staff;
    }

    private function writer(): OrderItemGuestDataWriter
    {
        return app(OrderItemGuestDataWriter::class);
    }

    private function token(OrderItem $item): string
    {
        return (string) $item->fresh()->updated_at->getTimestamp();
    }

    /** El suplemento ESCRITO (céntimos), leído de las líneas marcadas — no del veredicto. */
    private function writtenCents(OrderItem $item): int
    {
        return (int) OrderAdjustment::query()
            ->where('order_id', $item->order_id)
            ->where('type', OrderAdjustment::TYPE_MIXED)
            ->get()
            ->filter(fn (OrderAdjustment $a): bool => is_array($a->context) && isset($a->context['mixed_party']))
            ->filter(fn (OrderAdjustment $a): bool => ! OrderItem::find($a->order_item_id)?->isCancelled())
            ->sum('amount_cents');
    }

    // ─── C · La puerta única, con el rastro del operador ─────────────────────────

    public function test_the_operator_corrects_an_age_with_their_own_trail(): void
    {
        $item = $this->party([4, 5, 6, 4, 5]);
        $this->assertSame(0, $this->writtenCents($item), 'la fiesta nace sin mezcla');

        $staff = $this->staff(['orders.view', 'orders.edit_guest_data']);
        $this->actingAs($staff);
        AuditLog::query()->delete();
        Notification::fake();

        // El operador corrige la edad del tercero: 6 → 8 (pasa al tramo de Jump).
        $rows = $this->rows([4, 5, 8, 4, 5]);
        $outcome = $this->writer()->save($item->order, $item, $rows, $this->token($item), $staff);

        $this->assertFalse($outcome->isBlocked(), (string) $outcome->reason);
        $this->assertTrue($outcome->changed);
        $this->assertSame('8', $item->fresh()->guestData()[2]['edad']);

        // El suplemento se reconcilió con el OPERADOR de actor y la razón del panel.
        $this->assertSame(700, $this->writtenCents($item));
        $adjustment = OrderAdjustment::query()
            ->where('order_id', $item->order_id)
            ->get()
            ->first(fn (OrderAdjustment $a): bool => is_array($a->context) && isset($a->context['mixed_party']));
        $this->assertNotNull($adjustment);
        $this->assertSame($staff->id, (int) $adjustment->applied_by, 'el ajuste lleva al operador, no al titular');

        // El audit del guardado dice POR DÓNDE entró (`via: panel`) y quién fue (el actor de la
        // petición), sin PII (`RGPD-02`).
        $submitted = AuditLog::where('action', 'orders.guest_form_submitted')->get();
        $this->assertCount(1, $submitted);
        $this->assertSame('panel', $submitted->first()->payload['via']);
        $this->assertSame($staff->id, (int) $submitted->first()->user_id);
        $synced = AuditLog::where('action', 'orders.mixed_party_surcharge_synced')->first();
        $this->assertNotNull($synced);
        $this->assertSame('panel_guest_form', $synced->payload['reason']);

        // Y el correo al cliente habla con la voz del PARQUE, no con la suya.
        Notification::assertSentTo(
            $item->order->user,
            MixedPartySurchargeChanged::class,
            fn (MixedPartySurchargeChanged $n): bool => $n->byCustomer === false,
        );
    }

    // ─── D · Sin permiso, nada ───────────────────────────────────────────────────

    public function test_without_the_permission_nothing_is_written(): void
    {
        $item = $this->party([4, 5, 6, 4, 5]);
        $before = $item->fresh()->guestData();

        $staff = $this->staff(['orders.view', 'orders.edit_item']); // sin `orders.edit_guest_data`
        AuditLog::query()->delete();

        $outcome = $this->writer()->save($item->order, $item, $this->rows([4, 5, 8, 4, 5]), $this->token($item), $staff);

        $this->assertTrue($outcome->isBlocked());
        $this->assertSame('permission_denied', $outcome->reason);
        $this->assertSame($before, $item->fresh()->guestData());
        $this->assertSame(0, AuditLog::where('action', 'orders.guest_form_submitted')->count());
    }

    // ─── E · Token rancio y «sin cambios» ────────────────────────────────────────

    public function test_a_stale_token_is_rejected_and_identical_rows_leave_no_trace(): void
    {
        $item = $this->party([4, 5, 6, 4, 5]);
        $staff = $this->staff(['orders.view', 'orders.edit_guest_data']);
        $this->actingAs($staff);

        $stale = $this->writer()->save($item->order, $item, $this->rows([4, 5, 8, 4, 5]), 'rancio', $staff);
        $this->assertTrue($stale->isBlocked());
        $this->assertSame('stale_version', $stale->reason);

        // Fichas idénticas a las guardadas → `unchanged` y NI UNA fila de audit: no hubo guardado,
        // no hubo reconciliación, no hubo correo.
        AuditLog::query()->delete();
        Notification::fake();
        $unchanged = $this->writer()->save($item->order, $item, $this->rows([4, 5, 6, 4, 5]), $this->token($item), $staff);

        $this->assertFalse($unchanged->isBlocked());
        $this->assertFalse($unchanged->changed);
        $this->assertSame(0, AuditLog::count());
        Notification::assertNothingSent();
    }

    // ─── F · Los datos generales del post-form se CONSERVAN ──────────────────────

    public function test_the_general_postform_answers_survive_a_panel_edit(): void
    {
        $item = $this->party([4, 5, 6, 4, 5]);
        $this->assertSame('globos', $item->fresh()->event_data['nota'], 'el cliente contestó el campo general');

        $staff = $this->staff(['orders.view', 'orders.edit_guest_data']);
        $this->actingAs($staff);
        $outcome = $this->writer()->save($item->order, $item, $this->rows([4, 5, 8, 4, 5]), $this->token($item), $staff);

        $this->assertTrue($outcome->changed);
        // El panel edita FICHAS: lo que el cliente contestó en los campos generales sigue ahí.
        // (La mutación que esto caza: pasar `[]` en vez de `null` a `submitGuestForm`, que
        // vaciaría los campos del post-form en silencio.)
        $this->assertSame('globos', $item->fresh()->event_data['nota']);
    }

    // ─── G · D7: bajar del mínimo, con permiso y rastro ──────────────────────────

    public function test_below_the_minimum_needs_the_switch_and_the_permission_and_is_audited(): void
    {
        $canBoth = $this->staff(['orders.view', 'orders.edit_item', 'orders.edit_item_below_minimum']);
        $editOnly = $this->staff(['orders.view', 'orders.edit_item']);

        // Sin interruptor → el mínimo manda, como siempre.
        $item = $this->party([4, 5, 6, 4, 5]);
        $blocked = $this->edit($item, qty: 2, by: $canBoth, belowMinimum: false);
        $this->assertSame('pack_quantity_range', $blocked->reason);

        // Con interruptor pero SIN permiso → el mínimo manda igual (`SEC-04`: el permiso se
        // re-exige en el punto de ejecución; un payload fabricado no compra nada).
        $blocked = $this->edit($item, qty: 2, by: $editOnly, belowMinimum: true);
        $this->assertSame('pack_quantity_range', $blocked->reason);

        // Con las dos cosas → guardado, y la EXCEPCIÓN queda en el audit con el mínimo del pack.
        AuditLog::query()->delete();
        $done = $this->edit($item, qty: 2, by: $canBoth, belowMinimum: true);
        $this->assertFalse($done->isBlocked(), (string) $done->reason);
        $this->assertSame(2, (int) $item->fresh()->quantity);

        $audit = AuditLog::where('action', 'orders.item_edited')->first();
        $this->assertNotNull($audit);
        $this->assertTrue($audit->payload['below_pack_minimum']);
        $this->assertSame(5, (int) $audit->payload['pack_min_qty']);

        // Control: una edición NORMAL no lleva la marca — un `false` en cada edición sería ruido
        // que entierra la señal.
        AuditLog::query()->delete();
        $this->edit($item->fresh(['ticketType', 'slot']), qty: 6, by: $canBoth, belowMinimum: false);
        $normal = AuditLog::where('action', 'orders.item_edited')->first();
        $this->assertNotNull($normal);
        $this->assertArrayNotHasKey('below_pack_minimum', $normal->payload);
    }

    public function test_the_switch_does_not_open_the_ceiling_nor_zero(): void
    {
        $staff = $this->staff(['orders.view', 'orders.edit_item', 'orders.edit_item_below_minimum']);
        $item = $this->party([4, 5, 6, 4, 5]);

        // `>= 1` sigue: el interruptor rebaja el mínimo del PACK, no abre el cero.
        $this->assertSame('invalid_quantity', $this->edit($item, qty: 0, by: $staff, belowMinimum: true)->reason);
        // Y el MÁXIMO sigue mandando.
        $this->assertSame('pack_quantity_range', $this->edit($item, qty: 31, by: $staff, belowMinimum: true)->reason);
    }

    // ─── H · Tras bajar del mínimo, la regla del dinero es la de §22 ─────────────

    public function test_after_going_below_minimum_the_money_follows_the_complete_rule(): void
    {
        $staff = $this->staff(['orders.view', 'orders.edit_item', 'orders.edit_item_below_minimum']);

        // CONGELA: 14,00 € escritos; el cliente deja una edad en blanco (no mueve nada); el
        // operador baja a 4 invitados y la ficha en blanco sigue dentro → NADA se mueve, en
        // ninguna dirección (§22.3, también en los disparos del panel).
        $item = $this->party([8, 4, 9, 4, 4, 4]);
        $this->assertSame(1400, $this->writtenCents($item));
        $item->fresh(['ticketType', 'slot', 'order'])->submitGuestForm($this->rows([8, 4, null, 4, 4, 4]), null, 'signed_link');
        $this->assertSame(1400, $this->writtenCents($item), 'vaciar no borra el cargo (#268)');

        $done = $this->edit($item->fresh(['ticketType', 'slot']), qty: 4, by: $staff, belowMinimum: true);
        $this->assertFalse($done->isBlocked(), (string) $done->reason);
        $this->assertSame(1400, $this->writtenCents($item), 'con una edad en blanco, congelado');

        // RECALCULA: el cliente (o el operador, T3·F) completa las 4 edades → el suplemento sigue
        // al hecho: queda solo el invitado de 8.
        $item->fresh(['ticketType', 'slot', 'order'])->submitGuestForm($this->rows([8, 4, 5, 4]), null, 'signed_link');
        $this->assertSame(700, $this->writtenCents($item), 'completo recalcula');
    }

    // ─── I · El modal, por Livewire ──────────────────────────────────────────────

    /**
     * ⚠️ La PRESENCIA de la pestaña no se asevera con `assertSee`: el modal de Filament es un
     * `wire:partial` y su HTML no llega al arnés (la trampa medida en `waiver-probatorio.md`
     * §9.7). Se asevera por CONDUCTA, que es más fuerte: el formulario solo dehidrata los campos
     * DECLARADOS en el esquema, así que las fichas fluyen hasta el guardado exactamente cuando la
     * pestaña existe — y no fluyen cuando no.
     */
    public function test_the_guests_tab_saves_through_the_modal(): void
    {
        $item = $this->party([4, 5, 6, 4, 5]);
        $staff = $this->staff(['orders.view', 'orders.edit_guest_data']);

        $rows = [];
        foreach ($this->rows([4, 5, 8, 4, 5]) as $i => $row) {
            $rows[] = ['idx' => $i] + $row;
        }

        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $item->order->code])
            ->mountAction('manageItem', arguments: ['item' => $item->id])
            ->setActionData(['guest_data' => $rows])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('8', $item->fresh()->guestData()[2]['edad'], 'guardar desde la pestaña escribe');
        $this->assertSame(700, $this->writtenCents($item), 'y el suplemento siguió a la edad');
    }

    public function test_without_the_permission_the_tab_does_not_exist_and_a_forged_payload_writes_nothing(): void
    {
        $item = $this->party([4, 5, 6, 4, 5]);
        $before = $item->fresh()->guestData();
        // Con `edit_event_data` para que el guardado sin cambios siga su camino preexistente; lo
        // que NO tiene es `edit_guest_data`, que es lo que este caso prueba.
        $staff = $this->staff(['orders.view', 'orders.edit_event_data']);
        AuditLog::query()->delete();

        $rows = [];
        foreach ($this->rows([4, 5, 8, 4, 5]) as $i => $row) {
            $rows[] = ['idx' => $i] + $row;
        }

        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $item->order->code])
            ->mountAction('manageItem', arguments: ['item' => $item->id])
            ->setActionData(['guest_data' => $rows])
            ->callMountedAction();

        $this->assertSame($before, $item->fresh()->guestData(), 'sin permiso, un payload fabricado no escribe nada');
        $this->assertSame(0, AuditLog::where('action', 'orders.guest_form_submitted')->count());
    }

    // ─── El despacho del editor, compartido por los casos de D7 ──────────────────

    private function edit(OrderItem $item, int $qty, User $by, bool $belowMinimum): ItemActionOutcome
    {
        $item = $item->fresh(['ticketType', 'slot', 'order']);

        return app(OrderItemEditor::class)->edit(
            $item->order, $item, '', '', false,
            (int) $item->ticket_type_id, $qty, null,
            ['edits' => [], 'adds' => []],
            (string) $item->updated_at->getTimestamp(),
            $by,
            belowMinimum: $belowMinimum,
        );
    }
}
