#!/usr/bin/env bash
# Arnés de mutación del ARRANQUE DEL CAJÓN (F4 · T1, `docs/specs/cajon-empaquetable.md` §4.5).
#
# El arranque tiene dos transportes —el `data-boot` del layout y `GET /api/v1/sidebar/{boot,session}`— sobre UN
# modelo de lectura (`Http\Sidebar\SidebarBoot`). Lo que se rompe sin ruido: que los dos dejen de decir lo
# mismo, que la mitad pública deje de ser función de su URL (idioma) o empiece a saber quién mira, que la
# privada se cachee, que un campo cambie de tipo según haya sesión, o que el desenlace del pago deje de
# consumirse. Cada mutación quita una de esas y un test tiene que morir.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la
# mutación SE APLICÓ · restaurar por COPIA DE SEGURIDAD y no con `git checkout` (`#181`).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

SAIL="docker compose exec -u sail -T laravel.test"
TESTS="$SAIL php artisan test --filter=SidebarBootTest"

BOOT=app/Http/Sidebar/SidebarBoot.php
CTRL=app/Http/Controllers/Api/V1/SidebarBootController.php
ROUTES=routes/api.php
LAYOUT=resources/views/components/layout.blade.php

TMP="$(mktemp -d)"
FICHEROS=("$BOOT" "$CTRL" "$ROUTES" "$LAYOUT")
restaurar() { for f in "${FICHEROS[@]}"; do cp "$TMP/$(basename "$f")" "$f"; touch "$f"; done; $SAIL php artisan view:clear >/dev/null 2>&1; }
trap 'restaurar; rm -rf "$TMP"' EXIT
for f in "${FICHEROS[@]}"; do cp "$f" "$TMP/$(basename "$f")"; done

verde() { $SAIL php artisan view:clear >/dev/null 2>&1; $TESTS >/dev/null 2>&1; }

if ! verde; then
    echo '✗ los tests del arranque NO están verdes antes de mutar: el veredicto de abajo no valdría nada.' >&2
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

# ── Un modelo, dos transportes ─────────────────────────────────────────────────────────────────
mutar "el layout pinta algo que la API no sirve (una clave de más al fundir)" "$BOOT" \
  "            'auth' => \$shared['auth'],
            'userId' => \$personal['userId']," \
  "            'auth' => \$shared['auth'],
            'soloEnElLayout' => true,
            'userId' => \$personal['userId'],"

mutar "el layout deja de pintar el arranque del modelo" "$LAYOUT" \
  "json_encode(\\App\\Http\\Sidebar\\SidebarBoot::forCurrentRequest(), JSON_UNESCAPED_UNICODE)" \
  "json_encode([], JSON_UNESCAPED_UNICODE)"

# ── La mitad pública es función de su URL y no sabe quién mira ─────────────────────────────────
mutar "el idioma deja de venir de la URL (se queda el de la sesión o la cabecera)" "$CTRL" \
  "        app()->setLocale(\$data['lang']);
" ""

mutar "la mitad pública se entera de quién mira" "$BOOT" \
  "            'auth' => __('auth'),
            // ⚠️ **Las rutas las compone el SERVIDOR" \
  "            'auth' => __('auth') + ['quien' => auth()->user()?->name],
            // ⚠️ **Las rutas las compone el SERVIDOR"

mutar "la mitad pública deja de ser cacheable (sin \`cache.headers\`)" "$ROUTES" \
  "        ->middleware('cache.headers:public;max_age=300;etag')
" ""

# ── La mitad privada ───────────────────────────────────────────────────────────────────────────
mutar "la mitad privada se puede cachear (sin \`no-store\`)" "$ROUTES" \
  "        ->middleware('no-store')
        ->name('sidebar.session');" \
  "        ->name('sidebar.session');"

mutar "un grupo vacío vuelve a salir como lista (el campo cambia de tipo con la sesión)" "$CTRL" \
  "        \$personal['account'] = (object) \$personal['account'];
" ""

mutar "leer el desenlace del pago deja de consumirlo" "$BOOT" \
  "        \$entry = SidebarEntry::consume();" \
  "        \$entry = SidebarEntry::peek();"

echo
echo "mutaciones que muerden: ${muerden}/${total}"
if [ "$muerden" -eq "$total" ]; then
    exit 0
fi
exit 1
