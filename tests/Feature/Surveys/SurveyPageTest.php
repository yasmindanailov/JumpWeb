<?php

namespace Tests\Feature\Surveys;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyParticipation;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use App\Domain\Platform\Services\Surveys\SurveySeals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * **La página de la encuesta del correo** (`docs/specs/encuestas.md` §4.3 y §4.7, T3 y T5): abre por su token, en el
 * idioma del correo, sin cookie de medición y `no-store`, con el aviso del anonimato; se contesta UNA vez y la
 * respuesta nace SIN persona; todo lo que no abre es el mismo 404; y la baja es una página con un botón que funciona
 * aunque la encuesta ya se contestara.
 */
class SurveyPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-26 18:30:00', 'Europe/Madrid'));
    }

    /** @return array{0: Survey, 1: string, 2: User} la encuesta, el token EN CLARO (el del correo) y el cliente */
    private function sent(array $surveyOverrides = [], string $locale = 'en'): array
    {
        $survey = Survey::create(array_merge([
            'key' => 'que-tal-ayer',
            'name' => ['es' => '¿Qué tal ayer?', 'en' => 'How was it?', 'fr' => 'C’était comment ?'],
            'kind' => Survey::KIND_EXTERNAL,
            'active' => true,
            'questions' => [
                ['key' => 'ambiente', 'type' => 'scale', 'required' => true, 'label' => ['es' => 'Ambiente', 'en' => 'Atmosphere', 'fr' => 'Ambiance']],
                ['key' => 'volveria', 'type' => 'yesno', 'label' => ['es' => '¿Volverías?', 'en' => 'Would you come back?', 'fr' => 'Reviendrais-tu ?']],
                ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Algo más', 'en' => 'Anything else', 'fr' => 'Autre chose']],
            ],
        ], $surveyOverrides));
        $user = User::factory()->create(['email_verified_at' => now(), 'locale' => $locale]);
        $token = app(SurveyResponses::class)->send($survey, $user->id, '2026-09-25', $locale);
        $this->assertIsString($token);

        return [$survey, $token, $user];
    }

    public function test_the_page_opens_by_token_in_the_customers_language_without_the_measurement_cookie_and_no_store(): void
    {
        [, $token] = $this->sent();

        $page = $this->get(route('survey.show', ['token' => $token]));

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

    /**
     * `#754`: arriba, EL AVISO del anonimato, y junto a la pregunta de texto, «no escribas tu nombre». En los tres
     * idiomas del correo, tecleados a mano (`#734`).
     */
    public function test_the_page_carries_the_anonymity_notice_and_the_text_hint_in_the_three_languages(): void
    {
        $expected = [
            'es' => ['Nadie en el parque verá tu nombre junto a tus respuestas, y a los 90 días se separan de ti del todo.', 'Si quieres seguir en el anonimato, no escribas tu nombre ni datos personales.'],
            'en' => ['No one at the park will see your name next to your answers, and after 90 days they are separated from you entirely.', 'If you want to stay anonymous, do not write your name or any personal details.'],
            'fr' => ['Personne au parc ne verra ton nom à côté de tes réponses, et au bout de 90 jours elles sont entièrement détachées de toi.', "Si tu veux rester anonyme, n'écris ni ton nom ni tes données personnelles."],
        ];

        foreach ($expected as $locale => [$notice, $hint]) {
            [, $token] = $this->sent(['key' => 'enc-'.$locale], $locale);
            $page = $this->get(route('survey.show', ['token' => $token]))->assertOk();

            $page->assertSee('data-survey-notice', false)->assertSee('data-survey-text-hint', false);
            $html = html_entity_decode((string) $page->getContent(), ENT_QUOTES);
            $this->assertStringContainsString($notice, $html, "el aviso en {$locale}");
            $this->assertStringContainsString($hint, $html, "la advertencia del texto libre en {$locale}");
            Survey::query()->where('key', 'enc-'.$locale)->update(['active' => false]);
        }
    }

    public function test_an_unknown_answered_switched_off_or_orphan_token_is_the_same_404(): void
    {
        $this->get(route('survey.show', ['token' => Str::random(40)]))->assertNotFound();
        $this->get('/encuesta/no-tiene-forma-de-token')->assertNotFound();

        [, $answered] = $this->sent();
        $this->post(route('survey.answer', ['token' => $answered]), ['answers' => ['ambiente' => '4']])->assertRedirect();
        $this->get(route('survey.show', ['token' => $answered]))->assertNotFound();
        $this->post(route('survey.answer', ['token' => $answered]), ['answers' => ['ambiente' => '4']])->assertNotFound();

        [$survey, $off] = $this->sent(['key' => 'apagada']);
        $survey->update(['active' => false]);
        $this->get(route('survey.show', ['token' => $off]))->assertNotFound();

        [, $orphan, $user] = $this->sent(['key' => 'huerfana']);
        SurveyParticipation::query()->where('user_id', $user->id)->update(['user_id' => null]);
        $this->get(route('survey.show', ['token' => $orphan]))->assertNotFound();
    }

    public function test_answering_spends_the_token_once_and_writes_an_anonymous_typed_response(): void
    {
        [$survey, $token, $user] = $this->sent();
        $show = route('survey.show', ['token' => $token]);
        $answer = route('survey.answer', ['token' => $token]);

        $this->post($answer, ['answers' => ['ambiente' => '4', 'volveria' => '0', 'comentario' => '  Great, thanks.  ']])
            ->assertRedirect(route('survey.thanks', ['token' => $token]));

        // La respuesta, SIN persona: el día y la franja del parque (las 18:30 son la tarde), sin quién preguntó (es un
        // correo), lo grueso de la visita y lo contestado tipado. Sin idioma: el del correo es el del cliente.
        $row = SurveyResponse::query()->sole();
        $this->assertSame($survey->id, (int) $row->survey_id);
        $this->assertSame(SurveyResponse::CHANNEL_EXTERNAL, $row->channel);
        $this->assertSame('2026-09-26', $row->answered_on->toDateString());
        $this->assertSame(SurveyResponse::BAND_AFTERNOON, $row->band);
        $this->assertNull($row->asked_by);
        $this->assertFalse($row->declined);
        $this->assertSame(['ambiente' => 4, 'volveria' => false, 'comentario' => 'Great, thanks.'], $row->answers, 'tipadas por su pregunta, no como llegaron');
        $this->assertCount(1, app(SurveySeals::class)->sealedFor($user->id), 'contestada = sellada');

        // El token se GASTA con su propia etiqueta (distinta de la de búsqueda): sin el token en claro no se unen.
        $this->assertSame(1, DB::table('survey_spent_tokens')->count());
        $this->assertSame(SurveyResponses::tokenHash($token, SurveyResponses::HASH_SPENT), DB::table('survey_spent_tokens')->value('hash'));
        $this->assertNotSame(SurveyParticipation::query()->value('token_hash'), DB::table('survey_spent_tokens')->value('hash'));

        // Al libro NO va (`#754`): su hora exacta y el cliente unirían el desenlace.
        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'survey_answered')->count());
        $this->assertStringNotContainsString('Great', json_encode(AnalyticsEvent::query()->get(), JSON_UNESCAPED_UNICODE), 'ninguna respuesta pisa el libro');

        $this->get(route('survey.thanks', ['token' => $token]))->assertOk()->assertSee('data-survey-thanks', false);

        // El token se cerró: ni vuelve a abrir, ni admite una segunda respuesta.
        $this->get($show)->assertNotFound();
        $this->post($answer, ['answers' => ['ambiente' => '1']])->assertNotFound();
        $this->assertSame(1, SurveyResponse::query()->count());
        $this->assertSame(['ambiente' => 4, 'volveria' => false, 'comentario' => 'Great, thanks.'], SurveyResponse::query()->sole()->answers);
    }

    public function test_a_missing_required_answer_goes_back_with_the_error_and_writes_nothing(): void
    {
        [, $token] = $this->sent();
        $show = route('survey.show', ['token' => $token]);

        $this->from($show)
            ->post(route('survey.answer', ['token' => $token]), ['answers' => ['comentario' => 'x', 'ambiente' => '9']])
            ->assertRedirect($show)
            ->assertSessionHasErrors(['answers.ambiente']);

        $this->assertSame(0, SurveyResponse::query()->count());
        $this->assertSame(0, DB::table('survey_spent_tokens')->count());
        // «Gracias» no abre para un token sin contestar.
        $this->get(route('survey.thanks', ['token' => $token]))->assertNotFound();
    }

    public function test_opting_out_takes_one_button_not_one_link_and_survives_an_answered_token(): void
    {
        [, $token, $user] = $this->sent();
        $ask = route('survey.optout', ['token' => $token]);

        // Abrir la página NO da de baja: los escáneres de enlaces de los gestores de correo abren los GET.
        $this->get($ask)->assertOk()->assertSee('data-survey-optout="ask"', false);
        $this->assertFalse((bool) $user->fresh()->surveys_opt_out);

        $this->post(route('survey.optout.confirm', ['token' => $token]))
            ->assertRedirect($ask)
            ->assertSessionHas('survey_optout', 'done');
        $this->assertTrue((bool) $user->fresh()->surveys_opt_out);
        $this->get($ask)->assertOk()->assertSee('data-survey-optout="done"', false);

        // Y con la encuesta ya contestada la baja sigue funcionando: no caduca con el token.
        $user->forceFill(['surveys_opt_out' => false])->save();
        $this->post(route('survey.answer', ['token' => $token]), ['answers' => ['ambiente' => '5']])->assertRedirect();
        $this->post(route('survey.optout.confirm', ['token' => $token]))->assertRedirect($ask);
        $this->assertTrue((bool) $user->fresh()->surveys_opt_out);

        // Un token inventado: el mismo 404.
        $this->get(route('survey.optout', ['token' => Str::random(40)]))->assertNotFound();
    }
}
