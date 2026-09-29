#!/usr/bin/env bash
# Arnés de mutación de LA LISTA DEL OWNER (`[DECIDIDO owner]` `#805`; `specs/fiesta-sistema-nuevo.md` §4.16): todo el de la
# lista confirmado, sin la firma de los invitados ni cifras, y el «No podemos» aparte y en tono suave.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA.
#
#   bash scripts/mutar-lista-805.sh
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ART="docker compose exec -u sail -T laravel.test php artisan"
FILTRO='ListaDeInvitadosTest|InvitationHostBlockTest'

TMP="$(mktemp -d)"
FICHEROS=(
    resources/views/fiesta/lista/fila.blade.php
    resources/views/fiesta/lista/zona-2.blade.php
    app/Http/Fiesta/ListaDeInvitados.php
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; $ART view:clear >/dev/null 2>&1' EXIT
for f in "${FICHEROS[@]}"; do cp -p "$f" "$(copia "$f")"; done

verde() { $ART test --filter="$FILTRO" >/dev/null 2>&1; }

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
        echo "  ⚠ «$nombre» NO SE APLICÓ: el veredicto no vale"
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

FILA=resources/views/fiesta/lista/fila.blade.php
Z2=resources/views/fiesta/lista/zona-2.blade.php
MODELO=app/Http/Fiesta/ListaDeInvitados.php

mutar "vuelve «Sin contestar» para el añadido a mano" "$FILA" \
  "\$estado = \$n['respuesta'] === 'no' ? 'no' : 'confirmado';" \
  "\$estado = \$n['respuesta'] === 'si' ? 'confirmado' : (\$n['respuesta'] === 'no' ? 'no' : 'sin-contestar');"
mutar "vuelve la firma de los invitados a sus filas" "$FILA" \
  ":signed=\"\$n['firmada']\" :firma=\"false\"" ":signed=\"\$n['firmada']\" :firma=\"true\""
mutar "el «no» emparejado vuelve a la lista" "$Z2" \
  "! \$n['pendiente'] && ! \$n['vacia'] && \$n['respuesta'] !== 'no'));" "! \$n['pendiente'] && ! \$n['vacia']));"
mutar "el bloque de los que no pueden venir desaparece" "$Z2" \
  "@if (\$noVienen > 0)" "@if (false)"
mutar "la fecha del plazo lleva doble punto" "$Z2" \
  "['plazo' => rtrim(\$inv['plazo'], '.')]" "['plazo' => \$inv['plazo']]"
mutar "los añadidos a mano dejan de contar como confirmados en el número" "$MODELO" \
  "if (\$n['origen'] !== 'cumple' && \$n['respuesta'] !== 'no') {" "if (\$n['origen'] !== 'cumple' && \$n['respuesta'] === 'si') {"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
