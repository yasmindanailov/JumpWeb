@props(['tone' => 'neutral', 'variant' => 'soft', 'size' => 'md', 'dot' => false])
@php
    /*
     * La etiqueta corta de estado (`core/Badge.jsx`), con sus estilos EN LÍNEA como el JSX. Los tonos del diseño son
     * primitivos de la paleta: aquí, roles `--fiesta-*` [fondo suave, texto suave, fondo sólido, texto sólido, punto].
     */
    $tonos = [
        'neutral' => ['var(--fiesta-tinta-100)', 'var(--fiesta-tinta-900)', 'var(--fiesta-tinta-900)', 'var(--fiesta-nieve)', 'var(--fiesta-tinta-900)'],
        'ink' => ['var(--fiesta-tinta-100)', 'var(--fiesta-tinta-900)', 'var(--fiesta-tinta-900)', 'var(--fiesta-nieve)', 'var(--fiesta-tinta-900)'],
        'flare' => ['var(--fiesta-llama-100)', 'var(--fiesta-llama-700)', 'var(--fiesta-llama-500)', 'var(--fiesta-tinta-900)', 'var(--fiesta-llama-600)'],
        'volt' => ['var(--fiesta-lima-100)', 'var(--fiesta-lima-700)', 'var(--fiesta-lima-500)', 'var(--fiesta-tinta-900)', 'var(--fiesta-lima-600)'],
        'aqua' => ['var(--fiesta-agua-100)', 'var(--fiesta-agua-700)', 'var(--fiesta-agua-500)', 'var(--fiesta-tinta-900)', 'var(--fiesta-agua-600)'],
        'berry' => ['var(--fiesta-baya-100)', 'var(--fiesta-baya-700)', 'var(--fiesta-baya-600)', 'var(--fiesta-nieve)', 'var(--fiesta-baya-600)'],
        'success' => ['var(--success-100)', 'var(--success-600)', 'var(--success-600)', 'var(--fiesta-nieve)', 'var(--success-500)'],
        'warn' => ['var(--warn-100)', '#7a5200', 'var(--warn-500)', 'var(--fiesta-tinta-900)', 'var(--warn-500)'],
        'danger' => ['var(--danger-100)', 'var(--danger-600)', 'var(--danger-600)', 'var(--fiesta-nieve)', 'var(--danger-500)'],
    ];
    $t = $tonos[$tone] ?? $tonos['neutral'];
    $solid = $variant === 'solid';
    $outline = $variant === 'outline';
    $small = $size === 'sm';
    $estilo = 'display: inline-flex; align-items: center; gap: '.($small ? '5px' : '6px').'; height: '.($small ? '22px' : '28px').'; padding: '.($small ? '0 9px' : '0 12px').'; border-radius: var(--r-pill);'
        .' background: '.($outline ? 'transparent' : ($solid ? $t[2] : $t[0])).'; color: '.($solid ? $t[3] : $t[1]).';'
        .' box-shadow: '.($outline ? 'inset 0 0 0 1px '.$t[4] : 'none').'; font-family: var(--font-ui); font-size: '.($small ? 'var(--fs-overline)' : 'var(--fs-caption)').';'
        .' font-weight: var(--fw-bold); letter-spacing: 0.01em; white-space: nowrap;';
@endphp
<span style="{{ $estilo }}" {{ $attributes->except('style') }}>@if ($dot)<span style="width: 7px; height: 7px; border-radius: 50%; background: {{ $solid ? 'currentColor' : $t[4] }}; animation: fiesta-pulse 1.8s var(--ease-in-out) infinite;"></span>@endif{{ $slot }}</span>