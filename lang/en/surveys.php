<?php

// Surveys sent to the customer by e-mail (`docs/specs/encuestas.md` §4.3, T3): the next-day mail and the page its
// button opens. Read by a customer in THEIR language, without signing in. Service mail: nothing commercial here.
return [
    'mail' => [
        'badge' => 'Your opinion',
        'headline' => 'How was the park yesterday?',
        'subject' => 'How was the park yesterday?',
        'preheader' => 'Two minutes to help us improve. No account or password needed.',
        'line1' => 'You visited :park yesterday and we would love to know how it went. Just a few questions.',
        'line2' => 'It takes two minutes and you do not need to sign in. It is only to improve the park, nothing else.',
        'action' => 'Answer',
        'optout' => 'I do not want to receive more surveys',
    ],
    'page' => [
        'title' => 'Your opinion',
        'badge' => 'Survey',
        'intro_default' => 'A few questions about your visit. Thank you for your time.',
        'required' => 'required',
        'yes' => 'Yes',
        'no' => 'No',
        'text_placeholder' => 'Write here (optional)',
        'submit' => 'Send answers',
        'error_required' => 'Please answer this question.',
        'error_invalid' => 'That answer is not valid for this question.',
        'thanks_title' => 'Thank you!',
        'thanks' => 'Your answers help us improve :park.',
        'optout_title' => 'Stop receiving surveys',
        'optout_text' => 'If you press the button we will not send you any more survey e-mails. You can turn them on again from «My account → Privacy».',
        'optout_button' => 'No more surveys',
        'optout_done_title' => 'Done',
        'optout_done' => 'We will not send you any more surveys by e-mail.',
    ],
];
