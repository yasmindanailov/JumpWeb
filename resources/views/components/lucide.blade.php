@props(['name' => 'sparkles', 'size' => 20, 'color' => 'currentColor', 'fill' => false, 'label' => null, 'strokeBox' => false])
@php
    /*
     * Un icono Lucide en línea, IDÉNTICO al `Icon` del sistema de diseño (`Icon.jsx`) — `DECISIONES #686`.
     *
     * Mismos props y misma salida: un `<span>` inline-flex del tamaño pedido, que hereda el color, con el SVG
     * dentro (`Lucide::svg()` hace las mismas sustituciones que el diseño). `label` lo hace imagen con nombre;
     * sin él, es decorativo (`aria-hidden`). `strokeBox` lo envuelve en un chip cuadrado del doble de lado.
     *
     * ⚠️ El `style` que se le pase SE SUMA al de base, como el `Object.assign` del diseño: el de base no se pierde.
     * ⚠️ Sin espacios alrededor, a propósito (ni comentario Blade delante ni salto de línea al final): junto a
     *    un texto, un salto de línea se pinta como un espacio, y el `Icon` del diseño no deja ninguno.
     *    `LucideIconTest` lo comprueba.
     */
    $px = (int) $size;
    $estilo = "display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; width: {$px}px; height: {$px}px; color: {$color}; line-height: 0;";
    if ($attributes->has('style')) {
        $estilo .= ' '.$attributes->get('style');
    }
@endphp
@if ($strokeBox)<span style="display: inline-flex; align-items: center; justify-content: center; width: {{ $px * 2 }}px; height: {{ $px * 2 }}px; border-radius: var(--r-md); background: var(--bg-muted);">@endif<span @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif style="{{ $estilo }}" {{ $attributes->except('style') }}>{!! \App\Domain\Content\Services\Lucide::svg($name, (bool) $fill) !!}</span>@if ($strokeBox)</span>@endif