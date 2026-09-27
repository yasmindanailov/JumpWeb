#!/usr/bin/env bash
# Arnés de mutación de «LA ANALÍTICA PARA DECIDIR» (`specs/analitica-para-decidir.md`, `#755`). Crece por tanda.
#
# T0a · EL TRAMO TRANSCURRIDO (§4.3): un periodo EN CURSO termina AHORA (con el segundo en curso dentro) y se compara
# con el MISMO tramo del periodo con el que se compara —el 1–27 de septiembre hasta las 15:40 contra el 1–27 de agosto
# hasta las 15:40—; día y semana, contra el mismo día de la SEMANA (−7 y −364 días); el corte desplazado no pasa del
# fin del periodo (el 31 de marzo contra febrero entero). Medido el 27-09: «Este mes» llegaba al 30 y se comparaba con
# 30 días enteros, y cada Δ del mes en curso salía sesgado a la baja.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE
# APLICÓ · un CONTROL que no debe morder · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

FILTER='ReportPeriodTest|MoneyReportTest|WindowLabelTest|AnalyticsPageTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Platform/Services/Analytics/Reports/Window.php
    app/Domain/Platform/Enums/ReportPeriod.php
    app/Filament/Analytics/WindowLabel.php
    app/Filament/Pages/AnalyticsPage.php
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

muerden=0; total=0; control_ok=1

aplicar() {
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); n=s.count(sys.argv[2]); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1)); sys.exit(0 if n == 1 else 3)' \
        "$1" "$2" "$3"
}

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    aplicar "$fichero" "$buscar" "$poner"; local unico=$?
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    if [[ $unico -ne 0 ]]; then
        echo "  ⚠ «$nombre»: el ancla NO es única; se mutó la primera y el veredicto es dudoso"
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

control() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    aplicar "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ CONTROL «$nombre» NO SE APLICÓ"; control_ok=0; return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✓ control:   $nombre (sigue verde, como debe)"
    else
        echo "  ✗ CONTROL ROJO: $nombre — la suite se pone roja por algo que no es la regla"; control_ok=0
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

W=app/Domain/Platform/Services/Analytics/Reports/Window.php
P=app/Domain/Platform/Enums/ReportPeriod.php

# ── El corte: un periodo en curso termina AHORA ────────────────────────────────────────────────
mutar "el periodo en curso deja de cortarse ahora (vuelve al mes entero)" "$P" \
  'return $this->wholeWindow($from, $to)->upTo(CarbonImmutable::now());' \
  'return $this->wholeWindow($from, $to);'

mutar "el corte deja fuera lo escrito en el segundo en curso" "$W" \
  '->startOfSecond()->addSecond();' \
  '->startOfSecond();'

mutar "upTo alarga una ventana cerrada hasta ahora" "$W" \
  'if ($at <= $this->from || $at >= $this->to) {' \
  'if ($at <= $this->from) {'

mutar "el último día vuelve a ser «el fin menos un día» (con el corte, ayer)" "$W" \
  'return $this->lastInstant()->toDateString();' \
  'return $this->to->subDay()->toDateString();'

mutar "los días dejan de contar el de hoy a medias" "$W" \
  'diffInDays(self::civil($this->lastInstant())) + 1;' \
  'diffInDays(self::civil($this->to));'

# ── La comparación: el MISMO tramo, desplazado por su unidad ───────────────────────────────────
mutar "el mes se compara con los días pegados por delante (lo de antes del 27-09)" "$W" \
  'self::UNIT_MONTH => $this->shifted(static fn (CarbonImmutable $at): CarbonImmutable => $at->subMonthsNoOverflow(1)),' \
  'self::UNIT_MONTH => $this->shifted(fn (CarbonImmutable $at): CarbonImmutable => $at->subDays($this->periodDays())),'

mutar "el trimestre se desplaza un mes" "$W" \
  '$at->subMonthsNoOverflow(3)' \
  '$at->subMonthsNoOverflow(1)'

mutar "día y semana, contra el día de antes y no el mismo día de la semana" "$W" \
  '$at->subDays(7)' \
  '$at->subDays(1)'

mutar "hace un año, por calendario también para día y semana (el sábado deja de ser sábado)" "$W" \
  '$at->subDays(364)' \
  '$at->subYearsNoOverflow(1)'

mutar "30 días y a medida se desplazan un día y no su longitud" "$W" \
  'default => $this->shifted(fn (CarbonImmutable $at): CarbonImmutable => $at->subDays($this->periodDays())),' \
  'default => $this->shifted(fn (CarbonImmutable $at): CarbonImmutable => $at->subDays(1)),'

mutar "la comparación ignora el corte y toma el periodo anterior ENTERO" "$W" \
  '$this->isInProgress() ? $shift($this->to) : $end' \
  '$end'

# El freno «el corte no pasa del fin» se midió como código muerto (27-09: sobrevivía): los desplazamientos son
# monótonos. Lo que de verdad sostiene «el 31 de marzo contra febrero entero» es el NoOverflow.
mutar "el mes se desplaza DESBORDANDO (el 31 de marzo cae al 3 de marzo)" "$W" \
  'self::UNIT_MONTH => $this->shifted(static fn (CarbonImmutable $at): CarbonImmutable => $at->subMonthsNoOverflow(1)),' \
  'self::UNIT_MONTH => $this->shifted(static fn (CarbonImmutable $at): CarbonImmutable => $at->subMonths(1)),'

mutar "el mes se desplaza como un tramo de días" "$P" \
  'self::ThisMonth, self::LastMonth => Window::UNIT_MONTH,' \
  'self::ThisMonth, self::LastMonth => Window::UNIT_SPAN,'

mutar "una unidad desconocida se acepta" "$W" \
  'if (! in_array($unit, self::UNITS, true)) {' \
  'if (false) {'

# ── El rótulo: las fechas exactas bajo el filtro ────────────────────────────────────────────────
L=app/Filament/Analytics/WindowLabel.php
A=app/Filament/Pages/AnalyticsPage.php

mutar "el periodo en curso deja de decir «hasta ahora»" "$L" \
  "? __('admin.analytics.window.so_far', ['range' => self::range(\$window)])" \
  '? self::range($window)'

mutar "la línea de «Comparar con» rotula el periodo y no la comparación" "$A" \
  "WindowLabel::baseline(Comparison::fromValue(\$get('compare'))->baseline(self::windowOf(\$get)))" \
  'WindowLabel::baseline(self::windowOf($get))'

mutar "«desde» pierde la compactación y repite mes y año" "$L" \
  "\$from->isSameMonth(\$to) => 'date_same_month'," \
  "\$from->isSameMonth(\$to) => 'date_full',"

# ── El CONTROL: tocar un comentario no puede poner nada en rojo ─────────────────────────────────
control "un comentario de Window" "$W" \
  'Un día' \
  'Un dia'

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
