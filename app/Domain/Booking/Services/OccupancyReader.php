<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * **Lo que PASÓ en cada punto de la rejilla, por lotes** (`docs/specs/analitica-para-decidir.md` §4.8.ter, la T2 de la
 * analítica; `DECISIONES #758`): cuántas plazas y cuántas fiestas hubo en cada franja de un tramo de días, con su aforo.
 * Es el lector del cuadro «Ocupación»; no decide nada de la venta.
 *
 * ⚠️⚠️ **La aritmética es la de los dos contadores del aforo, copiada a propósito y atada por paridad**:
 *  · las ENTRADAS, la de `SlotAvailability::occupancyMap()`: cada línea viva que ocupa plazas en la zona (con su
 *    `duration_min + extra_minutes`; nula = hasta el cierre) cuenta en CADA franja cuyo inicio cae en su tramo
 *    (`AFORO-12`: la rejilla se solapa y el aforo cuenta PRESENCIA);
 *  · las FIESTAS, la de `PackAvailability::occupancyMaps()`: cada línea de pack cuenta en las franjas de su ventana, con
 *    la preparación si la zona la cuenta.
 * Aquellos cuentan una zona y un día por llamada (dos consultas cada vez), que es lo que necesita la venta; un mes de
 * cuadro serían cientos. Éste lee el tramo entero en CUATRO consultas. No se reutiliza su código porque viven en el
 * `CRITICAL_RE` y hacerlo exigiría tocarlos: lo que impide que diverjan es `OccupancyReaderParityTest`, que compara punto
 * a punto con los dos contadores sobre la rejilla solapada, la duración ilimitada, la hora extra, la preparación y las
 * líneas canceladas.
 *
 * Diferencias DECLARADAS con los contadores de la venta: solo cuentan los pedidos PAGADOS —ni pendientes ni cestas: es lo
 * que pasó, no lo que se retiene— y los topes de fiestas se leen de `PackAvailability` (su resolución por zona y ajuste).
 */
final class OccupancyReader
{
    public function __construct(private readonly PackAvailability $packs) {}

    /**
     * Cada punto de la rejilla de `[$from, $to]` (fechas `Y-m-d`, hora de pared) con su aforo y lo que hubo en él.
     *
     * @return list<array{zone_id: int, date: string, start: string, minutes: int, capacity: int, online_capacity: int, closed: bool, entry_zone: bool, pack_zone: bool, seats: int, parties: int, guests: int, max_parties: int, max_guests: int, last_paid_at: ?string}>
     */
    public function points(string $from, string $to): array
    {
        $slots = DB::table('slots')
            ->whereBetween('date', [$from, $to])
            ->orderBy('zone_id')->orderBy('date')->orderBy('start_time')
            ->get(['zone_id', 'date', 'start_time', 'end_time', 'capacity', 'online_capacity', 'status']);
        if ($slots->isEmpty()) {
            return [];
        }

        $zones = $this->zones();
        $lines = $this->occupyingLines($from, $to);

        // Los inicios de cada zona y día: definen las franjas válidas de un tramo (como `occupancyMap()`).
        $starts = [];
        foreach ($slots as $slot) {
            $starts[$slot->zone_id.'|'.substr((string) $slot->date, 0, 10)][] = (string) $slot->start_time;
        }

        $seats = [];
        $parties = [];
        $guests = [];
        $lastPaid = [];
        foreach ($lines as $line) {
            $key = $line->zone_id.'|'.substr((string) $line->date, 0, 10);
            $dayStarts = $starts[$key] ?? [];
            $entry = (string) $line->entry_start;
            $duration = $line->duration_min === null ? null : (int) $line->duration_min;

            // Entradas (`occupancyMap()`): [inicio, inicio + duración), o hasta el cierre si la duración es nula.
            $end = $duration ? SlotAvailability::spanEnd($entry, $duration) : null;
            foreach ($dayStarts as $start) {
                if ($start < $entry || ($end !== null && $start >= $end)) {
                    continue;
                }
                $seats[$key][$start] = ($seats[$key][$start] ?? 0) + (int) $line->seats;
                if ((int) $line->seats > 0) {
                    $lastPaid[$key][$start] = max($lastPaid[$key][$start] ?? '', (string) $line->paid_at);
                }
            }

            // Fiestas (`occupancyMaps()`): la ventana del pack, con la preparación si la zona la cuenta.
            if ($line->type === TicketType::TYPE_PACK) {
                $zone = $zones[(int) $line->zone_id] ?? null;
                [$windowStart, $windowEnd] = self::partyWindow($entry, (int) $line->prep_before_min, $duration, (int) $line->prep_after_min, $zone['prep_blocks'] ?? true);
                foreach ($dayStarts as $start) {
                    if ($start < $windowStart || $start >= $windowEnd) {
                        continue;
                    }
                    $parties[$key][$start] = ($parties[$key][$start] ?? 0) + 1;
                    $guests[$key][$start] = ($guests[$key][$start] ?? 0) + (int) $line->seats;
                }
            }
        }

        $out = [];
        $count = $slots->count();
        foreach ($slots->values() as $i => $slot) {
            $day = substr((string) $slot->date, 0, 10);
            $key = $slot->zone_id.'|'.$day;
            $start = (string) $slot->start_time;
            $next = $slots[$i + 1] ?? null;
            // Los minutos que REPRESENTA el punto: hasta el siguiente inicio de la misma zona y día, o hasta su fin.
            $until = $next !== null && $i + 1 < $count && (int) $next->zone_id === (int) $slot->zone_id && substr((string) $next->date, 0, 10) === $day
                ? (string) $next->start_time
                : (string) $slot->end_time;
            $zone = $zones[(int) $slot->zone_id] ?? ['entry' => false, 'pack' => false, 'max_parties' => 0, 'max_guests' => 0, 'prep_blocks' => true];

            $out[] = [
                'zone_id' => (int) $slot->zone_id,
                'date' => $day,
                'start' => $start,
                'minutes' => max(0, (int) round((strtotime('1970-01-01 '.$until.' UTC') - strtotime('1970-01-01 '.$start.' UTC')) / 60)),
                'capacity' => (int) $slot->capacity,
                'online_capacity' => (int) $slot->online_capacity,
                'closed' => $slot->status === Slot::STATUS_CLOSED,
                'entry_zone' => $zone['entry'],
                'pack_zone' => $zone['pack'],
                'seats' => $seats[$key][$start] ?? 0,
                'parties' => $parties[$key][$start] ?? 0,
                'guests' => $guests[$key][$start] ?? 0,
                'max_parties' => $zone['max_parties'],
                'max_guests' => $zone['max_guests'],
                'last_paid_at' => ($lastPaid[$key][$start] ?? '') === '' ? null : $lastPaid[$key][$start],
            ];
        }

        return $out;
    }

    /**
     * Las líneas PRINCIPALES vivas de pedidos pagados con la visita en `[$from, $to]`: lo que se vendió para esas visitas
     * y cuándo se cobró (la anticipación, el ingreso por plaza). El tipo con la regla de `PaidVisits`: un pack es una
     * fiesta, un producto con tramos por volumen es un grupo, lo demás es una entrada.
     *
     * @return list<array{zone_id: int, date: string, start: string, product_id: int, product: string, kind: string, seats: int, charged_cents: int, paid_at: ?string}>
     */
    public function paidLines(string $from, string $to): array
    {
        $locale = app()->getLocale();

        return DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->join('slots as s', 's.id', '=', 'i.slot_id')
            ->join('ticket_types as t', 't.id', '=', 'i.ticket_type_id')
            ->where('o.status', Order::STATUS_PAID)
            ->whereNull('i.cancelled_at')
            ->whereNull('i.parent_item_id')
            ->whereBetween('s.date', [$from, $to])
            ->select(['s.zone_id', 's.date', 's.start_time', 't.id as product_id', 't.name', 't.type', 'i.seats', 'i.quantity', 'i.free_quantity', 'i.unit_price', 'o.paid_at'])
            ->selectRaw('EXISTS (SELECT 1 FROM price_tiers pt WHERE pt.ticket_type_id = t.id) AS tiered')
            ->orderBy('i.id')
            ->get()
            ->map(static function (object $row) use ($locale): array {
                $name = json_decode((string) $row->name, true);

                return [
                    'zone_id' => (int) $row->zone_id,
                    'date' => substr((string) $row->date, 0, 10),
                    'start' => (string) $row->start_time,
                    'product_id' => (int) $row->product_id,
                    'product' => is_array($name) ? (string) ($name[$locale] ?? $name['es'] ?? reset($name)) : (string) $row->name,
                    'kind' => $row->type === TicketType::TYPE_PACK ? 'party' : ((bool) $row->tiered ? 'group' : 'entry'),
                    'seats' => (int) $row->seats,
                    // `OrderItem::chargedSubtotalCents()`: las unidades cobradas por su precio (las incluidas, gratis).
                    'charged_cents' => max(0, (int) $row->quantity - (int) $row->free_quantity) * (int) $row->unit_price,
                    'paid_at' => $row->paid_at === null ? null : (string) $row->paid_at,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Las líneas que OCUPAN algo en el tramo: vivas, de pedidos pagados, con franja; con lo que el aforo necesita.
     *
     * @return list<object>
     */
    private function occupyingLines(string $from, string $to): array
    {
        return DB::table('order_items as i')
            ->join('slots as s', 's.id', '=', 'i.slot_id')
            ->join('ticket_types as t', 't.id', '=', 'i.ticket_type_id')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->where('o.status', Order::STATUS_PAID)
            ->whereNull('i.cancelled_at')
            ->whereBetween('s.date', [$from, $to])
            ->select(['s.zone_id', 's.date', 's.start_time as entry_start', 'i.seats', 't.type', 't.prep_before_min', 't.prep_after_min', 'o.paid_at'])
            ->selectRaw('(t.duration_min + i.extra_minutes) as duration_min')
            ->get()
            ->all();
    }

    /**
     * Por zona: si vende entradas, si vende packs, sus topes de fiesta y si la preparación cuenta (la resolución de zona
     * y ajuste es la de `PackAvailability`, no una copia).
     *
     * @return array<int, array{entry: bool, pack: bool, max_parties: int, max_guests: int, prep_blocks: bool}>
     */
    private function zones(): array
    {
        $types = DB::table('ticket_types')->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK])
            ->whereNotNull('zone_id')->select(['zone_id', 'type'])->distinct()->get();

        $out = [];
        foreach (Zone::query()->get() as $zone) {
            $id = (int) $zone->getKey();
            $out[$id] = [
                'entry' => $types->contains(static fn (object $t): bool => (int) $t->zone_id === $id && $t->type === TicketType::TYPE_ENTRY),
                'pack' => $types->contains(static fn (object $t): bool => (int) $t->zone_id === $id && $t->type === TicketType::TYPE_PACK),
                'max_parties' => $this->packs->maxPartiesPerSlot($zone),
                'max_guests' => $this->packs->maxGuestsPerSlot($zone),
                'prep_blocks' => $this->packs->prepBlocksCupo($zone),
            ];
        }

        return $out;
    }

    /**
     * La ventana `[inicio, fin)` de una fiesta: su duración (60 si no la tiene) y, si el cupo cuenta la preparación, el
     * montaje antes y la limpieza después; acotada al día. **La misma aritmética que `PackAvailability::window()`**
     * (privada allí): la paridad la vigila `OccupancyReaderParityTest`.
     *
     * @return array{0: string, 1: string}
     */
    public static function partyWindow(string $start, int $prepBefore, ?int $durationMin, int $prepAfter, bool $prepBlocks): array
    {
        $before = $prepBlocks ? $prepBefore : 0;
        $after = $prepBlocks ? $prepAfter : 0;
        $duration = (int) ($durationMin ?? 60);

        $startC = Carbon::parse($start)->setMicrosecond(0);
        $beforeC = $startC->copy()->subMinutes($before);
        $endC = $startC->copy()->addMinutes($duration + $after);

        return [
            $beforeC->toDateString() !== $startC->toDateString() ? '00:00:00' : $beforeC->format('H:i:s'),
            $endC->toDateString() !== $startC->toDateString() ? '24:00:00' : $endC->format('H:i:s'),
        ];
    }
}
