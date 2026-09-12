<?php

return [
    'nav' => [
        'menu_group' => ['section' => 'Sur cette page', 'page' => 'Pages'],
        'pages' => [
            'pricing' => 'Tarifs', 'events' => 'Anniversaires', 'attractions' => 'Attractions',
            'bar' => 'Le bar',
            'rules' => 'Règles', 'services' => 'Services', 'contact' => 'Contact',
        ],
        'zones' => 'Zones', 'rides' => 'Attractions', 'pricing' => 'Tarifs',
        'events' => 'Anniversaires', 'info' => 'Nous visiter', 'reserve' => "S'inscrire",
        'reserve_tickets_aria' => 'Réserver billets et anniversaires',
        'register' => "Inscription d'accès",
        'register_subtitle' => 'Pour entrer dans le parc',
        'register_info' => "Pour entrer dans le parc, tu dois compléter l'inscription d'accès. Fais-le maintenant pour accélérer ton arrivée.",
        'cta_buy' => 'Réserver',
        'cta_buy_from' => 'dès :amount',
        'cta_book' => 'Réserver',
        'tickets' => 'Billets',
        'cta_switch_buy' => 'Passer à la réservation',
        'cta_switch_signup' => "Passer à l'inscription",
        'cta_switch_signup_sub' => 'ou se connecter',
        'cta_switch_account' => 'Passer à mon compte',
        'cta_account_sub' => 'vos réservations et vos données',
        'menu_label' => 'Menu principal',
        'menu_open' => 'Ouvrir le menu',
        'menu_close' => 'Fermer le menu',
        // Rótulo VISIBLE del botón de menú (`#217`, como el mockup del 2.º cliente). NO es el
        // nombre accesible —ése lo dan `menu_open`/`menu_close`, que dicen la ACCIÓN—: éste es
        // una palabra corta al lado del dibujo, y por debajo de 620 px no se pinta.
        'burger_label' => 'Menu',
        'burger_label_open' => 'Fermer',
        'skip' => 'Aller au contenu',
        'slider_prev' => 'Précédent',
        'slider_next' => 'Suivant',
        'park_items' => [
            'info' => ['t' => 'Adresse et horaires'],
        ],
    ],
    'hero' => [
        'today' => "Murcia · Ouvert aujourd'hui",
        // Eslogan sobre el titular del hero. CORTO a propósito: el sistema del 2.º cliente
        // limita el rotulador a seis palabras («el eslogan, nada más»), y girado no se lee más.
        'kicker' => 'active ton mode amusement',
        'l1' => 'DU FUN', 'l2' => 'ON',   // el rótulo del interruptor del titular (`#254`)
        'tag' => 'Parc de sauts, trampolines, tyrolienne et bien plus en plein Murcia. Fait pour rire.',
        'cta' => 'Réserver ici', 'cta2' => 'Voir les attractions', 'reel' => 'Vidéo du parc',
        'cta_buy' => 'Réserver ici',
        'cta_buy_from' => 'dès :amount',
        // Segundo botón del hero: el contorno sobre el vídeo (`#216`). Lleva a la página de
        // precios; es el «Ver precios» del mockup del 2.º cliente.
        'cta_prices' => 'Voir les tarifs',
        'cta_buy_no_price' => 'Anniversaires en ligne',
        // Chip d'état du hero (data-driven, App\Domain\Content\Services\HeroStatus). `:duration` déjà formatée
        // («2 h» / «45 min»); `:time` = «HH:MM»; `:day` = jour de la semaine en minuscules.
        'status_open' => 'Ouvert maintenant',
        'status_opens_in' => 'On ouvre dans :duration',
        'status_opens_tomorrow' => 'On ouvre demain à :time',
        'status_opens_day' => 'On ouvre :day à :time',
        'status_link_hint' => "Voir les horaires et l'accès",
        'stats' => [
            ['num' => '7 000', 'label' => 'M² de fun'],
            ['num' => '23', 'label' => 'Attractions'],
            ['num' => '2', 'label' => 'Zones par âge'],
            ['num' => '+1M', 'label' => 'Sauts / an'],
        ],
    ],
    'zones' => [
        'axis_label' => 'taille',
        'below_is' => 'en dessous, :zone',
        'above_is' => 'au-dessus, :zone',
        'eyebrow' => 'Pour qui',
        'title' => 'Chacun a sa zone',
        'rule' => "L'âge décide. Si ça ne colle pas, c'est la taille.",
        'from' => 'à partir de',
        'see_zone' => 'Voir la zone :zone',
        'height_up_to' => "jusqu'à :h m",
        'height_from' => 'à partir de :h m',
        'height_between' => 'de :a à :b m',
        'intro' => 'On a conçu deux univers différents — un pour ceux qui sautent déjà sans freins, un pour ceux qui apprennent. Choisis le tien.',
    ],
    'rides' => [
        'eyebrow' => 'Ce qu’il y a dedans',
        'title' => 'Saute, grimpe et lâche-toi',
        'intro' => ':count attractions dedans. Trampolines, toboggans, foam et piscine à balles.',
        'door' => 'Voir les :count attractions',
        'aside_title' => 'Et moi, je fais quoi pendant ce temps ?',
        'zone_tab' => 'Zone',
        'book_zone' => 'Réserver :zone',
        'buy' => 'Acheter',
    ],
    // `/atracciones` (carril de diseño, T2d). El rótulo es la RUTA y no vive aquí: lo deriva la
    // cabecera de página de la URL real (`#525`).
    'attractions' => [
        'title' => 'Tout ce qu’il y a dedans',
        'intro' => 'Les :count attractions du parc, avec leur âge.',
        'zone_tablist' => 'Zone',
        'count_phrase' => 'attractions pour :age',
        'count_phrase_plain' => 'attractions dans :zone',
    ],
    'pricing' => [
        'title' => 'Tous les tarifs',
        'intro' => 'Ce que coûte de sauter, par zone et par durée. Sans calcul : chaque jour a son prix écrit.',
        'from' => 'dès', 'book' => 'Réserver', 'call' => 'Appeler',

        // ⚠️ Initiales de calendrier, pas celles de Carbon : en français le M se répète (mardi et
        // mercredi) par convention. Le nom complet vient de Carbon et c'est lui qui est lu à voix haute.
        'week_initials' => [1 => 'L', 2 => 'M', 3 => 'M', 4 => 'J', 5 => 'V', 6 => 'S', 0 => 'D'],
        'week_label' => 'Quel tarif s\'applique chaque jour',
        'week_normal' => 'Tarif normal',
        'week_special' => 'Tarif spécial',

        'col_range' => 'de :from à :to',
        'col_special' => 'Spécial',
        'table_label' => 'Tarifs :zone',
        'not_sold' => 'Pas vendu ce jour-là',
        'with_entry' => 'avec :entries',

        'special_title' => 'Ce qu\'est le tarif spécial',
        'special_text' => 'Les jours de tarif spécial sont : :label.',
        'special_plain' => 'Les autres jours, :days, c\'est le tarif normal.',
        'special_calm' => 'Rien à calculer : en choisissant le jour, le prix que tu vois est le tien.',

        'holidays_title' => 'Les jours fériés',

        'addons_title' => 'Ce qui s\'ajoute',
        'addons_lede' => 'À acheter en réservant ou au parc.',

    ],

    /* Section 02 de la page d'accueil. Voir le fichier espagnol pour le motif du bloc à part. */
    'rates' => [
        'eyebrow' => 'Combien',
        'title' => 'Une heure, deux ou la journée',
        'intro' => 'Tu choisis la zone et la durée. Dès :from.',
        'intro_plain' => 'Tu choisis la zone et la durée.',
        'pick_zone' => 'Choisis ta zone',
        'days_range' => 'du :from au :to',
        'days_list' => ':days',
        'days_only' => ':days uniquement',
        'special_suffix' => 'en tarif spécial',
        'special_note' => 'Tarif spécial : :label.',
        'book_name' => 'Réserver :name',
        'zone_chip' => 'Zone :zone',
        'saving' => 'Tu économises',
        'saving_base' => 'face à :count de :unit',
        'times' => [2 => 'deux', 3 => 'trois', 4 => 'quatre', 5 => 'cinq', 6 => 'six'],
        'addons_title' => 'Compléments disponibles',
        'addons_intro' => 'Tu peux les ajouter à n\'importe quel billet.',
        'from' => 'dès',
        'addon_per_guest' => 'par invité',
        'addon_each' => 'l\'unité',
        'from' => 'dès',
    ],
    'registration' => [
        'title' => 'Finalise ton inscription à la maison',
        'copy' => "Scanne le QR ou appuie sur le bouton et termine ton inscription avant d'arriver au parc.",
        'copy2' => "Ainsi, à l'entrée tu n'as plus qu'à acheter ton billet et tu accèdes plus vite, sans file pour t'inscrire.",
        'cta' => "Finaliser l'inscription",
        'qr_aria' => 'Code QR pour finaliser ton inscription',
    ],
    'addons' => [
        'label' => 'Compléments disponibles',
    ],
    'events' => [
        // La sección 04 rehecha desde el canvas (`#483`). `/cumpleanos` tiene su grupo, `birthday`.
        'section_title' => 'L’anniversaire, réglé',
        'section_intro' => 'Deux heures, le repas des enfants et les chaussettes. Dès :from par enfant.',
        'section_intro_plain' => 'Deux heures, le repas des enfants et les chaussettes.',
        'age_between' => 'De :a à :b ans',
        'age_from' => 'Dès :a ans',
        'age_up_to' => 'Jusqu’à :b ans',
        'per_child' => 'par enfant',
        'special_suffix' => 'en tarif spécial',
        'see_pack' => 'Voir cet anniversaire',
        'clock_title' => 'Les :duration, à votre rythme',
        'clock_a' => 'Sauts', 'clock_b' => 'Goûter', 'clock_c' => 'Gâteau',
        'clock_rule' => 'Tout y tient et sans horaire : s’ils goûtent vite, ils sautent plus. L’ordre, c’est vous.',
        'clock_monitor' => 'Un animateur avec eux du début à la fin. Vous, assis.',
        'addons_title' => 'Ta fête, à ta manière',
        'addons_intro' => 'Ajoute ce que tu veux : rien de tout ça n’est nécessaire pour réserver.',
        'eyebrow' => 'Anniversaires',
        'reserve_terms' => 'De :min à :max enfants · Acompte de :deposit € pour réserver',
        'coming_soon' => 'Nous préparons les packs d’anniversaire. Si tu veux réserver plus tôt, écris-nous et nous t’aiderons.',
        'coming_soon_cta' => 'Nous contacter',
    ],
    // `/cumpleanos` (`#528`). Los plurales por `trans_choice`: los packs los pone el panel.
    'birthday' => [
        'title' => 'L’anniversaire, en détail',
        'lede' => 'Ce que comprend chaque pack, ce qu’il coûte et ce qui se décide après la réservation.',
        'packs_title' => '{1} Le pack|{2} Les deux packs|[3,*] Les :count packs',
        'count_question' => 'Combien d’enfants viennent ?',
        'count_less' => 'Un enfant de moins',
        'count_more' => 'Un enfant de plus',
        'table_label' => 'Comparatif des packs',
        'row_each' => 'Par enfant',
        'row_each_special' => 'En tarif spécial',
        'row_age' => 'Âge',
        'row_kids' => 'Enfants',
        'row_duration' => 'Durée',
        'row_deposit' => 'Acompte',
        'row_features' => 'Comprend',
        'row_extend' => 'Prolonger la fête',
        'row_total' => 'Total',
        'row_total_special' => 'Total en tarif spécial',
        'kids' => 'De :min à :max enfants',
        'kids_from' => 'Dès :min enfants',
        'kids_note' => 'Le minimum et le maximum pour réserver.',
        'deposit_title' => 'Acompte de :deposit pour réserver',
        'deposit_note' => 'Il est déduit du total.',
        'deposit_line' => 'L’acompte de :deposit est déduit du total ; le reste se paie le jour de la fête, au parc.',
        'book' => 'Réserver l’anniversaire',
        'menu_title' => 'Ce qu’ils mangent',
        'choice_title' => 'Au choix',
        'choice_lede' => '{1} Se choisit à la réservation.|{2} On en choisit un des deux à la réservation.|[3,*] On en choisit un des :count à la réservation.',
        'shared_title' => '{1} Ce qu’il comprend|{2} Pareil dans les deux|[3,*] Pareil dans tous',
        'shared_lede' => '{1} Tout ce que comprend le pack.|[2,*] Ce qui ne change pas d’un pack à l’autre, pour ne pas avoir à le comparer.',
        'after_title' => 'Après la réservation',
        'after_lede' => 'À la réservation, tu reçois par email le lien vers le « :form » pour nous dire qui vient. Pas besoin de tout remplir d’un coup : tu peux le modifier jusqu’au jour de la fête.',
        'after_children' => 'Pour chaque enfant',
        'after_group' => 'Pour vous',
        'after_extras' => 'Ce que vous pouvez ajouter',
        'after_paid' => 'Ce qui s’ajoute là se paie au parc, le jour de la fête.',
        'cutoff' => 'jusqu’à :time avant',
        'cutoff_start' => 'jusqu’au début de la fête',
        'mixed_title' => '{2} Et si des enfants des deux âges viennent ?|[3,*] Et si des enfants d’âges différents viennent ?',
        'mixed_text' => 'Chaque enfant paie le pack qui correspond à son âge, et la différence —dans un sens ou dans l’autre— se règle à l’accueil le jour de la fête. Cette différence n’est jamais débitée en ligne : ce qui change après la réservation se règle au parc.',
        'mixed_seal' => 'Et ce que tu as réservé ne bouge pas : chaque réservation garde les conditions du jour où tu l’as faite, même si les prix changent.',
        'info_title' => 'On personnalise chaque anniversaire',
        'info_text' => 'S’il y a une allergie, une peur ou une surprise à préparer, dis-le-nous et on s’en occupe.',
    ],
    'plan' => [
        'label' => 'Planifie ta visite', 'heading' => 'Billetterie',
        'items' => [
            ['t' => 'Billets', 's' => 'Viens directement ou appelle-nous'],
            ['t' => 'Anniversaire enfant', 's' => 'Salles privées'],
            ['t' => 'Groupes & entreprises', 's' => 'Écoles, entreprises, adultes'],
            ['t' => 'Sortie adultes', 's' => '22h00–01h00 · min. 30'],
            ['t' => 'Contact', 's' => 'Écris-nous ou appelle-nous'],
        ],
    ],
    'gallery' => [
        'title' => 'En direct',
        'intro' => 'Ce qui se passe au parc, en temps réel.',
    ],
    'info' => [
        'eyebrow' => 'Nous visiter',
        'title' => 'Où nous sommes et quand nous ouvrons',
        'closed' => 'Fermé',
        'open_generic' => 'Ouvert',
        'day_range' => ':from à :to',
        'weekdays' => [0 => 'Dimanche', 1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'],
        'address_title' => 'Adresse', 'directions' => 'Itinéraire',

        // ── 07 · NOUS VISITER ── (`#487`). La entradilla se DERIVA del horario: el porqué, en
        // `lang/es/landing.php` y en `ScheduleDisplay::weeklyLede()`.
        'lede_one' => 'Les mêmes horaires tous les jours.',
        'lede_two' => 'Deux horaires : :a et :b.',
        'lede_many' => 'Les horaires changent selon le jour.',

        'today_is' => "Aujourd'hui, :day",
        'state' => [
            'open' => 'Ouvert maintenant',
            'open_line' => "Jusqu'à :time",
            'later' => "Ouvre aujourd'hui",
            'later_line' => 'Ouvre à :opens et ferme à :closes',
            'closed_now' => 'Nous avons déjà fermé',
            'closed_today' => "Fermé aujourd'hui",
            'next_tomorrow' => 'Demain il ouvre à :time',
            'next_day' => 'Le :day il ouvre à :time',
        ],

        // Frase, no fila de datos (`#489`). No dice «jour férié»: son fechas especiales.
        'special_soon' => 'Le :date, horaires spéciaux : :detail',
        'special_soon_closed' => 'Nous fermons le :date',
        'specials_open' => 'Voir les dates spéciales',
        'specials_close' => 'Masquer les dates spéciales',

        'map_credit' => 'Carte fournie par Google',
        'open_in_maps' => 'Ouvrir dans Google Maps',
    ],
    // ⚠️ El grupo `rules` se fue entero en `#531`: su última nota —la de los calcetines— la sustituye
    // la ficha del complemento en `/precios`, con el texto que escribe el panel. Ver `lang/es`.

    // ── 05 · AVANT DE VENIR ── (`#485`). El término del descargo en francés es «décharge de
    // responsabilité», y lo vigila `WaiverWordingIsOneTermTest`.
    'before' => [
        'eyebrow' => 'Avant de venir',
        'title' => 'Ton inscription, c’est ce QR',
        'lede' => 'Tu signes la décharge de responsabilité une seule fois, sur ton téléphone. À l’entrée, tu montres juste le code.',

        'qr_aria' => 'Mon QR, exemple',
        'qr_name' => 'Mon QR',
        'qr_sample' => 'exemple',
        'qr_where' => 'Dans ton compte et dans l’e-mail de chaque réservation. Pas besoin de l’imprimer.',

        'carries_title' => 'Un seul code pour tout',
        'carries_lede' => 'Tu le montres à l’entrée et l’équipe voit tout d’un coup : ce que tu as réservé, que tu as déjà signé et qui vient avec toi. Sans chercher ton nom, sans montrer ton e-mail et sans rien remplir sur place.',
        'rows' => [
            'booking' => ['key' => 'Tes réservations', 'val' => 'Celles que tu as et celles que tu feras'],
            'waiver' => ['key' => 'Ta signature', 'val' => 'La décharge, signée une seule fois'],
            'minors' => ['key' => 'Tes enfants', 'val' => 'Ceux que tu as ajoutés à ton compte'],
        ],
        'always' => 'Un seul code, toujours le même, valable à chaque visite.',

        'socks_lead' => 'La seule chose qui ne tient pas dans le code :',
        'socks_text' => 'des chaussettes antidérapantes. Apporte les tiennes ou achète-les ici, et tu les gardes.',
        'guest_text' => 'Un enfant qui n’est pas de ta famille vient ? Tu peux envoyer un lien à ses parents pour qu’ils signent eux-mêmes, sans créer de compte.',

        'all_rules' => 'Voir toutes les règles',
        'cta' => 'Créer mon compte',
        'cta_account' => 'Voir mon QR',
    ],
    // Sección 06 · «Reseñas» (`#490`). `lede_google` nace sin consumidor: la usa la mitad `b`.
    'reviews' => [
        'eyebrow' => 'Avis',
        'title' => 'Ceux qui sont déjà venus le disent',
        'lede_own' => 'Quelques-unes des choses qu\'on nous dit en partant.',
        'lede_google' => 'Ce n\'est pas nous qui les choisissons : ce sont celles que Google met en premier.',
        'stars' => '{1} :n étoile sur 5|[2,*] :n étoiles sur 5',
        'out_of' => 'sur 5',
        'count' => '{1} :n avis|[2,*] :n avis',
        'score_aria' => ':value sur 5 sur Google',
        'read_more' => 'Voir plus',
        'read_less' => 'Voir moins',
        'see_on_google' => 'Voir sur Google',
        'prev' => 'Avis précédent',
        'next' => 'Avis suivant',
        'go' => 'Voir l\'avis :n',

        // Atribución de Google (`#494`). El enlace al perfil dice adónde lleva.
        'author_on_google' => ':name sur Google Maps',

        // ⚠️⚠️ **AQUÍ EL FRANCÉS NO ADMITE LA FORMA LITERAL, y por eso la frase es OTRA.** «Traduit
        //    de …» exige contraer con el artículo del idioma —«de l'espagnol» pero «du portugais»—,
        //    y el idioma de origen lo pone Google en tiempo de ejecución: con una sola plantilla
        //    saldría mal la mitad de las veces («traduit de l'portugais»). Ninguna variable de
        //    Laravel puede decidir esa contracción.
        // ▶ «original en :lang» no la necesita y vale para todos los idiomas: «original en
        //    espagnol», «original en portugais». Es la traducción bien hecha, no una desviación —
        //    cada lengua usa su forma natural, y en ES/EN la literal ya lo es.
        'translated_from' => 'Traduit · original en :lang',
        'translated' => 'Traduit automatiquement',
        'see_original' => 'Voir l\'original',
        'see_translation' => 'Voir la traduction',

        // La frase es la de Google, traducida (ver la nota en `lang/en`).
        'google_policy' => 'Google ne vérifie pas les avis, mais supprime les faux contenus lorsqu\'il les détecte.',
    ],

    // Sección 08 · «Dudas» (`#488`). El titular es una frase; «Questions» pasa a ser el RÓTULO.
    'faq' => [
        'eyebrow' => 'Questions',
        'title' => 'Ce qu’on nous demande le plus',
        'lede' => 'Celles qui arrivent par téléphone, répondues ici.',
    ],
    // **«Salta la ciudad»**, el minijuego del hero del cierre (`#231`). Rótulos cortos: viven
    // dentro de una tarjeta que ya está llena, y el juego se explica solo al primer toque.
    // **«Salta la ciudad»**, el minijuego del hero del cierre (`#231`). Rótulos cortos: viven
    // dentro de una tarjeta que ya está llena, y el juego se explica solo al primer toque.
    // ⚠️ Las claves `*_touch` NO son un lujo: el mockup cambia el texto según el puntero, y
    // «Espacio para saltar» en un móvil es una instrucción que no se puede seguir.
    'game' => [
        'play' => 'Espace pour sauter le château',
        'play_touch' => 'Touche pour sauter le château',
        'rec' => 'rec :m m',
        'm' => 'm',
        'again' => 'Encore · espace',
        'again_touch' => 'Encore',
        'book' => 'Réserver',
        'over' => 'Terminé',
        'newrec' => 'nouveau record !',
        'bands' => 'bracelets',
        'record' => 'record :m m',
        'aria' => 'Saute la ville : mini-jeu. Appuie pour commencer et pour sauter.',
        'hint' => 'maintiens espace · éch quitte',
        'hint_touch' => 'maintiens pour sauter plus haut',
    ],
    'reserve' => [
        'title' => 'ON VA', 'stroke' => 'Y', 'fill' => 'ALLER',
        'copy' => 'Réserve ton anniversaire en ligne en une minute, avec confirmation immédiate. Tu viens juste sauter ? Viens directement ou appelle-nous.',
        'cta' => 'Réserver ici', 'cta2' => 'Appeler',
    ],
    'footer' => [
        'tag' => 'Parc de sauts pour toute la famille · Murcia',
        'col_park' => 'Le parc', 'col_info' => 'Infos', 'col_contact' => 'Contact',
        'links_park' => ['Zone Jump', 'Zone Kids', 'Attractions'],
        'links_info' => ['Tarifs', 'Anniversaires', 'Groupes & entreprises', 'Règles'],
        'rights' => 'Fait pour rire.',
        'legal' => ['Mentions légales', 'Confidentialité', 'Conditions', 'Cookies', 'Décharge de responsabilité'],
        'contact_link' => 'Contact',
        'account_link' => 'Mon compte',
        'register_link' => "Inscription d'accès",
        'language' => 'Langue',
    ],
];
