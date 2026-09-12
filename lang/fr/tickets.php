<?php

return [
    'time_no_limit' => 'sans limite',
    'eyebrow' => 'Billets',
    'title' => 'Réservations',
    'my_reservations' => 'Voir mes réservations',
    'intro' => "Choisis ton billet ou pack, le jour et l'heure. Ajoute ce que tu veux au panier ; le prix s'ajuste selon le jour.",
    // Les titres d'étape sont des QUESTIONS (`#557`) : l'impératif vit déjà dans le bouton du pied.
    'step_date' => 'Quel jour venez-vous ?',
    'step_time' => 'À quelle heure ?',
    // ⚠️ Pas de durée ici : c'est la donnée de CETTE installation et le catalogue la dit (`#487`).
    'step_time_lede' => 'Entre quand tu veux dans ton créneau.',
    'step_tickets' => 'Choisis tes billets',
    // Sidebar v2 — bande de phases et pied de page dynamique. Depuis `#555` il y a CINQ phases, une
    // par ÉCRAN du parcours (jour · heure · panier · qui es-tu · payer).
    'step_count' => 'Étape :n sur :total',
    'phase_date' => 'Date',
    'phase_time' => 'Heure',
    // ⚠️ Les CINQ phases sont une par ÉCRAN du parcours (`#555`). `phase_extras` et `phase_details`
    // disparaissent : elles nommaient la troisième selon le type de produit et s'allumaient DANS
    // l'écran de l'heure, si bien que le même « Étape 3 sur 5 » apparaissait à deux endroits.
    'phase_cart' => 'Ton panier',
    'phase_identify' => 'Qui es-tu',
    'phase_pay' => 'Payer',
    'go_to_cart' => 'Voir le panier',
    'go_to_pay' => 'Payer',
    'cart_items' => ':count article|:count articles',
    // Détail de l'acompte dans le pied de page sticky, sous le Total. « Tu paies maintenant » est
    // neutre dans le panier (un panier mixte n'est pas que de l'acompte) ; l'étape produit unique
    // précise « (acompte) ». Le reste (« Au parc ») réutilise tickets.pay_at_park.
    'footer_pay_now' => 'Tu paies maintenant',
    'footer_pay_now_deposit' => 'Tu paies maintenant (acompte)',
    // Dans la BANDE de l'étape de paiement, la seconde ligne porte le verbe (« À payer au parc ») et
    // non le « Au parc » du ⓘ : là elle dépend de « Tu paies maintenant », qui dit déjà ce qui se
    // passe, et ici de « Total », qui ne le dit pas (`#554`).
    'footer_park_total' => 'À payer au parc',
    // La RAISON pour laquelle on ne peut pas avancer va dans le libellé du total, là où elle se lit,
    // et non dans le bouton désactivé (`#554`).
    'footer_pick_day' => 'Choisis un jour pour voir le prix',
    'footer_pick_time' => 'Choisis une heure pour voir le prix',
    'deposit_info' => "Voir le détail de l'acompte",
    'back_to_cart' => 'Retour au panier',
    'section_entries' => 'Billets',
    // « Grupos », pas « Servicios » (`[DECIDIDO owner]`, `#552`) : la section regroupe les produits de
    // type `pack`, qui sont ceux de GROUPE. La clé ne change pas ; ce qui change, c'est ce qu'on lit.
    'section_services' => 'Groupes',
    // La frase de cada franja del catálogo (`#552`). Sans données de l'installation : la durée d'un
    // pack vient du panneau (`#487`).
    'section_entries_sub' => 'Choisis ta zone et la durée',
    'section_services_sub' => 'La zone entière pour vous',
    'catalog_search' => 'Rechercher dans le catalogue…',
    'catalog_search_none' => 'Aucun résultat.',
    'guests' => 'Invités',
    'guests_left' => ':count places restantes',
    'guests_count' => ':count invités',
    'mixed_party_badge' => 'MIXTE',
    'mixed_party_product_name' => ':name · :badge',
    'gate_mixed_party_line' => 'Supplément pour :count invité d’une autre tranche d’âge|Supplément pour :count invités d’une autre tranche d’âge',
    'gate_mixed_party_line_named' => 'Supplément pour :count invité relevant de :target|Supplément pour :count invités relevant de :target',
    'gate_mixed_party_credit_line' => 'Remise pour :count invité d’une tranche plus économique|Remise pour :count invités d’une tranche plus économique',
    'gate_mixed_party_credit_line_named' => 'Remise pour :count invité relevant de :target|Remise pour :count invités relevant de :target',
    // La quantité AVEC son substantif, qui est ce qui la distingue du montant (`DECISIONES #128`) :
    // « 8×216,00 € » se lit comme 8 × 216 = 1 728 € ; « 8 invités · 216,00 € », non.
    'entries_count' => ':count billet|:count billets',
    'units_count' => ':count unité|:count unités',
    'per_child' => 'par enfant',
    'step_complements' => 'Compléments',
    // Un LIBELLÉ, pas un chapô (`#557`) : une question courte en encre au-dessus de ses lignes.
    'complements_intro' => 'Tu veux ajouter quelque chose ?',
    'addon_choose_one' => 'Choisis une option :',
    'addon_badge_included' => 'Inclus',
    'addon_badge_free' => 'Gratuit',
    'addon_more_info' => 'Plus d’infos',
    'addon_included' => 'Inclus',
    'addon_included_partial' => ':count inclus gratuitement',
    'addon_included_extra' => 'Inclus · extras :price/u',
    'addon_extra_each' => 'extras :price/u',
    'addon_per_unit' => ':price/invité',
    // L'HEURE SUPPLÉMENTAIRE (`specs/hora-extra.md` §8.6) : la quantité, ce sont des ENTRÉES qui restent.
    'addon_stay_price' => ':price par entrée qui reste',
    'addon_stay_selected' => 'Pour 1 entrée qui reste · :price|Pour :count entrées qui restent · :price',
    'addon_per_guest_qty' => ':count (un par invité)',
    'addon_per_guest_add' => 'Ajouter',
    'addon_stay_per_guest_selected' => 'Une heure de plus pour 1 invité · :price/invité|Une heure de plus pour les :count invités · :price/invité',
    'addon_requires' => 'Nécessite : :name',
    'from' => 'à partir de',
    'qty_less' => 'Retirer un',
    'qty_more' => 'Ajouter un',
    'quantity' => 'Quantité',
    'no_dates' => 'Aucun jour disponible pour le moment. Réessaie plus tard.',
    'zone' => 'Zone',
    'seats_left' => ':count dispo',
    'sold_out' => 'Complet',
    'summary' => 'Récapitulatif',
    'summary_empty' => 'Choisis le jour, l\'heure et les billets pour voir le total.',
    'total' => 'Total',
    'continue' => 'Continuer',
    'confirm_next' => 'Panier prêt. Continue pour t\'identifier et payer.',
    'iva_note' => 'Prix TTC. Le paiement est traité en toute sécurité avec Redsys.',
    'prev_month' => 'Mois précédent',
    'next_month' => 'Mois suivant',
    'legend_normal' => 'Jour normal',
    'legend_special' => 'Spécial (férié, week-end ou veille)',
    // Bande de jours + calendrier repliable (`DECISIONES #239`).
    'calendar_show' => 'Voir plus de dates',
    'calendar_hide' => 'Masquer le calendrier',
    // Indication d'occupation sur la puce d'horaire. `[DECIDIDO owner]` : sans chiffre — elle dit
    // qu'il reste peu de places, pas combien. Le seuil est réglé par l'exploitant.
    'almost_full' => 'Presque complet',
    'strip_prev' => 'Voir les précédents',
    'strip_next' => 'Voir les suivants',
    'back' => 'Retour',
    'add_to_cart' => 'Ajouter au panier',
    'cart_title' => 'Ton panier',
    'cart_empty' => 'Ton panier est vide.',
    // Les deux contrôles du bloc « données manquantes » d'une ligne restaurée (`#560`).
    'pending_save' => 'Enregistrer',
    'pending_discard' => 'Annuler',
    'add_another' => 'Ajouter une autre réservation',
    'remove' => 'Retirer',
    'identify_title' => 'Identifie-toi',
    'identify_intro' => 'Connecte-toi ou crée ton compte pour finaliser ta réservation.',
    'pay_title' => 'Paiement',
    'pay_intro' => 'Vérifie ta réservation avant de payer.',
    // ── Ce qui manque avant de payer (`#349`) ──────────────────────────────────────────────────
    'due_phone_label' => 'Ton téléphone',
    'due_phone_hint' => "Il nous manque pour pouvoir te prévenir si quelque chose change dans ta réservation. Nous ne l'utilisons pour rien d'autre.",
    'due_terms' => 'J\'ai lu et j\'accepte les <a href=":url" target="_blank" rel="noopener">conditions de réservation</a>.',
    'terms_link' => 'En réservant, tu acceptes les <a href=":url" target="_blank" rel="noopener">conditions de réservation</a>.',
    'due_terms_updated' => 'Nous avons mis à jour les conditions de réservation. Merci de les lire et de les accepter pour continuer.',
    'pay_notice' => 'Paiement par carte sécurisé via Redsys. Tes données de carte ne sont pas conservées sur ce site.',
    'pay_confirm' => 'Payer par carte',
    'pay_redirecting' => 'Nous t’emmenons vers la page de paiement sécurisée. Si la redirection ne se fait pas dans quelques secondes, appuie sur le bouton.',
    'pay_redirecting_title' => 'Redirection vers le paiement',
    'pay_proceed_manual' => 'Continuer vers le paiement',
    'redsys_product_description' => 'Réservation :name · commande :code',
    'payment_failed_title' => 'Le paiement n’a pas abouti',
    'payment_failed_intro' => 'Ta banque n’a pas autorisé le paiement. Rien n’a été débité de ta carte.',
    'payment_failed_retry' => 'Nous gardons ta réservation encore quelques minutes au cas où tu voudrais réessayer le paiement. Si cela n’aboutit pas, la place redeviendra disponible.',
    'payment_failed_contact' => 'Nous contacter',
    'payment_failed_retry_cta' => 'Réessayer le paiement',
    'payment_failed_reason_label' => 'Motif',
    'payment_failed' => [
        'reasons' => [
            'card_expired' => 'Ta carte est expirée (ou la date saisie est incorrecte).',
            'card_invalid' => 'La carte n’est pas valide ou ne peut pas être utilisée pour ce paiement.',
            'cvv_wrong' => 'Le code CVV (3 chiffres au dos) est incorrect.',
            'card_unsupported' => 'Ta carte n’accepte pas les paiements chez ce commerçant. Essaie avec une autre.',
            'auth_failed' => 'Nous n’avons pas pu vérifier ton identité auprès de la banque. Réessaie ou utilise une autre carte.',
            'bank_denied' => 'Ta banque a refusé le paiement. Contacte-la pour en connaître la raison.',
            'fraud_suspicion' => 'Ta banque a détecté une activité inhabituelle et a bloqué le paiement. Contacte-la pour l’autoriser.',
            'pin_attempts_exceeded' => 'Tu as dépassé le nombre d’essais autorisés par ta banque. Attends quelques heures ou contacte ta banque.',
            'user_cancelled' => 'Le paiement a été annulé sur la passerelle. Tu peux réessayer quand tu veux.',
            'system_error' => 'La passerelle de paiement n’a pas pu traiter l’opération. Réessaie dans quelques minutes.',
            'default' => 'Le paiement n’a pas été autorisé. Si le problème persiste, contacte ta banque ou écris-nous.',
        ],
    ],
    'payment_rejected_generic' => 'Nous n’avons pas pu vérifier le résultat du paiement. En cas de doute, contacte-nous.',
    'payment_verifying_title' => 'Vérification de ton paiement',
    'payment_verifying_intro' => 'Ta banque a traité le paiement. Nous confirmons l’opération avec la passerelle ; cela prend généralement quelques secondes.',
    'payment_verifying_email_note' => 'Nous t’enverrons un e-mail dès que ce sera confirmé. Tu peux aussi vérifier l’état depuis "Mes réservations".',
    'verify_title' => 'Vérifie ton e-mail',
    'verify_intro' => 'Pour finaliser ta réservation, vérifie ton compte depuis l’e-mail que nous t’avons envoyé.',
    'verify_hold' => 'Ta place est réservée pendant que tu confirmes.',
    'reservation_created' => 'Réservation créée !',
    'reservation_thanks' => 'Merci ! Ta place est réservée. Voici le récapitulatif :',
    'order_code' => 'N° de commande',
    'email_sent_note' => 'Nous t’avons envoyé un e-mail avec le récapitulatif et ce numéro.',
    'pending_payment' => 'Réservation en attente de paiement.',
    'payment_confirmed_note' => 'Paiement confirmé.',
    'new_purchase' => 'Faire une autre réservation',
    'see_my_orders' => 'Voir mes réservations',
    'guest_form_notice' => 'Nous te demanderons de compléter le formulaire de ta réservation : nous t’enverrons le lien par e-mail (aussi dans « Mes réservations »).',
    'statuses' => [
        'pending' => 'En attente de paiement',
        'paid' => 'Terminé',
        'cancelled' => 'Annulé',
        'refunded' => 'Remboursé',
        'expired' => 'Expiré',
    ],
    // Badge secondaire + résumé du remboursement en Mon compte (#146).
    'refunded_badge' => 'Remboursé',
    'refunded_on' => 'Remboursé le :date',
    'net' => 'Net',
    // #225 (acompte) : split du sidecart (étape 8) et de l’écran de confirmation (étape 6).
    // #225 F2 : l’acompte est annoncé tout au long de l’achat (catalogue · quantité · panier · paiement).
    'total_pay_now' => 'Total à régler maintenant',
    'deposit_catalog' => 'Acompte :amount pour réserver',
    'deposit_card_note' => 'Acompte :deposit · :rest au parc',
    'pay_at_park' => 'Au parc',
    'paid_online_confirmed' => 'Payé en ligne',
    'pending_at_park' => 'En attente au parc',
    'subtotal' => 'Sous-total',
    // ▶ Les libellés du modèle à deux axes (`ledger.*`, les lignes de frais à l'accueil,
    // « Remboursement en attente », « Total final »…) vivaient ici jusqu'à la T3·4 du livre
    // (`DECISIONES #315`) : le livre utilise `journal.*` à la place.
    // ⚠️⚠️ LA PHRASE qui explique l'état de la commande (`DECISIONES #127`) : composée par le
    // DOMAINE (`Booking\Services\OrderBook`), qui est le seul à savoir de quel cas il s'agit.
    'ledger_note' => [
        // ⚠️⚠️ `under_review` PRIME sur le reste : si le livre ne ferme pas, aucune autre phrase ne
        // peut être vraie (`DECISIONES #132`). Les autres classes de solde n'ont pas de phrase : le
        // livre les dit avec leur propre ligne (`journal.balance_*`).
        'under_review' => "Nous vérifions le détail de cette commande. Le montant qui t'a été encaissé est celui indiqué ci-dessous ; en cas de doute, écris-nous et nous le regarderons ensemble.",
        'expired' => 'Cette réservation a expiré avant la fin du paiement. Aucun montant ne vous a été prélevé.',
        'pending_payment' => "Le paiement de :amount n'est pas encore terminé. Votre place est retenue jusqu'à expiration.",
    ],
    // Le LIVRE de la commande (`specs/desglose-libro.md` §4.3, `DECISIONES #305`) : une ligne par
    // action, avec son signe et sa date ; le total est la somme ; le solde se règle au parc. Composé
    // par le domaine (`Booking\Services\MovementLabel`), sans voix : un seul dictionnaire pour le
    // client et le panneau.
    'journal' => [
        'booking' => 'Réservation effectuée',
        'quantity' => 'Quantité : :old → :new',
        'addon_quantity' => ':name : :old → :new',
        'product_change' => 'Remplacé par :name',
        'slot_change' => 'Nouvelle date : :when',
        'price_change' => 'Prix du jour : :old → :new',
        'edit_fallback' => 'Modifications de :product',
        'cancel' => 'Annulé : :name · :quantity',
        'courtesy' => 'Remise commerciale',
        'paid_online' => 'Payé en ligne',
        'paid_desk' => "Payé à l'accueil",
        'refund_card' => 'Remboursé sur la carte',
        'refund_manual' => 'Remboursé au parc (enregistré)',
        'refund_pending' => 'Remboursement en cours',
        'refund_failed' => 'Remboursement échoué',
        // T5 · D9 : aucun encaissement n'est enregistré à l'accueil, donc « réglé », jamais « payé ».
        'gate' => 'Réglé au parc',
        'gate_refund' => 'Remboursé au parc',
        'with_reservation' => ':reservation · :label',
        // T3·1 : les titres du livre et les libellés du solde (un par `balance.kind` ; `settled` n'en a pas).
        'movements_title' => 'Mouvements',
        'settlements_title' => 'Paiements et remboursements',
        'total' => 'Total',
        'paid' => 'Payé',
        'balance_pay_at_park' => 'À régler au parc',
        'balance_refund_at_park' => 'À vous rembourser au parc',
        'balance_refund_pending' => 'Remboursement en attente',
        'balance_pay_online' => 'Reste à payer en ligne',
        'balance_rest_at_park' => 'et :amount au parc',
        'email_title' => 'Votre commande, à ce jour',
        'balance_settled' => 'Rien en attente',
        'balance_expired' => 'Expirée sans paiement',
        'balance_under_review' => 'Montant en cours de vérification',
    ],
    'errors' => [
        'choose_one' => 'Choisis au moins un billet pour continuer.',
        'cart_empty' => 'Ajoute au moins une visite pour continuer.',
        'login_required' => 'Connecte-toi pour finaliser ta réservation.',
        'sold_out' => 'Désolé, ce créneau vient d’être complet. Essaie une autre heure.',
        'unavailable' => 'Ce créneau n’est plus disponible. Vérifie ton panier.',
        'pack_sold_out' => 'Désolé, cette date et heure d’anniversaire vient d’être complète. Essaie un autre créneau.',
        'pack_guests_range' => 'Le nombre d’invités n’est pas valide pour ce pack.',
        'event_required' => 'Renseigne les informations obligatoires de l’anniversaire.',
        // Validation au clic (#UX) : feedback précis — résumé nommant les champs manquants + un
        // message par champ sous chaque saisie mise en évidence.
        'fields_missing' => 'Reste à remplir : :fields.',
        'field_required' => 'Champ obligatoire.',
        'cart_too_large' => 'Tu as atteint le maximum de lignes dans le panier. Termine cette réservation avant d’en ajouter d’autres.',
        // Messages par ligne (2e audit, P-13/P-14).
        'sold_out_line' => '« :product » du :when est complet. Retire-le du panier et choisis un autre créneau.',
        'unavailable_line' => "« :product » du :when n'est plus disponible. Vérifie ton panier.",
        'past_date_line' => '« :product » du :when est à une date passée. Retire-le et choisis-en une nouvelle.',
        'outside_window_line' => "L'heure du :when n'est pas disponible pour « :product ». Choisis-en une autre.",
        'too_soon_line' => "« :product » du :when doit être réservé plus à l'avance. Choisis une date ultérieure.",
        'too_late_line' => "L'heure de « :product » du :when est déjà passée. Choisis un créneau plus tard.",
        // L'HEURE SUPPLÉMENTAIRE (`specs/hora-extra.md`) : un supplément qui OCCUPE le créneau suivant.
        'addon_occupancy_line' => '« :addon » de « :product » du :when ne rentre plus : le créneau suivant est complet ou fermé. Retire-le ou choisis une autre heure.',
        'addon_over_line' => 'Il ne peut pas rester plus de personnes (:staying) que celles qui entrent (:entering). Vérifie les heures supplémentaires de ta sélection.',
        'pack_sold_out_line' => 'Le pack « :product » du :when n’a plus de capacité. Retire-le du panier et essaie un autre créneau.',
        'stay_extension_line' => 'Le pack « :product » du :when ne rentre pas avec l’heure supplémentaire : la salle est occupée ensuite. Retire l’heure supplémentaire ou choisis un autre créneau.',
        'pack_guests_range_line' => 'Le nombre d’invités pour « :product » doit être entre :min et :max.',
        'event_required_line' => 'Il manque des données d’anniversaire pour « :product ». Reviens en arrière et remplis-les.',
        'too_many_pending' => 'Tu as :max réservations en attente (le maximum). Si tu as besoin d’annuler l’une d’elles, écris-nous depuis Contact et nous t’aidons.',
        'try_later' => 'Trop de tentatives à la suite. Attends une minute avant de réessayer.',
        'payment_unavailable' => 'Nous n’avons pas pu démarrer le paiement. Réessaie dans un instant ; si le problème persiste, contacte-nous.',
        'retry_expired' => 'Ta réservation a expiré pendant l’attente du paiement. La place est de nouveau disponible ; tu devras la choisir à nouveau.',
        // Réservations suspendues (#218) : garde serveur du tunnel d’achat.
        'reservations_paused' => 'La réservation en ligne est suspendue pour le moment. Appelle-nous au :phone pour réserver.',
        'reservations_paused_no_phone' => 'La réservation en ligne est suspendue pour le moment. Contacte-nous pour réserver.',
    ],

    // Avis de maintenance DANS le sidecart (#218, item 3) : à l'ouverture de la réservation avec les
    // réservations suspendues, le sidecart affiche ceci + les canaux de contact (téléphone / WhatsApp).
    'paused' => [
        'title' => 'Nous sommes en maintenance',
        'body' => 'Désolés pour la gêne. La réservation en ligne est indisponible pour le moment, mais tu peux réserver en nous appelant ou en nous écrivant sur WhatsApp.',
        'call' => 'Appeler le :phone',
        'whatsapp' => 'Écris-nous sur WhatsApp',
        'contact' => 'Aller au contact',
    ],

    // Los menores a cargo EN EL EMBUDO (Fase 6 · tanda 4, `menores-a-cargo.md` §9.9): el selector de
    // los pasos 3 y 4, el aviso de la puerta 2 y el «Para:» del resumen. ⚠️ Viven AQUÍ y no en
    // `account.dependents` porque `account` viaja SOLO con sesión y quien entra anónimo y se identifica
    // en el paso 5 los necesita sin recargar — lo cazó el guion headless (§5.undecies).
    'dependents' => [
        'title' => 'Pour qui sont ces billets ?',
        'hint' => 'Cochez les mineurs qui viennent avec ces billets ; les autres sont des adultes.',
        'adult' => 'a déjà 18 ans',
        'assigned' => '1 billet attribué',
        'unsigned' => 'décharge non signée : signez-la dans « Mineurs à ma charge »',
        'outdated' => 'vous avez signé une version antérieure : acceptez la nouvelle dans « Mineurs à ma charge »',
        'notice' => 'Vous avez des mineurs à charge : indiquez pour qui est chaque billet avant de payer (ou laissez-les en adultes).',
        'for' => 'Pour :',
        'age' => ':age ans',
    ],
    /*
     * Le JUSTIFICATIF d'un mineur invité (`specs/waiver-por-reserva.md` §12.2).
     * ⚠️ Le texte nomme le CAS et jamais notre vocabulaire : la personne qui achète ne sait pas ce
     * qu'est un « waiver offshore », mais elle reconnaît « un mineur qui n'est pas à ma charge ».
     */
    'who_block' => [
        'title' => 'Qui vient ?',
        'none' => 'Mineurs à votre charge et autorisations',
        'some' => 'Mineurs à votre charge : :count',
        'guardian' => "Avec l'autorisation d'un mineur invité",
        'both' => 'Mineurs à votre charge : :count · et une autorisation',
    ],
    'guardian_no_places' => 'Il ne reste plus de places libres sur cette réservation : vous les avez toutes attribuées à des mineurs à votre charge. Ajoutez un billet ou retirez une attribution.',
    'guardian_optional' => "Un mineur qui n'est pas à ma charge vient",
    'guardian_optional_help' => "Son père, sa mère ou son tuteur devra signer une autorisation. À la fin de l'achat, nous vous envoyons le lien à lui transmettre.",
    'guardian_required' => "Chaque mineur de cette réservation a besoin de l'autorisation signée de son père, sa mère ou son tuteur. À la fin de l'achat, nous vous envoyons le lien à partager.",
];
