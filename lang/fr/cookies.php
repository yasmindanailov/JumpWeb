<?php

// Bannière et panneau de consentement aux cookies (#219). T3a de la spec d'analytique : quatre finalités — carte et
// avis, réseaux, analyse d'usage identifiée et publicité — ; notre propre mesure d'audience est exemptée et
// expliquée sous « Nécessaires ». ⚠️ Chaque catégorie de `CookieConsent::OPTIONAL` a besoin de ses
// `<catégorie>_title` et `<catégorie>_desc`.

return [
    'banner' => [
        'aria' => 'Avis sur les cookies',
        'eyebrow' => 'Cookies',
        'title' => 'Avant de sauter…',
        'text' => 'Nous utilisons nos propres cookies pour faire fonctionner le site et mesurer l\'audience de façon anonyme. Seulement avec ton accord : la carte et les avis Google, l\'analyse d\'usage liée à ton compte et la publicité.',
        'policy' => 'Plus d\'informations',
        'accept' => 'Accepter',
        'reject' => 'Refuser',
        'configure' => 'Configurer',
        'manage_link' => 'Configuration des cookies',
    ],

    'panel' => [
        'necessary_title' => 'Nécessaires',
        'always_on' => 'Toujours actifs',
        'necessary_desc' => 'Indispensables à la session, à la sécurité des formulaires et au panier, plus un cookie propre de 13 mois qui mesure l\'audience de façon anonyme, sans croiser ni céder de données. Ils sont exemptés de consentement.',
        'maps_title' => 'Carte et avis (Google)',
        'maps_desc' => 'Permet d\'afficher la carte de localisation de Google et les avis publiés sur Google, avec la photo de leurs auteurs. Google peut installer ses propres cookies et traiter des données aux États-Unis.',
        'social_title' => 'Réseaux sociaux',
        'social_desc' => 'Permet d\'afficher nos dernières publications Instagram/TikTok via un widget externe, qui peut installer ses propres cookies.',
        'analytics_title' => 'Analyse d\'usage identifiée',
        'analytics_desc' => 'Permet de lier ta navigation à ton compte lorsque tu te connectes ou achètes, pour comprendre comment tu utilises le site, et d\'utiliser un outil d\'analyse avec un identifiant chiffré à la place de ton nom. La mesure anonyme de l\'audience n\'a pas besoin de cette autorisation.',
        'marketing_title' => 'Publicité',
        'marketing_desc' => 'Permet de charger les pixels des plateformes publicitaires et de leur communiquer les achats, pour mesurer quelles campagnes fonctionnent. Sans cette autorisation, aucun pixel n\'est chargé et rien n\'est communiqué.',
        'reject_all' => 'Tout refuser',
        'save' => 'Enregistrer les préférences',
        'accept_all' => 'Tout accepter',
        'policy_link' => 'Lire la politique de cookies',
    ],

    'policy' => [
        'tool_active' => 'Outil d\'analyse d\'usage actif sur ce site : :tool. Il ne se charge que si tu autorises la catégorie «analyse», et il ne reçoit jamais ton nom, ton e-mail ni ton adresse IP.',
        'tool_posthog' => 'PostHog (PostHog Inc. ; les données sont hébergées sur des serveurs de l\'Union européenne)',
        'tool_matomo' => 'Matomo (installation propre sur :host)',
    ],

    'frame' => [
        'maps_text' => 'Pour voir la carte, il faut charger du contenu de Google Maps, qui peut installer des cookies.',
        'maps_btn' => 'Charger la carte',
        'social_text' => 'Pour voir le fil, il faut charger du contenu d\'un fournisseur externe, qui peut installer des cookies.',
        'social_btn' => 'Charger le contenu',
        'policy_link' => 'Politique de cookies',
        'noscript' => 'Avec JavaScript désactivé, nous ne chargeons pas ce contenu de tiers, afin de ne pas installer de cookies sans ton accord.',
    ],
];
