<?php

namespace App\Domain\Booking\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ajuste financiero del pedido FUERA de Redsys (sub-fase 7.2e cimientos).
 *
 * Modela dinero que el CLIENTE DEBE AL PARQUE por una edición que subió el
 * importe del pedido. Se cobra en persona al llegar al parque — el cobro
 * presencial es implícito (al pasar el slot del item, se asume cobrado;
 * decisión clienta sesión 7.2e).
 *
 * Dimensión ortogonal a `PaymentRefund`: aquí dinero `cliente → parque` (en
 * efectivo/datáfono presencial), allí dinero `parque → cliente` (Redsys o
 * reconocido manualmente). Cero solape conceptual.
 *
 * `type` enum extensible. En v1 solo `extra_due`. Si se necesita registrar
 * explícitamente "cobrado en puerta" en una v2 (trazabilidad fina), se añade
 * `collected_in_person` sin migración.
 */
class OrderAdjustment extends Model
{
    public const TYPE_EXTRA_DUE = 'extra_due';

    /**
     * Resto de la SEÑAL/DEPÓSITO a cobrar presencialmente (#225). Cuando un producto
     * cobra online solo una señal, la parte del valor base NO cobrada online
     * (`valor_base − señal`) se registra como un ajuste de este tipo, atado a la línea,
     * conocido DESDE LA CREACIÓN del pedido. Vive en el mismo "bucket de puerta" que
     * `extra_due` pero SEPARADO: `extra_due` nace de ediciones posteriores (delta SOBRE
     * el valor) y alimenta `totalWithChanges`; el resto-señal es parte del valor base y
     * NO debe sumarse a `extra_due` (rompería `gateLineLabel`/`totalWithChanges`). Lo
     * consume {@see Order::itemDepositRemainderCents} → {@see Order::itemCollectedCents}.
     */
    public const TYPE_DEPOSIT_REMAINDER = 'deposit_remainder';

    /**
     * Tipo polivalente reservado para v2 (cuando el negocio pida trazabilidad
     * fina del cobro presencial). Documentado aquí para que cualquier helper
     * que aplique filtros sobre `type` sepa que la familia es extensible.
     */
    public const TYPE_COLLECTED_IN_PERSON = 'collected_in_person';

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

    /**
     * Etiqueta compacta del ajuste para el desglose "A cobrar en el parque"
     * (sub-fase 7.2e.5, decisión #171). Formato delta elegido por la clienta:
     *  - Cambio de cantidad de un item:  "+2 Pulsera Jump"
     *  - Cambio de producto:             "Cambio a Pack Kids"
     *  - Complementos añadidos/subidos:  "+3 Calcetines" (varios → unidos por ", ")
     *
     * Lee del `context` estructurado que `executeItemEdit` persiste (changes con
     * old/new + addon_change con added/updated). Si el ajuste es legacy (context
     * solo con claves, o vacío) cae limpio al NOMBRE del item — nunca rompe ni
     * inventa una cantidad que no consta. El importe lo pinta el blade aparte.
     */
    public function breakdownLabel(): string
    {
        $ctx = is_array($this->context) ? $this->context : [];
        $itemName = $this->orderItem?->ticketType?->tr('name') ?? '—';

        // Complementos: lista de añadidos (+qty) y subidos (+delta).
        $addon = $ctx['addon_change'] ?? null;
        if (is_array($addon)) {
            $parts = [];
            foreach ($addon['added'] ?? [] as $a) {
                $qty = (int) ($a['qty'] ?? 0);
                if ($qty > 0) {
                    $parts[] = '+'.$qty.' '.($a['name'] ?? '—');
                }
            }
            foreach ($addon['updated'] ?? [] as $u) {
                $delta = (int) ($u['new'] ?? 0) - (int) ($u['old'] ?? 0);
                if ($delta > 0) {
                    $parts[] = '+'.$delta.' '.($u['name'] ?? '—');
                }
            }
            if ($parts !== []) {
                return implode(', ', $parts);
            }
        }

        // Item: cambio de producto tiene prioridad sobre el de cantidad.
        $changes = $ctx['changes'] ?? null;
        if (is_array($changes)) {
            $product = $changes['product_change'] ?? null;
            if (is_array($product) && isset($product['new'])) {
                return __('admin.orders.order_financial.breakdown.product_change', ['name' => $product['new']]);
            }
            $qty = $changes['quantity_change'] ?? null;
            if (is_array($qty) && isset($qty['old'], $qty['new'])) {
                $delta = (int) $qty['new'] - (int) $qty['old'];
                if ($delta > 0) {
                    return '+'.$delta.' '.$itemName;
                }
            }
        }

        return $itemName;
    }
}
