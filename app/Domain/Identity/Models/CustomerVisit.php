<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fase 6 · subsistema A — una VISITA ACREDITADA en la puerta (`docs/specs/identidad-qr-puerta.md`
 * §8.3, §9.2 A·4): el hecho observable del que saldrán los JumpPoints de la fuente «visita»
 * (`lealtad-jumppoints.md` §8.1). Una fila por (titular, día), escrita por un acto EXPLÍCITO del
 * empleado; se escribe una vez y no se edita.
 *
 * Único escritor: `Identity\Services\GateVisits`.
 */
#[Fillable(['user_id', 'visited_on', 'registered_by'])]
class CustomerVisit extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['visited_on' => 'immutable_date'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
