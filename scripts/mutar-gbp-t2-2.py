#!/usr/bin/env python3
"""Arnés de mutación de la T2·2: traer las reseñas de la ficha
(`docs/specs/google-business-profile.md` §4.3·1 → §4.3·3, §4.3·5, §4.3·8 y §4.3·10;
`DECISIONES #524`, `#728`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · se publica **el original** del autor, en los dos órdenes de los marcadores;
  · lo que no se puede separar **falla cerrado**: crudo, marcado, y también para las variantes
    del marcador que todavía no se han medido;
  · una **anónima** llega sin nombre y sin foto, y la foto se compara por host ENTERO y con `https`;
  · una reseña **sin nota** no se cuela con una nota inventada, y una fecha ilegible no tumba la pasada;
  · `seen` cuenta **filas**, no candidatas — de eso depende que un filtro estricto no borre la tabla;
  · el recorrido **se deduplica**, **ordena antes de recortar** y **dice cuándo quedó a medias**;
  · un testigo de página **vacío** termina el recorrido en vez de repetirlo para siempre;
  · y una pasada **incoherente** no se puede creer, en sus tres formas.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t2-2.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

TEXT = 'app/Domain/Content/Services/GoogleReviewText.php'
INCOMING = 'app/Domain/Content/Services/IncomingGoogleReview.php'
READER = 'app/Domain/Content/Services/GoogleReviewReader.php'
PASS = 'app/Domain/Content/Services/GoogleReviewPass.php'
FILTER = 'app/Domain/Content/Services/GoogleReviewFilter.php'
API = 'app/Domain/Platform/Services/GoogleBusinessApi.php'

X = 'GoogleReviewTextTest::'
R = 'GoogleReviewReaderTest::'

AMBIGUO = X + 'test_lo_que_no_se_puede_separar_se_guarda_crudo_y_se_marca'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    # ── El texto (§4.3·8) ───────────────────────────────────────────────────────────────────────
    (
        # ❗ Publicar la traducción es firmar con el nombre y la cara de una persona un texto que
        # escribió una máquina.
        'se publica el ORIGINAL, no la traducción',
        TEXT,
        '        $original = trim($original);',
        '        $original = trim(substr($texto, $inicioTraducido + strlen(self::TRANSLATED), max(0, $inicioOriginal - $inicioTraducido - strlen(self::TRANSLATED))));',
        php(X + 'test_con_la_traduccion_delante_se_publica_el_original'),
    ),
    (
        # §4.3·8 dice que el orden VARÍA; dar por hecho uno solo se lleva media reseña por delante.
        'el ORDEN de los marcadores puede ser el otro',
        TEXT,
        '''        $original = $inicioOriginal > $inicioTraducido
            ? substr($texto, $inicioOriginal + strlen(self::ORIGINAL))
            : substr($texto, $inicioOriginal + strlen(self::ORIGINAL), $inicioTraducido - $inicioOriginal - strlen(self::ORIGINAL));''',
        '        $original = substr($texto, $inicioOriginal + strlen(self::ORIGINAL));',
        php(X + 'test_con_el_original_delante_tambien_se_publica_el_original'),
    ),
    (
        # Media pareja o pareja repetida: un autor puede escribir «(Original)» dentro de su reseña.
        'media pareja de marcadores FALLA CERRADO',
        TEXT,
        '        if ($traducciones !== 1 || $originales !== 1) {',
        '        if (false) {',
        php(AMBIGUO),
    ),
    (
        # La red para las variantes del marcador que aún no se han medido (§6·T2① está pendiente).
        'un paréntesis que nombra a Google y no se separó, se marca',
        TEXT,
        '        return preg_match(self::SUSPICION, $texto) === 1;',
        '        return false;',
        php(AMBIGUO),
    ),
    (
        'marcadores sin original detrás también fallan cerrado',
        TEXT,
        '''        return $original === ''
            ? new self($texto, true)
            : new self($original, false);''',
        '        return new self($original, false);',
        php(AMBIGUO),
    ),
    # ── Lo que se trae de cada reseña (§4.3·5) ───────────────────────────────────────────────────
    (
        # ❗❗ El `null` entra ANTES del INSERT: así su nombre no existe en la base por ningún camino.
        'una ANÓNIMA se queda sin nombre',
        INCOMING,
        "            authorName: $anonymous ? null : self::name($reviewer['displayName'] ?? null),",
        "            authorName: self::name($reviewer['displayName'] ?? null),",
        php(R + 'test_una_anonima_llega_sin_nombre_y_sin_foto'),
    ),
    (
        'una ANÓNIMA se queda sin foto',
        INCOMING,
        "            authorPhotoSourceUrl: $anonymous ? null : self::photoUrl($reviewer['profilePhotoUrl'] ?? null),",
        "            authorPhotoSourceUrl: self::photoUrl($reviewer['profilePhotoUrl'] ?? null),",
        php(R + 'test_una_anonima_llega_sin_nombre_y_sin_foto'),
    ),
    (
        # ⚠️ El clásico: `lh3.googleusercontent.com.malo.net` CONTIENE el host permitido.
        'el host de la foto no vale con CONTENER el permitido',
        INCOMING,
        "        return in_array(mb_strtolower((string) ($parts['host'] ?? '')), self::PHOTO_HOSTS, true) ? $url : null;",
        "        $host = mb_strtolower((string) ($parts['host'] ?? ''));\n\n        foreach (self::PHOTO_HOSTS as $permitido) {\n            if (str_contains($host, $permitido)) {\n                return $url;\n            }\n        }\n\n        return null;",
        php(R + 'test_una_foto_que_no_es_de_un_host_de_google_no_se_acepta'),
    ),
    (
        # ⚠️ Y el otro lado, que el arnés destapó porque faltaba su caso: `evil.lh3.googleuser‑
        # content.com` TERMINA en el host permitido y un `str_ends_with` lo daría por bueno.
        'el host de la foto tampoco vale con TERMINAR en el permitido',
        INCOMING,
        "        return in_array(mb_strtolower((string) ($parts['host'] ?? '')), self::PHOTO_HOSTS, true) ? $url : null;",
        "        $host = mb_strtolower((string) ($parts['host'] ?? ''));\n\n        foreach (self::PHOTO_HOSTS as $permitido) {\n            if (str_ends_with($host, $permitido)) {\n                return $url;\n            }\n        }\n\n        return null;",
        php(R + 'test_una_foto_que_no_es_de_un_host_de_google_no_se_acepta'),
    ),
    (
        'la foto tiene que venir por https',
        INCOMING,
        "        if (! is_array($parts) || mb_strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {\n            return null;\n        }\n\n        return in_array(mb_strtolower((string) ($parts['host'] ?? '')), self::PHOTO_HOSTS, true) ? $url : null;",
        "        if (! is_array($parts)) {\n            return null;\n        }\n\n        return in_array(mb_strtolower((string) ($parts['host'] ?? '')), self::PHOTO_HOSTS, true) ? $url : null;",
        php(R + 'test_una_foto_que_no_es_de_un_host_de_google_no_se_acepta'),
    ),
    (
        # «Sin nota» no es una nota. Inventarle uno la cuela como candidata.
        'una reseña SIN NOTA no se cuela con una inventada',
        INCOMING,
        "        'FIVE' => 5,\n    ];",
        "        'FIVE' => 5,\n        'STAR_RATING_UNSPECIFIED' => 5,\n    ];",
        php(R + 'test_una_resena_sin_nota_no_sirve'),
    ),
    (
        # `parse()` LANZA con una cadena que no entiende, y esto viene de fuera.
        'una fecha ilegible no tumba la pasada entera',
        INCOMING,
        '        } catch (Throwable) {\n            return null;\n        }',
        '        } catch (Throwable $e) {\n            throw $e;\n        }',
        php(R + 'test_una_resena_con_una_fecha_ilegible_no_sirve'),
    ),
    # ── El recorrido (§4.3·1 → §4.3·3) ───────────────────────────────────────────────────────────
    (
        # ⚠️⚠️ La más cara del lote: contando solo candidatas, un filtro estricto se lee como una
        # ficha vacía y §4.3·3 borra la tabla por el motivo equivocado.
        '`seen` cuenta FILAS, no candidatas',
        READER,
        '                $vistas++;\n\n                $resena = IncomingGoogleReview::fromApi($fila);\n\n                if ($resena === null || ! $filter->accepts($resena)) {\n                    continue;\n                }',
        '                $resena = IncomingGoogleReview::fromApi($fila);\n\n                if ($resena === null || ! $filter->accepts($resena)) {\n                    continue;\n                }\n\n                $vistas++;',
        php(R + 'test_las_que_no_llegan_al_minimo_de_estrellas_se_quedan_fuera'),
    ),
    (
        'las reseñas se DEDUPLICAN por su nombre de recurso',
        READER,
        '                $candidatas[$resena->name] = $resena;',
        '                $candidatas[] = $resena;',
        php(R + 'test_una_resena_repetida_entre_paginas_se_deduplica'),
    ),
    (
        # Una pasada cortada por el tope no ha visto la ficha entera y no puede borrar.
        'una pasada cortada por el tope se DECLARA incompleta',
        READER,
        '        $completa = false;',
        '        $completa = true;',
        php(R + 'test_el_tope_de_paginas_corta_y_la_pasada_queda_incompleta'),
    ),
    (
        'se ORDENA antes de recortar',
        READER,
        'usort($lista, fn (IncomingGoogleReview $a, IncomingGoogleReview $b) => $b->createdAt <=> $a->createdAt);',
        'usort($lista, fn (IncomingGoogleReview $a, IncomingGoogleReview $b) => $a->createdAt <=> $b->createdAt);',
        php(R + 'test_se_guardan_las_mas_recientes_y_se_recorta_al_tope'),
    ),
    # ── La paginación (§4.3·1) ───────────────────────────────────────────────────────────────────
    (
        'el testigo de la página anterior SE MANDA',
        API,
        "        if ($pageToken !== null && $pageToken !== '') {\n            $query['pageToken'] = $pageToken;\n        }",
        '',
        php(R + 'test_la_pagina_se_pide_con_el_tamano_maximo_y_el_testigo_de_la_anterior'),
    ),
    (
        # «Hay más, con una cadena vacía» no existe: tratarlo como un testigo pide la MISMA página
        # para siempre.
        'un testigo VACÍO es «no hay más»',
        API,
        "            'nextPageToken' => is_string($siguiente) && $siguiente !== '' ? $siguiente : null,",
        "            'nextPageToken' => is_string($siguiente) ? $siguiente : null,",
        php(R + 'test_un_testigo_de_pagina_vacio_termina_el_recorrido'),
    ),
    # ── Cuándo se puede creer una pasada (§4.3·3) ────────────────────────────────────────────────
    (
        'una pasada INCOMPLETA no se puede creer',
        PASS,
        '        if (! $this->complete) {\n            return false;\n        }',
        '        if (false) {\n            return false;\n        }',
        php(R + 'test_el_tope_de_paginas_corta_y_la_pasada_queda_incompleta'),
    ),
    (
        # La ficha se movió mientras se paginaba: lo recogido es una mezcla de dos momentos.
        'la ficha tuvo que estarse QUIETA entre la primera página y la última',
        PASS,
        '        if ($this->firstTotal !== $this->lastTotal || $this->firstAverage !== $this->lastAverage) {\n            return false;\n        }',
        '        if (false) {\n            return false;\n        }',
        php(R + 'test_si_el_total_cambia_entre_la_primera_pagina_y_la_ultima_no_se_puede_creer'),
    ),
    (
        'una lista VACÍA con total mayor que cero no se puede creer',
        PASS,
        '        return ! ($this->lastTotal > 0 && $this->seen === 0);',
        '        return true;',
        php(R + 'test_una_lista_vacia_con_total_mayor_que_cero_no_se_puede_creer'),
    ),
    # ── El ajuste del panel (§4.3·10) ────────────────────────────────────────────────────────────
    (
        # Un 0 apaga el filtro sin decirlo y un 7 vacía la sección para siempre.
        'el mínimo de estrellas se ACOTA a 1–5',
        FILTER,
        '        return new self(max(1, min(5, $minimo)));',
        '        return new self($minimo);',
        php(R + 'test_el_minimo_de_estrellas_sale_del_panel_y_se_acota'),
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
