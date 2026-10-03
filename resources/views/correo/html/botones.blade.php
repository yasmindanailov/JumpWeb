{{-- DOS BOTONES (el `botones()` del diseño): el principal y, debajo, uno claro que no compite —«Pagar con Bizum» y «Pagar con
     tarjeta» del 5—. --}}
<tr data-bloque="botones"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
@include('correo.piezas.boton', ['texto' => $b['principal']['texto'], 'url' => $b['principal']['url']]){!! $correo->hueco(10) !!}@include('correo.piezas.boton', ['texto' => $b['secundario']['texto'], 'url' => $b['secundario']['url'], 'secundario' => true])
</td></tr>
