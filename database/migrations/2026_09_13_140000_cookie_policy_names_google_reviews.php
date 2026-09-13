<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `#592` — La política de cookies nombra las RESEÑAS de Google dentro de la categoría del mapa.
 *
 * ⚠️⚠️ **La página `/cookies` vive en la BD** (`pages`, sembrada desde `CookiePolicyContent`): cambiar
 * esa clase solo alcanza a las instalaciones NUEVAS, y las que ya existen necesitan esto.
 *
 * ⚠️ **Es QUIRÚRGICA y respeta las ediciones** (el criterio de `2026_06_08_000002`): solo sustituye un
 * párrafo si sigue EXACTAMENTE como lo sembró el producto. Si la clienta lo reescribió desde el panel no
 * se toca, y ese texto seguirá sin nombrar las reseñas: hay que revisarlo a mano.
 * ⚠️ **Autocontenida**: el texto de antes y el de después van escritos aquí, sin leer
 * `CookiePolicyContent`, para que el día que esa clase cambie esta migración siga haciendo lo mismo.
 * ▶ Idempotente: con el texto nuevo ya puesto no encuentra nada que sustituir.
 */
return new class extends Migration
{
    /**
     * El párrafo del mapa, entero: [idioma => [[título viejo, texto viejo], [título nuevo, texto nuevo]]].
     *
     * ⚠️ El texto viejo decía también que `/contacto` lleva mapa, y no lo lleva desde `#535`.
     */
    private const MAP_SECTION = [
        'es' => [
            ['Mapa de ubicación (Google Maps)', 'En la página de inicio y en la de contacto mostramos un mapa de Google Maps, pero solo se carga si das tu consentimiento a la categoría «mapa». Al cargarlo, Google LLC puede instalar cookies propias en tu navegador con finalidades de funcionamiento, seguridad y, en su caso, medición. Mientras no lo autorices, verás un aviso en lugar del mapa y no se instala ninguna cookie de Google.'],
            ['Mapa y reseñas (Google)', 'En la página de inicio mostramos un mapa de Google Maps y las reseñas publicadas en Google, con la foto de quien las escribe, pero solo se cargan si das tu consentimiento a la categoría «mapa y reseñas». Al cargarlos, Google LLC puede instalar cookies propias en tu navegador con finalidades de funcionamiento, seguridad y, en su caso, medición, y las fotos se sirven desde sus servidores. Mientras no lo autorices, verás un aviso en lugar del mapa y de las reseñas, y no se instala ninguna cookie de Google. La valoración media sí se muestra siempre: la consulta nuestro servidor y tu navegador no se conecta a Google para verla.'],
        ],
        'en' => [
            ['Location map (Google Maps)', 'On the home and contact pages we show a Google Maps map, but it only loads if you consent to the «map» category. When loaded, Google LLC may install its own cookies in your browser for operation, security and, where applicable, measurement purposes. Until you allow it, you will see a notice instead of the map and no Google cookie is installed.'],
            ['Map and reviews (Google)', 'On the home page we show a Google Maps map and the reviews published on Google, with their authors\' photos, but they only load if you consent to the «map and reviews» category. When loaded, Google LLC may install its own cookies in your browser for operation, security and, where applicable, measurement purposes, and the photos are served from its servers. Until you allow it, you will see a notice instead of the map and the reviews, and no Google cookie is installed. The average rating is always shown: our server fetches it, and your browser does not connect to Google to display it.'],
        ],
        'fr' => [
            ['Carte de localisation (Google Maps)', 'Sur la page d\'accueil et la page de contact, nous affichons une carte Google Maps, mais elle ne se charge que si tu consens à la catégorie «carte». Lors du chargement, Google LLC peut installer ses propres cookies dans ton navigateur à des fins de fonctionnement, de sécurité et, le cas échéant, de mesure. Tant que tu ne l\'autorises pas, tu verras un avis à la place de la carte et aucun cookie de Google n\'est installé.'],
            ['Carte et avis (Google)', 'Sur la page d\'accueil, nous affichons une carte Google Maps et les avis publiés sur Google, avec la photo de leurs auteurs, mais ils ne se chargent que si tu consens à la catégorie «carte et avis». Lors du chargement, Google LLC peut installer ses propres cookies dans ton navigateur à des fins de fonctionnement, de sécurité et, le cas échéant, de mesure, et les photos sont servies depuis ses serveurs. Tant que tu ne l\'autorises pas, tu verras un message à la place de la carte et des avis, et aucun cookie de Google n\'est installé. La note moyenne est toujours affichée : c\'est notre serveur qui la consulte, et ton navigateur ne se connecte pas à Google pour l\'afficher.'],
        ],
    ];

    /**
     * El párrafo de transferencias: [idioma => [texto viejo entero, [[fragmento viejo, fragmento nuevo], …]]].
     *
     * ⚠️ Se exige el párrafo viejo ENTERO antes de sustituir los fragmentos: cambiar una frase dentro de
     * un texto que la clienta ha reescrito sería editarle el suyo.
     */
    private const TRANSFERS = [
        'es' => [
            'Si activas el mapa o el contenido de redes sociales, algunos proveedores pueden tratar datos fuera del Espacio Económico Europeo. Google LLC (mapa) y Cloudflare, Inc. (sistema antifraude) están adheridos al marco de adecuación EU-US Data Privacy Framework, que ofrece garantías para la transferencia a Estados Unidos. Respecto al proveedor del feed social, la garantía de transferencia aplicable es [PENDIENTE: confirmar adhesión al Data Privacy Framework o cláusulas contractuales tipo].',
            [['Si activas el mapa o el contenido', 'Si activas el mapa y las reseñas o el contenido'], ['Google LLC (mapa)', 'Google LLC (mapa y reseñas)']],
        ],
        'en' => [
            'If you enable the map or social media content, some providers may process data outside the European Economic Area. Google LLC (map) and Cloudflare, Inc. (anti-fraud) are certified under the EU-US Data Privacy Framework, which provides safeguards for transfers to the United States. For the social feed provider, the applicable transfer safeguard is [PENDING: confirm Data Privacy Framework certification or standard contractual clauses].',
            [['If you enable the map or social', 'If you enable the map and reviews or social'], ['Google LLC (map)', 'Google LLC (map and reviews)']],
        ],
        'fr' => [
            'Si tu actives la carte ou le contenu de réseaux sociaux, certains fournisseurs peuvent traiter des données hors de l\'Espace économique européen. Google LLC (carte) et Cloudflare, Inc. (anti-fraude) adhèrent au cadre EU-US Data Privacy Framework, qui offre des garanties pour le transfert vers les États-Unis. Concernant le fournisseur du fil social, la garantie de transfert applicable est [À COMPLÉTER : confirmer l\'adhésion au Data Privacy Framework ou des clauses contractuelles types].',
            [['Si tu actives la carte ou le contenu', 'Si tu actives la carte et les avis ou le contenu'], ['Google LLC (carte)', 'Google LLC (carte et avis)']],
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
            if (! is_array($sections)) {
                continue;
            }

            foreach ($sections as $i => $section) {
                if (! is_array($section) || ! is_string($section['h'] ?? null) || ! is_string($section['p'] ?? null)) {
                    continue;
                }

                [$old, $new] = self::MAP_SECTION[$locale] ?? [null, null];
                if ($old !== null && $section['h'] === $old[0] && $section['p'] === $old[1]) {
                    $body[$locale][$i] = ['h' => $new[0], 'p' => $new[1]];
                    $changed = true;

                    continue;
                }

                [$oldTransfers, $fragments] = self::TRANSFERS[$locale] ?? [null, []];
                if ($oldTransfers !== null && $section['p'] === $oldTransfers) {
                    $body[$locale][$i]['p'] = str_replace(array_column($fragments, 0), array_column($fragments, 1), $oldTransfers);
                    $changed = true;
                }
            }
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
        // No-op: devolver la política a un texto que no nombra las reseñas volvería a pedir un permiso
        // sin decir para qué es. Si hace falta, se edita desde el panel.
    }
};
