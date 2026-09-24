<?php

// Cookie consent banner and panel (#219). T3a of the analytics spec: four purposes — map and reviews, social,
// identified usage analytics and advertising —; our own audience measurement is exempt and explained under
// «Necessary». ⚠️ Every category in `CookieConsent::OPTIONAL` needs its `<category>_title` and `<category>_desc`.

return [
    'banner' => [
        'aria' => 'Cookie notice',
        'eyebrow' => 'Cookies',
        'title' => 'Before you jump…',
        'text' => 'We use our own cookies to make the site work and to measure the audience anonymously. Only with your permission: the map and Google reviews, usage analytics linked to your account, and advertising.',
        'policy' => 'More information',
        'accept' => 'Accept',
        'reject' => 'Reject',
        'configure' => 'Configure',
        'manage_link' => 'Cookie settings',
    ],

    'panel' => [
        'necessary_title' => 'Necessary',
        'always_on' => 'Always on',
        'necessary_desc' => 'Essential for the session, form security and the shopping cart, plus our own 13-month cookie that measures the audience anonymously, without cross-referencing or sharing data. They are exempt from consent.',
        'maps_title' => 'Map and reviews (Google)',
        'maps_desc' => 'Allows the Google location map and the reviews published on Google, with their authors\' photos, to be shown. Google may install its own cookies and process data in the US.',
        'social_title' => 'Social media',
        'social_desc' => 'Allows our latest Instagram/TikTok posts to be shown through an external widget, which may install its own cookies.',
        'analytics_title' => 'Identified usage analytics',
        'analytics_desc' => 'Allows your browsing to be linked to your account when you log in or buy, to understand how you use the site, and an analytics tool to be used with an encrypted identifier instead of your name. Anonymous audience measurement does not need this permission.',
        'marketing_title' => 'Advertising',
        'marketing_desc' => 'Allows the advertising platforms\' pixels to load and purchases to be reported to them, to measure which campaigns work. Without this permission no pixel loads and nothing is reported.',
        'reject_all' => 'Reject all',
        'save' => 'Save preferences',
        'accept_all' => 'Accept all',
        'policy_link' => 'Read the cookie policy',
    ],

    'frame' => [
        'maps_text' => 'To see the map you need to load content from Google Maps, which may install cookies.',
        'maps_btn' => 'Load the map',
        'social_text' => 'To see the feed you need to load content from an external provider, which may install cookies.',
        'social_btn' => 'Load the content',
        'policy_link' => 'Cookie policy',
        'noscript' => 'With JavaScript disabled we do not load this third-party content, so as not to install cookies without your permission.',
    ],
];
