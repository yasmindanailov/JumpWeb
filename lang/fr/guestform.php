<?php

/*
 * Le formulaire des invités d'un anniversaire : ce que le DOMAINE et le contrôleur disent encore avec ces clés. La
 * page est habillée par le nouveau système (`specs/fiesta-sistema-nuevo.md`, `fiesta.php`) ; ce que l'ancienne
 * peau affichait a été retiré avec elle au T4 (26-09). `ClavesDeIdiomaTest` veille à ce qu'aucun texte mort ne reste.
 */
return [
    'title' => 'Formulaire de réservation',
    'progress' => ':done sur :total fiches complétées',
    'progress_complete' => 'Toutes les fiches complétées (:total)',
    'no_product_title' => 'Un âge sans produit',
    'no_product_below' => 'L’un des invités a un âge inférieur à la tranche la plus basse de cet anniversaire, et il n’existe aucun produit pour cet âge dans les conditions de votre réservation. Appelez-nous au :phone et nous verrons cela ensemble ; d’ici là, cette fiche n’est pas considérée comme complète.',
    'no_product_above' => 'L’un des invités a un âge supérieur à la tranche la plus haute de cet anniversaire, et il n’existe aucun produit pour cet âge dans les conditions de votre réservation. Appelez-nous au :phone et nous verrons cela ensemble ; d’ici là, cette fiche n’est pas considérée comme complète.',
    'no_product_gap' => 'L’un des invités a un âge situé entre deux tranches de cet anniversaire, et il n’existe aucun produit pour cet âge dans les conditions de votre réservation. Appelez-nous au :phone et nous verrons cela ensemble ; d’ici là, cette fiche n’est pas considérée comme complète.',
    'no_product_phone_fallback' => 'parc',
    'frozen_missing_ages' => '{1} Il manque :count âge : le supplément ne sera pas recalculé — ni à la hausse ni à la baisse — tant qu’ils ne seront pas tous renseignés.|[2,*] Il manque :count âges : le supplément ne sera pas recalculé — ni à la hausse ni à la baisse — tant qu’ils ne seront pas tous renseignés.',
    'mixed_title' => 'Fête mixte',
    'mixed_line' => ':count invité(s) relèvent de « :target » (:target_price par invité) et non de « :booked » (:booked_price).',
    'mixed_line_written' => ':count invité(s) relèvent de « :target » : :unit de plus par invité.',
    'mixed_surcharge' => 'Un supplément de :amount est donc à régler au parc, le jour de la fête.',
    'mixed_discount_total' => ':amount sont donc déduits. S’il vous reste quelque chose à régler au parc, ils en sont déduits ; si tout était déjà payé, ils vous sont remboursés au parc le jour de la fête.',
    'mixed_net_zero' => 'Entre le supplément et la remise, ce que vous réglez au parc ne change pas pour cette raison.',
    'mixed_savings_pending' => 'Votre fête revient donc :amount moins cher : la remise s’appliquera une fois tous les âges renseignés.',
    'mixed_no_difference' => 'Il n’y a aucune différence de prix entre les deux : rien de plus à régler pour cette raison.',
    'readonly_notice' => 'Cette réservation a déjà eu lieu. Le formulaire est en lecture seule : vous pouvez consulter les informations mais plus les modifier.',
    'count_warn_title' => 'Avant d’enregistrer',
    'count_error_title' => 'Le nombre d’invités n’a pas changé',
    'saved' => 'Formulaire enregistré. Merci ! Vous pouvez le modifier à tout moment.',
    'extras_closed_cutoff' => 'Ce n’est plus modifiable',
    'extras_closed_sold' => 'Vous l’avez choisi à la réservation — appelez-nous pour le modifier',
    'extras_blocked' => 'Vos informations ont été enregistrées, mais l’un des extras n’a pas pu être modifié : son délai est peut-être dépassé. Appelez-nous si besoin.',
    'extras_stale' => 'Vos informations ont été enregistrées, mais pas les extras : la réservation a changé pendant que cette page était ouverte. Rechargez-la et vérifiez-les.',

    'count_hint' => 'Vous pouvez le modifier jusqu\'au :when (maximum :max).',
    'count_closed_cutoff' => 'Le nombre d\'invités ne peut plus être modifié : le délai est dépassé.',
    'count_closed' => 'Le nombre d\'invités ne peut plus être modifié.',
    'count_warn_discard' => 'En passant à :count invités, les données déjà saisies sur :discarded fiche seront perdues.|En passant à :count invités, les données déjà saisies sur :discarded fiches seront perdues.',
    'count_error_above_max' => 'Vos données ont été enregistrées, mais pas le nombre d\'invités : c\'est plus que ce que cet anniversaire permet. Appelez-nous.',
    'count_error_below_min' => 'Vos données ont été enregistrées, mais pas le nombre d\'invités : c\'est moins que le minimum de cet anniversaire. Appelez-nous.',
    'count_error_below_assigned' => 'Vos données ont été enregistrées, mais pas le nombre d\'invités : vous avez déjà attribué plus de places que vous ne souhaitez garder. Retirez quelqu\'un de la liste et réessayez.',
    'count_error_sold_out' => 'Vos données ont été enregistrées, mais pas le nombre d\'invités : il n\'y a plus de place pour autant de monde à cette heure-là. Appelez-nous.',
    'count_error_cutoff' => 'Vos données ont été enregistrées, mais pas le nombre d\'invités : le délai pour le modifier est dépassé.',
    'count_error_closed' => 'Vos données ont été enregistrées, mais le nombre d\'invités n\'a pas pu être modifié. Appelez-nous.',
    'count_error_stale' => 'Vos données ont été enregistrées, mais pas le nombre d\'invités : la réservation a changé pendant que cette page était ouverte. Rechargez-la.',

    // L'invitation, vue depuis la liste de l'hôte (T6, `specs/celebracion-e-invitacion.md` §4.7).
    'invite' => [
        'repeated' => 'Cette famille a répondu plusieurs fois. Nous vous montrons sa dernière réponse.',
        'dismissed' => "C'est fait : ce n'est plus dans votre liste.",
        'overflow_title' => 'Des réponses ne rentrent plus',
        'overflow' => "{1} Une famille a dit qu'elle venait et il ne reste plus de fiche pour son enfant : augmentez le nombre d'invités ou prévenez-la.|[2,*] :count réponses ne rentrent plus : augmentez le nombre d'invités ou prévenez ces familles.",
        'theme_confeti' => 'Confettis',
        'theme_fiesta' => 'Fête',
        'theme_sereno' => 'Sobre',
        'rejected_title' => "Nous n'avons pas enregistré cela",
        'rejected' => "Les liens et les adresses e-mail ne sont pas acceptés dans « De qui est l'anniversaire » ni dans « Invités par » : l'invitation est publiée sur notre site et n'importe qui pourrait cliquer dessus. Retirez-les et enregistrez à nouveau.",

        // Le rappel (T6·6, §4.7). Rien n'est envoyé : nous n'avons pas l'e-mail du parent. Cela écrit
        // le message et l'hôte le colle là où il a déjà partagé le lien.
        'remind_names' => '{1} Nommer la famille qui manque|[2,*] Nommer les :count familles qui manquent',
        'remind_last' => '{1} Vous l’avez écrit une fois, la dernière le :when|[2,*] Vous l’avez écrit :count fois, la dernière le :when',
        'reminder_text' => "Il nous manque des réponses pour l'anniversaire de :name. Si vous ne nous avez pas encore dit si vous venez, cela prend un instant ici :",
        'reminder_text_generic' => 'Il nous manque des réponses pour notre fête. Si vous ne nous avez pas encore dit si vous venez, cela prend un instant ici :',
        'reminder_names' => 'Il nous manque : :names.',
        'reminder_deadline' => "On peut répondre jusqu'au :when",
    ],
];
