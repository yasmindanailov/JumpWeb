<?php

namespace App\Domain\Payments\Models;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de un intento de reembolso (sub-fase 7.2b extendida, #142).
 *
 * Modela el evento "intento de devolución" independientemente del estado del
 * pedido. La acumulación de filas `succeeded` por `payment_id` da el total
 * realmente devuelto al cliente (útil para reembolsos parciales futuros vía
 * gestión por-item — `order_item_id` no nulo).
 *
 * Estados:
 *  - `pending`   → fila creada, REST call en vuelo (o modo manual aún sin commit final).
 *  - `succeeded` → REST devolvió 0900 (o modo manual confirmado por el operador).
 *  - `failed`    → REST devolvió código distinto a 0900 (`gateway_denied`) o falló
 *                  por red/timeout (`transport_error_check_portal`).
 */
class PaymentRefund extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const MODE_REST = 'rest';

    public const MODE_MANUAL = 'manual';

    public const FAILURE_TRANSPORT = 'transport_error_check_portal';

    public const FAILURE_GATEWAY_DENIED = 'gateway_denied';

    public const FAILURE_UNKNOWN = 'unknown';

    /** `Ds_Response` para éxito de devolución según manual Redsys §refund. */
    public const REDSYS_REFUND_SUCCESS_CODE = '0900';

    /** Marcador de gateway_response_code para reembolsos registrados como manuales. */
    public const MANUAL_RESPONSE_MARKER = 'MANUAL';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'raw_response' => 'array',
    ];

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
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
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
