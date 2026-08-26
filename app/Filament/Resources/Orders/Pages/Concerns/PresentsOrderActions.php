<?php

namespace App\Filament\Resources\Orders\Pages\Concerns;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Payments\Models\PaymentRefund;
use App\Notifications\OrderItemRefunded;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

/**
 * La PRESENTACIÓN de las acciones de `ViewOrder`: las Notification de
 * Filament que ve el operador (bloqueos, éxitos, fallos de pasarela y de
 * batch), los previews reactivos de importe de los modales y las opciones
 * de sus Selects/CheckboxList.
 *
 * Extracción 2 del desmontaje de `ViewOrder`
 * (`docs/specs/desmontar-view-order.md` §4.1, spec §9.3): trait de la capa
 * de entrega sobre la MISMA clase — la palabra de la spec era «Presenter»,
 * pero la medición de dependencias mandó (spec §9.3): los dos previews
 * componen sobre cómputos del DOMINIO (`computeEditPricing`,
 * `computeAddonPricing`, `applyGroupChoices`) que la extracción 4 va a
 * mover — una clase Presenter habría fijado HOY firmas que la 4 volvería a
 * romper. El trait separa la responsabilidad sin inventar un contrato
 * prematuro; la doctrina de `#37`: el segundo consumidor es quien revela la
 * forma del enchufe.
 *
 * ⚠️ Dependencias hacia la clase que lo compone (se resuelven al aplanar):
 * `$this->record` · `$this->resolveItem()` · `$this->refundItemOptionLabel()`
 * · `$this->eurosFromCents()` · `self::groupFieldName()` · los cómputos de
 * dominio citados arriba (extracción 4) · `$this->calendarSelectedDate` y
 * los ayudantes de complementos (`itemChoiceGroups`, `normalizeAddonEdits`)
 * — los previews leen la fecha efectiva del calendario a través del estado
 * Livewire, como fija su docblock.
 */
trait PresentsOrderActions
{
    /**
     * Render del feedback cuando el orquestador devuelve `ok=false`. Distingue:
     *  - `gateway_failed` con `failure_reason=transport_error_check_portal`:
     *    el operador DEBE verificar manualmente en el portal antes de reintentar.
     *  - `gateway_failed` con `failure_reason=gateway_denied`: banco denegó; muestra
     *    el `Ds_Response` para que el operador entienda el motivo.
     *  - `inflight_refund`: ya hay un refund pending para el mismo Payment (otro click).
     *  - `no_paid_payment`: defensivo, no debería ocurrir si `canBeRefunded()=true`.
     *  - Razones de bloqueo (`already_*`, `not_paid`, etc): reutiliza el helper.
     */
    private function renderRefundFailure(array $result): void
    {
        $reason = (string) ($result['reason'] ?? 'unknown');

        if ($reason === 'gateway_failed') {
            /** @var PaymentRefund|null $refund */
            $refund = $result['refund'] ?? null;
            $isTransport = $refund?->failure_reason === PaymentRefund::FAILURE_TRANSPORT;
            $msg = $isTransport
                ? __('admin.orders.actions.refund.transport_error')
                : __('admin.orders.actions.refund.gateway_denied', [
                    'code' => $result['gateway_response_code'] ?? '—',
                ]);

            Notification::make()
                ->title(__('admin.orders.actions.refund.failed_title'))
                ->body($msg)
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($reason === 'inflight_refund') {
            Notification::make()
                ->title(__('admin.orders.actions.refund.inflight_title'))
                ->body(__('admin.orders.actions.refund.inflight_body'))
                ->warning()
                ->send();

            return;
        }

        if ($reason === 'no_paid_payment') {
            Notification::make()
                ->title(__('admin.orders.actions.refund.no_paid_payment'))
                ->danger()
                ->send();

            return;
        }

        // Razones de bloqueo del modelo (already_*, not_paid, etc.).
        $this->blockedNotification('refund', $reason);
    }

    /**
     * Opciones del Select del modal: label legible por tipo + (cuando aplica)
     * detalles contextuales que ayudan al operador a elegir (importe del reembolso,
     * fecha de cancelación, etc.). Solo se exponen los tipos disponibles para
     * este record concreto (`availableResendEmailTypes()`).
     *
     * @return array<string, string>
     */
    private static function resendTypeOptions(Order $record): array
    {
        return collect($record->availableResendEmailTypes())
            ->mapWithKeys(fn (string $type): array => [
                $type => __('admin.orders.actions.resend.types.'.$type),
            ])
            ->all();
    }

    private function blockedNotification(string $actionKey, string $reason): void
    {
        Notification::make()
            ->title(__("admin.orders.actions.{$actionKey}.blocked", [
                'reason' => __("admin.orders.actions.reasons.{$reason}"),
            ]))
            ->danger()
            ->send();
    }

    /**
     * Opciones del selector de producto: mismo `type` + misma `zone` +
     * vendibles (sub-fase 7.2e.3, #167). El producto ACTUAL se incluye
     * siempre aunque dejara de ser vendible tras la compra, para que el
     * operador pueda mantenerlo sin que el modal le obligue a cambiarlo.
     *
     * @return array<int, string>
     */
    private function sameScopeProductOptions(OrderItem $item): array
    {
        $type = $item->ticketType;
        if ($type === null || $type->zone_id === null) {
            return [];
        }

        $types = TicketType::query()
            ->where('type', $type->type)
            ->where('zone_id', $type->zone_id)
            ->where('is_sellable', true)
            ->orderBy('position')
            ->get();

        if (! $types->contains('id', $type->id)) {
            $types->push($type);
        }

        return $types
            ->sortBy('position')
            ->mapWithKeys(fn (TicketType $t): array => [$t->id => $t->tr('name')])
            ->all();
    }

    /**
     * Bloque HTML del recálculo en vivo del importe (Placeholder reactivo,
     * sub-fase 7.2e.3, #167). Lee el producto + cantidad del estado del form
     * (`Get`) y la fecha efectiva del estado Livewire del calendario; computa
     * el precio nuevo con `RateResolver` (tarifa del día) y muestra
     * actual → nuevo + el signo del diff (cobro en puerta / reembolso / sin
     * cambio).
     */
    private function priceDiffPreview(OrderItem $item, Get $get): HtmlString
    {
        $newTypeId = (int) ($get('product_id') ?: $item->ticket_type_id);
        $newQty = max(1, (int) ($get('quantity') ?: $item->quantity));
        $dateStr = $this->calendarSelectedDate ?: $item->slot?->date?->toDateString();

        $pricing = $this->itemEditPricing()->computeEditPricing($item, $newTypeId, $newQty, $dateStr);
        $oldTotal = $pricing['old'];

        $rows = '<div class="text-sm" style="display:flex;gap:1.5rem;flex-wrap:wrap;">'
            .'<span><span style="opacity:.7;">'.e(__('admin.orders.manage_item.price_current')).':</span> <strong>'.$this->eurosFromCents($oldTotal).' €</strong></span>';

        if ($pricing['new'] === null) {
            $rows .= '<span style="opacity:.7;">'.e(__('admin.orders.manage_item.price_new')).': —</span></div>';

            return new HtmlString($rows);
        }

        $newTotal = (int) $pricing['new'];
        $diff = (int) $pricing['diff'];
        $rows .= '<span><span style="opacity:.7;">'.e(__('admin.orders.manage_item.price_new')).':</span> <strong>'.$this->eurosFromCents($newTotal).' €</strong></span></div>';

        if ($diff > 0) {
            $line = __('admin.orders.manage_item.price_diff_extra', ['amount' => $this->eurosFromCents($diff)]);
            $color = '#b45309'; // amber-700: se cobra en puerta
        } elseif ($diff < 0) {
            // #225 (D8): bajar = solo cancelar; NO se reembolsa automáticamente (color ámbar, no verde).
            $line = __('admin.orders.manage_item.price_diff_reduce', ['amount' => $this->eurosFromCents(-$diff)]);
            $color = '#b45309'; // amber-700: se cancela; reembolso aparte si procede
        } else {
            $line = __('admin.orders.manage_item.price_diff_none');
            $color = '#6b7280'; // gray-500
        }

        return new HtmlString($rows.'<div class="text-sm" style="margin-top:.35rem;font-weight:600;color:'.$color.';">'.e($line).'</div>');
    }

    /**
     * Bloque HTML reactivo del importe del cambio de complementos (Placeholder,
     * sub-fase 7.2e.4, #170). Reusa `computeAddonPricing` (mismo cálculo que el
     * guardado). Subidas → cobro en puerta; quitar → pendiente de reembolso.
     */
    private function addonDiffPreview(OrderItem $item, Get $get): HtmlString
    {
        // ②b: el preview reactivo refleja la elección del Radio del grupo (la materializa como un
        // `add` igual que el guardado), de modo que «lo que se muestra == lo que se cobrará».
        $data = [
            'addon_edits' => $get('addon_edits') ?? [],
            'addon_adds' => $get('addon_adds') ?? [],
        ];
        foreach ($this->itemChoiceGroups($item) as $groupKey => $group) {
            $data[self::groupFieldName($groupKey)] = $get(self::groupFieldName($groupKey));
        }
        $normalized = $this->normalizeAddonEdits($this->applyGroupChoices($item, $data));
        $type = $item->ticketType;
        if ($type === null) {
            return new HtmlString('');
        }
        $dateStr = $this->calendarSelectedDate ?: $item->slot?->date?->toDateString();
        $pricing = $this->itemEditPricing()->computeAddonPricing($item, $type, $normalized['edits'], $normalized['adds'], $dateStr);

        $upcharge = (int) ($pricing['upcharge'] ?? 0);
        $hasRemoval = ! empty($pricing['changes']['addon_change']['removed']);

        if ($upcharge > 0) {
            $line = __('admin.orders.manage_item.addons_upcharge_extra', ['amount' => $this->eurosFromCents($upcharge)]);
            $color = '#b45309';
        } elseif ($hasRemoval) {
            $line = __('admin.orders.manage_item.addons_upcharge_removed');
            $color = '#15803d';
        } else {
            $line = __('admin.orders.manage_item.addons_upcharge_none');
            $color = '#6b7280';
        }

        return new HtmlString('<div class="text-sm" style="font-weight:600;color:'.$color.';">'.e($line).'</div>');
    }

    /**
     * Notificación de bloqueo por addons huérfanos con la lista de
     * complementos afectados (sub-fase 7.2e.3, #167).
     *
     * @param  array<int, string>  $orphans
     */
    private function orphanAddonsBlockedNotification(array $orphans): void
    {
        Notification::make()
            ->title(__('admin.orders.manage_item.orphan_addons_warning', [
                'list' => implode(', ', array_values($orphans)),
            ]))
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * Notificación de éxito del edit según el movimiento de dinero (#225, D8). Una BAJADA ya no
     * auto-reembolsa: solo cancela unidades → el mensaje avisa de que NO se ha reembolsado y de
     * que el reembolso, si procede, se hace aparte con «Reembolsar» (cancelar ≠ reembolsar).
     */
    private function editSuccessNotification(?int $extraDueCents, ?int $reducedCents, array $itemEditContext = []): void
    {
        $hasExtra = $extraDueCents !== null && $extraDueCents > 0;
        $hasReduced = $reducedCents !== null && $reducedCents > 0;

        // ⚠️ D2 (`#146`): «unidades canceladas» era MENTIRA cuando la bajada venía de una
        // RE-TARIFICACIÓN (`PAY-18`) sin tocar la cantidad — el operador leía una cancelación que no
        // había ocurrido, cada vez que movía una fecha a la baja. La causa ya viaja en el contexto;
        // el texto elige la variante que es VERDAD: cantidad si la cantidad bajó, precio si no.
        $qty = $itemEditContext['quantity_change'] ?? null;
        $quantityDropped = is_array($qty) && (int) ($qty['new'] ?? 0) < (int) ($qty['old'] ?? 0);

        // Subida (cobro en puerta) + bajada (cancelación) en la misma edición: un mensaje que cita
        // el cobro y recuerda que la bajada no se reembolsó.
        if ($hasExtra && $hasReduced) {
            Notification::make()
                ->title(__('admin.orders.manage_item.success_edited_mixed_reduced', [
                    'extra' => $this->eurosFromCents($extraDueCents),
                ]))
                ->warning()
                ->send();

            return;
        }

        // Bajada: NO se reembolsa automáticamente, y el motivo se dice sin inventar.
        if ($hasReduced) {
            Notification::make()
                ->title(__($quantityDropped
                    ? 'admin.orders.manage_item.success_edited_reduced'
                    : 'admin.orders.manage_item.success_edited_reduced_price'))
                ->warning()
                ->send();

            return;
        }

        // Subida: cobro en puerta.
        if ($hasExtra) {
            Notification::make()
                ->title(__('admin.orders.manage_item.success_edited_extra_due', [
                    'amount' => $this->eurosFromCents($extraDueCents),
                ]))
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('admin.orders.manage_item.success_edited'))
            ->success()
            ->send();
    }

    /**
     * Notification de bloqueo al operador con texto específico por razón.
     */
    private function manageItemBlockedNotification(string $reason): void
    {
        $reasonText = __('admin.orders.item_actions.reasons.'.$reason);
        if ($reasonText === 'admin.orders.item_actions.reasons.'.$reason) {
            $reasonText = $reason;
        }

        Notification::make()
            ->title(__('admin.orders.manage_item.blocked', ['reason' => $reasonText]))
            ->danger()
            ->send();
    }

    private function itemEventDataBlockedNotification(string $reason): void
    {
        $key = match ($reason) {
            'stale_version' => 'admin.orders.item_detail.flash_stale',
            default => 'admin.orders.item_detail.flash_blocked.'.$reason,
        };

        $title = __($key);
        // Fallback si la clave específica no existe.
        if ($title === $key) {
            $title = __('admin.orders.item_detail.flash_blocked.generic');
        }

        Notification::make()
            ->title($title)
            ->danger()
            ->send();
    }

    /**
     * Construye las opciones del CheckboxList del modal Reembolsar.
     *
     * Incluye el item principal + sus complementos (children) que aún tengan
     * importe refundable restante. Items completamente refundados (sea el
     * principal o un complemento) NO aparecen — no hay nada que devolver y
     * mostrarlos confundiría al operador.
     *
     * Labels: "Producto X — Y,YY €" para el principal; "  ↳ Complemento Z —
     * Y,YY €" para children (indentado visual mínimo dentro del label, dado
     * que CheckboxList renderiza items planos).
     *
     * @return array<int, string> [item_id => label]
     */
    private function buildRefundItemsOptions(array $arguments): array
    {
        $item = $this->resolveItem($arguments);
        if ($item === null) {
            return [];
        }
        /** @var Order $order */
        $order = $this->record;
        $options = [];

        // #172: solo se ofrecen items cuyo importe refundable NO supere la
        // capacidad reembolsable restante del pedido. Un item que la excede
        // (p. ej. un pack que creció con cargos de puerta `extra_due` no
        // cobrados online) NO puede reembolsarse entero → no debe poder
        // seleccionarse (antes se marcaba y saltaba `exceeds_refundable_capacity`
        // al enviar). La validación al submit se mantiene como red de seguridad.
        $capacity = $order->refundableCapacityCents();
        $isOfferable = function (OrderItem $i) use ($order, $capacity): bool {
            $remainder = $order->itemRefundableRemainderCents($i);

            return $remainder > 0 && $remainder <= $capacity;
        };

        // Item principal.
        if ($isOfferable($item)) {
            $options[$item->id] = $this->refundItemOptionLabel($item, isAddon: false);
        }

        // Children (addons) del pack.
        foreach ($item->children as $child) {
            if ($isOfferable($child)) {
                $options[$child->id] = $this->refundItemOptionLabel($child, isAddon: true);
            }
        }

        return $options;
    }

    /**
     * Renderiza notification + dispatch de emails tras un batch refund.
     * Si hubo éxitos parciales: notify success al operador con resumen +
     * email único al cliente con TODOS los items refundados (UX coherente:
     * un solo correo aunque haya N items).
     *
     * Si hubo fallos: notify danger con detalle del primer fallo + cita
     * cuántos quedaron pendientes.
     *
     * @param  array{succeeded:list<array{item:OrderItem,amount_cents:int}>, failed:list<array{item:OrderItem,reason:string,gateway_response_code:?string,failure_message:?string}>, aborted:list<int>}  $batchResult
     */
    private function renderBatchResult(array $batchResult, Order $order, string $mode): void
    {
        $succeeded = $batchResult['succeeded'] ?? [];
        $failed = $batchResult['failed'] ?? [];

        // Emails: uno por item refundado con éxito. Coherente con el patrón
        // de notification 1:1 — el cliente recibe N emails si se refundaron
        // N items. Sub-fase 7.2e.1bis4 (decisión #157): leemos el flag REAL
        // del resultado (`also_cancelled_item`) en vez de hardcodear `true`.
        // Como el handler invoca `executePartialRefundBatch(alsoCancelItems:
        // false)`, el cliente recibe "te hemos devuelto X €" sin línea de
        // cancellation; el item sigue activo en su pedido.
        foreach ($succeeded as $row) {
            $alsoCancelled = (bool) ($row['refund']->order_item_id !== null
                && $row['item']->fresh()->isCancelled());
            $order->notifyCustomer(new OrderItemRefunded(
                order: $order->fresh(),
                item: $row['item']->fresh(),
                refundedAmountCents: $row['amount_cents'],
                alsoCancelledItem: $alsoCancelled,
            ));
        }

        if ($succeeded === [] && $failed !== []) {
            // Todo falló — notify danger con el primer error.
            $first = $failed[0];
            $this->renderItemRefundBatchFailure($first);

            return;
        }

        if ($failed !== []) {
            // Éxito parcial — success con avisos.
            $totalAmount = array_sum(array_column($succeeded, 'amount_cents'));
            Notification::make()
                ->title(__('admin.orders.refund_item.success_partial_'.$mode, [
                    'count_ok' => count($succeeded),
                    'count_failed' => count($failed),
                    'amount' => number_format($totalAmount / 100, 2, ',', '.'),
                ]))
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        // Todos éxito.
        $totalAmount = array_sum(array_column($succeeded, 'amount_cents'));
        Notification::make()
            ->title(__('admin.orders.refund_item.success_all_'.$mode, [
                'count' => count($succeeded),
                'amount' => number_format($totalAmount / 100, 2, ',', '.'),
            ]))
            ->success()
            ->send();
    }

    /**
     * Render del fallo individual del primer item del batch.
     *
     * @param  array{item:OrderItem,reason:string,gateway_response_code:?string,failure_message:?string}  $failure
     */
    private function renderItemRefundBatchFailure(array $failure): void
    {
        $reason = $failure['reason'];

        if ($reason === 'gateway_failed') {
            $isTransport = (string) ($failure['failure_message'] ?? '') !== ''
                && str_contains((string) $failure['failure_message'], 'Network');
            $msg = $isTransport
                ? __('admin.orders.refund_item.transport_error')
                : __('admin.orders.refund_item.gateway_denied', [
                    'code' => $failure['gateway_response_code'] ?? '—',
                ]);
            Notification::make()
                ->title(__('admin.orders.refund_item.failed_title'))
                ->body($msg)
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($reason === 'inflight_refund') {
            Notification::make()
                ->title(__('admin.orders.refund_item.inflight_title'))
                ->body(__('admin.orders.refund_item.inflight_body'))
                ->warning()
                ->send();

            return;
        }

        $this->itemActionBlockedNotification('refund', $reason);
    }

    /**
     * Notification de bloqueo al operador con texto específico por razón.
     *
     * @param  'cancel'|'refund'  $actionKey
     */
    private function itemActionBlockedNotification(string $actionKey, string $reason): void
    {
        $reasonText = __('admin.orders.item_actions.reasons.'.$reason);
        // Fallback: si la clave específica no existe, dejamos la razón cruda
        // en lugar del texto técnico (mejor que nada para el operador).
        if ($reasonText === 'admin.orders.item_actions.reasons.'.$reason) {
            $reasonText = $reason;
        }

        Notification::make()
            ->title(__('admin.orders.'.$actionKey.'_item.blocked', ['reason' => $reasonText]))
            ->danger()
            ->send();
    }
}
