#!/usr/bin/env bash
# Arnés de mutación de la ANALÍTICA DE LA FIESTA — T1, el régimen del invitado y los hechos de la reserva
# (`specs/analitica-fiesta.md` §4.1, §4.2 y §6; `DECISIONES #739`; `RGPD-02`, `RGPD-07`).
#
# Lo que la T1 promete y que no se ve leyendo: que el invitado no recibe la cookie de medición, que un hecho de
# la fiesta no se ata a nadie (ni al titular con sesión ni a una cookie de otra visita), que un robot no cuenta,
# que los días hasta la fiesta son exactos, que la firma cuenta solo cuando se CREA y que un nombre fuera del
# contrato se rechaza. Cada mutación rompe una de esas promesas SIN que nada falle a simple vista; el arnés dice
# si la suite la caza.
#
# Reglas de la casa: verde antes de mutar · comprobar que la mutación SE APLICÓ · veredicto por CÓDIGO DE SALIDA ·
# restaurar por COPIA y no con `git checkout` (`#181`) · árbol byte a byte (sha1) · CONTROL · superviviente
# DECLARADO con su razón (esconderlo bajando el denominador es mentir en el informe).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUT=routes/web.php
REC=app/Domain/Platform/Services/Analytics/Recorder.php
CON=app/Domain/Platform/Services/Analytics/Contract.php
DIAS=app/Domain/Platform/Services/Analytics/PartyFacts.php
TRT=app/Http/Concerns/RecordsPartyFacts.php
AUT=app/Http/Controllers/GuardianAuthorizationController.php
INV=app/Http/Controllers/InvitationPageController.php
TMP="$(mktemp -d)"
FICHEROS=("$RUT" "$REC" "$CON" "$DIAS" "$TRT" "$AUT" "$INV")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done
SHA_ANTES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"

# Las guardas de la T1 de la fiesta, más las del contrato y de los hechos de servidor que ya existían.
verde() { docker compose exec -u sail -T laravel.test php artisan test \
    --filter='FocusedPagesAreCookieFreeTest|PartyFactsTest|AnalyticsContractTest|ServerEventsTest' >/dev/null 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; mutantes=0; controles=0; control_ok=1; no_aplicados=0; declarados=0; declarado_ok=1

# Tres clases de mutación:
#   · `muerde`    — tiene que poner la suite en rojo.
#   · `control`   — no cambia conducta; tiene que quedarse en verde.
#   · `declarado` — sobrevive por diseño, con su razón escrita. Si un día MUERDE, ha nacido una guarda.
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

echo '── El invitado no es un visitante: sin cookie de medición en las páginas enfocadas ─────────────'

mutar "el grupo de las páginas enfocadas vuelve a acuñar la cookie del visitante" "$RUT" \
  "Route::withoutMiddleware([ResolveVisitor::class.':'.ResolveVisitor::MINT])->group(function (): void {" \
  "Route::group([], function (): void {"

echo '── Un hecho de la reserva no es de nadie ────────────────────────────────────────────────────────'

mutar "un hecho del pedido se ata al titular con sesión" "$REC" \
  "            'visitor_id' => null,
            'user_id' => null,
            'order_id' => \$orderId," \
  "            'visitor_id' => null,
            'user_id' => auth()->id(),
            'order_id' => \$orderId,"

mutar "un hecho del pedido se ata a la cookie que traiga la petición" "$REC" \
  "            'session_id' => null,
            'visitor_id' => null,
            'user_id' => null," \
  "            'session_id' => null,
            'visitor_id' => \$this->context->visitorId(),
            'user_id' => null,"

mutar "un nombre fuera del contrato entra en el libro como hecho del pedido" "$REC" \
  "        if (! Contract::isServer(\$name)) {
            Log::warning('analytics.record_refused', ['event' => \$name, 'reason' => 'not a server fact']);

            return;
        }

        \$this->write(\$name, \$this->allowed(\$name, \$props), [
            'session_id' => null," \
  "        \$this->write(\$name, \$this->allowed(\$name, \$props), [
            'session_id' => null,"

mutar "\`invitation_viewed\` desaparece del contrato (el registrador lo rechaza en silencio)" "$CON" \
  "        'invitation_viewed' => ['source' => self::SERVER, 'props' => ['days_before', 'device', 'locale']],
" \
  ""

echo '── Un robot no cuenta; los días son del parque y exactos ────────────────────────────────────────'

mutar "las vistas previas de los chats (robots) cuentan como aperturas" "$TRT" \
  "        if (Device::isBot(\$request->userAgent())) {" \
  "        if (false) {"

mutar "los días hasta la fiesta salen con uno de más" "$DIAS" \
  "        return (int) \$today->diffInDays(\$party, false);" \
  "        return (int) \$today->diffInDays(\$party, false) + 1;"

echo '── La firma cuenta solo cuando se CREA; el «no» también es una respuesta ────────────────────────'

mutar "el reenvío del mismo padre cuenta como segunda firma" "$AUT" \
  "        if (\$result['created']) {
            \$opened" \
  "        if (true) {
            \$opened"

mutar "un «no» se anota como «sí»" "$INV" \
  "                'attending' => \$data['attending'] === '1' ? 'yes' : 'no'," \
  "                'attending' => 'yes',"

echo '── CONTROL ──────────────────────────────────────────────────────────────────────────────────────'

mutar "CONTROL: el orden de dos props del contrato cambia (nada de conducta)" "$CON" \
  "        'authorization_opened' => ['source' => self::SERVER, 'props' => ['via', 'days_before', 'device']]," \
  "        'authorization_opened' => ['source' => self::SERVER, 'props' => ['device', 'via', 'days_before']]," \
  control

SHA_DESPUES="$(sha1sum "${FICHEROS[@]}" | sha1sum)"
if [[ "$SHA_ANTES" != "$SHA_DESPUES" ]]; then
    echo '✗ el árbol NO ha quedado como estaba: revisa a mano antes de hacer nada.' >&2
    exit 1
fi
echo '✓ árbol restaurado byte a byte'
echo "▶ mutaciones que muerden: $muerden/$mutantes · controles en verde: $controles · supervivientes declarados: $declarados · sin aplicar: $no_aplicados"
[[ "$muerden" == "$mutantes" && "$control_ok" == 1 && "$declarado_ok" == 1 && "$no_aplicados" == 0 ]] || exit 1
