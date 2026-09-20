#!/usr/bin/env python3
"""Arnés de mutación de la T2·4: las imágenes de las reseñas
(`docs/specs/google-business-profile.md` §4.3·6 y §4.3·9; `DECISIONES #524`, `#730`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · la lista blanca de host es EXACTA y solo `https`, y una URL de fuera **ni se pide**;
  · **no se siguen redirecciones**: un 302 es cómo se sale de la lista blanca sin que se entere;
  · el **tope de bytes** corta una respuesta que no termina;
  · el tipo lo dicen **los bytes**, no la cabecera, y el SVG cae solo por ser lista BLANCA;
  · el nombre es el **hash del contenido**, así que no lleva nada del autor y no se duplica;
  · la ruta que las sirve comprueba **la forma del nombre** y pone su propia CSP y `nosniff`;
  · el fichero **se va con su fila**, y el borrado no toca nada que no sea nuestro;
  · el barrido respeta lo que está en uso **y lo recién escrito**;
  · desconectar **se lleva también las reseñas**;
  · y una descarga que falla deja la reseña **sin foto**, nunca con la URL de Google.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t2-4.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

IMAGES = 'app/Domain/Content/Services/GoogleReviewImages.php'
MODEL = 'app/Domain/Content/Models/GoogleBusinessReview.php'
SYNC = 'app/Domain/Content/Services/GoogleBusinessSync.php'
CTRL = 'app/Http/Controllers/ReviewPhotoController.php'
ADMIN = 'app/Http/Controllers/Admin/GoogleBusinessConnectController.php'
INCOMING = 'app/Domain/Content/Services/IncomingGoogleReview.php'

I = 'GoogleReviewImagesTest::'
S = 'GoogleBusinessSyncTest::'
D = 'GoogleBusinessDisconnectTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    # ── A quién se le abre un socket (§4.3·6) ───────────────────────────────────────────────────
    (
        # `lh3…com.malo.net` CONTIENE uno permitido; `evil.lh3…com` TERMINA en uno permitido.
        'el host de la imagen se compara ENTERO',
        IMAGES,
        "        return in_array(mb_strtolower((string) ($parts['host'] ?? '')), self::HOSTS, true);",
        "        $host = mb_strtolower((string) ($parts['host'] ?? ''));\n\n        foreach (self::HOSTS as $permitido) {\n            if (str_contains($host, $permitido) || str_ends_with($host, $permitido)) {\n                return true;\n            }\n        }\n\n        return false;",
        php(I + 'test_una_url_fuera_de_la_lista_ni_se_pide'),
    ),
    (
        'la imagen tiene que venir por https',
        IMAGES,
        "        if (! is_array($parts) || mb_strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {\n            return false;\n        }",
        '        if (! is_array($parts)) {\n            return false;\n        }',
        php(I + 'test_una_url_fuera_de_la_lista_ni_se_pide'),
    ),
    (
        # ❗❗❗ Un 302 es la forma barata de sacar la petición de la lista blanca: el host se
        # comprueba sobre la URL que escribimos nosotros, no sobre la que nos diga un tercero.
        'NO se siguen redirecciones',
        IMAGES,
        "            $respuesta = Http::withOptions(['allow_redirects' => false, 'stream' => true])",
        "            $respuesta = Http::withOptions(['stream' => true])",
        php(I + 'test_no_se_siguen_redirecciones'),
    ),
    # ── Cuánto y qué entra (§4.3·6) ─────────────────────────────────────────────────────────────
    (
        # Una respuesta que no termina llena el disco y tumba el worker.
        'el TOPE de bytes corta',
        IMAGES,
        '            if (strlen($bytes) > self::MAX_BYTES) {\n                // Se corta en cuanto se pasa, no al final: de eso va el tope.\n                return null;\n            }',
        '',
        php(I + 'test_una_respuesta_demasiado_grande_se_corta'),
    ),
    (
        # Un SVG es XML con scripts dentro. Cae solo porque la lista es BLANCA.
        'un SVG no pasa por imagen',
        IMAGES,
        '        $extension = self::sniff($bytes);\n\n        if ($extension === null) {\n            return null;\n        }',
        "        $extension = self::sniff($bytes) ?? 'png';",
        php(I + 'test_un_svg_no_es_una_imagen_que_aceptemos'),
    ),
    (
        # La cabecera la escribe quien sirve el fichero, y aquí quien lo sirve es de fuera.
        'el tipo lo dicen los BYTES, no la cabecera',
        IMAGES,
        "        foreach (self::MAGIC as $extension => $firma) {\n            if (str_starts_with($bytes, $firma)) {\n                return $extension;\n            }\n        }",
        "        foreach (self::MAGIC as $extension => $firma) {\n            if (str_contains($bytes, $firma)) {\n                return $extension;\n            }\n        }\n\n        if (str_contains($bytes, '<svg')) {\n            return 'png';\n        }",
        php(I + 'test_el_tipo_lo_dicen_los_bytes_y_no_la_cabecera'),
    ),
    (
        'una respuesta que no es un 200 no deja nada',
        IMAGES,
        '        if (! $respuesta->successful()) {\n            return null;\n        }',
        '',
        php(I + 'test_una_respuesta_que_no_es_un_200_no_deja_nada'),
    ),
    # ── Cómo se guarda (§4.3·6) ─────────────────────────────────────────────────────────────────
    (
        # Derivarlo del autor pondría su nombre en una URL pública.
        'el nombre sale del CONTENIDO, no de la URL',
        IMAGES,
        "        $nombre = hash('sha256', $bytes).'.'.$extension;",
        "        $nombre = hash('sha256', basename(parse_url($url, PHP_URL_PATH) ?: 'x')).'.'.$extension;",
        php(I + 'test_el_nombre_no_lleva_nada_del_autor_ni_de_la_url'),
    ),
    (
        'no quedan ficheros a medio escribir',
        IMAGES,
        '        if ($disco->move($temporal, $nombre)) {\n            return $nombre;\n        }',
        '        return $temporal;',
        php(I + 'test_no_quedan_ficheros_a_medio_escribir'),
    ),
    # ── La ruta que las sirve (§4.3·6) ──────────────────────────────────────────────────────────
    (
        # Es lo que hace imposible el recorrido de directorios: la FORMA del nombre, no buscar «..».
        'la ruta comprueba la FORMA del nombre',
        IMAGES,
        "        return preg_match('/^[0-9a-f]{64}\\.(jpg|png|gif|webp)$/', $name) === 1;",
        '        return $name !== \'\';',
        php(I + 'test_un_fichero_del_disco_que_no_tiene_nuestro_nombre_no_se_sirve'),
    ),
    (
        'la imagen sale con su propia política de contenido',
        CTRL,
        '            \'Content-Security-Policy\' => "default-src \'none\'; sandbox",',
        '',
        php(I + 'test_la_politica_del_sitio_no_pisa_la_de_la_imagen'),
    ),
    (
        # ⚠️ No hay mutación de `nosniff`: la pone `SecurityHeaders` en TODA respuesta, así que
        # escribirla también aquí era una línea que ninguna mutación podía poner en rojo. Se retiró
        # del controlador; el caso sigue aseverando la propiedad.
        'un fichero del disco con extensión que no admitimos no se sirve',
        IMAGES,
        "        return preg_match('/^[0-9a-f]{64}\\.(jpg|png|gif|webp)$/', $name) === 1;",
        "        return preg_match('/^[0-9a-f]{64}\\./', $name) === 1;",
        php(I + 'test_un_fichero_del_disco_que_no_tiene_nuestro_nombre_no_se_sirve'),
    ),
    # ── El fichero se va con su fila (§4.3·6) ───────────────────────────────────────────────────
    (
        'borrar la fila se lleva sus ficheros',
        MODEL,
        '        static::deleting(function (self $review): void {\n            app(GoogleReviewImages::class)->forget($review->imagePaths());\n        });',
        '',
        php(I + 'test_borrar_la_fila_se_lleva_sus_ficheros'),
    ),
    (
        # No solo la del autor: las que adjuntó también son datos de un tercero.
        'también se van las fotos de la reseña, no solo la del autor',
        MODEL,
        '        foreach ($this->photos ?? [] as $foto) {\n            $rutas[] = is_string($foto) ? $foto : null;\n        }',
        '',
        php(I + 'test_borrar_la_fila_se_lleva_sus_ficheros'),
    ),
    (
        'el borrado no toca un fichero que no es nuestro',
        IMAGES,
        "            if (is_string($ruta) && $ruta !== '' && self::isOwnName($ruta)) {",
        "            if (is_string($ruta) && $ruta !== '') {",
        php(I + 'test_el_borrado_no_se_lleva_un_fichero_que_no_es_nuestro'),
    ),
    (
        'la pasada se lleva el fichero al retirar la reseña',
        SYNC,
        '                    $fila->delete();\n                    $retiradas++;',
        "                    GoogleBusinessReview::query()->where('review_name', $nombre)->delete();\n                    $retiradas++;",
        php(S + 'test_al_retirar_una_resena_su_fichero_se_va_con_ella'),
    ),
    # ── El barrido de huérfanos (§4.3·6) ────────────────────────────────────────────────────────
    (
        'el barrido no toca lo que está en uso',
        IMAGES,
        '            if (isset($vivos[$fichero])) {\n                continue;\n            }',
        '',
        php(I + 'test_el_barrido_no_toca_lo_que_esta_en_uso'),
    ),
    (
        # ⚠️ La carrera real: entre que la descarga escribe y la transacción confirma pasa un rato.
        'el barrido no toca lo recién escrito',
        IMAGES,
        '            if ($disco->lastModified($fichero) >= $corte) {\n                continue;\n            }',
        '',
        php(I + 'test_el_barrido_no_toca_lo_recien_escrito'),
    ),
    (
        # La MISMA línea que la mutación de arriba, y ése es el hallazgo: `imagePaths()` sostiene dos
        # promesas distintas —que el borrado se lleve las fotos y que el barrido no las tome por
        # huérfanas— y cada una tiene su caso.
        'el barrido mira las fotos de la reseña, no solo la del autor',
        MODEL,
        '        foreach ($this->photos ?? [] as $foto) {\n            $rutas[] = is_string($foto) ? $foto : null;\n        }',
        '',
        php(I + 'test_el_barrido_mira_tambien_las_fotos_de_la_resena'),
    ),
    # ── La pasada (§4.3·6) ──────────────────────────────────────────────────────────────────────
    (
        'la pasada DESCARGA la foto y guarda su ruta',
        SYNC,
        "                    $atributos['author_photo_path'] = $this->images->fetch($candidata->authorPhotoSourceUrl);",
        '',
        php(S + 'test_la_foto_se_descarga_y_lo_que_se_guarda_es_una_ruta_nuestra'),
    ),
    (
        # ❗❗ El atajo que deshace la T2 entera sin romper nada visible: la foto se vería igual y el
        # visitante volvería a pedírsela a Google. (Venía del arnés de la T2·3; se mudó aquí cuando
        # esta tanda pasó a rellenar la columna.)
        'la pasada guarda la RUTA, nunca la URL de Google',
        SYNC,
        "                    $atributos['author_photo_path'] = $this->images->fetch($candidata->authorPhotoSourceUrl);",
        "                    $atributos['author_photo_path'] = $candidata->authorPhotoSourceUrl;",
        php(S + 'test_la_foto_se_descarga_y_lo_que_se_guarda_es_una_ruta_nuestra'),
    ),
    (
        # Volver a pedirla cada día son doce imágenes diarias para reescribir los mismos bytes.
        'una foto ya descargada NO se vuelve a pedir',
        SYNC,
        '                if ($fila === null || $fila->author_photo_path === null) {',
        '                if (true) {',
        php(S + 'test_una_foto_ya_descargada_no_se_vuelve_a_pedir'),
    ),
    (
        # §4.3·6: si la descarga falla, la inicial — jamás la URL de Google.
        'si la descarga falla, la reseña se guarda igual',
        SYNC,
        '                if ($fila === null) {\n                    GoogleBusinessReview::create([\'review_name\' => $candidata->name] + $atributos);',
        "                if ($atributos['author_photo_path'] === null) {\n                    continue;\n                }\n\n                if ($fila === null) {\n                    GoogleBusinessReview::create(['review_name' => $candidata->name] + $atributos);",
        php(S + 'test_si_la_descarga_falla_la_resena_se_guarda_sin_foto'),
    ),
    # ── Desconectar (§4.3·6) ────────────────────────────────────────────────────────────────────
    (
        # Sin conexión no queda base para publicar el nombre y la cara de terceros.
        'desconectar se lleva también las reseñas',
        ADMIN,
        '        foreach (GoogleBusinessReview::query()->get() as $resena) {\n            $resena->delete();\n        }',
        '',
        php(D + 'test_desconectar_se_lleva_tambien_las_resenas_y_sus_ficheros'),
    ),
    (
        'desconectar se lleva también el resumen',
        ADMIN,
        '        GoogleBusinessReviewSummary::query()->delete();',
        '',
        php(D + 'test_desconectar_se_lleva_tambien_las_resenas_y_sus_ficheros'),
    ),
    # ── Los vídeos no (§4.3·6) ──────────────────────────────────────────────────────────────────
    (
        # Traer solo la miniatura enseñaría un fotograma como si fuera una foto del autor.
        'un elemento con vídeo se descarta ENTERO',
        INCOMING,
        "            if (! is_array($item) || ($item['videoUrl'] ?? null) !== null) {",
        '            if (! is_array($item)) {',
        php('GoogleReviewReaderTest::test_las_fotos_de_la_resena_llegan_sin_los_videos'),
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
