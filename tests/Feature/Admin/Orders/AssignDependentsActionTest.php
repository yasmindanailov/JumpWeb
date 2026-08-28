<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 6 · menores a cargo, tanda 5 (el PANEL, spec §9.10.2 D14·3/·4/·6) — la acción «Asignar menores»
 * de una línea: quién la ve, qué enseña y las cuatro capas que revalida antes de escribir.
 */
class AssignDependentsActionTest extends TestCase
{
    use RefreshDatabase;

    private const VISIT = '2026-09-05';

    private Zone $zone;

    private TicketType $entry;

    private TicketType $pack;

    private Slot $slot;

    private int $counter = 0;

    private ?LegalDocumentVersion $version = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->travelTo(Carbon::parse('2026-08-27 12:00:00', 'Europe/Madrid'));

        $rateId = (int) RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0])->id;
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 990]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $this->pack->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1500]);
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => self::VISIT, 'start_time' => '10:00:00',
            'end_time' => '11:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);
        $this->mode('interno');
    }

    // ─── Fixture ──────────────────────────────────────────────────────────────

    private function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
        Setting::flushMemo();
    }

    private function publish(): LegalDocumentVersion
    {
        return $this->version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function signFor(User $holder, Dependent $dependent): void
    {
        app(WaiverSigner::class)->sign($holder, $this->version ?? $this->publish(), new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT, subjectId: (int) $dependent->getKey(),
        ));
    }

    private function staffWith(array $permissions): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        return $u;
    }

    private function editor(): User
    {
        return $this->staffWith(['orders.view', 'orders.edit_item']);
    }

    private function viewer(): User
    {
        return $this->staffWith(['orders.view']);
    }

    private function customer(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    private function add(User $holder, string $name, string $bornOn = '2017-03-12'): Dependent
    {
        return app(DependentRegistry::class)->add($holder, $name, $bornOn);
    }

    /** @return array{0: Order, 1: list<OrderItem>} */
    private function paidOrder(User $holder, array $lines): array
    {
        $order = Order::create([
            'user_id' => $holder->id, 'code' => 'R-ASG'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => 1980, 'tax' => 0, 'total' => 1980, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        $items = [];
        foreach ($lines as [$type, $quantity]) {
            $items[] = $order->items()->create([
                'ticket_type_id' => $type->id, 'slot_id' => $this->slot->id, 'quantity' => $quantity,
                'unit_price' => 990, 'seats' => $quantity,
            ]);
        }

        return [$order->fresh(), $items];
    }

    private function assignViaCheckout(User $holder, Order $order, OrderItem $item, array $ids): void
    {
        $outcome = app(DependentAssigner::class)->assign($holder, $order->id, [
            ['index' => 0, 'product_id' => $this->entry->id, 'date' => self::VISIT, 'quantity' => (int) $item->quantity, 'dependent_ids' => $ids],
        ]);
        $this->assertSame(count($ids), $outcome->assigned);
    }

    private function assigned(OrderItem $item): array
    {
        return DependentAssignment::where('order_item_id', $item->id)->orderBy('id')->pluck('dependent_id')->map(fn ($id): int => (int) $id)->all();
    }

    private function mountCall(OrderItem $item): string
    {
        return "mountAction('assignDependents', { item: {$item->id} })";
    }

    // ─── Quién la ve (D14·4) ──────────────────────────────────────────────────

    public function test_the_icon_is_offered_only_on_entries_of_a_holder_with_dependents_to_staff_who_can_edit(): void
    {
        $holder = $this->customer();
        $this->add($holder, 'Lior');
        [$order, [$entryItem, $packItem]] = $this->paidOrder($holder, [[$this->entry, 2], [$this->pack, 4]]);

        $html = Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])->html();
        $this->assertStringContainsString($this->mountCall($entryItem), $html, 'la entrada ofrece «Asignar menores»');
        $this->assertStringNotContainsString($this->mountCall($packItem), $html, 'un pack nunca');
        $this->assertStringContainsString('Asignar menores a Entrada 1h', $html);

        $html = Livewire::actingAs($this->viewer())->test(ViewOrder::class, ['record' => $order->code])->html();
        $this->assertStringNotContainsString("mountAction('assignDependents'", $html, 'sin `orders.edit_item` no se ofrece');

        [$plain, [$plainItem]] = $this->paidOrder($this->customer(), [[$this->entry, 2]]);
        $html = Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $plain->code])->html();
        $this->assertStringNotContainsString("mountAction('assignDependents'", $html, 'un titular sin menores no tiene nada que asignar');

        $entryItem->forceFill(['cancelled_at' => now()])->save();
        $html = Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])->html();
        $this->assertStringNotContainsString($this->mountCall($entryItem), $html, 'una línea cancelada no admite cambios');
    }

    // ─── Qué enseña (D14·6) ───────────────────────────────────────────────────

    public function test_the_modal_lists_the_candidates_with_their_reason_and_preselects_the_current_set(): void
    {
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');              // firmado y asignado
        $vera = $this->add($holder, 'Vilma', '2019-11-02');   // sin firma → deshabilitada, con motivo
        $this->signFor($holder, $lucas);
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 3]]);
        $this->assignViaCheckout($holder, $order, $item, [$lucas->id]);

        $page = Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('assignDependents', ['item' => $item->id])
            ->assertActionMounted('assignDependents')
            ->assertActionDataSet(['dependent_ids' => [$lucas->id]]);

        // El HTML del modal no forma parte del render del componente en el test: se lee el SCHEMA montado.
        $list = $this->mountedCheckboxList($page->instance());
        $this->assertSame('¿Para quién son estas entradas?', (string) $list->getLabel());
        $this->assertSame([$lucas->id => 'Lior · 9 años', $vera->id => 'Vilma · 6 años'], $list->getOptions());
        $this->assertFalse($list->isOptionDisabled($lucas->id, 'Lior · 9 años'));
        $this->assertTrue($list->isOptionDisabled($vera->id, 'Vilma · 6 años'), 'sin firma no se puede MARCAR');
        $this->assertSame('sin exención firmada y vigente', (string) $list->getDescription($vera->id));
        $this->assertNull($list->getDescription($lucas->id));
    }

    /** Una asignación a un menor ya RETIRADO no es una casilla: se explica y se descuenta del tope (D14·3). */
    public function test_the_modal_shows_conserved_assignments_to_removed_dependents_and_lowers_the_limit(): void
    {
        $this->mode('externo');
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 2]]);
        $this->assignViaCheckout($holder, $order, $item, [$lucas->id, $vera->id]);
        app(DependentRegistry::class)->remove($holder, $vera->id);

        $page = Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('assignDependents', ['item' => $item->id])
            ->assertActionDataSet(['dependent_ids' => [$lucas->id]]);

        $list = $this->mountedCheckboxList($page->instance());
        $this->assertSame([$lucas->id => 'Lior · 9 años'], $list->getOptions(), 'Vilma ya no es una casilla');
        $notice = $this->mountedSchema($page->instance())->getComponent(fn ($c): bool => $c instanceof Placeholder && $c->getName() === 'conserved');
        $this->assertInstanceOf(Placeholder::class, $notice);
        $this->assertStringContainsString('Vilma ya no están en la cuenta del cliente', (string) $notice->getContent());

        // Y Vilma sigue ocupando una unidad: Lior + Max no caben en una línea de dos.
        $max = $this->add($holder, 'Max', '2016-01-01');
        Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('assignDependents', data: ['dependent_ids' => [$lucas->id, $max->id]], arguments: ['item' => $item->id])
            ->assertNotified(__('admin.orders.dependents.rejected', ['reasons' => __('admin.orders.dependents.reasons.too_many')]));
        $this->assertSame([$lucas->id, $vera->id], $this->assigned($item), 'nada cambió: Vilma conservada, Lior mantenido');
    }

    private function mountedSchema(ViewOrder $component): Schema
    {
        return $component->getSchema($component->getMountedActionSchemaName());
    }

    private function mountedCheckboxList(ViewOrder $component): CheckboxList
    {
        $list = $this->mountedSchema($component)->getComponent(fn ($c): bool => $c instanceof CheckboxList);
        $this->assertInstanceOf(CheckboxList::class, $list);

        return $list;
    }

    public function test_the_modal_explains_where_dependents_are_declared_when_the_holder_has_none(): void
    {
        $holder = $this->customer();
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 1]]);

        $page = Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('assignDependents', ['item' => $item->id])
            ->assertActionMounted('assignDependents');

        $schema = $this->mountedSchema($page->instance());
        $this->assertNull($schema->getComponent(fn ($c): bool => $c instanceof CheckboxList), 'sin candidatos no hay casillas');
        $notice = $schema->getComponent(fn ($c): bool => $c instanceof Placeholder);
        $this->assertInstanceOf(Placeholder::class, $notice);
        $this->assertStringContainsString('El cliente no tiene menores a cargo declarados', (string) $notice->getContent());
    }

    // ─── Qué escribe (D14·3) ──────────────────────────────────────────────────

    public function test_saving_sets_the_set_and_audits_with_the_operator(): void
    {
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $this->signFor($holder, $lucas);
        $this->signFor($holder, $vera);
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 2]]);
        $this->assignViaCheckout($holder, $order, $item, [$lucas->id]);
        $operator = $this->editor();

        Livewire::actingAs($operator)->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('assignDependents', data: ['dependent_ids' => [$vera->id]], arguments: ['item' => $item->id])
            ->assertHasNoActionErrors()
            ->assertNotified(__('admin.orders.dependents.saved', ['names' => 'Vilma']));

        $this->assertSame([$vera->id], $this->assigned($item));
        $unassigned = AuditLog::where('action', 'dependents.unassigned')->sole();
        $this->assertSame($operator->id, (int) $unassigned->user_id);
        $this->assertSame($lucas->id, (int) $unassigned->payload['dependent_id']);
        $assigned = AuditLog::where('action', 'dependents.assigned')->orderByDesc('id')->firstOrFail();
        $this->assertSame($operator->id, (int) $assigned->user_id);
        $this->assertSame($vera->id, (int) $assigned->payload['dependent_id']);
        $this->assertStringNotContainsString('Vilma', json_encode(AuditLog::all()), 'RGPD-02');

        // Desmarcar a todos: todas las entradas son de adultos.
        Livewire::actingAs($operator)->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('assignDependents', data: ['dependent_ids' => []], arguments: ['item' => $item->id])
            ->assertNotified(__('admin.orders.dependents.saved_none'));
        $this->assertSame([], $this->assigned($item));
    }

    public function test_saving_the_same_set_changes_nothing(): void
    {
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $this->signFor($holder, $lucas);
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 2]]);
        $this->assignViaCheckout($holder, $order, $item, [$lucas->id]);

        Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('assignDependents', data: ['dependent_ids' => [$lucas->id]], arguments: ['item' => $item->id])
            ->assertNotified(__('admin.orders.dependents.unchanged'));

        $this->assertSame([$lucas->id], $this->assigned($item));
        $this->assertSame(1, AuditLog::whereIn('action', ['dependents.assigned', 'dependents.unassigned'])->count());
    }

    /**
     * FAIL-CLOSED en el mostrador: el estado cambió bajo los pies (versión nueva publicada con el modal
     * abierto) y nada se escribe. La primera capa que lo para es el FORMULARIO —Filament valida cada
     * valor contra las opciones habilitadas al enviar, y Vilma ya está deshabilitada—; el dominio
     * (`DependentAssignerTest`) es la última, para lo que no pase por el formulario.
     */
    public function test_a_rejection_writes_nothing_and_says_why(): void
    {
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $this->signFor($holder, $lucas);
        $this->signFor($holder, $vera);
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 2]]);
        $this->assignViaCheckout($holder, $order, $item, [$lucas->id]);
        $page = Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('assignDependents', ['item' => $item->id]);

        // Con el modal abierto se publica un texto nuevo: Vilma (a añadir) ya no tiene firma vigente;
        // Lior (que se mantiene) tampoco, pero lo que se mantiene no se re-valida.
        $this->publish();

        $page->setActionData(['dependent_ids' => [$lucas->id, $vera->id]])
            ->callMountedAction()
            ->assertHasActionErrors();

        $this->assertSame([$lucas->id], $this->assigned($item), 'no se escribió NADA');
        $this->assertSame(0, AuditLog::where('action', 'dependents.assigned')->where('user_id', '!=', null)->count());
    }

    public function test_more_minors_than_units_is_rejected(): void
    {
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $this->signFor($holder, $lucas);
        $this->signFor($holder, $vera);
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 1]]);

        Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('assignDependents', data: ['dependent_ids' => [$lucas->id, $vera->id]], arguments: ['item' => $item->id])
            ->assertNotified(__('admin.orders.dependents.rejected', ['reasons' => __('admin.orders.dependents.reasons.too_many')]));

        $this->assertSame([], $this->assigned($item));
    }

    // ─── Las capas de defensa (D14·4) ─────────────────────────────────────────

    public function test_staff_without_edit_permission_is_blocked_and_audited(): void
    {
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $this->signFor($holder, $lucas);
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 1]]);

        Livewire::actingAs($this->viewer())->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('assignDependents', data: ['dependent_ids' => [$lucas->id]], arguments: ['item' => $item->id])
            ->assertNotified(__('admin.orders.manage_item.permission_denied'));

        $this->assertSame([], $this->assigned($item));
        $blocked = AuditLog::where('action', 'orders.item_edit_blocked')->sole();
        $this->assertSame('permission_denied', $blocked->payload['reason']);
        $this->assertTrue($blocked->payload['dependents']);
    }

    /** IDOR: un ítem de OTRO pedido no se toca desde esta ficha, y el intento queda auditado. */
    public function test_an_item_of_another_order_is_blocked_and_audited(): void
    {
        $mine = $this->customer();
        [$myOrder] = $this->paidOrder($mine, [[$this->entry, 1]]);
        $other = $this->customer();
        $kid = $this->add($other, 'Ajeno');
        $this->signFor($other, $kid);
        [, [$foreignItem]] = $this->paidOrder($other, [[$this->entry, 1]]);

        // El modal de un ítem ajeno no tiene casillas (schema de aviso): se llama sin datos, como haría
        // un cliente que fuerza el `mountAction` — el handler es quien bloquea y audita.
        Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $myOrder->code])
            ->callAction('assignDependents', arguments: ['item' => $foreignItem->id]);

        $this->assertSame([], $this->assigned($foreignItem));
        $blocked = AuditLog::where('action', 'orders.item_edit_blocked')->sole();
        $this->assertSame('not_in_order', $blocked->payload['reason']);
        $this->assertSame($foreignItem->id, (int) $blocked->payload['order_item_id']);
    }

    public function test_a_cancelled_item_is_blocked(): void
    {
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $this->signFor($holder, $lucas);
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 1]]);
        $item->forceFill(['cancelled_at' => now()])->save();

        Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('assignDependents', data: ['dependent_ids' => [$lucas->id]], arguments: ['item' => $item->id])
            ->assertNotified(__('admin.orders.manage_item.blocked', ['reason' => __('admin.orders.item_actions.reasons.item_cancelled')]));

        $this->assertSame([], $this->assigned($item));
        $this->assertSame('item_cancelled', AuditLog::where('action', 'orders.item_edit_blocked')->sole()->payload['reason']);
    }

    public function test_a_pack_line_is_blocked(): void
    {
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $this->signFor($holder, $lucas);
        [$order, [$packItem]] = $this->paidOrder($holder, [[$this->pack, 4]]);

        Livewire::actingAs($this->editor())->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('assignDependents', arguments: ['item' => $packItem->id])
            ->assertNotified(__('admin.orders.dependents.blocked', ['reason' => __('admin.orders.dependents.reasons.entries_only')]));

        $this->assertSame(0, DependentAssignment::count());
    }
}
