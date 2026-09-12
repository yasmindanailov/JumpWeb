{{-- ══ EL ARCO DE REBOTE (`F3` del kit de fachada) ════════════════════════════════════════════
     `DECISIONES #547`. El artboard lo describe así: *«cuatro momentos de un mismo salto —impulso,
     subida, cumbre y caída— sobre la parábola punteada. Un solo color y **una sola cumbre a
     opacidad plena**: si van todas iguales parecen cuatro niños»*.

     ❗❗❗ **LA PIEZA ESTÁ PARTIDA EN DOS A PROPÓSITO, y la línea es la de `elementos-fachada.md` §5:**
       · las **cuatro figuras** son ARTE de este parque → salen del kit de la instalación por
         `<x-site.ilu>`, como todo lo demás. Sin paquete no se pinta ninguna.
       · la **parábola** es MECANISMO —una curva punteada, no un dibujo— → la dibuja el producto.
     Meter la parábola en el kit la habría atado a un cliente; meter las figuras en el producto
     habría clavado su mural dentro de JumpWeb.

     ⚠️⚠️ **Las cuatro poses y sus opacidades son las del artboard, leídas de su marcado**, no
     elegidas aquí: `puños` al 32 % · `cohete` al 60 % · `victoria` a plena · `picado` al 60 %, con
     sus cajas de 34×44, 27×54, 32×64 y 26×58. Es lo que hace que se lea como UN salto y no como
     cuatro personas — y por eso las alturas no son iguales: la cumbre es la más alta.

     ⚠️ La parábola va `preserveAspectRatio="none"`: se estira al ancho que tenga sin deformar el
     trazo, que es como el artboard la usa. Su color es `--line-strong`, no el `#D6D8D4` literal del
     artboard — ese gris es el token de Línea de este cliente y viaja en su paquete.

     ⚠️ Los pies van sobre la curva: las figuras se alinean por ABAJO (`align-items: flex-end`) y la
     curva se dibuja por detrás. Si alguien cambia el `d` de la parábola, hay que mover los pies. --}}
@props(['clase' => ''])

@php
    // pose del kit => [ancho, alto, opacidad] — las cajas y las opacidades son las del artboard.
    $figuras = [
        'slot-pose-p9' => [34, 44, '.32'],
        'slot-pose-p8' => [27, 54, '.6'],
        'slot-pose-p5' => [32, 64, '1'],
        'slot-pose-p2' => [26, 58, '.6'],
    ];

    // ⚠️ Si el paquete no trae alguna de las cuatro, la pieza NO se pinta a medias: un arco con dos
    // figuras no cuenta un salto. Es la misma regla de todo o nada del hueco de ilustración.
    $completo = collect(array_keys($figuras))
        ->every(fn (string $k): bool => \App\Domain\Content\Services\IllustrationKit::has($k));
@endphp

@if ($completo)
    <div class="arc {{ $clase }}" aria-hidden="true">
        <svg class="arc__curve" viewBox="0 0 340 150" preserveAspectRatio="none" focusable="false">
            <path d="M6 134 Q170 -16 334 122" fill="none" stroke-width="2.5" stroke-dasharray="2 11" stroke-linecap="round" />
        </svg>
        <div class="arc__row">
            @foreach ($figuras as $clave => [$w, $h, $op])
                <span class="arc__fig" style="width: {{ $w }}px; height: {{ $h }}px; opacity: {{ $op }};">
                    <x-site.ilu :clave="$clave" style="width: 100%; height: 100%;" />
                </span>
            @endforeach
        </div>
    </div>
@endif
