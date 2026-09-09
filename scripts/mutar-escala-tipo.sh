#!/usr/bin/env bash
# Arnés de mutación de la ESCALA TIPOGRÁFICA del sistema (`specs/rediseno-desde-canvas.md` §5.3,
# `DECISIONES #474`) — la T1g del carril de diseño.
#
# La propiedad que protege es una sola: **cada uno de los diez niveles vale exactamente la talla que
# el canvas declara, en las dos superficies y en el tramo de en medio**. Es la única afirmación que
# se puede sostener sin navegador, y por eso tiene que estar realmente vigilada.
#
# ❗❗ **Este arnés encontró DOS huecos en la guarda que lo acompaña, y son simétricos.** Un `clamp`
# tiene tres partes y **cada una manda en un tramo distinto de ancho**, así que medir en un solo
# sitio deja las otras dos sin vigilar:
#   · **el TRAMO interpolado manda entre 390 y 1024** — torcer sus constantes salía VERDE cuando
#     solo se medía en los extremos, porque ahí el `clamp` capa. De ahí
#     `test_every_level_interpolates_linearly_in_between` (mutaciones 3 y 4).
#   · **los EXTREMOS mandan FUERA de esa recta** — cambiar el mínimo de 42 a 40 o el máximo de 52 a
#     56 no mueve un píxel entre 390 y 1024, pero sí lo que ve un teléfono de 320 y una pantalla de
#     1920. De ahí `test_every_level_is_capped_outside_the_line` (mutaciones 1 y 2).
# ▶ La lección, que vale para cualquier valor fluido: *una medida en un punto no vigila una función;
# hay que medir en cada tramo donde manda una parte distinta de la expresión.*
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por CÓDIGO DE SALIDA (nunca `grep
# passed`) · comprobar que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con
# `git checkout` (`#181`) · copia en RUTA FIJA que se repara al arrancar (`#448`: un `trap … EXIT`
# no corre con SIGKILL y deja el árbol mutado) · `mutar()` ABORTA si el fichero no tiene copia
# (`#449`: sin copia las mutaciones se ACUMULAN y el veredicto deja de valer).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='TypeScaleTest|SidebarTokenBudgetTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="storage/app/mutaciones/escala-tipo"
FICHEROS=(
    public/css/landing.css
    tests/Feature/Architecture/SidebarTokenBudgetTest.php
)

restaurar() {
    for f in "${FICHEROS[@]}"; do
        [ -f "$TMP/$(basename "$f")" ] || continue
        cp "$TMP/$(basename "$f")" "$f"; touch "$f"
    done
}

# ── Reparación de un corte ANTERIOR, antes de tocar nada ────────────────────────────────────
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

CSS=public/css/landing.css

echo '── Las TALLAS de los extremos ──'

# 1 · la talla MÓVIL de un nivel deja de ser la del canvas.
mutar "Display XL a 40 en móvil (el canvas dice 42)" "$CSS" \
  "--fs-display-xl: clamp(calc(var(--fs-unit) * 42)" \
  "--fs-display-xl: clamp(calc(var(--fs-unit) * 40)"

# 2 · la talla de ESCRITORIO de un nivel deja de ser la del canvas.
mutar "Display L a 56 en escritorio (el canvas dice 52)" "$CSS" \
  "+ 2.8391vw), calc(var(--fs-unit) * 52))" \
  "+ 2.8391vw), calc(var(--fs-unit) * 56))"

echo
echo '── El TRAMO FLUIDO · lo que los extremos NO ven ──'
# ❗ Las dos de abajo salían VERDES antes de escribir la comprobación del punto medio: el `clamp`
# capa en 390 y en 1024, así que el error solo se ve en los anchos de en medio.

# 3 · la pendiente (`B`, en vw) del tramo.
mutar "la PENDIENTE de Cuerpo, torcida (0.1577 → 0.3)" "$CSS" \
  "* 15.3849 + 0.1577vw)" \
  "* 15.3849 + 0.3vw)"

# 4 · la ordenada (`A`, en px) del tramo.
mutar "la ORDENADA de Entradilla, torcida (16.1546 → 14)" "$CSS" \
  "* 16.1546 + 0.4732vw)" \
  "* 14 + 0.4732vw)"

echo
echo '── La FORMA de un nivel ──'

# 5 · un nivel de talla única escrito como clamp: da el número correcto y miente sobre el mecanismo.
mutar "Etiqueta escrita como clamp de extremos iguales" "$CSS" \
  "--fs-label:      calc(var(--fs-unit) * 12);" \
  "--fs-label:      clamp(calc(var(--fs-unit) * 12), calc(var(--fs-unit) * 12 + 0vw), calc(var(--fs-unit) * 12));"

# 6 · un literal en vez del mando de la instalación: MISMO píxel, así que ninguna talla lo delata.
mutar "Cuerpo S con literal, fuera del mando --fs-unit" "$CSS" \
  "--fs-body-s:     calc(var(--fs-unit) * 15);" \
  "--fs-body-s:     15px;"

echo
echo '── La CONVIVENCIA con la escala por píxel y el CONTROL del escáner ──'

# 7 · retirar un escalón de la escala vieja antes de tiempo deja sus reglas con un calc() sin var.
mutar "se retira --fs-13 antes de que su superficie se vista" "$CSS" \
  "  --fs-13: calc(var(--fs-unit) * 13);" \
  "  /* --fs-13 retirado */"

# 8 · CONTROL del instrumento: si el escáner no encuentra un nivel, tiene que decirlo y no pasar.
mutar "un nivel cambia de nombre (control del escáner)" "$CSS" \
  "--fs-title:      clamp" \
  "--fs-titulo:     clamp"

echo
echo '── La EXCEPCIÓN declarada · que no se convierta en un agujero ──'
# Los diez niveles nacen sin consumidor a propósito, y por eso están nominados en
# `SidebarTokenBudgetTest::SIN_ESTRENAR`. Una lista de excepciones que nadie limpia deja de ser
# deuda y pasa a ser un hueco permanente: estas dos comprueban que la lista sigue viva por los dos
# lados —que sin ella la guarda de escalones muertos muerde, y que estrenar un nivel sin sacarlo de
# la lista pone rojo el trinquete—.
TST=tests/Feature/Architecture/SidebarTokenBudgetTest.php

# 9 · sin la excepción, la guarda original vuelve a morder (o sea: la lista NO está de más).
mutar "se retira un nivel de SIN_ESTRENAR (la guarda original debe morder)" "$TST" \
  "'--fs-body', '--fs-body-s', '--fs-button', '--fs-label', '--fs-slogan'," \
  "'--fs-body', '--fs-body-s', '--fs-button', '--fs-label',"

# 10 · el caso REAL de la Fase 2: se estrena un nivel y nadie limpia la lista.
mutar "se ESTRENA --fs-button sin sacarlo de la lista (el trinquete debe morder)" "$CSS" \
  "  font-weight: 600; font-size: var(--fs-14);" \
  "  font-weight: 600; font-size: var(--fs-button);"

echo
echo "── ${muerden}/${total} muerden ──"
[ "$muerden" -eq "$total" ] || exit 1

# ── Integridad: el árbol tiene que quedar como estaba ───────────────────────────────────────
restaurar
for f in "${FICHEROS[@]}"; do
    cmp -s "$f" "$TMP/$(basename "$f")" || { echo "✗ «$f» NO quedó como estaba" >&2; exit 1; }
done
echo '✓ árbol idéntico al de partida'
