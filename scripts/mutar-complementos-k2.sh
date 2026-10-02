#!/usr/bin/env bash
# Arnés de mutación de K2 de LOS COMPLEMENTOS EN DOS (`[DECIDIDO owner]` `#807`; `specs/fiesta-sistema-nuevo.md` §4.17): la
# TARTA, VARIAS A LA VEZ —una tarjeta por tarta del panel, con su cantidad en `addons[]`— y «Sin tarta», una casilla con su 0
# oculto que solo decide con alguna tarta en plazo; sus raciones contra los niños (`racionesTarta()`), y el modelo del banco.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA.
#
#   bash scripts/mutar-complementos-k2.sh
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ART="docker compose exec -u sail -T laravel.test php artisan"
FILTRO='ExtrasDeLaFiestaListaTest|ExtrasDeLaFiestaDatoTest|FiestaModeloTest'
RUNJS="docker compose exec -u sail -T laravel.test node --test resources/js/fiesta/logica.test.js"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Http/Fiesta/ListaDeInvitados.php
    app/Http/Controllers/GuestFormController.php
    resources/views/fiesta/lista/zona-4.blade.php
    resources/js/fiesta/logica.js
    scripts/banco-fiesta/modelos.php
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; $ART view:clear >/dev/null 2>&1' EXIT
for f in "${FICHEROS[@]}"; do cp -p "$f" "$(copia "$f")"; done

verde() { $ART test --filter="$FILTRO" >/dev/null 2>&1; }
verdejs() { $RUNJS >/dev/null 2>&1; }

if ! verde || ! verdejs; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# mutar <nombre> <fichero> <buscar> <poner> [js]
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" juez="${5:-php}"
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
        echo "  ⚠ «$nombre» NO SE APLICÓ: el veredicto no vale"
        return
    fi
    touch "$fichero"
    local sigue=1
    if [[ "$juez" == js ]]; then verdejs || sigue=0; else verde || sigue=0; fi
    if [[ $sigue -eq 1 ]]; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

MODELO=app/Http/Fiesta/ListaDeInvitados.php
GFW=app/Http/Controllers/GuestFormController.php
Z4=resources/views/fiesta/lista/zona-4.blade.php
LJ=resources/js/fiesta/logica.js
BANCO=scripts/banco-fiesta/modelos.php

# ── La tarta en la página ──────────────────────────────────────────────────────────────────────
mutar "cerrada, salen también las tartas no pedidas" "$MODELO" \
  "\$visibles = \$abierta ? \$tartas : array_values(" "\$visibles = true ? \$tartas : array_values("
mutar "la tarjeta de la tarta no dice sus raciones" "$MODELO" \
  "\$a->serves === null ? '' : __('fiesta.lista.tarta.raciones'" "true ? '' : __('fiesta.lista.tarta.raciones'"
mutar "«Sin tarta» vuelve sin marcar" "$MODELO" \
  "                'declinada' => \$reservation->cakeDeclined(\$addons)," "                'declinada' => false,"
# Retiradas con su sujeto (§3.quater, `#912` P1·b): «el aviso sigue con una tarta pedida» y «…con «Sin tarta»» (el aviso de
# la tarta ya no existe). Y la del «plazo pasado» se re-apunta: desde P1 la tarta cerrada no dice «pasó».
mutar "sin el 0 oculto, desmarcar «Sin tarta» no se envía" "$Z4" \
  $'                <input type="hidden" name="cake_declined" value="0">\n' ""
mutar "«Sin tarta» sale con la tarta cerrada" "$Z4" \
  "            @if (\$ta['abierta'])" "            @if (true)"
mutar "cerrada y con una pedida, sale también «Sin tarta»" "$Z4" \
  "@if (\$ta['declinada'] || \$ta['tarjetas'] === [])<p class=\"pli-tarta-fija\">" "@if (true)<p class=\"pli-tarta-fija\">"

# ── «Sin tarta» en el controlador ──────────────────────────────────────────────────────────────
mutar "«Sin tarta» se decide con la tarta cerrada" "$GFW" \
  "        return \$abierta ? (string) \$answer === '1' : null;" "        return (string) \$answer === '1';"
mutar "la web no guarda «Sin tarta»" "$GFW" \
  "        return \$abierta ? (string) \$answer === '1' : null;" "        return null;"
mutar "un valor que no es de la casilla decide" "$GFW" \
  "if (! is_scalar(\$answer) || ! in_array((string) \$answer, ['0', '1'], true)) {" "if (! is_scalar(\$answer)) {"
mutar "la casilla no llega al dominio" "$GFW" \
  "->settleCakeAnswer(\$cakeDeclined);" "->settleCakeAnswer(null);"

# ── Las raciones contra los niños (`racionesTarta()`) ──────────────────────────────────────────
mutar "las raciones justas no llegan" "$LJ" \
  "return { cubre: raciones >= ninos, uds, raciones };" "return { cubre: raciones > ninos, uds, raciones };" js
mutar "una pedida sin raciones no calla la cuenta" "$LJ" \
  " || pedidas.some((t) => !(t.serves > 0))) return null;" ") return null;" js
mutar "una sin raciones y sin pedir calla la cuenta" "$LJ" \
  "const pedidas = (tartas ?? []).filter((t) => t.uds > 0);" "const pedidas = (tartas ?? []);" js

# ── El banco de píxeles habla de lo mismo que el controlador ───────────────────────────────────
mutar "el banco pinta la tarta con otra forma" "$BANCO" \
  "                'declinada' => false," "                'decidida' => false,"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
