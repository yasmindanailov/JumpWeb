<?php

/*
 * Fase 6 · el JUSTIFICANTE de un menor INVITADO a una reserva
 * (`docs/specs/waiver-por-reserva.md`, tanda T2).
 *
 * Lo LEE UN DESCONOCIDO: un padre o una madre sin cuenta, desde un enlace que le ha pasado quien
 * reservó. Por eso vive en un espacio con los TRES idiomas del cliente (es/en/fr) y NUNCA en
 * `admin.*` (`DECISIONES #154`), y por eso el tono explica en vez de dar por sabido: quien lee esto
 * no ha usado nunca esta web.
 */
return [
    'title' => 'Autorización para menores',
    'intro' => 'Rellena este formulario para autorizar la entrada de un menor a tu cargo. Lo ha preparado la persona que hizo la reserva.',

    'booking' => [
        'heading' => 'La reserva',
        'reference' => 'Referencia',
        'date' => 'Día de la visita',
        'dates' => 'Días de la visita',
        'no_date' => 'Sin fecha asignada todavía',
    ],

    'minor' => [
        'heading' => 'Datos del menor',
        'name' => 'Nombre',
        'surname' => 'Apellidos',
        'born_on' => 'Fecha de nacimiento',
        'born_on_help' => 'La usamos para saber su edad el día de la visita.',
    ],

    'guardian' => [
        'heading' => 'Tus datos',
        'help' => 'Como padre, madre o tutor legal del menor.',
        'name' => 'Nombre',
        'surname' => 'Apellidos',
        'relationship' => 'Relación con el menor',
        'relationship_placeholder' => 'Elige una opción',
        'email' => 'Correo electrónico',
        'email_help' => 'Opcional. Si lo dejas, te enviamos una copia de lo que firmas.',
        'phone' => 'Teléfono',
        'phone_help' => 'Opcional. Para poder localizarte el día de la visita.',
    ],

    'relationships' => [
        'father' => 'Padre',
        'mother' => 'Madre',
        'legal_guardian' => 'Tutor o tutora legal',
        'grandparent' => 'Abuelo o abuela',
        'other' => 'Otra',
    ],

    'waiver' => [
        'heading' => 'Descargo de responsabilidad',
        'accept' => 'He leído el descargo de responsabilidad y lo acepto en nombre del menor.',
        'version' => 'Versión :version, publicada el :date',
    ],

    'submit' => 'Firmar la autorización',

    'done' => [
        'signed' => 'Listo: la autorización de :name ha quedado registrada.',
        'signed_generic' => 'Listo: la autorización ha quedado registrada.',
        'already' => ':name ya tiene su autorización firmada para esta reserva. No hace falta hacer nada más.',
        'already_generic' => 'Ese menor ya tiene su autorización firmada para esta reserva.',
        'stale' => 'El texto del descargo de responsabilidad se ha actualizado mientras rellenabas. Vuelve a leerlo y acéptalo de nuevo.',
        'antibot' => 'No hemos podido comprobar que no eres un robot, así que NO hemos registrado nada. Vuelve a intentarlo; si sigue sin funcionar, avisa a la persona que hizo la reserva.',
    ],

    'blocked' => [
        'heading' => 'Por aquí no se puede firmar',
        'not_paid' => 'Esta reserva todavía no está confirmada. Habla con la persona que la hizo.',
        'closed' => 'La visita de esta reserva ya ha pasado, así que este formulario está cerrado.',
        'full' => 'Esta reserva ya tiene todas sus autorizaciones. Si crees que falta la de tu hijo o hija, habla con la persona que hizo la reserva.',
    ],

    'errors' => [
        'accept_waiver' => 'Para poder firmar tienes que aceptar el descargo de responsabilidad.',
        'born_on_future' => 'La fecha de nacimiento tiene que ser anterior a hoy.',
        'born_on_adult' => 'Esa fecha dice que la persona ya es mayor de edad, y entonces firma por sí misma: esta autorización es solo para menores.',
    ],

    /*
     * Se dice con todas las letras que estos datos NO están verificados
     * (`waiver-probatorio.md` §4.5: «Fingir lo contrario es peor que decirlo»). Aquí es más grave que
     * en el resto del subsistema: allí lo declaraba alguien con cuenta y correo verificado; aquí lo
     * declara un desconocido.
     */
    'notice' => 'Los datos que escribes aquí los declaras tú y no los comprobamos con ningún documento. Se conservan como prueba de esta autorización.',
    'privacy' => 'Tratamos estos datos para poder dejar entrar al menor y como prueba de tu autorización. Puedes ejercer tus derechos como se explica en :link.',
    'privacy_link' => 'nuestra política de privacidad',
];
