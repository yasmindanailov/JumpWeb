@php
    use Illuminate\Support\Js;

    /** @var \App\Domain\Booking\Models\Order $record */
    $code = $record->code;
    $status = $record->displayStatus();
    $statusLabel = __('admin.orders.status.'.$status);

    // Colores del badge alineados con `OrderInfolist::summarySection` (#129/#130).
    // Mantenemos la jerarquía visual: pagado = success / cancelado-refund-expired = danger /
    // pending o equivalentes = warning.
    $statusColorClasses = match ($status) {
        \App\Domain\Booking\Models\Order::STATUS_PAID
            => 'bg-green-100 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-400/30',
        \App\Domain\Booking\Models\Order::STATUS_REFUNDED,
        \App\Domain\Booking\Models\Order::STATUS_CANCELLED,
        \App\Domain\Booking\Models\Order::STATUS_EXPIRED
            => 'bg-red-100 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-400/30',
        default
            => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/30',
    };

    // P3: estado OPERATIVO (respecto al horario del evento/franja), JUNTO al estado del pedido.
    // Antes vivía en la card "Resumen"; ahora acompaña al badge de estado en la H1.
    $operative = $record->displayOperativeStatus();
    $operativeLabel = __('admin.orders.operative.'.$operative);
    $operativeColorClasses = match ($operative) {
        \App\Domain\Booking\Models\Order::OPERATIVE_STATUS_FINISHED
            => 'bg-gray-100 text-gray-600 ring-gray-500/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-400/30',
        \App\Domain\Booking\Models\Order::OPERATIVE_STATUS_IN_PROGRESS
            => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/30',
        default
            => 'bg-blue-100 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-400/30',
    };
@endphp

{{-- Heading enriquecido del detalle del pedido (decisiones #132 + #136).
     Estructura horizontal: [Pedido] [ID: code copy] [badge].

     **#136**: el eyebrow apilado vertical resultaba demasiado prominente sobre el
     código. Se sustituye por un label inline compacto "ID:" + código + botón copiar
     en la misma línea base. El botón sigue copiando SOLO el código (no "ID: XXX"),
     vía `Js::from($code)` — bien para pegar en sistemas externos. --}}
<span class="flex flex-wrap items-center gap-x-3 gap-y-2">
    <span>{{ __('admin.orders.heading_pedido') }}</span>

    <span x-data="{ copied: false }" class="inline-flex items-center gap-x-1.5">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.orders.heading_id_label') }}:</span>
        <span class="font-mono text-base font-semibold tracking-tight">{{ $code }}</span>
        <button type="button"
                @click="navigator.clipboard.writeText({{ Js::from($code) }}); copied = true; setTimeout(() => copied = false, 1500)"
                class="inline-flex items-center justify-center rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
                :aria-label="copied ? '{{ __('admin.orders.payments.copied') }}' : '{{ __('admin.orders.payments.copy') }}'">
            <svg x-show="!copied" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/>
            </svg>
            <svg x-show="copied" x-cloak class="h-4 w-4 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
        </button>
    </span>

    {{-- P3: tooltip al pasar el ratón explica QUÉ dimensión es este badge (estado del pedido). --}}
    <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-sm font-medium ring-1 ring-inset cursor-help {{ $statusColorClasses }}"
          title="{{ __('admin.orders.status_badge_tooltip') }}">
        {{ $statusLabel }}
    </span>

    {{-- P3: estado OPERATIVO junto al del pedido; su tooltip explica la dimensión del evento. --}}
    <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-sm font-medium ring-1 ring-inset cursor-help {{ $operativeColorClasses }}"
          title="{{ __('admin.orders.operative_badge_tooltip') }}">
        {{ $operativeLabel }}
    </span>

    {{-- Badge secundario "Reembolsado" (#146 + 7.2e.1bis5/#158):
         Sub-fase 7.2e.1bis5 (punto 7A feedback): el badge aparece ÚNICAMENTE
         cuando el pedido está reembolsado COMPLETAMENTE (refund total). Para
         reembolsos parciales (solo un complemento, parte del precio), el
         badge NO se muestra a nivel Order — el reembolso parcial vive como
         badge a nivel del producto o complemento afectado en la card
         "Productos del pedido". Esto evita confundir al operador con un
         badge "Reembolsado" en pedidos que en realidad están parcialmente
         devueltos.

         `isFullyRefunded()` es resiliente: usa `refund_amount_cents >= total`
         + fallback legacy `status=refunded`. El resumen financiero del Order
         (Total / Devuelto / Neto) sigue mostrándose en parciales — es info
         financiera, no estado. --}}
    {{-- #F1: espejo del guard de OrdersTable (#179, línea 61). Solo mostramos el
         badge secundario "Reembolsado" si el badge de ESTADO no lo dice ya. En
         data legacy con status=refunded, displayStatus()='refunded' y su propio
         badge ya reza "Reembolsado" → sin este guard salía DOBLE. --}}
    @if ($record->isFullyRefunded() && $record->displayStatus() !== \App\Domain\Booking\Models\Order::STATUS_REFUNDED)
        <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-sm font-medium ring-1 ring-inset bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/30">
            {{ __('admin.orders.refunded_badge') }}
        </span>
    @endif
</span>
