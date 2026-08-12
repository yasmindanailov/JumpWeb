<?php

namespace App\Domain\Content\Services;

/**
 * Contenido real de la política de cookies (2.ª capa, #219). FUENTE ÚNICA usada por:
 *   - el seeder (`LandingContentSeeder::seedPages`, instalación limpia), y
 *   - la migración de reparación (`..._refresh_cookie_policy_content`), que reemplaza el listado
 *     `[PENDIENTE]` heredado en BD ya existentes SOLO si sigue presente (idempotente, no pisa
 *     ediciones de la clienta — patrón de reparación de datos legacy).
 *
 * Estructura = la de `pages.body`: por idioma, una lista de secciones `{h, p}`. Los datos fiscales
 * del responsable van como tokens `:legal_name/:legal_nif/:legal_address/:legal_email`, que
 * `LegalIdentity::interpolate()` sustituye EN EL RENDER por los settings de `/admin/settings`.
 *
 * ⚠️ El texto es técnico-orientativo y refleja las cookies REALES del sitio; la redacción legal
 * definitiva la valida la asesoría de la clienta. Marca `[PENDIENTE: …]` donde hace falta un dato
 * que debe aportar la clienta (p. ej. la garantía de transferencia del proveedor del feed social).
 */
class CookiePolicyContent
{
    /** @return array<string,string> */
    public static function title(): array
    {
        return ['es' => 'Política de cookies', 'en' => 'Cookie policy', 'fr' => 'Politique de cookies'];
    }

    /** @return array<string,array<int,array{h:string,p:string}>> */
    public static function body(): array
    {
        return [
            'es' => [
                ['h' => '¿Qué son las cookies?', 'p' => 'Las cookies son pequeños archivos que un sitio web guarda en tu dispositivo. Sirven para que la web funcione correctamente, para recordar tus preferencias y, en algunos casos, para mostrar contenido de terceros (como un mapa o un feed de redes sociales). Algunas son imprescindibles para prestarte el servicio; otras solo se instalan si tú lo autorizas.'],
                ['h' => '¿Quién es el responsable?', 'p' => 'El responsable del tratamiento es :legal_name, con NIF :legal_nif y domicilio en :legal_address. Para cualquier consulta sobre cookies o protección de datos puedes escribir a :legal_email.'],
                ['h' => 'Cookies técnicas y de seguridad (necesarias)', 'p' => 'Son imprescindibles para que la web funcione y están exentas de consentimiento. Usamos una cookie de sesión propia (unas 2 horas de duración) que sostiene tu navegación, el inicio de sesión y el carrito de compra; una cookie de seguridad «XSRF-TOKEN» que protege los formularios frente a falsificaciones; y, solo si marcas «recuérdame» al iniciar sesión, una cookie de permanencia que mantiene tu sesión entre visitas. Si rellenas el formulario de registro, el sistema antifraude Cloudflare Turnstile puede instalar una cookie de seguridad temporal para distinguir a personas de robots.'],
                ['h' => 'Mapa de ubicación (Google Maps)', 'p' => 'En la página de inicio y en la de contacto mostramos un mapa de Google Maps, pero solo se carga si das tu consentimiento a la categoría «mapa». Al cargarlo, Google LLC puede instalar cookies propias en tu navegador con finalidades de funcionamiento, seguridad y, en su caso, medición. Mientras no lo autorices, verás un aviso en lugar del mapa y no se instala ninguna cookie de Google.'],
                ['h' => 'Contenido de redes sociales', 'p' => 'En la galería podemos mostrar nuestras últimas publicaciones de Instagram o TikTok mediante un widget de un proveedor externo (SnapWidget o LightWidget). Solo se carga si das tu consentimiento a la categoría «redes sociales»; al hacerlo, el proveedor del widget puede instalar sus propias cookies. Mientras no lo autorices, mostramos una galería estática y no se instala ninguna cookie de terceros.'],
                ['h' => 'Pasarela de pago (Redsys)', 'p' => 'Cuando realizas un pago, te redirigimos a la pasarela bancaria segura Redsys, que puede instalar cookies técnicas en su propio dominio para procesar la operación. Son necesarias para completar la compra que tú inicias y se rigen por la política de Redsys.'],
                ['h' => 'Transferencias internacionales de datos', 'p' => 'Si activas el mapa o el contenido de redes sociales, algunos proveedores pueden tratar datos fuera del Espacio Económico Europeo. Google LLC (mapa) y Cloudflare, Inc. (sistema antifraude) están adheridos al marco de adecuación EU-US Data Privacy Framework, que ofrece garantías para la transferencia a Estados Unidos. Respecto al proveedor del feed social, la garantía de transferencia aplicable es [PENDIENTE: confirmar adhesión al Data Privacy Framework o cláusulas contractuales tipo].'],
                ['h' => '¿Cómo aceptar, configurar o retirar el consentimiento?', 'p' => 'La primera vez que visitas la web te mostramos un aviso para que puedas aceptar todas las cookies, rechazarlas o configurarlas por categoría. Puedes cambiar o retirar tu decisión en cualquier momento, con la misma facilidad con la que la diste, desde el enlace «Configuración de cookies» del pie de página. También puedes bloquear o eliminar las cookies ya instaladas desde la configuración de tu navegador.'],
                ['h' => 'Plazo de conservación del consentimiento', 'p' => 'Conservamos tu decisión durante un máximo de 24 meses; transcurrido ese plazo te volveremos a preguntar. También te lo volveremos a pedir antes si cambian las finalidades, los proveedores o esta política de cookies.'],
                ['h' => 'Más información', 'p' => 'Para conocer en detalle cómo tratamos tus datos personales, consulta nuestra Política de privacidad.'],
            ],
            'en' => [
                ['h' => 'What are cookies?', 'p' => 'Cookies are small files that a website stores on your device. They make the site work properly, remember your preferences and, in some cases, display third-party content (such as a map or a social media feed). Some are essential to provide the service; others are only installed if you allow them.'],
                ['h' => 'Who is responsible?', 'p' => 'The data controller is :legal_name, tax ID :legal_nif, registered at :legal_address. For any question about cookies or data protection you can write to :legal_email.'],
                ['h' => 'Technical and security cookies (necessary)', 'p' => 'These are essential for the website to work and are exempt from consent. We use our own session cookie (around 2 hours) that supports your browsing, login and shopping cart; a security cookie «XSRF-TOKEN» that protects forms against forgery; and, only if you tick «remember me» when logging in, a persistence cookie that keeps your session between visits. If you fill in the registration form, the Cloudflare Turnstile anti-fraud system may install a temporary security cookie to tell humans from bots.'],
                ['h' => 'Location map (Google Maps)', 'p' => 'On the home and contact pages we show a Google Maps map, but it only loads if you consent to the «map» category. When loaded, Google LLC may install its own cookies in your browser for operation, security and, where applicable, measurement purposes. Until you allow it, you will see a notice instead of the map and no Google cookie is installed.'],
                ['h' => 'Social media content', 'p' => 'In the gallery we may show our latest Instagram or TikTok posts through a third-party widget (SnapWidget or LightWidget). It only loads if you consent to the «social media» category; when you do, the widget provider may install its own cookies. Until you allow it, we show a static gallery and no third-party cookie is installed.'],
                ['h' => 'Payment gateway (Redsys)', 'p' => 'When you make a payment, we redirect you to the secure bank gateway Redsys, which may install technical cookies on its own domain to process the transaction. They are necessary to complete the purchase you start and are governed by the Redsys policy.'],
                ['h' => 'International data transfers', 'p' => 'If you enable the map or social media content, some providers may process data outside the European Economic Area. Google LLC (map) and Cloudflare, Inc. (anti-fraud) are certified under the EU-US Data Privacy Framework, which provides safeguards for transfers to the United States. For the social feed provider, the applicable transfer safeguard is [PENDING: confirm Data Privacy Framework certification or standard contractual clauses].'],
                ['h' => 'How to accept, configure or withdraw consent', 'p' => 'The first time you visit the site we show a notice so you can accept all cookies, reject them or configure them by category. You can change or withdraw your decision at any time, as easily as you gave it, from the «Cookie settings» link in the footer. You can also block or delete already installed cookies from your browser settings.'],
                ['h' => 'How long we keep your consent', 'p' => 'We keep your decision for a maximum of 24 months; after that we will ask you again. We will also ask again sooner if the purposes, the providers or this cookie policy change.'],
                ['h' => 'More information', 'p' => 'To learn in detail how we handle your personal data, see our Privacy policy.'],
            ],
            'fr' => [
                ['h' => 'Que sont les cookies ?', 'p' => 'Les cookies sont de petits fichiers qu\'un site web enregistre sur ton appareil. Ils permettent au site de fonctionner correctement, de mémoriser tes préférences et, dans certains cas, d\'afficher du contenu de tiers (comme une carte ou un fil de réseaux sociaux). Certains sont indispensables pour fournir le service ; d\'autres ne sont installés que si tu les autorises.'],
                ['h' => 'Qui est le responsable ?', 'p' => 'Le responsable du traitement est :legal_name, NIF :legal_nif, domicilié à :legal_address. Pour toute question sur les cookies ou la protection des données, tu peux écrire à :legal_email.'],
                ['h' => 'Cookies techniques et de sécurité (nécessaires)', 'p' => 'Ils sont indispensables au fonctionnement du site et sont exemptés de consentement. Nous utilisons notre propre cookie de session (environ 2 heures) qui soutient ta navigation, ta connexion et ton panier ; un cookie de sécurité «XSRF-TOKEN» qui protège les formulaires contre la falsification ; et, seulement si tu coches «se souvenir de moi» à la connexion, un cookie de persistance qui maintient ta session entre les visites. Si tu remplis le formulaire d\'inscription, le système anti-fraude Cloudflare Turnstile peut installer un cookie de sécurité temporaire pour distinguer les personnes des robots.'],
                ['h' => 'Carte de localisation (Google Maps)', 'p' => 'Sur la page d\'accueil et la page de contact, nous affichons une carte Google Maps, mais elle ne se charge que si tu consens à la catégorie «carte». Lors du chargement, Google LLC peut installer ses propres cookies dans ton navigateur à des fins de fonctionnement, de sécurité et, le cas échéant, de mesure. Tant que tu ne l\'autorises pas, tu verras un avis à la place de la carte et aucun cookie de Google n\'est installé.'],
                ['h' => 'Contenu de réseaux sociaux', 'p' => 'Dans la galerie, nous pouvons afficher nos dernières publications Instagram ou TikTok via un widget d\'un fournisseur externe (SnapWidget ou LightWidget). Il ne se charge que si tu consens à la catégorie «réseaux sociaux» ; le cas échéant, le fournisseur du widget peut installer ses propres cookies. Tant que tu ne l\'autorises pas, nous affichons une galerie statique et aucun cookie de tiers n\'est installé.'],
                ['h' => 'Passerelle de paiement (Redsys)', 'p' => 'Lorsque tu effectues un paiement, nous te redirigeons vers la passerelle bancaire sécurisée Redsys, qui peut installer des cookies techniques sur son propre domaine pour traiter l\'opération. Ils sont nécessaires pour finaliser l\'achat que tu engages et sont régis par la politique de Redsys.'],
                ['h' => 'Transferts internationaux de données', 'p' => 'Si tu actives la carte ou le contenu de réseaux sociaux, certains fournisseurs peuvent traiter des données hors de l\'Espace économique européen. Google LLC (carte) et Cloudflare, Inc. (anti-fraude) adhèrent au cadre EU-US Data Privacy Framework, qui offre des garanties pour le transfert vers les États-Unis. Concernant le fournisseur du fil social, la garantie de transfert applicable est [À COMPLÉTER : confirmer l\'adhésion au Data Privacy Framework ou des clauses contractuelles types].'],
                ['h' => 'Comment accepter, configurer ou retirer le consentement', 'p' => 'Lors de ta première visite, nous affichons un avis pour que tu puisses accepter tous les cookies, les refuser ou les configurer par catégorie. Tu peux modifier ou retirer ta décision à tout moment, aussi facilement que tu l\'as donnée, depuis le lien «Configuration des cookies» en pied de page. Tu peux aussi bloquer ou supprimer les cookies déjà installés depuis les réglages de ton navigateur.'],
                ['h' => 'Durée de conservation du consentement', 'p' => 'Nous conservons ta décision pendant 24 mois maximum ; passé ce délai, nous te redemanderons. Nous te le redemanderons aussi plus tôt si les finalités, les fournisseurs ou cette politique de cookies changent.'],
                ['h' => 'Plus d\'informations', 'p' => 'Pour savoir en détail comment nous traitons tes données personnelles, consulte notre Politique de confidentialité.'],
            ],
        ];
    }
}
