<?php

namespace Tests\Feature\Surveys;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyParticipation;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use App\Domain\Platform\Services\Surveys\SurveySeals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **La encuesta de la puerta, PREGUNTA A PREGUNTA** (`docs/specs/puerta-nueva.md` §4.4, la P1b; `#817`·3): el primer toque
 * escribe la participación y la respuesta —sellada, anónima, con la FRANJA y no la hora— con lo contestado hasta ahí
 * (`startInPerson()`), y los siguientes la completan (`addInPerson()`) solo si es SU fila: interna, de esta encuesta, de hoy,
 * de este empleado y no declinada. Una pregunta contestada no se pisa (`#754`, `encuestas.md` §4.7).
 */
class SurveyInPersonStepsTest extends TestCase
{
    use RefreshDatabase;

    private Survey $survey;

    private SurveyResponses $responses;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-25 11:00:00', 'Europe/Madrid'));
        $this->survey = Survey::create([
            'key' => 'visita', 'name' => ['es' => 'Tu visita'], 'kind' => Survey::KIND_INTERNAL, 'active' => true,
            'questions' => [
                ['key' => 'nota', 'type' => 'scale', 'label' => ['es' => 'Nota']],
                ['key' => 'volveria', 'type' => 'yesno', 'label' => ['es' => '¿Volverías?']],
                ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Algo más']],
            ],
        ]);
        $this->responses = app(SurveyResponses::class);
    }

    public function test_the_first_tap_writes_the_participation_and_a_sealed_partial_response_and_returns_its_key(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create();

        $key = $this->responses->startInPerson($this->survey, $customer->id, ['nota' => 4], $staff->id);

        $this->assertNotNull($key);
        $this->assertSame((int) $customer->id, (int) SurveyParticipation::query()->sole()->user_id);
        $row = SurveyResponse::query()->sole();
        $this->assertSame($key, (string) $row->getKey());
        $this->assertSame(['nota' => 4], $row->answers);
        $this->assertSame(SurveyResponse::BAND_MORNING, $row->band, 'la franja, nunca la hora');
        $this->assertFalse($row->declined);
        $this->assertCount(1, app(SurveySeals::class)->sealedFor($customer->id), 'contestada = sellada, como siempre');

        $this->assertNull($this->responses->startInPerson($this->survey, $customer->id, ['nota' => 1], $staff->id), 'una por cliente y encuesta');
        $this->assertSame(1, SurveyResponse::query()->count());
    }

    public function test_the_next_taps_complete_the_same_row_and_never_overwrite_an_answer(): void
    {
        $staff = User::factory()->create();
        $key = (string) $this->responses->startInPerson($this->survey, User::factory()->create()->id, ['nota' => 4], $staff->id);
        $antes = DB::table('survey_responses')->where('id', $key)->first();

        $this->assertTrue($this->responses->addInPerson($this->survey, $key, ['volveria' => true], $staff->id));
        $this->assertFalse($this->responses->addInPerson($this->survey, $key, ['nota' => 1], $staff->id), 'lo contestado no se pisa');
        $this->assertTrue($this->responses->addInPerson($this->survey, $key, ['nota' => 1, 'comentario' => 'Genial'], $staff->id), 'lo nuevo entra; lo ya contestado, no');

        $row = SurveyResponse::query()->sole();
        $this->assertSame(['nota' => 4, 'volveria' => true, 'comentario' => 'Genial'], $row->answers);
        $despues = DB::table('survey_responses')->where('id', $key)->first();
        foreach (['survey_id', 'channel', 'answered_on', 'band', 'asked_by', 'first_visit', 'visit_kind', 'declined', 'seal', 'returned', 'returned_after_days'] as $col) {
            $this->assertSame($antes->{$col}, $despues->{$col}, "completar no toca «{$col}»");
        }
    }

    public function test_only_its_own_row_can_be_completed(): void
    {
        $staff = User::factory()->create();
        $otro = User::factory()->create();
        $key = (string) $this->responses->startInPerson($this->survey, User::factory()->create()->id, ['nota' => 4], $staff->id);
        $otra = Survey::create(['key' => 'otra', 'name' => ['es' => 'Otra'], 'kind' => Survey::KIND_INTERNAL, 'active' => false, 'questions' => [['key' => 'nota', 'type' => 'scale', 'label' => ['es' => 'Nota']]]]);

        $this->assertFalse($this->responses->addInPerson($otra, $key, ['volveria' => true], $staff->id), 'de OTRA encuesta');
        $this->assertFalse($this->responses->addInPerson($this->survey, $key, ['volveria' => true], $otro->id), 'de OTRO empleado');
        $this->assertFalse($this->responses->addInPerson($this->survey, 'no-existe', ['volveria' => true], $staff->id));

        $this->travelTo(Carbon::parse('2026-09-26 11:00:00', 'Europe/Madrid'));
        $this->assertFalse($this->responses->addInPerson($this->survey, $key, ['volveria' => true], $staff->id), 'de OTRO día');

        $this->assertSame(['nota' => 4], SurveyResponse::query()->sole()->answers);
    }

    public function test_a_declined_or_external_row_is_never_completed(): void
    {
        $staff = User::factory()->create();
        $this->assertTrue($this->responses->declineInPerson($this->survey, User::factory()->create()->id, $staff->id));
        $declinada = (string) SurveyResponse::query()->sole()->getKey();

        $this->assertFalse($this->responses->addInPerson($this->survey, $declinada, ['nota' => 5], $staff->id), 'una declinada no se rellena');
        $this->assertNull(SurveyResponse::query()->sole()->answers);

        DB::table('survey_responses')->where('id', $declinada)->update(['declined' => false, 'channel' => SurveyResponse::CHANNEL_EXTERNAL]);
        $this->assertFalse($this->responses->addInPerson($this->survey, $declinada, ['nota' => 5], $staff->id), 'ni una del correo');
    }
}
