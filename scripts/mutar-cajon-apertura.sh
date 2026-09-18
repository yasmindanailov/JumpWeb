#!/usr/bin/env bash
# Arnés de mutación de la APERTURA DEL CAJÓN sin framework (F4 · T2, `docs/specs/cajon-empaquetable.md` §4.2).
#
# La T2 mudó el store `purchase` de Alpine a `resources/js/cajon/controller.js` y RE-APUNTÓ a ese fichero
# cuatro guardas que leían `app.js` como texto. Una guarda re-apuntada que no se ha visto morder en su sitio
# nuevo puede estar vigilando la nada (la lección de `SEC-06`: «cita re-apuntada y MEDIDA, no supuesta»). Aquí
# se muta cada cadena que esas guardas buscan, EN EL FICHERO NUEVO, más lo que añade la tanda: los eventos, los
# atributos `data-jw-*`, el motor que ya no nombra a Alpine y el proxy de `window.JumpWeb.cajon`.
#
# ⚠️ Lo que este arnés NO puede ver es lo que de verdad importa —que la carcasa se mueva—: eso lo dice el
# navegador (`scripts/sonda-cajon-apertura.mjs`, 17 comprobaciones, vista en rojo con el proxy quitado).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"

CTRL=resources/js/cajon/controller.js
DECL=resources/js/cajon/declarative.js
APP=resources/js/app.js
GAINED=resources/js/sidebar/account/session-gained.js
# ⚠️ `CARCASA` y no `SHELL`: esa es una variable del propio bash y pisarla se la cambia a todo lo que se lance después.
CARCASA=resources/js/cajon/shell.js
LAYOUT=resources/views/components/layout.blade.php

TMP="$(mktemp -d)"
FICHEROS=("$CTRL" "$DECL" "$APP" "$GAINED" "$CARCASA" "$LAYOUT")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() {
    $SAIL node --test "resources/js/cajon/*.test.js" resources/js/sidebar/account/session-gained.test.js >/dev/null 2>&1 || return 1
    $SAIL php artisan test --filter='AccountDoorWiringTest|SidebarSeamTest|SidebarMountTest|ScrollLockOwnerTest' >/dev/null 2>&1
}

if ! verde; then
    echo '✗ las guardas de la apertura NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    total=$((total + 1))
    python3 -c 'import sys; p=sys.argv[1]; s=open(p,encoding="utf-8").read(); open(p,"w",encoding="utf-8").write(s.replace(sys.argv[2], sys.argv[3], 1))' \
        "$fichero" "$buscar" "$poner"
    if cmp -s "$fichero" "$TMP/$(basename "$fichero")"; then
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
    cp "$TMP/$(basename "$fichero")" "$fichero"; touch "$fichero"
}

# ── Las guardas RE-APUNTADAS, mutadas en su fichero nuevo ──────────────────────────────────────
mutar "la zona de la puerta deja de aplicarse donde convergen los dos caminos" "$CTRL" \
  "                this.applyAccountZone(this.spaHandle);
" ""

mutar "la zona de cuenta deja de CONSUMIRSE (cerrar y reabrir volvería siempre a ella)" "$CTRL" \
  "            this.accountZone = '';
" ""

mutar "el puente de la cabecera aplica la zona SIN esperar al motor" "$CTRL" \
  "            this.bootSpaEngine()?.then?.((handle) => this.applyAccountZone(handle));" \
  "            this.bootSpaEngine();"

mutar "cerrar vuelve a resetear el modo del panel (el segundo escritor)" "$CTRL" \
  "            this.isOpen = false;" \
  "            this.isOpen = false;
            this.mode = 'catalog';"

mutar "abrir deja de pedir la llave del cerrojo de scroll" "$CTRL" \
  "            scrollLock.lock('sidecart');
" ""

mutar "la fachada de intención pierde \`flushIntent\`" "$CTRL" \
  "        flushIntent() {" \
  "        vaciarIntencion() {"

# ── Lo que AÑADE la tanda ──────────────────────────────────────────────────────────────────────
mutar "abrir deja de anunciarse a la página (\`jw:cajon:open\`)" "$CTRL" \
  "            announce('open');
" ""

mutar "el cierre se anuncia DESPUÉS de decidir la recarga, sin decir que recarga" "$CTRL" \
  "announce('close', { reloading: this.authChanged });" \
  "announce('close', { reloading: false });"

mutar "un clic con Ctrl/⌘ o central se traga en vez de dejar pasar el \`href\`" "$DECL" \
  "event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey" \
  "event.defaultPrevented"

mutar "los abridores capturan el cajón en vez de pedirlo en cada clic" "$DECL" \
  "        const cajon = getCajon();
        if (! cajon) return; " \
  "        const cajon = (installDeclarativeOpeners.cache ??= getCajon());
        if (! cajon) return; "

mutar "el motor vuelve a avisar a Alpine en vez de al anfitrión" "$GAINED" \
  "    const host = cajonHost(win);" \
  "    const host = win?.Alpine?.store?.('purchase');"

mutar "el controlador deja de registrarse como \`\$store.purchase\` (la landing se queda muda)" "$APP" \
  "    window.Alpine.store('purchase', cajon);
" ""

mutar "\`window.JumpWeb.cajon\` se queda en el objeto crudo, sin proxy" "$APP" \
  "    window.JumpWeb.cajon = window.Alpine.store('purchase');
" ""

# ── T3a · la CARCASA con un solo dueño sin framework ───────────────────────────────────────────
mutar "la carcasa deja de escuchar el modo (el panel se queda en \`is-catalog\` para siempre, #118)" "$CARCASA" \
  "    doc.addEventListener('jw:cajon:mode', (event) => paintMode(event.detail?.mode));
" ""

mutar "\`setMode()\` deja de anunciar el modo" "$CTRL" \
  "            announce('mode', { mode: this.mode });
" ""

mutar "Escape vuelve a cerrar un cajón CERRADO" "$CARCASA" \
  "if (event.key === 'Escape' && getCajon()?.isOpen) close();" \
  "if (event.key === 'Escape') close();"

mutar "cerrar un cajón cerrado vuelve a anunciarse a la página" "$CTRL" \
  "if (wasOpen) announce('close'," \
  "announce('close',"

mutar "la trampa de foco vuelve a contar los controles con \`visibility: hidden\` (el foco se iba a las cookies)" "$CARCASA" \
  "    const canFocus = (el) => el.offsetParent !== null
        && (view?.getComputedStyle?.(el)?.visibility ?? 'visible') !== 'hidden';" \
  "    const canFocus = (el) => el.offsetParent !== null;"

mutar "el cajón que NACE abierto roba el foco (el anillo sobre la × al cargar: cambio visible sin decidir)" "$CARCASA" \
  "    if (cajon?.isOpen) paintOpen(true, { focus: false });" \
  "    if (cajon?.isOpen) paintOpen(true);"

mutar "el panel del layout vuelve a llevar un atributo de Alpine" "$LAYOUT" \
  "<aside class=\"sidecart__panel\" role=\"dialog\"" \
  "<aside class=\"sidecart__panel\" :class=\"'is-' + \$store.purchase.mode\" role=\"dialog\""

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
