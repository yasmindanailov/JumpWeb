#!/usr/bin/env bash
# Arnés de mutación de la T2 de `docs/specs/complementos-post-reserva.md` (`DECISIONES #413`):
# el dominio de la venta posterior — las tres escrituras, R0/R1/R2/R4, el token y el plazo.
#
# Lo que protege es lo que más caro cuesta: **que el LIBRO siga cerrando después de cada gesto**. Las
# dos formas de romperlo son simétricas y ninguna falla al escribir — el pedido pasa a «en revisión»
# y el cliente se queda sin su desglose (`#132`) por haber tocado un cubo de refrescos.
#
# ⚠️ El orden de LOCKS no se muta aquí: SQLite no reproduce interbloqueos (`SUITE-04`). Su rojo vive
# en `postform:verify-concurrency --scenario=cross --control`, y está medido: 6 de 12 procesos.
#
# Reglas de la casa construidas dentro: verde antes de mutar · veredicto por código de salida · y
# comprobar que la mutación SE APLICÓ. Restaura con `git checkout`: el árbol tiene que estar
# commiteado (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='PostFormAddonsTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

S=app/Domain/Booking/Services/PostFormAddons.php

verde() { $RUN >/dev/null 2>&1; }

if ! git diff --quiet -- "$S"; then
    echo "✗ hay cambios sin commitear en $S: commitea antes (#181)." >&2
    exit 1
fi
if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo "✓ base verde con el filtro ${FILTER}"

muerden=0; total=0

mutar() {
    local nombre="$1" buscar="$2" poner="$3"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$S" "$buscar" "$poner" \
        || { echo "  ⚠ la mutación «$nombre» no se pudo aplicar"; git checkout -- "$S"; return; }
    if git diff --quiet -- "$S"; then
        echo "  ⚠ la mutación «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    git checkout -- "$S"
    touch "$S"
}

# ── Las TRES escrituras: las dos formas de romper el libro ────────────────────────────────────
mutar "RETIRAR escribe además su hecho (el valor se resta DOS veces → I1/I3 fallan)" \
  '        if ($target === 0) {
            $current?->markCancelled($actor);

            return 0;
        }' \
  '        if ($target === 0) {
            if ($current !== null) {
                $this->recordMove($order, $current, -$current->chargedSubtotalCents(), $addon, $from, 0, $actor);
                $current->markCancelled($actor);
            }

            return 0;
        }'

mutar "BAJAR deja de escribir su hecho (el valor de nacimiento se desplaza)" \
  '        $this->recordMove($order, $current, $newCharged - $oldCharged, $addon, $from, $target, $actor);' \
  '        if ($newCharged > $oldCharged) {
            $this->recordMove($order, $current, $newCharged - $oldCharged, $addon, $from, $target, $actor);
        }'

mutar "el ALTA deja de escribir su hecho (la línea nacería cobrada online)" \
  '            $this->recordMove($order, $child, $newCharged, $addon, 0, $target, $actor);' \
  ''

# ── R0 · la puerta es el HECHO, no el eje ─────────────────────────────────────────────────────
mutar "la puerta vuelve a ser el EJE (el cliente retira líneas ya cobradas)" \
  '            if (LineFacts::forItem($order, $child)->birthValue() === 0) {' \
  '            if (true) {'

# ── R1 · el plazo ─────────────────────────────────────────────────────────────────────────────
mutar "el plazo deja de filtrar la oferta (se añade fuera de plazo)" \
  '            ->filter(fn (TicketType $addon): bool => self::isWithinWindow($principal, $addon->pivot))' \
  ''

mutar "el plazo se mide con el reloj TORCIDO (UTC en vez de la hora del parque)" \
  '        $start = Carbon::parse(
            $slot->date->format('"'"'Y-m-d'"'"').'"'"' '"'"'.$slot->start_time,
            DisplayTime::timezone(),
        );

        return DisplayTime::now()->lt($start->subHours($hours));' \
  '        $start = Carbon::parse($slot->date->format('"'"'Y-m-d'"'"').'"'"' '"'"'.$slot->start_time);

        return Carbon::now()->lt($start->subHours($hours));'

# ── R2 · la línea conserva su precio ──────────────────────────────────────────────────────────
mutar "una subida re-tarifica al precio de HOY (cambia lo comunicado al cliente)" \
  '        $unit = $current !== null
            ? (int) $current->unit_price
            : $this->rates->priceCents($addon, Carbon::today());' \
  '        $unit = $this->rates->priceCents($addon, Carbon::today());'

# ── R4 · idempotencia ─────────────────────────────────────────────────────────────────────────
mutar "un guardado idéntico vuelve a escribir" \
  '            if ($target === $from) {
                continue; // R4: idempotente.
            }' ''

# ── El token optimista ────────────────────────────────────────────────────────────────────────
mutar "el token deja de comprobarse (el cliente pisa al operador)" \
  '            if ($expectedVersion !== null && $expectedVersion !== self::versionOf($item)) {' \
  '            if (false) {'

# ── La re-validación bajo el lock (`SEC-04` aplicado al tiempo) ───────────────────────────────
mutar "se deja de re-comprobar que la fiesta no haya pasado" \
  '            if ($item === null || ! $item->acceptsGuestForm() || $item->isFinishedInPractice()) {' \
  '            if ($item === null) {'

# ── La atribución y el tope ───────────────────────────────────────────────────────────────────
mutar "el hecho se ata al PRINCIPAL en vez de a la hija" \
  '        $order->recordEdit($child, $delta, $actor, '"'"'postform_addon'"'"', [' \
  '        $order->recordEdit($child->parent ?? $child, $delta, $actor, '"'"'postform_addon'"'"', ['

mutar "el tope del enganche deja de aplicarse (99 cubos de refrescos)" \
  '            $target = AddonResolver::effectiveQuantity($pivot, max(0, (int) $rawQty), (int) $item->quantity);' \
  '            $target = max(0, (int) $rawQty);'

# ── El rótulo del libro ───────────────────────────────────────────────────────────────────────
# ⚠️ Sin BACKTICKS en el rótulo: dentro de comillas dobles bash los ejecuta como sustitución de
# comando y el nombre de la mutación sale vacío — el veredicto seguiría siendo bueno, pero el
# informe mentiría sobre QUÉ se mutó, que es justo lo que un arnés no puede hacer.
mutar "el contexto vuelve a ser addon_change (el libro no sabe nombrar la bajada)" \
  "            'changes' => ['quantity_change' => ['old' => \$from, 'new' => \$to]]," \
  "            'addon_change' => ['added' => [], 'removed' => [], 'updated' => []],"

echo
echo "── Veredicto: ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ]
