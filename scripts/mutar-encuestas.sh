#!/usr/bin/env bash
# Arnés de mutación de las ENCUESTAS — la puerta, el correo, la página, el cuadro y, desde la T5, EL ANONIMATO
# (`specs/encuestas.md` §4.2, §4.3, §4.4, §4.7 y §6; `DECISIONES #740` y `#754`; `RGPD-01`, `RGPD-07`, `SEC-04`).
#
# Lo que las encuestas prometen y que no se ve leyendo: que se ofrecen SOLO con la visita acreditada, que «una por
# cliente y encuesta» es una regla y no una casualidad, que lo marcado se tipa y se valida en el servidor, que una
# encuesta apagada no se escribe, que el correo espera a su hora y respeta la baja y el plazo, que un token contestado
# no vuelve a abrir, que el CSV no lleva un texto libre. Y desde `#754`, que la respuesta NO LLEVA A NADIE: el sello va
# cifrado y se borra al volver, a los 90 días y al anonimizar (el de esa persona y ninguno más), la franja sale de la
# hora del parque, la clave es aleatoria, ninguna cifra sale de menos de 5, los textos no van por fecha, el rastro de
# la puerta no dice el desenlace, y solo `SurveySeals` lee el sello. Cada mutación rompe una de esas promesas SIN que
# nada falle a simple vista.
#
# Reglas de la casa: verde antes de mutar · comprobar que la mutación SE APLICÓ · veredicto por CÓDIGO DE SALIDA ·
# restaurar por COPIA y no con `git checkout` (`#181`) · árbol byte a byte (sha1) · CONTROL · superviviente
# DECLARADO con su razón.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

LW=app/Livewire/Admin/Puerta/ValidarRegistro.php
RES=app/Domain/Platform/Services/Surveys/SurveyResponses.php
SEALS=app/Domain/Platform/Services/Surveys/SurveySeals.php
SCH=app/Domain/Platform/Services/Surveys/QuestionSchema.php
MODEL=app/Domain/Platform/Models/SurveyResponse.php
USER=app/Domain/Identity/Models/User.php
EXP=app/Domain/Identity/Services/AccountPrivacy.php
CMD=app/Console/Commands/SendExternalSurveys.php
REP=app/Filament/Analytics/SurveysReport.php
BRK=app/Filament/Widgets/Analytics/SurveysBreakdownWidget.php
LOW=app/Filament/Widgets/Analytics/SurveysLowScoresWidget.php
TMP="$(mktemp -d)"
FICHEROS=("$LW" "$RES" "$SEALS" "$SCH" "$MODEL" "$USER" "$EXP" "$CMD" "$REP" "$BRK" "$LOW")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
SHA_ANTES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"

# Las guardas de las encuestas, de la T1 a la T5.
verde() { docker compose exec -u sail -T laravel.test php artisan test \
    --filter='GateSurveyTest|SurveyPrivacyTest|QuestionSchemaTest|SurveySendTest|SurveyPageTest|SurveysReportTest|SurveyReturnsTest|SurveyAnonymityTest|SurveyVisitFactsTest' >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; mutantes=0; controles=0; control_ok=1; no_aplicados=0; declarados=0; declarado_ok=1

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4" espera="${5:-muerde}"
    case "$espera" in
        control) controles=$((controles + 1)) ;;
        declarado) declarados=$((declarados + 1)) ;;
        *) mutantes=$((mutantes + 1)) ;;
    esac
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        case "$espera" in
            control) controles=$((controles - 1)) ;;
            declarado) declarados=$((declarados - 1)) ;;
            *) mutantes=$((mutantes - 1)) ;;
        esac
        no_aplicados=$((no_aplicados + 1))
        return
    fi
    touch "$fichero"
    if verde; then
        case "$espera" in
            control) echo "  ✓ CONTROL en verde, como debe: $nombre" ;;
            declarado) echo "  ◦ SOBREVIVE, declarado: $nombre" ;;
            *) echo "  ✗ NO muerde: $nombre" ;;
        esac
    else
        case "$espera" in
            control)
                echo "  ✗ CONTROL EN ROJO — el arnés acusa a lo que no cambia conducta: $nombre"
                control_ok=0 ;;
            declarado)
                echo "  ⚠ EL DECLARADO MUERDE — ha nacido una guarda: reclasifícalo: $nombre"
                declarado_ok=0 ;;
            *)
                echo "  ✓ muerde:    $nombre"
                muerden=$((muerden + 1)) ;;
        esac
    fi
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

echo '── Cuándo se ofrece: solo con la visita acreditada, y una vez por cliente ──────────────────────'

# ◦ DECLARADO (27-09, al rehacer el arnés para la T5): EQUIVALENTE desde `#756`. El escaneo (`#741`) y la búsqueda por
#   correo o móvil (`#756`) acreditan la visita ANTES de componer la ficha, y sin el permiso de la ficha no hay ficha: no
#   queda ninguna vía que abra una ficha sin la visita de hoy, y por eso ningún test puede distinguir la guarda de su
#   ausencia. Se deja en el código como defensa: si una vía futura abre la ficha sin acreditar (una «consulta»), la
#   encuesta seguirá sin ofrecerse. Si algún día MUERDE, ha nacido esa vía: reclasifícalo.
mutar "la interna se ofrece SIN acreditar la visita (equivalente desde #756)" "$LW" \
  "        if (! (bool) (\$this->profile['visit_registered_today'] ?? false)) {
            return;
        }" \
  "        if (false) {
            return;
        }" \
  declarado

mutar "a quien ya se le preguntó se le vuelve a ofrecer" "$RES" \
  "        return \$asked ? null : \$survey;" \
  "        return \$survey;"

echo '── El servidor tipa y valida: lo marcado no manda ───────────────────────────────────────────────'

mutar "una obligatoria en blanco pasa" "$SCH" \
  "                if (\$question['required']) {
                    \$errors[\$question['key']] = 'required';
                }" \
  "                if (false) {
                    \$errors[\$question['key']] = 'required';
                }"

mutar "un valor fuera de la escala pasa" "$SCH" \
  "            self::TYPE_SCALE => is_int(\$value) && \$value >= self::SCALE_MIN && \$value <= self::SCALE_MAX," \
  "            self::TYPE_SCALE => is_int(\$value) && \$value >= self::SCALE_MIN && \$value <= 99,"

mutar "el «no» de un sí/no se guarda como «sí»" "$SCH" \
  "                    \$value === false, \$value === 0, \$value === '0', \$value === 'false' => false," \
  "                    \$value === false, \$value === 0, \$value === '0', \$value === 'false' => true,"

mutar "las respuestas se guardan como llegaron del formulario, sin tipar" "$LW" \
  "answerInPerson(\$survey, (int) \$customer->getKey(), \$typed," \
  "answerInPerson(\$survey, (int) \$customer->getKey(), \$this->surveyAnswers,"

mutar "una encuesta apagada entre la oferta y el guardado se escribe igual" "$LW" \
  "        if (\$customer === null || \$survey === null || ! \$survey->isRunning()) {" \
  "        if (\$customer === null || \$survey === null) {"

echo '── T5 · el anonimato en la puerta: el rastro, la franja, el sello de quien no contestó ───────────'

mutar "el rastro de la puerta dice el desenlace (con la hora, destapa la respuesta)" "$LW" \
  "        AuditLogger::log('puerta.survey_closed', \$customer, ['survey' => \$survey->key]);" \
  "        AuditLogger::log('puerta.survey_closed', \$customer, ['survey' => \$survey->key, 'answered' => true]);"

mutar "la franja sale de la hora UTC y no de la del parque" "$RES" \
  "    private function closeInPerson(Survey \$survey, int \$userId, ?array \$answers, ?int \$askedBy): bool
    {
        \$now = DisplayTime::now();" \
  "    private function closeInPerson(Survey \$survey, int \$userId, ?array \$answers, ?int \$askedBy): bool
    {
        \$now = now();"

mutar "«no preguntar» también se sella (el sello solo es de quien puntuó)" "$RES" \
  "\$answers === null ? null : \$userId);" \
  "\$userId);"

echo '── T5 · el sello: cifrado, solo en SurveySeals, borrado al volver, a los 90 días y al anonimizar ────'

mutar "el sello guarda el cliente EN CLARO" "$SEALS" \
  "        \$response->forceFill(['seal' => Crypt::encryptString((string) \$userId)]);" \
  "        \$response->forceFill(['seal' => (string) \$userId]);"

mutar "al volver se anota «volvió» pero el sello se queda" "$SEALS" \
  "                            'returned_after_days' => (int) Carbon::parse(\$day)->diffInDays(Carbon::parse(\$first)),
                            'seal' => null," \
  "                            'returned_after_days' => (int) Carbon::parse(\$day)->diffInDays(Carbon::parse(\$first)),"

mutar "a los 90 días se anota «no volvió» pero el sello se queda" "$SEALS" \
  "update(['returned' => false, 'seal' => null]);" \
  "update(['returned' => false]);"

mutar "la visita de HOY cuenta como vuelta (días sin vivir)" "$SEALS" \
  "        \$lived = Carbon::parse(\$today)->subDay()->toDateString();" \
  "        \$lived = \$today;"

mutar "anonimizar NO borra el sello de esa persona" "$USER" \
  "            app(SurveySeals::class)->forget((int) \$this->getKey());" \
  "            // sin olvido del sello"

mutar "anonimizar borra los sellos de TODOS, no solo los suyos" "$SEALS" \
  "                    if (\$this->open((string) \$row->seal) === \$userId) {" \
  "                    if (\$this->open((string) \$row->seal) !== null) {"

mutar "el cuadro lee el sello (un lector nuevo, fuera de SurveySeals)" "$REP" \
  "            ->select(['id', 'survey_id', 'channel', 'answered_on', 'declined', 'answers', 'returned'])" \
  "            ->select(['id', 'survey_id', 'channel', 'answered_on', 'declined', 'answers', 'returned', 'seal'])"

mutar "la clave de la respuesta es v7, ordenada por tiempo" "$MODEL" \
  "                \$response->setAttribute(\$response->getKeyName(), (string) Str::uuid());" \
  "                \$response->setAttribute(\$response->getKeyName(), (string) Str::uuid7());"

echo '── T5 · el cuadro: el mínimo de 5, los textos sin fecha, «notas bajas» sin persona ───────────────'

mutar "una cifra de UNA respuesta se pinta (sin mínimo)" "$REP" \
  "        return \$n >= self::MIN_CELL;" \
  "        return \$n >= 1;"

mutar "el mínimo baja a 4" "$REP" \
  "    public const MIN_CELL = 5;" \
  "    public const MIN_CELL = 4;"

mutar "los textos salen por fecha de respuesta" "$REP" \
  "            ->select(['id', 'survey_id', 'channel', 'answered_on', 'declined', 'answers', 'returned'])
            ->orderBy('id')" \
  "            ->select(['id', 'survey_id', 'channel', 'answered_on', 'declined', 'answers', 'returned'])
            ->orderBy('answered_on')"

mutar "«volvió» de un grupo de menos de 5 se enseña" "$LOW" \
  "                \$known = SurveysReport::enough(\$base);" \
  "                \$known = true;"

mutar "una respuesta sin resolver de hace más de 90 días «aún puede volver» para siempre" "$REP" \
  "                \$out[\$group][substr((string) \$row->answered_on, 0, 10) >= \$stillOpenFrom ? 'pending' : 'unknown']++;" \
  "                \$out[\$group]['pending']++;"

mutar "las que «no se saben» cuentan en la tasa como un «no volvió»" "$LOW" \
  "                \$base = \$g['n'] - \$g['unknown'];" \
  "                \$base = \$g['n'];"

echo '── El export: la participación y lo aún sellado ─────────────────────────────────────────────────'

mutar "el export del titular olvida sus encuestas" "$EXP" \
  "            'surveys' => \$this->surveysFor(\$user)," \
  ""

echo '── El correo del día siguiente: la hora del parque, la baja, #742, el plazo, el token gastado ──────'

mutar "el comando manda a cualquier hora, sin esperar a las 10:00 del parque" "$CMD" \
  "        if (\$now->hour < self::FROM_HOUR && ! \$this->option('force')) {" \
  "        if (false) {"

mutar "el comando manda a quien se dio de baja" "$CMD" \
  "            ->where('surveys_opt_out', false)
" \
  ""

mutar "el correo se manda a quien ya contestó la interna en esa visita (#742)" "$CMD" \
  "            ->when(\$answeredInPerson !== [], static fn (Builder \$q) => \$q->whereNotIn('users.id', \$answeredInPerson))" \
  ""

mutar "el plazo entre dos encuestas se ignora" "$CMD" \
  "        \$cooldownSince = Carbon::now()->subDays(SurveySettings::cooldownDays());" \
  "        \$cooldownSince = Carbon::now();"

mutar "un token ya contestado vuelve a abrir la página" "$RES" \
  "        return \$this->isSpent(\$token) ? null : \$participation;" \
  "        return \$participation;"

mutar "contestar desde el correo no GASTA el token" "$RES" \
  "            try {
                DB::table('survey_spent_tokens')->insert(['hash' => self::tokenHash(\$token, self::HASH_SPENT)]);
            } catch (UniqueConstraintViolationException) {
                return false;
            }" \
  ""

echo '── T4 · el cuadro: el CSV no lleva un texto libre ───────────────────────────────────────────────'

mutar "el CSV lleva los textos libres (un texto puede llevar un nombre)" "$BRK" \
  "        if (\$this->forcedWindow === null) {" \
  "        if (true) {"

echo '── CONTROL ──────────────────────────────────────────────────────────────────────────────────────'

mutar "CONTROL: el orden de dos atributos de la participación cambia (nada de conducta)" "$RES" \
  "                'channel' => SurveyResponse::CHANNEL_INTERNAL,
                'asked_by' => \$askedBy," \
  "                'asked_by' => \$askedBy,
                'channel' => SurveyResponse::CHANNEL_INTERNAL," \
  control

SHA_DESPUES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"
if [[ "$SHA_ANTES" != "$SHA_DESPUES" ]]; then
    echo '✗ el árbol NO ha quedado como estaba: revisa a mano antes de hacer nada.' >&2
    exit 1
fi
echo '✓ árbol restaurado byte a byte'
echo "▶ mutaciones que muerden: $muerden/$mutantes · controles en verde: $controles · supervivientes declarados: $declarados · sin aplicar: $no_aplicados"
[[ "$muerden" == "$mutantes" && "$control_ok" == 1 && "$declarado_ok" == 1 && "$no_aplicados" == 0 ]] || exit 1
