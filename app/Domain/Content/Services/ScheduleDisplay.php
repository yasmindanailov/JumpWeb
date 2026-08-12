<?php

namespace App\Domain\Content\Services;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\SeasonWindow;
use App\Domain\Booking\Contracts\SpecialDay;
use App\Domain\Booking\Contracts\WeeklyOpening;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Carbon;

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
 * {@see OperatingCalendar} para las reservas: lo anunciado no PUEDE divergir de lo aplicado,
 * porque desde el paso 7 de Fase 2 ya no se calcula aquí — se pide resuelto.
 */
class ScheduleDisplay
{
    /** Orden de presentación: lunes→domingo (el valor es el weekday de Carbon). */
    private const ORDER = [1, 2, 3, 4, 5, 6, 0];

    /** @var array<int, WeeklyOpening>|null horario semanal memoizado por weekday */
    private ?array $weekly = null;

    /** @var list<SeasonWindow>|null temporadas activas memoizadas (ordenadas por inicio) */
    private ?array $activeSeasons = null;

    /** @var bool|null ¿hay una fecha especial para HOY? (memoizado) */
    private ?bool $todaySpecial = null;

    public function __construct(private readonly OperatingCalendar $calendar) {}

    /**
     * Filas del horario semanal, agrupando días consecutivos con el mismo horario.
     *
     * @return array<int, array{label: string, time: string, is_today: bool}>
     */
    public function weeklyRows(): array
    {
        $rows = $this->weekly();
        if ($rows === []) {
            return [];
        }

        // Si hoy lo gobierna una temporada o una fecha especial, el horario semanal NO es el «actual»
        // (se destaca la temporada; #269bis punto 2). Solo entonces marcamos la fila de hoy.
        $today = $this->todayOverridden() ? null : now(DisplayTime::timezone())->dayOfWeek;

        $groups = [];
        $current = null;
        foreach (self::ORDER as $weekday) {
            $row = $rows[$weekday] ?? null;
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

        return array_values(array_map(
            fn (SeasonWindow $season): array => [
                'name' => $season->name,
                'range' => Carbon::parse($season->startsOn)->isoFormat('D MMM')
                    .' – '.Carbon::parse($season->endsOn)->isoFormat('D MMM'),
                'time' => substr((string) $season->opensAt, 0, 5).' – '.substr((string) $season->closesAt, 0, 5),
                // ¿Es la temporada vigente HOY? Lo decide Booking (#269bis punto 2).
                'is_current' => $season->isCurrent,
            ],
            array_filter($this->activeSeasons(), fn (SeasonWindow $s): bool => $s->endsOn >= $today)
        ));
    }

    /**
     * Próximas fechas especiales (desde hoy): cierres puntuales u horarios especiales.
     *
     * @return array<int, array{date: string, detail: string, is_closed: bool}>
     */
    public function upcomingSpecialDates(int $limit = 4): array
    {
        return array_map(fn (SpecialDay $special): array => [
            'date' => Carbon::parse($special->date)->isoFormat('ddd D MMM'),
            'detail' => $this->specialDetail($special),
            'is_closed' => $special->isClosed,
        ], $this->calendar->upcomingSpecialDays($limit));
    }

    /** Firma de un día para agrupar (cerrado / ventana / abierto-sin-ventana / sin-config). */
    private function signature(?WeeklyOpening $row): string
    {
        if ($row === null) {
            return 'unset';
        }
        if ($row->isClosed) {
            return 'closed';
        }

        return 'open:'.substr((string) $row->opensAt, 0, 5).'-'.substr((string) $row->closesAt, 0, 5);
    }

    /** Texto del horario de un día/grupo. */
    private function timeLabel(?WeeklyOpening $row): string
    {
        if ($row !== null && $row->isClosed) {
            return __('landing.info.closed');
        }
        if ($row === null || $row->opensAt === null || $row->closesAt === null) {
            return __('landing.info.open_generic');
        }

        return substr((string) $row->opensAt, 0, 5).' – '.substr((string) $row->closesAt, 0, 5);
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

    /**
     * Detalle de una excepción. Ya NO resuelve la ventana efectiva: la trae el contrato
     * calculada por Booking con la misma regla que aplican las reservas (#269bis punto 1).
     * Aquí solo queda el formato.
     */
    private function specialDetail(SpecialDay $special): string
    {
        if ($special->isClosed) {
            return __('landing.info.closed');
        }
        if ($special->hasHours()) {
            return substr((string) $special->opensAt, 0, 5).' – '.substr((string) $special->closesAt, 0, 5);
        }

        // Sin ventana en ningún nivel (p. ej. día abierto sin horario configurado): nota o «Abierto».
        return (string) ($special->note ?? __('landing.info.open_generic'));
    }

    /** @return array<int, WeeklyOpening> horario semanal por weekday (memoizado). */
    private function weekly(): array
    {
        return $this->weekly ??= $this->calendar->weeklyOpenings();
    }

    /** @return list<SeasonWindow> temporadas activas ordenadas por inicio (memoizado). */
    private function activeSeasons(): array
    {
        return $this->activeSeasons ??= $this->calendar->activeSeasons();
    }

    /** ¿Hay una temporada vigente HOY? La regla la aplica Booking; aquí solo se lee el flag. */
    private function hasCurrentSeason(): bool
    {
        foreach ($this->activeSeasons() as $season) {
            if ($season->isCurrent) {
                return true;
            }
        }

        return false;
    }

    /** ¿Hay una fecha especial configurada para HOY? (memoizado) */
    private function todayHasSpecial(): bool
    {
        return $this->todaySpecial ??= $this->calendar->hasSpecialDay(now(DisplayTime::timezone()));
    }

    /** ¿El horario de HOY lo gobierna una fecha especial o una temporada (no el semanal)? */
    private function todayOverridden(): bool
    {
        return $this->todayHasSpecial() || $this->hasCurrentSeason();
    }
}
