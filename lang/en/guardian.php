<?php

/*
 * Phase 6 · the AUTHORISATION for a minor invited to a booking
 * (`docs/specs/waiver-por-reserva.md`, batch T2). Read by a STRANGER: a parent without an account,
 * from a link the person who booked passed on. Three languages, never in `admin.*`.
 */
return [
    'title' => 'Authorisation for minors',
    'stub' => [
        'badge' => 'Entry authorization',
        'heading' => 'Authorize your child',
        'lede' => 'Someone has booked a visit to the park and your child is coming with the group. For them to come in we need your written authorization. It takes two minutes and no account is needed.',
    ],

    'booking' => [
        'heading' => 'The booking',
        'reference' => 'Reference',
        'date' => 'Day of the visit',
        'dates' => 'Days of the visit',
        'no_date' => 'No date assigned yet',
        'responsible' => 'Going with',
    ],

    'minor' => [
        'heading' => "The minor's details",
        'name' => 'First name',
        'surname' => 'Surname',
        'born_on' => 'Date of birth',
        'born_on_help' => 'We use it to know their age on the day of the visit.',
        'pick' => 'Choose your child',
        'pick_manual' => 'Type the details by hand',
        'pick_help' => 'These are the minors declared in your account. Picking one fills in their details; you can correct them.',
    ],

    'guardian' => [
        'heading' => 'Your details',
        'help' => 'As the parent or legal guardian of the minor.',
        'name' => 'First name',
        'surname' => 'Surname',
        'relationship' => 'Relationship to the minor',
        'relationship_placeholder' => 'Choose one',
        'email' => 'Email address',
        'email_help' => 'Optional. If you leave it, we send you a copy of what you sign.',
        'phone' => 'Phone',
        'phone_help' => 'Optional. So we can reach you on the day of the visit.',
    ],

    'relationships' => [
        'father' => 'Father',
        'mother' => 'Mother',
        'legal_guardian' => 'Legal guardian',
        'grandparent' => 'Grandparent',
        'other' => 'Other',
    ],

    'waiver' => [
        'heading' => 'Liability waiver',
        'accept' => 'I have read the liability waiver and accept it on behalf of the minor.',
        'version' => 'Version :version, published on :date',
    ],

    'submit' => 'Sign the authorisation',

    'done' => [
        'signed' => "Done: :name's authorisation has been registered.",
        'signed_generic' => 'Done: the authorisation has been registered.',
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
        'accept_waiver' => 'You need to accept the liability waiver before signing.',
        'born_on_future' => 'The date of birth has to be before today.',
        'born_on_adult' => 'That date says this person is already an adult, and adults sign for themselves: this authorisation is only for minors.',
    ],

    'notice' => 'You are declaring these details yourself and we do not check them against any document. They are kept as proof of this authorisation.',
    'privacy' => 'We process these details so the minor can be admitted and as proof of your authorisation. You can exercise your rights as explained in :link.',
    'privacy_link' => 'our privacy policy',
];
