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
        'subject_dependent' => 'Un menor a su cargo (n.º :id)',
        'holder_note' => 'Los datos de identidad los declaró la persona al crear su cuenta y no han sido verificados por medios externos. Se copian aquí tal y como estaban en el momento de la aceptación.',
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
        'presented_note' => 'El texto se presentó en el propio flujo y la persona lo aceptó de forma explícita, mediante una casilla separada y desmarcada por defecto. Este registro prueba que se le presentó y que lo aceptó; no prueba que lo leyera.',
        'declared_title' => 'Aceptación DECLARADA por el operador',
        'declared_text' => 'Esta aceptación no la registró la persona desde su propio dispositivo: el operador :operator declara que la persona aceptó el texto en persona, en el mostrador. Es sustancialmente más débil que una aceptación registrada por la propia persona.',
        'integrity' => 'Integridad del registro',
        'document_hash' => 'Hash del texto (SHA-256)',
        'signature_hash' => 'Hash del registro (SHA-256)',
        'prev_hash' => 'Hash del registro anterior',
        'first_link' => 'Primer registro de esta persona (sin anterior)',
        'canonical' => 'Esquema canónico',
        'verification' => 'Comprobación',
        'verified_yes' => 'Verificada: el texto y el registro coinciden con sus hashes',
        'verified_no' => 'NO VERIFICADA: el contenido no coincide con su hash',
        'retention' => 'Conservación',
        'retention_until' => 'Este registro se conservará hasta el :date, salvo obligación legal en contrario.',
        'retention_none' => 'El plazo de conservación de este registro no está fijado todavía.',
        'footer_note' => 'Este documento es la representación legible de un registro electrónico. El valor probatorio reside en el registro (fila inmutable, versión inmutable del texto y rastro de auditoría), no en este PDF, que no lleva firma digital.',
    ],
];
