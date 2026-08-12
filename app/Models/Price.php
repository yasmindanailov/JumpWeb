<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Precio de un producto para una tarifa (matriz, §4bis). Polimórfico: vale para
 * `ticket_types` y `event_packages`. Importes en céntimos (única fuente de verdad, #61).
 */
class Price extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<RateType, $this>
     */
    public function rateType(): BelongsTo
    {
        return $this->belongsTo(RateType::class);
    }
}
