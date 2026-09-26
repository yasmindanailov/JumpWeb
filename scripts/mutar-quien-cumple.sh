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

FILTER='QuienCumpleFilaTest|ModuleContractsTest|AlFinalVieneTest|GuestCountSurfacesTest'
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
    app/Http/Controllers/GuestFormController.php
    app/Http/Controllers/Api/V1/GuestFormController.php
    resources/views/components/fiesta/fila-invitado.blade.php
    resources/views/fiesta/lista/zona-3.blade.php
)
# ⚠️ La copia va por la RUTA entera, no por el nombre base: hay dos `GuestFormController.php` (la web y la API), y por el
# nombre base el segundo pisaba al primero y la restauración escribía un controlador encima del otro.
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
mutar "la firma de quien cumple deja de mirar su ficha de menor a cargo (F3b)" "$LDI" \
  "                || (\$esCumple && self::cumpleFirmado(\$reservation, \$key)));" \
  ");"
mutar "cuenta como firmada una exención que no está vigente (F3b)" "$LDI" \
  "->minorState() === WaiverStatus::MINOR_CURRENT) {" \
  "->minorState() !== null) {"
mutar "la lista deja de abrir con quien cumple" "$Z2" \
  "\$cumpleFila = collect(\$m['ninos'])->first(fn (array \$n): bool => \$n['origen'] === 'cumple');" \
  "\$cumpleFila = null;"

# ── 6 · «Al final viene» (F3c) ──────────────────────────────────────────────────────────────────
GFW=app/Http/Controllers/GuestFormController.php
GFA=app/Http/Controllers/Api/V1/GuestFormController.php
FILA=resources/views/components/fiesta/fila-invitado.blade.php
mutar "vuelve también un «sí» (el id no se contrasta con un «no»)" "$PI" \
  $'->pending()\n            ->where(\'attending\', false)\n            ->orderBy(\'id\')\n            ->get();\n\n        $rows' \
  $'->pending()\n            ->orderBy(\'id\')\n            ->get();\n\n        $rows'
mutar "la ficha de quien cumple cuenta como ficha libre" "$PI" \
  "if (\$candidata(\$i) && \$row === []) {" \
  "if (\$row === []) {"
mutar "sin ficha libre, se calla" "$PI" \
  $'                $full++;\n' \
  ""
mutar "la respuesta no pasa a «sí»" "$PI" \
  $'\'attending\' => true,\n                \'host_rejoined_at\' => now(),' \
  "'host_rejoined_at' => now(),"
mutar "no queda el rastro de que la cambió el anfitrión" "$PI" \
  $'                \'host_rejoined_at\' => now(),\n' \
  ""
mutar "el nombre no se escribe en la ficha libre" "$PI" \
  "        if (\$nuevas) {" \
  "        if (false) {"
mutar "la web deja de mandar la vuelta" "$GFW" \
  "\$rejoinFull = \$rejoin !== []" \
  "\$rejoinFull = false"
mutar "la API deja de mandar la vuelta" "$GFA" \
  "if (isset(\$validated['rejoin']) && is_array(\$validated['rejoin'])) {" \
  "if (false) {"
mutar "«Al final viene» deja de ser un botón de envío (sin JavaScript no hace nada)" "$FILA" \
  ":type=\"\$volverValue !== null ? 'submit' : null\"" \
  ":type=\"null\""
mutar "la ficha del «no» pierde el id de su respuesta" "$LDI" \
  "'no_reply_id' => \$declinada ? (int) (\$declinadas->get(\$i)['id'] ?? 0) : null," \
  "'no_reply_id' => null,"

# ── 7 · La lista que supera la reserva (F4) ─────────────────────────────────────────────────────
Z3=resources/views/fiesta/lista/zona-3.blade.php
mutar "se guarda con más niños que el número sin «Sí» (cobro sin confirmar o nombres tirados)" "$GFW" \
  "if (\$llenas !== null && \$llenas > (\$desiredCount ?? (int) \$reservation->quantity)) {" \
  "if (false) {"
mutar "con la subida rechazada, las fichas de más se guardan a medias" "$GFW" \
  "if (! \$countChange->applied && \$llenas !== null && \$llenas > (int) \$reservation->quantity) {" \
  "if (false) {"
mutar "una vuelta sobre su propia ficha cuenta como plaza nueva" "$PI" \
  "            ->filter(fn (\$childKey): bool => ! \$this->matches(\$keys, (string) \$childKey))" \
  "            ->filter(fn (\$childKey): bool => true)"
mutar "las vueltas sueltas no cuentan en la guarda" "$GFW" \
  "return \$llenas + app(PartyInvitations::class)->rejoinCardsNeeded(\$reservation, \$rejoin, \$guests);" \
  "return \$llenas;"
mutar "la zona 3 pierde el precio de un niño más" "$LDI" \
  "'precio_nino' => \$editable ? self::precioNino(\$reservation) : ''," \
  "'precio_nino' => '',"
mutar "sin plantilla no se pueden añadir niños de más" "$LDI" \
  "'plantilla' => ((bool) \$v['guestCount']['editable'] && ! \$readonly) ? self::plantilla(\$columnas) : null," \
  "'plantilla' => null,"
mutar "la zona 3 enseña el estado equivocado" "$Z3" \
  "\$estado = \$num['en_lista'] < \$num['valor'] ? 'libres'" \
  "\$estado = \$num['en_lista'] < \$num['valor'] ? 'listo'"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
