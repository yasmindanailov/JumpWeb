{{-- EL BOTÓN PRINCIPAL (el `boton()` del diseño): uno por correo, en píldora, con el color de ACCIÓN y su letra
     (`[DECIDIDO owner]` `#803`: como el diseño; sustituye el «naranja solo vende» de `#503` en los correos). 52 px de
     alto, a lo ancho en el móvil y hasta 340 en escritorio. Outlook no redondea: le queda la caja. --}}
@php($t = $correo->tema)
<tr data-bloque="boton"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
<!--[if mso]><table {!! $correo::TABLA !!} width="340" align="left"><tr><td><![endif]-->
<table {!! $correo::TABLA !!} width="100%" style="max-width:340px;border-collapse:separate;"><tr>
<td class="pjm-btn" align="center" bgcolor="{{ $t->claro('accion') }}" style="background:{{ $t->claro('accion') }};border-radius:{{ $t->radio('pildora') }}px;mso-padding-alt:16px 24px;">
<a class="pjm-btn-t" href="{{ $b['url'] }}" target="_blank" style="display:block;padding:16px 24px;border-radius:{{ $t->radio('pildora') }}px;text-decoration:none;{{ $correo->ty('texto', 16, 20, 700, $t->claro('accion-letra')) }}">{{ $b['texto'] }}</a>
</td></tr></table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
