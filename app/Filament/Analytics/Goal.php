<?php

namespace App\Filament\Analytics;

use App\Domain\Platform\Services\Analytics\AnalyticsGoals;
use App\Domain\Platform\Services\Analytics\Reports\Window;

/**
 * **El objetivo del mes de una cifra** (T3c·2 de `specs/analitica-para-decidir.md` §4.4 y §4.13, `DECISIONES #759`): cuánto
 * quiere el parque que valga, y si va bien. Lo guarda {@see AnalyticsGoals}; aquí se decide qué cifras lo admiten y qué dice
 * la tarjeta.
 *
 *  - **Las que ACUMULAN en el mes** ({@see ACCUMULATING}): el objetivo es un total. Con el mes cerrado, alcanzado o no; en
 *    curso, el RITMO: lo que llevas frente a la parte del mes que ha pasado, como si cada día valiera lo mismo (y se dice
 *    así en «¿Cómo se calcula?»). Antes de {@see MIN_ELAPSED} del mes el ritmo no se juzga: un día de ventas no dice nada.
 *  - **Las tasas de NIVEL** ({@see LEVEL}): el objetivo es un nivel; por encima o por debajo, esté el mes en curso o no.
 *
 * Solo con «Este mes» o «El mes pasado» ({@see applies()}): son los únicos periodos que SON un mes, el del objetivo.
 * Las ocho suben cuando va bien (`MetricsCatalogTest` lo vigila): el tono no mira la polaridad.
 */
final readonly class Goal
{
    /** @var list<string> */
    public const ACCUMULATING = ['money.net', 'money.sold', 'money.orders', 'occupancy.visitors', 'parties.parties', 'customers.registrations'];

    /** @var list<string> */
    public const LEVEL = ['occupancy.entries', 'traffic.conversion'];

    /** @var list<string> las ocho, en el orden del formulario */
    public const KEYS = [...self::ACCUMULATING, ...self::LEVEL];

    /**
     * En qué unidad se guarda cada objetivo: la de su cifra (`MetricsCatalogTest` lo compara con el catálogo).
     *
     * @var array<string, string>
     */
    public const UNITS = [
        'money.net' => Metric::UNIT_MONEY,
        'money.sold' => Metric::UNIT_MONEY,
        'money.orders' => Metric::UNIT_COUNT,
        'occupancy.visitors' => Metric::UNIT_COUNT,
        'parties.parties' => Metric::UNIT_COUNT,
        'customers.registrations' => Metric::UNIT_COUNT,
        'occupancy.entries' => Metric::UNIT_RATE,
        'traffic.conversion' => Metric::UNIT_RATE,
    ];

    /** La parte del mes antes de la cual el ritmo no se juzga (un 10 %: los tres primeros días). */
    public const MIN_ELAPSED = 0.1;

    public const STATE_ON_PACE = 'on_pace';

    public const STATE_BEHIND = 'behind';

    public const STATE_EARLY = 'early';

    public const STATE_REACHED = 'reached';

    public const STATE_MISSED = 'missed';

    public const STATE_ABOVE = 'above';

    public const STATE_BELOW = 'below';

    private function __construct(
        /** En la unidad de la cifra: céntimos, unidades o puntos básicos. */
        public int $target,
        /** Qué parte del mes ha pasado: de 0 a 1 (1, cerrado). */
        public float $elapsed,
        public bool $level,
    ) {}

    /** ¿Es la ventana un mes de calendario? Solo entonces una cifra tiene objetivo. */
    public static function applies(Window $window): bool
    {
        return $window->unit === Window::UNIT_MONTH && $window->from->day === 1;
    }

    /** El objetivo de una cifra en esa ventana; `null` si la cifra no admite objetivo o la ventana no es un mes. */
    public static function of(string $key, int $target, Window $window): ?self
    {
        if (! in_array($key, self::KEYS, true) || $target <= 0 || ! self::applies($window)) {
            return null;
        }

        $length = $window->end->getTimestamp() - $window->from->getTimestamp();
        $elapsed = $length > 0 ? ($window->to->getTimestamp() - $window->from->getTimestamp()) / $length : 1.0;

        return new self($target, max(0.0, min(1.0, $elapsed)), in_array($key, self::LEVEL, true));
    }

    /** Qué parte del objetivo lleva, en puntos básicos (10000 = el objetivo entero). */
    public function progress(int $value): int
    {
        return (int) round($value / $this->target * 10000);
    }

    public function state(int $value): string
    {
        if ($this->level) {
            return $value >= $this->target ? self::STATE_ABOVE : self::STATE_BELOW;
        }

        if ($this->elapsed >= 1.0) {
            return $value >= $this->target ? self::STATE_REACHED : self::STATE_MISSED;
        }

        if ($this->elapsed < self::MIN_ELAPSED) {
            return self::STATE_EARLY;
        }

        return $value >= $this->target * $this->elapsed ? self::STATE_ON_PACE : self::STATE_BEHIND;
    }

    /**
     * Lo que la tarjeta dice del objetivo: el estado, su tono (el mismo vocabulario que el veredicto) y la frase.
     *
     * @return array{state: string, tone: string, line: string}
     */
    public function read(Metric $metric): array
    {
        $state = $this->state($metric->value);
        $tone = match ($state) {
            self::STATE_ON_PACE, self::STATE_REACHED, self::STATE_ABOVE => Metric::TONE_GOOD,
            self::STATE_BEHIND, self::STATE_MISSED, self::STATE_BELOW => Metric::TONE_WATCH,
            default => Metric::TONE_NEUTRAL,
        };

        return ['state' => $state, 'tone' => $tone, 'line' => __('admin.analytics.goal.'.$state, [
            'target' => $metric->format($this->target),
            'progress' => self::percent($this->progress($metric->value)),
            'elapsed' => self::percent((int) round($this->elapsed * 10000)),
        ])];
    }

    /** Puntos básicos → «82 %» (sin decimales: es un avance, no una tasa). */
    private static function percent(int $basisPoints): string
    {
        return number_format($basisPoints / 100, 0, ',', '.')."\u{00A0}%";
    }
}
