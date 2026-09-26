<?php

/*
 * The guest form of a birthday party: what the DOMAIN and the controller still say with these keys. The page is
 * dressed by the new system (`specs/fiesta-sistema-nuevo.md`, `fiesta.php`); what the old skin painted was retired
 * with it in T4 (26-09). `ClavesDeIdiomaTest` keeps dead text out.
 */
return [
    'title' => 'Booking form',
    'progress' => ':done of :total guests completed',
    'progress_complete' => 'All guests completed (:total)',
    'no_product_title' => 'An age with no product',
    'no_product_below' => 'One of the guests is younger than the lowest age band of this party, and there is no product for that age under the terms of your booking. Call us on :phone and we will sort it out together; until then that guest is not counted as completed.',
    'no_product_above' => 'One of the guests is older than the highest age band of this party, and there is no product for that age under the terms of your booking. Call us on :phone and we will sort it out together; until then that guest is not counted as completed.',
    'no_product_gap' => 'One of the guests has an age that falls between two age bands of this party, and there is no product for that age under the terms of your booking. Call us on :phone and we will sort it out together; until then that guest is not counted as completed.',
    'no_product_phone_fallback' => 'the park',
    'frozen_missing_ages' => '{1} :count age is still missing: the supplement will not be recalculated — up or down — until they are all in.|[2,*] :count ages are still missing: the supplement will not be recalculated — up or down — until they are all in.',
    'mixed_title' => 'Mixed party',
    'mixed_line' => ':count guest(s) belong to ":target" (:target_price per guest) rather than ":booked" (:booked_price).',
    'mixed_line_written' => ':count guest(s) belong to “:target”: :unit more per guest.',
    'mixed_surcharge' => 'That adds a :amount supplement, payable at the park on the day of the party.',
    'mixed_discount_total' => 'That takes :amount off. If you still have something to pay at the park, it comes off that; if you had already paid everything, it is refunded to you at the park on the day of the party.',
    'mixed_net_zero' => 'Between the supplement and the discount, what you pay at the park does not change for this reason.',
    'mixed_savings_pending' => 'That makes your party :amount cheaper: the discount will apply once every age is filled in.',
    'mixed_no_difference' => 'There is no price difference between the two: nothing extra to pay for this.',
    'readonly_notice' => 'This booking has already taken place. The form is read-only: you can review the details but no longer edit them.',
    'count_error_title' => 'The guest count hasn’t changed',
    'saved' => 'Form saved. Thank you! You can edit it again anytime.',
    'extras_closed_cutoff' => 'No longer changeable',
    'extras_closed_sold' => 'You chose this when booking — call us to change it',
    'extras_blocked' => 'Your details were saved, but one of the extras could not be changed: its deadline may have passed. Call us if you need to.',
    'extras_stale' => 'Your details were saved, but the extras were not: the booking changed while you had this page open. Reload it and check them.',

    'count_hint' => 'You can change it until :when (maximum :max).',
    'count_closed_cutoff' => 'The number of guests can no longer be changed: the deadline has passed.',
    'count_closed' => 'The number of guests can no longer be changed.',
    'count_error_above_max' => 'Your details were saved, but the number of guests was not: it is more than this party allows. Call us and we will sort it out.',
    'count_error_below_min' => 'Your details were saved, but the number of guests was not: it is below this party\'s minimum. Call us and we will sort it out.',
    'count_error_below_assigned' => 'Your details were saved, but the number of guests was not: you have already assigned more places than you want to keep. Remove someone from the list and try again.',
    'count_error_sold_out' => 'Your details were saved, but the number of guests was not: there is no longer room for that many at that time. Call us and we will sort it out.',
    'count_error_cutoff' => 'Your details were saved, but the number of guests was not: the deadline to change it has passed.',
    'count_error_closed' => 'Your details were saved, but the number of guests could not be changed. Call us and we will sort it out.',
    'count_error_stale' => 'Your details were saved, but the number of guests was not: the booking changed while you had this page open. Please reload it.',
    'count_error_unconfirmed' => 'Nothing was saved: there are more children on the list than in your booking. Confirm the number under «The final number» and save again.',
    'count_error_unsaved' => 'Nothing was saved: the list has more children than your booking and the number couldn’t go up. Call us and we’ll sort it out with you.',

    // The invitation, as seen from the host's list (T6, `specs/celebracion-e-invitacion.md` §4.7).
    'invite' => [
        'repeated' => 'This family has replied more than once. We are showing you the latest.',
        'dismissed' => 'Done: that is no longer on your list.',
        'overflow_title' => 'Some replies no longer fit',
        'overflow' => '{1} One family has said they are coming and there is no card left for their child: raise the number of guests or let them know.|[2,*] :count replies no longer fit: raise the number of guests or let those families know.',
        'theme_confeti' => 'Confetti',
        'theme_fiesta' => 'Party',
        'theme_sereno' => 'Calm',
        'rejected_title' => "We haven't saved that",
        'rejected' => 'Links and email addresses are not allowed in “Whose birthday it is” or “Invited by”: the invitation is published on our website and anyone could tap them. Remove them and save again.',

        // The reminder (T6·6, §4.7). Nothing is sent: we have no email for the parent, so this writes
        // the message and the host pastes it where they already shared the link.
        'remind_names' => '{1} Name the family we are missing|[2,*] Name the :count families we are missing',
        'remind_last' => '{1} You wrote it once, last on :when|[2,*] You wrote it :count times, last on :when',
        'reminder_text' => "We are still missing replies for :name's birthday. If you have not told us whether you are coming, it only takes a moment here:",
        'reminder_text_generic' => 'We are still missing replies for our party. If you have not told us whether you are coming, it only takes a moment here:',
        'reminder_names' => 'We are missing: :names.',
        'reminder_deadline' => 'You can reply until :when',
    ],
];
