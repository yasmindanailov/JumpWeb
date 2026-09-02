<?php

/*
 * Phase 3 · step 0 — human-readable messages for the `/api/v1` error envelope (spec §4.3).
 *
 * The KEYS here are not the public contract: the contract is the envelope's `code`
 * (`App\Http\Api\ApiErrorCode`). The indirection exists so these keys can be reorganised without
 * breaking any client. The text is the client's last resort: shown as-is when it has nothing
 * better to do with the `code`.
 */

return [
    'errors' => [
        'unauthenticated' => 'You need to sign in to continue.',
        'invalid_credentials' => 'The email or password is not correct.',
        'unauthorized' => "You don't have permission to do this.",
        'not_found' => "We couldn't find what you're looking for.",
        'method_not_allowed' => 'That operation is not available at this address.',
        'session_expired' => 'Your session has expired. Please sign in again.',
        'validation_failed' => 'Please check the details you sent.',
        'too_many_requests' => 'Too many requests in a row. Please try again in a moment.',
        'maintenance' => "We're carrying out maintenance. Please try again in a few minutes.",
        'bad_request' => "We couldn't make sense of the request.",
        'server_error' => 'Something went wrong. Please try again.',

        // ── Negocio (Fase 3 · paso 4c) ───────────────────────────────────────────────────
        'cart_empty' => 'Your basket is empty.',
        'cart_too_large' => 'Your basket has too many lines. Remove one to continue.',
        'product_unavailable' => 'That product is not available.',
        'line_unavailable' => 'One of the lines in your basket is no longer available.',
        'line_past_date' => 'That date has already passed.',
        'line_too_late' => 'That time has already passed. Please choose another.',
        'line_too_soon' => 'That time is too soon to book. Please choose another.',
        'line_outside_window' => 'That time falls outside the available hours.',
        'line_sold_out' => 'There are no places left for that time.',
        'line_pack_sold_out' => 'There is no room left for that booking at that time.',
        'line_pack_guests_range' => 'That number of guests is not valid for this service.',
        'line_event_required' => 'Some required booking details are missing.',
        'reservations_paused' => 'Online booking is temporarily closed.',
        'too_many_pending_orders' => 'You already have several bookings awaiting payment. Complete them or wait until they expire.',
        'order_not_retryable' => 'That booking can no longer be paid.',
        'payment_unavailable' => 'We could not open the payment gateway. Please try again.',
        'guest_form_closed' => 'That booking has already taken place: its details can be viewed but no longer edited.',
        'waiver_not_internal' => 'The waiver is not signed on this website.',
        'waiver_document_stale' => 'The waiver text has changed. Please read it again and accept it once more.',
        'waiver_email_unverified' => 'Verify your email address before signing the waiver.',
        'dependent_not_minor' => 'A dependent must be under 18.',
        'dependents_limit_reached' => 'Your account has reached its maximum number of dependents (:max).',
        // T5 · D8: an account with an upcoming reservation cannot be deleted (`cumple-mixto.md` §25.4).
        'account_has_upcoming_reservations' => 'We cannot delete your account yet: you have upcoming reservations. You can delete it once they have taken place or if they are cancelled.',
    ],

    // Per-field notices for assigning tickets to dependents (`POST /orders`, Phase 6 · batch 4).
    'dependents' => [
        'not_yours' => 'That dependent is not on your account.',
        'not_minor_on_date' => 'They will already be 18 on that day: buy their ticket as an adult.',
        'waiver_unsigned' => 'Their waiver is not signed yet: sign it under “Dependents” before assigning them a ticket.',
        'too_many' => 'You have picked more dependents than tickets.',
        'entries_only' => 'Dependents can only be assigned to tickets: a party pack already asks for its guests.',
    ],

    'register' => [
        'waiver_stale' => 'The waiver text has changed. Please read it again and accept it once more.',
        'waiver_not_internal' => 'The waiver is not signed on this website.',
        'waiver_document_required' => 'To accept the waiver you must indicate which text you have read.',
        'waiver_required' => 'To create the account you must read and accept the liability waiver.',
        'online_sales_disabled' => 'Online booking is not available yet. Call us or come to the park to book.',
    ],

    'google' => [
        'refused' => 'We could not finish signing you up with that Google account. Try again, or sign up with your email and a password.',
    ],
];
