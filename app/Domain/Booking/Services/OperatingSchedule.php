<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\OperatingWindow;
use App\Domain\Booking\Contracts\SeasonWindow;
use App\Domain\Booking\Contracts\SpecialDay;
use App\Domain\Booking\Contracts\WeeklyOpening;
use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\Season;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Horario EFECTIVO del parque para una fecha (transversal §9.1 del plan). Resuelve por
 * prioridad: una excepción en `special_dates` (día concreto) manda sobre la TEMPORADA
 * vigente (rango de fechas, #207), y esta sobre el `opening_hours` del día de la semana.
 * Es la fuente única que usan el generador de franjas, la compra y el panel para saber si
 * un día está abierto y en qué ventana [apertura, cierre].
 *
 * Fallback no destructivo: si no hay NADA configurado para un día, se considera abierto y
 * sin restricción de ventana (open/close = null), para no bloquear cuando el parque aún no
 * ha definido sus horarios reales [PENDIENTE]. El precio del día lo resuelve RateResolver.
 */
class OperatingSchedule implements OperatingCalendar
{
    /** @var Collection<int, OpeningHour>|null horario semanal memoizado por weekday */
    private ?Collection $weekly = null;

    /** @var Collection<int, Season>|null temporadas activas memoizadas (el calendario resuelve muchos días) */
    private ?Collection $seasons = null;

    /** @var Collection<string, SpecialDate>|null fechas especiales memoizadas por fecha (el generador resuelve un rango entero) */
    private ?Collection $special = null;

    /**
     * Horario efectivo de un día. open/close en formato 'H:i:s' (null = sin restricción de ventana).
     *
     * @return array{is_open: bool, open: string|null, close: string|null}
     */
    public function effectiveFor(CarbonInterface $date): array
    {
        $special = $this->special()->get($date->toDateString());

        if ($special) {
            if ($special->is_closed) {
                return ['is_open' => false, 'open' => null, 'close' => null];
            }

            // Excepción abierta: usa su ventana; si no la define, cae al horario semanal.
            $weekly = $this->weekly()->get($date->dayOfWeek);

            return [
                'is_open' => true,
                'open' => $special->open_time ?? $weekly?->open_time,
                'close' => $special->close_time ?? $weekly?->close_time,
            ];
        }

        // Temporada vigente (rango de fechas): sustituye al horario semanal y abre todos los
        // días de su rango con su ventana (#207).
        $season = $this->seasonFor($date);
        if ($season) {
            return ['is_open' => true, 'open' => $season->open_time, 'close' => $season->close_time];
        }

        $weekly = $this->weekly()->get($date->dayOfWeek);

        if ($weekly && $weekly->is_closed) {
            return ['is_open' => false, 'open' => null, 'close' => null];
        }

        return [
            'is_open' => true,
            'open' => $weekly?->open_time,   // null = sin horario configurado → sin restricción
            'close' => $weekly?->close_time,
        ];
    }

    /** ¿Está el parque abierto ese día? */
    public function isOpenOn(CarbonInterface $date): bool
    {
        return $this->effectiveFor($date)['is_open'];
    }

    /** @return Collection<int, OpeningHour> */
    private function weekly(): Collection
    {
        return $this->weekly ??= OpeningHour::all()->keyBy('weekday');
    }

    /**
     * Fechas especiales memoizadas por fecha ('Y-m-d'). El generador de franjas resuelve un
     * rango entero día a día; cargarlas en bloque evita una consulta por día.
     *
     * @return Collection<string, SpecialDate>
     */
    private function special(): Collection
    {
        return $this->special ??= SpecialDate::all()->keyBy(fn (SpecialDate $s): string => $s->date->toDateString());
    }

    /**
     * Temporada vigente en la fecha, o null. Si varias solapan, gana la de inicio más
     * temprano (orden determinista). Memoiza las temporadas activas (el calendario resuelve
     * muchos días seguidos).
     */
    private function seasonFor(CarbonInterface $date): ?Season
    {
        $day = $date->toDateString();

        return $this->seasons()->first(
            fn (Season $season): bool => $season->start_date->toDateString() <= $day
                && $day <= $season->end_date->toDateString()
        );
    }

    /** @return Collection<int, Season> */
    private function seasons(): Collection
    {
        return $this->seasons ??= Season::where('is_active', true)->orderBy('start_date')->get();
    }

    // ─────────────────────────────────────────────────────────────────────────────────────
    // Contrato `OperatingCalendar` (Fase 2, paso 7): la cara del calendario para fuera de
    // Booking. Todo se compone sobre los datos que esta clase YA memoiza, así que no añade
    // ni una consulta: de hecho retira las que Content hacía por su cuenta.
    // ─────────────────────────────────────────────────────────────────────────────────────

    public function windowFor(CarbonInterface $date): OperatingWindow
    {
        $effective = $this->effectiveFor($date);

        return new OperatingWindow(
            isOpen: $effective['is_open'],
            opensAt: $effective['open'],
            closesAt: $effective['close'],
        );
    }

    /** @return array<int, WeeklyOpening> */
    public function weeklyOpenings(): array
    {
        return $this->weekly()
            ->map(fn (OpeningHour $row): WeeklyOpening => new WeeklyOpening(
                weekday: (int) $row->weekday,
                isClosed: (bool) $row->is_closed,
                opensAt: $row->open_time,
                closesAt: $row->close_time,
            ))
            ->all();
    }

    /** @return list<SeasonWindow> */
    public function activeSeasons(): array
    {
        $current = $this->seasonFor(now(DisplayTime::timezone()));

        return $this->seasons()
            ->map(fn (Season $season): SeasonWindow => new SeasonWindow(
                id: (int) $season->id,
                name: (string) $season->name,
                startsOn: $season->start_date->toDateString(),
                endsOn: $season->end_date->toDateString(),
                opensAt: $season->open_time,
                closesAt: $season->close_time,
                isCurrent: $current !== null && (int) $current->id === (int) $season->id,
            ))
            ->values()
            ->all();
    }

    /** @return list<SpecialDay> */
    public function upcomingSpecialDays(int $limit): array
    {
        $today = now(DisplayTime::timezone())->toDateString();

        return $this->special()
            ->filter(fn (SpecialDate $special): bool => $special->date->toDateString() >= $today)
            ->sortBy(fn (SpecialDate $special): string => $special->date->toDateString())
            ->take(max(1, $limit))
            ->map(function (SpecialDate $special): SpecialDay {
                // La ventana que viaja es la EFECTIVA: la propia del día o, si no la define, la
                // del semanal. La resuelve la MISMA `effectiveFor` que aplican las reservas, no
                // una copia — era la regla que Content repetía a mano.
                $window = $this->windowFor($special->date);

                return new SpecialDay(
                    date: $special->date->toDateString(),
                    isClosed: ! $window->isOpen,
                    note: $special->tr('note'),
                    opensAt: $window->opensAt,
                    closesAt: $window->closesAt,
                );
            })
            ->values()
            ->all();
    }

    public function hasSpecialDay(CarbonInterface $date): bool
    {
        return $this->special()->has($date->toDateString());
    }
}
