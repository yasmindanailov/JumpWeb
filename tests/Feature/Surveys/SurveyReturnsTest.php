<?php

namespace Tests\Feature\Surveys;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use App\Domain\Platform\Services\Surveys\SurveySeals;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **¿Volvió quien puntuó?** (`docs/specs/encuestas.md` §4.7, T5; `[DECIDIDO owner]` `DECISIONES #754`): cada noche,
 * `surveys:resolve-returns` mira cada respuesta aún sellada contra las visitas de su cliente en los 90 días siguientes,
 * contando solo días YA VIVIDOS; al saberlo —volvió, o pasó el plazo— anota el resultado y BORRA el sello en la misma
 * escritura. «No preguntar» no se sella. Es idempotente y está en el planificador.
 */
class SurveyReturnsTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Europe/Madrid';

    private Survey $survey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->survey = Survey::create([
            'key' => 'visita', 'name' => ['es' => 'Tu visita'], 'kind' => Survey::KIND_INTERNAL, 'active' => true,
            'questions' => [['key' => 'nota', 'type' => 'scale', 'label' => ['es' => 'Nota']]],
        ]);
    }

    /** Contesta en la puerta ese día (a las 11:00 del parque) y devuelve el cliente. */
    private function answeredOn(string $day, int $score = 2): User
    {
        $user = User::factory()->create();
        $this->travelTo(Carbon::parse($day.' 11:00:00', self::TZ));
        app(GateVisits::class)->register($user, null, Carbon::parse($day));
        $this->assertTrue(app(SurveyResponses::class)->answerInPerson($this->survey, $user->id, ['nota' => $score], null));

        return $user;
    }

    private function resolveOn(string $day): string
    {
        $this->travelTo(Carbon::parse($day.' 04:20:00', self::TZ));
        $this->assertSame(0, Artisan::call('surveys:resolve-returns'));

        return Artisan::output();
    }

    public function test_a_return_within_ninety_days_is_noted_with_its_days_and_the_seal_goes(): void
    {
        $ana = $this->answeredOn('2026-09-01', 1);
        app(GateVisits::class)->register($ana, null, Carbon::parse('2026-09-15'));

        $output = $this->resolveOn('2026-09-20');

        $row = DB::table('survey_responses')->sole();
        $this->assertSame(1, (int) $row->returned);
        $this->assertSame(14, (int) $row->returned_after_days);
        $this->assertNull($row->seal, 'al saberlo, el sello se borra en la MISMA escritura');
        $this->assertSame([], app(SurveySeals::class)->sealedFor($ana->id), 'desde ahí la respuesta no es de nadie');
        $this->assertStringContainsString('Volvieron 1', $output);
    }

    public function test_without_a_return_it_stays_sealed_until_the_ninety_days_pass_and_then_it_is_a_no(): void
    {
        $this->answeredOn('2026-09-01');

        $this->resolveOn('2026-11-30');
        $row = DB::table('survey_responses')->sole();
        $this->assertNull($row->returned, 'a los 89 días vividos aún puede volver');
        $this->assertNotNull($row->seal);

        // El 30-11 es el día 90: se ha VIVIDO el día 1-12 a la noche siguiente.
        $this->resolveOn('2026-12-01');
        $row = DB::table('survey_responses')->sole();
        $this->assertSame(0, (int) $row->returned);
        $this->assertNull($row->returned_after_days);
        $this->assertNull($row->seal, 'a los 90 días se separa del todo');
    }

    public function test_only_days_already_lived_count_so_today_is_not_a_return_yet(): void
    {
        $ana = $this->answeredOn('2026-09-01');
        app(GateVisits::class)->register($ana, null, Carbon::parse('2026-09-10'));

        $this->resolveOn('2026-09-10');
        $this->assertNull(DB::table('survey_responses')->value('returned'), 'la visita de HOY aún no ha pasado');

        $this->resolveOn('2026-09-11');
        $this->assertSame(1, (int) DB::table('survey_responses')->value('returned'));
    }

    public function test_declining_is_never_sealed_and_the_pass_is_idempotent(): void
    {
        $user = User::factory()->create();
        $this->travelTo(Carbon::parse('2026-09-01 11:00:00', self::TZ));
        app(SurveyResponses::class)->declineInPerson($this->survey, $user->id, null);
        $ana = $this->answeredOn('2026-09-01');
        app(GateVisits::class)->register($ana, null, Carbon::parse('2026-09-05'));

        $this->assertSame(1, DB::table('survey_responses')->whereNotNull('seal')->count(), '«no preguntar» no se sella');

        $this->resolveOn('2026-09-06');
        $first = DB::table('survey_responses')->orderBy('id')->get()->toArray();
        $this->resolveOn('2026-09-07');
        $this->assertEquals($first, DB::table('survey_responses')->orderBy('id')->get()->toArray(), 'la segunda pasada no toca nada');
        $this->assertSame(1, DB::table('survey_responses')->where('returned', true)->count());
        $this->assertNull(DB::table('survey_responses')->where('declined', true)->value('returned'));
    }

    public function test_a_seal_this_application_cannot_read_still_expires(): void
    {
        $this->answeredOn('2026-09-01');
        DB::table('survey_responses')->update(['seal' => 'no-es-un-sello']);

        $this->resolveOn('2026-09-20');
        $this->assertSame('no-es-un-sello', DB::table('survey_responses')->value('seal'), 'ilegible: ni vuelve ni caduca antes de tiempo');

        $this->resolveOn('2026-12-01');
        $this->assertNull(DB::table('survey_responses')->value('seal'), 'y a los 90 días se borra igual');
    }

    public function test_it_runs_every_night_from_the_scheduler(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(static fn ($event): bool => str_contains((string) $event->command, 'surveys:resolve-returns'));

        $this->assertCount(1, $events);
        $this->assertSame('20 4 * * *', $events->first()->expression);
    }
}
