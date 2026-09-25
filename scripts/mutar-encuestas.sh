#!/usr/bin/env bash
# Arnés de mutación de las ENCUESTAS — T2, la interna en la puerta (`specs/encuestas.md` §4.2 y §6;
# `DECISIONES #740`; `RGPD-01`, `RGPD-07`, `SEC-04`).
#
# Lo que la T2 promete y que no se ve leyendo: que la encuesta se ofrece SOLO con la visita acreditada, que una
# respuesta por cliente y encuesta es una regla y no una casualidad, que lo marcado se tipa y se valida en el
# servidor (una obligatoria en blanco y un valor imposible se rechazan; el «no» no se convierte en «sí»), que
# una encuesta apagada entre la oferta y el guardado no se escribe, que al libro va el HECHO y que el export del
# titular lleva sus respuestas. Cada mutación rompe una de esas promesas SIN que nada falle a simple vista.
#
# Reglas de la casa: verde antes de mutar · comprobar que la mutación SE APLICÓ · veredicto por CÓDIGO DE SALIDA ·
# restaurar por COPIA y no con `git checkout` (`#181`) · árbol byte a byte (sha1) · CONTROL · superviviente
# DECLARADO con su razón.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

LW=app/Livewire/Admin/Puerta/ValidarRegistro.php
RES=app/Domain/Platform/Services/Surveys/SurveyResponses.php
SCH=app/Domain/Platform/Services/Surveys/QuestionSchema.php
EXP=app/Domain/Identity/Services/AccountPrivacy.php
TMP="$(mktemp -d)"
FICHEROS=("$LW" "$RES" "$SCH" "$EXP")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
SHA_ANTES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"

# Las guardas de la T2, más las del esquema y de la privacidad de la T1.
verde() { docker compose exec -u sail -T laravel.test php artisan test \
    --filter='GateSurveyTest|SurveyPrivacyTest|QuestionSchemaTest' >/dev/null 2>&1; }

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

mutar "la interna se ofrece SIN acreditar la visita" "$LW" \
  "        if (! (bool) (\$this->profile['visit_registered_today'] ?? false)) {
            return;
        }" \
  "        if (false) {
            return;
        }"

mutar "una encuesta ya contestada (o declinada) se vuelve a ofrecer" "$RES" \
  "        return \$this->rowExists(\$survey, \$userId) ? null : \$survey;" \
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
  "            SurveyResponse::CHANNEL_INTERNAL,
            \$typed," \
  "            SurveyResponse::CHANNEL_INTERNAL,
            \$this->surveyAnswers,"

echo '── Los límites: la encuesta apagada, el libro, el export ────────────────────────────────────────'

mutar "una encuesta apagada entre la oferta y el guardado se escribe igual" "$LW" \
  "        if (\$customer === null || \$survey === null || ! \$survey->isRunning()) {" \
  "        if (\$customer === null || \$survey === null) {"

mutar "la respuesta no deja su hecho en el libro" "$RES" \
  "            \$this->recorder->fact('survey_answered', ['survey' => \$survey->key, 'channel' => \$channel], ['user_id' => \$userId]);" \
  "            // sin hecho"

mutar "el export del titular olvida sus encuestas" "$EXP" \
  "            'surveys' => \$this->surveysFor(\$user)," \
  ""

echo '── CONTROL ──────────────────────────────────────────────────────────────────────────────────────'

mutar "CONTROL: el orden de dos atributos de la fila cambia (nada de conducta)" "$RES" \
  "            'channel' => \$channel,
            'answered_by' => \$answeredBy,
            'visited_on' => \$visitedOn,
            'answered_at' => now()," \
  "            'answered_by' => \$answeredBy,
            'channel' => \$channel,
            'visited_on' => \$visitedOn,
            'answered_at' => now()," \
  control

SHA_DESPUES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"
if [[ "$SHA_ANTES" != "$SHA_DESPUES" ]]; then
    echo '✗ el árbol NO ha quedado como estaba: revisa a mano antes de hacer nada.' >&2
    exit 1
fi
echo '✓ árbol restaurado byte a byte'
echo "▶ mutaciones que muerden: $muerden/$mutantes · controles en verde: $controles · supervivientes declarados: $declarados · sin aplicar: $no_aplicados"
[[ "$muerden" == "$mutantes" && "$control_ok" == 1 && "$declarado_ok" == 1 && "$no_aplicados" == 0 ]] || exit 1
