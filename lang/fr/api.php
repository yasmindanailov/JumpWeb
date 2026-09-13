<?php

/*
 * Phase 3 · étape 0 — messages lisibles de l'enveloppe d'erreur de `/api/v1` (spec §4.3).
 *
 * Les CLÉS de ce fichier ne sont pas le contrat public : le contrat, c'est le `code` de
 * l'enveloppe (`App\Http\Api\ApiErrorCode`). L'indirection existe précisément pour pouvoir
 * réorganiser ces clés sans casser aucun client. Le texte est le dernier recours du client :
 * affiché tel quel lorsqu'il ne sait rien faire de mieux avec le `code`.
 */

return [
    'errors' => [
        'unauthenticated' => 'Vous devez vous connecter pour continuer.',
        'invalid_credentials' => 'L’adresse e-mail ou le mot de passe n’est pas correct.',
        'unauthorized' => "Vous n'avez pas l'autorisation d'effectuer cette action.",
        'not_found' => "Nous n'avons pas trouvé ce que vous cherchez.",
        'method_not_allowed' => "Cette opération n'est pas disponible à cette adresse.",
        'session_expired' => 'Votre session a expiré. Veuillez vous reconnecter.',
        'validation_failed' => 'Veuillez vérifier les données envoyées.',
        'too_many_requests' => 'Trop de requêtes successives. Réessayez dans quelques instants.',
        'maintenance' => 'Nous effectuons une opération de maintenance. Réessayez dans quelques minutes.',
        'bad_request' => "Nous n'avons pas pu interpréter la requête.",
        'server_error' => "Une erreur inattendue s'est produite. Veuillez réessayer.",

        // ── Negocio (Fase 3 · paso 4c) ───────────────────────────────────────────────────
        'cart_empty' => 'Votre panier est vide.',
        'cart_too_large' => 'Votre panier contient trop de lignes. Retirez-en une pour continuer.',
        'product_unavailable' => "Ce produit n'est pas disponible.",
        'line_unavailable' => "L'une des lignes de votre panier n'est plus disponible.",
        'line_past_date' => 'Cette date est déjà passée.',
        'line_too_late' => 'Cette heure est déjà passée. Choisissez-en une autre.',
        'line_too_soon' => 'Cette heure est trop proche pour réserver. Choisissez-en une autre.',
        'line_outside_window' => 'Cette heure est en dehors des horaires disponibles.',
        'line_sold_out' => "Il n'y a plus de places à cette heure.",
        'line_pack_sold_out' => "Il n'y a plus de place pour cette réservation à cette heure.",
        'line_pack_guests_range' => "Ce nombre d'invités n'est pas valide pour ce service.",
        'line_event_required' => 'Il manque des informations obligatoires pour la réservation.',
        'line_addon_occupancy' => "L'heure supplémentaire ne rentre plus : le créneau suivant est complet ou fermé.",
        'line_stay_extension' => "La fête ne rentre pas avec l'heure supplémentaire : la salle est occupée ensuite. Retire l'heure supplémentaire ou choisis un autre créneau.",
        'line_addon_over_quantity' => 'Il ne peut pas rester plus de personnes que celles qui entrent.',
        'reservations_paused' => 'La réservation en ligne est temporairement fermée.',
        'too_many_pending_orders' => 'Vous avez déjà plusieurs réservations en attente de paiement. Terminez-les ou attendez leur expiration.',
        'order_not_retryable' => 'Cette réservation ne peut plus être payée.',
        'payment_unavailable' => "Nous n'avons pas pu ouvrir la passerelle de paiement. Réessayez.",
        'guest_form_closed' => 'Cette réservation a déjà eu lieu : ses informations sont consultables mais ne sont plus modifiables.',
        'waiver_not_internal' => 'La décharge ne se signe pas sur ce site.',
        'waiver_document_stale' => 'Le texte de la décharge a changé. Relisez-le et acceptez-le à nouveau.',
        'waiver_email_unverified' => 'Vérifiez votre adresse e-mail avant de signer la décharge.',
        'dependent_not_minor' => 'La personne à charge doit être mineure.',
        'dependents_limit_reached' => 'Votre compte a atteint le nombre maximal de personnes à charge (:max).',
        // T5 · D8 : un compte avec une réservation à venir ne peut pas être supprimé (`cumple-mixto.md` §25.4).
        'account_has_upcoming_reservations' => 'Nous ne pouvons pas encore supprimer votre compte : vous avez des réservations à venir. Vous pourrez le supprimer une fois passées ou si elles sont annulées.',
    ],

    // Avis PAR CHAMP de l'attribution de billets aux personnes à charge (`POST /orders`, Phase 6 · lot 4).
    'dependents' => [
        'not_yours' => 'Cette personne à charge n\'est pas sur votre compte.',
        'not_minor_on_date' => 'Elle aura déjà 18 ans ce jour-là : achetez son billet en tant qu\'adulte.',
        'waiver_unsigned' => 'Sa décharge n\'est pas signée : signez-la dans « Personnes à charge » avant de lui attribuer un billet.',
        'too_many' => 'Vous avez choisi plus de personnes à charge que de billets.',
        'entries_only' => 'Les personnes à charge ne s\'attribuent qu\'aux billets : un pack anniversaire demande déjà ses invités.',
        'repeated' => 'Vous avez choisi deux fois la même personne à charge pour ces billets.',
    ],

    'register' => [
        'waiver_stale' => 'Le texte de la décharge a changé. Relisez-le et acceptez-le à nouveau.',
        'waiver_not_internal' => 'La décharge ne se signe pas sur ce site.',
        'waiver_document_required' => 'Pour accepter la décharge, il faut indiquer quel texte a été lu.',
        'waiver_required' => 'Pour créer le compte, il faut lire et accepter la décharge de responsabilité.',
        'online_sales_disabled' => 'La réservation en ligne n’est pas encore disponible. Appelez-nous ou venez au parc pour réserver.',
    ],

    'google' => [
        'refused' => "Nous n'avons pas pu terminer l'inscription avec ce compte Google. Réessaie, ou inscris-toi avec ton e-mail et un mot de passe.",
    ],
];
