<?php

namespace App\Domain\Platform\Services\Analytics\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * **Una ventana de tiempo del cuadro de mando** (`docs/specs/analitica.md` §4.5, T2a; `DECISIONES #735`):
 * del primer instante del primer día al primer instante del día SIGUIENTE al último, en la zona del parque.
 * Dos INSTANTES medio abiertos, `[from, to)`, y no dos fechas: es lo único que se puede comparar con un
 * `paid_at` guardado en UTC sin equivocarse en el cambio de hora —treinta días de octubre no duran 720 horas—.
 *
 * De aquí salen las tres cosas que el SQL y el gráfico necesitan y que no pueden divergir:
 *  - los bordes en UTC ({@see utcFrom()} / {@see utcTo()}), que es lo que va en el `WHERE`;
 *  - la granularidad ({@see granularity()}): por DÍA hasta 31 días, por SEMANA hasta 92 y por MES hasta el año
 *    (T2f, `#736`: en directo contra las tablas de siempre; el roll-up diario de la T2e queda para el volumen);
 *  - y la clave de cubo de un instante cualquiera ({@see bucketKey()}), que es cómo un cobro a las 00:30 de
 *    Madrid cae en SU día y no en el anterior, que es donde lo pondría `DATE(paid_at)`.
 */
final readonly class Window
{
    public const GRANULARITY_DAY = 'day';

    public const GRANULARITY_WEEK = 'week';

    public const GRANULARITY_MONTH = 'month';

    /** Hasta cuántos días se agrupa por día; más allá, por semana; y más allá de un trimestre, por mes. */
    public const MAX_DAYS_BY_DAY = 31;

    public const MAX_DAYS_BY_WEEK = 92;

    /** Una ventana no pasa de un año y un día: el cuadro es en directo y el año es el periodo más largo. */
    public const MAX_DAYS = 366;

    private function __construct(
        /** El primer instante de la ventana, en la zona del parque (inclusive). */
        public CarbonImmutable $from,
        /** El primer instante FUERA de la ventana, en la zona del parque (exclusivo). */
        public CarbonImmutable $to,
        public string $timezone,
    ) {}

    /** La ventana que cubre los días civiles `[$firstDay, $lastDay]` de la zona dada, ambos inclusive. */
    public static function ofDays(CarbonInterface $firstDay, CarbonInterface $lastDay, string $timezone): self
    {
        $from = CarbonImmutable::instance($firstDay)->setTimezone($timezone)->startOfDay();
        $to = CarbonImmutable::instance($lastDay)->setTimezone($timezone)->startOfDay()->addDay();

        if ($to <= $from) {
            throw new InvalidArgumentException('A window needs at least one day: the last day is before the first.');
        }

        $window = new self($from, $to, $timezone);

        if ($window->days() > self::MAX_DAYS) {
            throw new InvalidArgumentException('A window is at most a year and a day long: the dashboard is live, and the year is the longest period.');
        }

        return $window;
    }

    /**
     * Cuántos días CIVILES cubre. Se cuenta sobre las fechas y no sobre los instantes a propósito: entre dos
     * medianoches que cruzan el cambio de hora hay 23 o 25 horas, y `diffInDays` sobre instantes redondearía.
     */
    public function days(): int
    {
        return (int) self::civil($this->from)->diffInDays(self::civil($this->to));
    }

    /** La ventana anterior de la MISMA longitud, pegada por delante (para el «frente al periodo anterior»). */
    public function previous(): self
    {
        $lastDay = $this->from->subDay();

        return self::ofDays($lastDay->subDays($this->days() - 1), $lastDay, $this->timezone);
    }

    /**
     * Los MISMOS días civiles un año antes (para el «frente al mismo periodo del año pasado»): junio contra
     * junio, el segundo trimestre contra el segundo trimestre. Un 29 de febrero cae al 28.
     */
    public function yearAgo(): self
    {
        return self::ofDays($this->from->subYearNoOverflow(), $this->to->subDay()->subYearNoOverflow(), $this->timezone);
    }

    public function utcFrom(): CarbonImmutable
    {
        return $this->from->utc();
    }

    public function utcTo(): CarbonImmutable
    {
        return $this->to->utc();
    }

    /** El primer día, `YYYY-MM-DD` (para lo que se corta por fecha de VISITA, `slots.date`). */
    public function dateFrom(): string
    {
        return $this->from->toDateString();
    }

    /** El último día, `YYYY-MM-DD`, inclusive. */
    public function dateTo(): string
    {
        return $this->to->subDay()->toDateString();
    }

    public function granularity(): string
    {
        $days = $this->days();

        return match (true) {
            $days <= self::MAX_DAYS_BY_DAY => self::GRANULARITY_DAY,
            $days <= self::MAX_DAYS_BY_WEEK => self::GRANULARITY_WEEK,
            default => self::GRANULARITY_MONTH,
        };
    }

    public function contains(CarbonInterface $instant): bool
    {
        $at = CarbonImmutable::instance($instant);

        return $at >= $this->from && $at < $this->to;
    }

    /**
     * La clave del cubo en el que cae un instante: el día del PARQUE (`YYYY-MM-DD`) o, por semanas, el lunes
     * de su semana en el parque. ⚠️ Se convierte de zona ANTES de mirar el día: es la trampa del reloj del
     * carril, y aquí es donde se paga o no se paga.
     */
    public function bucketKey(CarbonInterface $instant): string
    {
        $local = CarbonImmutable::instance($instant)->setTimezone($this->timezone);

        return match ($this->granularity()) {
            self::GRANULARITY_DAY => $local->toDateString(),
            self::GRANULARITY_WEEK => $local->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
            default => $local->startOfMonth()->toDateString(),
        };
    }

    /**
     * Todas las claves de cubo de la ventana, en orden y SIN huecos: un día sin ventas tiene que salir con un
     * cero, no desaparecer del gráfico. Por semanas, la primera clave es el lunes de la semana del primer día
     * aunque ese lunes quede fuera de la ventana.
     *
     * @return list<string>
     */
    public function bucketKeys(): array
    {
        $keys = [];
        for ($day = $this->from; $day < $this->to; $day = $day->addDay()) {
            $key = $this->bucketKey($day);
            if (($keys[array_key_last($keys) ?? -1] ?? null) !== $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** La fecha civil como instante UTC a medianoche: aritmética de calendario sin cambio de hora. */
    private static function civil(CarbonImmutable $at): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $at->toDateString(), 'UTC');
    }
}
