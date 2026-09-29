<?php

namespace App\Filament\Pages;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\Analytics\AnalyticsGoals;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\CsvExport;
use App\Filament\Analytics\GoalsForm;
use App\Filament\Analytics\WindowLabel;
use App\Filament\Widgets\Analytics\AnticipationChart;
use App\Filament\Widgets\Analytics\AudienceWidget;
use App\Filament\Widgets\Analytics\ChangesWidget;
use App\Filament\Widgets\Analytics\CustomersBreakdownWidget;
use App\Filament\Widgets\Analytics\CustomersMoreWidget;
use App\Filament\Widgets\Analytics\CustomersOverviewWidget;
use App\Filament\Widgets\Analytics\CustomersSeriesChart;
use App\Filament\Widgets\Analytics\DataQualityWidget;
use App\Filament\Widgets\Analytics\EmailsHeatmapWidget;
use App\Filament\Widgets\Analytics\EmailsWidget;
use App\Filament\Widgets\Analytics\ExperimentsWidget;
use App\Filament\Widgets\Analytics\FunnelChart;
use App\Filament\Widgets\Analytics\FunnelWidget;
use App\Filament\Widgets\Analytics\GateHoursChart;
use App\Filament\Widgets\Analytics\GateWidget;
use App\Filament\Widgets\Analytics\MetricsWidget;
use App\Filament\Widgets\Analytics\MoneyBreakdownWidget;
use App\Filament\Widgets\Analytics\MoneyChannelsChart;
use App\Filament\Widgets\Analytics\MoneyMoreWidget;
use App\Filament\Widgets\Analytics\MoneyOverviewWidget;
use App\Filament\Widgets\Analytics\MoneyProductsChart;
use App\Filament\Widgets\Analytics\MoneySeriesChart;
use App\Filament\Widgets\Analytics\OccupancyBreakdownWidget;
use App\Filament\Widgets\Analytics\OccupancyHeatmapWidget;
use App\Filament\Widgets\Analytics\OccupancyOverviewWidget;
use App\Filament\Widgets\Analytics\PagesWidget;
use App\Filament\Widgets\Analytics\PartiesBreakdownWidget;
use App\Filament\Widgets\Analytics\PartiesFunnelChart;
use App\Filament\Widgets\Analytics\PartiesMoneyChart;
use App\Filament\Widgets\Analytics\PartiesMoreWidget;
use App\Filament\Widgets\Analytics\PartiesOverviewWidget;
use App\Filament\Widgets\Analytics\PartiesTimingChart;
use App\Filament\Widgets\Analytics\RegistrationMethodsChart;
use App\Filament\Widgets\Analytics\RegistrationsWidget;
use App\Filament\Widgets\Analytics\SegmentsWidget;
use App\Filament\Widgets\Analytics\SourcesChart;
use App\Filament\Widgets\Analytics\SourcesWidget;
use App\Filament\Widgets\Analytics\SummaryWidget;
use App\Filament\Widgets\Analytics\SurveysAnswersChart;
use App\Filament\Widgets\Analytics\SurveysBreakdownWidget;
use App\Filament\Widgets\Analytics\SurveysLowScoresWidget;
use App\Filament\Widgets\Analytics\SurveysMoreWidget;
use App\Filament\Widgets\Analytics\SurveysOverviewWidget;
use App\Filament\Widgets\Analytics\TrafficMoreWidget;
use App\Filament\Widgets\Analytics\TrafficSeriesChart;
use App\Filament\Widgets\Analytics\TrafficWidget;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Livewire\Attributes\Url;
use LogicException;

/**
 * **«Analítica», el cuadro de mando** (`docs/specs/analitica.md` §4.5; `DECISIONES #735`, `#736`; y desde la T3a de
 * `analitica-para-decidir.md` §4.13, `#759`, su forma de hoy).
 *
 * Es el SEGUNDO cuadro del panel: «Hoy» contesta qué viene y este contesta cómo fue. Por eso es una `Dashboard` de
 * Filament y no una `Page` a mano —el filtro, el ciclo de vida de los widgets y su carga diferida vienen resueltos—, con
 * su propia ruta (`/admin/analitica`) y sus propios widgets: desde que hay dos cuadros, «Hoy» declara los suyos, o
 * `Filament::getWidgets()` le colgaría también estos.
 *
 * **Siete pestañas, una pregunta cada una** (§4.1): Resumen, Dinero, Ocupación, Clientes, Marketing, Fiestas y
 * Satisfacción (`self::TABS`). La cabecera es la pregunta de la pestaña abierta; dentro, el mismo orden de lectura: como
 * mucho seis tarjetas arriba, como mucho tres gráficos, y plegado al pie lo que no decide —tarjetas y tablas— y el botón
 * del CSV de su informe. El filtro es común: el periodo y contra qué se compara.
 *
 * ⚠️ **Solo se pinta la pestaña abierta** (T3a): medido el 28-09, las pestañas de Filament con Alpine dejan las inactivas
 * en el DOM (`invisible absolute h-0`) y el observador de Livewire pedía los widgets de TODAS al abrir (10). Con
 * `Tabs::livewireProperty()` una pestaña inactiva se pinta vacía y sus widgets ni existen ni piden; la pestaña vive en la
 * propiedad `$tab` y en la URL (`?pestana=…`). En el móvil, un `<select>` nativo en lugar de la fila de pestañas.
 *
 * ⚠️ **Permiso `reports.view`**: sembrado en F7.11 («Ver informes y exportaciones»). Es del grupo `gestion`, así que el
 * admin lo tiene por `Gate::before` y el empleado no. Cada widget lo vuelve a preguntar: esconder no es autorizar.
 *
 * ⚠️ Quinto sitio del menú plano (`#223`), después de «Clientes»: es día a día para quien dirige, no puesta en marcha.
 * `AdminNavigationTest` fija los cinco.
 */
class AnalyticsPage extends BaseDashboard
{
    use HasFiltersForm;

    /** El permiso que abre la página y cada uno de sus widgets. */
    public const PERMISSION = 'reports.view';

    /** El permiso del CSV (T2d): auditado, sin PII, con recuento. */
    public const PERMISSION_EXPORT = 'reports.export';

    /**
     * Poner los objetivos del mes (T3c·2, `#759`): cambia lo que el cuadro dice de todos, así que es de gestión y propio
     * —el admin lo tiene por `Gate::before`—, y se vuelve a exigir al guardar (`SEC-04`).
     */
    public const PERMISSION_GOALS = 'analytics.manage';

    /** El evento con el que las tarjetas se vuelven a pintar tras guardar los objetivos. */
    public const GOALS_SAVED_EVENT = 'analytics-goals-saved';

    /** La pestaña con la que se abre. */
    public const DEFAULT_TAB = 'summary';

    /**
     * Los widgets de cada pestaña, en su orden de lectura: las tarjetas de arriba, los gráficos (los de media rejilla van
     * de dos en dos) y, plegados al final, las tarjetas y las tablas que no deciden. La clave es la de la URL y la del
     * rótulo (`admin.analytics.tabs.*`) y la pregunta (`admin.analytics.questions.*`).
     *
     * @var array<string, list<class-string<Widget>>>
     */
    public const TABS = [
        // T3a (`#759`): las cifras clave, las mismas de su pestaña; T3c·1 (`#791`): lo que ha cambiado, de todas.
        'summary' => [
            SummaryWidget::class,
            ChangesWidget::class,
        ],
        'money' => [
            MoneyOverviewWidget::class,
            MoneySeriesChart::class,
            MoneyProductsChart::class,
            MoneyChannelsChart::class,
            MoneyMoreWidget::class,
            MoneyBreakdownWidget::class,
        ],
        // La T2 de la analítica para decidir (`#758`): cómo de lleno está el parque, y cuándo.
        'occupancy' => [
            OccupancyOverviewWidget::class,
            OccupancyHeatmapWidget::class,
            AnticipationChart::class,
            OccupancyBreakdownWidget::class,
        ],
        'customers' => [
            CustomersOverviewWidget::class,
            CustomersSeriesChart::class,
            GateHoursChart::class,
            RegistrationMethodsChart::class,
            // T4b: los segmentos, un estado de HOY (no dependen del periodo).
            SegmentsWidget::class,
            CustomersMoreWidget::class,
            // TP·2 (`#792`): quién viene —su edad, sus hijos, con quién—, plegado.
            AudienceWidget::class,
            RegistrationsWidget::class,
            GateWidget::class,
            CustomersBreakdownWidget::class,
        ],
        // Era «Conversión»: T2c, los experimentos de la T5b y, al final, «Calidad del dato» (T3a).
        'marketing' => [
            TrafficWidget::class,
            FunnelChart::class,
            SourcesChart::class,
            TrafficSeriesChart::class,
            TrafficMoreWidget::class,
            ExperimentsWidget::class,
            FunnelWidget::class,
            SourcesWidget::class,
            PagesWidget::class,
            // La C4 de los correos salientes (`#796`): cada correo en conjunto y CUÁNDO abren y pulsan, plegados.
            EmailsWidget::class,
            EmailsHeatmapWidget::class,
            DataQualityWidget::class,
        ],
        // T2 de la fiesta (`specs/analitica-fiesta.md` §4.3, `#739`): de reservar a celebrar, por DÍA DE LA FIESTA.
        'parties' => [
            PartiesOverviewWidget::class,
            PartiesFunnelChart::class,
            PartiesMoneyChart::class,
            PartiesTimingChart::class,
            PartiesMoreWidget::class,
            PartiesBreakdownWidget::class,
        ],
        // Era «Encuestas» (`specs/encuestas.md` §4.4, `#740`; anónimas desde `#754`).
        'satisfaction' => [
            SurveysOverviewWidget::class,
            SurveysAnswersChart::class,
            SurveysLowScoresWidget::class,
            SurveysMoreWidget::class,
            SurveysBreakdownWidget::class,
        ],
    ];

    /** El informe del CSV al pie de cada pestaña; «Resumen» no tiene: cada cifra está en el de su pestaña. */
    public const REPORTS = [
        'money' => CsvExport::REPORT_MONEY,
        'occupancy' => CsvExport::REPORT_OCCUPANCY,
        'customers' => CsvExport::REPORT_CUSTOMERS,
        'marketing' => CsvExport::REPORT_FUNNEL,
        'parties' => CsvExport::REPORT_PARTIES,
        'satisfaction' => CsvExport::REPORT_SURVEYS,
    ];

    /** Las claves de antes de la T3a siguen abriendo su pestaña (un enlace guardado no se rompe). */
    public const LEGACY_TABS = ['traffic' => 'marketing', 'surveys' => 'satisfaction'];

    /** @var array<string, Heroicon> */
    private const TAB_ICONS = [
        'summary' => Heroicon::OutlinedSquares2x2,
        'money' => Heroicon::OutlinedBanknotes,
        'occupancy' => Heroicon::OutlinedTableCells,
        'customers' => Heroicon::OutlinedUsers,
        'marketing' => Heroicon::OutlinedMegaphone,
        'parties' => Heroicon::OutlinedCake,
        'satisfaction' => Heroicon::OutlinedFaceSmile,
    ];

    /** La clave de la pestaña en la URL (`?pestana=…`): se puede enlazar y sobrevive a recargar. */
    public const TAB_QUERY_KEY = 'pestana';

    /** La pestaña abierta: la única que se pinta y la única cuyos widgets piden. */
    #[Url(as: 'pestana')]
    public string $tab = self::DEFAULT_TAB;

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

    /**
     * La pestaña donde vive una cifra (arriba o plegada): adonde lleva su frase en «lo que ha cambiado» (T3c·1). «Resumen» no
     * cuenta: solo repite.
     */
    public static function tabOf(string $key): ?string
    {
        foreach (self::TABS as $tab => $widgets) {
            if ($tab === self::DEFAULT_TAB) {
                continue;
            }
            foreach ($widgets as $widget) {
                if (is_subclass_of($widget, MetricsWidget::class) && in_array($key, $widget::KEYS, true)) {
                    return $tab;
                }
            }
        }

        return null;
    }

    /** Una clave vieja abre su pestaña de ahora; una desconocida, «Resumen». */
    public static function normalizeTab(string $tab): string
    {
        $tab = self::LEGACY_TABS[$tab] ?? $tab;

        return array_key_exists($tab, self::TABS) ? $tab : self::DEFAULT_TAB;
    }

    public function mount(): void
    {
        $this->tab = self::normalizeTab($this->tab);
    }

    /** La pestaña la cambia el navegador (la fila de pestañas o el selector del móvil): se vuelve a normalizar. */
    public function updatedTab(): void
    {
        $this->tab = self::normalizeTab($this->tab);
    }

    public function getTitle(): string
    {
        return __('admin.analytics.title');
    }

    /** La pregunta de la pestaña abierta (§4.1): la frase técnica de antes baja a «¿Cómo se calcula?» de «Ingresos netos». */
    public function getSubheading(): ?string
    {
        return __('admin.analytics.questions.'.self::normalizeTab($this->tab));
    }

    /** Sin botones arriba (T3a): el CSV va al pie de cada pestaña; el segmento, al pie de «Clientes»; los objetivos, al de «Resumen». */
    protected function getHeaderActions(): array
    {
        return [];
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
                    ->helperText(fn (Get $get): string => WindowLabel::period(self::windowOf($get)))
                    ->live(),
                Select::make('compare')
                    ->label(__('admin.analytics.compare.label'))
                    ->options(Comparison::options())
                    ->default(Comparison::DEFAULT->value)
                    ->selectablePlaceholder(false)
                    ->helperText(fn (Get $get): string => WindowLabel::baseline(Comparison::fromValue($get('compare'))->baseline(self::windowOf($get))))
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
     * La ventana que el filtro tiene puesta, para rotular sus fechas debajo (T0, `#755`): la MISMA que resuelven los
     * widgets (`Concerns\AnalyticsWidget::window()`) y el CSV, con las dos fechas a medida recortadas a `YYYY-MM-DD`.
     */
    private static function windowOf(Get $get): Window
    {
        $from = $get('from');
        $to = $get('to');

        return ReportPeriod::fromValue($get('period'))->window(
            is_string($from) ? substr($from, 0, 10) : null,
            is_string($to) ? substr($to, 0, 10) : null,
        );
    }

    /**
     * El filtro —en el móvil, tras una píldora con el periodo y la comparación, que lo abre—, el selector nativo de las
     * pestañas en el móvil y las siete pestañas; cada una, una rejilla de dos columnas en escritorio (una en móvil) con
     * sus widgets y, al pie, sus botones.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Group::make([
                View::make('filament.pages.analytics.filter-pill')->viewData(fn (): array => ['label' => $this->filterPillLabel()]),
                Group::make([$this->getFiltersFormContentComponent()])
                    ->id('analitica-filtros')
                    ->extraAttributes(['x-bind:class' => "{ 'max-md:hidden': ! filtersOpen }"]),
            ])->extraAttributes(['x-data' => '{ filtersOpen: false }']),
            View::make('filament.pages.analytics.tab-select')->viewData(fn (): array => ['tabs' => $this->tabLabels()]),
            Tabs::make(__('admin.analytics.title'))
                ->livewireProperty('tab')
                ->contained(false)
                // En el móvil la fila de pestañas se cortaba (3 de 6 a la vista, 28-09): allí manda el selector.
                ->extraAttributes(['class' => 'max-md:[&>.fi-tabs]:hidden'])
                ->tabs(array_combine(array_keys(self::TABS), array_map(
                    fn (string $tab): Tab => Tab::make(__('admin.analytics.tabs.'.$tab))
                        ->icon(self::TAB_ICONS[$tab])
                        ->schema([
                            Grid::make(['default' => 1, 'lg' => 2])
                                ->schema(fn (): array => $this->getWidgetsSchemaComponents(self::TABS[$tab])),
                            ...$this->footOf($tab),
                        ]),
                    array_keys(self::TABS),
                ))),
        ]);
    }

    /**
     * Los botones al pie de una pestaña (§4.11): «Descargar CSV» de SU informe —sin modal: el periodo y la comparación son
     * los del filtro— y, en «Clientes», «Exportar segmento». El botón se esconde sin su permiso y la ruta lo vuelve a
     * exigir (esconder no es autorizar).
     *
     * @return list<Component>
     */
    private function footOf(string $tab): array
    {
        $actions = [];

        if (isset(self::REPORTS[$tab])) {
            $actions[] = Action::make('csv_'.$tab)
                ->label(__('admin.analytics.export.button'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->hasPermission(self::PERMISSION_EXPORT) ?? false)
                ->url(fn (): string => $this->csvUrl(self::REPORTS[$tab]), shouldOpenInNewTab: true);
        }

        // «Exportar segmento» (T4b), RETIRADO por la TP·3b (`#793`, owner 28-09: «no quiero exportar datos de los menores ni
        // de los clientes… solo analítica»): los segmentos quedan como recuentos. Nada del cuadro sale con nombres.

        if ($tab === self::DEFAULT_TAB) {
            $actions[] = $this->goalsAction();
        }

        return $actions === [] ? [] : [Actions::make($actions)->alignment(Alignment::End)];
    }

    /**
     * «Objetivos del mes» (T3c·2, `#759`): al pie de «Resumen», un formulario con el mes —este o el siguiente— y un campo
     * por cifra ({@see GoalsForm}). Se esconde sin `analytics.manage`. Pública: Filament la resuelve por su nombre.
     *
     * ⚠️ `SEC-04` lo cumple el propio `visible()`: al enviar, Filament lo vuelve a evaluar (`callMountedAction` →
     * `isDisabled()` → `isHidden()`, medido el 28-09) y con el permiso retirado no llama a `action()`; una segunda
     * comprobación aquí dentro no se podría alcanzar. Lo vigila `AnalyticsGoalsTest` (el permiso retirado con el
     * formulario abierto). Tampoco llega un mes forjado: el `Select` valida contra sus opciones al enviar (este y el
     * siguiente, recalculadas en ese momento).
     */
    public function goalsAction(): Action
    {
        return Action::make('goals')
            ->label(__('admin.analytics.goals.button'))
            ->icon(Heroicon::OutlinedFlag)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasPermission(self::PERMISSION_GOALS) ?? false)
            ->modalHeading(__('admin.analytics.goals.modal_heading'))
            ->modalDescription(__('admin.analytics.goals.modal_description'))
            ->modalSubmitActionLabel(__('admin.analytics.goals.submit'))
            ->modalWidth('lg')
            ->fillForm(fn (): array => GoalsForm::fill(GoalsForm::months()[array_key_first(GoalsForm::months())]))
            ->schema(fn (): array => GoalsForm::schema())
            ->action(function (array $data): void {
                $month = GoalsForm::month($data['month'] ?? null)
                    ?? throw new LogicException('El mes de los objetivos llega validado por su Select.');

                $changed = AnalyticsGoals::save($month, GoalsForm::targets($data), auth()->id());

                Notification::make()
                    ->title(__($changed ? 'admin.analytics.goals.saved' : 'admin.analytics.goals.unchanged'))
                    ->success()
                    ->send();
                $this->dispatch(self::GOALS_SAVED_EVENT);
            });
    }

    /** El CSV de un informe con el filtro de la página (T2d). */
    private function csvUrl(string $report): string
    {
        $filters = $this->filters ?? [];

        return route('admin.analitica.csv', array_filter([
            'report' => $report,
            'period' => ReportPeriod::fromValue($filters['period'] ?? null)->value,
            'compare' => Comparison::fromValue($filters['compare'] ?? null)->value,
            'from' => is_string($filters['from'] ?? null) ? substr($filters['from'], 0, 10) : null,
            'to' => is_string($filters['to'] ?? null) ? substr($filters['to'], 0, 10) : null,
        ], static fn ($v): bool => $v !== null));
    }

    /** «Este mes · frente al periodo anterior»: lo que dice la píldora del filtro en el móvil. */
    private function filterPillLabel(): string
    {
        $filters = $this->filters ?? [];

        return __('admin.analytics.filter_pill', [
            'period' => ReportPeriod::fromValue($filters['period'] ?? null)->label(),
            'compare' => __('admin.analytics.compare.short.'.Comparison::fromValue($filters['compare'] ?? null)->value),
        ]);
    }

    /** @return array<string, string> */
    private function tabLabels(): array
    {
        return array_combine(array_keys(self::TABS), array_map(static fn (string $tab): string => __('admin.analytics.tabs.'.$tab), array_keys(self::TABS)));
    }

    /**
     * Todos los widgets de ESTE cuadro, pestaña a pestaña (lo que «Hoy» no hereda).
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
