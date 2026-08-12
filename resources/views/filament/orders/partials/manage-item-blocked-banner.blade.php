@php
    /**
     * @var string $reason  Razón estructurada del bloqueo (`item_cancelled`,
     *                      `item_finished`, `item_is_addon`,
     *                      `order_not_operational`, `not_in_order`).
     *
     * Sub-fase 7.2e.2 (decisión #159) — banner explicativo dentro del Tab 1
     * "Producto y reserva" del modal Gestionar cuando el item NO es
     * editable. Defense in depth UI: los selectores aparecen disabled,
     * el botón "Guardar cambios" no se renderiza, y este banner explica
     * la causa al operador para que no pierda tiempo intentándolo.
     *
     * El backend (`Order::editItemBlockedReason`) revalidaría igualmente
     * si por bug del front se enviase un submit — este banner solo cubre
     * UX explicativa.
     */
@endphp

<div class="mb-2 flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30">
    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
    </svg>
    <div>
        <p class="font-medium">{{ __('admin.orders.manage_item.read_only_title') }}</p>
        <p class="mt-1">{{ __('admin.orders.manage_item.read_only_intro', ['reason' => __('admin.orders.item_actions.reasons.'.$reason)]) }}</p>
    </div>
</div>
