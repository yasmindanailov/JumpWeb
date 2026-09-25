<?php

namespace App\Domain\Platform\Services\Surveys;

use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Analytics\Recorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * **Las respuestas de una encuesta: a quién se le ofrece y cómo se escribe una** (`docs/specs/encuestas.md`
 * §4.2, T2; la externa de la T3 reutiliza la escritura).
 *
 * Tres reglas que viven aquí y en ningún otro sitio:
 *  - **Una respuesta por cliente y encuesta** (`[DECIDIDO owner]` §7·2): la BD lo garantiza con su índice único
 *    y aquí se RESPETA — una segunda escritura (una tablet dormida que reenvía, una página abierta dos veces)
 *    no pisa la primera ni deja un segundo hecho: devuelve `null` y no pasa nada.
 *  - **«No preguntar» también es una respuesta**: deja fila (`declined_at`) y no se vuelve a ofrecer.
 *  - **Al libro solo va el HECHO** (`survey_answered` · `survey_declined`, con la clave de la encuesta y el
 *    canal, régimen del contrato como `visit_checked_in`): ninguna respuesta lo pisa (`RGPD-07`).
 *
 * ⚠️ Platform no mira a Identity (`ModuleBoundariesTest`): el cliente es un `int`, y quien audita con el
 * modelo del cliente delante es la pantalla que llama (la puerta: `puerta.survey_*`).
 */
final class SurveyResponses
{
    /** El token de la página del correo: la credencial entera, como el de la invitación (§4.3). */
    public const TOKEN_LENGTH = 40;

    public const TOKEN_RE = '/^[A-Za-z0-9]{40}$/';

    public function __construct(private readonly Recorder $recorder) {}

    /**
     * T3 · el correo del día siguiente: la fila nace MANDADA (`sent_at`, su token) y deja el hecho `survey_sent`.
     * `null` si el cliente ya tenía fila para esta encuesta: es la idempotencia del comando, por construcción.
     */
    public function send(Survey $survey, int $userId, ?string $visitedOn, ?string $locale): ?SurveyResponse
    {
        $response = $this->insert($survey, $userId, [
            'channel' => SurveyResponse::CHANNEL_EXTERNAL,
            'visited_on' => $visitedOn,
            'token' => Str::random(self::TOKEN_LENGTH),
            'sent_at' => now(),
            'locale' => $locale,
        ]);

        if ($response !== null) {
            $this->recorder->fact('survey_sent', ['survey' => $survey->key, 'channel' => SurveyResponse::CHANNEL_EXTERNAL], ['user_id' => $userId]);
        }

        return $response;
    }

    /**
     * La fila que ABRE un token: mandada, sin contestar ni declinar, con titular, de una encuesta viva. Todo lo
     * demás —inventado, contestado, apagada, anonimizado— es el mismo `null`, y la página el mismo 404 (§4.3).
     */
    public function openByToken(string $token): ?SurveyResponse
    {
        if (preg_match(self::TOKEN_RE, $token) !== 1) {
            return null;
        }

        $response = SurveyResponse::query()
            ->where('token', $token)
            ->whereNull('answered_at')
            ->whereNull('declined_at')
            ->whereNotNull('user_id')
            ->with('survey')
            ->first();

        if ($response === null || $response->survey === null || ! $response->survey->isRunning()) {
            return null;
        }

        return $response;
    }

    /**
     * Contestar desde el correo: cierra la fila UNA sola vez —bajo candado: dos pestañas con la misma página no
     * escriben dos veces— y deja el hecho. `false` si ya estaba cerrada.
     *
     * @param  array<string, mixed>  $answers  ya tipadas y válidas ({@see QuestionSchema::validate()})
     */
    public function answerSent(SurveyResponse $response, array $answers, string $locale): bool
    {
        $written = DB::transaction(static function () use ($response, $answers, $locale): bool {
            $fresh = SurveyResponse::query()->whereKey($response->getKey())->lockForUpdate()->first();
            if ($fresh === null || $fresh->answered_at !== null || $fresh->declined_at !== null) {
                return false;
            }
            $fresh->forceFill(['answered_at' => now(), 'answers' => $answers, 'locale' => $locale])->save();

            return true;
        });

        if ($written && $response->survey !== null && $response->user_id !== null) {
            $this->recorder->fact('survey_answered', ['survey' => $response->survey->key, 'channel' => SurveyResponse::CHANNEL_EXTERNAL], ['user_id' => (int) $response->user_id]);
        }

        return $written;
    }

    /** La encuesta VIVA de esa clase que este cliente aún no tiene (ni contestada ni declinada), o `null`. */
    public function offerFor(string $kind, int $userId): ?Survey
    {
        $survey = Survey::runningOfKind($kind);
        if ($survey === null) {
            return null;
        }

        return $this->rowExists($survey, $userId) ? null : $survey;
    }

    /**
     * Escribe la respuesta y su hecho. `$answers` llegan ya TIPADAS y válidas ({@see QuestionSchema::fromForm()}
     * y {@see QuestionSchema::validate()} antes: aquí no se decide qué vale). `null` si el cliente ya tenía fila.
     *
     * @param  array<string, mixed>  $answers
     */
    public function answer(Survey $survey, int $userId, string $channel, array $answers, string $locale, ?string $visitedOn = null, ?int $answeredBy = null): ?SurveyResponse
    {
        $response = $this->insert($survey, $userId, [
            'channel' => $channel,
            'answered_by' => $answeredBy,
            'visited_on' => $visitedOn,
            'answered_at' => now(),
            'answers' => $answers,
            'locale' => $locale,
        ]);

        if ($response !== null) {
            $this->recorder->fact('survey_answered', ['survey' => $survey->key, 'channel' => $channel], ['user_id' => $userId]);
        }

        return $response;
    }

    /** «No preguntar» / «no quiero contestar»: fila con `declined_at` y su hecho; no se vuelve a ofrecer. */
    public function decline(Survey $survey, int $userId, string $channel, ?string $visitedOn = null, ?int $by = null, ?string $locale = null): ?SurveyResponse
    {
        $response = $this->insert($survey, $userId, [
            'channel' => $channel,
            'answered_by' => $by,
            'visited_on' => $visitedOn,
            'declined_at' => now(),
            'locale' => $locale,
        ]);

        if ($response !== null) {
            $this->recorder->fact('survey_declined', ['survey' => $survey->key, 'channel' => $channel], ['user_id' => $userId]);
        }

        return $response;
    }

    private function rowExists(Survey $survey, int $userId): bool
    {
        return SurveyResponse::query()
            ->where('survey_id', $survey->getKey())
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * La única escritura. El índice único `(survey_id, user_id)` es el árbitro: si la fila ya existe, la BD lo
     * dice y aquí se traduce a `null` sin pisar nada.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function insert(Survey $survey, int $userId, array $attributes): ?SurveyResponse
    {
        try {
            return SurveyResponse::query()->create(['survey_id' => $survey->getKey(), 'user_id' => $userId] + $attributes);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
