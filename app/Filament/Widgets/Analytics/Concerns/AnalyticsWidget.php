<?php

namespace App\Filament\Widgets\Analytics\Concerns;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\Money;
use App\Filament\Analytics\CustomersReport;
use App\Filament\Analytics\Delta;
use App\Filament\Analytics\FunnelReport;
use App\Filament\Analytics\MoneyReport;
use App\Filament\Pages\AnalyticsPage;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo que comparten los widgets de «Analítica» (`docs/specs/analitica.md` §4.5): el permiso, el periodo del
 * filtro de la página y los informes cacheados. Cada widget pinta UNA parte de un informe: el cálculo no se
 * repite por widget, lo reparte la caché de cada `for()`.
 *
 * ⚠️ Quien lo use lleva también `InteractsWithPageFilters`, que es de donde sale `$this->pageFilters`.
 */
trait AnalyticsWidget
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

    /** El informe del dinero (T2a). @return array<string, mixed> */
    protected function money(): array
    {
        return MoneyReport::for($this->period());
    }

    /** El informe de registros y puerta (T2b). @return array<string, mixed> */
    protected function customers(): array
    {
        return CustomersReport::for($this->period());
    }

    /** El informe del embudo y las fuentes (T2c). @return array<string, mixed> */
    protected function funnel(): array
    {
        return FunnelReport::for($this->period());
    }

    /** Puntos básicos → «12,3 %». */
    protected static function percent(int $basisPoints): string
    {
        return number_format($basisPoints / 100, 1, ',', '.')."\u{00A0}%";
    }

    /** Una tarjeta de DINERO con su variación frente al periodo anterior. */
    protected function moneyStat(string $label, int $current, int $previous, bool $upIsGood = true): Stat
    {
        return $this->withDelta(Stat::make($label, Money::format($current)), $current, $previous, $upIsGood);
    }

    /** Una tarjeta de RECUENTO con su variación frente al periodo anterior. */
    protected function countStat(string $label, int $current, int $previous, bool $upIsGood = true): Stat
    {
        return $this->withDelta(Stat::make($label, (string) $current), $current, $previous, $upIsGood);
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
