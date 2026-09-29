#!/usr/bin/env bash
# Arnés de mutación de LAS RUTAS VIEJAS QUE UNA PÁGINA SUSTITUYE (`#843`, T6f·1 de `specs/isla-y-landing-nueva.md` §4.22):
# una página del paquete declara `'sustituye' => [...]` y esas rutas del producto responden 301 a su URL, con su `?query`,
# antes del controlador; solo las de `InstancePages::SUSTITUIBLES` y una página por ruta; nunca a una URL que no existe; el
# sitemap las deja fuera; cada ruta de la lista lleva el middleware; y `/entradas` —que se queda— tiene la portada como
# canónica. La guarda es `InstancePagesTest`.
#
# Reglas de la casa dentro (`/mutar`): verde antes de mutar · veredicto por código de salida · ancla ÚNICA · comprobar
# que la mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD (por ruta entera) y `touch`, nunca `git checkout`.
#
#   bash scripts/mutar-sustituye.sh        (desde el host, con el contenedor en marcha)
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

PHP="docker compose exec -u sail -T laravel.test php artisan test --filter=InstancePagesTest"

MW=app/Http/Middleware/RedirectToInstancePage.php
IP=app/Http/Instancia/InstancePages.php
SM=app/Http/Controllers/SitemapController.php
RW=routes/web.php
PG=resources/views/components/pagina.blade.php

TMP="$(mktemp -d)"
FICHEROS=("$MW" "$IP" "$SM" "$RW" "$PG")
copia() { echo "$TMP/$(echo "$1" | tr '/' '_')"; }
restaurar() { for f in "${FICHEROS[@]}"; do cp "$(copia "$f")" "$f"; touch "$f"; done; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$(copia "$f")"; done

# La salida de cada corrida, para decir QUÉ prueba cae: que muerda por la razón que la mutación rompe.
SALIDA="$TMP/salida.txt"
verde() { $PHP >"$SALIDA" 2>&1; }

if ! verde; then
    echo '✗ la base NO está verde antes de mutar: el veredicto de abajo no valdría nada.' >&2
    exit 1
fi
echo '✓ base verde'

muerden=0; total=0

mutar() {
    local nombre="$1" fichero="$2" buscar="$3" poner="$4"
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
        echo "  ⚠ «$nombre» NO SE APLICÓ (el patrón no casa): el veredicto no vale"
        return
    fi
    touch "$fichero"
    if verde; then
        echo "  ✗ NO muerde: $nombre"
    else
        echo "  ✓ muerde:    $nombre"
        grep -E '⨯|✗|FAILED' "$SALIDA" | grep -v 'Tests:' | head -3 | sed 's/^/                 /'
        muerden=$((muerden + 1))
    fi
    cp "$(copia "$fichero")" "$fichero"; touch "$fichero"
}

# ── El 301 ────────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "el middleware nunca redirige" "$MW" "if (\$destino === null) {" "if (true) {"
mutar "el 301 pierde la consulta (y las utm_ de la campaña)" "$MW" \
  "return redirect()->to(\$consulta === null ? \$destino : \$destino.'?'.\$consulta, 301);" \
  "return redirect()->to(\$destino, 301);"
mutar "un 302 en vez de un 301 (Google no traslada lo indexado)" "$MW" \
  "\$destino.'?'.\$consulta, 301);" \
  "\$destino.'?'.\$consulta, 302);"
mutar "una ruta de la lista pierde el middleware (/bar)" "$RW" \
  "Route::get('/bar', BarController::class)->middleware(RedirectToInstancePage::class)->name('bar');" \
  "Route::get('/bar', BarController::class)->name('bar');"
mutar "entradas entra en la lista de lo que se suelta" "$IP" \
  "public const SUSTITUIBLES = ['precios', 'atracciones', 'servicios', 'bar', 'contacto'];" \
  "public const SUSTITUIBLES = ['precios', 'atracciones', 'servicios', 'bar', 'contacto', 'entradas'];"

# ── El destino ────────────────────────────────────────────────────────────────────────────────────────────────────
mutar "el destino ignora la ruta que ocupa la página (la portada no es su slug)" "$IP" \
  "\$suya = \$pagina->ocupa ?? \$pagina->ruta();" \
  "\$suya = \$pagina->ruta();"
mutar "redirige aunque la página no tenga ruta (a un 404)" "$IP" \
  "return Route::has(\$suya) ? route(\$suya) : null;" \
  "return Route::has(\$suya) ? route(\$suya) : url('/'.\$pagina->slug);"

# ── La declaración ────────────────────────────────────────────────────────────────────────────────────────────────
mutar "dos páginas pueden sustituir la misma ruta" "$IP" \
  "if ((\$repetidas = array_values(array_intersect(\$pagina->sustituye, array_keys(\$sustituidas)))) !== []) {" \
  "if (false) {"
mutar "se acepta sustituir cualquier ruta del producto" "$IP" \
  "! is_string(\$r) || ! in_array(\$r, self::SUSTITUIBLES, true)" \
  "! is_string(\$r)"

# ── El sitemap y la canónica ──────────────────────────────────────────────────────────────────────────────────────
mutar "el sitemap sigue anunciando lo que redirige" "$SM" \
  "\$entries = array_values(array_filter(\$entries, fn (array \$e): bool => \$paginas->redireccionDe(\$e[0]) === null));" \
  ""
mutar "/entradas vuelve a ser canónica de sí misma" "$PG" \
  "request()->routeIs('entradas') ? route('home') : url()->current();" \
  "url()->current();"
mutar "toda página se declara canónica de la portada" "$PG" \
  "request()->routeIs('entradas') ? route('home') : url()->current();" \
  "route('home');"

echo
echo "$muerden de $total mutantes muertos"
[[ "$muerden" == "$total" ]]
