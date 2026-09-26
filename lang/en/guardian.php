<?php

/*
 * Phase 6 · the AUTHORISATION for a minor invited to a booking (`docs/specs/waiver-por-reserva.md`, batch T2).
 * Read by a STRANGER: a parent without an account, from a link the person who booked passed on. Three
 * languages, never in `admin.*`. Since T4 of `specs/fiesta-sistema-nuevo.md` the page is dressed by the new
 * system (`fiesta.php`): only what the controller and the domain still read lives here.
 */
return [
    'booking' => [
        'no_date' => 'No date assigned yet',
        'responsible' => 'Going with',
    ],

    'minor' => [
        'born_on_help' => 'We use it to know their age on the day of the visit.',
        'from_invitation' => 'On the invitation you wrote “:name”. Split it here: first name in one box, surname in the other.',
        'pick' => 'Choose your child',
        'pick_manual' => 'Type the details by hand',
        'pick_help' => 'These are the minors declared in your account. Picking one fills in their details; you can correct them.',
    ],

    'guardian' => [
        'relationship_placeholder' => 'Choose one',
    ],

    'relationships' => [
        'father' => 'Father',
        'mother' => 'Mother',
        'legal_guardian' => 'Legal guardian',
        'grandparent' => 'Grandparent',
        'other' => 'Other',
    ],

    'waiver' => [
        'version' => 'Version :version, published on :date',
    ],

    'antibot_label' => 'Security check · Cloudflare',

    'done' => [
        'already_title' => 'Nothing else to do',
        'stale_title' => 'Please read it again',
        'refused_title' => 'Nothing was registered',
        'already' => ':name already has an authorisation signed for this booking. Nothing else to do.',
        'already_generic' => 'That minor already has an authorisation signed for this booking.',
        'stale' => 'The waiver text was updated while you were filling this in. Please read it again and accept it.',
        'antibot' => 'We could not verify that you are not a robot, so NOTHING was registered. Please try again; if it keeps failing, let the person who made the booking know.',
    ],

    'blocked' => [
        'heading' => 'This form is not open',
        'not_paid' => 'This booking is not confirmed yet. Please talk to the person who made it.',
        'closed' => 'The visit for this booking has already taken place, so this form is closed.',
        'full' => 'This booking cannot take more authorisations: there are already as many signed as there are places bought. If one is missing, please talk to the person who made the booking.',
    ],

    'errors' => [
        'born_on_future' => 'The date of birth has to be before today.',
        'born_on_adult' => 'That date says this person is already an adult, and adults sign for themselves: this authorisation is only for minors.',
    ],

    'notice' => 'You are declaring these details yourself and we do not check them against any document. They are kept as proof of this authorisation.',
    'privacy_link' => 'Read the privacy policy',
];
