<?php

// Cookie consent banner and panel (#219). T3a of the analytics spec: four purposes — map and reviews, social,
// identified usage analytics and advertising —; our own audience measurement is exempt and explained under
// «Necessary». ⚠️ Every category in `CookieConsent::OPTIONAL` needs its `<category>_title` and `<category>_desc`.

return [
    'banner' => [
        'aria' => 'Cookie notice',
        'eyebrow' => 'Cookies',
        'title' => 'Before you jump…',
        'text' => 'We use our own cookies to make the site work and to measure the audience anonymously. Only with your permission: the Google map, usage analytics linked to your account, and advertising.',
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
        'maps_title' => 'Map (Google)',
        'maps_desc' => 'Allows the Google map to get to the park to be shown. Google may install its own cookies and process data in the US.',
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

    'inventory' => [
        'title' => 'The cookies on this site, one by one',
        'intro' => 'This list is built by the site itself from what is active right now: if something is switched on or off, the list changes with it.',
        'labels' => ['holder' => 'Who sets it', 'purpose' => 'What for', 'duration' => 'How long it lasts', 'when' => 'When'],
        'own' => 'Us (first-party cookie)',
        'category' => [
            'necessary' => 'Necessary',
            'on_request' => 'Preference (you ask for it)',
            'measurement' => 'Audience measurement (exempt)',
            'maps' => 'Map (with your permission)',
            'social' => 'Social media (with your permission)',
            'analytics' => 'Analytics (with your permission)',
            'marketing' => 'Advertising (with your permission)',
        ],
        'hours' => ':n hours since your last visit',
        'minutes' => ':n minutes since your last visit',
        'months' => ':n months',
        'session' => ['purpose' => 'Keeps your visit going: the purchase in progress and, if you sign in, your session.', 'when' => 'Always'],
        'xsrf' => ['purpose' => 'Security: checks that forms are sent by you and not by another site (CSRF protection).', 'when' => 'Always'],
        'visitor' => ['purpose' => 'Counts visits and which campaigns bring them, only as anonymous statistics for us: it is not cross-referenced with other sites nor shared.', 'when' => 'Always; not renewed on each visit'],
        'consent' => ['purpose' => 'Stores what you decided in the cookie notice, so we do not ask you again.', 'duration' => ':n months (some browsers keep it for less)', 'when' => 'When you accept, reject or configure'],
        'remember' => ['purpose' => 'Keeps you signed in on this device, so we do not ask you for a code every time.', 'duration' => ':n days since your last visit, or until you sign out', 'when' => 'Only if you tick «Keep me signed in on this device» when signing in'],
        'redsys' => ['name' => 'Those of the payment gateway', 'holder' => 'Redsys Servicios de Procesamiento, S.L., on its own website', 'purpose' => 'Processing the card payment you start.', 'duration' => 'Those set by Redsys', 'when' => 'Only when paying, on the Redsys page'],
        'turnstile' => ['name' => 'Those of the anti-bot system (Turnstile)', 'holder' => 'Cloudflare, Inc. (US, under the EU-US Data Privacy Framework)', 'purpose' => 'Telling people from bots in forms.', 'duration' => 'Temporary', 'when' => 'When you sign up'],
        'maps' => ['name' => 'Those of Google Maps', 'holder' => 'Google Ireland Limited (and Google LLC, in the US, under the EU-US Data Privacy Framework)', 'purpose' => 'Showing the map of how to get here.', 'duration' => 'Those set by Google (see its policy)', 'when' => 'Only if you allow «Map (Google)»'],
        'social' => ['name' => 'Those of the social media widget (:provider)', 'holder' => ':provider (external provider; its transfer safeguard, in its privacy policy)', 'purpose' => 'Showing our latest social media posts.', 'duration' => 'Those set by the provider', 'when' => 'Only if you allow «Social media»'],
        'posthog' => ['name' => 'Those of PostHog (e.g. «ph_…_posthog»)', 'holder' => 'PostHog Inc. (data hosted on servers in the European Union)', 'purpose' => 'Understanding how the site is used with an encrypted identifier, without your name, email or IP address.', 'duration' => 'Up to 12 months', 'when' => 'Only if you allow «Identified usage analytics»'],
        'matomo' => ['name' => 'Those of Matomo (e.g. «_pk_id» and «_pk_ses»)', 'holder' => 'Us, with Matomo installed at :host', 'purpose' => 'Understanding how the site is used with an encrypted identifier, without your name, email or IP address.', 'duration' => 'Up to 13 months («_pk_ses», 30 minutes)', 'when' => 'Only if you allow «Identified usage analytics»'],
        'google_ads' => ['name' => 'Those of Google Ads (e.g. «_gcl_au»)', 'holder' => 'Google Ireland Limited (US, under the EU-US Data Privacy Framework)', 'purpose' => 'Knowing which ads bring purchases: it receives the purchase with an identifier and your contact details only as an irreversible fingerprint (hash).', 'duration' => 'Up to 90 days', 'when' => 'Only if you allow «Advertising»'],
        'meta' => ['name' => 'Those of Meta, for Facebook and Instagram (e.g. «_fbp»)', 'holder' => 'Meta Platforms Ireland Limited (US, under the EU-US Data Privacy Framework)', 'purpose' => 'Knowing which ads bring purchases: it receives the purchase with an identifier and your contact details only as an irreversible fingerprint (hash).', 'duration' => 'Up to 90 days', 'when' => 'Only if you allow «Advertising»'],
        'tiktok' => ['name' => 'Those of TikTok (e.g. «_ttp»)', 'holder' => 'TikTok Technology Limited (outside the EEA, under standard contractual clauses)', 'purpose' => 'Knowing which ads bring purchases: it receives the purchase with an identifier and your contact details only as an irreversible fingerprint (hash).', 'duration' => 'Up to 13 months', 'when' => 'Only if you allow «Advertising»'],
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
