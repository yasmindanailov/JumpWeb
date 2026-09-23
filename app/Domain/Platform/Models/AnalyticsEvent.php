<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * **Un hecho del libro de eventos** (`docs/specs/analitica.md` §4.2). El nombre es una clave del contrato
 * (`Platform\Services\Analytics\Contract`); lo escribe la ingesta (`EventIngestor`, hechos del cliente) o el
 * `Recorder` (hechos del servidor). Ver la migración `create_analytics_events`.
 *
 * ⚠️ **La poda va ANTES que la de sesiones** en la lista de `model:prune`: sin claves foráneas, el orden
 * es lo único que evita eventos huérfanos entre las dos pasadas. Y se poda por `received_at`, la verdad
 * temporal (el reloj del cliente solo ordena dentro de la sesión).
 *
 * @property int $id
 * @property string $event_id
 * @property ?int $session_id
 * @property ?string $visitor_id
 * @property ?int $user_id
 * @property string $name
 * @property ?string $route
 * @property ?array<string, mixed> $props
 * @property Carbon $occurred_at
 * @property Carbon $received_at
 * @property ?int $order_id
 * @property ?int $payment_id
 * @property ?int $refund_id
 */
class AnalyticsEvent extends Model
{
    use MassPrunable;

    public $timestamps = false;

    /** Solo escriben la ingesta y el recorder, con filas ya validadas contra el contrato. */
    protected $guarded = [];

    protected $casts = [
        'session_id' => 'integer',
        'user_id' => 'integer',
        'props' => 'array',
        'occurred_at' => 'datetime',
        'received_at' => 'datetime',
        'order_id' => 'integer',
        'payment_id' => 'integer',
        'refund_id' => 'integer',
    ];

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('received_at', '<', now()->subMonths(AnalyticsSession::RETENTION_MONTHS));
    }
}
