{{-- ZONA 3 · El número final, con lo que HAY (`#444`): el número de la reserva, sus límites y su plazo (la misma
     fuente que revalida bajo el lock, `GuestCountPolicy`). Por debajo de la reserva se enseñan las plazas libres e
     «Invitar a más»; llena, «Seréis N.». El editor (`guest_count`) sale con «Cambiar»; sin JavaScript, siempre.
     ⚠️ Lo que el diseño añade —la lista que supera la reserva y su «Sí» que sube el número— es lo que FALTA (§7·3). --}}
@php($num = $m['numero'])
@php($c = $m['cuentas'])
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
        <div data-numero-vista>
            <x-fiesta.plazas :total="$num['valor']" :confirmed="$num['en_lista']" :label="__('fiesta.lista.numero.meter', ['n' => $num['en_lista'], 'plazas' => $num['valor']])" data-plazas />
            @if ($num['libres'] > 0)
                <div class="pli-num-txt" style="margin-top: 16px;">
                    <p class="pli-frase" data-numero-frase>{{ trans_choice('fiesta.lista.numero.libres', $num['libres'], ['count' => $num['libres']]) }}</p>
                    <p class="pli-sub" data-numero-sub>{{ __('fiesta.lista.numero.tienes', ['plazas' => $num['valor'], 'lista' => $num['en_lista']]) }}</p>
                </div>
                <div class="pli-acciones" style="margin-top: 16px;">
                    @if ($m['invitacion'] !== null && $m['invitacion']['respuestas_abiertas'])
                        <x-pieza.boton variant="secondary" size="sm" :href="$m['invitacion']['whatsapp']" target="_blank" rel="noopener noreferrer"><x-slot:izquierda><x-lucide name="message-circle" :size="17" /></x-slot:izquierda>{{ __('fiesta.lista.numero.invitar') }}</x-pieza.boton>
                    @endif
                    <x-pieza.boton variant="ghost" size="sm" data-numero-cambiar><x-slot:izquierda><x-lucide name="pencil" :size="16" /></x-slot:izquierda>{{ __('fiesta.lista.numero.cambiar') }}</x-pieza.boton>
                </div>
            @else
                <div class="pli-fila-num" style="margin-top: 16px;"><p class="pli-frase ok" data-numero-frase><x-lucide name="circle-check" :size="20" />{{ __('fiesta.lista.numero.listo', ['n' => $num['valor']]) }}</p><x-pieza.boton variant="ghost" size="sm" data-numero-cambiar><x-slot:izquierda><x-lucide name="pencil" :size="16" /></x-slot:izquierda>{{ __('fiesta.lista.numero.cambiar') }}</x-pieza.boton></div>
                @if ($m['invitacion'] !== null && $m['invitacion']['respuestas_abiertas'])
                    {{-- Como el diseño: `target="_blank"` a secas, sin la cola de `external` (la flecha es de «Cómo llegar»). --}}
                    <div style="margin-top: 16px;"><x-pieza.enlace size="sm" :href="$m['invitacion']['whatsapp']" target="_blank" rel="noopener noreferrer"><x-slot:icono><x-lucide name="message-circle" :size="15" /></x-slot:icono>{{ __('fiesta.lista.numero.alguien_mas') }}</x-pieza.enlace></div>
                @endif
            @endif
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
