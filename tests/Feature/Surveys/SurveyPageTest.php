<?php

namespace Tests\Feature\Surveys;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * **La página de la encuesta del correo** (`docs/specs/encuestas.md` §4.3, T3): abre por su token, en el idioma
 * del cliente, sin cookie de medición y `no-store`; se contesta UNA vez; todo lo que no abre es el mismo 404; y
 * la baja es una página con un botón que funciona aunque la encuesta ya se contestara.
 */
class SurveyPageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Survey, 1: SurveyResponse, 2: User} */
    private function sent(array $surveyOverrides = [], string $locale = 'en'): array
    {
        $survey = Survey::create(array_merge([
            'key' => 'que-tal-ayer',
            'name' => ['es' => '¿Qué tal ayer?', 'en' => 'How was it?'],
            'kind' => Survey::KIND_EXTERNAL,
            'active' => true,
            'questions' => [
                ['key' => 'ambiente', 'type' => 'scale', 'required' => true, 'label' => ['es' => 'Ambiente', 'en' => 'Atmosphere']],
                ['key' => 'volveria', 'type' => 'yesno', 'label' => ['es' => '¿Volverías?', 'en' => 'Would you come back?']],
                ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Algo más', 'en' => 'Anything else']],
            ],
        ], $surveyOverrides));
        $user = User::factory()->create(['email_verified_at' => now(), 'locale' => $locale]);
        $response = app(SurveyResponses::class)->send($survey, $user->id, '2026-09-25', $locale);
        $this->assertNotNull($response);

        return [$survey, $response, $user];
    }

    public function test_the_page_opens_by_token_in_the_customers_language_without_the_measurement_cookie_and_no_store(): void
    {
        [, $response] = $this->sent();

        $page = $this->get(route('survey.show', ['token' => $response->token]));

        $page->assertOk()
            ->assertSee('How was it?')
            ->assertSee('Atmosphere')
            ->assertSee('Would you come back?')
            ->assertSee('data-survey-form', false)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $cookies = array_map(static fn (Cookie $cookie): string => $cookie->getName(), $page->headers->getCookies());
        $this->assertNotContains(Visitor::COOKIE, $cookies, 'la página del correo no acuña la cookie del visitante');
        $this->assertStringNotContainsString('data-cookie-', (string) $page->getContent(), 'sin banner de cookies');
    }

    public function test_an_unknown_answered_switched_off_or_orphan_token_is_the_same_404(): void
    {
        $this->get(route('survey.show', ['token' => Str::random(40)]))->assertNotFound();
        $this->get('/encuesta/no-tiene-forma-de-token')->assertNotFound();

        [, $answered] = $this->sent();
        $answered->forceFill(['answered_at' => now(), 'answers' => ['ambiente' => 5]])->save();
        $this->get(route('survey.show', ['token' => $answered->token]))->assertNotFound();
        $this->post(route('survey.answer', ['token' => $answered->token]), ['answers' => ['ambiente' => '4']])->assertNotFound();

        [$survey, $off] = $this->sent(['key' => 'apagada']);
        $survey->update(['active' => false]);
        $this->get(route('survey.show', ['token' => $off->token]))->assertNotFound();

        [, $orphan] = $this->sent(['key' => 'huerfana']);
        $orphan->forceFill(['user_id' => null])->save();
        $this->get(route('survey.show', ['token' => $orphan->token]))->assertNotFound();
    }

    public function test_answering_closes_the_token_once_types_the_answers_and_leaves_the_fact(): void
    {
        [, $response, $user] = $this->sent();
        $show = route('survey.show', ['token' => $response->token]);
        $answer = route('survey.answer', ['token' => $response->token]);

        $this->post($answer, ['answers' => ['ambiente' => '4', 'volveria' => '0', 'comentario' => '  Great, thanks.  ']])
            ->assertRedirect(route('survey.thanks', ['token' => $response->token]));

        $row = $response->fresh();
        $this->assertNotNull($row->answered_at);
        $this->assertSame('en', $row->locale);
        $this->assertSame(['ambiente' => 4, 'volveria' => false, 'comentario' => 'Great, thanks.'], $row->answers, 'tipadas por su pregunta, no como llegaron');

        $fact = AnalyticsEvent::query()->where('name', 'survey_answered')->sole();
        $this->assertSame(['survey' => 'que-tal-ayer', 'channel' => 'external'], $fact->props);
        $this->assertSame($user->id, (int) $fact->user_id);
        $this->assertStringNotContainsString('Great', json_encode(AnalyticsEvent::query()->get(), JSON_UNESCAPED_UNICODE), 'ninguna respuesta pisa el libro');

        $this->get(route('survey.thanks', ['token' => $response->token]))->assertOk()->assertSee('data-survey-thanks', false);

        // El token se cerró: ni vuelve a abrir, ni admite una segunda respuesta.
        $this->get($show)->assertNotFound();
        $this->post($answer, ['answers' => ['ambiente' => '1']])->assertNotFound();
        $this->assertSame(['ambiente' => 4, 'volveria' => false, 'comentario' => 'Great, thanks.'], $response->fresh()->answers);
        $this->assertSame(1, AnalyticsEvent::query()->where('name', 'survey_answered')->count());
    }

    public function test_a_missing_required_answer_goes_back_with_the_error_and_writes_nothing(): void
    {
        [, $response] = $this->sent();
        $show = route('survey.show', ['token' => $response->token]);

        $this->from($show)
            ->post(route('survey.answer', ['token' => $response->token]), ['answers' => ['comentario' => 'x', 'ambiente' => '9']])
            ->assertRedirect($show)
            ->assertSessionHasErrors(['answers.ambiente']);

        $this->assertNull($response->fresh()->answered_at);
        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'survey_answered')->count());
        // «Gracias» no abre para un token sin contestar.
        $this->get(route('survey.thanks', ['token' => $response->token]))->assertNotFound();
    }

    public function test_opting_out_takes_one_button_not_one_link_and_survives_an_answered_token(): void
    {
        [, $response, $user] = $this->sent();
        $ask = route('survey.optout', ['token' => $response->token]);

        // Abrir la página NO da de baja: los escáneres de enlaces de los gestores de correo abren los GET.
        $this->get($ask)->assertOk()->assertSee('data-survey-optout="ask"', false);
        $this->assertFalse((bool) $user->fresh()->surveys_opt_out);

        $this->post(route('survey.optout.confirm', ['token' => $response->token]))
            ->assertRedirect($ask)
            ->assertSessionHas('survey_optout', 'done');
        $this->assertTrue((bool) $user->fresh()->surveys_opt_out);
        $this->get($ask)->assertOk()->assertSee('data-survey-optout="done"', false);

        // Y con la encuesta ya contestada la baja sigue funcionando: no caduca con el token.
        $user->forceFill(['surveys_opt_out' => false])->save();
        $response->forceFill(['answered_at' => now()])->save();
        $this->post(route('survey.optout.confirm', ['token' => $response->token]))->assertRedirect($ask);
        $this->assertTrue((bool) $user->fresh()->surveys_opt_out);

        // Un token inventado: el mismo 404.
        $this->get(route('survey.optout', ['token' => Str::random(40)]))->assertNotFound();
    }
}
