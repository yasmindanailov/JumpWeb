<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\Season;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Content\Services\ScheduleDisplay;
use Carbon\Carbon;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Fase 7.7 (#207) — La landing muestra el horario data-driven (misma fuente que las
 * reservas): horario semanal agrupado, temporadas vigentes y próximas fechas especiales.
 */
class ScheduleDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    public function test_weekly_rows_group_consecutive_days_with_same_hours(): void
    {
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '16:00:00', 'close_time' => '22:00:00']);
        }
        foreach ([6, 0] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '11:00:00', 'close_time' => '22:00:00']);
        }

        $rows = app(ScheduleDisplay::class)->weeklyRows();

        $this->assertCount(2, $rows);
        $this->assertSame('Lunes a viernes', $rows[0]['label']);
        $this->assertStringContainsString('16:00', $rows[0]['time']);
        $this->assertStringContainsString('22:00', $rows[0]['time']);
        $this->assertSame('Sábado a domingo', $rows[1]['label']);
        $this->assertStringContainsString('11:00', $rows[1]['time']);
    }

    public function test_weekly_rows_show_closed_days(): void
    {
        OpeningHour::create(['weekday' => 1, 'is_closed' => true]); // lunes cerrado
        foreach ([2, 3, 4, 5, 6, 0] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '10:00:00', 'close_time' => '21:00:00']);
        }

        $rows = app(ScheduleDisplay::class)->weeklyRows();

        $this->assertSame('Lunes', $rows[0]['label']);
        $this->assertSame('Cerrado', $rows[0]['time']);
    }

    public function test_seasons_lists_active_and_upcoming(): void
    {
        Season::create(['name' => 'Verano', 'start_date' => '2026-07-01', 'end_date' => '2099-08-31', 'open_time' => '11:00:00', 'close_time' => '22:00:00', 'is_active' => true]);
        Season::create(['name' => 'Pasada', 'start_date' => '2020-01-01', 'end_date' => '2020-02-01', 'open_time' => '10:00:00', 'close_time' => '20:00:00', 'is_active' => true]);
        Season::create(['name' => 'Inactiva', 'start_date' => '2026-07-01', 'end_date' => '2099-08-31', 'open_time' => '10:00:00', 'close_time' => '20:00:00', 'is_active' => false]);

        $seasons = app(ScheduleDisplay::class)->seasons();

        $this->assertCount(1, $seasons);
        $this->assertSame('Verano', $seasons[0]['name']);
        $this->assertStringContainsString('11:00', $seasons[0]['time']);
    }

    public function test_upcoming_special_dates_are_future_and_limited(): void
    {
        SpecialDate::create(['date' => Carbon::yesterday()->toDateString(), 'is_closed' => true]); // pasada
        SpecialDate::create(['date' => Carbon::tomorrow()->toDateString(), 'is_closed' => true]);
        SpecialDate::create(['date' => Carbon::tomorrow()->addDay()->toDateString(), 'is_closed' => false, 'open_time' => '12:00:00', 'close_time' => '16:00:00']);

        $dates = app(ScheduleDisplay::class)->upcomingSpecialDates(4);

        $this->assertCount(2, $dates); // solo las futuras
        $this->assertTrue($dates[0]['is_closed']);
        $this->assertSame('Cerrado', $dates[0]['detail']);
        $this->assertStringContainsString('12:00', $dates[1]['detail']);
    }

    public function test_weekly_rows_mark_today_in_the_correct_group(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 12:00:00')); // miércoles (weekday 3)

        foreach ([1, 2, 3] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '16:00:00', 'close_time' => '22:00:00']);
        }
        OpeningHour::create(['weekday' => 4, 'open_time' => '11:00:00', 'close_time' => '22:00:00']);
        foreach ([5, 6, 0] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '16:00:00', 'close_time' => '22:00:00']);
        }

        $rows = app(ScheduleDisplay::class)->weeklyRows();

        $this->assertCount(3, $rows); // patrón fragmentado: 3 grupos
        $this->assertSame('Lunes a miércoles', $rows[0]['label']);
        $this->assertTrue($rows[0]['is_today']); // el miércoles cae en el primer grupo
        $this->assertFalse($rows[1]['is_today']);
        $this->assertFalse($rows[2]['is_today']);
        $this->assertSame(1, count(array_filter($rows, fn (array $r): bool => $r['is_today']))); // exactamente uno

        $this->travelBack();
    }

    public function test_today_is_marked_even_when_closed(): void
    {
        $this->travelTo(Carbon::parse('2026-06-10 12:00:00')); // miércoles
        OpeningHour::create(['weekday' => 3, 'is_closed' => true]);
        foreach ([1, 2, 4, 5, 6, 0] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '10:00:00', 'close_time' => '21:00:00']);
        }

        $todayRow = collect(app(ScheduleDisplay::class)->weeklyRows())->firstWhere('is_today', true);

        $this->assertNotNull($todayRow);
        $this->assertSame('Miércoles', $todayRow['label']);
        $this->assertSame('Cerrado', $todayRow['time']);

        $this->travelBack();
    }

    public function test_landing_renders_data_driven_hours_and_special_dates(): void
    {
        $this->seed(LandingContentSeeder::class);
        Cache::flush();

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '16:00:00', 'close_time' => '22:00:00']);
        }
        foreach ([6, 0] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '11:00:00', 'close_time' => '22:00:00']);
        }
        SpecialDate::create(['date' => Carbon::tomorrow()->toDateString(), 'is_closed' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        // ⚠️ Acotado a `<section id="info">`: «Lunes a viernes» sale también en otras vistas y una
        // aserción sobre el documento entero pasaría en verde con la sección borrada (`#295`).
        preg_match('#<section id="info".*?</section>#s', $html, $m);
        $seccion = $m[0] ?? '';
        $this->assertNotSame('', $seccion, 'La portada ya no trae `<section id="info">`.');

        $this->assertStringContainsString('Lunes a viernes', $seccion);   // horario data-driven agrupado

        // ⚠️⚠️ **Antes esto aseveraba el titular «Fechas especiales», y ese `h4` ya NO existe**
        // (`#307`, §3.octies: la excepción va PEGADA al estado, no bajo un encabezado propio). La
        // guarda se re-apunta al DATO en vez de al rótulo, y al hacerlo queda MÁS FUERTE: el
        // titular se pintaba aunque la lista viniera vacía, así que aquella aserción no
        // demostraba que la fecha especial llegara a la página. Ésta sí.
        $especial = collect(app(ScheduleDisplay::class)->upcomingSpecialDates())->first();
        $this->assertNotNull($especial, 'El caso se quedó sin fecha especial que comprobar.');
        $this->assertStringContainsString($especial['date'], $seccion);
        $this->assertStringContainsString($especial['detail'], $seccion);
    }

    public function test_special_date_without_own_hours_shows_the_weekly_hours(): void
    {
        // #270-bis punto 1: una fecha especial SIN ventana propia (p. ej. solo tarifa especial) hereda
        // el horario semanal de ese día → la landing muestra ESE horario, no «Abierto» ni el nombre.
        $this->travelTo(Carbon::parse('2026-06-10 12:00:00'));            // miércoles (weekday 3)
        OpeningHour::create(['weekday' => 3, 'open_time' => '16:00:00', 'close_time' => '22:00:00']);
        SpecialDate::create([                                            // 2026-06-17 = miércoles futuro
            'date' => '2026-06-17', 'is_closed' => false, 'note' => ['es' => 'Tarifa especial'],
        ]);

        $special = collect(app(ScheduleDisplay::class)->upcomingSpecialDates())->first();

        $this->assertNotNull($special);
        $this->assertFalse($special['is_closed']);
        $this->assertStringContainsString('16:00', $special['detail']); // horario semanal heredado
        $this->assertStringContainsString('22:00', $special['detail']);

        $this->travelBack();
    }

    public function test_active_season_takes_over_today_from_the_weekly_schedule(): void
    {
        // #270-bis punto 2: si HOY cae dentro de una temporada, el horario semanal NO se marca como
        // «actual»; se destaca la TEMPORADA vigente (es la que rige según OperatingSchedule).
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));           // dentro del rango de la temporada
        foreach ([1, 2, 3, 4, 5, 6, 0] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '16:00:00', 'close_time' => '22:00:00']);
        }
        Season::create(['name' => 'Verano', 'start_date' => '2026-07-01', 'end_date' => '2026-08-31', 'open_time' => '11:00:00', 'close_time' => '23:00:00', 'is_active' => true]);

        $schedule = app(ScheduleDisplay::class);
        $rows = $schedule->weeklyRows();
        $seasons = $schedule->seasons();

        // Ninguna fila semanal marcada como hoy (la gobierna la temporada).
        $this->assertSame(0, count(array_filter($rows, fn (array $r): bool => $r['is_today'])));
        // La temporada vigente sí se marca como actual.
        $this->assertCount(1, $seasons);
        $this->assertTrue($seasons[0]['is_current']);

        $this->travelBack();
    }

    public function test_weekly_is_marked_today_when_no_season_or_special_overrides(): void
    {
        // Contraprueba: sin temporada ni fecha especial hoy, el horario semanal SÍ marca el día actual.
        $this->travelTo(Carbon::parse('2026-06-10 12:00:00'));           // miércoles
        foreach ([1, 2, 3, 4, 5, 6, 0] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '16:00:00', 'close_time' => '22:00:00']);
        }

        $rows = app(ScheduleDisplay::class)->weeklyRows();

        $this->assertSame(1, count(array_filter($rows, fn (array $r): bool => $r['is_today'])));

        $this->travelBack();
    }
}
