<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Franja concreta (fecha + zona) contra la que se vende el aforo. El aforo se cuenta
 * por OCUPACIÓN: una entrada ocupa una plaza en cada franja que abarca su duración (#60).
 * (En docs/04 figura como `sessions`; renombrado a `slots` para no colisionar con la
 * tabla `sessions` de Laravel, #63.)
 */
class Slot extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_FULL = 'full';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date:Y-m-d', // sin hora: idempotencia de slots:generate y comparaciones exactas
        'capacity' => 'integer',
        'online_capacity' => 'integer',
        'online_sales_open' => 'boolean',
        'capacity_overridden' => 'boolean',
        'seats_taken' => 'integer',
    ];

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Predicado CANÓNICO de "franja ofrecible para vender online": abierta a venta
     * online y no cerrada. Fuente única reutilizada por la compra pública, el alta
     * manual del panel y la re-asignación de slot en gestión de pedido, que antes
     * lo expresaban de tres formas (`online_sales_open == true` vs `!= false`) —
     * equivalentes hoy (la columna es boolean NOT NULL default true) pero una bomba
     * de divergencia latente si el modelo de slots evolucionara. NO incluye el filtro
     * de fecha/zona: eso es propio de cada llamador (≥hoy, fecha exacta, rango de mes).
     */
    public function scopeSellableOnline(Builder $query): Builder
    {
        return $query
            ->where('online_sales_open', true)
            ->where('status', '!=', self::STATUS_CLOSED);
    }
}
