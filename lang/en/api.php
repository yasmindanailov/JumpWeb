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
    ],
];
