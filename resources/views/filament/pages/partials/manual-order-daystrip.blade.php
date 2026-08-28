{{-- La TIRA DE DÍAS RÁPIDOS del paso 2 (`DECISIONES #240`, flechas en `#241`).

     Los primeros 14 días OFRECIBLES del producto, para el caso normal del mostrador: reservar para
     hoy o para dentro de poco con un solo toque. El calendario amplio vive detrás de su CTA — un
     cumpleaños se reserva con meses de antelación y eso no se alcanza deslizando.

     ⚠️ **Los datos los compone el SERVIDOR** (`quickDays()`, sobre `SlotOffer`): aquí no se decide
     qué día se ofrece ni se calcula ninguna fecha. Y el clic va a `pickQuickDay()`, que **vuelve a
     comprobar** que la fecha esté en la oferta: el navegador propone, el servidor decide.

     ⚠️⚠️ **Las FLECHAS repiten la aritmética de `resources/js/sidebar/strip.js`, y eso está asumido**:
     el panel no carga el bundle del cajón, así que no hay forma de compartir el módulo. Los dos
     números —el 80 % de salto y el píxel de tolerancia— **tienen que seguir siendo los mismos**; la
     tolerancia no es defensiva: sin ella la flecha «siguiente» no se apaga nunca, porque `scrollLeft`
     es fraccionario con zoom o en pantallas de densidad alta. --}}
<div
    class="cmo-daystrip"
    role="group"
    aria-labelledby="cmo-daystrip-label"
    x-data="{
        prev: false,
        next: false,
        sync() {
            const t = $refs.track;
            if (! t) return;
            this.prev = t.scrollLeft > 1;
            this.next = t.scrollLeft + t.clientWidth < t.scrollWidth - 1;
        },
        move(d) {
            const t = $refs.track;
            t.scrollBy({ left: d * Math.max(1, Math.round(t.clientWidth * 0.8)), behavior: 'smooth' });
        },
    }"
    x-init="$nextTick(() => sync())"
>
    {{-- El rótulo es el del CAMPO de fecha: la tira es el control principal, y el calendario amplio
         entra por su CTA. Va con la clase de etiqueta de Filament para que se lea igual que las demás. --}}
    <span id="cmo-daystrip-label" class="fi-fo-field-label-content">{{ __('admin.orders.create_manual.date') }}</span>

    <div class="cmo-daystrip__body">
        {{-- Las flechas SOLO se pintan donde hay ratón (lo decide el CSS) y solo si llevan a algún
             sitio (lo decide el estado). Son dos preguntas distintas y se responden por separado. --}}
        <button type="button" class="cmo-daystrip__nav cmo-daystrip__nav--prev"
                x-show="prev" x-cloak x-on:click="move(-1)"
                aria-label="{{ __('admin.orders.create_manual.strip_prev') }}"><span aria-hidden="true"></span></button>
        <button type="button" class="cmo-daystrip__nav cmo-daystrip__nav--next"
                x-show="next" x-cloak x-on:click="move(1)"
                aria-label="{{ __('admin.orders.create_manual.strip_next') }}"><span aria-hidden="true"></span></button>

        <div class="cmo-daystrip__track" x-ref="track" x-on:scroll.passive="sync()">
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
</div>
