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
 * ⚠️ Todo se calcula en la zona del PARQUE (`DisplayTime::timezone()`), y el resultado es una {@see Window} —dos
 * instantes, no dos fechas— porque el dinero se corta por `paid_at`, que es un instante en UTC.
 * ⚠️ Más de 90 días no existe todavía: es la T2e (`analytics_daily`).
 */
enum ReportPeriod: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case ThisWeek = 'this_week';
    case LastWeek = 'last_week';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case Last30 = 'last_30';
    case Last90 = 'last_90';

    /** El periodo con el que abre la página: el mes en curso es la pregunta que más se hace. */
    public const DEFAULT = self::ThisMonth;

    /** Resuelve el valor del filtro a un caso, con respaldo no destructivo al periodo por defecto. */
    public static function fromValue(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::DEFAULT) : self::DEFAULT;
    }

    /** La ventana del periodo, hoy, en la zona del parque. Respeta `Carbon::setTestNow`. */
    public function window(): Window
    {
        $timezone = DisplayTime::timezone();
        $today = CarbonImmutable::now($timezone)->startOfDay();

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
            self::Last30 => [$today->subDays(29), $today],
            self::Last90 => [$today->subDays(89), $today],
        };

        return Window::ofDays($first, $last, $timezone);
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
