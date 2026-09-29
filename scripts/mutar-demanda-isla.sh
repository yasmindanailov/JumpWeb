#!/usr/bin/env bash
# Arnés de mutación de LA DEMANDA SIN HUECO EN LA ISLA (`DECISIONES #758`; `specs/isla-y-landing-nueva.md` §4.26):
# `isla/compra/demanda.test.js` y `oferta.test.js` contra la regla (`demanda.js`), lo que llegó (`oferta.js`), la
# pantalla 0 de la compra y las dos calculadoras de la página. Cada mutante rompe UNA decisión del diseño: solo si la
# oferta llegó, un reportero por página, la fila que se MIRA (no las demás, no al arrancar sola, la de después del
# cambio) y la oferta que viaja de la fiesta que no lo era.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y
# que su ancla está UNA vez (se cuenta en Python: hay anclas de dos líneas) · restaurar por COPIA DE SEGURIDAD (por RUTA,
# nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUN="docker compose exec -u sail -T laravel.test node --test resources/js/isla/compra/demanda.test.js resources/js/isla/compra/oferta.test.js"

DEM=resources/js/isla/compra/demanda.js
OF=resources/js/isla/compra/oferta.js
PC=resources/js/isla/compra/usePantallaCero.js
CAL=resources/js/isla/calculadora/useCalculadora.js
FIE=resources/js/isla/calculadora/useCalculadoraFiesta.js
FICHEROS=("$DEM" "$OF" "$PC" "$CAL" "$FIE")

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
    if [[ "$(python3 -c 'import sys; print(open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$fichero" "$buscar")" != 1 ]]; then
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

# ── La regla (`demanda.js`) ───────────────────────────────────────────────────────────────────────────────────────────
mutar "una oferta que NO llegó también informa (una red caída contaría como mes lleno)" "$DEM" \
  "if (id == null || ! (llegaron ?? []).some((x) => String(x) === String(id))) return [];" \
  "if (id == null) return [];"

mutar "el id como texto no casa con el que llegó como número" "$DEM" \
  "String(x) === String(id)" \
  "x === id"

mutar "un reportero por LLAMADA y no por página (calculadora + compra contarían dos veces)" "$DEM" \
  "reportar ?? (dePagina ??= createMissingReporter(" \
  "reportar ?? (dePagina = createMissingReporter("

mutar "sin tracker en la página, revienta" "$DEM" \
  "globalThis.window?.JumpWeb?.track?.(name, props)" \
  "window.JumpWeb.track(name, props)"

# ── Lo que llegó (`oferta.js`) ────────────────────────────────────────────────────────────────────────────────────────
mutar "una fila que falla cuenta como llegada" "$OF" \
  "llegaron: unicos.filter((id, i) => respuestas[i].ok)," \
  "llegaron: unicos,"

# ── La pantalla 0 de la compra (`usePantallaCero.js`) ──────────────────────────────────────────────────────────────────
mutar "la fila situada o cambiada no informa" "$PC" \
  $'    async function cargarFila() {\n        mirada();' \
  "    async function cargarFila() {"

mutar "el pack de una fiesta no informa" "$PC" \
  $'        mirada();\n        if (! ficha)' \
  "        if (! ficha)"

mutar "informa TODAS las filas de la zona, no la que se mira" "$PC" \
  "const mirada = () => informarDemanda({ id: compra.borrador.fila, dias: compra.precios[compra.borrador.fila], llegaron: compra.llegaron });" \
  "const mirada = () => Object.keys(compra.precios).forEach((id) => informarDemanda({ id, dias: compra.precios[id], llegaron: compra.llegaron }));"

mutar "situar pierde lo que llegó" "$PC" \
  "Object.assign(compra, { precios: oferta.dias, llegaron: oferta.llegaron });" \
  "Object.assign(compra, { precios: oferta.dias });"

mutar "la fiesta pierde lo que llegó" "$PC" \
  "Object.assign(compra, { fichas, precios: oferta.dias, llegaron: oferta.llegaron });" \
  "Object.assign(compra, { fichas, precios: oferta.dias });"

mutar "la oferta de la fiesta que no lo era (una excursión) se pierde por el camino" "$PC" \
  "}, oferta);" \
  "}, { dias: oferta.dias, llegaron: [] });"

# ── La calculadora de la página (`useCalculadora.js`) ───────────────────────────────────────────────────────────────────
mutar "informa al ARRANCAR sola, al acercarse la pieza (contaría a quien pasa)" "$CAL" \
  "Object.assign(e, { precios: oferta.dias, llegaron: oferta.llegaron });" \
  "Object.assign(e, { precios: oferta.dias, llegaron: oferta.llegaron }); mirada();"

mutar "tocarla no informa" "$CAL" \
  "arrancar().then(mirada);" \
  "arrancar();"

mutar "informa ANTES de que lleguen los días y del cambio (sin \`then\`)" "$CAL" \
  "arrancar().then(mirada);" \
  "arrancar(); mirada();"

# ── La calculadora de la fiesta (`useCalculadoraFiesta.js`) ────────────────────────────────────────────────────────────
mutar "la de la fiesta informa al ARRANCAR sola" "$FIE" \
  "Object.assign(e, { precios: oferta.dias, llegaron: oferta.llegaron });" \
  "Object.assign(e, { precios: oferta.dias, llegaron: oferta.llegaron }); mirada();"

mutar "la de la fiesta informa siempre el primer pack, no el de la edad" "$FIE" \
  $'const mirada = () => {\n        const p = pack() ?? packs[0];' \
  $'const mirada = () => {\n        const p = packs[0];'

mutar "tocar la de la fiesta no informa" "$FIE" \
  "arrancar().then(mirada);" \
  "arrancar();"

mutar "la de la fiesta informa el pack de ANTES de cambiar la edad (sin \`then\`)" "$FIE" \
  "arrancar().then(mirada);" \
  "arrancar(); mirada();"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
