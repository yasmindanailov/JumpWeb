#!/usr/bin/env bash
# Arnés de mutación de la VÍA LIGERA de la oferta (`DECISIONES #465`): `SlotOffer` deja de cargar el
# horizonte entero para contestar por un día, y las FECHAS dejan de hidratar 1.947 modelos.
#
# Lo que protege NO es el rendimiento: es que las dos vías sigan contestando lo mismo. Una que
# ofrezca un día cuyas horas la otra rechaza es un calendario que miente, y eso no lo ve ninguna
# prueba que mire una sola vía. Y protege el techo de venta, que al acotar por día deja de ponerlo la
# consulta y hay que volver a ponerlo a mano (`clampToHorizon`).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='SlotOfferPathParityTest|SlotOfferTest|AvailabilityReaderTest|Api\\V1\\AvailabilityTest|ExtraHourAddonTest|CreateManualOrderCalendarTest|CreateManualOrderCartAvailabilityTest|ManualOrderIgnoresMinAdvanceTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
F=app/Domain/Booking/Services/SlotOffer.php
trap 'cp "$TMP/base.php" "$F"; touch "$F"; rm -rf "$TMP"' EXIT
cp "$F" "$TMP/base.php"

verde() { $RUN >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" buscar="$2" poner="$3"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$F" "$buscar" "$poner"
    if cmp -s "$F" "$TMP/base.php"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$F"
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$TMP/base.php" "$F"; touch "$F"
}

# ── El techo de venta, que acotar por día se lleva ────────────────────────────────────────────
mutar "el horizonte deja de acotar el dia suelto (se vende a dos anos vista)" \
  '        return ($date >= $from && $date <= $to) ? $date : null;' \
  '        return $date;'

# ── La vía LIGERA tiene que aplicar el MISMO filtro ───────────────────────────────────────────
mutar "la via ligera no filtra: enciende todo dia con franja" \
  '            if ($this->passesOffer($type, $fecha, $dia, (string) $fila->start_time, $now, $sale)) {' \
  '            if (true) {'

mutar "la via ligera reutiliza el dia del principio (ventana equivocada)" \
  '            if ($dia !== $ymd) {
                $ymd = $dia;
                $fecha = Carbon::parse($dia);
            }' \
  '            $fecha ??= Carbon::parse($dia);'

# ── El predicado, que ahora comparten las dos vías ────────────────────────────────────────────
mutar "el predicado pierde el corte intra-dia" \
  '        if (! self::passesIntradayFloor($ymd, $startTime, $now)) {
            return false;
        }' \
  '        if (false) {
            return false;
        }'

mutar "el predicado pierde la ventana viva del dia" \
  '        return $this->productWindow->allowsStart($type, $date, $startTime)' \
  '        return true || $this->productWindow->allowsStart($type, $date, $startTime)'

mutar "la antelacion minima vuelve a atar al mostrador" \
  '            && ($sale->ignoresMinAdvance() || $type->meetsMinAdvance($ymd, $startTime, $now));' \
  '            && $type->meetsMinAdvance($ymd, $startTime, $now);'

# ── La consulta compartida ────────────────────────────────────────────────────────────────────
mutar "la consulta deja de mirar si la franja es vendible online" \
  '            ->sellableOnline()' \
  '            ->when(false, fn ($q) => $q)'

mutar "la consulta deja de filtrar por la zona del producto" \
  '            ->when($type && $type->zone_id, fn ($q) => $q->where('"'"'zone_id'"'"', $type->zone_id))' \
  '            ->when(false, fn ($q) => $q)'

echo
echo "── Veredicto: ${muerden}/${total} mutaciones muerden ──"
[ "$muerden" -eq "$total" ]
