{{-- La invitación viva (`InvInvitacion`): la tarjeta con su tema y, dentro, en el orden del brief: cuándo y dónde,
     «Cómo llegar» y «Añadir al calendario», qué es la fiesta (la descripción del pack, `#744`) y la merienda por grupos;
     debajo, la barra de la respuesta. Pasado el plazo, la tarjeta se ve igual y la barra se queda con su línea y
     «Llamar» (spec hermana §7.2·R8). Sin dato, sin bloque. --}}
@php
    $inv = $m['anfitrion'];
    $conQue = $m['texto'] !== [] || $m['merienda'] !== [];
@endphp
<div class="inv-card">
    <x-fiesta.invitacion :theme="$m['tema']['clave']" :name="$m['cumple']['nombre']" :age="$m['cumple']['edad']" :date="$m['fecha']" :time="$m['hora']" :place="$m['lugar']" :host="$inv['linea']" :phone="$inv['telefono']" :phoneHref="$inv['tel']" titleTag="h1" animate data-invitation-card :data-theme="$m['tema']['clave']">
        @if ($m['enlaces']['mapa'] !== '' || $m['enlaces']['calendario'] !== '')<div class="inv-enlaces">@if ($m['enlaces']['mapa'] !== '')<x-pieza.enlace :href="$m['enlaces']['mapa']" target="_blank" rel="noopener noreferrer" data-invitation-where><x-slot:icono><x-lucide name="map-pin" :size="16" /></x-slot:icono>{{ __('fiesta.invitacion_pagina.como_llegar') }}</x-pieza.enlace>@endif{{ '' }}@if ($m['enlaces']['calendario'] !== '')<x-pieza.enlace :href="$m['enlaces']['calendario']" :download="$m['enlaces']['ics']" data-invitation-calendar><x-slot:icono><x-lucide name="calendar-plus" :size="16" /></x-slot:icono>{{ __('fiesta.invitacion_pagina.calendario') }}</x-pieza.enlace>@endif</div>@endif
        @if ($conQue)<div class="inv-que">@foreach ($m['texto'] as $parrafo)<p class="inv-texto">{{ $parrafo }}</p>@endforeach{{ '' }}@if ($m['merienda'] !== [])<div class="inv-merienda" data-invitation-menu><span class="inv-merienda-t">{{ __('fiesta.invitacion_pagina.merienda') }}</span><ul class="inv-merienda-g">@foreach ($m['merienda'] as $grupo)<li><span class="inv-merienda-ic" aria-hidden="true"><x-lucide :name="$grupo['icono']" :size="17" /></span><span class="inv-merienda-tx"><span class="rot">{{ $grupo['rotulo'] }}</span>@if ($grupo['cosas'] !== [])<span class="cosas">@foreach ($grupo['cosas'] as $cosa)<span>{{ $cosa }}@if (! $loop->last)<span aria-hidden="true" class="sep">·</span>@endif</span>@endforeach</span>@endif</span></li>@endforeach</ul><p class="inv-merienda-al"><x-lucide name="pencil-line" :size="15" /><span>{{ $m['merienda_alergias'] }}</span></p></div>@endif</div>@endif
    </x-fiesta.invitacion>
</div>
<x-fiesta.rsvp-bar class="inv-barra" animate :action="$m['respuestas']['accion']" :deadline="$m['respuestas']['plazo']" :closed="! $m['respuestas']['abiertas']" :error="$m['respuestas']['error']" :value="old('child_name', '')" data-invitation-form>
    <x-slot:closedNote><span>{{ $m['respuestas']['cerrado'] }}</span>@if ($inv['tel'] !== '')<a class="inv-llamar" href="{{ $inv['tel'] }}" data-invitation-call><x-lucide name="phone" :size="15" />{{ __('fiesta.invitacion_pagina.llamar') }}</a>@endif</x-slot:closedNote>
    {{-- ⚠️ `{{ '' }}` tras cada `</x-slot>`: pegado a `@endif` compila a `@endslot@endif`, que Blade no ve (spec §4.7). --}}
    @if ($m['aviso'] !== null)<x-slot:notice><p class="inv-caducado" role="{{ $m['aviso']['rol'] }}" data-invitation-outcome>{{ $m['aviso']['texto'] }}</p></x-slot:notice>{{ '' }}@endif
    @if ($m['turnstile']['activo'])<x-slot:tercero><div class="inv-tercero"><span class="inv-tercero-rotulo">{{ $m['turnstile']['rotulo'] }}</span><div class="cf-turnstile" data-sitekey="{{ $m['turnstile']['clave'] }}"></div></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script></x-slot:tercero>{{ '' }}@endif
</x-fiesta.rsvp-bar>
{{-- El AVISO DE PRIVACIDAD (spec hermana §7.2·R7): sin casilla, y dice para qué, quién lo ve y cuándo se borra. --}}
<p class="inv-legal" data-invitation-privacy>{{ $m['privacidad']['texto'] }} <x-pieza.enlace size="sm" underline="always" :href="$m['privacidad']['enlace']">{{ $m['privacidad']['politica'] }}</x-pieza.enlace></p>
