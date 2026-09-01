<?php

namespace Tests\Feature\Puerta;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderLedger;
use App\Domain\Identity\Contracts\GateProfileData;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\GateProfile;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 6 · subsistema A — la FICHA compuesta (`docs/specs/identidad-qr-puerta.md` §4.6, §4.7, §9.2
 * A·3/A·7): hoy frente a la ventana, el dinero del ledger, los menores por edad y exención —NUNCA el
 * nombre— y un presupuesto de consultas constante.
 */
class GateProfileTest extends TestCase
{
    use RefreshDatabase;

    protected const TODAY = '2026-09-05';

    protected Zone $zone;

    protected TicketType $entry;

    protected TicketType $pack;

    protected TicketType $addon;

    protected int $counter = 0;

    protected ?LegalDocumentVersion $version = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::TODAY.' 09:00:00', 'Europe/Madrid'));

        $rateId = (int) RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0])->id;
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1000]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $this->addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 3,
        ]);
        $this->mode('interno');
    }

    protected function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
        Setting::flushMemo();
    }

    protected function publish(): LegalDocumentVersion
    {
        return $this->version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    protected function signFor(User $holder, ?Dependent $dependent = null): void
    {
        app(WaiverSigner::class)->sign($holder, $this->version ?? $this->publish(), new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test',
            subjectType: $dependent === null ? WaiverSignature::SUBJECT_HOLDER : WaiverSignature::SUBJECT_DEPENDENT,
            subjectId: $dependent?->getKey(),
        ));
    }

    protected function holder(): User
    {
        return User::factory()->create(['name' => 'Ana Titular', 'email_verified_at' => now()]);
    }

    protected function slotOn(string $date, string $start = '10:00:00'): Slot
    {
        return Slot::firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => $date, 'start_time' => $start],
            ['end_time' => Carbon::parse($start)->addHour()->format('H:i:s'), 'capacity' => 20, 'online_capacity' => 20],
        );
    }

    /** Un pedido PAGADO (efectivo) con las líneas `[tipo, cantidad, fecha]`. @return array{0: Order, 1: list<OrderItem>} */
    protected function paidOrder(User $holder, array $lines, string $status = Order::STATUS_PAID): array
    {
        $total = array_sum(array_map(fn (array $l): int => 1000 * $l[1], $lines));
        $order = Order::create([
            'user_id' => $holder->id, 'code' => 'R-GATE'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => $status, 'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => $status === Order::STATUS_PAID ? now()->subDay() : null,
        ]);
        if ($status === Order::STATUS_PAID) {
            Payment::create([
                'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'cash',
                'amount' => $total, 'currency' => 'EUR', 'status' => Payment::STATUS_PAID, 'paid_at' => now()->subDay(),
            ]);
        }
        $items = [];
        foreach ($lines as [$type, $quantity, $date]) {
            $items[] = $order->items()->create([
                'ticket_type_id' => $type->id, 'slot_id' => $this->slotOn($date)->id, 'quantity' => $quantity,
                'unit_price' => 1000, 'seats' => $quantity,
            ]);
        }

        return [$order->fresh(), $items];
    }

    protected function profile(User $holder, int $window = 1): GateProfileData
    {
        return app(GateProfile::class)->for($holder, CarbonImmutable::parse(self::TODAY), $window);
    }

    // ─── Los tres estados que hay que decir en voz alta (§4.6) ───────────────

    public function test_today_and_the_window_are_told_apart_and_nothing_is_an_answer_too(): void
    {
        $holder = $this->holder();
        $this->paidOrder($holder, [[$this->entry, 2, self::TODAY], [$this->pack, 6, '2026-09-06']]);
        $this->paidOrder($holder, [[$this->entry, 1, '2026-09-04']]);
        $this->paidOrder($holder, [[$this->entry, 1, '2026-09-08']]);                       // fuera de ±1
        $this->paidOrder($holder, [[$this->entry, 3, self::TODAY]], Order::STATUS_PENDING); // sin pagar: no cuenta

        $profile = $this->profile($holder);

        $this->assertSame('Ana Titular', $profile->holderName);
        $this->assertSame([self::TODAY], array_column($profile->today_reservations, 'date'));
        $this->assertSame(['Entrada 1h'], array_column($profile->today_reservations, 'product'));
        $this->assertSame([2], array_column($profile->today_reservations, 'quantity'));
        $this->assertSame(['2026-09-04', '2026-09-06'], array_column($profile->window, 'date'), 'la ventana: un día antes y un día después, en orden');
        $this->assertSame([true, false], array_column($profile->window, 'is_entry'));
        $this->assertSame(1, $profile->windowDays);

        $nothing = $this->profile($this->holder());
        $this->assertSame([], $nothing->today_reservations);
        $this->assertSame([], $nothing->window, '«sin reserva hoy» es una respuesta válida: puede comprar en puerta');
        $this->assertSame([], $nothing->dependents, '«0 menores a cargo» es una respuesta válida');
        $this->assertSame(GateProfileData::CARD_NONE, $nothing->card);
    }

    public function test_the_window_is_configurable_and_zero_means_only_today(): void
    {
        $holder = $this->holder();
        $this->paidOrder($holder, [[$this->entry, 1, '2026-09-02'], [$this->entry, 1, '2026-09-04'], [$this->entry, 1, self::TODAY]]);

        $this->assertSame(['2026-09-04'], array_column($this->profile($holder, 1)->window, 'date'));
        $this->assertSame(['2026-09-02', '2026-09-04'], array_column($this->profile($holder, 3)->window, 'date'));
        $this->assertSame([], $this->profile($holder, 0)->window);
        $this->assertCount(1, $this->profile($holder, 0)->today_reservations);
    }

    // ─── El dinero sale del LEDGER (§4.7) ─────────────────────────────────────

    public function test_money_comes_from_the_ledger_per_reservation(): void
    {
        $holder = $this->holder();
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 2, self::TODAY]]);
        OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $this->addon->id,
            'quantity' => 2, 'unit_price' => 0, 'seats' => 0,
        ]);
        // Un cargo pendiente en puerta sobre la línea (un cambio hecho desde el panel).
        $order->adjustments()->create([
            'order_item_id' => $item->id, 'type' => 'edit', 'amount_cents' => 500, 'currency' => 'EUR',
            'reason' => 'gate_change_line_slot', 'applied_by' => User::factory()->create()->id,
        ]);

        $row = $this->profile($holder)->today_reservations[0];
        // La puerta PINTA lo que el ledger dice; no se recompone aquí ni en el test (`LedgerSingleSourceTest`).
        $order->refresh()->load('adjustments', 'items.ticketType', 'items.slot', 'payments.refunds');
        $ledger = OrderLedger::forReservation($order, $order->items->firstWhere('id', $item->id));

        $this->assertSame('R-GATE001', $row['order_code']);
        $this->assertSame($item->id, $row['order_item_id']);
        $this->assertSame($ledger->pagadoOnline, $row['paid_online_cents'], 'lo pagado que respalda la reserva, según el ledger');
        $this->assertGreaterThan(0, $row['paid_online_cents']);
        $this->assertSame($ledger->pendientePuerta, $row['pending_gate_cents']);
        $this->assertSame(500, $row['pending_gate_cents'], 'lo pendiente EN PUERTA: si el empleado no lo ve, el negocio no cobra');
        $this->assertSame($ledger->cobroMetodo, $row['charge_method'], 'el método con la palabra del ledger («desk» para efectivo/datáfono)');
        $this->assertSame('desk', $row['charge_method']);
        $this->assertSame(['2 × Calcetines'], $row['addons']);
        $this->assertNotNull($row['paid_at']);
        $this->assertSame('10:00–11:00', $row['time_window']);
    }

    // ─── Menores: NOMBRE de pila, edad y exención — JAMÁS los apellidos (§4.6, A·7 · `#236`) ──

    /**
     * ⚠️⚠️ **Este caso cambió de regla en `#236`, y la de antes está aquí escrita a propósito.**
     *
     * Nació exigiendo que el nombre de un menor **no** llegara a la ficha de puerta (minimización,
     * `#142`). El owner lo revirtió por un motivo operativo que esa versión no resolvía: con tres
     * niños y una firma que falta, «7 años ✗» **no dice a cuál**.
     *
     * ▶ Lo que ahora vigila es el recorte que SÍ sigue en pie: **los apellidos no llegan nunca**.
     * Por eso el menor se declara con un apellido imposible de confundir y se exige que ese
     * apellido no aparezca en ninguna parte del JSON de la ficha. Si alguien añade `surname` al
     * DTO «ya que estamos», este caso cae.
     */
    public function test_minors_travel_with_their_first_name_but_never_their_surname(): void
    {
        $holder = $this->holder();
        $lucas = app(DependentRegistry::class)->add($holder, 'Lucas', '2017-03-12', 'Zorrocotroco Único', 'mother'); // 9 hoy
        $vera = app(DependentRegistry::class)->add($holder, 'Vilma', '2019-11-02', 'Retamocho Raro', 'father');       // 6, sin firma
        $this->signFor($holder, $lucas);
        [$order, [$item]] = $this->paidOrder($holder, [[$this->entry, 2, self::TODAY], [$this->pack, 4, self::TODAY]]);
        $this->mode('externo');
        $outcome = app(DependentAssigner::class)->assign($holder, $order->id, [
            ['index' => 0, 'product_id' => $this->entry->id, 'date' => self::TODAY, 'quantity' => 2, 'dependent_ids' => [$lucas->id, $vera->id]],
            ['index' => 1, 'product_id' => $this->pack->id, 'date' => self::TODAY, 'quantity' => 4, 'dependent_ids' => []],
        ]);
        $this->assertSame(2, $outcome->assigned);
        $this->mode('interno');

        $profile = $this->profile($holder);

        $esperado = [
            ['name' => 'Lucas', 'age' => 9, 'waiver' => 'current'],
            ['name' => 'Vilma', 'age' => 6, 'waiver' => 'missing'],
        ];
        $this->assertSame($esperado, $profile->dependents);
        $this->assertSame($esperado, $profile->today_reservations[0]['minors']);
        $this->assertSame([], $profile->today_reservations[1]['minors'], 'un pack no lleva menores');

        $json = json_encode($profile->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Zorrocotroco', $json, 'los APELLIDOS de un menor no están en la ficha: no hay campo para ellos');
        $this->assertStringNotContainsString('Retamocho', $json);
        $this->assertStringNotContainsString($holder->email, $json, 'ni el email completo del titular (§4.6)');

        // Fuera del modo interno no hay exención que enseñar — el nombre sí sigue.
        $this->mode('externo');
        $this->assertSame(
            [['name' => 'Lucas', 'age' => 9, 'waiver' => null], ['name' => 'Vilma', 'age' => 6, 'waiver' => null]],
            $this->profile($holder)->dependents,
        );
    }

    public function test_waiver_card_and_visit_states(): void
    {
        $holder = $this->holder();
        $this->signFor($holder);
        $profile = $this->profile($holder);
        $this->assertSame(['enabled' => true, 'signed' => true, 'accepted_on' => self::TODAY, 'outdated' => false], $profile->waiver);
        $this->assertFalse($profile->visitRegisteredToday);

        $this->publish();
        $card = app(CustomerCards::class)->ensureFor($holder);
        app(GateVisits::class)->register($holder, null, CarbonImmutable::parse(self::TODAY));
        $profile = $this->profile($holder);
        $this->assertTrue($profile->waiver['outdated']);
        $this->assertSame(GateProfileData::CARD_ACTIVE, $profile->card);
        $this->assertTrue($profile->visitRegisteredToday);

        $holder->revokeAllAccess();
        $this->assertSame(GateProfileData::CARD_REVOKED, $this->profile($holder)->card, 'tuvo carné y ya no: la puerta dirá «caducado»');
    }

    // ─── Presupuesto (§4.11, PERF-01/02) ──────────────────────────────────────

    public function test_the_composition_runs_a_constant_number_of_queries(): void
    {
        // ⚠️ Los dos fixtures tienen la MISMA FORMA (complementos, firmas, menores asignados) y distinta
        // CARDINALIDAD: Eloquent omite la carga eager de una relación sin filas padre, así que comparar
        // «con complementos» contra «sin complementos» mediría la forma, no el crecimiento.
        $small = $this->holder();
        $kid = app(DependentRegistry::class)->add($small, 'Uno', '2017-03-12');
        $this->signFor($small, $kid);
        [$o1, [$i1]] = $this->paidOrder($small, [[$this->entry, 1, self::TODAY]]);
        OrderItem::create(['order_id' => $o1->id, 'parent_item_id' => $i1->id, 'ticket_type_id' => $this->addon->id, 'quantity' => 1, 'unit_price' => 0, 'seats' => 0]);
        $this->mode('externo');
        app(DependentAssigner::class)->assign($small, $o1->id, [['index' => 0, 'product_id' => $this->entry->id, 'date' => self::TODAY, 'quantity' => 1, 'dependent_ids' => [$kid->id]]]);
        $this->mode('interno');

        $big = $this->holder();
        $kids = [];
        foreach (['A', 'B', 'C', 'D'] as $i => $n) {
            $kids[] = $k = app(DependentRegistry::class)->add($big, "Menor {$n}", '201'.(5 + $i).'-03-12');
            $this->signFor($big, $k);
        }
        [$o2, [$a, $b, $c]] = $this->paidOrder($big, [[$this->entry, 2, self::TODAY], [$this->entry, 2, '2026-09-04'], [$this->pack, 4, '2026-09-06']]);
        foreach ([$a, $b] as $item) {
            OrderItem::create(['order_id' => $o2->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $this->addon->id, 'quantity' => 1, 'unit_price' => 0, 'seats' => 0]);
        }
        $this->mode('externo');
        app(DependentAssigner::class)->assign($big, $o2->id, [
            ['index' => 0, 'product_id' => $this->entry->id, 'date' => self::TODAY, 'quantity' => 2, 'dependent_ids' => [$kids[0]->id, $kids[1]->id]],
            ['index' => 1, 'product_id' => $this->entry->id, 'date' => '2026-09-04', 'quantity' => 2, 'dependent_ids' => [$kids[2]->id, $kids[3]->id]],
            ['index' => 2, 'product_id' => $this->pack->id, 'date' => '2026-09-06', 'quantity' => 4, 'dependent_ids' => []],
        ]);
        $this->mode('interno');
        WaiverSettings::mode();

        $count = function (User $holder): int {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->profile($holder);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $forSmall = $count($small);
        $forBig = $count($big);
        $this->assertSame($forSmall, $forBig, "el presupuesto no crece con reservas ni menores ({$forSmall} frente a {$forBig})");
        // Medido: 27 — las reservas con sus cargas eager (14: la T3 sumó cuatro LOTES —
        // `order.items.{slot,ticketType,parent.slot}` y `adjustments.orderItem.ticketType`— a cambio
        // de quitar las consultas POR AJUSTE de `breakdownLabel`/`isFinishedInPractice`, que sí
        // crecían con las filas; `MixedPartyParkSurfacesTest` vigila esa mitad), asignaciones y
        // menores (2), sus firmas (3), el waiver del titular (2), el carné (2), los menores a cargo
        // con sus firmas (3) y la visita (1).
        $this->assertLessThanOrEqual(28, $forSmall, 'una ficha compuesta, no un escaneo que dispara decenas de consultas');
    }
}
