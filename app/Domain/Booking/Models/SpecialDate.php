<?php

namespace App\Domain\Booking\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Excepción de calendario: festivo, víspera, cierre u horario especial. Marca la
 * tarifa que aplica ese día (la usa RateResolver) y permite cerrar la venta.
 */
class SpecialDate extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date:Y-m-d', // sin hora: comparación exacta en el RateResolver y el generador
        'is_closed' => 'boolean',
        'note' => 'array',
    ];

    /**
     * @return BelongsTo<RateType, $this>
     */
    public function rateType(): BelongsTo
    {
        return $this->belongsTo(RateType::class);
    }
}
