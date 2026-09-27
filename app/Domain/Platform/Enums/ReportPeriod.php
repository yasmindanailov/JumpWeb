<?php

namespace App\Domain\Platform\Enums;

use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * **El periodo del cuadro de mando** (`docs/specs/analitica.md` §4.5, T2a; `DECISIONES #735`): el filtro propio
 * de «Analítica», distinto del de «Hoy» ({@see DashboardPeriod}) porque contesta a otra pregunta —no «qué
 * viene» sino «cómo fue»— y por eso lleva los periodos CERRADOS (ayer, la semana pasada, el mes pasado) y las
 * ventanas móviles de 30 y 90 días, que un cuadro operativo no necesita.
 *
 * Desde la T2f (`#736`, lo pidió el owner al ver el cuadro) también los trimestres, los años y dos fechas a medida
 * (`Custom`), hasta un año: se calculan en directo contra las tablas de siempre, agrupando por mes.
 *
 * ⚠️ Todo se calcula en la zona del PARQUE (`DisplayTime::timezone()`), y el resultado es una {@see Window} —dos
 * instantes, no dos fechas— porque el dinero se corta por `paid_at`, que es un instante en UTC.
 */
enum ReportPeriod: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case ThisWeek = 'this_week';
    case LastWeek = 'last_week';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case ThisQuarter = 'this_quarter';
    case LastQuarter = 'last_quarter';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';
    case Last30 = 'last_30';
    case Last90 = 'last_90';
    /** Dos fechas del owner (`from`/`to`), hasta un año. */
    case Custom = 'custom';

    /** El periodo con el que abre la página: el mes en curso es la pregunta que más se hace. */
    public const DEFAULT = self::ThisMonth;

    /** Resuelve el valor del filtro a un caso, con respaldo no destructivo al periodo por defecto. */
    public static function fromValue(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::DEFAULT) : self::DEFAULT;
    }

    /**
     * La ventana del periodo, hoy, en la zona del parque. Respeta `Carbon::setTestNow`.
     *
     * ⚠️ Un periodo EN CURSO (hoy, esta semana, este mes…, los últimos 30 días, o unas fechas a medida que llegan a hoy)
     * sale cortado AHORA, y su comparación es el mismo tramo ({@see Window::upTo()}; T0 de
     * `analitica-para-decidir.md` §4.3, `#755`): el cuadro no cuenta días que aún no han pasado.
     *
     * Para `Custom`, las dos fechas (`YYYY-MM-DD`): si faltan, no se entienden o van al revés, se ordenan o se
     * cae al periodo por defecto; si pasan de un año, la ventana se acorta al año desde la primera. Nunca lanza:
     * es la entrada de un filtro.
     */
    public function window(?string $from = null, ?string $to = null): Window
    {
        return $this->wholeWindow($from, $to)->upTo(CarbonImmutable::now());
    }

    /** El periodo ENTERO, sin cortar: del primer día al último, con la unidad por la que se compara. */
    private function wholeWindow(?string $from, ?string $to): Window
    {
        $timezone = DisplayTime::timezone();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if ($this === self::Custom) {
            return self::custom($from, $to, $timezone) ?? self::DEFAULT->wholeWindow(null, null);
        }

        [$first, $last] = match ($this) {
            self::Today => [$today, $today],
            self::Yesterday => [$today->subDay(), $today->subDay()],
            self::ThisWeek => [
                $today->startOfWeek(CarbonInterface::MONDAY),
                $today->endOfWeek(CarbonInterface::SUNDAY),
            ],
            self::LastWeek => [
                $today->subWeek()->startOfWeek(CarbonInterface::MONDAY),
                $today->subWeek()->endOfWeek(CarbonInterface::SUNDAY),
            ],
            self::ThisMonth => [$today->startOfMonth(), $today->endOfMonth()],
            self::LastMonth => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth(),
            ],
            self::ThisQuarter => [$today->startOfQuarter(), $today->endOfQuarter()],
            self::LastQuarter => [
                $today->subQuarterNoOverflow()->startOfQuarter(),
                $today->subQuarterNoOverflow()->endOfQuarter(),
            ],
            self::ThisYear => [$today->startOfYear(), $today->endOfYear()],
            self::LastYear => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
            self::Last30 => [$today->subDays(29), $today],
            self::Last90 => [$today->subDays(89), $today],
        };

        return Window::ofDays($first, $last, $timezone, $this->unit());
    }

    /** Por qué unidad se desplaza el periodo para compararse ({@see Window::previous()}). */
    private function unit(): string
    {
        return match ($this) {
            self::Today, self::Yesterday => Window::UNIT_DAY,
            self::ThisWeek, self::LastWeek => Window::UNIT_WEEK,
            self::ThisMonth, self::LastMonth => Window::UNIT_MONTH,
            self::ThisQuarter, self::LastQuarter => Window::UNIT_QUARTER,
            self::ThisYear, self::LastYear => Window::UNIT_YEAR,
            self::Last30, self::Last90, self::Custom => Window::UNIT_SPAN,
        };
    }

    private static function custom(?string $from, ?string $to, string $timezone): ?Window
    {
        $first = self::civil($from, $timezone);
        $last = self::civil($to, $timezone) ?? $first;

        if ($first === null || $last === null) {
            return null;
        }

        if ($last < $first) {
            [$first, $last] = [$last, $first];
        }

        if ($first->diffInDays($last) >= Window::MAX_DAYS) {
            $last = $first->addDays(Window::MAX_DAYS - 1);
        }

        return Window::ofDays($first, $last, $timezone);
    }

    private static function civil(?string $date, string $timezone): ?CarbonImmutable
    {
        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}/', $date) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10), $timezone) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isCustom(): bool
    {
        return $this === self::Custom;
    }

    public function label(): string
    {
        return __('admin.analytics.period.'.$this->value);
    }

    /**
     * Opciones para el filtro (valor => etiqueta), en el orden de los casos.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
