#!/usr/bin/env bash
# Arnés de mutación de LA PUERTA NUEVA, P1 (`docs/specs/puerta-nueva.md` §4.4): el veredicto (`GateVerdict`), el
# enmascarado (`QueryMask`), lo que se pinta de la ficha (`FichaPuerta`), los campos nuevos de cada fila (`GateReservation`,
# `GateReservationsReader`, `GateProfile`), el eco del componente y las decisiones que fijan la vista y la hoja.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y que
# su ancla es ÚNICA · restaurar por COPIA DE SEGURIDAD y `touch`, no con `git checkout` (`#181`) · copia por RUTA.
#
# ⚠️ Lo que NO juzga (es la sonda y el ojo): cómo se ve, el sonido, el solape de tarjetas, los 44 px de verdad, que un toque
# no rehaga la ficha ni que la de varias marque de una en una. Para eso, `scripts/sonda-puerta-p1.mjs`.
#
#   bash scripts/mutar-puerta-p1.sh                  (todas)
#   SOLO='veredicto' bash scripts/mutar-puerta-p1.sh (las que llevan eso en su nombre)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

EXEC="docker compose exec -u sail -T laravel.test"
PHP='Puerta|GateKioskTest|GateVerdictTest|QueryMaskTest|FichaPuertaTest|WaiverGateTest|MixedPartyParkSurfacesTest|PanelSecretPathTest|SurveyInPersonStepsTest|QuestionSchemaTest|SurveyVisitFactsTest|SurveysReportTest|SurveySendTest|SurveyResourceTest'

TMP="$(mktemp -d)"
FICHEROS=(
    app/Domain/Platform/Services/Surveys/SurveyResponses.php
    app/Domain/Platform/Services/Surveys/QuestionSchema.php
    app/Domain/Identity/Services/CustomerVisitFacts.php
    app/Domain/Booking/Services/PaidVisitsReader.php
    app/Filament/Analytics/SurveysReport.php
    app/Filament/Resources/Surveys/Concerns/GuardsSurveyForm.php
    app/Console/Commands/SendExternalSurveys.php
    app/Livewire/Admin/Puerta/GateVerdict.php
    app/Livewire/Admin/Puerta/QueryMask.php
    app/Livewire/Admin/Puerta/FichaPuerta.php
    app/Livewire/Admin/Puerta/ValidarRegistro.php
    app/Domain/Booking/Services/GateReservationsReader.php
    app/Domain/Identity/Services/GateProfile.php
    resources/views/livewire/admin/puerta/validar.blade.php
    resources/css/filament/admin/puerta.css
)
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp -p "$f" "$(copia "$f")"; done

verde_php() { $EXEC php artisan test --parallel --filter="$PHP" >/dev/null 2>&1; }

if ! verde_php; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

# mutar <nombre> <fichero> <buscar> <poner>
mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    [[ -n "${SOLO:-}" && "$nombre" != *"$SOLO"* ]] && return
    total=$((total + 1))
    local veces
    veces=$(python3 -c 'import sys; print(open(sys.argv[1],encoding="utf-8").read().count(sys.argv[2]))' "$fichero" "$buscar")
    if [[ "$veces" != "1" ]]; then
        echo "  ⚠ «$nombre»: el ancla aparece $veces veces (tiene que ser UNA): el veredicto no vale"
        return
    fi
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$(copia "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ: el veredicto no vale"
        return
    fi
    touch "$fichero"
    if verde_php; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

VEREDICTO=app/Livewire/Admin/Puerta/GateVerdict.php
MASCARA=app/Livewire/Admin/Puerta/QueryMask.php
FICHA=app/Livewire/Admin/Puerta/FichaPuerta.php
COMPONENTE=app/Livewire/Admin/Puerta/ValidarRegistro.php
LECTOR=app/Domain/Booking/Services/GateReservationsReader.php
PERFIL=app/Domain/Identity/Services/GateProfile.php
VISTA=resources/views/livewire/admin/puerta/validar.blade.php
HOJA=resources/css/filament/admin/puerta.css

# ── El veredicto ─────────────────────────────────────────────────────────────────────────────────────────────
mutar "veredicto · los menores a cargo no cuentan" "$VEREDICTO" \
  "if (is_array(\$minor) && (\$minor['waiver'] ?? null) === 'missing') {" "if (false) {"
mutar "veredicto · una versión anterior frena" "$VEREDICTO" \
  "(\$minor['waiver'] ?? null) === 'missing'" "in_array(\$minor['waiver'] ?? null, ['missing', 'outdated'], true)"
mutar "veredicto · con el descargo apagado no es verde" "$VEREDICTO" \
  "        if (! (\$waiver['enabled'] ?? false)) {
            return true;
        }
        if (! (\$waiver['signed'] ?? false)) {" "        if (! (\$waiver['signed'] ?? false)) {"
mutar "veredicto · «No encontrado» deja de ser el rojo" "$VEREDICTO" \
  "STATUS_NOT_REGISTERED => [self::ROJO," "STATUS_NOT_REGISTERED => [self::GRIS,"
mutar "veredicto · el gris suena" "$VEREDICTO" \
  "'sound' => \$notice || \$tone === self::GRIS ? null : \$tone," "'sound' => \$notice ? null : \$tone,"
mutar "veredicto · sin ficha, el semáforo pierde la fecha del descargo" "$VEREDICTO" \
  "ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER => trim(" "'_' => trim("

# ── El enmascarado ───────────────────────────────────────────────────────────────────────────────────────────
mutar "máscara · el correo enseña una letra de más" "$MASCARA" "mb_substr(\$user, 0, 2)" "mb_substr(\$user, 0, 3)"
mutar "máscara · el teléfono enseña una cifra de más" "$MASCARA" "substr(\$digits, -3)" "substr(\$digits, -4)"
mutar "máscara · el eco va entero" "$COMPONENTE" \
  "\$echo = \$type === self::INPUT_EMAIL ? QueryMask::email(mb_strtolower(\$raw)) : QueryMask::phone(\$raw);" "\$echo = \$raw;"

# ── Lo que se pinta de la ficha ──────────────────────────────────────────────────────────────────────────────
mutar "ficha · un cumpleaños cuenta su dinero" "$FICHA" \
  "'dinero' => \$fiesta ? null : self::dinero(\$r)," "'dinero' => self::dinero(\$r),"
mutar "ficha · un libro que no cuadra dice «Pagado»" "$FICHA" \
  "if (\$cents <= 0 || (\$r['balance_kind'] ?? Balance::KIND_UNDER_REVIEW) === Balance::KIND_UNDER_REVIEW) {" "if (\$cents <= 0) {"
mutar "ficha · lo que se devuelve sale con su signo" "$FICHA" \
  "Money::showcaseWithSymbol(-\$cents)" "Money::showcaseWithSymbol(\$cents)"
mutar "ficha · una clase que falta se lee como pagado" "$FICHA" \
  "\$kind = (string) (\$r['balance_kind'] ?? Balance::KIND_UNDER_REVIEW);" "\$kind = (string) (\$r['balance_kind'] ?? Balance::KIND_SETTLED);"
mutar "ficha · el descargo vigente también se rotula (#320)" "$FICHA" \
  "            'outdated' => __(self::T.'descargo_antiguo'),
            default => null," "            'outdated' => __(self::T.'descargo_antiguo'),
            default => 'vigente',"
mutar "ficha · quien cumple no va el primero" "$FICHA" \
  "        usort(\$hijos, static fn (array \$a, array \$b): int => (int) \$b['cumple'] <=> (int) \$a['cumple']);
" ""
mutar "ficha · los niños de la fiesta salen con nombre (#817)" "$FICHA" \
  " || in_array(\$g['order_code'] ?? null, \$fiestas, true)" ""
mutar "ficha · «añade a tus hijos» también si puede entrar un adulto" "$FICHA" \
  "(bool) (\$r['minors_only'] ?? false) && ! (\$r['is_party'] ?? false)" "! (\$r['is_party'] ?? false)"
mutar "ficha · la fila no separa las horas" "$FICHA" \
  "(string) (\$r['zone_slug'] ?? \$zona), (string) (\$r['start_time'] ?? '')," "(string) (\$r['zone_slug'] ?? \$zona), '',"
mutar "ficha · los de solo menores no van primero" "$FICHA" \
  "'orden' => [(bool) (\$r['minors_only'] ?? false) ? 0 : 1," "'orden' => [1,"
mutar "ficha · un cumpleaños sin duración «llega cuando llegue»" "$FICHA" \
  "if ((\$r['duration_minutes'] ?? null) === null && ! (\$r['is_party'] ?? false)) {" "if ((\$r['duration_minutes'] ?? null) === null) {"
mutar "ficha · a la hora en punto dice «empieza en 0 min»" "$FICHA" \
  "            \$minutos > 0 => __(self::T.'hora_empieza'" "            \$minutos >= 0 => __(self::T.'hora_empieza'"
mutar "ficha · el otro día se cuenta aunque haya reserva hoy" "$FICHA" \
  "'otros_dias' => \$today === [] ? array_map(" "'otros_dias' => true ? array_map("
mutar "ficha · la fiesta no cuenta sus autorizaciones" "$FICHA" \
  "__(self::T.'fiesta', ['firmados' => (int) (\$c['signed'] ?? 0)," "__(self::T.'fiesta', ['firmados' => 0,"

# ── Los campos nuevos de cada fila ───────────────────────────────────────────────────────────────────────────
mutar "fila · la mayoría de edad cuenta como menor" "$PERFIL" \
  "\$r->guestAgeMax < Dependent::ADULT_AGE" "\$r->guestAgeMax <= Dependent::ADULT_AGE"
mutar "fila · la ilimitada tiene duración" "$LECTOR" \
  "\$item->ticketType === null || \$item->ticketType->isUnlimited() ? null : (int) \$item->ticketType->duration_min" "(int) \$item->ticketType?->duration_min"
mutar "fila · la hora de inicio con segundos" "$LECTOR" \
  "substr((string) \$item->slot->start_time, 0, 5)" "substr((string) \$item->slot->start_time, 0, 8)"
mutar "fila · un pack no es un cumpleaños" "$LECTOR" \
  "isParty: \$item->ticketType?->type === TicketType::TYPE_PACK," "isParty: false,"
mutar "fila · la zona por consulta suelta (N+1)" "$LECTOR" \
  "'ticketType.zone', 'slot'," "'ticketType', 'slot',"

# ── La vista y la hoja (las decisiones que fijan las pruebas) ────────────────────────────────────────────────
mutar "vista · «Nueva búsqueda» solo con ficha (privacidad, #817)" "$VISTA" \
  "    @if (\$verdict !== null)
        <footer class=\"ppu-pie gate-foot\">" "    @if (\$ficha !== null)
        <footer class=\"ppu-pie gate-foot\">"
mutar "vista · la doble lectura llega a Livewire" "$VISTA" \
  "\$event.preventDefault(); \$event.stopImmediatePropagation();" "\$event.preventDefault();"
mutar "vista · «Sus hijos» sin el estado de su descargo" "$VISTA" \
  " data-gate-minor-waiver=\"{{ \$hijo['descargo'] }}\"" ""
mutar "vista · el veredicto sin la marca del descargo del titular" "$VISTA" \
  " data-gate-waiver=\"{{ \$ficha['descargo'] }}\"" ""
mutar "hoja · un botón de 40 px" "$HOJA" \
  "    min-width: 44px;
    min-height: 44px;
    padding: 0 18px;" "    min-width: 44px;
    min-height: 40px;
    padding: 0 18px;"
mutar "hoja · la pantalla se desplaza como documento" "$HOJA" \
  "    height: 100dvh;" "    min-height: 100dvh;"

# ── La P1b: la encuesta pregunta a pregunta (`#817`·3) ───────────────────────────────────────────────────────
RESPUESTAS=app/Domain/Platform/Services/Surveys/SurveyResponses.php
mutar "encuesta · completar pisa lo contestado" "$RESPUESTAS" \
  "\$response->answers = \$ya + \$nuevas;" "\$response->answers = \$answers + \$ya;"
mutar "encuesta · completa la fila de OTRO empleado" "$RESPUESTAS" \
  "                ->where('asked_by', \$askedBy)
" ""
mutar "encuesta · completa una declinada" "$RESPUESTAS" \
  "                ->where('declined', false)
" ""
mutar "encuesta · completa una de otro día" "$RESPUESTAS" \
  "                ->whereDate('answered_on', DisplayTime::today()->toDateString())
" ""
mutar "encuesta · el primer toque no sella" "$RESPUESTAS" \
  "\$answers, \$answers === null ? null : \$userId)->getKey();" "\$answers, null)->getKey();"
mutar "encuesta · manda la copia del navegador" "$COMPONENTE" \
  "        \$saved = \$survey->questionList();" "        \$saved = array_values((array) (\$this->survey['questions'] ?? []));"
mutar "encuesta · un valor imposible se guarda" "$COMPONENTE" \
  "                if (! QuestionSchema::accepts(\$question, \$typed[\$key])) {" "                if (false) {"
mutar "encuesta · «Ahora no» con algo contestado declina" "$COMPONENTE" \
  "        if (\$this->surveyResponseId !== null) {
            \$this->survey['state'] = 'answered';

            return;
        }

        if (app(SurveyResponses::class)->declineInPerson(" "        if (app(SurveyResponses::class)->declineInPerson("
mutar "encuesta · no pasa a la siguiente" "$COMPONENTE" \
  "\$this->survey['step'] = \$step + 1;" "\$this->survey['step'] = \$step;"
mutar "encuesta · se pinta también en ámbar" "$VISTA" \
  "\$conEncuesta = \$survey !== null && \$verdict['tone'] === \\App\\Livewire\\Admin\\Puerta\\GateVerdict::VERDE;" "\$conEncuesta = \$survey !== null;"
mutar "encuesta · el aviso del anonimato en cada pregunta" "$VISTA" \
  "                                    @if (\$paso === 0)" "                                    @if (true)"

# ── La P1b, robusta (el owner, 02-10: la «recarga» y la de varias que las marcaba todas) ─────────────────────────────
mutar "encuesta · contesta una pregunta que no está en pantalla (el doble toque)" "$COMPONENTE" \
  "        if ((\$this->survey['questions'][\$step]['key'] ?? null) !== \$key) {" "        if (false) {"
mutar "encuesta · la tarjeta no está bloqueada" "$COMPONENTE" \
  "    #[Locked]
    public ?array \$survey = null;" "    public ?array \$survey = null;"
mutar "encuesta · un carné tecleado en un texto se guarda" "$COMPONENTE" \
  "            \$card = self::cardIn(\$value);" "            \$card = null;"
mutar "encuesta · un texto con forma de carné se busca" "$COMPONENTE" \
  "            if (CardToken::isWellFormed(\$candidate)) {" "            if (CardToken::looksLike(\$candidate)) {"
mutar "encuesta · la de varias guarda cada opción las veces que llegue" "app/Domain/Platform/Services/Surveys/QuestionSchema.php" \
  "array_values(array_unique(array_filter(\$value, 'is_string')))" "array_values(array_filter(\$value, 'is_string'))"
mutar "lectura · no sube con cada búsqueda" "$COMPONENTE" \
  "        \$this->lectura++;
" ""
mutar "vista · la clave de la ficha lleva el reloj (cada toque la rehace)" "$VISTA" \
  "wire:key=\"ficha-{{ \$lectura }}-{{ \$profile['user_id'] }}\"" "wire:key=\"ficha-{{ \$lectura }}-{{ \$profile['user_id'] }}-{{ \$profile['expires_at'] }}\""
mutar "ficha · «Dar por firmado» deja un «Resultado para» en blanco" "$COMPONENTE" \
  "\$this->stateFor(\$customer, is_string(\$eco) ? \$eco : null);" "\$this->stateFor(\$customer, (string) (\$eco ?? ''));"
mutar "vista · el veredicto no vuelve a entrar al cambiar de tono" "$VISTA" \
  "wire:key=\"veredicto-{{ \$lectura }}-{{ \$verdict['tone'] }}\"" "wire:key=\"veredicto-{{ \$lectura }}\""

# ── La P1c: a quién y «Ahora no» (`#819`) ────────────────────────────────────────────────────────────────────────────
# ⚠️ Sin mutante, a propósito: la FECHA en la clave de «Ahora no» y su caducidad al acabar el día son dos seguros de lo
# mismo (la fecha es la regla; la caducidad, la limpieza): quitar uno solo deja el otro y el mutante sería equivalente.
mutar "a quién · la puerta no mira la primera visita" "$RESPUESTAS" \
  "        if (\$survey->onlyFirstVisit() && ! \$this->visits->isFirstVisit(\$userId, \$today)) {" "        if (false) {"
mutar "a quién · el correo no mira la primera visita" "app/Console/Commands/SendExternalSurveys.php" \
  "                if (\$survey->onlyFirstVisit() && ! \$visits->isFirstVisit((int) \$user->getKey(), \$yesterday)) {" "                if (false) {"
mutar "a quién · la tasa cuenta todas las visitas" "app/Filament/Analytics/SurveysReport.php" \
  "\$survey['audience'] === Survey::AUDIENCE_FIRST_VISIT ? \$this->firstVisitsAmong(\$visits) : \$visits->count();" "\$visits->count();"
mutar "a quién · el primer día no mira los días cobrados" "app/Domain/Identity/Services/CustomerVisitFacts.php" \
  "        foreach (\$this->paid->firstPaidDays(\$ids) as \$userId => \$day) {" "        foreach ([] as \$userId => \$day) {"
mutar "a quién · el primer día no es el menor" "app/Domain/Identity/Services/CustomerVisitFacts.php" \
  "            if (! isset(\$first[\$userId]) || \$day < \$first[\$userId]) {" "            if (! isset(\$first[\$userId]) || \$day > \$first[\$userId]) {"
mutar "a quién · un día cobrado incluye lo que no se pagó" "app/Domain/Booking/Services/PaidVisitsReader.php" \
  "                ->paidScheduledPrincipal()
                ->whereHas('order', static fn (\$query) => \$query->whereIn('user_id', \$chunk))" "                ->whereHas('order', static fn (\$query) => \$query->whereIn('user_id', \$chunk))"
mutar "a quién · con respuestas se puede cambiar" "app/Filament/Resources/Surveys/Concerns/GuardsSurveyForm.php" \
  "            \$data['audience'] = \$record->audience;
" ""
mutar "a quién · un valor forjado entra" "app/Filament/Resources/Surveys/Concerns/GuardsSurveyForm.php" \
  "in_array(\$data['audience'] ?? null, Survey::AUDIENCES, true) ? \$data['audience'] : Survey::AUDIENCE_ALL;" "\$data['audience'] ?? Survey::AUDIENCE_ALL;"
mutar "ahora no · escribe la fila «no preguntar»" "$COMPONENTE" \
  "        app(SurveyResponses::class)->postponeInPerson(\$survey, (int) \$customer->getKey());" "        app(SurveyResponses::class)->declineInPerson(\$survey, (int) \$customer->getKey(), null);"
mutar "ahora no · la tarjeta se queda" "$COMPONENTE" \
  "        app(SurveyResponses::class)->postponeInPerson(\$survey, (int) \$customer->getKey());
        \$this->forgetSurvey();" "        app(SurveyResponses::class)->postponeInPerson(\$survey, (int) \$customer->getKey());"
mutar "ahora no · vuelve a salir el mismo día" "$RESPUESTAS" \
  "        if (Cache::has(self::postponedKey((int) \$survey->getKey(), \$userId, \$today))) {" "        if (false) {"

echo
echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
