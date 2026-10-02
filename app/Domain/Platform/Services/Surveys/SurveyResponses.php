<?php

namespace App\Domain\Platform\Services\Surveys;

use App\Domain\Platform\Contracts\VisitFacts;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyParticipation;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Analytics\Recorder;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * **Preguntar y contestar una encuesta, ANÓNIMA** (`docs/specs/encuestas.md` §4.2, §4.3 y §4.7; `DECISIONES #740` y
 * `#754`): a quién se le ofrece, y cómo se escriben por separado la PARTICIPACIÓN (a quién se preguntó) y la RESPUESTA
 * (qué se contestó, sin persona).
 *
 * Las reglas que viven aquí y en ningún otro sitio:
 *  - **Una por cliente y encuesta** (`#740` §7·2): el índice único de la PARTICIPACIÓN es el árbitro. Una segunda
 *    escritura (una tablet dormida que reenvía, una página abierta dos veces) no pisa la primera ni deja una segunda
 *    respuesta: devuelve `false` y no pasa nada.
 *  - **«No preguntar» también es una fila**: participación y respuesta con `declined`, para la tasa. ⚠️ Desde `#819` la
 *    puerta ya no la escribe: su «Ahora no» sin nada contestado deja la encuesta para otro día ({@see postponeInPerson()}).
 *    Las filas `declined` que hay se quedan, y `declineInPerson()` es la API con la que las pruebas montan sus datos.
 *  - **La respuesta no lleva a nadie**: ni cliente, ni hora (la FRANJA), ni idioma; lleva primera visita y tipo de
 *    visita ({@see VisitFacts}) y, si se contestó, el SELLO ({@see SurveySeals}). Participación y respuesta se escriben
 *    en la misma transacción, pero nada en ellas las une.
 *  - **Al libro solo va `survey_sent`** (mandar es de la participación). Contestar y declinar ya no dejan hecho: su
 *    hora exacta y el cliente unirían el desenlace (§4.7); el cuadro cuenta de su tabla.
 *
 * ⚠️ Platform no mira a Identity (`ModuleBoundariesTest`): el cliente es un `int`, y quien audita con el modelo del
 * cliente delante es la pantalla que llama (la puerta: `puerta.survey_closed`, sin el desenlace).
 */
final class SurveyResponses
{
    /** El token de la página del correo: la credencial entera, como el de la invitación (§4.3). */
    public const TOKEN_LENGTH = 40;

    public const TOKEN_RE = '/^[A-Za-z0-9]{40}$/';

    /** Las dos etiquetas del HMAC del token: con la misma, las dos tablas se unirían sin el token en claro. */
    public const HASH_LOOKUP = 'lookup';

    public const HASH_SPENT = 'spent';

    public function __construct(
        private readonly Recorder $recorder,
        private readonly SurveySeals $seals,
        private readonly VisitFacts $visits,
    ) {}

    public static function tokenHash(string $token, string $purpose): string
    {
        return hash_hmac('sha256', $purpose.':'.$token, (string) config('app.key'));
    }

    // ─── La puerta ───────────────────────────────────────────────────────────────────────────────

    /**
     * La encuesta VIVA de esa clase a la que este cliente aún no ha participado, o `null`; desde `#819`, además, no la
     * dejó HOY para otro día («Ahora no») y, si es «solo en su primera visita», hoy es su primera visita.
     */
    public function offerFor(string $kind, int $userId): ?Survey
    {
        $survey = Survey::runningOfKind($kind);
        if ($survey === null) {
            return null;
        }

        $asked = SurveyParticipation::query()->where('survey_id', $survey->getKey())->where('user_id', $userId)->exists();
        if ($asked) {
            return null;
        }

        $today = DisplayTime::today()->toDateString();

        // `#819`: «Ahora no» sin nada contestado la deja para su PRÓXIMA visita; hoy, ya no.
        if (Cache::has(self::postponedKey((int) $survey->getKey(), $userId, $today))) {
            return null;
        }

        // `#819`: «solo en su primera visita» — ni visita acreditada ni día cobrado ANTES de hoy (la de hoy ya está
        // acreditada al escanear, y no cuenta).
        if ($survey->onlyFirstVisit() && ! $this->visits->isFirstVisit($userId, $today)) {
            return null;
        }

        return $survey;
    }

    /**
     * **«Ahora no» SIN nada contestado** (`#819`, `[DECIDIDO owner]`; el mockup: «no guarda nada»): no escribe NADA —ni
     * participación, ni respuesta, ni rastro— y la encuesta vuelve a salir en su PRÓXIMA visita. Ese día ya no (una visita
     * es un día: `customer_visits`): lo recuerda una marca en la caché que caduca al acabar el día del parque. Con algo
     * contestado no se llega aquí: se cierra con lo contestado.
     */
    public function postponeInPerson(Survey $survey, int $userId): void
    {
        $today = DisplayTime::today();
        Cache::put(self::postponedKey((int) $survey->getKey(), $userId, $today->toDateString()), true, $today->copy()->endOfDay());
    }

    /** La marca de «Ahora no» de un cliente, una encuesta y un día del parque (`Y-m-d`). */
    public static function postponedKey(int $surveyId, int $userId, string $day): string
    {
        return "surveys:postponed:{$surveyId}:{$userId}:{$day}";
    }

    /**
     * Contestada en la puerta, con la persona delante. `$answers` llegan ya TIPADAS y válidas
     * ({@see QuestionSchema::fromForm()} y {@see QuestionSchema::validate()} antes: aquí no se decide qué vale).
     * `false` si este cliente ya había participado.
     *
     * @param  array<string, mixed>  $answers
     */
    public function answerInPerson(Survey $survey, int $userId, array $answers, ?int $askedBy): bool
    {
        return $this->startInPerson($survey, $userId, $answers, $askedBy) !== null;
    }

    /**
     * «No preguntar»: participación y una respuesta `declined`, sin sello; no se vuelve a ofrecer. ⚠️ La puerta ya no lo usa
     * (`#819`: su «Ahora no» es {@see postponeInPerson()}); queda para las filas que hay y para las pruebas.
     */
    public function declineInPerson(Survey $survey, int $userId, ?int $askedBy): bool
    {
        return $this->closeInPerson($survey, $userId, null, $askedBy) !== null;
    }

    /**
     * **La primera respuesta en la puerta, PREGUNTA A PREGUNTA** (`docs/specs/puerta-nueva.md` §4.4, la P1b; `#817`·3):
     * la participación y la respuesta —sellada— nacen como siempre, juntas y en la misma transacción, pero con lo contestado
     * HASTA AHORA. Devuelve la clave de la respuesta (UUID v4) para que los toques siguientes la completen
     * ({@see addInPerson()}), o `null` si este cliente ya había participado. `$answers`, ya TIPADAS y válidas.
     *
     * @param  array<string, mixed>  $answers
     */
    public function startInPerson(Survey $survey, int $userId, array $answers, ?int $askedBy): ?string
    {
        return $this->closeInPerson($survey, $userId, $answers, $askedBy);
    }

    /**
     * **Los toques siguientes**: añade respuestas a la fila que abrió {@see startInPerson()}. Solo a una respuesta INTERNA,
     * de esta encuesta, de HOY, de este empleado y no declinada —la clave sola no basta—, y una pregunta ya contestada no se
     * pisa. Nada en la fila cambia salvo lo contestado: ni hora (no tiene), ni franja, ni sello. `true` si escribió algo.
     *
     * @param  array<string, mixed>  $answers  ya TIPADAS y válidas
     */
    public function addInPerson(Survey $survey, string $responseKey, array $answers, ?int $askedBy): bool
    {
        return DB::transaction(function () use ($survey, $responseKey, $answers, $askedBy): bool {
            $response = SurveyResponse::query()
                ->whereKey($responseKey)
                ->where('survey_id', $survey->getKey())
                ->where('channel', SurveyResponse::CHANNEL_INTERNAL)
                ->whereDate('answered_on', DisplayTime::today()->toDateString())
                ->where('asked_by', $askedBy)
                ->where('declined', false)
                ->lockForUpdate()
                ->first();
            if ($response === null) {
                return false;
            }

            $ya = (array) ($response->answers ?? []);
            $nuevas = array_diff_key($answers, $ya);
            if ($nuevas === []) {
                return false;
            }

            $response->answers = $ya + $nuevas;
            $response->save();

            return true;
        });
    }

    /**
     * @param  array<string, mixed>|null  $answers  `null` = «no preguntar»
     * @return string|null la clave de la respuesta escrita, o `null` si ya había participado
     */
    private function closeInPerson(Survey $survey, int $userId, ?array $answers, ?int $askedBy): ?string
    {
        $now = DisplayTime::now();
        $today = $now->toDateString();
        $facts = $this->factsOf($userId, $today);

        return DB::transaction(function () use ($survey, $userId, $answers, $askedBy, $now, $today, $facts): ?string {
            $participation = $this->participate([
                'survey_id' => $survey->getKey(),
                'user_id' => $userId,
                'channel' => SurveyResponse::CHANNEL_INTERNAL,
                'asked_by' => $askedBy,
                'asked_on' => $today,
            ]);
            if ($participation === null) {
                return null;
            }

            return (string) $this->respond($survey, SurveyResponse::CHANNEL_INTERNAL, $now->toDateString(), SurveyResponse::bandAt($now), $askedBy, $facts, $answers, $answers === null ? null : $userId)->getKey();
        });
    }

    // ─── El correo del día siguiente ─────────────────────────────────────────────────────────────

    /**
     * La participación nace MANDADA (el día de la visita, su hora de envío, el idioma y el HASH del token) y deja el
     * hecho `survey_sent`. Devuelve el token EN CLARO —solo existe para viajar en el correo— o `null` si el cliente ya
     * había participado (la idempotencia del comando, por construcción).
     */
    public function send(Survey $survey, int $userId, string $visitedOn, ?string $locale): ?string
    {
        $token = Str::random(self::TOKEN_LENGTH);
        $participation = $this->participate([
            'survey_id' => $survey->getKey(),
            'user_id' => $userId,
            'channel' => SurveyResponse::CHANNEL_EXTERNAL,
            'asked_on' => $visitedOn,
            'sent_at' => now(),
            'token_hash' => self::tokenHash($token, self::HASH_LOOKUP),
            'locale' => $locale,
        ]);
        if ($participation === null) {
            return null;
        }

        $this->recorder->fact('survey_sent', ['survey' => $survey->key, 'channel' => SurveyResponse::CHANNEL_EXTERNAL], ['user_id' => $userId]);

        return $token;
    }

    /** La participación de un token, esté como esté (la baja y «Gracias» la necesitan aunque ya se contestara). */
    public function participationOf(string $token): ?SurveyParticipation
    {
        if (preg_match(self::TOKEN_RE, $token) !== 1) {
            return null;
        }

        return SurveyParticipation::query()->where('token_hash', self::tokenHash($token, self::HASH_LOOKUP))->with('survey')->first();
    }

    public function isSpent(string $token): bool
    {
        return DB::table('survey_spent_tokens')->where('hash', self::tokenHash($token, self::HASH_SPENT))->exists();
    }

    /**
     * La participación que ABRE un token: con titular, sin contestar, de una encuesta viva. Todo lo demás —inventado,
     * contestado, apagada, anonimizado— es el mismo `null`, y la página el mismo 404 (§4.3).
     */
    public function openByToken(string $token): ?SurveyParticipation
    {
        $participation = $this->participationOf($token);
        if ($participation === null || $participation->user_id === null || $participation->survey === null || ! $participation->survey->isRunning()) {
            return null;
        }

        return $this->isSpent($token) ? null : $participation;
    }

    /**
     * Contestar desde el correo: GASTA el token una sola vez —su hash de «gastado» es clave primaria, así que dos
     * pestañas con la misma página no escriben dos veces— y escribe la respuesta sin persona, en la misma transacción.
     * `false` si ya estaba gastado.
     *
     * @param  array<string, mixed>  $answers  ya tipadas y válidas ({@see QuestionSchema::validate()})
     */
    public function answerSent(SurveyParticipation $participation, string $token, array $answers): bool
    {
        $survey = $participation->survey;
        $userId = $participation->user_id;
        if ($survey === null || $userId === null) {
            return false;
        }

        $now = DisplayTime::now();
        $facts = $this->factsOf((int) $userId, $participation->asked_on->toDateString());

        return DB::transaction(function () use ($survey, $userId, $answers, $token, $now, $facts): bool {
            try {
                DB::table('survey_spent_tokens')->insert(['hash' => self::tokenHash($token, self::HASH_SPENT)]);
            } catch (UniqueConstraintViolationException) {
                return false;
            }

            $this->respond($survey, SurveyResponse::CHANNEL_EXTERNAL, $now->toDateString(), SurveyResponse::bandAt($now), null, $facts, $answers, (int) $userId);

            return true;
        });
    }

    // ─── Las dos escrituras ──────────────────────────────────────────────────────────────────────

    /**
     * La participación. El índice único `(survey_id, user_id)` es el árbitro: si ya existía, la BD lo dice y aquí se
     * traduce a `null` sin pisar nada.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function participate(array $attributes): ?SurveyParticipation
    {
        try {
            return SurveyParticipation::query()->create($attributes);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * La respuesta, sin persona. Se sella solo si se CONTESTÓ (`$sealFor`): «volvió quien puntuó mal» no pregunta por
     * quien dijo «no preguntar».
     *
     * @param  array{first_visit: bool, visit_kind: string}  $facts
     * @param  array<string, mixed>|null  $answers
     */
    private function respond(Survey $survey, string $channel, string $day, string $band, ?int $askedBy, array $facts, ?array $answers, ?int $sealFor): SurveyResponse
    {
        $response = new SurveyResponse([
            'survey_id' => $survey->getKey(),
            'channel' => $channel,
            'answered_on' => $day,
            'band' => $band,
            'asked_by' => $askedBy,
            'first_visit' => $facts['first_visit'],
            'visit_kind' => $facts['visit_kind'],
            'declined' => $answers === null,
            'answers' => $answers,
        ]);
        if ($sealFor !== null) {
            $this->seals->stamp($response, $sealFor);
        }
        $response->save();

        return $response;
    }

    /** @return array{first_visit: bool, visit_kind: string} */
    private function factsOf(int $userId, string $visitDay): array
    {
        return [
            'first_visit' => $this->visits->isFirstVisit($userId, $visitDay),
            'visit_kind' => $this->visits->kindOn($userId, $visitDay),
        ];
    }
}
