#!/usr/bin/env bash
# Arnés de mutación de las PLAZAS CON DUEÑO y la EXCEPCIÓN DEL FIRMADOR
# (`specs/celebracion-e-invitacion.md` §4.5·7, §4.5·8 y §10.4.4, `DECISIONES #576`).
#
# Lo que protege, y las dos mitades fallan en direcciones OPUESTAS:
#
#  · **De menos**: si un «sí» no contara como plaza con dueño, el anfitrión podría bajar los invitados
#    por debajo de quien ya le confirmó y dejar fuera a un niño que avisó — sin que nada fallara.
#  · **De más**: si la firma atada a un «sí» no levantara el tope, el padre que dijo «sí» **no podría
#    firmar**: su propio aviso le cerraría la puerta. Es la peor forma de fallar de esta feature.
#  · Y en medio, la SEGURIDAD: si el firmador se creyera el id que le pasan en vez de preguntarle al
#    contrato, un enlace de otra fiesta serviría para saltarse el tope de ésta.
#
# ⚠️ La rotación del enlace se muta por su GUARDA y su RASTRO, no por su texto: un bloqueo silencioso
# no deja ver que alguien lo intentó (`SEC-04`), y el token no puede entrar en el rastro (`RGPD-02`).
#
# Reglas de la casa: verde antes de mutar · comprobar que la mutación SE APLICÓ · veredicto por CÓDIGO
# DE SALIDA · restaurar por COPIA y no con `git checkout` (`#181`) · árbol byte a byte (sha1) · CONTROL.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

PLA=app/Domain/Identity/Services/GuardianPlaces.php
SIG=app/Domain/Identity/Services/GuardianAuthorizationSigner.php
RDR=app/Domain/Booking/Services/PartyGuestsReader.php
INV=app/Domain/Booking/Models/PartyInvitation.php
VIE=app/Filament/Resources/Orders/Pages/ViewOrder.php

TMP="$(mktemp -d)"
FICHEROS=("$PLA" "$SIG" "$RDR" "$INV" "$VIE")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
SHA_ANTES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"

# Las guardas de FRONTERA entran en el conjunto a propósito: la T4·4 cruza Identity↔Booking y el
# mutante de abajo («Identity se salta el contrato») solo lo caza `ModuleContractsTest`.
verde() { docker compose exec -u sail -T laravel.test php artisan test \
    --filter='InvitationPlacesTest|InvitationLinkRotationTest|ModuleContractsTest|ModuleBoundariesTest' \
    >/dev/null 2>&1; }

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

echo '── El SUELO: un «sí» es una plaza con dueño (falla DE MENOS) ────────────────────────────'

mutar "un «sí» deja de contar: el anfitrión puede dejar fuera a quien confirmó" "$PLA" \
  '            + $this->committedGuests($reservationId);' \
  '            + 0;'

mutar "los «sí» ya firmados se cuentan DOS veces (el suelo sube de más)" "$PLA" \
  '        return count(array_diff($ids, $signed));' \
  '        return count($ids);'

echo '── La FRONTERA: Identity pregunta por el contrato, no por la implementación ─────────────'

# ⚠️ Éste es el único mutante que `InvitationPlacesTest` NO puede cazar: con datos reales la
# implementación concreta devuelve lo mismo que el binding, así que todo sigue verde. Lo caza
# `ModuleContractsTest`, cuyo doble se queda sin usar en el acto.
mutar "Identity se salta el contrato y llama a la implementación de Booking" "$PLA" \
  '        $ids = $this->guests->committedReplyIdsIn($reservationId);' \
  '        $ids = app(\App\Domain\Booking\Services\PartyGuestsReader::class)->committedReplyIdsIn($reservationId);'

echo '── El contrato: qué cuenta como «sí» vivo ───────────────────────────────────────────────'

mutar "un «no» pasa a ocupar plaza" "$RDR" \
  "            ->where('attending', true)" \
  '            '

mutar "una respuesta DESCARTADA sigue ocupando («no lo apuntes» deja de servir)" "$RDR" \
  "            ->whereNull('dismissed_at');" \
  '            ;'

mutar "dos respuestas del MISMO niño ocupan dos plazas" "$RDR" \
  "            ->keyBy('child_key')" \
  "            ->keyBy('id')"

echo '── La EXCEPCIÓN del firmador (falla DE MÁS: el que avisó no podría firmar) ──────────────'

mutar "la firma atada a un «sí» NO levanta el tope: el padre que avisó no puede firmar" "$SIG" \
  '            if (! $tied && $this->places->freeIn($reservation) < 1) {' \
  '            if ($this->places->freeIn($reservation) < 1) {'

mutar "⚠ el firmador se CREE el id en vez de preguntar al contrato (otra fiesta abre ésta)" "$SIG" \
  '            $tied = $invitationReplyId !== null
                && $this->guests->isCommittedReply($invitationReplyId, $reservationId);' \
  '            $tied = $invitationReplyId !== null;'

mutar "el vínculo con la respuesta no se escribe (el niño contaría dos veces para siempre)" "$SIG" \
  "                'invitation_reply_id' => \$tied ? \$invitationReplyId : null," \
  '                '

echo '── ANULAR el enlace: la guarda y el rastro ──────────────────────────────────────────────'

mutar "rotar no cambia el token (el enlace repartido sigue abriendo)" "$INV" \
  '        $this->forceFill(['"'"'token'"'"' => $token])->save();' \
  '        '

mutar "cualquier empleado puede anular el enlace (SEC-04)" "$VIE" \
  "                \$allowed = (auth()->user()?->hasPermission('orders.edit_guest_data') ?? false)
                    && \$invitation !== null;" \
  '                $allowed = $invitation !== null;'

mutar "el intento bloqueado NO deja rastro (nadie ve que alguien lo intentó)" "$VIE" \
  "                        AuditLogger::log('orders.invitation_link_rotate_blocked', \$this->record, [" \
  "                        AuditLogger::log('orders.invitation_reply_received', \$this->record, ["

echo '── CONTROL: cambiar prosa NO puede poner nada en rojo ────────────────────────────────────'

mutar "un comentario reescrito" "$PLA" \
  'Los «sí» vivos que **todavía no tienen justificante atado**.' \
  '(prosa mutada por el arnés) los «sí» vivos sin justificante.' \
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
