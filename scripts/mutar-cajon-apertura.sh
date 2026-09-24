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
BOOT=resources/js/cajon/standalone.js
SIDEBAR=resources/js/sidebar/Sidebar.vue
SECCION=resources/js/sidebar/section.js

TMP="$(mktemp -d)"
FICHEROS=("$CTRL" "$DECL" "$APP" "$GAINED" "$CARCASA" "$LAYOUT" "$BOOT" "$SIDEBAR" "$SECCION")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() {
    $SAIL node --test "resources/js/cajon/*.test.js" resources/js/sidebar/account/session-gained.test.js \
        resources/js/sidebar/section.test.js >/dev/null 2>&1 || return 1
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
# ⚠️ Re-apuntado en la T3e·2 (24-09): desde la analítica el anuncio lleva su motivo, y desde la T3e·2 su
# superficie; el patrón viejo (`announce('open');`) llevaba caducado desde entonces sin que nadie lo leyera.
mutar "abrir deja de anunciarse a la página (\`jw:cajon:open\`)" "$CTRL" \
  "            announce('open', { reason: 'user', product: detail?.product, surface: this.surface });
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

mutar "el cajón que NACE abierto deja de meter el foco (\`#634\`: con teclado, el panel delante y el foco detrás)" "$CARCASA" \
  "    if (cajon?.isOpen && cajon.surface !== ISLA) paintOpen(true);" \
  "    if (cajon?.isOpen && cajon.surface !== ISLA) root.classList.add('is-open');"

mutar "el panel del layout vuelve a llevar un atributo de Alpine" "$LAYOUT" \
  "<aside class=\"sidecart__panel\" role=\"dialog\"" \
  "<aside class=\"sidecart__panel\" :class=\"'is-' + \$store.purchase.mode\" role=\"dialog\""

# ── T3b · el paquete CONSTRUYE la carcasa y lee su arranque ────────────────────────────────────
mutar "el controlador no toma el camino de la página ajena (se queda sin arranque y sin carcasa)" "$CTRL" \
  "                if (boot === null) {" \
  "                if (false) {"

mutar "la mitad privada viaja SIN la cookie de sesión (el titular no llega y el desenlace tampoco)" "$BOOT" \
  "        pedir('session', { credentials: 'same-origin' })" \
  "        pedir('session', {})"

mutar "con las dos lecturas caídas devuelve un arranque VACÍO en vez de \`null\`" "$BOOT" \
  "    if (shared === null && personal === null) return null;
" ""

mutar "\`locales\` viaja siempre (el layout solo lo manda con sesión: las dos fusiones divergen)" "$BOOT" \
  "        ...(locales.length ? { locales } : {})," \
  "        locales,"

mutar "la carcasa construida pierde el nombre accesible de su ×" "$BOOT" \
  "'aria-label': boot.account?.close ?? ''" \
  "'aria-label': ''"

mutar "la carcasa construida nace con el hueco de cuenta ABIERTO (una franja vacía que se lee como un fallo)" "$BOOT" \
  "nodo(doc, 'div', 'acct acct--pending', { id: 'sidecart-account' })" \
  "nodo(doc, 'div', 'acct', { id: 'sidecart-account' })"

mutar "el suelo de logout se construye A MEDIAS, sin token CSRF" "$BOOT" \
  "if (! boot.userId || ! accion || ! csrf) return null;" \
  "if (! boot.userId || ! accion) return null;"

mutar "instalar la carcasa deja de ser idempotente (dos oyentes por gesto en una página ajena)" "$CARCASA" \
  "    if (root.dataset.jwShell === 'on') return { root };
" ""

mutar "\`installShell\` cuelga una carcasa que no le han dado (y revienta, o cuelga nada)" "$CARCASA" \
  "        if (! construida) return null;
" ""

mutar "el motor deja de anunciar la compra confirmada" "$SIDEBAR" \
  "        host.purchased?.(publishedPurchase(active, confirmed, orderCode));
" ""

mutar "la regla del anuncio ignora la SECCIÓN (cuenta una compra mientras el cliente mira sus pedidos)" "$SECCION" \
  "    if (section !== SECTIONS.PURCHASE || ! purchaseConfirmed) return '';" \
  "    if (! purchaseConfirmed) return '';"

mutar "la compra confirmada se anuncia CADA vez que se repinta la pantalla" "$CTRL" \
  "            if (! code || code === this.purchasedCode) return;" \
  "            if (! code) return;"

mutar "el cajón que NACE abierto deja de arrancar el motor (el hueco VACÍO de \`#59(b)\`)" "$CTRL" \
  "            return this.bootSpaEngine();" \
  "            return null;"

# ⚠️ Re-apuntado en la T3e·2: desde la analítica, entre la llave y el arranque va el anuncio con su motivo.
mutar "el cajón que NACE abierto no pide la llave del cerrojo (la página de detrás sigue rodando)" "$CTRL" \
  "            this.surface = superficieDe(this.carcasaActual(), { cuenta: Boolean(this.accountZone) });
            scrollLock.lock('sidecart');" \
  "            this.surface = superficieDe(this.carcasaActual(), { cuenta: Boolean(this.accountZone) });"

# ── La carcasa elegible (T3e·2, `DECISIONES #682`) ─────────────────────────────────────────────
mutar "la carcasa del lateral se abre también para una compra en la ISLA" "$CARCASA" \
  "paintOpen(event.detail?.surface !== ISLA)" \
  "paintOpen(true)"

mutar "con la isla como carcasa, la cuenta deja de abrirse en el lateral" "$CTRL" \
  "            this.open({ cuenta: true });" \
  "            this.open();"

mutar "la fusión del arranque de una página ajena pierde la carcasa (la isla no se enciende nunca)" "$BOOT" \
  "        shell: shared.shell ?? 'cajon',
" ""

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
