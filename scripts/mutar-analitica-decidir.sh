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

FILTER='ReportPeriodTest|MoneyReportTest|WindowLabelTest|AnalyticsPageTest|MetricTest|AnalyticsCensusTest|FunnelReportTest|PartiesReportTest|CustomersReportTest|GateSurveyTest|ValidarRegistroProfileTest|GateVisitsTest'
RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=${FILTER}"

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Platform/Services/Analytics/Reports/Window.php
    app/Domain/Platform/Enums/ReportPeriod.php
    app/Filament/Analytics/WindowLabel.php
    app/Filament/Pages/AnalyticsPage.php
    app/Filament/Analytics/Metric.php
    app/Filament/Widgets/Analytics/Concerns/AnalyticsWidget.php
    app/Filament/Analytics/CsvExport.php
    app/Filament/Analytics/MoneyReport.php
    app/Filament/Widgets/Analytics/MoneyCustomersWidget.php
    lang/zh_CN/admin.php
    app/Filament/Analytics/FunnelReport.php
    app/Filament/Analytics/PartiesReport.php
    app/Filament/Analytics/CustomersReport.php
    app/Livewire/Admin/Puerta/ValidarRegistro.php
    app/Domain/Identity/Services/GateVisits.php
    app/Filament/Widgets/Analytics/GateWidget.php
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

# ── T0b · LA ANATOMÍA DE UNA CIFRA (§4.2): base, puntos, cambio claro, polaridad, censo ─────────
M=app/Filament/Analytics/Metric.php
AW=app/Filament/Widgets/Analytics/Concerns/AnalyticsWidget.php

mutar "sin base, el porcentaje vuelve («+900 %»)" "$M" \
  'if ($this->previousBase === null || $this->previousBase < self::MIN_BASE) {' \
  'if ($this->previousBase === null) {'

mutar "todo cambio de un recuento es claro (el rojo y el verde sobre ruido)" "$M" \
  'return abs($z) >= self::Z;' \
  'return true;'

mutar "la prueba del recuento ignora la duración de cada ventana" "$M" \
  '$z = ($current - $n * $share) / sqrt($n * $share * (1 - $share));' \
  '$z = ($current - $n * 0.5) / sqrt($n * 0.25);'

mutar "el widget no pasa la duración de las ventanas (siempre mitad y mitad)" "$AW" \
  'return $now + $before > 0 ? $now / ($now + $before) : 0.5;' \
  'return 0.5;'

mutar "dos tasas siempre se distinguen (sin mirar sus intervalos)" "$M" \
  "return \$now['low_bp'] > \$before['high_bp'] || \$now['high_bp'] < \$before['low_bp'];" \
  'return true;'

mutar "una tasa vuelve a compararse en % relativo y no en puntos" "$M" \
  'if ($this->unit === self::UNIT_RATE) {' \
  'if (false) {'

mutar "la polaridad se ignora (subir es siempre verde)" "$M" \
  "\$up === (\$this->polarity === Polarity::UpIsGood) => 'success'," \
  "\$up => 'success',"

mutar "una cifra neutra se colorea" "$M" \
  "! \$clear, \$this->polarity === Polarity::Neutral => 'gray'," \
  "! \$clear => 'gray',"

mutar "un cero antes se lee como crecimiento («+15 frente al año pasado»)" "$M" \
  'if ($this->previous === 0) {' \
  'if (false) {'

mutar "el dinero de la tarjeta vuelve a los céntimos desde 100 €" "$M" \
  'if (abs($cents) < self::WHOLE_EUROS_FROM) {' \
  'if (true) {'

mutar "una tasa pierde su intervalo" "$M" \
  'if ($this->unit === self::UNIT_RATE && $this->base !== null && $this->base > 0 && $this->hits !== null) {' \
  'if (false) {'

mutar "una tasa de más del 100 % revienta el panel" "$M" \
  'if ($hits < 0 || $of < 0) {' \
  'if ($hits < 0 || $of < 0 || $hits > $of) {'

mutar "la base de «Devuelto» (las devoluciones) deja de contarse" "app/Filament/Analytics/MoneyReport.php" \
  "'refunds' => self::sumOf(\$refunded, 'count')," \
  "'refunds' => 0,"

mutar "una cifra sale del CSV (el censo: nada de lo medido se quita)" "app/Filament/Analytics/CsvExport.php" \
  "            [__('admin.analytics.money.adjustments'), Money::format(\$t['adjustments'])]," \
  ''

mutar "una tarjeta vuelve a ser un Stat suelto, sin anatomía" "app/Filament/Widgets/Analytics/MoneyCustomersWidget.php" \
  "\$this->metric(Metric::money('money.adjustments', __('admin.analytics.money.adjustments'), \$t['adjustments'], null, 0, null, Polarity::Neutral, self::how('money.adjustments')))," \
  "\\Filament\\Widgets\\StatsOverviewWidget\\Stat::make(__('admin.analytics.money.adjustments'), '0'),"

# El dinero, con la suma de los CUADRADOS de sus importes (27-09: «Valor medio −1 %» salía en rojo sin prueba).
mutar "el dinero se colorea sin prueba (con base, siempre claro)" "$M" \
  'self::UNIT_MONEY => $this->moneyDiffers($share),' \
  'self::UNIT_MONEY => true,'

mutar "una media de importes se prueba como una suma" "$M" \
  '? self::meansDiffer($this->value, $this->previous, (int) $this->base, (int) $this->previousBase, $this->squares, $this->previousSquares)' \
  '? self::sumsDiffer($this->value, $this->previous, $this->squares, $this->previousSquares, $share)'

mutar "la suma de dinero ignora la duración de las ventanas" "$M" \
  '$k = $share / (1 - $share);' \
  '$k = 1.0;'

mutar "la varianza de la suma olvida el periodo comparado" "$M" \
  '$variance = $squares + $k * $k * $previousSquares;' \
  '$variance = $squares;'

mutar "la varianza de la media no resta la media" "$M" \
  '$variance = max(0.0, $squares / $n - $mean * $mean);' \
  '$variance = $squares / $n;'

mutar "el informe del dinero pierde la suma de cuadrados de los cobros" "app/Filament/Analytics/MoneyReport.php" \
  "\$totals['collected_sq'] = self::sumOf(\$collected, 'squares');" \
  "\$totals['collected_sq'] = 0;"

mutar "la conversión pierde la suma de cuadrados de lo cobrado" "app/Filament/Analytics/FunnelReport.php" \
  "->selectRaw('COALESCE(SUM(p.amount), 0) AS amount, COUNT(*) AS n, COALESCE(SUM(p.amount * p.amount), 0) AS sq')" \
  "->selectRaw('COALESCE(SUM(p.amount), 0) AS amount, COUNT(*) AS n, 0 AS sq')"

mutar "las fiestas pierden la suma de cuadrados de lo vendido después" "app/Filament/Analytics/PartiesReport.php" \
  "\$out['sold_after_sq'] += \$cents * \$cents;" \
  "\$out['sold_after_sq'] += 0;"

mutar "los widgets del cuadro vuelven a sondear cada 5 s" "$AW" \
  "    protected function getPollingInterval(): ?string
    {
        return null;
    }" \
  "    protected function getPollingInterval(): ?string
    {
        return '5s';
    }"

mutar "una cifra pierde su «¿Cómo se calcula?» en chino" "lang/zh_CN/admin.php" \
  "                'scale_mean' => '本时段回答中第一个评分题（1 到 5）的平均分。'," \
  ''

# ── T0c · LOS QUE VUELVEN (§4.8.bis, #756) ────────────────────────────────────────────────────
CR=app/Filament/Analytics/CustomersReport.php
VR=app/Livewire/Admin/Puerta/ValidarRegistro.php

mutar "la búsqueda por correo o móvil deja de acreditar la visita (vuelve #741)" "$VR" \
  'app(GateVisits::class)->register($user, Auth::user(), DisplayTime::today(), CustomerVisit::SOURCE_LOOKUP);' \
  '// sin acreditar'

mutar "la visita deja de guardar su origen" "app/Domain/Identity/Services/GateVisits.php" \
  "'source' => \$source," \
  "'source' => null,"

mutar "el escaneo se apunta como búsqueda" "$VR" \
  'DisplayTime::today(), CustomerVisit::SOURCE_CARD);' \
  'DisplayTime::today(), CustomerVisit::SOURCE_LOOKUP);'

mutar "el periodo se filtra ANTES del LAG (la primera visita del periodo no ve la de antes)" "$CR" \
  "->where('visited_on', '<=', \$window->dateTo());" \
  "->whereBetween('visited_on', [\$window->dateFrom(), \$window->dateTo()]);"

mutar "la mediana toma la de arriba de las dos centrales" "$CR" \
  'return $n === 0 ? null : $sorted[intdiv($n - 1, 2)];' \
  'return $n === 0 ? null : $sorted[intdiv($n, 2)];'

mutar "«en una semana» deja fuera el día 7" "$CR" \
  'static fn (int $d): bool => $d <= 7)' \
  'static fn (int $d): bool => $d < 7)'

mutar "«en tres meses» se come el día 91" "$CR" \
  'static fn (int $d): bool => $d > 30 && $d <= 90)' \
  'static fn (int $d): bool => $d > 30 && $d <= 91)'

mutar "el periodo comparado olvida a los que vuelven" "$CR" \
  "'first_time' => \$returns['first_time']," \
  "'first_time' => 0,"

mutar "«repiten por la web» cuenta también a los nuevos" "app/Filament/Analytics/MoneyReport.php" \
  "->filter(static fn (object \$row): bool => (int) \$row->web_now === 1)->reject(\$isNew)->count()," \
  "->filter(static fn (object \$row): bool => (int) \$row->web_now === 1)->count(),"

mutar "«la web» deja fuera la app" "app/Filament/Analytics/MoneyReport.php" \
  '[$from, $to, $from, $to, AttributionContext::CHANNEL_WEB, AttributionContext::CHANNEL_APP],' \
  '[$from, $to, $from, $to, AttributionContext::CHANNEL_WEB, AttributionContext::CHANNEL_WEB],'

mutar "«Visitas acreditadas» cambia el carné por la búsqueda" "app/Filament/Widgets/Analytics/GateWidget.php" \
  "['card' => \$card, 'lookup' => \$lookup]" \
  "['card' => \$lookup, 'lookup' => \$card]"

mutar "las visitas de antes, sin origen, dejan de decirse" "app/Filament/Widgets/Analytics/GateWidget.php" \
  'return $other > 0 ?' \
  'return $other > 999 ?'

mutar "los compradores se comparan consigo mismos y no con el periodo anterior" "app/Filament/Analytics/MoneyReport.php" \
  "'previous' => \$this->buyerCounts(\$baseline, \$this->buyerRows(\$baseline))," \
  "'previous' => \$this->buyerCounts(\$window, \$this->buyerRows(\$window)),"

# ── El CONTROL: tocar un comentario no puede poner nada en rojo ─────────────────────────────────
control "un comentario de Window" "$W" \
  'Un día' \
  'Un dia'

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
