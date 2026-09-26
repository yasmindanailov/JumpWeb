{{-- EL RECIBO (`InvRecibo`): la misma tarjeta con su titular; tras «Vamos», el confeti vuelve a caer, «Añadir al calendario»
     pasa a botón, y debajo «Su ficha» (opcional; con JavaScript se guarda sola al salir de cada campo y dice «Guardado»;
     sin él, su Guardar), la autorización como oferta —desde F6a, la FIRMA DENTRO (`AuthForm`): el mismo formulario que su
     página, atado a esta respuesta, que vuelve aquí con su error o su desenlace; firmada, su Listo con quién firmó—, el
     aviso de privacidad y la línea de después. Sin nada que firmar aquí (fuera del modo interno o sin texto publicado),
     la sección no existe: el enlace de antes llevaba a un 404. Tras «No podemos», la tarjeta y la línea, sin confeti.
     ⚠️ Todo aquí es OPCIONAL: quien cierra la pestaña ha terminado bien.
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
    @php($a = $r['autorizacion'])
    @if ($a !== null)
    <section class="inv-sec" aria-labelledby="inv-h-aut" data-receipt-authorization>
        <h2 id="inv-h-aut" class="inv-h2">{{ __('fiesta.recibo.aut_titulo') }}</h2>
        <p class="inv-texto">{{ __('fiesta.recibo.aut_texto') }}</p>
        @if ($a['firmada'])
            <x-pieza.aviso tone="success" size="sm" data-receipt-signed><x-slot:icono><x-lucide name="circle-check" :size="18" /></x-slot:icono>{{ '' }}{{ __('fiesta.recibo.firmada') }}@if ($a['firmante'] !== '')<span class="inv-firma">{{ $a['firmante'] }}</span>@endif</x-pieza.aviso>
        @else
            @php($fr = $a['firma'])
            @if ($fr['aviso'] !== null)
                <x-pieza.aviso :tone="$fr['aviso']['tono']" :title="$fr['aviso']['titulo']" :role="$fr['aviso']['rol']" size="sm" data-receipt-outcome>{{ $fr['aviso']['texto'] }}</x-pieza.aviso>
            @endif
            @if ($fr['bloqueado'] !== null)
                <x-pieza.aviso tone="neutral" size="sm" role="status" data-receipt-blocked="{{ $fr['bloqueado']['motivo'] }}"><x-slot:icono><x-lucide name="lock" :size="17" /></x-slot:icono>{{ '' }}{{ $fr['bloqueado']['texto'] }}</x-pieza.aviso>
            @else
                @php($fa = $fr['formulario'])
                @if ($fa['menores'] !== [])
                    {{-- Con sesión, el menor se ELIGE (§12.5): rellena los campos y no envía; «a mano» los vacía. --}}
                    <x-pieza.selector id="dependent_pick" :label="__('guardian.minor.pick')" :hint="__('guardian.minor.pick_help')" :options="[['value' => '', 'label' => __('guardian.minor.pick_manual')], ...$fa['menores']]" data-guardian-pick-select />
                @endif
                @if ($fa['desde_invitacion'] !== '')
                    {{-- Lo que escribió al contestar se ENSEÑA, no se reparte (`#706`, `#236`): el nombre y los apellidos, los pone él. --}}
                    <p class="inv-nota" data-from-invitation>{{ __('guardian.minor.from_invitation', ['name' => $fa['desde_invitacion']]) }}</p>
                @endif
                <x-fiesta.firma child idPrefix="inv-aut" :action="$fa['accion']" :valores="$fa['valores']" :fallos="$fa['fallos']" :labels="['box' => $fa['casilla'], 'commit' => __('fiesta.recibo.aut_compromiso')]" data-receipt-firma>
                    <x-slot:oculto>@if ($fa['respuesta_id'] !== null)<input type="hidden" name="invitation_reply_id" value="{{ $fa['respuesta_id'] }}">@endif<input type="hidden" name="document_id" value="{{ $fa['documento_id'] }}"><div class="pz-sr" aria-hidden="true"><label for="contact_ref">Ref</label><input type="text" id="contact_ref" name="contact_ref" tabindex="-1" autocomplete="off"></div></x-slot:oculto>
                    <x-slot:nino><x-pieza.campo id="inv-aut-nacimiento" name="minor_born_on" type="date" :label="$fa['nacimiento']['label']" :hint="$fa['nacimiento']['hint']" :value="$fa['nacimiento']['value']" :error="$fa['nacimiento']['error']" /></x-slot:nino>
                    <x-slot:adulto><x-pieza.selector id="inv-aut-relacion" name="guardian_relationship" :label="$fa['relacion']['label']" :options="$fa['relacion']['opciones']" :value="$fa['relacion']['value']" :error="$fa['relacion']['error']" /></x-slot:adulto>
                    {{-- El texto del descargo se PRESENTA en el flujo (`waiver-probatorio.md` §4.4): «Leer el descargo» es un ancla. --}}
                    <x-slot:descargo><div class="aut-descargo" id="descargo" data-guardian-waiver><h2 class="inv-h2">{{ $fa['descargo']['titulo'] }}</h2><span class="inv-nota">{{ $fa['descargo']['version'] }}</span>@foreach ($fa['descargo']['cuerpo'] as $s)@if ($s['h'] !== '')<h3 class="aut-descargo-h">{{ $s['h'] }}</h3>@endif{{ '' }}@if ($s['p'] !== '')<p class="inv-texto">{{ $s['p'] }}</p>@endif{{ '' }}@endforeach</div></x-slot:descargo>
                    <x-slot:legal><p class="inv-legal" data-guardian-privacy>{{ $fr['privacidad']['texto'] }} <span>{{ $fr['privacidad']['datos'] }}</span></p></x-slot:legal>
                    @if ($fr['turnstile']['activo'])<x-slot:tercero><div class="inv-tercero"><span class="inv-tercero-rotulo">{{ $fr['turnstile']['rotulo'] }}</span><div class="cf-turnstile" data-sitekey="{{ $fr['turnstile']['clave'] }}"></div></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script></x-slot:tercero>{{ '' }}@endif
                </x-fiesta.firma>
            @endif
        @endif
    </section>
    @endif
    <p class="inv-legal" data-invitation-privacy>{{ $m['privacidad']['texto'] }} <x-pieza.enlace size="sm" underline="always" :href="$m['privacidad']['enlace']">{{ $m['privacidad']['politica'] }}</x-pieza.enlace></p>
@endif
<p class="inv-despues" data-receipt-after><x-lucide name="info" :size="16" /><span>{{ $r['despues'] }}</span></p>
