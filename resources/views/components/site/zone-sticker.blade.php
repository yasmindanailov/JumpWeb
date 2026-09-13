{{-- ══ LA PEGATINA DE ZONA ═══════════════════════════════════════════════════════════════════════
     `DECISIONES #582` · el lote de zona del canvas (`Iconos PJP` §07, rejilla 64): caja de color con
     keyline de tinta y sombra dura, y el glifo de la zona dentro.

     ❗❗ **El DIBUJO es del kit de la instalación (`zone-<slug>`) y la PEGATINA es del producto.** Sin
     ese dibujo no se pinta ni la caja: una caja de color vacía es exactamente el rectángulo que el
     hueco de ilustración existe para no dejar. Si el llamante pasa contenido, ése es el SUELO; si no,
     no queda nada.

     ⚠️ **Hoy la pinta UN sitio: la cifra de `/atracciones`.** `#582` la puso también en las tarjetas
     «Para quién» de la portada y en las cabeceras de `/precios`, y el owner la retiró de las dos en
     `#583` («quita los iconos de las card de zonas, y de la página de tarifas»).

     ⚠️ **El color es `zones.color`**, el mismo dato que nombra a la zona en el resto de la web —su
     regla: «el color ES el nombre de la zona en todo el sitio»—, no el color de familia del artboard.
     ⚠️ **La tinta del glifo la decide el contraste** (`ThemeSettings::stickerInk()`): el artboard la
     pinta siempre oscura y con los colores de esta instalación se cumple, pero el color lo pone el
     panel y sobre uno oscuro la silueta desaparecería.

     ⚠️ Sus tres noes, del artboard: **nunca por debajo de 48 px** (por eso `sm` es el suelo), **nunca
     dentro de una fila de texto** (para eso está el set de 24) y **nunca sobre foto** sin su caja. --}}
@props(['slug', 'color' => null, 'size' => 'md'])

@php
    $clave = 'zone-'.$slug;
    $hex = is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : null;
    $claseTalla = match ($size) {
        'sm' => 'zone-sticker--sm',
        'md' => 'zone-sticker--md',
        'lg' => 'zone-sticker--lg',
    };
@endphp

@if (\App\Domain\Content\Services\IllustrationKit::has($clave))
    <span {{ $attributes->class(['zone-sticker', $claseTalla]) }}
          @if ($hex) style="--sticker-bg: {{ $hex }}; --sticker-fg: {{ \App\Domain\Content\Services\ThemeSettings::stickerInk($hex) }};" @endif
          aria-hidden="true">
        <x-site.ilu :clave="$clave" class="zone-sticker__g" />
    </span>
@else
    {{ $slot }}
@endif
