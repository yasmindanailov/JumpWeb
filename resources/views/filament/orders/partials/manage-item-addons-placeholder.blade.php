@php
    /**
     * Sub-fase 7.2e.2 (decisión #159) — placeholder de Tab 2 "Complementos"
     * del modal Gestionar. La tab está `disabled()` en Filament; este partial
     * solo se renderiza si por algún motivo se llega a ella (defensa visual).
     *
     * La gestión real de complementos llega en 7.2e.4 (PLAN-FASE-7-PANEL.md):
     * tabla addons actuales (cantidad editable, 0 = eliminar) + selector
     * "Añadir complemento" con filtrado por `product_addons` del parent. La
     * tab se mantiene visible desde 7.2e.2 para anticipar la estructura.
     */
@endphp

<div class="rounded-xl bg-gray-50 p-6 text-sm text-gray-600 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
    <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6h16.5M3.75 12h16.5M3.75 18h16.5"/>
        </svg>
        <div>
            <p class="font-medium text-gray-700 dark:text-gray-300">{{ __('admin.orders.manage_item.addons_placeholder_title') }}</p>
            <p class="mt-1">{{ __('admin.orders.manage_item.addons_placeholder_body') }}</p>
        </div>
    </div>
</div>
