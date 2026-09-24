<?php

namespace App\Filament\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\Translated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * **LA FIESTA: el dinero de después de reservar, el embudo del invitado y los tiempos**
 * (`docs/specs/analitica-fiesta.md` §4.3, T2; `DECISIONES #739`).
 *
 * **La unidad es la RESERVA de un pack con formulario** (`OrderItem::acceptsGuestForm()`: línea principal, no
 * cancelada, de un pedido PAGADO, de un pack con campos de invitado) **y la unidad de tiempo es el DÍA DE LA FIESTA**
 * (`slots.date`), no el del cobro: el circuito se cierra ese día y así un periodo compara fiestas comparables.
 *
 * Tres fuentes que no se pisan:
 *  · **las tablas de negocio** dicen lo que pasó de verdad —`guest_form_completed_at`, `party_invitations`,
 *    `invitation_replies`, `guardian_authorizations`—;
 *  · **el libro del pedido** (`order_adjustments`, tipo `edit`) dice el dinero de después de reservar, por su
 *    `reason`: los extras del post-form y los invitados añadidos o quitados; una edición sin motivo es del PANEL y
 *    va en su propia línea, y lo cobrado EN EL PARQUE sale de `payments` (`cash`, `datafono`) de esos pedidos;
 *  · **los hechos de la reserva** (`analytics_events` con `order_id` y `props.reservation`, sin visitante) dicen lo
 *    de arriba del embudo, que ninguna tabla guarda: aperturas del formulario, de la invitación y del justificante,
 *    el `.ics`, y los días y las horas que tardan.
 *
 * ⚠️ Solo agregados: ningún nombre, ninguna clave de un menor. Una docena de consultas por periodo; la caché de
 * cinco minutos las reparte entre los widgets y el CSV.
 */
final class PartiesReport
{
    public const CACHE_SECONDS = 300;

    public const TOP_ROWS = 12;

    /** Los pasos del embudo por reserva, en orden. */
    public const STEPS = ['parties', 'form_opened', 'form_completed', 'with_extras', 'with_invitation', 'invitation_viewed', 'with_reply', 'authorization_opened', 'signed'];

    /** Los motivos del libro que son EXTRAS vendidos desde el post-form (con signo). */
    public const EXTRA_REASONS = ['postform_addon', 'addon_per_guest_rescale', 'addon_per_guest_rescale_reduction'];

    /** Los motivos del libro que son INVITADOS añadidos o quitados desde el post-form. */
    public const GUEST_REASONS = ['guest_count_increase', 'guest_count_decrease'];

    /** Los proveedores de cobro EN EL PARQUE. */
    public const PARK_PROVIDERS = ['cash', 'datafono'];

    /** Los tramos del histograma de «días antes de la fiesta» al completar el formulario, en orden. */
    public const DAYS_BUCKETS = ['late', 'same_day', 'd1_3', 'd4_7', 'd8_14', 'd15_plus'];

    /** Los hechos de la reserva que este informe lee. */
    private const FACTS = ['guest_form_opened', 'guest_form_submitted', 'invitation_viewed', 'invitation_replied', 'invitation_calendar_downloaded', 'authorization_opened', 'authorization_signed'];

    /** @return array<string, mixed> */
    public static function for(Window $window, Comparison $comparison = Comparison::Previous): array
    {
        $baseline = $comparison->baseline($window);

        return Cache::remember(self::cacheKey($window, $baseline), self::CACHE_SECONDS, fn (): array => (new self)->compute($window, $baseline));
    }

    public static function cacheKey(Window $window, Window $baseline): string
    {
        return 'analytics:parties:v1:'.$window->timezone.':'.$window->dateFrom().':'.$window->dateTo().':'.$baseline->dateFrom().':'.$baseline->dateTo().':'.app()->getLocale();
    }

    /**
     * @param  Window|null  $baseline  con qué se compara; sin ella, el periodo anterior
     * @return array<string, mixed>
     */
    public function compute(Window $window, ?Window $baseline = null): array
    {
        $baseline ??= $window->previous();
        $parties = $this->parties($window);
        $ids = $parties->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $orderIds = $parties->pluck('order_id')->map(static fn ($id): int => (int) $id)->unique()->values()->all();

        $facts = $this->facts($orderIds, $ids);
        $invitations = $this->invitations($ids);
        $replies = $this->replies($ids);
        $signatures = $this->signatures($ids);
        $money = $this->money($ids, $orderIds);
        $timing = $this->timing($window, $parties, $facts, $signatures);

        $count = $parties->count();
        $reached = [
            'parties' => $count,
            'form_opened' => count($facts['by_reservation']['guest_form_opened'] ?? []),
            'form_completed' => $parties->whereNotNull('guest_form_completed_at')->count(),
            'with_extras' => $money['with_extras'],
            'with_invitation' => count($invitations),
            'invitation_viewed' => count($facts['by_reservation']['invitation_viewed'] ?? []),
            'with_reply' => count($replies['by_reservation']),
            'authorization_opened' => count($facts['by_reservation']['authorization_opened'] ?? []),
            'signed' => count($signatures['by_reservation']),
        ];

        return [
            'window' => [
                'from' => $window->dateFrom(),
                'to' => $window->dateTo(),
                'days' => $window->days(),
                'granularity' => $window->granularity(),
            ],
            'parties' => $count,
            'funnel' => $this->funnel($reached),
            'forms' => [
                'opened' => $reached['form_opened'],
                'completed' => $reached['form_completed'],
                'on_time' => $timing['on_time'],
                'on_time_bp' => $reached['form_completed'] > 0 ? (int) round($timing['on_time'] / $reached['form_completed'] * 10000) : 0,
                'cutoff_hours' => $timing['cutoff_hours'],
            ],
            'invitations' => [
                'with' => $reached['with_invitation'],
                'views' => $facts['loads']['invitation_viewed'] ?? 0,
                'viewed' => $reached['invitation_viewed'],
                'replies_yes' => $replies['yes'],
                'replies_no' => $replies['no'],
                'with_reply' => $reached['with_reply'],
                'adopted' => $replies['adopted'],
                'calendar' => $facts['loads']['invitation_calendar_downloaded'] ?? 0,
            ],
            'authorizations' => [
                'marked' => $parties->filter(static fn (stdClass $p): bool => (int) $p->guardian_authorization === 1)->count(),
                'openings' => $facts['loads']['authorization_opened'] ?? 0,
                'opened' => $reached['authorization_opened'],
                'signed' => $reached['signed'],
                'signatures' => $signatures['total'],
                'from_invitation' => $signatures['from_invitation'],
            ],
            'money' => $money,
            'timing' => $timing['out'],
            'guests' => [
                'devices' => $facts['devices'],
                'locales' => $facts['locales'],
            ],
            'series' => $this->series($window, $parties, $invitations, $replies, $signatures, $money),
            'previous' => $this->totalsOnly($baseline),
        ];
    }

    // ─── Las fiestas del periodo ─────────────────────────────────────────────────────────────────

    /**
     * Una fila por reserva de pack con formulario cuya FIESTA cae en la ventana: la misma regla que
     * `OrderItem::acceptsGuestForm()`, escrita en SQL (línea principal, no cancelada, pedido pagado, pack con campos
     * de invitado).
     *
     * @return Collection<int, stdClass>
     */
    private function parties(Window $window): Collection
    {
        return DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->join('slots as sl', 'sl.id', '=', 'i.slot_id')
            ->join('ticket_types as t', 't.id', '=', 'i.ticket_type_id')
            ->whereNull('i.parent_item_id')
            ->whereNull('i.cancelled_at')
            ->where('o.status', Order::STATUS_PAID)
            ->where('t.type', TicketType::TYPE_PACK)
            ->whereNotNull('t.guest_fields')
            ->whereNotIn('t.guest_fields', ['[]', 'null', ''])
            ->whereBetween('sl.date', [$window->dateFrom(), $window->dateTo()])
            ->select(['i.id', 'i.order_id', 'i.quantity', 'i.guest_form_completed_at', 'i.guardian_authorization', 'sl.date', 'sl.start_time'])
            ->orderBy('sl.date')
            ->orderBy('i.id')
            ->get();
    }

    // ─── Los hechos de la reserva ────────────────────────────────────────────────────────────────

    /**
     * Los hechos de las fiestas del periodo, plegados: cuántas CARGAS de cada uno, qué reservas alcanzó cada uno, el
     * dispositivo y el idioma de los invitados, y los tiempos que solo el hecho conoce.
     *
     * @param  list<int>  $orderIds
     * @param  list<int>  $reservationIds
     * @return array{loads: array<string, int>, by_reservation: array<string, array<int, int>>, devices: array<string, int>, locales: array<string, int>, hours_since_open: list<float>}
     */
    private function facts(array $orderIds, array $reservationIds): array
    {
        $out = ['loads' => [], 'by_reservation' => [], 'devices' => [], 'locales' => [], 'hours_since_open' => []];

        if ($orderIds === []) {
            return $out;
        }

        $known = array_flip($reservationIds);
        $rows = DB::table('analytics_events')
            ->whereIn('order_id', $orderIds)
            ->whereIn('name', self::FACTS)
            ->select(['name', 'props'])
            ->get();

        foreach ($rows as $row) {
            $props = is_string($row->props) ? (array) json_decode($row->props, true) : [];
            $reservation = (int) ($props['reservation'] ?? 0);
            // Un hecho de otra reserva del mismo pedido (una fiesta fuera del periodo) no cuenta aquí.
            if (! isset($known[$reservation])) {
                continue;
            }
            $name = (string) $row->name;
            $out['loads'][$name] = ($out['loads'][$name] ?? 0) + 1;
            $out['by_reservation'][$name][$reservation] = ($out['by_reservation'][$name][$reservation] ?? 0) + 1;

            if (in_array($name, ['invitation_viewed', 'authorization_opened'], true) && isset($props['device'])) {
                $device = (string) $props['device'];
                $out['devices'][$device] = ($out['devices'][$device] ?? 0) + 1;
            }
            if ($name === 'invitation_viewed' && isset($props['locale'])) {
                $locale = (string) $props['locale'];
                $out['locales'][$locale] = ($out['locales'][$locale] ?? 0) + 1;
            }
            if ($name === 'authorization_signed' && isset($props['hours_since_open']) && is_numeric($props['hours_since_open'])) {
                $out['hours_since_open'][] = (float) $props['hours_since_open'];
            }
        }

        arsort($out['devices']);
        arsort($out['locales']);

        return $out;
    }

    // ─── La invitación y el justificante, desde las tablas de negocio ───────────────────────────

    /**
     * @param  list<int>  $reservationIds
     * @return array<int, int> reserva → 1 (tiene invitación materializada)
     */
    private function invitations(array $reservationIds): array
    {
        if ($reservationIds === []) {
            return [];
        }

        return DB::table('party_invitations')
            ->whereIn('order_item_id', $reservationIds)
            ->pluck('order_item_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->mapWithKeys(static fn (int $id): array => [$id => 1])
            ->all();
    }

    /**
     * @param  list<int>  $reservationIds
     * @return array{yes: int, no: int, adopted: int, by_reservation: array<int, int>, yes_by_reservation: array<int, int>}
     */
    private function replies(array $reservationIds): array
    {
        $out = ['yes' => 0, 'no' => 0, 'adopted' => 0, 'by_reservation' => [], 'yes_by_reservation' => []];

        if ($reservationIds === []) {
            return $out;
        }

        $rows = DB::table('invitation_replies')
            ->whereIn('order_item_id', $reservationIds)
            ->whereNull('dismissed_at')
            ->selectRaw('order_item_id, attending, COUNT(*) AS n, SUM(CASE WHEN adopted_at IS NULL THEN 0 ELSE 1 END) AS adopted')
            ->groupBy('order_item_id', 'attending')
            ->get();

        foreach ($rows as $row) {
            $id = (int) $row->order_item_id;
            $n = (int) $row->n;
            $out['by_reservation'][$id] = ($out['by_reservation'][$id] ?? 0) + $n;
            $out['adopted'] += (int) $row->adopted;
            if ((int) $row->attending === 1) {
                $out['yes'] += $n;
                $out['yes_by_reservation'][$id] = ($out['yes_by_reservation'][$id] ?? 0) + $n;
            } else {
                $out['no'] += $n;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $reservationIds
     * @return array{total: int, from_invitation: int, by_reservation: array<int, int>, signed_at: list<array{reservation: int, at: string}>}
     */
    private function signatures(array $reservationIds): array
    {
        $out = ['total' => 0, 'from_invitation' => 0, 'by_reservation' => [], 'signed_at' => []];

        if ($reservationIds === []) {
            return $out;
        }

        $rows = DB::table('guardian_authorizations')
            ->whereIn('order_item_id', $reservationIds)
            ->select(['order_item_id', 'invitation_reply_id', 'created_at'])
            ->get();

        foreach ($rows as $row) {
            $id = (int) $row->order_item_id;
            $out['total']++;
            $out['from_invitation'] += $row->invitation_reply_id === null ? 0 : 1;
            $out['by_reservation'][$id] = ($out['by_reservation'][$id] ?? 0) + 1;
            $out['signed_at'][] = ['reservation' => $id, 'at' => (string) $row->created_at];
        }

        return $out;
    }

    // ─── El dinero de después de reservar ────────────────────────────────────────────────────────

    /**
     * Lo que el libro dice de las fiestas del periodo: los extras (por complemento, en unidades y céntimos), los
     * invitados añadidos y quitados, las ediciones del panel aparte, y lo cobrado EN EL PARQUE de esos pedidos.
     *
     * @param  list<int>  $reservationIds
     * @param  list<int>  $orderIds
     * @return array<string, mixed>
     */
    private function money(array $reservationIds, array $orderIds): array
    {
        $out = [
            'extras' => 0, 'guests' => 0, 'guests_added' => 0, 'guests_removed' => 0, 'sold_after_booking' => 0,
            'panel_edits' => 0, 'collected_in_park' => 0, 'with_extras' => 0, 'avg_extras' => 0,
            'by_addon' => [], 'by_reservation' => [],
        ];

        if ($reservationIds === []) {
            return $out;
        }

        $rows = DB::table('order_adjustments as a')
            ->join('order_items as ai', 'ai.id', '=', 'a.order_item_id')
            ->where('a.type', OrderAdjustment::TYPE_EDIT)
            ->where(function (Builder $query) use ($reservationIds): void {
                $query->whereIn('ai.parent_item_id', $reservationIds)
                    ->orWhere(function (Builder $principal) use ($reservationIds): void {
                        $principal->whereNull('ai.parent_item_id')->whereIn('ai.id', $reservationIds);
                    });
            })
            ->selectRaw('COALESCE(ai.parent_item_id, ai.id) AS reservation, ai.ticket_type_id, a.reason, a.amount_cents, a.context')
            ->get();

        $byAddon = [];
        foreach ($rows as $row) {
            $reservation = (int) $row->reservation;
            $cents = (int) $row->amount_cents;
            $reason = (string) ($row->reason ?? '');

            if (in_array($reason, self::EXTRA_REASONS, true)) {
                $out['extras'] += $cents;
                $out['by_reservation'][$reservation] = ($out['by_reservation'][$reservation] ?? 0) + $cents;
                $type = (int) $row->ticket_type_id;
                $byAddon[$type] ??= ['units' => 0, 'cents' => 0];
                $byAddon[$type]['cents'] += $cents;
                $byAddon[$type]['units'] += self::unitsOf($row->context);
            } elseif (in_array($reason, self::GUEST_REASONS, true)) {
                // Los invitados no son «extras»: no entran en «reservas con extras» ni en su media.
                $out['guests'] += $cents;
                $units = self::unitsOf($row->context);
                if ($units > 0) {
                    $out['guests_added'] += $units;
                } else {
                    $out['guests_removed'] += -$units;
                }
            } else {
                $out['panel_edits'] += $cents;
            }
        }

        $out['sold_after_booking'] = $out['extras'] + $out['guests'];
        $withExtras = array_filter($out['by_reservation'], static fn (int $cents): bool => $cents > 0);
        $out['with_extras'] = count($withExtras);
        $out['avg_extras'] = $out['with_extras'] > 0 ? (int) round(array_sum($withExtras) / $out['with_extras']) : 0;

        $names = $byAddon === [] ? [] : DB::table('ticket_types')->whereIn('id', array_keys($byAddon))->pluck('name', 'id')->all();
        $list = [];
        foreach ($byAddon as $type => $sum) {
            $raw = $names[$type] ?? null;
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $list[] = [
                'addon' => is_array($decoded) ? (string) Translated::pick($decoded, app()->getLocale()) : (string) $type,
                'units' => $sum['units'],
                'cents' => $sum['cents'],
            ];
        }
        usort($list, static fn (array $a, array $b): int => $b['cents'] <=> $a['cents']);
        $out['by_addon'] = array_slice($list, 0, self::TOP_ROWS);

        $out['collected_in_park'] = (int) DB::table('payments')
            ->where('payable_type', (new Order)->getMorphClass())
            ->whereIn('payable_id', $orderIds)
            ->where('status', Payment::STATUS_PAID)
            ->whereIn('provider', self::PARK_PROVIDERS)
            ->sum('amount');

        return $out;
    }

    /** Las unidades que movió un asiento del libro (`context.changes.quantity_change`: `new − old`), o cero si no lo dice. */
    private static function unitsOf(mixed $context): int
    {
        $decoded = is_string($context) ? json_decode($context, true) : null;
        $change = is_array($decoded) ? ($decoded['changes']['quantity_change'] ?? null) : null;

        if (! is_array($change) || ! is_numeric($change['old'] ?? null) || ! is_numeric($change['new'] ?? null)) {
            return 0;
        }

        return (int) $change['new'] - (int) $change['old'];
    }

    // ─── Los tiempos ─────────────────────────────────────────────────────────────────────────────

    /**
     * Cuánto antes de la fiesta se completa el formulario (mediana e histograma, y cuántos dentro del plazo de
     * corte), cuánto antes se firma, y cuánto tarda un padre entre abrir el justificante y firmarlo.
     *
     * @param  Collection<int, stdClass>  $parties
     * @param  array{hours_since_open: list<float>}  $facts
     * @param  array{signed_at: list<array{reservation: int, at: string}>}  $signatures
     * @return array{out: array<string, mixed>, on_time: int, cutoff_hours: int}
     */
    private function timing(Window $window, Collection $parties, array $facts, array $signatures): array
    {
        $cutoff = app(GuestCountPolicy::class)->cutoffHours();
        $tz = $window->timezone;
        $partyDay = [];
        $partyStart = [];
        foreach ($parties as $party) {
            $partyDay[(int) $party->id] = CarbonImmutable::parse((string) $party->date, $tz)->startOfDay();
            $partyStart[(int) $party->id] = CarbonImmutable::parse((string) $party->date.' '.(string) ($party->start_time ?? '00:00:00'), $tz);
        }

        $formDays = [];
        $onTime = 0;
        $histogram = array_fill_keys(self::DAYS_BUCKETS, 0);
        foreach ($parties as $party) {
            if ($party->guest_form_completed_at === null) {
                continue;
            }
            $completed = CarbonImmutable::parse((string) $party->guest_form_completed_at, 'UTC');
            $days = (int) $completed->setTimezone($tz)->startOfDay()->diffInDays($partyDay[(int) $party->id], false);
            $formDays[] = $days;
            $histogram[self::bucketOfDays($days)]++;
            if ($completed->diffInHours($partyStart[(int) $party->id], false) >= $cutoff) {
                $onTime++;
            }
        }

        $signDays = [];
        foreach ($signatures['signed_at'] as $signature) {
            $day = $partyDay[$signature['reservation']] ?? null;
            if ($day === null) {
                continue;
            }
            $signDays[] = (int) CarbonImmutable::parse($signature['at'], 'UTC')->setTimezone($tz)->startOfDay()->diffInDays($day, false);
        }

        return [
            'out' => [
                'form_days_median' => self::median($formDays),
                'sign_days_median' => self::median($signDays),
                'hours_since_open_median' => self::median($facts['hours_since_open']),
                'form_days_histogram' => $histogram,
            ],
            'on_time' => $onTime,
            'cutoff_hours' => $cutoff,
        ];
    }

    public static function bucketOfDays(int $days): string
    {
        return match (true) {
            $days < 0 => 'late',
            $days === 0 => 'same_day',
            $days <= 3 => 'd1_3',
            $days <= 7 => 'd4_7',
            $days <= 14 => 'd8_14',
            default => 'd15_plus',
        };
    }

    /** @param  list<int|float>  $values */
    public static function median(array $values): int|float|null
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $n = count($values);
        $middle = intdiv($n, 2);
        $median = $n % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;

        return is_float($median) ? round($median, 1) : $median;
    }

    // ─── El embudo, la serie y el periodo anterior ───────────────────────────────────────────────

    /**
     * @param  array<string, int>  $reached
     * @return list<array{step: string, reached: int, of_parties_bp: int}>
     */
    private function funnel(array $reached): array
    {
        $parties = $reached['parties'];
        $funnel = [];
        foreach (self::STEPS as $step) {
            $count = min($reached[$step], $parties);
            $funnel[] = [
                'step' => $step,
                'reached' => $count,
                'of_parties_bp' => $parties > 0 ? (int) round($count / $parties * 10000) : 0,
            ];
        }

        return $funnel;
    }

    /**
     * @param  Collection<int, stdClass>  $parties
     * @param  array<int, int>  $invitations
     * @param  array{yes_by_reservation: array<int, int>}  $replies
     * @param  array{by_reservation: array<int, int>}  $signatures
     * @param  array{by_reservation: array<int, int>}  $money
     * @return list<array{key: string, parties: int, completed: int, invitations: int, replies_yes: int, signatures: int, extras: int}>
     */
    private function series(Window $window, Collection $parties, array $invitations, array $replies, array $signatures, array $money): array
    {
        $byKey = [];
        foreach ($parties as $party) {
            $id = (int) $party->id;
            $key = $window->bucketKey(CarbonImmutable::parse((string) $party->date, $window->timezone));
            $byKey[$key] ??= ['parties' => 0, 'completed' => 0, 'invitations' => 0, 'replies_yes' => 0, 'signatures' => 0, 'extras' => 0];
            $byKey[$key]['parties']++;
            $byKey[$key]['completed'] += $party->guest_form_completed_at === null ? 0 : 1;
            $byKey[$key]['invitations'] += $invitations[$id] ?? 0;
            $byKey[$key]['replies_yes'] += $replies['yes_by_reservation'][$id] ?? 0;
            $byKey[$key]['signatures'] += $signatures['by_reservation'][$id] ?? 0;
            $byKey[$key]['extras'] += max(0, $money['by_reservation'][$id] ?? 0);
        }

        $series = [];
        foreach ($window->bucketKeys() as $key) {
            $series[] = ['key' => $key] + ($byKey[$key] ?? ['parties' => 0, 'completed' => 0, 'invitations' => 0, 'replies_yes' => 0, 'signatures' => 0, 'extras' => 0]);
        }

        return $series;
    }

    /** Las cifras de las tarjetas para el periodo de comparación. @return array<string, int> */
    private function totalsOnly(Window $window): array
    {
        $parties = $this->parties($window);
        $ids = $parties->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $orderIds = $parties->pluck('order_id')->map(static fn ($id): int => (int) $id)->unique()->values()->all();
        $money = $this->money($ids, $orderIds);
        $replies = $this->replies($ids);
        $signatures = $this->signatures($ids);

        return [
            'parties' => $parties->count(),
            'completed' => $parties->whereNotNull('guest_form_completed_at')->count(),
            'sold_after_booking' => $money['sold_after_booking'],
            'collected_in_park' => $money['collected_in_park'],
            'with_extras' => $money['with_extras'],
            'avg_extras' => $money['avg_extras'],
            'replies_yes' => $replies['yes'],
            'signatures' => $signatures['total'],
        ];
    }
}
