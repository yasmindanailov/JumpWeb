<x-filament-panels::page>
    {{-- Indicador de pasos --}}
    <ol class="flex flex-wrap items-center gap-2 text-sm">
        @foreach ($this->stepLabels() as $n => $label)
            <li @class([
                'inline-flex items-center gap-2 rounded-full px-3 py-1 ring-1 ring-inset',
                'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30' => $this->step === $n,
                'bg-gray-50 text-gray-500 ring-gray-950/10 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10' => $this->step !== $n,
            ])>
                <span @class([
                    'flex h-5 w-5 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-primary-600 text-white' => $this->step >= $n,
                    'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $this->step < $n,
                ])>{{ $n }}</span>
                {{ $label }}
            </li>
            @if (! $loop->last)
                <li aria-hidden="true" class="text-gray-300 dark:text-gray-600">—</li>
            @endif
        @endforeach
    </ol>

    {{ $this->form }}

    {{-- #263: aviso de cliente duplicado por teléfono (alta sin email): «avisar y dejar elegir».
         Aparece en el paso Cliente cuando el teléfono tecleado ya existe. El operador reutiliza un
         cliente existente o crea uno nuevo de todos modos. --}}
    @if ($this->phoneMatchOptions !== [] && $this->step === \App\Filament\Pages\CreateManualOrderPage::STEP_CUSTOMER)
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">{{ __('admin.orders.create_manual.phone_match_title') }}</x-slot>
            <x-slot name="description">
                {{ __('admin.orders.create_manual.phone_match_help', ['phone' => $this->pendingNoEmailCustomer['phone'] ?? '']) }}
            </x-slot>

            <div class="flex flex-col gap-2">
                @foreach ($this->phoneMatchOptions as $matchId => $matchLabel)
                    <x-filament::button
                        color="primary"
                        icon="heroicon-o-user"
                        wire:click="useExistingCustomer({{ $matchId }})"
                    >
                        {{ __('admin.orders.create_manual.phone_match_use', ['name' => $matchLabel]) }}
                    </x-filament::button>
                @endforeach

                <div class="flex flex-wrap items-center gap-2 pt-2">
                    <x-filament::button color="gray" icon="heroicon-o-user-plus" wire:click="createNewCustomerAnyway">
                        {{ __('admin.orders.create_manual.phone_match_create_new') }}
                    </x-filament::button>
                    <x-filament::button color="gray" :outlined="true" wire:click="dismissPhoneMatch">
                        {{ __('admin.orders.create_manual.phone_match_dismiss') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- Resumen del pedido (carrito): siempre visible. --}}
    @include('filament.pages.partials.manual-order-cart')

    {{-- Navegación del asistente, DEBAJO del resumen. --}}
    <div class="flex items-center justify-between gap-3">
        <x-filament::button
            color="gray"
            icon="heroicon-o-arrow-left"
            wire:click="back"
            :disabled="$this->step === \App\Filament\Pages\CreateManualOrderPage::STEP_CUSTOMER"
        >
            {{ __('admin.orders.create_manual.back') }}
        </x-filament::button>

        @if ($this->step < \App\Filament\Pages\CreateManualOrderPage::STEP_PAYMENT)
            <x-filament::button
                icon="heroicon-o-arrow-right"
                icon-position="after"
                wire:click="next"
                :disabled="! $this->canAdvance()"
            >
                {{ __('admin.orders.create_manual.next') }}
            </x-filament::button>
        @else
            {{-- Action de Filament con confirmación en MODAL nativo del panel (antes `wire:confirm`,
                 el diálogo del navegador). Ver CreateManualOrderPage::createOrderAction(). --}}
            {{ $this->createOrderAction }}
        @endif
    </div>
</x-filament-panels::page>
