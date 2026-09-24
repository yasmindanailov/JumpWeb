<?php

namespace App\Filament\Analytics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\Money;
use App\Filament\Widgets\Analytics\CustomersBreakdownWidget;
use App\Filament\Widgets\Analytics\FunnelWidget;
use App\Filament\Widgets\Analytics\MoneyBreakdownWidget;
use App\Filament\Widgets\Analytics\PagesWidget;
use App\Filament\Widgets\Analytics\PartiesBreakdownWidget;
use App\Filament\Widgets\Analytics\SourcesWidget;

/**
 * **El CSV de «Analítica»** (`docs/specs/analitica.md` §4.5, T2d): un informe (el dinero, los registros y la
 * puerta, o la conversión) y un periodo, como el fichero que se abre en una hoja de cálculo.
 *
 * Las tablas son LAS MISMAS que pintan los widgets —se les piden a ellos ({@see MoneyBreakdownWidget::tablesFor()})
 * y no se recomponen aquí—, precedidas de un resumen con las cifras de las tarjetas. Así lo que se descarga es lo
 * que se vio, y un cambio en un desglose llega al CSV sin que nadie se acuerde de copiarlo.
 *
 * ⚠️ **Saneado de fórmulas** (spec §4.5, seguridad-10): toda celda que empiece por `=`, `+`, `-`, `@`, tabulador o
 * retorno de carro va precedida de un apóstrofo, que es lo que impide que una campaña llamada `=1+1` —o algo
 * peor— se ejecute al abrir el fichero. Un importe negativo («-5,00 €») también lo lleva: es el precio de la regla
 * y se paga a sabiendas. UTF-8 con BOM y punto y coma, que es lo que abre bien la hoja de cálculo en español.
 */
final class CsvExport
{
    public const REPORT_MONEY = 'money';

    public const REPORT_CUSTOMERS = 'customers';

    public const REPORT_FUNNEL = 'funnel';

    /** La fiesta (`specs/analitica-fiesta.md` §4.3, T2): por día de la FIESTA. */
    public const REPORT_PARTIES = 'parties';

    /** @var list<string> */
    public const REPORTS = [self::REPORT_MONEY, self::REPORT_CUSTOMERS, self::REPORT_FUNNEL, self::REPORT_PARTIES];

    public const SEPARATOR = ';';

    /** @var list<string> */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Las filas del fichero, ya como texto: el título, el periodo y cada tabla con su cabecera.
     *
     * @return array{filename: string, rows: list<list<string>>}
     */
    public function build(string $report, Window $window, Comparison $comparison = Comparison::Previous): array
    {
        $rows = [
            [__('admin.analytics.export.title', ['report' => __('admin.analytics.export.report.'.$report)])],
            [__('admin.analytics.export.period_line', ['from' => $window->dateFrom(), 'to' => $window->dateTo()])],
            [__('admin.analytics.export.compare_line', ['comparison' => $comparison->label()])],
            [],
        ];

        foreach ($this->sections($report, $window, $comparison) as $table) {
            $rows[] = [(string) $table['heading']];
            $rows[] = array_map(static fn ($c): string => (string) $c, $table['columns']);
            foreach ($table['rows'] as $row) {
                $rows[] = array_map(static fn ($c): string => (string) $c, $row);
            }
            $rows[] = [];
        }

        return [
            'filename' => sprintf('analitica-%s-%s-%s.csv', $report, $window->dateFrom(), $window->dateTo()),
            'rows' => $rows,
        ];
    }

    /**
     * El fichero: BOM, punto y coma, CRLF y cada celda saneada.
     *
     * @param  list<list<string>>  $rows
     */
    public static function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($handle, array_map([self::class, 'cell'], $row), self::SEPARATOR, '"', '', "\r\n");
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /** Una celda que parezca una fórmula sale precedida de un apóstrofo. */
    public static function cell(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true) ? "'".$value : $value;
    }

    /**
     * El resumen de las tarjetas y, detrás, las tablas de los widgets del informe.
     *
     * @return list<array{heading: string, columns: list<string>, rows: list<list<string>>}>
     */
    private function sections(string $report, Window $window, Comparison $comparison): array
    {
        return match ($report) {
            self::REPORT_MONEY => [
                $this->moneySummary($window, $comparison),
                ...(new MoneyBreakdownWidget)->tablesFor($window, $comparison),
            ],
            self::REPORT_CUSTOMERS => [
                $this->customersSummary($window, $comparison),
                ...(new CustomersBreakdownWidget)->tablesFor($window, $comparison),
            ],
            self::REPORT_FUNNEL => [
                $this->funnelSummary($window, $comparison),
                ...(new FunnelWidget)->tablesFor($window, $comparison),
                ...(new SourcesWidget)->tablesFor($window, $comparison),
                ...(new PagesWidget)->tablesFor($window, $comparison),
            ],
            self::REPORT_PARTIES => [
                $this->partiesSummary($window, $comparison),
                ...(new PartiesBreakdownWidget)->tablesFor($window, $comparison),
            ],
            default => throw new \InvalidArgumentException("«{$report}» no es un informe del cuadro"),
        };
    }

    /** @return array{heading: string, columns: list<string>, rows: list<list<string>>} */
    private function partiesSummary(Window $window, Comparison $comparison): array
    {
        $r = PartiesReport::for($window, $comparison);
        /** @var array<string, mixed> $m */
        $m = $r['money'];
        /** @var array<string, int> $f */
        $f = $r['forms'];
        /** @var array<string, int> $i */
        $i = $r['invitations'];
        /** @var array<string, int> $a */
        $a = $r['authorizations'];

        return $this->summary([
            [__('admin.analytics.parties.parties'), (string) $r['parties']],
            [__('admin.analytics.parties.sold_after'), Money::format((int) $m['sold_after_booking'])],
            [__('admin.analytics.parties.collected_in_park'), Money::format((int) $m['collected_in_park'])],
            [__('admin.analytics.parties.with_extras'), (string) $m['with_extras']],
            [__('admin.analytics.parties.avg_extras'), Money::format((int) $m['avg_extras'])],
            [__('admin.analytics.parties.completed'), (string) $f['completed']],
            [__('admin.analytics.parties.on_time'), number_format($f['on_time_bp'] / 100, 1, ',', '.').' %'],
            [__('admin.analytics.parties.replies_yes'), (string) $i['replies_yes']],
            [__('admin.analytics.parties.signatures'), (string) $a['signatures']],
        ]);
    }

    /** @return array{heading: string, columns: list<string>, rows: list<list<string>>} */
    private function moneySummary(Window $window, Comparison $comparison): array
    {
        $r = MoneyReport::for($window, $comparison);
        /** @var array<string, int> $t */
        $t = $r['totals'];
        /** @var array<string, int> $c */
        $c = $r['customers'];

        return $this->summary([
            [__('admin.analytics.money.collected'), Money::format($t['collected'])],
            [__('admin.analytics.money.refunded'), Money::format($t['refunded'])],
            [__('admin.analytics.money.net'), Money::format($t['net'])],
            [__('admin.analytics.money.sold'), Money::format($t['sold'])],
            [__('admin.analytics.money.orders'), (string) $t['orders']],
            [__('admin.analytics.money.avg_order'), Money::format($t['avg_order'])],
            [__('admin.analytics.money.avg_collected'), Money::format($t['avg_collected'])],
            [__('admin.analytics.money.adjustments'), Money::format($t['adjustments'])],
            [__('admin.analytics.money.buyers'), (string) $c['buyers']],
            [__('admin.analytics.money.new'), (string) $c['new']],
            [__('admin.analytics.money.returning'), (string) $c['returning']],
            [__('admin.analytics.money.avg_per_customer'), Money::format($c['avg_per_customer'])],
            [__('admin.analytics.money.lifetime_avg'), Money::format($c['lifetime_avg'])],
        ]);
    }

    /** @return array{heading: string, columns: list<string>, rows: list<list<string>>} */
    private function customersSummary(Window $window, Comparison $comparison): array
    {
        $r = CustomersReport::for($window, $comparison);
        /** @var array<string, mixed> $reg */
        $reg = $r['registrations'];
        /** @var array<string, int> $g */
        $g = $r['gate'];

        return $this->summary([
            [__('admin.analytics.customers.registrations'), (string) $reg['total']],
            [__('admin.analytics.customers.verified'), (string) $reg['verified']],
            [__('admin.analytics.customers.buyers'), (string) $reg['buyers']],
            [__('admin.analytics.customers.lookups'), (string) $g['lookups']],
            [__('admin.analytics.customers.typed'), (string) $g['typed']],
            [__('admin.analytics.customers.scanned'), (string) $g['scanned']],
            [__('admin.analytics.customers.found'), (string) $g['found']],
            [__('admin.analytics.customers.customers'), (string) $g['customers']],
            [__('admin.analytics.customers.profile_views'), (string) $g['profile_views']],
            [__('admin.analytics.customers.visits'), (string) $g['visits']],
            [__('admin.analytics.customers.visitors'), (string) $g['visitors']],
        ]);
    }

    /** @return array{heading: string, columns: list<string>, rows: list<list<string>>} */
    private function funnelSummary(Window $window, Comparison $comparison): array
    {
        $r = FunnelReport::for($window, $comparison);
        /** @var array<string, int> $t */
        $t = $r['traffic'];
        /** @var array<string, int> $p */
        $p = $r['purchases'];

        return $this->summary([
            [__('admin.analytics.traffic.visits'), (string) $t['visits']],
            [__('admin.analytics.traffic.purchases'), (string) $p['orders']],
            [__('admin.analytics.traffic.conversion'), number_format($p['conversion_bp'] / 100, 1, ',', '.').' %'],
            [__('admin.analytics.traffic.revenue'), Money::format($p['revenue'])],
            [__('admin.analytics.traffic.identified'), (string) $t['identified']],
            [__('admin.analytics.traffic.excluded'), __('admin.analytics.traffic.excluded_value', ['bots' => $t['bots'], 'internal' => $t['internal']])],
        ]);
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function summary(array $rows): array
    {
        return [
            'heading' => __('admin.analytics.export.summary'),
            'columns' => [__('admin.analytics.money.col.what'), __('admin.analytics.money.col.amount')],
            'rows' => $rows,
        ];
    }
}
