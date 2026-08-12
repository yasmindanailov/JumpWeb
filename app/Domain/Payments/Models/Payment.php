<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Cobro vía Redsys. Polimórfico (`payable`): `orders` y, en la Fase 6, `event_bookings`.
 * No se guardan tarjetas; solo el resultado del cobro. La verificación de firma e
 * idempotencia se gestionan en la entrega 5.5 (#62).
 */
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    /**
     * Intento de cobro DESCARTADO al iniciar un reintento (auditoría Fase 1, complemento del
     * fix C1). Cuando el cliente reintenta el pago se crea un Payment nuevo y los anteriores
     * `pending` del pedido se marcan `superseded`: NO es `failed` (eso dispararía la
     * idempotencia de denegación y un email al cliente) ni `pending` (no es el intento activo).
     * El `RedsysReturnHandler` lo trata como no-terminal: si una autorización TARDÍA de ese
     * intento llega, captura el pago real y la guarda `$canFulfil`/incidencia decide qué hacer
     * (típicamente la Order ya está PAID por el reintento → incidencia de cobro duplicado).
     */
    public const STATUS_SUPERSEDED = 'superseded';

    /**
     * Lista blanca de asignación masiva (recomendación B, 2026-06-15). Antes `$guarded = []`.
     * Defensa en profundidad: ningún sumidero alimenta estos modelos con input de usuario, pero
     * el allowlist evita que un futuro `create()/update()` exponga columnas sensibles (status,
     * amount, gateway_order) por error.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'payable_type',
        'payable_id',
        'provider',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'gateway_order',
        'auth_code',
        'raw_response',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'raw_response' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Intentos de devolución sobre este Payment (sub-fase 7.2b extendida, #142).
     * Una fila por intento — incluye éxitos, fallos y registros manuales. Ordenado
     * por la vista de Pagos en el panel para mostrar el historial cronológico.
     *
     * @return HasMany<PaymentRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    /**
     * Código `Ds_Response` extraído del JSON `raw_response`. Útil para reusar el
     * motivo real del banco en correos (p. ej. el "Reenviar email de pago" del panel
     * #139, que reenvía `OrderPaymentDeclined` con el dsResponse original para que
     * el cliente vea el mismo texto que recibió la primera vez).
     */
    public function dsResponse(): ?string
    {
        $value = $this->raw_response['Ds_Response'] ?? null;

        return $value === null ? null : (string) $value;
    }
}
