#!/usr/bin/env bash
# Arnés de mutación de LA ESCALERA QUE MARCA LO ELEGIDO, en sus pruebas UNITARIAS (T6c·6 de
# `specs/isla-y-landing-nueva.md` §4.19): `calculadora/escalera.test.js` (lo que se aplica sobre la página, con un DOM de
# mentira) y `calculadora/vista.test.js` (lo que la vista dice que se marque). En el navegador la guarda es
# `sonda-colegios.mjs`, con su arnés `mutar-sonda-colegios.sh`; éste es el de la red rápida, sin compilar ni navegador.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD (por RUTA, nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUN="docker compose exec -u sail -T laravel.test node --test resources/js/isla/calculadora/escalera.test.js resources/js/isla/calculadora/vista.test.js"

ESC=resources/js/isla/calculadora/escalera.js
VISTA=resources/js/isla/calculadora/vista.js
FICHEROS=("$ESC" "$VISTA")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

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
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

# ── Lo que se aplica sobre la página (`escalera.js`) ──────────────────────────────────────────────────────────────
mutar "la escalera de la otra duración no se oculta" "$ESC" \
  "escalera.hidden = ! suya(escalera);" \
  "escalera.hidden = false;"

mutar "con día, se marcan las dos tarifas del tramo" "$ESC" \
  "const marcada = activa && celda.dataset.jwTarifa === marca.tarifa;" \
  "const marcada = activa;"

mutar "la celda que deja de estar marcada conserva \`aria-current\`" "$ESC" \
  "else celda.removeAttribute('aria-current');" \
  ";"

mutar "una marca de una fila sin escalera oculta todas las escaleras" "$ESC" \
  "if (! marca || ! escaleras.some(suya)) return;" \
  "if (! marca) return;"

# ── Lo que la vista dice que se marque (`vista.escalera`) ─────────────────────────────────────────────────────────
mutar "la tarifa del día es siempre la normal" "$VISTA" \
  "tarifa: delDia ? (especial ? 'special' : 'normal') : null" \
  "tarifa: delDia ? 'normal' : null"

mutar "la escalera marca siempre el primer tramo" "$VISTA" \
  "tramo: tramoDe(fila, b.n)?.desde ?? null," \
  "tramo: fila.tramos[0]?.desde ?? null,"

mutar "unas entradas (sin tramos) también piden escalera" "$VISTA" \
  "escalera: esPack && fila.tramos?.length ?" \
  "escalera: true ?"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
