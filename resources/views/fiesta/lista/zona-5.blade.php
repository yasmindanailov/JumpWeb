{{-- ZONA 5 · Guardar: la barra, el ÚNICO botón que escribe. Dice en qué punto está (JS: cambios sin guardar, borrador,
     respuestas por repasar). Y la privacidad, que el diseño deja para la invitación (RGPD). El recordatorio vive desde F8
     (`#753`) junto a las cifras de la zona 1, a WhatsApp en un toque. --}}
<section class="pli-z5" data-zona="5" aria-label="{{ __('fiesta.barra.label') }}">
    @unless ($m['solo_lectura'])
        {{-- «Guardado hoy a las 16:05» (F5c, `#749`): cuándo guardó el titular por última vez; `lista.js` lo vuelve a poner
             cuando se deshacen los cambios. --}}
        <x-fiesta.barra-guardar :state="$m['guardar']['estado']" :status="$m['guardar']['estado'] === 'saved' ? $m['guardar']['guardado'] : __('fiesta.lista.guardar.nada')" :sticky="false" buttonType="submit" :loadingLabel="__('fiesta.lista.guardar.guardando')" :data-guardado="$m['guardar']['guardado']" />
    @endunless
    <p class="pli-sub">{{ __('fiesta.lista.privacidad') }} <a href="{{ $m['privacidad'] }}">{{ __('fiesta.lista.politica') }}</a></p>
</section>
