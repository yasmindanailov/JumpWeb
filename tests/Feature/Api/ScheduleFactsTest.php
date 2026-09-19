<?php

namespace Tests\Feature\Api;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Content\Services\HeroStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **El HORARIO del menú de hechos** (F5 · T2, `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Dos rutas porque no se cachean igual: `/schedule` son los hechos del calendario y `/schedule/now` es el
 * estado en vivo. Lo que se vigila aquí es lo que una landing no puede comprobar por su cuenta: **la
 * convención del día de la semana**, que las tres capas se pisen en el orden correcto, y que el chip del
 * producto y la API **nunca se contradigan**.
 */
class ScheduleFactsTest extends TestCase
{
    use RefreshDatabase;

    private function abre(int $weekday, string $desde, string $hasta): void
    {
        OpeningHour::query()->updateOrCreate(
            ['weekday' => $weekday],
            // ⚠️ En la TABLA se llaman `open_time`/`close_time`; `opensAt`/`closesAt` es el nombre del
            // contrato de dominio. No son sinónimos por casualidad: el contrato es lo estable.
            ['is_closed' => false, 'open_time' => $desde, 'close_time' => $hasta],
        );
    }

    /**
     * **`weekday` es 0 = DOMINGO**, y esto se clava contra una fecha real, no contra la documentación.
     *
     * ⚠️⚠️ Es el campo que más daño hace en silencio: una landing que asuma la convención ISO (1 = lunes,
     * 7 = domingo) pinta la semana entera desplazada un día y **no falla nada** — el JSON es válido y las
     * horas existen. El error sale un domingo, en el peor sitio: la puerta.
     */
    public function test_the_weekday_convention_is_zero_for_sunday(): void
    {
        // 2026-09-20 es DOMINGO. Se elige una fecha y se comprueba, en vez de confiar en el número.
        $domingo = Carbon::parse('2026-09-20');
        $this->assertSame('Sunday', $domingo->format('l'), 'la fecha de referencia dejó de ser domingo');
        $this->assertSame(0, $domingo->dayOfWeek);

        $this->abre(0, '11:00:00', '21:30:00');
        $this->abre(1, '16:30:00', '21:30:00');

        $semana = collect($this->getJson('/api/v1/schedule')->assertOk()->json('weekly'))
            ->keyBy('weekday');

        $this->assertSame('11:00', $semana[0]['opens_at'], 'el día 0 no es el domingo que dice el contrato');
        $this->assertSame('16:30', $semana[1]['opens_at'], 'el día 1 no es el lunes');
    }

    public function test_it_serves_the_week_without_a_single_translated_word(): void
    {
        $this->abre(3, '16:30:00', '21:30:00');

        $dia = collect($this->getJson('/api/v1/schedule')->assertOk()->json('weekly'))
            ->firstWhere('weekday', 3);

        $this->assertSame(['weekday', 'closed', 'opens_at', 'closes_at'], array_keys($dia));
        $this->assertSame('16:30', $dia['opens_at'], 'las horas van en HH:MM, no en HH:MM:SS');
    }

    /**
     * **Una fecha especial es un hecho, y lo que la instalación no escribió no viaja.**
     */
    public function test_a_special_day_travels_with_what_it_has(): void
    {
        SpecialDate::query()->create([
            'date' => Carbon::today()->addDays(3)->toDateString(),
            'is_closed' => true,
            'note' => 'Fiesta Nacional',
        ]);

        $dia = $this->getJson('/api/v1/schedule')->assertOk()->json('special_days.0');

        $this->assertTrue($dia['closed']);
        $this->assertSame('Fiesta Nacional', $dia['note']);
        $this->assertArrayNotHasKey('opens_at', $dia, 'un día CERRADO no anuncia hora de apertura');
        $this->assertArrayNotHasKey('rate_label', $dia);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El estado en vivo
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_open_now_says_until_when_and_nothing_else(): void
    {
        $this->abre(Carbon::now()->dayOfWeek, '00:00:00', '23:59:00');

        $ahora = $this->getJson('/api/v1/schedule/now')->assertOk();

        $ahora->assertJsonPath('open_now', true);
        $ahora->assertJsonPath('closes_at', '23:59');
        $ahora->assertJsonMissingPath('opens_at');
    }

    /**
     * ⚠️ Cerrado, el dato útil es **cuándo abre**, y no se anuncia una hora de cierre: un cartel que diga
     * «cerramos a las 21:30» con el parque cerrado es exactamente el error que esta separación evita.
     */
    public function test_closed_says_when_it_opens_and_never_when_it_closes(): void
    {
        OpeningHour::query()->delete();
        $this->abre(Carbon::now()->addDay()->dayOfWeek, '16:30:00', '21:30:00');

        $ahora = $this->getJson('/api/v1/schedule/now')->assertOk();

        $ahora->assertJsonPath('open_now', false);
        $ahora->assertJsonMissingPath('closes_at');
        $this->assertStringContainsString(
            Carbon::now()->addDay()->toDateString(), (string) $ahora->json('opens_at'),
            'la próxima apertura no es mañana',
        );
    }

    /**
     * **Sin horario configurado no se afirma nada.** Ni abierto, ni una apertura inventada.
     */
    public function test_without_a_schedule_it_claims_nothing(): void
    {
        OpeningHour::query()->delete();

        $ahora = $this->getJson('/api/v1/schedule/now')->assertOk();

        $ahora->assertJsonPath('open_now', false);
        $ahora->assertJsonMissingPath('opens_at');
        $ahora->assertJsonMissingPath('closes_at');
    }

    /**
     * **EL CHIP DE LA LANDING Y LA API DICEN LO MISMO**, y por eso el hecho se calcula una sola vez
     * (`OpeningState`). Hasta la T2 cada uno lo resolvía por su cuenta; nada fallaba, y el día que se
     * hubieran separado —un festivo, un cierre a media tarde— no lo habría visto ningún test.
     */
    public function test_the_landing_chip_and_the_api_cannot_disagree(): void
    {
        $this->abre(Carbon::now()->dayOfWeek, '00:00:00', '23:59:00');

        $api = $this->getJson('/api/v1/schedule/now')->assertOk()->json();
        $chip = app(HeroStatus::class)->current();

        $this->assertSame($chip['open_now'], $api['open_now']);
        $this->assertSame($chip['closes_at'], $api['closes_at'] ?? null);
    }

    public function test_the_calendar_and_the_live_state_are_cached_differently(): void
    {
        $calendario = (string) $this->getJson('/api/v1/schedule')->assertOk()->headers->get('Cache-Control');
        $envivo = (string) $this->getJson('/api/v1/schedule/now')->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('max-age=300', $calendario);
        $this->assertStringContainsString('max-age=60', $envivo, 'el estado en vivo no puede cachearse como el calendario');
        $this->assertStringContainsString('public', $envivo);
    }
}
