{{-- EL RECIBO (`InvRecibo`): la misma tarjeta con su titular; tras «Vamos», el confeti vuelve a caer, «Añadir al calendario»
     pasa a botón, y debajo «Su ficha» (opcional; con JavaScript se guarda sola al salir de cada campo y dice «Guardado»;
     sin él, su Guardar), la autorización como oferta (firmada, su Listo; si no, «Firmar» lleva a la autorización con la
     respuesta atada, hasta que `AuthForm` entre en T3), el aviso de privacidad y la línea de después. Tras «No podemos»,
     la tarjeta y la línea, sin confeti. ⚠️ Todo aquí es OPCIONAL: quien cierra la pestaña ha terminado bien.
     ⚠️ «¿Vas tú con él?» ya no se pregunta (`#743`·5): la autorización es una oferta sin pregunta. --}}
@php
    $r = $m['recibo'];
    $inv = $m['anfitrion'];
    $f = $r['ficha'];
@endphp
<div class="inv-card">
    <x-fiesta.invitacion :theme="$m['tema']['clave']" :age="$m['cumple']['edad']" :title="$r['titulo']" titleTag="h1" :animate="$r['si']" data-invitation-card :data-theme="$m['tema']['clave']" data-receipt="{{ $r['si'] ? 'si' : 'no' }}">
        @if ($r['si'])<p class="inv-texto fuerte">{{ $r['texto'] }}</p>@if ($m['enlaces']['calendario'] !== '' || $m['enlaces']['mapa'] !== '')<div class="inv-enlaces">@if ($m['enlaces']['calendario'] !== '')<x-pieza.boton variant="quiet" size="sm" :href="$m['enlaces']['calendario']" :download="$m['enlaces']['ics']" data-invitation-calendar><x-slot:izquierda><x-lucide name="calendar-plus" :size="17" /></x-slot:izquierda>{{ __('fiesta.invitacion_pagina.calendario') }}</x-pieza.boton>@endif{{ '' }}@if ($m['enlaces']['mapa'] !== '')<x-pieza.enlace :href="$m['enlaces']['mapa']" target="_blank" rel="noopener noreferrer"><x-slot:icono><x-lucide name="map-pin" :size="16" /></x-slot:icono>{{ __('fiesta.invitacion_pagina.como_llegar') }}</x-pieza.enlace>@endif</div>@endif{{ '' }}@else<p class="inv-texto fuerte">{{ $r['texto'] }}</p>@endif
    </x-fiesta.invitacion>
</div>
@if ($r['si'])
    @if ($f['abierta'] && $f['campos'] !== [])
        <section class="inv-sec" aria-labelledby="inv-h-ficha" data-receipt-fields>
            <div class="inv-sec-cab"><h2 id="inv-h-ficha" class="inv-h2">{{ __('fiesta.recibo.ficha') }}<span class="inv-opc">{{ __('fiesta.recibo.opcional') }}</span></h2><span class="inv-ok" role="status" aria-live="polite" data-ficha-ok data-guardado="{{ __('fiesta.recibo.guardado') }}">@if ($f['estado'] === 'saved')<x-lucide name="circle-check" :size="15" />{{ __('fiesta.recibo.guardado') }}@endif</span><template data-icono-ok><x-lucide name="circle-check" :size="15" /></template></div>
            <form class="inv-ficha-form" method="post" action="{{ $f['accion'] }}" data-ficha>
                @csrf
                <div class="inv-ficha">@foreach ($f['campos'] as $c)<x-pieza.campo :id="$c['id']" :name="$c['name']" :label="$c['label']" :value="$c['valor']" :type="$c['tipo']" :sufijo="$c['sufijo']" :inputmode="$c['inputmode']" :maxlength="$c['maxlength']" autocomplete="off" data-ficha-campo />@endforeach</div>
                <p class="inv-nota">{{ $f['ayuda'] }}</p>
                <div data-ficha-guardar><x-pieza.boton type="submit" variant="quiet" size="sm">{{ __('fiesta.recibo.guardar') }}</x-pieza.boton></div>
            </form>
            <div><x-pieza.enlace :href="$r['otro']" data-receipt-another><x-slot:icono><x-lucide name="user-round-plus" :size="17" /></x-slot:icono>{{ __('fiesta.recibo.otro') }}</x-pieza.enlace></div>
        </section>
    @elseif ($f['estado'] === 'closed')
        <x-pieza.aviso tone="warn" size="sm" role="alert" data-receipt-closed>{{ __('fiesta.recibo.cerrado') }}</x-pieza.aviso>
    @endif
    <section class="inv-sec" aria-labelledby="inv-h-aut" data-receipt-authorization>
        <h2 id="inv-h-aut" class="inv-h2">{{ __('fiesta.recibo.aut_titulo') }}</h2>
        <p class="inv-texto">{{ __('fiesta.recibo.aut_texto') }}</p>
        @if ($r['autorizacion']['firmada'])
            <x-pieza.aviso tone="success" size="sm" data-receipt-signed><x-slot:icono><x-lucide name="circle-check" :size="18" /></x-slot:icono>{{ __('fiesta.recibo.firmada') }}</x-pieza.aviso>
        @elseif ($r['autorizacion']['enlace'] !== '')
            {{-- La firma DENTRO del recibo (`AuthForm`) llega en T3: hasta entonces, la frase que quita la duda junto al
                 botón y «Firmar» lleva a la autorización con la respuesta atada (`#576`). --}}
            <div class="inv-firma-oferta"><p class="inv-nota">{{ __('fiesta.recibo.aut_compromiso') }}</p><x-pieza.boton variant="primary" size="md" full :href="$r['autorizacion']['enlace']" data-receipt-firmar>{{ __('fiesta.recibo.firmar') }}</x-pieza.boton></div>
        @endif
    </section>
    <p class="inv-legal" data-invitation-privacy>{{ $m['privacidad']['texto'] }} <x-pieza.enlace size="sm" underline="always" :href="$m['privacidad']['enlace']">{{ $m['privacidad']['politica'] }}</x-pieza.enlace></p>
@endif
<p class="inv-despues" data-receipt-after><x-lucide name="info" :size="16" /><span>{{ $r['despues'] }}</span></p>
