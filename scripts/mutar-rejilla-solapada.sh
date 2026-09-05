#!/usr/bin/env bash
# Arnés de mutación de `OverlappingSlotGridTest` (`DECISIONES #420`): la rejilla de franjas puede
# SOLAPARSE (inicios cada 30 min con franjas de 60) y eso no puede sobrevender.
#
# Lo que protege es una sola propiedad, en los tres sitios que la sostienen: **el aforo cuenta
# PRESENCIA, no huecos de un contenedor**. Cada ocupante marca todas las franjas cuyo inicio cae
# dentro de su tramo, así que dos ventas que se pisan comparten al menos un punto de la rejilla y se
# ven — aunque no compartan hora de entrada. Antes del 2026-09-05 ningún caso lo ejercitaba: todas
# las plantillas empezaban en punto y la rejilla era una partición del día.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='OverlappingSlotGridTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Services/SlotAvailability.php
    app/Domain/Booking/Services/PackAvailability.php
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

S=app/Domain/Booking/Services/SlotAvailability.php
P=app/Domain/Booking/Services/PackAvailability.php

# ── ENTRADAS · la ocupación es el TRAMO, no la franja de entrada ──────────────────────────────
mutar "una entrada ocupa SOLO su franja de entrada (el modelo de contenedor)" "$S" \
  "                if (\$start < \$occupant['entry_start']) {
                    continue;
                }
                if (\$end !== null && \$start >= \$end) {
                    continue;
                }" \
  "                if (\$start !== \$occupant['entry_start']) {
                    continue;
                }"

mutar "el fin del tramo pasa a INCLUSIVO (bloquea la franja en la que ya ha salido)" "$S" \
  "                if (\$end !== null && \$start >= \$end) {" \
  "                if (\$end !== null && \$start > \$end) {"

mutar "el tramo empieza antes de la entrada (ocupa franjas anteriores)" "$S" \
  "                if (\$start < \$occupant['entry_start']) {
                    continue;
                }
" \
  ""

mutar "una fiesta ocupa cupo hacia ATRÁS (antes de empezar)" "$P" \
  "                if (\$start < \$windowStart || \$start >= \$windowEnd) {" \
  "                if (\$start >= \$windowEnd) {"

# ── ENTRADAS · la cobertura tolera el solape ──────────────────────────────────────────────────
mutar "la cobertura exige contigüidad ESTRICTA (una rejilla solapada dejaría de cubrir)" "$S" \
  "            if ((string) \$slot->start_time > \$covered) {
                return false; // hueco" \
  "            if ((string) \$slot->start_time !== \$covered) {
                return false; // hueco"

# ── PACKS · el cupo se mide en todo el tramo ──────────────────────────────────────────────────
mutar "una fiesta ocupa cupo SOLO en su franja de entrada" "$P" \
  "                if (\$start < \$windowStart || \$start >= \$windowEnd) {
                    continue;
                }" \
  "                if (\$start !== \$occupant['start']) {
                    continue;
                }"

mutar "la ventana de la fiesta pasa a INCLUSIVA por el fin" "$P" \
  "                if (\$start < \$windowStart || \$start >= \$windowEnd) {" \
  "                if (\$start < \$windowStart || \$start > \$windowEnd) {"

mutar "el tope de FIESTAS deja entrar una de más" "$P" \
  "            if (\$maxParties > 0 && (\$parties[\$spannedSlot->start_time] ?? 0) >= \$maxParties) {
                return 0;
            }
            // Tope de NIÑOS" \
  "            if (\$maxParties > 0 && (\$parties[\$spannedSlot->start_time] ?? 0) > \$maxParties) {
                return 0;
            }
            // Tope de NIÑOS"

echo
echo "mutaciones que muerden: ${muerden}/${total}"
[ "$muerden" -eq "$total" ]
