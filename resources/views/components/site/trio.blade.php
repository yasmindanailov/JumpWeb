{{-- ══ EL TRÍO DE NIÑOS (`G4` del kit de fachada) ═════════════════════════════════════════════
     `DECISIONES #547`. El artboard lo describe así: *«tres niños distintos, tres colores del mural,
     alturas 100 / 85 / 73 y **los pies en la misma línea**. Es el remate de Zona Kids; **no lleva
     mancha detrás**»*.

     ⚠️⚠️ **Las tres cajas y el solape son los del artboard, leídos de su marcado** — 96×104, 51×88
     con −8 de solape, y 89×76 **espejada**—, no medidas a ojo. Las alturas desiguales son lo que
     hace que se lean como tres niños y no como un patrón; igualarlas rompe la pieza.

     ⚠️ **Los pies en la misma línea**: las tres se alinean por ABAJO (`align-items: flex-end`), que
     es lo que el artboard dice con esas palabras. Con alturas distintas, cualquier otra alineación
     las deja flotando.

     ⚠️ **Tres colores del mural, uno por niño**, y salen de `--strip-*`, que son exactamente los
     cinco colores de marca de esta instalación. Un solo color las convertiría en una silueta con
     tres cabezas.

     ⚠️ Y **sin mancha detrás**, porque su propia ficha lo dice. Si alguien le pone una, deja de ser
     `G4`. --}}
@props(['clase' => '', 'escala' => 1])

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
@endif
