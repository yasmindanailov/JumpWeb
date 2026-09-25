@props(['selected' => false, 'disabled' => false, 'count' => null, 'icono' => null, 'boton' => false])
@php
    /*
     * El chip de filtro o atributo (`core/Tag.jsx`): seleccionable cuando es un botón (`boton`), como cuando el diseño
     * le pasa `onClick`. El estilo, `.pz-etiqueta` en `fiesta.css`.
     */
    $clases = 'pz-etiqueta'.($selected ? ' pz-etiqueta--on' : '');
@endphp
@if ($boton)<button type="button" @disabled($disabled) {{ $attributes->class($clases) }}>@else<span {{ $attributes->class($clases) }}>@endif{{ $icono }}{{ $slot }}@if ($count !== null)<span class="pz-etiqueta__cuenta">{{ $count }}</span>@endif{{ '' }}@if ($boton)</button>@else</span>@endif