<x-filament-panels::page>
    {{-- Indicador de pasos --}}
    <ol class="cmo-steps">
        @foreach ($this->stepLabels() as $n => $label)
            <li @class(['cmo-steps__item', 'is-current' => $this->step === $n])>
                <span @class(['cmo-steps__num', 'is-done' => $this->step >= $n])>{{ $n }}</span>
                {{ $label }}
            </li>
            @if (! $loop->last)
                <li aria-hidden="true" class="cmo-steps__sep">—</li>
            @endif
        @endforeach
    </ol>

    {{-- ⚠️ **DOS COLUMNAS, y es la decisión de la tanda U7** (`DECISIONES #240`,
         `specs/panel-navegacion.md` §9). Medido en iPad horizontal (1080×810) antes de tocar nada:
         el paso 2 con un producto elegido medía **1.292 px** —se pasaba **482**— y con él quedaban
         fuera de pantalla las DOS cosas que el gerente necesita ver con el cliente delante: el
         **resumen del pedido** (a 1.100 px) y el botón de avanzar. Todo apilado en una columna de
         648 px dentro de un lienzo apaisado.

         La columna derecha es **pegajosa**: lo que se lleva y lo que se cobra no se pierden nunca de
         vista mientras se monta el pedido a la izquierda. Por debajo de la tablet se apilan, y
         entonces el resumen vuelve a ir detrás del formulario — que es el orden correcto en vertical. --}}
    <div class="cmo-layout">
        <div class="cmo-main">
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
        </div>

        <aside class="cmo-aside">
            {{-- Resumen del pedido (carrito): siempre visible. --}}
            @include('filament.pages.partials.manual-order-cart')

            {{-- ⚠️ **La navegación vive AQUÍ, con el resumen, y no debajo del formulario.** Es el gesto
                 más repetido de la pantalla y el que decide el cobro: en la columna pegajosa está
                 siempre a la misma altura del pulgar, se llene lo que se llene el formulario. --}}
            <div class="cmo-nav">
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
        </aside>
    </div>
</x-filament-panels::page>
