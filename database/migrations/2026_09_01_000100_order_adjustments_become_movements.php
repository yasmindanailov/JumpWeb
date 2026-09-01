<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * **La migración de HECHOS del libro** (T1, `specs/desglose-libro.md` §4.8 · `DECISIONES #305`).
 *
 * Las filas de `order_adjustments` pasan a ser hechos con UN discriminador (`type`):
 * `deposit_split` (el reparto de la señal al nacer) · `edit` (el delta ENTERO de una gestión) ·
 * `mixed` (la línea viva de fiesta mixta) · `courtesy` (dinero devuelto sin quitar producto).
 * Hasta hoy eran `extra_due` / `deposit_remainder`, y una BAJADA se guardaba en cascada —un crédito
 * por cubo y un marcador de 0 € cuando ninguno la absorbía— o a medias (el resto de una bajada
 * parcialmente cubierta no se persistía). Esta migración reconstruye esos hechos desde lo que las
 * propias filas llevan en su `context` y, para las ediciones anteriores a `#150` (que no dejaron
 * fila), desde el rastro de `audit_logs` — el único uso del registro como fuente, y solo para datos
 * que no volverán a producirse.
 *
 * ⚠️ **Es SOLO DATOS y es IDEMPOTENTE**: correrla dos veces deja lo mismo que una. Sobre una base
 * vacía (cada arranque de la suite) es un no-op. En producción no hay pedidos (`ENTORNOS`).
 * ⚠️ Autocontenida a propósito: no usa clases de la aplicación, así que sobrevive a cualquier
 * evolución del dominio (las dos migraciones «legacy» de 2026-06-06 dependían de servicios que ya
 * no existen y hubo que neutralizarlas).
 *
 * Orden de los pasos, y por qué ese orden:
 *  1. Cortesías históricas — se calculan con la FORMA VIEJA de las filas (la fórmula del modelo de
 *     dos ejes), así que van antes de renombrar nada.
 *  2. `deposit_remainder` de nacimiento (positivo, sin contexto) → `deposit_split`.
 *  3. `extra_due` con la marca `mixed_party` → `mixed`.
 *  4. Lo que queda de `extra_due` / `deposit_remainder` (cargos, créditos, marcadores) → `edit`.
 *  5. Cada gestión de BAJADA que quedó repartida en varias filas (o en un marcador de 0 €) pasa a
 *     UNA fila con su delta entero, reconstruido desde su propio contexto.
 *  6. Las ediciones anteriores a `#150` que no dejaron fila se recuperan del rastro
 *     `orders.item_edited` (`price_diff_cents`).
 *
 * Lo que no pueda reconstruirse con certeza se deja como está y se anota en el log: la identidad
 * de nacimiento (`OrderLedger`) lo marcará «en revisión», que es su estado real.
 */
return new class extends Migration
{
    private const OLD_EXTRA_DUE = 'extra_due';

    private const OLD_DEPOSIT_REMAINDER = 'deposit_remainder';

    private const TYPE_DEPOSIT_SPLIT = 'deposit_split';

    private const TYPE_EDIT = 'edit';

    private const TYPE_MIXED = 'mixed';

    private const TYPE_COURTESY = 'courtesy';

    public function up(): void
    {
        // Atajo para las bases VACÍAS (cada arranque de la suite): sin ajustes y sin ediciones en el
        // rastro no hay nada que convertir ni que recuperar. ⚠️ Con ediciones en el rastro y CERO
        // ajustes (un pedido 100 % online editado antes de `#150`) sí hay trabajo: el paso 6.
        if (DB::table('order_adjustments')->count() === 0
            && DB::table('audit_logs')->where('action', 'orders.item_edited')->count() === 0) {
            return;
        }

        DB::transaction(function (): void {
            $this->backfillCourtesies();
            $this->renameBirthSplits();
            $this->renameMixedLines();
            $this->renameEdits();
            $this->collapseReductionGroups();
            $this->recoverEditsFromAudit();
        });

        $left = DB::table('order_adjustments')->whereIn('type', [self::OLD_EXTRA_DUE, self::OLD_DEPOSIT_REMAINDER])->count();
        if ($left > 0) {
            throw new RuntimeException("order_adjustments: quedan {$left} filas con tipos anteriores a la T1 del libro");
        }
    }

    public function down(): void
    {
        // Reconstrucción de hechos one-way: volver a la cascada de créditos y al marcador de 0 €
        // reintroduciría la reconstrucción desde el catálogo vivo (el fantasma de la señal).
    }

    // ── 1 · Cortesías históricas ──────────────────────────────────────────────────────────────

    /**
     * Para cada pedido con reembolsos con éxito, la compensación que el modelo de dos ejes DERIVABA
     * al leer (`compensado = max(0, devuelto − (cobrado − lo que aún respalda producto))`) pasa a
     * ser una fila `courtesy` fechada en el último reembolso, atribuida a la reserva que la recibió.
     */
    private function backfillCourtesies(): void
    {
        $orderIds = DB::table('payment_refunds')
            ->join('payments', 'payments.id', '=', 'payment_refunds.payment_id')
            ->where('payment_refunds.status', 'succeeded')
            ->whereIn('payments.payable_type', $this->orderMorphTypes())
            ->distinct()
            ->pluck('payments.payable_id');

        foreach ($orderIds as $orderId) {
            $orderId = (int) $orderId;
            if (DB::table('order_adjustments')->where('order_id', $orderId)->where('type', self::TYPE_COURTESY)->exists()) {
                continue; // ya migrado
            }
            $order = DB::table('orders')->where('id', $orderId)->first();
            if ($order === null) {
                continue;
            }

            $payments = DB::table('payments')->whereIn('payable_type', $this->orderMorphTypes())->where('payable_id', $orderId)->get();
            $paymentIds = $payments->pluck('id')->all();
            $grossPaid = (int) $payments->where('status', 'paid')->sum('amount');
            $refunds = DB::table('payment_refunds')->whereIn('payment_id', $paymentIds)->where('status', 'succeeded')->orderBy('id')->get();
            $refunded = (int) $refunds->sum('amount_cents');
            if ($refunded === 0) {
                continue;
            }

            $items = DB::table('order_items')->where('order_id', $orderId)->orderBy('id')->get();
            $adjustments = DB::table('order_adjustments')->where('order_id', $orderId)->get();
            $orderCancelled = $order->status === 'cancelled';

            // Lo cobrado online que AÚN respalda producto, con la fórmula vieja (cubos sumados por tipo).
            $onlineBacking = 0;
            foreach ($items as $item) {
                if ($orderCancelled || $item->cancelled_at !== null) {
                    continue;
                }
                $gate = (int) $adjustments->where('order_item_id', $item->id)
                    ->whereIn('type', [self::OLD_EXTRA_DUE, self::OLD_DEPOSIT_REMAINDER])->sum('amount_cents');
                $onlineBacking += max(0, $this->charged($item) - $gate);
            }
            $compensado = max(0, $refunded - max(0, $grossPaid - $onlineBacking));
            if ($compensado === 0) {
                continue;
            }

            $principals = $items->whereNull('parent_item_id')->values();
            if ($principals->isEmpty()) {
                Log::warning('libro.migracion.cortesia_sin_reserva', ['order_id' => $orderId, 'cents' => $compensado]);

                continue;
            }
            $last = $refunds->last();
            $at = $last->processed_at ?? $last->requested_at ?? $last->created_at;

            // Reparto: cada reserva toma como mucho lo que ella misma tiene devuelto de forma
            // DIRECTA; lo que quede va a la primera reserva (un reembolso total no se ata a línea).
            $remaining = $compensado;
            $rows = [];
            foreach ($principals as $principal) {
                $lineIds = $items->where('parent_item_id', $principal->id)->pluck('id')->push($principal->id)->all();
                $direct = (int) $refunds->whereIn('order_item_id', $lineIds)->sum('amount_cents');
                $take = min($remaining, $direct);
                if ($take > 0) {
                    $rows[(int) $principal->id] = $take;
                    $remaining -= $take;
                }
            }
            if ($remaining > 0) {
                $first = (int) $principals->first()->id;
                $rows[$first] = ($rows[$first] ?? 0) + $remaining;
            }

            foreach ($rows as $itemId => $cents) {
                DB::table('order_adjustments')->insert([
                    'order_id' => $orderId,
                    'order_item_id' => $itemId,
                    'type' => self::TYPE_COURTESY,
                    'amount_cents' => -$cents,
                    'currency' => $order->currency ?? 'EUR',
                    'reason' => $last->intent ?? 'refund',
                    'context' => json_encode(['refund_id' => (int) $last->id, 'backfilled' => true]),
                    'applied_by' => $last->requested_by,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        }
    }

    // ── 2 · 3 · 4 · Renombrados ──────────────────────────────────────────────────────────────

    private function renameBirthSplits(): void
    {
        DB::table('order_adjustments')
            ->where('type', self::OLD_DEPOSIT_REMAINDER)
            ->where('amount_cents', '>', 0)
            ->whereNull('context')
            ->update(['type' => self::TYPE_DEPOSIT_SPLIT, 'reason' => 'deposit_split']);
    }

    private function renameMixedLines(): void
    {
        $rows = DB::table('order_adjustments')->where('type', self::OLD_EXTRA_DUE)->whereNotNull('context')->get(['id', 'context']);
        foreach ($rows as $row) {
            $context = json_decode((string) $row->context, true);
            if (is_array($context) && array_key_exists('mixed_party', $context)) {
                DB::table('order_adjustments')->where('id', $row->id)->update(['type' => self::TYPE_MIXED]);
            }
        }
    }

    private function renameEdits(): void
    {
        DB::table('order_adjustments')
            ->whereIn('type', [self::OLD_EXTRA_DUE, self::OLD_DEPOSIT_REMAINDER])
            ->update(['type' => self::TYPE_EDIT]);
    }

    // ── 5 · Cada bajada, UNA fila con su delta entero ────────────────────────────────────────

    /**
     * Agrupa las filas `edit` de una misma gestión (misma línea, mismo operador, mismo segundo y
     * mismo contexto). Un grupo con alguna fila NEGATIVA o de 0 € es una BAJADA escrita en cascada
     * (o un marcador): se sustituye por una sola fila cuyo importe es el delta reconstruido desde
     * el contexto (`nueva_cantidad × nuevo_precio − antigua × antiguo`), recorriendo las gestiones
     * de la línea de la ÚLTIMA a la PRIMERA para conocer el precio y la cantidad vigentes en cada
     * momento (el `old` de una gestión posterior es el `new` de la anterior).
     */
    private function collapseReductionGroups(): void
    {
        $items = DB::table('order_items')->get(['id', 'quantity', 'free_quantity', 'unit_price']);
        foreach ($items as $item) {
            $rows = DB::table('order_adjustments')
                ->where('order_item_id', $item->id)
                ->where('type', self::TYPE_EDIT)
                ->orderBy('created_at')->orderBy('id')
                ->get();
            if ($rows->isEmpty()) {
                continue;
            }

            // Grupos = gestiones, en orden cronológico.
            $groups = [];
            foreach ($rows as $row) {
                $key = ((string) $row->applied_by).'|'.substr((string) $row->created_at, 0, 19).'|'.((string) $row->context);
                $groups[$key][] = $row;
            }
            $groups = array_values($groups);

            // Estado vigente DESPUÉS de la última gestión: el de la fila hoy. Se retrocede.
            $qtyAfter = (int) $item->quantity;
            $unitAfter = (int) $item->unit_price;
            $free = (int) $item->free_quantity;
            $charged = static fn (int $qty, int $unit): int => max(0, $qty - $free) * $unit;

            for ($g = count($groups) - 1; $g >= 0; $g--) {
                $group = $groups[$g];
                $context = json_decode((string) $group[0]->context, true);
                $changes = is_array($context) ? ($context['changes'] ?? null) : null;
                $qtyChange = is_array($changes) ? ($changes['quantity_change'] ?? null) : null;
                $unitChange = is_array($changes) ? ($changes['unit_price_change'] ?? null) : null;
                $reconstructible = (is_array($qtyChange) && isset($qtyChange['old'], $qtyChange['new']))
                    || (is_array($unitChange) && isset($unitChange['old'], $unitChange['new']));
                $sum = (int) array_sum(array_map(static fn ($r): int => (int) $r->amount_cents, $group));
                $isReduction = count(array_filter($group, static fn ($r): bool => (int) $r->amount_cents <= 0)) > 0;

                if (! $reconstructible) {
                    if ($isReduction) {
                        Log::warning('libro.migracion.bajada_sin_contexto', ['order_item_id' => $item->id, 'rows' => array_map(static fn ($r) => $r->id, $group), 'sum' => $sum]);
                    }

                    continue;
                }

                $qtyNew = isset($qtyChange['new']) ? (int) $qtyChange['new'] : $qtyAfter;
                $qtyOld = isset($qtyChange['old']) ? (int) $qtyChange['old'] : $qtyNew;
                $unitNew = isset($unitChange['new']) ? (int) $unitChange['new'] : $unitAfter;
                $unitOld = isset($unitChange['old']) ? (int) $unitChange['old'] : $unitNew;
                $delta = $charged($qtyNew, $unitNew) - $charged($qtyOld, $unitOld);
                $qtyAfter = $qtyOld;
                $unitAfter = $unitOld;

                if (! $isReduction) {
                    if ($sum !== $delta) {
                        Log::warning('libro.migracion.subida_no_cuadra_con_contexto', ['order_item_id' => $item->id, 'sum' => $sum, 'delta' => $delta]);
                    }

                    continue;
                }
                if ($delta >= 0) {
                    Log::warning('libro.migracion.bajada_con_delta_no_negativo', ['order_item_id' => $item->id, 'sum' => $sum, 'delta' => $delta]);

                    continue;
                }
                if ($free > 0) {
                    Log::info('libro.migracion.bajada_con_unidades_gratis', ['order_item_id' => $item->id, 'free' => $free]);
                }

                // La fila que se queda: el crédito más antiguo, o el marcador si no había otra.
                $kept = null;
                foreach ($group as $row) {
                    if ($row->reason !== 'reduction_marker') {
                        $kept = $row;
                        break;
                    }
                }
                $kept ??= $group[0];
                DB::table('order_adjustments')->where('id', $kept->id)->update([
                    'amount_cents' => $delta,
                    'reason' => $kept->reason === 'reduction_marker' ? 'item_edit_reduction' : $kept->reason,
                ]);
                $others = array_values(array_filter(array_map(static fn ($r) => (int) $r->id, $group), static fn (int $id): bool => $id !== (int) $kept->id));
                if ($others !== []) {
                    DB::table('order_adjustments')->whereIn('id', $others)->delete();
                }
            }
        }
    }

    // ── 6 · Las ediciones anteriores a #150, desde el rastro ─────────────────────────────────

    private function recoverEditsFromAudit(): void
    {
        $entries = DB::table('audit_logs')
            ->where('action', 'orders.item_edited')
            ->orderBy('id')
            ->get(['id', 'user_id', 'target_id', 'payload', 'created_at']);

        foreach ($entries as $entry) {
            $payload = json_decode((string) $entry->payload, true);
            if (! is_array($payload)) {
                continue;
            }
            $diff = (int) ($payload['price_diff_cents'] ?? 0);
            $itemId = (int) ($payload['order_item_id'] ?? 0);
            if ($diff === 0 || $itemId === 0) {
                continue;
            }
            $item = DB::table('order_items')->where('id', $itemId)->first(['id', 'order_id']);
            if ($item === null) {
                continue;
            }
            $second = substr((string) $entry->created_at, 0, 19);
            $exists = DB::table('order_adjustments')
                ->where('order_item_id', $itemId)
                ->where('type', self::TYPE_EDIT)
                ->where('created_at', '>=', $second)
                ->where('created_at', '<', date('Y-m-d H:i:s', strtotime($second) + 1))
                ->exists();
            if ($exists) {
                continue;
            }

            $changes = [];
            if (isset($payload['from_quantity'], $payload['to_quantity']) && (int) $payload['from_quantity'] !== (int) $payload['to_quantity']) {
                $changes['quantity_change'] = ['old' => (int) $payload['from_quantity'], 'new' => (int) $payload['to_quantity']];
            }
            if (isset($payload['from_unit_price'], $payload['to_unit_price']) && (int) $payload['from_unit_price'] !== (int) $payload['to_unit_price']) {
                $changes['unit_price_change'] = ['old' => (int) $payload['from_unit_price'], 'new' => (int) $payload['to_unit_price']];
            }
            $order = DB::table('orders')->where('id', $item->order_id)->first(['user_id', 'currency']);

            DB::table('order_adjustments')->insert([
                'order_id' => (int) $item->order_id,
                'order_item_id' => $itemId,
                'type' => self::TYPE_EDIT,
                'amount_cents' => $diff,
                'currency' => $order->currency ?? 'EUR',
                'reason' => $diff < 0 ? 'item_edit_reduction' : 'item_edit',
                'context' => json_encode(['changes' => $changes, 'recovered_from_audit' => (int) $entry->id]),
                'applied_by' => $entry->user_id ?? $order->user_id,
                'created_at' => $entry->created_at,
                'updated_at' => $entry->created_at,
            ]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    /** El subtotal cobrado de una línea, como `OrderItem::chargedSubtotalCents()` (con signo de crédito). */
    private function charged(object $item): int
    {
        $units = max(0, (int) $item->quantity - (int) ($item->free_quantity ?? 0));
        $subtotal = $units * (int) $item->unit_price;

        return ! empty($item->is_credit) ? -$subtotal : $subtotal;
    }

    /** Los valores de `payments.payable_type` que designan un pedido (alias de morph y FQCN históricos). */
    private function orderMorphTypes(): array
    {
        return ['order', 'App\\Domain\\Booking\\Models\\Order', 'App\\Models\\Order'];
    }
};
