<?php

namespace Tests\Feature\Site;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Content\Services\HeroStatus;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Chip de estado de apertura del hero (`App\Domain\Content\Services\HeroStatus`): data-driven sobre el horario real
 * del parque (`OperatingSchedule`). Zona horaria forzada a UTC en tests → `now(tz)` == el instante fijado.
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

    /**
     * **El estado de apertura se ANUNCIA en la web, y enlaza a horario + cómo llegar.**
     *
     * ⚠️⚠️ **CORRECCIÓN: el sujeto se mudó dos veces el 2026-08-28, la regla no.** Vivía en el
     * CHIP del hero; `#226` vació el hero por decisión del owner y el chip se fue con él, y `#228`
     * lo colocó donde el mockup lo pone: el **bloque de datos del menú**, junto al teléfono y la
     * ubicación. Que el visitante pueda saber si está abierto —y llegar al horario desde ahí— no
     * ha cambiado nunca.
     *
     * ⚠️ **Y el `href` deja de ser el ancla desnuda `#info`, a propósito.** El chip vivía solo en
     * la portada, así que un ancla bastaba. El menú se pinta en las DOCE vistas, y desde
     * `/precios` un `#info` a secas no lleva a ninguna parte: tiene que ser la URL completa.
     * Aseverar el ancla desnuda ataba el caso a que el estado viviera solo en la home.
     *
     * ⚠️ **Este caso destapó un fallo de verdad y por eso se conserva** (`#230`): al mudar el
     * estado al menú, el dato seguía viniendo del `HomeController` — o sea que el menú lo pintaba
     * vacío en once vistas, **y también en la home**, porque el componente no lo recibía. El
     * estado había desaparecido de la web entera sin que nada fallara, salvo esto.
     */
    public function test_chip_renders_on_home_when_open(): void
    {
        Carbon::setTestNow('2026-06-15 18:00:00');
        $this->openToday();

        $this->get('/')
            ->assertOk()
            ->assertSee((string) __('landing.hero.status_open'))
            ->assertSee('href="'.url('/#info').'"', false);
    }

    /**
     * **Y se anuncia en TODAS las vistas, no solo en la portada.**
     *
     * Es la mitad que el caso de arriba no puede cubrir: el menú vive en las doce, y el dato
     * llegaba de un controlador que solo sirve una. Sin esto, alguien podría devolver
     * `heroStatus` al `HomeController` y el caso de arriba seguiría verde.
     */
    public function test_the_status_travels_to_every_view_because_the_menu_does(): void
    {
        Carbon::setTestNow('2026-06-15 18:00:00');
        $this->openToday();

        $this->get(route('precios'))
            ->assertOk()
            ->assertSee((string) __('landing.hero.status_open'));
    }
}
