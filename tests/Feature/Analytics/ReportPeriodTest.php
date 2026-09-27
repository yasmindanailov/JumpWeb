<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Enums\Comparison;
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
 * **El periodo y la ventana del cuadro de mando** (`specs/analitica.md` §4.5, T2a; `#735`), y desde la T0 de
 * `specs/analitica-para-decidir.md` (§4.3, `#755`) **el tramo transcurrido**: un periodo EN CURSO termina ahora y se
 * compara con el MISMO tramo del periodo con el que se compara (medido el 27-09: «Este mes» llegaba al día 30 y se
 * comparaba con 30 días enteros).
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
        return $this->daysOf($period->window());
    }

    /** @return array{0: string, 1: string} */
    private function daysOf(Window $window): array
    {
        return [$window->dateFrom(), $window->dateTo()];
    }

    /** @return array{0: string, 1: string} los dos bordes en UTC, `[from, to)` */
    private function instants(Window $window): array
    {
        return [$window->utcFrom()->format('Y-m-d H:i:s'), $window->utcTo()->format('Y-m-d H:i:s')];
    }

    // ─── Los rangos, en la zona del parque ──────────────────────────────────────────────────────

    public function test_today_and_yesterday_are_single_days(): void
    {
        $this->assertSame(['2026-06-10', '2026-06-10'], $this->days(ReportPeriod::Today));
        $this->assertSame(['2026-06-09', '2026-06-09'], $this->days(ReportPeriod::Yesterday));
        $this->assertSame(1, ReportPeriod::Today->window()->days());
    }

    /** Esta semana va del lunes a HOY; la pasada, de lunes a domingo entera. */
    public function test_weeks_run_monday_to_sunday_and_this_week_to_today(): void
    {
        $this->assertSame(['2026-06-08', '2026-06-10'], $this->days(ReportPeriod::ThisWeek));
        $this->assertSame(['2026-06-01', '2026-06-07'], $this->days(ReportPeriod::LastWeek));
        $this->assertSame(7, ReportPeriod::LastWeek->window()->days());
    }

    public function test_months_run_first_to_last_day_and_this_month_to_today(): void
    {
        $this->assertSame(['2026-06-01', '2026-06-10'], $this->days(ReportPeriod::ThisMonth));
        $this->assertSame(['2026-05-01', '2026-05-31'], $this->days(ReportPeriod::LastMonth));
        $this->assertSame(10, ReportPeriod::ThisMonth->window()->days(), 'el día de hoy, a medias, cuenta');
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

    // ─── EN CURSO: hasta ahora (T0, #755) ───────────────────────────────────────────────────────

    /** Un periodo en curso termina en el segundo SIGUIENTE a ahora (la BD no guarda fracción); uno cerrado, entero. */
    public function test_a_period_in_progress_ends_now_and_a_closed_one_is_whole(): void
    {
        foreach ([ReportPeriod::Today, ReportPeriod::ThisWeek, ReportPeriod::ThisMonth, ReportPeriod::ThisQuarter, ReportPeriod::ThisYear, ReportPeriod::Last30, ReportPeriod::Last90] as $period) {
            $window = $period->window();
            $this->assertTrue($window->isInProgress(), $period->value.' está en curso');
            $this->assertSame('2026-06-10 09:00:01', $window->utcTo()->format('Y-m-d H:i:s'), $period->value.' termina ahora (con el segundo en curso dentro)');
        }

        foreach ([ReportPeriod::Yesterday, ReportPeriod::LastWeek, ReportPeriod::LastMonth, ReportPeriod::LastQuarter, ReportPeriod::LastYear] as $period) {
            $this->assertFalse($period->window()->isInProgress(), $period->value.' está cerrado');
        }

        // A medida: si llega a hoy o más allá, hasta ahora; si ya pasó, entera.
        $this->assertSame(['2026-06-01', '2026-06-10'], $this->daysOf(ReportPeriod::Custom->window('2026-06-01', '2026-06-20')));
        $this->assertTrue(ReportPeriod::Custom->window('2026-06-01', '2026-06-20')->isInProgress());
        $this->assertFalse(ReportPeriod::Custom->window('2026-05-01', '2026-05-20')->isInProgress());
    }

    public function test_the_cut_keeps_the_current_second_inside(): void
    {
        $window = ReportPeriod::ThisMonth->window();

        $this->assertTrue($window->contains(CarbonImmutable::parse('2026-06-10 09:00:00', 'UTC')), 'lo escrito en este segundo entra');
        $this->assertFalse($window->contains(CarbonImmutable::parse('2026-06-10 09:00:01', 'UTC')), 'el segundo siguiente, no');
    }

    // ─── La ventana con la que se compara ───────────────────────────────────────────────────────

    /** El mes en curso contra el MISMO tramo del anterior: del 1 al 10 a las 09:00 contra del 1 al 10 a las 09:00. */
    public function test_this_month_compares_with_the_same_stretch_of_last_month(): void
    {
        $previous = ReportPeriod::ThisMonth->window()->previous();

        $this->assertSame(['2026-05-01 00:00:00', '2026-05-10 09:00:01'], $this->instants($previous));
        $this->assertSame(['2026-05-01', '2026-05-10'], $this->daysOf($previous));

        // Y un periodo cerrado, contra el anterior entero.
        $this->assertSame(['2026-04-01', '2026-04-30'], $this->daysOf(ReportPeriod::LastMonth->window()->previous()));
        $this->assertSame(['2025-10-01', '2025-12-31'], $this->daysOf(ReportPeriod::LastQuarter->window()->previous()), 'el trimestre cerrado, contra el anterior entero');
        $this->assertSame(['2026-01-01', '2026-03-10'], $this->daysOf(ReportPeriod::ThisQuarter->window()->previous()), 'el trimestre en curso contra el mismo tramo del anterior');
        $this->assertSame(['2025-01-01', '2025-06-10'], $this->daysOf(ReportPeriod::ThisYear->window()->previous()));
    }

    /** Día y semana, contra el mismo día de la SEMANA: en un parque un martes no se compara con un lunes. */
    public function test_days_and_weeks_compare_with_the_same_weekday(): void
    {
        $this->assertSame(['2026-06-02', '2026-06-02'], $this->daysOf(ReportPeriod::Yesterday->window()->previous()), 'martes contra martes');
        $this->assertSame(['2026-06-03 00:00:00', '2026-06-03 09:00:01'], $this->instants(ReportPeriod::Today->window()->previous()), 'hoy hasta ahora contra el miércoles pasado hasta la misma hora');
        $this->assertSame(['2026-06-01', '2026-06-03'], $this->daysOf(ReportPeriod::ThisWeek->window()->previous()), 'de lunes a miércoles contra de lunes a miércoles');
        $this->assertSame(['2026-05-25', '2026-05-31'], $this->daysOf(ReportPeriod::LastWeek->window()->previous()));

        // Hace un año, 52 semanas: el martes 9 de junio de 2026 contra el martes 10 de junio de 2025.
        $yearAgo = ReportPeriod::Yesterday->window()->yearAgo();
        $this->assertSame(['2025-06-10', '2025-06-10'], $this->daysOf($yearAgo));
        $this->assertSame(CarbonImmutable::parse('2026-06-09')->dayOfWeek, CarbonImmutable::parse($yearAgo->dateFrom())->dayOfWeek);
    }

    /** El 31 de marzo contra febrero: febrero ENTERO, no un día de marzo colado. */
    public function test_a_31st_compares_with_the_whole_of_a_shorter_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-31 09:00:00'));

        $previous = ReportPeriod::ThisMonth->window()->previous();
        $this->assertSame(['2026-02-01', '2026-02-28'], $this->daysOf($previous));
        $this->assertSame('2026-02-28 09:00:01', $previous->utcTo()->format('Y-m-d H:i:s'), 'el tramo: hasta el 28 a la misma hora');
    }

    /** 30 días y a medida: la ventana pegada por delante, de la misma longitud y cortada en el mismo instante. */
    public function test_rolling_and_custom_windows_compare_with_the_one_right_before(): void
    {
        $this->assertSame(['2026-04-12 00:00:00', '2026-05-11 09:00:01'], $this->instants(ReportPeriod::Last30->window()->previous()), '30 días atrás, cortada a la misma hora');
        $this->assertSame(['2026-02-19', '2026-02-28'], $this->daysOf(ReportPeriod::Custom->window('2026-03-01', '2026-03-10')->previous()));
    }

    /** El corte se desplaza en la hora de PARED del parque: las 11:00 del miércoles son las 11:00 del miércoles anterior, con o sin cambio de hora. */
    public function test_the_cut_keeps_the_wall_clock_across_the_time_change(): void
    {
        $this->timezone('Europe/Madrid');
        Carbon::setTestNow(Carbon::parse('2026-04-01 09:00:00'));   // 11:00 en Madrid (CEST); el 29 de marzo cambió la hora

        $previous = ReportPeriod::Today->window()->previous();

        $this->assertSame(['2026-03-24 23:00:00', '2026-03-25 10:00:01'], $this->instants($previous), 'el 25 de marzo aún es CET: las 11:00 son las 10:00 UTC');
    }

    // ─── El mismo periodo del año pasado ────────────────────────────────────────────────────────

    public function test_the_year_ago_window_keeps_the_same_civil_days_and_the_same_stretch(): void
    {
        $june = ReportPeriod::ThisMonth->window();
        $this->assertSame(['2025-06-01', '2025-06-10'], $this->daysOf($june->yearAgo()));
        $this->assertSame('2025-06-10 09:00:01', $june->yearAgo()->utcTo()->format('Y-m-d H:i:s'));

        $this->assertSame(['2025-05-01', '2025-05-31'], $this->daysOf(ReportPeriod::LastMonth->window()->yearAgo()));
        $this->assertSame(['2025-04-01', '2025-06-10'], $this->daysOf(ReportPeriod::ThisQuarter->window()->yearAgo()));

        // Un 29 de febrero cae al 28.
        Carbon::setTestNow(Carbon::parse('2028-02-29 09:00:00'));
        $this->assertSame(['2027-02-01', '2027-02-28'], $this->daysOf(ReportPeriod::ThisMonth->window()->yearAgo()));

        // Y febrero entero de 2029 contra el de 2028, que tiene 29 días.
        Carbon::setTestNow(Carbon::parse('2029-03-10 09:00:00'));
        $this->assertSame(['2028-02-01', '2028-02-29'], $this->daysOf(ReportPeriod::LastMonth->window()->yearAgo()));
    }

    public function test_the_comparison_resolves_to_the_two_baselines(): void
    {
        $window = ReportPeriod::ThisMonth->window();

        $this->assertSame($this->daysOf($window->previous()), $this->daysOf(Comparison::Previous->baseline($window)));
        $this->assertSame($this->daysOf($window->yearAgo()), $this->daysOf(Comparison::YearAgo->baseline($window)));
    }

    // ─── La granularidad y los cubos ────────────────────────────────────────────────────────────

    public function test_up_to_31_days_is_by_day_and_beyond_is_by_week(): void
    {
        $this->assertSame(Window::GRANULARITY_DAY, ReportPeriod::LastMonth->window()->granularity());
        $this->assertSame(Window::GRANULARITY_DAY, ReportPeriod::Last30->window()->granularity());
        $this->assertSame(Window::GRANULARITY_WEEK, ReportPeriod::Last90->window()->granularity());
    }

    public function test_bucket_keys_have_no_gaps_stop_today_and_weeks_start_on_monday(): void
    {
        $days = ReportPeriod::ThisMonth->window()->bucketKeys();
        $this->assertCount(10, $days, 'ni un día que aún no ha pasado');
        $this->assertSame('2026-06-01', $days[0]);
        $this->assertSame('2026-06-10', $days[9]);

        $this->assertCount(31, ReportPeriod::LastMonth->window()->bucketKeys());

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

        $june = Window::ofDays(CarbonImmutable::parse('2026-06-01', 'Europe/Madrid'), CarbonImmutable::parse('2026-06-30', 'Europe/Madrid'), 'Europe/Madrid', Window::UNIT_MONTH);
        $this->assertSame(['2026-05-31 22:00:00', '2026-06-30 22:00:00'], $this->instants($june));
        $this->assertSame(['2026-06-01', '2026-06-30'], $this->daysOf($june));

        $lateEvening = CarbonImmutable::parse('2026-06-09 22:30:00', 'UTC');
        $this->assertSame('2026-06-10', $june->bucketKey($lateEvening));
        $this->assertTrue($june->contains(CarbonImmutable::parse('2026-05-31 22:30:00', 'UTC')), 'las 00:30 de Madrid del 1 de junio SON junio');
        $this->assertFalse($june->contains(CarbonImmutable::parse('2026-06-30 22:30:00', 'UTC')), 'las 00:30 de Madrid del 1 de julio NO son junio');

        // Y el filtro de junio en curso, en Madrid: desde la medianoche del parque hasta ahora.
        $this->assertSame(['2026-05-31 22:00:00', '2026-06-10 09:00:01'], $this->instants(ReportPeriod::ThisMonth->window()));

        // El control: en UTC ese mismo instante sigue siendo el 9.
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

    public function test_quarters_and_years_run_whole_when_closed_and_to_today_when_in_progress(): void
    {
        $this->assertSame(['2026-04-01', '2026-06-10'], $this->days(ReportPeriod::ThisQuarter));
        $this->assertSame(['2026-01-01', '2026-03-31'], $this->days(ReportPeriod::LastQuarter));
        $this->assertSame(['2026-01-01', '2026-06-10'], $this->days(ReportPeriod::ThisYear));
        $this->assertSame(['2025-01-01', '2025-12-31'], $this->days(ReportPeriod::LastYear));

        $this->assertSame(Window::GRANULARITY_WEEK, ReportPeriod::ThisQuarter->window()->granularity(), '71 días: por semana');
        $this->assertSame(Window::GRANULARITY_MONTH, ReportPeriod::ThisYear->window()->granularity());
        $this->assertSame(Window::GRANULARITY_MONTH, ReportPeriod::LastYear->window()->granularity());

        $months = ReportPeriod::ThisYear->window()->bucketKeys();
        $this->assertCount(6, $months, 'de enero a junio: ni un mes que aún no ha empezado');
        $this->assertSame('2026-01-01', $months[0]);
        $this->assertSame('2026-06-01', $months[5]);
        $this->assertCount(12, ReportPeriod::LastYear->window()->bucketKeys());
        $this->assertSame('2026-06-01', ReportPeriod::ThisYear->window()->bucketKey(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC')));
    }

    public function test_a_custom_range_orders_its_dates_caps_at_a_year_and_falls_back_when_unreadable(): void
    {
        $this->assertSame(['2026-03-01', '2026-04-15'], $this->daysOf(ReportPeriod::Custom->window('2026-03-01', '2026-04-15')));
        $this->assertSame(['2026-03-01', '2026-04-15'], $this->daysOf(ReportPeriod::Custom->window('2026-04-15', '2026-03-01')), 'al revés, se ordenan');
        $this->assertSame(['2026-03-01', '2026-03-01'], $this->daysOf(ReportPeriod::Custom->window('2026-03-01', null)), 'sin «hasta», un día');
        $this->assertSame(['2024-01-01', '2024-12-31'], $this->daysOf(ReportPeriod::Custom->window('2024-01-01', '2026-01-01')), 'más de un año se acorta al año');
        $this->assertSame(['2026-06-01', '2026-06-10'], $this->daysOf(ReportPeriod::Custom->window('ayer', 'hoy')), 'ilegible: el periodo por defecto, hasta hoy');
        $this->assertSame(['2026-06-01', '2026-06-10'], $this->daysOf(ReportPeriod::Custom->window(null, null)));
        $this->assertSame(['2026-03-01', '2026-03-05'], $this->daysOf(ReportPeriod::Custom->window('2026-03-01 00:00:00', '2026-03-05T12:00')), 'un datetime del selector se recorta a la fecha');
        $this->assertSame(Window::GRANULARITY_DAY, ReportPeriod::Custom->window('2026-03-01', '2026-03-31')->granularity());
    }

    public function test_a_window_needs_at_least_one_day_and_a_known_unit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Window::ofDays(CarbonImmutable::parse('2026-06-10', 'UTC'), CarbonImmutable::parse('2026-06-09', 'UTC'), 'UTC');
    }

    public function test_an_unknown_unit_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Window::ofDays(CarbonImmutable::parse('2026-06-09', 'UTC'), CarbonImmutable::parse('2026-06-10', 'UTC'), 'UTC', 'fortnight');
    }
}
