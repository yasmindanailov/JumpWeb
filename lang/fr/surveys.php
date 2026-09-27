<?php

// Les enquêtes envoyées au client par e-mail (`docs/specs/encuestas.md` §4.3, T3) : l'e-mail du lendemain et la page
// qu'ouvre son bouton. Lu par un client dans SA langue, sans se connecter. E-mail de service : rien de commercial.
// `notice` et `page.text_hint` sont L'AVIS D'ANONYMAT (`#754`, §4.7) : texte du produit, fixe, non modifiable.
return [
    'notice' => 'Personne au parc ne verra ton nom à côté de tes réponses, et au bout de 90 jours elles sont entièrement détachées de toi.',
    'mail' => [
        'badge' => 'Ton avis',
        'headline' => "C'était comment hier au parc ?",
        'subject' => "C'était comment hier au parc ?",
        'preheader' => 'Deux minutes pour nous aider à progresser. Sans compte ni mot de passe.',
        'line1' => "Tu étais hier à :park et nous aimerions savoir comment ça s'est passé. Juste quelques questions.",
        'line2' => "Ça prend deux minutes et tu n'as pas besoin de te connecter. C'est uniquement pour améliorer le parc, rien d'autre.",
        'action' => 'Répondre',
        'optout' => "Je ne veux plus recevoir d'enquêtes",
    ],
    'page' => [
        'title' => 'Ton avis',
        'badge' => 'Enquête',
        'intro_default' => 'Quelques questions sur ta visite. Merci pour ton temps.',
        'required' => 'obligatoire',
        'yes' => 'Oui',
        'no' => 'Non',
        'text_placeholder' => 'Écris ici (facultatif)',
        'notice_title' => 'Elle est anonyme',
        'text_hint' => "Si tu veux rester anonyme, n'écris ni ton nom ni tes données personnelles.",
        'submit' => 'Envoyer mes réponses',
        'error_required' => 'Réponds à cette question, s\'il te plaît.',
        'error_invalid' => 'Cette réponse ne convient pas à cette question.',
        'thanks_title' => 'Merci !',
        'thanks' => 'Tes réponses nous aident à améliorer :park.',
        'optout_title' => "Ne plus recevoir d'enquêtes",
        'optout_text' => "Si tu appuies sur le bouton, nous ne t'enverrons plus d'e-mails d'enquête. Tu pourras les réactiver depuis « Mon compte → Confidentialité ».",
        'optout_button' => "Plus d'enquêtes",
        'optout_done_title' => "C'est fait",
        'optout_done' => "Nous ne t'enverrons plus d'enquêtes par e-mail.",
    ],
];
