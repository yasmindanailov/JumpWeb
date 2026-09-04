<?php

/*
 * Sobrescritura PARCIAL de las cadenas del buscador global de Filament (`#461`).
 *
 * ⚠️ Laravel fusiona los overrides de vendor con `array_replace_recursive` sobre las líneas del
 * paquete (verificado en este repo antes de escribir esto: con solo `field.placeholder` aquí,
 * `field.label` y `no_results_message` siguen resolviendo desde el vendor). Por eso este fichero
 * declara SOLO lo que cambia: añadir aquí las demás claves las congelaría en la versión de hoy y
 * dejarían de recibir las mejoras del paquete.
 *
 * El placeholder lo pidió el owner: el buscador no dice qué encuentra, y lo que encuentra es
 * justo lo que hace barato tener 19 pantallas escondidas (`specs/panel-navegacion.md` §7).
 */
return [

    'field' => [
        // Del owner: «Busca cliente, pedido, reserva, etc...». Se escribe con puntos suspensivos
        // («etc...» son cuatro puntos: `etc.` ya lleva el suyo) y sin el «etc.», que la elipsis dice.
        'placeholder' => 'Busca cliente, pedido, reserva…',
    ],

];
