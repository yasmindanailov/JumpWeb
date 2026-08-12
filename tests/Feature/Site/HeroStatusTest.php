<?php

namespace Tests\Feature\Site;

use App\Domain\Content\Services\HeroStatus;
use App\Domain\Platform\Models\Setting;
use App\Models\OpeningHour;
use App\Models\SpecialDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Chip de estado de apertura del hero (`App\Domain\Content\Services\HeroStatus`): data-driven sobre el horario real
 * del parque (`ParkSchedule`). Zona horaria forzada a UTC en tests → `now(tz)` == el instante fijado.
 */
class HeroStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'UTC', 'group' => 'general']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function heroStatus(): ?array
    {
        return app(HeroStatus::class)->current();
    }

    private function openToday(string $open = '16:00:00', string $close = '22:00:00'): void
    {
        OpeningHour::create([
            'weekday' => now('UTC')->dayOfWeek, 'open_time' => $open, 'close_time' => $close, 'is_closed' => false,
        ]);
    }

    public function test_null_when_no_schedule_configured(): void
    {
        Carbon::setTestNow('2026-06-15 18:00:00');
        $this->assertNull($this->heroStatus(), 'sin horario no se anuncia estado (evita un «abierto» falso)');
    }

    public function test_open_now_within_window(): void
    {
        Carbon::setTestNow('2026-06-15 18:00:00'); // dentro de 16–22
        $this->openToday();

        $s = $this->heroStatus();
        $this->assertNotNull($s);
        $this->assertTrue($s['open_now']);
        $this->assertSame((string) __('landing.hero.status_open'), $s['status']);
        $this->assertSame((string) __('landing.info.weekdays.'.now('UTC')->dayOfWeek), $s['day']);
    }

    public function test_opens_later_today_in_hours(): void
    {
        Carbon::setTestNow('2026-06-15 14:00:00'); // 2 h antes de abrir
        $this->openToday();

        $s = $this->heroStatus();
        $this->assertFalse($s['open_now']);
        $this->assertSame((string) __('landing.hero.status_opens_in', ['duration' => '2 h']), $s['status']);
    }

    public function test_opens_in_minutes_when_under_an_hour(): void
    {
        Carbon::setTestNow('2026-06-15 15:30:00'); // 30 min antes
        $this->openToday();

        $this->assertSame(
            (string) __('landing.hero.status_opens_in', ['duration' => '30 min']),
            $this->heroStatus()['status']
        );
    }

    public function test_opens_tomorrow_when_closed_for_the_day(): void
    {
        Carbon::setTestNow('2026-06-15 23:00:00'); // ya cerró hoy (cierra a las 22)
        $this->openToday();
        OpeningHour::create([
            'weekday' => now('UTC')->copy()->addDay()->dayOfWeek,
            'open_time' => '11:00:00', 'close_time' => '22:00:00', 'is_closed' => false,
        ]);

        $s = $this->heroStatus();
        $this->assertFalse($s['open_now']);
        $this->assertSame((string) __('landing.hero.status_opens_tomorrow', ['time' => '11:00']), $s['status']);
    }

    public function test_special_date_closed_today_overrides_weekly(): void
    {
        Carbon::setTestNow('2026-06-15 18:00:00'); // el horario semanal estaría abierto…
        $this->openToday();
        OpeningHour::create([
            'weekday' => now('UTC')->copy()->addDay()->dayOfWeek,
            'open_time' => '11:00:00', 'close_time' => '22:00:00', 'is_closed' => false,
        ]);
        // …pero una fecha especial CIERRA hoy → no «abierto ahora»; cae a mañana.
        SpecialDate::create(['date' => now('UTC')->toDateString(), 'is_closed' => true]);

        $s = $this->heroStatus();
        $this->assertFalse($s['open_now']);
        $this->assertSame((string) __('landing.hero.status_opens_tomorrow', ['time' => '11:00']), $s['status']);
    }

    public function test_chip_renders_on_home_when_open(): void
    {
        Carbon::setTestNow('2026-06-15 18:00:00');
        $this->openToday();

        $this->get('/')
            ->assertOk()
            ->assertSee((string) __('landing.hero.status_open'))
            ->assertSee('href="#info"', false); // el chip enlaza a horario + cómo llegar
    }
}
