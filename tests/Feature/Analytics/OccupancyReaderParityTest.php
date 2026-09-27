<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OccupancyReader;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **El lector del cuadro cuenta como el aforo** (`docs/specs/analitica-para-decidir.md` §4.8.ter, la T2; `#758`).
 * `OccupancyReader` copia la aritmética de `SlotAvailability::occupancyMap()` y `PackAvailability::occupancyMaps()`
 * (viven en el `CRITICAL_RE`, y el cuadro necesita leer un mes en cuatro consultas y no en cientos). Este test es lo que
 * impide que la copia diverja: punto a punto, sobre la rejilla SOLAPADA (`AFORO-12`), con una entrada de duración
 * ilimitada, una hora extra, un pack con preparación y una línea cancelada, los tres dan lo mismo. Y la diferencia que SÍ
 * es a propósito —el lector no cuenta lo pendiente: es lo que pasó— tiene su propio caso.
 */
class OccupancyReaderParityTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-06-10';

    private Zone $jump;

    private Zone $party;

    private TicketType $entry60;

    private TicketType $unlimited;

    private TicketType $pack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-01 10:00:00', 'Europe/Madrid'));

        $this->jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);
        // La zona de fiestas CUENTA la preparación y lleva sus topes (para no depender del memo de ajustes).
        $this->party = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true,
            'max_per_slot' => 3, 'max_guests_per_slot' => 40, 'prep_blocks_cupo' => true,
        ]);

        // LA REJILLA SOLAPADA: inicios cada 30 min, franjas de 60, de 15:00 a 19:30.
        foreach ([$this->jump->id => 20, $this->party->id => 200] as $zoneId => $capacity) {
            for ($minutes = 0; $minutes <= 210; $minutes += 30) {
                $start = Carbon::parse('15:00')->addMinutes($minutes);
                Slot::create([
                    'zone_id' => $zoneId, 'date' => self::DATE,
                    'start_time' => $start->format('H:i:s'), 'end_time' => $start->copy()->addHour()->format('H:i:s'),
                    'capacity' => $capacity, 'online_capacity' => $capacity,
                ]);
            }
        }

        $this->entry60 = $this->product('Entrada 1 h', $this->jump, TicketType::TYPE_ENTRY, 60);
        $this->unlimited = $this->product('Entrada libre', $this->jump, TicketType::TYPE_ENTRY, null);
        $this->pack = $this->product('Cumpleaños', $this->party, TicketType::TYPE_PACK, 90, prepBefore: 30, prepAfter: 30);
    }

    private function product(string $name, Zone $zone, string $type, ?int $duration, int $prepBefore = 0, int $prepAfter = 0): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'zone_id' => $zone->id, 'type' => $type, 'duration_min' => $duration,
            'prep_before_min' => $prepBefore, 'prep_after_min' => $prepAfter,
            'min_qty' => 1, 'max_qty' => 40, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
    }

    private function sold(TicketType $product, Zone $zone, string $start, int $seats, string $status = Order::STATUS_PAID, int $extraMinutes = 0, bool $cancelled = false): void
    {
        $slot = Slot::query()->where('zone_id', $zone->id)->where('date', self::DATE)->where('start_time', $start)->sole();
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'R-'.Str::upper(Str::random(6)), 'status' => $status,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR',
            'paid_at' => $status === Order::STATUS_PAID ? now() : null,
            'expires_at' => $status === Order::STATUS_PENDING ? now()->addHour() : null,
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $product->id, 'slot_id' => $slot->id, 'quantity' => $seats, 'unit_price' => 1000,
            'seats' => $seats, 'extra_minutes' => $extraMinutes,
        ]);
        if ($cancelled) {
            $item->forceFill(['cancelled_at' => now()])->save();
        }
    }

    /** @return array<string, array<string, int>> zona → inicio → cifra, del lector */
    private function reader(string $field): array
    {
        $out = [];
        foreach (app(OccupancyReader::class)->points(self::DATE, self::DATE) as $point) {
            if ($point[$field] > 0) {
                $out[$point['zone_id']][$point['start']] = $point[$field];
            }
        }

        return $out;
    }

    public function test_the_seats_and_the_parties_match_the_two_capacity_counters_point_by_point(): void
    {
        $this->sold($this->entry60, $this->jump, '15:30:00', 6);                   // pisa 15:30 y 16:00
        $this->sold($this->entry60, $this->jump, '16:00:00', 4, extraMinutes: 30); // hora extra: 16:00, 16:30 y 17:00
        $this->sold($this->unlimited, $this->jump, '17:30:00', 2);                 // ilimitada: hasta el cierre
        $this->sold($this->entry60, $this->jump, '15:00:00', 9, cancelled: true);  // cancelada: no ocupa
        $this->sold($this->pack, $this->party, '16:30:00', 12);                    // 90 min + 30 antes + 30 después
        $this->sold($this->pack, $this->party, '17:00:00', 8);

        $seats = app(SlotAvailability::class)->occupancyMap($this->jump->id, self::DATE);
        [$parties, $guests] = app(PackAvailability::class)->occupancyMaps($this->party->id, self::DATE, [], null, $this->party);
        $partySeats = app(SlotAvailability::class)->occupancyMap($this->party->id, self::DATE);

        ksort($seats);
        ksort($parties);
        ksort($guests);
        ksort($partySeats);
        $this->assertNotSame([], $seats, 'el fixture ocupa algo: si no, la paridad no mide nada');
        $this->assertSame($seats, $this->reader('seats')[$this->jump->id]);
        $this->assertSame($partySeats, $this->reader('seats')[$this->party->id], 'los niños de una fiesta ocupan plazas de su zona, como en el aforo');
        $this->assertSame($parties, $this->reader('parties')[$this->party->id]);
        $this->assertSame($guests, $this->reader('guests')[$this->party->id]);

        // Y lo que el fixture dice escrito a mano, para que un cambio en los DOS lados a la vez tampoco pase.
        $this->assertSame(['15:30:00' => 6, '16:00:00' => 10, '16:30:00' => 4, '17:00:00' => 4, '17:30:00' => 2, '18:00:00' => 2, '18:30:00' => 2], $seats);
        $this->assertSame(['16:00:00' => 1, '16:30:00' => 2, '17:00:00' => 2, '17:30:00' => 2, '18:00:00' => 2, '18:30:00' => 1], $parties);
    }

    /** La diferencia DECLARADA: una cesta pendiente viva retiene plaza para la venta, pero no es algo que PASÓ. */
    public function test_a_live_pending_order_holds_a_seat_for_the_sale_but_is_not_occupancy(): void
    {
        $this->sold($this->entry60, $this->jump, '15:00:00', 5, Order::STATUS_PENDING);

        $this->assertSame(5, app(SlotAvailability::class)->occupancyMap($this->jump->id, self::DATE)['15:00:00']);
        $this->assertSame([], $this->reader('seats'));
    }

    public function test_every_point_carries_its_capacity_its_minutes_and_its_zone_limits(): void
    {
        $points = collect(app(OccupancyReader::class)->points(self::DATE, self::DATE));

        $this->assertCount(16, $points, 'ocho inicios por zona');
        $first = $points->firstWhere('start', '15:00:00');
        $this->assertSame(20, $first['capacity']);
        $this->assertSame(30, $first['minutes'], 'cada punto representa la media hora hasta el siguiente');
        $this->assertSame(60, $points->where('zone_id', $this->jump->id)->last()['minutes'], 'el último, hasta su fin');
        $this->assertTrue($first['entry_zone']);
        $this->assertFalse($first['pack_zone']);
        $party = $points->firstWhere('zone_id', $this->party->id);
        $this->assertTrue($party['pack_zone']);
        $this->assertSame([3, 40], [$party['max_parties'], $party['max_guests']], 'los topes de la zona, resueltos por PackAvailability');
    }
}
