<?php

return [
    'time_no_limit' => 'no time limit',
    'eyebrow' => 'Tickets',
    'title' => 'Bookings',
    'my_reservations' => 'View my bookings',
    'intro' => 'Pick your ticket or pack, the day and the time. Add what you want to your cart; the price adjusts to the day.',
    // Step titles are QUESTIONS (`#557`): the imperative already lives in the footer button.
    'step_date' => 'What day are you coming?',
    'step_time' => 'What time?',
    // ⚠️ No duration here: that is this installation's data and the catalogue says it (`#487`).
    'step_time_lede' => 'Come in whenever you like within your slot.',
    'step_tickets' => 'Choose your tickets',
    // Sidebar v2 — phase band and dynamic footer. Since `#555` there are FIVE phases, one per SCREEN
    // of the path (day · time · cart · about you · pay).
    'step_count' => 'Step :n of :total',
    'phase_date' => 'Date',
    'phase_time' => 'Time',
    // ⚠️ The FIVE phases are one per SCREEN of the path (`#555`). `phase_extras` and `phase_details`
    // are gone: they named the third one by product type and lit up INSIDE the time screen, so the
    // same «Step 3 of 5» showed up in two places.
    'phase_cart' => 'Your cart',
    'phase_identify' => 'About you',
    'phase_pay' => 'Pay',
    'go_to_cart' => 'Go to cart',
    'go_to_pay' => 'Go to payment',
    'cart_items' => ':count item|:count items',
    // Deposit breakdown in the sticky footer, under the Total. «You pay now» is neutral in the cart
    // (mixed baskets aren't only a deposit); the single-product step clarifies «(deposit)». The rest
    // («At the park») reuses tickets.pay_at_park.
    'footer_pay_now' => 'You pay now',
    'footer_pay_now_deposit' => 'You pay now (deposit)',
    // On the pay step's BAND the second row carries the verb («To pay at the park») instead of the
    // ⓘ's «At the park»: there it hangs off «You pay now», which already says what happens; here it
    // hangs off «Total», which doesn't (`#554`).
    'footer_park_total' => 'To pay at the park',
    // The REASON you can't move on goes in the total's label, where it can be read, and not inside
    // the disabled button (`#554`).
    'footer_pick_day' => 'Pick a day to see the price',
    'footer_pick_time' => 'Pick a time to see the price',
    'deposit_info' => 'See deposit breakdown',
    'back_to_cart' => 'Back to cart',
    'section_entries' => 'Tickets',
    // «Grupos», no «Servicios» (`[DECIDIDO owner]`, `#552`): la sección agrupa los productos de tipo
    // `pack`, que son los de GRUPO. La clave se queda; lo que cambia es lo que lee el cliente.
    'section_services' => 'Groups',
    // La frase de cada franja del catálogo (`#552`). Sin datos de la instalación: la duración de un
    // pack la pone el panel (`#487`).
    'section_entries_sub' => 'Pick a zone and how long',
    'section_services_sub' => 'The whole zone for you',
    'catalog_search' => 'Search the catalogue…',
    'catalog_search_none' => 'No results.',
    'guests' => 'Guests',
    'guests_left' => ':count spots left',
    'guests_count' => ':count guests',
    'mixed_party_badge' => 'MIXED',
    'mixed_party_product_name' => ':name · :badge',
    'gate_mixed_party_line' => 'Supplement for :count guest in a different age range|Supplement for :count guests in a different age range',
    'gate_mixed_party_line_named' => 'Supplement for :count guest who belongs to :target|Supplement for :count guests who belong to :target',
    'gate_mixed_party_credit_line' => 'Discount for :count guest in a cheaper age range|Discount for :count guests in a cheaper age range',
    'gate_mixed_party_credit_line_named' => 'Discount for :count guest who belongs to :target|Discount for :count guests who belong to :target',
    // Quantity WITH its noun — that is what tells it apart from the amount (`DECISIONES #128`):
    // "8×216,00 €" reads as 8 × 216 = 1,728 €; "8 guests · 216,00 €" does not.
    'entries_count' => ':count ticket|:count tickets',
    'units_count' => ':count unit|:count units',
    'per_child' => 'per child',
    'step_complements' => 'Add-ons',
    // A LABEL, not a lede (`#557`): a short question in ink above its rows. «(optional)» goes away
    // because a question no longer compels anything.
    'complements_intro' => 'Anything else?',
    'addon_choose_one' => 'Choose one:',
    'addon_badge_included' => 'Included',
    'addon_badge_free' => 'Free',
    'addon_more_info' => 'More info',
    'addon_included' => 'Included',
    'addon_included_partial' => ':count included free',
    'addon_included_extra' => 'Included · extras :price each',
    'addon_extra_each' => 'extras :price each',
    'addon_per_unit' => ':price/guest',
    // The EXTRA HOUR (`specs/hora-extra.md` §8.6): the quantity is TICKETS that stay.
    'addon_stay_price' => ':price per ticket that stays',
    'addon_stay_selected' => 'For 1 ticket staying · :price|For :count tickets staying · :price',
    'addon_per_guest_qty' => ':count (one per guest)',
    'addon_per_guest_add' => 'Add',
    'addon_stay_per_guest_selected' => 'One extra hour for 1 guest · :price/guest|One extra hour for all :count guests · :price/guest',
    'addon_requires' => 'Requires: :name',
    'from' => 'from',
    'qty_less' => 'Remove one',
    'qty_more' => 'Add one',
    'quantity' => 'Quantity',
    'no_dates' => 'No days available right now. Please try again later.',
    'zone' => 'Zone',
    'seats_left' => ':count left',
    'sold_out' => 'Sold out',
    'summary' => 'Summary',
    'summary_empty' => 'Pick day, time and tickets to see the total.',
    'total' => 'Total',
    'continue' => 'Continue',
    'confirm_next' => 'Cart ready. Continue to sign in and pay.',
    'iva_note' => 'Prices include VAT. Payment is processed securely with Redsys.',
    'prev_month' => 'Previous month',
    'next_month' => 'Next month',
    'legend_normal' => 'Regular day',
    'legend_special' => 'Special (holiday, weekend or eve)',
    // Day strip + collapsible calendar (`DECISIONES #239`).
    'calendar_show' => 'See more dates',
    'calendar_hide' => 'Hide calendar',
    // Occupancy hint on the time chip. `[DECIDIDO owner]`: no number — it says few are left, not how
    // many. The threshold is set by the operator (`booking.low_availability_max`).
    'almost_full' => 'Almost full',
    'strip_prev' => 'See previous',
    'strip_next' => 'See next',
    'back' => 'Back',
    'add_to_cart' => 'Add to cart',
    'cart_title' => 'Your cart',
    'cart_empty' => 'Your cart is empty.',
    // The two controls of a restored line's «missing data» block (`#560`).
    'pending_save' => 'Save',
    'pending_discard' => 'Discard',
    'add_another' => 'Add another booking',
    'remove' => 'Remove',
    // The lede says WHAT the account is for, not that one is required (`#561`).
    'identify_title' => 'Almost there',
    'identify_intro' => 'Your account keeps your code and your signature. With that, at the door you just show your phone.',
    'pay_title' => 'Payment',
    'pay_intro' => 'Review your booking before paying.',
    // ── What is missing before paying (`#349`) ─────────────────────────────────────────────────
    'due_phone_label' => 'Your phone number',
    'due_phone_hint' => 'We need it so we can let you know if anything changes about your booking. We use it for nothing else.',
    'due_terms' => 'I have read and accept the <a href=":url" target="_blank" rel="noopener">booking terms</a>.',
    'terms_link' => 'By booking you accept the <a href=":url" target="_blank" rel="noopener">booking terms</a>.',
    'due_terms_updated' => 'We have updated the booking terms. Please read and accept them to continue.',
    'pay_notice' => 'Secure card payment via Redsys. Your card details are not stored on this site.',
    'pay_confirm' => 'Pay by card',
    'pay_redirecting' => 'Taking you to the secure payment page. If you are not redirected in a few seconds, tap the button.',
    'pay_redirecting_title' => 'Redirecting to payment',
    'pay_proceed_manual' => 'Continue to payment',
    'redsys_product_description' => ':name booking · order :code',
    'payment_failed_title' => 'The payment did not go through',
    'payment_failed_intro' => 'Your bank declined the charge. Nothing was billed to your card.',
    'payment_failed_retry' => 'We are holding your booking for a few more minutes in case you want to retry the payment. If it does not go through, the spot will become available again.',
    'payment_failed_contact' => 'Contact us',
    'payment_failed_retry_cta' => 'Retry payment',
    'payment_failed_reason_label' => 'Reason',
    'payment_failed' => [
        'reasons' => [
            'card_expired' => 'Your card has expired (or the date you entered is incorrect).',
            'card_invalid' => 'The card is invalid or cannot be used for this payment.',
            'cvv_wrong' => 'The CVV code (3 digits on the back) is incorrect.',
            'card_unsupported' => 'Your card does not support payments to this merchant. Try another one.',
            'auth_failed' => 'We could not verify your identity with the bank. Try again or use another card.',
            'bank_denied' => 'Your bank declined the payment. Contact them to find out why.',
            'fraud_suspicion' => 'Your bank detected unusual activity and blocked the payment. Contact them to authorise it.',
            'pin_attempts_exceeded' => 'You have exceeded the number of attempts allowed by your bank. Wait a few hours or contact your bank.',
            'user_cancelled' => 'The payment was cancelled at the gateway. You can try again whenever you want.',
            'system_error' => 'The payment gateway could not process the operation. Try again in a few minutes.',
            'default' => 'The payment was not authorised. If the problem continues, contact your bank or write to us.',
        ],
    ],
    'payment_rejected_generic' => 'We could not verify the payment result. If in doubt, please contact us.',
    'payment_verifying_title' => 'Verifying your payment',
    'payment_verifying_intro' => 'Your bank has processed the payment. We are confirming the operation with the gateway; this usually takes a few seconds.',
    'payment_verifying_email_note' => 'We will email you when it is confirmed. You can also check the status from "My bookings".',
    'verify_title' => 'Verify your email',
    'verify_intro' => 'To complete your booking, verify your account from the email we sent you.',
    'verify_hold' => 'Your spot is reserved while you confirm.',
    'reservation_created' => 'Booking created!',
    'reservation_thanks' => 'Thank you! Your spot is reserved. Here’s your summary:',
    'order_code' => 'Order no.',
    'email_sent_note' => 'We have emailed you the summary and this number.',
    'pending_payment' => 'Booking pending payment.',
    'payment_confirmed_note' => 'Payment confirmed.',
    'new_purchase' => 'Make another booking',
    'see_my_orders' => 'See my bookings',
    'guest_form_notice' => 'We’ll ask you to complete your booking form: we’ll email you the link (also in “My bookings”).',
    'statuses' => [
        'pending' => 'Pending payment',
        'paid' => 'Completed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        'expired' => 'Expired',
    ],
    // Additive secondary badge + refund summary in my-account (#146).
    'refunded_badge' => 'Refunded',
    'refunded_on' => 'Refunded on :date',
    'net' => 'Net',
    // #225 (deposit): cart sidecart (step 8) and confirmation screen (step 6) split.
    // #225 F2: deposit announced across the whole purchase flow (catalogue · quantity · cart · pay).
    'total_pay_now' => 'Total to pay now',
    'deposit_catalog' => 'Deposit :amount to book',
    'deposit_card_note' => 'Deposit :deposit · :rest at the park',
    'pay_at_park' => 'At the park',
    'paid_online_confirmed' => 'Paid online',
    'pending_at_park' => 'Pending at the park',
    'subtotal' => 'Subtotal',
    // ▶ The two-axis labels (`ledger.*`, the gate-charge lines, «Refund pending», «Final total»…)
    // lived here until T3·4 of the book (`DECISIONES #315`): the book uses `journal.*` instead.
    // ⚠️⚠️ THE SENTENCE that explains the state of the order (`DECISIONES #127`): composed by the
    // DOMAIN (`Booking\Services\OrderBook`), which is the one that knows which case it is.
    'ledger_note' => [
        // ⚠️⚠️ `under_review` WINS over the rest: if the book does not close, no other sentence can be
        // true (`DECISIONES #132`). The other balance kinds carry no sentence: the book says them
        // with their own line (`journal.balance_*`).
        'under_review' => 'We are reviewing the breakdown of this order. The amount charged to you is the one shown below; if you have any doubt, write to us and we will look at it with you.',
        'expired' => 'This booking expired before the payment was completed. You have not been charged.',
        'pending_payment' => 'The :amount payment has not been completed yet. Your slot is held until it expires.',
    ],
    // The order BOOK (`specs/desglose-libro.md` §4.3, `DECISIONES #305`): one line per action, with
    // its sign and its date; the total is the sum; the balance is settled at the park. Composed by
    // the domain (`Booking\Services\MovementLabel`), voice-neutral: one dictionary for customer and panel.
    'journal' => [
        'booking' => 'Booking placed',
        'quantity' => 'Quantity: :old → :new',
        'addon_quantity' => ':name: :old → :new',
        'product_change' => 'Changed to :name',
        'slot_change' => 'Date changed to :when',
        'price_change' => 'Price of the day: :old → :new',
        'edit_fallback' => 'Changes to :product',
        'cancel' => 'Cancelled: :name · :quantity',
        'courtesy' => 'Courtesy discount',
        'paid_online' => 'Paid online',
        'paid_desk' => 'Paid at the desk',
        'refund_card' => 'Refunded to the card',
        'refund_manual' => 'Refunded at the park (recorded)',
        'refund_pending' => 'Refund in progress',
        'refund_failed' => 'Refund failed',
        // T5 · D9: no collection is recorded at the gate, so "settled", never "paid".
        'gate' => 'Settled at the park',
        'gate_refund' => 'Refunded at the park',
        'with_reservation' => ':reservation · :label',
        // T3·1: the book's titles and the balance labels (one per `balance.kind`; `settled` has none).
        'movements_title' => 'Movements',
        'settlements_title' => 'Payments and refunds',
        'total' => 'Total',
        'paid' => 'Paid',
        'balance_pay_at_park' => 'To pay at the park',
        'balance_refund_at_park' => 'To be refunded at the park',
        'balance_refund_pending' => 'Refund pending',
        'balance_pay_online' => 'Left to pay online',
        'balance_rest_at_park' => 'and :amount at the park',
        'email_title' => 'Your order, as of today',
        'balance_settled' => 'Nothing outstanding',
        'balance_expired' => 'Expired without payment',
        'balance_under_review' => 'Amount under review',
    ],
    'errors' => [
        'choose_one' => 'Choose at least one ticket to continue.',
        'cart_empty' => 'Add at least one visit to continue.',
        'login_required' => 'Log in to complete your booking.',
        'sold_out' => 'Sorry, that time slot has just sold out. Try another time.',
        'unavailable' => 'That time slot is no longer available. Please review your cart.',
        'pack_sold_out' => 'Sorry, that birthday date and time has just filled up. Try another slot.',
        'pack_guests_range' => 'The number of guests is not valid for this pack.',
        'event_required' => 'Please fill in the required birthday details.',
        // Validate-on-submit (#UX): specific feedback — summary naming the missing fields + a
        // per-field message under each highlighted input.
        'fields_missing' => 'Still to fill in: :fields.',
        'field_required' => 'Required field.',
        'cart_too_large' => 'You’ve reached the maximum number of cart lines. Finish this booking before adding more.',
        // Per-line messages (2nd-round audit, P-13/P-14).
        'sold_out_line' => '“:product” on :when has sold out. Remove it from the cart and pick another slot.',
        'unavailable_line' => '“:product” on :when is no longer available. Please review your cart.',
        'past_date_line' => '“:product” on :when is a past date. Remove it and pick a new one.',
        'outside_window_line' => 'The time on :when is not available for “:product”. Pick another.',
        'too_soon_line' => '“:product” on :when must be booked further in advance. Pick a later date.',
        'too_late_line' => 'The time for “:product” on :when has already passed. Pick a later slot.',
        // The EXTRA HOUR (`specs/hora-extra.md`): an add-on that OCCUPIES the next slot.
        'addon_occupancy_line' => '“:addon” for “:product” on :when no longer fits: the next slot is full or closed. Remove it or pick another time.',
        'addon_over_line' => 'More people cannot stay (:staying) than are coming in (:entering). Review the extra hours in your selection.',
        'pack_sold_out_line' => 'Pack “:product” on :when has no capacity left. Remove it from the cart and try another slot.',
        'stay_extension_line' => 'Pack “:product” on :when does not fit with the extra hour: the room is taken afterwards. Remove the extra hour or pick another slot.',
        'pack_guests_range_line' => 'Number of guests for “:product” must be between :min and :max.',
        'event_required_line' => 'Missing birthday details for “:product”. Go back and fill them in.',
        'too_many_pending' => 'You have :max pending bookings (the maximum). If you need to cancel one, write to us from Contact and we will help you.',
        'try_later' => 'Too many attempts in a row. Wait a minute before trying again.',
        'payment_unavailable' => 'We could not start the payment. Please try again in a moment; if the problem persists, contact us.',
        'retry_expired' => 'Your booking expired while we were waiting for the payment. The spot is available again; you will need to pick it once more.',
        // Reservations paused (#218): purchase-flow server guard.
        'reservations_paused' => 'Online booking is paused for now. Please call us at :phone to book.',
        'reservations_paused_no_phone' => 'Online booking is paused for now. Please reach us via Contact to book.',
    ],

    // Maintenance notice INSIDE the sidecart (#218, item 3): opening the booking with reservations
    // paused shows this + the contact channels (phone / WhatsApp).
    'paused' => [
        'title' => 'We are under maintenance',
        'body' => 'Sorry for the inconvenience. Online booking is unavailable right now, but you can book by calling us or messaging us on WhatsApp.',
        'call' => 'Call :phone',
        'whatsapp' => 'Message us on WhatsApp',
        'contact' => 'Go to contact',
    ],

    // Los menores a cargo EN EL EMBUDO (Fase 6 · tanda 4, `menores-a-cargo.md` §9.9): el selector de
    // los pasos 3 y 4, el aviso de la puerta 2 y el «Para:» del resumen. ⚠️ Viven AQUÍ y no en
    // `account.dependents` porque `account` viaja SOLO con sesión y quien entra anónimo y se identifica
    // en el paso 5 los necesita sin recargar — lo cazó el guion headless (§5.undecies).
    'dependents' => [
        'title' => 'Who are these tickets for?',
        'hint' => 'Tick the minors coming with these tickets; the rest are adults.',
        'adult' => 'already 18',
        'assigned' => '1 ticket assigned',
        'unsigned' => 'waiver not signed: sign it under “Minors in my care”',
        'outdated' => 'you signed an earlier version: accept the new one under “Minors in my care”',
        'notice' => 'You have minors in your care: say who each ticket is for before paying (or leave them as adults).',
        'for' => 'For:',
        'age' => ':age years old',
    ],
    /*
     * The GUARDIAN AUTHORIZATION for an invited minor (`specs/waiver-por-reserva.md` §12.2).
     * ⚠️ The wording names the CASE, never our vocabulary: whoever buys does not know what a
     * «waiver offshore» is, and «a minor who is not in your care» is what they recognise.
     */
    'who_block' => [
        'title' => 'Who is coming?',
        'none' => 'Minors in your care and authorizations',
        'some' => 'Minors in your care: :count',
        'guardian' => 'With an invited minor’s authorization',
        'both' => 'Minors in your care: :count · and an authorization',
    ],
    'guardian_no_places' => 'There are no free places left on this booking: you have already assigned them all to minors in your care. Add another ticket or remove an assignment.',
    'guardian_optional' => 'A minor who is not in my care is coming',
    'guardian_optional_help' => 'Their parent or legal guardian will have to sign an authorization. When you finish the purchase we send you the link to pass on.',
    'guardian_required' => 'Every minor on this booking needs a signed authorization from their parent or legal guardian. When you finish the purchase we send you the link to share.',
];
