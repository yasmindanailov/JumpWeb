<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\Setting;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Support\AssignedDependents;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\DeclaresDependents;
use Tests\TestCase;

/**
 * Fase 6 · menores a cargo, tanda 5 (el PANEL, spec §9.10.2 D14·1) — la ficha del pedido dice PARA QUIÉN
 * es cada entrada: nombre, edad EN LA FECHA DE LA VISITA y estado del descargo (solo en interno).
 */
class ItemsListDependentsTest extends TestCase
{
    use DeclaresDependents;
    use RefreshDatabase;

    private const VISIT = '2026-09-05';

    protected Zone $zone;

    protected TicketType $entry;

    protected TicketType $pack;

    protected Slot $slot;

    protected int $counter = 0;

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
    }

    protected function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
        Setting::flushMemo();
    }

    protected ?LegalDocumentVersion $version = null;

    protected function publish(): LegalDocumentVersion
    {
        return $this->version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    protected function signFor(User $holder, Dependent $dependent): void
    {
        app(WaiverSigner::class)->sign($holder, $this->version ?? $this->publish(), new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT, subjectId: (int) $dependent->getKey(),
        ));
    }

    protected function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    protected function customer(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    protected function add(User $holder, string $name, string $bornOn): Dependent
    {
        return $this->declareLegacyDependent($holder, $name, $bornOn);
    }

    /** Un pedido PAGADO con las líneas dadas, en orden. @return array{0: Order, 1: list<OrderItem>} */
    protected function paidOrder(User $holder, array $lines): array
    {
        $order = Order::create([
            'user_id' => $holder->id, 'code' => 'R-DEP'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
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

    protected function assign(User $holder, Order $order, array $perLine): void
    {
        $lines = [];
        foreach ($perLine as $index => [$type, $quantity, $ids]) {
            $lines[] = ['index' => $index, 'product_id' => $type->id, 'date' => self::VISIT, 'quantity' => $quantity, 'dependent_ids' => $ids];
        }
        $outcome = app(DependentAssigner::class)->assign($holder, $order->id, $lines);
        $this->assertNull($outcome->abortedBecause);
    }

    public function test_the_order_sheet_says_for_whom_each_entry_is_with_the_age_on_the_visit_date_and_the_waiver_state(): void
    {
        // Se asigna en EXTERNO (sin firma que exigir) y la instalación pasa a INTERNO después: es el único
        // camino por el que una entrada asignada puede decir «sin descargo firmado» — en interno el
        // asignador no escribe a un menor sin firma (`#202`·2).
        $this->mode('externo');
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lucas', '2017-03-12'); // 9 el día de la visita
        $vera = $this->add($holder, 'Vera', '2019-11-02');   // 6, sin firma
        [$order, [$entryItem]] = $this->paidOrder($holder, [[$this->entry, 3], [$this->pack, 4]]);
        $this->assign($holder, $order, [[$this->entry, 3, [$lucas->id, $vera->id]], [$this->pack, 4, []]]);
        $this->mode('interno');
        $this->signFor($holder, $lucas);

        $page = Livewire::actingAs($this->admin())->test(ViewOrder::class, ['record' => $order->code]);

        // `#320`: Lucas está firmado y vigente y por eso NO lleva rótulo —la firma es condición para
        // estar asignado, anunciarla en cada fila es repetir lo que el sistema ya garantiza—; Vera sí,
        // porque «sin descargo» es una excepción que el operador tiene que ver.
        $page->assertSee('Para:')
            ->assertSee('Lucas (9 años),')
            ->assertSee('Vera (6 años · sin descargo firmado)')
            ->assertDontSee(__('admin.orders.dependents.waiver_current'));
        $this->assertSame(1, substr_count($page->html(), 'data-dependents-for="'), 'solo la línea de ENTRADA lleva el «Para:»; el pack nunca');
        $this->assertStringContainsString('data-dependents-for="'.$entryItem->id.'"', $page->html());
    }

    /**
     * `#320` · el estado SIGUE existiendo aunque no se pinte: ocultar el rótulo de «firmada y vigente»
     * es una decisión de PRESENTACIÓN, no una amputación del read-model. Si esto se rompiera, el panel
     * y la puerta perderían la diferencia entre «firmada» y «el modo no responde por menores», que es
     * la que hace falta para diagnosticar por qué una fila no dice nada.
     */
    public function test_the_current_state_still_travels_in_the_read_model_even_though_it_is_not_painted(): void
    {
        $this->mode('interno');
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lucas', '2017-03-12');
        $this->signFor($holder, $lucas);
        [$order] = $this->paidOrder($holder, [[$this->entry, 1]]);
        $this->assign($holder, $order, [[$this->entry, 1, [$lucas->id]]]);

        $read = AssignedDependents::forOrder($order->load('items.ticketType', 'items.slot'));

        $this->assertSame(
            [AssignedDependents::WAIVER_CURRENT],
            array_column($read[$order->items[0]->id], 'waiver'),
            'el estado se compone igual; lo que cambia es que `label()` no lo rotula',
        );
        $this->assertSame('Lucas (9 años)', $read[$order->items[0]->id][0]['label']);
    }

    /** Una firma de una versión anterior se SEÑALA; un menor retirado de la cuenta sigue saliendo, marcado. */
    public function test_an_outdated_waiver_and_a_removed_dependent_are_marked_not_hidden(): void
    {
        $this->mode('interno');
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lucas', '2017-03-12');
        $vera = $this->add($holder, 'Vera', '2019-11-02');
        $this->signFor($holder, $lucas);
        $this->signFor($holder, $vera);
        [$order] = $this->paidOrder($holder, [[$this->entry, 2]]);
        $this->assign($holder, $order, [[$this->entry, 2, [$lucas->id, $vera->id]]]);
        $this->publish();                                            // Lucas y Vera quedan «anterior»
        app(DependentRegistry::class)->remove($holder, $vera->id);   // Vera, desvinculada (tiene firma y entrada detrás)

        Livewire::actingAs($this->admin())->test(ViewOrder::class, ['record' => $order->code])
            ->assertSee('Lucas (9 años · descargo de una versión anterior),')
            ->assertSee('Vera (6 años · descargo de una versión anterior · retirado de la cuenta)');
    }

    /** Fuera del modo interno no hay firma que enseñar: nombre y edad, nada más. */
    public function test_outside_internal_mode_only_name_and_age_are_shown(): void
    {
        $this->mode('externo');
        $holder = $this->customer();
        $lucas = $this->add($holder, 'Lucas', '2017-09-04'); // 8 hoy, 9 el día de la visita (D13)
        [$order] = $this->paidOrder($holder, [[$this->entry, 1]]);
        $this->assign($holder, $order, [[$this->entry, 1, [$lucas->id]]]);

        Livewire::actingAs($this->admin())->test(ViewOrder::class, ['record' => $order->code])
            ->assertSee('Lucas (9 años)')
            ->assertDontSee('descargo');
    }

    public function test_nothing_is_shown_when_no_entry_has_a_dependent(): void
    {
        $this->mode('interno');
        $holder = $this->customer();
        $this->add($holder, 'Lucas', '2017-03-12');
        [$order] = $this->paidOrder($holder, [[$this->entry, 2]]);

        Livewire::actingAs($this->admin())->test(ViewOrder::class, ['record' => $order->code])
            ->assertDontSee('Para:')
            ->assertDontSee('data-dependents-for');
    }

    /** Presupuesto: la composición es CONSTANTE sean 1 o 4 los menores y 1 o 2 las líneas. */
    public function test_the_read_model_runs_a_constant_number_of_queries(): void
    {
        $this->mode('interno');
        $holder = $this->customer();
        $kids = [];
        foreach (['Lucas', 'Vera', 'Max', 'Noa'] as $i => $name) {
            $kids[] = $kid = $this->add($holder, $name, '201'.(5 + $i).'-03-12');
            $this->signFor($holder, $kid);
        }
        [$small] = $this->paidOrder($holder, [[$this->entry, 1]]);
        $this->assign($holder, $small, [[$this->entry, 1, [$kids[0]->id]]]);
        [$big] = $this->paidOrder($holder, [[$this->entry, 2], [$this->entry, 2]]);
        $this->assign($holder, $big, [[$this->entry, 2, [$kids[0]->id, $kids[1]->id]], [$this->entry, 2, [$kids[2]->id, $kids[3]->id]]]);
        WaiverSettings::mode();

        $count = function (Order $order): int {
            $order->load('items.ticketType', 'items.slot');
            DB::enableQueryLog();
            DB::flushQueryLog();
            AssignedDependents::forOrder($order);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $forSmall = $count($small);
        $forBig = $count($big);
        $this->assertSame($forSmall, $forBig, 'el presupuesto no crece con las líneas ni con los menores');
        $this->assertLessThanOrEqual(5, $forSmall, 'asignaciones + sus menores + firmas + versiones + versión vigente');

        $read = AssignedDependents::forOrder($big->load('items.ticketType', 'items.slot'));
        $this->assertCount(2, $read);
        $this->assertSame(['Lucas', 'Vera'], array_column($read[$big->items[0]->id], 'name'));
        $this->assertSame([AssignedDependents::WAIVER_CURRENT, AssignedDependents::WAIVER_CURRENT], array_column($read[$big->items[0]->id], 'waiver'));
    }
}
