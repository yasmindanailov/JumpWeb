#!/usr/bin/env bash
# Arnés de mutación de F8, UNA ACCIÓN POR TAREA PARA LA INVITACIÓN Y CADA ENVÍO MEDIDO (`specs/fiesta-sistema-nuevo.md`
# §4.14, `[DECIDIDO owner]` `#753`).
#
# F8a (la página): las cifras son un resumen (no filtran), «Enviar por WhatsApp» es uno y el mismo, el recordatorio sale
# junto a las cifras solo con la invitación enviada y alguien sin contestar, va a WhatsApp en una pestaña nueva, e
# «Invitar a más» solo mientras el número pueda subir. F8b (la medida): el envío y el recordatorio son hechos de la
# reserva, la invitación sabe que salió (`shared_at`, solo el primero), el envío tiene su PROPIO cupo, y el canal del
# enlace llega a la visita, a la respuesta y al informe. F8c: la app dice que salió y el recurso dice cuándo.
#
# ⚠️ Equivalente por diseño y NO se muta: el `respuestas_abiertas` de «Invitar a más» — las respuestas y el número cierran
# con el MISMO plazo (`#766`), y con el número cerrado la zona 3 no pinta ningún estado.
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-envio-invitacion.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='EnvioDeLaInvitacionTest|InvitationReminderTest|ListaDeInvitadosTest|PartiesReportTest|InvitationApiTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    resources/views/fiesta/lista/zona-1.blade.php
    resources/views/fiesta/lista/zona-3.blade.php
    resources/views/fiesta/lista/auxiliares.blade.php
    app/Http/Fiesta/ListaDeInvitados.php
    app/Http/Controllers/GuestFormController.php
    app/Http/Controllers/InvitationPageController.php
    app/Http/Controllers/Api/V1/InvitationHostController.php
    app/Http/Resources/Api/V1/InvitationResource.php
    app/Domain/Booking/Services/PartyInvitations.php
    app/Domain/Platform/Services/Analytics/Contract.php
    app/Filament/Analytics/PartiesReport.php
    routes/web.php
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

Z1=resources/views/fiesta/lista/zona-1.blade.php
Z3=resources/views/fiesta/lista/zona-3.blade.php
AX=resources/views/fiesta/lista/auxiliares.blade.php
LI=app/Http/Fiesta/ListaDeInvitados.php
GF=app/Http/Controllers/GuestFormController.php
IP=app/Http/Controllers/InvitationPageController.php
IH=app/Http/Controllers/Api/V1/InvitationHostController.php
IR=app/Http/Resources/Api/V1/InvitationResource.php
PI=app/Domain/Booking/Services/PartyInvitations.php
CT=app/Domain/Platform/Services/Analytics/Contract.php
PR=app/Filament/Analytics/PartiesReport.php
WR=routes/web.php

# ── 1 · F8a: la página ───────────────────────────────────────────────────────────────────────────
mutar "las cifras vuelven a filtrar (botones)" "$Z1" \
  '<span class="pli-cuenta"><span class="pli-cuenta-top">' \
  '<span class="pli-cuenta" data-filtro="{{ $k }}"><span class="pli-cuenta-top">'
mutar "el recordatorio y las cifras antes de enviarla" "$LI" \
  '$compartida = $inv->shared_at !== null' \
  '$compartida = true'
mutar "recordar sin nadie a quien recordar" "$Z1" \
  "@if (\$inv['respuestas_abiertas'] && \$inv['faltan'] > 0)" \
  "@if (\$inv['respuestas_abiertas'])"
mutar "el recordatorio no abre una pestaña nueva" "$AX" \
  'class="pz-sr" target="_blank">@csrf</form>' \
  'class="pz-sr">@csrf</form>'
mutar "el enlace de WhatsApp no dice su canal" "$LI" \
  "'enlace' => (string) (\$vista['url_wa'] ?? \$reserva['enlace'])" \
  "'enlace' => \$reserva['enlace']"
mutar "el enlace copiado no dice su canal" "$LI" \
  "'url_copia' => (string) (\$vista['url_copy'] ?? \$vista['url'] ?? '')," \
  "'url_copia' => (string) (\$vista['url'] ?? ''),"
mutar "«Invitar a más» con el número en el tope" "$Z3" \
  "\$puedeSubir = \$num['techo'] === null || \$num['valor'] < \$num['techo'];" \
  '$puedeSubir = true;'

# ── 2 · F8b: el recordatorio ─────────────────────────────────────────────────────────────────────
mutar "el recordatorio vuelve a la lista en vez de a WhatsApp" "$GF" \
  "return redirect()->away('https://wa.me/?text='.rawurlencode(\$text));" \
  'return redirect()->to($this->invitationBackUrl($request, $reservation));'
mutar "el recordatorio no deja su hecho" "$GF" \
  "\$this->partyFact(\$request, \$reservation, 'invitation_reminded', ['listed' => \$withNames]);" \
  '// sin hecho'
mutar "un recordatorio no marca que salió" "$PI" \
  $'        // Un recordatorio es un envío (F8, `#753`): la invitación ya salió.\n        $this->markShared($invitation);' \
  '        // sin marcar'
mutar "el enlace del recordatorio no dice su canal" "$PI" \
  "\$url = \$invitation === null ? null : \$this->shareUrlFor(\$invitation, 'rec');" \
  '$url = $invitation === null ? null : $this->shareUrlFor($invitation);'

# ── 3 · F8b: el envío ────────────────────────────────────────────────────────────────────────────
mutar "el envío no marca que salió" "$GF" \
  $'        if ($invitation !== null) {\n            $invitations->markShared($invitation);' \
  $'        if ($invitation !== null) {\n            // sin marcar'
mutar "el envío no deja su hecho" "$GF" \
  "\$this->partyFact(\$request, \$reservation, 'invitation_shared', ['via' => \$datos['via'], 'where' => \$datos['where']]);" \
  '// sin hecho'
mutar "el envío acepta cualquier canal" "$GF" \
  "'via' => ['required', 'string', 'in:whatsapp,copy']," \
  "'via' => ['required', 'string'],"
mutar "el envío se apunta con la fiesta pasada" "$GF" \
  '$invitation = $reservation->isFinishedInPractice() ? null : $invitations->existingFor($reservation);' \
  '$invitation = $invitations->existingFor($reservation);'
mutar "cada envío mueve la fecha del primero" "$PI" \
  $'            ->whereNull(\'shared_at\')\n            ->update([\'shared_at\' => now(), \'updated_at\' => now()]);' \
  $'            ->update([\'shared_at\' => now(), \'updated_at\' => now()]);'
mutar "el envío gasta el cupo de Guardar" "$WR" \
  "->middleware(['throttle:60,1,invitation-share', 'no-store'])" \
  "->middleware(['throttle:60,1', 'no-store'])"

# ── 4 · F8b: el canal del enlace, en la visita y en la respuesta ────────────────────────────────
mutar "la visita no dice su canal" "$IP" \
  "\$this->partyFact(\$request, \$reservation, 'invitation_viewed', ['channel' => \$canal]);" \
  "\$this->partyFact(\$request, \$reservation, 'invitation_viewed');"
mutar "el formulario de respuesta pierde el canal" "$IP" \
  "'replyAction' => route('invitation.reply', ['token' => \$invitation->token] + (\$canal === null ? [] : [PartyInvitations::CHANNEL_PARAM => \$canal]))," \
  "'replyAction' => route('invitation.reply', ['token' => \$invitation->token]),"
mutar "la respuesta no dice su canal" "$IP" \
  $'                \'companion\' => (bool) ($outcome->reply->companion ?? false),\n                \'channel\' => $canal,' \
  $'                \'companion\' => (bool) ($outcome->reply->companion ?? false),'
mutar "cualquier canal entra en los hechos" "$PI" \
  'return is_string($value) && in_array($value, self::CHANNELS, true) ? $value : null;' \
  'return is_string($value) ? $value : null;'
mutar "el contrato no deja pasar el canal" "$CT" \
  "'props' => ['reservation', 'days_before', 'device', 'locale', 'channel']]," \
  "'props' => ['reservation', 'days_before', 'device', 'locale']],"

# ── 5 · F8b: el informe ──────────────────────────────────────────────────────────────────────────
mutar "el embudo sin «Enviaron la invitación»" "$PR" \
  "'with_invitation', 'invitation_shared', 'invitation_viewed'," \
  "'with_invitation', 'invitation_viewed',"
mutar "«salió» ignora las que alguien ya vio" "$PR" \
  "count(\$this->sharedInvitations(\$ids) + (\$facts['by_reservation']['invitation_viewed'] ?? []))," \
  'count($this->sharedInvitations($ids)),'
mutar "«Invitar a más» no se cuenta aparte" "$PR" \
  "if ((\$props['where'] ?? null) === 'number') {" \
  "if ((\$props['where'] ?? null) === 'invitation') {"

# ── 6 · F8c: la API ──────────────────────────────────────────────────────────────────────────────
mutar "la app no marca que salió" "$IH" \
  $'        if ($invitation !== null) {\n            $this->invitations->markShared($invitation);' \
  $'        if ($invitation !== null) {\n            // sin marcar'
mutar "el envío de la app no dice que es de la app" "$IH" \
  "['via' => \$data['via'], 'where' => 'app']" \
  "['via' => \$data['via'], 'where' => 'invitation']"
mutar "el recurso no dice cuándo salió" "$IR" \
  "'shared_at' => \$this->resource->shared_at?->toIso8601String()," \
  "'shared_at' => null,"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
