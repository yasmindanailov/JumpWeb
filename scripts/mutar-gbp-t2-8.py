#!/usr/bin/env python3
"""Arnés de mutación de la T2·8: la tarjeta de una reseña de la ficha
(`docs/specs/google-business-profile.md` §4.3·5, §4.3·8 y §4.3·10; `DECISIONES #524`, `#734`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · la **anónima** se pinta como «Usuario de Google», con su inicial, y una con nombre NO;
  · la **respuesta del parque** sale debajo de la reseña, y sus **fotos** —hasta cuatro, y el resto
    se dice— con su texto alternativo;
  · el texto conserva sus **saltos de línea** y su dirección;
  · la cifra sale con **«a fecha de»**, en la zona del parque, y **sin fecha no se inventa una**;
  · la **entradilla** deja de decir «no las elegimos nosotros» con la ficha, y la conserva con Places;
  · la **atribución** es la PALABRA con la ficha y sigue siendo el LOGOTIPO de Maps con Places;
  · los **enlaces** de la línea del filtro van aparte y parecen enlaces;
  · y la **fuente declara** su atribución: la vista no la deduce de `source`.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t2-8.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

VIEW = 'resources/views/anfitrion/portada.blade.php'
CSS = 'public/css/landing.css'
SRC = 'app/Domain/Content/Services/BusinessProfileSocialProof.php'
TIME = 'app/Domain/Platform/Services/DisplayTime.php'
ES = 'lang/es/landing.php'

C = 'ReviewCardTest::'
T = r'\App\Domain\Content\Contracts\Testimonial'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    # ── La anónima (§4.3·5) ─────────────────────────────────────────────────────────────────────
    (
        'la anónima se pinta como «Usuario de Google»',
        VIEW,
        "@php($autor = $op->anonymous ? __('landing.reviews.anonymous') : $op->author)",
        '@php($autor = $op->author)',
        php(C + 'test_una_anonima_sale_como_usuario_de_google'),
    ),
    (
        # Con el nombre vacío, `initial()` devuelve «·»: un punto por cara.
        'la inicial de la anónima sale de su rótulo',
        VIEW,
        '{{ $op->anonymous ? mb_strtoupper(mb_substr($autor, 0, 1)) : $op->initial() }}',
        '{{ $op->initial() }}',
        php(C + 'test_una_anonima_sale_como_usuario_de_google'),
    ),
    (
        'una con nombre NO se pinta como anónima',
        VIEW,
        '                                                {{ $autor }}',
        "                                                {{ __('landing.reviews.anonymous') }}",
        php(C + 'test_una_con_nombre_no_sale_como_anonima'),
    ),
    # ── La respuesta y las fotos (§4.3·10 y §4.3·6) ─────────────────────────────────────────────
    (
        'la respuesta del parque se pinta',
        VIEW,
        '                                @if ($op->reply)',
        '                                @if (false)',
        php(C + 'test_la_respuesta_del_parque_sale_debajo_de_la_resena'),
    ),
    (
        'la respuesta lleva su texto',
        VIEW,
        '<p class="rev__reply-text" dir="auto">{{ $op->reply }}</p>',
        '<p class="rev__reply-text" dir="auto"></p>',
        php(C + 'test_la_respuesta_del_parque_sale_debajo_de_la_resena'),
    ),
    (
        'las fotos se pintan',
        VIEW,
        '                                @if ($op->photos !== [])',
        '                                @if (false)',
        php(C + 'test_las_fotos_de_la_resena_se_pintan'),
    ),
    (
        'una foto sin `alt` no es una foto',
        VIEW,
        "alt=\"{{ __('landing.reviews.photo_alt', ['n' => $k + 1, 'total' => $fotos, 'name' => $autor]) }}\"",
        'alt=""',
        php(C + 'test_las_fotos_de_la_resena_se_pintan'),
    ),
    (
        'el carril enseña CUATRO como mucho',
        VIEW,
        '@foreach (array_slice($op->photos, 0, 4) as $k => $foto)',
        '@foreach ($op->photos as $k => $foto)',
        php(C + 'test_con_mas_de_cuatro_fotos_se_dice_cuantas_quedan'),
    ),
    (
        'y dice cuántas quedan fuera',
        VIEW,
        '                                        @if ($fotos > 4)',
        '                                        @if (false)',
        php(C + 'test_con_mas_de_cuatro_fotos_se_dice_cuantas_quedan'),
    ),
    # ── El texto (§4.3·8) ───────────────────────────────────────────────────────────────────────
    (
        'el texto conserva sus saltos de línea',
        CSS,
        'color: var(--fg); min-height: 93px; white-space: pre-line; }',
        'color: var(--fg); min-height: 93px; }',
        php(C + 'test_el_texto_conserva_sus_saltos_de_linea_y_su_direccion'),
    ),
    (
        'el texto declara su dirección',
        VIEW,
        '<p class="rev__text" x-show="!original" dir="auto"',
        '<p class="rev__text" x-show="!original"',
        php(C + 'test_el_texto_conserva_sus_saltos_de_linea_y_su_direccion'),
    ),
    # ── «A fecha de» (§4.3·10) ──────────────────────────────────────────────────────────────────
    (
        'la cifra sale con su fecha',
        VIEW,
        '                        @if ($socialRating->asOf)',
        '                        @if (false)',
        php(C + 'test_la_cifra_de_la_ficha_dice_a_fecha_de_en_la_hora_del_parque'),
    ),
    (
        'la fecha se escribe en la zona del PARQUE',
        TIME,
        "        return $carbon->setTimezone(self::timezone())->locale(app()->getLocale())->isoFormat('LL');",
        "        return $carbon->locale(app()->getLocale())->isoFormat('LL');",
        php(C + 'test_la_cifra_de_la_ficha_dice_a_fecha_de_en_la_hora_del_parque'),
    ),
    (
        # Places no la trae: escribir «a fecha de» de algo que no la tiene es inventarla.
        'sin fecha no se inventa una',
        VIEW,
        '                        @if ($socialRating->asOf)',
        '                        @if (true)',
        php(C + 'test_una_cifra_sin_fecha_no_se_inventa_una'),
    ),
    # ── La entradilla (§4.3·10) ─────────────────────────────────────────────────────────────────
    (
        'con la ficha, entradilla nueva',
        VIEW,
        "                    <p class=\"sec-head__lede\">{{ $socialSelection\n                        ? __('landing.reviews.lede_profile')",
        "                    <p class=\"sec-head__lede\">{{ false\n                        ? __('landing.reviews.lede_profile')",
        php(C + 'test_con_la_ficha_la_entradilla_no_dice_que_no_las_elegimos'),
    ),
    (
        'con Places, la suya sigue siendo verdad',
        VIEW,
        "                            ? __('landing.reviews.lede_google')",
        "                            ? __('landing.reviews.lede_profile')",
        php(C + 'test_con_places_la_entradilla_sigue_siendo_la_suya'),
    ),
    # ── La atribución (§4.3·10) ─────────────────────────────────────────────────────────────────
    (
        'con la ficha, NO el logotipo de Maps en la tarjeta',
        VIEW,
        '@if ($op->source === ' + T + '::SOURCE_GOOGLE && ! $porPalabra)',
        '@if ($op->source === ' + T + '::SOURCE_GOOGLE)',
        php(C + 'test_la_ficha_se_atribuye_con_la_palabra_y_no_con_el_logotipo_de_maps'),
    ),
    (
        'con Places, el logotipo SIGUE siendo obligatorio',
        VIEW,
        '@if ($op->source === ' + T + '::SOURCE_GOOGLE && ! $porPalabra)',
        '@if (false)',
        php(C + 'test_con_places_el_logotipo_de_maps_sigue_siendo_obligatorio'),
    ),
    (
        'la tarjeta de la ficha lleva la palabra al pie',
        VIEW,
        '                                @if ($porPalabra)\n                                    <p class="rev__via">',
        '                                @if (false)\n                                    <p class="rev__via">',
        php(C + 'test_la_ficha_se_atribuye_con_la_palabra_y_no_con_el_logotipo_de_maps'),
    ),
    (
        'el recuento dice «en Google» con la ficha',
        VIEW,
        "trans_choice($cifraPorPalabra ? 'landing.reviews.count_on_google' : 'landing.reviews.count'",
        "trans_choice('landing.reviews.count'",
        php(C + 'test_la_ficha_se_atribuye_con_la_palabra_y_no_con_el_logotipo_de_maps'),
    ),
    (
        'la chapa de la ficha no lleva el logotipo de Maps',
        VIEW,
        '                        @if ($cifraPorPalabra)',
        '                        @if (false)',
        php(C + 'test_la_ficha_se_atribuye_con_la_palabra_y_no_con_el_logotipo_de_maps'),
    ),
    (
        # La vista no puede deducirla de `source`: las dos fuentes dicen «google».
        'la FUENTE declara la atribución de la cifra',
        SRC,
        '            attribution: TestimonialData::ATTRIBUTION_GOOGLE_WORD,\n        );',
        '        );',
        php('BusinessProfileSocialProofTest::test_la_ficha_declara_que_se_atribuye_con_la_palabra'),
    ),
    (
        'la FUENTE declara la atribución de cada tarjeta',
        SRC,
        '            attribution: TestimonialData::ATTRIBUTION_GOOGLE_WORD,\n        ))->values();',
        '        ))->values();',
        php('BusinessProfileSocialProofTest::test_la_ficha_declara_que_se_atribuye_con_la_palabra'),
    ),
    # ── Los enlaces de la línea del filtro ──────────────────────────────────────────────────────
    (
        'los enlaces van en su propia fila',
        VIEW,
        '                                <span class="rev-sec__links">',
        '                                <span>',
        php(C + 'test_los_enlaces_de_la_linea_del_filtro_van_aparte_y_parecen_enlaces'),
    ),
    (
        'y parecen enlaces',
        CSS,
        '  color: var(--interactive); font-weight: 700; text-decoration: underline; text-underline-offset: 3px;',
        '  color: var(--interactive); font-weight: 700;',
        php(C + 'test_los_enlaces_de_la_linea_del_filtro_van_aparte_y_parecen_enlaces'),
    ),
    (
        # El rótulo de la anónima vive en `lang/`, no en la vista: lo traduce un traductor.
        'el rótulo de la anónima es texto traducible',
        ES,
        "        'anonymous' => 'Usuario de Google',",
        "        'anonymous' => '',",
        php(C + 'test_una_anonima_sale_como_usuario_de_google'),
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
        mutated = original.replace(old, new, 1)
        if mutated == original:
            print(f'la mutación «{name}» no cambia nada en {path}')
            return 2
        file.write_text(mutated, encoding='utf-8')
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
