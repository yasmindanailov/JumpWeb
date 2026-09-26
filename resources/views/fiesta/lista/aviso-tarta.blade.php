{{-- EL AVISO DE LA TARTA (`PliAvisoTarta`, F5 §4.11): arriba, bajo la cabecera, solo si la tarta está SIN DECIDIR en lo
     guardado y su plazo cierra hoy o mañana. Un aviso, no un bloqueo: se cierra eligiendo. Elegida y sin guardar, `lista.js`
     cambia el texto en el mismo hueco (si desapareciera al tocar una opción, toda la página subiría bajo el dedo); se va al
     guardar. --}}
@php($cuando = $m['extras']['tarta']['cuando'])
<p class="pli-aviso-tarta" role="note" data-aviso-tarta data-elegida="{{ __('fiesta.lista.tarta.aviso_elegida', ['cuando' => $cuando]) }}" data-sin="{{ __('fiesta.lista.tarta.aviso_sin', ['cuando' => $cuando]) }}" data-ver="{{ __('fiesta.lista.tarta.aviso_ver') }}">
    <span class="pli-aviso-ic" aria-hidden="true"><x-lucide name="cake" :size="18" data-aviso-icono="cake" /><x-lucide name="circle-check" :size="18" data-aviso-icono="elegida" hidden /></span>
    <span data-aviso-texto>{{ __('fiesta.lista.tarta.aviso', ['cuando' => $cuando]) }}</span>
    <x-pieza.enlace size="sm" underline="always" href="#pli-tarta" data-aviso-ir>{{ __('fiesta.lista.tarta.aviso_ir') }}</x-pieza.enlace>
</p>
