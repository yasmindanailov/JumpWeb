<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\SiteLocales;
use App\Domain\Platform\Services\Surveys\QuestionSchema;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * **La PÁGINA de la encuesta del correo** (`docs/specs/encuestas.md` §4.3, T3; `DECISIONES #740`): la abre el
 * cliente desde el botón del correo, sin sesión, y contesta una sola vez.
 *
 * ## Las reglas, y de dónde vienen
 *  · **El token es la credencial** (40 caracteres, como la invitación): lo que no abre —inventado, contestado,
 *    encuesta apagada, cuenta anonimizada— es **el mismo 404** (`SurveyResponses::openByToken()`): distinguirlos
 *    diría que ese token existió.
 *  · **Hoja enfocada**: sin cookie de medición (el grupo de rutas), sin banner, sin tercero; `no-store`
 *    (`RGPD-04`) porque se entra sin sesión; `Referrer-Policy: no-referrer` para que el token no viaje en el
 *    `Referer` si algún día la página lleva un enlace fuera.
 *  · **El idioma es el del cliente** (`survey_responses.locale`, fijado al mandar), no el del navegador.
 *  · **Se contesta UNA vez**: el POST tipa y valida como la puerta (`QuestionSchema`), escribe bajo candado y
 *    cierra el token; «Gracias» vive en su propia URL para que recargar no reenvíe nada.
 *  · **La baja es una página con UN botón**, no un enlace que escribe al abrirse: los escáneres de enlaces de los
 *    gestores de correo abren los GET, y darían de baja a quien no pidió nada. Funciona aunque ya se contestara.
 */
class SurveyPageController extends Controller
{
    public function __construct(private readonly SurveyResponses $responses) {}

    public function show(string $token): Response
    {
        $response = $this->responses->openByToken($token);
        abort_if($response === null, 404);

        $this->useLocaleOf($response);
        /** @var Survey $survey */
        $survey = $response->survey;
        $locale = app()->getLocale();

        return response()
            ->view('survey.show', [
                'token' => $token,
                'name' => $survey->displayName($locale),
                'intro' => $survey->displayIntro($locale),
                'questions' => array_map(static fn (array $q): array => [
                    'key' => $q['key'],
                    'type' => $q['type'],
                    'required' => $q['required'],
                    'label' => QuestionSchema::label($q['label'], $locale),
                    'options' => array_map(
                        static fn (array $o): array => ['key' => $o['key'], 'label' => QuestionSchema::label($o['label'], $locale)],
                        $q['options'],
                    ),
                ], $survey->questionList()),
            ])
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function answer(Request $request, string $token): RedirectResponse
    {
        $response = $this->responses->openByToken($token);
        abort_if($response === null, 404);

        $this->useLocaleOf($response);
        /** @var Survey $survey */
        $survey = $response->survey;
        $questions = $survey->questionList();
        $raw = $request->input('answers');
        $typed = QuestionSchema::fromForm($questions, is_array($raw) ? $raw : []);
        $errors = QuestionSchema::validate($questions, $typed);

        if ($errors !== []) {
            $messages = [];
            foreach ($errors as $key => $reason) {
                $messages['answers.'.$key] = (string) __('surveys.page.error_'.$reason);
            }

            throw ValidationException::withMessages($messages);
        }

        $this->responses->answerSent($response, $typed, app()->getLocale());

        return redirect()->route('survey.thanks', ['token' => $token]);
    }

    /** «Gracias», en su propia URL (POST → redirect → GET): solo para un token que YA contestó. */
    public function thanks(string $token): Response
    {
        $response = $this->row($token);
        abort_if($response === null || $response->answered_at === null, 404);

        $this->useLocaleOf($response);

        return response()->view('survey.thanks')->header('Referrer-Policy', 'no-referrer');
    }

    /** La página de la baja, con un solo botón. Abre también con un token ya contestado: la baja no caduca con la encuesta. */
    public function optOut(string $token): Response
    {
        $response = $this->row($token);
        abort_if($response === null || $response->user_id === null, 404);

        $this->useLocaleOf($response);

        return response()
            ->view('survey.optout', ['token' => $token, 'done' => session('survey_optout') === 'done'])
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function confirmOptOut(string $token, AccountPrivacy $privacy): RedirectResponse
    {
        $response = $this->row($token);
        abort_if($response === null || $response->user_id === null, 404);

        $user = User::query()->find($response->user_id);
        if ($user !== null) {
            $privacy->setSurveys($user, false);
        }

        return redirect()->route('survey.optout', ['token' => $token])->with('survey_optout', 'done');
    }

    private function row(string $token): ?SurveyResponse
    {
        if (preg_match(SurveyResponses::TOKEN_RE, $token) !== 1) {
            return null;
        }

        return SurveyResponse::query()->where('token', $token)->first();
    }

    /** El idioma que se fijó al mandar el correo; si no es uno de los del sitio (`SiteLocales`), el de la instalación. */
    private function useLocaleOf(SurveyResponse $response): void
    {
        $locale = (string) $response->locale;
        if (in_array($locale, SiteLocales::SUPPORTED, true)) {
            app()->setLocale($locale);
        }
    }
}
