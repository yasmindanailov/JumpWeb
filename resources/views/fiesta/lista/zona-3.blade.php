{{-- ZONA 3 · El número final (`#444`): el número de la reserva, sus límites y su plazo (la misma fuente que revalida
     bajo el lock, `GuestCountPolicy`). TRES estados, como `PliZona3`, según (número elegido, niños en la lista): MENOS, las
     plazas libres e «Invitar a más»; IGUAL, «Seréis N.»; MÁS (F4, §4.9, `#747`), la barra crece con los de más en ámbar
     y «Seréis N: … ¿Es correcto?» con su «Sí», que sube el número. Los tres van en el HTML y `lista.js` enseña el que toca
     al teclear; sin JavaScript, el del servidor. El editor (`guest_count`) sale con «Cambiar»; sin JavaScript, siempre.
     ⚠️ Con más niños que el número, guardar se PARA y se pregunta (`[DECIDIDO owner]` `#747`): `lista.js` no envía, y el
     servidor, si le llega, no escribe nada. --}}
@php
    $num = $m['numero'];
    $c = $m['cuentas'];
    $estado = $num['en_lista'] < $num['valor'] ? 'libres' : ($num['en_lista'] === $num['valor'] ? 'listo' : 'mas');
    $oculto = static fn (string $e): string => $e === $estado ? '' : ' hidden';
    $mas = $estado === 'mas';
    // «Seréis 12: Vera, los 9 confirmados y 2 que añadiste.» — quien cumple delante si su fila está en la lista (F3a).
    $partes = array_values(array_filter([
        $m['cumple']['fila'] ? $m['cumple']['nombre'] : '',
        $c['confirmados'] > 0 ? trans_choice('fiesta.lista.numero.confirmados', $c['confirmados'], ['count' => $c['confirmados']]) : '',
        $c['sin_contestar'] > 0 ? __('fiesta.lista.numero.anadidos', ['count' => $c['sin_contestar']]) : '',
    ]));
    $lista = count($partes) > 1 ? implode(', ', array_slice($partes, 0, -1)).__('fiesta.lista.numero.y').end($partes) : ($partes[0] ?? '');
@endphp
<section class="pli-zona" data-zona="3" aria-labelledby="pli-h-numero" data-numero>
    <div class="pli-cab-z">
        <h2 id="pli-h-numero" class="pli-h2">{{ __('fiesta.lista.numero.titulo') }}</h2>
        @if ($num['editable'] && $num['pista'] !== '')
            <span class="pli-plazo">{{ $num['pista'] }}</span>
        @endif
    </div>
    @if (! $num['editable'])
        <p class="pli-frase">{{ __('fiesta.lista.numero.listo', ['n' => $num['valor']]) }}</p>
        @unless ($m['solo_lectura'])
            <x-pieza.aviso tone="warn" size="sm"><x-slot:icono><x-lucide name="clock-alert" :size="17" /></x-slot:icono>{{ '' }}{{ $num['pista'] !== '' ? $num['pista'] : __('fiesta.lista.numero.cerrado') }} <a href="tel:{{ $m['reserva']['tel'] }}">{{ __('fiesta.lista.numero.llamanos') }}</a>{{ __('fiesta.lista.numero.y_lo_vemos') }}</x-pieza.aviso>
        @endunless
    @else
        <div data-numero-vista data-precio="{{ $num['precio_nino'] }}" data-reservados="{{ $num['valor'] }}">
            <x-fiesta.plazas :total="$mas ? $num['en_lista'] : $num['valor']" :confirmed="$mas ? $num['valor'] : $num['en_lista']" :pending="$mas ? $num['de_mas'] : 0" :reserved="$mas ? $num['valor'] : 0" :label="$mas ? __('fiesta.lista.numero.meter_mas', ['n' => $num['en_lista'], 'extra' => $num['de_mas'], 'plazas' => $num['valor']]) : __('fiesta.lista.numero.meter', ['n' => $num['en_lista'], 'plazas' => $num['valor']])" data-plazas />
            {{-- MENOS: las plazas libres. --}}
            <div data-numero-estado="libres"{!! $oculto('libres') !!}>
                <div class="pli-num-txt" style="margin-top: 16px;">
                    <p class="pli-frase" data-numero-libres>{{ trans_choice('fiesta.lista.numero.libres', max(1, $num['libres']), ['count' => max(1, $num['libres'])]) }}</p>
                    <p class="pli-sub" data-numero-tienes>{{ __('fiesta.lista.numero.tienes', ['plazas' => $num['valor'], 'lista' => $num['en_lista']]) }}</p>
                </div>
                <div class="pli-acciones" style="margin-top: 16px;">
                    @if ($m['invitacion'] !== null && $m['invitacion']['respuestas_abiertas'])
                        <x-pieza.boton variant="secondary" size="sm" :href="$m['invitacion']['whatsapp']" target="_blank" rel="noopener noreferrer"><x-slot:izquierda><x-lucide name="message-circle" :size="17" /></x-slot:izquierda>{{ __('fiesta.lista.numero.invitar') }}</x-pieza.boton>
                    @endif
                    <x-pieza.boton variant="ghost" size="sm" data-numero-cambiar><x-slot:izquierda><x-lucide name="pencil" :size="16" /></x-slot:izquierda>{{ __('fiesta.lista.numero.cambiar') }}</x-pieza.boton>
                </div>
            </div>
            {{-- IGUAL: «Seréis N.» --}}
            <div data-numero-estado="listo"{!! $oculto('listo') !!}>
                <div class="pli-fila-num" style="margin-top: 16px;"><p class="pli-frase ok"><x-lucide name="circle-check" :size="20" /><span data-numero-listo>{{ __('fiesta.lista.numero.listo', ['n' => $num['valor']]) }}</span></p><x-pieza.boton variant="ghost" size="sm" data-numero-cambiar><x-slot:izquierda><x-lucide name="pencil" :size="16" /></x-slot:izquierda>{{ __('fiesta.lista.numero.cambiar') }}</x-pieza.boton></div>
                @if ($m['invitacion'] !== null && $m['invitacion']['respuestas_abiertas'])
                    {{-- Como el diseño: `target="_blank"` a secas, sin la cola de `external` (la flecha es de «Cómo llegar»). --}}
                    <div style="margin-top: 16px;"><x-pieza.enlace size="sm" :href="$m['invitacion']['whatsapp']" target="_blank" rel="noopener noreferrer"><x-slot:icono><x-lucide name="message-circle" :size="15" /></x-slot:icono>{{ __('fiesta.lista.numero.alguien_mas') }}</x-pieza.enlace></div>
                @endif
            </div>
            {{-- MÁS (F4): la pregunta, lo que cuesta cada niño de más (se paga en el parque) y su «Sí». --}}
            <div data-numero-estado="mas"{!! $oculto('mas') !!}>
                <p class="pli-frase" style="margin: 16px 0 0;"><span data-numero-frase>{{ __('fiesta.lista.numero.frase', ['n' => $num['en_lista'], 'lista' => $lista]) }}</span> <strong>{{ __('fiesta.lista.numero.pregunta') }}</strong></p>
                <p class="pli-sub" style="margin: 16px 0 0;" data-numero-mas>{{ $num['precio_nino'] !== '' ? trans_choice('fiesta.lista.numero.mas', max(1, $num['de_mas']), ['count' => max(1, $num['de_mas']), 'plazas' => $num['valor'], 'precio' => $num['precio_nino']]) : trans_choice('fiesta.lista.numero.mas_sin_precio', max(1, $num['de_mas']), ['count' => max(1, $num['de_mas']), 'plazas' => $num['valor']]) }}</p>
                <div class="pli-acciones" style="margin-top: 16px;">
                    <x-pieza.boton variant="outline" size="sm" data-numero-si><x-slot:izquierda><x-lucide name="check" :size="16" /></x-slot:izquierda>{{ __('fiesta.lista.numero.si') }}</x-pieza.boton>
                    <x-pieza.boton variant="ghost" size="sm" data-numero-cambiar><x-slot:izquierda><x-lucide name="pencil" :size="16" /></x-slot:izquierda>{{ __('fiesta.lista.numero.cambiar') }}</x-pieza.boton>
                </div>
                {{-- Guardar con más niños que el número se PARA y lo dice aquí (`#747`). --}}
                <div hidden style="margin-top: 16px;" data-numero-confirma><x-pieza.aviso tone="warn" size="sm" role="alert"><x-slot:icono><x-lucide name="triangle-alert" :size="17" /></x-slot:icono>{{ '' }}{{ __('fiesta.lista.numero.confirma') }}</x-pieza.aviso></div>
            </div>
        </div>
        {{-- El editor: el campo `guest_count` de siempre, con sus límites. Con JavaScript sale al pulsar «Cambiar». --}}
        <div class="pli-editar" data-numero-editor>
            <x-pieza.cantidad variant="bare" :label="__('fiesta.lista.numero.ninos')" :value="$num['valor']" :min="$num['suelo']" :max="$num['techo'] ?? INF" :labels="[__('fiesta.lista.numero.uno_menos'), __('fiesta.lista.numero.uno_mas')]" name="guest_count" data-guest-count data-actual="{{ $num['valor'] }}" />
            @if ($num['pista'] !== '')
                <p class="pli-sub">{{ $num['pista'] }}</p>
            @endif
            <div><x-pieza.boton variant="quiet" size="sm" data-numero-hecho>{{ __('fiesta.lista.numero.hecho') }}</x-pieza.boton></div>
        </div>
    @endif
    <p class="pli-pagas"><x-lucide name="hand-coins" :size="16" />{{ __('fiesta.lista.numero.pagas') }}</p>
</section>
