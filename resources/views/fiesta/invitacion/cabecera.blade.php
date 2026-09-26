{{-- Arriba, lo del parque (`InvCabecera`): su logotipo, «Ver el parque» (F1c: el vídeo de portada en un círculo, como
     un estado de WhatsApp, con la nota de Google en la misma píldora; se abre a pantalla completa solo si se toca, y
     solo si la instalación tiene vídeo) y el idioma. El idioma: se ve el código con su flecha y el desplegable nativo va
     encima, invisible y del tamaño de la píldora; con JavaScript, elegir cambia de idioma (`lang.switch`, que vuelve
     aquí desde la sesión); sin él, los tres enlaces. --}}
@php($parque = $m['parque'] ?? ['video' => '', 'poster' => '', 'ver' => '', 'nota' => null])
<div class="inv-cab">
    <div class="inv-top">
        @if ($m['marca']['src'])<img class="inv-logo" src="{{ $m['marca']['src'] }}" alt="{{ $m['marca']['alt'] }}">@else<span class="inv-marca">{{ $m['marca']['alt'] }}</span>@endif
        @if ($parque['video'] !== '')<button type="button" class="inv-historia" aria-expanded="false" aria-label="{{ $parque['ver'] }}{{ $parque['nota'] !== null ? ' · '.$parque['nota']['texto'] : '' }}" data-visor-abrir data-invitation-park><span class="inv-historia-aro">@if ($parque['poster'] !== '')<img src="{{ $parque['poster'] }}" alt="">@endif<span class="inv-historia-play"><x-lucide name="play" :size="11" fill /></span></span><span>{{ $parque['ver'] }}</span>@if ($parque['nota'] !== null)<span class="inv-historia-nota"><x-lucide name="star" :size="13" fill color="var(--fiesta-sol-600)" />{{ $parque['nota']['valor'] }}</span>@endif</button>@endif
        <label class="inv-lang" data-idioma><x-lucide name="globe" :size="16" /><span aria-hidden="true" class="inv-lang-cod">{{ $m['idiomas']['actual_corto'] }}</span><x-lucide name="chevron-down" :size="14" /><select aria-label="{{ __('fiesta.invitacion_pagina.idioma') }}" data-idioma-select>@foreach ($m['idiomas']['lista'] as $i)<option value="{{ $i['enlace'] }}" lang="{{ $i['clave'] }}" @selected($i['clave'] === $m['idiomas']['actual'])>{{ $i['nombre'] }}</option>@endforeach</select></label>
        <span class="inv-lang-enlaces">@foreach ($m['idiomas']['lista'] as $i)<a href="{{ $i['enlace'] }}" lang="{{ $i['clave'] }}" @if ($i['clave'] === $m['idiomas']['actual']) aria-current="true" @endif>{{ $i['corto'] }}</a>@endforeach</span>
    </div>
</div>
