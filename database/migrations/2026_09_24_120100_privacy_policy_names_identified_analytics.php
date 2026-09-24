<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T3a de la analítica (`specs/analitica.md` §4.3, `#678`) — La política de privacidad deja de decir «ni
 * elaboramos perfiles» a secas: con la categoría `analytics` del banner la navegación SÍ se vincula a la
 * cuenta, y el párrafo dice cuándo, para qué y cómo se retira.
 *
 * ⚠️⚠️ **La página `/privacidad` vive en la BD** (`pages`, sembrada desde `LegalContent`) y NO se publica
 * por `LegalDocumentPublisher`: a las cuentas existentes se les informa antes de activar el enlace.
 *
 * ⚠️ **Quirúrgica y respeta las ediciones** (criterio de `#592`): sustituye el párrafo solo si su título y
 * su texto siguen EXACTAMENTE como los sembró el producto. **Autocontenida**: los textos van aquí, sin leer
 * `LegalContent`; el test exige que lo migrado quede como lo siembra una instalación nueva.
 * ▶ Idempotente: con el texto nuevo puesto no encuentra nada que sustituir.
 */
return new class extends Migration
{
    /** [idioma => [título, texto viejo, texto nuevo]] */
    private const PROFILING = [
        'es' => [
            'Decisiones automatizadas y elaboración de perfiles',
            'No tomamos decisiones automatizadas que produzcan efectos jurídicos sobre ti o te afecten significativamente, ni elaboramos perfiles con tus datos.',
            'No tomamos decisiones automatizadas que produzcan efectos jurídicos sobre ti o te afecten significativamente. Solo si lo autorizas en el aviso de cookies (categoría «análisis»), vinculamos tu navegación en la web a tu cuenta para entender cómo la usas y mejorarla; puedes retirar esa autorización cuando quieras desde «Mi cuenta → Privacidad» o desde la configuración de cookies, y entonces desvinculamos lo registrado. Fuera de eso no elaboramos perfiles con tus datos.',
        ],
        'en' => [
            'Automated decisions and profiling',
            'We do not make automated decisions producing legal effects on you or significantly affecting you, nor do we carry out profiling with your data.',
            'We do not make automated decisions producing legal effects on you or significantly affecting you. Only if you allow it in the cookie notice («analytics» category), we link your browsing on the site to your account to understand how you use it and improve it; you can withdraw that permission at any time from «My account → Privacy» or from the cookie settings, and we then unlink what was recorded. Beyond that we do not carry out profiling with your data.',
        ],
        'fr' => [
            'Décisions automatisées et profilage',
            'Nous ne prenons pas de décisions automatisées produisant des effets juridiques à ton égard ou t\'affectant de manière significative, et nous n\'effectuons pas de profilage avec tes données.',
            'Nous ne prenons pas de décisions automatisées produisant des effets juridiques à ton égard ou t\'affectant de manière significative. Seulement si tu l\'autorises dans l\'avis sur les cookies (catégorie «analyse»), nous lions ta navigation sur le site à ton compte pour comprendre comment tu l\'utilises et l\'améliorer ; tu peux retirer cette autorisation à tout moment depuis «Mon compte → Confidentialité» ou depuis la configuration des cookies, et nous délions alors ce qui a été enregistré. En dehors de cela, nous n\'effectuons pas de profilage avec tes données.',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $row = DB::table('pages')->where('slug', 'privacidad')->first(['id', 'body']);
        $body = $row === null ? null : json_decode((string) $row->body, true);

        if (! is_array($body)) {
            return;
        }

        $changed = false;

        foreach ($body as $locale => $sections) {
            if (! is_array($sections) || ! isset(self::PROFILING[$locale])) {
                continue;
            }

            [$heading, $old, $new] = self::PROFILING[$locale];

            foreach ($sections as $i => $section) {
                if (is_array($section) && ($section['h'] ?? null) === $heading && ($section['p'] ?? null) === $old) {
                    $body[$locale][$i]['p'] = $new;
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
        // No-op: volver a «ni elaboramos perfiles» con el enlace activo sería decir algo falso.
    }
};
