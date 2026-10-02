{{-- ZONA 4 · Los complementos de venta posterior (`PliZona4`; F5 de la spec §4.11, `[DECIDIDO owner]` `#749`), en los
     bloques que dice cada enganche:
       · LA TARTA (K2 de §4.17, `#807`: varias a la vez): «¿La tarta?» con una tarjeta por tarta del panel, cada una con su
         cantidad; debajo, sus raciones contra los niños; y «Sin tarta», una casilla. Fuera de plazo, las pedidas (cerradas)
         o «Sin tarta», y «El plazo de la tarta pasó. Llámanos y lo vemos.».
       · PARA LOS PADRES: «¿Cuántos adultos se quedan?» (el campo general de tipo `adults`) y una familia por `family`, con
         su sugerencia debajo («Para 8 adultos: 1 Combo picoteo, 59 €» y «Ponerlo»), que nunca se pone sola.
       · Los que no dicen bloque, en grupos (una familia junta; cada suelto, solo).
     Desde K1 (§4.17, `[DECIDIDO owner]` `#806`/`#807`), en DOS: «Para los niños» (la tarta y los sueltos, con «Uno para cada
     niño» contado con los niños de la fiesta) y «Para los adultos» (lo de los padres). Un bloque vacío no se pinta.
     Los ids viajan SIEMPRE, también en los cerrados. Sin JavaScript: casillas y campos numéricos, un formulario completo. --}}
@php
    $x = $m['extras'];
    $ta = $x['tarta'];
    $pa = $x['padres'];
    $ni = $x['ninos'];
    $llamanos = ' <a href="tel:'.e($x['tel']).'">'.e(__('fiesta.lista.numero.llamanos')).'</a>'.e(__('fiesta.lista.numero.y_lo_vemos'));
@endphp
<section class="pli-zona" data-zona="4" aria-labelledby="pli-h-extras" data-extras>
    <h2 id="pli-h-extras" class="pli-h2">{{ __('fiesta.lista.extras.titular') }}</h2>
    @if ($ni !== null)
    <div class="pli-ninos-g" id="pli-extras-ninos" data-ninos data-sois="{{ $ni['sois'] }}">
    <h3 class="pli-h3">{{ __('fiesta.lista.ninos.titulo') }}</h3>
    @if ($ta !== null)
        {{-- Sin aviso ni texto de plazo propio (`#912`, P1·b): la hora de la lista la dice la cabecera, siempre. --}}
        <div class="pli-tarta" id="pli-tarta" data-tarta data-sois="{{ $ta['sois'] }}">
            <h4 class="pli-h4">{{ __('fiesta.lista.tarta.pregunta') }}</h4>
            {{-- Una tarjeta por tarta del panel (su foto, «De 12 raciones», su precio y su tope): se piden varias a la vez. --}}
            @if ($ta['tarjetas'] !== [])
                <div class="pli-grid2">
                    @foreach ($ta['tarjetas'] as $e)
                        @include('fiesta.lista.tarjeta-extra', ['e' => $e, 'x' => $x])
                    @endforeach
                </div>
            @endif
            {{-- Las raciones de lo pedido contra los niños (`racionesTarta()`): «Sois 14 y la tarta es de 12 raciones.» o «Cubre a
                 los 14 niños.». Las pinta `lista.js` con lo que se teclea; sin JavaScript, no salen. --}}
            <p class="pli-sug" data-tarta-sug hidden><span data-tarta-sug-texto></span></p>
            <p class="pli-sug ok" data-tarta-ok hidden><span class="pli-cubre"><x-lucide name="circle-check" :size="15" /><span data-tarta-ok-texto></span></span></p>
            @if ($ta['abierta'])
                {{-- «Sin tarta» decide. El 0 oculto va DELANTE: marcada, manda el 1; desmarcada, el 0
                     —sin él no se sabría si la desmarcó o no la vio—. Solo con alguna tarta en plazo, como el controlador. --}}
                <input type="hidden" name="cake_declined" value="0">
                <x-pieza.casilla name="cake_declined" value="1" id="pli-sin-tarta" :checked="$ta['declinada']" :label="__('fiesta.lista.tarta.sin')" />
            @else
                {{-- Cerrada, sin «pasó»: el plazo de la lista lo dice su cabecera (`#912`). Lo pedido, en sus tarjetas; sin nada
                     pedido (o «Sin tarta» decidido), «Sin tarta», que es lo que hay. --}}
                @if ($ta['declinada'] || $ta['tarjetas'] === [])<p class="pli-tarta-fija">{{ __('fiesta.lista.tarta.sin') }}</p>@endif
            @endif
        </div>
    @endif
    {{-- Los sueltos, de dos en dos en escritorio como lo de los padres (cada uno con su «Uno para cada niño» debajo); una
         familia con título ocupa la fila entera. --}}
    @if ($ni['grupos'] !== [])
    <div class="pli-grupos">
    @foreach ($ni['grupos'] as $g)
        @include('fiesta.lista.grupo-extras', ['g' => $g, 'x' => $x, 'sois' => $ni['sois']])
    @endforeach
    </div>
    @endif
    </div>
    @endif
    @if ($pa !== null)
        <div class="pli-padres-g" id="pli-extras-padres" data-padres>
            <div class="pli-padres-cab">
                <h3 class="pli-h3">{{ __('fiesta.lista.padres.titulo') }}</h3>
                <p class="pli-sub">{{ __('fiesta.lista.padres.sub') }}</p>
            </div>
            @if ($pa['adultos'] !== null)
                <div class="pli-adultos" data-adultos>
                    <span class="pli-adultos-t"><strong>{{ __('fiesta.lista.padres.adultos') }}</strong><span>{{ __('fiesta.lista.padres.adultos_hint') }}</span></span>
                    <x-pieza.cantidad variant="bare" :label="__('fiesta.lista.padres.adultos')" :value="$pa['adultos']['valor']" :min="0" :max="\App\Domain\Booking\Models\TicketType::ADULTS_MAX" :labels="[__('fiesta.lista.padres.uno_menos'), __('fiesta.lista.padres.uno_mas')]" :name="$pa['adultos']['name']" id="pli-adultos" />
                </div>
            @endif
            @foreach ($pa['familias'] as $fam)
                @include('fiesta.lista.grupo-extras', ['g' => $fam, 'x' => $x])
            @endforeach
        </div>
    @endif
    <p class="pli-pie-z"><x-lucide name="wallet" :size="16" />{{ $x['pie'] }}</p>
</section>
