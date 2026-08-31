<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AgeFamilySealer;
use App\Domain\Booking\Services\ReservationSlip;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateProfile;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * T3 · E — la diferencia por cabeza SE VE en el parque (`docs/specs/cumple-mixto.md` §23.2).
 *
 * Medido antes de la T3: ni la hoja de sala ni la pantalla de puerta consultaban el veredicto
 * (cero referencias) — el operador hacía la cuenta de memoria con el cliente delante (§18.4·E).
 *
 * ⚠️⚠️ Las dos superficies imprimen lo ESCRITO del suplemento, NUNCA el veredicto derivado: lo
 * escrito es lo que se le comunicó al cliente y lo que se cobra (`PAY-19`). Del veredicto solo
 * salen los dos datos que el dinero no dice — el caso barato (§14) y las edades sin producto.
 *
 * ⚠️ Y protegen que salga GRATIS: `written()` lee relaciones que el reader de puerta ya carga, y
 * el último caso compara el presupuesto de consultas de la ficha con y sin suplemento escrito.
 */
class MixedPartyParkSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private Slot $slot;

    private TicketType $kids;

    private TicketType $jump;

    private int $counter = 0;

    /** Fecha fija: el fixture tiene calendario (`TESTING.md` §2). */
    private const DAY = '2026-07-15';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        // La franja del fixture tiene que quedar en el FUTURO: el pendiente de puerta se da por
        // resuelto cuando la reserva termina (`isFinishedInPractice`), y la tarjeta de puerta
        // enseña el suplemento solo mientras quede algo que cobrar.
        $this->travelTo(self::DAY.' 08:00:00');

        $rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
        $this->zone = Zone::create(['slug' => 'cumples', 'name' => ['es' => 'Cumpleaños'], 'color' => '#FF5B22']);
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => self::DAY,
            'start_time' => '11:00:00', 'end_time' => '13:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);

        $this->kids = $this->pack('Cumpleaños Kids', 1, 6, 1800, $rate);
        $this->jump = $this->pack('Cumpleaños Jump', 7, 99, 2500, $rate);
    }

    private function pack(string $name, int $min, int $max, int $cents, RateType $rate): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1,
            'min_qty' => 1, 'max_qty' => 30, 'is_sellable' => true, 'is_active' => true,
            'duration_min' => 120,
            'position' => (int) TicketType::max('position') + 1,
            'guest_fields' => [
                ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => true, 'label' => ['es' => 'Edad']],
            ],
            'guest_age_family' => 'cumple', 'guest_age_min' => $min, 'guest_age_max' => $max,
        ]);
        Price::create([
            'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
            'rate_type_id' => $rate->id, 'amount_cents' => $cents, 'currency' => 'EUR',
        ]);

        return $pack;
    }

    /** @param  list<int|null>  $ages  `null` = ficha sin edad declarada */
    private function party(array $ages, ?User $holder = null): OrderItem
    {
        $order = Order::create([
            'user_id' => ($holder ?? User::factory()->create())->id,
            'code' => 'JJ-PS'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'subtotal' => 1800 * count($ages), 'total' => 1800 * count($ages), 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (400000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $this->kids->id, 'slot_id' => $this->slot->id,
            'quantity' => count($ages), 'unit_price' => 1800, 'seats' => count($ages),
        ]);
        // El sello que `OrderCreator` pone al nacer (§21): sin él nada de esto participa.
        app(AgeFamilySealer::class)->seal($item, $this->kids, $this->slot->date);

        $rows = [];
        foreach ($ages as $i => $age) {
            $row = ['name' => 'Invitado '.($i + 1)];
            if ($age !== null) {
                $row['edad'] = (string) $age;
            }
            $rows[] = $row;
        }
        $item->submitGuestForm($rows, [], 'signed_link');

        return $item->fresh(['ticketType', 'slot', 'order']);
    }

    /** La hoja de sala RENDERIZADA (la vista real del PDF, hoja operativa sin precios). */
    private function renderedSlip(OrderItem $item): string
    {
        return view('pdf.reservation-slip', [
            'slip' => ReservationSlip::make($item->order, $item),
            'showPrices' => false,
        ])->render();
    }

    // ─── A · La hoja de sala ─────────────────────────────────────────────────────

    public function test_the_slip_prints_the_written_lines_and_the_total(): void
    {
        // 2 invitados de Jump sobre Kids: 2 × 7,00 € = 14,00 € escritos.
        $html = $this->renderedSlip($this->party([4, 8, 9]));

        $this->assertStringContainsString(__('admin.orders.slip.mixed_party_heading'), $html);
        $this->assertStringContainsString(
            __('admin.orders.mixed_party.line', ['count' => 2, 'name' => 'Cumpleaños Jump', 'unit' => ReservationSlip::money(700)]),
            $html,
        );
        // El total con su «se cobra en el parque» — la clave `applied` lo lleva dentro.
        $this->assertStringContainsString(
            __('admin.orders.mixed_party.applied', ['amount' => ReservationSlip::money(1400)]),
            $html,
        );
    }

    public function test_the_slip_counts_the_ages_without_product(): void
    {
        // El de 0 años no lo cubre ningún tramo sellado (Kids 1–6 · Jump 7–99): sin producto.
        $html = $this->renderedSlip($this->party([0, 4, 5]));

        $this->assertStringContainsString(
            __('admin.orders.mixed_party.out_of_range', ['count' => 1]),
            $html,
        );
    }

    public function test_the_slip_warns_the_cheap_case_without_inventing_money(): void
    {
        // Invitado de 3 años en una fiesta Jump: le tocaría Kids (más barato). Aviso, no dinero.
        $html = $this->renderedSlip($this->jumpParty([8, 3]));

        $this->assertStringContainsString(
            __('admin.orders.mixed_party.cheaper', ['amount' => ReservationSlip::money(700)]),
            $html,
        );
        // Y NO imprime un total aplicado: no hay cargo escrito que cobrar.
        $this->assertStringNotContainsString(__('admin.orders.mixed_party.applied', ['amount' => ReservationSlip::money(0)]), $html);
    }

    public function test_without_a_mix_the_slip_has_no_block(): void
    {
        // Control: todos en su tramo → el bloque entero no existe (§23.2, «sin mezcla, nada»).
        $html = $this->renderedSlip($this->party([4, 5, 6]));

        $this->assertStringNotContainsString(__('admin.orders.slip.mixed_party_heading'), $html);
    }

    /** Una fiesta del pack CARO (Jump) para el caso barato. @param list<int> $ages */
    private function jumpParty(array $ages): OrderItem
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-PJ'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'subtotal' => 2500 * count($ages), 'total' => 2500 * count($ages), 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (410000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $this->jump->id, 'slot_id' => $this->slot->id,
            'quantity' => count($ages), 'unit_price' => 2500, 'seats' => count($ages),
        ]);
        app(AgeFamilySealer::class)->seal($item, $this->jump, $this->slot->date);

        $rows = [];
        foreach ($ages as $i => $age) {
            $rows[] = ['name' => 'Invitado '.($i + 1), 'edad' => (string) $age];
        }
        $item->submitGuestForm($rows, [], 'signed_link');

        return $item->fresh(['ticketType', 'slot', 'order']);
    }

    // ─── B · La pantalla de puerta ───────────────────────────────────────────────

    public function test_the_gate_reader_carries_the_written_lines(): void
    {
        $item = $this->party([4, 8, 9]);

        $rows = app(GateReservations::class)->forHolder((int) $item->order->user_id, self::DAY, self::DAY);

        $this->assertNotEmpty($rows);
        $this->assertSame(1400, $rows[0]->mixedPartySurchargeCents);
        $this->assertSame(
            [['name' => 'Cumpleaños Jump', 'count' => 2, 'unit_cents' => 700]],
            $rows[0]->mixedPartyLines,
        );
    }

    public function test_the_gate_card_paints_the_lines_between_product_and_pending(): void
    {
        $item = $this->party([4, 8, 9]);

        $profile = app(GateProfile::class)->for(
            $item->order->user, CarbonImmutable::parse(self::DAY), 0,
        );
        $row = $profile->today_reservations[0] ?? null;
        $this->assertNotNull($row, 'la ficha tiene que traer la reserva del día');

        $html = view('livewire.admin.puerta.partials.reservation', ['r' => $row])->render();

        $line = __('admin.puerta.validar.profile.mixed_party_line', [
            'count' => 2, 'name' => 'Cumpleaños Jump', 'unit' => Money::amount(700),
        ]);
        $total = __('admin.puerta.validar.profile.mixed_party_total', ['amount' => Money::amount(1400)]);
        $this->assertStringContainsString($line, $html);
        $this->assertStringContainsString($total, $html);

        // Bajo el producto y ENCIMA del pendiente (§23.2): el orden en el documento es la spec.
        $product = mb_strpos($html, 'Cumpleaños Kids');
        $mixed = mb_strpos($html, $line);
        $pending = mb_strpos($html, 'data-gate-pending');
        $this->assertNotFalse($product);
        $this->assertNotFalse($pending);
        $this->assertTrue($product < $mixed && $mixed < $pending, 'el bloque va bajo el producto y encima del pendiente');
    }

    public function test_without_a_written_surcharge_the_gate_card_paints_nothing(): void
    {
        // Control: fiesta sin mezcla → ni el bloque ni un total de 0,00 €.
        $item = $this->party([4, 5, 6]);

        $profile = app(GateProfile::class)->for(
            $item->order->user, CarbonImmutable::parse(self::DAY), 0,
        );
        $html = view('livewire.admin.puerta.partials.reservation', ['r' => $profile->today_reservations[0]])->render();

        $this->assertStringNotContainsString('data-gate-mixed-party', $html);
    }

    public function test_the_gate_profile_query_budget_does_not_grow_with_the_rows(): void
    {
        // ⚠️⚠️ El riesgo real de enseñar el suplemento en la puerta: las etiquetas del desglose
        // caminan `adjustment->orderItem->ticketType` y el resumen financiero camina `slot` y
        // `parent->slot` — sin sus eager-loads, cada ajuste pagaba consultas POR FILA en la
        // pantalla que se abre decenas de veces en hora punta (cazado al escribir este caso: 4
        // consultas sueltas por ficha). El presupuesto se compara entre UN pedido con suplemento
        // y TRES: los lotes eager no crecen con las filas; un perezoso sí.
        $one = User::factory()->create();
        $this->party([4, 8, 9], $one);

        $three = User::factory()->create();
        $this->party([4, 8, 9], $three);
        $this->party([5, 9], $three);
        $this->party([3, 7, 12], $three);

        $costOne = $this->countQueriesComposingTheProfile($one);
        $costThree = $this->countQueriesComposingTheProfile($three);

        $this->assertSame($costOne, $costThree, 'el coste crece con las filas: falta un eager-load');
    }

    private function countQueriesComposingTheProfile(User $holder): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(GateProfile::class)->for($holder, CarbonImmutable::parse(self::DAY), 0);

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
