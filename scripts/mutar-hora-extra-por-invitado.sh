#!/usr/bin/env bash
# Arnés de mutación de la HORA EXTRA COBRADA POR INVITADO (`specs/hora-extra.md` §11, `DECISIONES #443`).
#
# Lo que protege es la propiedad que hace posible la feature y que §10.3.1 daba por imposible:
# **el PRECIO escala con los invitados y los MINUTOS no**. Las dos cosas salían del mismo número
# —la cantidad de la línea hija— y esta tanda las separó en `AddonOccupancy::blocksFor()`.
#
# ⚠️ Cada mutación tiene su pareja en direcciones opuestas donde importa: no basta con romper el
# caso nuevo, hay que comprobar que «arreglarlo» de más rompe el CONTROL (el extensor de cantidad
# fija, que sigue contando bloques).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='StayExtensionPerGuestTest|StayExtensionGuardsTest|PackStayExtensionTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Services/AddonOccupancy.php
    app/Domain/Booking/Services/AddonResolver.php
    app/Domain/Booking/Services/AddonOfferReader.php
    app/Domain/Booking/Models/ProductAddon.php
    app/Domain/Booking/Models/TicketType.php
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

AOC=app/Domain/Booking/Services/AddonOccupancy.php
AR=app/Domain/Booking/Services/AddonResolver.php
AO=app/Domain/Booking/Services/AddonOfferReader.php
PAD=app/Domain/Booking/Models/ProductAddon.php
TT=app/Domain/Booking/Models/TicketType.php

# ── LA REGLA: los minutos salen del BLOQUE, no de la cantidad ─────────────────────────────────
mutar "los minutos vuelven a salir de la CANTIDAD (una fiesta de 15 alargaría 900 min)" "$AOC" \
  "        return \$pivot->isPerGuest() ? 1 : max(0, \$quantity);" \
  "        return max(0, \$quantity);"

mutar "los bloques son SIEMPRE 1 (se pierde el extensor de cantidad fija)" "$AOC" \
  "        return \$pivot->isPerGuest() ? 1 : max(0, \$quantity);" \
  "        return 1;"

mutar "el barrido de la oferta deja de topar los bloques de un por-invitado" "$AOC" \
  "        return \$pivot->isPerGuest() ? 1 : max(1, (int) (\$pivot->max_qty ?? 1));" \
  "        return max(1, (int) (\$pivot->max_qty ?? 1));"

mutar "la oferta vuelve a preguntar por CANTIDAD donde tiene BLOQUES" "$AO" \
  "            \$ceiling = AddonOccupancy::maxBlocks(\$addon->pivot);" \
  "            \$ceiling = max(1, (int) (\$addon->pivot->max_qty ?? 1));"

# ── El CANDADO del modo (§11.11 · A1) ─────────────────────────────────────────────────────────
mutar "cambiar el modo con reservas vivas deja de rechazarse" "$PAD" \
  "            if (\$pivot->hasEditableSoldLines()) {" \
  "            if (false) {"

mutar "el candado se vuelve un CERROJO (bloquea también las fiestas pasadas)" "$PAD" \
  "            if (! \$parent->isFinishedInPractice()) {
                return true;
            }" \
  "            return true;"

mutar "el candado ignora las líneas canceladas" "$PAD" \
  "        \$parents = OrderItem::query()
            ->whereNull('cancelled_at')" \
  "        \$parents = OrderItem::query()"

# ── Lo que se RELAJÓ, relajado de más ─────────────────────────────────────────────────────────
mutar "un extensor INCLUIDO deja de rechazarse (toda fiesta nacería alargada)" "$PAD" \
  "            if (\$pivot->is_mandatory || \$pivot->is_included) {" \
  "            if (\$pivot->is_mandatory) {"

mutar "un extensor OBLIGATORIO deja de rechazarse" "$PAD" \
  "            if (\$pivot->is_mandatory || \$pivot->is_included) {" \
  "            if (\$pivot->is_included) {"

mutar "el cinturón del COBRO deja pasar un extensor incluido" "$AR" \
  "                if (\$pivot->is_mandatory || \$pivot->is_included) {
                    throw new ReservationException('tickets.errors.unavailable');
                }" \
  ""

mutar "el interruptor se enciende sobre un enganche INCLUIDO" "$TT" \
  "                        ->where('is_mandatory', true)
                        ->orWhere('is_included', true)
                        ->orWhere('stage', ProductAddon::STAGE_POSTFORM))" \
  "                        ->where('stage', ProductAddon::STAGE_POSTFORM))"

# ── Lo que ve el cliente ──────────────────────────────────────────────────────────────────────
mutar "la nota de la oferta pierde la rama del por-invitado (promete 4,00 € sobre 60,00 €)" "$AR" \
  "                \$note = (\$qty > 0 && \$addon->extendsParentStay())
                    ? trans_choice('tickets.addon_stay_per_guest_selected', \$qty, ['count' => \$qty, 'price' => \$priceStr])
                    : __('tickets.addon_per_unit', ['price' => \$priceStr]);" \
  "                \$note = __('tickets.addon_per_unit', ['price' => \$priceStr]);"

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
