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
        'text' => 'Nous utilisons nos propres cookies pour faire fonctionner le site et mesurer l\'audience de façon anonyme. Seulement avec ton accord : la carte Google, l\'analyse d\'usage liée à ton compte et la publicité.',
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
        'maps_title' => 'Carte (Google)',
        'maps_desc' => 'Permet d\'afficher la carte Google pour venir au parc. Google peut installer ses propres cookies et traiter des données aux États-Unis.',
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

    'inventory' => [
        'title' => 'Les cookies de ce site, un par un',
        'intro' => 'Cette liste est établie par le site lui-même à partir de ce qui est actif en ce moment : si quelque chose est activé ou désactivé, la liste change avec.',
        'labels' => ['holder' => 'Qui le dépose', 'purpose' => 'À quoi il sert', 'duration' => 'Combien de temps', 'when' => 'Quand'],
        'own' => 'Nous (cookie propre)',
        'category' => [
            'necessary' => 'Nécessaire',
            'on_request' => 'Préférence (vous la demandez)',
            'measurement' => 'Mesure d’audience (exemptée)',
            'maps' => 'Carte (avec votre accord)',
            'social' => 'Réseaux sociaux (avec votre accord)',
            'analytics' => 'Analyse (avec votre accord)',
            'marketing' => 'Publicité (avec votre accord)',
        ],
        'hours' => ':n heures depuis votre dernière visite',
        'minutes' => ':n minutes depuis votre dernière visite',
        'months' => ':n mois',
        'session' => ['purpose' => 'Maintient votre visite : l’achat en cours et, si vous vous connectez, votre session.', 'when' => 'Toujours'],
        'xsrf' => ['purpose' => 'Sécurité : vérifie que les formulaires sont bien envoyés par vous et non par un autre site (protection CSRF).', 'when' => 'Toujours'],
        'visitor' => ['purpose' => 'Compte les visites et les campagnes qui les amènent, uniquement en statistiques anonymes pour nous : il n’est ni croisé avec d’autres sites ni cédé.', 'when' => 'Toujours ; il n’est pas renouvelé à chaque visite'],
        'consent' => ['purpose' => 'Conserve ce que vous avez décidé dans l’avis sur les cookies, pour ne pas vous le redemander.', 'duration' => ':n mois (certains navigateurs le conservent moins)', 'when' => 'Quand vous acceptez, refusez ou configurez'],
        'remember' => ['purpose' => 'Vous garde connecté sur cet appareil, pour ne pas vous demander un code à chaque fois.', 'duration' => ':n jours depuis votre dernière visite, ou jusqu’à votre déconnexion', 'when' => 'Seulement si vous cochez « Rester connecté sur cet appareil » à la connexion'],
        'redsys' => ['name' => 'Ceux de la passerelle de paiement', 'holder' => 'Redsys Servicios de Procesamiento, S.L., sur son propre site', 'purpose' => 'Traiter le paiement par carte que vous lancez.', 'duration' => 'Ceux fixés par Redsys', 'when' => 'Seulement au paiement, sur la page de Redsys'],
        'turnstile' => ['name' => 'Ceux du système anti-robots (Turnstile)', 'holder' => 'Cloudflare, Inc. (États-Unis, dans le cadre du EU-US Data Privacy Framework)', 'purpose' => 'Distinguer les personnes des robots dans les formulaires.', 'duration' => 'Temporaires', 'when' => 'À l’inscription'],
        'maps' => ['name' => 'Ceux de Google Maps', 'holder' => 'Google Ireland Limited (et Google LLC, aux États-Unis, dans le cadre du EU-US Data Privacy Framework)', 'purpose' => 'Afficher la carte pour venir.', 'duration' => 'Ceux fixés par Google (voir sa politique)', 'when' => 'Seulement si vous autorisez « Carte (Google) »'],
        'social' => ['name' => 'Ceux du widget de réseaux sociaux (:provider)', 'holder' => ':provider (prestataire externe ; sa garantie de transfert, dans sa politique de confidentialité)', 'purpose' => 'Afficher nos dernières publications sur les réseaux sociaux.', 'duration' => 'Ceux fixés par le prestataire', 'when' => 'Seulement si vous autorisez « Réseaux sociaux »'],
        'posthog' => ['name' => 'Ceux de PostHog (p. ex. « ph_…_posthog »)', 'holder' => 'PostHog Inc. (données hébergées sur des serveurs de l’Union européenne)', 'purpose' => 'Comprendre l’usage du site avec un identifiant chiffré, sans votre nom, votre e-mail ni votre adresse IP.', 'duration' => 'Jusqu’à 12 mois', 'when' => 'Seulement si vous autorisez « Analyse d\'usage identifiée »'],
        'matomo' => ['name' => 'Ceux de Matomo (p. ex. « _pk_id » et « _pk_ses »)', 'holder' => 'Nous, avec Matomo installé sur :host', 'purpose' => 'Comprendre l’usage du site avec un identifiant chiffré, sans votre nom, votre e-mail ni votre adresse IP.', 'duration' => 'Jusqu’à 13 mois (« _pk_ses », 30 minutes)', 'when' => 'Seulement si vous autorisez « Analyse d\'usage identifiée »'],
        'google_ads' => ['name' => 'Ceux de Google Ads (p. ex. « _gcl_au »)', 'holder' => 'Google Ireland Limited (États-Unis, dans le cadre du EU-US Data Privacy Framework)', 'purpose' => 'Savoir quelles annonces amènent des achats : il reçoit l’achat avec un identifiant et vos coordonnées uniquement sous forme d’empreinte irréversible (hachage).', 'duration' => 'Jusqu’à 90 jours', 'when' => 'Seulement si vous autorisez « Publicité »'],
        'meta' => ['name' => 'Ceux de Meta, pour Facebook et Instagram (p. ex. « _fbp »)', 'holder' => 'Meta Platforms Ireland Limited (États-Unis, dans le cadre du EU-US Data Privacy Framework)', 'purpose' => 'Savoir quelles annonces amènent des achats : il reçoit l’achat avec un identifiant et vos coordonnées uniquement sous forme d’empreinte irréversible (hachage).', 'duration' => 'Jusqu’à 90 jours', 'when' => 'Seulement si vous autorisez « Publicité »'],
        'tiktok' => ['name' => 'Ceux de TikTok (p. ex. « _ttp »)', 'holder' => 'TikTok Technology Limited (hors EEE, sous clauses contractuelles types)', 'purpose' => 'Savoir quelles annonces amènent des achats : il reçoit l’achat avec un identifiant et vos coordonnées uniquement sous forme d’empreinte irréversible (hachage).', 'duration' => 'Jusqu’à 13 mois', 'when' => 'Seulement si vous autorisez « Publicité »'],
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
