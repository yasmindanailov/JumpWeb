<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Contracts\CheckoutLines;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\CreateManualOrderPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 6 · menores a cargo, tanda 5 (el PANEL, spec §9.10.2 D14·5) — el ALTA MANUAL: el selector por
 * línea de entrada, `check()` ANTES de cobrar (un rechazo no crea ni cobra nada) y `assign()` DESPUÉS
 * de que `fulfill()` devuelva (un fallo deja el pedido en pie y avisa).
 */
class CreateManualOrderDependentsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private TicketType $pack;

    private string $date;

    private ?LegalDocumentVersion $version = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $rateId = (int) RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0])->id;
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(2)->toDateString();
        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 10, 'online_capacity' => 10,
        ]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1000]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'min_qty' => 2, 'max_qty' => 20, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 2,
        ]);
        $this->pack->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1500]);

        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
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

    private function add(User $holder, string $name, string $bornOn = '2017-03-12'): Dependent
    {
        return app(DependentRegistry::class)->add($holder, $name, $bornOn);
    }

    /** @return array<string, mixed> */
    private function cartLine(int $qty, array $dependentIds = [], array $names = []): array
    {
        return [
            'ticket_type_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => $qty,
            'event_data' => [], 'addons' => [], 'addon_display' => [], 'label' => 'JUMP · Jump · 1 hora',
            'when' => Carbon::parse($this->date)->format('d/m/Y').' 10:00', 'line_total_cents' => 1000 * $qty,
            'deposit_cents' => null, 'dependent_ids' => $dependentIds, 'dependent_display' => $names,
        ];
    }

    // ─── El selector ──────────────────────────────────────────────────────────

    public function test_the_selector_offers_the_customers_minors_with_their_reason_only_for_entries(): void
    {
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $vera = $this->add($holder, 'Vilma', '2019-11-02');
        $this->signFor($holder, $lucas);
        $age = Dependent::ageBetween('2017-03-12', Carbon::parse($this->date));

        $page = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_PRODUCT)
            ->set('data.customer_id', $holder->id)
            ->call('pickProduct', $this->entry->id)
            ->set('data.sel_date', $this->date)
            // `#462`: el selector de menores vive en el paso «Datos», que es adonde lleva el
            // asistente tras la hora. Aquí se salta directo porque lo que se prueba es el SELECTOR,
            // no el camino.
            ->set('step', CreateManualOrderPage::STEP_DETAILS);

        $page->assertSee('¿Para quién son estas entradas?')
            ->assertSee("Lior · {$age} años")
            ->assertSee('Vilma · ')
            ->assertSee('sin descargo firmado y vigente');

        // Un pack no lleva menores; sin cliente tampoco hay selector.
        $page->call('pickProduct', $this->pack->id)->assertDontSee('¿Para quién son estas entradas?');
        $page->call('pickProduct', $this->entry->id)->set('data.customer_id', null)->assertDontSee('¿Para quién son estas entradas?');

        // Un cliente sin menores: nada que preguntar.
        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('step', CreateManualOrderPage::STEP_PRODUCT)
            ->set('data.customer_id', $this->customer()->id)
            ->call('pickProduct', $this->entry->id)
            ->set('data.sel_date', $this->date)
            ->set('step', CreateManualOrderPage::STEP_DETAILS)
            ->assertDontSee('¿Para quién son estas entradas?');
    }

    public function test_adding_a_line_keeps_only_assignable_minors_and_refuses_more_than_units(): void
    {
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $vera = $this->add($holder, 'Vilma', '2019-11-02'); // sin firma: no asignable
        $max = $this->add($holder, 'Max', '2016-01-01');
        $this->signFor($holder, $lucas);
        $this->signFor($holder, $max);

        $page = Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $holder->id)
            ->call('pickProduct', $this->entry->id)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->set('data.sel_qty', 1)
            ->set('data.sel_dependent_ids', [$lucas->id, $vera->id, 999])
            ->call('addLineToCart');

        $cart = $page->get('cart');
        $this->assertCount(1, $cart);
        $this->assertSame([$lucas->id], $cart[0]['dependent_ids'], 'un id no asignable o inexistente se descarta');
        $this->assertSame(['Lior'], $cart[0]['dependent_display']);
        $page->assertSet('data.sel_dependent_ids', []);
        $page->assertSee('Para: Lior');

        // Dos menores para una entrada: aviso y NO se añade la línea.
        $page->call('pickProduct', $this->entry->id)
            ->set('data.sel_date', $this->date)
            ->set('data.sel_time', '10:00:00')
            ->set('data.sel_qty', 1)
            ->set('data.sel_dependent_ids', [$lucas->id, $max->id])
            ->call('addLineToCart')
            ->assertNotified(__('admin.orders.dependents.manual_too_many'));
        $this->assertCount(1, $page->get('cart'));
    }

    // ─── Cobrar y asignar ─────────────────────────────────────────────────────

    public function test_create_checks_before_charging_and_assigns_after_the_order_exists(): void
    {
        Notification::fake();
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $this->signFor($holder, $lucas);
        $operator = $this->staff();

        Livewire::actingAs($operator)
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $holder->id)
            ->set('data.payment_method', 'cash')
            ->set('cart', [$this->cartLine(2, [$lucas->id], ['Lior']), $this->cartLine(1)])
            ->call('create')
            ->assertNotNotified(__('admin.orders.dependents.manual_assign_failed', ['count' => 1]));

        $order = Order::where('user_id', $holder->id)->sole();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        [$first, $second] = $order->items()->whereNull('parent_item_id')->orderBy('id')->get();
        $this->assertSame([$lucas->id], DependentAssignment::where('order_item_id', $first->id)->pluck('dependent_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(0, DependentAssignment::where('order_item_id', $second->id)->count());
        $audit = AuditLog::where('action', 'dependents.assigned')->sole();
        $this->assertSame($operator->id, (int) $audit->user_id, 'en el mostrador, el `by` es el operador');
        $this->assertStringNotContainsString('Lior', json_encode($audit->payload));
    }

    /** FAIL-CLOSED antes del dinero: un menor que dejó de ser asignable no crea ni cobra NADA. */
    public function test_a_rejected_minor_creates_and_charges_nothing(): void
    {
        Notification::fake();
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $this->signFor($holder, $lucas);
        // La línea se añadió con Lior asignable; después se publicó un texto nuevo y su firma quedó «anterior».
        $cart = [$this->cartLine(1, [$lucas->id], ['Lior'])];
        $this->publish();

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $holder->id)
            ->set('data.payment_method', 'cash')
            ->set('cart', $cart)
            ->call('create')
            ->assertNotified(__('admin.orders.dependents.manual_check_failed', ['reasons' => __('api.dependents.waiver_unsigned')]));

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, DependentAssignment::count());
    }

    /**
     * Si la asignación falla DESPUÉS de cobrar, el pedido sigue en pie y el operador lo sabe.
     *
     * ⚠️ **Re-apuntado en `#466`, no reescrito**: el aviso era un *toast* y ahora lo dice el
     * DESENLACE. El sujeto no cambia —«el operador se entera»—; lo que cambia es que un toast
     * **desaparece** y lo que hay que hacer (asignarlos desde la ficha) queda para después.
     */
    public function test_an_assignment_failure_after_charging_leaves_the_order_and_warns(): void
    {
        Notification::fake();
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lior');
        $this->signFor($holder, $lucas);
        $this->app->instance(CheckoutLines::class, new class implements CheckoutLines
        {
            public function forOrder(int $orderId, int $userId): array
            {
                throw new \RuntimeException('booking caído');
            }
        });

        Livewire::actingAs($this->staff())
            ->test(CreateManualOrderPage::class)
            ->set('data.customer_id', $holder->id)
            ->set('data.payment_method', 'cash')
            ->set('cart', [$this->cartLine(1, [$lucas->id], ['Lior'])])
            ->call('create')
            ->assertSet('dependentsSkipped', 1)
            ->assertSee(__('admin.orders.dependents.manual_assign_failed', ['count' => 1]))
            ->assertSee(__('admin.orders.create_manual.done_dependents_hint'));

        $order = Order::where('user_id', $holder->id)->sole();
        $this->assertSame(Order::STATUS_PAID, $order->status, 'el cobro y el pedido no se deshacen por una etiqueta');
        $this->assertSame(0, DependentAssignment::count());
    }
}
