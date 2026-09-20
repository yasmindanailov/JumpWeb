#!/usr/bin/env python3
"""Arnés de mutación de la T2·3: la pasada que persiste las reseñas
(`docs/specs/google-business-profile.md` §4.3·1 → §4.3·3; `DECISIONES #524`, `#729`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · se ESCRIBE lo que se vio y se BORRA solo con una pasada coherente — son dos permisos distintos;
  · `fetched_at` se renueva **cambie o no** la reseña, que es de lo que viven los dos plazos;
  · el resumen solo se toca con una pasada creíble, y la media sale de Google, no de lo guardado;
  · lo retirado se borra **por el modelo**, para que la T2·4 pueda llevarse su fichero;
  · el candado se coge, **sigue echado mientras se escribe** y se suelta pase lo que pase;
  · el presupuesto de tiempo **para de verdad** el recorrido;
  · los estados que no llaman **no llaman**, y una ficha sin cuenta tampoco;
  · un «no» permanente apaga la conexión, uno pasajero no, y **nada pisa una reconexión nueva**;
  · y ni el log ni el comando sueltan un texto, un nombre o un token.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t2-3.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

SYNC = 'app/Domain/Content/Services/GoogleBusinessSync.php'
RESULT = 'app/Domain/Content/Services/GoogleBusinessSyncResult.php'
READER = 'app/Domain/Content/Services/GoogleReviewReader.php'
CMD = 'app/Console/Commands/SyncGoogleBusinessReviews.php'

S = 'GoogleBusinessSyncTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    # ── Escribir contra borrar (§4.3·3) ─────────────────────────────────────────────────────────
    (
        # ❗❗❗ La de la tanda: lo que la pasada no ha visto se parece a lo que ya no existe, y no
        # son lo mismo. Borrar sin creerse la pasada se lleva reseñas que están publicadas.
        'solo se BORRA con una pasada coherente',
        SYNC,
        '            if ($coherente) {\n                foreach ($existentes as $nombre => $fila) {',
        '            if (true) {\n                foreach ($existentes as $nombre => $fila) {',
        php(S + 'test_una_pasada_incoherente_guarda_lo_que_vio_y_no_borra_nada'),
    ),
    (
        # Y su otra mitad: escribir lo que se vio es SIEMPRE seguro, también con una pasada a medias.
        'se ESCRIBE lo que se vio aunque la pasada no se pueda creer',
        SYNC,
        '            foreach ($pasada->candidates as $candidata) {',
        '            foreach (($coherente ? $pasada->candidates : []) as $candidata) {',
        php(S + 'test_una_pasada_incoherente_guarda_lo_que_vio_y_no_borra_nada'),
    ),
    (
        # «Cuándo se la vio por última vez», no «cuándo se insertó»: de esto viven los DOS plazos.
        'la fecha de la última pasada se renueva CAMBIE O NO la reseña',
        SYNC,
        "                    'fetched_at' => $ahora,\n                ];",
        '                ];',
        php(S + 'test_la_fecha_de_la_ultima_pasada_se_renueva_aunque_la_resena_no_cambie'),
    ),
    (
        # La defensa que parece prudente y no lo es: «si no hemos traído nada, mejor no borrar». Eso
        # ya lo decidió `coherent()`, y con esto una ficha que de verdad se queda sin reseñas no se
        # vaciaría nunca — las de ayer vivirían hasta que las alcanzara el plazo.
        'una ficha que se queda vacía SÍ vacía la tabla',
        SYNC,
        '                foreach ($existentes as $nombre => $fila) {\n                    if (isset($vistas[$nombre])) {',
        '                foreach (($vistas === [] ? [] : $existentes) as $nombre => $fila) {\n                    if (isset($vistas[$nombre])) {',
        php(S + 'test_una_ficha_que_se_queda_sin_resenas_vacia_la_tabla'),
    ),
    # ── El resumen (§4.3·10) ─────────────────────────────────────────────────────────────────────
    (
        # Una media recogida a mitad de un cambio es una afirmación falsa sobre un tercero. Es la
        # MISMA línea que la primera mutación, y eso es el hallazgo: `if ($coherente)` sostiene dos
        # promesas distintas —no borrar y no tocar la media— y cada una tiene su caso.
        'el resumen solo se toca con una pasada creíble',
        SYNC,
        '            if ($coherente) {\n                foreach ($existentes as $nombre => $fila) {',
        '            if (true) {\n                foreach ($existentes as $nombre => $fila) {',
        php(S + 'test_una_pasada_incoherente_no_toca_la_media'),
    ),
    (
        # La cifra viene de Google contada sobre TODAS, no de las doce que se guardan (§4.3·10).
        'la media sale de GOOGLE, no de lo guardado',
        SYNC,
        "                    'average_rating' => $pasada->lastAverage,",
        "                    'average_rating' => $pasada->candidates === [] ? null : array_sum(array_map(fn ($c) => $c->stars, $pasada->candidates)) / count($pasada->candidates),",
        php(S + 'test_el_resumen_sale_de_google_y_los_enlaces_de_la_conexion'),
    ),
    (
        # La portada no debe leer la fila que lleva el token cifrado (§4.2·5).
        'los enlaces se COPIAN de la conexión al resumen',
        SYNC,
        "                    'maps_uri' => $conexion->maps_uri,",
        "                    'maps_uri' => null,",
        php(S + 'test_el_resumen_sale_de_google_y_los_enlaces_de_la_conexion'),
    ),
    # ── Lo que no se toca (§4.3·9) ───────────────────────────────────────────────────────────────
    (
        # El atajo que deshace la T2 entera sin romper nada visible.
        'la URL de la foto de Google NO se escribe en la tabla',
        SYNC,
        "                    'review_created_at' => $candidata->createdAt,",
        "                    'author_photo_path' => $candidata->authorPhotoSourceUrl,\n                    'review_created_at' => $candidata->createdAt,",
        php(S + 'test_la_url_de_la_foto_de_google_no_llega_a_la_tabla'),
    ),
    # ── El borrado fila a fila (§4.3·6) ──────────────────────────────────────────────────────────
    (
        # Un borrado en masa no instancia nada y la T2·4 cuelga del evento el borrado del fichero.
        'lo retirado se borra POR EL MODELO, no en masa',
        SYNC,
        '                    $fila->delete();\n                    $retiradas++;',
        "                    GoogleBusinessReview::query()->where('review_name', $nombre)->delete();\n                    $retiradas++;",
        php(S + 'test_lo_retirado_se_borra_por_el_modelo_y_no_en_masa'),
    ),
    # ── El candado (§4.3·1) ──────────────────────────────────────────────────────────────────────
    (
        'con una pasada en curso no se llama a Google',
        SYNC,
        '        if (! $lock->get()) {\n            return new GoogleBusinessSyncResult(GoogleBusinessSyncOutcome::Busy, $estado);\n        }',
        '',
        php(S + 'test_con_una_pasada_en_curso_no_se_llama_a_google'),
    ),
    (
        # ❗❗ El defecto que tuvo este código un rato: soltar el candado al terminar de LEER y
        # persistir fuera de él. Ningún caso de un solo hilo lo vería de otra forma.
        'la ESCRITURA va dentro del candado',
        SYNC,
        '            $resultado = $this->persist($pasada, $conexion, $estado);',
        '            $lock->release();\n            $resultado = $this->persist($pasada, $conexion, $estado);',
        php(S + 'test_el_candado_sigue_echado_mientras_se_escribe'),
    ),
    (
        # Sin esto, un solo 403 deja la sincronización bloqueada hasta que caduque el candado.
        'el candado se suelta aunque Google diga que no',
        SYNC,
        '        } finally {\n            $lock->release();\n        }',
        '        }',
        php(S + 'test_el_candado_se_suelta_aunque_google_diga_que_no'),
    ),
    (
        # Preguntar si hay una pasada en curso no puede dejar el candado echado.
        'preguntar por el candado no lo deja echado',
        SYNC,
        '        // Se cogió para mirar, así que se suelta: preguntar no puede dejar el candado echado.\n        $lock->release();',
        '',
        php(S + 'test_el_candado_se_suelta_al_terminar'),
    ),
    # ── El presupuesto (§4.3·1) ──────────────────────────────────────────────────────────────────
    (
        'el presupuesto de tiempo PARA el recorrido',
        READER,
        '            if ($deadline !== null && now()->getTimestamp() >= $deadline) {\n                break;\n            }',
        '',
        php(S + 'test_agotar_el_presupuesto_deja_la_pasada_incompleta_y_no_borra'),
    ),
    (
        'la pasada sale con su presupuesto puesto',
        SYNC,
        '                now()->getTimestamp() + self::BUDGET_SECONDS,',
        '                null,',
        php(S + 'test_agotar_el_presupuesto_deja_la_pasada_incompleta_y_no_borra'),
    ),
    # ── Los estados que no llaman (§4.2·7) ───────────────────────────────────────────────────────
    (
        # Reintentar contra una caducada gasta cuota COMPARTIDA entre todos los parques.
        'los estados que no llaman NO llaman',
        SYNC,
        '        if ($estado !== GoogleBusinessStatus::Connected || $conexion === null) {',
        '        if ($conexion === null) {',
        php(S + 'test_una_conexion_caducada_no_se_reintenta'),
    ),
    (
        # `#726`: sin la cuenta delante, `reviews.list` devuelve un 404 que se lee como «ficha perdida».
        'una ficha elegida SIN CUENTA no se pide',
        SYNC,
        '        if ($token === null || $parent === null) {',
        '        if ($token === null) {',
        php(S + 'test_una_ficha_elegida_sin_cuenta_no_se_pide'),
    ),
    # ── Marcar el estado (§4.2·7) ────────────────────────────────────────────────────────────────
    (
        'un «no» permanente de Google APAGA la conexión',
        SYNC,
        "        $actual->update(['status' => $e->status, 'status_changed_at' => now()]);",
        '',
        php(S + 'test_un_no_permanente_de_google_apaga_la_conexion'),
    ),
    (
        # 429 y 5xx son de Google, no del parque: confundirlos es apagar por un mal minuto.
        'un fallo PASAJERO no apaga la conexión',
        SYNC,
        '        if ($e->status === null) {\n            return;\n        }',
        '',
        php(S + 'test_un_fallo_pasajero_no_apaga_la_conexion'),
    ),
    (
        # ❗❗ Comparar-y-escribir: el admin puede haber reconectado mientras esperábamos a Google.
        'nada pisa una RECONEXIÓN hecha mientras llamábamos',
        SYNC,
        '        if ($actual === null || ! $actual->tokenStillIs($token)) {\n            return;\n        }',
        '        if ($actual === null) {\n            return;\n        }',
        php(S + 'test_si_reconectan_mientras_llamabamos_no_se_pisa_la_conexion_nueva'),
    ),
    # ── Lo que no puede salir (`RGPD-02`) ────────────────────────────────────────────────────────
    (
        'el log de la pasada no lleva ni un texto ni un nombre',
        RESULT,
        "            'vistas' => $this->seen,",
        "            'vistas' => $this->seen,\n            'candidatas' => 'UN-TEXTO-QUE-NO-PUEDE-SALIR',",
        php(S + 'test_el_log_de_la_pasada_no_lleva_ni_un_texto_ni_un_nombre'),
    ),
    (
        'el comando no imprime ni textos ni nombres',
        CMD,
        "                $this->line(sprintf(\n                    'Vistas %d · guardadas %d · retiradas %d.',",
        "                $this->line(\\App\\Domain\\Content\\Models\\GoogleBusinessReview::query()->pluck('comment')->implode(' '));\n                $this->line(sprintf(\n                    'Vistas %d · guardadas %d · retiradas %d.',",
        php(S + 'test_el_comando_no_imprime_ni_textos_ni_nombres'),
    ),
    (
        # §4.3·8: si esto no se dijera en ninguna parte, nadie miraría `text_ambiguous` nunca.
        'el comando AVISA de lo que no se pudo separar',
        CMD,
        '                if ($resultado->ambiguous > 0) {',
        '                if (false) {',
        php(S + 'test_el_comando_avisa_de_lo_que_no_se_pudo_separar'),
    ),
    (
        # Un cron que se pone rojo todas las noches en una instalación sin conectar no lo mira nadie.
        'no llamar NO es un fallo del comando',
        CMD,
        '                $this->line(\'No se ha llamado a Google: el estado de la conexión no lo permite.\');\n\n                return self::SUCCESS;',
        '                $this->line(\'No se ha llamado a Google: el estado de la conexión no lo permite.\');\n\n                return self::FAILURE;',
        php(S + 'test_el_comando_sale_con_cero_cuando_no_hay_que_llamar'),
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
