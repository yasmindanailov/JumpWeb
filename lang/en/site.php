<?php

return [
    'legal_eyebrow' => 'Legal information',
    'rules_eyebrow' => 'Rules',
    'rules_title' => 'Rules',
    'rules_headline' => 'What you have to follow',
    'rules_intro' => 'There are few of them and they all have a reason. Read them once and that is that.',

    'rules_moment' => [
        'before' => ['label' => 'Before you come', 'title' => 'At home'],
        'gate' => ['label' => 'At the door', 'title' => 'When you arrive'],
        'inside' => ['label' => 'Inside', 'title' => 'While you jump'],
    ],
    'rules_other' => 'Also',

    'rules_axis_title' => 'Height at a glance',
    'rules_axis_label' => 'height',

    'rules_waiver_title' => 'The text you sign',
    'rules_waiver_text' => 'When you register you approve these rules and the liability waiver: the document where you acknowledge that jumping carries risk and that you will follow instructions. You sign it once, for you and for your children.',
    'rules_waiver_cta' => 'Read the full waiver',

    'rules_staff' => 'Any questions about rates, birthdays or promotions, ask the park staff: that is what they are there for.',
    'rules_updated' => 'Updated in :fecha',
    'back_home' => '← Back to home',
    'legal_draft_notice' => 'Draft text, pending legal review.',

    // ══ /contacto (`DECISIONES #535`) ═══════════════════════════════════════════════════════
    'contact_eyebrow' => 'Contact',
    'contact_title' => 'Let\'s talk',
    'contact_intro' => 'Tell us what you need: we reply during our opening hours.',
    'contact_intro_plain' => 'Tell us what you need and we\'ll get back to you as soon as possible.',
    'contact_name' => 'Name',
    'contact_email' => 'Email',
    'contact_phone' => 'Phone',
    'contact_phone_hint' => 'if you would rather we called you',
    'contact_message' => 'Your message',
    'contact_send' => 'Send',
    'contact_success' => 'Thank you! We have received your message and will reply soon.',
    'contact_hp' => 'Do not fill in this field',
    'contact_form_title' => 'Write to us',
    'contact_form_lede' => 'Tell us what you need and we\'ll reply by email.',
    'contact_reply_note' => 'We reply by email.',
    'contact_privacy_notice' => 'We use what you write only to reply to you.',
    'contact_privacy_link' => 'How we handle your data',

    'contact_topic' => 'What about?',
    'contact_topic_none' => 'Pick a topic (optional)',
    'contact_topics' => [
        'birthday' => 'A birthday party',
        'groups' => 'Groups and schools',
        'booking' => 'A booking I already have',
        'other' => 'Something else',
    ],

    'contact_channels_title' => 'Whichever you prefer',
    'contact_channel' => [
        'phone' => ['t' => 'Phone', 'd' => 'To speak to the front desk.'],
        'phone_whatsapp' => ['t' => 'Phone and WhatsApp', 'd' => 'Call or message us on the same number.'],
        'whatsapp' => ['t' => 'WhatsApp', 'd' => 'If you would rather write, it is the quickest.'],
        'email' => ['t' => 'Email', 'd' => 'For groups, invoices and anything that needs to be in writing.'],
    ],

    'contact_answers_title' => 'It may already be answered',

    'contact_where_title' => 'Where we are',
    'contact_where_cta' => 'See the map and the opening hours',

    // ══ /bar (`DECISIONES #536`) ══════════════════════════════════════════════════════════════
    'bar_menu_title' => 'The menu',
    'bar_menu_zoom' => 'View full size',
    'bar_menu_hint' => 'Tap the menu to see it full size.',
    'bar_counter_title' => 'Order at the bar',
    'bar_counter_text' => 'No need to book a table or order online: sit wherever you like and order at the bar. You pay right there.',
    'bar_allergens' => 'If you have an allergy or intolerance, ask at the bar before ordering: we will tell you the allergens in any dish.',
    'bar_free_entry_yes' => 'You can come just for the bar, without buying a park ticket.',
    'bar_free_entry_no' => 'You need a park ticket to come into the bar.',
    'bar_party_line' => 'If you are here for a birthday, the children\'s food is included in the pack and is served at these tables.',
    'bar_party_cta' => 'See the birthday packs',

    'visit_hours' => 'Hours & location',
    'visit_hours_sub' => 'When and where to find us',

    'e404_eyebrow' => 'You bounced off the trampoline',
    'e404_title' => 'Page not found',
    'e404_body' => "The page you're looking for doesn't exist or has moved. Head back home, book your jump, or hop to one of these sections.",
    'e404_home' => 'Back home',
    'e404_book' => 'Book here',
    'e404_popular' => 'Were you looking for one of these?',

    // Maintenance (#218). Site-wide 503 page + staff bypass banner.
    'maintenance' => [
        'eyebrow' => 'Maintenance',
        'title' => "We'll be right back",
        'body' => "We're making improvements to the site. Please check back shortly; if you need anything, give us a call or drop us a line.",
        'contact' => 'Need something now?',
        'preview_banner' => "You're viewing the site in MAINTENANCE MODE: only you (staff) can see it; visitors see the \u{201c}We'll be right back\u{201d} page.",
        'preview_manage' => 'Manage maintenance',
    ],

    // Per-PAGE maintenance (#218, item 1): only this section is down (nav/footer stay so you can browse on).
    'page_maintenance' => [
        'eyebrow' => 'Section unavailable',
        'title' => 'This section is under maintenance',
        'body' => "We're updating this page. Please check back shortly; meanwhile, you can keep browsing the rest of the site.",
    ],
];
