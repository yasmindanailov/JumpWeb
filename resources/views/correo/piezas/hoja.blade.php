{{-- LA HOJA DEL CALENDARIO (la `hoja()` del diseño, de BookingCard): el día se lee antes que nada. El día de la semana, el
     número y el mes, en la hoja (tinta) y su letra. `chica`, la del resguardo corto (52 px). Recibe `dia` y `chica`. --}}
@php
    $t = $correo->tema;
    $chica = (bool) ($chica ?? false);
    $w = $chica ? 52 : 64;
@endphp
<table {!! $correo::TABLA !!} width="{{ $w }}" style="width:{{ $w }}px;border-collapse:separate;"><tr><td class="pjm-tile" align="center" bgcolor="{{ $t->claro('hoja') }}" style="background:{{ $t->claro('hoja') }};border-radius:{{ $t->radio('md') }}px;padding:{{ $chica ? '8px 0 7px' : '10px 0 9px' }};">
<p class="pjm-tile-t" style="margin:0;{{ $correo->ty('texto', 12, 12, 700, $t->claro('hoja-letra'), 'letter-spacing:0.06em;text-transform:uppercase;') }}">{{ $dia['dow'] }}</p>
<p class="pjm-tile-t" style="margin:0;padding:2px 0;{{ $correo->ty('titular', $chica ? 22 : 28, $chica ? 24 : 30, 900, $t->claro('hoja-letra'), 'font-variant-numeric:tabular-nums;') }}">{{ $dia['n'] }}</p>
<p class="pjm-tile-t" style="margin:0;{{ $correo->ty('texto', 12, 12, 600, $t->claro('hoja-letra')) }}">{{ $dia['month'] }}</p>
</td></tr></table>
