<?php

/*
 * La INVITACIÓN DIGITAL de una fiesta (`docs/specs/celebracion-e-invitacion.md` §4.6, T5).
 *
 * ❗❗ Lo LEE UN DESCONOCIDO, y más desconocido que en ninguna otra pantalla del producto: el enlace
 * se reparte **a un grupo de clase entero** por un chat de padres. Quien abre esto no ha usado nunca
 * esta web, puede que ni sepa qué es el parque, y lo único que quiere saber es de quién es la fiesta,
 * cuándo y dónde. Por eso el tono explica en vez de dar por sabido, y por eso vive en los TRES
 * idiomas del cliente (es/en/fr) y NUNCA en `admin.*` (`DECISIONES #154`).
 *
 * ⚠️ Lo que el ANFITRIÓN escribe —el nombre de quien cumple y la línea «te invita»— no está aquí: es
 * texto suyo, va tal cual lo escribió y solo pasa por el saneo que impide publicar enlaces (§7.2·R9).
 */
return [
    'title' => 'Una invitación',

    'badge' => 'Estás invitado',
    'heading' => '¡Te invito a mi cumple!',

    // `trans_choice`: «cumple 1 año» / «cumple 8 años». La edad es opcional, así que este texto solo
    // se pinta cuando el anfitrión la ha puesto.
    'age' => 'Cumple :count año|Cumple :count años',

    'when' => 'Cuándo',
    'host' => 'Te invita',
    'where' => 'Dónde',
    'directions' => 'Cómo llegar',
    'menu' => 'Qué hay de comer',
    // Nombre accesible del desplegable de cada plato: se VE el chevron, se ANUNCIA esto.
    'menu_more' => 'Ver qué lleva',

    'soon' => [
        'title' => 'Para decir si venís',
        // ⚠️ Se DICE que aún no se puede contestar aquí, en vez de callarlo: quien abre la invitación
        // y no encuentra dónde responder pensaría que la página está rota.
        'open' => 'Muy pronto podrás confirmar desde esta misma página. Mientras tanto, díselo a quien te ha invitado.',
        'closed' => 'El plazo para confirmar ya ha pasado, pero la información de la fiesta sigue aquí.',
    ],
];
