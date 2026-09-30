{{-- UN GRUPO de complementos de la zona 4 (`PliFamilia`): los de una misma familia, con su título, o uno suelto (K1 de §4.17).
     Es la misma pieza para los padres y para los niños; lo que cambia es con QUÉ cuenta `lista.js` la sugerencia (los adultos
     que se quedan o los niños de la fiesta), y eso lo dice la sección que la contiene. El estado va DEBAJO de las tarjetas:
     si cambia, nada de lo que se toca se mueve bajo el dedo. --}}
<div @class(['pli-fam', 'pli-fam--titulo' => $g['titulo'] !== '']) data-familia>
    @if ($g['titulo'] !== '')<h4 class="pli-h4">{{ $g['titulo'] }}</h4>@endif
    <div class="pli-grid2">
        @foreach ($g['tarjetas'] as $e)
            @include('fiesta.lista.tarjeta-extra', ['e' => $e, 'x' => $x])
        @endforeach
    </div>
    @if ($g['uno'] ?? false)
        {{-- «Uno para cada niño» (K1, `#807`): el `Tag` de los calcetines de la calculadora («Un par para cada niño»), marcado
             cuando la cantidad ya cubre a todos. Sin caja ni frase: es un toque. Sin JavaScript no hace nada, así que no sale. --}}
        <p class="pli-uno" data-uno hidden><x-pieza.etiqueta boton data-uno-boton aria-pressed="false" :count="$sois ?? 0">{{ __('fiesta.lista.ninos.uno_para_cada') }}</x-pieza.etiqueta></p>
    @else
        <p class="pli-sug" data-familia-sug hidden><span data-familia-sug-texto></span><x-pieza.boton variant="quiet" size="sm" data-familia-poner><x-slot:izquierda><x-lucide name="plus" :size="16" /></x-slot:izquierda><span data-familia-poner-texto>{{ trans_choice('fiesta.lista.padres.poner', 1) }}</span></x-pieza.boton></p>
        <p class="pli-sug ok" data-familia-ok hidden><span class="pli-cubre"><x-lucide name="circle-check" :size="15" /><span data-familia-ok-texto></span></span></p>
    @endif
</div>
