{{-- EL BOTÓN (el `boton()` del diseño), para los bloques que lo llevan: el PRINCIPAL, en el rol de acción y su letra —uno
     por correo, `[DECIDIDO owner]` `#803`—; el SECUNDARIO, claro (lo callado y su borde), que no compite. 52 px de alto, a lo
     ancho en el móvil y hasta 340 en escritorio; Outlook no redondea y le queda la caja.
     Recibe `texto`, `url` y, si hace falta, `secundario` y `centro`. --}}
@php
    $t = $correo->tema;
    $sec = (bool) ($secundario ?? false);
    $centrado = (bool) ($centro ?? false);
@endphp
<!--[if mso]><table {!! $correo::TABLA !!} width="340" align="{{ $centrado ? 'center' : 'left' }}"><tr><td><![endif]-->
<table {!! $correo::TABLA !!} width="100%" style="max-width:340px;border-collapse:separate;{{ $centrado ? 'margin:0 auto;' : '' }}"><tr>
@if ($sec)
<td class="pjm-quiet" align="center" bgcolor="{{ $t->claro('callado') }}" style="background:{{ $t->claro('callado') }};border:1px solid {{ $t->claro('callado-borde') }};border-radius:{{ $t->radio('pildora') }}px;mso-padding-alt:14px 24px;">
<a class="pjm-strong" href="{{ $url }}" target="_blank" style="display:block;padding:14px 24px;border-radius:{{ $t->radio('pildora') }}px;text-decoration:none;{{ $correo->ty('texto', 16, 20, 700, $t->claro('fuerte')) }}">{{ $texto }}</a>
</td>
@else
<td class="pjm-btn" align="center" bgcolor="{{ $t->claro('accion') }}" style="background:{{ $t->claro('accion') }};border-radius:{{ $t->radio('pildora') }}px;mso-padding-alt:16px 24px;">
<a class="pjm-btn-t" href="{{ $url }}" target="_blank" style="display:block;padding:16px 24px;border-radius:{{ $t->radio('pildora') }}px;text-decoration:none;{{ $correo->ty('texto', 16, 20, 700, $t->claro('accion-letra')) }}">{{ $texto }}</a>
</td>
@endif
</tr></table>
<!--[if mso]></td></tr></table><![endif]-->
