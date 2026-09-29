#!/usr/bin/env bash
# Arnés de mutación de LA WEB ENTERA (T6f·4 de `specs/isla-y-landing-nueva.md` §4.22, `#843`): la guarda es
# `scripts/sonda-web.mjs` —los 301 de las rutas viejas, el sitemap, cada página en es/en/fr y los enlaces internos—, y cada
# mutación es un DEFECTO de verdad, en el producto o en el paquete: el 301 que pierde la consulta o pasa a 302, el sitemap
# que anuncia lo que redirige, la canónica, `/entradas` que ya no abre la compra, un enlace con un salto de más, una clave
# sin traducir, dos `h1`, un desborde y un enlace roto. (Quitar un `'sustituye'` NO es un defecto: la sonda sigue a la
# declaración, que es del cliente.)
#
# ⚠️ Muta el PRODUCTO y la INSTANCIA (`../instancias/playjump`, montada en el contenedor). Se ESPERA 3 s tras mutar y tras
# restaurar: opcache revalida cada 2 s (la trampa pagada en `mutar-sonda-visitanos.sh`). Cada corrida de la sonda tarda
# ~1,5 min: el arnés entero, ~20.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ ·
# restaurar por COPIA DE SEGURIDAD (por RUTA, nunca por `basename`) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

ANCHO="${1:-390}"
INSTANCIA="${INSTANCIA:-../instancias/playjump}"
RUN="docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test node scripts/sonda-web.mjs ${ANCHO}"

MW=app/Http/Middleware/RedirectToInstancePage.php
SM=app/Http/Controllers/SitemapController.php
PG=resources/views/components/pagina.blade.php
BC=resources/views/components/site/body-compra.blade.php
P7="$INSTANCIA/web/inicio/pieza-7.blade.php"
V2="$INSTANCIA/web/visitanos/pieza-2.blade.php"
VB="$INSTANCIA/web/visitanos.blade.php"
PIE="$INSTANCIA/web/components/site-footer.blade.php"
FICHEROS=("$MW" "$SM" "$PG" "$BC" "$P7" "$V2" "$VB" "$PIE")

TMP="$(mktemp -d)"
copia() { echo "$TMP/${1//\//__}"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; sleep 3; }
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done
trap 'restaurar; rm -rf "$TMP"' EXIT

SALIDA="$TMP/salida.txt"
verde() { $RUN >"$SALIDA" 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo "✓ base verde (sonda-web a ${ANCHO})"

muerden=0; total=0
SOLO="${SOLO:-}"

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
    if [[ -n "$SOLO" && "$nombre" != *"$SOLO"* ]]; then return; fi
    total=$((total + 1))
    if [[ "$(grep -cF -- "$buscar" "$fichero")" != 1 ]]; then
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
    sleep 3
    if verde; then
        echo "  ✗ NO muerde: $nombre"
        if [[ -n "$SOLO" ]]; then sed 's/^/                 /' "$SALIDA"; fi
    else
        echo "  ✓ muerde:    $nombre"
        grep '^✗' "$SALIDA" | head -3 | sed 's/^/                 /'
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
    sleep 3
}

# ── Los 301 y el sitemap (producto) ───────────────────────────────────────────────────────────────────────────────
mutar "el 301 pierde la consulta" "$MW" \
  "return redirect()->to(\$consulta === null ? \$destino : \$destino.'?'.\$consulta, 301);" \
  "return redirect()->to(\$destino, 301);"
mutar "un 302 en vez de un 301" "$MW" \
  "\$destino.'?'.\$consulta, 301);" \
  "\$destino.'?'.\$consulta, 302);"
mutar "el sitemap anuncia lo que redirige" "$SM" \
  "\$entries = array_values(array_filter(\$entries, fn (array \$e): bool => \$paginas->redireccionDe(\$e[0]) === null));" \
  ""

# ── La canónica y /entradas (producto) ────────────────────────────────────────────────────────────────────────────
mutar "toda página, canónica de la portada" "$PG" \
  "request()->routeIs('entradas') ? route('home') : url()->current();" \
  "route('home');"
mutar "/entradas ya no abre la compra" "$BC" \
  "request()->routeIs('entradas') ||" \
  "false ||"

# ── Las páginas (paquete) ─────────────────────────────────────────────────────────────────────────────────────────
mutar "la portada vuelve a enlazar /contacto (un salto de más)" "$P7" \
  ":href=\"\\Illuminate\\Support\\Facades\\Route::has('instancia.visitanos') ? route('instancia.visitanos') : route('contacto')\"" \
  ":href=\"route('contacto')\""
mutar "una clave sin traducir en Visítanos" "$V2" \
  ":title=\"\$p['p2']['titular']\"" \
  ":title=\"__('instancia::paginas.visitanos.no_existe')\""
mutar "Visítanos con dos h1" "$VB" \
  "<div data-jw-isla></div>" \
  "<div data-jw-isla></div><h1>Otra</h1>"
mutar "Visítanos se sale de ancho" "$VB" \
  "<div data-jw-isla></div>" \
  "<div data-jw-isla></div><div style=\"width: 1500px; height: 1px\"></div>"
mutar "el pie enlaza a una página que no existe" "$PIE" \
  "<a href=\"{{ \$brand['href'] ?? '#' }}\"" \
  "<a href=\"/no-existe\""

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
