{{-- ZONA 4 · Los complementos de venta posterior (`PliZona4`; F5 de la spec §4.11, `[DECIDIDO owner]` `#749`), en los
     bloques que dice cada enganche:
       · LA TARTA: su foto, «¿La tarta?» con una opción por complemento y «Sin tarta» (un radio `cake`), «¿Cuántas tartas?»
         (`cake_quantity`, con su cuenta) y, si los niños no caben en sus raciones, «Añadir otra tarta» — sin tarta grande
         (`#749`). Fuera de plazo, solo la elegida y «El plazo de la tarta pasó. Llámanos y lo vemos.».
       · PARA LOS PADRES: «¿Cuántos adultos se quedan?» (el campo general de tipo `adults`) y una familia por `family`, con
         su sugerencia debajo («Para 8 adultos: 1 Combo picoteo, 59 €» y «Ponerlo»), que nunca se pone sola.
       · Los que no dicen bloque, en grupos (una familia junta; cada suelto, solo).
     Desde K1 (§4.17, `[DECIDIDO owner]` `#806`/`#807`), en DOS: «Para los niños» (la tarta y los sueltos, con «Uno para cada
     niño» contado con los niños de la fiesta) y «Para los adultos» (lo de los padres). Un bloque vacío no se pinta.
     Los ids viajan SIEMPRE, también en los cerrados. Sin JavaScript: radios y campos numéricos, un formulario completo. --}}
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
        <div class="pli-tarta" id="pli-tarta" data-tarta data-tartas="{{ json_encode($ta['datos']) }}" data-sois="{{ $ta['sois'] }}" data-pista-plazo="{{ $ta['pista_plazo'] }}" data-pista-cambia="{{ $ta['pista_cambia'] }}" data-guardar-texto="{{ $ta['cuando'] !== '' ? __('fiesta.lista.tarta.guardar', ['cuando' => $ta['cuando']]) : '' }}" data-pronto="{{ $ta['pronto'] ? '1' : '0' }}">
            {{-- La tarta es el momento de la fiesta y el extra más alto: su foto, grande. Sin foto, sin marco. --}}
            @if ($ta['foto'] !== '')
                <x-pieza.marco kind="image" :src="$ta['foto']" :alt="__('fiesta.lista.tarta.foto')" aspect="16 / 9" flat />
            @endif
            @if ($ta['opciones'] !== [])
                <x-pieza.opciones name="cake" :label="__('fiesta.lista.tarta.pregunta')" :hint="$ta['abierta'] ? ($ta['elegida'] !== null ? $ta['pista_cambia'] : $ta['pista_plazo']) : ''" :columns="count($ta['opciones']) > 3 && $ta['abierta'] ? 2 : 3" :value="$ta['elegida']" :items="$ta['opciones']" />
            @else
                <p class="pli-tarta-l">{{ __('fiesta.lista.tarta.pregunta') }}</p>
            @endif
            @if ($ta['abierta'])
                {{-- «¿Cuántas tartas?» (el owner, 26-09: «no se ven cantidades»): la cantidad A LA VISTA y con su cuenta, con la
                     pieza de «¿Cuántos adultos se quedan?» (el `QuantityStepper` desnudo del diseño). Sin tarta elegida, con
                     JavaScript se esconde (`--sin`); sin él se ve siempre: se elige la tarta y su cantidad a la vez. --}}
                <div @class(['pli-adultos', 'pli-tarta-n', 'pli-tarta-n--sin' => ! $ta['con']]) data-tarta-cantidad>
                    <span class="pli-adultos-t"><strong>{{ __('fiesta.lista.tarta.cuantas') }}</strong><span data-tarta-cuenta>{{ $ta['cuenta'] }}</span></span>
                    <x-pieza.cantidad variant="bare" :label="__('fiesta.lista.tarta.cuantas')" :value="$ta['cantidad']" :min="1" :max="$ta['tope']" :labels="[__('fiesta.lista.tarta.una_menos'), __('fiesta.lista.tarta.una_mas')]" name="cake_quantity" id="pli-tarta-n" />
                </div>
                {{-- «Sois 14 y la tarta es de 12 raciones.» con «Añadir otra tarta» (el + de arriba), solo mientras no llegue.
                     Lo pinta `lista.js` con lo que se teclea. --}}
                <p class="pli-sug" data-tarta-sug hidden><span data-tarta-sug-texto></span><x-pieza.boton variant="quiet" size="sm" data-tarta-otra><x-slot:izquierda><x-lucide name="plus" :size="16" /></x-slot:izquierda>{{ __('fiesta.lista.tarta.otra') }}</x-pieza.boton></p>
            @else
                {{-- Cerrada, la cantidad también se ve (solo leída): «2 tartas · 24 raciones · 50,00 €». --}}
                @if ($ta['con'])<p class="pli-tarta-fija" data-tarta-fija>{{ trans_choice('fiesta.lista.tarta.fija', $ta['cantidad'], ['count' => $ta['cantidad'], 'cuenta' => $ta['cuenta']]) }}</p>@endif
                <x-pieza.aviso tone="warn" size="sm"><x-slot:icono><x-lucide name="clock-alert" :size="17" /></x-slot:icono>{{ '' }}{{ __('fiesta.lista.tarta.pasada') }}{!! $llamanos !!}</x-pieza.aviso>
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
