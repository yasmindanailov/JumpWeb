#!/usr/bin/env bash
# Arnés de mutación de COLEGIOS (T6c de `specs/isla-y-landing-nueva.md` §4.19, `#837`→`#841`): la guarda es
# `scripts/sonda-colegios.mjs` —la página, su calculadora, la compra de una excursión, el cálculo que se retoma y la hoja
# para dirección, en un navegador—, y cada mutación rompe UN mecanismo de los que la T6c añadió: el precio del tramo de
# esa gente, el aviso del tramo cercano, el cálculo que viaja en el enlace, la hora retomada que ya no está libre, la
# capa que enfoca lo que falta, los datos del centro en la línea y las cifras del grupo en la hoja.
#
# ⚠️ Todas son de la isla (`resources/js/isla/**`): obligan a recompilar (`npm run build`) al mutar y al restaurar.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD (por RUTA, nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ANCHO="${1:-390}"
RUN="docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test node scripts/sonda-colegios.mjs ${ANCHO}"
BUILD="docker compose exec -u sail -T laravel.test npm run build"

VISTA=resources/js/isla/calculadora/vista.js
CALC=resources/js/isla/calculadora/useCalculadora.js
IR_A=resources/js/isla/compra/ir-a.js
LINEA=resources/js/isla/compra/linea.js
HOJA=resources/js/isla/hoja/montar.js
FICHEROS=("$VISTA" "$CALC" "$IR_A" "$LINEA" "$HOJA")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
compilar() { $BUILD >/dev/null 2>&1; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; compilar; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

# La salida de cada corrida, para decir QUÉ comprobación cae: que muerda por la razón que la mutación rompe.
SALIDA="$TMP/salida.txt"
verde() { $RUN >"$SALIDA" 2>&1; }

compilar
if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo "✓ base verde (sonda-colegios a ${ANCHO})"

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    if [[ "$(grep -cF -- "$buscar" "$fichero")" != 1 ]]; then
        echo "  ⚠ «$nombre» NO APLICA: el ancla no está UNA vez en $fichero"
        return
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$fichero"
    compilar
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        grep '^✗' "$SALIDA" | head -3 | sed 's/^/                 /'
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
    compilar
}

# ── La calculadora ────────────────────────────────────────────────────────────────────────────────────────────────
mutar "el precio por persona es siempre el del primer tramo (75 alumnos a 15 € y no a 13 €)" "$VISTA" \
  "b.desde - a.desde).find((t) => n >= t.desde)" \
  "a.desde - b.desde).find((t) => n >= t.desde)"

mutar "el tramo siguiente no se avisa nunca (con 60, sin «Desde 70…»)" "$VISTA" \
  "export const CERCA_DEL_TRAMO = 10;" \
  "export const CERCA_DEL_TRAMO = 0;"

# ── El cálculo que se retoma (`#837`) ─────────────────────────────────────────────────────────────────────────────
mutar "el cálculo del enlace pierde la hora" "$VISTA" \
  "horaCorta(b.hora) ?? ''].join('_')" \
  "''].join('_')"

mutar "la hora retomada que ya no está libre se suelta sin decirlo" "$CALC" \
  "if (horaRetomada && b.dia && ! b.hora) e.perdida = true;" \
  "if (false) e.perdida = true;"

# ── La compra de una excursión en la isla (`#839`, `#840`) ────────────────────────────────────────────────────────
mutar "la capa baja a lo que falta pero no lo enfoca" "$IR_A" \
  "if (el.matches('input, textarea, select')) el.focus({ preventScroll: true });" \
  ";"

mutar "los datos del centro no viajan en la línea" "$LINEA" \
  "event_data: { ...(p.evento ?? {}) }," \
  "event_data: {},"

# ── La hoja para dirección (`#841`) ───────────────────────────────────────────────────────────────────────────────
mutar "la hoja escribe como total la señal" "$HOJA" \
  "poner('[data-jw-hoja-cifra=\"total\"]', euros(linea.total_cents, locale));" \
  "poner('[data-jw-hoja-cifra=\"total\"]', euros(linea.deposit_cents, locale));"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
