<?php

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Services\Surveys\QuestionSchema;
use App\Domain\Platform\Services\Translated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * **Una encuesta** (`docs/specs/encuestas.md` §4.1, T1; `DECISIONES #740`): su clave, su clase, sus textos, su
 * ventana y sus preguntas. Ver la migración `create_surveys_tables` para lo que significa cada columna.
 *
 * ⚠️ Vive en `Platform` como `Experiment`: es configuración del producto que leen la puerta (Identity), el correo
 * y el cuadro, y no mira a ningún módulo. **Una viva por clase** (`[DECIDIDO owner]`): la regla la aplica el
 * formulario al encender (`anotherRunning()`), y `runningOfKind()` es lo que la puerta y el comando preguntan.
 *
 * @property int $id
 * @property string $key
 * @property array<string, string> $name
 * @property ?array<string, string> $intro
 * @property string $kind
 * @property bool $active
 * @property ?Carbon $starts_at
 * @property ?Carbon $ends_at
 * @property ?array<int, mixed> $questions lo que guardó el panel; `questionList()` es quien lo normaliza
 * @property ?int $created_by
 */
class Survey extends Model
{
    public const KIND_INTERNAL = 'internal';

    public const KIND_EXTERNAL = 'external';

    /** @var list<string> */
    public const KINDS = [self::KIND_INTERNAL, self::KIND_EXTERNAL];

    public const KEY_RE = '/^[a-z][a-z0-9_-]{0,47}$/';

    protected $fillable = ['key', 'name', 'intro', 'kind', 'active', 'starts_at', 'ends_at', 'questions', 'created_by'];

    protected $casts = [
        'name' => 'array',
        'intro' => 'array',
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'questions' => 'array',
    ];

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /** Activa y dentro de su ventana: es lo único que decide si se ofrece. */
    public function isRunning(): bool
    {
        return $this->active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    /** @return list<array{key: string, type: string, required: bool, label: array<string, string>, options: list<array{key: string, label: array<string, string>}>}> */
    public function questionList(): array
    {
        return QuestionSchema::normalize($this->questions);
    }

    public function displayName(?string $locale = null): string
    {
        return (string) (Translated::pick($this->name ?? [], $locale) ?? $this->key);
    }

    public function displayIntro(?string $locale = null): ?string
    {
        $intro = Translated::pick($this->intro ?? [], $locale);

        return is_string($intro) && trim($intro) !== '' ? $intro : null;
    }

    /** Con respuestas guardadas, las claves y los tipos de las preguntas quedan bloqueados (§4.1). */
    public function hasResponses(): bool
    {
        return $this->responses()->exists();
    }

    /** La encuesta VIVA de una clase, si la hay (una como máximo, `#740`). */
    public static function runningOfKind(string $kind): ?self
    {
        return self::query()->where('kind', $kind)->where('active', true)->orderBy('id')->get()
            ->first(static fn (self $survey): bool => $survey->isRunning());
    }

    /** ¿Hay OTRA encuesta viva de esta clase? (la guarda del formulario al encender). */
    public static function anotherRunning(string $kind, ?int $exceptId): bool
    {
        return self::query()->where('kind', $kind)->where('active', true)
            ->when($exceptId !== null, static fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->get()
            ->contains(static fn (self $survey): bool => $survey->isRunning());
    }
}
