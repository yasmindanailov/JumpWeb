#!/usr/bin/env bash
# Arnés de mutación del RESUMEN IMPRIMIBLE con periodo (la L5 de `#876`, `#879`): `DailySummaryTest` contra el presenter
# (cumpleaños y excursiones por separado, la semana de lunes a domingo y el mes natural, la merienda y la tarta por su
# enganche, «Sin tarta», el resto de complementos sin repetir), la acción del botón (el periodo en la URL, «Solo
# excursiones» en el selector) y el controlador (el periodo, y en su rastro).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y
# que su ancla está UNA vez · restaurar por COPIA DE SEGURIDAD (por RUTA) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=DailySummaryTest"

RESUMEN=app/Domain/Booking/Services/DailyReservationsSummary.php
ACCION=app/Filament/Concerns/PrintsDaySummary.php
CONTROLADOR=app/Http/Controllers/Admin/DailySummaryController.php
FICHEROS=("$RESUMEN" "$ACCION" "$CONTROLADOR")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

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
    if [[ "$(python3 -c 'import sys; print(open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$fichero" "$buscar")" != 1 ]]; then
        echo "  ⚠ «$nombre» NO APLICA: el ancla no está UNA vez en $fichero"
        return
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
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
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

mutar "«Solo cumpleaños» vuelve a meter las excursiones" "$RESUMEN" \
  "self::TYPE_PACK => self::isBirthday(\$i)," \
  "self::TYPE_PACK => true,"

mutar "«Solo excursiones» mete también los cumpleaños" "$RESUMEN" \
  "self::TYPE_TRIP => ! self::isBirthday(\$i)," \
  "self::TYPE_TRIP => true,"

mutar "la semana empieza en domingo" "$RESUMEN" \
  "startOfWeek(Carbon::MONDAY)" \
  "startOfWeek(Carbon::SUNDAY)"

mutar "el mes pierde su último día" "$RESUMEN" \
  "\$date->copy()->endOfMonth()->startOfDay()" \
  "\$date->copy()->endOfMonth()->subDay()->startOfDay()"

mutar "la merienda no reconoce el grupo de elección (el menú)" "$RESUMEN" \
  "(\$pivot->choiceGroup() !== null || \$pivot->showsInInvitation())" \
  "\$pivot->showsInInvitation()"

mutar "la tarta no reconoce su bloque" "$RESUMEN" \
  "=== ProductAddon::BLOCK_CAKE;" \
  "=== 'nada';"

mutar "«Sin tarta» no se dice" "$RESUMEN" \
  "? __('admin.calendar.day_summary.cake_none') : null" \
  "? null : null"

mutar "el resto de complementos repite la merienda y la tarta" "$RESUMEN" \
  "->reject(fn (OrderItem \$child): bool => \$merienda->contains(\$child) || self::isCake(\$item, \$child))" \
  "->reject(fn (OrderItem \$child): bool => false)"

mutar "el botón no lleva el periodo a la URL" "$ACCION" \
  "                    'period' => (string) (\$data['period'] ?? DailyReservationsSummary::PERIOD_DAY)," \
  ""

mutar "el selector pierde «Solo excursiones»" "$ACCION" \
  "                        DailyReservationsSummary::TYPE_TRIP => __('admin.calendar.day_summary.type_trips')," \
  ""

mutar "el controlador ignora el periodo" "$CONTROLADOR" \
  "DailyReservationsSummary::for(\$date, \$type, \$period)" \
  "DailyReservationsSummary::for(\$date, \$type)"

mutar "el rastro no dice el periodo" "$CONTROLADOR" \
  "            'period' => \$summary->period," \
  ""

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
