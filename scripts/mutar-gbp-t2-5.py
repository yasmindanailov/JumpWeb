#!/usr/bin/env python3
"""Arnés de mutación de la T2·5: «Ocultar» una reseña
(`docs/specs/google-business-profile.md` §4.3·7; `DECISIONES #524`, `#731`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · ocultar son **dos cosas**: borrar lo que hay **y** apuntar para que no vuelva;
  · lo apuntado entra en el filtro de la pasada, que es lo que hace que «oculta» siga oculta;
  · «ocultar» **no toca** ni la media ni el total, que son de Google;
  · el rastro lleva **el hash y el motivo**, nunca el texto ni el nombre;
  · el motivo es **tasado**: un texto libre en una tabla que no caduca acaba con un nombre dentro;
  · hace falta `settings.manage`, y `panel_role` no basta;
  · la pantalla enseña lo publicado con el **mismo plazo que la portada**, y de lo oculto **solo la
    huella**;
  · y dejar de ocultar **retira de verdad** el apunte.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t2-5.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

SUPP = 'app/Domain/Content/Services/GoogleReviewSuppressions.php'
FILTER = 'app/Domain/Content/Services/GoogleReviewFilter.php'
SYNC = 'app/Domain/Content/Services/GoogleBusinessSync.php'
ADMIN = 'app/Http/Controllers/Admin/GoogleBusinessConnectController.php'
PAGE = 'app/Filament/Pages/GoogleBusinessProfilePage.php'
VIEW = 'resources/views/filament/pages/google-business-profile.blade.php'

S = 'GoogleReviewSuppressionTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    # ── Ocultar son DOS cosas (§4.3·7) ──────────────────────────────────────────────────────────
    (
        # Con el apunte sin el borrado, la reseña sigue en la portada hasta mañana.
        'ocultar BORRA en el acto lo que hay',
        SUPP,
        '            $review->delete();',
        '',
        php(S + 'test_ocultar_borra_en_el_acto_lo_que_hay'),
    ),
    (
        # ❗❗ Y con el borrado sin el apunte, VUELVE mañana. Los dos desenlaces parecen «funcionó».
        'ocultar APUNTA la reseña para que no vuelva',
        SUPP,
        "            GoogleBusinessReviewSuppression::query()->updateOrCreate(\n                ['review_hash' => $hash],\n                ['reason' => $reason],\n            );",
        '',
        php(S + 'test_ocultar_apunta_la_resena_para_que_no_vuelva'),
    ),
    (
        # Reconocer la misma reseña sin guardar nada de ella: para eso está el hash.
        'el apunte reconoce la MISMA reseña',
        SUPP,
        "        return hash('sha256', $reviewName);",
        "        return hash('sha256', $reviewName.random_bytes(8));",
        php(S + 'test_ocultar_apunta_la_resena_para_que_no_vuelva'),
    ),
    (
        'ocultar dos veces no duplica el apunte',
        SUPP,
        "            GoogleBusinessReviewSuppression::query()->updateOrCreate(\n                ['review_hash' => $hash],\n                ['reason' => $reason],\n            );",
        "            GoogleBusinessReviewSuppression::query()->create([\n                'review_hash' => $hash,\n                'reason' => $reason,\n            ]);",
        php(S + 'test_ocultar_dos_veces_la_misma_resena_no_duplica'),
    ),
    # ── Que no vuelva (§4.3·2) ──────────────────────────────────────────────────────────────────
    (
        # Sin esto, la lista de supresión es un apunte que nadie lee y ocultar dura hasta las 04:40.
        'el filtro DEJA FUERA lo que está oculto',
        FILTER,
        '        return ! isset($this->suppressed[GoogleReviewSuppressions::hash($review->name)]);',
        '        return true;',
        php(S + 'test_la_pasada_no_trae_una_resena_oculta'),
    ),
    (
        'la pasada le pasa al filtro la lista de ocultas',
        SYNC,
        '                GoogleReviewFilter::fromSettings()->withSuppressed($this->suppressions->hashes()),',
        '                GoogleReviewFilter::fromSettings(),',
        php('GoogleBusinessSyncTest::test_la_pasada_no_vuelve_a_traer_una_resena_oculta'),
    ),
    # ── Lo que «Ocultar» NO toca (§4.3·7) ───────────────────────────────────────────────────────
    (
        # Que «ocultar» bajara el recuento sería el parque cambiando la nota de su propia ficha.
        'ocultar NO toca la media ni el total',
        SUPP,
        '            // ⚠️ Por el MODELO: su evento `deleting` es el que se lleva los ficheros (T2·4).\n            $review->delete();',
        "            $review->delete();\n\n            \\App\\Domain\\Content\\Models\\GoogleBusinessReviewSummary::query()->decrement('total_review_count');",
        php(S + 'test_ocultar_no_toca_la_media_ni_el_total'),
    ),
    # ── El rastro (§4.3·7 y `RGPD-02`) ──────────────────────────────────────────────────────────
    (
        # `audit_logs` sobrevive a la reseña que lo causó: dura más que el dato que lo originó.
        'el rastro NO lleva el texto ni el nombre',
        ADMIN,
        "        AuditLogger::log(GoogleReviewSuppressions::ACTION_HIDDEN, payload: ['hash' => $hash, 'reason' => $motivo->value]);",
        "        AuditLogger::log(GoogleReviewSuppressions::ACTION_HIDDEN, payload: ['hash' => $hash, 'reason' => $motivo->value, 'autor' => $resena->author_name, 'texto' => $resena->comment, 'nombre' => $resena->review_name]);",
        php(S + 'test_el_rastro_lleva_el_hash_y_el_motivo_y_nada_mas'),
    ),
    (
        'dejar de ocultar también deja rastro',
        ADMIN,
        "        AuditLogger::log(GoogleReviewSuppressions::ACTION_UNHIDDEN, payload: ['hash' => $datos['hash']]);",
        '',
        php(S + 'test_dejar_de_ocultar_tambien_deja_rastro'),
    ),
    # ── El motivo tasado (§4.3·7) ───────────────────────────────────────────────────────────────
    (
        # Un texto libre en una tabla que NO caduca acaba con el nombre de alguien dentro.
        'el motivo es TASADO, no texto libre',
        ADMIN,
        "            'reason' => ['required', Rule::enum(GoogleReviewSuppressionReason::class)],",
        "            'reason' => ['required', 'string'],",
        php(S + 'test_un_motivo_que_no_esta_tasado_no_entra'),
    ),
    # ── Quién puede (§4.2·1) ────────────────────────────────────────────────────────────────────
    (
        # `panel_role` deja pasar a `staff`: el permiso se comprueba EN EL CONTROLADOR.
        'ocultar exige `settings.manage`',
        ADMIN,
        "    public function hideReview(Request $request, GoogleReviewSuppressions $suppressions): RedirectResponse\n    {\n        $this->authorizeSettings($request);",
        '    public function hideReview(Request $request, GoogleReviewSuppressions $suppressions): RedirectResponse\n    {',
        php(S + 'test_sin_permiso_de_ajustes_no_se_puede_ocultar'),
    ),
    (
        'dejar de ocultar exige `settings.manage`',
        ADMIN,
        "    public function unhideReview(Request $request, GoogleReviewSuppressions $suppressions): RedirectResponse\n    {\n        $this->authorizeSettings($request);",
        '    public function unhideReview(Request $request, GoogleReviewSuppressions $suppressions): RedirectResponse\n    {',
        php(S + 'test_sin_permiso_de_ajustes_no_se_puede_dejar_de_ocultar'),
    ),
    (
        'una reseña que ya no está no rompe la pantalla',
        ADMIN,
        "        if ($resena === null) {\n            // La pasada pudo haberla retirado entre que se pintó la pantalla y se pulsó el botón.\n            return $this->back('google-business-review-missing');\n        }",
        '',
        php(S + 'test_ocultar_una_resena_que_ya_no_esta_no_rompe'),
    ),
    # ── Dejar de ocultar (`#731`) ───────────────────────────────────────────────────────────────
    (
        'dejar de ocultar RETIRA el apunte',
        SUPP,
        "        return GoogleBusinessReviewSuppression::query()->where('review_hash', $hash)->delete() > 0;",
        '        return true;',
        php(S + 'test_dejar_de_ocultar_retira_el_apunte'),
    ),
    (
        # «Lo he quitado» cuando no había nada que quitar es una mentira que se lee como un éxito.
        'dejar de ocultar lo que no estaba no miente',
        SUPP,
        "        return GoogleBusinessReviewSuppression::query()->where('review_hash', $hash)->delete() > 0;",
        "        GoogleBusinessReviewSuppression::query()->where('review_hash', $hash)->delete();\n\n        return true;",
        php(S + 'test_dejar_de_ocultar_lo_que_no_estaba_oculto_no_miente'),
    ),
    # ── La pantalla (§4.2·1) ────────────────────────────────────────────────────────────────────
    (
        # Si aquí saliera una que la web ya no enseña, el admin gastaría una entrada de una lista
        # que no caduca en algo que ya no se veía.
        'la pantalla usa el MISMO plazo que la portada',
        PAGE,
        '            ->withinRetention()',
        '',
        php(S + 'test_la_pantalla_no_ensena_una_resena_pasada_de_plazo'),
    ),
    (
        'la pantalla ofrece OCULTAR cada reseña',
        VIEW,
        "                            <form method=\"POST\" action=\"{{ route('admin.google_business.hide_review') }}\" class=\"mt-3 flex flex-wrap items-center gap-2\">",
        '                            <form class="mt-3 flex flex-wrap items-center gap-2">',
        php(S + 'test_la_pantalla_lista_las_resenas_con_su_boton_de_ocultar'),
    ),
    (
        'la pantalla ofrece DEJAR DE OCULTAR',
        VIEW,
        "                        <form method=\"POST\" action=\"{{ route('admin.google_business.unhide_review') }}\">",
        '                        <form>',
        php(S + 'test_la_pantalla_ensena_lo_oculto_sin_decir_que_era'),
    ),
]


def green(cmd: list[str]) -> bool:
    return subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).returncode == 0


def main() -> int:
    for name, _path, _old, _new, cmd in MUTATIONS:
        if not green(cmd):
            print(f'CONTROL en ROJO antes de mutar («{name}»): el arnés no puede medir nada')
            return 2

    bitten = 0
    for name, path, old, new, cmd in MUTATIONS:
        file = ROOT / path
        original = file.read_text(encoding='utf-8')
        if original.count(old) != 1:
            print(f'la mutación «{name}» no encuentra su sitio EXACTO en {path}: el arnés ha caducado')
            return 2
        file.write_text(original.replace(old, new, 1), encoding='utf-8')
        try:
            red = not green(cmd)
        finally:
            file.write_text(original, encoding='utf-8')
        print(('MUERDE     ' if red else 'NO MUERDE  ') + name)
        bitten += int(red)

    print(f'{bitten}/{len(MUTATIONS)}')
    return 0 if bitten == len(MUTATIONS) else 1


if __name__ == '__main__':
    sys.exit(main())
