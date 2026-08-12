<?php

// /servicios page copy (v2 «Editorial XL» design). Same logic as v1: i18n-driven page, CTA to
// /contacto, real prices [PENDING] from the client (not shown). `sections[].anchor` and
// `other.anchor` are STABLE (linked from the nav as `/servicios#xxx` and asserted by tests).
// Mirror of lang/es/services.php — keep the structure in parity.

return [
    'meta' => [
        'title' => 'Services',
        'description' => 'School trips, companies, adults sessions and private events at our park.',
    ],
    'eyebrow' => 'For groups and events',
    'title' => 'Beyond open jump',
    // Highlighted fragment of the hero title (`.blink`, mockup v2). Must be an EXACT substring
    // of `title`; if absent, the hero falls back gracefully to the plain title.
    'title_accent' => 'open jump',
    'intro' => "We adapt the park for schools, companies and groups, including outside our regular hours. Ask for no-strings info and we'll plan the day with you.",
    'cta_contact' => 'Get in touch',
    'service_label' => 'Service',
    'zone_label' => 'Zone',
    // Group rate table (informational) for a section — «School trips» case.
    'rates' => [
        'title' => 'Group rates',
        'group' => 'Group',
        'weekday' => 'Mon–Fri',
        'weekend' => 'Weekend/holiday',
        'kids' => ':count kids',
        'people' => ':count people',
        'note_kids' => 'Price per child; lower for bigger groups. Sessions outside public opening hours. Book by phone or ask us for details.',
        'note_people' => 'Price per person; lower for bigger groups. Sessions outside public opening hours. Book by phone or ask us for details.',
    ],
    // `sections` moved to the `LandingService` CMS entity (#256, model A): the /servicios blade
    // reads them from the DB (seeded in LandingContentSeeder). Only page chrome remains here.
    'other' => [
        'anchor' => 'eventos',
        'title' => 'Other events',
        'body' => "Hen/stag parties, private parties, film shoots or any other idea. Tell us what you have in mind and we'll tailor a proposal.",
    ],
];
