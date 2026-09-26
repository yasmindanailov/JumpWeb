<?php

/*
 * La INVITACIÓN DIGITAL de una fiesta (`docs/specs/celebracion-e-invitacion.md` §4.6, T5).
 *
 * ❗❗ Lo LEE UN DESCONOCIDO: el enlace se reparte **a un grupo de clase entero** por un chat de padres. Por eso vive
 * en los TRES idiomas del cliente (es/en/fr) y NUNCA en `admin.*` (`DECISIONES #154`).
 *
 * ▶ Desde la T2 de `specs/fiesta-sistema-nuevo.md` (25-09) la página y su recibo se visten con el sistema nuevo y
 *   sus textos viven en `fiesta.php` (`invitacion_pagina`, `recibo`): aquí queda SOLO lo que el controlador y el
 *   dominio siguen leyendo (la vista previa al pegar el enlace, el calendario, los rechazos, los desenlaces del POST,
 *   el anti-robot). Lo que pintaba la piel vieja se retiró con ella en la T4 (26-09); `ClavesDeIdiomaTest` vigila.
 */
return [
    'calendar' => [
        // Lo que verá en su calendario, meses después y fuera de esta página: tiene que decir de qué
        // fiesta habla sin contexto ninguno.
        'summary' => 'Cumple de :name',
    ],
    // ⚠️⚠️ Lo que se ve al PEGAR el enlace en un chat. Aquí solo entran nombre, edad, día, hora y
    // negocio (§4.6): la vista previa la pinta un tercero que nadie controla.
    'og' => [
        'title' => ':name cumple :age',
        'title_no_age' => 'El cumple de :name',
        'description' => 'A las :time en :business. Dinos si venís.',
        'description_no_time' => 'En :business. Dinos si venís.',
    ],

    'antibot_label' => 'Comprobación de seguridad',

    // Lo que dice el POST al volver: el «sí» genérico y el «no» viajan en sesión hasta el recibo.
    'done' => [
        'yes_generic' => 'Ya se lo hemos dicho a quien organiza la fiesta. ¡Nos vemos allí!',
        'no' => 'Se lo hemos dicho a quien organiza la fiesta. Otra vez será.',
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

    'receipt' => [
        'save' => 'Guardar',
    ],
];
