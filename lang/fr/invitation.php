<?php

/*
 * L'INVITATION numérique à une fête (`docs/specs/celebracion-e-invitacion.md` §4.6, T5).
 *
 * Lue par UN INCONNU : le lien circule dans le groupe de toute une classe, sur une conversation de
 * parents. Celui qui l'ouvre n'a jamais utilisé ce site et ne connaît peut-être même pas le parc :
 * le ton explique au lieu de supposer. Vit dans les trois langues du client, jamais dans `admin.*`
 * (`DECISIONES #154`).
 */
return [
    'title' => 'Une invitation',

    'badge' => 'Tu es invité',
    'heading' => 'Viens à mon anniversaire !',

    'age' => 'Il fête ses :count an|Il fête ses :count ans',

    'when' => 'Quand',
    'host' => 'Invité par',
    'where' => 'Où',
    'directions' => 'Comment venir',
    'menu' => 'Ce qu’on mange',

    'calendar' => [
        'title' => 'Pour ne pas oublier',
        'add' => 'Ajouter au calendrier',
        'summary' => 'Anniversaire de :name',
    ],
    'og' => [
        'title' => ':name fête ses :age ans',
        'title_no_age' => 'L’anniversaire de :name',
        'description' => 'À :time chez :business. Dis-nous si vous venez.',
        'description_no_time' => 'Chez :business. Dis-nous si vous venez.',
    ],
    'menu_more' => 'Voir ce qu’il contient',

    'soon' => [
        'title' => 'Pour dire si vous venez',
        'open' => 'Tu pourras bientôt confirmer depuis cette page. En attendant, préviens la personne qui t’a invité.',
        'closed' => 'Le délai pour confirmer est passé, mais les informations de la fête restent ici.',
    ],

    'field' => 'Nom et prénom de l’enfant',
    'field_hint' => 'Par exemple : Martina Serra López',
    'yes' => 'Oui, il vient',
    'no' => 'On ne peut pas',
    'antibot_label' => 'Vérification de sécurité',

    'done' => [
        'yes_title' => 'On compte sur vous !',
        'yes' => 'Nous l’avons dit à la personne qui organise la fête. À bientôt, :name.',
        'yes_generic' => 'Nous l’avons dit à la personne qui organise la fête. À bientôt !',
        'no_title' => 'Merci de nous prévenir',
        'no' => 'Nous l’avons dit à la personne qui organise la fête. Ce sera pour une prochaine fois.',
        'refused_title' => 'Nous n’avons pas pu l’enregistrer',
        'antibot' => 'La vérification de sécurité n’a pas abouti. Réessaie ; si cela ne marche toujours pas, préviens directement la personne qui t’a invité.',
    ],

    'refused' => [
        'closed' => 'Cette fête n’accepte plus de réponses. Si tu penses que c’est une erreur, parles-en à la personne qui t’a invité.',
        'cutoff' => 'Le délai pour confirmer est passé. Préviens directement la personne qui t’a invité.',
        'no_name' => 'Nous avons besoin du nom de l’enfant pour pouvoir l’ajouter.',
        'too_many' => 'Cette invitation a reçu trop de réponses d’affilée. Réessaie plus tard.',
    ],

    'closed' => [
        'title' => 'Le délai pour confirmer est passé',
        'text' => 'Les informations de la fête restent ici. Si tu n’as encore rien dit, parles-en à la personne qui t’a invité.',
    ],

    'privacy' => [
        'text' => 'Ce que tu écris ici est transmis à la personne qui organise la fête, pour qu’elle sache qui vient, et au parc, pour préparer la journée. Nous ne l’utilisons pour rien d’autre et le supprimons 14 jours après la fête.',
        'link' => 'Politique de confidentialité',
    ],

    'receipt' => [
        'cta' => 'Laisser ses informations (2 minutes)',
        'hint' => 'Ce lien est valable 2 heures. Si tu le laisses passer, ce n’est pas grave : il est déjà inscrit.',
        'title' => 'C’est noté',
        'badge' => 'Confirmé',
        'heading' => 'On compte sur :name',
        'lede' => 'Si tu veux, dis-nous encore deux choses. Tout est facultatif.',
        'save' => 'Enregistrer',
        'saved_title' => 'Enregistré',
        'saved' => 'Nous le transmettons à la personne qui organise la fête.',
        'closed' => 'Cette fête n’accepte plus de modifications. Ce que tu nous avais dit reste enregistré.',

        'g2_title' => 'Qui vient',
        'g2_lede' => 'Rien n’est obligatoire. Remplis seulement ce que tu veux nous dire.',
        'g2_privacy' => 'Si tu nous parles d’une allergie ou d’un autre besoin, la personne qui organise la fête et l’équipe du parc le verront, pour en tenir compte ce jour-là. Rien d’autre.',

        'g3_title' => 'Tu restes avec lui ?',
        'g3' => [
            'with_adult_title' => 'Je reste',
            'with_adult' => '— Rien à signer. Tu restes au parc pendant la fête et tu t’identifies à l’entrée.',
            'alone_title' => 'Je le dépose',
            'alone' => '— Il nous faut alors ta signature : deux minutes, ici même.',
            'unknown_title' => 'Je ne sais pas encore',
            'unknown' => '— Signe au cas où, ou règle ça à l’entrée le jour même.',
        ],
        'g3_sign' => 'Signer maintenant',
        'g3_signed_title' => 'Déjà signé',
        'g3_signed' => 'Nous avons ta signature pour cet enfant. Tu n’as rien d’autre à faire.',
    ],
];
