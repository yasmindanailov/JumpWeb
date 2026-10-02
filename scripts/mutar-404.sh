#!/usr/bin/env bash
# Arnés de mutación de LA 404 DE LA INSTANCIA (la L4 de `#876`, `isla-y-landing-nueva.md` §4.28): `InstancePagesTest` (sus
# dos casos de la 404) y los dos 405 que el comodín no puede tapar (`RedsysNotificationEndpointTest`,
# `GoogleBusinessDisconnectTest`) contra el pintor (`InstanceNotFound`), su registro (`bootstrap/app.php`), el comodín
# (`routes/web.php`) y lo que una página puede ocupar (`InstancePages::OCUPABLES`).
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y
# que su ancla está UNA vez · restaurar por COPIA DE SEGURIDAD (por RUTA) y `touch`, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=InstancePagesTest|RedsysNotificationEndpointTest|GoogleBusinessDisconnectTest"

PINTOR=app/Http/Instancia/InstanceNotFound.php
ARRANQUE=bootstrap/app.php
RUTAS=routes/web.php
PAGINAS=app/Http/Instancia/InstancePages.php
FICHEROS=("$PINTOR" "$ARRANQUE" "$RUTAS" "$PAGINAS")

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

mutar "el pintor no se registra (la 404 de siempre)" "$ARRANQUE" \
  "fn (NotFoundHttpException \$e, Request \$request) => app(InstanceNotFound::class)->render(\$request)" \
  "fn (NotFoundHttpException \$e, Request \$request) => null"

mutar "sin comodín (lo desconocido sale sin el grupo web)" "$RUTAS" \
  "Route::fallback([InstanceNotFound::class, 'comodin'])->where('fallbackPlaceholder', '(?!'.preg_quote(ApiSurface::PREFIX, '#').'(?:/|\$))[^.]*');" \
  ""

mutar "el comodín casa con la API (un POST que no existe, 405)" "$RUTAS" \
  "'(?!'.preg_quote(ApiSurface::PREFIX, '#').'(?:/|\$))[^.]*'" \
  "'[^.]*'"

mutar "el comodín tapa los 405 (un GET a lo que solo es POST)" "$PINTOR" \
  "            throw new MethodNotAllowedHttpException(\$otros);" \
  "            abort(404);"

mutar "el panel, con la 404 de la web" "$PINTOR" \
  "            && ! PanelPath::matches(\$request)" \
  ""

mutar "lo que lleva extensión, con la página entera" "$PINTOR" \
  "            && pathinfo(\$request->path(), PATHINFO_EXTENSION) === '';" \
  "            && true;"

mutar "quien pide JSON, con la página" "$PINTOR" \
  "            && ! \$request->expectsJson()" \
  ""

mutar "un POST, con la página" "$PINTOR" \
  "        return in_array(\$request->getMethod(), ['GET', 'HEAD'], true)" \
  "        return true"

mutar "una 404 rota del paquete se vuelve un 500" "$PINTOR" \
  "        } catch (Throwable \$e) {" \
  "        } catch (\\LogicException \$e) {"

mutar "la 404 sale con 200 (un falso 200 para Google)" "$PINTOR" \
  "        return response(\$html, 404);" \
  "        return response(\$html, 200);"

mutar "la 404 se indexa" "$PINTOR" \
  "\$request->url(), noindex: true)" \
  "\$request->url(), noindex: false)"

mutar "la 404 no es algo que una página pueda ocupar" "$PAGINAS" \
  "'legal', '404'];" \
  "'legal'];"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
