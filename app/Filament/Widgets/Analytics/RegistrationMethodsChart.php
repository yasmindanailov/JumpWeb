<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\CustomersReport;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Cómo se registran** (`specs/analitica.md` §4.5, T2f): con contraseña, con Google o sin dato, como anillo.
 * Su vista de tabla es «Cómo se registran» en el desglose plegado.
 */
class RegistrationMethodsChart extends CategoryChart
{
    protected static ?int $sort = 8;

    protected string $kind = self::KIND_RING;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.customers.methods_chart');
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, colors?: list<string>}>}|null */
    protected function categories(): ?array
    {
        /** @var array<string, int> $methods */
        $methods = $this->customers()['registrations']['by_method'];
        $keys = [...CustomersReport::METHODS, CustomersReport::METHOD_UNKNOWN];
        $data = array_map(static fn (string $m): int => $methods[$m] ?? 0, $keys);

        if (array_sum($data) === 0) {
            return null;
        }

        return [
            'labels' => array_map(static fn (string $m): string => __('admin.analytics.customers.method_short.'.$m), $keys),
            'datasets' => [[
                'label' => __('admin.analytics.customers.registrations'),
                'data' => $data,
                'colors' => [self::PALETTE[0], self::PALETTE[1], self::COLOR_NONE],
            ]],
        ];
    }
}
