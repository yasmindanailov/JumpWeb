#!/usr/bin/env bash
# Arnés de mutación de la Z6a DE LA ISLA (zip (6), «tres huecos, cristal y morph»; `specs/isla-y-landing-nueva.md` §4.27):
# `situacion.test.js`, `forma.test.js` y `asentado-cruce.test.js` contra la tabla de situaciones (`situacion.js`), la forma
# (`forma.js`), el asentado (`useAsentado.js`) y el cruce (`useCruce.js`). Cada mutante devuelve UNA decisión del 27-09 o
# rompe UNA de la Z6a: la acción que se cede, la secundaria que no llega, la reserva a medias que baja, la tarea y la
# reserva de hoy que vuelven a mandar en la barra, [Hoy] sin la frase entera, el elegido sin acción, la isla cedida, la
# frase con el menú abierto, la tinta en vez del cristal, las dos velocidades, la capa grande con rebote, lo que trae el
# scroll sin asentar, la otra clave que no reinicia el reloj, lo que se va que es lo nuevo, la llegada que cruza y lo que
# se va que no se quita.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y
# que su ancla está UNA vez (se cuenta en Python: hay anclas de dos líneas) · restaurar por COPIA DE SEGURIDAD (por RUTA,
# nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUN="docker compose exec -u sail -T laravel.test node --test resources/js/isla/situacion.test.js resources/js/isla/forma.test.js resources/js/isla/asentado-cruce.test.js"

SIT=resources/js/isla/situacion.js
FORMA=resources/js/isla/forma.js
ASENT=resources/js/isla/useAsentado.js
CRUCE=resources/js/isla/useCruce.js
FICHEROS=("$SIT" "$FORMA" "$ASENT" "$CRUCE")

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

# ── La tabla de situaciones ───────────────────────────────────────────────────────────────────────────────────────────
mutar "con un botón de la página a la vista, la acción se CEDE (el 27-09)" "$SIT" \
  "    const act = p.page.action || { label: t(m, 'accion.reservar') };" \
  "    const act = p.ctaVisible ? null : (p.page.action || { label: t(m, 'accion.reservar') });"
mutar "la secundaria no llega nunca (calm siempre falso)" "$SIT" \
  "        if (s.calm === undefined) s.calm = !s.locked && !s.urgent && Boolean(p.ctaVisible);" \
  "        if (s.calm === undefined) s.calm = false;"
mutar "la reserva a medias baja a secundaria (sin \`urgent\`)" "$SIT" \
  "tone: 'alert', urgent: true, action: { label: t(m, 'accion.seguir')" \
  "tone: 'alert', action: { label: t(m, 'accion.seguir')"
mutar "la TAREA vuelve a mandar en la barra" "$SIT" \
  "    { id: 'elegido', when: (p) => p.chosen," \
  "    { id: 'tarea', when: (p) => p.task, read: (p) => ({ line: p.task.text, tone: 'alert', action: p.task.action }) },
    { id: 'elegido', when: (p) => p.chosen,"
mutar "la RESERVA DE HOY vuelve a mandar en la barra" "$SIT" \
  "    { id: 'pago-fallido', when: (p) => p.payment === 'failed'," \
  "    { id: 'reserva-hoy', when: (p) => p.bookingToday, read: (p) => ({ line: p.bookingToday.text, tone: 'live' }) },
    { id: 'pago-fallido', when: (p) => p.payment === 'failed',"
mutar "[Hoy] antes de abrir, sin la frase entera de la cabecera" "$SIT" \
  "        ? (hoy.closesAt" \
  "        ? (false"
mutar "con el widget a la vista, el elegido se queda SIN acción (el 27-09)" "$SIT" \
  "            action: { label: p.chosen.label || t(m, 'accion.pagar_senal'), onClick: p.chosen.onClick } }) }," \
  "            action: (p.chosen.widgetVisible || p.ctaVisible) ? null : { label: p.chosen.label || t(m, 'accion.pagar_senal'), onClick: p.chosen.onClick } }) },"
mutar "la isla CEDIDA vuelve: en secundaria se encoge a fila" "$SIT" \
  "    return { hasLine, row: top };" \
  "    return { hasLine, row: top || Boolean(s.calm) };"
mutar "con el menú abierto, la frase se queda" "$SIT" \
  "    const hasLine = Boolean(s.line) && !inCheckout && !menuOpen;" \
  "    const hasLine = Boolean(s.line) && !inCheckout;"

# ── La forma ──────────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "la isla vuelve a la TINTA (sin cristal)" "$FORMA" \
  "        background: lee ? 'var(--ink-surface)' : 'var(--surface-glass-ink-float)'," \
  "        background: 'var(--ink-surface)',"
mutar "lo que trae el scroll, igual de rápido dentro (sin pasar la calma a lo que se cruza)" "$FORMA" \
  "        '--dur-island': rapido ? undefined : 'var(--dur-island-calma)'," \
  "        '--dur-island': undefined,"
mutar "el morph sin la velocidad de la calma" "$FORMA" \
  "tamanoEn(rapido ? 'var(--dur-island)' : 'var(--dur-island-calma)', 'var(--ease-island)')" \
  "tamanoEn('var(--dur-island)', 'var(--ease-island)')"
mutar "la capa grande con REBOTE (sin calma)" "$FORMA" \
  "    const tamano = calm
        ? tamanoEn(inCheckout ? 'var(--dur-slow)' : 'var(--dur-close)')" \
  "    const tamano = false
        ? tamanoEn(inCheckout ? 'var(--dur-slow)' : 'var(--dur-close)')"

# ── El asentado y el cruce ────────────────────────────────────────────────────────────────────────────────────────────
mutar "lo que trae el scroll manda AL MOMENTO (sin asentar)" "$ASENT" \
  "        if (! ms || manda.value.clave === k) {" \
  "        if (true) {"
mutar "otra clave NO reinicia el reloj (bajando deprisa, manda la de en medio)" "$ASENT" \
  "        if (pendiente && pendiente.clave === k && pendiente.ms === ms) return;" \
  "        if (pendiente) return;"
mutar "lo que se va es LO NUEVO" "$CRUCE" \
  "            if (anima && previa.sit.line) c.lineaSale = previa.sit;" \
  "            if (anima && sit.line) c.lineaSale = sit;"
mutar "la LLEGADA también cruza" "$CRUCE" \
  "        const anima = animate.value;" \
  "        const anima = true;"
mutar "lo que se va NO se quita" "$CRUCE" \
  "            reloj = setTimeout(() => { cruce.value = { ...cruce.value, lineaSale: null, accSale: null }; }, 480);" \
  "            reloj = null;"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
