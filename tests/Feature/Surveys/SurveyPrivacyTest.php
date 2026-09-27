<?php

namespace Tests\Feature\Surveys;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyParticipation;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use App\Domain\Platform\Services\Surveys\SurveySeals;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **Las encuestas y la persona** (`docs/specs/encuestas.md` §4.5 y §4.7, T1 y T5; `RGPD-01`; `#754`): al anonimizar,
 * la participación se suelta y el SELLO de esa persona se borra —y el de otra no—; a los 24 meses se poda; «una por
 * cliente y encuesta» es una regla de la BD; y el export lleva lo que sigue siendo suyo: sus participaciones y sus
 * respuestas AÚN selladas, nunca las de otro.
 */
class SurveyPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-25 11:00:00', 'Europe/Madrid'));
    }

    private function survey(): Survey
    {
        return Survey::create([
            'key' => 'visita', 'name' => ['es' => 'Tu visita'], 'kind' => Survey::KIND_INTERNAL, 'active' => true,
            'questions' => [
                ['key' => 'ambiente', 'type' => 'scale', 'label' => ['es' => 'Ambiente']],
                ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Cuéntanos']],
            ],
        ]);
    }

    /** @param  array<string, mixed>  $answers */
    private function answerInPerson(Survey $survey, User $customer, array $answers): void
    {
        $this->assertTrue(app(SurveyResponses::class)->answerInPerson($survey, $customer->id, $answers, null));
    }

    public function test_anonymizing_unlinks_the_participation_and_forgets_that_seal_and_no_other(): void
    {
        $survey = $this->survey();
        $customer = User::factory()->create(['surveys_opt_out' => true]);
        $other = User::factory()->create();
        $this->answerInPerson($survey, $customer, ['ambiente' => 2, 'comentario' => 'Mucha cola']);
        $this->answerInPerson($survey, $other, ['ambiente' => 5, 'comentario' => 'Genial']);
        $seals = app(SurveySeals::class);
        $this->assertCount(1, $seals->sealedFor($customer->id));

        $customer->anonymize();

        $this->assertNull(SurveyParticipation::query()->where('user_id', $customer->id)->first(), 'su participación ya no es suya');
        $this->assertSame(2, SurveyParticipation::query()->count(), 'la fila sigue contando como preguntada, sin nadie');
        $this->assertSame([], $seals->sealedFor($customer->id), 'su sello se borra en la misma transacción');
        $this->assertCount(1, $seals->sealedFor($other->id), 'CONTROL: el sello de otro cliente no se toca');
        $this->assertSame(2, SurveyResponse::query()->count(), 'las respuestas anónimas siguen contando');
        $this->assertSame(1, DB::table('survey_responses')->whereNull('seal')->count());
        $this->assertFalse($customer->fresh()->surveys_opt_out, 'la baja vuelve a neutro con la cuenta');
    }

    public function test_participations_and_responses_older_than_two_years_are_pruned(): void
    {
        $survey = $this->survey();
        $old = SurveyParticipation::create(['survey_id' => $survey->id, 'channel' => 'external', 'asked_on' => now()->subMonths(30)->toDateString(), 'sent_at' => now()->subMonths(30)]);
        $recent = SurveyParticipation::create(['survey_id' => $survey->id, 'channel' => 'internal', 'asked_on' => now()->subMonths(6)->toDateString()]);
        $oldAnswer = SurveyResponse::create(['survey_id' => $survey->id, 'channel' => 'internal', 'answered_on' => now()->subMonths(30)->toDateString(), 'band' => 'morning', 'answers' => ['ambiente' => 4]]);
        $recentAnswer = SurveyResponse::create(['survey_id' => $survey->id, 'channel' => 'internal', 'answered_on' => now()->subMonths(6)->toDateString(), 'band' => 'morning', 'answers' => ['ambiente' => 3]]);

        Artisan::call('model:prune', ['--model' => [SurveyResponse::class, SurveyParticipation::class]]);

        $this->assertDatabaseMissing('survey_participations', ['id' => $old->id]);
        $this->assertDatabaseHas('survey_participations', ['id' => $recent->id]);
        $this->assertDatabaseMissing('survey_responses', ['id' => $oldAnswer->id]);
        $this->assertDatabaseHas('survey_responses', ['id' => $recentAnswer->id]);
    }

    public function test_one_participation_per_customer_and_survey_is_a_database_rule(): void
    {
        $survey = $this->survey();
        $customer = User::factory()->create();
        SurveyParticipation::create(['survey_id' => $survey->id, 'user_id' => $customer->id, 'channel' => 'internal', 'asked_on' => '2026-09-25']);

        $this->expectException(UniqueConstraintViolationException::class);
        SurveyParticipation::create(['survey_id' => $survey->id, 'user_id' => $customer->id, 'channel' => 'external', 'asked_on' => '2026-09-26', 'sent_at' => now()]);
    }

    public function test_a_survey_is_running_only_while_active_and_inside_its_window_and_one_per_kind(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');
        $live = $this->survey();
        $this->assertTrue($live->isRunning());
        $this->assertSame($live->id, Survey::runningOfKind(Survey::KIND_INTERNAL)?->id);
        $this->assertNull(Survey::runningOfKind(Survey::KIND_EXTERNAL));
        $this->assertTrue(Survey::anotherRunning(Survey::KIND_INTERNAL, null));
        $this->assertFalse(Survey::anotherRunning(Survey::KIND_INTERNAL, $live->id), 'ella misma no cuenta como otra');

        $live->forceFill(['ends_at' => now()->subMinute()])->save();
        $this->assertFalse($live->fresh()->isRunning());
        $this->assertNull(Survey::runningOfKind(Survey::KIND_INTERNAL));
    }

    /**
     * El export del titular (art. 15, `RGPD-01`; contrato 1.46.0): a qué encuestas se le preguntó y lo que contestó
     * MIENTRAS sigue sellado —texto libre incluido: es suyo—. Nada de otro cliente, nada de quién preguntó, y nada ya
     * desellado: pasado el sello, nadie puede saber cuál fue la suya.
     */
    public function test_the_export_carries_the_participations_and_the_still_sealed_answers_and_only_theirs(): void
    {
        $survey = $this->survey();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $staff = User::factory()->create();
        $this->assertTrue(app(SurveyResponses::class)->answerInPerson($survey, $customer->id, ['ambiente' => 4, 'comentario' => 'Muy bien'], $staff->id));
        $this->answerInPerson($survey, $other, ['ambiente' => 1, 'comentario' => 'Secreto de otro']);

        $export = app(AccountPrivacy::class)->exportFor($customer)['surveys'];

        $this->assertSame([['survey' => 'visita', 'channel' => 'internal', 'asked_on' => '2026-09-25', 'sent_at' => null]], $export['participations']);
        $this->assertSame([['survey' => 'visita', 'channel' => 'internal', 'answered_on' => '2026-09-25', 'answers' => ['ambiente' => 4, 'comentario' => 'Muy bien']]], $export['sealed_responses']);
        $json = json_encode($export, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Secreto de otro', (string) $json);
        $this->assertStringNotContainsString((string) $staff->id, (string) json_encode($export['participations']), 'quién preguntó es un dato del empleado');

        // Desellada (volvió, o pasaron los 90 días), la respuesta deja de ser suya y deja de estar aquí.
        DB::table('survey_responses')->update(['seal' => null]);
        $this->assertSame([], app(AccountPrivacy::class)->exportFor($customer)['surveys']['sealed_responses']);
    }
}
