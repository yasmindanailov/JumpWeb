<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fase 6 · menores a cargo, tanda 4 — **una ENTRADA asignada a una persona a cargo**
 * (`docs/specs/menores-a-cargo.md` §4.6–§4.10, §9.9.3 D4).
 *
 * La posee IDENTITY y referencia el ítem del pedido por su id ENTERO (`order_item_id`): Booking no
 * puede mirar a Identity (`ModuleBoundariesTest`), así que la flecha va al revés y por contrato
 * (`Booking\Contracts\CheckoutLines`). No hay relación Eloquent hacia `OrderItem` a propósito.
 *
 * Tres reglas que son la fila entera:
 *  - **Es un CONJUNTO, no una lista con posiciones**: «estas entradas son para Lucas y Vera; el
 *    resto, adultos». Un menor no usa dos entradas a la vez (único `(order_item_id, dependent_id)`)
 *    y nunca hay más menores que unidades — se exige al escribir y se DERIVA al leer, porque la
 *    cantidad puede bajar desde el panel sin que Booking avise a nadie (D4).
 *  - **Es una REFERENCIA** (§4.4): un menor con entradas asignadas se DESVINCULA al quitarlo, no se
 *    borra — FK RESTRICT hacia `dependents`, y `Dependent::referenced()` la cuenta.
 *  - **Cae con la línea**: FK CASCADE desde `order_items`. Una asignación sin su línea no significa
 *    nada, y así la limpieza de go-live (que borra pedidos antes que menores) y los verificadores
 *    no tienen que conocerla. Y la borra `User::anonymize()` como vacía `guest_data` (D6).
 *
 * Único escritor: `Identity\Services\DependentAssigner`, bajo el lock de la fila del titular.
 */
#[Fillable(['dependent_id', 'order_item_id'])]
class DependentAssignment extends Model
{
    /** Se escribe una vez y no se edita: quitar y poner son filas distintas. */
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Dependent, $this>
     */
    public function dependent(): BelongsTo
    {
        return $this->belongsTo(Dependent::class);
    }
}
