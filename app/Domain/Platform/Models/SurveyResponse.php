<?php

namespace App\Domain\Platform\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * **Lo que se contestó a una encuesta, SIN persona** (`docs/specs/encuestas.md` §4.7, T5; `[DECIDIDO owner]`
 * `DECISIONES #754`). A quién se preguntó vive en {@see SurveyParticipation}, y las dos filas no comparten clave.
 *
 * Qué guarda y por qué así:
 *  · la clave es un **UUID v4 aleatorio** ({@see booted()}): ni autoincremental ni v7, porque el ORDEN de inserción
 *    uniría esta fila con su participación. Sin `timestamps()` por lo mismo;
 *  · el **día del parque** y la **FRANJA** ({@see bandAt()}), no la hora: el registro de actividad apunta cada escaneo
 *    con su hora y su empleado, y con la hora exacta cualquiera con ese registro unía la respuesta a su persona;
 *  · quién preguntó (`asked_by`, la puerta), primera visita y tipo de visita: lo grueso que el owner quiso conservar;
 *  · `declined`: «No preguntar» también es una fila, para la tasa;
 *  · el **SELLO** (`seal`): el cliente CIFRADO, que solo existe para saber si volvió en 90 días. Lo lee y lo borra
 *    UNA clase, `Platform\Services\Surveys\SurveySeals`; nunca un cast `encrypted`, que lo descifraría al cargar el
 *    modelo en cualquier pantalla.
 *
 * ⚠️ Sin el idioma: en la página del correo es el del cliente, y `users.locale` lo cruzaría. Se poda a los 24 meses
 * (`RGPD-01`) y ninguna respuesta entra en el libro de eventos (`RGPD-07`).
 *
 * @property string $id
 * @property int $survey_id
 * @property string $channel
 * @property Carbon $answered_on
 * @property string $band
 * @property ?int $asked_by
 * @property ?bool $first_visit
 * @property ?string $visit_kind
 * @property bool $declined
 * @property ?array<string, mixed> $answers
 * @property ?bool $returned
 * @property ?int $returned_after_days
 * @property-read ?Survey $survey
 */
class SurveyResponse extends Model
{
    use MassPrunable;

    public const CHANNEL_INTERNAL = 'internal';

    public const CHANNEL_EXTERNAL = 'external';

    /** Las franjas del día, en hora del PARQUE: hasta las 13:00, hasta las 16:00, y después. */
    public const BAND_MORNING = 'morning';

    public const BAND_MIDDAY = 'midday';

    public const BAND_AFTERNOON = 'afternoon';

    /** @var list<string> */
    public const BANDS = [self::BAND_MORNING, self::BAND_MIDDAY, self::BAND_AFTERNOON];

    /** Tipo de la visita, del pedido de ese día (`Platform\Contracts\VisitFacts`). */
    public const KIND_ENTRY = 'entry';

    public const KIND_PARTY = 'party';

    public const KIND_GROUP = 'group';

    public const KIND_OTHER = 'other';

    /** @var list<string> */
    public const KINDS = [self::KIND_ENTRY, self::KIND_PARTY, self::KIND_GROUP, self::KIND_OTHER];

    /** Meses que se conserva una respuesta (`RGPD-01`). */
    public const RETENTION_MONTHS = 24;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    /** ⚠️ `seal` NO es asignable en masa: solo `SurveySeals` lo escribe, con `forceFill()`. */
    protected $fillable = ['survey_id', 'channel', 'answered_on', 'band', 'asked_by', 'first_visit', 'visit_kind', 'declined', 'answers'];

    /** El sello nunca viaja: ni en `toArray()`, ni en un JSON, ni en un volcado del modelo. */
    protected $hidden = ['seal'];

    protected $casts = [
        'answered_on' => 'date:Y-m-d',
        'first_visit' => 'boolean',
        'declined' => 'boolean',
        'answers' => 'array',
        'returned' => 'boolean',
        'returned_after_days' => 'integer',
    ];

    /** La clave, aleatoria: `Str::uuid()` es v4 (el `HasUuids` de Laravel da v7, ORDENADO por tiempo). */
    protected static function booted(): void
    {
        static::creating(static function (self $response): void {
            if (! is_string($response->getKey()) || $response->getKey() === '') {
                $response->setAttribute($response->getKeyName(), (string) Str::uuid());
            }
        });
    }

    /** @return BelongsTo<Survey, $this> */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /** La franja de un instante, en la hora de PARED del parque que ya trae (quien llama lo pasa por `DisplayTime`). */
    public static function bandAt(CarbonInterface $parkTime): string
    {
        $hour = (int) $parkTime->format('G');

        return $hour < 13 ? self::BAND_MORNING : ($hour < 16 ? self::BAND_MIDDAY : self::BAND_AFTERNOON);
    }

    /** Las respuestas de más de {@see RETENTION_MONTHS} meses, por su día. */
    public function prunable(): Builder
    {
        return static::query()->where('answered_on', '<', now()->subMonths(self::RETENTION_MONTHS)->toDateString());
    }
}
