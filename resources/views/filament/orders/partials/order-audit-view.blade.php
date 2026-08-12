@php
    /**
     * @var \App\Domain\Booking\Models\Order $order
     * @var \Illuminate\Pagination\LengthAwarePaginator $paginator
     * @var array<int> $perPageOptions
     *
     * Sub-fase 7.2d (decisión #151) — vista del audit log agregado del Order.
     * Renderizado server-side con paginación NATIVA Filament
     * (`<x-filament::pagination>`) sobre un `LengthAwarePaginator` calculado
     * en `ViewOrder::getOrderAuditPaginatorProperty()`.
     *
     * El componente Livewire `ViewOrder` usa `WithPagination` trait; las
     * navegaciones (anterior, siguiente, página N, cambio de tamaño) las
     * gestiona Filament+Livewire SIN cerrar el modal montado.
     *
     * Cada entrada distingue visualmente si afecta al Order (badge primary)
     * o a un OrderItem (badge gray). Los detalles del payload se renderizan
     * según el `action`: diff por clave para `event_data_updated`, razón
     * estructurada para `*_blocked`, transiciones de estado para los demás.
     */
    use App\Domain\Booking\Models\Order;
    use App\Domain\Platform\Services\DisplayTime;

    // Eager load idempotente: garantiza que el lookup `$order->items->firstWhere(...)`
    // dentro del loop no dispare N+1 queries por `ticketType`/`slot` de cada item.
    // El blade de items-list de la misma página probablemente ya los tiene
    // cargados — `loadMissing` es no-op si ya están en memoria.
    $order->loadMissing(['items.ticketType.zone', 'items.slot']);
@endphp

<div class="space-y-4">
    @if ($paginator->total() === 0)
        <p class="rounded-xl bg-gray-50 px-4 py-6 text-center text-sm text-gray-600 ring-1 ring-gray-950/5 dark:bg-gray-900/50 dark:text-gray-400 dark:ring-white/5">
            {{ __('admin.orders.audit_modal.empty') }}
        </p>
    @else
        <ol class="space-y-2">
            @foreach ($paginator as $entry)
                @php
                    $isOrderLevel = $entry->target_type === Order::class;
                    $targetTypeKey = $isOrderLevel
                        ? 'admin.orders.audit_modal.target_order'
                        : 'admin.orders.audit_modal.target_item';

                    $actionKey = "admin.orders.audit_modal.actions.{$entry->action}";
                    $actionLabel = __($actionKey);
                    if ($actionLabel === $actionKey) {
                        $actionLabel = $entry->action;
                    }

                    $who = $entry->user?->name ?? $entry->user?->email;
                    $payload = is_array($entry->payload) ? $entry->payload : [];
                @endphp

                <li class="rounded-xl bg-white p-4 ring-1 ring-gray-950/5 dark:bg-gray-900/30 dark:ring-white/10">
                    <header class="flex flex-wrap items-baseline justify-between gap-2">
                        <div class="flex items-center gap-2 flex-wrap min-w-0">
                            <x-filament::badge
                                :color="$isOrderLevel ? 'primary' : 'gray'"
                                size="xs"
                            >
                                {{ __($targetTypeKey) }}
                            </x-filament::badge>
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $actionLabel }}</span>
                        </div>
                        <time class="text-xs text-gray-500 dark:text-gray-400">
                            {{ DisplayTime::format($entry->created_at, 'd/m/Y H:i:s') }}
                        </time>
                    </header>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                        {{ $who
                            ? __('admin.orders.audit_modal.by', ['who' => $who])
                            : __('admin.orders.audit_modal.by_unknown') }}
                    </p>

                    {{-- Si la entrada afecta a un OrderItem, mostramos QUÉ producto
                         fue (decisión #151bis): el operador necesita saber "marcado
                         preparado: cuál". Lookup en `$order->items` ya cargado en
                         memoria; fallback silencioso si el item fue eliminado en
                         el futuro (7.2e cancelar item suelto). --}}
                    @if (! $isOrderLevel)
                        @php
                            $relatedItem = $order->items->firstWhere('id', $entry->target_id);
                            $relatedTicketType = $relatedItem?->ticketType;
                        @endphp
                        @if ($relatedTicketType)
                            <p class="mt-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                <span>{{ __('admin.orders.audit_modal.related_item', ['name' => $relatedTicketType->tr('name')]) }}</span>
                                @if ($relatedItem?->slot)
                                    <span class="ml-1 text-gray-500 dark:text-gray-400">
                                        · {{ \Illuminate\Support\Carbon::parse($relatedItem->slot->date)->isoFormat('ddd D MMM') }}
                                        {{ $relatedItem->displayTimeWindow() }}
                                    </span>
                                @endif
                            </p>
                        @endif
                    @endif

                    {{-- Detalles del payload formateados según action --}}
                    @php
                        $hasReason = isset($payload['reason']);
                        $hasDiff = isset($payload['diff']) && is_array($payload['diff']);
                        $hasPreviousStatus = isset($payload['previous_status']);
                        $hasPreviousPreparedAt = array_key_exists('previous_prepared_at', $payload);
                        $hasRefundDetails = isset($payload['refund_amount_cents']) || isset($payload['mode']);
                        $hasResendType = isset($payload['type']);
                    @endphp

                    @if ($hasDiff)
                        <ul class="mt-2 space-y-0.5 text-xs text-gray-700 dark:text-gray-300">
                            @foreach (($payload['diff']['changed'] ?? []) as $key => $pair)
                                <li>{{ __('admin.orders.audit_modal.diff_changed', ['label' => $key, 'old' => (string) ($pair[0] ?? ''), 'new' => (string) ($pair[1] ?? '')]) }}</li>
                            @endforeach
                            @foreach (($payload['diff']['added'] ?? []) as $key => $new)
                                <li>{{ __('admin.orders.audit_modal.diff_added', ['label' => $key, 'new' => (string) $new]) }}</li>
                            @endforeach
                            @foreach (($payload['diff']['removed'] ?? []) as $key => $old)
                                <li>{{ __('admin.orders.audit_modal.diff_removed', ['label' => $key, 'old' => (string) $old]) }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($hasReason)
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">
                            {{ __('admin.orders.audit_modal.reason', ['reason' => $payload['reason']]) }}
                        </p>
                    @endif

                    @if ($hasPreviousStatus)
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">
                            {{ __('admin.orders.audit_modal.previous_status', ['status' => $payload['previous_status']]) }}
                        </p>
                    @endif

                    @if ($hasPreviousPreparedAt)
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">
                            @if ($payload['previous_prepared_at'])
                                {{ __('admin.orders.audit_modal.previous_prepared_at', ['when' => $payload['previous_prepared_at']]) }}
                            @else
                                {{ __('admin.orders.audit_modal.previously_unprepared') }}
                            @endif
                        </p>
                    @endif

                    @if ($hasRefundDetails)
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">
                            @if (isset($payload['mode']))
                                {{ __('admin.orders.audit_modal.refund_mode', ['mode' => $payload['mode']]) }}
                            @endif
                            @if (isset($payload['refund_amount_cents']))
                                <span class="ml-2">{{ __('admin.orders.audit_modal.refund_amount', ['amount' => number_format($payload['refund_amount_cents'] / 100, 2, ',', '.')]) }}</span>
                            @endif
                        </p>
                    @endif

                    @if ($hasResendType)
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">
                            {{ __('admin.orders.audit_modal.resend_type', ['type' => $payload['type']]) }}
                            @if (isset($payload['email']))
                                <span class="text-gray-500 dark:text-gray-500">{{ $payload['email'] }}</span>
                            @endif
                        </p>
                    @endif
                </li>
            @endforeach
        </ol>

        {{-- Paginación nativa Filament. Recibe el LengthAwarePaginator y las
             opciones de tamaño de página. El selector de page-size enlaza
             con la property `$auditPerPage` (Livewire `WithPagination` la
             sincroniza con la query string). --}}
        <div class="pt-2">
            <x-filament::pagination
                :paginator="$paginator"
                :page-options="$perPageOptions"
                current-page-option-property="auditPerPage"
                extreme-links
            />
        </div>
    @endif
</div>
