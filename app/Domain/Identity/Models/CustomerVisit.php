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
 *
 * ▶ `source` (`#756`): de dónde vino —el escaneo del carné o la búsqueda por correo o móvil—; `null` en las de antes (el
 * botón retirado en `#234`). No se reconstruyen visitas desde el rastro de la puerta (`[DECIDIDO owner]` 27-09): la
 * historia empieza cuando la puerta empezó a acreditar.
 */
#[Fillable(['user_id', 'visited_on', 'registered_by', 'source'])]
class CustomerVisit extends Model
{
    public const UPDATED_AT = null;

    /** El escaneo del carné QR (`#741`). */
    public const SOURCE_CARD = 'card';

    /** La búsqueda por correo o móvil en la puerta (`#756`). */
    public const SOURCE_LOOKUP = 'lookup';

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
