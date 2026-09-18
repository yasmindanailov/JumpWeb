<?php

/*
 * Phase 6 · l'AUTORISATION pour un mineur invité à une réservation
 * (`docs/specs/waiver-por-reserva.md`, lot T2). Lue par un INCONNU : un parent sans compte, depuis un
 * lien transmis par la personne qui a réservé. Trois langues, jamais dans `admin.*`.
 */
return [
    'title' => 'Autorisation pour les mineurs',
    'stub' => [
        'badge' => "Autorisation d'entrée",
        'heading' => 'Autorisez votre enfant',
        'lede' => "Quelqu'un a réservé une visite au parc et votre enfant vient avec le groupe. Pour qu'il puisse entrer, nous avons besoin de votre autorisation écrite. Cela prend deux minutes et aucun compte n'est nécessaire.",
    ],

    'booking' => [
        'heading' => 'La réservation',
        'reference' => 'Référence',
        'date' => 'Jour de la visite',
        'no_date' => 'Aucune date attribuée pour l’instant',
        'responsible' => 'Accompagné par',
    ],

    'minor' => [
        'heading' => 'Données du mineur',
        'name' => 'Prénom',
        'surname' => 'Nom',
        'born_on' => 'Date de naissance',
        'born_on_help' => 'Elle nous sert à connaître son âge le jour de la visite.',
        'from_invitation' => 'Sur l’invitation tu as écrit « :name ». Répartis-le ici : le prénom dans une case et le nom dans l’autre.',
        'pick' => 'Choisissez votre enfant',
        'pick_manual' => 'Saisir les données à la main',
        'pick_help' => 'Ce sont les mineurs déclarés dans votre compte. En choisir un remplit ses données ; vous pouvez les corriger.',
    ],

    'guardian' => [
        'heading' => 'Vos données',
        'help' => 'En tant que parent ou tuteur légal du mineur.',
        'name' => 'Prénom',
        'surname' => 'Nom',
        'relationship' => 'Lien avec le mineur',
        'relationship_placeholder' => 'Choisissez une option',
        'email' => 'Adresse e-mail',
        'email_help' => 'Si vous la laissez, nous vous envoyons une copie de ce que vous signez.',
        'phone' => 'Téléphone',
        'phone_help' => 'Pour pouvoir vous joindre le jour de la visite.',
    ],

    'relationships' => [
        'father' => 'Père',
        'mother' => 'Mère',
        'legal_guardian' => 'Tuteur ou tutrice légale',
        'grandparent' => 'Grand-parent',
        'other' => 'Autre',
    ],

    'waiver' => [
        'heading' => 'Décharge de responsabilité',
        'accept' => 'J’ai lu la décharge de responsabilité et je l’accepte au nom du mineur.',
        'version' => 'Version :version, publiée le :date',
    ],

    'submit' => 'Signer l’autorisation',
    'submit_short' => 'Signer',
    'bar' => [
        'minor' => 'Mineur',
    ],

    'antibot_label' => 'Contrôle de sécurité · Cloudflare',

    'done' => [
        'signed_title' => 'Autorisation enregistrée',
        'already_title' => 'Rien d’autre à faire',
        'stale_title' => 'Il faut le relire',
        'refused_title' => 'Rien n’a été enregistré',
        'signed' => 'C’est fait : l’autorisation de :name a bien été enregistrée.',
        'signed_generic' => 'C’est fait : l’autorisation a bien été enregistrée.',
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
        'accept_waiver' => 'Vous devez accepter la décharge de responsabilité pour pouvoir signer.',
        'born_on_future' => 'La date de naissance doit être antérieure à aujourd’hui.',
        'born_on_adult' => 'Cette date indique que la personne est déjà majeure, et une personne majeure signe pour elle-même : cette autorisation est réservée aux mineurs.',
    ],

    'notice' => 'Vous déclarez vous-même ces données et nous ne les vérifions avec aucun document. Elles sont conservées comme preuve de cette autorisation.',
    'privacy' => 'Nous traitons ces données pour permettre l’entrée du mineur et comme preuve de votre autorisation. Vous pouvez exercer vos droits comme expliqué dans notre politique de confidentialité.',
    'privacy_link' => 'Lire la politique de confidentialité',
];
