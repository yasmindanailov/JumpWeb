@php($euros = fn (int $cents) => \App\Domain\Platform\Services\Money::amount($cents))

<x-filament::section :heading="__('admin.orders.create_manual.cart_title')" class="mt-6">
    @if (empty($this->cart))
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('admin.orders.create_manual.cart_empty_hint') }}
        </p>
    @else
        <ul class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($this->cart as $i => $line)
                <li class="flex items-start justify-between gap-3 py-2">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ $line['qty'] }}× {{ $line['label'] }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $line['when'] }}</p>
                        @if (! empty($line['addon_display']))
                            <ul class="mt-0.5 space-y-0.5">
                                @foreach ($line['addon_display'] as $ad)
                                    <li class="text-xs text-gray-500 dark:text-gray-400">
                                        + {{ $ad['qty'] }}× {{ $ad['name'] }}@if ($ad['free_qty'] > 0) <span class="text-emerald-600 dark:text-emerald-400">({{ $ad['free_qty'] >= $ad['qty'] ? __('tickets.addon_included') : __('tickets.addon_included_partial', ['count' => $ad['free_qty']]) }})</span>@endif
                                        — {{ $euros($ad['subtotal']) }} €
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        {{-- #225 (display): producto con señal → se cobra ahora solo la señal. --}}
                        @if (! is_null($line['deposit_cents'] ?? null))
                            <p class="mt-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                                {{ __('admin.orders.create_manual.deposit_line_note', ['amount' => $euros($line['deposit_cents'])]) }}
                            </p>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 whitespace-nowrap">
                        <span class="text-sm tabular-nums text-gray-700 dark:text-gray-300">
                            {{ $euros($line['line_total_cents']) }} €
                        </span>
                        <x-filament::icon-button
                            icon="heroicon-o-trash"
                            color="danger"
                            size="sm"
                            wire:click="removeLine({{ $i }})"
                            :label="__('admin.orders.create_manual.remove_line')"
                        />
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3 dark:border-white/10">
            <span class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.orders.create_manual.cart_total') }}
            </span>
            <span class="text-base font-bold tabular-nums text-gray-950 dark:text-white">
                {{ $euros($this->cartTotalCents()) }} €
            </span>
        </div>

        {{-- #225 (display): si hay productos con señal, se aclara cuánto se cobra AHORA (la señal) y
             cuánto queda para el parque. Espeja la landing; NO cambia el cobro (lo decide el alta). --}}
        @if ($this->cartHasDeposit())
            <div class="mt-2 space-y-1 rounded-lg bg-amber-50 p-3 text-sm ring-1 ring-amber-600/15 dark:bg-amber-400/10 dark:ring-amber-400/20">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-amber-800 dark:text-amber-300">{{ __('admin.orders.create_manual.pay_now_deposit') }}</span>
                    <span class="font-bold tabular-nums text-amber-800 dark:text-amber-300">{{ $euros($this->cartOnlineDueCents()) }} €</span>
                </div>
                <div class="flex items-center justify-between text-amber-700/90 dark:text-amber-400/90">
                    <span>{{ __('admin.orders.create_manual.pay_at_park') }}</span>
                    <span class="tabular-nums">{{ $euros($this->cartTotalCents() - $this->cartOnlineDueCents()) }} €</span>
                </div>
                <p class="text-xs leading-snug text-amber-700/80 dark:text-amber-400/70">{{ __('admin.orders.create_manual.deposit_hint') }}</p>
            </div>
        @endif
    @endif
</x-filament::section>
