<?php

return [
    'close' => 'Close',

    'nav' => [
        'hello' => 'Hi, :name',
        'login' => 'Log in',
        'sign_out' => 'Log out',
        'pending_form' => 'You have a pending form',
    ],

    'sidecart' => [
        // ⚠️⚠️ **Aquí vivían `next` y `no_upcoming`, y se RETIRARON el 2026-08-28**
        // (`specs/identidad-qr-puerta.md` §9.7 C·2, `DECISIONES #217`): eran la sub-línea de la
        // próxima reserva bajo el nombre, que se decía en DOS sitios —el bloque de cuenta y el índice
        // del área— y el hueco lo ocupa ahora el atajo «Mi QR». El índice sigue enseñándola.
        // ▶ Se van también del ARRANQUE, y eso vale más que el borrado: este grupo NO se poda por
        // sesión —el bloque cambia de cara sin recargar— así que viajaban en **todas** las páginas
        // públicas para no pintarse nunca. Con ellas se fue `panel.js::sublineOf()` y sus casos.
        'form_pending_one' => 'You have a pending form for :product',
        'form_pending_many' => 'You have :count pending forms',
        'guest_hello' => 'Hi, jumper!',
        'guest_sub' => 'Log in to keep your bookings.',
        'upcoming_count' => ':count upcoming bookings',
        // Sidebar v2 — subtle tag shown when the account minimises on entering the flow.
    ],

    'account' => [
        'eyebrow' => 'Your space',
        'title' => 'My account',
        'subtitle' => 'Manage your details, your access and your privacy.',
        'wrong_password' => 'Your current password is incorrect.',
        // La salida de quien entró con Google y no tiene contraseña (art. 12.2, `#344`).
        'no_password' => 'Signed in with Google and have no password? Create one and you will be able to do this: we send a link to your email.',
        'profile' => [
            'title' => 'Your details',
            'intro' => 'Update your name, phone and language. If you change your email, we will send a link to the new mailbox: the change is applied when you confirm it from there.',
            'name' => 'Full name',
            'email' => 'Email',
            'phone' => 'Phone',
            'locale' => 'Language',
            'current_password' => 'Current password',
            'email_change_hint' => 'Only needed if you change your email. The change does NOT apply until you confirm it from the new mailbox (we will send you a link).',
            'save' => 'Save changes',
            'saving' => 'Saving…',
            'pending_email_title' => 'Email change pending',
            'pending_email_msg' => 'We sent a confirmation link to :email. It expires in :minutes min. In the meantime your account still uses :current.',
            'pending_email_resend' => 'Resend link',
            'pending_email_cancel' => 'Cancel change',
        ],
        'password' => [
            'title' => 'Change password',
            'intro' => 'Use a long, unique password.',
            'current' => 'Current password',
            'new' => 'New password',
            'confirm' => 'Repeat new password',
            'save' => 'Update password',
            'saving' => 'Saving…',
            'show' => 'Show password',
            'hide' => 'Hide password',
        ],
        'sessions' => [
            'title' => 'Sessions',
            'intro' => 'If you think someone else is using your account, log out on all other devices.',
            'current_password' => 'Current password',
            'logout_others' => 'Log out on other devices',
            'working' => 'Logging out…',
            // Las cuentas externas vinculadas y su salida (`specs/auth-con-google.md` §8).
            'identities_title' => 'Linked accounts',
            'unlink' => 'Unlink',
        ],
        'privacy' => [
            'title' => 'Privacy & data (GDPR)',
            'intro' => 'Download a copy of your data or delete your account.',
            'consents_title' => 'Your consents',
            'no_consents' => 'No consents on record.',
            'consent_types' => [
                'privacy' => 'Privacy policy',
                'terms' => 'Terms and conditions',
                'waiver' => 'Liability waiver',
                'marketing' => 'Marketing communications',
            ],
            'export_btn' => 'Download my data',
            'consent_revoked' => 'withdrawn on',
            'marketing_label' => 'I want to receive news and offers from the park.',
            'marketing_hint' => 'You can withdraw it whenever you like, in one click. We keep the date on record and stop writing to you.',
            'delete_title' => 'Delete my account',
            'delete_intro' => 'We will permanently delete your name, email, phone and password, and log you out. By law we keep the minimum order data (without your identity) for invoicing. This cannot be undone.',
            'delete_password' => 'Current password',
            'delete_confirm' => 'Are you sure you want to delete your account? This is permanent.',
            'delete_btn' => 'Delete my account',
            'waiver' => [
                'title' => 'Liability waiver',
                'status_external' => 'The park manages it outside this website.',
                'status_unsigned' => 'You have not signed it yet.',
                'status_current' => 'Signed, current version (v:version).',
                'status_outdated' => 'You signed an earlier version of the text: you can still come in, but please accept the new one.',
                'sign_btn' => 'Sign',
                'signing' => 'Signing…',
                'signed_ok' => 'Signature recorded ✓',
                'declared' => 'declared at the front desk',
                'pdf' => 'PDF',
                'pending_notice' => 'Your liability waiver is pending.',
                'pending_cta' => 'Sign it',
                'status_awaiting_verification' => 'Your liability waiver will be signed as soon as you verify your email.',
            ],
            'deleting' => 'Deleting…',
        ],
        // Phase 6 · dependents (`specs/menores-a-cargo.md` §9.8): the zone and its cards, signed-in only.
        'dependents' => [
            'title' => 'Minors in my care',
            'intro' => 'Declare the minors you are responsible for and sign the waiver on behalf of each one. At the door only their age and whether their waiver is current are shown, never their name.',
            'empty' => 'You have not declared any minor yet.',
            'add_title' => 'Add a minor',
            'name' => 'Name',
            'name_hint' => 'The name you use at home is fine: only you will see it.',
            'surname' => 'Surname',
            'relationship' => 'What are you to them?',
            'relationship_choose' => 'Choose one',
            'relationship_hint' => 'We need it to accept that you sign the waiver on their behalf.',
            'relationship_father' => 'Father',
            'relationship_mother' => 'Mother',
            'relationship_legal_guardian' => 'Legal guardian',
            'relationship_grandparent' => 'Grandparent',
            'relationship_other' => 'Other',
            'born_on' => 'Date of birth',
            'add' => 'Add',
            'adding' => 'Adding…',
            // The add form is now DISCLOSED by a button (owner, 2026-08-28): `add_title` labels both
            // the trigger and the section it opens; `add_cancel` folds it back and drops the input.
            'add_cancel' => 'Cancel',
            'age' => ':age years old',
            'adult' => 'Now 18: your waiver no longer covers them.',
            'remove' => 'Remove',
            'removing' => 'Removing…',
            'remove_confirm' => 'Remove :name from your account? If you signed the waiver on their behalf, that record is kept.',
            'waiver_unsigned' => 'Waiver not signed on their behalf.',
            'waiver_current' => 'Waiver signed on their behalf, current version (v:version).',
            'waiver_outdated' => 'earlier version',
            // Assigning TICKETS to minors in the funnel (batch 4): the picker in steps 3 and 4, the
            // notice after signing in, the summary row and the reservation card.
            'for_label' => 'For:',
            'assigned_none' => 'Not assigned to any minor.',
            'assigned_show' => 'See who it is for',
            'assigned_hide' => 'Hide who it is for',
            // The list is paginated CLIENT-SIDE (it arrives whole from `GET /me/dependents`) and the
            // pager is only painted when there is more than one page.
            'pagination' => [
                'label' => 'Minors pagination',
                'prev' => 'Previous',
                'next' => 'Next',
                'page' => 'Page :current of :last',
            ],
        ],
        // The door QR (Phase 6 · A, `specs/identidad-qr-puerta.md` §9.6 B·2): see it, read it out, download it, renew it.
        // ⚠️ The customer-facing word is «QR», never «card» (`[DECIDIDO owner, 2026-08-28]`, §9.7 C·6):
        // EN used to mix «QR card» and «QR pass». Keys and technical names are unchanged.
        'card' => [
            'title' => 'My QR',
            'intro' => 'Your QR identifies you at the door: show it on your phone or printed. It does not sign you in to your account.',
            'alt' => 'Your QR',
            'token_label' => 'If the camera fails, read out this code:',
            'download' => 'Download (PNG)',
            'hint' => 'Share this link with the parents. Each fills in THEIR details.',
            'unavailable' => 'This QR can no longer be shown. Renew it and you will have a new one right away.',
            'rotate' => 'Renew my QR',
            'rotating' => 'Renewing…',
            // ⚠️ El aviso va SIEMPRE visible bajo el botón, no dentro de la confirmación
            // (`specs/identidad-qr-puerta.md` §9.7 C·4): quien no pulsaba nunca llegaba a leer que el
            // QR anterior deja de valer, porque el texto solo existía dentro de `window.confirm`.
            // Y la confirmación vive ya DENTRO del cajón (`[DECIDIDO owner]`, `DECISIONES #217`): la
            // del navegador salía fuera, sin nuestros tres idiomas, y el owner no llegó a verla.
            'rotate_notice' => 'Your previous QR stops working immediately: the one in your email and any printed copy.',
            'rotate_confirm_title' => 'Renew it for sure?',
            'rotate_confirm_yes' => 'Yes, renew',
            'rotate_confirm_no' => 'Cancel',
            'rotated' => 'QR renewed. The previous one no longer works.',
            'expired' => 'Your session has expired: sign in again to see your QR.',
        ],
    ],

    'login' => [
        'cta' => 'Log in',
        'eyebrow' => 'Welcome back',
        'title' => 'Log in',
        'email' => 'Email',
        'password' => 'Password',
        'remember' => 'Keep me logged in',
        'submit' => 'Log in',
        'submitting' => 'Logging in…',
        'forgot' => 'Forgot your password?',
    ],

    'forgot' => [
        'eyebrow' => 'Recover access',
        'title' => 'Reset your password',
        'intro' => 'Enter your email and we will send you a link to set a new password.',
        'email' => 'Email',
        'submit' => 'Send link',
        'submitting' => 'Sending…',
        'back_to_login' => 'Back to log in',
        'sent_title' => 'Check your email',
        'sent_msg' => 'If an account exists for that email, we have sent a link to reset the password. Check your spam folder too.',
    ],

    'reset' => [
        'eyebrow' => 'New password',
        'title' => 'Create a new password',
        'intro' => 'Choose a new password for your account.',
        'email' => 'Email',
        'password' => 'New password',
        'password_confirmation' => 'Repeat password',
        'submit' => 'Save password',
        'submitting' => 'Saving…',
    ],

    'register' => [
        'cta' => 'Sign up',
        'eyebrow' => 'Join us',
        'title' => 'Create your account',
        'subtitle' => 'Needed to book tickets and birthdays.',
        'name' => 'Full name',
        'email' => 'Email',
        'phone' => 'Phone',
        'password' => 'Password',
        'password_hint' => 'At least 8 characters. Avoid common or breached passwords.',
        'must_accept' => 'You must accept this to continue.',
        'accept_waiver' => 'I have read and accept the liability waiver.',
        'waiver_read' => 'Read the full text',
        'accept_privacy' => 'I have read and accept the <a href=":url" target="_blank" rel="noopener">privacy policy</a>.',
        'accept_terms' => 'I accept the <a href=":url" target="_blank" rel="noopener">terms and conditions</a>.',
        'marketing' => 'I want to receive news and offers (optional).',
        'submit' => 'Create account',
        'submitting' => 'Creating…',
        'fix_errors' => 'Please check these fields:',
        'bot_check_failed' => 'We could not verify you are not a robot. Please try again.',
        'already_exists' => 'You already have an account with this email. Please log in to continue.',
        'exists_unverified' => 'You already registered with this email but did not verify it. We have resent the verification link.',
        'leave_blank' => 'Leave this field blank',
        'google_cta' => 'Continue with Google',
    ],

    'google' => [
        'eyebrow' => 'Almost there',
        'title' => 'Finish signing up',
        // ⚠️ Said «…to book in your name», which was FALSE (`#345`): nothing is booked here — this
        // screen CREATES THE ACCOUNT, which is what its own submit button says.
        'intro' => 'Google has already confirmed who you are. This is all we need to create your account.',
        'email_label' => 'Your email',
        'email_hint' => 'The one from your Google account, already verified.',
        'submit' => 'Create my account',
        'submitting' => 'Creating…',
        'expired' => 'Too much time has passed since you signed in with Google, or this signup was already completed.',
        'restart' => 'Start again with Google',
    ],

    'status' => [
        'email-verified' => 'Email confirmed! Your account is now active.',
        'email-already-verified' => 'Your email was already confirmed.',
        'verification-link-sent' => 'We have resent your verification email. Check your inbox (and your spam folder).',
        'verification-resend-throttled' => 'We just sent you the email. Please wait a minute before requesting another.',
        'password-reset' => 'Password updated. You can now log in.',
        'profile-updated' => 'Details updated.',
        'password-updated' => 'Password updated. We have logged out your other sessions for your safety.',
        'email-change-requested' => 'We have sent a link to your new email to confirm the change. In the meantime your account keeps using the current email.',
        'email-change-confirmed' => 'Email confirmed. You can now use it to log in.',
        'email-change-expired' => 'The link to confirm the email change has expired. Request it again if you still want to change it.',
        'email-change-taken' => 'That email was registered by another account while you were waiting to confirm. We have cancelled the change; try another email.',
        'email-change-cancelled' => 'Email change cancelled.',
        'email-change-resent' => 'We have resent the confirmation link to the new email.',
        'logged-out-others' => 'You have logged out on all other devices.',
        'account-deleted' => 'Your account has been deleted. We hope to see you again.',
        'order-retry-unavailable' => 'We can no longer retry this payment: the booking has expired and the spot has been released. You can make a new booking whenever you want.',
        'order-retry-failed' => 'We could not start the payment now. Please try again in a moment; if the problem persists, write to us.',
        // Reservations paused (#218): payment retry is blocked; the customer can call.
        'order-retry-throttled' => 'You are trying too often. Wait a minute and try again: your booking is still held.',
        'order-retry-paused' => 'Online booking is paused for now. Call us and we will complete your booking by phone.',
        'guest-form-saved' => 'Booking form saved. Thank you! You can edit it again anytime.',
        'google-linked' => 'We have linked your Google account. From now on you can sign in with it.',
        'google-cancelled' => 'You did not finish signing in with Google. You can try again whenever you like.',
        'google-failed' => 'We could not sign you in with Google. Please try again; if it keeps failing, sign in with your password or contact us.',
        'google-email-unverified' => 'Google does not consider that address verified, so we cannot use it to identify you. Verify it in your Google account, or sign up with your email and a password.',
        'google-anonymized' => 'That account was deleted at its owner’s request and cannot be recovered. You can create a new one whenever you like.',
        'google-provider-conflict' => 'Your account is already linked to a different Google account. Sign in with that one, or with your password, and contact us if you want to change it.',
    ],

    'verify' => [
        'eyebrow' => 'Almost done',
        'title' => 'Confirm your email',
        'intro' => 'We have sent a confirmation link to your email. Open it to activate your account.',
        'sent_to' => 'We have sent a confirmation email to :email. Open it to activate your account.',
        'spam_hint' => 'Cannot find it? Check your spam or promotions folder.',
        'resend' => 'Resend email',
        'pending_notice' => 'You must verify your email address.',
        'resend_in' => 'Resend in',
        'resending' => 'Resending…',
        'resends_left' => ':n resends left.',
        'resend_limit' => 'You have reached the resend limit. Check your spam folder or try again later.',
        'notice_resend_hint' => 'Did not get it? Resend your verification email:',
        'notice_resend_button' => 'Resend verification email',
        'notice_resend_hint_guest' => 'Did not get it? Log in and you will be able to resend your verification email.',
        'already_have_account' => 'Already have an account?',
    ],

    'exists_mail' => [
        'subject' => 'You already have a :park account',
        'greeting' => 'Hi!',
        'line1' => 'Someone tried to sign up with your email. If it was you, you already have an account: sign in or reset your password.',
        'action' => 'Sign in',
        'line2' => 'If it was not you, you can safely ignore this message.',
    ],

    'social_link_mail' => [
        'providers' => [
            'google' => 'Google',
        ],
        'subject' => 'Your :park account now signs in with :provider',
        'greeting' => 'Hi!',
        'line1' => 'From now on you can sign in to your :park account with :provider, as well as the way you did before.',
        'promoted' => 'Your email address was not verified yet, so we have taken :provider as proof of it and, to keep the account safe, closed any open sessions and disabled the previous password. If you want a password again, use “I forgot my password”.',
        'not_you' => 'If this was not you, change your email account password right away and let us know.',
        'action' => 'Contact us',
    ],

    'orders' => [
        // Las respuestas del pack, BAJO DEMANDA (tanda 3): son datos de un menor
        // (art. 9) y por eso no se pintan solas ni viajan en la lista de pedidos.
        'event_data_show' => 'Show the event details',
        'event_data_hide' => 'Hide the event details',
        'eyebrow' => 'Your bookings',
        'title' => 'My bookings',
        'intro' => 'Check your bookings, their code and status.',
        'view' => 'View my bookings',
        'empty' => 'You don’t have any bookings yet.',
        // #179: orders list pagination.
        'pagination' => [
            'label' => 'Bookings pagination',
            'prev' => 'Previous',
            'next' => 'Next',
            'page' => 'Page :current of :last',
        ],
        'item_finished' => 'Finished',
        'item_cancelled' => 'Cancelled',
        'history' => [
            'title' => 'Booking history',
            'cta' => 'View booking history',
            'empty' => 'Your past and cancelled bookings will appear here.',
        ],
        'order_ref' => 'Order :code',
        'order_show' => 'View order',
        'retry_payment' => 'Retry payment',
        'retry_hint' => 'We are holding your booking for a few more minutes in case you want to complete the payment.',
        'guest_form_pending' => 'Complete the booking form',
        'guest_form_done' => 'View or edit the booking form',
        'guest_form_past' => 'View the booking form',
        'guest_form_cancelled' => 'Booking form · cancelled',
        'manage' => 'Manage',
        'manage_eyebrow' => 'Your booking',
        'manage_title' => 'Manage your booking',
        'manage_intro' => 'Any change to your booking (date or time, cancellation, guest count or any other issue) is handled directly with our team so we can assess your case and offer you the best possible solution.',
        'manage_note' => 'Write down or copy this number and provide it when you contact us so we can help you quickly.',
        'manage_cta' => 'Go to contact',
    ],

    /** **My orders** — the MONEY, per order. Own screen: the breakdown belongs to the ORDER, and a
     * booking is not its unit. ⚠️ Mind the name: the `orders` group above is «My bookings» (its web
     * route is `/mi-cuenta/pedidos`), so this screen's group has to be called something else. */
    'purchases' => [
        'guest_minors' => [
            'count' => ':count signed',
            'waiver_outdated' => 'earlier version',
            'waiver_missing' => 'signature missing',
            'hint' => 'Pass it on to the parents or guardians: each of them fills in THEIR details and cannot see anyone else’s.',
            'share' => 'Share or copy the link',
            'shared' => 'Link shared',
            'copied' => 'Link copied',
            'failed' => 'Could not copy: select the link and copy it by hand.',
            'places' => 'free places: :count',
        ],
        'title' => 'My orders',
        'empty' => 'You do not have any orders yet.',
        'ref' => 'Order :code',
        'show' => 'Show the breakdown',
        'hide' => 'Hide the breakdown',
        'reservations' => 'Bookings in this order',
        'pagination' => [
            'label' => 'Order pagination',
            'prev' => 'Previous',
            'next' => 'Next',
            'page' => 'Page :current of :last',
        ],
    ],
];
