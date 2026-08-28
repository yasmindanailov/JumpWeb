{{-- Las FRANJAS del día elegido, en chips de dos niveles (`DECISIONES #241`).

     `[OWNER, 2026-08-28]`: «lo de las plazas debería mostrarse de manera más sutil, no al mismo nivel
     que la hora». En `#240` esto era un `ToggleButtons` nativo y su etiqueta es **texto plano** —no
     admite `allowHtml`—, así que «10:00 · 20 plazas» salía todo con el mismo peso. Aquí la hora manda
     y el cupo queda de contexto.

     ❗ **Cambiar el control NO cambia la regla**: una franja no vendible sale DESHABILITADA y no
     escondida —igual que en la web—, lo decide el mismo `timeMap()` de `SlotOffer` (`AFORO-02`) y
     `pickTime()` lo vuelve a comprobar en el servidor. --}}
<div class="cmo-times">
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
