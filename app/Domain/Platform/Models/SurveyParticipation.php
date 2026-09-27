<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **A quién se preguntó una encuesta, sin lo que contestó** (`docs/specs/encuestas.md` §4.7, T5; `DECISIONES #754`).
 *
 * Nace al cerrar la tarjeta de la puerta (contestada o «no preguntar») o al mandar el correo, y no se vuelve a tocar:
 * **no guarda el desenlace**, porque con él y el registro de actividad se acotaría qué fila anónima es de quién. Es
 * el árbitro de «una por cliente y encuesta» (índice único `(survey_id, user_id)`, `#740` §2), la fuente del plazo
 * entre correos y de la baja de la página, y lo que el export del titular lleva (art. 15).
 *
 * `asked_on` es el día de la VISITA que la originó (la de hoy en la puerta; la de ayer en el correo). `token_hash` es
 * `HMAC(token)` con una etiqueta propia: el token en claro solo viaja en el correo, y el de «ya contestado»
 * (`survey_spent_tokens`) usa OTRA etiqueta, así que las dos tablas no se unen sin el token.
 *
 * ⚠️ `anonymize()` suelta `user_id` y el hash (la fila sigue contando como mandada o preguntada). Se poda a los 24
 * meses (`RGPD-01`).
 *
 * @property int $id
 * @property int $survey_id
 * @property ?int $user_id
 * @property string $channel
 * @property ?int $asked_by
 * @property Carbon $asked_on
 * @property ?Carbon $sent_at
 * @property ?string $token_hash
 * @property ?string $locale
 * @property-read ?Survey $survey
 */
class SurveyParticipation extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $fillable = ['survey_id', 'user_id', 'channel', 'asked_by', 'asked_on', 'sent_at', 'token_hash', 'locale'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'asked_on' => 'date:Y-m-d',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<Survey, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /** Las de más de {@see SurveyResponse::RETENTION_MONTHS} meses, por el día de su visita. */
    public function prunable(): Builder
    {
        return static::query()->where('asked_on', '<', now()->subMonths(SurveyResponse::RETENTION_MONTHS)->toDateString());
    }

    /**
     * **Al anonimizar una cuenta** (`User::anonymize()`, `RGPD-01`): sus participaciones dejan de ser suyas y el hash
     * del token se va con ellas (la página no vuelve a abrir). Las filas siguen contando en las tasas, sin nadie.
     */
    public static function forgetPerson(int $userId): void
    {
        static::query()->where('user_id', $userId)->update(['user_id' => null, 'token_hash' => null]);
    }
}
