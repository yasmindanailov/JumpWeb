<?php

namespace App\Filament\Analytics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\Analytics\AnalyticsGoals;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Analytics\Metrics\CustomersMetrics;
use App\Filament\Analytics\Metrics\MarketingMetrics;
use App\Filament\Analytics\Metrics\MoneyMetrics;
use App\Filament\Analytics\Metrics\OccupancyMetrics;
use App\Filament\Analytics\Metrics\PartiesMetrics;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Set;

/**
 * **El formulario «Objetivos del mes»** (T3c·2 de `specs/analitica-para-decidir.md` §4.13, `DECISIONES #759`), al pie de
 * «Resumen»: el mes —este o el siguiente; el pasado ya no se toca— y un campo por cifra de {@see Goal::KEYS}, en la unidad
 * que lee el operador (euros, personas, porcentaje) y guardado en la de la cifra (céntimos, unidades, puntos básicos).
 * Vacío, sin objetivo. Cada campo dice cuánto valió el mes pasado: un objetivo se pone mirando lo que ya pasó.
 *
 * Aquí solo se traduce; quien guarda y deja rastro es {@see AnalyticsGoals::save()}, y el permiso lo vuelve a exigir la
 * acción de la página al guardar (`SEC-04`).
 */
final class GoalsForm
{
    /**
     * Los meses que se pueden tocar, por su clave `YYYY-MM`: este y el siguiente, en la zona del parque.
     *
     * @return array<string, CarbonImmutable>
     */
    public static function months(): array
    {
        $first = CarbonImmutable::parse(DisplayTime::today()->format('Y-m-01'));

        return [$first->format('Y-m') => $first, $first->addMonthNoOverflow()->format('Y-m') => $first->addMonthNoOverflow()];
    }

    /** El mes de una clave `YYYY-MM`, si es uno de los que se pueden tocar; si no, `null`. */
    public static function month(mixed $key): ?CarbonImmutable
    {
        return is_string($key) ? (self::months()[$key] ?? null) : null;
    }

    /**
     * @return list<Component>
     */
    public static function schema(): array
    {
        $months = self::months();
        $lastMonth = self::lastMonth();

        return [
            Select::make('month')
                ->label(__('admin.analytics.goals.month'))
                ->options(array_map(static fn (CarbonImmutable $month): string => self::monthLabel($month), $months))
                ->default(array_key_first($months))
                ->selectablePlaceholder(false)
                ->required()
                ->live()
                ->afterStateUpdated(static function (Set $set, mixed $state): void {
                    $month = self::month($state);
                    foreach (self::fill($month ?? CarbonImmutable::now()) as $field => $value) {
                        if ($field !== 'month') {
                            $set($field, $value);
                        }
                    }
                }),
            ...array_map(static function (string $key) use ($lastMonth): TextInput {
                $metric = $lastMonth[$key];
                $input = TextInput::make(self::field($key))
                    ->label($metric->label)
                    ->numeric()
                    ->helperText(__('admin.analytics.goals.last_month', ['value' => $metric->displayValue()]));

                return match (Goal::UNITS[$key]) {
                    Metric::UNIT_MONEY => $input->suffix('€')->minValue(0.01)->step(0.01),
                    Metric::UNIT_RATE => $input->suffix('%')->minValue(0.1)->maxValue(100)->step(0.1),
                    default => $input->minValue(1)->step(1)->integer(),
                };
            }, Goal::KEYS),
        ];
    }

    /**
     * Lo que el formulario enseña para un mes: sus objetivos, en la unidad del operador.
     *
     * @return array<string, string|int|float|null>
     */
    public static function fill(CarbonImmutable $month): array
    {
        $targets = AnalyticsGoals::forMonth($month);
        $data = ['month' => $month->format('Y-m')];

        foreach (Goal::KEYS as $key) {
            $target = $targets[$key] ?? null;
            $data[self::field($key)] = $target === null ? null : match (Goal::UNITS[$key]) {
                Metric::UNIT_MONEY, Metric::UNIT_RATE => $target / 100,
                default => $target,
            };
        }

        return $data;
    }

    /**
     * Lo escrito, en la unidad de cada cifra; un campo vacío (o que no es un número mayor que cero) quita el objetivo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, ?int>
     */
    public static function targets(array $data): array
    {
        $targets = [];

        foreach (Goal::KEYS as $key) {
            $raw = $data[self::field($key)] ?? null;
            $number = is_numeric($raw) ? (float) $raw : null;
            $target = $number === null ? null : match (Goal::UNITS[$key]) {
                Metric::UNIT_MONEY, Metric::UNIT_RATE => (int) round($number * 100),
                default => (int) round($number),
            };
            if ($target !== null && Goal::UNITS[$key] === Metric::UNIT_RATE) {
                $target = min($target, 10000);
            }
            $targets[$key] = $target !== null && $target > 0 ? $target : null;
        }

        return $targets;
    }

    /** El nombre del campo de una cifra: sin puntos, que en un formulario anidarían el estado. */
    public static function field(string $key): string
    {
        return 'goal_'.str_replace('.', '__', $key);
    }

    /**
     * Las ocho cifras del mes pasado, de su catálogo: su nombre y su valor, para la pista de cada campo.
     *
     * @return array<string, Metric>
     */
    private static function lastMonth(): array
    {
        $window = ReportPeriod::LastMonth->window();
        $comparison = Comparison::Previous;

        return MoneyMetrics::for($window, $comparison)
            + OccupancyMetrics::for($window, $comparison)
            + MarketingMetrics::for($window, $comparison)
            + PartiesMetrics::for($window, $comparison)
            + CustomersMetrics::for($window, $comparison);
    }

    private static function monthLabel(CarbonImmutable $month): string
    {
        $local = $month->locale(app()->getLocale());

        return __('admin.analytics.goals.month_option', ['month' => $local->translatedFormat('F'), 'year' => $month->year]);
    }
}
