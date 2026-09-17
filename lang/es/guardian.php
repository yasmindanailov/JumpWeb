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

    /*
     * El RESGUARDO de la cabecera (T8, §12.4). Dice las dos cosas que un padre necesita saber antes
     * de firmar: a qué visita va su hijo y CON QUIÉN.
     */
    'stub' => [
        'badge' => 'Autorización de entrada',
        'heading' => 'Autoriza a tu hijo o hija',
        'lede' => 'Alguien ha reservado una visita al parque y tu hijo o hija va con el grupo. Para que pueda entrar necesitamos que lo autorices por escrito. Son dos minutos y no hace falta tener cuenta.',
    ],

    'booking' => [
        'heading' => 'La reserva',
        'reference' => 'Referencia',
        'date' => 'Día de la visita',
        'no_date' => 'Sin fecha asignada todavía',
        // ⚠️ Se enseña el NOMBRE y el TELÉFONO de quien reservó, nunca su correo (§12.4,
        // `[DECIDIDO owner]`): este enlace lo reparte él a gente que no conocemos.
        'responsible' => 'Va con',
    ],

    'minor' => [
        'heading' => 'Datos del menor',
        'name' => 'Nombre',
        'surname' => 'Apellidos',
        'born_on' => 'Fecha de nacimiento',
        'born_on_help' => 'La usamos para saber su edad el día de la visita.',
        // El selector de menores a cargo (§12.5), solo con sesión iniciada.
        'pick' => 'Elige a tu hijo o hija',
        'pick_manual' => 'Escribir los datos a mano',
        'pick_help' => 'Son los menores que tienes declarados en tu cuenta. Al elegir uno se rellenan sus datos; puedes corregirlos.',
    ],

    'guardian' => [
        'heading' => 'Tus datos',
        'help' => 'Como padre, madre o tutor legal del menor.',
        'name' => 'Nombre',
        'surname' => 'Apellidos',
        'relationship' => 'Relación con el menor',
        'relationship_placeholder' => 'Elige una opción',
        'email' => 'Correo electrónico',
        // ⚠️ Sin «Opcional.» delante (T3): lo opcional lo marca el RÓTULO del campo, con
        // `guestform.optional`, como en la hoja hermana. Decirlo dos veces era la grieta del asterisco al revés.
        'email_help' => 'Si lo dejas, te enviamos una copia de lo que firmas.',
        'phone' => 'Teléfono',
        'phone_help' => 'Para poder localizarte el día de la visita.',
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

    /*
     * La BARRA de firmar (T3 de `celebracion-e-invitacion.md`): pegada abajo, con el nombre del menor a la
     * izquierda —lo único que hay que releer antes de firmar— y «Firmar» a 56. El rótulo largo no cabe
     * junto a un nombre a 390 px, así que se queda de NOMBRE ACCESIBLE del botón (contiene al visible).
     */
    'submit' => 'Firmar la autorización',
    'submit_short' => 'Firmar',
    'bar' => [
        'minor' => 'Menor',
    ],

    // El anti-robot es la caja de un TERCERO y se dice qué es (J-08): segunda excepción declarada del
    // sistema, después del botón de Google. No se recolorea; lo nuestro es este rótulo.
    'antibot_label' => 'Comprobación de seguridad · Cloudflare',

    /*
     * Cinco desenlaces, cuatro tonos (J-02), y cada uno con su TÍTULO: la receta del aviso sobre papel
     * lo pide, y el punto del tono vive en él. El anti-robot y el rechazo del dominio comparten título
     * porque dicen lo mismo: no se ha registrado nada.
     */
    'done' => [
        'signed_title' => 'Autorización registrada',
        'already_title' => 'No hace falta hacer nada',
        'stale_title' => 'Hay que volver a leerlo',
        'refused_title' => 'No se ha registrado nada',
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
        'full' => 'Esta reserva no admite más autorizaciones: ya hay tantas firmadas como plazas compradas. Si falta la de tu hijo o hija, habla con la persona que hizo la reserva.',
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
    // ⚠️ La política va FUERA de su frase, como control propio de 48 (J-07, la misma regla que F-08 en
    // la hoja hermana): dentro del párrafo corría en cajas de línea de 20 px.
    'privacy' => 'Tratamos estos datos para poder dejar entrar al menor y como prueba de tu autorización. Puedes ejercer tus derechos como se explica en nuestra política de privacidad.',
    'privacy_link' => 'Leer la política de privacidad',
];
