<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\EmailsReport;
use App\Filament\Resources\EmailSends\Tables\EmailSendTable;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **Los correos, uno a uno, plegado** (`specs/correos-salientes.md` §4.14, `#796`, la C4): de los que salieron en el periodo,
 * cuántos, qué parte se pulsó y cuál se abrió (entre los que se medían) y cuánto se tarda en pulsar. En conjunto y sin
 * persona (`RGPD-07`): una cifra de 1 a 4 se escribe «menos de 5», y un % o una mediana sobre menos de cinco, «—». El CSV de
 * «Marketing» lleva la misma tabla (`tablesFor()`).
 */
class EmailsWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 18;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.tables';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $r = EmailsReport::for($this->window());

        $rows = array_map(fn (array $m): array => [
            EmailSendTable::label($m['key']),
            self::count($m['sent']),
            self::share($m['clicked'], $m['clicks_measured']),
            self::share($m['opened'], $m['opens_measured']),
            $m['clicks_timed'] < EmailsReport::MIN_CELL ? '—' : self::duration((int) $m['minutes_to_click']),
        ], $r['by_mail']);

        return [
            'heading' => __('admin.analytics.emails.heading'),
            'description' => __('admin.analytics.emails.note', ['min' => EmailsReport::MIN_CELL]),
            'tables' => [[
                'heading' => __('admin.analytics.emails.table'),
                'wide' => true,
                'columns' => [
                    __('admin.analytics.emails.col.mail'), __('admin.analytics.emails.col.sent'), __('admin.analytics.emails.col.clicked'),
                    __('admin.analytics.emails.col.opened'), __('admin.analytics.emails.col.to_click'),
                ],
                'rows' => $rows,
            ]],
        ];
    }

    /** Un recuento de 1 a 4, dicho «menos de 5». */
    private static function count(int $n): string
    {
        return $n > 0 && $n < EmailsReport::MIN_CELL ? __('admin.analytics.surveys.fewer_than_min', ['min' => EmailsReport::MIN_CELL]) : (string) $n;
    }

    /** «34 % de 120»; «No se mide» si ninguno se medía; «—» con menos de cinco medidos. */
    private static function share(int $n, int $of): string
    {
        return match (true) {
            $of === 0 => (string) __('admin.analytics.emails.not_measured'),
            $of < EmailsReport::MIN_CELL => '—',
            default => __('admin.analytics.emails.share', ['percent' => number_format($n / $of * 100, 0, ',', '.'), 'of' => $of]),
        };
    }

    /** «12 min», «3 h», «2 días»: lo que se tarda en pulsar, redondeado a lo que se lee de un vistazo. */
    private static function duration(int $minutes): string
    {
        return match (true) {
            $minutes < 1 => __('admin.analytics.emails.under_a_minute'),
            $minutes < 60 => __('admin.analytics.emails.minutes', ['n' => $minutes]),
            $minutes < 48 * 60 => __('admin.analytics.emails.hours', ['n' => intdiv($minutes, 60)]),
            default => __('admin.analytics.emails.days', ['n' => intdiv($minutes, 24 * 60)]),
        };
    }
}
