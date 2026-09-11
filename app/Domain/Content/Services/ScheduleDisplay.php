<?php

namespace App\Domain\Content\Services;

use App\Domain\Booking\Contracts\OperatingCalendar;
use App\Domain\Booking\Contracts\SeasonWindow;
use App\Domain\Booking\Contracts\SpecialDay;
use App\Domain\Booking\Contracts\WeeklyOpening;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

        // ⚠️ `is_closed` es un campo AÑADIDO (`#487`): la sección 07 escribe «Cerrado» en el sitio de
        // las horas y la entradilla cuenta solo los grupos ABIERTOS. Deducirlo comparando `time` con
        // la traducción de «Cerrado» sería atar una decisión de datos al diccionario.
        return array_map(fn (array $group): array => [
            'label' => $this->dayRangeLabel($group['days']),
            'time' => $group['time'],
            'is_today' => $group['has_today'] ?? false,
            'is_closed' => $group['signature'] === 'closed',
        ], $groups);
    }

    /**
     * **La entradilla de la sección 07, DERIVADA del horario** (`#487`).
     *
     * ❗❗❗ **Se deriva y no se escribe, y ése es el punto.** El canvas la fija como *«Abrimos todos
     * los días. Entre semana por la tarde, y de viernes a domingo también por la mañana»* — una
     * afirmación cierta en PlayJump y **falsa en cualquier instalación que cierre un día**. En un
     * `lang/` del PRODUCTO eso es la fuga que `DECISIONES #1` prohíbe, y es la misma familia que el
     * owner acaba de cerrar dos veces en esta tanda (el aparcamiento y «los festivos, como el
     * finde»): *una promesa que el producto no puede saber no se escribe en el producto*.
     *
     * ▶ Lo que sí se puede decir con el dato delante es **cuántos horarios distintos hay y cuáles**,
     * que es exactamente lo que la entradilla del artboard de móvil cuenta: «Dos horarios: entre
     * semana y de viernes a domingo».
     *
     * ⚠️ **Se cuentan solo los grupos ABIERTOS.** Un parque con «L–V 16:30», «sábado cerrado» y
     * «domingo 11:00» tiene TRES filas y **dos** horarios: contar filas diría «tres horarios» y una
     * de ellas es un cierre.
     */
    public function weeklyLede(): ?string
    {
        $abiertos = array_values(array_filter(
            $this->weeklyRows(),
            static fn (array $row): bool => ! $row['is_closed'],
        ));

        return match (count($abiertos)) {
            0 => null,
            1 => (string) __('landing.info.lede_one'),
            2 => (string) __('landing.info.lede_two', [
                'a' => mb_strtolower($abiertos[0]['label']),
                'b' => mb_strtolower($abiertos[1]['label']),
            ]),
            default => (string) __('landing.info.lede_many'),
        };
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
     * ⚠️ **`days_away` es un campo AÑADIDO** (`#487`): la sección 07 solo anuncia FUERA del pliegue
     * la que está cerca, y «cerca» es una cantidad de días — un dato que la fecha ya escrita no
     * puede devolver sin volver a parsearla en la vista.
     *
     * ⚠️ **`note` y `rate` los añade `#531` para `/precios`**, que publica la lista entera porque esas
     * fechas cambian el PRECIO: el nombre que el parque le da al día («Víspera de Navidad») y la
     * tarifa que aplica. La 07 de la portada no los lee — sigue con `detail`, que es su horario.
     *
     * @return array<int, array{date: string, detail: string, is_closed: bool, days_away: int, note: ?string, rate: ?string}>
     */
    public function upcomingSpecialDates(int $limit = 4): array
    {
        $hoy = now(DisplayTime::timezone())->startOfDay();

        return array_map(fn (SpecialDay $special): array => [
            'date' => Carbon::parse($special->date)->isoFormat('ddd D MMM'),
            'detail' => $this->specialDetail($special),
            'is_closed' => $special->isClosed,
            'days_away' => (int) $hoy->diffInDays(Carbon::parse($special->date)->startOfDay(), false),
            'note' => $special->note,
            'rate' => $special->rateLabel,
        ], $this->calendar->upcomingSpecialDays($limit));
    }

    /**
     * **LAS FECHAS ESPECIALES COMO LAS PUBLICA `/precios`** (`DECISIONES #531`): la fecha, el nombre
     * que el parque le da y **el hecho de ese día**.
     *
     * ❗❗❗ **Cada fila dice SU hecho, y no hay ninguna frase general.** El artboard escribe encima
     * «cuentan como fin de semana, en precio y en horario» y eso es justo lo que `#487` retiró de la
     * 07: el producto **no puede afirmarlo** —una instalación puede cerrar el 25 y abrir el 6 con
     * otro horario—. Aquí el hecho se deriva de la fila: cerrada dice «Cerrado», con tarifa dice el
     * rótulo de esa tarifa —que es lo que el visitante viene a saber en una página de precios— y si
     * no, su horario.
     *
     * ⚠️ **Sin fechas cargadas devuelve vacío y la página no pinta el bloque**: «una sección cuyo
     * contenido lo pone el panel desaparece con cero filas», regla dura del canvas.
     *
     * @return list<array{date: string, name: ?string, fact: string, is_closed: bool}>
     */
    public function pricingCalendar(int $limit = 40): array
    {
        return array_map(fn (array $row): array => [
            // ⚠️ Mayúscula inicial: `isoFormat('ddd D MMM')` devuelve «vie 12 dic» en español, y una
            // lista de fechas empieza cada línea como una etiqueta, no como media frase.
            'date' => Str::ucfirst($row['date']),
            'name' => $row['note'],
            'fact' => $row['is_closed'] ? __('landing.info.closed') : ($row['rate'] ?? $row['detail']),
            'is_closed' => $row['is_closed'],
        ], $this->upcomingSpecialDates($limit));
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
