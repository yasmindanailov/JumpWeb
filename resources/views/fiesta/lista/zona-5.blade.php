{{-- ZONA 5 · Guardar: la barra, el ÚNICO botón que escribe. Dice en qué punto está (JS: cambios sin guardar, borrador,
     respuestas por repasar). Debajo, «Escribir el recordatorio»: lo compone el parque y el anfitrión lo copia
     (`writeReminder`, por su propio formulario). Y la privacidad, que el diseño deja para la invitación (RGPD). --}}
@php($inv = $m['invitacion'])
<section class="pli-z5" data-zona="5" aria-label="{{ __('fiesta.barra.label') }}">
    @unless ($m['solo_lectura'])
        {{-- «Guardado hoy a las 16:05» (F5c, `#749`): cuándo guardó el titular por última vez; `lista.js` lo vuelve a poner
             cuando se deshacen los cambios. --}}
        <x-fiesta.barra-guardar :state="$m['guardar']['estado']" :status="$m['guardar']['estado'] === 'saved' ? $m['guardar']['guardado'] : __('fiesta.lista.guardar.nada')" :sticky="false" buttonType="submit" :loadingLabel="__('fiesta.lista.guardar.guardando')" :data-guardado="$m['guardar']['guardado']" />
    @endunless
    {{-- ⚠️ No se ata a «compartida»: aquí eso se INFIERE (hay respuestas o ya se recordó), y el recordatorio sirve
         justo cuando todavía no ha contestado nadie. Solo cuando se puede compartir y las respuestas siguen abiertas. --}}
    @if ($inv !== null && $inv['compartible'] && $inv['respuestas_abiertas'])
        <div class="pli-rec" data-recordatorio>
            <x-pieza.enlace type="submit" form="fiesta-recordatorio" data-recordatorio-escribir><x-slot:icono><x-lucide name="message-square-text" :size="18" /></x-slot:icono>{{ __('fiesta.lista.recordatorio.titulo') }}</x-pieza.enlace>
            @if ($inv['faltan'] > 0)
                <x-pieza.casilla id="pli-rec-nombres" name="with_names" value="1" form="fiesta-recordatorio" :label="trans_choice('guestform.invite.remind_names', $inv['faltan'], ['count' => $inv['faltan']])" />
            @endif
            @if ($inv['texto_recordatorio'])
                <div class="pli-rec-panel">
                    <x-pieza.aviso tone="success" size="sm" hidden data-recordatorio-copiado><x-slot:icono><x-lucide name="check" :size="17" /></x-slot:icono>{{ '' }}{{ __('fiesta.lista.recordatorio.copiado') }}</x-pieza.aviso>
                    <p class="pli-msg" data-recordatorio-texto>{{ $inv['texto_recordatorio'] }}</p>
                    <div><x-pieza.boton variant="quiet" size="sm" data-recordatorio-copiar><x-slot:izquierda><x-lucide name="copy" :size="16" /></x-slot:izquierda>{{ __('fiesta.lista.recordatorio.copiar') }}</x-pieza.boton></div>
                </div>
            @endif
            @if ($inv['recordado_veces'] > 0 && $inv['recordado_el'] !== '')
                <p class="pli-sub">{{ trans_choice('guestform.invite.remind_last', $inv['recordado_veces'], ['count' => $inv['recordado_veces'], 'when' => $inv['recordado_el']]) }}</p>
            @endif
        </div>
    @endif
    <p class="pli-sub">{{ __('fiesta.lista.privacidad') }} <a href="{{ $m['privacidad'] }}">{{ __('fiesta.lista.politica') }}</a></p>
</section>
