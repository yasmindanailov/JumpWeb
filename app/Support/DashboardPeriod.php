<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Fase 7.4 iter2 — periodo del filtro compartido del dashboard (HOY · ESTA
 * SEMANA · ESTE MES). Centraliza el mapeo periodo → rango de fechas para que el
 * filtro de la página y los dos widgets no deriven. Las fechas se calculan en la
 * zona de presentación del parque (`DisplayTime`), coherente con cómo se guardan
 * `slots.date` (hora de pared, no UTC).
 */
enum DashboardPeriod: string
{
    case Today = 'today';
    case Week = 'week';
    case Month = 'month';

    /** Resuelve el valor del filtro a un caso, con fallback no destructivo a HOY. */
    public static function fromValue(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Today) : self::Today;
    }

    /**
     * Rango [desde, hasta] en formato YYYY-MM-DD (inclusive en ambos extremos),
     * usado con `whereBetween('slots.date', …)`. La semana es lunes→domingo
     * (coherente con `firstDay: 1` del calendario).
     *
     * @return array{0: string, 1: string}
     */
    public function range(): array
    {
        $now = CarbonImmutable::now(DisplayTime::timezone());

        return match ($this) {
            self::Today => [$now->toDateString(), $now->toDateString()],
            self::Week => [
                $now->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
                $now->endOfWeek(CarbonInterface::SUNDAY)->toDateString(),
            ],
            self::Month => [
                $now->startOfMonth()->toDateString(),
                $now->endOfMonth()->toDateString(),
            ],
        };
    }

    public function label(): string
    {
        return __('admin.dashboard.period.'.$this->value);
    }

    /**
     * Opciones para el filtro (valor => etiqueta), en orden HOY → SEMANA → MES.
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
