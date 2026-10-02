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

FILTER='ReportPeriodTest|MoneyReportTest|WindowLabelTest|AnalyticsPageTest|MetricTest|AnalyticsCensusTest|FunnelReportTest|PartiesReportTest|CustomersReportTest|GateSurveyTest|ValidarRegistroProfileTest|GateVisitsTest|OccupancyReaderParityTest|OccupancyReportTest|AnalyticsTabsTest|AnalyticsJargonTest|MetricsCatalogTest|AnalyticsExportTest|SegmentsReportTest|ExplainerTest|MetricsHistoryTest|ChangesTest|GoalTest|AnalyticsGoalsTest|HolderBirthDateTest|HolderBirthDatePanelTest|AnonymizeCoversEveryUserColumnTest|MeTest|SidebarTranslationKeysExistTest|AudienceReportTest'
# Modo «solo una tanda» (`SOLO=<tanda> bash scripts/mutar-analitica-decidir.sh`; 28-09, el owner: esperar ~95 min por tanda
# es inviable). Corre SOLO las mutaciones de esa sección y con SUS pruebas: un filtro más estrecho nunca inventa un «muerde»,
# como mucho esconde uno, y el veredicto sigue siendo por código de salida. La base verde y el CONTROL corren siempre. El arnés
# entero se reserva para cerrar un bloque (la T3 entera, la TP…). Una tanda nueva: su `SECCION=` y su filtro aquí.
declare -A FILTRO_DE=(
    [T3c2]='GoalTest|AnalyticsGoalsTest|MetricsCatalogTest'
    [TP1]='HolderBirthDateTest|HolderBirthDatePanelTest|AnonymizeCoversEveryUserColumnTest|MeTest|SidebarTranslationKeysExistTest'
    [TP2]='AudienceReportTest|AnalyticsCensusTest|AnalyticsPageTest'
    [TP3]='AudienceReportTest|SegmentsReportTest|AnalyticsPageTest|AccessI18nParityTest'
    [T3d]='ExplainerTest|MetricTest|ChangesTest|AnalyticsPageTest'
    [T4]='BookedReportTest|ExplainerTest|AnalyticsTabsTest|AnalyticsCensusTest'
    [B3]='ExperimentsReportTest|AnalyticsEventsTest|AnalyticsContractTest'
    [TA]='OccupancyReportTest|CustomersReportTest|RegistrationCampaignTest|AnalyticsContractTest'
)
SOLO="${SOLO:-}"
SECCION=''
if [[ -n "$SOLO" ]]; then
    [[ -n "${FILTRO_DE[$SOLO]:-}" ]] || { echo "✗ SOLO=$SOLO no es una tanda de este arnés (${!FILTRO_DE[*]})" >&2; exit 2; }
    FILTER="${FILTRO_DE[$SOLO]}"
fi
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
    app/Filament/Widgets/Analytics/MetricsWidget.php
    lang/zh_CN/admin.php
    lang/es/admin.php
    app/Filament/Analytics/FunnelReport.php
    app/Filament/Analytics/PartiesReport.php
    app/Filament/Analytics/CustomersReport.php
    app/Livewire/Admin/Puerta/ValidarRegistro.php
    app/Domain/Identity/Services/GateVisits.php
    app/Filament/Analytics/Metrics/CustomersMetrics.php
    app/Filament/Analytics/Metrics/MoneyMetrics.php
    app/Filament/Analytics/Metrics/SurveysMetrics.php
    app/Filament/Analytics/Metrics/MetricSet.php
    resources/views/filament/widgets/analytics/metric.blade.php
    app/Filament/Analytics/Changes.php
    app/Filament/Widgets/Analytics/ChangesWidget.php
    app/Filament/Widgets/Analytics/SummaryWidget.php
    app/Filament/Widgets/Analytics/MoneyOverviewWidget.php
    app/Filament/Widgets/Analytics/OccupancyBreakdownWidget.php
    app/Filament/Widgets/Analytics/PagesWidget.php
    resources/views/filament/pages/analytics/tab-select.blade.php
    app/Domain/Booking/Services/OccupancyReader.php
    app/Filament/Analytics/OccupancyReport.php
    app/Filament/Widgets/Analytics/OccupancyHeatmapWidget.php
    resources/js/sidebar/calendar.js
    resources/js/sidebar/missing.js
    app/Filament/Analytics/Goal.php
    app/Filament/Analytics/GoalsForm.php
    app/Domain/Platform/Services/Analytics/AnalyticsGoals.php
    app/Domain/Identity/Services/BirthDatePolicy.php
    app/Domain/Identity/Services/AccountProfile.php
    app/Http/Controllers/Api/V1/MeProfileController.php
    app/Http/Controllers/Api/V1/AuthRegistrationController.php
    app/Http/Controllers/Api/V1/GoogleSignupController.php
    app/Domain/Identity/Services/SelfSignup.php
    app/Domain/Identity/Services/GoogleSignup.php
    app/Domain/Identity/Services/CustomerRegistrar.php
    app/Filament/Pages/CreateManualOrderPage.php
    app/Domain/Identity/Models/User.php
    app/Domain/Identity/Services/AccountPrivacy.php
    app/Http/Resources/Api/V1/UserResource.php
    app/Filament/Resources/Users/Schemas/UserInfolist.php
    app/Http/Sidebar/SidebarBoot.php
    resources/js/sidebar/register.js
    resources/js/sidebar/account/google.js
    resources/js/sidebar/stores/profile.js
    app/Filament/Analytics/AudienceReport.php
    app/Filament/Widgets/Analytics/AudienceWidget.php
    app/Filament/Widgets/Analytics/SegmentsWidget.php
    app/Domain/Identity/Services/PermissionCatalog.php
    database/seeders/PermissionSeeder.php
    database/migrations/2026_09_29_110000_drop_analytics_export_permission.php
    app/Domain/Platform/Enums/Comparison.php
    app/Filament/Analytics/Explainer.php
    resources/views/filament/pages/analytics/explain.blade.php
    app/Filament/Analytics/BookedReport.php
    app/Filament/Analytics/Metrics/BookedMetrics.php
    app/Filament/Widgets/Analytics/BookedChart.php
    app/Filament/Analytics/ExperimentsReport.php
    app/Filament/Widgets/Analytics/ExperimentsWidget.php
    app/Domain/Platform/Services/Analytics/Contract.php
    app/Domain/Platform/Services/Analytics/Recorder.php
    app/Domain/Platform/Services/Analytics/AttributionContext.php
    app/Filament/Widgets/Analytics/CustomersBreakdownWidget.php
    app/Filament/Widgets/Analytics/SourcesWidget.php
)
# La copia de cada fichero, por su RUTA entera (T3a): por su nombre, `lang/es/admin.php` y `lang/zh_CN/admin.php` chocaban.
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

# La T2 (`#758`) añade dos módulos de JS del cajón (la demanda sin hueco): su red es `node --test`, no la suite. Y la TP·1
# (`#792`) tres más: la fecha de nacimiento solo viaja si hay una (el alta, Google) y «Tus datos» no la borra por omisión.
verde() { $RUN >/dev/null 2>&1 && docker compose exec -u sail -T laravel.test node --test resources/js/sidebar/calendar.test.js resources/js/sidebar/missing.test.js resources/js/sidebar/register.test.js resources/js/sidebar/account/google.test.js resources/js/sidebar/stores/profile.test.js >/dev/null 2>&1; }

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
    if [[ -n "$SOLO" && "$SECCION" != "$SOLO" ]]; then
        return
    fi
    total=$((total + 1))
    aplicar "$fichero" "$buscar" "$poner"; local unico=$?
    if cmp -s "$fichero" "$(copia "$fichero")"; then
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
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

control() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    aplicar "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ CONTROL «$nombre» NO SE APLICÓ"; control_ok=0; return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✓ control:   $nombre (sigue verde, como debe)"
    else
        echo "  ✗ CONTROL ROJO: $nombre — la suite se pone roja por algo que no es la regla"; control_ok=0
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
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

# La proporción vive en `Comparison::share()` desde la T3d (la tarjeta y el texto para IA juzgan igual).
mutar "el widget no pasa la duración de las ventanas (siempre mitad y mitad)" "app/Domain/Platform/Enums/Comparison.php" \
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

# T3a (`#759`): la tarjeta la pinta `MetricsWidget::tile()` para TODAS las pestañas (antes, cada widget la suya).
mutar "una tarjeta vuelve a ser un Stat suelto, sin anatomía" "app/Filament/Widgets/Analytics/MetricsWidget.php" \
  '$stat = $this->metric($metric);' \
  '$stat = Stat::make($metric->label, $metric->displayValue());'

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
  "                'scale_mean' => '本时段回答中第一个评分题（1 到 5）的平均分。回答是匿名的：少于 5 条时不显示平均分。'," \
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

# T3a (`#759`): la composición de las cifras vive en el catálogo; `GateWidget` solo elige claves.
mutar "«Visitas acreditadas» cambia el carné por la búsqueda" "app/Filament/Analytics/Metrics/CustomersMetrics.php" \
  "['card' => \$card, 'lookup' => \$lookup]" \
  "['card' => \$lookup, 'lookup' => \$card]"

mutar "las visitas de antes, sin origen, dejan de decirse" "app/Filament/Analytics/Metrics/CustomersMetrics.php" \
  'return $other > 0 ?' \
  'return $other > 999 ?'

mutar "los compradores se comparan consigo mismos y no con el periodo anterior" "app/Filament/Analytics/MoneyReport.php" \
  "'previous' => \$this->buyerCounts(\$baseline, \$this->buyerRows(\$baseline))," \
  "'previous' => \$this->buyerCounts(\$window, \$this->buyerRows(\$window)),"

# ── La T2 (`#758`): la ocupación con la regla del aforo, dos cifras, lo que ya pasó, y la demanda sin hueco ──────
OR=app/Domain/Booking/Services/OccupancyReader.php
OP=app/Filament/Analytics/OccupancyReport.php

mutar "el lector cuenta lo PENDIENTE como ocupación (no es lo que pasó)" "$OR" \
  "            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->where('o.status', Order::STATUS_PAID)
            ->whereNull('i.cancelled_at')
            ->whereBetween('s.date', [\$from, \$to])
            ->select(['s.zone_id', 's.date', 's.start_time as entry_start'," \
  "            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->whereNull('i.cancelled_at')
            ->whereBetween('s.date', [\$from, \$to])
            ->select(['s.zone_id', 's.date', 's.start_time as entry_start',"

mutar "una línea CANCELADA sigue ocupando" "$OR" \
  "            ->where('o.status', Order::STATUS_PAID)
            ->whereNull('i.cancelled_at')
            ->whereBetween('s.date', [\$from, \$to])
            ->select(['s.zone_id', 's.date', 's.start_time as entry_start'," \
  "            ->where('o.status', Order::STATUS_PAID)
            ->whereBetween('s.date', [\$from, \$to])
            ->select(['s.zone_id', 's.date', 's.start_time as entry_start',"

mutar "la HORA EXTRA no alarga la ocupación" "$OR" \
  "->selectRaw('(t.duration_min + i.extra_minutes) as duration_min')" \
  "->selectRaw('(t.duration_min + 0) as duration_min')"

mutar "el tramo de una entrada incluye su FIN (una franja de más)" "$OR" \
  'if ($start < $entry || ($end !== null && $start >= $end)) {' \
  'if ($start < $entry || ($end !== null && $start > $end)) {'

mutar "la PREPARACIÓN de una fiesta no cuenta aunque la zona la cuente" "$OR" \
  "\$zone['prep_blocks'] ?? true);" \
  "false);"

mutar "cada punto representa su franja entera y no la media hora hasta el siguiente" "$OR" \
  '? (string) $next->start_time' \
  '? (string) $slot->end_time'

mutar "un pack cuenta como grupo (la regla del tipo, invertida)" "$OR" \
  "'kind' => \$row->type === TicketType::TYPE_PACK ? 'party' : ((bool) \$row->tiered ? 'group' : 'entry')," \
  "'kind' => \$row->type === TicketType::TYPE_PACK ? 'group' : ((bool) \$row->tiered ? 'party' : 'entry'),"

mutar "una franja CERRADA cuenta como ofrecida, y las zonas de fiestas se suman a las entradas" "$OP" \
  "            if (! \$p['entry_zone'] || \$p['closed']) {
                continue;
            }
            \$entryZones[\$p['zone_id']] = true;" \
  "            if (false) {
                continue;
            }
            \$entryZones[\$p['zone_id']] = true;"

mutar "lo que aún no ha pasado (esta tarde) cuenta" "$OP" \
  "return array_values(array_filter(\$rows, static fn (array \$row): bool => \$window->contains(CarbonImmutable::parse(\$row['date'].' '.\$row['start'], \$window->timezone))));" \
  "return array_values(\$rows);"

mutar "la antelación del llenado redondea hacia arriba" "$OP" \
  'max(0, (int) floor(CarbonImmutable::parse($p' \
  'max(0, (int) ceil(CarbonImmutable::parse($p'

mutar "un cobro apuntado DESPUÉS de la visita da antelación negativa" "$OP" \
  '$days = max(0, (int) $paidOn->diffInDays($visit, false));' \
  '$days = (int) $paidOn->diffInDays($visit, false);'

mutar "el tope de fiestas deja de ser el de la zona" "$OP" \
  "\$out['cap'] += \$p['max_parties'];" \
  "\$out['cap'] += 1;"

mutar "«desde cuándo» se mide sale del último evento y no del primero" "$OP" \
  "->where('name', 'availability_missing')->min('received_at');" \
  "->where('name', 'availability_missing')->max('received_at');"

mutar "el mapa de calor parte los pasos en 25 puntos (el lleno deja de verse)" "app/Filament/Widgets/Analytics/OccupancyHeatmapWidget.php" \
  'intdiv(min($bp, 9999), 2000)' \
  'intdiv(min($bp, 9999), 2500)'

mutar "sin ningún día a la venta no hay demanda sin hueco (JS)" "resources/js/sidebar/calendar.js" \
  '        return [current];' \
  '        return [];'

mutar "la demanda sin hueco se repite al reabrir el producto (JS)" "resources/js/sidebar/missing.js" \
  '            if (seen.has(key)) continue;' \
  '            if (false) continue;'

# ── T3a · LA FORMA (§4.13, `#759`): siete pestañas, solo la abierta, ≤ 6 arriba, lo plegado, el glosario ──────────
AP=app/Filament/Pages/AnalyticsPage.php
CSV=app/Filament/Analytics/CsvExport.php
mutar "las pestañas vuelven a pintarse todas (las de Alpine)" "$AP" \
  "->livewireProperty('tab')" \
  "->persistTabInQueryString(self::TAB_QUERY_KEY)"

mutar "una clave vieja deja de abrir su pestaña" "$AP" \
  "public const LEGACY_TABS = ['traffic' => 'marketing', 'surveys' => 'satisfaction'];" \
  "public const LEGACY_TABS = ['surveys' => 'satisfaction'];"

mutar "el navegador puede poner una pestaña que no existe" "$AP" \
  'public function updatedTab(): void
    {
        $this->tab = self::normalizeTab($this->tab);' \
  'public function updatedTab(): void
    {'

mutar "el botón del CSV de Marketing descarga otro informe" "$AP" \
  "'marketing' => CsvExport::REPORT_FUNNEL," \
  "'marketing' => CsvExport::REPORT_MONEY,"

mutar "«Exportar segmento» sale en todas las pestañas" "$AP" \
  "if (\$tab === 'customers') {" \
  "if (true) {"

mutar "«Resumen» cambia una cifra por otra" "app/Filament/Widgets/Analytics/SummaryWidget.php" \
  "public const KEYS = ['money.net'," \
  "public const KEYS = ['money.sold',"

mutar "arriba de Dinero caben siete" "app/Filament/Widgets/Analytics/MoneyOverviewWidget.php" \
  "'money.orders', 'money.avg_order'];" \
  "'money.orders', 'money.avg_order', 'money.collected'];"

mutar "la principal deja de ir a doble ancho" "app/Filament/Widgets/Analytics/MoneyOverviewWidget.php" \
  "public const PRINCIPAL = 'money.net';" \
  "public const PRINCIPAL = null;"

mutar "lo plegado nace abierto" "app/Filament/Widgets/Analytics/MetricsWidget.php" \
  '->collapsed(static::FOLDED)' \
  '->collapsed(false)'

mutar "vuelve la jerga («El embudo»)" "lang/es/admin.php" \
  "'funnel_heading' => 'Del paso a paso a la compra'," \
  "'funnel_heading' => 'El embudo',"

mutar "el selector del móvil deja de cambiar la pestaña al momento" "resources/views/filament/pages/analytics/tab-select.blade.php" \
  'wire:model.live="tab"' \
  'wire:model="tab"'

mutar "«Pendiente de cobrar» lee lo liquidado" "app/Filament/Analytics/Metrics/MoneyMetrics.php" \
  "\$d['pending'], null, \$d['orders']" \
  "\$d['settled'], null, \$d['orders']"

mutar "«Visitantes» cuenta reservas y no plazas" "$OP" \
  "\$out['seats'] += \$seats;" \
  "\$out['seats'] += 1;"

mutar "«Visitantes» del periodo comparado, a cero" "$OP" \
  "'visitors' => \$visitors['seats'], 'visitor_lines'" \
  "'visitors' => 0, 'visitor_lines'"

mutar "las plazas en lotes se prueban como sucesos sueltos" "$M" \
  'self::UNIT_COUNT => $this->squares !== null' \
  'self::UNIT_COUNT => $this->squares === -1'

mutar "la tasa de respuesta olvida el correo" "app/Filament/Analytics/Metrics/SurveysMetrics.php" \
  "\$t['answered_internal'] + \$t['answered_external'], \$t['offered'] + \$t['sent']," \
  "\$t['answered_internal'], \$t['offered'] + \$t['sent'],"

mutar "el CSV pierde la tasa de respuesta" "$CSV" \
  "[__('admin.analytics.surveys.response_rate'), " \
  "[__('admin.analytics.surveys.response_rate_x'), "

mutar "el CSV de Clientes pierde a los visitantes" "$CSV" \
  "            [__('admin.analytics.occupancy.visitors'), (string) \$visitors['seats']]," \
  ''

mutar "los eventos rechazados salen del CSV" "$CSV" \
  '                ...(new DataQualityWidget)->tablesFor($window, $comparison),' \
  ''

mutar "el desglose de la ocupación deja de escribir días" "app/Filament/Widgets/Analytics/OccupancyBreakdownWidget.php" \
  "OccupancyMetrics::days(\$k['median'])" \
  "(string) \$k['median']"

mutar "la tabla de las horas pierde una" "app/Filament/Widgets/Analytics/PagesWidget.php" \
  'range(0, 23)' \
  'range(0, 22)'

# 28-09: la caché de la ocupación llevaba el corte al SEGUNDO y no servía entre los widgets de una pestaña.
mutar "la caché de la ocupación vuelve a cambiar cada segundo" "$OP" \
  "'analytics:occupancy:v3:'.\$window->timezone.':'.\$window->dateFrom().':'.\$window->dateTo()" \
  "'analytics:occupancy:v3:'.\$window->timezone.':'.\$window->utcFrom()->format('YmdHis').':'.\$window->utcTo()->format('YmdHis')"

# ── T3b · ¿ES NORMAL PARA TI? (§4.13, `#790` `[DECIDIDO owner]`: el rango mín–máx de los últimos 12) ─────────────
MS=app/Filament/Analytics/Metrics/MetricSet.php
mutar "el borde de abajo de la banda deja de ser normal" "$M" \
  '$this->value < $low => self::VERDICT_LOW,' \
  '$this->value <= $low => self::VERDICT_LOW,'

mutar "se juzga con menos de 8 periodos" "$M" \
  'if ($n < self::MIN_HISTORY) {' \
  'if ($n < 3) {'

mutar "el tono ignora la polaridad (subir siempre es malo)" "$M" \
  '($state === self::VERDICT_HIGH) === ($this->polarity === Polarity::UpIsGood) => self::TONE_GOOD,' \
  '($state === self::VERDICT_HIGH) !== ($this->polarity === Polarity::UpIsGood) => self::TONE_GOOD,'

mutar "una media con pocos casos se juzga" "$M" \
  'if (($this->unit === self::UNIT_RATE || $this->isMean) && ($this->base ?? 0) < self::MIN_BASE) {' \
  'if ($this->unit === self::UNIT_RATE && ($this->base ?? 0) < self::MIN_BASE) {'

mutar "una historia plana dice «entre 0 y 0»" "$M" \
  "\$flat = \$verdict['low'] === \$verdict['high'];" \
  '$flat = false;'

mutar "cuentan los periodos de antes de medir" "$MS" \
  'return $since !== null && $period->from->greaterThanOrEqualTo($since);' \
  'return $since !== null;'

mutar "una fuente que aún no mide tiene historia" "$MS" \
  'return $since !== null && $period->from->greaterThanOrEqualTo($since);' \
  'return $since === null || $period->from->greaterThanOrEqualTo($since);'

mutar "la media de un periodo sin casos entra como cero" "$MS" \
  "\$undefined = (\$metric->unit === Metric::UNIT_RATE || \$metric->isMean) && (\$metric->previousBase ?? 0) === 0;" \
  '$undefined = false;'

mutar "la historia mira años atrás y no los periodos anteriores" "$MS" \
  '$period = $period->previous();' \
  '$period = $period->yearAgo();'

mutar "la historia mira 11 periodos" "$MS" \
  'public const HISTORY = 12;' \
  'public const HISTORY = 11;'

mutar "un día se compara con todos los lunes" "$MS" \
  "'day:'.\$window->from->isoWeekday()" \
  "'day:1'"

mutar "los compradores del dinero no cambian de periodo en la historia" "app/Filament/Analytics/Metrics/MoneyMetrics.php" \
  "        \$report['customers']['previous'] = \$totals['customers_previous'];" \
  ''

mutar "el «bien» se pinta como una atención" "resources/views/filament/widgets/analytics/metric.blade.php" \
  "'text-success-700 dark:text-success-400' => \$verdict['tone'] === \\App\\Filament\\Analytics\\Metric::TONE_GOOD," \
  "'text-success-700 dark:text-success-400' => \$verdict['tone'] === \\App\\Filament\\Analytics\\Metric::TONE_WATCH,"

# ── T3c·1 · LO QUE HA CAMBIADO (§4.5 y §4.13, `#791`) ─────────────────────────────────────────────────────────
CH=app/Filament/Analytics/Changes.php
mutar "la escala de una bajada deja de mirar el periodo de antes" "$CH" \
  'max($metric->base ?? 0, $metric->previousBase ?? 0) < Metric::MIN_BASE' \
  '($metric->base ?? 0) < Metric::MIN_BASE'

mutar "entran los números diminutos (sin escala)" "$CH" \
  ' || max($metric->base ?? 0, $metric->previousBase ?? 0) < Metric::MIN_BASE' \
  ''

mutar "el dinero deja de ir delante" "$CH" \
  '? [1, (float) $outside]' \
  '? [0, (float) $outside]'

mutar "lo que no es dinero se ordena por lo absoluto" "$CH" \
  ': [0, $outside / max(abs($edge), $high - $low, 1)];' \
  ': [0, (float) $outside];'

mutar "caben seis" "$CH" \
  'public const MAX = 5;' \
  'public const MAX = 6;'

mutar "las que no caben se callan" "$CH" \
  "'more' => max(0, count(\$out) - self::MAX)" \
  "'more' => 0"

mutar "no se cuenta cuántas cifras se miraron" "$CH" \
  '            $judged++;' \
  ''

mutar "con historia y nada fuera, dice «sin historia»" "app/Filament/Widgets/Analytics/ChangesWidget.php" \
  "\$changes['judged'] > 0" \
  "\$changes['judged'] > 999999"

mutar "una frase de «lo que ha cambiado» lleva a «Resumen»" "$AP" \
  '            if ($tab === self::DEFAULT_TAB) {
                continue;
            }' \
  ''

# ── T3c·2 · LOS OBJETIVOS DEL MES (§4.13, `#759`) ────────────────────────────────────────────────────────────────
G=app/Filament/Analytics/Goal.php
GS=app/Domain/Platform/Services/Analytics/AnalyticsGoals.php
GF=app/Filament/Analytics/GoalsForm.php
SECCION=T3c2
mutar "el ritmo se mide contra el objetivo entero" "$G" \
  'return $value >= $this->target * $this->elapsed ? self::STATE_ON_PACE : self::STATE_BEHIND;' \
  'return $value >= $this->target ? self::STATE_ON_PACE : self::STATE_BEHIND;'

mutar "ir justo al ritmo es ir por detrás" "$G" \
  'return $value >= $this->target * $this->elapsed ? self::STATE_ON_PACE' \
  'return $value > $this->target * $this->elapsed ? self::STATE_ON_PACE'

mutar "los primeros días se juzga el ritmo" "$G" \
  'if ($this->elapsed < self::MIN_ELAPSED) {' \
  'if (false) {'

mutar "los primeros días son la mitad" "$G" \
  'public const MIN_ELAPSED = 0.1;' \
  'public const MIN_ELAPSED = 0.05;'

mutar "una tasa se juzga por el ritmo" "$G" \
  '        if ($this->level) {' \
  '        if (false) {'

mutar "un mes cerrado se juzga por el ritmo" "$G" \
  'if ($this->elapsed >= 1.0) {' \
  'if ($this->elapsed > 1.0) {'

mutar "llegar justo no es alcanzarlo" "$G" \
  'return $value >= $this->target ? self::STATE_REACHED' \
  'return $value > $this->target ? self::STATE_REACHED'

mutar "cualquier ventana tiene objetivo" "$G" \
  'return $window->unit === Window::UNIT_MONTH && $window->from->day === 1;' \
  'return true;'

mutar "cualquier cifra admite objetivo" "$G" \
  'if (! in_array($key, self::KEYS, true) || $target <= 0 || ! self::applies($window)) {' \
  'if ($target <= 0 || ! self::applies($window)) {'

mutar "ir por detrás no pide atención" "$G" \
  'self::STATE_BEHIND, self::STATE_MISSED, self::STATE_BELOW => Metric::TONE_WATCH,' \
  'self::STATE_MISSED, self::STATE_BELOW => Metric::TONE_WATCH,'

mutar "el avance se dice sobre lo que falta" "$G" \
  'return (int) round($value / $this->target * 10000);' \
  'return (int) round(($this->target - $value) / $this->target * 10000);'

mutar "guardar no olvida la caché del mes" "$GS" \
  '        Cache::forget(self::cacheKey($first));' \
  ''

mutar "vaciar un campo no quita el objetivo" "$GS" \
  '$row?->delete();' \
  '$row?->touch();'

mutar "guardar lo mismo también escribe y deja rastro" "$GS" \
  'if ($old === $target) {' \
  'if (false) {'

mutar "el rastro no lleva el antes" "$GS" \
  '$changes['"'"'before'"'"'][$key] = $old;' \
  ''

mutar "guardar no deja rastro" "$GS" \
  "        AuditLogger::log('analytics.goals_updated', null, ['month' => \$first->format('Y-m')] + \$changed);" \
  ''

mutar "un objetivo de cero se acepta" "$GS" \
  'if ($target !== null && $target <= 0) {' \
  'if ($target !== null && $target < 0) {'

mutar "cualquier clave se acepta" "$GS" \
  'if (preg_match(self::KEY_RE, $key) !== 1) {' \
  'if (false) {'

mutar "el mes se guarda por el día que llega" "$GS" \
  "return CarbonImmutable::parse(\$month->format('Y-m-01'));" \
  "return CarbonImmutable::parse(\$month->format('Y-m-d'));"

mutar "no se guarda quién lo puso" "$GS" \
  "'month' => \$first->toDateString(), 'target' => \$target, 'set_by' => \$setBy]" \
  "'month' => \$first->toDateString(), 'target' => \$target, 'set_by' => null]"

mutar "los euros se guardan como euros" "$GF" \
  '                Metric::UNIT_MONEY, Metric::UNIT_RATE => (int) round($number * 100),' \
  '                Metric::UNIT_RATE => (int) round($number * 100),'

mutar "una tasa pasa del 100 %" "$GF" \
  '$target = min($target, 10000);' \
  '$target = $target;'

mutar "se puede tocar cualquier mes" "$GF" \
  "return is_string(\$key) ? (self::months()[\$key] ?? null) : null;" \
  "return is_string(\$key) ? CarbonImmutable::parse(\$key.'-01') : null;"

mutar "un cero pone objetivo" "$GF" \
  '$targets[$key] = $target !== null && $target > 0 ? $target : null;' \
  '$targets[$key] = $target;'

mutar "las tarjetas no llevan su objetivo" "$MS" \
  'return self::withGoals(static::judged(static::from($report), $report, $window), $window);' \
  'return static::judged(static::from($report), $report, $window);'

mutar "la historia pierde el objetivo" "$M" \
  '            $history, $unit, $this->goal,' \
  '            $history, $unit,'

mutar "el objetivo pierde la historia" "$M" \
  '            $this->history, $this->historyUnit, $goal,' \
  '            null, null, $goal,'

mutar "la tarjeta no pinta el objetivo" "resources/views/filament/widgets/analytics/metric.blade.php" \
  '@php($goal = $metric->goal?->read($metric))' \
  '@php($goal = null)'

mutar "la tarjeta no dice el tono del objetivo" "resources/views/filament/widgets/analytics/metric.blade.php" \
  "data-metric-goal-tone=\"{{ \$goal['tone'] }}\"" \
  'data-metric-goal-tone="neutral"'

mutar "el botón de los objetivos se ve sin permiso" "$AP" \
  '->visible(fn (): bool => auth()->user()?->hasPermission(self::PERMISSION_GOALS) ?? false)' \
  '->visible(fn (): bool => true)'

mutar "las tarjetas no se enteran del guardado" "$AP" \
  '                $this->dispatch(self::GOALS_SAVED_EVENT);' \
  ''

mutar "el botón de los objetivos no está al pie de «Resumen»" "$AP" \
  '        if ($tab === self::DEFAULT_TAB) {
            $actions[] = $this->goalsAction();' \
  "        if (\$tab === 'nada') {
            \$actions[] = \$this->goalsAction();"

mutar "las tarjetas no escuchan el guardado" "app/Filament/Widgets/Analytics/MetricsWidget.php" \
  '    #[On(AnalyticsPage::GOALS_SAVED_EVENT)]' \
  ''

# ── TP·1 · LA FECHA DE NACIMIENTO DEL TITULAR (§4.14, `#792` `[DECIDIDO owner]`: entera y opcional) ──────────────
# Una política para las cuatro puertas (alta, Google, mostrador, Mi cuenta): no futura, ≥ 18 el día del PARQUE, ≤ 120.
# «Tus datos» sin la clave NO la toca (la isla guarda así); la purga la borra, el export y `GET /me` la llevan.
SECCION=TP1
BP=app/Domain/Identity/Services/BirthDatePolicy.php

mutar "la edad se cuenta con el reloj del contenedor (UTC), no el día del parque" "$BP" \
  '        $today ??= DisplayTime::today();' \
  "        \$today ??= CarbonImmutable::today('UTC');"

mutar "un menor de edad pasa" "$BP" \
  '        if ($age < Dependent::ADULT_AGE) {' \
  '        if ($age < 0) {'

mutar "el día del 18.º cumpleaños todavía no vale" "$BP" \
  '        if ($age < Dependent::ADULT_AGE) {' \
  '        if ($age <= Dependent::ADULT_AGE) {'

mutar "una fecha futura se avisa como si fuera de un menor" "$BP" \
  "        if (\$born->greaterThan(CarbonImmutable::createFromFormat('!Y-m-d', \$today->toDateString(), 'UTC'))) {" \
  '        if (false) {'

mutar "un año con una errata (0198) pasa" "$BP" \
  '        return $age > self::MAX_AGE ? self::IMPLAUSIBLE : null;' \
  '        return null;'

mutar "el alta con correo no valida la fecha" "app/Http/Controllers/Api/V1/AuthRegistrationController.php" \
  "            'born_on' => BirthDatePolicy::rules()," \
  "            'born_on' => ['nullable', 'date_format:Y-m-d'],"

mutar "el alta con correo no la guarda" "app/Domain/Identity/Services/SelfSignup.php" \
  "                'born_on' => BirthDatePolicy::normalize(\$data['born_on'] ?? null)," \
  "                'born_on' => null,"

mutar "la pantalla tras Google no valida la fecha" "app/Http/Controllers/Api/V1/GoogleSignupController.php" \
  "            'born_on' => BirthDatePolicy::rules()," \
  "            'born_on' => ['nullable', 'date_format:Y-m-d'],"

mutar "la pantalla tras Google no la guarda" "app/Domain/Identity/Services/GoogleSignup.php" \
  "                'born_on' => BirthDatePolicy::normalize(\$data['born_on'] ?? null)," \
  "                'born_on' => null,"

mutar "Mi cuenta no valida la fecha" "app/Domain/Identity/Services/AccountProfile.php" \
  "            'born_on' => ['sometimes', ...BirthDatePolicy::rules()]," \
  "            'born_on' => ['sometimes', 'nullable'],"

mutar "Mi cuenta BORRA la fecha cuando no viaja (cada guardado de la isla)" "app/Http/Controllers/Api/V1/MeProfileController.php" \
  "        ] + array_intersect_key(\$data, ['born_on' => true]), \$emailChanges ? \$data['code'] : null, (string) \$request->ip());" \
  "        ] + ['born_on' => \$data['born_on'] ?? null], \$emailChanges ? \$data['code'] : null, (string) \$request->ip());"

mutar "el mostrador no la guarda" "app/Domain/Identity/Services/CustomerRegistrar.php" \
  "                'born_on' => \$bornOn," \
  "                'born_on' => null,"

mutar "el mostrador se fía del formulario (la llamada directa y el pendiente reescrito pasan)" "app/Filament/Pages/CreateManualOrderPage.php" \
  "        if ((\$error = BirthDatePolicy::firstError(\$bornOn, 'admin.orders.create_manual.born_on_errors')) !== null) {" \
  '        if (false) {'

mutar "la supresión (art. 17) se deja la fecha" "app/Domain/Identity/Models/User.php" \
  "                'born_on' => null,                               // TP·1 (\`#792\`): la fecha de nacimiento es PII" \
  ''

mutar "el export (art. 20) no la lleva" "app/Domain/Identity/Services/AccountPrivacy.php" \
  "                'born_on' => \$user->born_on?->toDateString()," \
  ''

mutar "GET /me no la sirve" "app/Http/Resources/Api/V1/UserResource.php" \
  "            'born_on' => \$this->born_on?->toDateString()," \
  ''

mutar "la ficha del panel no dice la edad de hoy" "app/Filament/Resources/Users/Schemas/UserInfolist.php" \
  "                                    'age' => \$record->age()," \
  "                                    'age' => 0,"

mutar "el rótulo y la pista no viajan al cajón (se pintarían VACÍOS)" "app/Http/Sidebar/SidebarBoot.php" \
  "                    'born_on', 'born_on_hint'," \
  ''

mutar "el alta manda la fecha vacía (un 422 por esquema)" "resources/js/sidebar/register.js" \
  "    return value === '' ? {} : { born_on: value };" \
  '    return { born_on: value };'

mutar "la pantalla tras Google no la manda" "resources/js/sidebar/account/google.js" \
  '        ...bornOnField(form),' \
  ''

mutar "«Tus datos» la manda aunque el formulario no la traiga (la isla la borraría)" "resources/js/sidebar/stores/profile.js" \
  "    if (form && 'born_on' in form) body.born_on = String(form.born_on ?? '').trim() || null;" \
  "    body.born_on = String(form?.born_on ?? '').trim() || null;"

if [[ -z "$SOLO" || "$SOLO" == TP1 ]]; then
    control "un comentario de la política de la fecha" "$BP" \
      'Más años que esto es una errata' \
      'Mas años que esto es una errata'
fi

# ── TP·2 · QUIÉN VIENE (§4.14, `#792`): una persona una vez con su primer día, sus hijos MENORES, con quién viene, las fiestas, y
# ninguna celda de 1 a 4 (`RGPD-07`). Al CSV (el censo) y a la pestaña «Clientes».
SECCION=TP2
AR=app/Filament/Analytics/AudienceReport.php
AW=app/Filament/Widgets/Analytics/AudienceWidget.php

mutar "cuentan también las reservas sin pagar" "$AR" \
  "            ->where('o.status', Order::STATUS_PAID)" \
  ''

mutar "cuentan también las líneas canceladas" "$AR" \
  "            ->whereNull('i.cancelled_at')" \
  ''

mutar "la edad es la de la ÚLTIMA visita del periodo, no la primera" "$AR" \
  '            $firstDay[$user] = isset($firstDay[$user]) ? min($firstDay[$user], $day) : $day;' \
  '            $firstDay[$user] = $day;'

mutar "la edad de quien reserva se cuenta HOY, no el día de su visita" "$AR" \
  '            $ages[] = Dependent::ageBetween(substr((string) $date, 0, 10), CarbonImmutable::parse($firstDay[(int) $id]));' \
  '            $ages[] = Dependent::ageBetween(substr((string) $date, 0, 10), CarbonImmutable::now());'

mutar "los hijos retirados cuentan" "$AR" \
  "            ->whereNull('removed_at')" \
  ''

mutar "un hijo que ya cumplió 18 cuenta como niño que viene" "$AR" \
  '                return $age === null || $age >= Dependent::ADULT_AGE ? null' \
  '                return $age === null ? null'

mutar "un reparto de menos de cinco con dato se enseña" "$AR" \
  "        return ['of' => \$of, 'with_data' => \$counted, 'rows' => \$counted < self::MIN_CELL ? [] : self::foldRanges(\$buckets)];" \
  "        return ['of' => \$of, 'with_data' => \$counted, 'rows' => self::foldRanges(\$buckets)];"

mutar "una celda pequeña no se funde con la vecina" "$AR" \
  "            if (\$open['count'] === 0 || \$open['count'] >= self::MIN_CELL) {" \
  '            if (true) {'

mutar "un resto pequeño al final se queda solo" "$AR" \
  "        while (\$open !== null && \$open['count'] > 0 && \$open['count'] < self::MIN_CELL && \$out !== []) {" \
  '        while (false) {'

mutar "una categoría pequeña no va a «Otros»" "$AR" \
  '            if ($n > 0 && $n < self::MIN_CELL) {' \
  '            if (false) {'

mutar "el parentesco «otro» y «Otros» son dos filas" "$AR" \
  '        if (array_key_exists(self::OTHER, $kept)) {' \
  '        if (false) {'

mutar "con menores es solo la entrada asignada (el producto de menores no cuenta)" "$AR" \
  '        $withMinors = $lines->filter(fn ($l): bool => isset($assigned[$l->id]) || in_array((int) $l->ticket_type_id, $minorsOnly, true))->count();' \
  '        $withMinors = $lines->filter(fn ($l): bool => isset($assigned[$l->id]))->count();'

mutar "sin invitación, la edad de quien cumple se pierde" "$AR" \
  '            $age = $party->partyInvitation->honoree_age ?? self::celebrantAge($party, $type);' \
  '            $age = $party->partyInvitation->honoree_age ?? null;'

mutar "quien cumple cuenta como invitado" "$AR" \
  '                if ($i === 0 && $party->hasHonoreeRow()) {' \
  '                if (false) {'

mutar "un recuento de 1 a 4 se enseña" "$AW" \
  '        return $n > 0 && $n < AudienceReport::MIN_CELL ? __(' \
  '        return false ? __('

mutar "el CSV de «Clientes» no lleva quién viene" "app/Filament/Analytics/CsvExport.php" \
  '                ...(new AudienceWidget)->tablesFor($window, $comparison),' \
  ''

mutar "la pestaña «Clientes» no enseña quién viene" "app/Filament/Pages/AnalyticsPage.php" \
  '            AudienceWidget::class,
            RegistrationsWidget::class,' \
  '            RegistrationsWidget::class,'

if [[ -z "$SOLO" || "$SOLO" == TP2 ]]; then
    control "un comentario del informe de quién viene" "$AR" \
      'Los tramos de edad de quien reserva' \
      'Los tramos de edad de quien reservó'
fi

# ── TP·3 · PARA LOS ANUNCIOS Y SIN NOMBRES (§4.14, `#793`). La TP·3a: los tramos de Google Ads y el estado parental como lo puede
# decir el producto («con hijos declarados» o «sin dato», nunca «no es padre»). La TP·3b: fuera «Exportar segmento» con su permiso
# —el público es ANÓNIMO— y los segmentos, solo recuentos con 1–4 dicho «menos de 5».
SECCION=TP3
SW=app/Filament/Widgets/Analytics/SegmentsWidget.php

mutar "55–64 y 65+ vuelven a ser un solo «55+»" "$AR" \
  '    public const ADULT_BRACKETS = [[18, 24], [25, 34], [35, 44], [45, 54], [55, 64], [65, null]];' \
  '    public const ADULT_BRACKETS = [[18, 24], [25, 34], [35, 44], [45, 54], [55, null]];'

mutar "los hijos vuelven a ir de tres en tres (9–11, 12–14, 15–17)" "$AR" \
  '    public const CHILD_BRACKETS = [[0, 2], [3, 5], [6, 8], [9, 12], [13, 17]];' \
  '    public const CHILD_BRACKETS = [[0, 2], [3, 5], [6, 8], [9, 11], [12, 14], [15, 17]];'

mutar "«sin dato» cuenta a todos, también a los que declararon hijos" "$AW" \
  "            \$noData = \$t['of'] - \$t['with_data'];
            \$rows = [
                [__('admin.analytics.audience.ads.parents')," \
  "            \$noData = \$t['of'];
            \$rows = [
                [__('admin.analytics.audience.ads.parents'),"

mutar "«Para los anuncios» se pinta con menos de cinco personas" "$AW" \
  "    private function forAds(array \$t): array
    {
        \$rows = [];
        if (\$t['of'] >= AudienceReport::MIN_CELL) {" \
  "    private function forAds(array \$t): array
    {
        \$rows = [];
        if (true) {"

mutar "«con hijos declarados» de 1 a 4 se enseña, con su %" "$AW" \
  "[__('admin.analytics.audience.ads.parents'), self::masked(\$t['with_data']), self::share(\$t['with_data'], \$t['of'], masked: true)]," \
  "[__('admin.analytics.audience.ads.parents'), (string) \$t['with_data'], self::share(\$t['with_data'], \$t['of'])],"

mutar "«sin dato» se rotula «no es padre» (no declarar no es no tener)" "lang/es/admin.php" \
  "                'unknown' => 'Sin dato («Desconocido»; no declarar no es no tener)'," \
  "                'unknown' => 'No es padre',"

mutar "la tabla «Para los anuncios» sale de quién viene" "$AW" \
  "                \$this->forAds(\$r['kids_count'])," \
  ''

mutar "un segmento de 1 a 4 se escribe tal cual" "$SW" \
  '        return $n > 0 && $n < AudienceReport::MIN_CELL ? __(' \
  '        return false ? __('

mutar "los del opt-in de un segmento se escriben sin máscara" "$SW" \
  "            self::masked((int) \$counts[\$segment]['opt_in'])," \
  "            (string) \$counts[\$segment]['opt_in'],"

mutar "el permiso de exportar personas vuelve al catálogo" "app/Domain/Identity/Services/PermissionCatalog.php" \
  "            'analytics.manage'," \
  "            'analytics.export',
            'analytics.manage',"

mutar "el seeder vuelve a sembrar el permiso de exportar personas" "database/seeders/PermissionSeeder.php" \
  "        'analytics.manage' => 'Poner los objetivos del mes de la analítica'," \
  "        'analytics.export' => 'Exportar segmentos de clientes (con opt-in)',
        'analytics.manage' => 'Poner los objetivos del mes de la analítica',"

mutar "la migración deja el permiso viejo donde ya estaba sembrado" "database/migrations/2026_09_29_110000_drop_analytics_export_permission.php" \
  "        DB::table('permissions')->where('name', 'analytics.export')->delete();" \
  ''

if [[ -z "$SOLO" || "$SOLO" == TP3 ]]; then
    control "un comentario del widget de los segmentos" "$SW" \
      'Un recuento de 1 a 4, dicho «menos de 5».' \
      'Un recuento de 1 a 4, dicho «menos de cinco».'
fi

# ── T3d · «EXPLÍCAMELO CON IA» (§4.7 y §4.13; el techo, 12 KB, `#798`): el texto sale a un tercero —solo cifras de conjunto, y
# si algo tiene pinta de correo o teléfono NO se enseña—; lleva las de arriba y las plegadas que se salen de lo normal, cada una
# con la primera frase de su definición; juzga el cambio como la tarjeta; y el botón es de quien exporta, con su rastro.
SECCION=T3d
EX=app/Filament/Analytics/Explainer.php

mutar "la guarda deja pasar un correo o un teléfono" "$EX" \
  "        \$hits = preg_match_all(Contract::PII_VALUE_RE, \$composed['text']);" \
  '        $hits = 0;'

mutar "el modal pinta la caja de texto aunque la guarda lo rechazara" "resources/views/filament/pages/analytics/explain.blade.php" \
  "@if (\$explanation['refused'] || \$explanation['text'] === null)" \
  '@if (false)'

mutar "las de arriba incluyen las plegadas (vuelven las 58)" "app/Filament/Pages/AnalyticsPage.php" \
  '                if (is_subclass_of($widget, MetricsWidget::class) && ! $widget::FOLDED) {' \
  '                if (is_subclass_of($widget, MetricsWidget::class)) {'

mutar "una plegada fuera de lo normal no entra en su tabla" "$EX" \
  "            if (\$tab !== null && ! isset(\$seen[\$key])) {" \
  '            if (false) {'

mutar "la definición es el «¿Cómo se calcula?» entero" "$EX" \
  "        return preg_match('/^.+?(?:\\.(?=\\s|\$)|。)/u', \$how, \$m) === 1 ? \$m[0] : \$how;" \
  '        return $how;'

mutar "el texto juzga el cambio con mitad y mitad" "$EX" \
  '        $share = $comparison->share($window);' \
  '        $share = 0.5;'

mutar "el cambio se da como claro sin prueba" "$M" \
  '            $this->isClear($share) => self::SHIFT_CLEAR,' \
  '            true => self::SHIFT_CLEAR,'

mutar "lo que vende cuenta también lo que ya no se vende" "$EX" \
  "        \$types = TicketType::query()->where('is_active', true)->where('is_sellable', true)->distinct()->pluck('type')->all();" \
  "        \$types = TicketType::query()->where('is_sellable', true)->distinct()->pluck('type')->all();"

mutar "la media tapada vuelve a decir «menos de 5» (se lee como la nota)" "app/Filament/Analytics/Metrics/SurveysMetrics.php" \
  "            \$scale['suppressed'] || \$scale['mean'] === null => __('admin.analytics.surveys.mean_hidden')," \
  "            \$scale['suppressed'] || \$scale['mean'] === null => __('admin.analytics.surveys.fewer_than_min', ['min' => 5]),"

mutar "abrir el texto no deja rastro" "app/Filament/Pages/AnalyticsPage.php" \
  "                AuditLogger::log('analytics.explained', null, [" \
  "                if (false) AuditLogger::log('analytics.explained', null, ["

mutar "el botón es de quien ve el cuadro, no de quien exporta" "app/Filament/Pages/AnalyticsPage.php" \
  "            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasPermission(self::PERMISSION_EXPORT) ?? false)" \
  "            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasPermission(self::PERMISSION) ?? false)"

if [[ -z "$SOLO" || "$SOLO" == T3d ]]; then
    control "un comentario del texto para IA" "$EX" \
      'Una celda de tabla en una línea y sin romper la tabla.' \
      'Una celda de tabla en una sola línea y sin romper la tabla.'
fi

# ── T4 · LA CARTERA (§4.8.quater): lo ya vendido para lo que viene frente a «a estas alturas» —lo cobrado antes del instante y no
# cancelado antes—; los complementos con su visita; el año con −364 días o la media de cuatro semanas; la foto de antes de medir
# no vale (el margen, el p95 de la antelación); las semanas con el cambio de hora; y la cartera en «Resumen», en «lo que ha
# cambiado», en el texto para IA y en el CSV.
SECCION=T4
BR=app/Filament/Analytics/BookedReport.php
OR=app/Domain/Booking/Services/OccupancyReader.php
BOOKED_LINE="            if (\$line['date'] < \$from || \$line['date'] > \$to || \$line['paid_at'] > \$at || (\$line['cancelled_at'] !== null && \$line['cancelled_at'] <= \$at)) {"

mutar "una línea cobrada DESPUÉS de la foto cuenta en ella" "$BR" \
  "$BOOKED_LINE" \
  "            if (\$line['date'] < \$from || \$line['date'] > \$to || (\$line['cancelled_at'] !== null && \$line['cancelled_at'] <= \$at)) {"

mutar "una línea cancelada después de la foto deja de contar en ella" "$BR" \
  "$BOOKED_LINE" \
  "            if (\$line['date'] < \$from || \$line['date'] > \$to || \$line['paid_at'] > \$at || \$line['cancelled_at'] !== null) {"

mutar "un pedido cancelado SIN fecha cuenta" "$OR" \
  '                if ($row->status === Order::STATUS_CANCELLED && $cancelled === null) {' \
  '                if (false) {'

mutar "el complemento no cae con su principal cancelada" "$OR" \
  '                    $own === null => $parent,' \
  '                    $own === null => null,'

mutar "el complemento sin franja se queda fuera (no cuelga de su principal)" "$OR" \
  "            ->join('slots as s', 's.id', '=', DB::raw('COALESCE(p.slot_id, i.slot_id)'))" \
  "            ->join('slots as s', 's.id', '=', 'i.slot_id')"

mutar "el año con 365 días (deja de ser el mismo día de la semana)" "$BR" \
  '    public const YEAR_DAYS = 364;' \
  '    public const YEAR_DAYS = 365;'

mutar "la media de las cuatro semanas deja de ser una media" "$BR" \
  "                return ['seats' => \$sum['seats'] / count(\$fallback), 'cents' => \$sum['cents'] / count(\$fallback)];" \
  "                return ['seats' => (float) \$sum['seats'], 'cents' => (float) \$sum['cents']];"

mutar "una foto de antes de que el sistema midiera vale" "$BR" \
  '        $valid = static fn (CarbonImmutable $s): bool => $since !== null && $since->lessThanOrEqualTo($s->subDays($margin));' \
  '        $valid = static fn (CarbonImmutable $s): bool => $since !== null;'

mutar "el margen es siempre el mínimo (no sale de la antelación)" "$BR" \
  '        $margin = max(self::MIN_MARGIN_DAYS, self::percentile($leads, 0.95));' \
  '        $margin = self::MIN_MARGIN_DAYS;'

# (Una mutación que quitaba un `round()` de las semanas sobrevivió: Carbon ya da días enteros con el cambio de hora —medido—, y el
# `round()` era código muerto; se quitó. La prueba de primavera se queda: vigila el resultado.)
mutar "las semanas empiezan un día antes" "$BR" \
  '            $from = max(0, (int) $today->diffInDays($weeksStart->addDays(7 * $j), false));' \
  '            $from = max(0, (int) $today->diffInDays($weeksStart->addDays(7 * $j - 1), false));'

mutar "el cambio frente a «a estas alturas» sale al revés" "app/Filament/Analytics/Metrics/BookedMetrics.php" \
  "        \$delta = \$value > 0 ? ' ('.self::signed((int) round((\$metric->value - \$value) / \$value * 100)).')' : '';" \
  "        \$delta = \$value > 0 ? ' ('.self::signed((int) round((\$value - \$metric->value) / \$value * 100)).')' : '';"

mutar "el gráfico pierde «a estas alturas»" "app/Filament/Widgets/Analytics/BookedChart.php" \
  "        if (array_filter(array_column(\$weeks, 'baseline')) !== []) {" \
  '        if (false) {'

mutar "la cartera sale de «lo que ha cambiado»" "app/Filament/Analytics/Changes.php" \
  '            + BookedMetrics::for(),' \
  ''

mutar "el texto para IA pierde lo de «Resumen» plegado (la cartera)" "$EX" \
  '            if ($tab !== null && ! in_array($key, $top[$tab] ?? [], true)) {' \
  '            if (false) {'

mutar "«Resumen» pierde la cartera" "app/Filament/Widgets/Analytics/SummaryWidget.php" \
  "'surveys.scale_mean', 'booked.cents_30'];" \
  "'surveys.scale_mean'];"

mutar "el CSV de la ocupación pierde la cartera" "app/Filament/Analytics/CsvExport.php" \
  "            ...array_map(static fn (Metric \$m): array => [\$m->label, \$m->unit === Metric::UNIT_MONEY ? Money::format(\$m->value) : (string) \$m->value], array_values(BookedMetrics::for()))," \
  ''

if [[ -z "$SOLO" || "$SOLO" == T4 ]]; then
    control "un comentario de la cartera" "$BR" \
      'Las semanas del gráfico: la de hoy y las 12 siguientes (~90 días).' \
      'Las semanas del gráfico: la de hoy y las doce siguientes (~90 días).'
fi

# ── B3 · LA MEDIDA DEL EXPERIMENTO DE LA ISLA (la Z6c·3, `specs/analitica.md` §4.4): `isla_accion` por visita en móvil ─────
SECCION=B3
ER=app/Filament/Analytics/ExperimentsReport.php
EW=app/Filament/Widgets/Analytics/ExperimentsWidget.php
CT=app/Domain/Platform/Services/Analytics/Contract.php
mutar "B3 · una visita de otro dispositivo cuenta" "$ER" \
  "            ->where('s.device', \$medida['device'])
" ""
mutar "B3 · los robots y el tráfico interno cuentan en la medida" "$ER" \
  "            ->where('s.is_bot', false)
            ->where('s.is_internal', false)
            ->where('s.device', \$medida['device'])" \
  "            ->where('s.device', \$medida['device'])"
mutar "B3 · la visita con dos variantes cuenta" "$ER" \
  '$clean = array_filter($bySession, static fn (array $seen): bool => count($seen) === 1);' \
  '$clean = $bySession;'
mutar "B3 · un toque de antes de verla cuenta" "$ER" \
  'if ($gesture !== null && $gesture->gte($seen[$variantKey])) {' \
  'if ($gesture !== null) {'
mutar "B3 · la isla sin su medida" "$ER" \
  "public const GESTURES = ['isla' => ['event' => 'isla_accion', 'device' => Device::MOBILE]];" \
  'public const GESTURES = [];'
mutar "B3 · cuenta otro gesto" "$ER" \
  "['isla' => ['event' => 'isla_accion'," \
  "['isla' => ['event' => 'isla_panel',"
mutar "B3 · la tasa sin su intervalo" "$ER" \
  "self::wilson(\$counts['acted'], \$counts['visits'])" \
  "self::wilson(0, \$counts['visits'])"
mutar "B3 · el widget sin la tabla de la medida" "$EW" \
  "if ((\$experiment['gestures'] ?? null) !== null) {" \
  'if (false) {'
mutar "B3 · el contrato sin el toque de la isla" "$CT" \
  "        'isla_accion' => ['source' => self::CLIENT, 'props' => ['situacion', 'etiqueta', 'tono', 'cara', 'pagina', 'variante']],
" ""
mutar "B3 · el toque admite una prop de más" "$CT" \
  "'cara', 'pagina', 'variante']]," \
  "'cara', 'pagina', 'variante', 'de_mas']],"

# ── TA · Las altas por origen (`#876`, la fila 8 de la lista del owner; `analitica-para-decidir.md` §4.15) ──────
# TA·0, el defecto de la «demanda sin hueco» (contaba robots y personal: el embudo no); la TA, la campaña de su visita en el
# alta —siempre, y la visita solo con «análisis»—, el informe por origen (la web, el mostrador y el resto) y su tabla.
SECCION=TA
RC=app/Domain/Platform/Services/Analytics/Recorder.php
AC=app/Domain/Platform/Services/Analytics/AttributionContext.php
CBW=app/Filament/Widgets/Analytics/CustomersBreakdownWidget.php
mutar "TA·0 · la demanda sin hueco cuenta los robots" "$OP" \
  "            ->where('s.is_bot', false)
" ""
mutar "TA·0 · la demanda sin hueco cuenta al personal" "$OP" \
  "            ->where('s.is_internal', false);" \
  "            ;"
mutar "TA·0 · el periodo vuelve a contarlo todo" "$OP" \
  "\$rows = \$this->cleanEvents('availability_missing')" \
  "\$rows = DB::table('analytics_events as e')->where('e.name', 'availability_missing')"
mutar "TA·0 · la comparación vuelve a contarlo todo" "$OP" \
  "'missing' => (int) \$this->cleanEvents('availability_missing')" \
  "'missing' => (int) DB::table('analytics_events as e')->where('e.name', 'availability_missing')"
mutar "TA · el alta sin la marca de campaña" "$CT" \
  "'user_registered' => ['source' => self::SERVER, 'props' => ['method'], 'campaign' => true]," \
  "'user_registered' => ['source' => self::SERVER, 'props' => ['method']],"
mutar "TA · el contrato no admite la campaña" "$CT" \
  "return self::carriesCampaign(\$name) ? [...\$props, ...self::CAMPAIGN_PROPS] : \$props;" \
  "return \$props;"
mutar "TA · el Recorder no pone la campaña" "$RC" \
  "\$props += \$this->campaign();" \
  "\$props += [];"
mutar "TA · la campaña solo con «análisis»" "$RC" \
  "\$touch = \$this->context->currentTouch() ?? [];" \
  "\$touch = \$this->linksToVisitor() ? (\$this->context->currentTouch() ?? []) : [];"
mutar "TA · una campaña con pinta de dato personal viaja" "$RC" \
  " && ! Contract::looksLikePii(\$value)" \
  ""
mutar "TA · la visita viaja sin «análisis»" "$RC" \
  "return \$this->context->consented('analytics');" \
  "return true;"
mutar "TA · sin la visita en curso" "$AC" \
  "return \$this->session === null ? null : self::touch(\$this->session);" \
  "return null;"
mutar "TA · el informe no reparte por origen" "$CR" \
  "\$origins = ['web' => \$facts['by_origin'], 'counter' => \$trail['counter']];" \
  "\$origins = ['web' => [], 'counter' => \$trail['counter']];"
mutar "TA · el mostrador no cuenta" "$CR" \
  "'counter' => (int) (\$row->counter ?? 0)" \
  "'counter' => 0"
mutar "TA · «sin dato» no descuenta el mostrador" "$CR" \
  "array_sum(array_column(\$origins['web'], 'n')) - \$origins['counter']);" \
  "array_sum(array_column(\$origins['web'], 'n')));"
mutar "TA · un alta sin campaña sale como fila" "$CR" \
  "if (! is_string(\$row->source) || \$row->source === '') {" \
  "if (false) {"
mutar "TA · el método se pierde al juntar las consultas" "$CR" \
  "\$byMethod[\$key] += \$n;" \
  "\$byMethod[\$key] = \$n;"
mutar "TA · la tabla sin la fila del mostrador" "$CBW" \
  "        \$rows[] = [__('admin.analytics.customers.origin.counter'), '—', '—', (string) \$origins['counter']];
" ""
mutar "TA · la pestaña sin la tabla de las altas" "$CBW" \
  "                \$this->origins(\$report['registrations']['by_origin']),
" ""

# ── El CONTROL: tocar un comentario no puede poner nada en rojo ─────────────────────────────────
control "un comentario de Window" "$W" \
  'Un día' \
  'Un dia'

echo
echo "$muerden/$total muerden${SOLO:+ (solo la tanda $SOLO)}"
[[ $muerden -eq $total && $control_ok -eq 1 ]]
