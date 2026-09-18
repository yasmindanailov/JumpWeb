#!/usr/bin/env bash
# Arnés de mutación de la INVITACIÓN DIGITAL POR API
# (`specs/celebracion-e-invitacion.md` §4.10 y §10.4.6, `DECISIONES #578`).
#
# Esta tanda tiene **dos superficies con dos credenciales**, y casi todos los mutantes atacan lo que
# las separa:
#
#  · **La HOJA EN BLANCO.** Si la tarjeta pública filtrara una respuesta, un contador o el token,
#    cualquiera con el enlace —que se reparte a un grupo de clase entero— podría averiguar **quién va
#    a la fiesta de un niño**. Es la propiedad de la que cuelga toda la feature.
#  · **El ORÁCULO.** Si los cuatro «no» dejaran de ser el mismo 404, el código de estado contaría que
#    un token existió. Se muta cada peldaño por separado, porque basta uno para abrir la rendija.
#  · **El `where` de la reserva.** En adoptar y en descartar es una GUARDA, no una optimización: sin
#    él, un anfitrión toca las respuestas de otra familia acertando un número.
#
# ⚠️ Lo que este arnés **no** necesita mutar: que el cuerpo de una respuesta no lleve campos de más lo
# impide `additionalProperties: false` del contrato, que `ApiContractTest` ya vigila con su propia
# mutación. Duplicarlo aquí sería medir dos veces lo mismo.
#
# Reglas de la casa: verde antes de mutar · comprobar que la mutación SE APLICÓ · veredicto por CÓDIGO
# DE SALIDA · restaurar por COPIA y no con `git checkout` (`#181`) · árbol byte a byte (sha1) · CONTROL.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SRV=app/Domain/Booking/Services/PartyInvitations.php
CARD=app/Http/Resources/Api/V1/InvitationCardResource.php
CTRL=app/Http/Controllers/Api/V1/InvitationsController.php
HOST=app/Http/Controllers/Api/V1/InvitationHostController.php
GF=app/Http/Controllers/Api/V1/GuestFormController.php
SIG=app/Domain/Identity/Services/GuardianAuthorizationSigner.php

TMP="$(mktemp -d)"
FICHEROS=("$SRV" "$CARD" "$CTRL" "$HOST" "$GF" "$SIG")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
SHA_ANTES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"

verde() { docker compose exec -u sail -T laravel.test php artisan test \
    --filter='InvitationApiTest|PartyInvitationsTest|InvitationPlacesTest' >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; mutantes=0; controles=0; control_ok=1; no_aplicados=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" espera="${5:-muerde}"
    if [[ "$espera" == 'control' ]]; then controles=$((controles + 1)); else mutantes=$((mutantes + 1)); fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        if [[ "$espera" == 'control' ]]; then controles=$((controles - 1)); else mutantes=$((mutantes - 1)); fi
        no_aplicados=$((no_aplicados + 1))
        return
    fi
    touch "$fichero"
    if verde; then
        if [[ "$espera" == 'control' ]]; then
            echo "  ✓ CONTROL en verde, como debe: $nombre"
        else
            echo "  ✗ NO muerde: $nombre"
        fi
    else
        if [[ "$espera" == 'control' ]]; then
            echo "  ✗ CONTROL EN ROJO — el arnés acusa a lo que no cambia conducta: $nombre"
            control_ok=0
        else
            echo "  ✓ muerde:    $nombre"
            muerden=$((muerden + 1))
        fi
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

echo '── La HOJA EN BLANCO: lo que un desconocido NO puede saber ──────────────────────────────'

mutar "⚠ la tarjeta pública filtra el TELÉFONO del anfitrión sin que él lo marcara" "$CARD" \
  "            'host_phone' => \$this->resource->show_host_phone
                ? (\$reservation?->order?->user?->phone ?: null)
                : null," \
  "            'host_phone' => \$reservation?->order?->user?->phone ?: null,"

echo '── El ORÁCULO: los cuatro «no» tienen que ser el MISMO 404 ──────────────────────────────'

mutar "el enlace de un pedido CANCELADO sigue abriendo" "$SRV" \
  "        if (! (bool) \$reservation->ticketType->guest_invitation || ! \$this->policy->isOpenFor(\$reservation)) {" \
  "        if (! (bool) \$reservation->ticketType->guest_invitation) {"

mutar "el enlace sigue abriendo con el titular ANONIMIZADO (RGPD-03)" "$SRV" \
  "        if (\$reservation->order?->user?->isAnonymized() ?? false) {
            return null;
        }" \
  '        '

mutar "el producto apaga la invitación y su enlace sigue abriendo" "$SRV" \
  "        if (! (bool) \$reservation->ticketType->guest_invitation || ! \$this->policy->isOpenFor(\$reservation)) {" \
  "        if (! \$this->policy->isOpenFor(\$reservation)) {"

mutar "⚠ el cuerpo se valida ANTES de resolver el token (un 422 delata que existe)" "$CTRL" \
  "        \$invitation = \$this->invitations->resolvePublic(\$token);

        abort_if(\$invitation === null, 404);

        // ⚠️ Se valida **después** de resolver el token" \
  "        // ⚠️ Se valida **después** de resolver el token"

echo '── El «where» de la reserva: guarda, no optimización ───────────────────────────────────'

mutar "⚠⚠ ADOPTAR alcanza la respuesta de OTRA fiesta" "$SRV" \
  "        \$replies = InvitationReply::query()
            ->where('order_item_id', \$reservation->getKey())
            ->whereIn('id', \$ids)" \
  "        \$replies = InvitationReply::query()
            ->whereIn('id', \$ids)"

mutar "⚠⚠ DESCARTAR alcanza la respuesta de OTRA fiesta" "$SRV" \
  "        \$reply = InvitationReply::query()
            ->where('order_item_id', \$reservation->getKey())
            ->whereKey(\$replyId)" \
  "        \$reply = InvitationReply::query()
            ->whereKey(\$replyId)"

mutar "adoptar acepta una respuesta ya adoptada o descartada" "$SRV" \
  "            ->pending()
            // Un «no» no se pinta sobre ninguna ficha, así que no hay nada que adoptar: se descarta.
            ->where('attending', true)" \
  "            ->where('attending', true)"

echo '── El ORÁCULO DE PERTENENCIA que cerró #520 ─────────────────────────────────────────────'

# ❗❗ Reintroducir el rechazo por lista completa vuelve a abrir la fuga: con la lista llena, el nombre
# que empareja se acepta y el nuevo recibe «full», así que quien tenga el enlace puede reconstruir la
# lista de invitados probando nombres. Es la misma fuga que el motivo «repetido» ya tenía cerrada.
mutar "⚠⚠ la lista completa vuelve a rechazar (oráculo de pertenencia)" "$SRV" \
  '            $joined = $attending && $this->placeFor($reservation, $existing, $childKey);' \
  "            \$joined = \$attending && \$this->placeFor(\$reservation, \$existing, \$childKey);
            if (\$attending && ! \$joined && count(\$this->namedGuestKeys(\$reservation)) >= \$quantity) {
                return InvitationReplyOutcome::refused('full');
            }"

echo '── Los DOS defectos que encontró la revisión adversarial (#579) ─────────────────────────'

# ❗❗ El camino de bandera: el anfitrión pega nombres de pila y el padre contesta con apellidos. Si la
# adopción vuelve a marcar con la clave del PADRE, la reconciliación —que compara contra las FICHAS—
# la da por huérfana y la descarta EN EL MISMO PUT.
mutar "⚠⚠ adoptar vuelve a marcar con la clave del PADRE y se descarta sola" "$SRV" \
  "            \$reply->forceFill(['adopted_at' => now(), 'adopted_name_key' => \$key])->save();" \
  "            \$reply->forceFill(['adopted_at' => now(), 'adopted_name_key' => \$reply->child_key])->save();"

# ❗❗ Un «sí» abre la puerta UNA vez. Sin la segunda mitad, el mismo id levanta el tope N veces y
# entran N justificantes por encima de lo comprado.
mutar "⚠⚠ el mismo «sí» levanta el tope del firmador tantas veces como quieras" "$SIG" \
  "                && ! GuardianAuthorization::query()
                    ->where('order_item_id', \$reservationId)
                    ->where('invitation_reply_id', \$invitationReplyId)
                    ->exists();" \
  '                ;'

echo '── Lo que el anfitrión ve, y lo que NO debe moverse ─────────────────────────────────────'

mutar "una respuesta adoptada sigue proponiéndose (el anfitrión la repasa dos veces)" "$SRV" \
  '            ->pending()
            ->orderBy('"'"'id'"'"')' \
  "            ->orderBy('id')"

mutar "la ficha propuesta se calcula mal: todas caen en la primera vacía" "$SRV" \
  '        if (count($candidates) === 1) {' \
  '        if (false) {'

mutar "una adoptada cuya ficha se borró NO se descarta (queda escondida y ocupando plaza)" "$GF" \
  "            app(PartyInvitations::class)->reconcileAdopted(\$item->fresh(['ticketType']) ?? \$item);" \
  '            '

mutar "personalizar TOCA la reserva y deja obsoleta la página abierta del anfitrión" "$HOST" \
  '        return new InvitationResource($this->invitations->personalize($invitation, $data), $item);' \
  '        $item->touch();

        return new InvitationResource($this->invitations->personalize($invitation, $data), $item);'

mutar "un texto con enlace se guarda tal cual bajo el dominio del parque (SEC-07)" "$SRV" \
  '            if ($clean !== null) {
                $changes[$field] = $clean;
            }' \
  '            $changes[$field] = $clean ?? $value;'

echo '── El enlace que se cierra SOLO cuando exista la página ─────────────────────────────────'

mutar "el enlace se clava a mano y no seguiría a la ruta real de la T5" "$SRV" \
  "        return Route::has(self::PUBLIC_ROUTE)
            ? route(self::PUBLIC_ROUTE, ['token' => (string) \$invitation->token])
            : null;" \
  "        return url('/invitacion/'.\$invitation->token);"

echo '── CONTROL: cambiar prosa NO puede poner nada en rojo ────────────────────────────────────'

mutar "un comentario reescrito" "$SRV" \
  'Un padre no escribe `order_items`.' \
  '(prosa mutada por el arnés) un padre no escribe la reserva.' \
  control

echo
restaurar
SHA_DESPUES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"
if [[ "$SHA_ANTES" != "$SHA_DESPUES" ]]; then
    echo "✗ el árbol NO quedó byte a byte como estaba." >&2
    exit 1
fi
echo "✓ árbol restaurado byte a byte (sha1 $SHA_ANTES)"
echo "▶ mutaciones que muerden: $muerden/$mutantes · controles en verde: $controles · sin aplicar: $no_aplicados"
[[ "$muerden" == "$mutantes" && "$control_ok" == 1 && "$no_aplicados" == 0 ]] || exit 1
