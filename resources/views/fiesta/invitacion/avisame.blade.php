{{-- «AVÍSAME DE FECHAS» (`specs/avisame-de-fechas.md` §4.2, `[DECIDIDO owner]` `#743`·8, `#747`, `#750`): sola en el recibo,
     donde el diseño pone el bloque del QR (que no va), y solo tras un «sí» con la autorización firmada desde aquí CON
     correo. La casilla, sin marcar, con su texto legal: te escribimos unas semanas antes de su cumpleaños y te das de baja
     en cada correo. Se guarda contra la MISMA URL firmada del recibo (`dates`): el `0` escondido va delante para que
     desmarcar también viaje. Con JavaScript se guarda sola al cambiar y lo dice; sin él, su botón. --}}
@php($av = $r['avisame'])
<section class="inv-sec" id="inv-avisame" aria-label="{{ __('fiesta.recibo.avisame') }}" data-receipt-dates>
    <form class="inv-avisame" method="post" action="{{ $av['accion'] }}" data-avisame>
        @csrf
        <input type="hidden" name="dates" value="0">
        <x-pieza.casilla id="inv-avisame-casilla" name="dates" value="1" :checked="$av['marcada']" :label="__('fiesta.recibo.avisame')" :description="__('fiesta.recibo.avisame_texto')" data-avisame-casilla />
        <p class="inv-legal">{{ __('fiesta.recibo.avisame_legal') }} <x-pieza.enlace size="sm" underline="always" :href="$m['privacidad']['enlace']">{{ $m['privacidad']['politica'] }}</x-pieza.enlace></p>
        <div class="inv-avisame-pie">
            <span class="inv-ok" role="status" aria-live="polite" data-avisame-ok data-guardado="{{ __('fiesta.recibo.guardado') }}">@if ($av['estado'] === 'saved')<x-lucide name="circle-check" :size="15" />{{ __('fiesta.recibo.guardado') }}@endif</span><template data-icono-ok><x-lucide name="circle-check" :size="15" /></template>
            <div data-avisame-guardar><x-pieza.boton type="submit" variant="quiet" size="sm">{{ __('fiesta.recibo.guardar') }}</x-pieza.boton></div>
        </div>
    </form>
    @if ($av['mandado'] !== null)
        <p class="inv-nota" data-avisame-mandado>{{ __('fiesta.recibo.avisame_mandado', ['dia' => $av['mandado']]) }}</p>
    @endif
</section>
