#!/usr/bin/env bash
# Arnés de mutación de «LA IMPAR A LO ANCHO» en la lista de invitados (P2 de `specs/fiesta-sistema-nuevo.md` §4.20,
# `[DECIDIDO owner]` `#913`). Lo que se prueba aquí es la parte del SERVIDOR: el suelto que se queda solo en su fila
# (`ListaDeInvitados::extras()`, la marca `ancha`) y su clase en la vista. La parte de la HOJA (las tarjetas dentro de un grupo)
# la mide `scripts/sonda-lista-huecos.mjs`, con su `--control`: la suite no ve geometría.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que su
# ancla es ÚNICA · un CONTROL que no debe morder · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
FILTER='ExtrasDeLaFiestaListaTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"
TMP="$(mktemp -d)"
FICHEROS=(
    app/Http/Fiesta/ListaDeInvitados.php
    resources/views/fiesta/lista/grupo-extras.blade.php
)
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"; docker compose exec -u sail -T laravel.test php artisan view:clear >/dev/null 2>&1' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
verde() { $RUN >/dev/null 2>&1; }
if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0; control_ok=1

aplicar() {
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); n=s.count(sys.argv[2]); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1)); sys.exit(0 if n == 1 else 3)' \
        "$1" "$2" "$3"
}

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    aplicar "$fichero" "$buscar" "$poner"; local unico=$?
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    if [[ $unico -ne 0 ]]; then
        echo "  ⚠ «$nombre»: el ancla NO es única; se mutó la primera y el veredicto es dudoso"
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

control() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    aplicar "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ CONTROL «$nombre» NO SE APLICÓ"; control_ok=0; return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✓ control:   $nombre (sigue verde, como debe)"
    else
        echo "  ✗ CONTROL ROJO: $nombre — se pone rojo por algo que no es la regla"; control_ok=0
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

LI=app/Http/Fiesta/ListaDeInvitados.php
GE=resources/views/fiesta/lista/grupo-extras.blade.php

mutar "la racha impar ya no se marca" "$LI" \
  "if (count(\$racha) % 2 === 1) {" \
  "if (false) {"
mutar "se marca la racha PAR (la pareja)" "$LI" \
  "if (count(\$racha) % 2 === 1) {" \
  "if (count(\$racha) % 2 === 0) {"
mutar "se marca el PRIMERO de la racha y no el último" "$LI" \
  "\$grupos[(int) end(\$racha)]['ancha'] = true;" \
  "\$grupos[(int) reset(\$racha)]['ancha'] = true;"
mutar "un grupo con título corta la racha y deja de cortarla" "$LI" \
  "if (\$i !== null && \$grupos[\$i]['titulo'] === '') {" \
  "if (\$i !== null) {"
mutar "la vista ya no pone la clase" "$GE" \
  ", 'pli-fam--ancha' => \$g['ancha'] ?? false])" \
  "])"

# ── El CONTROL: tocar un comentario no puede poner nada en rojo ─────────────────────────────────
control "un comentario de extras()" "$LI" \
  "así que una RACHA impar deja el último solo" \
  "así que una racha impar deja el último solo"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
