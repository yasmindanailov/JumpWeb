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

    // ── CONTESTAR (T5·2) ──────────────────────────────────────────────────────────────────────────
    'field' => 'Nombre y apellidos del niño o la niña',
    // ⚠️ Se piden los APELLIDOS y se dice por qué en el marcador: es lo que distingue a dos niños que
    // se llaman igual, y sin ellos el anfitrión no sabe a quién apuntar.
    'field_hint' => 'Por ejemplo: Martina Serra López',
    'yes' => 'Sí, viene',
    'no' => 'No podemos',
    'antibot_label' => 'Comprobación de seguridad',

    'done' => [
        'yes_title' => '¡Contamos con vosotros!',
        'yes' => 'Ya se lo hemos dicho a quien organiza la fiesta. Nos vemos allí, :name.',
        'yes_generic' => 'Ya se lo hemos dicho a quien organiza la fiesta. ¡Nos vemos allí!',
        'no_title' => 'Gracias por avisar',
        'no' => 'Se lo hemos dicho a quien organiza la fiesta. Otra vez será.',
        'refused_title' => 'No hemos podido apuntarlo',
        // Turnstile le falla también a personas: se le dice qué hacer, no se le culpa.
        'antibot' => 'La comprobación de seguridad no ha salido bien. Vuelve a intentarlo; si sigue sin funcionar, díselo directamente a quien te invitó.',
    ],

    // Entre que se abre la página y se pulsa puede cambiar el mundo. Cada motivo dice qué pasó y qué
    // hacer, y NINGUNO depende del nombre que se escribiera: contestan igual a todo el mundo.
    'refused' => [
        'closed' => 'Esta fiesta ya no admite respuestas. Si crees que es un error, habla con quien te invitó.',
        'cutoff' => 'El plazo para confirmar ya ha pasado. Díselo directamente a quien te invitó.',
        'no_name' => 'Necesitamos el nombre del niño o la niña para poder apuntarlo.',
        'too_many' => 'Esta invitación ha recibido demasiadas respuestas seguidas. Inténtalo más tarde.',
    ],

    'closed' => [
        'title' => 'El plazo para confirmar ya ha pasado',
        'text' => 'La información de la fiesta sigue aquí. Si aún no has dicho nada, habla con quien te invitó.',
    ],

    // ⚠️⚠️ BORRADOR PARA EL OWNER (§7.2·R7, §8). Dice las TRES cosas que hay que decir: para qué, quién
    // lo ve —y aquí lo lee un TERCERO, el anfitrión, que no es el parque— y cuándo se borra. Sin
    // casilla (el criterio de `#350`), con la política como control de 48.
    'privacy' => [
        'text' => 'Lo que escribas aquí se lo damos a quien organiza la fiesta, para que sepa quién viene, y al parque, para preparar el día. No lo usamos para nada más y lo borramos a los 14 días de la fiesta.',
        'link' => 'Política de privacidad',
    ],
];
