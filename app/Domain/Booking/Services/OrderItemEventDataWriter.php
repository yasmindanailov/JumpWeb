<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\ItemActionOutcome;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Los DATOS DEL EVENTO de un pack ya comprado (el nombre del homenajeado, la
 * edad, las notas…), guardados desde el panel — extracción 4b del desmontaje
 * de `ViewOrder` (`docs/specs/desmontar-view-order.md` §9.6 · sub-paso B).
 *
 * Es la única de las cuatro formas transaccionales de la 4b que no toca ni
 * dinero ni aforo: una transacción MÍNIMA (lock del ítem, diff contra el
 * estado bloqueado, `save` solo si hay diff). No toma el lock de zona/día y
 * por eso NO pertenece al `CRITICAL_RE`.
 *
 * Defense in depth, en este orden (la misma que tenía el handler HTTP de
 * `#147` y después la página):
 *  1. Permiso `orders.edit_event_data` — re-exigido AQUÍ, en el punto de
 *     ejecución, sea quien sea el llamante (`SEC-04`).
 *  2. Semántica: el ítem es un pack con `eventFields` no vacíos.
 *  3. Optimistic lock contra `updated_at` (el token que el modal envió).
 *  4. `sanitizeEventData` + campos obligatorios presentes.
 *  5. Claves LEGACY preservadas: si el pack se editó tras la compra y el
 *     ítem tiene claves que ya no están en `eventFields()`, el formulario no
 *     las envía pero se conservan en vez de borrarlas en silencio.
 *  6. Transacción: `lockForUpdate` + diff contra el estado BLOQUEADO (no
 *     contra el snapshot anterior) + `save` solo si el diff no está vacío.
 *
 * ⚠️ `RGPD-02`: el audit `order_items.event_data_updated` registra SOLO las
 * CLAVES cambiadas, jamás los valores — `event_data` lleva el nombre del
 * homenajeado (PII de menor, art. 9), `AuditLogger::log` es para acciones
 * sin dato personal, y `User::anonymize()` no podría purgar PII enterrada en
 * un payload. `eventDataDiffKeys` es la proyección que lo garantiza.
 *
 * Devuelve un `ItemActionOutcome`: la página traduce el RECHAZO (audit
 * `order_items.event_data_blocked` + aviso) y el «sin cambios»; el audit de
 * ÉXITO se escribe aquí, tras el commit.
 */
class OrderItemEventDataWriter
{
    /**
     * @param  array<string,mixed>  $raw  el `event_data` tal cual llegó del formulario
     */
    public function save(Order $order, OrderItem $item, array $raw, string $optimisticToken, ?User $by): ItemActionOutcome
    {
        if (! ($by?->hasPermission('orders.edit_event_data') ?? false)) {
            return ItemActionOutcome::blocked('permission_denied');
        }

        $ticketType = $item->ticketType;
        if ($ticketType === null || ! $ticketType->isPack()) {
            return ItemActionOutcome::blocked('not_pack');
        }
        $eventFields = $ticketType->eventFields();
        if ($eventFields === []) {
            return ItemActionOutcome::blocked('no_event_fields');
        }

        // Optimistic lock: el `updated_at` que se envió al fillForm debe coincidir con el actual;
        // si otro operador editó entre el render del modal y el submit, se rechaza sin modificar.
        $currentToken = (string) ($item->updated_at?->getTimestamp() ?? '');
        if ($optimisticToken === '' || $optimisticToken !== $currentToken) {
            return ItemActionOutcome::blocked('stale_version');
        }

        $sanitized = $ticketType->sanitizeEventData($raw);
        $missing = $ticketType->missingRequiredEventFields($raw);
        if ($missing !== []) {
            return ItemActionOutcome::blocked('required_missing', ['missing_keys' => $missing]);
        }

        // Preservar claves legacy (mismo razonamiento que en #147): si el pack se editó tras la
        // compra y el item tiene claves que ya no están en `eventFields()` actual, el form NO las
        // envía pero se conservan en lugar de borrarlas silenciosamente.
        $beforeData = is_array($item->event_data) ? $item->event_data : [];
        $schemaKeys = array_column($eventFields, 'key');
        $legacyPreserved = collect($beforeData)
            ->reject(fn ($v, string $k): bool => in_array($k, $schemaKeys, true))
            ->all();
        $sanitized = array_merge($legacyPreserved, $sanitized);

        $diff = null;
        DB::transaction(function () use ($item, $sanitized, &$diff): void {
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $current = is_array($locked->event_data) ? $locked->event_data : [];

            // Re-comparar contra el estado bloqueado, NO contra el snapshot anterior — entre el
            // optimistic_token y este lock pudo haber pasado algo (improbable pero correcto).
            $diff = self::computeEventDataDiff($current, $sanitized);

            if (! self::eventDataDiffIsEmpty($diff)) {
                $locked->forceFill(['event_data' => $sanitized])->save();
            }
        });

        if (self::eventDataDiffIsEmpty($diff)) {
            return ItemActionOutcome::unchanged();
        }

        AuditLogger::log(
            action: 'order_items.event_data_updated',
            target: $item->fresh(),
            payload: [
                'order_code' => $order->code,
                'ticket_type_id' => $item->ticket_type_id,
                // Auditoría Fase 1 · P2 (RGPD art.9): SOLO las CLAVES cambiadas, NUNCA los valores —
                // `event_data` lleva el nombre del homenajeado (PII de menor) y `AuditLogger::log`
                // es para acciones SIN dato personal (su contrato). El detalle vive en el pedido.
                'diff' => self::eventDataDiffKeys($diff),
            ],
        );

        return ItemActionOutcome::done();
    }

    /**
     * @param  array<string,scalar>  $before
     * @param  array<string,scalar>  $after
     * @return array{changed:array<string,array{0:string,1:string}>,added:array<string,string>,removed:array<string,string>}
     */
    private static function computeEventDataDiff(array $before, array $after): array
    {
        $changed = [];
        $added = [];
        $removed = [];

        foreach ($after as $key => $newValue) {
            $newStr = (string) $newValue;
            if (! array_key_exists($key, $before)) {
                $added[$key] = $newStr;

                continue;
            }
            $oldStr = (string) $before[$key];
            if ($oldStr !== $newStr) {
                $changed[$key] = [$oldStr, $newStr];
            }
        }

        foreach ($before as $key => $oldValue) {
            if (! array_key_exists($key, $after)) {
                $removed[$key] = (string) $oldValue;
            }
        }

        return [
            'changed' => $changed,
            'added' => $added,
            'removed' => $removed,
        ];
    }

    /**
     * Proyección del diff a SOLO las CLAVES cambiadas (auditoría Fase 1 · P2): el audit log de
     * `event_data_updated` registra QUÉ campos cambió el operador, NUNCA sus valores — `event_data`
     * porta el nombre del homenajeado (PII de menor, art. 9) y `AuditLogger::log` es para acciones
     * sin dato personal; además `User::anonymize()` no podría purgar PII enterrada en el payload.
     *
     * @param  array{changed:array<string,mixed>,added:array<string,mixed>,removed:array<string,mixed>}  $diff
     * @return array{changed:list<string>,added:list<string>,removed:list<string>}
     */
    private static function eventDataDiffKeys(array $diff): array
    {
        return [
            'changed' => array_keys($diff['changed'] ?? []),
            'added' => array_keys($diff['added'] ?? []),
            'removed' => array_keys($diff['removed'] ?? []),
        ];
    }

    /**
     * @param  array{changed:array<string,mixed>,added:array<string,mixed>,removed:array<string,mixed>}|null  $diff
     */
    private static function eventDataDiffIsEmpty(?array $diff): bool
    {
        if ($diff === null) {
            return true;
        }

        return $diff['changed'] === []
            && $diff['added'] === []
            && $diff['removed'] === [];
    }
}
