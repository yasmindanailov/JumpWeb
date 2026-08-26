<?php

/*
 * Phase 6 · waiver — textes du PDF du registre probatoire (`specs/waiver-probatorio.md` §4.5).
 * Lu par le client : espace de noms avec les TROIS langues du client.
 */
return [
    'proof' => [
        'title' => 'Registre d\'acceptation de la décharge',
        'published_at' => 'Version publiée le :date',
        'signed_text' => 'Texte accepté (intégral, tel qu\'il a été présenté)',
        'holder' => 'Signataire',
        'holder_name' => 'Nom',
        'holder_email' => 'Adresse e-mail',
        'subject' => 'Au nom de',
        'subject_holder' => 'La personne titulaire du compte elle-même',
        'subject_dependent' => 'Un mineur à sa charge (n° :id)',
        'holder_note' => 'Les données d\'identité ont été déclarées par la personne lors de la création de son compte et n\'ont pas été vérifiées par des moyens externes. Elles sont copiées ici telles qu\'elles étaient au moment de l\'acceptation.',
        'holder_anonymised' => 'Le compte a été supprimé à la demande de son titulaire (art. 17 RGPD). Ce registre est conservé, lié, sous traitement restreint (art. 17.3.e et 18).',
        'acceptance' => 'Acceptation',
        'accepted_at' => 'Date et heure',
        'channel' => 'Canal',
        'channels' => [
            'web' => 'Web (navigateur de la personne)',
            'api' => 'Application (API)',
            'panel' => 'Accueil (panneau de l\'opérateur)',
        ],
        'ip' => 'Adresse IP',
        'user_agent' => 'Navigateur (user-agent)',
        'presented_note' => 'Le texte a été présenté dans le parcours lui-même et la personne l\'a accepté de façon explicite, par une case séparée et décochée par défaut. Ce registre prouve qu\'il a été présenté et accepté ; il ne prouve pas qu\'il a été lu.',
        'declared_title' => 'Acceptation DÉCLARÉE par l\'opérateur',
        'declared_text' => 'Cette acceptation n\'a pas été enregistrée par la personne depuis son propre appareil : l\'opérateur :operator déclare que la personne a accepté le texte en personne, à l\'accueil. Elle est sensiblement plus faible qu\'une acceptation enregistrée par la personne elle-même.',
        'integrity' => 'Intégrité du registre',
        'document_hash' => 'Empreinte du texte (SHA-256)',
        'signature_hash' => 'Empreinte du registre (SHA-256)',
        'prev_hash' => 'Empreinte du registre précédent',
        'first_link' => 'Premier registre de cette personne (sans précédent)',
        'canonical' => 'Schéma canonique',
        'verification' => 'Vérification',
        'verified_yes' => 'Vérifiée : le texte et le registre correspondent à leurs empreintes',
        'verified_no' => 'NON VÉRIFIÉE : le contenu ne correspond pas à son empreinte',
        'retention' => 'Conservation',
        'retention_until' => 'Ce registre sera conservé jusqu\'au :date, sauf obligation légale contraire.',
        'retention_none' => 'La durée de conservation de ce registre n\'est pas encore fixée.',
        'footer_note' => 'Ce document est la représentation lisible d\'un registre électronique. Sa valeur probante réside dans le registre lui-même (ligne immuable, version immuable du texte et piste d\'audit), et non dans ce PDF, qui ne porte aucune signature numérique.',
    ],
];
