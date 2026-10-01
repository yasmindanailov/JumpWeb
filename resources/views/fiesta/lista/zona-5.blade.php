{{-- ZONA 5 · Guardar (L2 de la isla de enlace, `#814`, §4.18): el ÚNICO botón que escribe vive en la isla, en naranja solo
     con algo que guardar y con lo exacto (`lista.js` + `logica.js::caraDeLaLista`); guardado, el aviso «Guardado hoy a las…»
     y, sin nada que guardar, «Enviar por WhatsApp» si la invitación aún no salió (fuera de la vista de la zona 1). Al pie se
     queda la LÍNEA de lo guardado. Sin JavaScript, la isla va aquí, en el flujo, con su Guardar de enviar. Y la privacidad,
     que el diseño deja para la invitación (RGPD). Sin recordatorio: la lista no tiene «sin contestar» desde `#805`. --}}
@php
    $inv = $m['invitacion'];
    $enviar = $inv !== null && $inv['respuestas_abiertas'] && ! $inv['compartida'];
    $nombre = trim((string) $m['cumple']['nombre']);
@endphp
<section class="pli-z5" data-zona="5" aria-label="{{ __('fiesta.barra.label') }}">
    @unless ($m['solo_lectura'])
        {{-- «Guardado hoy a las 16:05» (F5c, `#749`): cuándo guardó el titular por última vez. --}}
        <p class="pli-guardado" data-guardado-linea>@if ($m['guardar']['estado'] === 'saved')<x-lucide name="circle-check" :size="17" />{{ $m['guardar']['guardado'] }}@else{{ __('fiesta.lista.guardar.nada') }}@endif</p>
        <x-fiesta.isla class="pli-isla" :label="__('fiesta.barra.label')" data-lista-isla :data-recien="$m['guardar']['recien'] ? '1' : '0'" :data-guardado="$m['guardar']['guardado']">
            <x-fiesta.isla-barra :label="__('fiesta.barra.label')" form="fiesta-form" />
            <x-slot:plantillas><template data-isla-plantilla="guardar"><x-fiesta.isla-barra :label="__('fiesta.barra.label')" form="fiesta-form" /></template>@if ($enviar)<template data-isla-plantilla="enviar"><x-fiesta.isla-barra :label="__('fiesta.lista.enviar')" :sub="$nombre === '' ? '' : __('fiesta.lista.isla.de_quien', ['n' => $nombre])" icon="message-circle" :href="$inv['whatsapp']" target="_blank" data-envio="whatsapp" data-envio-donde="invitation" data-isla-hecho /></template>@endif</x-slot:plantillas>
        </x-fiesta.isla>
    @endunless
    <p class="pli-sub">{{ __('fiesta.lista.privacidad') }} <a href="{{ $m['privacidad'] }}">{{ __('fiesta.lista.politica') }}</a></p>
</section>
