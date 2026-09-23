<?php

namespace App\Filament\Widgets\Analytics\Concerns;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\Money;
use App\Filament\Analytics\Delta;
use App\Filament\Analytics\MoneyReport;
use App\Filament\Pages\AnalyticsPage;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo que comparten los widgets del dinero (`docs/specs/analitica.md` §4.5, T2a): el permiso, el periodo del
 * filtro de la página y el informe cacheado. Cada widget pinta UNA parte del mismo informe: el cálculo no se
 * repite por widget, lo reparte la caché de {@see MoneyReport::for()}.
 *
 * ⚠️ Quien lo use lleva también `InteractsWithPageFilters`, que es de donde sale `$this->pageFilters`.
 */
trait ReadsMoneyReport
{
    /** El permiso de la página, re-preguntado en cada widget: esconder no es autorizar. */
    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(AnalyticsPage::PERMISSION) ?? false;
    }

    protected function period(): ReportPeriod
    {
        return ReportPeriod::fromValue($this->pageFilters['period'] ?? null);
    }

    /** @return array<string, mixed> */
    protected function report(): array
    {
        return MoneyReport::for($this->period());
    }

    /** Una tarjeta de DINERO con su variación frente al periodo anterior. */
    protected function moneyStat(string $label, int $current, int $previous, bool $upIsGood = true): Stat
    {
        return $this->withDelta(Stat::make($label, Money::format($current)), $current, $previous, $upIsGood);
    }

    /** Una tarjeta de RECUENTO con su variación frente al periodo anterior. */
    protected function countStat(string $label, int $current, int $previous): Stat
    {
        return $this->withDelta(Stat::make($label, (string) $current), $current, $previous);
    }

    private function withDelta(Stat $stat, int $current, int $previous, bool $upIsGood = true): Stat
    {
        $delta = Delta::describe($current, $previous, $upIsGood);

        return $stat
            ->description($delta['description'])
            ->descriptionIcon($delta['icon'])
            ->color($delta['color']);
    }
}
