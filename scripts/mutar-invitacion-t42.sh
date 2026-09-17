#!/usr/bin/env bash
# Arnés de mutación de las REGLAS DEL DOMINIO de la invitación digital
# (`specs/celebracion-e-invitacion.md` §4.5 y §10.4.2, `DECISIONES #574`).
#
# Lo que protege: la hoja en blanco (un repetido se acepta EN SILENCIO), la lista completa, el
# emparejado por nombre y por primera palabra, el plazo, el tope anti-spam, el saneo del texto que se
# publica y los dos lectores de columna — que se leen por REGLA y no por clave quemada.
#
# ⚠️⚠️ **Lo que este arnés NO puede probar: el `lockForUpdate()`.** La suite corre sobre SQLite, donde
# `SQLiteGrammar::compileLock()` devuelve cadena vacía: quitar el lock **no mueve ni un caso**. Esa
# guarda la da `php artisan invitation:verify-places` sobre InnoDB, y su mutante es retirar el lock y
# verlo aceptar a varios. Un arnés que incluyera aquí ese mutante diría «no muerde» y tendría razón —
# midiendo el motor equivocado.
#
# Reglas de la casa dentro: verde antes de mutar · comprobar que la mutación SE APLICÓ · veredicto por
# CÓDIGO DE SALIDA · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`) · árbol byte a
# byte al terminar (sha1) · y un CONTROL que tiene que quedarse en verde.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SRV=app/Domain/Booking/Services/PartyInvitations.php
TXT=app/Domain/Platform/Services/PublicFreeText.php
TYP=app/Domain/Booking/Models/TicketType.php

TMP="$(mktemp -d)"
FICHEROS=("$SRV" "$TXT" "$TYP")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
SHA_ANTES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"

verde() { docker compose exec -u sail -T laravel.test php artisan test \
    --filter='PartyInvitationsTest|PublicFreeTextTest' >/dev/null 2>&1; }

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

echo '── La HOJA EN BLANCO y la lista completa ─────────────────────────────────────────────────'

mutar "un nombre repetido pasa a ocupar plaza NUEVA (rompe V6 por la puerta de atrás)" "$SRV" \
  '        if ($pending->contains($childKey)) {
            return true;
        }' \
  '        if (false) {
            return true;
        }'

mutar "la lista completa admite uno más (un niño de pie en la fiesta)" "$SRV" \
  '        return $taken >= max(0, (int) $reservation->quantity) ? null : false;' \
  '        return $taken > max(0, (int) $reservation->quantity) ? null : false;'

mutar "los «sí» pendientes dejan de contar: solo cuentan las fichas escritas" "$SRV" \
  '        $taken = count($namedKeys) + $pendingOwnPlaces;' \
  '        $taken = count($namedKeys);'

echo '── El EMPAREJADO ─────────────────────────────────────────────────────────────────────────'

mutar "deja de emparejar por la PRIMERA PALABRA («Mateo» ≠ «Mateo Ruiz»)" "$SRV" \
  '            if ($key === $childKey || ($key !== '"''"' && $key === $firstWord)) {' \
  '            if ($key === $childKey) {'

mutar "deja de emparejar por el nombre COMPLETO" "$SRV" \
  '            if ($key === $childKey || ($key !== '"''"' && $key === $firstWord)) {' \
  '            if ($key !== '"''"' && $key === $firstWord) {'

echo '── Los PLAZOS y el tope ──────────────────────────────────────────────────────────────────'

mutar "se contesta pasado el plazo (el anfitrión ya no puede mover su lista, un extraño sí)" "$SRV" \
  '            if (! $this->policy->isWithinWindow($reservation)) {' \
  '            if (false) {'

mutar "una reserva cancelada sigue recogiendo respuestas" "$SRV" \
  '            if ($reservation === null || ! $this->policy->isOpenFor($reservation)) {' \
  '            if ($reservation === null) {'

mutar "el tope anti-spam sube cien veces" "$SRV" \
  '    public const REPLY_CAP_PER_GUEST = 3;' \
  '    public const REPLY_CAP_PER_GUEST = 300;'

echo '── Los LECTORES por REGLA (no por clave quemada) ─────────────────────────────────────────'

mutar "la columna de nombre se lee por la clave «name» en vez de por su tipo" "$SRV" \
  '        $nameKey = $type?->guestNameFieldKey();' \
  '        $nameKey = '"'"'name'"'"';'

mutar "la edad del homenajeado se lee por una clave llamada «age»" "$SRV" \
  '        $key = $type->celebrantAgeFieldKey();' \
  '        $key = '"'"'age'"'"';'

mutar "el nombre del homenajeado se lee por la clave «celebrant»" "$SRV" \
  '        $key = $type->celebrantNameFieldKey();' \
  '        $key = '"'"'celebrant'"'"';'

echo '── El texto que se PUBLICA (SEC-07) ──────────────────────────────────────────────────────'

# ⚠️ Se muta el RECORRIDO y no los patrones: una expresión regular dentro de un `replace` de bash
# llega con las barras invertidas dobladas y el mutante no se aplica — pasó, y el arnés lo dijo
# («NO SE APLICÓ»), que es justo para lo que existe esa comprobación.
mutar "solo se mira el primer patrón: correos y dominios pelados pasan" "$TXT" \
  'foreach (self::FORBIDDEN as $pattern) {' \
  'foreach (array_slice(self::FORBIDDEN, 0, 1) as $pattern) {'

mutar "se deja de mirar el ÚLTIMO patrón: «regaloslucia.com» pasa" "$TXT" \
  'foreach (self::FORBIDDEN as $pattern) {' \
  'foreach (array_slice(self::FORBIDDEN, 0, 4) as $pattern) {'

echo '── CONTROL: cambiar prosa NO puede poner nada en rojo ────────────────────────────────────'

mutar "un comentario reescrito" "$SRV" \
  '// Un «no» NUNCA ocupa' \
  '// (prosa mutada por el arnés) un «no» NUNCA ocupa' \
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
