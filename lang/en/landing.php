<?php

return [
    'nav' => [
        'menu_group' => ['section' => 'On this page', 'page' => 'Other pages'],
        'zones' => 'Zones', 'rides' => 'Rides', 'pricing' => 'Pricing',
        'events' => 'Birthdays', 'info' => 'Visit', 'reserve' => 'Sign up',
        'reserve_tickets_aria' => 'Book tickets and birthdays',
        'register' => 'Access registration',
        'register_subtitle' => 'To enter the park',
        'register_info' => 'To enter the park you need to complete the access registration. Do it now to speed up your arrival.',
        'cta_buy' => 'Book',
        'cta_buy_from' => 'from :amount',
        'cta_book' => 'Book',
        'park' => 'The park',
        'services' => 'Services',
        'tickets' => 'Tickets',
        'cta_switch_buy' => 'Switch to booking tickets',
        'cta_switch_signup' => 'Switch to sign up',
        'cta_switch_signup_sub' => 'or log in',
        'cta_switch_account' => 'Switch to my account',
        'cta_account_sub' => 'your bookings and details',
        'menu_label' => 'Main menu',
        'menu_open' => 'Open menu',
        'menu_close' => 'Close menu',
        // Rótulo VISIBLE del botón de menú (`#217`, como el mockup del 2.º cliente). NO es el
        // nombre accesible —ése lo dan `menu_open`/`menu_close`, que dicen la ACCIÓN—: éste es
        // una palabra corta al lado del dibujo, y por debajo de 620 px no se pinta.
        'burger_label' => 'Menu',
        'burger_label_open' => 'Close',
        'skip' => 'Skip to content',
        'slider_prev' => 'Previous',
        'slider_next' => 'Next',
        'park_items' => [
            'rides' => ['t' => 'Rides', 's' => 'Trampolines, foam pit, zipline'],
            'info' => ['t' => 'Location & hours', 's' => 'Murcia · how to get there'],
        ],
        'services_items' => [
            'birthdays' => ['t' => 'Birthdays', 's' => 'Per-kid packs · private room'],
            'school' => ['t' => 'School trips', 's' => '30-minute session'],
            'team_building' => ['t' => 'Companies', 's' => 'From 30 people'],
            'adults' => ['t' => 'Adults outing', 's' => '22:00–01:00 · min. 30'],
            'events' => ['t' => 'Other events', 's' => 'Hen, stag, shoots, parties'],
        ],
    ],
    'hero' => [
        'today' => 'Murcia · Open today',
        // Eslogan sobre el titular del hero. CORTO a propósito: el sistema del 2.º cliente
        // limita el rotulador a seis palabras («el eslogan, nada más»), y girado no se lee más.
        'kicker' => 'switch on your fun mode',
        'l1' => 'FUN', 'l2' => 'ON',   // el rótulo del interruptor del titular (`#254`)
        'tag' => 'Indoor jump park — trampolines, ziplines and a lot more, in the heart of Murcia. Built for laughter.',
        'cta' => 'Book here', 'cta2' => 'See the rides', 'reel' => 'Park reel',
        'cta_buy' => 'Book here',
        'cta_buy_from' => 'from :amount',
        // Segundo botón del hero: el contorno sobre el vídeo (`#216`). Lleva a la página de
        // precios; es el «Ver precios» del mockup del 2.º cliente.
        'cta_prices' => 'See prices',
        'cta_buy_no_price' => 'Birthdays online',
        // Hero status chip (data-driven, App\Domain\Content\Services\HeroStatus). `:duration` already formatted
        // («2 h» / «45 min»); `:time` = «HH:MM»; `:day` = lowercased weekday.
        'status_open' => 'Open now',
        'status_opens_in' => 'We open in :duration',
        'status_opens_tomorrow' => 'We open tomorrow at :time',
        'status_opens_day' => 'We open on :day at :time',
        'status_link_hint' => 'See opening hours & how to get here',
        'stats' => [
            ['num' => '7,000', 'label' => 'Sqm of fun'],
            ['num' => '23', 'label' => 'Rides'],
            ['num' => '2', 'label' => 'Zones by age'],
            ['num' => '+1M', 'label' => 'Jumps / year'],
        ],
    ],
    'zones' => [
        'axis_label' => 'height',
        'below_is' => 'below, :zone',
        'above_is' => 'above, :zone',
        'eyebrow' => 'Who it is for',
        'title' => 'Everyone has their zone',
        'rule' => 'Age decides. If it does not fit, height decides.',
        'from' => 'from',
        'see_zone' => 'See the :zone zone',
        'height_up_to' => 'up to :h m',
        'height_from' => 'from :h m',
        'height_between' => 'from :a to :b m',
        'intro' => 'We built two different worlds — one for the kids who fly without brakes, one for those just learning to jump. Pick yours.',
    ],
    'rides' => [
        'eyebrow' => 'What’s inside',
        'title' => 'Jump, climb and let go',
        'intro' => ':count rides inside. Trampolines, slides, foam and a ball pit.',
        'door' => 'See all :count rides',
        'zone_tab' => 'Zone',
        'book_zone' => 'Book :zone',
        'buy' => 'Buy',
    ],
    // `/atracciones` (carril de diseño, T2d). El rótulo es la RUTA y no se traduce: es la URL.
    'attractions' => [
        'eyebrow' => '/atracciones',
        'title' => 'Everything inside',
        'intro' => 'All :count rides in the park, with their age.',
        'zone_tablist' => 'Zone',
        'count_phrase' => 'rides for :age',
        'count_phrase_plain' => 'rides in :zone',
        'see_zones' => 'See the zones',
    ],
    'pricing' => [
        'title' => 'Pricing',
        'intro' => 'Pick your zone and see the prices. For now, tickets are bought at the box office or by phone.',
        'from' => 'from', 'pick_zone' => 'Choose a zone', 'tab' => 'Tickets',
        'book' => 'Book', 'call' => 'Call',
    ],

    /* Section 02 of the home page. See the Spanish file for why this is its own block. */
    'rates' => [
        'eyebrow' => 'How much',
        'title' => 'An hour, two or all day',
        'intro' => 'Pick your zone and how long. From :from.',
        'intro_plain' => 'Pick your zone and how long.',
        'pick_zone' => 'Choose a zone',
        'days_range' => ':from to :to',
        'days_list' => ':days',
        'days_only' => ':days only',
        'special_suffix' => 'on the special rate',
        'special_note' => 'Special rate: :label.',
        'book_name' => 'Book :name',
        'zone_chip' => ':zone zone',
        'saving' => 'You save',
        'saving_base' => 'versus :count of :unit',
        'times' => [2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six'],
        'addons_title' => 'Available add-ons',
        'addons_intro' => 'You can add them to any ticket.',
        'from' => 'from',
        'addon_per_guest' => 'per guest',
        'addon_each' => 'each',
        'from' => 'from',
    ],
    'registration' => [
        'title' => 'Complete your registration at home',
        'copy' => 'Scan the QR or tap the button and finish registering before you reach the park.',
        'copy2' => 'That way, on arrival you just buy your ticket and get in faster, with no sign-up queues.',
        'cta' => 'Complete registration',
        'qr_aria' => 'QR code to complete your registration',
    ],
    'addons' => [
        'label' => 'Available add-ons',
    ],
    'events' => [
        // La sección 04 rehecha desde el canvas (`#483`). `title` se queda con su valor viejo:
        // lo usa `/cumpleanos`, que es una PÁGINA con artboard propio (Fase 3).
        'section_title' => 'Birthdays, sorted',
        'section_intro' => 'Two hours, the kids’ food and the socks. From :from per child.',
        'section_intro_plain' => 'Two hours, the kids’ food and the socks.',
        'age_between' => 'Ages :a to :b',
        'age_from' => 'From age :a',
        'age_up_to' => 'Up to age :b',
        'per_child' => 'per child',
        'special_suffix' => 'on the special rate',
        'see_pack' => 'See this party',
        'mixed_note' => 'What if kids of both ages come? We sort it child by child at reception.',
        'clock_title' => 'The :duration, at your own pace',
        'clock_a' => 'Jumping', 'clock_b' => 'Snack', 'clock_c' => 'Cake',
        'clock_rule' => 'It all fits, with no timetable: if they eat fast, they jump longer. You decide the order.',
        'clock_monitor' => 'A host with them from start to finish. You, sitting down.',
        'addons_title' => 'Your party, your way',
        'addons_intro' => 'Add whatever you like: none of it is needed to book.',
        'eyebrow' => 'Birthdays', 'title' => 'Birthdays',
        'included' => 'Included in the pack', 'from' => 'From',
        'choose' => 'Choose your birthday',
        'reserve_terms' => 'From :min to :max kids · :deposit € deposit to book',
        'reserve_terms_rich' => 'From :min to :max kids · <strong>:deposit € deposit to book</strong>, deducted from the total.',
        'bd_photo_cap' => 'Birthdays to remember',
        'bd_sticker' => 'Happy birthday!',
        'bd_ticket_label' => 'Birthday pack',
        'bd_ticket_sub' => 'guests',
        'invite_link' => 'Would you like to create your custom invitation?',
        'process_eyebrow' => 'How to book',
        'process_title' => 'Step by step',
        'process_step' => 'Step', 'process_of' => 'of',
        'process_prev' => 'Previous step', 'process_next' => 'Next step',
        'process' => [
            's1_k' => 'Booking', 's1_t' => 'Pick date and pack', 's1_s' => 'The day, time and pack, in the online calendar.',
            's2_k' => 'Details', 's2_t' => 'Fill in the details', 's2_s' => "Your contact details and the birthday child's name.",
            's3_k' => 'Deposit', 's3_t' => 'Pay a :deposit € deposit', 's3_s' => 'It holds your date and comes off the total. The rest, on the day at the park.',
            's4_k' => 'Guests', 's4_t' => 'Complete the details', 's4_s' => 'Guests, allergies and cake — no rush, when you know.',
            's5_k' => 'Done!', 's5_t' => 'Just wait for the day!', 's5_s' => "We'll remind you by email 48 h before.",
        ],
        'invite' => [
            'eyebrow' => 'Invitation',
            'title' => 'Your invite',
            'intro' => 'Fill in the details, pick the zone colour and share the card with your guests. It updates instantly.',
            'editor_title' => 'Editor — changes save themselves',
            'color_label' => 'Colour',
            'card_eyebrow' => 'Birthday party',
            'card_msg' => 'Come jump at my party!',
            'card_ps' => 'PS: bring grip socks — the non-slip kind. Let’s jump a lot!',
            'lead' => "You're invited to my birthday!",
            'years' => 'yrs',
            'when' => 'When', 'time' => 'Time', 'where' => 'Where',
            'name_label' => 'Name', 'age_label' => 'Age', 'date_label' => 'Date', 'time_label' => 'Time',
            'name_placeholder' => "Birthday child's name",
            'name_fallback' => 'your name',
            'date_fallback' => 'pick the date',
            'sample_name' => 'Lucia',
            'download' => 'Download card',
            'share' => 'Share',
            'hint' => 'Edit the details, download the card and share it with your guests.',
            'share_text' => "You're invited to my birthday at :park! 🎉",
        ],
        'timeline_label' => 'Your afternoon, step by step',
        'timeline' => [
            ['time' => '17:00', 'label' => 'Arrival'],
            ['time' => '17:15', 'label' => 'Free jumps'],
            ['time' => '18:30', 'label' => 'Pizza & cake'],
            ['time' => '19:00', 'label' => 'Group photo'],
            ['time' => '19:15', 'label' => 'Goodbye'],
        ],
        'cta' => 'Book a birthday',
        'coming_soon' => 'We are preparing the birthday packs. If you want to book sooner, write to us and we will help you.',
        'coming_soon_cta' => 'Contact us',
    ],
    'plan' => [
        'label' => 'Plan your visit', 'heading' => 'Ticket booth',
        'items' => [
            ['t' => 'Tickets', 's' => 'Come straight in or call us'],
            ['t' => 'Kids birthday', 's' => 'Private rooms'],
            ['t' => 'Groups & companies', 's' => 'Schools, companies, adults'],
            ['t' => 'Adults outing', 's' => '22:00–01:00 · min. 30'],
            ['t' => 'Contact', 's' => 'Write or call us'],
        ],
    ],
    'gallery' => [
        'title' => 'Live',
        'intro' => "What's happening in the park, right now.",
    ],
    'info' => [
        'eyebrow' => 'Visit us',
        'title' => 'Where we are and when we open',
        'closed' => 'Closed',
        'open_generic' => 'Open',
        'day_range' => ':from to :to',
        'weekdays' => [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'],
        'address_title' => 'Location', 'directions' => 'Directions',

        // ── 07 · VISIT US ── (`#487`). La entradilla se DERIVA del horario: el porqué, en
        // `lang/es/landing.php` y en `ScheduleDisplay::weeklyLede()`.
        'lede_one' => 'The same hours every day.',
        'lede_two' => 'Two sets of hours: :a and :b.',
        'lede_many' => 'Our hours change from day to day.',

        'today_is' => 'Today, :day',
        'state' => [
            'open' => 'Open now',
            'open_line' => 'Until :time',
            'later' => 'Opens today',
            'later_line' => 'Opens at :opens and closes at :closes',
            'closed_now' => "We've closed for today",
            'closed_today' => 'Closed today',
            'next_tomorrow' => 'Tomorrow it opens at :time',
            'next_day' => 'On :day it opens at :time',
        ],

        // Frase, no fila de datos (`#489`). No dice «holiday»: son fechas especiales, no festivos.
        'special_soon' => 'Special hours on :date: :detail',
        'special_soon_closed' => 'We are closed on :date',
        'specials_open' => 'See the special dates',
        'specials_close' => 'Hide the special dates',

        'map_credit' => 'Map by Google',
        'open_in_maps' => 'Open in Google Maps',
    ],
    // ⚠️ Solo quedan las dos que lee `<x-site.socks-note>` en `/precios`: la sección de normas de la
    // portada la sustituyó la 05 «Antes de venir» (`#485`). El porqué, en `lang/es/landing.php`.
    'rules' => [
        'socks_title' => 'Non-slip socks required',
        'socks_text' => "They're a must for safe jumping. Bring your own from home or add them to your ticket.",
    ],

    // ── 05 · BEFORE YOU COME ── (`#485`). El término del descargo en inglés es «liability waiver»,
    // y lo vigila `WaiverWordingIsOneTermTest`.
    'before' => [
        'eyebrow' => 'Before you come',
        'title' => 'Your sign-up is this QR',
        'lede' => 'You sign the liability waiver once, on your phone. At the door you just show the code.',

        'qr_aria' => 'My QR, sample',
        'qr_name' => 'My QR',
        'qr_sample' => 'sample',
        'qr_where' => 'In your account and in every booking email. No need to print it.',

        'carries_title' => 'One code for everything',
        'carries_lede' => 'You show it at the door and staff see it all at once: what you booked, that you already signed, and who is coming with you. No looking up your name, no showing your email, nothing to fill in there.',
        'rows' => [
            'booking' => ['key' => 'Your bookings', 'val' => 'The ones you have and the ones you make later'],
            'waiver' => ['key' => 'Your signature', 'val' => 'The liability waiver, signed once'],
            'minors' => ['key' => 'Your children', 'val' => 'The ones you added to your account'],
        ],
        'always' => 'One code, always the same, good for every visit.',

        'socks_lead' => "The one thing the code can't carry:",
        'socks_text' => 'non-slip socks. Bring them from home or buy them here, and they are yours to keep.',
        'guest_text' => "Is a child coming who isn't family? You can send their parents a link so they sign themselves, no account needed.",

        'all_rules' => 'See all the rules',
        'cta' => 'Create my account',
        'cta_account' => 'See my QR',
    ],
    // Sección 06 · «Reseñas» (`#490`). `lede_google` nace sin consumidor: la usa la mitad `b`.
    'reviews' => [
        'eyebrow' => 'Reviews',
        'title' => 'From the people who came',
        'lede_own' => 'Some of the things people tell us on their way out.',
        'lede_google' => 'We do not pick them: these are the ones Google puts first.',
        'stars' => '{1} :n star out of 5|[2,*] :n stars out of 5',
        'out_of' => 'out of 5',
        'count' => '{1} :n review|[2,*] :n reviews',
        'score_aria' => ':value out of 5 on Google',
        'read_more' => 'See more',
        'read_less' => 'See less',
        'see_on_google' => 'See on Google',
        'prev' => 'Previous review',
        'next' => 'Next review',
        'go' => 'See review :n',

        // Atribución de Google (`#494`). El enlace al perfil dice adónde lleva.
        'author_on_google' => ':name on Google Maps',

        // Aviso de traducción, obligatorio. `translated` a secas es la salida cuando no se puede
        // nombrar el idioma de origen.
        'translated_from' => 'Translated from :lang',
        'translated' => 'Automatically translated',
        'see_original' => 'See original',
        'see_translation' => 'See translation',

        // ❗ La redacción es LA DE GOOGLE, palabra por palabra: «Reviews aren't verified by Google,
        //   but Google checks for and removes fake content when it's identified». En inglés se cita
        //   tal cual; en los otros idiomas se traduce esa misma frase.
        'google_policy' => 'Reviews aren\'t verified by Google, but Google checks for and removes fake content when it\'s identified.',
    ],

    // Sección 08 · «Dudas» (`#488`). El titular es una frase; «FAQ» pasa a ser el RÓTULO.
    'faq' => [
        'eyebrow' => 'FAQ',
        'title' => 'What people ask us most',
        'lede' => 'The ones that come in by phone, answered here.',
    ],
    // **«Salta la ciudad»**, el minijuego del hero del cierre (`#231`). Rótulos cortos: viven
    // dentro de una tarjeta que ya está llena, y el juego se explica solo al primer toque.
    // **«Salta la ciudad»**, el minijuego del hero del cierre (`#231`). Rótulos cortos: viven
    // dentro de una tarjeta que ya está llena, y el juego se explica solo al primer toque.
    // ⚠️ Las claves `*_touch` NO son un lujo: el mockup cambia el texto según el puntero, y
    // «Espacio para saltar» en un móvil es una instrucción que no se puede seguir.
    'game' => [
        'play' => 'Space to jump the castle',
        'play_touch' => 'Tap to jump the castle',
        'rec' => 'best :m m',
        'm' => 'm',
        'again' => 'Again · space',
        'again_touch' => 'Again',
        'book' => 'Book',
        'over' => 'Game over',
        'newrec' => 'new record!',
        'bands' => 'wristbands',
        'record' => 'best :m m',
        'aria' => 'Jump the city: mini-game. Press to start and to jump.',
        'hint' => 'hold space · esc exits',
        'hint_touch' => 'hold to jump higher',
    ],
    'reserve' => [
        'title' => "LET'S", 'stroke' => 'GO', 'fill' => 'JUMP',
        'copy' => 'Book your birthday online in a minute, with instant confirmation. Just coming to jump? Come straight in or call us.',
        'cta' => 'Book here', 'cta2' => 'Call',
    ],
    'footer' => [
        'tag' => 'Family jump park · Murcia',
        'col_park' => 'The park', 'col_info' => 'Info', 'col_contact' => 'Contact',
        'links_park' => ['Jump zone', 'Kids zone', 'Rides'],
        'links_info' => ['Pricing', 'Birthdays', 'Groups & companies', 'Rules'],
        'rights' => 'Built for laughter.',
        'legal' => ['Legal', 'Privacy', 'Terms', 'Cookies', 'Liability waiver'],
        'contact_link' => 'Contact',
        'account_link' => 'My account',
        'register_link' => 'Access registration',
        'language' => 'Language',
    ],
];
