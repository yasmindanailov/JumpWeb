<?php

namespace App\Filament\Widgets\Analytics\Concerns;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\CustomersReport;
use App\Filament\Analytics\FunnelReport;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\MoneyReport;
use App\Filament\Analytics\OccupancyReport;
use App\Filament\Analytics\PartiesReport;
use App\Filament\Analytics\SurveysReport;
use App\Filament\Pages\AnalyticsPage;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo que comparten los widgets de «Analítica» (`docs/specs/analitica.md` §4.5): el permiso, la ventana y la
 * comparación del filtro de la página y los informes cacheados. Cada widget pinta UNA parte de un informe: el
 * cálculo no se repite por widget, lo reparte la caché de cada `for()`.
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

    /**
     * ⚠️ SIN sondeo (T0b, `#755`, medido el 27-09). Filament hace que cada widget de tarjetas y de gráfico vuelva a
     * pedirse cada 5 s (`CanPoll`): el cuadro abierto y quieto hacía una petición cada 5 s (6 en 30 s) que obligaba a
     * pintar otra vez sus widgets, para unos informes que se cachean 5 minutos; y la que estaba en vuelo cuando el
     * operador tocaba el filtro se abortaba con un rechazo sin atender en la consola (siete de golpe). El cuadro
     * contesta «cómo fue»: se recalcula al cambiar el filtro o al recargar, no solo.
     */
    protected function getPollingInterval(): ?string
    {
        return null;
    }

    /** La ventana y la comparación FORZADAS desde fuera (el CSV), por encima del filtro de la página. */
    protected ?Window $forcedWindow = null;

    protected ?Comparison $forcedComparison = null;

    protected function period(): ReportPeriod
    {
        return ReportPeriod::fromValue($this->pageFilters['period'] ?? null);
    }

    /** La ventana del filtro: el periodo elegido o las dos fechas a medida. */
    protected function window(): Window
    {
        if ($this->forcedWindow !== null) {
            return $this->forcedWindow;
        }

        $from = $this->pageFilters['from'] ?? null;
        $to = $this->pageFilters['to'] ?? null;

        return $this->period()->window(is_string($from) ? $from : null, is_string($to) ? $to : null);
    }

    /** Contra qué se compara: el periodo anterior o el mismo periodo del año pasado. */
    protected function comparison(): Comparison
    {
        return $this->forcedComparison ?? Comparison::fromValue($this->pageFilters['compare'] ?? null);
    }

    /**
     * Las mismas tablas de un widget de tablas, para el CSV (T2d): la ventana y la comparación vienen de fuera,
     * no del filtro de la página.
     *
     * @return list<array{heading: string, columns: list<string>, rows: list<list<string>>}>
     */
    public function tablesFor(Window $window, Comparison $comparison = Comparison::Previous): array
    {
        $this->forcedWindow = $window;
        $this->forcedComparison = $comparison;

        return $this->getViewData()['tables'];
    }

    /** El informe del dinero (T2a). @return array<string, mixed> */
    protected function money(): array
    {
        return MoneyReport::for($this->window(), $this->comparison());
    }

    /** El informe de registros y puerta (T2b). @return array<string, mixed> */
    protected function customers(): array
    {
        return CustomersReport::for($this->window(), $this->comparison());
    }

    /** El informe del embudo y las fuentes (T2c). @return array<string, mixed> */
    protected function funnel(): array
    {
        return FunnelReport::for($this->window(), $this->comparison());
    }

    /** El informe de la fiesta (`specs/analitica-fiesta.md` §4.3, T2). @return array<string, mixed> */
    protected function parties(): array
    {
        return PartiesReport::for($this->window(), $this->comparison());
    }

    /** El informe de las encuestas (`specs/encuestas.md` §4.4, T4). @return array<string, mixed> */
    protected function surveys(): array
    {
        return SurveysReport::for($this->window(), $this->comparison());
    }

    /** El informe de la ocupación (`specs/analitica-para-decidir.md` §4.8.ter, la T2). @return array<string, mixed> */
    protected function occupancy(): array
    {
        return OccupancyReport::for($this->window(), $this->comparison());
    }

    /** Puntos básicos → «12,3 %». */
    protected static function percent(int $basisPoints): string
    {
        return number_format($basisPoints / 100, 1, ',', '.')."\u{00A0}%";
    }

    /**
     * UNA tarjeta del cuadro con su anatomía (T0b, `#755`): la cifra la compone el catálogo de su informe
     * (`Filament\Analytics\Metrics\*`, T3a) y la pinta UNA vista. «¿Cómo se calcula?» sale de `admin.analytics.how.<clave>`.
     *
     * @param  array{url: string, label: string}|null  $link  en «Resumen», adónde lleva la cifra (su pestaña)
     */
    protected function metric(Metric $metric, ?array $link = null): Stat
    {
        return Stat::make($metric->label, $metric->displayValue())
            ->view('filament.widgets.analytics.metric', [
                'metric' => $metric,
                'reading' => $metric->reading($this->comparison(), $this->windowShare()),
                'link' => $link,
            ]);
    }

    /**
     * Qué parte del tiempo de las dos ventanas es la del periodo ({@see Comparison::share()}, que lo calcula para la tarjeta y
     * para el texto para IA). ⚠️ No se llama `share()`: un widget ya tenía el suyo y lo pisaba (lo cazó el censo, 27-09).
     */
    private function windowShare(): float
    {
        return $this->comparison()->share($this->window());
    }
}
