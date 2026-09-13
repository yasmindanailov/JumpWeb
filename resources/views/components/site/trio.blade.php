{{-- ══ EL TRÍO DE NIÑOS (`G4` del kit de fachada) ═════════════════════════════════════════════
     `DECISIONES #547` · `#580`. El artboard lo describe así: *«tres niños distintos, tres colores
     del mural, alturas 100 / 85 / 73 y **los pies en la misma línea**. Es el remate de Zona Kids;
     **no lleva mancha detrás**»*.

     ⚠️⚠️ **Las tres cajas y el solape son los del artboard, leídos de su marcado** — 96×104, 51×88
     con −8 de solape, y 89×76 **espejada**—, no medidas a ojo. Las alturas desiguales son lo que
     hace que se lean como tres niños y no como un patrón; igualarlas rompe la pieza.

     ⚠️ **Tres colores del mural, uno por niño**, y salen de `--strip-*`, los cinco colores de marca
     de la instalación. Un solo color las convertiría en una silueta con tres cabezas.

     ❗❗ **EL SOPORTE (`.trio-stand`) ES LO QUE LO HACE ROBUSTO EN MÓVIL, y se coloca en la vista
     justo ENCIMA de la tarjeta sobre la que el trío se apoya.** En escritorio es `display: contents`
     —no genera caja, así que el trío se posiciona contra la sección, al lado del titular—; en
     estrecho pasa a ser un bloque que RESERVA el alto del trío, y el trío se apoya en su borde
     inferior con los pies dentro de la tarjeta.
     ▶ El prototipo lo colocaba a `top: 196px` de la sección, o sea a lo que medía la cabecera con
     ESE texto: a 390 px la entradilla de la portada partía en dos líneas y **los brazos del trío
     tapaban «14,95 € por niño»**. Anclado a la tarjeta, lo largo que sea el texto de encima deja de
     importar. --}}
@props(['clase' => '', 'escala' => 2])

@php
    // pose del kit => [ancho, alto, ¿espejada?, solape previo, color]
    $ninos = [
        'slot-pose-k1' => [96, 104, false, 0, 'var(--strip-2)'],
        'slot-pose-k2' => [51, 88, false, -8, 'var(--strip-1)'],
        'slot-pose-k3' => [89, 76, true, 0, 'var(--strip-3)'],
    ];

    // ⚠️ Todo o nada, como el arco: un trío con dos niños no es un trío.
    $completo = collect(array_keys($ninos))
        ->every(fn (string $k): bool => \App\Domain\Content\Services\IllustrationKit::has($k));
@endphp

@if ($completo)
    <div class="trio-stand">
        <div class="trio {{ $clase }}" aria-hidden="true">
            @foreach ($ninos as $clave => [$w, $h, $espejo, $solape, $color])
                <span class="trio__n"
                      style="width: {{ round($w * $escala) }}px; height: {{ round($h * $escala) }}px;
                             margin-left: {{ round($solape * $escala) }}px;
                             --ilu-fg: {{ $color }};
                             @if ($espejo) transform: scaleX(-1); @endif">
                    <x-site.ilu :clave="$clave" style="width: 100%; height: 100%;" />
                </span>
            @endforeach
        </div>
    </div>
@endif
