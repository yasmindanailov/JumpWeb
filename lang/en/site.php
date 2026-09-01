<?php

return [
    'legal_eyebrow' => 'Legal information',
    'rules_eyebrow' => 'Rules',
    'rules_title' => 'Rules',
    'back_home' => '← Back to home',
    'legal_draft_notice' => 'Draft text, pending legal review.',

    'contact_eyebrow' => 'Contact',
    'contact_title' => 'Let\'s talk',
    'contact_intro' => 'Questions, group visits or planning an event? Write to us and we\'ll get back to you as soon as possible.',
    'contact_name' => 'Name',
    'contact_email' => 'Email',
    'contact_phone' => 'Phone (optional)',
    'contact_message' => 'Message',
    'contact_send' => 'Send message',
    'contact_success' => 'Thank you! We have received your message and will reply soon.',
    'contact_hp' => 'Do not fill in this field',

    'contact_quick_title' => 'Or reach us directly',
    'contact_call' => 'Call',
    'contact_whatsapp' => 'WhatsApp',
    'contact_location' => 'Directions',

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
