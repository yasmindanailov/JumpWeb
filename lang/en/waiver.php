<?php

/*
 * Phase 6 · waiver — texts of the proof-of-acceptance PDF (`specs/waiver-probatorio.md` §4.5).
 * Read by the customer, so it lives in a namespace with the THREE customer languages.
 */
return [
    'proof' => [
        'title' => 'Waiver acceptance record',
        'published_at' => 'Version published on :date',
        'signed_text' => 'Accepted text (in full, exactly as presented)',
        'holder' => 'Signatory',
        'holder_name' => 'Name',
        'holder_email' => 'Email address',
        'subject' => 'On behalf of',
        'subject_holder' => 'The account holder themselves',
        'subject_dependent' => 'A minor in their care (no. :id)',
        'holder_note' => 'Identity details were declared by the person when creating their account and have not been verified by external means. They are copied here exactly as they were at the time of acceptance.',
        'holder_anonymised' => 'The account has been erased at its holder\'s request (GDPR art. 17). This record is retained, linked, under restricted processing (art. 17.3.e and 18).',
        'acceptance' => 'Acceptance',
        'accepted_at' => 'Date and time',
        'channel' => 'Channel',
        'channels' => [
            'web' => 'Web (the person\'s browser)',
            'api' => 'Application (API)',
            'panel' => 'Front desk (operator panel)',
        ],
        'ip' => 'IP address',
        'user_agent' => 'Browser (user-agent)',
        'presented_note' => 'The text was presented within the flow itself and the person accepted it explicitly, through a separate checkbox unticked by default. This record proves that it was presented and accepted; it does not prove that it was read.',
        'declared_title' => 'Acceptance DECLARED by the operator',
        'declared_text' => 'This acceptance was not recorded by the person from their own device: operator :operator declares that the person accepted the text in person, at the front desk. It is substantially weaker than an acceptance recorded by the person themselves.',
        'integrity' => 'Record integrity',
        'document_hash' => 'Text hash (SHA-256)',
        'signature_hash' => 'Record hash (SHA-256)',
        'prev_hash' => 'Previous record hash',
        'first_link' => 'First record for this person (no previous one)',
        'canonical' => 'Canonical schema',
        'verification' => 'Check',
        'verified_yes' => 'Verified: the text and the record match their hashes',
        'verified_no' => 'NOT VERIFIED: the content does not match its hash',
        'retention' => 'Retention',
        'retention_until' => 'This record will be kept until :date, unless a legal obligation requires otherwise.',
        'retention_none' => 'The retention period for this record has not been set yet.',
        'footer_note' => 'This document is the human-readable representation of an electronic record. Its evidential value lies in the record itself (immutable row, immutable text version and audit trail), not in this PDF, which carries no digital signature.',
    ],
];
