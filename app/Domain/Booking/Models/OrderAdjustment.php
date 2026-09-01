<?php

namespace App\Domain\Booking\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * **Un HECHO de dinero de una línea del pedido** (`specs/desglose-libro.md` §4.2, `DECISIONES #305`).
 *
 * Desde la T1 del libro cada fila es una de estas cuatro cosas, y `type` es el ÚNICO discriminador:
 *
 * | `type`          | Qué es                                                                   | Importe |
 * |-----------------|--------------------------------------------------------------------------|---------|
 * | `deposit_split` | El reparto de la SEÑAL al nacer: la parte del valor de la línea que NO se cobró online (la escribe `OrderCreator`; principal y, en la Opción A del origen, cada complemento de pago de un pack con señal) | ≥ 0 |
 * | `edit`          | El DELTA ENTERO de una gestión sobre la línea (cantidad · producto · fecha con re-tarifa · complemento añadido/subido · re-escala per-invitado). Una fila por gestión y por línea afectada, con signo | ≠ 0 |
 * | `mixed`         | El suplemento o el descuento de fiesta mixta: la línea VIVA que `MixedPartySurcharge` reconcilia en el sitio (su gemelo de la línea hija) | con signo |
 * | `courtesy`      | La compensación: dinero devuelto SIN que desapareciera producto, escrito al reembolsar (`Order::executePartialRefund` / `executeFullRefund`) | ≤ 0 |
 *
 * ⚠️⚠️ **Hasta la T1 aquí vivían `extra_due` y `deposit_remainder`, y una bajada se escribía en
 * CASCADA** —un crédito contra el cubo de ediciones, otro contra el resto de la señal, y **un
 * marcador de 0 €** cuando ninguno la absorbía—, de modo que el importe de una bajada 100 % online
 * **no existía en ninguna fila** y se RECONSTRUÍA al leer con la señal del catálogo VIVO. Ésa era
 * la raíz del fantasma de la señal (`DEUDA.md`, 2026-09-01). Ahora el hecho es la fila: `edit`
 * lleva el delta completo y **la liquidación se DERIVA** del saldo del libro
 * (`Booking\Services\OrderBook`, sobre los hechos sumados por línea en `LineFacts`).
 *
 * ⚠️ `deposit_split` NO es un movimiento: no cambia lo que vale la línea, dice cómo se repartió al
 * nacer. Por eso no entra en `Δ(i)` y sí en «lo que esta línea aportó al cobro online»
 * (`nac − reparto`).
 *
 * ⚠️ La columna `amount_cents` es SIGNED desde la migración `2026_06_06_000002`.
 */
class OrderAdjustment extends Model
{
    /** El reparto de la señal al nacer (≥ 0, contexto nulo). */
    public const TYPE_DEPOSIT_SPLIT = 'deposit_split';

    /** El delta entero de una gestión sobre la línea (con signo, ≠ 0). */
    public const TYPE_EDIT = 'edit';

    /** El suplemento (+) o descuento (−) de fiesta mixta: línea viva, reconciliada en el sitio. */
    public const TYPE_MIXED = 'mixed';

    /** La compensación: dinero devuelto sin que desapareciera producto (≤ 0). */
    public const TYPE_COURTESY = 'courtesy';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_DEPOSIT_SPLIT,
        self::TYPE_EDIT,
        self::TYPE_MIXED,
        self::TYPE_COURTESY,
    ];

    /**
     * Los tipos que MUEVEN el valor de la línea respecto de su nacimiento (`Δ(i)` en la spec §4.1).
     * `deposit_split` reparte, no mueve; `courtesy` no toca la fila (es una línea de valor aparte).
     *
     * @var list<string>
     */
    public const VALUE_DELTA_TYPES = [self::TYPE_EDIT, self::TYPE_MIXED];

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'context' => 'array',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function isDepositSplit(): bool
    {
        return $this->type === self::TYPE_DEPOSIT_SPLIT;
    }

    public function isEdit(): bool
    {
        return $this->type === self::TYPE_EDIT;
    }

    public function isMixed(): bool
    {
        return $this->type === self::TYPE_MIXED;
    }

    public function isCourtesy(): bool
    {
        return $this->type === self::TYPE_COURTESY;
    }

    /** ¿Mueve el valor de la línea respecto de su nacimiento? (`edit` o `mixed`). */
    public function isValueDelta(): bool
    {
        return in_array($this->type, self::VALUE_DELTA_TYPES, true);
    }

    // ▶ `breakdownLabel()` vivió aquí hasta la T3·4 del libro (`DECISIONES #315`): la etiqueta de
    // una gestión la compone `Booking\Services\MovementLabel` (`edit` desde el `context`
    // estructurado; `mixed` desde la marca `mixed_party`) — UNA vez, para las nueve superficies.
}
