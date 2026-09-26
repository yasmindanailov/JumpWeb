{{-- Arriba, lo del parque (`InvCabecera`): su logotipo y «Ver el parque» (F1c: el vídeo de portada en un círculo, como
     un estado de WhatsApp, con la nota de Google en la misma píldora; se abre a pantalla completa solo si se toca, y
     solo si la instalación tiene vídeo). ⚠️ SIN el idioma del diseño (`#748`, el owner): la página lo elige sola, como
     la web, y quien quiera cambiarlo tiene la línea de abajo (`fiesta.invitacion.idiomas`). --}}
@php($parque = $m['parque'] ?? ['video' => '', 'poster' => '', 'ver' => '', 'nota' => null])
<div class="inv-cab">
    <div class="inv-top">
        @if ($m['marca']['src'])<img class="inv-logo" src="{{ $m['marca']['src'] }}" alt="{{ $m['marca']['alt'] }}">@else<span class="inv-marca">{{ $m['marca']['alt'] }}</span>@endif
        @if ($parque['video'] !== '')<button type="button" class="inv-historia" aria-expanded="false" aria-label="{{ $parque['ver'] }}{{ $parque['nota'] !== null ? ' · '.$parque['nota']['texto'] : '' }}" data-visor-abrir data-invitation-park><span class="inv-historia-aro">@if ($parque['poster'] !== '')<img src="{{ $parque['poster'] }}" alt="">@endif<span class="inv-historia-play"><x-lucide name="play" :size="11" fill /></span></span><span>{{ $parque['ver'] }}</span>@if ($parque['nota'] !== null)<span class="inv-historia-nota"><x-lucide name="star" :size="13" fill color="var(--fiesta-sol-600)" />{{ $parque['nota']['valor'] }}</span>@endif</button>@endif
    </div>
</div>
