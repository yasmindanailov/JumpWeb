<?php

return [
    'close' => 'Fermer',

    'nav' => [
        'hello' => 'Bonjour, :name',
        'login' => 'Se connecter',
        'sign_out' => 'Se déconnecter',
        'pending_form' => 'Tu as un formulaire en attente',
    ],

    'sidecart' => [
        // ⚠️⚠️ **Aquí vivían `next` y `no_upcoming`, y se RETIRARON el 2026-08-28**
        // (`specs/identidad-qr-puerta.md` §9.7 C·2, `DECISIONES #217`): eran la sub-línea de la
        // próxima reserva bajo el nombre, que se decía en DOS sitios —el bloque de cuenta y el índice
        // del área— y el hueco lo ocupa ahora el atajo «Mi QR». El índice sigue enseñándola.
        // ▶ Se van también del ARRANQUE, y eso vale más que el borrado: este grupo NO se poda por
        // sesión —el bloque cambia de cara sin recargar— así que viajaban en **todas** las páginas
        // públicas para no pintarse nunca. Con ellas se fue `panel.js::sublineOf()` y sus casos.
        'form_pending_one' => 'Un formulaire est en attente pour :product',
        'form_pending_many' => 'Tu as :count formulaires en attente',
        'extras_invite' => 'Ajoute des extras à ta fête :product',
        'guest_hello' => 'Salut, sauteur·se !',
        'guest_sub' => 'Connecte-toi pour garder tes réservations.',
        'upcoming_count' => ':count réservations à venir',
        // Sidebar v2 — petit tag affiché quand le compte se réduit à l'entrée du flux.
    ],

    'account' => [
        'title' => 'Mon compte',
        'subtitle' => 'Gérez vos informations, votre accès et votre confidentialité.',
        'wrong_password' => 'Votre mot de passe actuel est incorrect.',
        // La salida de quien entró con Google y no tiene contraseña (art. 12.2, `#344`).
        'no_password' => "Tu t'es connecté avec Google et tu n'as pas de mot de passe ? Crées-en un et tu pourras le faire : nous t'envoyons un lien par e-mail.",
        'profile' => [
            'title' => 'Vos informations',
            'intro' => "Mettez à jour votre nom, téléphone et langue. Si vous changez d'e-mail, nous enverrons un lien à la nouvelle boîte : le changement est appliqué quand vous le confirmez depuis là-bas.",
            'name' => 'Nom et prénom',
            'email' => 'E-mail',
            'phone' => 'Téléphone',
            'locale' => 'Langue',
            'current_password' => 'Mot de passe actuel',
            'email_change_hint' => "Nécessaire uniquement si vous changez d'e-mail. Le changement n'est PAS appliqué tant que vous ne le confirmez pas depuis la nouvelle boîte (nous vous enverrons un lien).",
            'save' => 'Enregistrer les modifications',
            'saving' => 'Enregistrement…',
            'pending_email_title' => "Changement d'e-mail en attente",
            'pending_email_msg' => 'Nous avons envoyé un lien de confirmation à :email. Il expire dans :minutes min. En attendant, votre compte utilise toujours :current.',
            'pending_email_resend' => 'Renvoyer le lien',
            'pending_email_cancel' => 'Annuler le changement',
        ],
        'password' => [
            'title' => 'Changer le mot de passe',
            'intro' => 'Utilisez un mot de passe long et unique.',
            'current' => 'Mot de passe actuel',
            'new' => 'Nouveau mot de passe',
            'confirm' => 'Répétez le nouveau mot de passe',
            'save' => 'Mettre à jour le mot de passe',
            'saving' => 'Enregistrement…',
            'show' => 'Afficher le mot de passe',
            'hide' => 'Masquer le mot de passe',
        ],
        'sessions' => [
            'title' => 'Sessions',
            'intro' => "Si vous pensez que quelqu'un d'autre utilise votre compte, déconnectez-vous sur les autres appareils.",
            'current_password' => 'Mot de passe actuel',
            'logout_others' => 'Se déconnecter des autres appareils',
            'working' => 'Déconnexion…',
            // Las cuentas externas vinculadas y su salida (`specs/auth-con-google.md` §8).
            'identities_title' => 'Comptes liés',
            'unlink' => 'Dissocier',
            'link_google' => 'Lier mon compte Google',
        ],
        'privacy' => [
            'title' => 'Confidentialité et données',
            'intro' => 'Téléchargez une copie de vos données ou supprimez votre compte.',
            'consents_title' => 'Vos consentements',
            'no_consents' => 'Aucun consentement enregistré.',
            'consent_types' => [
                'privacy' => 'Politique de confidentialité',
                'terms' => 'Conditions générales',
                'waiver' => 'Décharge de responsabilité',
                'marketing' => 'Communications commerciales',
            ],
            'export_btn' => 'Télécharger mes données',
            'consent_revoked' => 'retiré le',
            'marketing_label' => 'Je veux recevoir les actualités et les offres du parc.',
            'marketing_hint' => "Tu peux le retirer quand tu veux, en un clic. Nous gardons la date pour qu'il en reste une trace, et nous cessons de t'écrire.",
            'delete_title' => 'Supprimer mon compte',
            'delete_intro' => 'Nous supprimons définitivement votre nom, e-mail, téléphone et mot de passe, et vous déconnectons. Par obligation légale, nous conservons les données minimales de vos commandes (sans votre identité) pour la facturation. Cette action est irréversible.',
            'delete_password' => 'Mot de passe actuel',
            'delete_confirm' => 'Voulez-vous vraiment supprimer votre compte ? Action définitive.',
            'delete_confirm_yes' => 'Oui, supprimer',
            'delete_confirm_no' => 'Annuler',
            'delete_btn' => 'Supprimer mon compte',
            'waiver' => [
                'title' => 'Décharge de responsabilité',
                'status_external' => 'Le parc la gère en dehors de ce site.',
                'status_unsigned' => 'Vous ne l\'avez pas encore signée.',
                'status_current' => 'Signée, version en vigueur (v:version).',
                'status_outdated' => 'Vous avez signé une version antérieure du texte : vous pouvez entrer, mais merci d\'accepter la nouvelle.',
                'sign_btn' => 'Signer',
                'signing' => 'Signature…',
                'signed_ok' => 'Signature enregistrée ✓',
                'declared' => 'déclarée à l\'accueil',
                'pdf' => 'PDF',
                'pending_notice' => 'Votre décharge de responsabilité est en attente.',
                'pending_cta' => 'La signer',
                'status_awaiting_verification' => 'Votre décharge de responsabilité sera signée dès que vous aurez vérifié votre e-mail.',
            ],
            'deleting' => 'Suppression…',
        ],
        // Phase 6 · mineurs à charge (`specs/menores-a-cargo.md` §9.8) : la zone et ses cartes, avec session.
        'dependents' => [
            'title' => 'Mineurs à ma charge',
            'intro' => 'Déclarez les mineurs dont vous êtes responsable et signez la décharge au nom de chacun. À l\'entrée, seuls leur âge et l\'état de leur décharge sont visibles, jamais leur nom.',
            'empty' => 'Vous n\'avez encore déclaré aucun mineur.',
            'add_title' => 'Ajouter un mineur',
            'name' => 'Prénom',
            'name_hint' => 'Le prénom que vous utilisez à la maison suffit : vous seul le verrez.',
            'surname' => 'Nom de famille',
            'relationship' => 'Quel est votre lien ?',
            'relationship_choose' => 'Choisissez une option',
            'relationship_hint' => 'Nous en avons besoin pour accepter que vous signiez la décharge en son nom.',
            'relationship_father' => 'Père',
            'relationship_mother' => 'Mère',
            'relationship_legal_guardian' => 'Tuteur ou tutrice légale',
            'relationship_grandparent' => 'Grand-parent',
            'relationship_other' => 'Autre',
            'born_on' => 'Date de naissance',
            'accept_waiver' => 'J\'ai lu et j\'accepte la décharge de responsabilité en son nom.',
            'add' => 'Ajouter',
            'adding' => 'Ajout…',
            // Le formulaire d'ajout se DÉPLIE depuis un bouton (owner, 2026-08-28) : `add_title`
            // nomme le déclencheur ET la section qu'il ouvre ; `add_cancel` la replie.
            'add_cancel' => 'Annuler',
            'age' => ':age ans',
            'adult' => 'Il a désormais 18 ans : votre décharge ne le couvre plus.',
            'remove' => 'Retirer',
            'removing' => 'Retrait…',
            'remove_confirm' => 'Retirer :name de votre compte ? Si vous avez signé la décharge en son nom, ce registre est conservé.',
            'remove_confirm_yes' => 'Oui, retirer',
            'remove_confirm_no' => 'Annuler',
            'waiver_unsigned' => 'Décharge non signée en son nom.',
            'waiver_awaiting_verification' => 'Vous pourrez signer sa décharge dès que vous aurez vérifié votre e-mail.',
            'waiver_pending_notice' => 'L’un de vos mineurs n’a pas sa décharge signée.',
            'waiver_current' => 'Décharge signée en son nom, version en vigueur (v:version).',
            'waiver_outdated' => 'Vous avez signé en son nom une version antérieure du texte : merci d\'accepter la nouvelle.',
            // L'attribution de BILLETS aux mineurs dans le parcours (lot 4) : le sélecteur des étapes 3
            // et 4, l'avis après connexion, la ligne du récapitulatif et la carte de réservation.
            'for_label' => 'Pour :',
            'assigned_none' => 'Attribué à aucun mineur.',
            'assigned_show' => 'Voir pour qui',
            'assigned_hide' => 'Masquer pour qui',
            // La liste est paginée CÔTÉ CLIENT (elle arrive entière de `GET /me/dependents`) et la
            // barre de pages n'apparaît que s'il y a plus d'une page.
            'pagination' => [
                'label' => 'Pagination des mineurs',
                'prev' => 'Précédents',
                'next' => 'Suivants',
                'page' => 'Page :current sur :last',
            ],
        ],
        // Le QR (Phase 6 · A, `specs/identidad-qr-puerta.md` §9.6 B·2) : le voir, le dicter, le télécharger, le renouveler.
        // ⚠️ Le mot côté client est « QR » (`[DECIDIDO owner, 2026-08-28]`, §9.7 C·6). **« carte » est
        // FÉMININ et « QR » MASCULIN** : les pronoms et les participes ont tourné avec le mot
        // (montre-la → montre-le, imprimée → imprimé, renouvelée → renouvelé, celle → celui).
        'card' => [
            'title' => 'Mon QR',
            'intro' => 'Ton QR t’identifie à l’entrée : montre-le sur ton téléphone ou imprimé. Il ne permet pas de te connecter à ton compte.',
            'alt' => 'Ton QR',
            'token_label' => 'Si la caméra ne lit pas, dicte ce code :',
            'download' => 'Télécharger (PNG)',
            // Un texte d'un AUTRE écran vivait ici (`#565`, brèche 08).
            'hint' => 'Ce code est le tien et ne change pas. Il vaut pour toutes tes réservations.',
            'unavailable' => 'Ce QR ne peut plus être affiché. Renouvelle-le et tu en auras un nouveau immédiatement.',
            'rotate' => 'Renouveler mon QR',
            'rotating' => 'Renouvellement…',
            // ⚠️ El aviso va SIEMPRE visible bajo el botón, no dentro de la confirmación
            // (`specs/identidad-qr-puerta.md` §9.7 C·4): quien no pulsaba nunca llegaba a leer que el
            // QR anterior deja de valer, porque el texto solo existía dentro de `window.confirm`.
            // Y la confirmación vive ya DENTRO del cajón (`[DECIDIDO owner]`, `DECISIONES #217`): la
            // del navegador salía fuera, sin nuestros tres idiomas, y el owner no llegó a verla.
            'rotate_notice' => 'Le QR précédent cessera de fonctionner immédiatement : celui de l’e-mail et toute copie imprimée.',
            'rotate_confirm_title' => 'Veux-tu vraiment le renouveler ?',
            'rotate_confirm_yes' => 'Oui, renouveler',
            'rotate_confirm_no' => 'Annuler',
            'rotated' => 'QR renouvelé. L’ancien ne fonctionne plus.',
            'expired' => 'Ta session a expiré : reconnecte-toi pour voir ton QR.',
        ],
    ],

    'login' => [
        'cta' => 'Se connecter',
        'title' => 'Connexion',
        'email' => 'E-mail',
        'password' => 'Mot de passe',
        'remember' => 'Rester connecté',
        'submit' => 'Se connecter',
        'submitting' => 'Connexion…',
        'forgot' => 'Mot de passe oublié ?',
    ],

    'forgot' => [
        'title' => 'Réinitialisez votre mot de passe',
        'intro' => 'Saisissez votre e-mail et nous vous enverrons un lien pour créer un nouveau mot de passe.',
        'email' => 'E-mail',
        'submit' => 'Envoyer le lien',
        'submitting' => 'Envoi…',
        'back_to_login' => 'Retour à la connexion',
        'sent_title' => 'Vérifiez votre e-mail',
        'sent_msg' => 'Si un compte existe pour cet e-mail, nous avons envoyé un lien pour réinitialiser le mot de passe. Vérifiez aussi vos spams.',
    ],

    'reset' => [
        'eyebrow' => 'Nouveau mot de passe',
        'title' => 'Créez un nouveau mot de passe',
        'intro' => 'Choisissez un nouveau mot de passe pour votre compte.',
        'email' => 'E-mail',
        'password' => 'Nouveau mot de passe',
        'password_confirmation' => 'Répétez le mot de passe',
        'submit' => 'Enregistrer',
        'submitting' => 'Enregistrement…',
    ],

    'register' => [
        'cta' => 'Créer un compte',
        'title' => 'Créez votre compte',
        'subtitle' => 'Nécessaire pour réserver billets et anniversaires.',
        'name' => 'Nom et prénom',
        'email' => 'E-mail',
        'phone' => 'Téléphone',
        // Le téléphone dit À QUOI IL SERT (`#561`).
        'phone_hint' => "Pour t'appeler à propos de ta réservation, quoi qu'il arrive.",
        'password' => 'Mot de passe',
        'password_hint' => 'Au moins 8 caractères. Évitez les mots de passe courants ou compromis.',
        'must_accept' => 'Vous devez accepter cette condition pour continuer.',
        'accept_waiver' => "J'ai lu et j'accepte la décharge de responsabilité.",
        // La décharge se nomme EN ENTIER et toujours pareil (`#561`, `Voz PJP`).
        'waiver_read' => 'Lire la décharge de responsabilité',
        // Voir l'avertissement dans `lang/es/account.php` : c'est un AVIS, pas une case à cocher.
        // Sin marcado desde `#566`: el enlace vive en su propia fila (grieta 13).
        'privacy_notice' => 'En créant votre compte, nous traitons vos données conformément à notre politique de confidentialité.',
        'privacy_read' => 'Lire la politique de confidentialité',
        'submit' => 'Créer le compte',
        'submitting' => 'Création…',
        'fix_errors' => 'Vérifiez ces champs :',
        'bot_check_failed' => "Nous n'avons pas pu vérifier que vous n'êtes pas un robot. Réessayez.",
        'already_exists' => 'Vous avez déjà un compte avec cet e-mail. Connectez-vous pour continuer.',
        'exists_unverified' => 'Vous vous êtes déjà inscrit avec cet e-mail sans le vérifier. Nous venons de renvoyer le lien de vérification.',
        'leave_blank' => 'Laissez ce champ vide',
        'google_cta' => 'Continuer avec Google',
        'or' => 'ou',
    ],

    'google' => [
        'title' => 'Termine ton inscription',
        // ⚠️ Disait «…pour réserver à ton nom», ce qui était FAUX (`#345`) : on ne réserve rien ici —
        // cet écran CRÉE LE COMPTE, ce que dit son propre bouton d'envoi.
        'intro' => 'Google nous a déjà confirmé qui tu es. Il ne manque que ceci pour créer ton compte.',
        'email_label' => 'Ton e-mail',
        'email_hint' => 'Celui de ton compte Google, déjà vérifié.',
        'submit' => 'Créer mon compte',
        'submitting' => 'Création…',
        'expired' => "Trop de temps s'est écoulé depuis ta connexion avec Google, ou cette inscription est déjà terminée.",
        'restart' => 'Recommencer avec Google',
    ],

    'status' => [
        'email-verified' => 'E-mail confirmé ! Votre compte est maintenant actif.',
        'email-already-verified' => 'Votre e-mail était déjà confirmé.',
        'verification-link-sent' => "Nous vous avons renvoyé l'e-mail de vérification. Vérifiez votre boîte de réception (et vos spams).",
        'verification-resend-throttled' => "Nous venons de vous envoyer l'e-mail. Attendez une minute avant d'en demander un autre.",
        'password-reset' => 'Mot de passe mis à jour. Vous pouvez vous connecter.',
        'profile-updated' => 'Informations mises à jour.',
        'password-updated' => 'Mot de passe mis à jour. Nous avons déconnecté vos autres sessions par sécurité.',
        'email-change-requested' => "Nous avons envoyé un lien au nouvel e-mail pour confirmer le changement. En attendant, votre compte continue d'utiliser l'e-mail actuel.",
        'email-change-confirmed' => 'E-mail confirmé. Vous pouvez maintenant l’utiliser pour vous connecter.',
        'email-change-expired' => 'Le lien pour confirmer le changement d’e-mail a expiré. Demandez-le à nouveau si vous voulez encore le changer.',
        'email-change-taken' => "Cet e-mail a été enregistré par un autre compte pendant l'attente de la confirmation. Nous avons annulé le changement ; essayez un autre e-mail.",
        'email-change-cancelled' => 'Changement d’e-mail annulé.',
        'email-change-resent' => 'Nous avons renvoyé le lien de confirmation au nouvel e-mail.',
        'logged-out-others' => 'Vous êtes déconnecté des autres appareils.',
        'account-deleted' => 'Votre compte a été supprimé. Au plaisir de vous revoir.',
        'order-retry-unavailable' => 'Nous ne pouvons plus réessayer ce paiement : la réservation a expiré et la place a été libérée. Tu peux faire une nouvelle réservation quand tu veux.',
        'order-retry-failed' => 'Nous n’avons pas pu démarrer le paiement maintenant. Réessaie dans un instant ; si le problème persiste, écris-nous.',
        // Réservations suspendues (#218) : la relance du paiement est bloquée ; le client peut appeler.
        'order-retry-throttled' => 'Tu essaies trop souvent. Attends une minute et réessaie : ta réservation est toujours conservée.',
        'order-retry-paused' => 'La réservation en ligne est suspendue pour le moment. Appelle-nous et nous finalisons ta réservation par téléphone.',
        'guest-form-saved' => 'Formulaire de réservation enregistré. Merci ! Vous pouvez le modifier à tout moment.',
        'google-linked' => 'Nous avons lié ton compte Google. Tu peux désormais te connecter avec lui.',
        'google-cancelled' => "Tu n'as pas terminé la connexion avec Google. Tu peux réessayer quand tu veux.",
        'google-failed' => "Nous n'avons pas pu te connecter avec Google. Réessaie ; si le problème persiste, connecte-toi avec ton mot de passe ou écris-nous.",
        'google-email-unverified' => "Google ne considère pas cette adresse comme vérifiée, nous ne pouvons donc pas l'utiliser pour t'identifier. Vérifie-la dans ton compte Google, ou inscris-toi avec ton e-mail et un mot de passe.",
        'google-anonymized' => 'Ce compte a été supprimé à la demande de son titulaire et ne peut pas être récupéré. Tu peux en créer un nouveau quand tu veux.',
        'google-provider-conflict' => 'Ton compte est déjà lié à un autre compte Google. Connecte-toi avec celui-là, ou avec ton mot de passe, et écris-nous si tu veux en changer.',
        // ⚠️ Le MIROIR du précédent, et ils ne sont pas interchangeables : celui-là dit « ton compte a
        // déjà une autre clé » ; celui-ci dit « cette clé ouvre déjà un autre compte ».
        'google-provider-taken' => 'Ce compte Google est déjà lié à un autre compte du parc. Connecte-toi avec lui s\'il est à toi, ou lie un autre compte Google.',
        'google-already-linked' => 'Ce compte Google était déjà lié au tien.',
        'google-link-session-changed' => 'Tu as changé de session pendant la liaison de ton compte Google : nous n\'avons rien lié. Réessaie.',
    ],

    'verify' => [
        'title' => 'Confirmez votre e-mail',
        'intro' => 'Nous avons envoyé un lien de confirmation à votre e-mail. Ouvrez-le pour activer votre compte.',
        'sent_to' => 'Nous avons envoyé un e-mail de confirmation à :email. Ouvrez-le pour activer votre compte.',
        'spam_hint' => 'Introuvable ? Vérifiez votre dossier spam ou promotions.',
        'resend' => "Renvoyer l'e-mail",
        'pending_notice' => 'Vous devez vérifier votre adresse e-mail.',
        'resend_in' => 'Renvoyer dans',
        'resending' => 'Envoi…',
        'resends_left' => ':n renvois restants.',
        'resend_limit' => 'Vous avez atteint la limite de renvois. Vérifiez vos spams ou réessayez plus tard.',
        'notice_resend_hint' => 'Pas reçu ? Renvoyez votre e-mail de vérification :',
        'notice_resend_button' => "Renvoyer l'e-mail de vérification",
        'notice_resend_hint_guest' => 'Pas reçu ? Connectez-vous pour pouvoir renvoyer votre e-mail de vérification.',
        'already_have_account' => 'Vous avez déjà un compte ?',
    ],

    'exists_mail' => [
        'subject' => 'Tu as déjà un compte',
        'preheader' => "Aucun nouveau compte n'a été créé. Si c'était toi, connecte-toi ou réinitialise.",
        'greeting' => 'Bonjour !',
        'line1' => "Quelqu'un a tenté de s'inscrire avec ton e-mail. Si c'était toi, tu as déjà un compte : connecte-toi ou réinitialise ton mot de passe.",
        'action' => 'Se connecter',
        'line2' => "Si ce n'était pas toi, tu peux ignorer ce message en toute tranquillité.",
        'badge' => 'Vous avez déjà un compte',
        'headline' => 'Votre compte existe déjà',
    ],

    'social_link_mail' => [
        'providers' => [
            'google' => 'Google',
        ],
        'badge' => 'Compte associé',
        'headline' => 'Vous avez associé un compte',
        'subject' => 'Tu peux te connecter avec :provider',
        'preheader' => "Si ce n'était pas toi, change le mot de passe de ta messagerie et préviens-nous.",
        'greeting' => 'Bonjour !',
        'line1' => 'Tu peux désormais te connecter à ton compte :park avec :provider, en plus de la méthode que tu utilisais avant.',
        'promoted' => "Ton adresse e-mail n'était pas encore vérifiée : nous la considérons vérifiée grâce à :provider et, par sécurité, nous avons fermé les sessions ouvertes et désactivé l'ancien mot de passe. Si tu veux de nouveau un mot de passe, utilise « j'ai oublié mon mot de passe ».",
        'not_you' => "Si ce n'était pas toi, change tout de suite le mot de passe de ta messagerie et préviens-nous.",
        'action' => 'Nous écrire',
    ],

    'orders' => [
        // Las respuestas del pack, BAJO DEMANDA (tanda 3): son datos de un menor
        // (art. 9) y por eso no se pintan solas ni viajan en la lista de pedidos.
        'event_data_show' => 'Voir les données de l\'événement',
        'event_data_hide' => 'Masquer les données de l\'événement',
        'eyebrow' => 'Tes réservations',
        'title' => 'Mes réservations',
        'intro' => 'Consulte tes réservations, leur code et leur statut.',
        'view' => 'Voir mes réservations',
        'empty' => 'Tu n’as pas encore de réservations.',
        // #179: pagination de la liste des commandes.
        'pagination' => [
            'label' => 'Pagination des réservations',
            'prev' => 'Précédents',
            'next' => 'Suivants',
            'page' => 'Page :current sur :last',
        ],
        'item_finished' => 'Terminé',
        'item_cancelled' => 'Annulé',
        'history' => [
            'title' => 'Historique des réservations',
            'cta' => 'Voir l\'historique des réservations',
            'empty' => 'Vos réservations passées et annulées apparaîtront ici.',
        ],
        'order_ref' => 'Commande :code',
        'order_show' => 'Voir la commande',
        'retry_payment' => 'Réessayer le paiement',
        'retry_hint' => 'Nous gardons ta réservation encore quelques minutes au cas où tu voudrais finaliser le paiement.',
        'guest_form_pending' => 'Complétez le formulaire de réservation',
        'guest_form_done' => 'Voir ou modifier le formulaire de réservation',
        'guest_form_extras' => 'Ajoute des extras ou modifie le formulaire',
        'guest_form_past' => 'Voir le formulaire de réservation',
        'guest_form_cancelled' => 'Formulaire de réservation · annulée',
        'manage' => 'Gérer',
        'manage_eyebrow' => 'Ta réservation',
        'manage_title' => 'Gérer ta réservation',
        'manage_intro' => 'Tout changement sur ta réservation (modifier la date ou l’heure, annuler, ajuster le nombre d’invités ou tout autre incident) est traité directement avec notre équipe afin de pouvoir évaluer ton cas et te proposer la meilleure solution possible.',
        'manage_note' => 'Note ou copie ce numéro et fournis-le lorsque tu nous contactes pour que nous puissions t’aider rapidement.',
        'manage_cta' => 'Aller au contact',
    ],

    /** **Mes commandes** — l'ARGENT, par commande. Écran propre : le détail est celui de la COMMANDE
     * et une réservation n'en est pas l'unité. ⚠️ Attention au nom : le groupe `orders` ci-dessus est
     * « Mes réservations » (sa route web est `/mi-cuenta/pedidos`). */
    'purchases' => [
        'guest_minors' => [
            'title' => 'Autorisations des invités',
            'count' => ':count signées',
            'waiver_outdated' => 'version précédente',
            'waiver_missing' => 'signature manquante',
            'hint' => 'Transmettez-le aux parents ou tuteurs : chacun remplit SES données et ne voit pas celles des autres.',
            'share' => 'Partager ou copier le lien',
            'shared' => 'Lien partagé',
            'copied' => 'Lien copié',
            'failed' => 'Impossible de copier. Sélectionnez le lien et copiez-le à la main.',
            'places' => 'places libres : :count',
        ],
        // « Mes paiements », pas « Mes commandes » (`#565`) : cet écran, c'est l'ARGENT.
        'title' => 'Mes paiements',
        'empty' => "Tu n'as encore aucune commande.",
        'ref' => 'Commande :code',
        'show' => 'Voir le détail',
        'hide' => 'Masquer le détail',
        'reservations' => 'Réservations de cette commande',
        'pagination' => [
            'label' => 'Pagination des commandes',
            'prev' => 'Précédentes',
            'next' => 'Suivantes',
            'page' => 'Page :current sur :last',
        ],
    ],
];
