#!/usr/bin/env bash
# Arnés de mutación de K1 de LOS COMPLEMENTOS EN DOS (`[DECIDIDO owner]` `#806`/`#807`; `specs/fiesta-sistema-nuevo.md` §4.17):
# «Para los niños» (la tarta y los sueltos, en grupos contados en niños, con «Uno para cada niño») y «Para los adultos».
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA.
#
#   bash scripts/mutar-complementos-k1.sh
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ART="docker compose exec -u sail -T laravel.test php artisan"
FILTRO='ExtrasDeLaFiestaListaTest|FiestaModeloTest'

TMP="$(mktemp -d)"
FICHEROS=(
    app/Http/Fiesta/ListaDeInvitados.php
    resources/views/fiesta/lista/zona-4.blade.php
    resources/views/fiesta/lista/grupo-extras.blade.php
    scripts/banco-fiesta/modelos.php
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

MODELO=app/Http/Fiesta/ListaDeInvitados.php
Z4=resources/views/fiesta/lista/zona-4.blade.php
GRUPO=resources/views/fiesta/lista/grupo-extras.blade.php
BANCO=scripts/banco-fiesta/modelos.php

mutar "el bloque de los niños no se pinta" "$Z4" \
  "@if (\$ni !== null)" "@if (false)"
mutar "los niños se titulan como los adultos" "$Z4" \
  "{{ __('fiesta.lista.ninos.titulo') }}" "{{ __('fiesta.lista.padres.titulo') }}"
mutar "el bloque de los niños sale aunque no haya nada" "$MODELO" \
  "'ninos' => \$tarta !== null || \$grupos !== [] ?" "'ninos' => true ?"
mutar "los sueltos vuelven a un solo grupo" "$MODELO" \
  "if (\$a->family === '') {" "if (false) {"
mutar "la chapa de los niños vuelve a «personas»" "$MODELO" \
  "trans_choice('fiesta.lista.ninos.para'," "trans_choice('fiesta.lista.padres.para',"
mutar "«Uno para cada niño» también cerrado" "$MODELO" \
  "'uno' => \$a->serves === 1 && ! \$carta['cerrado']" "'uno' => \$a->serves === 1"
mutar "«Uno para cada niño» para cualquier «para N»" "$MODELO" \
  "'uno' => \$a->serves === 1 &&" "'uno' => \$a->serves !== null &&"
mutar "la cuenta de los niños no llega a la página" "$MODELO" \
  "['grupos' => \$grupos, 'sois' => \$sois]" "['grupos' => \$grupos, 'sois' => 0]"
mutar "«Uno para cada niño» sin su cifra" "$GRUPO" \
  ":count=\"\$sois ?? 0\"" ":count=\"null\""
mutar "el banco pinta otra forma que el controlador" "$BANCO" \
  "'ninos' => ['grupos' => [], 'sois' => \$RESERVA['reservados']]," "'lista' => [],"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
