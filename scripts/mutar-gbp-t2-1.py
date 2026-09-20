#!/usr/bin/env python3
"""Arnés de mutación de la T2·1: la tabla de reseñas, el plazo y la guarda de la imagen
(`docs/specs/google-business-profile.md` §4.3·2 → §4.3·11; `DECISIONES #524`, `#727`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · el plazo se aplica **al leer**, y su borde es un instante y no una fecha;
  · el **nombre y la cara** de un tercero caducan antes que su texto;
  · la purga se lleva lo de la política y **no** lo que ya no se lee: son dos plazos;
  · una imagen es una **ruta nuestra**, y eso incluye la URL sin esquema y la incrustada;
  · la guarda muerde también **al actualizar**, que es por donde entraría de verdad;
  · la identidad de una reseña y la fila única del resumen son **reglas de la base**;
  · y una media vieja **deja de enseñarse** en vez de enseñarse vieja.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t2-1.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

MODEL = 'app/Domain/Content/Models/GoogleBusinessReview.php'
SUMMARY = 'app/Domain/Content/Models/GoogleBusinessReviewSummary.php'
MIGRATION = 'database/migrations/2026_09_20_220000_create_google_business_reviews.php'

T = 'GoogleBusinessReviewTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        # El filtro es la GARANTÍA del plazo: sin él, una fila caducada se pinta mientras la purga
        # no haya corrido, y la purga es un comando que puede no haber corrido.
        'el plazo se aplica AL LEER',
        MODEL,
        "        return $query->where('fetched_at', '>=', now()->subDays(self::FRESH_DAYS));",
        '        return $query;',
        php(T + 'test_un_segundo_pasado_el_borde_la_resena_ya_no_se_lee'),
    ),
    (
        # Un `>` en vez de `>=` deja la ventana en 28 días y el número de la constante mentiría.
        'el borde de la ventana se INCLUYE',
        MODEL,
        "        return $query->where('fetched_at', '>=', now()->subDays(self::FRESH_DAYS));",
        "        return $query->where('fetched_at', '>', now()->subDays(self::FRESH_DAYS));",
        php(T + 'test_la_ventana_de_lectura_llega_justo_hasta_su_borde'),
    ),
    (
        # ❗❗ El autor borró su reseña y la sincronización lleva días rota: su cara no puede seguir
        # en la portada porque el fallo sea nuestro.
        'el nombre y la cara CADUCAN sin una pasada que los confirme',
        MODEL,
        '        return $this->fetched_at->greaterThanOrEqualTo(now()->subDays(self::IDENTIFIED_DAYS));',
        '        return true;',
        php(T + 'test_sin_una_pasada_que_la_confirme_el_autor_deja_de_publicarse'),
    ),
    (
        # La cara caduca con el nombre, no después: es el mismo dato personal.
        'la FOTO caduca con el nombre',
        MODEL,
        '        return $this->identified() ? $this->author_photo_path : null;',
        '        return $this->author_photo_path;',
        php(T + 'test_sin_una_pasada_que_la_confirme_el_autor_deja_de_publicarse'),
    ),
    (
        # Igualar los dos plazos borraría el margen del que vive la separación entre filtro y purga.
        'la purga usa el tope de la POLÍTICA, no la ventana de lectura',
        MODEL,
        "        return static::query()->where('fetched_at', '<', now()->subDays(self::RETENTION_DAYS));",
        "        return static::query()->where('fetched_at', '<', now()->subDays(self::FRESH_DAYS));",
        php(T + 'test_la_purga_solo_se_lleva_lo_que_la_politica_ya_no_permite'),
    ),
    (
        # El atajo que deshace la tanda entera sin romper nada visible.
        'una URL de Google NO entra en la tabla',
        MODEL,
        '            $review->guardAgainstForeignImage($review->author_photo_path, \'author_photo_path\');',
        '',
        php(T + 'test_la_foto_del_autor_no_puede_ser_una_url_de_google'),
    ),
    (
        # ⚠️ `//host/…` no tiene esquema y PARECE una ruta; el navegador le pone el de la página.
        # Una guarda que solo buscara «https://» la dejaría pasar, y es como vuelve el defecto.
        'una URL SIN ESQUEMA también se rechaza',
        MODEL,
        "    private const URL_SHAPE = '#^(?:[a-z][a-z0-9+.\\-]*:|//)#i';",
        "    private const URL_SHAPE = '#^https?://#i';",
        php(T + 'test_una_url_sin_esquema_tambien_se_rechaza'),
    ),
    (
        'las fotos de la RESEÑA se miran una a una',
        MODEL,
        "                $review->guardAgainstForeignImage(is_string($photo) ? $photo : null, 'photos');",
        '',
        php(T + 'test_una_foto_de_la_resena_no_puede_ser_una_url_de_google'),
    ),
    (
        # La re-sincronización ACTUALIZA filas que ya existen: `creating()` no vería ese camino.
        'la guarda muerde también al ACTUALIZAR',
        MODEL,
        '        static::saving(function (self $review): void {',
        '        static::creating(function (self $review): void {',
        php(T + 'test_la_guarda_tambien_muerde_al_actualizar'),
    ),
    (
        # Dos pasadas solapadas insertarían la misma reseña dos veces.
        'la identidad de una reseña es REGLA de la base',
        MIGRATION,
        "            $table->string('review_name')->unique();",
        "            $table->string('review_name');",
        php(T + 'test_dos_filas_no_pueden_compartir_el_nombre_de_google'),
    ),
    (
        # Dos resúmenes son un estado que nadie sabe leer.
        'el resumen es UNA fila, por índice único',
        MIGRATION,
        "            $table->unique('singleton');",
        '',
        php(T + 'test_un_segundo_resumen_es_imposible'),
    ),
    (
        # Una media que lleva días sin comprobarse es una afirmación falsa sobre un tercero.
        'una media VIEJA deja de enseñarse',
        SUMMARY,
        '            && $this->fetched_at !== null\n            && $this->fetched_at->greaterThanOrEqualTo(now()->subDays(self::FRESH_DAYS));',
        ';',
        php(T + 'test_un_resumen_viejo_no_se_publica'),
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
