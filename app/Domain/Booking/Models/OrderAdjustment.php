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
 * lleva el delta completo y **la liquidación se DERIVA** (`Booking\Services\GateBuckets` mientras
 * el modelo de dos ejes siga leyendo; el libro de la T2 después).
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
