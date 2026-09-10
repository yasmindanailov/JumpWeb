@props([
    'titulo',
    'tono' => 'info',
])
{{--
    EL AVISO del correo — la caja de tinte con su punto de color (`DECISIONES #503`; artboard
    `Correos PJP` 1a). Es donde va lo que hay que saber ANTES de venir, el motivo de un pago
    denegado o la frase de que un enlace se puede repartir: contenido que no es el cuerpo y que
    se pierde si se escribe como un párrafo más.

    ❗ SUSTITUYE AL FILETE DE ACENTO del `.panel` de Laravel, que es cromo de documentos y no del
    producto (regla dura del sistema). La receta es la de v1.10: **fondo al 14 % sobre papel,
    borde al 30 %, y el título y el cuerpo en TINTA** — el tono se queda en el punto y en el
    borde, que no son texto. Sobre una superficie teñida solo aguanta la tinta.

    ⚠️ El PUNTO es una celda de 8×8 con radio de píldora, no un `list-style` ni un carácter: un
    bullet tipográfico cambia de forma en cada cliente de correo, y un emoji no entra en este
    sistema (el artboard no usa ninguno).

    ⚠️ Los cuatro fondos SÍ entran en el mapa de modo oscuro por su color de texto (la tinta del
    título y del párrafo), que es lo que se lee. El fondo teñido claro se queda: sobre él la tinta
    sigue contrastando, y en oscuro el mapa sube el texto a Papel 200.
--}}
@php
    // Los cuatro avisos sobre PAPEL de v1.10 (`tintePapel` de los tokens).
    $tonos = [
        'ok' => ['#DFE9D6', '#C7DDB7', '#5FA82E'],   // Verde Salta
        'warn' => ['#F4EDCF', '#F4E6A9', '#F5C400'], // Amarillo Aviso
        'err' => ['#F0DBD2', '#ECBDAF', '#D93E14'],  // Rojo Goteo
        'info' => ['#D5EAEE', '#B3DDEB', '#1AA9DE'], // Cian PJP
    ];
    [$fondo, $borde, $punto] = $tonos[$tono] ?? $tonos['info'];
    $sans = 'Arial, Helvetica, sans-serif';
@endphp
<table class="notice" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:18px 0;border-radius:10px;background-color:{{ $fondo }};border:1px solid {{ $borde }};border-collapse:separate;">
<tr>
<td style="padding:14px 16px;">

<table cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 7px;border-collapse:collapse;">
<tr>
<td width="8" style="width:8px;padding:0 8px 0 0;vertical-align:middle;">
<table cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:separate;">
<tr><td style="width:8px;height:8px;border-radius:999px;background-color:{{ $punto }};font-size:0;line-height:0;">&nbsp;</td></tr>
</table>
</td>
<td style="font-family:{{ $sans }};font-size:16px;font-weight:700;color:#101418;line-height:1.3;">{{ $titulo }}</td>
</tr>
</table>

<div style="font-family:{{ $sans }};font-size:16px;line-height:1.5;color:#101418;">{{ $slot }}</div>

</td>
</tr>
</table>
