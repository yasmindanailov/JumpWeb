<?php

namespace App\Support;

use App\Domain\Platform\Services\DisplayTime;
use App\Models\OpeningHour;
use App\Models\Season;
use App\Models\SpecialDate;
use Illuminate\Support\Collection;

/**
 * Fase 7.7 (#207) — Presenta el horario del parque para la LANDING, data-driven desde la
 * misma fuente que usan las reservas (`opening_hours` + `seasons` + `special_dates`), de
 * modo que lo que se anuncia coincide con lo que aplican las reservas.
 *
 *  - `weeklyRows()`: el horario semanal, AGRUPANDO días consecutivos con el mismo horario
 *    («Lunes a viernes: 16:00 – 22:00»). Marca la fila de hoy SOLO si el horario efectivo de hoy
 *    lo gobierna el semanal (no una temporada ni una fecha especial — #269bis punto 2).
 *  - `seasons()`: las temporadas vigentes o futuras; marca `is_current` la que rige hoy.
 *  - `upcomingSpecialDates()`: las próximas fechas especiales. Muestra su horario EFECTIVO (ventana
 *    propia o, si no la define, el semanal de ese día), no solo el nombre (#269bis punto 1).
 *
 * La prioridad del horario efectivo (fecha especial > temporada > semanal) es la MISMA que resuelve
 * {@see ParkSchedule::effectiveFor()} para las reservas: lo anunciado no diverge de lo aplicado.
 */
class ScheduleDisplay
{
    /** Orden de presentación: lunes→domingo (el valor es el weekday de Carbon). */
    private const ORDER = [1, 2, 3, 4, 5, 6, 0];

    /** @var Collection<int, OpeningHour>|null horario semanal memoizado por weekday */
    private ?Collection $weekly = null;

    /** @var Collection<int, Season>|null temporadas activas memoizadas (ordenadas por inicio) */
    private ?Collection $activeSeasons = null;

    /** @var bool|null ¿hay una fecha especial para HOY? (memoizado) */
    private ?bool $todaySpecial = null;

    /**
     * Filas del horario semanal, agrupando días consecutivos con el mismo horario.
     *
     * @return array<int, array{label: string, time: string, is_today: bool}>
     */
    public function weeklyRows(): array
    {
        $rows = $this->weekly();
        if ($rows->isEmpty()) {
            return [];
        }

        // Si hoy lo gobierna una temporada o una fecha especial, el horario semanal NO es el «actual»
        // (se destaca la temporada; #269bis punto 2). Solo entonces marcamos la fila de hoy.
        $today = $this->todayOverridden() ? null : now(DisplayTime::timezone())->dayOfWeek;

        $groups = [];
        $current = null;
        foreach (self::ORDER as $weekday) {
            $row = $rows->get($weekday);
            $signature = $this->signature($row);

            if ($current !== null && $current['signature'] === $signature) {
                $current['days'][] = $weekday;
            } else {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = ['signature' => $signature, 'days' => [$weekday], 'time' => $this->timeLabel($row)];
            }

            if ($weekday === $today) {
                $current['has_today'] = true;
            }
        }
        if ($current !== null) {
            $groups[] = $current;
        }

        return array_map(fn (array $group): array => [
            'label' => $this->dayRangeLabel($group['days']),
            'time' => $group['time'],
            'is_today' => $group['has_today'] ?? false,
        ], $groups);
    }

    /**
     * Temporadas vigentes o futuras (no las ya terminadas), para anunciarlas en la landing.
     *
     * @return array<int, array{name: string, range: string, time: string, is_current: bool}>
     */
    public function seasons(): array
    {
        $today = now(DisplayTime::timezone())->toDateString();
        $current = $this->currentSeason();

        return $this->activeSeasons()
            ->filter(fn (Season $season): bool => $season->end_date->toDateString() >= $today)
            ->map(fn (Season $season): array => [
                'name' => $season->name,
                'range' => $season->start_date->isoFormat('D MMM').' – '.$season->end_date->isoFormat('D MMM'),
                'time' => substr((string) $season->open_time, 0, 5).' – '.substr((string) $season->close_time, 0, 5),
                // ¿Es la temporada vigente HOY? Se destaca como horario actual (#269bis punto 2).
                'is_current' => $current !== null && (int) $season->id === (int) $current->id,
            ])
            ->values()
            ->all();
    }

    /**
     * Próximas fechas especiales (desde hoy): cierres puntuales u horarios especiales.
     *
     * @return array<int, array{date: string, detail: string, is_closed: bool}>
     */
    public function upcomingSpecialDates(int $limit = 4): array
    {
        return SpecialDate::whereDate('date', '>=', now(DisplayTime::timezone())->toDateString())
            ->orderBy('date')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (SpecialDate $special): array => [
                'date' => $special->date->isoFormat('ddd D MMM'),
                'detail' => $this->specialDetail($special),
                'is_closed' => (bool) $special->is_closed,
            ])
            ->all();
    }

    /** Firma de un día para agrupar (cerrado / ventana / abierto-sin-ventana / sin-config). */
    private function signature(?OpeningHour $row): string
    {
        if ($row === null) {
            return 'unset';
        }
        if ($row->is_closed) {
            return 'closed';
        }

        return 'open:'.substr((string) $row->open_time, 0, 5).'-'.substr((string) $row->close_time, 0, 5);
    }

    /** Texto del horario de un día/grupo. */
    private function timeLabel(?OpeningHour $row): string
    {
        if ($row !== null && $row->is_closed) {
            return __('landing.info.closed');
        }
        if ($row === null || $row->open_time === null || $row->close_time === null) {
            return __('landing.info.open_generic');
        }

        return substr((string) $row->open_time, 0, 5).' – '.substr((string) $row->close_time, 0, 5);
    }

    /**
     * Etiqueta del rango de días en el idioma activo: un día («Lunes») o un rango
     * («Lunes a viernes»).
     *
     * @param  array<int, int>  $days  weekdays de Carbon en orden de presentación
     */
    private function dayRangeLabel(array $days): string
    {
        $first = __('landing.info.weekdays.'.$days[0]);
        if (count($days) === 1) {
            return $first;
        }

        // "Lunes a viernes": el primer día capitalizado (inicia la etiqueta), el segundo en
        // minúscula (lectura natural del rango).
        return __('landing.info.day_range', [
            'from' => $first,
            'to' => mb_strtolower(__('landing.info.weekdays.'.end($days))),
        ]);
    }

    private function specialDetail(SpecialDate $special): string
    {
        if ($special->is_closed) {
            return __('landing.info.closed');
        }

        // Ventana EFECTIVA: la propia del día especial o, si no la define, el horario SEMANAL de ese
        // día (igual que ParkSchedule::effectiveFor). Antes se mostraba solo la nota/«Abierto» y se
        // perdía el horario cuando la fecha especial heredaba el semanal (#269bis punto 1).
        $day = $this->weekly()->get($special->date->dayOfWeek);
        $open = $special->open_time ?? $day?->open_time;
        $close = $special->close_time ?? $day?->close_time;
        if ($open !== null && $close !== null) {
            return substr((string) $open, 0, 5).' – '.substr((string) $close, 0, 5);
        }

        // Sin ventana en ningún nivel (p. ej. día abierto sin horario configurado): nota o «Abierto».
        return (string) ($special->tr('note') ?? __('landing.info.open_generic'));
    }

    /** @return Collection<int, OpeningHour> horario semanal por weekday (memoizado). */
    private function weekly(): Collection
    {
        return $this->weekly ??= OpeningHour::all()->keyBy('weekday');
    }

    /** @return Collection<int, Season> temporadas activas ordenadas por inicio (memoizado). */
    private function activeSeasons(): Collection
    {
        return $this->activeSeasons ??= Season::where('is_active', true)->orderBy('start_date')->get();
    }

    /**
     * Temporada vigente HOY (si varias solapan, la de inicio más temprano), o null. Misma regla que
     * {@see ParkSchedule::seasonFor()}, para no divergir de lo que aplican las reservas.
     */
    private function currentSeason(): ?Season
    {
        $day = now(DisplayTime::timezone())->toDateString();

        return $this->activeSeasons()->first(
            fn (Season $season): bool => $season->start_date->toDateString() <= $day
                && $day <= $season->end_date->toDateString()
        );
    }

    /** ¿Hay una fecha especial configurada para HOY? (memoizado) */
    private function todayHasSpecial(): bool
    {
        return $this->todaySpecial ??= SpecialDate::query()
            ->whereDate('date', now(DisplayTime::timezone())->toDateString())
            ->exists();
    }

    /** ¿El horario de HOY lo gobierna una fecha especial o una temporada (no el semanal)? */
    private function todayOverridden(): bool
    {
        return $this->todayHasSpecial() || $this->currentSeason() !== null;
    }
}
