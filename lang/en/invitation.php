<?php

/*
 * The digital PARTY INVITATION (`docs/specs/celebracion-e-invitacion.md` §4.6, T5).
 *
 * Read by a STRANGER: the link is shared with a whole class group over a parents' chat. Whoever opens
 * this has never used this site and may not know the venue at all, so the tone explains rather than
 * assumes. Lives in the three customer-facing languages, never in `admin.*` (`DECISIONES #154`).
 */
return [
    'title' => 'An invitation',

    'badge' => 'You are invited',
    'heading' => 'Come to my birthday party!',

    'age' => 'Turning :count|Turning :count',

    'when' => 'When',
    'host' => 'Invited by',
    'where' => 'Where',
    'directions' => 'Get directions',
    'menu' => 'What we are eating',
    'menu_more' => 'See what it includes',

    'soon' => [
        'title' => 'To let them know',
        'open' => 'You will soon be able to confirm right here. In the meantime, just tell whoever invited you.',
        'closed' => 'The deadline to confirm has passed, but the party details are still here.',
    ],

    'field' => 'Child’s full name',
    'field_hint' => 'For example: Martina Serra López',
    'yes' => 'Yes, coming',
    'no' => 'We can’t make it',
    'antibot_label' => 'Security check',

    'done' => [
        'yes_title' => 'See you there!',
        'yes' => 'We have told whoever is hosting the party. See you there, :name.',
        'yes_generic' => 'We have told whoever is hosting the party. See you there!',
        'no_title' => 'Thanks for letting us know',
        'no' => 'We have told whoever is hosting the party. Maybe next time.',
        'refused_title' => 'We could not save it',
        'antibot' => 'The security check did not go through. Please try again; if it keeps failing, tell whoever invited you directly.',
    ],

    'refused' => [
        'closed' => 'This party is no longer taking replies. If you think that is a mistake, talk to whoever invited you.',
        'cutoff' => 'The deadline to confirm has passed. Tell whoever invited you directly.',
        'no_name' => 'We need the child’s name to be able to add them.',
        'too_many' => 'This invitation has had too many replies in a row. Please try again later.',
    ],

    'closed' => [
        'title' => 'The deadline to confirm has passed',
        'text' => 'The party details are still here. If you have not replied yet, talk to whoever invited you.',
    ],

    'privacy' => [
        'text' => 'What you write here goes to whoever is hosting the party, so they know who is coming, and to the venue, to get the day ready. We use it for nothing else and delete it 14 days after the party.',
        'link' => 'Privacy policy',
    ],
];
