<?php

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Services\Surveys\QuestionSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **Lo que contestó un cliente a una encuesta** (`docs/specs/encuestas.md` §4.1, T1): una fila por cliente y
 * encuesta. Nace al mandar el correo (externa: `sent_at` y `token`) o al contestar en la puerta (interna), y se
 * cierra con `answered_at` o con `declined_at`.
 *
 * ⚠️ **Privacidad**: `user_id` se pone a NULL al anonimizar y las respuestas de TEXTO LIBRE se borran con él
 * ({@see forgetPerson()}); los agregados de elección y escala sobreviven sin persona. Se poda a los 24 meses
 * (`RGPD-01`, `model:prune`). Ninguna respuesta entra en el libro de eventos (`RGPD-07`).
 *
 * @property int $id
 * @property int $survey_id
 * @property ?int $user_id
 * @property ?Carbon $visited_on
 * @property string $channel
 * @property ?int $answered_by
 * @property ?string $token
 * @property ?Carbon $sent_at
 * @property ?Carbon $answered_at
 * @property ?Carbon $declined_at
 * @property ?array<string, mixed> $answers
 * @property ?string $locale
 * @property-read ?Survey $survey
 */
class SurveyResponse extends Model
{
    use MassPrunable;

    public const CHANNEL_INTERNAL = 'internal';

    public const CHANNEL_EXTERNAL = 'external';

    /** Meses que se conserva una respuesta (`RGPD-01`). */
    public const RETENTION_MONTHS = 24;

    protected $fillable = ['survey_id', 'user_id', 'visited_on', 'channel', 'answered_by', 'token', 'sent_at', 'answered_at', 'declined_at', 'answers', 'locale'];

    protected $casts = [
        'visited_on' => 'date:Y-m-d',
        'sent_at' => 'datetime',
        'answered_at' => 'datetime',
        'declined_at' => 'datetime',
        'answers' => 'array',
    ];

    /** @return BelongsTo<Survey, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    /** Las respuestas de más de {@see RETENTION_MONTHS} meses, por su última fecha. */
    public function prunable(): Builder
    {
        $limit = now()->subMonths(self::RETENTION_MONTHS);

        return static::query()
            ->where(static function (Builder $query) use ($limit): void {
                $query->where('answered_at', '<', $limit)
                    ->orWhere(static function (Builder $sent) use ($limit): void {
                        $sent->whereNull('answered_at')->where('created_at', '<', $limit);
                    });
            });
    }

    /**
     * **Al anonimizar una cuenta** (`User::anonymize()`, `RGPD-01`): sus respuestas dejan de ser suyas y el texto
     * libre —lo único que puede llevar un nombre o un dato— se borra; lo demás sigue contando en los agregados.
     */
    public static function forgetPerson(int $userId): void
    {
        static::query()->where('user_id', $userId)->with('survey')->get()->each(static function (self $response): void {
            $answers = $response->answers ?? [];
            $survey = $response->survey;
            if ($survey !== null) {
                foreach ($survey->questionList() as $question) {
                    if ($question['type'] === QuestionSchema::TYPE_TEXT) {
                        unset($answers[$question['key']]);
                    }
                }
            }
            $response->forceFill(['user_id' => null, 'answers' => $answers === [] ? null : $answers])->saveQuietly();
        });
    }
}
