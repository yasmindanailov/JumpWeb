<?php

namespace App\Filament\Analytics;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Contract;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\Metrics\CustomersMetrics;
use App\Filament\Analytics\Metrics\MarketingMetrics;
use App\Filament\Analytics\Metrics\MoneyMetrics;
use App\Filament\Analytics\Metrics\OccupancyMetrics;
use App\Filament\Analytics\Metrics\PartiesMetrics;
use App\Filament\Analytics\Metrics\SurveysMetrics;
use App\Filament\Pages\AnalyticsPage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * **«Explícamelo con IA»** (T3d de `analitica-para-decidir.md` §4.7 y §4.13): el cuadro de un periodo como un texto en Markdown,
 * listo para pegar en el asistente de IA que use el operador. Lleva lo que pide §4.7: las instrucciones, el negocio sin su
 * nombre, el periodo y su comparación, «lo que ha cambiado» y cada cifra con su valor, su comparación, su referencia (la
 * historia del PROPIO negocio, `#790`) y su definición, y cuánto fiarse de los datos. En el idioma del panel.
 *
 * Medido el 29-09: las 58 cifras del cuadro con todo lo suyo ocupan 15,5–16,8 KB, y el techo es {@see MAX_BYTES}. Van las
 * de ARRIBA de cada pestaña ({@see AnalyticsPage::topKeys()}: las que el cuadro dice que deciden) y las plegadas que se
 * salen de lo normal ({@see Changes}); las demás, contadas en una línea: nunca un tope callado.
 *
 * ⚠️ **Sale a un tercero** (`#793`, `RGPD-07`): se compone SOLO desde {@see Metric} —agregados—, nunca desde filas, tablas de
 * desglose ni textos libres; y antes de enseñarlo, si algo tiene pinta de correo o de teléfono ({@see Contract::PII_VALUE_RE}),
 * NO se enseña: falla cerrada, como la ingesta. Una fecha ISO seguida de otra casaba como teléfono: las fechas van como en
 * el panel ({@see WindowLabel}).
 */
final class Explainer
{
    /**
     * El techo del texto, en bytes UTF-8: 12 KB (`[DECIDIDO owner]` 29-09, `#798`; la spec decía 8). Medido: lo normal ocupa
     * 7,7–8,0 KB y el peor caso —las 28 de arriba con rangos largos y cinco plegadas fuera de lo normal—, 10,2 KB.
     */
    public const MAX_BYTES = 12288;

    /** Las cifras de «Calidad del dato» que el texto dice en su sección, no en las tablas. */
    public const QUALITY_KEYS = ['traffic.identified', 'traffic.excluded'];

    /**
     * El texto de una ventana, leído de los seis catálogos (cacheados, con su historia).
     *
     * @return array{text: ?string, bytes: int, metrics: int, refused: bool}
     */
    public static function for(Window $window, Comparison $comparison): array
    {
        $metrics = MoneyMetrics::for($window, $comparison)
            + OccupancyMetrics::for($window, $comparison)
            + CustomersMetrics::for($window, $comparison)
            + MarketingMetrics::for($window, $comparison)
            + PartiesMetrics::for($window, $comparison)
            + SurveysMetrics::for($window, $comparison);

        /** @var array{days: int, total: int} $rejected */
        $rejected = FunnelReport::for($window, $comparison)['rejected'];

        return self::guarded(self::compose($metrics, AnalyticsPage::topKeys(), $window, $comparison, self::sells(), $rejected));
    }

    /**
     * La composición, sin tocar la base de datos.
     *
     * @param  array<string, Metric>  $metrics  las de los seis catálogos, por clave
     * @param  array<string, list<string>>  $top  las de arriba de cada pestaña ({@see AnalyticsPage::topKeys()})
     * @param  list<string>  $sells  los tipos de producto que se venden ({@see TicketType}::TYPE_*)
     * @param  array{days: int, total: int}  $rejected  los eventos de navegación descartados
     * @return array{text: string, metrics: int}
     */
    public static function compose(array $metrics, array $top, Window $window, Comparison $comparison, array $sells, array $rejected): array
    {
        $share = $comparison->share($window);
        $changes = Changes::select($metrics);
        $changed = array_map(static fn (array $item): string => $item['metric']->key, $changes['items']);

        // Las filas de cada pestaña: las de arriba y, detrás, las plegadas que dice «lo que ha cambiado» (como mucho
        // `Changes::MAX`: así el tamaño tiene techo aunque se salgan muchas). Cada clave, una vez.
        $tables = [];
        $seen = [];
        foreach ($top as $tab => $keys) {
            foreach ($keys as $key) {
                if (isset($metrics[$key]) && ! isset($seen[$key])) {
                    $tables[$tab][] = $metrics[$key];
                    $seen[$key] = true;
                }
            }
        }
        foreach ($changed as $key) {
            $tab = AnalyticsPage::tabOf($key);
            if ($tab !== null && ! isset($seen[$key])) {
                $tables[$tab][] = $metrics[$key];
                $seen[$key] = true;
            }
        }
        $rest = count(array_diff(array_keys($metrics), array_keys($seen), self::QUALITY_KEYS));

        $out = [
            '# '.__('admin.analytics.explain.title', ['period' => WindowLabel::period($window)]),
            '## '.__('admin.analytics.explain.ask_heading'),
            __('admin.analytics.explain.ask'),
            '## '.__('admin.analytics.explain.business_heading'),
            self::business($sells),
            '## '.__('admin.analytics.explain.period_heading'),
            __('admin.analytics.explain.period', [
                'period' => WindowLabel::period($window),
                'baseline' => Str::lcfirst(WindowLabel::baseline($comparison->baseline($window))),
                'comparison' => mb_strtolower($comparison->label()),
            ]),
            __('admin.analytics.explain.figures_note', ['unit' => Changes::unitOf($window)]),
            '## '.__('admin.analytics.explain.changes_heading'),
            self::changes($changes, $window),
            '## '.__('admin.analytics.explain.figures_heading'),
        ];

        foreach ($tables as $tab => $rows) {
            $out[] = '### '.__('admin.analytics.tabs.'.$tab);
            $out[] = self::table($rows, $share);
        }
        if ($rest > 0) {
            $out[] = trans_choice('admin.analytics.explain.rest', $rest, ['n' => $rest]);
        }

        $included = array_merge(...array_values($tables));
        $out[] = '## '.__('admin.analytics.explain.definitions_heading');
        $out[] = implode("\n", array_map(static fn (Metric $m): string => '- **'.$m->label.'**: '.self::definition($m->how), $included));

        $out[] = '## '.__('admin.analytics.explain.quality_heading');
        $out[] = self::quality($metrics, $included, $rejected);

        return ['text' => implode("\n\n", $out)."\n", 'metrics' => count($included)];
    }

    /**
     * **La guarda**: si algo tiene pinta de correo o de teléfono, el texto no se enseña (falla cerrada) y queda en el log el
     * número de casos, nunca el contenido.
     *
     * @param  array{text: string, metrics: int}  $composed
     * @return array{text: ?string, bytes: int, metrics: int, refused: bool}
     */
    public static function guarded(array $composed): array
    {
        $hits = preg_match_all(Contract::PII_VALUE_RE, $composed['text']);

        if ($hits !== 0) {
            Log::warning('analytics.explain_refused', ['matches' => $hits]);

            return ['text' => null, 'bytes' => 0, 'metrics' => $composed['metrics'], 'refused' => true];
        }

        return ['text' => $composed['text'], 'bytes' => strlen($composed['text']), 'metrics' => $composed['metrics'], 'refused' => false];
    }

    /**
     * Qué se vende, por los tipos de producto activos a la venta: el negocio en genérico, sin su nombre.
     *
     * @return list<string>
     */
    private static function sells(): array
    {
        $types = TicketType::query()->where('is_active', true)->where('is_sellable', true)->distinct()->pluck('type')->all();

        return array_values(array_intersect([TicketType::TYPE_ENTRY, TicketType::TYPE_PACK, TicketType::TYPE_ADDON], $types));
    }

    /** @param  list<string>  $sells */
    private static function business(array $sells): string
    {
        $what = array_map(static fn (string $type): string => __('admin.analytics.explain.sells.'.$type), $sells);

        return __('admin.analytics.explain.business', [
            'sells' => $what === [] ? __('admin.analytics.explain.sells.none') : implode(', ', $what),
        ]);
    }

    /**
     * «Lo que ha cambiado», con las frases del panel: las cinco primeras y cuántas más.
     *
     * @param  array{items: list<array{metric: Metric, verdict: array{state: string, tone: string, low: ?int, high: ?int, n: int}}>, more: int, judged: int}  $changes
     */
    private static function changes(array $changes, Window $window): string
    {
        if ($changes['items'] === []) {
            return $changes['judged'] > 0
                ? trans_choice('admin.analytics.changes.none', $changes['judged'], ['n' => $changes['judged']])
                : __('admin.analytics.changes.no_history', ['unit' => Changes::unitOf($window)]);
        }

        // Las frases del panel hablan al operador («tus últimas 8 semanas»); este texto habla a la IA en primera persona.
        $lines = array_map(static fn (array $item): string => '- '.__('admin.analytics.explain.change_item', [
            'label' => $item['metric']->label,
            'value' => $item['metric']->displayValue(),
            'normal' => self::normal($item['metric']),
        ]), $changes['items']);
        if ($changes['more'] > 0) {
            $lines[] = '- '.trans_choice('admin.analytics.explain.more', $changes['more'], ['n' => $changes['more']]);
        }

        return implode("\n", $lines);
    }

    /**
     * Una tabla por pestaña: cifra · valor · cambio · normal. Sin columna «antes»: el cambio con su signo ya la lleva, y no
     * cabía en {@see MAX_BYTES} (medido el 29-09).
     *
     * @param  list<Metric>  $rows
     */
    private static function table(array $rows, float $share): string
    {
        $head = [
            __('admin.analytics.explain.col.metric'), __('admin.analytics.explain.col.value'),
            __('admin.analytics.explain.col.change'), __('admin.analytics.explain.col.normal'),
        ];
        $lines = ['| '.implode(' | ', $head).' |', '|'.str_repeat('---|', count($head))];

        foreach ($rows as $m) {
            $cells = [
                $m->label,
                $m->displayValue(),
                self::shift($m, $share),
                self::normal($m),
            ];
            $lines[] = '| '.implode(' | ', array_map([self::class, 'cell'], $cells)).' |';
        }

        return implode("\n", $lines);
    }

    /** El cambio, corto: «+450 € (+12 %), claro». */
    private static function shift(Metric $m, float $share): string
    {
        $shift = $m->shift($share);

        if ($shift === null) {
            return '—';
        }

        return $shift['delta'] === null
            ? __('admin.analytics.explain.shift.'.$shift['state'])
            : __('admin.analytics.explain.shift.'.$shift['state'], ['delta' => $shift['delta']]);
    }

    /** La referencia, corta y con su fuente: el rango de la historia del propio negocio. */
    private static function normal(Metric $m): string
    {
        $verdict = $m->verdict();

        if ($verdict === null) {
            return '—';
        }

        return match ($verdict['state']) {
            Metric::VERDICT_NO_HISTORY => __('admin.analytics.explain.normal.no_history', ['n' => $verdict['n'], 'min' => Metric::MIN_HISTORY]),
            Metric::VERDICT_FEW => __('admin.analytics.explain.normal.few'),
            // La unidad de los periodos va una vez, en la nota de arriba; aquí, cuántos.
            default => __('admin.analytics.explain.normal.'.$verdict['state'], [
                'range' => __('admin.analytics.explain.'.($verdict['low'] === $verdict['high'] ? 'flat' : 'range'), [
                    'low' => $m->format((int) $verdict['low']),
                    'high' => $m->format((int) $verdict['high']),
                ]),
                'n' => $verdict['n'],
            ]),
        };
    }

    /**
     * Cuánto fiarse: las visitas identificadas y lo que queda fuera, lo descartado, cuántas sin historia o con pocos casos, lo
     * que el cuadro no ve y que aún no hay referencia del sector.
     *
     * @param  array<string, Metric>  $metrics
     * @param  list<Metric>  $included
     * @param  array{days: int, total: int}  $rejected
     */
    private static function quality(array $metrics, array $included, array $rejected): string
    {
        $lines = [];
        foreach (self::QUALITY_KEYS as $key) {
            if (isset($metrics[$key])) {
                $lines[] = '- '.$metrics[$key]->label.': '.$metrics[$key]->displayValue().'.';
            }
        }
        if ($rejected['total'] > 0) {
            $lines[] = '- '.__('admin.analytics.explain.quality.rejected', ['n' => $rejected['total'], 'days' => $rejected['days']]);
        }

        $states = array_map(static fn (Metric $m): ?string => $m->verdict()['state'] ?? null, $included);
        $noHistory = count(array_keys($states, Metric::VERDICT_NO_HISTORY, true));
        $few = count(array_keys($states, Metric::VERDICT_FEW, true));
        if ($noHistory > 0) {
            $lines[] = '- '.trans_choice('admin.analytics.explain.quality.no_history', $noHistory, ['n' => $noHistory, 'min' => Metric::MIN_HISTORY]);
        }
        if ($few > 0) {
            $lines[] = '- '.trans_choice('admin.analytics.explain.quality.few', $few, ['n' => $few]);
        }
        $lines[] = '- '.__('admin.analytics.explain.quality.outside');
        $lines[] = '- '.__('admin.analytics.explain.quality.no_sector');

        return implode("\n", $lines);
    }

    /**
     * La definición de una cifra: la PRIMERA frase de su «¿Cómo se calcula?» —una sola fuente, sin copia que se desvíe—. Lo que
     * sigue habla de la tarjeta («Debajo, …») o afina el cálculo, y con las 28 no cabía en {@see MAX_BYTES} (medido el 29-09).
     */
    private static function definition(string $how): string
    {
        $how = self::oneLine($how);

        // El punto, seguido de espacio o del final; el «。» del chino no lleva espacio detrás.
        return preg_match('/^.+?(?:\.(?=\s|$)|。)/u', $how, $m) === 1 ? $m[0] : $how;
    }

    /** Una celda de tabla en una línea y sin romper la tabla. */
    private static function cell(string $value): string
    {
        return str_replace('|', '\|', self::oneLine($value));
    }

    private static function oneLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
