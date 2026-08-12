<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Models\Setting;
use App\Support\DashboardPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 7.4 iter2 — `DashboardPeriod`: mapeo periodo → rango de fechas del filtro
 * compartido del dashboard. Reloj fijado (miércoles 2026-06-10) en UTC para un
 * rango determinista.
 */
class DashboardPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'UTC', 'group' => 'general']);
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00')); // miércoles
    }

    public function test_today_range_is_a_single_day(): void
    {
        $this->assertSame(['2026-06-10', '2026-06-10'], DashboardPeriod::Today->range());
    }

    public function test_week_range_is_monday_to_sunday(): void
    {
        $this->assertSame(['2026-06-08', '2026-06-14'], DashboardPeriod::Week->range());
    }

    public function test_month_range_is_first_to_last_day(): void
    {
        $this->assertSame(['2026-06-01', '2026-06-30'], DashboardPeriod::Month->range());
    }

    public function test_from_value_resolves_known_values(): void
    {
        $this->assertSame(DashboardPeriod::Week, DashboardPeriod::fromValue('week'));
        $this->assertSame(DashboardPeriod::Month, DashboardPeriod::fromValue('month'));
        $this->assertSame(DashboardPeriod::Today, DashboardPeriod::fromValue('today'));
    }

    public function test_from_value_falls_back_to_today(): void
    {
        $this->assertSame(DashboardPeriod::Today, DashboardPeriod::fromValue('bogus'));
        $this->assertSame(DashboardPeriod::Today, DashboardPeriod::fromValue(null));
        $this->assertSame(DashboardPeriod::Today, DashboardPeriod::fromValue(123));
    }

    public function test_options_are_ordered_today_week_month(): void
    {
        $this->assertSame(['today', 'week', 'month'], array_keys(DashboardPeriod::options()));
    }
}
