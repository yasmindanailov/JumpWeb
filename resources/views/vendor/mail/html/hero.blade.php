@props([
    'chapa' => null,
    'tono' => 'info',
    'titulo',
    'datos' => [],
])
{{--
    LA CABECERA DEL CORREO — la caja de TINTA que abre los 23 (`DECISIONES #503`, T3 de
    `specs/correos-desde-canvas.md`; artboard `Correos PJP` 1a, sección B).

    Tres piezas dentro de una caja oscura, y en este orden:
      · la CHAPA   — una píldora que dice el ESTADO en una palabra («Reserva confirmada»,
                     «No se ha cobrado nada»). Es lo primero que se lee al abrir.
      · el TITULAR — 26px/800, la frase que resume («Nos vemos el sábado 4»).
      · el RESGUARDO — las cuatro cosas que se buscan al abrir: cuándo, qué, dónde y con qué
                     código, una por fila. Es el mismo resguardo del formulario y del
                     justificante: la tercera superficie que lo usa.

    ❗❗ TABLAS, NO FLEX. El artboard lo dibuja con `display:flex` porque es una maqueta de
    navegador; en un correo eso no existe — Outlook usa el motor de Word. Todo va en `<table>`
    con estilos pegados a cada etiqueta.

    ⚠️ El PADDING no se pone en el `<td>` con `padding` a secas en Outlook viejo: se respeta
    razonablemente en la mayoría, y las paredes de la caja se refuerzan con celdas de ancho fijo
    donde importa. Aquí basta con `padding`, que es lo que hace el resto de este molde.

    ⚠️ La caja es TINTA sobre la tarjeta blanca, así que su contenido usa la escala de tinta:
    titular en Papel, etiquetas del resguardo en Humo Claro, valores en Papel. Esto NO lo toca el
    modo oscuro: ya es oscuro en los dos modos, y por eso sus colores no entran en el mapa
    (`layout.blade.php`) — son de la superficie, no del texto sobre papel.
--}}
@php
    // Los cuatro tonos de la chapa, con los tintes sobre TINTA del sistema (tokens v1.10).
    $tonos = [
        'ok' => ['#252C19', '#3E4A21', '#A3C21C'],   // Lima Bote — confirmado
        'warn' => ['#2A2413', '#4A4020', '#F5C400'], // Amarillo Aviso — falta algo
        'err' => ['#2C1A17', '#4A2620', '#FF8A6B'],  // Rojo Claro — algo ha fallado
        'info' => ['#112934', '#1A4657', '#1AA9DE'], // Cian PJP — informativo
        /*
         * ❗ NEUTRO — y existe por una REGLA DURA del sistema: «una devolución no es un color: es un
         * signo y una fecha». No es error —nadie ha roto nada— y no es éxito —el verde significa
         * «reserva confirmada», y una reserva devuelta se leería como confirmada—. Con cinco de los
         * ocho colores de marca ya comprometidos, un sexto significado los devalúa a todos.
         * ▶ Así que la chapa de un reembolso NO se tiñe: borde de tinta y texto en Papel.
         */
        'neutro' => ['#101418', '#3B434B', '#F4F4F1'],
    ];
    [$chapaBg, $chapaBorde, $chapaFg] = $tonos[$tono] ?? $tonos['info'];
    $sans = 'Arial, Helvetica, sans-serif';
@endphp
<table class="hero" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 18px;border-radius:10px;background-color:#101418;border-collapse:separate;">
<tr>
<td style="padding:20px;">

@if ($chapa)
<table cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 12px;border-collapse:separate;">
<tr>
<td style="padding:6px 12px;border-radius:999px;background-color:{{ $chapaBg }};border:1px solid {{ $chapaBorde }};font-family:{{ $sans }};font-size:12px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:{{ $chapaFg }};line-height:1.2;">{{ $chapa }}</td>
</tr>
</table>
@endif

<div style="font-family:{{ $sans }};font-size:26px;font-weight:800;line-height:1.15;letter-spacing:-0.01em;color:#F4F4F1;">{{ $titulo }}</div>

@if (! empty($datos))
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:12px 0 0;border:1px solid #2A3138;border-radius:10px;border-collapse:separate;">
@foreach ($datos as $etiqueta => $valor)
<tr>
<td style="padding:12px 14px;{{ $loop->first ? '' : 'border-top:1px solid #2A3138;' }}font-family:{{ $sans }};font-size:12px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:#9AA1A8;vertical-align:top;">{{ $etiqueta }}</td>
<td align="right" style="padding:12px 14px;{{ $loop->first ? '' : 'border-top:1px solid #2A3138;' }}font-family:{{ $sans }};font-size:16px;font-weight:600;color:#F4F4F1;text-align:right;vertical-align:top;">{{ $valor }}</td>
</tr>
@endforeach
</table>
@endif

</td>
</tr>
</table>
