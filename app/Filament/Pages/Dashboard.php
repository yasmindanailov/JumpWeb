<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PrintsDaySummary;
use App\Support\DashboardPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/**
 * Fase 7.4 iter2 — Dashboard del panel con un filtro de PERIODO compartido
 * (HOY · ESTA SEMANA · ESTE MES). El filtro vive a nivel de página (patrón
 * idiomático de Filament `HasFiltersForm`): su estado (`$filters`) se pasa a
 * cada widget como `pageFilters`, así un único control gobierna los dos widgets
 * operativos (`DashboardStatsWidget` + `ReservationsWidget`). Default: HOY, que
 * deja la vista de arranque igual que sin filtro.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;
    use PrintsDaySummary;

    /**
     * Botón "Imprimir resumen del día" en la cabecera del Escritorio (decisión
     * #184), compartido con el Calendario vía el trait `PrintsDaySummary`.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->printDaySummaryAction()];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            ToggleButtons::make('period')
                ->label(__('admin.dashboard.period.label'))
                ->options(DashboardPeriod::options())
                ->default(DashboardPeriod::Today->value)
                ->inline()
                ->live(),
        ]);
    }
}
