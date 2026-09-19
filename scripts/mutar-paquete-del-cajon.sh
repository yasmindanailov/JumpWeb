#!/usr/bin/env bash
# Arnés de mutación del PAQUETE DEL CAJÓN (F4 · T5, `docs/specs/cajon-empaquetable.md` §4.1).
#
# Lo que vigilan las guardas de esta tanda: que la ruta estable REDIRIJA al cargador construido —y no sirva
# sus bytes, que es el fallo silencioso: el cargador se ejecutaría y el cajón moriría al pedir su motor por
# una ruta relativa que desde ahí no existe—, que apunte al cargador y no a la entrada del producto, que sin
# `npm run build` lo diga, y que el cargador siga siendo un cargador y no se traiga la landing o el motor
# dentro.
#
# ⚠️ Las dos últimas mutaciones tocan el JS fuente, así que este arnés CONSTRUYE antes de cada veredicto: un
# presupuesto se mide sobre `public/build`, y sin reconstruir la mutación no llega a existir.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter='PaqueteDelCajonTest|SidebarBundleBudgetTest'"

CTRL=app/Http/Controllers/PaqueteDelCajonController.php
CARGADOR=resources/js/cajon/paquete.js
PAQUETE_INDEX=resources/js/cajon/index.js

TMP="$(mktemp -d)"
FICHEROS=("$CTRL" "$CARGADOR" "$PAQUETE_INDEX")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; $SAIL npm run build >/dev/null 2>&1; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

construir() { $SAIL npm run build >/dev/null 2>&1; }
verde() { construir; eval "$TESTS" >/dev/null 2>&1; }

if ! verde; then
    echo '✗ las guardas del paquete NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
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

# ── La ruta estable ────────────────────────────────────────────────────────────────────────────
mutar "la ruta SIRVE los bytes en vez de redirigir (el cargador corre y el cajón muere al pedir su motor)" \
  "$CTRL" "return redirect(asset('build/'.\$fichero), 302);" \
  "return response(file_get_contents(public_path('build/'.\$fichero)), 200)->header('Content-Type', 'application/javascript');"

mutar "la ruta apunta a la entrada del PRODUCTO (funciona… descargando la landing entera)" \
  "$CTRL" "private const ENTRADA = 'resources/js/cajon/paquete.js';" \
  "private const ENTRADA = 'resources/js/app.js';"

mutar "sin construir, un 404 mudo en vez del 503 que se explica" \
  "$CTRL" "            503," "            404,"

# ── El cargador sigue siendo un cargador ───────────────────────────────────────────────────────
mutar "el cargador se trae el MOTOR de forma estática (Vue y Pinia dejan de ser diferidos)" \
  "$CARGADOR" "import { installCajon } from './index.js';" \
  "import { installCajon } from './index.js';
import '../sidebar/index.js';"

# ⚠️ Ésta es la que vigila el arreglo de la guarda «el motor es un chunk aparte»: desde la T5 son DOS las
# entradas que montan el cajón, así que Rollup lo sacó a un chunk compartido y el `import()` del motor ya no
# lo declara `app.js` sino ese chunk. La guarda pasó a recorrer el grafo estático; si alguien la devolviera a
# mirar solo `app.js`, este caso seguiría mordiendo y el suyo no.
mutar "el motor deja de ser diferido: el cajón se lo lleva estático (y viaja con toda página pública)" \
  "$PAQUETE_INDEX" "import { installShell } from './shell.js';" \
  "import { installShell } from './shell.js';
import '../sidebar/index.js';"

echo
echo "mutaciones: $muerden/$total muerden"
[ "$muerden" -eq "$total" ]
