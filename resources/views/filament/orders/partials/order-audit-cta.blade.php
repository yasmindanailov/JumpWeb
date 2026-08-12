@php
    /**
     * Sub-fase 7.2d (decisión #151) — CTA al final de la card "Detalles" del
     * Order que abre el modal del audit log agregado. Se renderiza dentro
     * del schema de `OrderInfolist::detailsSection()` vía `View::make`.
     *
     * El botón monta la `viewOrderHistoryAction` definida en `ViewOrder`. La
     * descripción corta explica al operador qué encontrará al abrir el modal
     * — útil para staff que aún no conoce la sección.
     */
@endphp

<div class="mt-2 flex flex-col items-start gap-3 rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-gray-900/40 dark:ring-white/5 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0 max-w-prose text-sm text-gray-700 dark:text-gray-300">
        <p class="font-medium text-gray-900 dark:text-gray-100">
            {{ __('admin.orders.audit_cta.title') }}
        </p>
        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">
            {{ __('admin.orders.audit_cta.description') }}
        </p>
    </div>

    <x-filament::button
        size="sm"
        color="gray"
        wire:click="mountAction('viewOrderHistory')"
        icon="heroicon-o-clock"
        class="shrink-0"
    >
        {{ __('admin.orders.audit_cta.button') }}
    </x-filament::button>
</div>
