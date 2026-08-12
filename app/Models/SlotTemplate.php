<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plantilla recurrente de franja (horario "tipo" semanal), por zona. De aquí se
 * generan las franjas concretas (`slots`) con el comando `slots:generate`.
 * (En docs/04 figura como `session_templates`; renombrado a `slot_templates`, #63.)
 */
class SlotTemplate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weekday' => 'integer',
        'duration_min' => 'integer',
        'capacity' => 'integer',
        'online_capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
