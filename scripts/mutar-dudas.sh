#!/usr/bin/env bash
# Arnés de mutación de la SECCIÓN 08 «Dudas» (`DECISIONES #488`, carril de diseño Fase 2 · T2h,
# `specs/rediseno-desde-canvas.md` §5.4).
#
# Las propiedades que protege, en una línea cada una:
#   · con el panel vacío la sección ENTERA desaparece —ni rótulo, ni titular, ni caja, ni FAQPage—;
#   · el titular es una FRASE de 3 a 6 palabras y no el rótulo;
#   · todas las dudas nacen CERRADAS, en el fuente y en el bundle;
#   · cero salida: ni un enlace, y un solo pulsable por duda;
#   · el acordeón es UNA tarjeta y el filete va ENTRE filas, nunca encima de la primera;
#   · el signo son DOS iconos del set y ninguno gira;
#   · el pulsable mide 64 y por eso no lleva `data-tap`;
#   · en escritorio la cabecera son cuatro columnas y el acordeón ocho (352 y 736).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca
# `grep passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA en RUTA FIJA que se
# repara al arrancar (`#448`: un `trap … EXIT` no corre con SIGKILL y deja el árbol mutado).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='DudasSectionTest|FaqAccordionTest|SectionHeadlineTest|ShapeScaleTest|InteractionColourIsNotAZoneTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="storage/app/mutaciones/dudas"
FICHEROS=(
    resources/views/home.blade.php
    public/css/landing.css
    resources/js/app.js
    lang/es/landing.php
)
restaurar() {
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cp "$TMP/$(basename "$f")" "$f"; touch "$f"
    done
}

if [ -d "$TMP" ]; then
    sucios=0
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cmp -s "$f" "$TMP/$(basename "$f")" || sucios=$((sucios + 1))
    done
    if [ "$sucios" -gt 0 ]; then
        echo "⚠️  Una ejecución anterior murió sin restaurar: ${sucios} fichero(s) MUTADOS en el árbol."
        restaurar
        echo '✓ restaurados desde la copia. Comprueba con `git diff` antes de seguir.'
    fi
    rm -rf "$TMP"
fi

mkdir -p "$TMP"
trap 'restaurar; rm -rf "$TMP"' EXIT INT TERM
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'
echo

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))

    if [ ! -f "$TMP/$(basename "$fichero")" ]; then
        echo "✗ «$nombre» quiere mutar «$fichero», que NO está en FICHEROS: sin copia no hay" >&2
        echo "  restauración, y el veredicto de toda la tanda deja de valer. Añádelo al array." >&2
        exit 1
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

HB=resources/views/home.blade.php
CSS=public/css/landing.css
JS=resources/js/app.js
ES=lang/es/landing.php

echo '── El panel vacío ──'

# 1 · la regla dura del sistema: una sección que pone el panel desaparece con cero filas.
mutar "la sección se pinta con el panel vacío" "$HB" \
  '    @if ($faqs->isNotEmpty())' \
  '    @if (true)'

echo
echo '── La cabecera común ──'

# 2 · el defecto que esta tanda arregla: el titular vuelve a decir el rótulo.
mutar "el titular vuelve a ser el rótulo" "$ES" \
  "        'title' => 'Lo que más nos preguntáis'," \
  "        'title' => 'Dudas',"

# 3 · sin rótulo, la sección deja de decir a qué eje contesta.
mutar "se pierde el rótulo" "$HB" \
  '                    <p class="sec-head__eyebrow">{{ __(' \
  '                    <p class="sec-head__NO">{{ __('

# 4 · sin entradilla, las preguntas dejan de tener procedencia.
mutar "se pierde la entradilla" "$HB" \
  '                    <p class="sec-head__lede">{{ __(' \
  '                    <p class="sec-head__NADA">{{ __('

echo
echo '── Todas cerradas ──'

# 5 · la conducta anterior: la primera abierta empuja las demás fuera del pulgar.
mutar "vuelve a abrirse la primera duda al cargar" "$JS" \
  '        faqOpen: -1,' \
  '        faqOpen: 0,'

echo
echo '── Cero salida ──'

# 6 · un enlace al final del desagüe repite el canal que el cierre ya ofrece.
mutar "entra un enlace en la sección" "$HB" \
  '            <x-site.faq-json-ld :faqs="$faqs" />' \
  '            <a href="/normas">Normas</a><x-site.faq-json-ld :faqs="$faqs" />'

# 7 · y su otra mitad: un pulsable que no pliega nada.
mutar "entra un botón que no es el de plegar" "$HB" \
  '            <x-site.faq-json-ld :faqs="$faqs" />' \
  '            <button type="button">Escríbenos</button><x-site.faq-json-ld :faqs="$faqs" />'

echo
echo '── La tarjeta y su filete ──'

# 8 · el defecto que el owner señaló en `#486`: dos líneas seguidas en la primera fila.
mutar "el filete sube a la primera fila" "$CSS" \
  '.faq__item + .faq__item { border-top: 1px solid var(--bg-soft); }' \
  '.faq__item { border-top: 1px solid var(--bg-soft); }'

# 9 · sin borde, el acordeón deja de ser una tarjeta y vuelve a ser filetes sueltos.
mutar "la tarjeta pierde su borde" "$CSS" \
  '  border: 1px solid var(--line);
  border-radius: var(--r-lg);
  /* Recorta las esquinas' \
  '  border: 0;
  border-radius: var(--r-lg);
  /* Recorta las esquinas'

# 10 · sin recorte, la primera y la última fila se salen del radio de la tarjeta.
mutar "la tarjeta deja de recortar sus esquinas" "$CSS" \
  '  border-radius: var(--r-lg);
  /* Recorta las esquinas de la primera y la última fila contra el radio de la tarjeta. */
  overflow: hidden;' \
  '  border-radius: var(--r-lg);'

# 11 · un canto escrito a mano se sale de la escala cerrada de cuatro.
mutar "el canto de la tarjeta sale de la escala" "$CSS" \
  '  border-radius: var(--r-lg);
  /* Recorta las esquinas' \
  '  border-radius: 12px;
  /* Recorta las esquinas'

echo
echo '── El signo ──'

# 12 · la conducta anterior: un «+» girado 45° es una «×», que significa cerrar y no plegar.
mutar "el signo vuelve a girar" "$CSS" \
  '.faq__item.open .faq__sign-i--menos { display: block; }' \
  '.faq__item.open .faq__sign-i--menos { display: block; transform: rotate(45deg); }'

# 13 · con un solo icono el estado abierto se queda sin signo propio.
mutar "se pierde el «–»" "$HB" \
  '                                    <x-icons.minus class="faq__sign-i faq__sign-i--menos" :width="16" :height="16" />' \
  ''

# 14 · la interacción vuelve a pintarse con la identidad de una zona (auditoría C2, `#436`): con el
#      cian de este cliente daba 2,45 sobre papel. Y de paso comprueba que la guarda re-apuntada
#      de `InteractionColourIsNotAZoneTest` sigue mordiendo con los dos estados en UNA regla.
mutar "la interacción vuelve a leer una zona" "$CSS" \
  '.faq__item.open .faq__q { color: var(--interactive); }' \
  '.faq__item.open .faq__q { color: var(--zone-1); }'

echo
echo '── El pulsable ──'

# 14 · el suelo del sistema son 48 «sin excepciones», y aquí el artboard pide 64.
mutar "el pulsable baja de 64" "$CSS" \
  '  width: 100%; min-height: 64px; text-align: left;' \
  '  width: 100%; min-height: 44px; text-align: left;'

# 15 · el pseudo de `#264` sobre un control que ya mide 64 solo añade capas que se solapan.
# ⚠️ Sin comillas invertidas en el rótulo: dentro de una cadena entre comillas dobles bash las
# ejecuta como comando —lo hizo, imprimió «data-tap: command not found» y dejó el nombre vacío—.
mutar "vuelve el data-tap a un control que ya mide 64" "$HB" \
  '                            <button type="button" class="faq__q"' \
  '                            <button type="button" class="faq__q" data-tap'

echo
echo '── La rejilla de escritorio ──'

# 16 · `1fr 2fr` da 362,67 y 725,33: se parece a 352/736 y no es su número.
mutar "la rejilla deja de tener doce pistas" "$CSS" \
  '    display: grid; grid-template-columns: repeat(12, 1fr); gap: 32px; align-items: start;' \
  '    display: grid; grid-template-columns: 1fr 2fr; gap: 32px; align-items: start;'

# 17 · el acordeón se estira a las doce y la respuesta da líneas de 140 caracteres.
mutar "el acordeón se estira a las doce columnas" "$CSS" \
  '  .faq-sec > .faq { grid-column: span 8; }' \
  '  .faq-sec > .faq { grid-column: span 12; }'

# 18 · el margen de la cabecera dentro de la rejilla separa de nada y desalinea la primera pregunta.
mutar "la cabecera conserva su margen dentro de la rejilla" "$CSS" \
  '  .faq-sec > .sec-head { grid-column: span 4; margin-bottom: 0; }' \
  '  .faq-sec > .sec-head { grid-column: span 4; }'

echo
echo "── ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ] || exit 1
