<?php

/*
 * Fase 6 · waiver — textos del PDF del registro probatorio (`specs/waiver-probatorio.md` §4.5).
 * Es un documento que LEE EL TITULAR (y el operador), así que vive en un espacio con los TRES
 * idiomas del cliente (es/en/fr), nunca en `admin.*` (`DECISIONES #154`). Se sirve en el idioma
 * del texto firmado.
 */
return [
    'proof' => [
        'title' => 'Registro de aceptación del waiver',
        'published_at' => 'Versión publicada el :date',
        'signed_text' => 'Texto aceptado (íntegro, tal y como se presentó)',
        'holder' => 'Firmante',
        'holder_name' => 'Nombre',
        'holder_email' => 'Correo electrónico',
        'subject' => 'En nombre de',
        'subject_holder' => 'La propia persona titular de la cuenta',
        'subject_dependent' => 'Un menor a su cargo: :name (fecha de nacimiento :born_on)',
        'subject_dependent_note' => 'Los datos del menor los declaró la persona titular al añadirlo a su cuenta y no han sido verificados por medios externos. Se copian aquí tal y como estaban en el momento de la aceptación.',
        'subject_guest_minor' => 'Un menor INVITADO a una reserva: :name (fecha de nacimiento :born_on). No es una persona a cargo de quien reservó.',
        'signer' => 'Quien acepta (adulto responsable del menor)',
        'signer_contact' => 'Contacto de quien acepta',
        'responsible' => 'Persona titular de la reserva',
        'booking' => 'Reserva',
        'subject_guest_minor_note' => 'Esta aceptación NO la registró la persona titular de la reserva: la registró un adulto que declara ser padre, madre o tutor legal del menor y que NO tiene cuenta en este sistema. Ni su identidad, ni su correo, ni los datos del menor han sido verificados por ningún medio externo: se copian aquí tal y como los escribió en el momento de aceptar. Lo que este registro prueba es que a esa persona se le presentó el texto y que lo aceptó de forma explícita.',
        'holder_note' => 'Los datos de identidad los declaró la persona al crear su cuenta y no han sido verificados por medios externos. Se copian aquí tal y como estaban en el momento de la aceptación.',
        'holder_note_declared' => 'Los datos de identidad los tecleó el operador en el mostrador y no han sido verificados por medios externos. Se copian aquí tal y como quedaron en el momento del registro.',
        'holder_anonymised' => 'La cuenta ha sido suprimida a petición de su titular (art. 17 RGPD). Este registro se conserva vinculado bajo tratamiento restringido (art. 17.3.e y 18).',
        'acceptance' => 'Aceptación',
        'accepted_at' => 'Fecha y hora',
        'channel' => 'Canal',
        'channels' => [
            'web' => 'Web (navegador de la persona)',
            'api' => 'Aplicación (API)',
            'panel' => 'Mostrador (panel del operador)',
        ],
        'ip' => 'Dirección IP',
        'user_agent' => 'Navegador (user-agent)',
        'ip_declared' => 'Dirección IP (del puesto de mostrador)',
        'user_agent_declared' => 'Navegador del puesto de mostrador (user-agent)',
        'presented_note' => 'El texto se presentó en el propio flujo y la persona lo aceptó de forma explícita, mediante una casilla separada y desmarcada por defecto. Este registro prueba que se le presentó y que lo aceptó; no prueba que lo leyera.',
        'declared_title' => 'Aceptación DECLARADA por el operador',
        'declared_text' => 'Esta aceptación no la registró la persona desde su propio dispositivo: el operador :operator declara que la persona aceptó el texto en persona, en el mostrador. Es sustancialmente más débil que una aceptación registrada por la propia persona.',
        'integrity' => 'Integridad del registro',
        'document_hash' => 'Hash del texto (SHA-256)',
        'signature_hash' => 'Hash del registro (SHA-256)',
        'prev_hash' => 'Hash del registro anterior',
        'first_link' => 'Primer registro de este sujeto (sin anterior)',
        'canonical' => 'Esquema canónico',
        'verification' => 'Comprobación',
        'verified_yes' => 'Coincide: el texto y el registro cuadran con sus hashes (comprobación interna)',
        'verified_no' => 'NO COINCIDE: el contenido no cuadra con su hash (comprobación interna)',
        'verification_note' => 'La comprobación es interna: el registro es una cadena de hashes por sujeto —el titular, o cada menor a su cargo— (SHA-256, sin secreto) guardada en la propia base de datos, sin sello de tiempo cualificado ni anclaje en un tercero. Detecta alteraciones accidentales o hechas por la aplicación; no protege frente a quien pueda escribir directamente en la base de datos.',
        'retention' => 'Conservación',
        'retention_until' => 'Este registro se conservará hasta el :date, salvo obligación legal en contrario.',
        'retention_none' => 'El plazo de conservación de este registro no está fijado todavía.',
        'footer_note' => 'Este documento es la representación legible de un registro electrónico. El valor probatorio reside en el registro —solo se añade, con hash encadenado por titular, versión inmutable del texto y rastro de auditoría—, no en este PDF, que no lleva firma digital. El registro no está anclado en un tercero: no hay sello de tiempo cualificado.',
    ],
];
