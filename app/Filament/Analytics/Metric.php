<?php

namespace App\Filament\Analytics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Money;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * **Una cifra del cuadro, con su anatomía** (T0b de `analitica-para-decidir.md` §4.2, `#755`): qué es, cuánto vale,
 * contra qué se compara, si el cambio es bueno o malo, si es un cambio CLARO o puede ser azar, y cómo se calcula. Las
 * 44 tarjetas del cuadro salen de aquí y de UNA vista (`filament.widgets.analytics.metric`); el owner lo pidió el 27-09:
 * «el operador no entiende tantos números».
 *
 * Las reglas, medidas el 27-09 sobre el cuadro de entonces:
 *  - ⚠️ **Sin base no hay porcentaje** («+900 %» era pasar de 4 a 40): el relativo sale solo si el periodo comparado
 *    tiene {@see MIN_BASE} casos o más —el recuento mismo; en el dinero, sus operaciones; en una tasa, su denominador—.
 *    El absoluto sale siempre.
 *  - ⚠️ **Una tasa se compara en PUNTOS** («7,4 % · −20 %» era un relativo de un porcentaje).
 *  - ⚠️ **El color solo si el cambio es CLARO** (al 95 %): en un recuento, la prueba binomial condicionada con la
 *    duración de cada ventana (dos recuentos de Poisson); en una tasa, que los intervalos de Wilson no se solapen (la
 *    regla de los experimentos, {@see ExperimentsReport::wilson()}); en el dinero, con la SUMA DE LOS CUADRADOS de sus
 *    importes, que el informe trae de la misma consulta: una suma (Poisson compuesto: su varianza es esa suma, y los
 *    ritmos se comparan con la duración de cada ventana) o una media (dos medias con su error). Sin esa suma, gris: el
 *    27-09 «Valor medio del pedido −1 %» salía en ROJO por tener base y no tener prueba. Lo que no es claro, gris y dicho.
 *  - La polaridad se DECLARA ({@see Polarity}), sin valor por defecto.
 */
final readonly class Metric
{
    public const UNIT_COUNT = 'count';

    public const UNIT_MONEY = 'money';

    /** Puntos básicos: 10000 = 100 %. */
    public const UNIT_RATE = 'rate';

    /** Una cifra ya escrita que no se compara («56 bots · 1 internas»). */
    public const UNIT_TEXT = 'text';

    /** Casos mínimos en el periodo comparado para dar un porcentaje o un color. */
    public const MIN_BASE = 20;

    /** El valor crítico al 95 % (dos colas), el mismo de los experimentos. */
    private const Z = 1.96;

    /** Desde cuántos céntimos una tarjeta escribe el dinero sin céntimos (100 €). */
    private const WHOLE_EUROS_FROM = 10000;

    private function __construct(
        /** Estable: el censo, el DOM (`data-metric`) y la clave de «¿Cómo se calcula?». */
        public string $key,
        public string $label,
        public string $unit,
        public Polarity $polarity,
        public string $how,
        public int $value = 0,
        public ?int $previous = null,
        /** Los casos que sostienen la cifra en el periodo (dinero: sus operaciones; tasa: su denominador). */
        public ?int $base = null,
        public ?int $previousBase = null,
        /** En una tasa, los aciertos (el numerador) de cada periodo. */
        public ?int $hits = null,
        public ?int $previousHits = null,
        /** El valor ya escrito, cuando no es el número a secas («3 de 15»). */
        public ?string $display = null,
        /** Una línea de contexto bajo la cifra («Cobro medio: 43,74 €»). */
        public ?string $detail = null,
        /** En el dinero, la suma de los cuadrados de cada importe (céntimos²) de cada periodo: su varianza. */
        public ?int $squares = null,
        public ?int $previousSquares = null,
        /** ¿Es una MEDIA de importes (valor medio del pedido) y no una suma? Cambia la prueba. */
        public bool $isMean = false,
        /**
         * La HISTORIA (T3b, `#790`): el valor de la cifra en los periodos comparables anteriores que cuentan —los que empezaron
         * cuando su fuente ya medía—, con la misma fórmula que la tarjeta. `null`: la cifra no tiene historia (sin comparación).
         *
         * @var list<int>|null
         */
        public ?array $history = null,
        /** Cómo se dice la unidad de la historia: `month`, `week`, `quarter`, `year`, `span` o `day:1…7` (el día ISO). */
        public ?string $historyUnit = null,
    ) {}

    /** Periodos de historia que hacen falta para decir si una cifra es normal (§4.4). */
    public const MIN_HISTORY = 8;

    public const VERDICT_NORMAL = 'normal';

    public const VERDICT_HIGH = 'high';

    public const VERDICT_LOW = 'low';

    public const VERDICT_NO_HISTORY = 'no_history';

    public const VERDICT_FEW = 'few';

    public const TONE_GOOD = 'good';

    public const TONE_WATCH = 'watch';

    public const TONE_NEUTRAL = 'neutral';

    public static function count(string $key, string $label, int $value, ?int $previous, Polarity $polarity, string $how, ?string $display = null, ?string $detail = null): self
    {
        return new self($key, $label, self::UNIT_COUNT, $polarity, $how, $value, $previous, $value, $previous, display: $display, detail: $detail);
    }

    /**
     * Un recuento de UNIDADES QUE LLEGAN EN LOTES (T3a, `#759`): las plazas de las visitas, que vienen de reserva en reserva
     * —una entrada, una fiesta de veinte—. No es un Poisson: veinte plazas de una fiesta no son veinte sucesos, y probarlas
     * como tales daría por claro un cambio que es una fiesta más. Se prueba como una suma de lotes (la varianza es la suma
     * de los CUADRADOS de cada lote, como el dinero) y su base son las reservas (`$operations`), no las plazas.
     */
    public static function units(
        string $key, string $label, int $units, ?int $previousUnits, int $operations, ?int $previousOperations, int $squares, ?int $previousSquares,
        Polarity $polarity, string $how, ?string $detail = null,
    ): self {
        return new self(
            $key, $label, self::UNIT_COUNT, $polarity, $how, $units, $previousUnits, $operations, $previousOperations,
            detail: $detail, squares: $squares, previousSquares: $previousSquares,
        );
    }

    /**
     * Dinero en céntimos; `$operations` son los casos que lo sostienen (cobros, pedidos…), para la regla de la base, y
     * `$squares` la suma de los cuadrados de sus importes, para la prueba. Una MEDIA (`$mean`) se prueba como media.
     */
    public static function money(
        string $key, string $label, int $cents, ?int $previousCents, int $operations, ?int $previousOperations, Polarity $polarity, string $how,
        ?string $detail = null, ?int $squares = null, ?int $previousSquares = null, bool $mean = false,
    ): self {
        return new self(
            $key, $label, self::UNIT_MONEY, $polarity, $how, $cents, $previousCents, $operations, $previousOperations,
            detail: $detail, squares: $squares, previousSquares: $previousSquares, isMean: $mean,
        );
    }

    /**
     * Una tasa desde sus dos números: el valor se calcula AQUÍ, en un solo sitio, y con ellos el intervalo. ⚠️ Los
     * aciertos PUEDEN pasar de los casos cuando son cosas distintas —la conversión son pedidos entre visitas, y un pedido
     * de la app sin sesión existe—: el valor se enseña tal cual y el intervalo se calcula con la tasa recortada a 100 %.
     */
    public static function rate(string $key, string $label, int $hits, int $of, ?int $previousHits, ?int $previousOf, Polarity $polarity, string $how, ?string $detail = null): self
    {
        if ($hits < 0 || $of < 0) {
            throw new InvalidArgumentException("Una tasa no tiene números negativos («{$key}»: {$hits} de {$of}).");
        }

        return new self(
            $key, $label, self::UNIT_RATE, $polarity, $how,
            self::bp($hits, $of),
            $previousOf === null || $previousHits === null ? null : self::bp($previousHits, $previousOf),
            $of, $previousOf, $hits, $previousHits,
            detail: $detail,
        );
    }

    public static function text(string $key, string $label, string $display, string $how, ?string $detail = null): self
    {
        return new self($key, $label, self::UNIT_TEXT, Polarity::Neutral, $how, display: $display, detail: $detail);
    }

    /** El valor como se lee en la tarjeta. */
    public function displayValue(): string
    {
        return $this->display ?? $this->format($this->value);
    }

    /** Un número de esta cifra, escrito como su valor (euros, porcentaje o recuento): la banda de la historia lo usa. */
    public function format(int $value): string
    {
        return match ($this->unit) {
            self::UNIT_MONEY => self::euros($value),
            self::UNIT_RATE => self::percent($value),
            default => number_format($value, 0, ',', '.'),
        };
    }

    /**
     * La misma cifra con su historia (T3b, `#790`).
     *
     * @param  list<int>  $history
     */
    public function withHistory(array $history, string $unit): self
    {
        return new self(
            $this->key, $this->label, $this->unit, $this->polarity, $this->how, $this->value, $this->previous, $this->base, $this->previousBase,
            $this->hits, $this->previousHits, $this->display, $this->detail, $this->squares, $this->previousSquares, $this->isMean,
            $history, $unit,
        );
    }

    /**
     * **¿Es normal para ti?** (T3b; `[DECIDIDO owner]` 28-09, `#790`): normal si cae entre la más baja y la más alta de su
     * historia —el rango mín–máx—; fuera, alta o baja. Hacen falta {@see MIN_HISTORY} periodos; una TASA con menos de
     * {@see MIN_BASE} casos no se juzga. El TONO sale de la polaridad: subir es bien, mal o ni lo uno ni lo otro.
     *
     * Simulado el 28-09: un periodo normal cae fuera del mín–máx de 12 el 15 % de las veces (de la P25–P75, el 57 %).
     *
     * @return array{state: string, tone: string, low: ?int, high: ?int, n: int}|null `null`: sin historia (ni se dice)
     */
    public function verdict(): ?array
    {
        if ($this->history === null || $this->unit === self::UNIT_TEXT) {
            return null;
        }

        $n = count($this->history);
        $none = ['tone' => self::TONE_NEUTRAL, 'low' => null, 'high' => null, 'n' => $n];

        if ($n < self::MIN_HISTORY) {
            return ['state' => self::VERDICT_NO_HISTORY] + $none;
        }

        // Una tasa o una MEDIA con pocos casos no se juzga: con dos pedidos, el valor medio es casi azar.
        if (($this->unit === self::UNIT_RATE || $this->isMean) && ($this->base ?? 0) < self::MIN_BASE) {
            return ['state' => self::VERDICT_FEW] + $none;
        }

        $low = min($this->history);
        $high = max($this->history);
        $state = match (true) {
            $this->value < $low => self::VERDICT_LOW,
            $this->value > $high => self::VERDICT_HIGH,
            default => self::VERDICT_NORMAL,
        };
        $tone = match (true) {
            $state === self::VERDICT_NORMAL, $this->polarity === Polarity::Neutral => self::TONE_NEUTRAL,
            ($state === self::VERDICT_HIGH) === ($this->polarity === Polarity::UpIsGood) => self::TONE_GOOD,
            default => self::TONE_WATCH,
        };

        return ['state' => $state, 'tone' => $tone, 'low' => $low, 'high' => $high, 'n' => $n];
    }

    /**
     * **La frase del veredicto** (§4.6): lo que la tarjeta dice en palabras, con la banda y la unidad («Normal para ti: entre
     * 1.200 € y 1.900 € en tus últimos 12 meses»). El cambio ya lo dice su línea (T0b): aquí no se repite.
     */
    public function verdictLine(): ?string
    {
        $verdict = $this->verdict();

        if ($verdict === null) {
            return null;
        }

        $unit = (string) $this->historyUnit;
        $weekday = str_starts_with($unit, 'day:') ? __('admin.analytics.verdict.weekday.'.substr($unit, 4)) : null;

        if ($verdict['state'] === self::VERDICT_NO_HISTORY) {
            return __('admin.analytics.verdict.no_history', ['n' => $verdict['n'], 'min' => self::MIN_HISTORY, 'unit' => $weekday ?? __('admin.analytics.verdict.unit.'.$unit)]);
        }

        if ($verdict['state'] === self::VERDICT_FEW) {
            return __('admin.analytics.verdict.few', ['min' => self::MIN_BASE]);
        }

        // «tus últimas 12 semanas», «tus últimos 12 miércoles»: el adjetivo concuerda con la unidad.
        $span = $weekday !== null
            ? __('admin.analytics.verdict.span.day', ['n' => $verdict['n'], 'weekday' => $weekday])
            : __('admin.analytics.verdict.span.'.$unit, ['n' => $verdict['n']]);
        $key = $verdict['state'] === self::VERDICT_NORMAL ? 'normal' : $verdict['state'].'_'.$verdict['tone'];
        // Una historia PLANA dice «siempre 0,00 €», no «entre 0,00 € y 0,00 €» (visto el 28-09: un lunes a primera hora).
        $flat = $verdict['low'] === $verdict['high'];
        $values = ['low' => $this->format((int) $verdict['low']), 'high' => $this->format((int) $verdict['high'])];

        return __('admin.analytics.verdict.'.$key, [
            'span' => $span,
            'range' => __('admin.analytics.verdict.'.($flat ? 'flat' : 'range'), $values),
            'before' => __('admin.analytics.verdict.'.($flat ? 'before_flat' : 'before_range'), $values),
        ]);
    }

    /**
     * La lectura del cambio: la línea («+13 (+38 %) frente al periodo anterior»), su color y su icono, y las notas
     * (el intervalo de una tasa; «pocos datos»; «no es un cambio claro»).
     *
     * @param  float  $share  qué parte del tiempo de las dos ventanas es la del periodo (0,5 si duran lo mismo): la
     *                        prueba de un recuento compara ritmos, no totales.
     * @return array{line: ?string, color: string, icon: ?Heroicon, notes: list<string>}
     */
    public function reading(Comparison $comparison, float $share = 0.5): array
    {
        $notes = [];

        if ($this->unit === self::UNIT_RATE && $this->base !== null && $this->base > 0 && $this->hits !== null) {
            $interval = ExperimentsReport::wilson(min($this->hits, $this->base), $this->base);
            $notes[] = __('admin.analytics.metric.interval', [
                'low' => self::percent($interval['low_bp']),
                'high' => self::percent($interval['high_bp']),
                'n' => number_format($this->base, 0, ',', '.'),
            ]);
        }

        if ($this->unit === self::UNIT_TEXT || $this->previous === null) {
            return ['line' => null, 'color' => 'gray', 'icon' => null, 'notes' => $notes];
        }

        // Un cero antes puede ser «no pasó nada» o «aún no se medía» (sin datos de 2025): «+1.531 € frente al año pasado»
        // sugeriría un crecimiento que nadie ha visto. Se dice lo que se sabe (y va antes que «igual»: 0 contra 0 también).
        if ($this->previous === 0) {
            return [
                'line' => __('admin.analytics.delta.none_'.$comparison->value),
                'color' => 'gray',
                'icon' => Heroicon::OutlinedMinus,
                'notes' => $notes,
            ];
        }

        $diff = $this->value - $this->previous;

        if ($diff === 0) {
            return [
                'line' => __('admin.analytics.delta.same_'.$comparison->value),
                'color' => 'gray',
                'icon' => Heroicon::OutlinedMinus,
                'notes' => $notes,
            ];
        }

        $enough = $this->hasBase();
        $clear = $enough && $this->isClear($share);

        if (! $enough) {
            $notes[] = __('admin.analytics.metric.few', ['min' => self::MIN_BASE]);
        } elseif (! $clear) {
            $notes[] = __('admin.analytics.metric.unclear');
        }

        $up = $diff > 0;

        return [
            'line' => __('admin.analytics.delta.vs_'.$comparison->value, ['delta' => $this->change($diff, $enough)]),
            'color' => match (true) {
                ! $clear, $this->polarity === Polarity::Neutral => 'gray',
                $up === ($this->polarity === Polarity::UpIsGood) => 'success',
                default => 'danger',
            },
            'icon' => $up ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown,
            'notes' => $notes,
        ];
    }

    /** ¿Tiene el periodo comparado casos suficientes (y la tasa, también el actual)? */
    private function hasBase(): bool
    {
        if ($this->previousBase === null || $this->previousBase < self::MIN_BASE) {
            return false;
        }

        return $this->unit !== self::UNIT_RATE || ($this->base ?? 0) >= self::MIN_BASE;
    }

    /** ¿Es un cambio claro al 95 %? Se llama solo con base suficiente. */
    private function isClear(float $share): bool
    {
        return match ($this->unit) {
            // Con sus cuadrados es un recuento EN LOTES ({@see units()}): una suma, como el dinero.
            self::UNIT_COUNT => $this->squares !== null
                ? $this->previousSquares !== null && self::sumsDiffer($this->value, (int) $this->previous, $this->squares, $this->previousSquares, $share)
                : self::countsDiffer($this->value, (int) $this->previous, $share),
            self::UNIT_RATE => self::ratesDiffer((int) $this->hits, (int) $this->base, (int) $this->previousHits, (int) $this->previousBase),
            self::UNIT_MONEY => $this->moneyDiffers($share),
            default => false,
        };
    }

    /** El dinero, con la suma de los cuadrados de sus importes; sin ella no se puede saber, y no se colorea. */
    private function moneyDiffers(float $share): bool
    {
        if ($this->squares === null || $this->previousSquares === null || $this->previous === null) {
            return false;
        }

        return $this->isMean
            ? self::meansDiffer($this->value, $this->previous, (int) $this->base, (int) $this->previousBase, $this->squares, $this->previousSquares)
            : self::sumsDiffer($this->value, $this->previous, $this->squares, $this->previousSquares, $share);
    }

    /**
     * Dos sumas de importes (Poisson compuesto): la varianza de cada una es la suma de los cuadrados de sus importes, y
     * sin cambio el periodo esperaría `k` veces lo de antes, con `k` la razón de las duraciones.
     */
    private static function sumsDiffer(int $current, int $previous, int $squares, int $previousSquares, float $share): bool
    {
        if ($share <= 0.0 || $share >= 1.0) {
            return false;
        }

        $k = $share / (1 - $share);
        $variance = $squares + $k * $k * $previousSquares;

        if ($variance <= 0.0) {
            return false;
        }

        return abs(($current - $k * $previous) / sqrt($variance)) >= self::Z;
    }

    /** Dos medias de importes, cada una con su error (la varianza muestral sale de la suma de los cuadrados). */
    private static function meansDiffer(int $mean, int $previousMean, int $n, int $previousN, int $squares, int $previousSquares): bool
    {
        if ($n < 2 || $previousN < 2) {
            return false;
        }

        $variance = max(0.0, $squares / $n - $mean * $mean);
        $previousVariance = max(0.0, $previousSquares / $previousN - $previousMean * $previousMean);
        $error = $variance / $n + $previousVariance / $previousN;

        if ($error <= 0.0) {
            return $mean !== $previousMean;   // importes todos iguales: la diferencia es cierta
        }

        return abs(($mean - $previousMean) / sqrt($error)) >= self::Z;
    }

    /**
     * Dos recuentos de Poisson en ventanas de duración distinta: condicionado al total, el del periodo es binomial con
     * probabilidad `$share`; el cambio es claro si se aleja más de 1,96 desviaciones de lo esperado.
     */
    private static function countsDiffer(int $current, int $previous, float $share): bool
    {
        $n = $current + $previous;

        if ($n === 0 || $share <= 0.0 || $share >= 1.0) {
            return false;
        }

        $z = ($current - $n * $share) / sqrt($n * $share * (1 - $share));

        return abs($z) >= self::Z;
    }

    /** Dos tasas se distinguen si sus intervalos de Wilson al 95 % no se solapan (la regla de los experimentos). */
    private static function ratesDiffer(int $hits, int $of, int $previousHits, int $previousOf): bool
    {
        $now = ExperimentsReport::wilson(min($hits, $of), $of);
        $before = ExperimentsReport::wilson(min($previousHits, $previousOf), $previousOf);

        return $now['low_bp'] > $before['high_bp'] || $now['high_bp'] < $before['low_bp'];
    }

    /** «+13 (+38 %)», «+310 € (+8 %)», «−1,2 puntos»; el relativo solo con base. */
    private function change(int $diff, bool $withPercent): string
    {
        $sign = $diff > 0 ? '+' : '−';

        if ($this->unit === self::UNIT_RATE) {
            return __('admin.analytics.metric.points', ['delta' => $sign.number_format(abs($diff) / 100, 1, ',', '.')]);
        }

        $absolute = $this->unit === self::UNIT_MONEY ? self::euros(abs($diff)) : number_format(abs($diff), 0, ',', '.');

        if (! $withPercent || $this->previous === null || $this->previous === 0) {
            return $sign.$absolute;
        }

        $percent = (int) round(abs($diff) / abs($this->previous) * 100);

        return $sign.$absolute.' ('.$sign.$percent."\u{00A0}%)";
    }

    private static function bp(int $hits, int $of): int
    {
        return $of > 0 ? (int) round($hits / $of * 10000) : 0;
    }

    private static function percent(int $basisPoints): string
    {
        return number_format($basisPoints / 100, 1, ',', '.')."\u{00A0}%";
    }

    /** Desde 100 €, sin céntimos en la tarjeta («1.531 €»); por debajo, con ellos. Las tablas y el CSV, siempre con. */
    private static function euros(int $cents): string
    {
        if (abs($cents) < self::WHOLE_EUROS_FROM) {
            return Money::format($cents);
        }

        return number_format((int) round($cents / 100), 0, ',', '.').' €';
    }
}
