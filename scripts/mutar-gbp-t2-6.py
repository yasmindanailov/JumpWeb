#!/usr/bin/env python3
"""Arnés de mutación de la T2·6: el contrato y la sección
(`docs/specs/google-business-profile.md` §4.3·9 y §4.3·10; `DECISIONES #524`, `#732`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · la cifra sale de Google **con su fecha**, y **no se filtra** aunque las tarjetas sí;
  · hay cifra **aunque el filtro no deje ninguna tarjeta**, y el umbral del owner se conserva;
  · el plazo largo y el CORTO llegan hasta la tarjeta: sin pasada que la confirme, sale anónima;
  · las imágenes salen como **rutas nuestras**, nunca como un host de terceros;
  · la **línea del filtro** viaja en el contrato y la pinta la sección — es la Ómnibus, no diseño;
  · la selección sale de **la fuente que responde**, no de la primera que tenga una;
  · la ficha **va delante** de Places y responde **sin consentimiento**;
  · el bloque lleva `data-nosnippet` y el JSON-LD **no** lleva nota agregada.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t2-6.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

SRC = 'app/Domain/Content/Services/BusinessProfileSocialProof.php'
CASCADA = 'app/Domain/Content/Services/FallingBackSocialProof.php'
CTRL = 'app/Http/Controllers/HomeController.php'
VIEW = 'resources/views/anfitrion/portada.blade.php'
CONTRATO = 'app/Http/Instancia/InstanceViews.php'

B = 'BusinessProfileSocialProofTest::'
D = 'ReviewDisclosureTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    # ── La cifra (§4.3·10) ──────────────────────────────────────────────────────────────────────
    (
        # Publicarla a secas la presenta como «ahora mismo», y la trajo una pasada diaria.
        'la cifra sale con su FECHA',
        SRC,
        '            asOf: $resumen->fetched_at,',
        '            asOf: null,',
        php(B + 'test_la_cifra_sale_de_google_con_su_fecha'),
    ),
    (
        # Una media que lleva días sin comprobarse es una afirmación falsa sobre un tercero.
        'una cifra VIEJA no se publica',
        SRC,
        '        if ($resumen === null || ! $resumen->publishable() || $resumen->total_review_count < self::MIN_REVIEWS) {',
        '        if ($resumen === null) {',
        php(B + 'test_sin_pasada_reciente_no_hay_cifra'),
    ),
    (
        # ❗❗ Que la cifra bajara al filtrar sería el parque cambiando la nota de su propia ficha.
        'la cifra NO se compone con las tarjetas',
        SRC,
        '            count: $resumen->total_review_count,',
        '            count: $this->reviews()->count(),',
        php(B + 'test_la_cifra_no_se_filtra_aunque_las_tarjetas_si'),
    ),
    (
        # §4.3·10, literal: «si deja 0, las opiniones propias, y la media se sigue enseñando».
        'hay cifra aunque no quede ninguna tarjeta',
        SRC,
        '        $resumen = GoogleBusinessReviewSummary::current();\n\n        if ($resumen === null || ! $resumen->publishable()',
        '        $resumen = $this->reviews()->isEmpty() ? null : GoogleBusinessReviewSummary::current();\n\n        if ($resumen === null || ! $resumen->publishable()',
        php(B + 'test_hay_cifra_aunque_el_filtro_no_deje_ninguna_tarjeta'),
    ),
    (
        # `#494`, definitivo. Subirlo «para que la media sea más estable» apaga el widget entero.
        'el umbral del owner sigue en UNA reseña',
        SRC,
        '    public const MIN_REVIEWS = 1;',
        '    public const MIN_REVIEWS = 10;',
        php(B + 'test_el_umbral_del_owner_se_conserva'),
    ),
    # ── Los plazos llegan hasta la tarjeta (§4.3·4) ─────────────────────────────────────────────
    (
        # ❗❗ Leer la columna a pelo publicaría el nombre de alguien cuya reseña puede llevar días
        # borrada de Google.
        'el plazo CORTO llega hasta la tarjeta',
        SRC,
        "            author: $r->publishableAuthor() ?? '',",
        "            author: $r->author_name ?? '',",
        php(B + 'test_sin_una_pasada_que_la_confirme_la_tarjeta_sale_anonima'),
    ),
    (
        'la foto caduca con el nombre',
        SRC,
        '            avatarUrl: self::photoUrl($r->publishablePhotoPath()),',
        '            avatarUrl: self::photoUrl($r->author_photo_path),',
        php(B + 'test_sin_una_pasada_que_la_confirme_la_tarjeta_sale_anonima'),
    ),
    (
        'el plazo LARGO se aplica al leer',
        SRC,
        '            ->withinRetention()',
        '',
        php(B + 'test_una_resena_pasada_de_plazo_no_se_ensena'),
    ),
    (
        'la portada enseña SEIS, no las doce que se guardan',
        SRC,
        '            ->limit(self::SHOWN)',
        '',
        php(B + 'test_se_ensenan_las_mas_recientes_y_solo_seis'),
    ),
    # ── Las imágenes (§4.3·9) ───────────────────────────────────────────────────────────────────
    (
        # Lo que sale de aquí va directo al `src` de un `<img>` en la portada.
        'la imagen sale como RUTA nuestra',
        SRC,
        "        return route('resenas.foto', ['fichero' => $path]);",
        '        return $path;',
        php(B + 'test_la_tarjeta_trae_la_respuesta_del_parque_y_sus_fotos'),
    ),
    (
        'la respuesta del parque viaja con la reseña',
        SRC,
        '            reply: $r->reply_comment,',
        '            reply: null,',
        php(B + 'test_la_tarjeta_trae_la_respuesta_del_parque_y_sus_fotos'),
    ),
    # ── La línea del filtro (§4.3·10 · Ómnibus) ─────────────────────────────────────────────────
    (
        # ❗❗❗ Sin esto la landing NO PUEDE cumplir la Ómnibus aunque quiera: no sabría qué decir.
        'la selección viaja en el contrato',
        SRC,
        '        return new ReviewSelection(',
        '        return null;\n\n        return new ReviewSelection(',
        php(B + 'test_la_seleccion_dice_el_minimo_y_los_dos_enlaces'),
    ),
    (
        # Si alguien pone 9 en el panel, lo que hay que decir es lo que se aplica.
        'se declara el mínimo EFECTIVO, no el de la casilla',
        SRC,
        '            minStars: GoogleReviewFilter::fromSettings()->minStars,',
        "            minStars: (int) \\App\\Domain\\Platform\\Models\\Setting::value(GoogleReviewFilter::MIN_STARS_KEY, 4),",
        php(B + 'test_el_minimo_que_se_declara_es_el_efectivo'),
    ),
    (
        # Avisar de un filtro que no se está aplicando a lo que se ve es peor que callar.
        'sin tarjetas no se declara filtro',
        SRC,
        '        if ($this->reviews()->isEmpty()) {\n            return null;\n        }',
        '',
        php(B + 'test_sin_tarjetas_no_hay_nada_que_declarar'),
    ),
    (
        'la selección sale de la fuente que RESPONDE',
        CASCADA,
        '        return $this->elegida()?->selection();',
        '        foreach ($this->fuentes as $fuente) {\n            $s = $fuente->selection();\n\n            if ($s !== null) {\n                return $s;\n            }\n        }\n\n        return null;',
        php(B + 'test_una_fuente_que_declara_filtro_pero_no_responde_no_pinta_su_linea'),
    ),
    (
        'el controlador PASA la selección a la vista',
        CTRL,
        "            'socialSelection' => app(SocialProof::class)->selection(),",
        "            'socialSelection' => null,",
        php(D + 'test_con_filtro_la_seccion_declara_que_filtra'),
    ),
    (
        'la sección PINTA la línea del filtro',
        VIEW,
        "                            {{ __('landing.reviews.filtered', ['stars' => $socialSelection->minStars]) }}",
        '',
        php(D + 'test_con_filtro_la_seccion_declara_que_filtra'),
    ),
    (
        'sin filtro la sección NO avisa de un filtro',
        VIEW,
        '                    @if ($socialSelection)',
        '                    @if (true)',
        php(D + 'test_sin_filtro_no_se_avisa_de_un_filtro'),
    ),
    (
        'el contrato de instancia DECLARA la selección',
        CONTRATO,
        "                'socialProof', 'socialRating', 'socialLocked', 'socialSelection',",
        "                'socialProof', 'socialRating', 'socialLocked',",
        EXEC + ['php', 'artisan', 'test', '--filter', 'InstanceViewContractTest'],
    ),
    # ── La cascada de tres (§4.3·9) ─────────────────────────────────────────────────────────────
    (
        'la ficha va DELANTE de las opiniones propias',
        CASCADA,
        '        return $this->elegida()?->testimonials() ?? collect();',
        '        return $this->fuentes[count($this->fuentes) - 1]->testimonials();',
        php(B + 'test_la_ficha_va_delante_de_las_opiniones_propias'),
    ),
    (
        # ❗❗❗ Lo que la T2 vino a arreglar: sin consentimiento la sección ya no cae a las propias.
        'la ficha responde SIN consentimiento',
        SRC,
        '    public function reviewsNeedConsent(): bool\n    {\n        return false;\n    }',
        '    public function reviewsNeedConsent(): bool\n    {\n        return true;\n    }',
        php(B + 'test_la_ficha_responde_aunque_el_visitante_no_acepte_terceros'),
    ),
    # 📜 Aquí iba «las de Places SÍ necesitan consentimiento»: su sujeto se retiró en `#771`. El mecanismo del permiso
    # se prueba con una fuente de prueba en `ReviewsCascadeConsentTest`.
    (
        'la cifra se recorre aunque las opiniones las den las propias',
        CASCADA,
        '        foreach ($this->fuentes as $fuente) {\n            $cifra = $fuente->rating();\n\n            if ($cifra !== null) {\n                return $cifra;\n            }\n        }\n\n        return null;',
        '        return $this->elegida()?->rating();',
        php(B + 'test_la_cifra_de_la_ficha_se_ensena_aunque_respondan_las_propias'),
    ),
    (
        # Con `bind` serían cuatro objetos y cuatro recorridos de la cascada por sección (`PERF-02`).
        'el contrato se resuelve UNA vez por petición',
        'app/Providers/AppServiceProvider.php',
        '        $this->app->scoped(SocialProof::class, function (): SocialProof {',
        '        $this->app->bind(SocialProof::class, function (): SocialProof {',
        php(B + 'test_el_contrato_lo_resuelve_una_sola_instancia_por_peticion'),
    ),
    # ── Lo que no puede salir en un buscador (§4.3·10) ──────────────────────────────────────────
    (
        'el bloque de reseñas lleva `data-nosnippet`',
        VIEW,
        '        <section id="reviews" class="section wrap" data-nosnippet>',
        '        <section id="reviews" class="section wrap">',
        php(D + 'test_el_bloque_de_resenas_lleva_data_nosnippet'),
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
