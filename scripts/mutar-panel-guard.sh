#!/usr/bin/env bash
# Arnés de mutación de EL PANEL SOLO CONFÍA EN SU PROPIO INICIO DE SESIÓN (P1 de `docs/specs/panel-a-salvo.md`,
# `DECISIONES #850`): `tests/Feature/Admin/PanelOwnGuardTest.php` contra el guard del panel, las trece rutas del personal
# de `routes/web.php`, el paso libre del mantenimiento (middleware y aviso del layout) y el código del panel que nombrara
# el guard de la web.
#
# Reglas de la casa dentro: verde antes de mutar · veredicto por código de salida · comprobar que la mutación SE APLICÓ y
# que su ancla está UNA vez · restaurar por COPIA DE SEGURIDAD (por RUTA) y `touch` —Blade compara fechas: una vista
# compilada con la mutación podría ganar—, nunca con `git checkout`.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

RUN="docker compose exec -u sail -T laravel.test php artisan test --filter=PanelOwnGuardTest"

PANEL=app/Providers/Filament/AdminPanelProvider.php
RUTAS=routes/web.php
MANT=app/Http/Middleware/EnsureSiteAvailable.php
LAYOUT=resources/views/components/layout.blade.php
IDIOMA=app/Http/Controllers/Admin/PanelLocaleController.php
FICHEROS=("$PANEL" "$RUTAS" "$MANT" "$LAYOUT" "$IDIOMA")

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

mutar "el panel vuelve al guard de la web" "$PANEL" \
  "->authGuard('admin')" \
  "->authGuard('web')"

mutar "una ruta del personal (el calendario) vuelve a la sesión de la web" "$RUTAS" \
  "['web', 'auth:admin', 'panel_role', 'panel_mfa', SetAdminLocale::class, 'no-store']" \
  "['web', 'auth', 'panel_role', 'panel_mfa', SetAdminLocale::class, 'no-store']"

mutar "el paso libre del mantenimiento lee la sesión de la web" "$MANT" \
  "\$user = \$request->user('admin');" \
  "\$user = \$request->user();"

mutar "el aviso del mantenimiento lee la sesión de la web" "$LAYOUT" \
  "@if (auth('admin')->check() && (auth('admin')->user()->hasRole('admin') || auth('admin')->user()->hasRole('staff'))" \
  "@if (auth()->check() && (auth()->user()->hasRole('admin') || auth()->user()->hasRole('staff'))"

mutar "código del panel que lee la sesión de la web" "$IDIOMA" \
  "\$user = \$request->user();" \
  "\$user = auth('web')->user();"

echo "$muerden/$total muerden"
[[ $muerden -eq $total ]]
