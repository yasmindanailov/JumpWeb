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
 *
 * **T3a de la analítica** (`specs/analitica.md` §4.3, `#678`): tres secciones nuevas —la medición de
 * audiencia PROPIA (exenta, se declara y no se pide), el análisis de uso IDENTIFICADO (categoría
 * `analytics`) y la PUBLICIDAD (categoría `marketing`)— y el párrafo de transferencias que las nombra.
 * Van en constantes públicas porque las lee también la migración quirúrgica que las lleva a una BD ya
 * sembrada (`2026_09_24_120000_cookie_policy_adds_analytics_and_marketing`) y su test, que exige que lo
 * migrado quede EXACTAMENTE como lo sembraría una instalación nueva.
 */
class CookiePolicyContent
{
    /** @var array<string, string> */
    public const AUDIENCE_H = [
        'es' => 'Medición de audiencia propia (exenta de consentimiento)',
        'en' => 'Our own audience measurement (exempt from consent)',
        'fr' => 'Mesure d\'audience propre (exemptée de consentement)',
    ];

    /** @var array<string, string> */
    public const AUDIENCE_P = [
        'es' => 'Para saber cuántas personas visitan la web, qué páginas ven y desde qué campañas llegan, usamos una cookie propia llamada «visitor_id» que dura 13 meses y no se renueva en cada visita. Solo produce estadísticas anónimas para nosotros: no se cruza con otros sitios ni se cede a nadie, y los datos se conservan como máximo 25 meses. Según la Guía de cookies de la AEPD (2024), esta medición está exenta de consentimiento y por eso no te la pedimos; puedes borrar la cookie desde tu navegador cuando quieras.',
        'en' => 'To know how many people visit the site, which pages they see and which campaigns bring them, we use our own cookie called «visitor_id», which lasts 13 months and is not renewed on each visit. It only produces anonymous statistics for us: it is not cross-referenced with other sites nor shared with anyone, and the data is kept for at most 25 months. Under the AEPD cookie guide (2024) this measurement is exempt from consent, so we do not ask for it; you can delete the cookie from your browser whenever you want.',
        'fr' => 'Pour savoir combien de personnes visitent le site, quelles pages elles voient et de quelles campagnes elles viennent, nous utilisons un cookie propre appelé «visitor_id», qui dure 13 mois et n\'est pas renouvelé à chaque visite. Il ne produit que des statistiques anonymes pour nous : il n\'est ni croisé avec d\'autres sites ni cédé à qui que ce soit, et les données sont conservées 25 mois au maximum. Selon le guide des cookies de l\'AEPD (2024), cette mesure est exemptée de consentement, c\'est pourquoi nous ne te le demandons pas ; tu peux supprimer le cookie depuis ton navigateur quand tu veux.',
    ];

    /** @var array<string, string> */
    public const ANALYTICS_H = [
        'es' => 'Análisis de uso identificado (categoría «análisis»)',
        'en' => 'Identified usage analytics («analytics» category)',
        'fr' => 'Analyse d\'usage identifiée (catégorie «analyse»)',
    ];

    /** @var array<string, string> */
    public const ANALYTICS_P = [
        'es' => 'Solo si lo autorizas en la categoría «análisis», vinculamos tu navegación a tu cuenta cuando inicias sesión o compras, para entender cómo usas la web y mejorarla. Con ese mismo permiso podemos usar una herramienta externa de análisis de uso (grabaciones de sesión y mapas de calor), que recibe un identificador cifrado en lugar de tu nombre, sin tu dirección IP y con lo que escribes enmascarado; si está activa, la nombramos más abajo. Mientras no lo autorices, esa herramienta no se carga y tu navegación no se asocia a tu cuenta. Si tienes cuenta, puedes retirar esta vinculación cuando quieras desde «Mi cuenta → Privacidad».',
        'en' => 'Only if you allow it under the «analytics» category, we link your browsing to your account when you log in or buy, to understand how you use the site and improve it. With that same permission we may use an external usage analytics tool (session recordings and heat maps), which receives an encrypted identifier instead of your name, without your IP address and with what you type masked; if it is active, we name it below. Until you allow it, that tool does not load and your browsing is not associated with your account. If you have an account, you can withdraw this link at any time from «My account → Privacy».',
        'fr' => 'Seulement si tu l\'autorises dans la catégorie «analyse», nous lions ta navigation à ton compte lorsque tu te connectes ou achètes, pour comprendre comment tu utilises le site et l\'améliorer. Avec cette même autorisation, nous pouvons utiliser un outil externe d\'analyse d\'usage (enregistrements de session et cartes de chaleur), qui reçoit un identifiant chiffré à la place de ton nom, sans ton adresse IP et avec ce que tu écris masqué ; s\'il est actif, nous le nommons plus bas. Tant que tu ne l\'autorises pas, cet outil ne se charge pas et ta navigation n\'est pas associée à ton compte. Si tu as un compte, tu peux retirer ce lien à tout moment depuis «Mon compte → Confidentialité».',
    ];

    /** @var array<string, string> */
    public const MARKETING_H = [
        'es' => 'Publicidad (categoría «publicidad»)',
        'en' => 'Advertising («advertising» category)',
        'fr' => 'Publicité (catégorie «publicité»)',
    ];

    /** @var array<string, string> */
    public const MARKETING_P = [
        'es' => 'Solo si lo autorizas en la categoría «publicidad», cargamos los píxeles de las plataformas de anuncios en las que hagamos campañas (Google Ads, Meta o TikTok) y les comunicamos las compras que se completan, para saber qué anuncios funcionan. Esas plataformas pueden instalar sus propias cookies y reciben un identificador de la compra y datos de contacto transformados de forma irreversible (hash), nunca en claro. Mientras no lo autorices, no se carga ningún píxel ni se comunica nada. Las plataformas activas en esta web, con la empresa responsable y su garantía de transferencia, se nombran más abajo.',
        'en' => 'Only if you allow it under the «advertising» category, we load the pixels of the advertising platforms where we run campaigns (Google Ads, Meta or TikTok) and report completed purchases to them, to know which ads work. Those platforms may install their own cookies and receive a purchase identifier and contact data transformed irreversibly (hashed), never in the clear. Until you allow it, no pixel loads and nothing is reported. The platforms active on this site, with the responsible company and their transfer safeguard, are named below.',
        'fr' => 'Seulement si tu l\'autorises dans la catégorie «publicité», nous chargeons les pixels des plateformes publicitaires sur lesquelles nous menons des campagnes (Google Ads, Meta ou TikTok) et nous leur communiquons les achats finalisés, pour savoir quelles annonces fonctionnent. Ces plateformes peuvent installer leurs propres cookies et reçoivent un identifiant de l\'achat et des données de contact transformées de façon irréversible (hachage), jamais en clair. Tant que tu ne l\'autorises pas, aucun pixel n\'est chargé et rien n\'est communiqué. Les plateformes actives sur ce site, avec la société responsable et leur garantie de transfert, sont nommées plus bas.',
    ];

    /**
     * El párrafo de transferencias, que nombra las cuatro finalidades. ⚠️ El «[PENDIENTE…]» del proveedor del
     * feed social es anterior a la analítica (`#592`, carril de la web) y se conserva tal cual; la herramienta de
     * análisis y las plataformas de anuncios se nombran en el RENDER de `/cookies` cuando están activas.
     *
     * @var array<string, string>
     */
    public const TRANSFERS_P = [
        'es' => 'Si activas el mapa y las reseñas, el contenido de redes sociales, el análisis de uso identificado o la publicidad, algunos proveedores pueden tratar datos fuera del Espacio Económico Europeo. Google LLC (mapa, reseñas y Google Ads) y Cloudflare, Inc. (sistema antifraude) están adheridos al marco de adecuación EU-US Data Privacy Framework, que ofrece garantías para la transferencia a Estados Unidos. La herramienta de análisis y las plataformas de anuncios activas en esta web se nombran más abajo con su empresa responsable y su garantía de transferencia. Respecto al proveedor del feed social, la garantía de transferencia aplicable es [PENDIENTE: confirmar adhesión al Data Privacy Framework o cláusulas contractuales tipo].',
        'en' => 'If you enable the map and reviews, social media content, identified usage analytics or advertising, some providers may process data outside the European Economic Area. Google LLC (map, reviews and Google Ads) and Cloudflare, Inc. (anti-fraud) are certified under the EU-US Data Privacy Framework, which provides safeguards for transfers to the United States. The analytics tool and the advertising platforms active on this site are named below with the responsible company and their transfer safeguard. For the social feed provider, the applicable transfer safeguard is [PENDING: confirm Data Privacy Framework certification or standard contractual clauses].',
        'fr' => 'Si tu actives la carte et les avis, le contenu de réseaux sociaux, l\'analyse d\'usage identifiée ou la publicité, certains fournisseurs peuvent traiter des données hors de l\'Espace économique européen. Google LLC (carte, avis et Google Ads) et Cloudflare, Inc. (anti-fraude) adhèrent au cadre EU-US Data Privacy Framework, qui offre des garanties pour le transfert vers les États-Unis. L\'outil d\'analyse et les plateformes publicitaires actives sur ce site sont nommés plus bas avec la société responsable et leur garantie de transfert. Concernant le fournisseur du fil social, la garantie de transfert applicable est [À COMPLÉTER : confirmer l\'adhésion au Data Privacy Framework ou des clauses contractuelles types].',
    ];

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
                ['h' => self::AUDIENCE_H['es'], 'p' => self::AUDIENCE_P['es']],
                ['h' => self::ANALYTICS_H['es'], 'p' => self::ANALYTICS_P['es']],
                ['h' => self::MARKETING_H['es'], 'p' => self::MARKETING_P['es']],
                ['h' => 'Mapa y reseñas (Google)', 'p' => 'En la página de inicio mostramos un mapa de Google Maps y las reseñas publicadas en Google, con la foto de quien las escribe, pero solo se cargan si das tu consentimiento a la categoría «mapa y reseñas». Al cargarlos, Google LLC puede instalar cookies propias en tu navegador con finalidades de funcionamiento, seguridad y, en su caso, medición, y las fotos se sirven desde sus servidores. Mientras no lo autorices, verás un aviso en lugar del mapa y de las reseñas, y no se instala ninguna cookie de Google. La valoración media sí se muestra siempre: la consulta nuestro servidor y tu navegador no se conecta a Google para verla.'],
                ['h' => 'Contenido de redes sociales', 'p' => 'En la galería podemos mostrar nuestras últimas publicaciones de Instagram o TikTok mediante un widget de un proveedor externo (SnapWidget o LightWidget). Solo se carga si das tu consentimiento a la categoría «redes sociales»; al hacerlo, el proveedor del widget puede instalar sus propias cookies. Mientras no lo autorices, mostramos una galería estática y no se instala ninguna cookie de terceros.'],
                ['h' => 'Pasarela de pago (Redsys)', 'p' => 'Cuando realizas un pago, te redirigimos a la pasarela bancaria segura Redsys, que puede instalar cookies técnicas en su propio dominio para procesar la operación. Son necesarias para completar la compra que tú inicias y se rigen por la política de Redsys.'],
                ['h' => 'Transferencias internacionales de datos', 'p' => self::TRANSFERS_P['es']],
                ['h' => '¿Cómo aceptar, configurar o retirar el consentimiento?', 'p' => 'La primera vez que visitas la web te mostramos un aviso para que puedas aceptar todas las cookies, rechazarlas o configurarlas por categoría. Puedes cambiar o retirar tu decisión en cualquier momento, con la misma facilidad con la que la diste, desde el enlace «Configuración de cookies» del pie de página. También puedes bloquear o eliminar las cookies ya instaladas desde la configuración de tu navegador.'],
                ['h' => 'Plazo de conservación del consentimiento', 'p' => 'Conservamos tu decisión durante un máximo de 24 meses; transcurrido ese plazo te volveremos a preguntar. También te lo volveremos a pedir antes si cambian las finalidades, los proveedores o esta política de cookies.'],
                ['h' => 'Más información', 'p' => 'Para conocer en detalle cómo tratamos tus datos personales, consulta nuestra Política de privacidad.'],
            ],
            'en' => [
                ['h' => 'What are cookies?', 'p' => 'Cookies are small files that a website stores on your device. They make the site work properly, remember your preferences and, in some cases, display third-party content (such as a map or a social media feed). Some are essential to provide the service; others are only installed if you allow them.'],
                ['h' => 'Who is responsible?', 'p' => 'The data controller is :legal_name, tax ID :legal_nif, registered at :legal_address. For any question about cookies or data protection you can write to :legal_email.'],
                ['h' => 'Technical and security cookies (necessary)', 'p' => 'These are essential for the website to work and are exempt from consent. We use our own session cookie (around 2 hours) that supports your browsing, login and shopping cart; a security cookie «XSRF-TOKEN» that protects forms against forgery; and, only if you tick «remember me» when logging in, a persistence cookie that keeps your session between visits. If you fill in the registration form, the Cloudflare Turnstile anti-fraud system may install a temporary security cookie to tell humans from bots.'],
                ['h' => self::AUDIENCE_H['en'], 'p' => self::AUDIENCE_P['en']],
                ['h' => self::ANALYTICS_H['en'], 'p' => self::ANALYTICS_P['en']],
                ['h' => self::MARKETING_H['en'], 'p' => self::MARKETING_P['en']],
                ['h' => 'Map and reviews (Google)', 'p' => 'On the home page we show a Google Maps map and the reviews published on Google, with their authors\' photos, but they only load if you consent to the «map and reviews» category. When loaded, Google LLC may install its own cookies in your browser for operation, security and, where applicable, measurement purposes, and the photos are served from its servers. Until you allow it, you will see a notice instead of the map and the reviews, and no Google cookie is installed. The average rating is always shown: our server fetches it, and your browser does not connect to Google to display it.'],
                ['h' => 'Social media content', 'p' => 'In the gallery we may show our latest Instagram or TikTok posts through a third-party widget (SnapWidget or LightWidget). It only loads if you consent to the «social media» category; when you do, the widget provider may install its own cookies. Until you allow it, we show a static gallery and no third-party cookie is installed.'],
                ['h' => 'Payment gateway (Redsys)', 'p' => 'When you make a payment, we redirect you to the secure bank gateway Redsys, which may install technical cookies on its own domain to process the transaction. They are necessary to complete the purchase you start and are governed by the Redsys policy.'],
                ['h' => 'International data transfers', 'p' => self::TRANSFERS_P['en']],
                ['h' => 'How to accept, configure or withdraw consent', 'p' => 'The first time you visit the site we show a notice so you can accept all cookies, reject them or configure them by category. You can change or withdraw your decision at any time, as easily as you gave it, from the «Cookie settings» link in the footer. You can also block or delete already installed cookies from your browser settings.'],
                ['h' => 'How long we keep your consent', 'p' => 'We keep your decision for a maximum of 24 months; after that we will ask you again. We will also ask again sooner if the purposes, the providers or this cookie policy change.'],
                ['h' => 'More information', 'p' => 'To learn in detail how we handle your personal data, see our Privacy policy.'],
            ],
            'fr' => [
                ['h' => 'Que sont les cookies ?', 'p' => 'Les cookies sont de petits fichiers qu\'un site web enregistre sur ton appareil. Ils permettent au site de fonctionner correctement, de mémoriser tes préférences et, dans certains cas, d\'afficher du contenu de tiers (comme une carte ou un fil de réseaux sociaux). Certains sont indispensables pour fournir le service ; d\'autres ne sont installés que si tu les autorises.'],
                ['h' => 'Qui est le responsable ?', 'p' => 'Le responsable du traitement est :legal_name, NIF :legal_nif, domicilié à :legal_address. Pour toute question sur les cookies ou la protection des données, tu peux écrire à :legal_email.'],
                ['h' => 'Cookies techniques et de sécurité (nécessaires)', 'p' => 'Ils sont indispensables au fonctionnement du site et sont exemptés de consentement. Nous utilisons notre propre cookie de session (environ 2 heures) qui soutient ta navigation, ta connexion et ton panier ; un cookie de sécurité «XSRF-TOKEN» qui protège les formulaires contre la falsification ; et, seulement si tu coches «se souvenir de moi» à la connexion, un cookie de persistance qui maintient ta session entre les visites. Si tu remplis le formulaire d\'inscription, le système anti-fraude Cloudflare Turnstile peut installer un cookie de sécurité temporaire pour distinguer les personnes des robots.'],
                ['h' => self::AUDIENCE_H['fr'], 'p' => self::AUDIENCE_P['fr']],
                ['h' => self::ANALYTICS_H['fr'], 'p' => self::ANALYTICS_P['fr']],
                ['h' => self::MARKETING_H['fr'], 'p' => self::MARKETING_P['fr']],
                ['h' => 'Carte et avis (Google)', 'p' => 'Sur la page d\'accueil, nous affichons une carte Google Maps et les avis publiés sur Google, avec la photo de leurs auteurs, mais ils ne se chargent que si tu consens à la catégorie «carte et avis». Lors du chargement, Google LLC peut installer ses propres cookies dans ton navigateur à des fins de fonctionnement, de sécurité et, le cas échéant, de mesure, et les photos sont servies depuis ses serveurs. Tant que tu ne l\'autorises pas, tu verras un message à la place de la carte et des avis, et aucun cookie de Google n\'est installé. La note moyenne est toujours affichée : c\'est notre serveur qui la consulte, et ton navigateur ne se connecte pas à Google pour l\'afficher.'],
                ['h' => 'Contenu de réseaux sociaux', 'p' => 'Dans la galerie, nous pouvons afficher nos dernières publications Instagram ou TikTok via un widget d\'un fournisseur externe (SnapWidget ou LightWidget). Il ne se charge que si tu consens à la catégorie «réseaux sociaux» ; le cas échéant, le fournisseur du widget peut installer ses propres cookies. Tant que tu ne l\'autorises pas, nous affichons une galerie statique et aucun cookie de tiers n\'est installé.'],
                ['h' => 'Passerelle de paiement (Redsys)', 'p' => 'Lorsque tu effectues un paiement, nous te redirigeons vers la passerelle bancaire sécurisée Redsys, qui peut installer des cookies techniques sur son propre domaine pour traiter l\'opération. Ils sont nécessaires pour finaliser l\'achat que tu engages et sont régis par la politique de Redsys.'],
                ['h' => 'Transferts internationaux de données', 'p' => self::TRANSFERS_P['fr']],
                ['h' => 'Comment accepter, configurer ou retirer le consentement', 'p' => 'Lors de ta première visite, nous affichons un avis pour que tu puisses accepter tous les cookies, les refuser ou les configurer par catégorie. Tu peux modifier ou retirer ta décision à tout moment, aussi facilement que tu l\'as donnée, depuis le lien «Configuration des cookies» en pied de page. Tu peux aussi bloquer ou supprimer les cookies déjà installés depuis les réglages de ton navigateur.'],
                ['h' => 'Durée de conservation du consentement', 'p' => 'Nous conservons ta décision pendant 24 mois maximum ; passé ce délai, nous te redemanderons. Nous te le redemanderons aussi plus tôt si les finalités, les fournisseurs ou cette politique de cookies changent.'],
                ['h' => 'Plus d\'informations', 'p' => 'Pour savoir en détail comment nous traitons tes données personnelles, consulte notre Politique de confidentialité.'],
            ],
        ];
    }
}
