<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Services\Money;
use App\Filament\Analytics\BucketLabel;
use App\Filament\Analytics\PartiesReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **La fiesta, al detalle** (`specs/analitica-fiesta.md` §4.3, T2): las tablas de los gráficos y lo que no cabe en
 * una tarjeta —por día o semana de fiesta, el embudo paso a paso, el dinero de después de reservar con las ediciones
 * del panel aparte, por complemento, la invitación y el justificante, los tiempos y los invitados—. Plegada al pie
 * de la pestaña, y es lo que lleva el CSV.
 */
class PartiesBreakdownWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 14;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.tables';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $report = $this->parties();

        return [
            'heading' => __('admin.analytics.parties.breakdown_heading'),
            'description' => __('admin.analytics.parties.breakdown_note'),
            'tables' => [
                $this->series($report['series'], (string) $report['window']['granularity']),
                $this->funnel($report['funnel']),
                $this->money($report['money']),
                $this->byAddon($report['money']['by_addon']),
                $this->invitationAndAuthorization($report['invitations'], $report['authorizations']),
                $this->timing($report['timing'], $report['forms']),
                $this->guests($report['guests']),
            ],
        ];
    }

    /**
     * @param  list<array{key: string, parties: int, completed: int, invitations: int, replies_yes: int, signatures: int, extras: int}>  $series
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function series(array $series, string $granularity): array
    {
        $rows = array_map(static fn (array $r): array => [
            BucketLabel::long($r['key'], $granularity),
            (string) $r['parties'],
            (string) $r['completed'],
            (string) $r['invitations'],
            (string) $r['replies_yes'],
            (string) $r['signatures'],
            Money::format($r['extras']),
        ], $series);

        $sum = static fn (string $field): int => (int) array_sum(array_column($series, $field));
        $rows[] = [
            __('admin.analytics.money.col.total'),
            (string) $sum('parties'), (string) $sum('completed'), (string) $sum('invitations'),
            (string) $sum('replies_yes'), (string) $sum('signatures'), Money::format($sum('extras')),
        ];

        return [
            'heading' => BucketLabel::heading($granularity),
            'columns' => [
                BucketLabel::column($granularity),
                __('admin.analytics.parties.col.parties'),
                __('admin.analytics.parties.col.completed'),
                __('admin.analytics.parties.col.invitations'),
                __('admin.analytics.parties.col.replies_yes'),
                __('admin.analytics.parties.col.signatures'),
                __('admin.analytics.parties.col.extras'),
            ],
            'rows' => $rows,
            'wide' => true,
        ];
    }

    /**
     * @param  list<array{step: string, reached: int, of_parties_bp: int}>  $funnel
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function funnel(array $funnel): array
    {
        return [
            'heading' => __('admin.analytics.parties.funnel'),
            'columns' => [__('admin.analytics.parties.col.step'), __('admin.analytics.parties.col.reservations'), __('admin.analytics.parties.col.of_parties')],
            'rows' => array_map(static fn (array $row): array => [
                __('admin.analytics.parties.step.'.$row['step']),
                (string) $row['reached'],
                self::percent($row['of_parties_bp']),
            ], $funnel),
        ];
    }

    /**
     * @param  array<string, mixed>  $money
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function money(array $money): array
    {
        return [
            'heading' => __('admin.analytics.parties.money_heading'),
            'columns' => [__('admin.analytics.parties.col.what'), __('admin.analytics.parties.col.amount')],
            'rows' => [
                [__('admin.analytics.parties.row.extras'), Money::format((int) $money['extras'])],
                [__('admin.analytics.parties.row.guests').' ('.__('admin.analytics.parties.row.guests_units', ['added' => $money['guests_added'], 'removed' => $money['guests_removed']]).')', Money::format((int) $money['guests'])],
                [__('admin.analytics.parties.row.sold_after'), Money::format((int) $money['sold_after_booking'])],
                [__('admin.analytics.parties.row.panel_edits'), Money::format((int) $money['panel_edits'])],
                [__('admin.analytics.parties.row.collected_in_park'), Money::format((int) $money['collected_in_park'])],
                [__('admin.analytics.parties.row.with_extras'), (string) $money['with_extras']],
                [__('admin.analytics.parties.row.avg_extras'), Money::format((int) $money['avg_extras'])],
            ],
        ];
    }

    /**
     * @param  list<array{addon: string, units: int, cents: int}>  $byAddon
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function byAddon(array $byAddon): array
    {
        return [
            'heading' => __('admin.analytics.parties.by_addon'),
            'columns' => [__('admin.analytics.parties.col.addon'), __('admin.analytics.parties.col.units'), __('admin.analytics.parties.col.amount')],
            'rows' => array_map(static fn (array $row): array => [$row['addon'], (string) $row['units'], Money::format($row['cents'])], $byAddon),
        ];
    }

    /**
     * @param  array<string, int>  $i
     * @param  array<string, int>  $a
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function invitationAndAuthorization(array $i, array $a): array
    {
        $row = static fn (string $key, int $value): array => [__('admin.analytics.parties.row.'.$key), (string) $value];

        return [
            'heading' => __('admin.analytics.parties.invitation_heading'),
            'columns' => [__('admin.analytics.parties.col.what'), __('admin.analytics.parties.col.count')],
            'rows' => [
                $row('with_invitation', $i['with']),
                $row('invitation_views', $i['views']),
                $row('invitation_viewed', $i['viewed']),
                $row('replies_yes', $i['replies_yes']),
                $row('replies_no', $i['replies_no']),
                $row('adopted', $i['adopted']),
                $row('calendar', $i['calendar']),
                $row('marked', $a['marked']),
                $row('openings', $a['openings']),
                $row('opened', $a['opened']),
                $row('signed', $a['signed']),
                $row('signatures', $a['signatures']),
                $row('from_invitation', $a['from_invitation']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $timing
     * @param  array<string, int>  $forms
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function timing(array $timing, array $forms): array
    {
        $number = static fn (int|float|null $value): string => $value === null ? __('admin.analytics.parties.none') : (string) $value;
        /** @var array<string, int> $histogram */
        $histogram = $timing['form_days_histogram'];

        $rows = [
            [__('admin.analytics.parties.row.form_days_median'), $number($timing['form_days_median'])],
            [__('admin.analytics.parties.row.on_time', ['hours' => $forms['cutoff_hours']]), $forms['on_time'].' · '.self::percent($forms['on_time_bp'])],
            [__('admin.analytics.parties.row.sign_days_median'), $number($timing['sign_days_median'])],
            [__('admin.analytics.parties.row.hours_since_open_median'), $number($timing['hours_since_open_median'])],
        ];
        foreach (PartiesReport::DAYS_BUCKETS as $bucket) {
            $rows[] = [__('admin.analytics.parties.days_bucket.'.$bucket), (string) ($histogram[$bucket] ?? 0)];
        }

        return [
            'heading' => __('admin.analytics.parties.timing_heading'),
            'columns' => [__('admin.analytics.parties.col.what'), __('admin.analytics.parties.col.count')],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{devices: array<string, int>, locales: array<string, int>}  $guests
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function guests(array $guests): array
    {
        $rows = [];
        foreach ($guests['devices'] as $device => $n) {
            $rows[] = [__('admin.analytics.parties.col.device').' · '.(__('admin.analytics.traffic.device.'.$device) !== 'admin.analytics.traffic.device.'.$device ? __('admin.analytics.traffic.device.'.$device) : $device), (string) $n];
        }
        foreach ($guests['locales'] as $locale => $n) {
            $rows[] = [__('admin.analytics.parties.col.locale').' · '.$locale, (string) $n];
        }

        return [
            'heading' => __('admin.analytics.parties.guests_heading'),
            'columns' => [__('admin.analytics.parties.col.what'), __('admin.analytics.parties.col.count')],
            'rows' => $rows,
        ];
    }
}
