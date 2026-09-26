<?php

/*
 * Phase 6 · l'AUTORISATION pour un mineur invité à une réservation (`docs/specs/waiver-por-reserva.md`, lot T2).
 * Lue par un INCONNU : un parent sans compte, depuis un lien transmis par la personne qui a réservé. Trois
 * langues, jamais dans `admin.*`. Depuis le T4 de `specs/fiesta-sistema-nuevo.md`, la page est habillée par le
 * nouveau système (`fiesta.php`) : il ne reste ici que ce que le contrôleur et le domaine lisent encore.
 */
return [
    'booking' => [
        'no_date' => 'Aucune date attribuée pour l’instant',
        'responsible' => 'Accompagné par',
    ],

    'minor' => [
        'born_on_help' => 'Elle nous sert à connaître son âge le jour de la visite.',
        'from_invitation' => 'Sur l’invitation tu as écrit « :name ». Répartis-le ici : le prénom dans une case et le nom dans l’autre.',
        'pick' => 'Choisissez votre enfant',
        'pick_manual' => 'Saisir les données à la main',
        'pick_help' => 'Ce sont les mineurs déclarés dans votre compte. En choisir un remplit ses données ; vous pouvez les corriger.',
    ],

    'guardian' => [
        'relationship_placeholder' => 'Choisissez une option',
    ],

    'relationships' => [
        'father' => 'Père',
        'mother' => 'Mère',
        'legal_guardian' => 'Tuteur ou tutrice légale',
        'grandparent' => 'Grand-parent',
        'other' => 'Autre',
    ],

    'waiver' => [
        'version' => 'Version :version, publiée le :date',
    ],

    'antibot_label' => 'Contrôle de sécurité · Cloudflare',

    'done' => [
        'already_title' => 'Rien d’autre à faire',
        'stale_title' => 'Il faut le relire',
        'refused_title' => 'Rien n’a été enregistré',
        'already' => ':name a déjà une autorisation signée pour cette réservation. Rien d’autre à faire.',
        'already_generic' => 'Ce mineur a déjà une autorisation signée pour cette réservation.',
        'stale' => 'Le texte de la décharge a été mis à jour pendant que vous remplissiez le formulaire. Relisez-le et acceptez-le à nouveau.',
        'antibot' => 'Nous n’avons pas pu vérifier que vous n’êtes pas un robot : rien n’a été enregistré. Réessayez ; si cela ne fonctionne toujours pas, prévenez la personne qui a réservé.',
    ],

    'blocked' => [
        'heading' => 'Impossible de signer ici',
        'not_paid' => 'Cette réservation n’est pas encore confirmée. Parlez-en à la personne qui l’a faite.',
        'closed' => 'La visite de cette réservation a déjà eu lieu : ce formulaire est fermé.',
        'full' => "Cette réservation n'accepte plus d'autorisations : il y en a déjà autant de signées que de places achetées. S'il en manque une, parlez-en à la personne qui a réservé.",
    ],

    'errors' => [
        'born_on_future' => 'La date de naissance doit être antérieure à aujourd’hui.',
        'born_on_adult' => 'Cette date indique que la personne est déjà majeure, et une personne majeure signe pour elle-même : cette autorisation est réservée aux mineurs.',
    ],

    'notice' => 'Vous déclarez vous-même ces données et nous ne les vérifions avec aucun document. Elles sont conservées comme preuve de cette autorisation.',
    'privacy_link' => 'Lire la politique de confidentialité',
];
