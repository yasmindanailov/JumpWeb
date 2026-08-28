<?php

namespace App\Filament\Pages;

use App\Domain\Platform\Enums\DashboardPeriod;
use App\Filament\Concerns\PrintsDaySummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
     * #223 — primera del menú plano y renombrada a «Hoy».
     *
     * «Escritorio» no decía nada de lo que hay dentro; esta pantalla contesta la pregunta
     * con la que se abre el panel cada mañana —quién viene y cuántas plazas quedan— y su
     * filtro nace en HOY. El nombre es ahora el de la pregunta.
     */
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('admin.dashboard.nav_label');
    }

    public function getTitle(): string
    {
        return __('admin.dashboard.title');
    }

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
