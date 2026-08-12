{{--
    Celda «Producto» del widget de reservas del Escritorio (refinamiento clienta 2026-06-13):
    icono del TIPO (entrada → ticket, cumpleaños → tarta) TINTADO con el color de la zona + nombre.
    Sustituye al antiguo ColorColumn (cuadro de color en columna propia) → una columna menos.

    Por qué una vista de columna propia y NO `TextColumn->html()`: el saneador de Filament (Symfony
    HtmlSanitizer con `allowSafeElements()`) ELIMINA el elemento `<svg>` (no es un «elemento seguro»),
    así que el icono desaparecía. Aquí el icono se pinta con el componente nativo `<x-filament::icon>`
    (SVG real, sin sanear) y el tinte va en un `<span style="color:…">` envolvente (el heroicon usa
    `currentColor`). El hex se valida contra un patrón estricto antes de imprimirlo (defensa).
--}}
@php
    $record = $getRecord();
    $type = $record?->ticketType;
    $isPack = $type?->isPack() ?? false;
    $color = $type?->zone?->color;
    $color = (is_string($color) && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $color)) ? $color : '#9CA3AF';
    $zoneName = $type?->zone?->tr('name');
    $name = $type?->tr('name') ?? '—';
@endphp

<div class="flex items-center gap-2 text-sm" @if (filled($zoneName)) title="{{ $zoneName }}" @endif>
    <span class="inline-flex shrink-0" style="color: {{ $color }}">
        <x-filament::icon :icon="$isPack ? 'heroicon-o-cake' : 'heroicon-o-ticket'" class="h-5 w-5" />
    </span>
    <span>{{ $name }}</span>
</div>
