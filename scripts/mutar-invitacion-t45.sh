#!/usr/bin/env bash
# Arnés de mutación del RGPD DE LA INVITACIÓN DIGITAL
# (`specs/celebracion-e-invitacion.md` §4.4 y §10.4.5, `DECISIONES #577`; `RGPD-01`).
#
# Una unidad de BORRADO no falla sola: falla el día que la supresión corre en producción y nadie mira.
# Estas mutaciones son las cuatro formas de equivocarse, y **dos van en direcciones opuestas**:
#
#  · **De menos** — la supresión deja PII de un menor en pie: el titular borró su cuenta y el nombre
#    del homenajeado, o lo que contestó el padre de otro niño, sigue ahí. Silencioso.
#  · **De más** — la supresión se lleva el JUSTIFICANTE, que no es del responsable para que él lo
#    borre (art. 17.3.e): el parque se queda sin la prueba de una visita que ocurrió.
#  · **Sin acotar** — un `delete()` al que le falta el `where` borra las fiestas de TODO el mundo, y
#    los dos primeros casos lo darían por bueno. De ahí el control del segundo anfitrión.
#  · **Apoyada en la cascada** — sin apagar las FK esa mutación no mordería, y el caso que las apaga
#    es lo único que distingue «lo borra la supresión» de «lo borra la base de datos».
#
# ⚠️ **Lo que este arnés NO puede mutar, dicho para que no se lea como cobertura**: la cascada de la
# purga vive en las claves foráneas, no en PHP, y que la cadena de la firma siga verificando es
# estructural —el hash cubre una COPIA del nombre, no la relación viva—. Los dos se comprueban
# empíricamente en el fichero de pruebas; ninguno tiene mutante aquí, y eso es un hecho, no un hueco
# tapado.
#
# Reglas de la casa: verde antes de mutar · comprobar que la mutación SE APLICÓ · veredicto por CÓDIGO
# DE SALIDA · restaurar por COPIA y no con `git checkout` (`#181`) · árbol byte a byte (sha1) · CONTROL.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

USR=app/Domain/Identity/Models/User.php
EXP=app/Domain/Booking/Services/CustomerOrderHistoryReader.php

TMP="$(mktemp -d)"
FICHEROS=("$USR" "$EXP")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
SHA_ANTES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"

# Las guardas HERMANAS del art. 17 entran en el conjunto a propósito: quien toca `anonymize()` tiene
# que medirse contra la supresión entera, no solo contra su tanda.
verde() { docker compose exec -u sail -T laravel.test php artisan test \
    --filter='InvitationPrivacyTest|PrivacyTest|GuestMinorAuthorizationTest' >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; mutantes=0; controles=0; control_ok=1; no_aplicados=0; declarados=0; declarado_ok=1

# Tres clases de mutación, y la tercera es la que hace honesto a este arnés:
#   · `muerde`    — tiene que poner la suite en rojo.
#   · `control`   — no cambia conducta; tiene que quedarse en verde.
#   · `declarado` — **sobrevive por diseño**, con su razón escrita. Si algún día MUERDE, el arnés
#                   avisa: ha nacido una guarda y hay que reclasificarla. Esconder un superviviente
#                   bajando el denominador es la forma limpia de mentir en un informe de mutación.
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" espera="${5:-muerde}"
    case "$espera" in
        control) controles=$((controles + 1)) ;;
        declarado) declarados=$((declarados + 1)) ;;
        *) mutantes=$((mutantes + 1)) ;;
    esac
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        case "$espera" in
            control) controles=$((controles - 1)) ;;
            declarado) declarados=$((declarados - 1)) ;;
            *) mutantes=$((mutantes - 1)) ;;
        esac
        no_aplicados=$((no_aplicados + 1))
        return
    fi
    touch "$fichero"
    if verde; then
        case "$espera" in
            control) echo "  ✓ CONTROL en verde, como debe: $nombre" ;;
            declarado) echo "  ◦ SOBREVIVE, declarado: $nombre" ;;
            *) echo "  ✗ NO muerde: $nombre" ;;
        esac
    else
        case "$espera" in
            control)
                echo "  ✗ CONTROL EN ROJO — el arnés acusa a lo que no cambia conducta: $nombre"
                control_ok=0 ;;
            declarado)
                echo "  ⚠ EL DECLARADO MUERDE — ha nacido una guarda: reclasifícalo: $nombre"
                declarado_ok=0 ;;
            *)
                echo "  ✓ muerde:    $nombre"
                muerden=$((muerden + 1)) ;;
        esac
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

echo '── DE MENOS: la supresión deja PII de un menor en pie ───────────────────────────────────'

mutar "la INVITACIÓN sobrevive a la supresión (el nombre del homenajeado se queda)" "$USR" \
  "            DB::table('party_invitations')->whereIn('order_item_id', \$invitationItemIds)->delete();" \
  '            '

# ◦ DECLARADO. Con las claves foráneas ACTIVAS, al caer la invitación sus respuestas caen con ella,
# así que quitar esta consulta no cambia nada observable. Se queda como CINTURÓN —el art. 17 no debe
# depender de una acción de FK, la lección que `user_identities` dejó escrita en esa misma función—.
# ⚠️ Hubo un caso que pretendía ejercerla apagando las FK y **medido, no las apagaba**:
# `Schema::withoutForeignKeyConstraints()` es un no-op dentro de una transacción y `RefreshDatabase`
# abre una. Se retiró: una guarda que no puede fallar no es una guarda.
# ▶ Que la cascada EXISTA ya lo vigila `PartyInvitationSchemaTest::test_deleting_the_invitation_takes_its_replies`.
mutar "las RESPUESTAS solo caen por la cascada (el cinturón, no el tirante)" "$USR" \
  "            DB::table('invitation_replies')->whereIn('order_item_id', \$invitationItemIds)->delete();" \
  '            ' \
  declarado

echo '── DE MÁS: la supresión se lleva lo que NO es del responsable ───────────────────────────'

# Es el error simpático: «ya que limpio, limpio todo». Se lleva la prueba de que un adulto autorizó
# la entrada de un menor de OTRA familia a una visita que ocurrió (art. 17.3.e, `RGPD-01`).
mutar "⚠ el JUSTIFICANTE cae con la cuenta del anfitrión (art. 17.3.e)" "$USR" \
  "            DB::table('party_invitations')->whereIn('order_item_id', \$invitationItemIds)->delete();" \
  "            DB::table('party_invitations')->whereIn('order_item_id', \$invitationItemIds)->delete();
            DB::table('guardian_authorizations')->whereIn('order_item_id', \$invitationItemIds)->delete();"

echo '── SIN ACOTAR: el delete que se lleva las fiestas de todo el mundo ──────────────────────'

mutar "el borrado de RESPUESTAS pierde su where (borra las de todos los anfitriones)" "$USR" \
  "            DB::table('invitation_replies')->whereIn('order_item_id', \$invitationItemIds)->delete();" \
  "            DB::table('invitation_replies')->delete();"

mutar "el borrado de INVITACIONES pierde su where" "$USR" \
  "            DB::table('party_invitations')->whereIn('order_item_id', \$invitationItemIds)->delete();" \
  "            DB::table('party_invitations')->delete();"

echo '── El EXPORT del art. 20: datos de terceros que no son portables ────────────────────────'

# Lo que un padre contestó sobre SU hijo no es un dato del anfitrión. Es la misma doctrina que ya
# deja `guest_data` fuera del export mientras `event_data` va entero.
mutar "el export publica los nombres de los niños invitados" "$EXP" \
  "            'event_data' => \$item->event_data ?: null," \
  "            'event_data' => \$item->event_data ?: null,
            'replies' => \App\Domain\Booking\Models\InvitationReply::query()
                ->where('order_item_id', \$item->id)->pluck('child_name')->all(),"

echo '── CONTROL: cambiar prosa NO puede poner nada en rojo ────────────────────────────────────'

mutar "un comentario reescrito" "$USR" \
  'Es la MISMA clase de dato que acaba de vaciarse arriba:' \
  '(prosa mutada por el arnés) la misma clase de dato de arriba:' \
  control

echo
restaurar
SHA_DESPUES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"
if [[ "$SHA_ANTES" != "$SHA_DESPUES" ]]; then
    echo "✗ el árbol NO quedó byte a byte como estaba." >&2
    exit 1
fi
echo "✓ árbol restaurado byte a byte (sha1 $SHA_ANTES)"
echo "▶ mutaciones que muerden: $muerden/$mutantes · controles en verde: $controles · supervivientes declarados: $declarados · sin aplicar: $no_aplicados"
[[ "$muerden" == "$mutantes" && "$control_ok" == 1 && "$declarado_ok" == 1 && "$no_aplicados" == 0 ]] || exit 1
