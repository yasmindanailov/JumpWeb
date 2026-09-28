<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\Changes;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **«Lo que ha cambiado»**, en «Resumen» bajo sus cifras (T3c·1 de `analitica-para-decidir.md` §4.5; `DECISIONES #791`):
 * hasta cinco frases con las cifras de TODO el cuadro que salen de su rango normal, primero las de dinero, cada una con su
 * icono, su palabra y el enlace a su pestaña; «y N más» si no caben. La selección es {@see Changes}; aquí solo se pinta.
 */
class ChangesWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.changes';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $window = $this->window();
        $changes = Changes::for($window, $this->comparison());

        $items = array_map(static function (array $item): array {
            $metric = $item['metric'];
            $tab = AnalyticsPage::tabOf($metric->key);

            return [
                'key' => $metric->key,
                'state' => $item['verdict']['state'],
                'tone' => $item['verdict']['tone'],
                'sentence' => __('admin.analytics.changes.item', ['label' => $metric->label, 'value' => $metric->displayValue(), 'verdict' => $metric->verdictLine()]),
                'link' => $tab === null ? null : [
                    'url' => AnalyticsPage::getUrl([AnalyticsPage::TAB_QUERY_KEY => $tab]),
                    'label' => __('admin.analytics.summary.see', ['tab' => __('admin.analytics.tabs.'.$tab)]),
                ],
            ];
        }, $changes['items']);

        return [
            'items' => $items,
            'more' => $changes['more'],
            'empty' => $items !== [] ? null : ($changes['judged'] > 0
                ? trans_choice('admin.analytics.changes.none', $changes['judged'], ['n' => $changes['judged']])
                : __('admin.analytics.changes.no_history', ['unit' => self::unitOf($window)])),
        ];
    }

    /** «meses», «semanas», «domingos»…: la unidad de la historia de esta ventana. */
    private static function unitOf(Window $window): string
    {
        return $window->unit === Window::UNIT_DAY
            ? __('admin.analytics.verdict.weekday.'.$window->from->isoWeekday())
            : __('admin.analytics.verdict.unit.'.$window->unit);
    }
}
