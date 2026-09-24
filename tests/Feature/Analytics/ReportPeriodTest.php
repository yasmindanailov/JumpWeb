<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Reports\SqlTime;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * **El periodo y la ventana del cuadro de mando** (`specs/analitica.md` §4.5, T2a; `#735`).
 *
 * Reloj fijado en el miércoles 2026-06-10 a las 09:00 UTC. Los rangos se afirman en UTC (zona del parque
 * `UTC`) y las conversiones en `Europe/Madrid`, que es donde el «día» y el «instante» dejan de coincidir:
 * un cobro a las 22:30 UTC del 9 es del 10 en el parque, y ahí es donde `DATE(paid_at)` mentiría.
 */
class ReportPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timezone('UTC');
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00')); // miércoles
    }

    private function timezone(string $tz): void
    {
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => $tz, 'group' => 'general']);
        Setting::flushMemo();
    }

    /** @return array{0: string, 1: string} el primer día y el último, inclusive */
    private function days(ReportPeriod $period): array
    {
        $w = $period->window();

        return [$w->dateFrom(), $w->dateTo()];
    }

    // ─── Los rangos, en la zona del parque ──────────────────────────────────────────────────────

    public function test_today_and_yesterday_are_single_days(): void
    {
        $this->assertSame(['2026-06-10', '2026-06-10'], $this->days(ReportPeriod::Today));
        $this->assertSame(['2026-06-09', '2026-06-09'], $this->days(ReportPeriod::Yesterday));
        $this->assertSame(1, ReportPeriod::Today->window()->days());
    }

    public function test_weeks_run_monday_to_sunday(): void
    {
        $this->assertSame(['2026-06-08', '2026-06-14'], $this->days(ReportPeriod::ThisWeek));
        $this->assertSame(['2026-06-01', '2026-06-07'], $this->days(ReportPeriod::LastWeek));
        $this->assertSame(7, ReportPeriod::LastWeek->window()->days());
    }

    public function test_months_run_first_to_last_day(): void
    {
        $this->assertSame(['2026-06-01', '2026-06-30'], $this->days(ReportPeriod::ThisMonth));
        $this->assertSame(['2026-05-01', '2026-05-31'], $this->days(ReportPeriod::LastMonth));
        $this->assertSame(30, ReportPeriod::ThisMonth->window()->days());
        $this->assertSame(31, ReportPeriod::LastMonth->window()->days());
    }

    public function test_rolling_windows_end_today_and_count_today(): void
    {
        $this->assertSame(['2026-05-12', '2026-06-10'], $this->days(ReportPeriod::Last30));
        $this->assertSame(['2026-03-13', '2026-06-10'], $this->days(ReportPeriod::Last90));
        $this->assertSame(30, ReportPeriod::Last30->window()->days());
        $this->assertSame(90, ReportPeriod::Last90->window()->days());
    }

    /** El mes pasado, visto desde un 31: `subMonth` a secas daría el 1 de mayo desde el 31 de mayo… o julio. */
    public function test_last_month_does_not_overflow_from_a_31st(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-31 09:00:00'));

        $this->assertSame(['2026-06-01', '2026-06-30'], $this->days(ReportPeriod::LastMonth));
    }

    // ─── La ventana anterior ────────────────────────────────────────────────────────────────────

    public function test_previous_window_has_the_same_length_and_ends_the_day_before(): void
    {
        $previous = ReportPeriod::ThisMonth->window()->previous();
        $this->assertSame(['2026-05-02', '2026-05-31'], [$previous->dateFrom(), $previous->dateTo()]);
        $this->assertSame(30, $previous->days());

        $previous = ReportPeriod::LastMonth->window()->previous();
        $this->assertSame(['2026-03-31', '2026-04-30'], [$previous->dateFrom(), $previous->dateTo()]);
        $this->assertSame(31, $previous->days());
    }

    // ─── La granularidad y los cubos ────────────────────────────────────────────────────────────

    public function test_up_to_31_days_is_by_day_and_beyond_is_by_week(): void
    {
        $this->assertSame(Window::GRANULARITY_DAY, ReportPeriod::LastMonth->window()->granularity());
        $this->assertSame(Window::GRANULARITY_DAY, ReportPeriod::Last30->window()->granularity());
        $this->assertSame(Window::GRANULARITY_WEEK, ReportPeriod::Last90->window()->granularity());
    }

    public function test_bucket_keys_have_no_gaps_and_weeks_start_on_monday(): void
    {
        $days = ReportPeriod::ThisMonth->window()->bucketKeys();
        $this->assertCount(30, $days);
        $this->assertSame('2026-06-01', $days[0]);
        $this->assertSame('2026-06-30', $days[29]);

        // 90 días desde el viernes 13 de marzo: la primera semana es la del lunes 9, aunque quede fuera.
        $weeks = ReportPeriod::Last90->window()->bucketKeys();
        $this->assertSame('2026-03-09', $weeks[0]);
        $this->assertSame('2026-06-08', $weeks[array_key_last($weeks)]);
        $this->assertCount(14, $weeks);
        $this->assertSame('2026-04-13', ReportPeriod::Last90->window()->bucketKey(CarbonImmutable::parse('2026-04-15 10:00:00', 'UTC')));
    }

    // ─── La zona del parque: el día y el instante no coinciden ──────────────────────────────────

    /** Un cobro a las 22:30 UTC del 9 es del DÍA 10 en Madrid, y la ventana de junio empieza el 31 de mayo a las 22:00 UTC. */
    public function test_in_madrid_the_window_edges_and_the_bucket_key_follow_the_park_day(): void
    {
        $this->timezone('Europe/Madrid');

        $june = ReportPeriod::ThisMonth->window();
        $this->assertSame('2026-05-31 22:00:00', $june->utcFrom()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-30 22:00:00', $june->utcTo()->format('Y-m-d H:i:s'));
        $this->assertSame(['2026-06-01', '2026-06-30'], [$june->dateFrom(), $june->dateTo()]);

        $lateEvening = CarbonImmutable::parse('2026-06-09 22:30:00', 'UTC');
        $this->assertSame('2026-06-10', $june->bucketKey($lateEvening));
        $this->assertTrue($june->contains(CarbonImmutable::parse('2026-05-31 22:30:00', 'UTC')), 'las 00:30 de Madrid del 1 de junio SON junio');
        $this->assertFalse($june->contains(CarbonImmutable::parse('2026-06-30 22:30:00', 'UTC')), 'las 00:30 de Madrid del 1 de julio NO son junio');

        // Y el control: en UTC ese mismo instante sigue siendo el 9.
        $this->timezone('UTC');
        $this->assertSame('2026-06-09', ReportPeriod::ThisMonth->window()->bucketKey($lateEvening));
    }

    /** El cubo de hora que devuelve el SQL (`YYYY-MM-DD HH`, UTC) vuelve a ser un instante con el que preguntar el día del parque. */
    public function test_an_sql_hour_bucket_maps_back_to_the_park_day(): void
    {
        $this->timezone('Europe/Madrid');

        $start = SqlTime::bucketStart('2026-06-09 22');
        $this->assertSame('2026-06-09 22:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $start->timezoneName);
        $this->assertSame('2026-06-10', ReportPeriod::ThisMonth->window()->bucketKey($start));

        // La expresión existe para el driver de la suite y no se rompe al pedirla.
        $this->assertStringContainsString('%Y-%m-%d %H', SqlTime::hourBucket('paid_at'));
    }

    // ─── El filtro ──────────────────────────────────────────────────────────────────────────────

    public function test_from_value_resolves_known_values_and_falls_back_to_this_month(): void
    {
        $this->assertSame(ReportPeriod::LastWeek, ReportPeriod::fromValue('last_week'));
        $this->assertSame(ReportPeriod::Last90, ReportPeriod::fromValue('last_90'));
        $this->assertSame(ReportPeriod::ThisMonth, ReportPeriod::fromValue('bogus'));
        $this->assertSame(ReportPeriod::ThisMonth, ReportPeriod::fromValue(null));
        $this->assertSame(ReportPeriod::ThisMonth, ReportPeriod::fromValue(123));
    }

    public function test_options_keep_the_order_of_the_cases(): void
    {
        $this->assertSame(
            ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year', 'last_year', 'last_30', 'last_90', 'custom'],
            array_keys(ReportPeriod::options()),
        );
    }

    // ─── Trimestres, años y a medida (lo que pidió el owner al ver el cuadro, 24-09) ────────────

    public function test_quarters_and_years_run_whole_and_group_by_month(): void
    {
        $this->assertSame(['2026-04-01', '2026-06-30'], $this->days(ReportPeriod::ThisQuarter));
        $this->assertSame(['2026-01-01', '2026-03-31'], $this->days(ReportPeriod::LastQuarter));
        $this->assertSame(['2026-01-01', '2026-12-31'], $this->days(ReportPeriod::ThisYear));
        $this->assertSame(['2025-01-01', '2025-12-31'], $this->days(ReportPeriod::LastYear));

        $this->assertSame(Window::GRANULARITY_WEEK, ReportPeriod::ThisQuarter->window()->granularity(), '91 días: por semana');
        $this->assertSame(Window::GRANULARITY_MONTH, ReportPeriod::ThisYear->window()->granularity());

        $months = ReportPeriod::ThisYear->window()->bucketKeys();
        $this->assertCount(12, $months);
        $this->assertSame('2026-01-01', $months[0]);
        $this->assertSame('2026-12-01', $months[11]);
        $this->assertSame('2026-06-01', ReportPeriod::ThisYear->window()->bucketKey(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC')));
    }

    public function test_the_year_ago_window_keeps_the_same_civil_days(): void
    {
        $june = ReportPeriod::ThisMonth->window();
        $this->assertSame(['2025-06-01', '2025-06-30'], [$june->yearAgo()->dateFrom(), $june->yearAgo()->dateTo()]);

        $quarter = ReportPeriod::ThisQuarter->window();
        $this->assertSame(['2025-04-01', '2025-06-30'], [$quarter->yearAgo()->dateFrom(), $quarter->yearAgo()->dateTo()]);

        // Un 29 de febrero cae al 28.
        Carbon::setTestNow(Carbon::parse('2028-02-29 09:00:00'));
        $this->assertSame(['2027-02-01', '2027-02-28'], [ReportPeriod::ThisMonth->window()->yearAgo()->dateFrom(), ReportPeriod::ThisMonth->window()->yearAgo()->dateTo()]);
    }

    public function test_a_custom_range_orders_its_dates_caps_at_a_year_and_falls_back_when_unreadable(): void
    {
        $this->assertSame(['2026-03-01', '2026-04-15'], $this->daysOf(ReportPeriod::Custom->window('2026-03-01', '2026-04-15')));
        $this->assertSame(['2026-03-01', '2026-04-15'], $this->daysOf(ReportPeriod::Custom->window('2026-04-15', '2026-03-01')), 'al revés, se ordenan');
        $this->assertSame(['2026-03-01', '2026-03-01'], $this->daysOf(ReportPeriod::Custom->window('2026-03-01', null)), 'sin «hasta», un día');
        $this->assertSame(['2024-01-01', '2024-12-31'], $this->daysOf(ReportPeriod::Custom->window('2024-01-01', '2026-01-01')), 'más de un año se acorta al año');
        $this->assertSame(['2026-06-01', '2026-06-30'], $this->daysOf(ReportPeriod::Custom->window('ayer', 'hoy')), 'ilegible: el periodo por defecto');
        $this->assertSame(['2026-06-01', '2026-06-30'], $this->daysOf(ReportPeriod::Custom->window(null, null)));
        $this->assertSame(['2026-03-01', '2026-03-05'], $this->daysOf(ReportPeriod::Custom->window('2026-03-01 00:00:00', '2026-03-05T12:00')), 'un datetime del selector se recorta a la fecha');
        $this->assertSame(Window::GRANULARITY_DAY, ReportPeriod::Custom->window('2026-03-01', '2026-03-31')->granularity());
    }

    /** @return array{0: string, 1: string} */
    private function daysOf(Window $window): array
    {
        return [$window->dateFrom(), $window->dateTo()];
    }

    public function test_a_window_needs_at_least_one_day(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Window::ofDays(CarbonImmutable::parse('2026-06-10', 'UTC'), CarbonImmutable::parse('2026-06-09', 'UTC'), 'UTC');
    }
}
