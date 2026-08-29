{{-- Ver la contraseña · **DIBUJADO POR NOSOTROS aplicando la anatomía del set** — el artboard no
     dibuja el ojo, y el campo de contraseña lo necesita en las dos superficies (la web y el cajón).
     ⚠️ Va con TRAZO y no con masa, y eso también sale del artboard: sus dos glifos de la misma
     familia —`ui/hora` (esfera + agujas) y `ui/buscar` (aro + mango)— se dibujan igual, porque el
     sujeto es un contorno. La regla de §06 es «nada por debajo de 3», no «nada de trazo».
     ⚠️ La pupila sí es masa: es lo que hace que el ojo se lea a 18 px.
     ⚠️ El área viva se respeta: el almendrado va de 2,6 a 21,4 en X y de 5,4 a 18,6 en Y. --}}
<svg {{ $attributes->merge(['width' => 24, 'height' => 24, 'class' => 'pwd-input__icon']) }} viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    <path d="M2.6 12s3.5-6.6 9.4-6.6S21.4 12 21.4 12s-3.5 6.6-9.4 6.6S2.6 12 2.6 12z" />
    <circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none" />
</svg>
