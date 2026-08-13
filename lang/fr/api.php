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
    ],
];
