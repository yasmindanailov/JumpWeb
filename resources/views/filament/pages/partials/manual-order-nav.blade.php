@php
    use App\Filament\Pages\CreateManualOrderPage as Cmo;

    $paso = $this->step;
@endphp

{{-- La NAVEGACIÓN del asistente (`#462`).

     ⚠️⚠️ **El botón que hace avanzar CAMBIA de rótulo y de trabajo según el paso**, y eso no es un
     capricho: el último paso de una línea no «avanza», la AÑADE; el del carrito no «avanza», lleva a
     pagar. Un «Siguiente» genérico en los tres sitios obliga a leer la pantalla para saber qué va a
     pasar, que es exactamente la fricción que esta tanda viene a quitar.

     ⚠️ **Cuál es el último paso de la línea es VARIABLE** —Extras si el producto los tiene; si no,
     Datos; si no, Cuándo— y lo decide `isLastLineStep()`. Por eso el botón vive aquí y no dentro del
     formulario de un paso fijo: la navegación es lo único que sabe en qué paso está.

     ⚠️ Todos los botones que deciden algo llevan `fi-jj-action`, la escala de acción primaria del
     panel (`#461`): 44 px de alto y un solo tamaño para todos. --}}
<div class="cmo-nav">
    {{-- ⚠️ **Solo el ICONO, con su fondo** (`#242`, `[OWNER]`). El rótulo «Atrás» competía con el
         botón que hace avanzar, que es el que se busca. ▶ **El nombre accesible NO se pierde**: sin
         `aria-label` un botón de solo icono queda MUDO para un lector de pantalla. --}}
    <x-filament::button
        color="gray"
        icon="heroicon-o-arrow-left"
        wire:click="back"
        :disabled="$paso === Cmo::STEP_CUSTOMER"
        :aria-label="__('admin.orders.create_manual.back')"
        :title="__('admin.orders.create_manual.back')"
        class="cmo-nav__back fi-jj-action"
    />

    @if ($paso === Cmo::STEP_PAYMENT)
        {{-- Action de Filament con confirmación en MODAL nativo del panel. --}}
        {{ $this->createOrderAction }}
    @elseif ($paso === Cmo::STEP_CART)
        <x-filament::button
            color="gray"
            icon="heroicon-o-plus"
            wire:click="addMoreProducts"
            class="cmo-nav__more fi-jj-action"
        >
            {{ __('admin.orders.create_manual.add_more_products') }}
        </x-filament::button>

        <x-filament::button
            icon="heroicon-o-credit-card"
            icon-position="after"
            wire:click="next"
            :disabled="! $this->canAdvance()"
            class="cmo-nav__pay fi-jj-action"
        >
            {{ __('admin.orders.create_manual.go_to_pay') }}
        </x-filament::button>
    @elseif ($this->isLastLineStep())
        <x-filament::button
            icon="heroicon-o-plus"
            wire:click="next"
            :disabled="! $this->canAdvance()"
            class="cmo-nav__add fi-jj-action"
        >
            {{ __('admin.orders.create_manual.add_to_cart') }}
        </x-filament::button>
    @else
        <x-filament::button
            icon="heroicon-o-arrow-right"
            icon-position="after"
            wire:click="next"
            :disabled="! $this->canAdvance()"
            class="fi-jj-action"
        >
            {{ __('admin.orders.create_manual.next') }}
        </x-filament::button>
    @endif
</div>
