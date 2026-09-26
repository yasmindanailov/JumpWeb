{{-- ZONA 4 · Los complementos de venta posterior (`PliZona4`; F5 de la spec §4.11, `[DECIDIDO owner]` `#749`), en los
     bloques que dice cada enganche:
       · LA TARTA: su foto, «¿La tarta?» con una opción por complemento y «Sin tarta» (un radio `cake`), y si los niños no
         caben en sus raciones, «Añadir otra tarta» (`cake_quantity`) — sin tarta grande (`#749`). Fuera de plazo, solo la
         elegida y «El plazo de la tarta pasó. Llámanos y lo vemos.».
       · PARA LOS PADRES: «¿Cuántos adultos se quedan?» (el campo general de tipo `adults`) y una familia por `family`, con
         su sugerencia debajo («Para 8 adultos: 1 Combo picoteo, 59 €» y «Ponerlo»), que nunca se pone sola.
       · Los que no dicen bloque, en la rejilla de siempre.
     Los ids viajan SIEMPRE, también en los cerrados. Sin JavaScript: radios y campos numéricos, un formulario completo. --}}
@php
    $x = $m['extras'];
    $ta = $x['tarta'];
    $pa = $x['padres'];
    $llamanos = ' <a href="tel:'.e($x['tel']).'">'.e(__('fiesta.lista.numero.llamanos')).'</a>'.e(__('fiesta.lista.numero.y_lo_vemos'));
@endphp
<section class="pli-zona" data-zona="4" aria-labelledby="pli-h-extras" data-extras>
    <h2 id="pli-h-extras" class="pli-h2">{{ __('fiesta.lista.extras.titular') }}</h2>
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
                <input type="hidden" name="cake_quantity" value="{{ $ta['cantidad'] }}">
                {{-- «Sois 14 y la tarta es de 12 raciones.» con «Añadir otra tarta»; con dos o más, lo dice y deja quitar
                     una. Lo pinta `lista.js` con lo que se teclea (sin JavaScript, la cantidad no cambia). --}}
                <p class="pli-sug" data-tarta-sug hidden><span data-tarta-sug-texto></span><x-pieza.boton variant="quiet" size="sm" data-tarta-otra><x-slot:izquierda><x-lucide name="plus" :size="16" /></x-slot:izquierda>{{ __('fiesta.lista.tarta.otra') }}</x-pieza.boton><x-pieza.boton variant="quiet" size="sm" data-tarta-quitar hidden><x-slot:izquierda><x-lucide name="minus" :size="16" /></x-slot:izquierda>{{ __('fiesta.lista.tarta.quitar') }}</x-pieza.boton></p>
            @else
                <x-pieza.aviso tone="warn" size="sm"><x-slot:icono><x-lucide name="clock-alert" :size="17" /></x-slot:icono>{{ '' }}{{ __('fiesta.lista.tarta.pasada') }}{!! $llamanos !!}</x-pieza.aviso>
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
                <div class="pli-fam" data-familia>
                    @if ($fam['titulo'] !== '')<h4 class="pli-h4">{{ $fam['titulo'] }}</h4>@endif
                    <div class="pli-grid2">
                        @foreach ($fam['tarjetas'] as $e)
                            @include('fiesta.lista.tarjeta-extra', ['e' => $e, 'x' => $x])
                        @endforeach
                    </div>
                    {{-- El estado de la familia va DEBAJO de sus tarjetas: si cambia, nada de lo que se toca se mueve bajo el dedo. --}}
                    <p class="pli-sug" data-familia-sug hidden><span data-familia-sug-texto></span><x-pieza.boton variant="quiet" size="sm" data-familia-poner><x-slot:izquierda><x-lucide name="plus" :size="16" /></x-slot:izquierda><span data-familia-poner-texto>{{ trans_choice('fiesta.lista.padres.poner', 1) }}</span></x-pieza.boton></p>
                    <p class="pli-sug ok" data-familia-ok hidden><span class="pli-cubre"><x-lucide name="circle-check" :size="15" /><span data-familia-ok-texto></span></span></p>
                </div>
            @endforeach
        </div>
    @endif
    @if ($x['lista'] !== [])
        <div class="pli-grid2">
            @foreach ($x['lista'] as $e)
                @include('fiesta.lista.tarjeta-extra', ['e' => $e, 'x' => $x])
            @endforeach
        </div>
    @endif
    <p class="pli-pie-z"><x-lucide name="wallet" :size="16" />{{ $x['pie'] }}</p>
</section>
