<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T3a de la analítica (`specs/analitica.md` §4.3, `#678`) — La política de cookies gana tres secciones:
 * la medición de audiencia PROPIA (exenta), el análisis de uso IDENTIFICADO (categoría `analytics`) y la
 * PUBLICIDAD (categoría `marketing`); y el párrafo de transferencias las nombra.
 *
 * ⚠️⚠️ **La página `/cookies` vive en la BD** (`pages`, sembrada desde `CookiePolicyContent`): cambiar
 * esa clase solo alcanza a las instalaciones NUEVAS, y las que ya existen necesitan esto.
 *
 * ⚠️ **Es QUIRÚRGICA y respeta las ediciones** (el criterio de `#592`, `2026_09_13_140000`): las tres
 * secciones se INSERTAN detrás de la de cookies técnicas —localizada por su título— solo si ninguna de las
 * tres está ya; y el párrafo de transferencias se sustituye solo si sigue EXACTAMENTE como lo sembró el
 * producto. Si la clienta reescribió la política desde el panel no se toca, y hay que revisarla a mano.
 * ⚠️ **Autocontenida**: los textos van escritos aquí, sin leer `CookiePolicyContent`, para que el día que
 * esa clase cambie esta migración siga haciendo lo mismo. El test de `CookiePolicyContentTest` exige que
 * lo migrado quede EXACTAMENTE como lo siembra una instalación nueva, que es lo que ata las dos copias.
 * ▶ Idempotente: con las secciones puestas no inserta nada; con el párrafo nuevo no sustituye nada.
 */
return new class extends Migration
{
    /** El título de la sección tras la que se insertan las tres nuevas. */
    private const ANCHOR_H = [
        'es' => 'Cookies técnicas y de seguridad (necesarias)',
        'en' => 'Technical and security cookies (necessary)',
        'fr' => 'Cookies techniques et de sécurité (nécessaires)',
    ];

    /** @var array<string, list<array{h: string, p: string}>> */
    private const NEW_SECTIONS = [
        'es' => [
            ['h' => 'Medición de audiencia propia (exenta de consentimiento)', 'p' => 'Para saber cuántas personas visitan la web, qué páginas ven y desde qué campañas llegan, usamos una cookie propia llamada «visitor_id» que dura 13 meses y no se renueva en cada visita. Solo produce estadísticas anónimas para nosotros: no se cruza con otros sitios ni se cede a nadie, y los datos se conservan como máximo 25 meses. Según la Guía de cookies de la AEPD (2024), esta medición está exenta de consentimiento y por eso no te la pedimos; puedes borrar la cookie desde tu navegador cuando quieras.'],
            ['h' => 'Análisis de uso identificado (categoría «análisis»)', 'p' => 'Solo si lo autorizas en la categoría «análisis», vinculamos tu navegación a tu cuenta cuando inicias sesión o compras, para entender cómo usas la web y mejorarla. Con ese mismo permiso podemos usar una herramienta externa de análisis de uso (grabaciones de sesión y mapas de calor), que recibe un identificador cifrado en lugar de tu nombre, sin tu dirección IP y con lo que escribes enmascarado; si está activa, la nombramos más abajo. Mientras no lo autorices, esa herramienta no se carga y tu navegación no se asocia a tu cuenta. Si tienes cuenta, puedes retirar esta vinculación cuando quieras desde «Mi cuenta → Privacidad».'],
            ['h' => 'Publicidad (categoría «publicidad»)', 'p' => 'Solo si lo autorizas en la categoría «publicidad», cargamos los píxeles de las plataformas de anuncios en las que hagamos campañas (Google Ads, Meta o TikTok) y les comunicamos las compras que se completan, para saber qué anuncios funcionan. Esas plataformas pueden instalar sus propias cookies y reciben un identificador de la compra y datos de contacto transformados de forma irreversible (hash), nunca en claro. Mientras no lo autorices, no se carga ningún píxel ni se comunica nada. [PENDIENTE: asesoría — nombrar a las plataformas activas como destinatarias y su garantía de transferencia].'],
        ],
        'en' => [
            ['h' => 'Our own audience measurement (exempt from consent)', 'p' => 'To know how many people visit the site, which pages they see and which campaigns bring them, we use our own cookie called «visitor_id», which lasts 13 months and is not renewed on each visit. It only produces anonymous statistics for us: it is not cross-referenced with other sites nor shared with anyone, and the data is kept for at most 25 months. Under the AEPD cookie guide (2024) this measurement is exempt from consent, so we do not ask for it; you can delete the cookie from your browser whenever you want.'],
            ['h' => 'Identified usage analytics («analytics» category)', 'p' => 'Only if you allow it under the «analytics» category, we link your browsing to your account when you log in or buy, to understand how you use the site and improve it. With that same permission we may use an external usage analytics tool (session recordings and heat maps), which receives an encrypted identifier instead of your name, without your IP address and with what you type masked; if it is active, we name it below. Until you allow it, that tool does not load and your browsing is not associated with your account. If you have an account, you can withdraw this link at any time from «My account → Privacy».'],
            ['h' => 'Advertising («advertising» category)', 'p' => 'Only if you allow it under the «advertising» category, we load the pixels of the advertising platforms where we run campaigns (Google Ads, Meta or TikTok) and report completed purchases to them, to know which ads work. Those platforms may install their own cookies and receive a purchase identifier and contact data transformed irreversibly (hashed), never in the clear. Until you allow it, no pixel loads and nothing is reported. [PENDING: legal review — name the active platforms as recipients and their transfer safeguard].'],
        ],
        'fr' => [
            ['h' => 'Mesure d\'audience propre (exemptée de consentement)', 'p' => 'Pour savoir combien de personnes visitent le site, quelles pages elles voient et de quelles campagnes elles viennent, nous utilisons un cookie propre appelé «visitor_id», qui dure 13 mois et n\'est pas renouvelé à chaque visite. Il ne produit que des statistiques anonymes pour nous : il n\'est ni croisé avec d\'autres sites ni cédé à qui que ce soit, et les données sont conservées 25 mois au maximum. Selon le guide des cookies de l\'AEPD (2024), cette mesure est exemptée de consentement, c\'est pourquoi nous ne te le demandons pas ; tu peux supprimer le cookie depuis ton navigateur quand tu veux.'],
            ['h' => 'Analyse d\'usage identifiée (catégorie «analyse»)', 'p' => 'Seulement si tu l\'autorises dans la catégorie «analyse», nous lions ta navigation à ton compte lorsque tu te connectes ou achètes, pour comprendre comment tu utilises le site et l\'améliorer. Avec cette même autorisation, nous pouvons utiliser un outil externe d\'analyse d\'usage (enregistrements de session et cartes de chaleur), qui reçoit un identifiant chiffré à la place de ton nom, sans ton adresse IP et avec ce que tu écris masqué ; s\'il est actif, nous le nommons plus bas. Tant que tu ne l\'autorises pas, cet outil ne se charge pas et ta navigation n\'est pas associée à ton compte. Si tu as un compte, tu peux retirer ce lien à tout moment depuis «Mon compte → Confidentialité».'],
            ['h' => 'Publicité (catégorie «publicité»)', 'p' => 'Seulement si tu l\'autorises dans la catégorie «publicité», nous chargeons les pixels des plateformes publicitaires sur lesquelles nous menons des campagnes (Google Ads, Meta ou TikTok) et nous leur communiquons les achats finalisés, pour savoir quelles annonces fonctionnent. Ces plateformes peuvent installer leurs propres cookies et reçoivent un identifiant de l\'achat et des données de contact transformées de façon irréversible (hachage), jamais en clair. Tant que tu ne l\'autorises pas, aucun pixel n\'est chargé et rien n\'est communiqué. [À COMPLÉTER : conseil juridique — nommer les plateformes actives comme destinataires et leur garantie de transfert].'],
        ],
    ];

    /** El párrafo de transferencias: [idioma => [texto viejo ENTERO (v2, `#592`), texto nuevo]]. */
    private const TRANSFERS = [
        'es' => [
            'Si activas el mapa y las reseñas o el contenido de redes sociales, algunos proveedores pueden tratar datos fuera del Espacio Económico Europeo. Google LLC (mapa y reseñas) y Cloudflare, Inc. (sistema antifraude) están adheridos al marco de adecuación EU-US Data Privacy Framework, que ofrece garantías para la transferencia a Estados Unidos. Respecto al proveedor del feed social, la garantía de transferencia aplicable es [PENDIENTE: confirmar adhesión al Data Privacy Framework o cláusulas contractuales tipo].',
            'Si activas el mapa y las reseñas, el contenido de redes sociales, el análisis de uso identificado o la publicidad, algunos proveedores pueden tratar datos fuera del Espacio Económico Europeo. Google LLC (mapa, reseñas y Google Ads) y Cloudflare, Inc. (sistema antifraude) están adheridos al marco de adecuación EU-US Data Privacy Framework, que ofrece garantías para la transferencia a Estados Unidos. Respecto al proveedor del feed social, a la herramienta de análisis y a las demás plataformas de anuncios, la garantía de transferencia aplicable es [PENDIENTE: confirmar adhesión al Data Privacy Framework o cláusulas contractuales tipo].',
        ],
        'en' => [
            'If you enable the map and reviews or social media content, some providers may process data outside the European Economic Area. Google LLC (map and reviews) and Cloudflare, Inc. (anti-fraud) are certified under the EU-US Data Privacy Framework, which provides safeguards for transfers to the United States. For the social feed provider, the applicable transfer safeguard is [PENDING: confirm Data Privacy Framework certification or standard contractual clauses].',
            'If you enable the map and reviews, social media content, identified usage analytics or advertising, some providers may process data outside the European Economic Area. Google LLC (map, reviews and Google Ads) and Cloudflare, Inc. (anti-fraud) are certified under the EU-US Data Privacy Framework, which provides safeguards for transfers to the United States. For the social feed provider, the analytics tool and the other advertising platforms, the applicable transfer safeguard is [PENDING: confirm Data Privacy Framework certification or standard contractual clauses].',
        ],
        'fr' => [
            'Si tu actives la carte et les avis ou le contenu de réseaux sociaux, certains fournisseurs peuvent traiter des données hors de l\'Espace économique européen. Google LLC (carte et avis) et Cloudflare, Inc. (anti-fraude) adhèrent au cadre EU-US Data Privacy Framework, qui offre des garanties pour le transfert vers les États-Unis. Concernant le fournisseur du fil social, la garantie de transfert applicable est [À COMPLÉTER : confirmer l\'adhésion au Data Privacy Framework ou des clauses contractuelles types].',
            'Si tu actives la carte et les avis, le contenu de réseaux sociaux, l\'analyse d\'usage identifiée ou la publicité, certains fournisseurs peuvent traiter des données hors de l\'Espace économique européen. Google LLC (carte, avis et Google Ads) et Cloudflare, Inc. (anti-fraude) adhèrent au cadre EU-US Data Privacy Framework, qui offre des garanties pour le transfert vers les États-Unis. Concernant le fournisseur du fil social, l\'outil d\'analyse et les autres plateformes publicitaires, la garantie de transfert applicable est [À COMPLÉTER : confirmer l\'adhésion au Data Privacy Framework ou des clauses contractuelles types].',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $row = DB::table('pages')->where('slug', 'cookies')->first(['id', 'body']);
        $body = $row === null ? null : json_decode((string) $row->body, true);

        if (! is_array($body)) {
            return;
        }

        $changed = false;

        foreach ($body as $locale => $sections) {
            if (! is_array($sections) || ! isset(self::NEW_SECTIONS[$locale])) {
                continue;
            }

            $sections = array_values(array_filter($sections, static fn ($s): bool => is_array($s) && is_string($s['h'] ?? null) && is_string($s['p'] ?? null)));
            $headings = array_column($sections, 'h');

            // 1. Las tres secciones, detrás de la de cookies técnicas, solo si ninguna está ya.
            $already = array_intersect(array_column(self::NEW_SECTIONS[$locale], 'h'), $headings) !== [];
            $anchor = array_search(self::ANCHOR_H[$locale], $headings, true);

            if (! $already && $anchor !== false) {
                array_splice($sections, $anchor + 1, 0, self::NEW_SECTIONS[$locale]);
                $changed = true;
            }

            // 2. El párrafo de transferencias, solo si sigue siendo el que sembró el producto.
            [$old, $new] = self::TRANSFERS[$locale];
            foreach ($sections as $i => $section) {
                if ($section['p'] === $old) {
                    $sections[$i]['p'] = $new;
                    $changed = true;
                }
            }

            $body[$locale] = $sections;
        }

        if (! $changed) {
            return;
        }

        $update = ['body' => json_encode($body)];
        if (Schema::hasColumn('pages', 'updated_at')) {
            $update['updated_at'] = now();
        }

        DB::table('pages')->where('id', $row->id)->update($update);
    }

    public function down(): void
    {
        // No-op: quitar las secciones dejaría el banner pidiendo dos permisos que la política no explica.
        // Si hace falta, se edita desde el panel.
    }
};
