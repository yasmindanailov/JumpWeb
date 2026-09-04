{{-- El CALENDARIO del paso «Cuándo» (`DECISIONES #464`, T3).

     `[owner]`: «la fecha la selecciona de un calendario grande, bien visible». Sustituye a la tira
     de 14 días y al `DatePicker` plegado tras su CTA de `#241`: medido antes de tocarlo, aquel
     calendario era un **popover de 259×248 px con celdas de 29×28** —bajo el mínimo táctil de 44— y
     costaba dos toques abrirlo, en una pantalla que se usa con el dedo.

     ⚠️ **Los datos los compone el SERVIDOR** (`calendarMonth()`, sobre `SlotOffer`): aquí no se
     decide qué día se ofrece ni se calcula ninguna fecha. Y el clic va a `pickDay()`, que **vuelve a
     comprobar** que la fecha esté en la oferta: el navegador propone, el servidor decide
     (`AFORO-02`).

     ⚠️ **Las PLAZAS no salen por día** (`[DECIDIDO owner]` D2): pintarlas aquí costaría 709 consultas
     y 11,4 s, porque la disponibilidad se resuelve día a día y no hay vía agregada por rango. Un día
     dice si se puede reservar; cuánto queda lo dicen las horas, justo debajo. --}}
<div class="cmo-cal" role="group" aria-labelledby="cmo-cal-label">
    <span id="cmo-cal-label" class="fi-fo-field-label-content">{{ $label }}</span>

    <div class="cmo-cal__box">
        <div class="cmo-cal__head">
            {{-- Las flechas saltan al mes OFRECIBLE anterior/siguiente, y **no existen** si no hay:
                 un control que no lleva a ninguna parte es peor que su ausencia. --}}
            @if ($mes['prev'])
                <button type="button" class="cmo-cal__nav" wire:click="goToMonth('{{ $mes['prev'] }}')"
                        aria-label="{{ __('admin.orders.create_manual.calendar_prev') }}">
                    <span aria-hidden="true">‹</span>
                </button>
            @else
                <span class="cmo-cal__nav is-empty" aria-hidden="true"></span>
            @endif

            <p class="cmo-cal__month" aria-live="polite">{{ $mes['label'] }}</p>

            @if ($mes['next'])
                <button type="button" class="cmo-cal__nav" wire:click="goToMonth('{{ $mes['next'] }}')"
                        aria-label="{{ __('admin.orders.create_manual.calendar_next') }}">
                    <span aria-hidden="true">›</span>
                </button>
            @else
                <span class="cmo-cal__nav is-empty" aria-hidden="true"></span>
            @endif
        </div>

        <div class="cmo-cal__grid" role="presentation">
            @foreach ($mes['weekdays'] as $inicial)
                <span class="cmo-cal__wd">{{ $inicial }}</span>
            @endforeach

            @foreach ($mes['weeks'] as $semana)
                @foreach ($semana as $dia)
                    @if ($dia === null)
                        {{-- Relleno de otro mes: ni número ni diana. Dos grises que significan cosas
                             distintas —«no es de este mes» y «no se vende»— se pulsan igual de mal. --}}
                        <span class="cmo-cal__pad" aria-hidden="true"></span>
                    @else
                        <button
                            type="button"
                            @class([
                                'cmo-cal__day',
                                'is-selected' => $dia['selected'],
                                'is-today' => $dia['today'],
                            ])
                            wire:key="cmo-cal-{{ $dia['date'] }}"
                            @disabled(! $dia['offerable'])
                            @if ($dia['selected']) aria-current="date" @endif
                            wire:click="pickDay('{{ $dia['date'] }}')"
                        >{{ $dia['day'] }}</button>
                    @endif
                @endforeach
            @endforeach
        </div>
    </div>
</div>
