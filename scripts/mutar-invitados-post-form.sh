#!/usr/bin/env bash
# Arnés de mutación de «EL CLIENTE CAMBIA SUS INVITADOS» (`specs/invitados-en-post-form.md`, `#444`).
#
# Lo que protege son las tres cosas que hacen segura la PRIMERA puerta por la que el cliente mueve
# aforo: que el cupo se revalide **dentro** del lock, que los DOS suelos y el techo sean del dominio
# —y no de la pantalla—, y que el ORDEN del guardado (testigo → cantidad → re-leer → fichas → extras)
# no se pueda alterar sin que algo se ponga rojo.
#
# ⚠️ El aforo bajo CARRERA no se mide aquí: la suite corre en SQLite y no ejerce los locks
# (`SUITE-04`). Eso es `purchase:verify-oversell --scenario=guest-count`, visto FALLAR con 96
# invitados donde caben 30.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='GuestCountTest|GuestCountSurfacesTest|GuestFormTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Services/GuestCountAdjuster.php
    app/Domain/Booking/Services/GuestCountPolicy.php
    app/Http/Controllers/GuestFormController.php
    app/Http/Concerns/AuthorizesGuestForm.php
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

ADJ=app/Domain/Booking/Services/GuestCountAdjuster.php
POL=app/Domain/Booking/Services/GuestCountPolicy.php
WEB=app/Http/Controllers/GuestFormController.php
CON=app/Http/Concerns/AuthorizesGuestForm.php

# ── El AFORO, que es lo que hace peligrosa esta puerta ─────────────────────────────────────────
mutar "el cupo deja de revalidarse al subir" "$ADJ" \
  "        if (\$available < \$newSeats) {" \
  "        if (false) {"

mutar "la revalidación deja de excluir la huella propia (la fiesta compite consigo misma)" "$ADJ" \
  "            (int) \$item->id," \
  "            null,"

mutar "la revalidación olvida los minutos de la hora extra" "$ADJ" \
  "            (int) (\$item->extra_minutes ?? 0)," \
  "            0,"

# ── El TECHO y los DOS suelos ──────────────────────────────────────────────────────────────────
mutar "el techo del producto deja de mirarse" "$ADJ" \
  "        if (\$max !== null && \$desired > \$max) {" \
  "        if (false) {"

mutar "el suelo del pack deja de mirarse" "$ADJ" \
  "        if (\$desired < \$this->policy->contractableFloorFor(\$item)) {" \
  "        if (false) {"

mutar "el suelo de lo que YA TIENE DUEÑO deja de mirarse (plazas negativas en la hoja de sala)" "$ADJ" \
  "        if (\$desired < \$this->policy->assignedFloorFor(\$item)) {" \
  "        if (false) {"

mutar "los dos suelos se FUNDEN en un motivo (el cliente no sabe qué hacer)" "$ADJ" \
  "            return GuestCountChange::blocked(GuestCountChange::REASON_BELOW_ASSIGNED, \$from, \$desired);" \
  "            return GuestCountChange::blocked(GuestCountChange::REASON_BELOW_MIN, \$from, \$desired);"

# ── El PLAZO ───────────────────────────────────────────────────────────────────────────────────
mutar "el plazo deja de aplicarse" "$ADJ" \
  "        if (! \$this->policy->isWithinWindow(\$item)) {" \
  "        if (false) {"

mutar "el plazo se mide contra el fin y no contra el corte" "$POL" \
  "        )->subHours(\$this->cutoffHours());" \
  "        );"

mutar "el ajuste configurado deja de leerse (siempre 24 h)" "$POL" \
  "        return max(0, (int) \$raw);" \
  "        return self::DEFAULT_CUTOFF_HOURS;"

# ── El TESTIGO ─────────────────────────────────────────────────────────────────────────────────
mutar "el testigo optimista deja de comprobarse" "$ADJ" \
  "            if (\$item !== null && \$expectedVersion !== null && \$expectedVersion !== PostFormAddons::versionOf(\$item)) {" \
  "            if (false) {"

# ── El DINERO ──────────────────────────────────────────────────────────────────────────────────
mutar "el cambio no deja su hecho en el libro" "$ADJ" \
  "        if (\$actor !== null && \$fresh !== null && \$change->deltaCents !== 0) {" \
  "        if (false) {"

mutar "una bajada escribe delta POSITIVO" "$ADJ" \
  "                \$change->deltaCents," \
  "                abs(\$change->deltaCents),"

# ── El ORDEN del guardado, que es donde esto se rompe en silencio ──────────────────────────────
mutar "la reserva NO se re-lee: las fichas se recortan contra la cantidad VIEJA" "$WEB" \
  "            \$reservation = \$reservation->fresh(['ticketType', 'slot', 'order', 'children']) ?? \$reservation;" \
  "            // sin re-leer"

mutar "la ausencia de la clave pasa a significar «pon la cantidad actual»" "$CON" \
  "        if (! array_key_exists('guest_count', \$source)) {
            return null;
        }" \
  "        if (! array_key_exists('guest_count', \$source)) {
            return 1;
        }"

mutar "un rechazo se guarda en silencio (el desenlace deja de decirlo)" "$WEB" \
  "        \$status = (\$countChange !== null && ! \$countChange->applied && \$countChange->reason !== GuestCountChange::REASON_NOOP)
            ? 'guest-count-'.\$countChange->reason
            : 'guest-form-saved';" \
  "        \$status = 'guest-form-saved';"

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
