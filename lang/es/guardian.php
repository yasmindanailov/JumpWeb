<?php

/*
 * Fase 6 · el JUSTIFICANTE de un menor INVITADO a una reserva (`docs/specs/waiver-por-reserva.md`, T2).
 *
 * Lo LEE UN DESCONOCIDO: un padre o una madre sin cuenta, desde un enlace que le ha pasado quien reservó. Por eso
 * vive en un espacio con los TRES idiomas del cliente (es/en/fr) y NUNCA en `admin.*` (`DECISIONES #154`).
 *
 * ▶ Desde la T4 de `specs/fiesta-sistema-nuevo.md` (26-09) la página se viste con el sistema nuevo y sus textos
 *   viven en `fiesta.php` (`firma`, `autorizacion`): aquí queda SOLO lo que el controlador y el dominio siguen
 *   leyendo (los desenlaces, los bloqueos, los errores de validación, el selector de menores, la relación). Lo que
 *   pintaba la piel vieja se retiró con ella, y `ClavesDeIdiomaTest` vigila que no vuelva a quedar texto muerto.
 */
return [
    'booking' => [
        'no_date' => 'Sin fecha asignada todavía',
        // ⚠️ Se enseña el NOMBRE y el TELÉFONO de quien reservó, nunca su correo (§12.4, `[DECIDIDO owner]`).
        'responsible' => 'Va con',
    ],

    'minor' => [
        'born_on_help' => 'La usamos para saber su edad el día de la visita.',
        // Quien llega desde la invitación ya escribió el nombre allí, en UNA casilla. Aquí van dos, así
        // que se le devuelve lo que puso y lo reparte él: el producto no adivina dónde acaba un nombre.
        'from_invitation' => 'En la invitación escribiste «:name». Repártelo aquí: el nombre en una casilla y los apellidos en la otra.',
        // El selector de menores a cargo (§12.5), solo con sesión iniciada.
        'pick' => 'Elige a tu hijo o hija',
        'pick_manual' => 'Escribir los datos a mano',
        'pick_help' => 'Son los menores que tienes declarados en tu cuenta. Al elegir uno se rellenan sus datos; puedes corregirlos.',
    ],

    'guardian' => [
        'relationship_placeholder' => 'Elige una opción',
    ],

    'relationships' => [
        'father' => 'Padre',
        'mother' => 'Madre',
        'legal_guardian' => 'Tutor o tutora legal',
        'grandparent' => 'Abuelo o abuela',
        'other' => 'Otra',
    ],

    'waiver' => [
        'version' => 'Versión :version, publicada el :date',
    ],

    // El anti-robot es la caja de un TERCERO y se dice qué es (J-08). No se recolorea; lo nuestro es este rótulo.
    'antibot_label' => 'Comprobación de seguridad · Cloudflare',

    /*
     * Los desenlaces que NO firman, cada uno con su TÍTULO (J-02). El anti-robot y el rechazo del dominio comparten
     * título porque dicen lo mismo: no se ha registrado nada. El «Firmada» vive en `fiesta.firma.firmada`.
     */
    'done' => [
        'already_title' => 'No hace falta hacer nada',
        'stale_title' => 'Hay que volver a leerlo',
        'refused_title' => 'No se ha registrado nada',
        'already' => ':name ya tiene su autorización firmada para esta reserva. No hace falta hacer nada más.',
        'already_generic' => 'Ese menor ya tiene su autorización firmada para esta reserva.',
        'stale' => 'El texto del descargo de responsabilidad se ha actualizado mientras rellenabas. Vuelve a leerlo y acéptalo de nuevo.',
        'antibot' => 'No hemos podido comprobar que no eres un robot, así que NO hemos registrado nada. Vuelve a intentarlo; si sigue sin funcionar, avisa a la persona que hizo la reserva.',
    ],

    'blocked' => [
        'heading' => 'Por aquí no se puede firmar',
        'not_paid' => 'Esta reserva todavía no está confirmada. Habla con la persona que la hizo.',
        'closed' => 'La visita de esta reserva ya ha pasado, así que este formulario está cerrado.',
        'full' => 'Esta reserva no admite más autorizaciones: ya hay tantas firmadas como plazas compradas. Si falta la de tu hijo o hija, habla con la persona que hizo la reserva.',
    ],

    'errors' => [
        'born_on_future' => 'La fecha de nacimiento tiene que ser anterior a hoy.',
        'born_on_adult' => 'Esa fecha dice que la persona ya es mayor de edad, y entonces firma por sí misma: esta autorización es solo para menores.',
    ],

    /*
     * Se dice con todas las letras que estos datos NO están verificados (`waiver-probatorio.md` §4.5: «Fingir lo
     * contrario es peor que decirlo»). Aquí lo declara un desconocido.
     */
    'notice' => 'Los datos que escribes aquí los declaras tú y no los comprobamos con ningún documento. Se conservan como prueba de esta autorización.',
    'privacy_link' => 'Leer la política de privacidad',
];
