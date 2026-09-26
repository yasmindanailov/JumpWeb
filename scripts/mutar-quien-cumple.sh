#!/usr/bin/env bash
# Arnés de mutación de QUIEN CUMPLE, LA PRIMERA FILA (F3a de `specs/fiesta-sistema-nuevo.md` §4.8, `[DECIDIDO owner]` `#747`).
#
# Protege lo que hace distinta a la ficha 0 de una reserva sellada: que el sello nazca con la reserva, el espejo con la
# invitación en los dos sentidos (y la puerta de `PublicFreeText`), que la ficha esté CLAVADA al compactar, que ninguna
# respuesta caiga en ella ni la marque, que ocupe su plaza en el suelo y en las firmas (aforo), la API y la lista.
#
# ⚠️ El aforo bajo CARRERA no se mide aquí (la suite corre en SQLite): eso es `VERIFY_CONC=1` en el push.
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · comprobar que la mutación
# SE APLICÓ · restaurar por COPIA DE SEGURIDAD y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-quien-cumple.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='QuienCumpleFilaTest|ModuleContractsTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Services/OrderCreator.php
    app/Domain/Booking/Models/TicketType.php
    app/Domain/Booking/Models/OrderItem.php
    app/Domain/Booking/Services/PartyInvitations.php
    app/Domain/Booking/Services/GuestCardOrder.php
    app/Domain/Booking/Services/PartyGuestsReader.php
    app/Domain/Identity/Services/GuardianPlaces.php
    app/Http/Resources/Api/V1/GuestFormResource.php
    app/Http/Fiesta/ListaDeInvitados.php
    resources/views/fiesta/lista/zona-1.blade.php
    resources/views/fiesta/lista/zona-2.blade.php
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
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
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
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

OC=app/Domain/Booking/Services/OrderCreator.php
TT=app/Domain/Booking/Models/TicketType.php
OI=app/Domain/Booking/Models/OrderItem.php
PI=app/Domain/Booking/Services/PartyInvitations.php
GCO=app/Domain/Booking/Services/GuestCardOrder.php
PGR=app/Domain/Booking/Services/PartyGuestsReader.php
GP=app/Domain/Identity/Services/GuardianPlaces.php
API=app/Http/Resources/Api/V1/GuestFormResource.php
LDI=app/Http/Fiesta/ListaDeInvitados.php
Z1=resources/views/fiesta/lista/zona-1.blade.php
Z2=resources/views/fiesta/lista/zona-2.blade.php

# ── 1 · El sello y el ajuste del pack ──────────────────────────────────────────────────────────
mutar "la reserva deja de sellarse al nacer" "$OC" \
  "'honoree_row' => \$type->countsHonoree()," \
  "'honoree_row' => false,"
mutar "el ajuste deja de exigir un pack" "$TT" \
  $'return (bool) $this->honoree_counts\n            && $this->isPack()' \
  'return (bool) $this->honoree_counts'

# ── 2 · El espejo con la invitación ─────────────────────────────────────────────────────────────
mutar "personalizar deja de escribir la ficha 0" "$PI" \
  "\$this->mirrorHonoreeToRow(\$invitation);" \
  ""
mutar "guardar la lista deja de escribir la tarjeta" "$OI" \
  "app(PartyInvitations::class)->syncHonoreeFromRow(\$this);" \
  "null;"
mutar "la tarjeta publica el nombre sin pasar por PublicFreeText" "$PI" \
  "\$name = PublicFreeText::clean(trim((string) (\$row[\$nameKey] ?? '')), PartyInvitation::HONOREE_NAME_MAX);" \
  "\$name = trim((string) (\$row[\$nameKey] ?? ''));"
mutar "una ficha sin edad borra la de la tarjeta" "$PI" \
  "        if (is_numeric(\$age)) {" \
  "        if (true) {"

# ── 3 · La ficha clavada y las respuestas ───────────────────────────────────────────────────────
mutar "la ficha de quien cumple deja de estar clavada al compactar" "$GCO" \
  "\$cabeza = [array_shift(\$rows)];" \
  "\$cabeza = [];"
mutar "una respuesta puede caer en la ficha de quien cumple" "$PI" \
  "'taken' => \$honoree];" \
  "'taken' => false];"
mutar "un «no» con su nombre marca la ficha de quien cumple" "$PI" \
  "\$rows = \$nameKey === null ? [] : \$reservation->invitedGuestRows();" \
  "\$rows = \$nameKey === null ? [] : array_slice(\$reservation->guestData(), 0, (int) \$reservation->quantity, true);"
mutar "se le recuerda a quien cumple que conteste" "$PI" \
  "foreach (\$reservation->invitedGuestRows() as \$row) {" \
  "foreach (array_slice(\$reservation->guestData(), 0, (int) \$reservation->quantity) as \$row) {"

# ── 4 · Su plaza (aforo) ────────────────────────────────────────────────────────────────────────
mutar "el suelo deja de contar su plaza (Identity)" "$GP" \
  "+ \$this->guests->honoreeSeatsIn(\$reservationId);" \
  "+ 0;"
mutar "Booking deja de publicar su plaza" "$PGR" \
  "return \$item?->hasHonoreeRow() === true ? 1 : 0;" \
  "return 0;"

# ── 5 · La API y la lista ───────────────────────────────────────────────────────────────────────
mutar "la API deja de decir que la ficha 0 es de quien cumple" "$API" \
  "'honoree_row' => \$item->hasHonoreeRow()," \
  "'honoree_row' => false,"
mutar "la lista deja de reconocer su fila" "$LDI" \
  "\$esCumple = \$cumple['fila'] && \$i === OrderItem::HONOREE_ROW_INDEX;" \
  "\$esCumple = false;"
mutar "quien cumple cuenta como una respuesta confirmada" "$LDI" \
  $'            if ($n[\'origen\'] === \'cumple\') {\n                continue;\n            }\n' \
  ""
mutar "«Personalizar» vuelve a mandar el nombre (dos campos para el mismo dato)" "$Z1" \
  ":name=\"\$espejo === null ? 'honoree_name' : null\"" \
  ":name=\"'honoree_name'\""
mutar "la lista deja de abrir con quien cumple" "$Z2" \
  "\$cumpleFila = collect(\$m['ninos'])->first(fn (array \$n): bool => \$n['origen'] === 'cumple');" \
  "\$cumpleFila = null;"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
