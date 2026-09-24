<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T3b·3 de la analítica (`specs/analitica.md` §4.3, `#678`) — La política de cookies deja de llevar un
 * «[PENDIENTE: asesoría]» a la vista del cliente en el párrafo de publicidad y en el de transferencias: las
 * plataformas de anuncios ACTIVAS se nombran en el RENDER de `/cookies` (`Pixels::active()`, como la
 * herramienta de análisis desde la T3a·2), con su empresa responsable y su garantía de transferencia.
 *
 * ⚠️⚠️ **La página `/cookies` vive en la BD** (`pages`, sembrada desde `CookiePolicyContent`): cambiar esa
 * clase solo alcanza a las instalaciones NUEVAS, y las que ya existen necesitan esto.
 * ⚠️ **Es QUIRÚRGICA y respeta las ediciones** (el criterio de `#592`): cada párrafo se sustituye solo si
 * sigue EXACTAMENTE como lo sembró el producto en la T3a·1. Si la clienta lo reescribió, no se toca.
 * ⚠️ **Autocontenida**: los textos van escritos aquí, sin leer `CookiePolicyContent`; `CookiePolicyContentTest`
 * exige que lo migrado quede EXACTAMENTE como lo siembra una instalación nueva.
 * ⚠️ El «[PENDIENTE…]» del proveedor del FEED SOCIAL en transferencias es anterior a la analítica (`#592`, carril
 * de la web) y aquí se conserva tal cual: no es de esta tanda decidirlo.
 * ▶ Idempotente: con los párrafos nuevos no sustituye nada.
 */
return new class extends Migration
{
    /** @var array<string, list<array{0: string, 1: string}>> por idioma, pares [texto sembrado, texto nuevo] */
    private const REPLACEMENTS = [
        'es' => [
            [
                'Solo si lo autorizas en la categoría «publicidad», cargamos los píxeles de las plataformas de anuncios en las que hagamos campañas (Google Ads, Meta o TikTok) y les comunicamos las compras que se completan, para saber qué anuncios funcionan. Esas plataformas pueden instalar sus propias cookies y reciben un identificador de la compra y datos de contacto transformados de forma irreversible (hash), nunca en claro. Mientras no lo autorices, no se carga ningún píxel ni se comunica nada. [PENDIENTE: asesoría — nombrar a las plataformas activas como destinatarias y su garantía de transferencia].',
                'Solo si lo autorizas en la categoría «publicidad», cargamos los píxeles de las plataformas de anuncios en las que hagamos campañas (Google Ads, Meta o TikTok) y les comunicamos las compras que se completan, para saber qué anuncios funcionan. Esas plataformas pueden instalar sus propias cookies y reciben un identificador de la compra y datos de contacto transformados de forma irreversible (hash), nunca en claro. Mientras no lo autorices, no se carga ningún píxel ni se comunica nada. Las plataformas activas en esta web, con la empresa responsable y su garantía de transferencia, se nombran más abajo.',
            ],
            [
                'Si activas el mapa y las reseñas, el contenido de redes sociales, el análisis de uso identificado o la publicidad, algunos proveedores pueden tratar datos fuera del Espacio Económico Europeo. Google LLC (mapa, reseñas y Google Ads) y Cloudflare, Inc. (sistema antifraude) están adheridos al marco de adecuación EU-US Data Privacy Framework, que ofrece garantías para la transferencia a Estados Unidos. Respecto al proveedor del feed social, a la herramienta de análisis y a las demás plataformas de anuncios, la garantía de transferencia aplicable es [PENDIENTE: confirmar adhesión al Data Privacy Framework o cláusulas contractuales tipo].',
                'Si activas el mapa y las reseñas, el contenido de redes sociales, el análisis de uso identificado o la publicidad, algunos proveedores pueden tratar datos fuera del Espacio Económico Europeo. Google LLC (mapa, reseñas y Google Ads) y Cloudflare, Inc. (sistema antifraude) están adheridos al marco de adecuación EU-US Data Privacy Framework, que ofrece garantías para la transferencia a Estados Unidos. La herramienta de análisis y las plataformas de anuncios activas en esta web se nombran más abajo con su empresa responsable y su garantía de transferencia. Respecto al proveedor del feed social, la garantía de transferencia aplicable es [PENDIENTE: confirmar adhesión al Data Privacy Framework o cláusulas contractuales tipo].',
            ],
        ],
        'en' => [
            [
                'Only if you allow it under the «advertising» category, we load the pixels of the advertising platforms where we run campaigns (Google Ads, Meta or TikTok) and report completed purchases to them, to know which ads work. Those platforms may install their own cookies and receive a purchase identifier and contact data transformed irreversibly (hashed), never in the clear. Until you allow it, no pixel loads and nothing is reported. [PENDING: legal review — name the active platforms as recipients and their transfer safeguard].',
                'Only if you allow it under the «advertising» category, we load the pixels of the advertising platforms where we run campaigns (Google Ads, Meta or TikTok) and report completed purchases to them, to know which ads work. Those platforms may install their own cookies and receive a purchase identifier and contact data transformed irreversibly (hashed), never in the clear. Until you allow it, no pixel loads and nothing is reported. The platforms active on this site, with the responsible company and their transfer safeguard, are named below.',
            ],
            [
                'If you enable the map and reviews, social media content, identified usage analytics or advertising, some providers may process data outside the European Economic Area. Google LLC (map, reviews and Google Ads) and Cloudflare, Inc. (anti-fraud) are certified under the EU-US Data Privacy Framework, which provides safeguards for transfers to the United States. For the social feed provider, the analytics tool and the other advertising platforms, the applicable transfer safeguard is [PENDING: confirm Data Privacy Framework certification or standard contractual clauses].',
                'If you enable the map and reviews, social media content, identified usage analytics or advertising, some providers may process data outside the European Economic Area. Google LLC (map, reviews and Google Ads) and Cloudflare, Inc. (anti-fraud) are certified under the EU-US Data Privacy Framework, which provides safeguards for transfers to the United States. The analytics tool and the advertising platforms active on this site are named below with the responsible company and their transfer safeguard. For the social feed provider, the applicable transfer safeguard is [PENDING: confirm Data Privacy Framework certification or standard contractual clauses].',
            ],
        ],
        'fr' => [
            [
                'Seulement si tu l\'autorises dans la catégorie «publicité», nous chargeons les pixels des plateformes publicitaires sur lesquelles nous menons des campagnes (Google Ads, Meta ou TikTok) et nous leur communiquons les achats finalisés, pour savoir quelles annonces fonctionnent. Ces plateformes peuvent installer leurs propres cookies et reçoivent un identifiant de l\'achat et des données de contact transformées de façon irréversible (hachage), jamais en clair. Tant que tu ne l\'autorises pas, aucun pixel n\'est chargé et rien n\'est communiqué. [À COMPLÉTER : conseil juridique — nommer les plateformes actives comme destinataires et leur garantie de transfert].',
                'Seulement si tu l\'autorises dans la catégorie «publicité», nous chargeons les pixels des plateformes publicitaires sur lesquelles nous menons des campagnes (Google Ads, Meta ou TikTok) et nous leur communiquons les achats finalisés, pour savoir quelles annonces fonctionnent. Ces plateformes peuvent installer leurs propres cookies et reçoivent un identifiant de l\'achat et des données de contact transformées de façon irréversible (hachage), jamais en clair. Tant que tu ne l\'autorises pas, aucun pixel n\'est chargé et rien n\'est communiqué. Les plateformes actives sur ce site, avec la société responsable et leur garantie de transfert, sont nommées plus bas.',
            ],
            [
                'Si tu actives la carte et les avis, le contenu de réseaux sociaux, l\'analyse d\'usage identifiée ou la publicité, certains fournisseurs peuvent traiter des données hors de l\'Espace économique européen. Google LLC (carte, avis et Google Ads) et Cloudflare, Inc. (anti-fraude) adhèrent au cadre EU-US Data Privacy Framework, qui offre des garanties pour le transfert vers les États-Unis. Concernant le fournisseur du fil social, l\'outil d\'analyse et les autres plateformes publicitaires, la garantie de transfert applicable est [À COMPLÉTER : confirmer l\'adhésion au Data Privacy Framework ou des clauses contractuelles types].',
                'Si tu actives la carte et les avis, le contenu de réseaux sociaux, l\'analyse d\'usage identifiée ou la publicité, certains fournisseurs peuvent traiter des données hors de l\'Espace économique européen. Google LLC (carte, avis et Google Ads) et Cloudflare, Inc. (anti-fraude) adhèrent au cadre EU-US Data Privacy Framework, qui offre des garanties pour le transfert vers les États-Unis. L\'outil d\'analyse et les plateformes publicitaires actives sur ce site sont nommés plus bas avec la société responsable et leur garantie de transfert. Concernant le fournisseur du fil social, la garantie de transfert applicable est [À COMPLÉTER : confirmer l\'adhésion au Data Privacy Framework ou des clauses contractuelles types].',
            ],
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
            if (! is_array($sections) || ! isset(self::REPLACEMENTS[$locale])) {
                continue;
            }

            foreach ($sections as $i => $section) {
                foreach (self::REPLACEMENTS[$locale] as [$old, $new]) {
                    if (is_array($section) && ($section['p'] ?? null) === $old) {
                        $sections[$i]['p'] = $new;
                        $changed = true;
                    }
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
        // No-op: volver a poner un «[PENDIENTE]» a la vista del cliente no es una vuelta atrás que valga.
    }
};
