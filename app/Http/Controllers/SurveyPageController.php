<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyParticipation;
use App\Domain\Platform\Services\SiteLocales;
use App\Domain\Platform\Services\Surveys\QuestionSchema;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * **La PÁGINA de la encuesta del correo** (`docs/specs/encuestas.md` §4.3 y §4.7, T3 y T5; `DECISIONES #740` y
 * `#754`): la abre el cliente desde el botón del correo, sin sesión, y contesta una sola vez, ANÓNIMA.
 *
 * ## Las reglas, y de dónde vienen
 *  · **El token es la credencial** (40 caracteres, como la invitación): lo que no abre —inventado, contestado,
 *    encuesta apagada, cuenta anonimizada— es **el mismo 404** (`SurveyResponses::openByToken()`): distinguirlos
 *    diría que ese token existió. La base guarda su HASH, nunca el token.
 *  · **Hoja enfocada**: sin cookie de medición (el grupo de rutas), sin banner, sin tercero; `no-store`
 *    (`RGPD-04`) porque se entra sin sesión; `Referrer-Policy: no-referrer` para que el token no viaje en el
 *    `Referer` si algún día la página lleva un enlace fuera.
 *  · **El idioma es el del correo** (`survey_participations.locale`, fijado al mandar), no el del navegador.
 *  · **Se contesta UNA vez**: el POST tipa y valida como la puerta (`QuestionSchema`), GASTA el token y escribe la
 *    respuesta sin persona; «Gracias» vive en su propia URL para que recargar no reenvíe nada.
 *  · **El aviso del anonimato** arriba y junto a cada pregunta de texto (`#754`): texto del producto, fijo.
 *  · **La baja es una página con UN botón**, no un enlace que escribe al abrirse: los escáneres de enlaces de los
 *    gestores de correo abren los GET, y darían de baja a quien no pidió nada. Funciona aunque ya se contestara.
 */
class SurveyPageController extends Controller
{
    public function __construct(private readonly SurveyResponses $responses) {}

    public function show(string $token): Response
    {
        $participation = $this->responses->openByToken($token);
        abort_if($participation === null, 404);

        $this->useLocaleOf($participation);
        /** @var Survey $survey */
        $survey = $participation->survey;
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
        $participation = $this->responses->openByToken($token);
        abort_if($participation === null, 404);

        $this->useLocaleOf($participation);
        /** @var Survey $survey */
        $survey = $participation->survey;
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

        $this->responses->answerSent($participation, $token, $typed);

        return redirect()->route('survey.thanks', ['token' => $token]);
    }

    /** «Gracias», en su propia URL (POST → redirect → GET): solo para un token que YA se gastó. */
    public function thanks(string $token): Response
    {
        $participation = $this->responses->participationOf($token);
        abort_if($participation === null || ! $this->responses->isSpent($token), 404);

        $this->useLocaleOf($participation);

        return response()->view('survey.thanks')->header('Referrer-Policy', 'no-referrer');
    }

    /** La página de la baja, con un solo botón. Abre también con un token ya contestado: la baja no caduca con la encuesta. */
    public function optOut(string $token): Response
    {
        $participation = $this->responses->participationOf($token);
        abort_if($participation === null || $participation->user_id === null, 404);

        $this->useLocaleOf($participation);

        return response()
            ->view('survey.optout', ['token' => $token, 'done' => session('survey_optout') === 'done'])
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function confirmOptOut(string $token, AccountPrivacy $privacy): RedirectResponse
    {
        $participation = $this->responses->participationOf($token);
        abort_if($participation === null || $participation->user_id === null, 404);

        $user = User::query()->find($participation->user_id);
        if ($user !== null) {
            $privacy->setSurveys($user, false);
        }

        return redirect()->route('survey.optout', ['token' => $token])->with('survey_optout', 'done');
    }

    /** El idioma que se fijó al mandar el correo; si no es uno de los del sitio (`SiteLocales`), el de la instalación. */
    private function useLocaleOf(SurveyParticipation $participation): void
    {
        $locale = (string) $participation->locale;
        if (in_array($locale, SiteLocales::SUPPORTED, true)) {
            app()->setLocale($locale);
        }
    }
}
