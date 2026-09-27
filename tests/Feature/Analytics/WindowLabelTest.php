<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Setting;
use App\Filament\Analytics\WindowLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **Las fechas exactas bajo el filtro** (T0 de `specs/analitica-para-decidir.md` §4.3, `#755`): desde que un periodo en
 * curso termina ahora y se compara con el mismo tramo, «el periodo anterior» ya no dice qué días son, y lo dice esta
 * línea. Los rótulos se escriben A MANO (`#734`): con `__()` dentro, vaciar la clave pasaría igual.
 */
class WindowLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));   // miércoles, 11:00 en Madrid
        app()->setLocale('es');
    }

    public function test_a_month_in_progress_and_its_same_stretch(): void
    {
        $window = ReportPeriod::ThisMonth->window();

        $this->assertSame('Del 1 al 10 jun. 2026, hasta ahora', WindowLabel::period($window));
        $this->assertSame('Del 1 al 10 may. 2026, hasta la misma hora', WindowLabel::baseline(Comparison::Previous->baseline($window)));
        $this->assertSame('Del 1 al 10 jun. 2025, hasta la misma hora', WindowLabel::baseline(Comparison::YearAgo->baseline($window)));
    }

    public function test_closed_periods_single_days_and_ranges_across_months_and_years(): void
    {
        $this->assertSame('Del 1 al 31 may. 2026', WindowLabel::period(ReportPeriod::LastMonth->window()));
        $this->assertSame('9 jun. 2026', WindowLabel::period(ReportPeriod::Yesterday->window()));
        $this->assertSame('2 jun. 2026', WindowLabel::baseline(ReportPeriod::Yesterday->window()->previous()), 'martes contra martes');
        $this->assertSame('Del 12 may. al 10 jun. 2026, hasta ahora', WindowLabel::period(ReportPeriod::Last30->window()));
        $this->assertSame('Del 1 dic. 2025 al 10 ene. 2026', WindowLabel::period(ReportPeriod::Custom->window('2025-12-01', '2026-01-10')));
    }

    public function test_the_panel_in_chinese_reads_its_own_dates(): void
    {
        app()->setLocale('zh_CN');

        $this->assertSame('1日 至 2026年6月10日（截至目前）', WindowLabel::period(ReportPeriod::ThisMonth->window()));
    }
}
