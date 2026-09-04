{{-- Las FRANJAS del día elegido, en chips de dos niveles (`DECISIONES #241`).

     `[OWNER, 2026-08-28]`: «lo de las plazas debería mostrarse de manera más sutil, no al mismo nivel
     que la hora». En `#240` esto era un `ToggleButtons` nativo y su etiqueta es **texto plano** —no
     admite `allowHtml`—, así que «10:00 · 20 plazas» salía todo con el mismo peso. Aquí la hora manda
     y el cupo queda de contexto.

     ❗ **Cambiar el control NO cambia la regla**: una franja no vendible sale DESHABILITADA y no
     escondida —igual que en la web—, lo decide el mismo `timeMap()` de `SlotOffer` (`AFORO-02`) y
     `pickTime()` lo vuelve a comprobar en el servidor.

     ⚠️ **El bloque se trae a la vista al elegir día** (`#464`): con el calendario grande delante, las
     franjas caen fuera de una tablet de 810 px, y sin esto el operador elige día y **no ve pasar
     nada**. Lo dispara `pickDay()` en el servidor —un evento, no un estado—, así que solo ocurre en
     el gesto que lo justifica y no en cada render. `block: 'end'` deja a la vista lo más posible del
     calendario que se acaba de usar. --}}
<div class="cmo-times"
     x-data
     x-on:cmo-day-chosen.window="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'end' }))"
>
    <span class="fi-fo-field-label-content">{{ $label }}</span>

    @if ($times === [])
        <p class="cmo-times__help">{{ $help }}</p>
    @else
        <div class="cmo-times__track" role="group" aria-label="{{ $label }}">
            @foreach ($times as $slot)
                <button
                    type="button"
                    @class(['cmo-times__slot', 'is-selected' => $slot['selected']])
                    wire:key="cmo-time-{{ $slot['time'] }}"
                    @disabled(! $slot['sellable'])
                    @if ($slot['selected']) aria-current="true" @endif
                    wire:click="pickTime('{{ $slot['time'] }}')"
                >
                    <span class="cmo-times__h">{{ $slot['label'] }}</span>
                    <span class="cmo-times__seats">{{ __('admin.orders.create_manual.seats', ['n' => $slot['seats']]) }}</span>
                </button>
            @endforeach
        </div>
        <p class="cmo-times__help">{{ $help }}</p>
    @endif
</div>
