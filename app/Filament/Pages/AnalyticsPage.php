<?php

namespace App\Filament\Pages;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Filament\Widgets\Analytics\MoneyBreakdownWidget;
use App\Filament\Widgets\Analytics\MoneyCustomersWidget;
use App\Filament\Widgets\Analytics\MoneyOverviewWidget;
use App\Filament\Widgets\Analytics\MoneySeriesChart;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * **«Analítica», el cuadro de mando** (`docs/specs/analitica.md` §4.5; `DECISIONES #735`).
 *
 * Es el SEGUNDO cuadro del panel: «Hoy» contesta qué viene y este contesta cómo fue. Por eso es una
 * `Dashboard` de Filament y no una `Page` a mano —el filtro de periodo, la rejilla de widgets y su ciclo de
 * vida vienen resueltos—, con su propia ruta (`/admin/analitica`) y sus propios widgets ({@see getWidgets()}):
 * desde que hay dos cuadros, «Hoy» declara los suyos, o `Filament::getWidgets()` le colgaría también estos.
 *
 * ⚠️ **Permiso `reports.view`**: sembrado en F7.11 («Ver informes y exportaciones») y sin consumidor hasta esta
 * tanda; se le da uno en vez de crear un sinónimo. Es del grupo `gestion`, así que el admin lo tiene por
 * `Gate::before` y el empleado no. Cada widget lo vuelve a preguntar: esconder no es autorizar.
 *
 * ⚠️ Quinto sitio del menú plano (`#223`), después de «Clientes»: es día a día para quien dirige, no puesta
 * en marcha. `AdminNavigationTest` fija los cinco.
 */
class AnalyticsPage extends BaseDashboard
{
    use HasFiltersForm;

    /** El permiso que abre la página y cada uno de sus widgets. */
    public const PERMISSION = 'reports.view';

    /** El permiso del CSV (T2d): auditado, sin PII, con recuento. */
    public const PERMISSION_EXPORT = 'reports.export';

    protected static string $routePath = 'analitica';

    protected static ?string $slug = 'analitica';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 50;   // #223 · menú plano: 5.ª, tras «Clientes»

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission(self::PERMISSION) ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.analytics.nav_label');
    }

    public function getTitle(): string
    {
        return __('admin.analytics.title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.analytics.subheading');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label(__('admin.analytics.period.label'))
                ->options(ReportPeriod::options())
                ->default(ReportPeriod::DEFAULT->value)
                ->selectablePlaceholder(false)
                ->live(),
        ]);
    }

    /**
     * Los widgets de ESTE cuadro, en orden. La T2b añade los de registros y puerta; la T2c, los del embudo.
     *
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            MoneyOverviewWidget::class,
            MoneySeriesChart::class,
            MoneyCustomersWidget::class,
            MoneyBreakdownWidget::class,
        ];
    }

    /** Una columna: cada widget ocupa el ancho y se leen de arriba abajo, como un informe. */
    public function getColumns(): int|array
    {
        return 1;
    }
}
