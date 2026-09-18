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

    'calendar' => [
        'title' => 'So you do not forget',
        'add' => 'Add to calendar',
        'summary' => ':name’s birthday',
    ],
    'og' => [
        'title' => ':name turns :age',
        'title_no_age' => ':name’s birthday',
        'description' => 'At :time, at :business. Let us know if you are coming.',
        'description_no_time' => 'At :business. Let us know if you are coming.',
    ],
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

    'receipt' => [
        'cta' => 'Leave their details (2 minutes)',
        'hint' => 'This link lasts 2 hours. If you skip it, no problem: they are already on the list.',
        'title' => 'You are on the list',
        'badge' => 'Confirmed',
        'heading' => 'We are counting on :name',
        'lede' => 'If you like, tell us two more things. All optional.',
        'save' => 'Save',
        'saved_title' => 'Saved',
        'saved' => 'We will pass it on to whoever is hosting the party.',
        'closed' => 'This party no longer takes changes. What you told us before is still saved.',

        'g2_title' => 'Who is coming',
        'g2_lede' => 'None of this is required. Fill in only what you want to tell us.',
        'g2_privacy' => 'If you tell us about an allergy or another need, it will be seen by whoever is hosting the party and by the venue team, so they can take it into account that day. Nothing else.',

        'g3_title' => 'Are you staying with them?',
        'g3' => [
            'with_adult_title' => 'I am staying',
            'with_adult' => '— Nothing to sign. You stay at the venue for the party and identify yourself at the door.',
            'alone_title' => 'I am dropping them off',
            'alone' => '— Then we need your signature: two minutes, right here.',
            'unknown_title' => 'I am not sure yet',
            'unknown' => '— Sign just in case, or sort it out at the door on the day.',
        ],
        'g3_sign' => 'Sign now',
        'g3_signed_title' => 'Already signed',
        'g3_signed' => 'We have your signature for this child. There is nothing else to do.',
    ],
];
