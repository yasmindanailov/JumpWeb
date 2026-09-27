<?php

namespace App\Domain\Platform\Services\Analytics\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
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
 *
 * ⚠️⚠️ **Un periodo EN CURSO termina AHORA, y se compara con el MISMO TRAMO** (T0 de `analitica-para-decidir.md` §4.3,
 * `#755`). Medido el 27-09: «Este mes» llegaba al día 30 y se comparaba con 30 días enteros, así que el día 27 cada Δ
 * salía unos diez puntos más bajo de lo que era (y «Este año», contra el año entero). Por eso la ventana sabe su UNIDAD
 * y su FIN de periodo ({@see $end}), {@see upTo()} la corta en el instante de ahora, y {@see previous()} y
 * {@see yearAgo()} la desplazan ENTERA —el principio, el corte y el fin— por su unidad: el 1–27 de septiembre hasta las
 * 15:40 contra el 1–27 de agosto hasta las 15:40. Día y semana van contra el mismo día de la SEMANA (−7 y −364 días):
 * en un parque un sábado no se compara con un viernes.
 */
final readonly class Window
{
    /** Hoy, ayer: se comparan con el mismo día de la semana anterior (o de hace 52 semanas). */
    public const UNIT_DAY = 'day';

    public const UNIT_WEEK = 'week';

    public const UNIT_MONTH = 'month';

    public const UNIT_QUARTER = 'quarter';

    public const UNIT_YEAR = 'year';

    /** 30 y 90 días y a medida: la ventana anterior es la pegada por delante, de la misma longitud. */
    public const UNIT_SPAN = 'span';

    public const UNITS = [self::UNIT_DAY, self::UNIT_WEEK, self::UNIT_MONTH, self::UNIT_QUARTER, self::UNIT_YEAR, self::UNIT_SPAN];

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
        /** El primer instante FUERA de la ventana, en la zona del parque (exclusivo): el fin del periodo o AHORA. */
        public CarbonImmutable $to,
        public string $timezone,
        /** Por dónde se desplaza para compararse (`UNIT_*`). */
        public string $unit,
        /** El fin del PERIODO (exclusivo), aunque la ventana se haya cortado antes en {@see upTo()}. */
        public CarbonImmutable $end,
    ) {}

    /** La ventana que cubre los días civiles `[$firstDay, $lastDay]` de la zona dada, ambos inclusive. */
    public static function ofDays(CarbonInterface $firstDay, CarbonInterface $lastDay, string $timezone, string $unit = self::UNIT_SPAN): self
    {
        $from = CarbonImmutable::instance($firstDay)->setTimezone($timezone)->startOfDay();
        $to = CarbonImmutable::instance($lastDay)->setTimezone($timezone)->startOfDay()->addDay();

        if ($to <= $from) {
            throw new InvalidArgumentException('A window needs at least one day: the last day is before the first.');
        }

        if (! in_array($unit, self::UNITS, true)) {
            throw new InvalidArgumentException("Unknown window unit «{$unit}».");
        }

        $window = new self($from, $to, $timezone, $unit, $to);

        if ($window->days() > self::MAX_DAYS) {
            throw new InvalidArgumentException('A window is at most a year and a day long: the dashboard is live, and the year is the longest period.');
        }

        return $window;
    }

    /**
     * La ventana HASTA el instante dado si el periodo está en curso (lo corta ahí); si todavía no ha empezado o ya
     * terminó, la misma. El fin del periodo se conserva: es lo que {@see previous()} y {@see yearAgo()} desplazan.
     *
     * ⚠️ El corte es el principio del SEGUNDO SIGUIENTE: la base de datos guarda los instantes sin fracción, y lo
     * escrito en el segundo en curso (un pedido de hace un instante, `created_at` = ahora) tiene que entrar.
     */
    public function upTo(CarbonInterface $now): self
    {
        $at = CarbonImmutable::instance($now)->setTimezone($this->timezone)->startOfSecond()->addSecond();

        if ($at <= $this->from || $at >= $this->to) {
            return $this;
        }

        return new self($this->from, $at, $this->timezone, $this->unit, $this->end);
    }

    /** ¿Está cortada en {@see upTo()}? Entonces es «hasta ahora» y su comparación, el mismo tramo. */
    public function isInProgress(): bool
    {
        return $this->to < $this->end;
    }

    /**
     * Cuántos días CIVILES toca. Se cuenta sobre las fechas y no sobre los instantes a propósito: entre dos
     * medianoches que cruzan el cambio de hora hay 23 o 25 horas, y `diffInDays` sobre instantes redondearía. Un día
     * a medias (el de hoy en una ventana en curso) cuenta.
     */
    public function days(): int
    {
        return (int) self::civil($this->from)->diffInDays(self::civil($this->lastInstant())) + 1;
    }

    /**
     * La ventana con la que se compara «frente al periodo anterior», desplazada ENTERA por su unidad: el día y la
     * semana, una semana atrás (el mismo día de la semana); el mes, el trimestre y el año, uno atrás (el mismo tramo:
     * del 1 al 27); 30, 90 días y a medida, la pegada por delante de la misma longitud.
     */
    public function previous(): self
    {
        return match ($this->unit) {
            self::UNIT_DAY, self::UNIT_WEEK => $this->shifted(static fn (CarbonImmutable $at): CarbonImmutable => $at->subDays(7)),
            self::UNIT_MONTH => $this->shifted(static fn (CarbonImmutable $at): CarbonImmutable => $at->subMonthsNoOverflow(1)),
            self::UNIT_QUARTER => $this->shifted(static fn (CarbonImmutable $at): CarbonImmutable => $at->subMonthsNoOverflow(3)),
            self::UNIT_YEAR => $this->shifted(static fn (CarbonImmutable $at): CarbonImmutable => $at->subYearsNoOverflow(1)),
            default => $this->shifted(fn (CarbonImmutable $at): CarbonImmutable => $at->subDays($this->periodDays())),
        };
    }

    /**
     * El mismo periodo del año pasado: los MISMOS días civiles (junio contra junio; un 29 de febrero cae al 28), y el
     * día y la semana, 52 semanas atrás, para que un sábado siga siendo un sábado.
     */
    public function yearAgo(): self
    {
        return match ($this->unit) {
            self::UNIT_DAY, self::UNIT_WEEK => $this->shifted(static fn (CarbonImmutable $at): CarbonImmutable => $at->subDays(364)),
            default => $this->shifted(static fn (CarbonImmutable $at): CarbonImmutable => $at->subYearsNoOverflow(1)),
        };
    }

    /**
     * Desplaza el principio, el fin del periodo y el corte con la MISMA regla, en la hora de pared del parque (una
     * semana atrás a las 15:40 son las 15:40, también cruzando el cambio de hora).
     *
     * El corte desplazado nunca pasa del fin desplazado, y no hace falta frenarlo: los desplazamientos son MONÓTONOS
     * (restar días, o meses y años SIN desbordar), así que no invierten dos instantes. El 31 de marzo a las 09:00 cae
     * al 28 de febrero a las 09:00, antes del 1 de marzo: febrero entero. Lo sostiene el `NoOverflow` (con `subMonth` a
     * secas el 31 de marzo daría el 3 de marzo), y lo vigila el arnés (medido el 27-09: un freno aquí era código muerto).
     *
     * @param  Closure(CarbonImmutable): CarbonImmutable  $shift
     */
    private function shifted(Closure $shift): self
    {
        $end = $shift($this->end);

        return new self($shift($this->from), $this->isInProgress() ? $shift($this->to) : $end, $this->timezone, $this->unit, $end);
    }

    /** Los días civiles del PERIODO entero, cortado o no: lo que se desplaza una ventana a medida o de 30 días. */
    private function periodDays(): int
    {
        return (int) self::civil($this->from)->diffInDays(self::civil($this->end));
    }

    /** El último instante DENTRO de la ventana (`to` es exclusivo), para saber qué día civil toca. */
    private function lastInstant(): CarbonImmutable
    {
        return $this->to->subMicroseconds(1);
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

    /** El último día, `YYYY-MM-DD`, inclusive (hoy, en una ventana en curso). */
    public function dateTo(): string
    {
        return $this->lastInstant()->toDateString();
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
