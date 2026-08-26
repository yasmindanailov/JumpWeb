<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;

/**
 * La EDICIÓN de un ítem ya comprado desde el panel — la orquestación de
 * dinero/aforo que vivía en `ViewOrder` (extracción 4b del desmontaje,
 * `docs/specs/desmontar-view-order.md` §9.6).
 *
 * Nace en el sub-paso A con su MITAD PURA: los validadores de destino
 * (producto, cantidad, complementos, franja) y las lecturas sobre las que
 * deciden. La otra mitad —`changeSlot()` y `edit()`, dueñas de la
 * transacción de aforo bajo el lock de zona/día y orquestadoras de la
 * secuencia financiera POST-commit (spec §4.3)— llega en los sub-pasos C y
 * F. Hasta entonces este servicio no toma locks; cuando los tome ENTRA en el
 * `CRITICAL_RE` del `pre-push`.
 *
 * Contrato de los validadores: devuelven la RAZÓN estructurada de bloqueo
 * —la misma clave que la capa de entrega audita (`orders.item_edit_blocked`)
 * y traduce al operador— o `null` si pasa. Sin efectos: son la defensa en
 * profundidad para un cliente que manipule el formulario (Filament ya
 * rechaza la mayoría antes, con `in:options` y `min/max`), y por eso se
 * prueban DIRECTAMENTE, sin pasar por el modal.
 */
class OrderItemEditor
{
    public function __construct(
        private ItemRescheduleOffer $rescheduleOffer,
        private OperatingSchedule $schedule,
        private ProductAvailability $productWindow,
    ) {}

    // ─── Producto y cantidad ────────────────────────────────────────────────

    /**
     * Validación PURA del destino de un edit (sub-fase 7.2e.3, #167):
     * producto (mismo tipo + misma zona + vendible, sin addons huérfanos) +
     * cantidad (rango del pack). Devuelve la razón estructurada de bloqueo o
     * `null` si pasa.
     */
    public function validateItemEditTarget(OrderItem $item, TicketType $newType, int $newQty): ?string
    {
        $oldType = $item->ticketType;
        $productChanged = (int) $newType->id !== (int) $item->ticket_type_id;

        if ($productChanged) {
            if (! $newType->is_sellable) {
                return 'invalid_product';
            }
            if ($oldType === null || $newType->type !== $oldType->type) {
                return 'cross_type_change_forbidden';
            }
            if ($newType->zone_id === null || (int) $newType->zone_id !== (int) $oldType->zone_id) {
                return 'cross_zone_change_forbidden_product';
            }
            if ($this->orphanAddonsForNewProduct($item, $newType) !== []) {
                return 'orphan_addons';
            }
        }

        if ($newQty < 1) {
            return 'invalid_quantity';
        }
        if ($newType->isPack()) {
            $min = (int) ($newType->min_qty ?? 1);
            $max = $newType->max_qty !== null ? (int) $newType->max_qty : null;
            if ($newQty < $min || ($max !== null && $newQty > $max)) {
                return 'pack_quantity_range';
            }
        }

        return null;
    }

    /**
     * Complementos del item que el producto NUEVO no admite (no están en su
     * pivote `product_addons`). Solo cuentan los children ACTIVOS (no
     * cancelados). Devuelve [order_item_id => nombre] para listarlos en el
     * banner (sub-fase 7.2e.3, #167).
     *
     * @return array<int, string>
     */
    public function orphanAddonsForNewProduct(OrderItem $item, TicketType $newType): array
    {
        $allowedAddonIds = $newType->addons()->pluck('ticket_types.id')->all();

        $orphans = [];
        foreach ($item->children as $child) {
            if ($child->isCancelled()) {
                continue;
            }
            if (! in_array((int) $child->ticket_type_id, array_map('intval', $allowedAddonIds), true)) {
                $orphans[(int) $child->id] = $child->ticketType?->tr('name') ?? ('#'.$child->id);
            }
        }

        return $orphans;
    }

    // ─── Complementos ───────────────────────────────────────────────────────

    /**
     * Validación PURA de los edits de complementos (sub-fase 7.2e.4, #170).
     * Devuelve la razón estructurada de bloqueo o `null`. Reglas:
     *  - cada EDIT referencia un child ACTIVO del item (`addon_not_in_parent`);
     *  - cantidad de un edit ≥ 0 (`addon_quantity_invalid`); la reducción
     *    parcial a un valor intermedio (0 < q < actual) NO está soportada sin
     *    refund (`addon_partial_reduce_unsupported`) — quitar (0) sí;
     *  - cada ADD está en el pivote `addons()` del producto NUEVO
     *    (`addon_incompatible_with_product`), con cantidad ≥ 1
     *    (`addon_quantity_invalid`), sin duplicar un complemento ya presente
     *    ni repetirlo en la misma tanda (`addon_already_added`).
     *
     * @param  array<int, array{child_id:int, quantity:int}>  $edits
     * @param  array<int, array{ticket_type_id:int, quantity:int}>  $adds
     */
    public function validateAddonEdits(OrderItem $item, TicketType $newType, array $edits, array $adds): ?string
    {
        $childById = $item->children->keyBy('id');
        $meta = $this->childAddonMeta($item);

        foreach ($edits as $edit) {
            $child = $childById->get($edit['child_id']);
            if ($child === null || (int) $child->parent_item_id !== (int) $item->id || $child->isCancelled()) {
                return 'addon_not_in_parent';
            }
            $q = (int) $edit['quantity'];
            if ($q < 0) {
                return 'addon_quantity_invalid';
            }
            // MISMAS condiciones que la web: los incluidos/obligatorios no se quitan ni bajan de lo
            // incluido; los per-invitado/grupo están bloqueados (se cambian con "elige menú").
            $cmeta = $meta[(int) $child->id] ?? ['min' => 0, 'locked' => false];
            if (($cmeta['locked'] ?? false) && $q !== (int) $child->quantity) {
                return 'addon_locked';
            }
            if ($q < ($cmeta['min'] ?? 0)) {
                return 'addon_locked';
            }
            // Tope de un INCLUIDO sin extras (#225 · auditoría Fase 1 P4): no se cobran unidades de
            // pago por encima de lo incluido (misma autoridad que la compra pública, AddonResolver).
            if (($cmeta['max'] ?? null) !== null && $q > $cmeta['max']) {
                return 'addon_no_extra';
            }
            if ($q > 0 && $q < (int) $child->quantity) {
                return 'addon_partial_reduce_unsupported';
            }
        }

        $allowedAddonIds = array_map('intval', $newType->addons()->pluck('ticket_types.id')->all());
        $activeAddonTypeIds = $item->children
            ->reject(fn (OrderItem $c): bool => $c->isCancelled())
            ->map(fn (OrderItem $c): int => (int) $c->ticket_type_id)
            ->all();

        // Pivotes del producto nuevo para detectar conflictos de grupo entre los adds.
        $newPivots = [];
        foreach ($newType->addons()->get() as $a) {
            $newPivots[(int) $a->id] = $a->pivot;
        }

        $seen = [];
        $seenGroups = [];
        foreach ($adds as $add) {
            $typeId = (int) $add['ticket_type_id'];
            if ((int) $add['quantity'] < 1) {
                return 'addon_quantity_invalid';
            }
            if (! in_array($typeId, $allowedAddonIds, true)) {
                return 'addon_incompatible_with_product';
            }
            if (in_array($typeId, $activeAddonTypeIds, true) || in_array($typeId, $seen, true)) {
                return 'addon_already_added';
            }
            // No se pueden elegir DOS del mismo grupo a la vez (la web es radio). Añadir UN miembro
            // de un grupo que ya tiene otro presente es un CAMBIO de menú (se permite; el guardado
            // sustituye el anterior).
            $group = $newPivots[$typeId]?->choiceGroup();
            if ($group !== null) {
                if (in_array($group, $seenGroups, true)) {
                    return 'addon_group_conflict';
                }
                $seenGroups[] = $group;
            }
            $seen[] = $typeId;
        }

        // Dependencias «requiere» (data-driven): tras aplicar edits+adds, ningún complemento ACTIVO
        // puede quedar con su requisito ausente. Conjunto de tipos que QUEDARÁN activos = children no
        // quitados (edit qty != 0) ∪ los añadidos; cada dependiente exige su requisito dentro (misma
        // autoridad que la compra pública, AddonResolver). Cubre tanto «añadir el dependiente sin su
        // requisito» como «quitar el requisito dejando al dependiente huérfano».
        $removedChildIds = [];
        foreach ($edits as $edit) {
            if ((int) $edit['quantity'] === 0) {
                $removedChildIds[(int) $edit['child_id']] = true;
            }
        }
        $finalActiveTypeIds = [];
        foreach ($item->children as $child) {
            if ($child->isCancelled() || isset($removedChildIds[(int) $child->id])) {
                continue;
            }
            $finalActiveTypeIds[(int) $child->ticket_type_id] = true;
        }
        foreach ($adds as $add) {
            $finalActiveTypeIds[(int) $add['ticket_type_id']] = true;
        }
        // El GUARDADO sustituye al miembro de grupo presente cuando un add es del MISMO grupo
        // (group-replacement, ②b). Simúlalo aquí: ese miembro NO seguirá activo, así que un
        // dependiente que lo «requiere» debe disparar `addon_requires_missing` (igual que la web),
        // en vez de quedar huérfano. Sin esto, el panel divergiría de la autoridad pública.
        $addedGroups = [];
        foreach ($adds as $add) {
            $group = $newPivots[(int) $add['ticket_type_id']]?->choiceGroup();
            if ($group !== null) {
                $addedGroups[$group] = true;
            }
        }
        if ($addedGroups !== []) {
            foreach ($item->children as $child) {
                if ($child->isCancelled()) {
                    continue;
                }
                $childGroup = $newPivots[(int) $child->ticket_type_id]?->choiceGroup();
                if ($childGroup !== null && isset($addedGroups[$childGroup])) {
                    unset($finalActiveTypeIds[(int) $child->ticket_type_id]);
                }
            }
        }
        foreach (array_keys($finalActiveTypeIds) as $typeId) {
            $required = $newPivots[$typeId]?->requiresAddonId();
            if ($required !== null && ! isset($finalActiveTypeIds[$required])) {
                return 'addon_requires_missing';
            }
        }

        return null;
    }

    /**
     * Metadatos de cada complemento ACTUAL del item para aplicar las MISMAS condiciones que la web:
     * cantidad mínima (los incluidos/obligatorios no se quitan ni bajan de lo incluido), bloqueo de
     * cantidad (per-invitado/grupo: se cambian con "elige menú", no editando la cantidad) y badge.
     * Los lee la validación de arriba y el formulario del modal (pistas y etiquetas).
     *
     * @return array<int, array{min:int, max:?int, locked:bool, badge:?string, group:?string, free:int}>
     */
    public function childAddonMeta(OrderItem $item): array
    {
        $pivots = $this->addonPivotsFor($item);
        $isPack = $item->ticketType?->isPack() ?? false;

        $meta = [];
        foreach ($item->children as $child) {
            $pivot = $pivots[(int) $child->ticket_type_id] ?? null;
            $perGuest = $pivot?->isPerGuest() ?? false;
            $group = $pivot?->choiceGroup();
            $locked = $perGuest || $group !== null;
            $min = $locked
                ? (int) $child->quantity
                : (($pivot?->is_mandatory ?? false) ? max(1, (int) $pivot->included_quantity) : 0);
            $badge = ($pivot?->is_included ?? false) ? ($isPack ? 'included' : 'free') : null;
            // Tope superior (#225 · auditoría Fase 1 P4): un complemento INCLUIDO SIN extras
            // (`is_included && !allow_extra`) no admite unidades de pago por encima de lo incluido —
            // la compra pública lo capa en `AddonResolver::effectiveQuantity`; el edit del panel
            // debe heredar la MISMA autoridad (antes cobraba `extra_due` de unidades no vendibles).
            // null = sin tope (admite extras de pago).
            // Un PER-INVITADO no tiene «extras» que topar: su cantidad es fija (= invitados) y
            // bloqueada. Los topes (incluido-sin-extra / max_qty) SOLO aplican a cantidad FIJA. Sin
            // este guard, un Menú incluido per-invitado daba max=included_quantity(1) y la validación
            // rechazaba CUALQUIER cambio de complementos del pack con `addon_no_extra`.
            $caps = [];
            if (! $perGuest) {
                if (($pivot?->is_included ?? false) && ! ($pivot?->allow_extra ?? false)) {
                    $caps[] = max(1, (int) $pivot->included_quantity);
                }
                if (($pivot?->max_qty ?? null) !== null) { // P9: tope por complemento
                    $caps[] = (int) $pivot->max_qty;
                }
            }
            $max = $caps === [] ? null : min($caps);

            $meta[(int) $child->id] = [
                'min' => $min,
                'max' => $max,
                'locked' => $locked,
                'badge' => $badge,
                'group' => $group,
                'free' => (int) $child->free_quantity,
            ];
        }

        return $meta;
    }

    /**
     * Pivotes de los complementos del producto del item, indexados por ticket_type_id, para leer
     * su config (incluido/obligatorio/por-invitado/grupo) al editar (modal "Gestionar"). Misma
     * autoridad que la compra pública.
     *
     * @return array<int, ProductAddon>
     */
    private function addonPivotsFor(OrderItem $item): array
    {
        $type = $item->ticketType;
        if ($type === null) {
            return [];
        }

        $out = [];
        foreach ($type->addons()->get() as $addon) {
            $out[(int) $addon->id] = $addon->pivot;
        }

        return $out;
    }

    /**
     * ¿El guardado incluye algún cambio REAL de complementos? (sub-fase 7.2e.4).
     * Un edit que deja la cantidad igual NO cuenta. Lo usa el despachador de la
     * página para decidir si entrar al guardado unificado.
     *
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, array{ticket_type_id:int, quantity:int}>}  $normalized
     */
    public function addonEditsPresent(OrderItem $item, array $normalized): bool
    {
        if (($normalized['adds'] ?? []) !== []) {
            return true;
        }
        $childById = $item->children->keyBy('id');
        foreach ($normalized['edits'] ?? [] as $edit) {
            $child = $childById->get($edit['child_id']);
            if ($child === null) {
                // Referencia desconocida (p. ej. IDOR cross-item o concurrente):
                // enrutar para que `validateAddonEdits` la bloquee con razón.
                return true;
            }
            if ($child->isCancelled()) {
                // Ya cancelado → un edit sobre él es no-op (no enrutar por esto).
                continue;
            }
            if ((int) $edit['quantity'] !== (int) $child->quantity) {
                return true;
            }
        }

        return false;
    }

    /**
     * child_ids de complementos que se QUITAN (cantidad 0) en este guardado.
     * Usado por la orphan-resolution: un complemento huérfano deja de bloquear
     * si se está quitando en el mismo guardado (sub-fase 7.2e.4, #170).
     *
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, mixed>}  $addonEdits
     * @return array<int, int>
     */
    public function addonChildIdsBeingRemoved(array $addonEdits): array
    {
        $ids = [];
        foreach ($addonEdits['edits'] ?? [] as $edit) {
            if ((int) ($edit['quantity'] ?? -1) === 0) {
                $ids[] = (int) $edit['child_id'];
            }
        }

        return $ids;
    }

    // ─── Franja ─────────────────────────────────────────────────────────────

    /**
     * Resuelve un Slot concreto (zone del item + fecha + hora). Devuelve
     * null si no existe (defense in depth — el guardado lo trata como
     * "selección inválida").
     */
    public function resolveSlotForItem(OrderItem $item, string $date, string $time): ?Slot
    {
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return null;
        }

        return Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->where('date', $date)
            ->where('start_time', $time)
            ->first();
    }

    /**
     * Valida el slot elegido en capa 4 del cambio. Devuelve la razón
     * estructurada para audit log o null si pasa.
     */
    public function validateNewSlot(OrderItem $item, ?Slot $newSlot, ?TicketType $forType = null): ?string
    {
        if ($newSlot === null) {
            return 'invalid_slot_selection';
        }
        // Sub-fase 7.2e.3 (#167): cuando se valida un cambio de producto, el
        // slot debe encajar con el producto NUEVO (zona + ventana). Por
        // defecto valida contra el producto actual del item (path 7.2e.2).
        $ticketType = $forType ?? $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id !== $newSlot->zone_id) {
            return 'cross_zone_change_forbidden';
        }
        if ($newSlot->online_sales_open === false || $newSlot->status === Slot::STATUS_CLOSED) {
            // Excepción: el slot ACTUAL del item podría estar cerrado por admin;
            // mantener el mismo slot no es un "cambio" (lo cubre el no-op del
            // handler). Si llegamos aquí con cerrado, es porque el operador
            // eligió el cerrado como destino — bloqueamos.
            return 'slot_closed';
        }
        $newDateCarbon = $newSlot->date;
        // El ancla de «hoy» es la del PARQUE, la misma que usa la oferta
        // (`ItemRescheduleOffer::today`, [DECIDIDO owner, 2026-08-26]): si la
        // validación anclara en UTC, entre las 00:00 y las ~02:00 del parque
        // rechazaría como pasada una fecha que el calendario acaba de ofrecer.
        if ($newDateCarbon->lt($this->rescheduleOffer->today())) {
            return 'slot_in_past';
        }
        // Capa defense in depth — horizonte de compra (sub-fase 7.2e.2bis6, #160):
        // si el slot está más allá del límite global (default 6 meses), rechazo.
        // El calendario UI ya filtra esto (`ItemRescheduleOffer::selectableDates`);
        // este check protege contra atacante autenticado que manipule el form.
        if ($newDateCarbon->gt($this->rescheduleOffer->horizon())) {
            return 'beyond_horizon';
        }
        if (! $this->schedule->isOpenOn($newDateCarbon)) {
            return 'park_closed';
        }
        if (! $this->productWindow->allowsStart($ticketType, $newDateCarbon, $newSlot->start_time)) {
            return 'product_window';
        }

        return null;
    }
}
