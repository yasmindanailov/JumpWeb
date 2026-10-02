<?php

namespace Tests\Feature\Surveys;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\PriceTier;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Platform\Contracts\VisitFacts;
use App\Domain\Platform\Models\SurveyResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **Lo grueso de una visita** (`docs/specs/encuestas.md` §4.7, T5; `DECISIONES #754`): lo que la respuesta anónima
 * guarda del cliente sin guardarle a él —¿primera vez?, ¿a qué vino?— y lo que el sello pregunta después —¿volvió?—.
 * «Visita» es una visita acreditada en la puerta O una reserva cobrada (línea principal viva de un pedido pagado) con
 * la fecha de su franja; lo que no está pagado o se canceló no cuenta.
 */
class SurveyVisitFactsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
    }

    private function facts(): VisitFacts
    {
        return app(VisitFacts::class);
    }

    private function product(string $type, bool $tiered = false): TicketType
    {
        $product = TicketType::create([
            'zone_id' => $this->zone->id, 'type' => $type, 'name' => ['es' => Str::ucfirst($type).' '.Str::random(4)],
            'duration_min' => 60, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 100,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        if ($tiered) {
            $rate = RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);
            PriceTier::create(['ticket_type_id' => $product->id, 'rate_type_id' => $rate->id, 'min_qty' => 30, 'amount_cents' => 1500]);
        }

        return $product;
    }

    /** Un pedido del cliente con UNA línea principal en `$day`. */
    private function booked(User $user, TicketType $product, string $day, string $status = Order::STATUS_PAID, bool $cancelledLine = false): void
    {
        $slot = Slot::query()->firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => $day, 'start_time' => '10:00:00'],
            ['end_time' => '11:00:00', 'capacity' => 100, 'online_capacity' => 100],
        );
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)), 'status' => $status,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);
        $item = $order->items()->create(['ticket_type_id' => $product->id, 'slot_id' => $slot->id, 'quantity' => 1, 'unit_price' => 1000, 'seats' => 1]);
        if ($cancelledLine) {
            $item->forceFill(['cancelled_at' => now()])->save();
        }
    }

    private function visited(User $user, string $day): void
    {
        app(GateVisits::class)->register($user, null, Carbon::parse($day));
    }

    public function test_first_visit_means_no_visit_and_no_paid_reservation_before_that_day(): void
    {
        $entry = $this->product(TicketType::TYPE_ENTRY);
        $nuevo = User::factory()->create();
        $this->visited($nuevo, '2026-09-10');
        $this->booked($nuevo, $entry, '2026-09-20');
        $this->assertTrue($this->facts()->isFirstVisit($nuevo->id, '2026-09-10'), 'la visita de ESE día y una reserva posterior no cuentan');

        $habitual = User::factory()->create();
        $this->visited($habitual, '2026-08-01');
        $this->assertFalse($this->facts()->isFirstVisit($habitual->id, '2026-09-10'), 'vino antes por la puerta');

        $compro = User::factory()->create();
        $this->booked($compro, $entry, '2026-09-01');
        $this->assertFalse($this->facts()->isFirstVisit($compro->id, '2026-09-10'), 'tenía una reserva cobrada antes');

        $sinPagar = User::factory()->create();
        $this->booked($sinPagar, $entry, '2026-09-01', Order::STATUS_PENDING);
        $this->booked($sinPagar, $entry, '2026-09-02', Order::STATUS_PAID, cancelledLine: true);
        $this->assertTrue($this->facts()->isFirstVisit($sinPagar->id, '2026-09-10'), 'ni una cesta sin pagar ni una línea cancelada son una visita');
    }

    public function test_the_kind_of_the_visit_is_what_was_paid_for_that_day(): void
    {
        $entry = $this->product(TicketType::TYPE_ENTRY);
        $pack = $this->product(TicketType::TYPE_PACK);
        $excursion = $this->product(TicketType::TYPE_ENTRY, tiered: true);
        $user = User::factory()->create();

        $this->assertSame(SurveyResponse::KIND_OTHER, $this->facts()->kindOn($user->id, '2026-09-10'), 'nada cobrado para ese día');

        $this->booked($user, $entry, '2026-09-10');
        $this->assertSame(SurveyResponse::KIND_ENTRY, $this->facts()->kindOn($user->id, '2026-09-10'));

        $this->booked($user, $excursion, '2026-09-11');
        $this->assertSame(SurveyResponse::KIND_GROUP, $this->facts()->kindOn($user->id, '2026-09-11'), 'con tramos por volumen: un grupo');

        $this->booked($user, $excursion, '2026-09-12');
        $this->booked($user, $pack, '2026-09-12');
        $this->assertSame(SurveyResponse::KIND_PARTY, $this->facts()->kindOn($user->id, '2026-09-12'), 'la fiesta manda sobre el grupo');

        $this->booked($user, $pack, '2026-09-13', Order::STATUS_PENDING);
        $this->assertSame(SurveyResponse::KIND_OTHER, $this->facts()->kindOn($user->id, '2026-09-13'), 'sin pagar no cuenta');
    }

    public function test_the_first_return_is_the_first_visit_or_paid_day_strictly_after_and_up_to_the_limit(): void
    {
        $entry = $this->product(TicketType::TYPE_ENTRY);
        $user = User::factory()->create();
        $this->visited($user, '2026-09-10');
        $this->visited($user, '2026-09-30');
        $this->booked($user, $entry, '2026-09-25');

        $this->assertSame('2026-09-25', $this->facts()->firstReturn($user->id, '2026-09-10', '2026-12-09'), 'la reserva cobrada va antes que la visita del 30');
        $this->assertSame('2026-09-30', $this->facts()->firstReturn($user->id, '2026-09-25', '2026-12-09'), 'el propio día NO es volver');
        $this->assertNull($this->facts()->firstReturn($user->id, '2026-09-10', '2026-09-24'), 'fuera del tramo no cuenta');
        $this->assertSame('2026-09-25', $this->facts()->firstReturn($user->id, '2026-09-10', '2026-09-25'), 'el último día del tramo sí');
    }

    /**
     * `#819`: el PRIMER día de cada cliente, por conjuntos —el menor entre su primera visita acreditada y su primer día
     * cobrado—, para la tasa de una encuesta «solo primera visita» sin una consulta por visita. Tiene que decir lo mismo que
     * `isFirstVisit()` cliente a cliente: una visita del día D es la primera si y solo si D es su primer día.
     */
    public function test_the_first_visit_days_agree_with_the_first_visit_rule(): void
    {
        $entry = $this->product(TicketType::TYPE_ENTRY);

        $soloPuerta = User::factory()->create();
        $this->visited($soloPuerta, '2026-09-10');
        $this->visited($soloPuerta, '2026-09-20');

        $compro = User::factory()->create();
        $this->booked($compro, $entry, '2026-09-01');
        $this->visited($compro, '2026-09-10');

        $reservaDespues = User::factory()->create();
        $this->visited($reservaDespues, '2026-09-10');
        $this->booked($reservaDespues, $entry, '2026-09-30');

        $sinPagar = User::factory()->create();
        $this->booked($sinPagar, $entry, '2026-09-01', Order::STATUS_PENDING);
        $this->visited($sinPagar, '2026-09-10');

        $nada = User::factory()->create();

        $first = $this->facts()->firstVisitDays([$soloPuerta->id, $compro->id, $reservaDespues->id, $sinPagar->id, $nada->id, $soloPuerta->id]);

        $this->assertSame('2026-09-10', $first[$soloPuerta->id]);
        $this->assertSame('2026-09-01', $first[$compro->id], 'el día cobrado va antes que la primera visita');
        $this->assertSame('2026-09-10', $first[$reservaDespues->id], 'una reserva posterior no adelanta nada');
        $this->assertSame('2026-09-10', $first[$sinPagar->id], 'una cesta sin pagar no es un día');
        $this->assertArrayNotHasKey($nada->id, $first, 'sin visitas ni días cobrados, no sale');

        foreach ([[$soloPuerta, '2026-09-10'], [$soloPuerta, '2026-09-20'], [$compro, '2026-09-10'], [$reservaDespues, '2026-09-10'], [$sinPagar, '2026-09-10']] as [$user, $day]) {
            $this->assertSame($this->facts()->isFirstVisit($user->id, $day), $first[$user->id] === $day, "la regla y el conjunto discrepan para el {$day}");
        }
        $this->assertSame([], $this->facts()->firstVisitDays([]));
    }
}
