<?php

/*
 * L'INVITATION numérique à une fête (`docs/specs/celebracion-e-invitacion.md` §4.6, T5). Lue par UN INCONNU : le
 * lien circule dans le groupe de toute une classe. Trois langues du client, jamais dans `admin.*` (`DECISIONES
 * #154`). Depuis le T2 de `specs/fiesta-sistema-nuevo.md`, la page est habillée par le nouveau système
 * (`fiesta.php`) : il ne reste ici que ce que le contrôleur et le domaine lisent encore.
 */
return [
    'calendar' => [
        'summary' => 'Anniversaire de :name',
    ],
    'og' => [
        'title' => ':name fête ses :age ans',
        'title_no_age' => 'L’anniversaire de :name',
        'description' => 'À :time chez :business. Dis-nous si vous venez.',
        'description_no_time' => 'Chez :business. Dis-nous si vous venez.',
    ],

    'antibot_label' => 'Vérification de sécurité',

    'done' => [
        'yes_generic' => 'Nous l’avons dit à la personne qui organise la fête. À bientôt !',
        'no' => 'Nous l’avons dit à la personne qui organise la fête. Ce sera pour une prochaine fois.',
        'antibot' => 'La vérification de sécurité n’a pas abouti. Réessaie ; si cela ne marche toujours pas, préviens directement la personne qui t’a invité.',
    ],

    'refused' => [
        'closed' => 'Cette fête n’accepte plus de réponses. Si tu penses que c’est une erreur, parles-en à la personne qui t’a invité.',
        'cutoff' => 'Le délai pour confirmer est passé. Préviens directement la personne qui t’a invité.',
        'no_name' => 'Nous avons besoin du nom de l’enfant pour pouvoir l’ajouter.',
        'too_many' => 'Cette invitation a reçu trop de réponses d’affilée. Réessaie plus tard.',
    ],

    'receipt' => [
        'save' => 'Enregistrer',
    ],
];
