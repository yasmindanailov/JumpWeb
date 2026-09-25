{{-- Arriba, lo del parque (`InvCabecera`): su logotipo y el idioma. «Ver el parque» (el vídeo y la nota de Google) es una
     pieza que FALTA (spec §1.4): la cabecera es la del brief, tal cual. El idioma: se ve el código con su flecha y el
     desplegable nativo va encima, invisible y del tamaño de la píldora; con JavaScript, elegir cambia de idioma
     (`lang.switch`, que vuelve aquí desde la sesión); sin él, los tres enlaces. --}}
<div class="inv-cab">
    <div class="inv-top">
        @if ($m['marca']['src'])<img class="inv-logo" src="{{ $m['marca']['src'] }}" alt="{{ $m['marca']['alt'] }}">@else<span class="inv-marca">{{ $m['marca']['alt'] }}</span>@endif
        <label class="inv-lang" data-idioma><x-lucide name="globe" :size="16" /><span aria-hidden="true" class="inv-lang-cod">{{ $m['idiomas']['actual_corto'] }}</span><x-lucide name="chevron-down" :size="14" /><select aria-label="{{ __('fiesta.invitacion_pagina.idioma') }}" data-idioma-select>@foreach ($m['idiomas']['lista'] as $i)<option value="{{ $i['enlace'] }}" lang="{{ $i['clave'] }}" @selected($i['clave'] === $m['idiomas']['actual'])>{{ $i['nombre'] }}</option>@endforeach</select></label>
        <span class="inv-lang-enlaces">@foreach ($m['idiomas']['lista'] as $i)<a href="{{ $i['enlace'] }}" lang="{{ $i['clave'] }}" @if ($i['clave'] === $m['idiomas']['actual']) aria-current="true" @endif>{{ $i['corto'] }}</a>@endforeach</span>
    </div>
</div>
