#!/usr/bin/env bash
# Arnés de mutación de la HORA EXTRA DE UN PACK (`specs/hora-extra.md` §10; `DECISIONES #421`→`#424`).
#
# Lo que protege es la propiedad que el hueco de §10.1 rompía: **una hora extra alarga la ventana de
# la fiesta, y el aforo la ve**. Cubre las dos mitades del defecto medido —el cupo de sala ciego a la
# extensión, y la línea hija que decía «1 persona» donde había veinte— más las cuatro defensas que la
# revisión adversarial encontró atravesadas por un extensor (`#423` · A3–A6), que fallan todas por lo
# mismo: el mecanismo estaba escrito sobre `occupiesAfterParent()`.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='PackStayExtensionTest|StayExtensionGuardsTest|OverlappingSlotGridTest|PackAvailabilityTest|SlotAvailabilityTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Booking/Services/PackAvailability.php
    app/Domain/Booking/Services/SlotAvailability.php
    app/Domain/Booking/Services/AddonResolver.php
    app/Domain/Booking/Services/AddonOfferReader.php
    app/Domain/Booking/Services/CartOccupants.php
    app/Domain/Booking/Services/OrderCreator.php
    app/Domain/Booking/Services/OrderItemEditor.php
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

PA=app/Domain/Booking/Services/PackAvailability.php
SA=app/Domain/Booking/Services/SlotAvailability.php
AR=app/Domain/Booking/Services/AddonResolver.php
AO=app/Domain/Booking/Services/AddonOfferReader.php
CO=app/Domain/Booking/Services/CartOccupants.php
OC=app/Domain/Booking/Services/OrderCreator.php
OE=app/Domain/Booking/Services/OrderItemEditor.php
PAD=app/Domain/Booking/Models/ProductAddon.php
TT=app/Domain/Booking/Models/TicketType.php

# ── El hueco de §10.1: el aforo tiene que VER la extensión ────────────────────────────────────
mutar "el cupo de SALA vuelve a ignorar los minutos extra (el hueco medido)" "$PA" \
  "            ->selectRaw('(ticket_types.duration_min + order_items.extra_minutes) as duration_min')" \
  "            ->addSelect('ticket_types.duration_min')"

mutar "el aforo de ASIENTOS vuelve a ignorar los minutos extra" "$SA" \
  "            ->selectRaw('(ticket_types.duration_min + order_items.extra_minutes) as duration_min')" \
  "            ->addSelect('ticket_types.duration_min')"

mutar "la fiesta que se evalúa no cuenta su propia extensión" "$PA" \
  "        return \$pack->duration_min === null ? null : (int) \$pack->duration_min + max(0, \$extraMinutes);" \
  "        return \$pack->duration_min === null ? null : (int) \$pack->duration_min;"

# ── El checkout ───────────────────────────────────────────────────────────────────────────────
mutar "el checkout deja de revalidar el cupo con la ventana alargada" "$OC" \
  "                \$extraMinutes = (int) (\$resolved['extra_minutes'] ?? 0);
                if (\$extraMinutes > 0) {" \
  "                \$extraMinutes = (int) (\$resolved['extra_minutes'] ?? 0);
                if (false) {"

mutar "la venta no guarda los minutos que se compraron" "$OC" \
  "                \$product['extra_minutes'] = \$extraMinutes;" \
  "                \$product['extra_minutes'] = 0;"

# ── El defecto (b): la hija no puede llevarse las plazas ──────────────────────────────────────
mutar "la hija extensora se lleva plazas propias (una persona donde hay veinte)" "$AR" \
  "                'seats' => \$occupies ? AddonOccupancy::seats(\$addon, \$qty) : 0," \
  "                'seats' => (\$occupies || \$extends) ? AddonOccupancy::seats(\$addon, \$qty) : 0,"

mutar "los minutos comprados no se suman" "$AR" \
  "                \$extraMinutes += AddonOccupancy::extraMinutes(\$addon, \$qty);" \
  "                \$extraMinutes += 0;"

# ── La cesta cuenta lo mismo que el cobro ─────────────────────────────────────────────────────
mutar "la cesta provisional se queda en la duración base" "$CO" \
  "        return (int) \$type->duration_min + \$extra;" \
  "        return (int) \$type->duration_min;"

# ── Las defensas que un extensor atravesaba (`#423` · A3–A6) ──────────────────────────────────
mutar "A3 · la oferta deja de mirar si la fiesta cabe alargada" "$AO" \
  "                    && (! \$addon->extendsParentStay()
                        || (\$stayCaps[(int) \$addon->id] ?? 0) > 0)" \
  "                    && true"

mutar "A4 · el modelo de vista pierde la rama simétrica" "$AR" \
  "            if (\$addon->extendsParentStay() && (! \$addon->hasSaneStayExtensionConfig() || ! \$isPack)) {
                continue;
            }" \
  ""

mutar "A5 · el editor deja pasar una extensión sin revalidar aforo" "$OE" \
  "            if (\$child->ticketType?->extendsParentStay() === true) {
                return 'addon_stay_extension_unsupported';
            }" \
  ""

# ── Las guardas de configuración ──────────────────────────────────────────────────────────────
mutar "el pivote deja colgar un extensor de algo que no es un pack" "$PAD" \
  "            \$parent = TicketType::query()->find(\$pivot->product_id);
            if (\$parent !== null && ! \$parent->isPack()) {" \
  "            \$parent = TicketType::query()->find(\$pivot->product_id);
            if (false) {"

mutar "A6 · el extensor deja de necesitar tope propio" "$PAD" \
  "            if (\$pivot->max_qty === null || (int) \$pivot->max_qty < 1) {" \
  "            if (false) {"

mutar "los dos interruptores dejan de ser excluyentes" "$TT" \
  "            if (\$type->occupies_after_parent === true) {
                throw new \\InvalidArgumentException(
                    'Un complemento no puede EXTENDER y OCUPAR a la vez" \
  "            if (false) {
                throw new \\InvalidArgumentException(
                    'Un complemento no puede EXTENDER y OCUPAR a la vez"

echo
echo "mutaciones que muerden: ${muerden}/${total}"
[ "$muerden" -eq "$total" ]
