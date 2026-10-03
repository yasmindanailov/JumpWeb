{{-- EL BOTÓN PRINCIPAL (el `boton()` del diseño): uno por correo, en píldora, con el color de ACCIÓN y su letra
     (`[DECIDIDO owner]` `#803`: como el diseño; sustituye el «naranja solo vende» de `#503` en los correos). La pieza
     (`correo/piezas/boton`) es la misma que usan los pasos, los dos botones, el QR y la sección. --}}
<tr data-bloque="boton"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
@include('correo.piezas.boton', ['texto' => $b['texto'], 'url' => $b['url']])
</td></tr>
