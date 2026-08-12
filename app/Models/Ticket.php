<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrada emitida (una por admisión), con su QR. `qr_token` aleatorio e
 * impredecible (#62). Hoy solo se usa el estado `purchased` (lo escribe
 * `TicketIssuer`); el ciclo de canje en puerta (prepared/redeemed/void) se
 * retiró junto con el sistema "preparado/no preparado" (#202).
 */
class Ticket extends Model
{
    public const STATUS_PURCHASED = 'purchased';

    protected $guarded = [];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * @return BelongsTo<Slot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }
}
