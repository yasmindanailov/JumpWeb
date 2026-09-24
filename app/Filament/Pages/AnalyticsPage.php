<?php

namespace App\Filament\Pages;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Filament\Analytics\CsvExport;
use App\Filament\Analytics\SegmentsReport;
use App\Filament\Widgets\Analytics\CustomersBreakdownWidget;
use App\Filament\Widgets\Analytics\CustomersSeriesChart;
use App\Filament\Widgets\Analytics\DevicesChart;
use App\Filament\Widgets\Analytics\ExperimentsWidget;
use App\Filament\Widgets\Analytics\FunnelChart;
use App\Filament\Widgets\Analytics\FunnelWidget;
use App\Filament\Widgets\Analytics\GateHoursChart;
use App\Filament\Widgets\Analytics\GateWidget;
use App\Filament\Widgets\Analytics\MoneyBreakdownWidget;
use App\Filament\Widgets\Analytics\MoneyChannelsChart;
use App\Filament\Widgets\Analytics\MoneyCustomersWidget;
use App\Filament\Widgets\Analytics\MoneyOverviewWidget;
use App\Filament\Widgets\Analytics\MoneyProductsChart;
use App\Filament\Widgets\Analytics\MoneySeriesChart;
use App\Filament\Widgets\Analytics\PagesWidget;
use App\Filament\Widgets\Analytics\RegistrationMethodsChart;
use App\Filament\Widgets\Analytics\RegistrationsWidget;
use App\Filament\Widgets\Analytics\SegmentsWidget;
use App\Filament\Widgets\Analytics\SourcesChart;
use App\Filament\Widgets\Analytics\SourcesWidget;
use App\Filament\Widgets\Analytics\TrafficHoursChart;
use App\Filament\Widgets\Analytics\TrafficSeriesChart;
use App\Filament\Widgets\Analytics\TrafficWidget;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * **«Analítica», el cuadro de mando** (`docs/specs/analitica.md` §4.5; `DECISIONES #735`, `#736`).
 *
 * Es el SEGUNDO cuadro del panel: «Hoy» contesta qué viene y este contesta cómo fue. Por eso es una
 * `Dashboard` de Filament y no una `Page` a mano —el filtro, el ciclo de vida de los widgets y su carga
 * diferida vienen resueltos—, con su propia ruta (`/admin/analitica`) y sus propios widgets: desde que hay dos
 * cuadros, «Hoy» declara los suyos, o `Filament::getWidgets()` le colgaría también estos.
 *
 * **Tres pestañas** (T2f, `#736`: lo pidió el owner el 24-09 al ver T2a–T2d en escritorio): Dinero, Clientes y
 * Conversión (`self::TABS`), y dentro de cada una el mismo orden de lectura: las tarjetas, los gráficos y, plegadas
 * al pie, las tablas. El filtro es común a las tres: el periodo (del día al año, o dos fechas a medida) y contra
 * qué se compara (el periodo anterior o el mismo periodo del año pasado). La pestaña viaja en la URL.
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

    /**
     * Exportar un SEGMENTO (T4b): una lista de PERSONAS —solo con opt-in—, no agregados; por eso es otro permiso,
     * propio y fuera del staff por defecto, y cada descarga deja rastro con el segmento y el recuento.
     */
    public const PERMISSION_SEGMENTS_EXPORT = 'analytics.export';

    /**
     * Los widgets de cada pestaña, en su orden de lectura: tarjetas, gráficos (los de media rejilla van de dos en
     * dos) y, plegadas al final, las tablas. La clave es también la del rótulo (`admin.analytics.tabs.*`).
     *
     * @var array<string, list<class-string<Widget>>>
     */
    public const TABS = [
        'money' => [
            MoneyOverviewWidget::class,
            MoneySeriesChart::class,
            MoneyProductsChart::class,
            MoneyChannelsChart::class,
            MoneyCustomersWidget::class,
            MoneyBreakdownWidget::class,
        ],
        'customers' => [
            RegistrationsWidget::class,
            GateWidget::class,
            CustomersSeriesChart::class,
            GateHoursChart::class,
            RegistrationMethodsChart::class,
            // T4b: los segmentos, un estado de HOY (no dependen del periodo). Van ANTES de la tabla plegada del
            // detalle: la regla de la T2f es que la tabla del gráfico cierra la pestaña.
            SegmentsWidget::class,
            CustomersBreakdownWidget::class,
        ],
        'traffic' => [
            TrafficWidget::class,
            FunnelChart::class,
            SourcesChart::class,
            TrafficSeriesChart::class,
            TrafficHoursChart::class,
            DevicesChart::class,
            // T5b: los experimentos, por variante y con su intervalo. Antes de las tablas plegadas que cierran la
            // pestaña (la regla de la T2f: la última es `PagesWidget`).
            ExperimentsWidget::class,
            FunnelWidget::class,
            SourcesWidget::class,
            PagesWidget::class,
        ],
    ];

    /** @var array<string, Heroicon> */
    private const TAB_ICONS = [
        'money' => Heroicon::OutlinedBanknotes,
        'customers' => Heroicon::OutlinedUsers,
        'traffic' => Heroicon::OutlinedFunnel,
    ];

    /** La clave de la pestaña en la URL (`?pestana=…`): se puede enlazar y sobrevive a recargar. */
    public const TAB_QUERY_KEY = 'pestana';

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

    /**
     * «Descargar CSV» (T2d): el botón se esconde sin `reports.export` y el controlador de la ruta vuelve a
     * exigirlo (esconder no es autorizar). El periodo, las dos fechas y la comparación son los del filtro de la
     * página; el informe se elige en el modal. Abre la URL en otra pestaña por el mismo camino que el resumen del día.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label(__('admin.analytics.export.button'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->hasPermission(self::PERMISSION_EXPORT) ?? false)
                ->modalHeading(__('admin.analytics.export.modal_heading'))
                ->modalDescription(__('admin.analytics.export.modal_description'))
                ->modalSubmitActionLabel(__('admin.analytics.export.submit'))
                ->modalWidth('md')
                ->schema([
                    Select::make('report')
                        ->label(__('admin.analytics.export.report_label'))
                        ->options(array_combine(CsvExport::REPORTS, array_map(static fn (string $r): string => __('admin.analytics.export.report.'.$r), CsvExport::REPORTS)))
                        ->default(CsvExport::REPORT_MONEY)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $filters = $this->filters ?? [];
                    $this->dispatch('open-url-new-tab', url: route('admin.analitica.csv', array_filter([
                        'report' => (string) ($data['report'] ?? CsvExport::REPORT_MONEY),
                        'period' => ReportPeriod::fromValue($filters['period'] ?? null)->value,
                        'compare' => Comparison::fromValue($filters['compare'] ?? null)->value,
                        'from' => is_string($filters['from'] ?? null) ? substr($filters['from'], 0, 10) : null,
                        'to' => is_string($filters['to'] ?? null) ? substr($filters['to'], 0, 10) : null,
                    ], static fn ($v): bool => $v !== null)));
                }),
            // «Exportar segmento» (T4b): una lista de personas con opt-in. Se esconde sin `analytics.export`; el
            // controlador de la ruta vuelve a comprobarlo y audita cada descarga.
            Action::make('exportSegment')
                ->label(__('admin.analytics.segments.export.button'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->hasPermission(self::PERMISSION_SEGMENTS_EXPORT) ?? false)
                ->modalHeading(__('admin.analytics.segments.export.modal_heading'))
                ->modalDescription(__('admin.analytics.segments.export.modal_description'))
                ->modalSubmitActionLabel(__('admin.analytics.segments.export.submit'))
                ->modalWidth('md')
                ->schema([
                    Select::make('segment')
                        ->label(__('admin.analytics.segments.export.segment_label'))
                        ->options(array_combine(SegmentsReport::SEGMENTS, array_map(static fn (string $s): string => __('admin.analytics.segments.name.'.$s), SegmentsReport::SEGMENTS)))
                        ->default(SegmentsReport::ONCE_NEVER_BACK)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $this->dispatch('open-url-new-tab', url: route('admin.analitica.segmentos.csv', [
                        'segment' => (string) ($data['segment'] ?? SegmentsReport::ONCE_NEVER_BACK),
                    ]));
                }),
        ];
    }

    /**
     * El periodo (de hoy al año, y dos fechas a medida), contra qué se compara, y las dos fechas cuando el
     * periodo es a medida. Todo `live`: los widgets se recalculan al cambiar. Cuatro campos en una fila ancha.
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
            ->components([
                Select::make('period')
                    ->label(__('admin.analytics.period.label'))
                    ->options(ReportPeriod::options())
                    ->default(ReportPeriod::DEFAULT->value)
                    ->selectablePlaceholder(false)
                    ->live(),
                Select::make('compare')
                    ->label(__('admin.analytics.compare.label'))
                    ->options(Comparison::options())
                    ->default(Comparison::DEFAULT->value)
                    ->selectablePlaceholder(false)
                    ->live(),
                DatePicker::make('from')
                    ->label(__('admin.analytics.period.from'))
                    ->native(false)
                    ->closeOnDateSelection()
                    ->visible(fn (Get $get): bool => $get('period') === ReportPeriod::Custom->value)
                    ->live(),
                DatePicker::make('to')
                    ->label(__('admin.analytics.period.to'))
                    ->native(false)
                    ->closeOnDateSelection()
                    ->visible(fn (Get $get): bool => $get('period') === ReportPeriod::Custom->value)
                    ->live(),
            ]);
    }

    /**
     * El filtro y, debajo, las tres pestañas; cada una es una rejilla de dos columnas en escritorio (una en
     * móvil) con sus widgets (`self::TABS`).
     *
     * ⚠️ Medido el 24-09: las pestañas inactivas de Filament NO son `display: none` (`invisible absolute h-0`),
     * así que el observador de intersección de Livewire da por visibles sus widgets y los pide también: abrir
     * la página cuesta 10 peticiones de Livewire, las de las TRES pestañas, no las de una. Los informes se
     * calculan una vez por ventana y comparación (caché de 5 min), así que el coste es el render, no el SQL.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFiltersFormContentComponent(),
            Tabs::make(__('admin.analytics.title'))
                ->persistTabInQueryString(self::TAB_QUERY_KEY)
                ->contained(false)
                ->tabs(array_map(
                    fn (string $tab): Tab => Tab::make(__('admin.analytics.tabs.'.$tab))
                        ->key($tab)   // la URL dice `?pestana=money`, no el slug del rótulo traducido
                        ->id($tab)
                        ->icon(self::TAB_ICONS[$tab])
                        ->schema([
                            Grid::make(['default' => 1, 'lg' => 2])
                                ->schema(fn (): array => $this->getWidgetsSchemaComponents(self::TABS[$tab])),
                        ]),
                    array_keys(self::TABS),
                )),
        ]);
    }

    /**
     * Todos los widgets de ESTE cuadro, pestaña a pestaña: el dinero (T2a), los registros y la puerta (T2b) y la
     * conversión —el embudo, las fuentes y las páginas— (T2c), con los gráficos de categorías de la T2f.
     *
     * @return list<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return array_merge(...array_values(self::TABS));
    }

    /** Una columna por pestaña; la rejilla de dos columnas vive dentro de cada una ({@see content()}). */
    public function getColumns(): int|array
    {
        return 1;
    }
}
