@php
    use App\Filament\Pages\CreateManualOrderPage as Cmo;

    $paso = $this->step;
    $enCarrito = $paso === Cmo::STEP_CART;
    $elecciones = $this->stepChoices();
@endphp

<x-filament-panels::page>
    {{-- ─── Indicador de pasos ────────────────────────────────────────────────────────────────
         `#462`. Tres cosas que NO son decoración:

         1. **Enseña lo ELEGIDO**, no solo el número. Es lo que hace seguro el auto-avance: si el
            operador se equivoca de producto lo ve escrito, en vez de retroceder a ciegas.
         2. **Los pasos ya pasados son BOTONES** y llevan de vuelta. Hacia adelante no: saltaría
            preguntas sin contestar.
         3. **Un paso SALTADO se pinta saltado.** La mayoría de los productos no tiene campos ni
            complementos (medido: 16 de 18 sin campos), así que el asistente se salta pasos casi
            siempre — y un salto silencioso se lee como un fallo. --}}
    <ol class="cmo-steps">
        @foreach ($this->stepLabels() as $n => $label)
            @php
                $vacio = $this->stepIsSkipped($n);
                $pasado = $paso > $n && ! $vacio;
                $elegido = $elecciones[$n] ?? null;
            @endphp

            <li @class([
                'cmo-steps__item',
                'is-current' => $paso === $n,
                'is-skipped' => $vacio,
            ])>
                @if ($pasado)
                    <button type="button" class="cmo-steps__go" wire:click="goToStep({{ $n }})">
                        <span class="cmo-steps__num is-done">{{ $n }}</span>
                        <span class="cmo-steps__text">
                            <span class="cmo-steps__label">{{ $label }}</span>
                            @if (filled($elegido))
                                <span class="cmo-steps__choice">{{ $elegido }}</span>
                            @endif
                        </span>
                    </button>
                @else
                    <span class="cmo-steps__go">
                        <span @class(['cmo-steps__num', 'is-done' => $paso > $n])>{{ $n }}</span>
                        <span class="cmo-steps__text">
                            <span class="cmo-steps__label">{{ $label }}</span>
                            @if ($vacio)
                                <span class="cmo-steps__choice">{{ __('admin.orders.create_manual.step_skipped') }}</span>
                            @elseif (filled($elegido))
                                <span class="cmo-steps__choice">{{ $elegido }}</span>
                            @endif
                        </span>
                    </span>
                @endif
            </li>

            @if (! $loop->last)
                <li aria-hidden="true" class="cmo-steps__sep">—</li>
            @endif
        @endforeach
    </ol>

    {{-- ⚠️ **DOS COLUMNAS, y es la decisión de la tanda U7** (`DECISIONES #240`,
         `specs/panel-navegacion.md` §9). Medido en iPad horizontal (1080×810) antes de tocar nada:
         el paso denso medía **1.292 px** —se pasaba **482**— y con él quedaban fuera de pantalla las
         DOS cosas que el gerente necesita ver con el cliente delante: el **resumen del pedido** y el
         botón de avanzar.

         ▶ **`#462`: el paso del CARRITO va a UNA columna** (`[DECIDIDO owner]`: «quiero que el
         carrito ocupe toda la pantalla»). Ahí el resumen deja de ser un acompañante y pasa a ser el
         asunto, así que tenerlo además en la columna de al lado sería enseñarlo dos veces. --}}
    <div @class(['cmo-layout', 'cmo-layout--single' => $enCarrito])>
        <div class="cmo-main">
            @if ($enCarrito)
                @include('filament.pages.partials.manual-order-cart')
                @include('filament.pages.partials.manual-order-nav')
            @else
                {{ $this->form }}

                {{-- #263: aviso de cliente duplicado por teléfono (alta sin email): «avisar y dejar
                     elegir». Aparece en el paso Cliente cuando el teléfono tecleado ya existe. --}}
                @if ($this->phoneMatchOptions !== [] && $paso === Cmo::STEP_CUSTOMER)
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
            @endif
        </div>

        @unless ($enCarrito)
            <aside class="cmo-aside">
                {{-- Resumen del pedido (carrito): siempre visible mientras se monta una línea. --}}
                @include('filament.pages.partials.manual-order-cart')

                {{-- ⚠️ **La navegación vive AQUÍ, con el resumen, y no debajo del formulario.** Es el
                     gesto más repetido de la pantalla y el que decide el cobro: en la columna
                     pegajosa está siempre a la misma altura del pulgar, se llene lo que se llene el
                     formulario. --}}
                @include('filament.pages.partials.manual-order-nav')
            </aside>
        @endunless
    </div>
</x-filament-panels::page>
