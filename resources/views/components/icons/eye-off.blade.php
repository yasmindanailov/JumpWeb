{{-- Ocultar la contraseña · pareja de `eye`, **dibujada por nosotros** con la misma anatomía.
     ⚠️⚠️ **La barra va DELANTE del ojo, no lo parte.** Tachar recortando el almendrado obliga a un
     `mask` o a un segundo trazo del color del fondo, y las dos cosas se rompen en cuanto el icono
     cae sobre otra superficie —que es justo lo que hace este, porque el campo de contraseña vive en
     la web y dentro del cajón—.
     ⚠️ La barra usa el mismo trazo 3 del set y termina redonda, como todo lo demás. --}}
<svg {{ $attributes->merge(['width' => 24, 'height' => 24, 'class' => 'pwd-input__icon']) }} viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    <path d="M2.6 12s3.5-6.6 9.4-6.6S21.4 12 21.4 12s-3.5 6.6-9.4 6.6S2.6 12 2.6 12z" />
    <circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none" />
    <path d="M4.4 4.4 19.6 19.6" />
</svg>
