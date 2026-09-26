#!/usr/bin/env bash
# Arnés de mutación de F7, LA EXENCIÓN DE QUIEN CUMPLE (`specs/fiesta-sistema-nuevo.md` §4.13, `[DECIDIDO owner]` `#752`).
#
# F7a (el dominio): que su plaza cuente UNA vez la cubra quien la cubra, que la cobertura lea la ATADURA (el justificante
# con `honoree` o su menor a cargo asignado) y nunca el nombre, que su justificante no gaste plaza ni se rechace con la
# fiesta llena, que nadie lo cubra dos veces (bajo el lock y con el `UNIQUE` de respaldo), que un justificante suelto del
# mismo niño se ATE en vez de dejarlo «firmado» sin cubrir, y que asignarlo conserve las reglas de un menor a cargo.
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-exencion-cumple.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='ExencionDeQuienCumpleTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Identity/Services/GuardianPlaces.php
    app/Domain/Identity/Services/GuardianAuthorizationSigner.php
    app/Domain/Identity/Services/DependentAssigner.php
    app/Domain/Identity/Exceptions/GuardianAuthorizationRefusedException.php
    database/migrations/2026_09_26_230000_add_honoree_to_guardian_authorizations.php
)
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

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

GP=app/Domain/Identity/Services/GuardianPlaces.php
GS=app/Domain/Identity/Services/GuardianAuthorizationSigner.php
DA=app/Domain/Identity/Services/DependentAssigner.php
EX=app/Domain/Identity/Exceptions/GuardianAuthorizationRefusedException.php
MG=database/migrations/2026_09_26_230000_add_honoree_to_guardian_authorizations.php

# ── 1 · Su plaza, una vez; la cobertura, por la atadura ─────────────────────────────────────────
mutar "su plaza cuenta dos veces si alguien la cubre" "$GP" \
  "> 0 && ! \$this->honoreeCovered(\$reservationId) ? 1 : 0);" \
  "> 0 ? 1 : 0);"
mutar "cubierto no mira su justificante" "$GP" \
  $'return GuardianAuthorization::query()->where(\'order_item_id\', $reservationId)->where(\'honoree\', true)->exists()\n            || ' \
  $'return false\n            || '
mutar "cubierto no mira su menor a cargo" "$GP" \
  '|| $this->honoreeAssignment($reservationId) !== null;' \
  '|| false;'
mutar "la cobertura no ve su justificante" "$GP" \
  $'        if ($authorization !== null) {\n            $status' \
  $'        if (false) {\n            $status'
mutar "la cobertura da por vigente un descargo viejo" "$GP" \
  '$internal ? WaiverStatus::forDependent($dependent)->minorState() : null,' \
  'WaiverStatus::MINOR_CURRENT,'

# ── 2 · Su justificante ──────────────────────────────────────────────────────────────────────────
mutar "su justificante pide plaza (y se rechaza con la fiesta llena)" "$GS" \
  $'            if ($forHonoree) {\n                $this->assertHonoreeOpen($reservationId);' \
  $'            if (false) {\n                $this->assertHonoreeOpen($reservationId);'
mutar "su justificante no queda atado" "$GS" \
  "'honoree' => \$forHonoree ? true : null," \
  "'honoree' => null,"
mutar "se ata a una reserva que no lo sella" "$GS" \
  $'if ($this->guests->honoreeSeatsIn($reservationId) < 1) {\n            throw GuardianAuthorizationRefusedException::notHonoree' \
  $'if (false) {\n            throw GuardianAuthorizationRefusedException::notHonoree'
mutar "se cubre dos veces" "$GS" \
  'if ($this->places->honoreeCovered($reservationId)) {' \
  'if (false) {'
mutar "un justificante suelto del mismo niño no se ata" "$GS" \
  "\$existing->forceFill(['honoree' => true])->save();" \
  '// sin atar'
mutar "la base de datos admite dos de quien cumple" "$MG" \
  "            \$table->unique(['order_item_id', 'honoree']);" \
  ''
mutar "el rechazo por llena vuelve a mentir" "$EX" \
  "\"La reserva #{\$orderId} no tiene plazas libres para otro justificante: sus {\$capacity} plazas ya tienen dueño.\"" \
  "\"El pedido #{\$orderId} ya tiene {\$capacity} justificantes, que es toda su capacidad.\""

# ── 3 · Su menor a cargo ─────────────────────────────────────────────────────────────────────────
mutar "se asigna aunque lo cubra el justificante de su padre" "$DA" \
  "if (GuardianAuthorization::query()->where('order_item_id', \$orderItemId)->where('honoree', true)->exists()) {" \
  'if (false) {'
mutar "se asigna en una reserva que no lo sella" "$DA" \
  'if ($line->isEntry || $this->guests->honoreeSeatsIn($orderItemId) < 1) {' \
  'if ($line->isEntry) {'
mutar "se asigna sin las reglas de un menor a cargo" "$DA" \
  $'return SyncOutcome::rejected($rejections);\n                }\n\n                $current = DependentAssignment::query()->where(\'order_item_id\', $orderItemId)->get();' \
  $'return SyncOutcome::rejected([]);\n                }\n\n                $current = DependentAssignment::query()->where(\'order_item_id\', $orderItemId)->get();'
mutar "otro hijo se suma en vez de sustituir" "$DA" \
  '$old->delete();' \
  '// sin quitar'

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
