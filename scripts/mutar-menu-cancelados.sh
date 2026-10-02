#!/usr/bin/env bash
# Arnés de mutación de «LO QUITADO EN LA LISTA NO SALE EN LA INVITACIÓN» (`specs/fiesta-sistema-nuevo.md` §4.17; el owner,
# 02-10: «si he seleccionado Sándwich, ¿por qué me sale pizza en la invitación?»).
#
# Quitar un complemento en la lista no borra su línea: la CANCELA (`cancelled_at`, con su historia), y `PartyInvitations::
# menuFor()` las leía todas. La guarda es una condición (`! $line->isCancelled()`) y su prueba, `InvitationPageTest::
# test_a_dish_taken_off_the_list_leaves_the_invitation` (la Pizza quitada no sale; de CONTROL, el Sándwich pedido sí).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y
# que su ancla es ÚNICA · un CONTROL que no debe morder · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"
FILTER='InvitationPageTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"
TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Services/PartyInvitations.php
)
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
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

PI=app/Domain/Booking/Services/PartyInvitations.php

mutar "la invitación vuelve a enseñar lo quitado en la lista" "$PI" \
  "! \$line->isCancelled() && in_array(" \
  "in_array("
mutar "la invitación enseña SOLO lo quitado (y nada de lo pedido)" "$PI" \
  "! \$line->isCancelled() && in_array(" \
  "\$line->isCancelled() && in_array("

# ── El CONTROL: tocar un comentario no puede poner nada en rojo ─────────────────────────────────
control "un comentario de menuFor()" "$PI" \
  "¿por qué me sale pizza?»;" \
  "¿por qué me sale la pizza?»;"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
