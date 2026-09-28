<?php

namespace App\Filament\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * **QUIÉN VIENE** (TP·2 de `specs/analitica-para-decidir.md` §4.14, `DECISIONES #792`): la edad de quien reserva, cuántos hijos
 * declara y de qué edad, quién los declara, con quién viene, y de las fiestas, la edad de quien cumple y la de sus invitados.
 *
 * **Quién viene = los titulares con una reserva PAGADA cuyo DÍA DE VISITA cae en el periodo**: la regla de
 * `OrderItem::paidScheduledPrincipal()` (línea principal, no cancelada, con franja, pedido pagado) escrita en SQL, la base de
 * «Ocupación»; cada persona UNA vez, con la edad de su primer día de visita del periodo (`AudienceReportTest` la compara con el
 * alcance). Las fiestas, por el día de la fiesta, con la regla de `PartiesReport`.
 *
 * ⚠️⚠️ **Sin persona** (`RGPD-07`): solo recuentos, y **ninguna celda de 1 a 4** (`SurveysReport::MIN_CELL`): en un reparto
 * ordenado se funde con la vecina («25–44»), en uno de categorías va a «Otros», y con menos de cinco con dato la tabla no se
 * reparte. Cada tabla dice «de N con dato»: no declarar no es no tener.
 */
final class AudienceReport
{
    public const CACHE_SECONDS = 300;

    public const MIN_CELL = SurveysReport::MIN_CELL;

    /** Los tramos de edad de quien reserva: `[desde, hasta]`, el último sin tope. */
    public const ADULT_BRACKETS = [[18, 24], [25, 34], [35, 44], [45, 54], [55, null]];

    /** Los tramos de edad de los hijos, de tres en tres: por debajo de los 18 (los que ya los cumplieron no cuentan). */
    public const CHILD_BRACKETS = [[0, 2], [3, 5], [6, 8], [9, 11], [12, 14], [15, 17]];

    /** Cuántos hijos ha declarado: uno, dos, tres o más. */
    public const KIDS_BRACKETS = [[1, 1], [2, 2], [3, null]];

    /** Quién declara cuando sus menores no dicen lo mismo, y cuando ninguno lo dice. */
    public const RELATIONSHIP_MIXED = 'mixed';

    public const RELATIONSHIP_UNKNOWN = 'unknown';

    /** La categoría donde van las celdas pequeñas de un reparto por categorías. */
    public const OTHER = 'other';

    public const WITH_MINORS = 'with_minors';

    public const NO_DATA = 'no_data';

    /** @return array<string, mixed> */
    public static function for(Window $window): array
    {
        return Cache::remember(self::cacheKey($window), self::CACHE_SECONDS, fn (): array => (new self)->compute($window));
    }

    public static function cacheKey(Window $window): string
    {
        return 'analytics:audience:v1:'.$window->timezone.':'.$window->dateFrom().':'.$window->dateTo();
    }

    /** @return array<string, mixed> */
    public function compute(Window $window): array
    {
        $lines = $this->visits($window);

        /** @var array<int, string> $firstDay la persona y su primer día de visita del periodo */
        $firstDay = [];
        foreach ($lines as $line) {
            $day = substr((string) $line->date, 0, 10);
            $user = (int) $line->user_id;
            $firstDay[$user] = isset($firstDay[$user]) ? min($firstDay[$user], $day) : $day;
        }

        $holders = array_keys($firstDay);
        $minors = $this->minorsOf($firstDay);

        return [
            'holders' => $this->holderAges($firstDay),
            'kids_count' => $this->kidsCount($holders, $minors),
            'kids_ages' => self::ranged($minors->count(), $minors->pluck('age')->all(), self::CHILD_BRACKETS),
            'declared_by' => $this->declaredBy($minors),
            'company' => $this->company($lines),
            ...$this->parties($window),
        ];
    }

    // ─── Quién reserva ───────────────────────────────────────────────────────────────────────────

    /**
     * Las líneas que VIENEN en el periodo: la regla de `OrderItem::paidScheduledPrincipal()` + `slotDateBetween()`, en SQL.
     *
     * @return Collection<int, \stdClass>
     */
    private function visits(Window $window): Collection
    {
        return DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->join('slots as sl', 'sl.id', '=', 'i.slot_id')
            ->whereNull('i.parent_item_id')
            ->whereNull('i.cancelled_at')
            ->where('o.status', Order::STATUS_PAID)
            ->whereNotNull('o.user_id')
            ->whereBetween('sl.date', [$window->dateFrom(), $window->dateTo()])
            ->get(['i.id', 'i.ticket_type_id', 'o.user_id', 'sl.date']);
    }

    /**
     * La edad de quien reserva el día de su primera visita del periodo, por tramos.
     *
     * @param  array<int, string>  $firstDay
     * @return array{of: int, with_data: int, rows: list<array{from: int, to: ?int, count: int}>}
     */
    private function holderAges(array $firstDay): array
    {
        $bornOn = $firstDay === [] ? [] : DB::table('users')->whereIn('id', array_keys($firstDay))->whereNotNull('born_on')->pluck('born_on', 'id')->all();

        $ages = [];
        foreach ($bornOn as $id => $date) {
            $ages[] = Dependent::ageBetween(substr((string) $date, 0, 10), CarbonImmutable::parse($firstDay[(int) $id]));
        }

        return self::ranged(count($firstDay), $ages, self::ADULT_BRACKETS);
    }

    // ─── Sus hijos ───────────────────────────────────────────────────────────────────────────────

    /**
     * Los HIJOS MENORES de esas personas el día de su primera visita del periodo: los activos (`Dependent::active()`), ya
     * nacidos y con menos de 18 ese día. Uno declarado que ya los cumplió no es un niño que viene.
     *
     * @param  array<int, string>  $firstDay
     * @return Collection<int, array{user_id: int, age: int, relationship: ?string}>
     */
    private function minorsOf(array $firstDay): Collection
    {
        if ($firstDay === []) {
            return collect();
        }

        return DB::table('dependents')
            ->whereIn('user_id', array_keys($firstDay))
            ->whereNull('removed_at')
            ->get(['user_id', 'born_on', 'relationship'])
            ->map(function (\stdClass $d) use ($firstDay): ?array {
                $born = substr((string) $d->born_on, 0, 10);
                $day = $firstDay[(int) $d->user_id];
                $age = $born > $day ? null : Dependent::ageBetween($born, CarbonImmutable::parse($day));

                return $age === null || $age >= Dependent::ADULT_AGE ? null
                    : ['user_id' => (int) $d->user_id, 'age' => $age, 'relationship' => is_string($d->relationship) ? $d->relationship : null];
            })
            ->filter()
            ->values();
    }

    /**
     * Cuántos hijos menores ha declarado cada persona que viene: 1, 2, 3 o más. Quien no ha declarado ninguno queda fuera
     * del reparto y dentro del «de N»: no declarar no es no tener.
     *
     * @param  list<int>  $holders
     * @param  Collection<int, array{user_id: int, age: int, relationship: ?string}>  $minors
     * @return array{of: int, with_data: int, rows: list<array{from: int, to: ?int, count: int}>}
     */
    private function kidsCount(array $holders, Collection $minors): array
    {
        $perHolder = $minors->countBy('user_id')->values()->map(fn ($n): int => (int) $n)->all();

        return self::ranged(count($holders), $perHolder, self::KIDS_BRACKETS);
    }

    /**
     * Quién declara, por PERSONA: el parentesco de sus menores si todos dicen el mismo, «varios» si no, y «sin dato» si
     * ninguno lo dice (las fichas de antes de `#236`).
     *
     * @param  Collection<int, array{user_id: int, age: int, relationship: ?string}>  $minors
     * @return array{of: int, with_data: int, rows: list<array{key: string, count: int}>, unknown: int}
     */
    private function declaredBy(Collection $minors): array
    {
        $counts = [];
        foreach ($minors->groupBy('user_id') as $own) {
            $said = $own->pluck('relationship')->filter(fn ($r): bool => is_string($r) && $r !== '')->unique()->values();
            $key = match ($said->count()) {
                0 => self::RELATIONSHIP_UNKNOWN,
                1 => in_array($said[0], Dependent::RELATIONSHIPS, true) ? (string) $said[0] : Dependent::RELATIONSHIPS[4],
                default => self::RELATIONSHIP_MIXED,
            };
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $of = array_sum($counts);
        $unknown = $counts[self::RELATIONSHIP_UNKNOWN] ?? 0;
        unset($counts[self::RELATIONSHIP_UNKNOWN]);

        return self::categorized($of, $counts, [...Dependent::RELATIONSHIPS, self::RELATIONSHIP_MIXED]) + ['unknown' => $unknown];
    }

    // ─── Con quién viene ─────────────────────────────────────────────────────────────────────────

    /**
     * Por RESERVA: con menores si lleva una entrada asignada a un menor o su producto es solo para menores (`#825`); si no, no
     * se sabe y NO se adivina.
     *
     * @param  Collection<int, \stdClass>  $lines
     * @return array{of: int, with_data: int}
     */
    private function company(Collection $lines): array
    {
        $ids = $lines->pluck('id')->all();
        $assigned = $ids === [] ? [] : array_flip(DB::table('dependent_assignments')->whereIn('order_item_id', $ids)->distinct()->pluck('order_item_id')->all());
        $minorsOnly = $lines->isEmpty() ? [] : TicketType::query()->whereIn('id', $lines->pluck('ticket_type_id')->unique()->all())->get()
            ->filter(fn (TicketType $t): bool => $t->onlyGuestsUnder(Dependent::ADULT_AGE))->modelKeys();

        $withMinors = $lines->filter(fn ($l): bool => isset($assigned[$l->id]) || in_array((int) $l->ticket_type_id, $minorsOnly, true))->count();

        // Dos grupos (con menores · sin dato) y no un reparto: la pantalla dice «menos de 5» en vez de un recuento de 1 a 4.
        return ['of' => $lines->count(), 'with_data' => $withMinors];
    }

    // ─── Las fiestas ─────────────────────────────────────────────────────────────────────────────

    /**
     * La edad de quien cumple (la de su invitación, o si no la hay, la que se declaró al reservar, por su TIPO de campo) y la
     * de sus invitados (cada ficha, con la clave de edad del pack y su mismo saneo; sin la fila de quien cumple), por año.
     * Las fiestas del periodo, con la regla de `PartiesReport`: pack con formulario, pagado, no cancelado, por el día de la fiesta.
     *
     * @return array{honorees: array<string, mixed>, guests: array<string, mixed>}
     */
    private function parties(Window $window): array
    {
        $parties = OrderItem::query()
            ->with(['ticketType', 'partyInvitation'])
            ->whereNull('parent_item_id')
            ->whereNull('cancelled_at')
            ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PAID))
            ->whereHas('ticketType', fn ($q) => $q->where('type', TicketType::TYPE_PACK)->whereNotNull('guest_fields')->whereNotIn('guest_fields', ['[]', 'null', '']))
            ->whereHas('slot', fn ($q) => $q->whereBetween('date', [$window->dateFrom(), $window->dateTo()]))
            ->get();

        $honorees = [];
        $guests = [];
        $guestRows = 0;
        foreach ($parties as $party) {
            $type = $party->ticketType;
            if (! $type instanceof TicketType) {
                continue;
            }

            $age = $party->partyInvitation->honoree_age ?? self::celebrantAge($party, $type);
            if ($age !== null) {
                $honorees[] = $age;
            }

            $key = $type->guestAgeFieldKey();
            $quantity = max(0, (int) $party->quantity);
            $rows = $type->sanitizeGuestData($party->guestData(), $quantity);
            foreach ($rows as $i => $row) {
                if ($i === 0 && $party->hasHonoreeRow()) {
                    continue;
                }
                $guestRows++;
                $raw = $key === null ? null : ($row[$key] ?? null);
                if (is_numeric($raw)) {
                    $guests[] = (int) $raw;
                }
            }
        }

        return [
            'honorees' => self::yearly($parties->count(), $honorees),
            'guests' => self::yearly($guestRows, $guests),
        ];
    }

    /** La edad declarada al reservar, leída por TIPO y saneada (la de `PartyInvitations`). */
    private static function celebrantAge(OrderItem $party, TicketType $type): ?int
    {
        $key = $type->celebrantAgeFieldKey();
        if ($key === null) {
            return null;
        }

        $value = $type->sanitizeEventData($party->event_data ?? [], TicketType::EVENT_STAGE_BOOKING)[$key] ?? null;

        return is_numeric($value) ? max(0, min(255, (int) $value)) : null;
    }

    // ─── Los repartos, sin celdas pequeñas ───────────────────────────────────────────────────────

    /**
     * Un reparto por TRAMOS: cuántos caen en cada uno, y las celdas de 1 a 4 fundidas con su vecina.
     *
     * @param  list<int>  $values
     * @param  list<array{0: int, 1: ?int}>  $brackets
     * @return array{of: int, with_data: int, rows: list<array{from: int, to: ?int, count: int}>}
     */
    public static function ranged(int $of, array $values, array $brackets): array
    {
        $buckets = array_map(static fn (array $b): array => ['from' => $b[0], 'to' => $b[1], 'count' => 0], $brackets);
        $counted = 0;
        foreach ($values as $v) {
            foreach ($buckets as $i => $b) {
                if ($v >= $b['from'] && ($b['to'] === null || $v <= $b['to'])) {
                    $buckets[$i]['count']++;
                    $counted++;
                    break;
                }
            }
        }

        return ['of' => $of, 'with_data' => $counted, 'rows' => $counted < self::MIN_CELL ? [] : self::foldRanges($buckets)];
    }

    /**
     * Un reparto AÑO A AÑO (las edades de las fiestas), del menor al mayor que haya, con los huecos a cero para que fundir
     * dé tramos seguidos.
     *
     * @param  list<int>  $ages
     * @return array{of: int, with_data: int, rows: list<array{from: int, to: ?int, count: int}>}
     */
    public static function yearly(int $of, array $ages): array
    {
        if ($ages === []) {
            return ['of' => $of, 'with_data' => 0, 'rows' => []];
        }

        $years = range(min($ages), max($ages));

        return self::ranged($of, $ages, array_map(static fn (int $y): array => [$y, $y], $years));
    }

    /**
     * Funde las celdas de 1 a 4 con la VECINA: un grupo se cierra al llegar a cinco (o si es un cero solo), y un resto pequeño
     * al final se funde hacia atrás hasta llegar a cinco. Un cero no delata a nadie y se queda como está.
     *
     * @param  list<array{from: int, to: ?int, count: int}>  $buckets
     * @return list<array{from: int, to: ?int, count: int}>
     */
    public static function foldRanges(array $buckets): array
    {
        $merge = static fn (array $a, array $b): array => ['from' => $a['from'], 'to' => $b['to'], 'count' => $a['count'] + $b['count']];
        $out = [];
        $open = null;

        foreach ($buckets as $b) {
            $open = $open === null ? $b : $merge($open, $b);
            if ($open['count'] === 0 || $open['count'] >= self::MIN_CELL) {
                $out[] = $open;
                $open = null;
            }
        }

        while ($open !== null && $open['count'] > 0 && $open['count'] < self::MIN_CELL && $out !== []) {
            $open = $merge(array_pop($out), $open);
        }
        if ($open !== null) {
            $out[] = $open;
        }

        return $out;
    }

    /**
     * Un reparto por CATEGORÍAS, en el orden dado: las de 1 a 4 van a «Otros», y si «Otros» se queda por debajo de cinco se
     * le suma la categoría más pequeña hasta llegar.
     *
     * @param  array<string, int>  $counts
     * @param  list<string>  $order
     * @return array{of: int, with_data: int, rows: list<array{key: string, count: int}>}
     */
    public static function categorized(int $of, array $counts, array $order): array
    {
        $withData = array_sum($counts);
        if ($withData < self::MIN_CELL) {
            return ['of' => $of, 'with_data' => $withData, 'rows' => []];
        }

        $kept = [];
        $other = 0;
        foreach ($order as $key) {
            $n = $counts[$key] ?? 0;
            if ($n > 0 && $n < self::MIN_CELL) {
                $other += $n;
            } else {
                $kept[$key] = $n;
            }
        }

        // ⚠️ Una categoría que YA se llama «otro» (el parentesco `other` de `Dependent::RELATIONSHIPS`) es la misma fila que
        // «Otros»: dos filas con la misma clave dirían dos cosas del mismo grupo.
        if (array_key_exists(self::OTHER, $kept)) {
            $other += $kept[self::OTHER];
            unset($kept[self::OTHER]);
        }

        while ($other > 0 && $other < self::MIN_CELL) {
            $candidates = array_filter($kept, static fn (int $n): bool => $n > 0);
            if ($candidates === []) {
                break;
            }
            $smallest = array_keys($candidates, min($candidates), true)[0];
            $other += $kept[$smallest];
            unset($kept[$smallest]);
        }

        // Una categoría sin nadie no se pinta: en un reparto por categorías no hay hueco que marcar entre vecinas.
        $rows = [];
        foreach ($kept as $key => $n) {
            if ($n > 0) {
                $rows[] = ['key' => (string) $key, 'count' => $n];
            }
        }
        if ($other > 0) {
            $rows[] = ['key' => self::OTHER, 'count' => $other];
        }

        return ['of' => $of, 'with_data' => $withData, 'rows' => $rows];
    }
}
