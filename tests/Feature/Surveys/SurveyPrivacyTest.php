<?php

namespace Tests\Feature\Surveys;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * **Las respuestas y la persona** (`docs/specs/encuestas.md` §4.5, T1; `RGPD-01`): al anonimizar, las respuestas
 * dejan de ser suyas y el texto libre se borra, los agregados sobreviven; a los 24 meses se podan; y la baja de
 * encuestas vuelve a neutro con la cuenta.
 */
class SurveyPrivacyTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_anonymizing_unlinks_the_responses_and_erases_the_free_text_but_keeps_the_scores(): void
    {
        $survey = $this->survey();
        $customer = User::factory()->create(['surveys_opt_out' => true]);
        $other = User::factory()->create();
        SurveyResponse::create(['survey_id' => $survey->id, 'user_id' => $customer->id, 'channel' => 'internal', 'answered_at' => now(), 'answers' => ['ambiente' => 2, 'comentario' => 'Me llamo Ana y el bar estaba cerrado']]);
        SurveyResponse::create(['survey_id' => $survey->id, 'user_id' => $other->id, 'channel' => 'internal', 'answered_at' => now(), 'answers' => ['ambiente' => 5, 'comentario' => 'Genial']]);

        $customer->anonymize();

        $mine = SurveyResponse::query()->whereNull('user_id')->sole();
        $this->assertSame(['ambiente' => 2], $mine->answers, 'la escala sobrevive sin persona; el texto libre no');
        $this->assertSame('Genial', SurveyResponse::query()->where('user_id', $other->id)->sole()->answers['comentario'], 'la de otro cliente no se toca');
        $this->assertFalse($customer->fresh()->surveys_opt_out, 'la baja vuelve a neutro con la cuenta');
        $this->assertSame(2, SurveyResponse::count(), 'los agregados siguen contando');
    }

    public function test_responses_older_than_two_years_are_pruned_answered_or_not(): void
    {
        $survey = $this->survey();
        $old = SurveyResponse::create(['survey_id' => $survey->id, 'channel' => 'external', 'sent_at' => now()->subMonths(30), 'answered_at' => now()->subMonths(30), 'answers' => ['ambiente' => 4]]);
        $oldUnanswered = SurveyResponse::create(['survey_id' => $survey->id, 'channel' => 'external', 'token' => 'abcdefghijabcdefghijabcdefghijabcdefghij', 'sent_at' => now()->subMonths(30)]);
        $oldUnanswered->forceFill(['created_at' => now()->subMonths(30)])->saveQuietly();
        $recent = SurveyResponse::create(['survey_id' => $survey->id, 'channel' => 'internal', 'answered_at' => now()->subMonths(6), 'answers' => ['ambiente' => 3]]);

        Artisan::call('model:prune', ['--model' => [SurveyResponse::class]]);

        $this->assertDatabaseMissing('survey_responses', ['id' => $old->id]);
        $this->assertDatabaseMissing('survey_responses', ['id' => $oldUnanswered->id]);
        $this->assertDatabaseHas('survey_responses', ['id' => $recent->id]);
    }

    public function test_one_response_per_customer_and_survey_is_a_database_rule(): void
    {
        $survey = $this->survey();
        $customer = User::factory()->create();
        SurveyResponse::create(['survey_id' => $survey->id, 'user_id' => $customer->id, 'channel' => 'internal', 'answered_at' => now(), 'answers' => ['ambiente' => 4]]);

        $this->expectException(UniqueConstraintViolationException::class);
        SurveyResponse::create(['survey_id' => $survey->id, 'user_id' => $customer->id, 'channel' => 'external', 'sent_at' => now()]);
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
}
