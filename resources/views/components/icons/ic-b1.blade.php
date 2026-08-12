@props([
    'size' => 32,
])

{{-- Tarta de cumpleaños — icono de marca (set v2, B1). La llama (.flame, acento --zone-1)
     parpadea suave; en reduced-motion queda fija (la pose base ya muestra la llama encendida).
     Capa base `.icon` + animación en public/css/site.css. Decorativo → aria-hidden. --}}
<span {{ $attributes->class('icon ic-b1') }} aria-hidden="true">
    <svg viewBox="0 0 40 40" width="{{ $size }}" height="{{ $size }}">
        <path d="M 7 33 L 33 33" />
        <path d="M 10 33 L 10 25 Q 10 22 13 22 L 27 22 Q 30 22 30 25 L 30 33" />
        <path d="M 11.5 27.5 L 28.5 27.5" class="dashed thin" />
        <path d="M 20 22 L 20 15" />
        <path class="flame accent-fill" d="M 20 14.5 Q 22.4 11.6 20 8.6 Q 17.6 11.6 20 14.5 Z" />
    </svg>
</span>
