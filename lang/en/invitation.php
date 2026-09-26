<?php

/*
 * The digital PARTY INVITATION (`docs/specs/celebracion-e-invitacion.md` §4.6, T5). Read by a STRANGER: the link
 * is shared with a whole class group. Three customer-facing languages, never in `admin.*` (`DECISIONES #154`).
 * Since T2 of `specs/fiesta-sistema-nuevo.md` the page is dressed by the new system (`fiesta.php`): only what the
 * controller and the domain still read lives here.
 */
return [
    'calendar' => [
        'summary' => ':name’s birthday',
    ],
    'og' => [
        'title' => ':name turns :age',
        'title_no_age' => ':name’s birthday',
        'description' => 'At :time, at :business. Let us know if you are coming.',
        'description_no_time' => 'At :business. Let us know if you are coming.',
    ],

    'antibot_label' => 'Security check',

    'done' => [
        'yes_generic' => 'We have told whoever is hosting the party. See you there!',
        'no' => 'We have told whoever is hosting the party. Maybe next time.',
        'antibot' => 'The security check did not go through. Please try again; if it keeps failing, tell whoever invited you directly.',
    ],

    'refused' => [
        'closed' => 'This party is no longer taking replies. If you think that is a mistake, talk to whoever invited you.',
        'cutoff' => 'The deadline to confirm has passed. Tell whoever invited you directly.',
        'no_name' => 'We need the child’s name to be able to add them.',
        'too_many' => 'This invitation has had too many replies in a row. Please try again later.',
    ],

    'receipt' => [
        'save' => 'Save',
    ],
];
