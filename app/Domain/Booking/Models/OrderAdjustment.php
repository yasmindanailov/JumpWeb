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

        // Suplemento de fiesta MIXTA (`docs/specs/cumple-mixto.md` §12). Va PRIMERO porque su
        // contexto no tiene ninguna de las claves de abajo y caería al respaldo, que dice el nombre
        // del producto portador sin explicar de dónde sale el cargo — justo el defecto que `#131`
        // corrigió en la otra rama muda. Aquí el cliente lee «Suplemento por 3 invitados de otro
        // tramo de edad», que se explica solo.
        $mixed = $ctx['mixed_party'] ?? null;
        if (is_array($mixed)) {
            $guests = (int) ($mixed['guests'] ?? 0);
            $target = $mixed['target_name'] ?? null;

            // El DESCUENTO (T4, `specs/cumple-mixto.md` §24.5): la línea espejo del suplemento.
            // El destino viaja en `targets` (lista: el tope puede juntar varios packs baratos en
            // una sola línea); con uno solo se nombra, con varios se cae a la frase genérica —
            // que sigue siendo cierta y no inventa un reparto.
            if (($mixed['credit'] ?? false) === true) {
                $targets = is_array($mixed['targets'] ?? null) ? $mixed['targets'] : [];
                $creditTarget = count($targets) === 1 ? ($targets[0]['name'] ?? null) : null;

                return $creditTarget !== null && $creditTarget !== ''
                    ? trans_choice('tickets.gate_mixed_party_credit_line_named', $guests, ['count' => $guests, 'target' => $creditTarget])
                    : trans_choice('tickets.gate_mixed_party_credit_line', $guests, ['count' => $guests]);
            }

            // ⚠️ `trans_choice` y no `__`: con un solo invitado, la frase decía «Suplemento por 1
            // invitadoS». Un desglose de dinero que no concuerda en número se lee como descuidado
            // justo donde más confianza hace falta.
            //
            // ⚠️ Y con el NOMBRE del pack destino cuando el cargo lo guardó (`[owner, 2026-08-29]`:
            // «que ponga por la diferencia de kids y jump»). Los cargos escritos antes de que el
            // contexto lo llevara caen a la frase sin nombre, que sigue siendo cierta.
            return $target !== null && $target !== ''
                ? trans_choice('tickets.gate_mixed_party_line_named', $guests, ['count' => $guests, 'target' => $target])
                : trans_choice('tickets.gate_mixed_party_line', $guests, ['count' => $guests]);
        }

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
            // ⚠️⚠️ **Estas etiquetas las lee TAMBIÉN el CLIENTE** (viajan en `gate_lines` del ledger,
            // `#154`): viven en `tickets.*` —ES/EN/FR— y no en `admin.*`, que solo existe en
            // español. Medido por HTTP antes del cambio: un cliente en inglés recibía la clave
            // literal en su desglose de dinero. El panel comparte el texto, que es neutro de voz.
            $product = $changes['product_change'] ?? null;
            if (is_array($product) && isset($product['new'])) {
                return __('tickets.gate_change_line_product', ['name' => $product['new']]);
            }
            $qty = $changes['quantity_change'] ?? null;
            if (is_array($qty) && isset($qty['old'], $qty['new'])) {
                $delta = (int) $qty['new'] - (int) $qty['old'];
                if ($delta > 0) {
                    return '+'.$delta.' '.$itemName;
                }
            }

            // ⚠️ **Cambio de FECHA — va el ÚLTIMO de los tres a propósito** (`DECISIONES #145`).
            // Producto y cantidad explican el importe mejor que la franja («+2 Pulsera Jump» dice
            // de dónde salen los euros); la fecha solo habla cuando es la única causa, que es
            // exactamente el caso que llegaba mudo. Ponerlo antes desplazaría etiquetas que hoy
            // funcionan, y este cambio no está para eso.
            $slot = $changes['slot_change'] ?? null;
            if (is_array($slot) && isset($slot['new'])) {
                return __('tickets.gate_change_line_slot', ['when' => $slot['new']]);
            }
        }

        // ⚠️⚠️ **El respaldo dice POR QUÉ se cobra, no solo de qué producto** (`DECISIONES #131`).
        // Devolvía el nombre pelado, y bajo «Pendiente de pagar en el parque» eso se lee como «te
        // cobramos 96,00 € de Cumpleaños Jump» sin decir de dónde sale — mientras su línea hermana,
        // «Resto de la señal de X», sí se explica sola.
        // ▶ **Y no es un caso raro ni de datos sucios**: medido el 2026-08-24 sobre TODOS los
        // `extra_due` de la BD —8, cinco de ellos escritos por el flujo REAL del panel—, **los ocho**
        // caían aquí. El flujo real guarda `context = {"changes": []}`, así que ninguna de las ramas
        // de arriba puede decir nada y ésta es la que sale en producción.
        return __('tickets.gate_change_line', ['product' => $itemName]);
    }
}
