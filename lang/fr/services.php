<?php

// Textes de la page /servicios (design v2 « Editorial XL »). Même logique que v1 : page pilotée
// par i18n, CTA vers /contacto, prix réels [À VENIR] du client (non affichés). `sections[].anchor`
// et `other.anchor` sont STABLES (liés depuis le nav via `/servicios#xxx` et vérifiés par les tests).
// Miroir de lang/es/services.php — garder la structure en parité.

return [
    'meta' => [
        'title' => 'Services',
        'description' => 'Sorties scolaires, entreprises, sessions adultes et événements privés dans notre parc.',
    ],
    'eyebrow' => 'Pour les groupes et événements',
    'title' => 'Au-delà du saut libre',
    // Fragment du titre mis en avant dans le hero (`.blink`, maquette v2). Doit être une
    // sous-chaîne EXACTE de `title`; sinon le hero retombe proprement sur le titre simple.
    'title_accent' => 'saut libre',
    'intro' => 'On adapte le parc aux écoles, entreprises et groupes, y compris en dehors de nos horaires habituels. Demande-nous des infos sans engagement et on conçoit la journée avec toi.',
    'cta_contact' => 'Nous contacter',
    'service_label' => 'Service',
    'zone_label' => 'Zone',
    // Tableau de tarifs de groupe (informatif) pour une section — cas « Sorties scolaires ».
    'rates' => [
        'title' => 'Tarifs de groupe',
        'group' => 'Groupe',
        'weekday' => 'Lun–Ven',
        'weekend' => 'Week-end/férié',
        'kids' => ':count enfants',
        'people' => ':count personnes',
        'note_kids' => "Prix par enfant ; dégressif selon la taille du groupe. Séances hors horaires d'ouverture au public. Réserve par téléphone ou demande-nous des infos.",
        'note_people' => "Prix par personne ; dégressif selon la taille du groupe. Séances hors horaires d'ouverture au public. Réserve par téléphone ou demande-nous des infos.",
    ],
    // `sections` migrées vers l'entité CMS `LandingService` (#256, modèle A) : le blade /servicios
    // les lit depuis la BD (semées dans LandingContentSeeder). Ici ne reste que le chrome de page.
    'other' => [
        'anchor' => 'eventos',
        'title' => 'Autres événements',
        'body' => 'EVJF/EVG, fêtes privées, tournages ou toute autre idée. Dis-nous ce que tu as en tête et on te propose une formule sur mesure.',
    ],
];
