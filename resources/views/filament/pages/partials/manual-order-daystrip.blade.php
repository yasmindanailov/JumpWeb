{{-- La TIRA DE DÍAS RÁPIDOS del paso 2 (`DECISIONES #240`, U7).

     Los primeros 14 días OFRECIBLES del producto, para el caso normal del mostrador: reservar para
     hoy o para dentro de poco con un solo toque. El calendario sigue justo debajo para el salto
     largo — un cumpleaños se reserva con meses de antelación y eso no se alcanza deslizando.

     ⚠️ **Los datos los compone el SERVIDOR** (`quickDays()`, sobre `SlotOffer`): aquí no se decide
     qué día se ofrece ni se calcula ninguna fecha. Y el clic va a `pickQuickDay()`, que **vuelve a
     comprobar** que la fecha esté en la oferta: el navegador propone, el servidor decide. --}}
<div class="cmo-daystrip" role="group" aria-labelledby="cmo-daystrip-label">
    {{-- El rótulo es el del CAMPO de fecha: la tira es el control principal, y el calendario de abajo
         pasa a llamarse «Otra fecha». Va con la clase de etiqueta de Filament para que se lea igual
         que las demás. --}}
    <span id="cmo-daystrip-label" class="fi-fo-field-label-content">{{ __('admin.orders.create_manual.date') }}</span>
    <div class="cmo-daystrip__track">
        @foreach ($days as $day)
            <button
                type="button"
                @class(['cmo-daystrip__day', 'is-selected' => $day['selected']])
                @if ($day['selected']) aria-current="date" @endif
                wire:key="cmo-day-{{ $day['date'] }}"
                wire:click="pickQuickDay('{{ $day['date'] }}')"
            >
                <span class="cmo-daystrip__wd">{{ $day['weekday'] }}</span>
                <span class="cmo-daystrip__num">{{ $day['day'] }}</span>
            </button>
        @endforeach
    </div>
</div>
