<?php

namespace App\Filament\Analytics;

use App\Domain\Platform\Services\Analytics\EmailTiming;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * **LOS CORREOS, EN CONJUNTO** (`specs/correos-salientes.md` §4.14, `#796`, la C4): de los que salieron en el periodo, por
 * correo, cuántos, qué parte se pulsó y se abrió (entre los que se medían) y cuánto se tardó en pulsar; y CUÁNDO se abren y
 * se pulsan, día × hora del parque, para elegir la hora de los de marketing.
 *
 * - **El periodo son los ENVÍOS** cuyo `sent_at` cae en él; sus clics y aperturas cuentan aunque lleguen después.
 * - Solo cuenta lo que no lleva veredicto (ni escáneres, ni Apple al entregar, ni la misma lectura otra vez).
 * - ⚠️⚠️ **El mapa, solo con los correos que le LLEGAN** (`EmailTiming::RECEIVED`): los que provoca el cliente se abren al
 *   instante y dirían a qué hora compra, no a qué hora lee.
 * - Devuelve RECUENTOS en crudo, sin persona; los mínimos de `RGPD-07` ({@see MIN_CELL}) los pone quien pinta
 *   (`EmailsWidget`, `EmailsHeatmapWidget`), que es también de donde sale el CSV.
 * - La hora del parque se calcula aquí, en PHP: el MySQL de producción puede no tener las tablas de zonas (`CONVERT_TZ`).
 */
final class EmailsReport
{
    public const CACHE_SECONDS = 300;

    public const MIN_CELL = SurveysReport::MIN_CELL;

    /** @return array<string, mixed> */
    public static function for(Window $window): array
    {
        return Cache::remember(self::cacheKey($window), self::CACHE_SECONDS, fn (): array => (new self)->compute($window));
    }

    public static function cacheKey(Window $window): string
    {
        return 'analytics:emails:v1:'.$window->timezone.':'.$window->utcFrom()->format('YmdHis').':'.$window->utcTo()->format('YmdHis');
    }

    /**
     * @return array{
     *     by_mail: list<array{key: string, sent: int, clicks_measured: int, clicked: int, opens_measured: int, opened: int, minutes_to_click: ?int, clicks_timed: int}>,
     *     clicks_heat: array<int, array<int, int>>, opens_heat: array<int, array<int, int>>, clicks_total: int, opens_total: int
     * }
     */
    public function compute(Window $window): array
    {
        $sends = $this->inWindow(DB::table('email_sends as s'), $window)
            ->select(['s.id', 's.mail_key', 's.sent_at', 's.tracks_clicks', 's.tracks_opens'])
            ->get();

        // El PRIMER clic y la PRIMERA apertura que cuentan de cada envío del periodo.
        $firstClick = $this->inWindow(DB::table('email_clicks as c')->join('email_sends as s', 's.id', '=', 'c.email_send_id'), $window)
            ->whereNull('c.verdict')->groupBy('c.email_send_id')
            ->select(['c.email_send_id as send_id', DB::raw('MIN(c.clicked_at) as first_at')])
            ->pluck('first_at', 'send_id');
        $firstOpen = $this->inWindow(DB::table('email_opens as o')->join('email_sends as s', 's.id', '=', 'o.email_send_id'), $window)
            ->whereNull('o.verdict')->groupBy('o.email_send_id')
            ->select(['o.email_send_id as send_id', DB::raw('MIN(o.opened_at) as first_at')])
            ->pluck('first_at', 'send_id');

        $byMail = [];
        foreach ($sends->groupBy('mail_key') as $key => $group) {
            $minutes = [];
            $row = ['key' => (string) $key, 'sent' => $group->count(), 'clicks_measured' => 0, 'clicked' => 0, 'opens_measured' => 0, 'opened' => 0];

            foreach ($group as $send) {
                if ((bool) $send->tracks_clicks) {
                    $row['clicks_measured']++;
                    if (isset($firstClick[$send->id])) {
                        $row['clicked']++;
                        $minutes[] = intdiv(CarbonImmutable::parse((string) $firstClick[$send->id], 'UTC')->getTimestamp() - CarbonImmutable::parse((string) $send->sent_at, 'UTC')->getTimestamp(), 60);
                    }
                }
                if ((bool) $send->tracks_opens) {
                    $row['opens_measured']++;
                    $row['opened'] += isset($firstOpen[$send->id]) ? 1 : 0;
                }
            }

            $byMail[] = [...$row, 'minutes_to_click' => self::median($minutes), 'clicks_timed' => count($minutes)];
        }
        usort($byMail, static fn (array $a, array $b): int => [$b['sent'], $a['key']] <=> [$a['sent'], $b['key']]);

        $clicks = $this->received($this->inWindow(DB::table('email_clicks as c')->join('email_sends as s', 's.id', '=', 'c.email_send_id'), $window))
            ->whereNull('c.verdict')->pluck('c.clicked_at');
        $opens = $this->received($this->inWindow(DB::table('email_opens as o')->join('email_sends as s', 's.id', '=', 'o.email_send_id'), $window))
            ->whereNull('o.verdict')->pluck('o.opened_at');

        return [
            'by_mail' => $byMail,
            'clicks_heat' => $this->heat($clicks->all(), $window->timezone),
            'opens_heat' => $this->heat($opens->all(), $window->timezone),
            'clicks_total' => $clicks->count(),
            'opens_total' => $opens->count(),
        ];
    }

    /** Los envíos del periodo: `[desde, hasta)` en UTC, el mismo corte que `Window::contains()`. */
    private function inWindow(Builder $query, Window $window): Builder
    {
        return $query->whereNotNull('s.sent_at')
            ->where('s.sent_at', '>=', $window->utcFrom()->format('Y-m-d H:i:s'))
            ->where('s.sent_at', '<', $window->utcTo()->format('Y-m-d H:i:s'));
    }

    /** Solo los correos que le LLEGAN (`EmailTiming::RECEIVED`). */
    private function received(Builder $query): Builder
    {
        return $query->whereIn('s.mail_key', EmailTiming::RECEIVED);
    }

    /**
     * Día de la semana (1 = lunes) × hora, en la zona del parque.
     *
     * @param  list<mixed>  $instants
     * @return array<int, array<int, int>>
     */
    private function heat(array $instants, string $timezone): array
    {
        $heat = [];
        foreach ($instants as $instant) {
            $local = CarbonImmutable::parse((string) $instant, 'UTC')->setTimezone($timezone);
            $heat[$local->dayOfWeekIso][$local->hour] = ($heat[$local->dayOfWeekIso][$local->hour] ?? 0) + 1;
        }

        return $heat;
    }

    /** @param  list<int>  $values */
    private static function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$middle] : intdiv($values[$middle - 1] + $values[$middle], 2);
    }
}
