#!/usr/bin/env bash
# Arnés de mutación de LA RAZÓN DE CADA PIEZA (Z6b·1 de `specs/isla-y-landing-nueva.md` §4.27): la guarda es
# `scripts/sonda-razon.mjs` —la isla de las páginas recorrida en un navegador contra lo que cada página le declara—, y cada
# mutación rompe UN mecanismo: la razón que no sale, las dos voces a la vez, la razón al llegar, la razón de otra pieza y el
# icono sin dibujar.
#
# ⚠️ Las mutaciones de la isla (JS) se CONSTRUYEN (`npm run build`) tras mutar y tras restaurar: la página sirve el paquete
# construido. La del servidor (`RazonesDeIsla.php`) no se construye: se ESPERA 3 s tras mutar y tras restaurar, que opcache
# revalida los ficheros cada 2 s (`mutar-sonda-visitanos.sh`, 28-09).
# Para ir más rápido, la sonda recorre solo Kids y Cumpleaños, sin las de apoyo (`SONDA_PAGINAS`, `SONDA_APOYO`).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y
# que su ancla está UNA vez · restaurar por COPIA DE SEGURIDAD (por RUTA, nunca por `basename`) y `touch`, nunca con
# `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ANCHO="${1:-390}"
DC="docker compose exec -u sail -T"
RUN="$DC -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers -e SONDA_PAGINAS=/kids,/cumpleanos -e SONDA_APOYO=0 laravel.test node scripts/sonda-razon.mjs ${ANCHO}"
CONSTRUIR="$DC laravel.test npm run build"

SIT=resources/js/isla/situacion.js
SAT=resources/js/isla/useSinSaturar.js
PAG=resources/js/isla/pagina/pagina.js
DIB=app/Http/Instancia/RazonesDeIsla.php
FICHEROS=("$SIT" "$SAT" "$PAG" "$DIB")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; $CONSTRUIR >/dev/null 2>&1; sleep 3; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

# La salida de cada corrida, para decir QUÉ comprobación cae: que muerda por la razón que la mutación rompe.
SALIDA="$TMP/salida.txt"
verde() { $RUN >"$SALIDA" 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    cat "$SALIDA" >&2
    exit 1
fi
echo "✓ base verde (sonda-razon a ${ANCHO}, Kids y Cumpleaños)"

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
    if [[ "$fichero" == *.js ]]; then $CONSTRUIR >/dev/null 2>&1; else sleep 3; fi
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre — $(grep -m1 '^✗' "$SALIDA" | cut -c1-150)"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
    if [[ "$fichero" == *.js ]]; then $CONSTRUIR >/dev/null 2>&1; else sleep 3; fi
}

mutar "la razón no sale nunca (la acción, en secundaria como en la Z6a)" "$SIT" \
  "    if (s.calm && p.reason && ! s.locked && ! s.bn) s.bn = { type: 'razon', ...p.reason };" \
  "    if (false) s.bn = { type: 'razon', ...p.reason };"
mutar "dos voces: la frase sigue con el banner" "$SIT" \
  "    const hasLine = Boolean(s.line) && !inCheckout && !menuOpen && !(s.bn && !isOpen);" \
  "    const hasLine = Boolean(s.line) && !inCheckout && !menuOpen;"
mutar "la razón sale AL LLEGAR" "$SAT" \
  "    const razonClave = computed(() => (movido.value ? textoDe(reason()) : null));" \
  "    const razonClave = computed(() => textoDe(reason()));"
mutar "la razón de la llegada en cualquier pieza" "$PAG" \
  "    return razones[zona] ?? razones.llegada ?? null;" \
  "    return razones.llegada ?? null;"
mutar "el icono de la razón NO se dibuja" "$DIB" \
  "                \$isla['razones'][\$zona]['svg'] = \$svg;" \
  "                \$isla['razones'][\$zona]['svg'] = null;"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
