#!/usr/bin/env bash
# Arnés de mutación de la T5f de Mi cuenta en la isla (`#824`, `specs/isla-y-landing-nueva.md` §4.13): «Reservar otra vez»
# (`otraVezDe`), la bienvenida (`esCuentaNueva`), sin conexión (`sinRed`, `intentar`) y cada bloque protegido (`protegido`).
# Lo que se ve en vivo lo juzga `scripts/sonda-cuenta.mjs` (su parte 10c); aquí, que las pruebas de la lógica MUERDEN.
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-t5f.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

JS="docker compose exec -u sail -T laravel.test node --test resources/js/isla/cuenta/reservas.test.js resources/js/isla/cuenta/conexion.test.js resources/js/isla/cuenta/seguro.test.js"

TMP="$(mktemp -d)"
FICHEROS=(
    resources/js/isla/cuenta/reservas.js
    resources/js/isla/cuenta/conexion.js
    resources/js/isla/cuenta/seguro.js
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

verde() { $JS >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    local veces
    veces=$(python3 -c 'import sys; print(open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$fichero" "$buscar")
    if [[ "$veces" != "1" ]]; then
        echo "  ⚠ «$nombre»: el ancla aparece $veces veces (tiene que ser UNA): el veredicto no vale"
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

RE=resources/js/isla/cuenta/reservas.js
CO=resources/js/isla/cuenta/conexion.js
SE=resources/js/isla/cuenta/seguro.js

# ── 1 · Reservar otra vez ────────────────────────────────────────────────────────────────────────
mutar "se ofrece una cancelada, devuelta o sin pagar" "$RE" "estadoDe(c) === 'pasada' && vendidas" "vendidas"
mutar "se ofrece lo que ya no se vende" "$RE" "vendidas.has(c.reservation?.product_id));" "true);"
# La fiesta la deja fuera el catálogo (solo entradas): sin su filtro, un pack del historial se ofrecería.
mutar "se ofrece una fiesta (un pack cuenta como entrada)" "$RE" ".filter((p) => p?.type === 'entry').map((p) => p.id)" ".map((p) => p?.id)"
mutar "la PRIMERA visita y no la última" "$RE" "const card = (pasadas ?? []).find(" "const card = (pasadas ?? []).findLast("
mutar "una cantidad rara sale como cero" "$RE" "n: Math.max(1, Number(card.reservation.quantity) || 1)" "n: Number(card.reservation.quantity)"

# ── 2 · La bienvenida ────────────────────────────────────────────────────────────────────────────
mutar "bienvenida con el historial sin leer" "$RE" "historialLeido === true && pasadas" "pasadas"
mutar "bienvenida mientras llegan las próximas" "$RE" "Array.isArray(proximas) && proximas.length === 0" "(proximas ?? []).length === 0"

# ── 3 · Sin conexión ─────────────────────────────────────────────────────────────────────────────
mutar "sin el dato de la red, se da por caída" "$CO" "nav?.onLine === false" "! nav?.onLine"
mutar "sin red, se intenta igual" "$CO" "if (sinRed(nav ?? globalThis.navigator)) {" "if (false) {"
mutar "el reintento no vuelve a mirar la red" "$CO" "fallar(() => vez(...args));" "fallar(() => hace(...args));"
mutar "con red, el fallo de antes se queda" "$CO" "        limpiar();
" ""

# ── 4 · Cada bloque, protegido ───────────────────────────────────────────────────────────────────
mutar "un bloque que revienta se lleva Mi cuenta" "$SE" "        avisar(nombre, error);

        return roto;" "        throw error;"
mutar "se ignora el valor de roto de quien llama" "$SE" "        return roto;" "        return ROTO;"
mutar "no se apunta en la consola" "$SE" "        avisar(nombre, error);
" ""

echo
echo "$muerden de $total mutantes muertos"
[[ "$muerden" == "$total" ]]
