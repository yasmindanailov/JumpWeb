<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * **Una sesión del libro de eventos** (`docs/specs/analitica.md` §4.1): un visitante con menos de 30 minutos
 * entre dos eventos. Ver la migración `create_analytics_sessions` para lo que significa cada columna.
 *
 * ⚠️ Vive en `Platform` como `AuditLog`: todos los módulos pueden escribir en el libro con escalares y el
 * libro no mira a ninguno. Así el grafo de `ModuleBoundariesTest` no cambia.
 *
 * ⚠️ **La poda es por `model:prune`** (`routes/console.php`), a los 25 meses del último evento: es la
 * condición de la exención (guía AEPD 2024) y, por eso mismo, una obligación y no una limpieza. Un
 * `Prunable` que no entre en esa lista no se poda nunca (`InvitationReply` ya lo aprendió).
 *
 * @property int $id
 * @property string $visitor_id
 * @property ?int $user_id
 * @property Carbon $started_at
 * @property Carbon $last_seen_at
 * @property string $surface
 * @property ?string $entry_route
 * @property ?string $referrer_host
 * @property ?string $utm_source
 * @property ?string $utm_medium
 * @property ?string $utm_campaign
 * @property ?string $utm_content
 * @property ?string $utm_term
 * @property ?string $ref
 * @property ?array<string, string> $click_ids
 * @property ?string $device
 * @property ?string $locale
 * @property ?array<string, bool> $consent
 * @property bool $is_bot
 * @property bool $is_internal
 */
class AnalyticsSession extends Model
{
    use MassPrunable;

    /** Meses que se conserva una sesión (guía AEPD 2024: ≤ 25). */
    public const RETENTION_MONTHS = 25;

    /** Minutos de inactividad que cierran una sesión. */
    public const IDLE_MINUTES = 30;

    public $timestamps = false;

    /** Solo escribe el propio libro (`SessionResolver`), nunca una petición: sin lista de rellenables. */
    protected $guarded = [];

    protected $casts = [
        'user_id' => 'integer',
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'click_ids' => 'array',
        'consent' => 'array',
        'is_bot' => 'boolean',
        'is_internal' => 'boolean',
    ];

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('last_seen_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }
}
