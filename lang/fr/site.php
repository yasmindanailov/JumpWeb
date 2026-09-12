<?php

return [
    'legal_eyebrow' => 'Informations légales',
    'rules_eyebrow' => 'Règles',
    'rules_title' => 'Règles',
    'rules_headline' => 'Ce qu’il faut respecter',
    'rules_intro' => 'Elles sont peu nombreuses et ont toutes une raison. On les lit une fois et c’est tout.',

    'rules_moment' => [
        'before' => ['label' => 'Avant de venir', 'title' => 'À la maison'],
        'gate' => ['label' => 'À l’entrée', 'title' => 'En arrivant'],
        'inside' => ['label' => 'À l’intérieur', 'title' => 'Pendant que tu sautes'],
    ],
    'rules_other' => 'En plus',

    'rules_axis_title' => 'La taille, en un coup d’œil',
    'rules_axis_label' => 'taille',

    'rules_waiver_title' => 'Le texte que tu signes',
    'rules_waiver_text' => 'En t’inscrivant, tu approuves ce règlement et la décharge de responsabilité : le document où tu reconnais que sauter comporte des risques et que tu suivras les consignes. Elle se signe une seule fois, pour toi et pour tes enfants.',
    'rules_waiver_cta' => 'Lire la décharge en entier',

    'rules_staff' => 'Pour toute question sur les tarifs, les anniversaires ou les promotions, demande au personnel du parc : il est là pour ça.',
    'rules_updated' => 'Mis à jour en :fecha',
    'back_home' => '← Retour à l\'accueil',
    'legal_draft_notice' => 'Texte provisoire, en attente de révision juridique.',

    // ══ /contacto (`DECISIONES #535`) ═══════════════════════════════════════════════════════
    'contact_eyebrow' => 'Contact',
    'contact_title' => 'Parlons-en',
    'contact_intro' => 'Dis-nous ce qu’il te faut : nous répondons pendant nos horaires d’ouverture.',
    'contact_intro_plain' => 'Dis-nous ce qu’il te faut et nous te répondrons au plus vite.',
    'contact_name' => 'Nom',
    'contact_email' => 'Email',
    'contact_phone' => 'Téléphone',
    'contact_phone_hint' => 'si tu préfères qu’on t’appelle',
    'contact_message' => 'Ton message',
    'contact_send' => 'Envoyer',
    'contact_success' => 'Merci ! Nous avons bien reçu ton message et te répondrons bientôt.',
    'contact_hp' => 'Ne pas remplir ce champ',
    'contact_form_title' => 'Écris-nous',
    'contact_form_lede' => 'Dis-nous ce qu’il te faut et nous te répondrons par email.',
    'contact_reply_note' => 'Nous répondons par email.',
    'contact_privacy_notice' => 'Nous utilisons ce que tu écris uniquement pour te répondre.',
    'contact_privacy_link' => 'Comment nous traitons tes données',

    'contact_topic' => 'À quel sujet ?',
    'contact_topic_none' => 'Choisis un sujet (facultatif)',
    'contact_topics' => [
        'birthday' => 'Un anniversaire',
        'groups' => 'Groupes et écoles',
        'booking' => 'Une réservation que j’ai déjà',
        'other' => 'Autre chose',
    ],

    'contact_channels_title' => 'Comme tu préfères',
    'contact_channel' => [
        'phone' => ['t' => 'Téléphone', 'd' => 'Pour parler à l’accueil.'],
        'phone_whatsapp' => ['t' => 'Téléphone et WhatsApp', 'd' => 'Appelle-nous ou écris-nous au même numéro.'],
        'whatsapp' => ['t' => 'WhatsApp', 'd' => 'Si tu préfères écrire, c’est le plus rapide.'],
        'email' => ['t' => 'Email', 'd' => 'Pour les groupes, les factures et tout ce qui doit être écrit.'],
    ],

    'contact_answers_title' => 'C’est peut-être déjà répondu',

    'contact_where_title' => 'Où nous sommes',
    'contact_where_cta' => 'Voir le plan et les horaires',

    // ══ /bar (`DECISIONES #536`) ══════════════════════════════════════════════════════════════
    'bar_menu_title' => 'La carte',
    'bar_menu_zoom' => 'Voir en grand',
    'bar_menu_hint' => 'Touche la carte pour la voir en grand.',
    'bar_counter_title' => 'On commande au comptoir',
    'bar_counter_text' => 'Pas besoin de réserver une table ni de commander sur le site : installe-toi où tu veux et commande au comptoir. On paie sur place.',
    'bar_allergens' => 'Si tu as une allergie ou une intolérance, demande au comptoir avant de commander : on te dit les allergènes de chaque plat.',
    'bar_free_entry_yes' => 'Tu peux venir seulement au bar, sans billet pour le parc.',
    'bar_free_entry_no' => 'Il faut un billet pour le parc pour entrer au bar.',
    'bar_party_line' => 'Si vous venez pour un anniversaire, le repas des enfants est inclus dans le pack et servi à ces tables.',
    'bar_party_cta' => 'Voir les packs anniversaire',

    'visit_hours' => 'Horaires & lieu',
    'visit_hours_sub' => 'Quand et où nous trouver',

    'e404_eyebrow' => 'Tu as raté le trampoline',
    'e404_title' => 'Page introuvable',
    'e404_body' => "La page que tu cherches n'existe pas ou a été déplacée. Reviens à l'accueil, réserve ton saut ou file vers l'une de ces sections.",
    'e404_home' => "Retour à l'accueil",
    'e404_book' => 'Réserver ici',
    'e404_popular' => 'Tu cherchais l’une de celles-ci ?',

    // Maintenance (#218). Page 503 du site entier + bandeau de contournement pour le personnel.
    'maintenance' => [
        'eyebrow' => 'Maintenance',
        'title' => 'Nous revenons très vite',
        'body' => 'Nous améliorons le site. Reviens dans un instant ; si tu as besoin de quelque chose, appelle-nous ou écris-nous.',
        'contact' => "Besoin d'aide tout de suite ?",
        'preview_banner' => 'Tu vois le site en MODE MAINTENANCE : toi seul (personnel) le vois ; les visiteurs voient la page « Nous revenons très vite ».',
        'preview_manage' => 'Gérer la maintenance',
    ],

    // Maintenance PAR PAGE (#218, item 1) : seule cette section est en panne (nav/pied restent pour naviguer).
    'page_maintenance' => [
        'eyebrow' => 'Section indisponible',
        'title' => 'Cette section est en maintenance',
        'body' => 'Nous mettons cette page à jour. Reviens dans un instant ; en attendant, tu peux continuer à parcourir le reste du site.',
    ],
];
