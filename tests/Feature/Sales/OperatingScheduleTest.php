<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\Season;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Services\OperatingSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Transversal §9.1 — Horario EFECTIVO del parque: una excepción de `special_dates` manda
 * sobre el `opening_hours` del día de la semana. Si no hay nada configurado, abierto y sin
 * restricción de ventana (fallback no destructivo).
 */
class OperatingScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;       // un miércoles concreto

    private int $weekday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = Carbon::parse('2026-06-10'); // miércoles
        $this->weekday = $this->date->dayOfWeek;
    }

    public function test_open_without_config_and_no_window(): void
    {
        $hours = (new OperatingSchedule)->effectiveFor($this->date);

        $this->assertTrue($hours['is_open']);
        $this->assertNull($hours['open']);
        $this->assertNull($hours['close']);
    }

    public function test_uses_the_weekly_opening_hours(): void
    {
        OpeningHour::create(['weekday' => $this->weekday, 'open_time' => '10:00:00', 'close_time' => '21:00:00']);

        $hours = (new OperatingSchedule)->effectiveFor($this->date);

        $this->assertTrue($hours['is_open']);
        $this->assertSame('10:00:00', $hours['open']);
        $this->assertSame('21:00:00', $hours['close']);
    }

    public function test_closed_weekday(): void
    {
        OpeningHour::create(['weekday' => $this->weekday, 'is_closed' => true]);

        $this->assertFalse((new OperatingSchedule)->isOpenOn($this->date));
    }

    public function test_special_date_closed_overrides_open_weekday(): void
    {
        OpeningHour::create(['weekday' => $this->weekday, 'open_time' => '10:00:00', 'close_time' => '21:00:00']);
        SpecialDate::create(['date' => $this->date->toDateString(), 'is_closed' => true]);

        $this->assertFalse((new OperatingSchedule)->isOpenOn($this->date));
    }

    public function test_special_date_window_overrides_weekly(): void
    {
        OpeningHour::create(['weekday' => $this->weekday, 'open_time' => '10:00:00', 'close_time' => '21:00:00']);
        SpecialDate::create(['date' => $this->date->toDateString(), 'open_time' => '12:00:00', 'close_time' => '16:00:00']);

        $hours = (new OperatingSchedule)->effectiveFor($this->date);

        $this->assertSame('12:00:00', $hours['open']);
        $this->assertSame('16:00:00', $hours['close']);
    }

    public function test_open_special_date_without_window_falls_back_to_weekly(): void
    {
        OpeningHour::create(['weekday' => $this->weekday, 'open_time' => '10:00:00', 'close_time' => '21:00:00']);
        // Festivo (solo cambia la tarifa) sin horario propio → hereda la ventana semanal.
        SpecialDate::create(['date' => $this->date->toDateString(), 'is_closed' => false]);

        $hours = (new OperatingSchedule)->effectiveFor($this->date);

        $this->assertTrue($hours['is_open']);
        $this->assertSame('10:00:00', $hours['open']);
        $this->assertSame('21:00:00', $hours['close']);
    }

    // ─── Temporadas (#207) ─────────────────────────────────────────────────────

    private function summerSeason(array $overrides = []): Season
    {
        return Season::create(array_merge([
            'name' => 'Verano',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-31',
            'open_time' => '11:00:00',
            'close_time' => '22:00:00',
            'is_active' => true,
        ], $overrides));
    }

    public function test_active_season_overrides_the_weekly_window(): void
    {
        OpeningHour::create(['weekday' => $this->weekday, 'open_time' => '16:00:00', 'close_time' => '21:00:00']);
        $this->summerSeason(); // cubre el 2026-06-10

        $hours = (new OperatingSchedule)->effectiveFor($this->date);

        $this->assertTrue($hours['is_open']);
        $this->assertSame('11:00:00', $hours['open']);
        $this->assertSame('22:00:00', $hours['close']);
    }

    public function test_active_season_opens_a_normally_closed_weekday(): void
    {
        OpeningHour::create(['weekday' => $this->weekday, 'is_closed' => true]);
        $this->summerSeason();

        $hours = (new OperatingSchedule)->effectiveFor($this->date);

        $this->assertTrue($hours['is_open']); // la temporada abre todos los días de su rango
        $this->assertSame('11:00:00', $hours['open']);
    }

    public function test_special_date_overrides_an_active_season(): void
    {
        $this->summerSeason();
        SpecialDate::create(['date' => $this->date->toDateString(), 'is_closed' => true]);

        // La fecha especial (cierre puntual) manda sobre la temporada.
        $this->assertFalse((new OperatingSchedule)->isOpenOn($this->date));
    }

    public function test_inactive_season_is_ignored(): void
    {
        OpeningHour::create(['weekday' => $this->weekday, 'open_time' => '16:00:00', 'close_time' => '21:00:00']);
        $this->summerSeason(['is_active' => false]);

        $hours = (new OperatingSchedule)->effectiveFor($this->date);

        $this->assertSame('16:00:00', $hours['open']); // cae al horario semanal
    }

    public function test_date_outside_season_range_uses_weekly(): void
    {
        OpeningHour::create(['weekday' => $this->weekday, 'open_time' => '16:00:00', 'close_time' => '21:00:00']);
        $this->summerSeason(['start_date' => '2026-07-01', 'end_date' => '2026-08-31']); // no cubre el 10-jun

        $hours = (new OperatingSchedule)->effectiveFor($this->date);

        $this->assertSame('16:00:00', $hours['open']);
    }

    public function test_overlapping_seasons_earliest_start_wins(): void
    {
        $this->summerSeason(['name' => 'A', 'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'open_time' => '11:00:00', 'close_time' => '22:00:00']);
        $this->summerSeason(['name' => 'B', 'start_date' => '2026-07-01', 'end_date' => '2026-09-30', 'open_time' => '09:00:00', 'close_time' => '21:00:00']);

        // El 15-jul cae en ambas; gana la de inicio más temprano (A) — comportamiento determinista.
        $hours = (new OperatingSchedule)->effectiveFor(Carbon::parse('2026-07-15'));
        $this->assertSame('11:00:00', $hours['open']);
    }

    public function test_single_day_season_applies_only_that_day(): void
    {
        foreach (range(0, 6) as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '16:00:00', 'close_time' => '21:00:00']);
        }
        $this->summerSeason(['start_date' => '2026-06-10', 'end_date' => '2026-06-10']); // un solo día

        $this->assertSame('11:00:00', (new OperatingSchedule)->effectiveFor(Carbon::parse('2026-06-10'))['open']); // ese día
        $this->assertSame('16:00:00', (new OperatingSchedule)->effectiveFor(Carbon::parse('2026-06-11'))['open']); // día siguiente → semanal
    }
}
