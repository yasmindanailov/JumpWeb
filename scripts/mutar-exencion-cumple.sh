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

FILTER='ExencionDeQuienCumpleTest|ExencionDeQuienCumpleWebTest|QuienCumpleFilaTest|GateHonoreeTest|GateInvitedGuestsTest|VisitEveNoticeTest|HonoreeWaiverApiTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Identity/Services/GuardianPlaces.php
    app/Domain/Identity/Services/GuardianAuthorizationSigner.php
    app/Domain/Identity/Services/DependentAssigner.php
    app/Domain/Identity/Exceptions/GuardianAuthorizationRefusedException.php
    database/migrations/2026_09_26_230000_add_honoree_to_guardian_authorizations.php
    app/Http/Fiesta/ListaDeInvitados.php
    app/Http/Fiesta/Autorizacion.php
    app/Http/Controllers/HonoreeWaiverController.php
    app/Http/Controllers/GuardianAuthorizationController.php
    app/Http/Concerns/ComposesGuardianForm.php
    app/Domain/Booking/Services/GateReservationsReader.php
    app/Domain/Identity/Services/GateProfile.php
    resources/views/livewire/admin/puerta/validar.blade.php
    app/Domain/Booking/Services/PendingBeforeVisit.php
    app/Domain/Booking/Contracts/PendingWork.php
    app/Notifications/VisitEveNotice.php
    app/Domain/Booking/Models/OrderItem.php
    app/Http/Resources/Api/V1/GuestFormResource.php
    app/Http/Controllers/Api/V1/HonoreeWaiverController.php
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
  "\$authorizations = GuardianAuthorization::query()->whereIn('order_item_id', \$sealed)->where('honoree', true)->get();" \
  "\$authorizations = GuardianAuthorization::query()->whereRaw('1 = 0')->get();"
mutar "la cobertura da por vigente un descargo viejo" "$GP" \
  '($dependentStatuses[(int) $d->getKey()] ?? null)?->minorState(),' \
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

# ── 4 · F7b: la lista ────────────────────────────────────────────────────────────────────────────
LI=app/Http/Fiesta/ListaDeInvitados.php
AU=app/Http/Fiesta/Autorizacion.php
HC=app/Http/Controllers/HonoreeWaiverController.php
GC=app/Http/Controllers/GuardianAuthorizationController.php
CF=app/Http/Concerns/ComposesGuardianForm.php
mutar "su fila sale firmada sin cobertura" "$LI" \
  '? ($cobertura?->signed() ?? false)' \
  '? true'
mutar "el justificante de quien cumple firma a un invitado de su nombre" "$LI" \
  "! (\$f['honoree'] ?? false) && PersonNameKey::cardMatches(" \
  "PersonNameKey::cardMatches("
mutar "«Firmada · Falta» fuera del modo interno" "$LI" \
  '$firmaVisible = WaiverSettings::isInternal();' \
  '$firmaVisible = true;'
mutar "el panel sigue con quien cumple firmado" "$LI" \
  '|| $cobertura === null || $cobertura->signed()) {' \
  '|| $cobertura === null) {'
mutar "el camino de la cuenta se pinta sin la sesión del titular" "$LI" \
  "\$sesion = \$viewer instanceof User && (int) \$viewer->getKey() === (int) (\$reservation->order->user_id ?? 0);" \
  '$sesion = true;'

# ── 5 · F7b: el camino de la cuenta ──────────────────────────────────────────────────────────────
mutar "otra cuenta con un enlace firmado escribe (sin comprobar que es el titular)" "$HC" \
  'if (! $holder instanceof User || ! $this->ownsGuestForm($request, $reservation) || ! WaiverSettings::isInternal()) {' \
  'if (! $holder instanceof User || ! WaiverSettings::isInternal()) {'
mutar "sin correo verificado deja medio paso" "$HC" \
  'if ($holder->email_verified_at === null) {' \
  'if (false) {'
mutar "firma sin la casilla" "$HC" \
  "'accept_waiver' => ['accepted']," \
  "'accept_waiver' => ['nullable'],"
mutar "firma un texto que ya no es el vigente" "$HC" \
  $'if ($document === null) {\n            return $this->back($request, $reservation, \'cumple-stale\');' \
  $'if (false) {\n            return $this->back($request, $reservation, \'cumple-stale\');'
mutar "un hijo sin su descargo no se firma en el mismo paso" "$HC" \
  'if (! $estado->signed || $estado->isOutdated()) {' \
  'if (false) {'

# ── 6 · F7b: su justificante ─────────────────────────────────────────────────────────────────────
mutar "el enlace de quien cumple no llega a la página" "$GC" \
  "if (\$request->query('para') === 'cumple') {" \
  'if (false) {'
mutar "la página de quien cumple se cierra por llena" "$CF" \
  $'        if ($honoree) {\n            if (app(PartyGuests::class)' \
  $'        if (false) {\n            if (app(PartyGuests::class)'
mutar "el envío de quien cumple no se ata" "$GC" \
  "(\$this->invitationExtras(\$request)['para'] ?? null) === 'cumple',
            );" \
  "false,
            );"
mutar "su casilla dice «a cargo de»" "$AU" \
  "'casilla' => \$cumple !== null ? __('fiesta.firma.casilla_cumple')" \
  "'casilla' => false ? __('fiesta.firma.casilla_cumple')"

# ── 7 · F7c: la puerta ───────────────────────────────────────────────────────────────────────────
GR=app/Domain/Booking/Services/GateReservationsReader.php
GF=app/Domain/Identity/Services/GateProfile.php
GV=resources/views/livewire/admin/puerta/validar.blade.php
mutar "la ficha de quien cumple vuelve a los invitados de la puerta" "$GR" \
  'foreach ($item->invitedGuestRows() as $row) {' \
  'foreach (array_slice($item->guestData(), 0, max(0, (int) $item->quantity)) as $row) {'
OI=app/Domain/Booking/Models/OrderItem.php
mutar "la puerta no sabe que la reserva lo sella" "$GR" \
  'honoreeName: $item->honoreeName(),' \
  'honoreeName: null,'
mutar "sin ficha, el homenajeado de la reserva no lo nombra" "$OI" \
  "if (\$name === '' && \$celebrantKey !== null) {" \
  'if (false) {'
mutar "su justificante lo nombra con otra fuente que la puerta" "$GC" \
  "? (string) \$reservation->honoreeName() : null," \
  "? '' : null,"
mutar "la puerta no pregunta por quien cumple" "$GF" \
  'static fn (GateReservation $r): bool => $r->honoreeName !== null && ($r->invitationOffered || $r->waiverOffered),' \
  'static fn (GateReservation $r): bool => false,'
mutar "su justificante se repite entre las firmas sueltas" "$GF" \
  '$matched[] = $c->authorizationId;' \
  '// sin marcar'
mutar "no cuenta en «8 de 12»" "$GF" \
  'if ($c->covered() && $r->invitationOffered) {' \
  'if (false) {'
mutar "cubierto, el nombre sale de la ficha y no de su prueba" "$GF" \
  "'name' => \$c->covered() ? (string) \$c->name : (string) \$r->honoreeName," \
  "'name' => (string) \$r->honoreeName,"
mutar "sin la edad de su prueba" "$GF" \
  "'age' => \$c->bornOn === null ? null : Dependent::ageBetween(\$c->bornOn, \$day)," \
  "'age' => null,"
mutar "sin el estado de su descargo" "$GF" \
  "'waiver' => \$c->waiver," \
  "'waiver' => null,"
mutar "cubierto sigue «sin resolver»" "$GF" \
  "(\$c->covered() ? 'signed' : 'unresolved')," \
  "'unresolved',"
mutar "el justificante de quien cumple firma a un invitado de la puerta" "$GF" \
  'if ((int) $a->order_item_id !== $reservationId || $a->honoree === true) {' \
  'if ((int) $a->order_item_id !== $reservationId) {'
mutar "una reserva sin invitación cuenta en «8 de 12»" "$GF" \
  $'            if (! $r->invitationOffered) {\n                continue;' \
  $'            if (false) {\n                continue;'
mutar "la pantalla no marca a quien cumple" "$GV" \
  '@if ($g['"'"'honoree'"'"'] ?? false) data-gate-guest-minor-honoree @endif' \
  ''

# ── 8 · F7c: la víspera ──────────────────────────────────────────────────────────────────────────
PB=app/Domain/Booking/Services/PendingBeforeVisit.php
PW=app/Domain/Booking/Contracts/PendingWork.php
VE=app/Notifications/VisitEveNotice.php
mutar "la víspera no pregunta por quien cumple" "$PB" \
  'honoreeWaiverMissing: $reservation->hasHonoreeRow() && $this->honoree->honoreeWaiverMissing((int) $reservation->getKey()),' \
  'honoreeWaiverMissing: false,'
mutar "le falta el descargo y no se avisa" "$PW" \
  '|| $this->honoreeWaiverMissing' \
  '|| false'
mutar "fuera del modo interno se le pide" "$GP" \
  $'        if (! WaiverSettings::isInternal()) {\n            return false;\n        }\n\n        $coverage = $this->honoreeCoverage($reservationId);' \
  $'        if (false) {\n            return false;\n        }\n\n        $coverage = $this->honoreeCoverage($reservationId);'
mutar "cubierto con el descargo vigente sigue faltando" "$GP" \
  'return $coverage !== null && ! $coverage->signed();' \
  'return $coverage !== null;'
mutar "el correo no lo nombra" "$VE" \
  'if ($this->pending->honoreeWaiverMissing) {' \
  'if (false) {'

# ── 9 · F7c: la API ──────────────────────────────────────────────────────────────────────────────
RS=app/Http/Resources/Api/V1/GuestFormResource.php
AC=app/Http/Controllers/Api/V1/HonoreeWaiverController.php
mutar "la API lo publica fuera del modo interno" "$RS" \
  $'        if (! WaiverSettings::isInternal()) {\n            return null;' \
  $'        if (false) {\n            return null;'
mutar "cubierto, la API sigue ofreciendo el justificante" "$RS" \
  "'authorization_url' => \$coverage->covered() || \$item->isFinishedInPractice()" \
  "'authorization_url' => \$item->isFinishedInPractice()"
mutar "ata quien trae el enlace firmado sin ser el titular" "$AC" \
  'abort_unless($this->ownsGuestForm($request, $item), 403);' \
  ''
mutar "ata fuera del modo interno" "$AC" \
  $'        if (! WaiverSettings::isInternal()) {\n            return ApiErrorResponse::make(ApiErrorCode::WaiverNotInternal, 409);' \
  $'        if (false) {\n            return ApiErrorResponse::make(ApiErrorCode::WaiverNotInternal, 409);'
mutar "ata con la fiesta pasada" "$AC" \
  $'        if ($item->isFinishedInPractice()) {\n            return ApiErrorResponse::make(ApiErrorCode::GuestFormClosed, 409);' \
  $'        if (false) {\n            return ApiErrorResponse::make(ApiErrorCode::GuestFormClosed, 409);'
mutar "el rechazo no dice por qué" "$AC" \
  "'dependent_id' => \$reason !== null ? __('api.dependents.'.\$reason) : __('fiesta.lista.cumple_firma.err_otro')," \
  "'dependent_id' => __('fiesta.lista.cumple_firma.err_otro'),"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
