<?php

/*
 * Sobrescritura PARCIAL del login de Filament (`#461`).
 *
 * ⚠️ **El panel TUTEA y el vendor trata de USTED.** Medido: las cadenas propias del panel usan
 * imperativo de tú (16 «elige», 11 «marca», 6 «revisa»… y **un solo** «revise»), mientras las 67
 * cadenas `es` de Filament tratan de usted — empezando por la PRIMERA pantalla que ve cualquiera,
 * que decía «Entre a su cuenta».
 *
 * Aquí se corrige la superficie COMPLETA del login de este panel, no una cadena suelta: las dos
 * que se pintan y las dos del limitador. El resto de ficheros del vendor siguen en usted y son
 * deuda declarada (`DEUDA.md`); el mecanismo para cerrarla es este mismo directorio.
 */
return [

    'heading' => 'Entra en tu cuenta',

    'notifications' => [

        'throttled' => [
            'title' => 'Demasiados intentos. Vuelve a probar en :seconds segundos.',
            'body' => 'Vuelve a probar en :seconds segundos.',
        ],

    ],

];
